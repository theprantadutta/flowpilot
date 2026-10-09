<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Models\AiBrief;
use App\Models\Approval;
use App\Models\Attachment;
use App\Models\BillingCustomer;
use App\Models\Comment;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStockLevel;
use App\Models\Invitation;
use App\Models\Issue;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PlanChangeRequest;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\ReportExport;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\WorkflowStepRun;
use App\Models\WorkflowVersion;
use App\Notifications\Channels\TenantDatabaseChannel;
use App\Support\Ai\AiProvider;
use App\Support\Ai\FreewayProvider;
use App\Support\Billing\Entitlements;
use App\Support\Tenancy\Tenancy;
use App\Workflows\Triggers\TriggerRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Log\Context\Repository as ContextRepository;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped, so each request and each queued job starts with no tenant.
        $this->app->scoped(Tenancy::class);

        // Database notifications record the organization they belong to.
        $this->app->bind(DatabaseChannel::class, TenantDatabaseChannel::class);

        // Stateless catalog of what can start a workflow.
        $this->app->singleton(TriggerRegistry::class);

        // What the plan allows, cached for one request or job.
        $this->app->scoped(Entitlements::class);

        // The AI provider behind AiOperationsService, chosen by config.
        $this->app->bind(AiProvider::class, function (): AiProvider {
            $name = (string) config('ai.provider', 'freeway');

            return match ($name) {
                'freeway' => new FreewayProvider((array) config('ai.providers.freeway', [])),
                default => throw new InvalidArgumentException("Unknown AI provider [{$name}]. Set AI_PROVIDER to freeway."),
            };
        });
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
        // Record ids are UUIDs. Anything else is a 404 before it reaches the
        // database (PostgreSQL rejects malformed UUIDs with an error).
        Route::patterns(array_fill_keys(
            ['project', 'task', 'issue', 'comment', 'attachment', 'checklistItem', 'blocker', 'member', 'invitation', 'workflow', 'version', 'run', 'approval', 'item', 'supplier', 'location', 'category', 'purchaseRequest', 'export', 'brief', 'planRequest', 'platformPlanRequest'],
            '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}',
        ));

        Route::bind('member', fn (string $value): OrganizationMembership => $this->app->make(Tenancy::class)
            ->currentOrFail()
            ->memberships()
            ->with('user')
            ->findOrFail($value));

        Route::bind('invitation', fn (string $value): Invitation => $this->app->make(Tenancy::class)
            ->currentOrFail()
            ->invitations()
            ->findOrFail($value));

        // Platform administration works across organizations, outside any tenant.
        Route::bind('platformPlanRequest', fn (string $value): PlanChangeRequest => PlanChangeRequest::withoutOrganizationScope()->findOrFail($value));
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
            'notification' => Notification::class,
            'project' => Project::class,
            'task' => Task::class,
            'task_checklist_item' => TaskChecklistItem::class,
            'issue' => Issue::class,
            'comment' => Comment::class,
            'attachment' => Attachment::class,
            'workflow' => Workflow::class,
            'workflow_version' => WorkflowVersion::class,
            'workflow_run' => WorkflowRun::class,
            'workflow_step_run' => WorkflowStepRun::class,
            'webhook_delivery' => WebhookDelivery::class,
            'approval' => Approval::class,
            'inventory_category' => InventoryCategory::class,
            'inventory_item' => InventoryItem::class,
            'inventory_location' => InventoryLocation::class,
            'inventory_stock_level' => InventoryStockLevel::class,
            'inventory_movement' => InventoryMovement::class,
            'supplier' => Supplier::class,
            'purchase_request' => PurchaseRequest::class,
            'report_export' => ReportExport::class,
            'ai_brief' => AiBrief::class,
            'subscription' => Subscription::class,
            'billing_customer' => BillingCustomer::class,
            'plan_change_request' => PlanChangeRequest::class,
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
