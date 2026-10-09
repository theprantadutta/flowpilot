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
        Schema::create('report_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report', 40);
            $table->string('format', 10)->default('csv');
            $table->json('parameters');
            $table->string('status', 20)->default('queued');
            $table->string('disk', 40)->nullable();
            $table->string('path')->nullable();
            $table->string('filename')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'created_at']);
            $table->index('expires_at');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("alter table report_exports add constraint report_exports_status_check check (status in ('queued', 'processing', 'completed', 'failed'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
