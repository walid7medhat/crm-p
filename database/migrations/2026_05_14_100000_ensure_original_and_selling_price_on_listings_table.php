<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure original_price and selling_price exist alongside price (idempotent for existing DBs).
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (! Schema::hasColumn('listings', 'original_price')) {
                $table->decimal('original_price', 15, 2)->nullable()->after('price');
            }
            if (! Schema::hasColumn('listings', 'selling_price')) {
                $after = Schema::hasColumn('listings', 'original_price') ? 'original_price' : 'price';
                $table->decimal('selling_price', 15, 2)->nullable()->after($after);
            }
        });
    }

    public function down(): void
    {
        // Intentionally empty: columns may pre-exist; dropping would risk data loss on rollback.
    }
};
