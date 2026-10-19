<?php

namespace Tests\Feature\Lead;

use App\Models\Lead;
use App\Services\LeadTextSearch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phone search must keep every lead the previous LIKE search returned, and it must
 * keep the caller's existing visibility constraints. work_phone_digits covers work_phone
 * only, so the other phone columns stay on the original substring match.
 */
class LeadPhoneSearchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_phone_results_include_every_legacy_match_and_the_frozen_cases(): void
    {
        $template = DB::table('leads')->whereNull('deleted_at')->first();
        if (! $template) {
            $this->markTestSkipped('Need one lead row to copy required foreign keys');
        }

        $owner = (int) ($template->responsible_person_id ?: $template->added_by);
        $otherOwner = (int) DB::table('users')->where('id', '!=', $owner)->value('id');

        $ids = [
            'exact' => $this->insertLead($template, $owner, [
                'work_phone' => '0501234567',
                'lead_name' => 'Phone Exact',
            ]),
            'formatted' => $this->insertLead($template, $owner, [
                'work_phone' => '+971 50 765 4321',
                'lead_name' => 'Phone Formatted',
            ]),
            'second' => $this->insertLead($template, $owner, [
                'work_phone_2' => '0508887766',
                'lead_name' => 'Phone Second',
            ]),
            'whatsapp' => $this->insertLead($template, $owner, [
                'whatsapp_number' => '971509991111',
                'lead_name' => 'Phone WhatsApp',
            ]),
            'source' => $this->insertLead($template, $owner, [
                'source_client_phone' => '044445555',
                'lead_name' => 'Phone Source',
            ]),
            'lead_number' => $this->insertLead($template, $owner, [
                'lead_number' => 'LN-55887766',
                'lead_name' => 'Phone Lead Number',
            ]),
            'middle' => $this->insertLead($template, $owner, [
                'work_phone' => '99950129999000',
                'lead_name' => 'Phone Middle',
            ]),
            'named' => $this->insertLead($template, $owner, [
                'lead_name' => 'SearchAlice Unique',
                'email' => 'searchalice@example.com',
                'work_phone' => '0500000000',
            ]),
            'hidden' => $this->insertLead($template, $otherOwner, [
                'work_phone' => '0501234567',
                'lead_name' => 'Phone Hidden Owner',
            ]),
        ];

        $formattedDigits = DB::table('leads')->where('id', $ids['formatted'])->value('work_phone_digits');
        $this->assertSame('971507654321', $formattedDigits);

        $phoneCases = [
            '0501234567' => [$ids['exact'], $ids['hidden']],
            '+971 50 123 4567' => [$ids['exact']],
            '501234567' => [$ids['exact']],
            '971507654321' => [$ids['formatted']],
            '0507654321' => [$ids['formatted']],
            '0508887766' => [$ids['second']],
            '971509991111' => [$ids['whatsapp']],
            '044445555' => [$ids['source']],
            '55887766' => [$ids['lead_number']],
            '012999' => [$ids['middle']],
        ];

        foreach ($phoneCases as $term => $expected) {
            $legacy = $this->legacyPhoneIds($term);
            $actual = $this->searchIds($term);
            $missing = array_values(array_diff($legacy, $actual));
            $this->assertSame([], $missing, "New phone search dropped legacy ids for [{$term}]");
            foreach ($expected as $id) {
                $this->assertContains($id, $actual, "Frozen case [{$term}] missed lead {$id}");
            }
        }

        $nameIds = $this->searchIds('SearchAlice Unique');
        $legacyNameIds = Lead::query()->where('lead_name', 'like', '%SearchAlice Unique%')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($ids['named'], $legacyNameIds);
        foreach ($legacyNameIds as $legacyNameId) {
            $this->assertContains($legacyNameId, $nameIds);
        }
        $this->assertContains($ids['named'], $nameIds);
        $this->assertNotContains($ids['exact'], $nameIds);

        $emailIds = $this->searchIds('searchalice@example.com');
        $this->assertContains($ids['named'], $emailIds);
        $this->assertNotContains($ids['exact'], $emailIds);

        $scoped = Lead::query()->where('responsible_person_id', $owner);
        LeadTextSearch::apply($scoped, '0501234567');
        $scopedIds = $scoped->pluck('id')->all();
        $this->assertContains($ids['exact'], $scopedIds);
        $this->assertNotContains($ids['hidden'], $scopedIds);
    }

    public function test_work_phone_digits_prefix_uses_its_index(): void
    {
        $plan = DB::select('EXPLAIN SELECT id FROM leads WHERE work_phone_digits = ? OR work_phone_digits LIKE ?', [
            '0501234567',
            '0501234567%',
        ]);

        $keys = array_map(fn ($row) => $row->key ?? null, $plan);
        $this->assertContains('leads_work_phone_digits_index', $keys);
    }

    public function test_name_search_sql_does_not_use_the_phone_index_path(): void
    {
        $sql = LeadTextSearch::apply(Lead::query(), 'SearchAlice')->toSql();
        $this->assertStringNotContainsString('work_phone_digits', $sql);
        $this->assertStringContainsString('lead_name', $sql);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertLead(object $template, int $ownerId, array $overrides): int
    {
        $now = now();
        $row = [
            'lead_number' => $overrides['lead_number'] ?? ('P1PH-'.uniqid()),
            'lead_name' => $overrides['lead_name'] ?? 'Phone fixture',
            'first_name' => 'Phone',
            'stage_id' => $template->stage_id,
            'added_by' => $template->added_by,
            'responsible_person_id' => $ownerId,
            'work_phone' => $overrides['work_phone'] ?? null,
            'work_phone_2' => $overrides['work_phone_2'] ?? null,
            'whatsapp_number' => $overrides['whatsapp_number'] ?? null,
            'source_client_phone' => $overrides['source_client_phone'] ?? null,
            'email' => $overrides['email'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return (int) DB::table('leads')->insertGetId($row);
    }

    /**
     * @return list<int>
     */
    private function searchIds(string $term): array
    {
        $query = Lead::query();
        LeadTextSearch::apply($query, $term);

        return $query->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The phone predicate this change replaces. Used only to prove the new
     * result set still contains those ids.
     *
     * @return list<int>
     */
    private function legacyPhoneIds(string $term): array
    {
        $digits = preg_replace('/\D+/', '', $term) ?? '';
        $variants = LeadTextSearch::phoneDigitVariants($digits);

        return Lead::query()
            ->where(function ($legacy) use ($variants) {
                foreach ($variants as $variant) {
                    $like = '%'.$variant.'%';
                    $legacy->orWhere('work_phone', 'like', $like)
                        ->orWhere('work_phone_2', 'like', $like)
                        ->orWhere('whatsapp_number', 'like', $like)
                        ->orWhere('lead_number', 'like', $like)
                        ->orWhere('source_client_phone', 'like', $like);
                }
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
