<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing workspace: Production / Design / Content boards (each with its own stages),
 * per-card links, comments and history, plus the posting calendar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_stages', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20)->index(); // production | design | content
            $table->string('name');
            $table->string('color', 20)->default('#64748b');
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_done')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('marketing_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20);
            $table->string('type', 20)->nullable(); // production only: video | photo | reel | event
            $table->string('title');
            $table->foreignId('stage_id')->constrained('marketing_stages');
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shooting_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('editing_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('deliverables')->nullable();
            $table->json('channels')->nullable();
            $table->string('tov', 50)->nullable();
            $table->text('reference')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'stage_id', 'position']);
        });

        Schema::create('marketing_task_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('marketing_tasks')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('label')->nullable();
            $table->string('kind', 20)->default('other');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketing_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('marketing_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('marketing_task_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('marketing_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('marketing_calendar_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained('marketing_tasks')->nullOnDelete();
            $table->foreignId('link_id')->nullable()->constrained('marketing_task_links')->nullOnDelete();
            $table->string('title');
            $table->string('platform', 30);
            $table->date('scheduled_date')->index();
            $table->boolean('is_posted')->default(false);
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        $stages = [
            ['name' => 'Pending',     'color' => '#f5b300', 'icon' => 'lucide:hourglass',     'is_done' => false],
            ['name' => 'Working',     'color' => '#2563eb', 'icon' => 'lucide:loader',        'is_done' => false],
            ['name' => 'On Revision', 'color' => '#9b87f5', 'icon' => 'lucide:rotate-ccw',    'is_done' => false],
            ['name' => 'Done',        'color' => '#16a34a', 'icon' => 'lucide:check-circle-2', 'is_done' => true],
            ['name' => 'Paused',      'color' => '#475569', 'icon' => 'lucide:pause',         'is_done' => false],
            ['name' => 'Cancelled',   'color' => '#dc2626', 'icon' => 'lucide:x',             'is_done' => false],
        ];

        $now = now();
        foreach (['production', 'design', 'content'] as $category) {
            foreach ($stages as $i => $stage) {
                DB::table('marketing_stages')->insert($stage + [
                    'category' => $category,
                    'sort_order' => $i,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_calendar_posts');
        Schema::dropIfExists('marketing_task_histories');
        Schema::dropIfExists('marketing_task_comments');
        Schema::dropIfExists('marketing_task_links');
        Schema::dropIfExists('marketing_tasks');
        Schema::dropIfExists('marketing_stages');
    }
};
