<?php

namespace Tests\Unit;

use App\Services\DailyMotivation\MotivationRotation;
use App\Services\DailyMotivation\MotivationWorkbookImporter;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class MotivationRotationTest extends TestCase
{
    public function test_same_day_slots_produce_different_messages_and_wrap_after_120(): void
    {
        $this->assertSame(1, MotivationRotation::messageNumber(0, 0));
        $this->assertSame(2, MotivationRotation::messageNumber(1, 0));
        $this->assertSame(120, MotivationRotation::messageNumber(0, 119));
        $this->assertSame(1, MotivationRotation::messageNumber(0, 120));
        $this->assertSame(2, MotivationRotation::messageNumber(1, 120));
    }

    public function test_missed_days_do_not_reset_personal_progress(): void
    {
        $this->assertSame(0, MotivationRotation::personalIndex(0, null));
        $this->assertSame(5, MotivationRotation::personalIndex(5, null));
        $this->assertSame(0, MotivationRotation::personalIndex(9, 9));
        $this->assertSame(1, MotivationRotation::personalIndex(10, 9));
    }

    public function test_day_index_follows_the_calendar_and_not_a_negative_gap(): void
    {
        $anchor = Carbon::parse('2026-10-01', 'Asia/Dubai')->startOfDay();
        $same = Carbon::parse('2026-10-01', 'Asia/Dubai')->startOfDay();
        $next = Carbon::parse('2026-10-04', 'Asia/Dubai')->startOfDay();
        $before = Carbon::parse('2026-09-30', 'Asia/Dubai')->startOfDay();

        $this->assertSame(0, MotivationRotation::dayIndex($anchor, $same));
        $this->assertSame(3, MotivationRotation::dayIndex($anchor, $next));
        $this->assertSame(0, MotivationRotation::dayIndex($anchor, $before));
    }

    public function test_slots_are_assigned_in_id_order_and_share_only_after_120(): void
    {
        $plan = MotivationRotation::assignSlots([5, 125], []);

        $this->assertSame(4, $plan[5]['slot']);
        $this->assertFalse($plan[5]['shared']);
        $this->assertSame(5, $plan[125]['slot']);
        $this->assertFalse($plan[125]['shared']);

        $taken = range(0, 119);
        $overflow = MotivationRotation::assignSlots([121], $taken);

        $this->assertTrue($overflow[121]['shared']);
        $this->assertSame(0, $overflow[121]['slot']);
    }

    public function test_disabling_a_message_substitutes_without_changing_the_canonical_number(): void
    {
        $this->assertSame(4, MotivationRotation::resolveDisplay(3, [1, 2, 4]));
        $this->assertSame(3, MotivationRotation::resolveDisplay(3, [3, 4]));
        $this->assertSame(1, MotivationRotation::resolveDisplay(120, [1]));
        $this->assertNull(MotivationRotation::resolveDisplay(8, []));
    }

    public function test_workbook_parser_requires_all_120_bilingual_rows(): void
    {
        $importer = new MotivationWorkbookImporter();
        $rows = [['No', 'English', 'Arabic']];
        for ($n = 1; $n <= 120; $n++) {
            $rows[] = [$n, "English {$n}", "عربي {$n}"];
        }

        $parsed = $importer->parse($rows);

        $this->assertCount(120, $parsed);
        $this->assertSame(1, $parsed[0]['number']);
        $this->assertSame('عربي 1', $parsed[0]['body_ar']);
        $this->assertSame(120, $parsed[119]['number']);
    }

    public function test_workbook_parser_rejects_a_missing_row(): void
    {
        $importer = new MotivationWorkbookImporter();
        $rows = [['No', 'English', 'Arabic'], [1, 'Only one', 'واحد']];

        $this->expectException(\RuntimeException::class);
        $importer->parse($rows);
    }
}
