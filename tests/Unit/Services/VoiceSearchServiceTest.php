<?php

namespace Tests\Unit\Services;

use App\Services\VoiceSearchService;
use Tests\TestCase;

/**
 * Language helpers do not need DB. Full parse assertions live in Feature tests.
 */
class VoiceSearchServiceTest extends TestCase
{
    public function test_detects_arabic_language(): void
    {
        $service = new VoiceSearchService();
        $this->assertSame('ar', $service->detectLanguage('عايز شقة في الريم'));
    }

    public function test_detects_english_language(): void
    {
        $service = new VoiceSearchService();
        $this->assertSame('en', $service->detectLanguage('I need a 2 bedroom apartment'));
    }

    public function test_detects_mixed_language(): void
    {
        $service = new VoiceSearchService();
        $this->assertSame('mixed', $service->detectLanguage('عايز apartment في Yas Island'));
    }

    public function test_parses_arabic_reem_island_example(): void
    {
        $service = new VoiceSearchService();
        $parsed = $service->parse('عايز شقة غرفتين في جزيرة الريم بسعر مليون ونص');

        $this->assertSame('ar', $parsed['language']);
        $this->assertSame('Apartment', $parsed['filters']['property_type']);
        $this->assertSame(2, $parsed['filters']['number_of_bedrooms']);
        $this->assertSame('Reem Island', $parsed['filters']['area']);
        $this->assertEquals(1500000, $parsed['filters']['max_price']);
    }

    public function test_parses_english_example(): void
    {
        $service = new VoiceSearchService();
        $parsed = $service->parse('I need a 2 bedroom apartment in Reem Island under 1.5 million');

        $this->assertSame('en', $parsed['language']);
        $this->assertSame('Apartment', $parsed['filters']['property_type']);
        $this->assertSame(2, $parsed['filters']['number_of_bedrooms']);
        $this->assertSame('Reem Island', $parsed['filters']['area']);
        $this->assertEquals(1500000, $parsed['filters']['max_price']);
    }

    public function test_to_listing_query_params_maps_existing_keys(): void
    {
        $service = new VoiceSearchService();
        $params = $service->toListingQueryParams([
            'property_type' => 'Apartment',
            'number_of_bedrooms' => 2,
            'max_price' => 1500000,
            'listing_status' => 'sale',
            'area' => 'Reem Island',
        ]);

        $this->assertSame(2, $params['number_of_bedrooms']);
        $this->assertSame(1500000.0, $params['max_price']);
        $this->assertSame('sale', $params['listing_status']);
        // area_id may be null if DB has no matching area; search fallback is OK for Phase 1
        $this->assertTrue(isset($params['area_ids']) || isset($params['search']));
    }
}
