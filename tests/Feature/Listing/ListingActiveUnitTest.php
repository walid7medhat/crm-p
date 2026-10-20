<?php

namespace Tests\Feature\Listing;

use App\Models\Listing;
use Tests\TestCase;

class ListingActiveUnitTest extends TestCase
{
    public function test_active_unit_scope_matches_the_all_listings_active_filter(): void
    {
        $query = Listing::query()->activeUnit();

        $this->assertSame(
            ['converted', 'rented', 'draft'],
            array_values(array_filter(
                $query->getBindings(),
                fn ($value) => in_array($value, ['converted', 'rented', 'draft'], true)
            ))
        );

        $sql = $query->toSql();
        $this->assertStringContainsString('is_active', $sql);
        $this->assertStringContainsString('is_archived', $sql);
        $this->assertStringContainsString('approved', $sql);
        $this->assertStringContainsString('status', $sql);
    }

    public function test_only_live_approved_units_count_as_active(): void
    {
        $active = new Listing([
            'is_active' => true,
            'is_archived' => false,
            'approved' => true,
            'status' => 'published',
        ]);
        $this->assertTrue($active->isActiveUnit());

        foreach ([
            ['is_active' => false, 'is_archived' => false, 'approved' => true, 'status' => 'published'],
            ['is_active' => true, 'is_archived' => true, 'approved' => true, 'status' => 'published'],
            ['is_active' => true, 'is_archived' => false, 'approved' => false, 'status' => 'published'],
            ['is_active' => true, 'is_archived' => false, 'approved' => true, 'status' => 'draft'],
            ['is_active' => true, 'is_archived' => false, 'approved' => true, 'status' => 'converted'],
            ['is_active' => true, 'is_archived' => false, 'approved' => true, 'status' => 'rented'],
        ] as $attributes) {
            $this->assertFalse((new Listing($attributes))->isActiveUnit());
        }
    }
}
