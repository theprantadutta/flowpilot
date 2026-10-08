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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Null for platform-level events that do not belong to one organization.
            $table->foreignUuid('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // "user", "system" (scheduler, workflow engine) or "ai".
            $table->string('actor_type', 20)->default('user');
            $table->string('action', 80);
            $table->string('subject_type', 60)->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->string('subject_label')->nullable();
            // Optional parent the event should also appear under (a task's project, a step's workflow run).
            $table->string('context_type', 60)->nullable();
            $table->string('context_id', 36)->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'action']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['context_type', 'context_id']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
