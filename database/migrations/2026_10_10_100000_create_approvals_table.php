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
        Schema::create('approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('priority', 20)->default('medium');
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            // Either one person decides, or anyone holding the role does.
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_role', 30)->nullable();
            $table->bigInteger('amount')->nullable();
            $table->string('currency', 3)->nullable();
            // Facts shown to the approver, captured when the request was made.
            $table->json('details')->nullable();
            // The record the request is about, if any (a task, an issue, a purchase request).
            $table->string('subject_type', 40)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            // The workflow step waiting on the decision, if a workflow asked.
            $table->foreignUuid('workflow_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('workflow_step_run_id')->nullable()->constrained()->nullOnDelete();
            // What happens when nobody decides in time: remind, or treat as rejected.
            $table->string('when_overdue', 20)->default('remind');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status', 'due_at']);
            $table->index(['organization_id', 'approver_id', 'status']);
            $table->index(['organization_id', 'approver_role', 'status']);
            $table->index(['organization_id', 'requester_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("alter table approvals add constraint approvals_status_check check (status in ('pending', 'changes_requested', 'approved', 'rejected', 'expired', 'cancelled'))");
            DB::statement('alter table approvals add constraint approvals_amount_check check (amount is null or amount >= 0)');
            DB::statement('alter table approvals add constraint approvals_approver_check check (approver_id is not null or approver_role is not null)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
