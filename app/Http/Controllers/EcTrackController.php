<?php

namespace App\Http\Controllers;

use App\Models\EcTrack;
use App\Providers\EcTrackServiceProvider;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EcTrackController extends Controller {
    /**
     * Return EcTrack JSON.
     *
     * @param Request $request
     * @param int     $id
     * @param array   $headers
     *
     * @return JsonResponse
     */
    public function getGeojson(Request $request, int $id, array $headers = []): JsonResponse {
        $track = EcTrack::find($id);

        if (is_null($track))
            return response()->json(['code' => 404, 'error' => "Not Found"], 404);

        return response()->json($track->getGeojson(), 200, $headers);
    }

    public static function getNeighbourEcMedia(int $idTrack): JsonResponse {
        $track = EcTrack::find($idTrack);
        if (is_null($track))
            return response()->json(['error' => 'Track not found'], 404);
        else
            return response()->json($track->getNeighbourEcMedia());
    }

    public static function getNeighbourEcPoi(int $idTrack): JsonResponse {
        $track = EcTrack::find($idTrack);
        if (is_null($track))
            return response()->json(['error' => 'Track not found'], 404);
        else
            return response()->json($track->getNeighbourEcPoi());
    }

    public static function getAssociatedEcMedia(int $idTrack): JsonResponse {
        $track = EcTrack::find($idTrack);
        if (is_null($track))
            return response()->json(['error' => 'Track not found'], 404);
        $result = [
            'type' => 'FeatureCollection',
            'features' => []
        ];
        foreach ($track->ecMedia as $media) {
            $result['features'][] = $media->getGeojson();
        }

        return response()->json($result);
    }

    public static function getAssociatedEcPois(int $idTrack): JsonResponse {
        $track = EcTrack::find($idTrack);
        if (is_null($track))
            return response()->json(['error' => 'Track not found'], 404);

        $result = [
            'type' => 'FeatureCollection',
            'features' => []
        ];
        foreach ($track->ecPois as $poi) {
            $result['features'][] = $poi->getGeojson();
        }

        return response()->json($result);
    }

    public static function getFeatureImage(int $idTrack): JsonResponse {
        $track = EcTrack::find($idTrack);
        if (is_null($track))
            return response()->json(['error' => 'Track not found'], 404);
        else
            return response()->json($track->featureImage()->get());
    }

    /**
     * Update the ec track with new data from Geomixer
     *
     * @param Request $request the request with data from geomixer POST
     * @param int     $id      the id of the EcTrack
     */
    public function updateComputedData(Request $request, int $id): JsonResponse {
        $ecTrack = EcTrack::find($id);
        if (is_null($ecTrack)) {
            return response()->json(['code' => 404, 'error' => "Not Found"], 404);
        }

        if (!empty($request->where_ids)) {
            $ecTrack->taxonomyWheres()->sync($request->where_ids);
        }

        if (!empty($request->duration)) {
            foreach ($request->duration as $activityIdentifier => $values) {
                $tax = $ecTrack->taxonomyActivities()->where('identifier', $activityIdentifier)->pluck('id')->first();
                $ecTrack->taxonomyActivities()->syncWithPivotValues([$tax], ['duration_forward' => $values['forward'], 'duration_backward' => $values['backward']], false);
            }
        }

        if (
            !is_null($request->geometry)
            && is_array($request->geometry)
            && isset($request->geometry['type'])
            && isset($request->geometry['coordinates'])
        ) {
            $ecTrack->geometry = DB::raw("public.ST_GeomFromGeojson('" . json_encode($request->geometry) . "')");
        }

        $fields = [
            'distance_comp',
            'distance',
            'ele_min',
            'ele_max',
            'ele_from',
            'ele_to',
            'ascent',
            'descent',
            'duration_forward',
            'duration_backward',
        ];

        foreach ($fields as $field) {
            if (isset($request->$field)) {
                $ecTrack->$field = $request->$field;
            } else $ecTrack->$field = null;
        }

        $ecTrack->skip_update = true;
        $ecTrack->save();

        return response()->json();
    }

    /**
     * Search the ec tracks using the GET parameters
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse {
        $featureCollection = [
            "type" => "FeatureCollection",
            "features" => []
        ];

        $bboxParam = $request->get('bbox');
        if (isset($bboxParam)) {
            try {
                $bbox = explode(',', $bboxParam);
                $bbox = array_map('floatval', $bbox);
            } catch (Exception $e) {
                Log::warning($e->getMessage());
            }

            if (isset($bbox) && is_array($bbox)) {
                $trackRef = $request->get('reference_id');
                if (isset($trackRef) && strval(intval($trackRef)) === $trackRef) $trackRef = intval($trackRef);
                else $trackRef = null;

                $searchString = $request->get('string');
                $featureCollection = EcTrackServiceProvider::getSearchClustersInsideBBox($bbox, $trackRef, $searchString, 'en');
            }
        }

        return response()->json($featureCollection);
    }

    /**
     * Get the closest ec track to the given location
     *
     * @param Request $request
     * @param string  $lon
     * @param string  $lat
     *
     * @return JsonResponse
     */
    public function nearestToLocation(Request $request, string $lon, string $lat): JsonResponse {
        $featureCollection = [
            "type" => "FeatureCollection",
            "features" => []
        ];
        if ($lon === strval(floatval($lon)) && $lat === strval(floatval($lat))) {
            $lon = floatval($lon);
            $lat = floatval($lat);
            $featureCollection = EcTrackServiceProvider::getNearestToLonLat($lon, $lat);
        }

        return response()->json($featureCollection);
    }

    /**
     * Get the most viewed ec tracks
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function mostViewed(Request $request): JsonResponse {
        //        $featureCollection = [
        //            "type" => "FeatureCollection",
        //            "features" => []
        //        ];

        $featureCollection = EcTrackServiceProvider::getMostViewed();

        return response()->json($featureCollection);
    }

    /**
     * Get the most viewed ec tracks
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function multiple(Request $request): JsonResponse {
        $featureCollection = [
            "type" => "FeatureCollection",
            "features" => []
        ];

        try {
            $ids = $request->get('ids');
            $ids = explode(',', $ids ?? void);
        } catch (Exception $e) {
        }

        if (isset($ids) && is_array($ids)) {
            $ids = array_slice($ids, 0, 3);
            $ids = array_values(array_unique($ids));
            foreach ($ids as $id) {
                if ($id === strval(intval($id))) {
                    $track = EcTrack::find($id);
                    if (isset($track))
                        $featureCollection["features"][] = $track->getGeojson();
                }
            }
        }

        return response()->json($featureCollection);
    }
}
