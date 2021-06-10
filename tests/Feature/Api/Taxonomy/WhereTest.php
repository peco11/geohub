<?php

namespace Tests\Feature\Api\Taxonomy;

use App\Models\TaxonomyWhere;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class WhereTest extends TestCase
{
    use RefreshDatabase;

    public function testGetGeoJson()
    {
        $taxonomyWhere = TaxonomyWhere::factory()->create();
        $response = $this->get(route("api.taxonomy.where.geojson", ['id' => $taxonomyWhere->id]));
        $this->assertSame(200, $response->status());
        $json = $response->json();
        $this->assertArrayHasKey('type', $json);
        $this->assertSame('Feature', $json["type"]);
    }

    public function testGetGeoJsonMissingId()
    {
        $response = $this->get(route("api.taxonomy.where.geojson", ['id' => 1]));
        $this->assertSame(404, $response->status());
    }

    public function testGetGeoJsonByIdentifier()
    {
        $taxonomyWhere = TaxonomyWhere::factory()->create();
        $response = $this->get(route("api.taxonomy.where.geojson.idt", ['identifier' => $taxonomyWhere->identifier]));
        $this->assertSame(200, $response->status());
        $json = $response->json();
        $this->assertArrayHasKey('type', $json);
        $this->assertSame('Feature', $json["type"]);
    }

    public function testIdentifierFormat()
    {
        $taxonomyWhere = TaxonomyWhere::factory()->create(['identifier' => "Testo dell'identifier di prova"]);
        $this->assertEquals($taxonomyWhere->identifier, "testo-dellidentifier-di-prova");
    }

    public function testIdentifierUniqueness()
    {
        TaxonomyWhere::factory()->create(['identifier' => "identifier"]);
        $taxonomyWhereSecond = TaxonomyWhere::factory()->create(['identifier' => NULL]);
        $taxonomyWhereThird = TaxonomyWhere::factory()->create(['identifier' => NULL]);
        $this->assertEquals($taxonomyWhereSecond->identifier, $taxonomyWhereThird->identifier);
        $this->assertNull($taxonomyWhereSecond->identifier);
        $this->assertNull($taxonomyWhereThird->identifier);

        try {
            TaxonomyWhere::factory()->create(['identifier' => "identifier"]);
        } catch (Exception $e) {
            $this->assertEquals($e->getCode(), '23505', "SQLSTATE[23505]: Unique violation error");
        }
    }
}
