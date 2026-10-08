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
        Schema::create('workflows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('trigger_type', 60);
            // The definition being edited. Publishing copies it into an immutable version.
            $table->json('draft_definition');
            $table->foreignUuid('current_version_id')->nullable();
            // Template the workflow was created from, if any.
            $table->string('template', 60)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'trigger_type', 'status']);
        });

        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('trigger_type', 60);
            $table->json('definition');
            $table->string('checksum', 64);
            $table->text('notes')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['workflow_id', 'version']);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('workflow_versions')->nullOnDelete();
        });

        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workflow_id')->constrained()->cascadeOnDelete();
            // Runs always execute the exact version they started on. "No action"
            // (checked at the end of the statement) lets a workflow delete cascade
            // through its runs and versions together.
            $table->foreignUuid('workflow_version_id')->constrained()->noActionOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('status', 20)->default('pending');
            $table->string('trigger_type', 60);
            $table->string('subject_type', 40)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->json('input')->nullable();
            // Everything the run has learned so far: input, subject snapshot, step outputs.
            $table->json('context')->nullable();
            $table->string('current_node_id', 64)->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            // Stops the same event from starting the same workflow twice.
            $table->string('idempotency_key', 120)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            $table->unique(['workflow_id', 'idempotency_key']);
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['workflow_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('workflow_step_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workflow_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('node_id', 64);
            $table->string('node_type', 30);
            $table->string('label');
            $table->string('status', 20)->default('pending');
            // Which outgoing path the step chose (true/false, approved/rejected, a branch case).
            $table->string('outcome', 64)->nullable();
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            // A record the step is waiting on, e.g. an approval.
            $table->string('waiting_on_type', 40)->nullable();
            $table->uuid('waiting_on_id')->nullable();
            $table->timestamp('resume_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_run_id', 'sequence']);
            $table->index(['status', 'resume_at']);
            $table->index(['waiting_on_type', 'waiting_on_id']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workflow_step_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 80);
            $table->string('url', 2048);
            $table->json('payload');
            // Sent as Idempotency-Key so receivers can ignore retries they already handled.
            $table->uuid('delivery_key')->unique();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            // Signs outgoing webhook payloads so receivers can verify they came from FlowPilot.
            $table->text('webhook_secret')->nullable()->after('settings');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("alter table workflows add constraint workflows_status_check check (status in ('draft', 'active', 'paused', 'archived'))");
            DB::statement("alter table workflow_runs add constraint workflow_runs_status_check check (status in ('pending', 'running', 'waiting', 'completed', 'failed', 'cancelled'))");
            DB::statement("alter table workflow_step_runs add constraint workflow_step_runs_status_check check (status in ('pending', 'running', 'waiting', 'completed', 'failed', 'skipped', 'cancelled'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('workflow_step_runs');
        Schema::dropIfExists('workflow_runs');
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('workflow_versions');
        Schema::dropIfExists('workflows');
    }
};
