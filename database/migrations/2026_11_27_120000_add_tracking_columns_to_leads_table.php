<?php

use App\Support\LeadTracking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * gclid / utm_* / landing_page sent by WordPress with website leads. Existing leads are
 * backfilled from field_mappings_data (the original payload), and the same rows are
 * removed from raw_meta_data so they no longer show as form questions to every user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach (LeadTracking::FIELDS as $field) {
                if (! Schema::hasColumn('leads', $field)) {
                    $field === 'landing_page'
                        ? $table->text($field)->nullable()
                        : $table->string($field, 500)->nullable();
                }
            }
        });

        DB::table('leads')
            ->select(['id', 'field_mappings_data', 'raw_meta_data'])
            ->whereNotNull('field_mappings_data')
            ->where(function ($q) {
                $q->where('field_mappings_data', 'like', '%utm\_%')
                    ->orWhere('field_mappings_data', 'like', '%gclid%')
                    ->orWhere('field_mappings_data', 'like', '%landing\_page%');
            })
            ->chunkById(500, function ($leads) {
                foreach ($leads as $lead) {
                    $payload = json_decode((string) $lead->field_mappings_data, true);
                    if (! is_array($payload)) {
                        continue;
                    }
                    $tracking = LeadTracking::extract($payload);
                    if (! $tracking) {
                        continue;
                    }

                    $update = $tracking;
                    $raw = json_decode((string) $lead->raw_meta_data, true);
                    if (is_array($raw) && is_array($raw['field_data'] ?? null)) {
                        $raw['field_data'] = array_values(array_filter(
                            $raw['field_data'],
                            fn ($row) => ! LeadTracking::isTrackingKey($row['name'] ?? null)
                        ));
                        $update['raw_meta_data'] = json_encode($raw, JSON_UNESCAPED_UNICODE);
                    }

                    DB::table('leads')->where('id', $lead->id)->update($update);
                }
            });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach (LeadTracking::FIELDS as $field) {
                if (Schema::hasColumn('leads', $field)) {
                    $table->dropColumn($field);
                }
            }
        });
    }
};
