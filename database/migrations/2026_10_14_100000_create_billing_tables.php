<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // One current subscription per organization. Provider columns stay
        // empty until a payment provider is connected.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan', 30);
            $table->string('status', 20);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('trial_reminded_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            // Custom limits agreed for one organization (Enterprise), by limit key.
            $table->json('limit_overrides')->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('provider_subscription_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'trial_ends_at']);
        });

        Schema::create('billing_customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('legal_name')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->char('country', 2)->nullable();
            $table->string('tax_id', 40)->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('provider_customer_id')->nullable();
            $table->timestamps();
        });

        Schema::create('plan_change_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_plan', 30);
            $table->string('to_plan', 30);
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("alter table subscriptions add constraint subscriptions_plan_check check (plan in ('free', 'starter', 'business', 'enterprise'))");
            DB::statement("alter table subscriptions add constraint subscriptions_status_check check (status in ('trialing', 'active', 'past_due', 'canceled'))");
            DB::statement("alter table plan_change_requests add constraint plan_change_requests_status_check check (status in ('pending', 'approved', 'declined', 'withdrawn'))");
        }

        // Organizations that already exist start the same trial a new one gets.
        $trialEndsAt = now()->addDays((int) config('billing.trial_days', 14));

        DB::table('organizations')->orderBy('id')->each(function (object $organization) use ($trialEndsAt): void {
            DB::table('subscriptions')->insert([
                'id' => (string) Str::uuid7(),
                'organization_id' => $organization->id,
                'plan' => (string) config('billing.trial_plan', 'business'),
                'status' => 'trialing',
                'trial_ends_at' => $trialEndsAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_change_requests');
        Schema::dropIfExists('billing_customers');
        Schema::dropIfExists('subscriptions');
    }
};
