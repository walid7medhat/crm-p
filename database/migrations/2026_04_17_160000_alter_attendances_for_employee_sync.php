<?php

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('attendances', 'employee_id')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->string('employee_id')->nullable()->after('id');
                $table->string('employee_name')->nullable()->after('employee_id');
                $table->string('status', 32)->nullable()->after('employee_name');
            });
        }

        $migration = $this;
        Attendance::query()->with('user')->orderBy('id')->each(function (Attendance $a) use ($migration) {
            $u = $a->user;
            $raw = $u?->biometric_code ?: (string) ($a->user_id ?? '');
            $norm = Attendance::normalizeEmployeeId($raw) ?: ('USER-' . ($a->user_id ?? $a->id));
            $status = $a->status;
            if ($status === null || $status === '') {
                $status = $migration->inferStatusFromCheckIn($a->check_in);
            }
            DB::table('attendances')->where('id', $a->id)->update([
                'employee_id' => $norm,
                'employee_name' => $u?->name ?? 'Unknown',
                'status' => $status,
                'updated_at' => now(),
            ]);
        });

        $indexNames = collect(DB::select('SHOW INDEX FROM attendances'))->pluck('Key_name')->unique()->values()->all();
        $hasEmployeeDateUnique = in_array('attendances_employee_id_date_unique', $indexNames, true);

        if (!$hasEmployeeDateUnique) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::table('attendances', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'date']);
            });

            Schema::table('attendances', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });

            Schema::table('attendances', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->unique(['employee_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'date']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'date']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['employee_id', 'employee_name', 'status']);
        });
    }

    private function inferStatusFromCheckIn($checkIn): string
    {
        if (!$checkIn) {
            return 'absent';
        }
        try {
            $checkIn = Carbon::parse($checkIn);
            $lateBoundary = Carbon::parse($checkIn->toDateString() . ' 09:15:00');

            return $checkIn->gt($lateBoundary) ? 'late' : 'present';
        } catch (\Throwable $e) {
            return 'present';
        }
    }
};
