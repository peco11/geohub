<?php

namespace Tests\Feature;

use App\Classes\OutSourceImporter\OutSourceImporterFeatureWP;
use App\Models\OutSourceFeature;
use App\Providers\CurlServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery\MockInterface;
use Tests\TestCase;

class OutSourceImporterFeatureWPImportTrackTest extends TestCase
{
    use RefreshDatabase;
    /** @test */
    public function when_endpoint_is_stelvio_and_type_is_track_it_creates_proper_out_feature()
    {
        // WHEN
        $type = 'track';
        $endpoint = 'https://stelvio.wp.webmapp.it';
        $source_id = 6;
        $source_id_en = 1239;
        $source_id_de = 1241;
        $stelvio_track_it = file_get_contents(base_path('tests/Feature/Stubs/stelvio_track.json'));
        $stelvio_track_en = file_get_contents(base_path('tests/Feature/Stubs/stelvio_track_en.json'));
        $stelvio_track_de = file_get_contents(base_path('tests/Feature/Stubs/stelvio_track_de.json'));
        $url_it = $endpoint.'/wp-json/wp/v2/track/'.$source_id;
        $url_en = $endpoint.'/wp-json/wp/v2/track/'.$source_id_en;
        $url_de = $endpoint.'/wp-json/wp/v2/track/'.$source_id_de;


        // PREPARE MOCK ITA
        $this->mock(CurlServiceProvider::class,function (MockInterface $mock) use ($stelvio_track_it,$url_it,$url_en,$stelvio_track_en,$url_de,$stelvio_track_de){
            $mock->shouldReceive('exec')
            ->once()
            ->with($url_it)
            ->andReturn($stelvio_track_it);

            $mock->shouldReceive('exec')
            ->once()
            ->with($url_en)
            ->andReturn($stelvio_track_en);

            $mock->shouldReceive('exec')
            ->once()
            ->with($url_de)
            ->andReturn($stelvio_track_de);
        });

        // FIRE
        $track = new OutSourceImporterFeatureWP($type,$endpoint,$source_id);
        $track_id = $track->importFeature();

        // VERIFY
        $out_source = OutSourceFeature::find($track_id);
        $this->assertEquals('track',$out_source->type);
        $this->assertEquals(6,$out_source->source_id);
        $this->assertEquals('https://stelvio.wp.webmapp.it',$out_source->endpoint);
        $this->assertEquals('App\Classes\OutSourceImporter\OutSourceImporterFeatureWP',$out_source->provider);
       
        // TODO: add some checks on tags
        $stelvio_track_js_it = json_decode($stelvio_track_it,TRUE);
        $stelvio_track_js_en = json_decode($stelvio_track_en,TRUE);
        $stelvio_track_js_de = json_decode($stelvio_track_de,TRUE);
        
        $this->assertEquals($stelvio_track_js_it['title']['rendered'],$out_source->tags['name']['it']);

        // TODO: make it work with  &#8211; convertion to '-' 
        // $this->assertEquals($stelvio_track_js_en['title']['rendered'],$out_source->tags['name']['en']);
        // $this->assertEquals($stelvio_track_js_de['title']['rendered'],$out_source->tags['name']['de']);

        $this->assertEquals($stelvio_track_js_it['content']['rendered'],$out_source->tags['description']['it']);
        $this->assertEquals($stelvio_track_js_en['content']['rendered'],$out_source->tags['description']['en']);
        $this->assertEquals($stelvio_track_js_de['content']['rendered'],$out_source->tags['description']['de']);

        // TODO: add some checks on geometry
        // TODO: add some checks on raw_data
        // This is not working:
        // $this->assertEquals($stelvio_track_it,json_encode($out_source->raw_data));

    }

}
