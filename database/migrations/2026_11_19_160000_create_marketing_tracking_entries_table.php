<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing → Tracking Sheets (Blogs / OIA / All).
 * Holds imported spreadsheet rows plus new entries created in the CRM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_tracking_entries', function (Blueprint $table) {
            $table->id();
            $table->string('brand_key', 32)->nullable()->index();
            $table->string('category', 20)->index(); // blogs | oia | all
            $table->string('sheet_group', 40)->default('projects')->index(); // projects | developers | new_domains | blogs
            $table->string('region', 40)->nullable()->index(); // abu_dhabi | dubai | null
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('area')->nullable();
            $table->string('developer')->nullable();
            $table->string('done_by')->nullable();
            $table->boolean('is_published')->nullable();
            $table->string('content_label')->nullable();
            $table->string('content_url', 2048)->nullable();
            $table->string('page_label')->nullable();
            $table->string('page_url', 2048)->nullable();
            $table->string('linkedin_label')->nullable();
            $table->string('linkedin_url', 2048)->nullable();
            $table->string('instagram_label')->nullable();
            $table->string('instagram_url', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'sheet_group', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_tracking_entries');
    }
};
