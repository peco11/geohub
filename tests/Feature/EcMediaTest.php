<?php

namespace Tests\Feature;

use App\Models\EcMedia;
use App\Providers\HoquServiceProvider;
use Doctrine\DBAL\Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class EcMediaTest extends TestCase
{
    use RefreshDatabase;

    public function testSaveEcMediaOk()
    {
        $this->mock(HoquServiceProvider::class, function ($mock) {
            $mock->shouldReceive('store')
                ->once()
                ->with('enrich_ec_media', ['id' => 1])
                ->andReturn(201);
        });
        $ecMedia = new EcMedia(['name' => 'testName', 'url' => 'testUrl']);
        $ecMedia->id = 1;
        $ecMedia->save();
    }

    public function testSaveEcMediaError()
    {
        $this->mock(HoquServiceProvider::class, function ($mock) {
            $mock->shouldReceive('store')
                ->once()
                ->with('enrich_ec_media', ['id' => 1])
                ->andThrows(new Exception());
        });
        Log::shouldReceive('error')
            ->once();
        $ecMedia = new EcMedia(['name' => 'testName', 'url' => 'testUrl']);
        $ecMedia->id = 1;
        $ecMedia->save();
    }
}
