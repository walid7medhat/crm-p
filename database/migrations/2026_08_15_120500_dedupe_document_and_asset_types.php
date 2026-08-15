<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dedupeNamedTypes('document_types', 'document_requests', 'document_type_id');
        $this->dedupeNamedTypes('asset_types', 'assets', 'asset_type_id');

        Schema::table('document_types', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::table('asset_types', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::table('asset_types', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }

    private function dedupeNamedTypes(string $table, string $childTable, string $foreignKey): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $groups = DB::table($table)
            ->select('name', DB::raw('MIN(id) as keep_id'))
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $duplicateIds = DB::table($table)
                ->where('name', $group->name)
                ->where('id', '!=', $group->keep_id)
                ->pluck('id');

            if ($duplicateIds->isEmpty()) {
                continue;
            }

            if (Schema::hasTable($childTable) && Schema::hasColumn($childTable, $foreignKey)) {
                DB::table($childTable)
                    ->whereIn($foreignKey, $duplicateIds)
                    ->update([$foreignKey => $group->keep_id]);
            }

            DB::table($table)->whereIn('id', $duplicateIds)->delete();
        }
    }
};
