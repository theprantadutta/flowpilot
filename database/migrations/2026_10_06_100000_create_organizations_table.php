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
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('logo_path')->nullable();
            $table->string('website')->nullable();
            $table->string('industry', 40)->nullable();
            $table->string('company_size', 20)->nullable();
            $table->string('primary_use_case', 40)->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('locale', 12)->default('en');
            $table->char('currency', 3)->default('USD');
            $table->string('date_format', 20)->default('M j, Y');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->string('status', 20)->default('active');
            $table->string('department', 80)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['organization_id', 'role']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 30);
            $table->string('department', 80)->nullable();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'email']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('email');
            $table->string('timezone', 64)->nullable()->after('avatar_path');
            $table->boolean('is_platform_admin')->default(false)->after('timezone');
            $table->foreignUuid('last_organization_id')->nullable()->after('is_platform_admin')
                ->constrained('organizations')->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("alter table organizations add constraint organizations_status_check check (status in ('active', 'suspended'))");
            DB::statement("alter table organization_memberships add constraint organization_memberships_status_check check (status in ('active', 'suspended'))");
            DB::statement('alter table organizations add constraint organizations_currency_check check (currency = upper(currency))');
            // One open invitation per email address per organization.
            DB::statement('create unique index invitations_open_unique on invitations (organization_id, lower(email)) where accepted_at is null and revoked_at is null');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_organization_id');
            $table->dropColumn(['avatar_path', 'timezone', 'is_platform_admin']);
        });

        Schema::dropIfExists('invitations');
        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('organizations');
    }
};
