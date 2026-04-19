<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assignment_patterns') && !Schema::hasColumn('assignment_patterns', 'context_fingerprint')) {
            Schema::table('assignment_patterns', function (Blueprint $table) {
                $table->string('context_source', 120)->nullable();
                $table->string('context_budget_range', 40)->nullable();
                $table->string('context_property_type', 120)->nullable();
                $table->string('context_nationality', 80)->nullable();
                $table->string('context_intent', 80)->nullable();
                $table->char('context_fingerprint', 40)->nullable();
            });

            if (Schema::hasColumn('assignment_patterns', 'lead_type')) {
                DB::table('assignment_patterns')->orderBy('id')->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $lt = (string) ($row->lead_type ?? '');
                        $src = 'unknown';
                        $intent = 'none';
                        if ($lt !== '') {
                            $parts = explode('|', $lt, 2);
                            $src = substr(strtolower(trim($parts[0])), 0, 120) ?: 'unknown';
                            $intent = isset($parts[1]) ? substr(strtolower(trim($parts[1])), 0, 80) : 'none';
                        }
                        $fp = sha1(implode('|', [$src, 'unknown', 'unknown', 'unknown', $intent]));
                        DB::table('assignment_patterns')->where('id', $row->id)->update([
                            'context_source' => $src,
                            'context_budget_range' => 'unknown',
                            'context_property_type' => 'unknown',
                            'context_nationality' => 'unknown',
                            'context_intent' => $intent,
                            'context_fingerprint' => $fp,
                        ]);
                    }
                });

                Schema::table('assignment_patterns', function (Blueprint $table) {
                    try {
                        $table->index('sales_id', 'assignment_patterns_sales_id_lookup');
                    } catch (\Throwable) {
                        //
                    }
                });

                Schema::table('assignment_patterns', function (Blueprint $table) {
                    try {
                        $table->dropUnique(['sales_id', 'lead_type']);
                    } catch (\Throwable) {
                        //
                    }
                });
            }

            Schema::table('assignment_patterns', function (Blueprint $table) {
                if (Schema::hasColumn('assignment_patterns', 'lead_type')) {
                    $table->dropColumn('lead_type');
                }
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE assignment_patterns MODIFY context_fingerprint CHAR(40) NOT NULL');
            }

            Schema::table('assignment_patterns', function (Blueprint $table) {
                try {
                    $table->unique(['sales_id', 'context_fingerprint'], 'assignment_patterns_sales_context_unique');
                } catch (\Throwable) {
                    //
                }
            });
        }

        if (!Schema::hasTable('sales_temporal_stats')) {
            Schema::create('sales_temporal_stats', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('weekday');
                $table->unsignedTinyInteger('hour');
                $table->unsignedInteger('assignments_count')->default(0);
                $table->unsignedInteger('wins_count')->default(0);
                $table->timestamps();
                $table->unique(['sales_id', 'weekday', 'hour'], 'sales_temporal_unique');
            });
        }

        if (Schema::hasTable('lead_assignment_settings')) {
            Schema::table('lead_assignment_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('lead_assignment_settings', 'exploration_epsilon')) {
                    $table->decimal('exploration_epsilon', 6, 4)->default(0.1);
                }
                if (!Schema::hasColumn('lead_assignment_settings', 'cold_start_max_samples')) {
                    $table->unsignedSmallInteger('cold_start_max_samples')->default(8);
                }
                if (!Schema::hasColumn('lead_assignment_settings', 'cold_start_explore_ratio')) {
                    $table->decimal('cold_start_explore_ratio', 6, 4)->default(0.15);
                }
                if (!Schema::hasColumn('lead_assignment_settings', 'adaptive_weights_enabled')) {
                    $table->boolean('adaptive_weights_enabled')->default(true);
                }
                if (!Schema::hasColumn('lead_assignment_settings', 'factor_weight_attendance')) {
                    $table->decimal('factor_weight_attendance', 7, 4)->default(0.3333);
                }
                if (!Schema::hasColumn('lead_assignment_settings', 'factor_weight_performance')) {
                    $table->decimal('factor_weight_performance', 7, 4)->default(0.3333);
                }
                if (!Schema::hasColumn('lead_assignment_settings', 'factor_weight_skill')) {
                    $table->decimal('factor_weight_skill', 7, 4)->default(0.3334);
                }
            });
        }

        if (Schema::hasTable('lead_assignment_logs')) {
            Schema::table('lead_assignment_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('lead_assignment_logs', 'dominant_factor')) {
                    $table->string('dominant_factor', 24)->nullable()->after('method');
                }
                if (!Schema::hasColumn('lead_assignment_logs', 'probability_of_close')) {
                    $table->decimal('probability_of_close', 8, 4)->nullable()->after('dominant_factor');
                }
                if (!Schema::hasColumn('lead_assignment_logs', 'was_exploration')) {
                    $table->boolean('was_exploration')->default(false)->after('probability_of_close');
                }
                if (!Schema::hasColumn('lead_assignment_logs', 'context_fingerprint')) {
                    $table->char('context_fingerprint', 40)->nullable()->after('was_exploration');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lead_assignment_logs')) {
            Schema::table('lead_assignment_logs', function (Blueprint $table) {
                foreach (['dominant_factor', 'probability_of_close', 'was_exploration', 'context_fingerprint'] as $col) {
                    if (Schema::hasColumn('lead_assignment_logs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('lead_assignment_settings')) {
            Schema::table('lead_assignment_settings', function (Blueprint $table) {
                foreach ([
                    'exploration_epsilon',
                    'cold_start_max_samples',
                    'cold_start_explore_ratio',
                    'adaptive_weights_enabled',
                    'factor_weight_attendance',
                    'factor_weight_performance',
                    'factor_weight_skill',
                ] as $col) {
                    if (Schema::hasColumn('lead_assignment_settings', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('sales_temporal_stats');
    }
};
