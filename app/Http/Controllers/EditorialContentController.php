<?php

namespace App\Http\Controllers;

use App\Models\App;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\EcMedia;
use App\Models\EcPoi;
use App\Models\EcTrack;
use App\Traits\GeometryFeatureTrait;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EditorialContentController extends Controller
{

    use GeometryFeatureTrait;

    /**
     * Calculate the model class name of a ugc from its type
     *
     * @param string $type the ugc type
     *
     * @return string the model class name
     *
     * @throws Exception
     */
    private function _getEcModelFromType(string $type): string
    {
        switch ($type) {
            case 'poi':
                $model = "\App\Models\EcPoi";
                break;
            case 'track':
                $model = "\App\Models\EcTrack";
                break;
            case 'media':
                $model = "\App\Models\EcMedia";
                break;
            default:
                throw new Exception("Invalid type ' . $type . '. Available types: poi, track, media");
        }

        return $model;
    }

    /**
     * Get Ec info by ID
     *
     * @param int $id the Ec id
     *
     * @return JsonResponse return the Ec info
     */
    public function getEcjson(int $id): JsonResponse
    {
        $apiUrl = explode("/", request()->path());
        try {
            $model = $this->_getEcModelFromType($apiUrl[2]);
        } catch (Exception $e) {
            return response()->json(['code' => 400, 'error' => $e->getMessage()], 400);
        }

        $ec = $model::find($id);
        if (is_null($ec))
            return response()->json(['code' => 404, 'error' => "Not Found"], 404);
        $ec = $ec->getGeojson();
        return response()->json($ec);
    }

    /**
     * Get Ec info by ID
     *
     * @param int $id the Ec id
     *
     * @return JsonResponse return the Ec info
     */
    public function getEcGeoJson(int $id): JsonResponse
    {
        $apiUrl = explode("/", request()->path());
        try {
            $model = $this->_getEcModelFromType($apiUrl[2]);
        } catch (Exception $e) {
            return response()->json(['code' => 400, 'error' => $e->getMessage()], 400);
        }

        $ec = $model::find($id);
        if (is_null($ec)) {
            return response()->json(['code' => 404, 'error' => "Not Found"], 404);
        }

        return response()->json($ec->getGeojson());
    }

    /**
     * Controller for API api/app/elbrus/{app_id}/geojson/ec_poi_{poi_id}.geojson
     *
     * @param int $app_id
     * @param int $poi_id
     * @return JsonResponse
     */
    public function getElbrusPoiGeojson(int $app_id, int $poi_id): JsonResponse {
        $app = App::find($app_id);
        $poi = EcPoi::find($poi_id);
        if (is_null($app) || is_null($poi)) {
            return response()->json(['code'=>404,'error'=>'Not found'],404);
        }
        $geojson=$poi->getGeojson();
        // MAPPING
        $geojson = $this->_mapGeojsonPropertyForElbrusApi($geojson,'contact_phone');
        $geojson = $this->_mapGeojsonPropertyForElbrusApi($geojson,'contact_email');
        return response()->json($geojson,200);
    }

    /**
     * Controller for API api/app/elbrus/{app_id}/geojson/ec_track_{poi_id}.geojson
     *
     * @param int $app_id
     * @param int $poi_id
     * @return JsonResponse
     */
    public function getElbrusTrackGeojson(int $app_id, int $track_id): JsonResponse {
        $app = App::find($app_id);
        $track = EcTrack::find($track_id);
        if (is_null($app) || is_null($track)) {
            return response()->json(['code'=>404,'error'=>'Not found'],404);
        }
        $geojson=$track->getGeojson();
        // MAPPING COLON
        $fields = [
            'ele_from','ele_to','ele_max', 'ele_min', 'duration_forward', 'duration_backward'
        ];
        foreach ($fields as $field) {
            $geojson = $this->_mapGeojsonPropertyForElbrusApi($geojson,$field);
        }
        return response()->json($geojson,200);
    }

    /**
     * Convert $geojson['properties']['example_nocolon'] to
     * $geojson['properties']['example:colon']. If parameter $field_with_colon is left null
     * then is derived from $filed using the rule "_" -> ":"
     *
     * @param $geojson
     * @param $field
     * @param null $field_with_colon
     */
    private function _mapGeojsonPropertyForElbrusApi($geojson,$field,$field_with_colon=null) {
        if(isset($geojson['properties'][$field])) {
            if(is_null($field_with_colon)) {
                $field_with_colon = preg_replace('/_/',':',$field);
            }
            $geojson['properties'][$field_with_colon]=$geojson['properties'][$field];
        }
        return $geojson;
    }

    /**
     * Get Ec image by ID
     *
     * @param int $id the Ec id
     *
     * @return JsonResponse return the Ec Image
     */
    public function getEcImage(int $id)
    {
        $apiUrl = explode("/", request()->path());
        try {
            $model = $this->_getEcModelFromType($apiUrl[2]);
        } catch (Exception $e) {
            return response()->json(['code' => 400, 'error' => $e->getMessage()], 400);
        }

        $ec = $model::find($id);
        if (is_null($ec))
            return response()->json(['code' => 404, 'error' => "Not Found"], 404);

        $headers = array();
        $imagePath = public_path() . '/storage/' . $ec->url;

        return Storage::disk('public')->download($ec->url, 'name' . '.jpg');
    }

    /** Update the ec media with new data from Geomixer
     *
     * @param Request $request the request with data from geomixer POST
     * @param int $id the id of the EcMedia
     */
    public function updateEcMedia(Request $request, $id)
    {
        $ecMedia = EcMedia::find($id);

        if (is_null($ecMedia))
            return response()->json(['code' => 404, 'error' => "Not Found"], 404);
        $actualUrl = $ecMedia->url;
        if (is_null($request->url))
            return response()->json(['code' => 400, 'error' => "Missing mandatory parameter: URL"], 400);
        $ecMedia->url = $request->url;

        if (
            !is_null($request->geometry)
            && is_array($request->geometry)
            && isset($request->geometry['type'])
            && isset($request->geometry['coordinates'])
        ) {
            $ecMedia->geometry = DB::raw("public.ST_Force2D(public.ST_GeomFromGeojson('" . json_encode($request->geometry) . "'))");
        }

        if (!empty($request->where_ids)) {
            $ecMedia->taxonomyWheres()->sync($request->where_ids);
        }

        if (!empty($request->thumbnail_urls)) {
            $ecMedia->thumbnails = $request->thumbnail_urls;
        }

        $ecMedia->save();

        try {
            $headers = get_headers($request->url);

            if (stripos($headers[0], "200 OK") >= 0)
                Storage::disk('public')->delete($actualUrl);
        } catch (Exception $e) {
            Log::warning($e->getMessage());
        }
    }

    /** Update the ec track with new data from Geomixer
     *
     * @param Request $request the request with data from geomixer POST
     * @param int $id the id of the EcTrack
     */
    public function updateEcTrack(Request $request, $id)
    {
        $ecTrack = EcTrack::find($id);
        if (is_null($ecTrack))
            return response()->json(['code' => 404, 'error' => "Not Found"], 404);
        if (!empty($request->where_ids)) {
            $ecTrack->taxonomyWheres()->sync($request->where_ids);
        }
        $ecTrack->distance_comp = $request->distance_comp;
        $ecTrack->save();
    }

    /** Update the ec media with new data from Geomixer
     *
     * @param Request $request the request with data from geomixer POST
     * @param int $id the id of the EcMedia
     */
    public function updateEcPoi(Request $request, $id)
    {
        $ecPoi = EcPoi::find($id);

        if (is_null($ecPoi)) {
            return response()->json(['code' => 404, 'error' => "Not Found"], 404);
        }

        if (
            !is_null($request->geometry)
            && is_array($request->geometry)
            && isset($request->geometry['type'])
            && isset($request->geometry['coordinates'])
        ) {
            $ecPoi->geometry = DB::raw("public.ST_Force2D(public.ST_GeomFromGeojson('" . json_encode($request->geometry) . "'))");
        }

        if (!empty($request->where_ids)) {
            $ecPoi->taxonomyWheres()->sync($request->where_ids);
        }

        $ecPoi->save();
    }

    /**
     * Return geometry formatted by $format.
     * 
     * @param Request $request the request with data from geomixer POST
     * @param int $id
     * @param string $format
     * 
     * @return Response
     */
    public function download(Request $request, int $id, string $format = 'geojson')
    {
        $ecPoi = EcPoi::find($id);

        $response = response()->json(['code' => 404, 'error' => "Not Found"], 404);
        if (is_null($ecPoi)) {
            return $response;
        }

        $headers = [];
        $url = url()->current();
        switch ($format) {
            case 'gpx';
                $headers['Content-Type'] = 'application/vnd.api+json';
                $headers['Content-Disposition'] = 'attachment; filename="download.gpx"';
                $content = $ecPoi->getGpx($url);
                $response = response($content, 200, $headers);
                break;
            case 'kml';
                $headers['Content-Type'] = 'application/xml';
                $headers['Content-Disposition'] = 'attachment; filename="download.kml"';
                $content = $ecPoi->getKml($url);
                $response = response()->kml($content, 200, $headers);
                break;
            default:
                $headers['Content-Type'] = 'application/vnd.api+json';
                $headers['Content-Disposition'] = 'attachment; filename="download.geojson"';
                $content = $ecPoi->getGeojson($url);
                $response = response()->json($content, 200, $headers);
                break;
        }

        return $response;
    }
}
