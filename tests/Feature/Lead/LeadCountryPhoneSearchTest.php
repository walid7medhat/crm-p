<?php

namespace Tests\Feature\Lead;

use App\Models\Lead;
use App\Support\LeadCountryCodeFilter;
use App\Support\PhoneSearch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadCountryPhoneSearchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_phone_search_matches_every_stored_shape_of_the_same_number(): void
    {
        if (! Schema::hasColumn('leads', 'work_phone_digits')) {
            $this->markTestSkipped('work_phone_digits is not installed');
        }

        $template = DB::table('leads')->whereNull('deleted_at')->first();
        if (! $template) {
            $this->markTestSkipped('Need one lead row to copy required foreign keys');
        }

        $owner = (int) ($template->responsible_person_id ?: $template->added_by);
        $same = [
            'plus' => $this->insert($template, $owner, ['work_phone' => '+971501234567']),
            'idd' => $this->insert($template, $owner, ['work_phone' => '00971501234567']),
            'bare' => $this->insert($template, $owner, ['work_phone' => '971501234567']),
            'local' => $this->insert($template, $owner, ['work_phone' => '0501234567']),
            'spaces' => $this->insert($template, $owner, ['work_phone' => '050 123 4567']),
            'hyphen' => $this->insert($template, $owner, ['work_phone' => '050-123-4567']),
            'brackets' => $this->insert($template, $owner, ['work_phone' => '(050) 1234567']),
            'spaced_cc' => $this->insert($template, $owner, ['work_phone' => '971 50 123 4567']),
            'spaced_idd' => $this->insert($template, $owner, ['work_phone' => '00971 50 123 4567']),
            'secondary' => $this->insert($template, $owner, ['work_phone_2' => '+971 50 123 4567']),
        ];
        $other = [
            'landline' => $this->insert($template, $owner, ['work_phone' => '04 123 4567']),
            'landline_cc' => $this->insert($template, $owner, ['work_phone' => '+971 4 123 4567']),
            'other_mobile' => $this->insert($template, $owner, ['work_phone' => '0507654321']),
            'uk' => $this->insert($template, $owner, ['work_phone' => '+44 7911 123456']),
            'egypt' => $this->insert($template, $owner, ['work_phone' => '+20 100 123 4567']),
            'oregon' => $this->insert($template, $owner, ['work_phone' => '9715551234']),
            'empty' => $this->insert($template, $owner, []),
        ];

        $fixtureIds = array_merge(array_values($same), array_values($other));

        foreach (['0501234567', '+971 50 123 4567', '00971501234567', '971501234567', '050-123-4567'] as $term) {
            $ids = $this->phoneIds($term, $fixtureIds);
            foreach ($same as $label => $id) {
                $this->assertContains($id, $ids, "{$term} missed {$label}");
            }
            $this->assertNotContains($other['uk'], $ids, "{$term} matched the UK number");
            $this->assertNotContains($other['egypt'], $ids, "{$term} matched the Egypt number");
            $this->assertNotContains($other['oregon'], $ids, "{$term} matched the Oregon number");
            $this->assertNotContains($other['other_mobile'], $ids, "{$term} matched a different UAE mobile");
            $this->assertNotContains($other['landline'], $ids, "{$term} matched a Dubai landline");
        }

        $partial = $this->phoneIds('50123', $fixtureIds);
        foreach (['plus', 'local', 'spaces', 'secondary'] as $label) {
            $this->assertContains($same[$label], $partial, "partial 50123 missed {$label}");
        }
        $this->assertNotContains($other['other_mobile'], $partial);

        $uae = $this->phoneIds('+971', $fixtureIds);
        foreach (array_merge($same, ['landline' => $other['landline'], 'landline_cc' => $other['landline_cc']]) as $label => $id) {
            $this->assertContains($id, $uae, "+971 missed {$label}");
        }
        $this->assertNotContains($other['uk'], $uae);
        $this->assertNotContains($other['egypt'], $uae);
        $this->assertNotContains($other['oregon'], $uae);
        $this->assertNotContains($other['empty'], $uae);

        $dubai = $this->phoneIds('04', $fixtureIds);
        $this->assertContains($other['landline'], $dubai);
        $this->assertContains($other['landline_cc'], $dubai);
        $this->assertNotContains($same['local'], $dubai);
        $this->assertNotContains($other['uk'], $dubai);

        $mobilePrefix = $this->phoneIds('050', $fixtureIds);
        $this->assertContains($same['local'], $mobilePrefix);
        $this->assertContains($other['other_mobile'], $mobilePrefix);
        $this->assertNotContains($other['landline'], $mobilePrefix);
        $this->assertNotContains($other['uk'], $mobilePrefix);
    }

    public function test_international_excludes_every_uae_shape_and_keeps_other_leads(): void
    {
        if (! Schema::hasColumn('leads', 'work_phone_digits')) {
            $this->markTestSkipped('work_phone_digits is not installed');
        }

        $template = DB::table('leads')->whereNull('deleted_at')->first();
        if (! $template) {
            $this->markTestSkipped('Need one lead row to copy required foreign keys');
        }

        $owner = (int) ($template->responsible_person_id ?: $template->added_by);
        $uae = [
            $this->insert($template, $owner, ['work_phone' => '+971501234567']),
            $this->insert($template, $owner, ['work_phone' => '00971501234567']),
            $this->insert($template, $owner, ['work_phone' => '971501234567']),
            $this->insert($template, $owner, ['work_phone' => '0501234567']),
            $this->insert($template, $owner, ['work_phone' => '050 123 4567']),
            $this->insert($template, $owner, ['work_phone' => '050-123-4567']),
            $this->insert($template, $owner, ['work_phone' => '(050) 1234567']),
            $this->insert($template, $owner, ['work_phone' => '971 50 123 4567']),
            $this->insert($template, $owner, ['work_phone' => '00971 50 123 4567']),
            $this->insert($template, $owner, ['work_phone' => '0521234567']),
            $this->insert($template, $owner, ['work_phone' => '04 123 4567']),
            $this->insert($template, $owner, ['work_phone' => '+971 4 123 4567']),
            $this->insert($template, $owner, ['work_phone_2' => '0581234567']),
        ];
        $keep = [
            'uk' => $this->insert($template, $owner, ['work_phone' => '+44 7911 123456']),
            'egypt' => $this->insert($template, $owner, ['work_phone' => '+20 100 123 4567']),
            'oregon' => $this->insert($template, $owner, ['work_phone' => '9715551234']),
            'empty' => $this->insert($template, $owner, []),
        ];

        $ids = $this->countryIds('international', array_merge($uae, array_values($keep)));
        foreach ($uae as $id) {
            $this->assertNotContains($id, $ids, "International kept UAE lead {$id}");
        }
        foreach ($keep as $label => $id) {
            $this->assertContains($id, $ids, "International dropped {$label}");
        }

        $uaeIds = $this->countryIds('971', array_merge($uae, array_values($keep)));
        foreach ($uae as $id) {
            $this->assertContains($id, $uaeIds, "UAE filter missed {$id}");
        }
        $this->assertNotContains($keep['uk'], $uaeIds);
        $this->assertNotContains($keep['egypt'], $uaeIds);
        $this->assertNotContains($keep['oregon'], $uaeIds);

        $egyptIds = $this->countryIds('+20', array_merge($uae, array_values($keep)));
        $this->assertSame([$keep['egypt']], $egyptIds);

        $withPhone = $this->scopedIds(function ($query) {
            PhoneSearch::applyWorkPhone($query, Request::create('/', 'GET', [
                'country_code' => 'international',
                'work_phone' => '447911',
            ]));
            LeadCountryCodeFilter::apply($query, Request::create('/', 'GET', [
                'country_code' => 'international',
                'work_phone' => '447911',
            ]), $this->admin());
        }, array_merge($uae, array_values($keep)));
        $this->assertSame([$keep['uk']], $withPhone);
    }

    public function test_country_filter_stays_behind_the_existing_roles_and_pagination_sql(): void
    {
        $sales = new class
        {
            public function hasAnyRole(...$roles): bool
            {
                return false;
            }
        };

        $branchAdmin = new class
        {
            public function hasAnyRole(...$roles): bool
            {
                $names = [];
                foreach ($roles as $role) {
                    foreach ((array) $role as $name) {
                        $names[] = $name;
                    }
                }

                return in_array('branch_admin', $names, true);
            }
        };

        $blocked = Lead::query();
        LeadCountryCodeFilter::apply($blocked, Request::create('/', 'GET', [
            'country_code' => 'international',
        ]), $branchAdmin);
        $this->assertStringNotContainsString('00971', $blocked->toSql());

        $query = Lead::query()->where('lead_name', 'like', 'Ada%')->orderBy('id');
        LeadCountryCodeFilter::apply($query, Request::create('/', 'GET', [
            'country_code' => 'international',
        ]), $sales);
        $sql = $query->toSql();

        $this->assertStringContainsString('lead_name', $sql);
        $this->assertStringContainsString('order by', strtolower($sql));
        $this->assertStringNotContainsString('00971', $sql);

        $adminSql = Lead::query();
        LeadCountryCodeFilter::apply($adminSql, Request::create('/', 'GET', [
            'country_code' => '+971,+44',
        ]), $this->admin());
        $bindings = array_map('strval', $adminSql->getBindings());
        $this->assertContains('971%', $bindings);
        $this->assertContains('44%', $bindings);
        $this->assertContains('0044%', $bindings);
        foreach ($bindings as $binding) {
            $this->assertFalse(str_starts_with($binding, '%'), $binding);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insert(object $template, int $ownerId, array $overrides): int
    {
        $now = now();

        return (int) DB::table('leads')->insertGetId([
            'lead_number' => 'P1CC-'.uniqid(),
            'lead_name' => 'Country phone fixture',
            'first_name' => 'Phone',
            'stage_id' => $template->stage_id,
            'added_by' => $template->added_by,
            'responsible_person_id' => $ownerId,
            'work_phone' => $overrides['work_phone'] ?? null,
            'work_phone_2' => $overrides['work_phone_2'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  list<int>  $within
     * @return list<int>
     */
    private function phoneIds(string $term, array $within): array
    {
        return $this->scopedIds(function ($query) use ($term) {
            PhoneSearch::applyWorkPhone($query, Request::create('/', 'GET', ['work_phone' => $term]));
        }, $within);
    }

    /**
     * @param  list<int>  $within
     * @return list<int>
     */
    private function countryIds(string $code, array $within): array
    {
        return $this->scopedIds(function ($query) use ($code) {
            LeadCountryCodeFilter::apply($query, Request::create('/', 'GET', [
                'country_code' => $code,
            ]), $this->admin());
        }, $within);
    }

    /**
     * @param  list<int>  $within
     * @return list<int>
     */
    private function scopedIds(callable $apply, array $within): array
    {
        $query = Lead::query()->whereIn('id', $within)->orderBy('id');
        $apply($query);

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function admin(): object
    {
        return new class
        {
            public function hasAnyRole(...$roles): bool
            {
                $names = [];
                foreach ($roles as $role) {
                    foreach ((array) $role as $name) {
                        $names[] = $name;
                    }
                }

                return in_array('admin', $names, true);
            }
        };
    }
}
