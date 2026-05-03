<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('deal_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->onDelete('cascade');
            $table->integer('sort_order')->default(0);
            
            // ========== Basic Property Info ==========
            $table->string('unit_no')->nullable();
            $table->foreignId('property_type_id')->nullable()->constrained('property_types');
            $table->string('bedrooms')->nullable();
            $table->string('unit_size')->nullable();
            
            // ========== Location ==========
            $table->foreignId('area_id')->nullable()->constrained('areas');
            $table->foreignId('project_id')->nullable()->constrained('projects');
            
            // ========== Developer ==========
            $table->foreignId('developer_id')->nullable()->constrained('developers');
            $table->string('developer_name')->nullable();
            $table->string('developer_phone')->nullable();
            
            // ========== Financials (لكل Property) ==========
            $table->decimal('budget_from', 15, 2)->nullable();
            $table->decimal('budget_to', 15, 2)->nullable();
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->decimal('rental_price', 15, 2)->nullable();
            
            // ========== Documents (JSON) ==========
            $table->json('payment_proof')->nullable();
            $table->json('spa_document')->nullable();
            $table->json('contract_document')->nullable();
            $table->json('ejari_document')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['deal_id', 'sort_order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('deal_properties');
    }
};