<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller captured when a listing is marked sold out lives in deal_parties /
 * deal_documents with listing_id set and deal_id NULL. When a deal is created on
 * that listing the same rows get deal_id (+ deal_property_id) — see
 * App\Support\Listing\ListingSellerParty.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL: drop the FK around the nullability change, then restore it (same cascade).
        Schema::table('deal_parties', function (Blueprint $table) {
            $table->dropForeign(['deal_id']);
        });
        Schema::table('deal_parties', function (Blueprint $table) {
            $table->unsignedBigInteger('deal_id')->nullable()->change();
            $table->foreign('deal_id')->references('id')->on('deals')->cascadeOnDelete();

            $table->foreignId('listing_id')->nullable()->after('deal_id')
                ->constrained('listings')->nullOnDelete();
            // nullOnDelete (not cascade): removing a unit from a deal must not delete the
            // listing's seller.
            $table->foreignId('deal_property_id')->nullable()->after('listing_id')
                ->constrained('deal_properties')->nullOnDelete();

            $table->index(['listing_id', 'party_type']);
        });

        Schema::table('deal_documents', function (Blueprint $table) {
            $table->dropForeign(['deal_id']);
        });
        Schema::table('deal_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('deal_id')->nullable()->change();
            $table->foreign('deal_id')->references('id')->on('deals')->cascadeOnDelete();

            $table->foreignId('listing_id')->nullable()->after('deal_id')
                ->constrained('listings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deal_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('listing_id');
        });

        Schema::table('deal_parties', function (Blueprint $table) {
            $table->dropIndex(['listing_id', 'party_type']);
            $table->dropConstrainedForeignId('deal_property_id');
            $table->dropConstrainedForeignId('listing_id');
        });
        // deal_id stays nullable on rollback: rows with deal_id NULL may exist.
    }
};
