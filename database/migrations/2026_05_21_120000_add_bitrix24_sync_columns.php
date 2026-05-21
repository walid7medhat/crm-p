<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'bitrix24_id')) {
                $table->unsignedBigInteger('bitrix24_id')->nullable()->after('lead_number');
                $table->unique('bitrix24_id', 'leads_bitrix24_id_unique');
            }
            if (!Schema::hasColumn('leads', 'more_information')) {
                $table->text('more_information')->nullable();
            }
        });

        if (Schema::hasTable('lead_comments')) {
            Schema::table('lead_comments', function (Blueprint $table) {
                if (!Schema::hasColumn('lead_comments', 'bitrix24_id')) {
                    $table->unsignedBigInteger('bitrix24_id')->nullable()->index('lead_comments_bitrix24_id_index');
                }
            });
        }

        if (Schema::hasTable('lead_activities')) {
            Schema::table('lead_activities', function (Blueprint $table) {
                if (!Schema::hasColumn('lead_activities', 'bitrix24_id')) {
                    $table->unsignedBigInteger('bitrix24_id')->nullable()->index('lead_activities_bitrix24_id_index');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'bitrix24_id')) {
                $table->dropUnique('leads_bitrix24_id_unique');
                $table->dropColumn('bitrix24_id');
            }
            if (Schema::hasColumn('leads', 'more_information')) {
                $table->dropColumn('more_information');
            }
        });

        if (Schema::hasTable('lead_comments')) {
            Schema::table('lead_comments', function (Blueprint $table) {
                if (Schema::hasColumn('lead_comments', 'bitrix24_id')) {
                    $table->dropIndex('lead_comments_bitrix24_id_index');
                    $table->dropColumn('bitrix24_id');
                }
            });
        }

        if (Schema::hasTable('lead_activities')) {
            Schema::table('lead_activities', function (Blueprint $table) {
                if (Schema::hasColumn('lead_activities', 'bitrix24_id')) {
                    $table->dropIndex('lead_activities_bitrix24_id_index');
                    $table->dropColumn('bitrix24_id');
                }
            });
        }
    }
};
