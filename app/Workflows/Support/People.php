<?php

namespace App\Workflows\Support;

use App\Enums\IssueStatus;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Turns the "who" of a step into members of the organization.
 *
 * A step names people as a list of picks:
 *
 *   { type: "member", id: 12 }          a specific member
 *   { type: "role", role: "finance" }   everyone holding a role
 *   { type: "starter" }                 whoever started the run
 *   { type: "assignee" }                the record's assignee
 *   { type: "field", field: "input.approver" }  a person chosen in the run's input
 *
 * Only active members of the organization are ever returned, whatever the
 * definition says.
 */
class People
{
    public const array TYPES = ['member', 'role', 'starter', 'assignee', 'field'];

    public const int MAX_PICKS = 20;

    /**
     * @param  mixed  $picks  The step's recipient list.
     * @param  array<string, mixed>  $context  The run context.
     * @return Collection<int, User>
     */
    public function resolve(Organization $organization, mixed $picks, array $context): Collection
    {
        $userIds = [];
        $roles = [];

        foreach (self::normalize($picks) as $pick) {
            match ($pick['type']) {
                'member' => $userIds[] = $pick['id'],
                'role' => $roles[] = $pick['role'],
                'starter' => $userIds[] = Arr::get($context, 'actor.id'),
                'assignee' => $userIds[] = Arr::get($context, 'subject.assignee_id'),
                'field' => $userIds[] = Arr::get($context, $pick['field']),
                default => null,
            };
        }

        $userIds = array_values(array_unique(array_filter($userIds, fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))));
        $userIds = array_map(intval(...), $userIds);

        if ($userIds === [] && $roles === []) {
            return new Collection;
        }

        return OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('status', MembershipStatus::Active)
            ->where(fn ($query) => $query->whereIn('user_id', $userIds)->orWhereIn('role', $roles))
            ->with('user')
            ->get()
            ->map(fn (OrganizationMembership $membership): User => $membership->user)
            ->unique('id')
            ->values();
    }

    /**
     * One person to give work to. For a role, the member of that role with the
     * least open work, so assignments spread across the team.
     *
     * @param  array<string, mixed>  $context
     */
    public function pickOne(Organization $organization, mixed $pick, array $context): ?User
    {
        $pick = self::normalize([$pick])[0] ?? null;

        if ($pick === null) {
            return null;
        }

        if ($pick['type'] !== 'role') {
            return $this->resolve($organization, [$pick], $context)->first();
        }

        $members = $this->resolve($organization, [$pick], $context);

        if ($members->isEmpty()) {
            return null;
        }

        $ids = $members->pluck('id')->all();

        $openTasks = Task::query()->whereIn('assignee_id', $ids)->where('status', '!=', TaskStatus::Done->value)
            ->selectRaw('assignee_id, count(*) as total')->groupBy('assignee_id')->pluck('total', 'assignee_id');
        $openIssues = Issue::query()->whereIn('assignee_id', $ids)->whereIn('status', array_map(fn (IssueStatus $status): string => $status->value, IssueStatus::open()))
            ->selectRaw('assignee_id, count(*) as total')->groupBy('assignee_id')->pluck('total', 'assignee_id');

        return $members
            ->sortBy([
                fn (User $a, User $b): int => ((int) ($openTasks[$a->id] ?? 0) + (int) ($openIssues[$a->id] ?? 0))
                    <=> ((int) ($openTasks[$b->id] ?? 0) + (int) ($openIssues[$b->id] ?? 0)),
                fn (User $a, User $b): int => $a->id <=> $b->id,
            ])
            ->first();
    }

    /**
     * @return list<array{type: string, id: int, role: string, field: string}>
     */
    public static function normalize(mixed $picks): array
    {
        $normalized = [];

        foreach (is_array($picks) ? $picks : [] as $pick) {
            if (! is_array($pick) || ! in_array($pick['type'] ?? null, self::TYPES, true)) {
                continue;
            }

            $normalized[] = [
                'type' => (string) $pick['type'],
                'id' => $pick['type'] === 'member' && is_numeric($pick['id'] ?? null) ? (int) $pick['id'] : 0,
                'role' => $pick['type'] === 'role' && is_string($pick['role'] ?? null) ? $pick['role'] : '',
                'field' => $pick['type'] === 'field' && is_string($pick['field'] ?? null) ? $pick['field'] : '',
            ];
        }

        return $normalized;
    }

    /**
     * Problems with a list of picks.
     *
     * @param  list<int>  $memberIds  Active members of the organization.
     * @param  list<string>  $personFields  Paths of person fields the run can read.
     * @param  bool  $hasSubject  Whether runs have a record with an assignee.
     * @return list<string>
     */
    public static function validate(mixed $picks, array $memberIds, array $personFields, bool $hasSubject, string $what = 'people'): array
    {
        $raw = is_array($picks) ? $picks : [];
        $normalized = self::normalize($raw);
        $errors = [];

        if ($normalized === []) {
            return ['Choose who to send this to.'];
        }

        if (count($raw) !== count($normalized)) {
            $errors[] = "Some of the {$what} could not be understood.";
        }

        if (count($normalized) > self::MAX_PICKS) {
            $errors[] = 'Choose at most '.self::MAX_PICKS." {$what}.";
        }

        foreach ($normalized as $pick) {
            $problem = match ($pick['type']) {
                'member' => in_array($pick['id'], $memberIds, true) ? null : 'A chosen person is no longer a member.',
                'role' => Role::tryFrom($pick['role']) ? null : 'A chosen role does not exist.',
                'assignee' => $hasSubject ? null : 'This trigger has no record, so there is no assignee to use.',
                'field' => in_array($pick['field'], $personFields, true) ? null : 'A chosen person field is not available.',
                default => null,
            };

            if ($problem !== null) {
                $errors[] = $problem;
            }
        }

        return array_values(array_unique($errors));
    }
}
