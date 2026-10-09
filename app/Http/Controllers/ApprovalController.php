<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\DecideApproval;
use App\Actions\Approvals\RequestApproval;
use App\Actions\Approvals\ResubmitApproval;
use App\Actions\Approvals\WithdrawApproval;
use App\Enums\ApprovalStatus;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\Priority;
use App\Enums\Role;
use App\Http\Requests\Approvals\DecideApprovalRequest;
use App\Http\Requests\Approvals\ResubmitApprovalRequest;
use App\Http\Requests\Approvals\StoreApprovalRequest;
use App\Http\Resources\ApprovalResource;
use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\User;
use App\Support\Activity\ActivityPresenter;
use App\Support\Billing\Entitlements;
use App\Support\FormOptions;
use App\Support\Search\Contains;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public const array VIEWS = ['waiting', 'mine', 'all'];

    public function __construct(private readonly Tenancy $tenancy) {}

    public function index(Request $request, FormOptions $options): Response
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $this->tenancy->membershipFor($user);
        $canDecide = $user->can(Permission::ApprovalsApprove->value) || $user->can(Permission::ApprovalsReject->value);
        $canSeeAll = $user->can(Permission::ApprovalsView->value);

        abort_unless($canSeeAll || $user->can(Permission::ApprovalsRequest->value), 403);

        $view = in_array($request->query('view'), self::VIEWS, true) ? (string) $request->query('view') : ($canDecide ? 'waiting' : 'mine');
        $view = ! $canSeeAll && $view === 'all' ? 'mine' : $view;
        $status = $request->query('status') === 'decided' || $request->query('status') === 'all' ? (string) $request->query('status') : 'open';
        $q = $request->string('q')->trim()->limit(80, '')->toString();

        $approvals = Approval::query()
            ->with(['requester:id,name,avatar_path', 'approver:id,name,avatar_path'])
            ->when($view === 'waiting', fn (Builder $query) => $query->waitingOn($user, $membership)->where(fn (Builder $query) => $query->whereNull('requester_id')->orWhere('requester_id', '!=', $user->id)))
            ->when($view === 'mine', fn (Builder $query) => $query->where('requester_id', $user->id))
            ->when($view !== 'waiting' && $status === 'open', fn (Builder $query) => $query->whereIn('status', ApprovalStatus::openValues()))
            ->when($status === 'decided', fn (Builder $query) => $query->whereNotIn('status', ApprovalStatus::openValues()))
            ->when($q !== '', function (Builder $query) use ($q): void {
                $number = ltrim(strtoupper($q), 'A-');

                $query->where(fn (Builder $query) => ctype_digit($number)
                    ? $query->where('number', (int) $number)->orWhere(fn (Builder $q2) => Contains::any($q2, ['title'], $q))
                    : Contains::any($query, ['title', 'description'], $q));
            })
            // Most urgent first: overdue, then soonest due.
            ->orderByRaw("case when status in ('pending', 'changes_requested') then 0 else 1 end")
            ->orderByRaw('case when due_at is null then 1 else 0 end')
            ->orderBy('due_at')
            ->orderByDesc('number')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('approvals/Index', [
            'approvals' => ApprovalResource::collection($approvals),
            'filters' => ['view' => $view, 'status' => $status, 'q' => $q],
            'counts' => [
                'waiting' => $canDecide ? Approval::query()->waitingOn($user, $membership)->where(fn (Builder $query) => $query->whereNull('requester_id')->orWhere('requester_id', '!=', $user->id))->count() : 0,
                'mine' => Approval::query()->where('requester_id', $user->id)->whereIn('status', ApprovalStatus::openValues())->count(),
            ],
            'priorities' => Priority::options(),
            'roles' => fn () => array_values(array_map(
                fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()],
                array_filter(Role::cases(), fn (Role $role): bool => $role !== Role::Owner && $role->allows(Permission::ApprovalsApprove)),
            )),
            'members' => fn () => array_values(array_filter($options->members(), fn (array $member): bool => $member['id'] !== $user->id)),
            'can' => [
                'create' => $user->can('create', Approval::class),
                'decide' => $canDecide,
                'viewAll' => $canSeeAll,
            ],
        ]);
    }

    public function store(StoreApprovalRequest $request, RequestApproval $requestApproval, Entitlements $entitlements): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $entitlements->ensure(Feature::Approvals, 'title');

        $approval = $requestApproval->handle($user, $request->approvalAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$approval->reference()} sent for approval."]);

        return to_route('approvals.show', ['approval' => $approval->id]);
    }

    public function show(Request $request, Approval $approval, ActivityPresenter $presenter): Response
    {
        Gate::authorize('view', $approval);

        /** @var User $user */
        $user = $request->user();

        $approval->load(['requester:id,name,avatar_path', 'approver:id,name,avatar_path', 'decider:id,name,avatar_path']);

        return Inertia::render('approvals/Show', [
            'approval' => (new ApprovalResource($approval))->resolve($request),
            'comments' => $approval->comments()->with('author:id,name,avatar_path')->get()
                ->map(fn (Comment $comment): array => $comment->toCommentArray($user)),
            'attachments' => $approval->attachments()->with('uploader:id,name')->get()
                ->map(fn (Attachment $attachment): array => [
                    ...$attachment->toFileArray(),
                    'can_delete' => $attachment->uploaded_by === $user->id || $user->can('update', $approval),
                ]),
            'history' => Inertia::defer(fn () => ActivityLog::query()
                ->where('subject_type', $approval->getMorphClass())
                ->where('subject_id', $approval->id)
                ->with('actor:id,name,avatar_path')
                ->oldest('created_at')
                ->limit(100)
                ->get()
                ->map(fn (ActivityLog $log): array => $presenter->present($log))),
            'can' => [
                'approve' => $user->can('approve', $approval),
                'reject' => $user->can('reject', $approval),
                'requestChanges' => $user->can('requestChanges', $approval),
                'resubmit' => $user->can('resubmit', $approval),
                'withdraw' => $user->can('withdraw', $approval),
                'update' => $user->can('update', $approval),
                'deciding_for_someone_else' => $approval->status === ApprovalStatus::Pending
                    && ! $approval->isWaitingOn($user, $this->tenancy->membershipFor($user))
                    && $user->can(Permission::ApprovalsOverride->value),
            ],
        ]);
    }

    public function decide(DecideApprovalRequest $request, Approval $approval, DecideApproval $decideApproval): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $approval = $decideApproval->handle($approval, $user, $request->decision(), $request->note());

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($approval->status) {
            ApprovalStatus::Approved => "{$approval->reference()} approved.",
            ApprovalStatus::Rejected => "{$approval->reference()} rejected.",
            default => "Sent {$approval->reference()} back for changes.",
        }]);

        return back();
    }

    public function resubmit(ResubmitApprovalRequest $request, Approval $approval, ResubmitApproval $resubmitApproval): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $resubmitApproval->handle($approval, $user, $request->changes(), $request->note());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$approval->reference()} sent back for a decision."]);

        return back();
    }

    public function withdraw(Request $request, Approval $approval, WithdrawApproval $withdrawApproval): RedirectResponse
    {
        Gate::authorize('withdraw', $approval);

        /** @var User $user */
        $user = $request->user();

        $withdrawApproval->handle($approval, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$approval->reference()} withdrawn."]);

        return back();
    }
}
