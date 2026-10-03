<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Property Finder Enterprise API leads.
 *  - leads.pf_lead_id: the Property Finder lead id — unique, so a lead that arrives by
 *    webhook AND by the scheduled sync (or a webhook retry) is only created once.
 *  - property_finder_webhook_events: every webhook delivery is stored before it is
 *    processed (dedupe on PF's event id + an audit trail of what PF sent).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'pf_lead_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('pf_lead_id', 100)->nullable()->after('bitrix24_id');
                $table->unique('pf_lead_id', 'leads_pf_lead_id_unique');
            });
        }

        if (! Schema::hasTable('property_finder_webhook_events')) {
            Schema::create('property_finder_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_id', 100)->unique();
                $table->string('type', 100)->index();
                $table->string('pf_lead_id', 100)->nullable()->index();
                $table->json('payload');
                $table->string('status', 20)->default('pending')->index(); // pending|processed|ignored|failed
                $table->text('error')->nullable();
                $table->unsignedBigInteger('lead_id')->nullable()->index();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('property_finder_webhook_events');

        if (Schema::hasColumn('leads', 'pf_lead_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropUnique('leads_pf_lead_id_unique');
                $table->dropColumn('pf_lead_id');
            });
        }
    }
};
