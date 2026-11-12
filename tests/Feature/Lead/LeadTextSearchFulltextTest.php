<?php

namespace Tests\Feature\Lead;

use App\Models\Lead;
use App\Services\LeadTextSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Proves ngram FULLTEXT identity search preserves LIKE contains semantics for
 * the covered columns. Does NOT use DatabaseTransactions: InnoDB FULLTEXT is
 * only updated after COMMIT.
 */
class LeadTextSearchFulltextTest extends TestCase
{
    /** @var list<int> */
    private array $createdIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        LeadTextSearch::clearFulltextCache();
        config(['lead_search.use_fulltext' => true]);
    }

    protected function tearDown(): void
    {
        if ($this->createdIds !== []) {
            DB::table('leads')->whereIn('id', $this->createdIds)->delete();
            $this->createdIds = [];
        }
        config(['lead_search.use_fulltext' => true]);
        LeadTextSearch::clearFulltextCache();
        parent::tearDown();
    }

    public function test_fulltext_index_migration_defines_ngram_parser(): void
    {
        $source = file_get_contents(base_path(
            'database/migrations/2026_11_12_210000_add_leads_identity_ngram_fulltext_index.php'
        ));
        $this->assertNotFalse($source);
        $this->assertStringContainsString('WITH PARSER ngram', $source);
        $this->assertStringContainsString('leads_identity_ngram_ft', $source);
        $this->assertStringContainsString('leads_lead_name_ngram_ft', $source);
        $this->assertStringContainsString('DROP INDEX', $source);
    }

    public function test_identity_fulltext_matches_like_contains_for_name_partials(): void
    {
        if (! $this->fulltextIndexPresent()) {
            $this->markTestSkipped('Run migration 2026_11_12_210000 on this database first.');
        }

        $template = DB::table('leads')->whereNull('deleted_at')->first();
        if (! $template) {
            $this->markTestSkipped('Need one lead row to copy required foreign keys');
        }

        $owner = (int) ($template->responsible_person_id ?: $template->added_by);
        $ids = [
            'ahmed' => $this->insertLead($template, $owner, [
                'lead_name' => 'Ahmed Hassan UniqueFt',
                'first_name' => 'Ahmed',
                'last_name' => 'Hassan',
                'email' => 'ahmed.ft.unique@example.com',
                'lead_source' => 'Website Portal',
            ]),
            'arabic' => $this->insertLead($template, $owner, [
                'lead_name' => 'محمد علي فت',
                'first_name' => 'محمد',
                'last_name' => 'علي',
                'email' => 'arabic.ft.unique@example.com',
            ]),
            'mid' => $this->insertLead($template, $owner, [
                'lead_name' => 'XXYasmeenAbdlrhmanHasanXX',
                'first_name' => 'Yasmeen',
                'last_name' => 'Hasan',
                'email' => 'mid.ft.unique@example.com',
            ]),
        ];

        $terms = [
            'Ahmed',
            'ahme',
            'hass',
            'UniqueFt',
            'محمد',
            'Yasmeen',
            'HasanXX',
        ];

        foreach ($terms as $term) {
            if (! LeadTextSearch::isActionable($term)) {
                continue;
            }

            $likeIds = $this->legacyIdentityIds($term);
            LeadTextSearch::clearFulltextCache();
            $this->assertTrue(LeadTextSearch::fulltextEnabled());
            $ftIds = $this->searchIds($term, ['admin' => true, 'lean' => true, 'relations' => false]);

            $missing = array_values(array_diff($likeIds, $ftIds));
            $this->assertSame(
                [],
                $missing,
                "FULLTEXT lean admin dropped LIKE identity ids for [{$term}]"
            );
        }

        LeadTextSearch::clearFulltextCache();
        $this->assertContains($ids['ahmed'], $this->searchIds('hass', ['admin' => true, 'lean' => true, 'relations' => false]));
        $this->assertContains($ids['arabic'], $this->searchIds('محمد', ['admin' => true, 'lean' => true, 'relations' => false]));
        $this->assertContains($ids['mid'], $this->searchIds('Yasmeen', ['admin' => true, 'lean' => true, 'relations' => false]));
    }

    public function test_non_admin_fulltext_does_not_match_email_only_hits(): void
    {
        if (! $this->fulltextIndexPresent()) {
            $this->markTestSkipped('Run migration 2026_11_12_210000 on this database first.');
        }

        $template = DB::table('leads')->whereNull('deleted_at')->first();
        if (! $template) {
            $this->markTestSkipped('Need one lead row to copy required foreign keys');
        }

        $owner = (int) ($template->responsible_person_id ?: $template->added_by);
        $emailOnly = $this->insertLead($template, $owner, [
            'lead_name' => 'ZZ No Name Hit',
            'first_name' => 'Nope',
            'last_name' => 'Nope',
            'email' => 'secretemailonlyft@example.com',
            'lead_source' => 'Other',
        ]);

        LeadTextSearch::clearFulltextCache();
        $this->assertTrue(LeadTextSearch::fulltextEnabled());
        $ids = $this->searchIds('secretemailonlyft', [
            'admin' => false,
            'lean' => true,
            'relations' => false,
        ]);
        $this->assertNotContains($emailOnly, $ids);
    }

    public function test_like_fallback_sql_when_fulltext_disabled(): void
    {
        config(['lead_search.use_fulltext' => false]);
        LeadTextSearch::clearFulltextCache();

        $sql = LeadTextSearch::apply(Lead::query(), 'SearchAlice', [
            'admin' => true,
            'lean' => true,
            'relations' => false,
        ])->toSql();

        $this->assertStringContainsString('lead_name', $sql);
        $this->assertStringNotContainsString('MATCH(', $sql);
    }

    public function test_fulltext_sql_uses_match_when_enabled_outside_transaction(): void
    {
        if (! $this->fulltextIndexPresent()) {
            $this->markTestSkipped('Run migration 2026_11_12_210000 on this database first.');
        }

        LeadTextSearch::clearFulltextCache();
        $this->assertTrue(LeadTextSearch::fulltextEnabled());
        $sql = LeadTextSearch::apply(Lead::query(), 'SearchAlice', [
            'admin' => true,
            'lean' => true,
            'relations' => false,
        ])->toSql();

        $this->assertStringContainsString('MATCH(', $sql);
        $this->assertStringContainsString('AGAINST (? IN BOOLEAN MODE)', $sql);
    }

    private function fulltextIndexPresent(): bool
    {
        if (! Schema::hasTable('leads')) {
            return false;
        }

        $names = collect(Schema::getIndexes('leads'))->pluck('name')->all();

        return in_array('leads_identity_ngram_ft', $names, true)
            && in_array('leads_lead_name_ngram_ft', $names, true);
    }

    /**
     * Historical admin lean identity predicate (LIKE only on FT-covered columns).
     *
     * @return list<int>
     */
    private function legacyIdentityIds(string $term): array
    {
        $like = '%'.$term.'%';

        return Lead::query()
            ->where(function ($s) use ($like) {
                $s->where('lead_name', 'like', $like)
                    ->orWhere('lead_number', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('lead_source', 'like', $like);
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array{comments?: bool, relations?: bool, admin?: bool, lean?: bool}  $options
     * @return list<int>
     */
    private function searchIds(string $term, array $options = []): array
    {
        $query = Lead::query();
        LeadTextSearch::apply($query, $term, $options);

        return $query->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertLead(object $template, int $ownerId, array $overrides): int
    {
        $now = now();
        $id = (int) DB::table('leads')->insertGetId([
            'lead_number' => $overrides['lead_number'] ?? ('FT-'.uniqid()),
            'lead_name' => $overrides['lead_name'] ?? 'FT fixture',
            'first_name' => $overrides['first_name'] ?? 'FT',
            'last_name' => $overrides['last_name'] ?? null,
            'stage_id' => $template->stage_id,
            'added_by' => $template->added_by,
            'responsible_person_id' => $ownerId,
            'work_phone' => $overrides['work_phone'] ?? null,
            'email' => $overrides['email'] ?? null,
            'lead_source' => $overrides['lead_source'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->createdIds[] = $id;

        return $id;
    }
}
