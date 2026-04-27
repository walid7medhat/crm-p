<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // Annual Leave, Sick Leave, etc.
            $table->string('slug', 100)->unique();
            $table->enum('payment_type', ['paid', 'half_paid', 'unpaid'])->default('paid');
            $table->integer('default_days')->default(0); // 30 days for annual, 15 for sick etc.
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Insert default data
        DB::table('leave_types')->insert([
            ['name' => 'Annual Leave - Paid Leave', 'slug' => 'annual_paid', 'payment_type' => 'paid', 'default_days' => 30, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sick Leave - Fully Paid', 'slug' => 'sick_fully_paid', 'payment_type' => 'paid', 'default_days' => 15, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sick Leave - Half Paid', 'slug' => 'sick_half_paid', 'payment_type' => 'half_paid', 'default_days' => 30, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sick Leave - Unpaid', 'slug' => 'sick_unpaid', 'payment_type' => 'unpaid', 'default_days' => 45, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sabbatical Leave', 'slug' => 'sabbatical', 'payment_type' => 'unpaid', 'default_days' => 15, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Maternity Leave - Fully Paid', 'slug' => 'maternity_fully_paid', 'payment_type' => 'paid', 'default_days' => 45, 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Maternity Leave - Half Paid', 'slug' => 'maternity_half_paid', 'payment_type' => 'half_paid', 'default_days' => 15, 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Paternity Leave', 'slug' => 'paternity', 'payment_type' => 'paid', 'default_days' => 15, 'sort_order' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Unpaid Leave', 'slug' => 'unpaid', 'payment_type' => 'unpaid', 'default_days' => 10, 'sort_order' => 9, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Grievance Leave', 'slug' => 'grievance', 'payment_type' => 'paid', 'default_days' => 10, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Compensation Off', 'slug' => 'comp_off', 'payment_type' => 'paid', 'default_days' => 10, 'sort_order' => 11, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Study Leave / Training Leave', 'slug' => 'study_training', 'payment_type' => 'paid', 'default_days' => 30, 'sort_order' => 12, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Hajj and Umrah Leave', 'slug' => 'hajj_umrah', 'payment_type' => 'paid', 'default_days' => 10, 'sort_order' => 13, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Work From Home', 'slug' => 'work_from_home', 'payment_type' => 'paid', 'default_days' => 30, 'sort_order' => 14, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'PTD Leaves', 'slug' => 'ptd', 'payment_type' => 'paid', 'default_days' => 15, 'sort_order' => 15, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};