<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 50)->unique(); // #AST-001
            $table->string('name'); // Asset Name
            $table->foreignId('asset_type_id')->constrained('asset_types')->onDelete('restrict');
            $table->string('serial_number')->nullable();
            $table->string('model_number')->nullable();
            $table->string('rdp_number')->nullable(); // Reference number
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();
            
            // Purchase Details
            $table->date('purchase_date')->nullable();
            $table->date('warranty_date')->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->string('supplier_name')->nullable();
            $table->integer('quantity')->default(1);
            
            // Status
            $table->enum('condition', ['new', 'used', 'working', 'damaged', 'maintenance'])->default('new');
            $table->enum('status', ['available', 'assigned', 'maintenance', 'disposed'])->default('available');
            
            // Location & Department
            $table->foreignId('branch_id')->nullable()->constrained('company_branches')->onDelete('set null');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null'); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};