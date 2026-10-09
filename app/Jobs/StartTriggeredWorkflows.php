<?php

namespace App\Jobs;

use App\Enums\Limit;
use App\Enums\WorkflowStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workflow;
use App\Notifications\PlanNoticeNotification;
use App\Support\Billing\BillingContacts;
use App\Support\Billing\Entitlements;
use App\Support\Tenancy\Tenancy;
use App\Workflows\Engine\WorkflowEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Starts every active workflow listening for an event on a record.
 */
class StartTriggeredWorkflows implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(
        public string $organizationId,
        public string $trigger,
        public string $subjectType,
        public string $subjectId,
        public ?int $actorId = null,
        public ?string $sourceWorkflowId = null,
        public int $depth = 0,
        public ?string $occurrence = null,
    ) {}

    public function handle(Tenancy $tenancy, WorkflowEngine $engine, Entitlements $entitlements, BillingContacts $contacts): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null || ! $organization->isActive()) {
            return;
        }

        $tenancy->run($organization, function () use ($engine, $entitlements, $contacts, $organization): void {
            $workflows = Workflow::query()
                ->where('trigger_type', $this->trigger)
                ->where('status', WorkflowStatus::Active)
                ->whereNotNull('current_version_id')
                ->when($this->sourceWorkflowId, fn ($query, string $id) => $query->whereKeyNot($id))
                ->with('currentVersion')
                ->get();

            if ($workflows->isEmpty()) {
                return;
            }

            $subject = $this->subject();

            if ($subject === null) {
                return;
            }

            $actor = $this->actorId ? User::query()->find($this->actorId) : null;
            $key = implode(':', array_filter([$this->trigger, $this->subjectId, $this->occurrence]));

            foreach ($workflows as $workflow) {
                if (! $entitlements->hasRoom(Limit::WorkflowRunsPerMonth, 1, $organization)) {
                    $this->reportLimitReached($organization, $entitlements, $contacts);

                    return;
                }

                $engine->start($workflow, subject: $subject, actor: $actor, idempotencyKey: $key, depth: $this->depth);
            }
        });
    }

    /**
     * Tell the owners once a month that automation has stopped starting.
     */
    private function reportLimitReached(Organization $organization, Entitlements $entitlements, BillingContacts $contacts): void
    {
        $month = now($organization->timezone)->format('Y-m');

        if (! Cache::add("plan-limit:{$organization->id}:runs:{$month}", true, now()->addDays(32))) {
            return;
        }

        Log::notice('Workflow runs skipped: monthly limit reached', ['organization' => $organization->id]);

        Notification::send($contacts->for($organization), new PlanNoticeNotification(
            'Workflows have stopped starting this month',
            'Your '.$entitlements->plan($organization)->label().' plan includes '.Limit::WorkflowRunsPerMonth->describe($entitlements->limit(Limit::WorkflowRunsPerMonth, $organization)).', and they are used up. New events will not start workflows until next month, or until you upgrade.',
            'danger',
        ));
    }

    private function subject(): ?Model
    {
        $class = Relation::getMorphedModel($this->subjectType);

        if ($class === null || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class::query()->find($this->subjectId);
    }
}
