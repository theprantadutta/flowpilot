<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Log\Context\Repository as ContextRepository;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped, so each request and each queued job starts with no tenant.
        $this->app->scoped(Tenancy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureModels();
        $this->configureTenancy();
        $this->configureAuthorization();
        $this->configureRouteBindings();
    }

    /**
     * Memberships and invitations are not auto-scoped models (the tenant is
     * resolved from them), so their route bindings are scoped here instead.
     */
    protected function configureRouteBindings(): void
    {
        Route::bind('member', fn (string $value): OrganizationMembership => $this->app->make(Tenancy::class)
            ->currentOrFail()
            ->memberships()
            ->with('user')
            ->findOrFail($value));

        Route::bind('invitation', fn (string $value): Invitation => $this->app->make(Tenancy::class)
            ->currentOrFail()
            ->invitations()
            ->findOrFail($value));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Catch the mistakes that turn into N+1 queries and silent data loss, and
     * keep stored polymorphic types independent of class names.
     */
    protected function configureModels(): void
    {
        Model::shouldBeStrict(! app()->isProduction());

        Relation::enforceMorphMap([
            'user' => User::class,
            'organization' => Organization::class,
            'membership' => OrganizationMembership::class,
            'invitation' => Invitation::class,
            'activity_log' => ActivityLog::class,
        ]);
    }

    /**
     * Carry the current organization into queued jobs: the id travels in the
     * job's context and is turned back into a tenant when the job starts.
     */
    protected function configureTenancy(): void
    {
        Context::hydrated(function (ContextRepository $context): void {
            $organizationId = $context->get(Tenancy::CONTEXT_KEY);

            if (! is_string($organizationId)) {
                return;
            }

            $tenancy = $this->app->make(Tenancy::class);

            if ($tenancy->id() === $organizationId) {
                return;
            }

            $organization = Organization::query()->find($organizationId);

            if ($organization) {
                $tenancy->set($organization);
            }
        });
    }

    /**
     * Permission names (e.g. "projects.update") resolve against the user's
     * membership of the current organization. Anything else falls through to
     * the model policies.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            $permission = Permission::tryFrom($ability);

            if ($permission === null) {
                return null;
            }

            return $this->app->make(Tenancy::class)->membershipFor($user)?->allows($permission) ?? false;
        });
    }
}
