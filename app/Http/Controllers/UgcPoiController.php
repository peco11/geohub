<?php

namespace App\Http\Controllers;

use App\Http\Resources\UgcPoiCollection;
use App\Models\App;
use App\Models\UgcMedia;
use App\Models\UgcPoi;
use App\Providers\HoquServiceProvider;
use App\Traits\UGCFeatureCollectionTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Log;

class UgcPoiController extends Controller
{
    use UGCFeatureCollectionTrait;
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function index(Request $request, $version = 'v1')
    {
        $user = auth('api')->user();
        if (isset($user)) {
            Log::channel('ugc')->info('*************index ugc poi*****************');
            Log::channel('ugc')->info('version:' . $version);
            Log::channel('ugc')->info('user email:' . $user->email);

            if (!empty($request->header('app-id'))) {
                $appId = $request->header('app-id');
                Log::channel('ugc')->info('request app-id' . $appId);
                Log::channel('ugc')->info('request App-id' . $request->header('App-id'));
                if (is_numeric($appId)) {
                    $app = App::where('id', $appId)->first();
                } else {
                    $app = App::where('sku', $appId)->first();
                }
                $pois = UgcPoi::where([
                    ['user_id', $user->id],
                    ['app_id', $app->id]
                ])->orderByRaw('updated_at DESC')->get();
                Log::channel('ugc')->info('pois count:' . count($pois));
                return $this->getUGCFeatureCollection($pois, $version);
            }

            $pois = UgcPoi::where('user_id', $user->id)->orderByRaw('updated_at DESC')->get();
            return $this->getUGCFeatureCollection($pois, $version);
        } else {
            return new UgcPoiCollection(UgcPoi::currentUser()->paginate(10));
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    //    public function create() {
    //    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function store(Request $request): Response
    {
        $data = $request->all();
        Log::channel('ugc')->info("*************store ugc poi*****************");
        $dataProperties = $data['properties'];
        Log::channel('ugc')->info('ugc poi store properties name:' . $dataProperties['name']);
        Log::channel('ugc')->info('ugc poi store properties app_id(sku):' . $dataProperties['app_id']);
        $validator = Validator::make($data, [
            'type' => 'required',
            'properties' => 'required|array',
            'properties.name' => 'required|max:255',
            'properties.app_id' => 'required|max:255',
            'geometry' => 'required|array',
            'geometry.type' => 'required',
            'geometry.coordinates' => 'required|array',
        ]);

        if ($validator->fails()) {
            Log::channel('ugc')->info('Validazione fallita:', $validator->errors()->toArray());
            return response(['error' => $validator->errors(), 'Validation Error'], 400);
        }

        $user = auth('api')->user();
        Log::channel('ugc')->info('user email:' . $user->email);
        Log::channel('ugc')->info('user id:' . $user->id);
        if (is_null($user)) {
            Log::channel('ugc')->info('Utente non autenticato');
            return response(['error' => 'User not authenticated'], 403);
        }


        $poi = new UgcPoi();
        $poi->name = $data['properties']['name'];
        if (isset($data['properties']['description']))
            $poi->description = $data['properties']['description'];
        $poi->geometry = DB::raw("ST_GeomFromGeojson('" . json_encode($data['geometry']) . ")')");
        $poi->user_id = $user->id;

        if (isset($data['properties']['app_id'])) {
            $app_id = $data['properties']['app_id'];
            if (is_numeric($app_id)) {
                Log::channel('ugc')->info('numeric');
                $app = App::where('id', '=', $app_id)->first();
                if ($app != null) {
                    $poi->app_id = $app_id;
                    $poi->sku = $app->sku;
                }
            } else {
                Log::channel('ugc')->info('sku');
                $app = App::where('sku', '=', $app_id)->first();
                if ($app != null) {
                    $poi->app_id = $app->id;
                    $poi->sku = $app_id;
                }
            }
        }

        unset($data['properties']['name']);
        unset($data['properties']['description']);
        unset($data['properties']['app_id']);
        $poi->raw_data = json_encode($data['properties']);
        try {
            $poi->save();
        } catch (\Exception $e) {
            Log::channel('ugc')->info('Errore nel salvataggio del poi:' . $e->getMessage());
            return response(['error' => 'Error saving POI'], 500);
        }

        if (isset($data['properties']['image_gallery']) && is_array($data['properties']['image_gallery']) && count($data['properties']['image_gallery']) > 0) {
            foreach ($data['properties']['image_gallery'] as $imageId) {
                if (!!UgcMedia::find($imageId))
                    $poi->ugc_media()->attach($imageId);
            }
        }

        unset($data['properties']['image_gallery']);
        $poi->raw_data = json_encode($data['properties']);
        $poi->save();

        $hoquService = app(HoquServiceProvider::class);
        try {
            $hoquService->store('update_ugc_taxonomy_wheres', ['id' => $poi->id, 'type' => 'poi']);
        } catch (\Exception $e) {
        }
        return response(['id' => $poi->id, 'message' => 'Created successfully'], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param UgcPoi $ugcPoi
     *
     * @return Response
     */
    //    public function show(UgcPoi $ugcPoi) {
    //    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param UgcPoi $ugcPoi
     *
     * @return Response
     */
    //    public function edit(UgcPoi $ugcPoi) {
    //    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param UgcPoi  $ugcPoi
     *
     * @return Response
     */
    //    public function update(Request $request, UgcPoi $ugcPoi) {
    //    }

    /**
     * Remove the specified resource from storage.
     *
     * @param UgcPoi $ugcPoi
     *
     * @return Response
     */
    public function destroy($id)
    {
        try {
            $poi = UgcPoi::find($id);
            $poi->delete();
        } catch (Exception $e) {
            return response()->json([
                'error' => "this waypoint can't be deleted by api",
                'code' => 400
            ], 400);
        }
        return response()->json(['success' => 'waypoint deleted']);
    }
}
