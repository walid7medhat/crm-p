<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lead_id')->nullable()->unique()->constrained()->onDelete('cascade');
                $table->string('deal_number')->nullable()->unique(); // هياخد من lead_number
                $table->enum('deal_type', ['primary', 'secondary', 'rental']);
                $table->foreignId('stage_id')->constrained('stages');
                
                // Basic Info
                $table->string('source')->nullable();
                $table->string('deal_name')->nullable();
                $table->enum('status', ['draft', 'pending_approval', 'approved', 'completed', 'cancelled'])->default('draft');
                
                // Financials
                $table->decimal('deal_total_amount', 15, 2)->nullable();
                $table->string('currency', 3)->default('AED');
                $table->decimal('deal_commission', 5, 2)->nullable();
                $table->decimal('agent_share', 5, 2)->nullable();
                $table->decimal('company_share', 5, 2)->nullable();
                
                $table->string('unit_no')->nullable();
                $table->foreignId('property_type_id')->nullable()->constrained('property_types');
                $table->string('bedrooms')->nullable(); // Studio, 1, 2, 3, 4, 5+
                $table->decimal('unit_size', 10, 2)->nullable();
                $table->string('property_link')->nullable();
                $table->string('property_reference')->nullable();
                
                // Relationships
                $table->foreignId('project_id')->nullable()->constrained('projects');
                $table->foreignId('area_id')->nullable()->constrained('areas');
                $table->foreignId('developer_id')->nullable()->constrained('developers');
                
                // Responsible
                $table->foreignId('responsible_person_id')->nullable()->constrained('users');
                $table->foreignId('created_by')->constrained('users');
                $table->foreignId('updated_by')->nullable()->constrained('users');
                
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
                
                // Indexes
                $table->index('deal_type');
                $table->index('stage_id');
                $table->index('status');
                $table->index('deal_number');
                $table->index('project_id');
                $table->index('area_id');
                $table->index('developer_id');
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
