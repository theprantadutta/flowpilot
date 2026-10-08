<?php

namespace App\Http\Controllers;

use App\Actions\Issues\CreateIssue;
use App\Actions\Issues\DeleteIssue;
use App\Actions\Issues\UpdateIssue;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Http\Requests\Issues\StoreIssueRequest;
use App\Http\Requests\Issues\UpdateIssueRequest;
use App\Http\Resources\IssueResource;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\User;
use App\Support\Activity\ActivityPresenter;
use App\Support\FormOptions;
use App\Support\Search\Contains;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function index(Request $request, FormOptions $options): Response
    {
        Gate::authorize('viewAny', Issue::class);

        /** @var User $user */
        $user = $request->user();
        $project = $request->string('project')->toString();

        $filters = [
            'q' => $request->string('q')->trim()->limit(80, '')->toString(),
            // By default show what is still open.
            'status' => $request->query('status') === 'all' ? 'all' : (IssueStatus::tryFrom($request->string('status')->toString())->value ?? 'open'),
            'severity' => IssueSeverity::tryFrom($request->string('severity')->toString())?->value,
            'mine' => $request->boolean('mine'),
            'project' => Str::isUuid($project) ? $project : null,
        ];

        $issues = Issue::query()
            ->with(['assignee:id,name,avatar_path', 'project:id,organization_id,name'])
            ->withCount('comments')
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $number = ltrim(strtoupper($filters['q']), 'I-');

                $query->where(fn (Builder $query) => ctype_digit($number)
                    ? $query->where('number', (int) $number)->orWhere(fn (Builder $q) => Contains::any($q, ['title'], $filters['q']))
                    : Contains::any($query, ['title', 'description'], $filters['q']));
            })
            ->when($filters['status'] === 'open', fn (Builder $query) => $query->open())
            ->when(! in_array($filters['status'], ['open', 'all'], true), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['severity'], fn (Builder $query, string $severity) => $query->where('severity', $severity))
            ->when($filters['mine'], fn (Builder $query) => $query->where('assignee_id', $user->id))
            ->when($filters['project'], fn (Builder $query, string $project) => $query->where('project_id', $project))
            ->orderByRaw("case severity when 'critical' then 4 when 'high' then 3 when 'medium' then 2 else 1 end desc")
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('issues/Index', [
            'issues' => IssueResource::collection($issues),
            'filters' => $filters,
            'openBySeverity' => Issue::query()->open()->selectRaw('severity, count(*) as total')->groupBy('severity')->pluck('total', 'severity'),
            'severities' => IssueSeverity::options(),
            'statuses' => IssueStatus::options(),
            'members' => fn () => $options->members(),
            'projects' => fn () => $options->projects(),
            'can' => ['create' => $user->can('create', Issue::class)],
        ]);
    }

    public function store(StoreIssueRequest $request, CreateIssue $createIssue): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $issue = $createIssue->handle($user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$issue->reference()} reported."]);

        return back();
    }

    public function show(Request $request, Issue $issue, FormOptions $options, ActivityPresenter $presenter): Response
    {
        Gate::authorize('view', $issue);

        /** @var User $user */
        $user = $request->user();

        $issue->load(['project:id,organization_id,name', 'assignee:id,name,avatar_path', 'reporter:id,name,avatar_path']);

        return Inertia::render('issues/Show', [
            'issue' => new IssueResource($issue),
            'comments' => $issue->comments()->with('author:id,name,avatar_path')->get()
                ->map(fn (Comment $comment): array => $comment->toCommentArray($user)),
            'attachments' => $issue->attachments()->with('uploader:id,name')->get()
                ->map(fn (Attachment $attachment): array => [
                    ...$attachment->toFileArray(),
                    'can_delete' => $attachment->uploaded_by === $user->id || $user->can('update', $issue),
                ]),
            'activity' => Inertia::defer(fn () => ActivityLog::query()
                ->where('subject_type', $issue->getMorphClass())
                ->where('subject_id', $issue->id)
                ->with('actor:id,name,avatar_path')
                ->latest('created_at')
                ->limit(30)
                ->get()
                ->map(fn (ActivityLog $log): array => $presenter->present($log))),
            'severities' => IssueSeverity::options(),
            'statuses' => IssueStatus::options(),
            'members' => fn () => $options->members(),
            'projects' => fn () => $options->projects(),
        ]);
    }

    public function update(UpdateIssueRequest $request, Issue $issue, UpdateIssue $updateIssue): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateIssue->handle($issue, $user, $request->validated());

        return back();
    }

    public function destroy(Request $request, Issue $issue, DeleteIssue $deleteIssue): RedirectResponse
    {
        Gate::authorize('delete', $issue);

        /** @var User $user */
        $user = $request->user();
        $reference = $issue->reference();

        $deleteIssue->handle($issue, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$reference} deleted."]);

        return to_route('issues.index');
    }
}
