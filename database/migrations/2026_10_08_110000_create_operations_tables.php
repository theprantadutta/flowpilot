<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Per-organization sequences for human-friendly references (T-42, I-7, PR-1842).
        Schema::create('organization_counters', function (Blueprint $table) {
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->unsignedBigInteger('value')->default(0);

            $table->primary(['organization_id', 'name']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('planning');
            $table->string('priority', 20)->default('medium');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('budget_amount')->nullable();
            $table->char('budget_currency', 3)->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'due_date']);
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['project_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('todo');
            $table->string('priority', 20)->default('medium');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            // Order within a board column. Fractional so a card can be dropped
            // between two others without renumbering the column.
            $table->double('position')->default(0);
            $table->json('tags')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status', 'position']);
            $table->index(['organization_id', 'assignee_id', 'status']);
            $table->index(['organization_id', 'due_date']);
            $table->index('project_id');
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->string('body', 500);
            $table->boolean('is_done')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'position']);
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            // The task that is waiting…
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            // …on this one to be done first.
            $table->foreignUuid('depends_on_id')->constrained('tasks')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['task_id', 'depends_on_id']);
            $table->index('depends_on_id');
        });

        Schema::create('issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity', 20)->default('medium');
            $table->string('status', 20)->default('open');
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status', 'severity']);
            $table->index(['organization_id', 'assignee_id']);
            $table->index('project_id');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('commentable_type', 40);
            $table->uuid('commentable_id');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['commentable_type', 'commentable_id', 'created_at']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('attachable_type', 40);
            $table->uuid('attachable_id');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
            $table->index(['organization_id', 'created_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("alter table projects add constraint projects_status_check check (status in ('planning', 'active', 'on_hold', 'completed', 'archived'))");
            DB::statement('alter table projects add constraint projects_dates_check check (start_date is null or due_date is null or due_date >= start_date)');
            DB::statement("alter table tasks add constraint tasks_status_check check (status in ('backlog', 'todo', 'in_progress', 'blocked', 'review', 'done'))");
            DB::statement("alter table tasks add constraint tasks_priority_check check (priority in ('low', 'medium', 'high', 'urgent'))");
            DB::statement('alter table task_dependencies add constraint task_dependencies_not_self check (task_id <> depends_on_id)');
            DB::statement("alter table issues add constraint issues_severity_check check (severity in ('critical', 'high', 'medium', 'low'))");
            DB::statement("alter table issues add constraint issues_status_check check (status in ('open', 'investigating', 'resolved', 'closed'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('organization_counters');
    }
};
