<?php

namespace Database\Seeders;

use App\Actions\Approvals\DecideApproval;
use App\Actions\Approvals\RequestApproval;
use App\Actions\Billing\ChangePlan;
use App\Actions\Comments\AddComment;
use App\Actions\Inventory\ChangePurchaseRequestStatus;
use App\Actions\Inventory\CreateInventoryItem;
use App\Actions\Inventory\ReceivePurchaseRequest;
use App\Actions\Inventory\RecordMovement;
use App\Actions\Inventory\SaveInventoryRecord;
use App\Actions\Inventory\SubmitPurchaseRequest;
use App\Actions\Issues\CreateIssue;
use App\Actions\Organizations\CreateOrganization;
use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Actions\Workflows\ChangeWorkflowStatus;
use App\Actions\Workflows\CreateWorkflow;
use App\Actions\Workflows\PublishWorkflow;
use App\Actions\Workflows\StartWorkflowRun;
use App\Enums\ApprovalStatus;
use App\Enums\MembershipStatus;
use App\Enums\MovementType;
use App\Enums\Plan;
use App\Enums\Priority;
use App\Enums\PurchaseRequestStatus;
use App\Enums\Role;
use App\Enums\WorkflowStatus;
use App\Models\Approval;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\Workflow;
use App\Support\Activity\ActivityLogger;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use RuntimeException;

/**
 * Northstar Manufacturing: a realistic organization for demos and local
 * development, built through FlowPilot's own actions so numbering, workflow
 * runs, approvals, notifications and the activity log are exactly what real
 * use produces. About a month of history ends with work waiting on people,
 * ready for the purchase approval demo.
 *
 * Development only. It refuses to run in production, needs DEMO_PASSWORD,
 * and does nothing if Northstar already exists.
 */
class NorthstarDemoSeeder extends Seeder
{
    public const string SLUG = 'northstar-manufacturing';

    public const string DOMAIN = 'northstar.test';

    public const string PLATFORM_ADMIN_EMAIL = 'support@flowpilot.test';

    /**
     * Demo people: key => [name, role, department, job title].
     *
     * @var array<string, array{0: string, 1: Role, 2: string, 3: string}>
     */
    private const array PEOPLE = [
        'owner' => ['Daniel Okoro', Role::Owner, 'Operations', 'Managing director'],
        'admin' => ['Maya Chen', Role::Admin, 'HR', 'People and systems lead'],
        'manager' => ['Marcus Reyes', Role::Manager, 'Operations', 'Production manager'],
        'finance' => ['Priya Nair', Role::Finance, 'Finance', 'Finance controller'],
        'procurement' => ['Tom Becker', Role::Procurement, 'Procurement', 'Buyer'],
        'operations' => ['Sam Rivera', Role::Operations, 'Operations', 'Shift supervisor'],
        'employee' => ['Dana Whitfield', Role::Employee, 'Operations', 'Maintenance technician'],
    ];

    /**
     * @var array<string, User>
     */
    private array $people = [];

    /**
     * @var array<string, InventoryLocation>
     */
    private array $locations = [];

    /**
     * @var array<string, Supplier>
     */
    private array $suppliers = [];

    /**
     * @var array<string, InventoryCategory>
     */
    private array $categories = [];

    /**
     * @var array<string, InventoryItem>
     */
    private array $items = [];

    /**
     * @var array<string, Project>
     */
    private array $projects = [];

    /**
     * @var array<string, Task>
     */
    private array $tasks = [];

    private CarbonImmutable $today;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The Northstar demo is for development and demos only. It never runs in production.');
        }

        $password = config('flowpilot.demo.password');

        if (! is_string($password) || mb_strlen($password) < 8) {
            throw new RuntimeException('Set DEMO_PASSWORD (at least 8 characters) in .env before seeding the Northstar demo.');
        }

        if (Organization::query()->where('slug', self::SLUG)->exists() || User::query()->where('email', 'like', '%@'.self::DOMAIN)->exists()) {
            $this->command->warn('Northstar Manufacturing is already seeded. Nothing changed.');

            return;
        }

        // Run triggered workflows and notifications inline, and keep demo mail
        // out of any real mailbox.
        $previous = ['queue.default' => config('queue.default'), 'mail.default' => config('mail.default')];
        config(['queue.default' => 'sync', 'mail.default' => 'array']);

        // The story ends at the most recent 16:30 that has already passed, so
        // no record is dated in the future.
        $now = CarbonImmutable::now();
        $this->today = $now->setTime(16, 30)->greaterThan($now) ? $now->subDay()->setTime(16, 30) : $now->setTime(16, 30);

        try {
            $this->at(30, 9, 0);
            $organization = $this->organization($password);

            app(Tenancy::class)->run($organization, function () use ($organization): void {
                $this->inventory();
                $this->workflows();
                $this->projects();
                $this->history($organization);
            }, OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $this->person('owner')->id)->sole());

            $this->platformAdmin($password);
        } finally {
            Date::setTestNow();
            config($previous);
        }

        $this->command->info('Northstar Manufacturing is ready. Sign in with any of these and the DEMO_PASSWORD from .env:');
        $this->command->table(['Name', 'Email', 'Role'], [
            ...array_map(fn (string $key): array => [self::PEOPLE[$key][0], $this->person($key)->email, self::PEOPLE[$key][1]->label()], array_keys(self::PEOPLE)),
            ['FlowPilot support', self::PLATFORM_ADMIN_EMAIL, 'Platform administrator'],
        ]);
    }

    /**
     * The company, its people and its plan.
     */
    private function organization(string $password): Organization
    {
        foreach (self::PEOPLE as $key => [$name]) {
            $user = User::query()->create([
                'name' => $name,
                'email' => strtolower(explode(' ', $name)[0].'.'.explode(' ', $name)[1]).'@'.self::DOMAIN,
                'password' => $password,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $this->people[$key] = $user;
        }

        $owner = $this->person('owner');
        $organization = app(CreateOrganization::class)->handle($owner, [
            'name' => 'Northstar Manufacturing',
            'industry' => 'manufacturing',
            'company_size' => '51-200',
            'primary_use_case' => 'purchasing',
            'timezone' => 'America/Chicago',
            'currency' => 'USD',
            'website' => 'https://northstar.example',
        ]);

        OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $owner->id)
            ->update(['department' => self::PEOPLE['owner'][2], 'job_title' => self::PEOPLE['owner'][3]]);

        foreach (self::PEOPLE as $key => [, $role, $department, $title]) {
            if ($key === 'owner') {
                continue;
            }

            $user = $this->person($key);
            $membership = OrganizationMembership::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => MembershipStatus::Active,
                'department' => $department,
                'job_title' => $title,
                'invited_by' => $owner->id,
                'joined_at' => now(),
                'last_active_at' => $this->today,
            ]);
            $user->forceFill(['last_organization_id' => $organization->id])->save();

            app(Tenancy::class)->run($organization, fn () => app(ActivityLogger::class)->log(
                'member.joined',
                $membership,
                ['role' => $role->value, 'name' => $user->name],
                actor: $user,
            ));
        }

        app(ChangePlan::class)->handle($organization, Plan::Business, $owner, 'Northstar chose Business for approvals, the AI brief and webhooks.', notify: false);

        return $organization;
    }

    /**
     * Where stock lives, who supplies it, and the items Northstar keeps.
     */
    private function inventory(): void
    {
        $save = app(SaveInventoryRecord::class);
        $actor = $this->person('procurement');

        foreach ([
            'warehouse' => ['Main warehouse', 'WH-1', 'Racking, receiving dock and the PPE store.'],
            'line2' => ['Line 2 stores', 'L2', 'Spare parts kept next to Line 2.'],
            'maintenance' => ['Maintenance cage', 'MC', 'Fluids and tools for the maintenance team.'],
        ] as $key => [$name, $code, $description]) {
            $this->locations[$key] = $save->handle(new InventoryLocation, $actor, ['name' => $name, 'code' => $code, 'description' => $description, 'is_active' => true]);
        }

        foreach ([
            'midwest' => ['Midwest Industrial Supply', 'Carla Jensen', 'orders@midwest-industrial.example', 5],
            'harbor' => ['Harbor Fasteners', 'Luis Ortega', 'sales@harborfasteners.example', 3],
            'safeguard' => ['SafeGuard PPE', 'Hannah Price', 'trade@safeguardppe.example', 2],
            'kessler' => ['Kessler Sensors', 'Jonas Weber', 'orders@kessler-sensors.example', 10],
        ] as $key => [$name, $contact, $email, $leadTime]) {
            $this->suppliers[$key] = $save->handle(new Supplier, $actor, ['name' => $name, 'contact_name' => $contact, 'email' => $email, 'lead_time_days' => $leadTime, 'is_active' => true]);
        }

        foreach (['sensors' => 'Sensors and controls', 'safety' => 'Safety equipment', 'parts' => 'Spare parts', 'consumables' => 'Consumables'] as $key => $name) {
            $this->categories[$key] = $save->handle(new InventoryCategory, $actor, ['name' => $name]);
        }

        $create = app(CreateInventoryItem::class);

        foreach ([
            'sensor' => ['SNS-200', 'Industrial proximity sensor', 'each', 'sensors', 'kessler', 'warehouse', 21050, 10, 40, 28],
            'gloves' => ['GLV-100', 'Nitrile gloves (box of 100)', 'box', 'safety', 'safeguard', 'warehouse', 1280, 20, 50, 46],
            'glasses' => ['EYE-01', 'Safety glasses', 'each', 'safety', 'safeguard', 'warehouse', 690, 30, 60, 75],
            'belt' => ['BLT-L2', 'Conveyor belt for Line 2', 'each', 'parts', 'midwest', 'line2', 320000, 1, 2, 2],
            'bearing' => ['BRG-6205', 'Deep groove bearing 6205', 'each', 'parts', 'midwest', 'line2', 845, 25, 100, 80],
            'bolts' => ['FST-M8', 'M8 hex bolts (pack of 50)', 'pack', 'parts', 'harbor', 'warehouse', 1490, 15, 40, 60],
            'fluid' => ['HYD-46', 'Hydraulic fluid ISO 46 (20 L drum)', 'each', 'consumables', 'midwest', 'maintenance', 18600, 6, 30, 14],
        ] as $key => [$sku, $name, $unit, $category, $supplier, $location, $cost, $reorderPoint, $reorderQuantity, $opening]) {
            $this->items[$key] = $create->handle($actor, [
                'sku' => $sku,
                'name' => $name,
                'unit' => $unit,
                'category_id' => $this->categories[$category]->id,
                'supplier_id' => $this->suppliers[$supplier]->id,
                'default_location_id' => $this->location($location)->id,
                'unit_cost_amount' => $cost,
                'minimum_stock' => (int) floor($reorderPoint / 2),
                'reorder_point' => $reorderPoint,
                'reorder_quantity' => $reorderQuantity,
                'is_active' => true,
            ], $opening, $this->location($location)->id);
        }
    }

    /**
     * The four workflows from the brief, plus escalation of critical issues,
     * published and switched on.
     */
    private function workflows(): void
    {
        $actor = $this->person('admin');

        foreach (['purchase-approval', 'low-stock-reorder', 'leave-request', 'expense-approval', 'critical-issue-escalation'] as $template) {
            $workflow = app(CreateWorkflow::class)->handle($actor, $this->templateName($template), null, 'manual', $template);
            app(PublishWorkflow::class)->handle($workflow, $actor, 'First version, set up with the operations team.');
            app(ChangeWorkflowStatus::class)->handle($workflow->refresh(), $actor, WorkflowStatus::Active);
        }
    }

    /**
     * Factory expansion, quality and the warehouse, with their tasks.
     */
    private function projects(): void
    {
        $createProject = app(CreateProject::class);
        $createTask = app(CreateTask::class);
        $owner = $this->person('owner');

        $projects = [
            'expansion' => [
                ['name' => 'Factory Expansion', 'description' => 'A new assembly bay and Line 3, so Northstar can take on the Kessler contract without overtime.', 'status' => 'active', 'priority' => 'high', 'owner_id' => $owner->id, 'start_date' => $this->day(-30), 'due_date' => $this->day(75), 'budget_amount' => 120_000_000, 'budget_currency' => 'USD'],
                ['manager', 'procurement', 'operations', 'admin'],
                [
                    ['Finalize the layout for the new assembly bay', 'done', 'high', 'manager', -18],
                    ['Get three quotes for the overhead crane', 'in_progress', 'high', 'procurement', 5],
                    ['Electrical load survey for Line 3', 'review', 'medium', 'operations', -1],
                    ['Order racking for the expansion floor', 'todo', 'medium', 'procurement', 12],
                    ['Fire safety sign-off with the insurer', 'todo', 'urgent', 'owner', 20],
                    ['Hire two machine operators', 'in_progress', 'medium', 'admin', 30],
                ],
            ],
            'quality' => [
                ['name' => 'Quality Improvement', 'description' => 'Cut the scrap rate on Line 2 from 4.1% to under 2% by the end of the quarter.', 'status' => 'active', 'priority' => 'medium', 'owner_id' => $this->person('manager')->id, 'start_date' => $this->day(-30), 'due_date' => $this->day(45)],
                ['employee', 'operations'],
                [
                    ['Install proximity sensors on the Line 2 rejects chute', 'in_progress', 'high', 'employee', -2],
                    ['Root cause analysis for the batch 14 rework', 'in_progress', 'high', 'operations', 3],
                    ['Weekly scrap rate review', 'todo', 'medium', 'manager', 2],
                    ['Calibrate the torque wrenches', 'done', 'low', 'employee', -10],
                    ['Update the final inspection checklist', 'backlog', 'low', 'manager', null],
                ],
            ],
            'warehouse' => [
                ['name' => 'Warehouse Upgrade', 'description' => 'Barcoded bins, tighter cycle counts and new dock seals before winter.', 'status' => 'planning', 'priority' => 'medium', 'owner_id' => $this->person('procurement')->id, 'start_date' => $this->day(-14), 'due_date' => $this->day(60)],
                ['operations'],
                [
                    ['Barcode labels for every bin location', 'todo', 'medium', 'procurement', 14],
                    ['Replace the dock door seals', 'blocked', 'high', 'operations', -3],
                    ['Cycle count process for fast movers', 'backlog', 'low', 'procurement', null],
                ],
            ],
        ];

        foreach ($projects as $key => [$attributes, $members, $tasks]) {
            $project = $createProject->handle($owner, $attributes, array_map(fn (string $member): int => $this->person($member)->id, $members));

            foreach ($tasks as [$title, $status, $priority, $assignee, $due]) {
                $this->tasks[$title] = $createTask->handle($this->person('manager'), [
                    'project_id' => $project->id,
                    'title' => $title,
                    'status' => $status,
                    'priority' => $priority,
                    'assignee_id' => $this->person($assignee)->id,
                    'due_date' => $due === null ? null : $this->day($due),
                ]);
            }

            $this->projects[$key] = $project;
        }
    }

    /**
     * Four weeks of purchasing, stock use, approvals and issues, ending with
     * work waiting on Marcus (manager) and Priya (finance).
     */
    private function history(Organization $organization): void
    {
        $dana = $this->person('employee');
        $sam = $this->person('operations');
        $marcus = $this->person('manager');
        $priya = $this->person('finance');
        $tom = $this->person('procurement');

        // Safety glasses: under 5,000, so the manager's approval is enough.
        $this->at(20, 9, 15);
        $glasses = $this->purchase($dana, 'glasses', 40, 'Stock for the new starters and visitors.', 10);
        $this->at(20, 11, 40);
        $this->decide($glasses, $marcus, 'approve', 'Fine, keep a box at the gatehouse too.');
        $this->at(19, 10, 0);
        app(ChangePurchaseRequestStatus::class)->handle($glasses->refresh(), $tom, PurchaseRequestStatus::Ordered, 'SG-55120');
        $this->at(17, 14, 20);
        app(ReceivePurchaseRequest::class)->handle($glasses->refresh(), $tom, 40, $this->location('warehouse')->id);

        // Leave: Dana asks for a week off; Marcus approves.
        $this->at(16, 8, 50);
        $leave = Workflow::query()->where('name', 'Leave request')->sole();
        app(StartWorkflowRun::class)->handle($leave, $dana, [
            'leave_type' => 'annual',
            'first_day' => $this->day(10),
            'last_day' => $this->day(14),
            'cover' => (string) $sam->id,
            'notes' => 'Family visit. Sam has agreed to cover the Line 2 checks.',
        ], 'northstar-leave-1');
        $this->at(16, 13, 5);
        $this->decideLatestWorkflowApproval($marcus, 'approve', 'Enjoy the break.');

        // Industrial sensors: over 5,000, so finance approves as well.
        $this->at(12, 10, 5);
        $sensors = $this->purchase($dana, 'sensor', 40, 'Proximity sensors for the Line 2 rejects chute, for the quality project.', 9);
        $this->at(12, 13, 30);
        $this->decide($sensors, $marcus, 'approve', 'Needed for the scrap rate work.');
        $this->at(11, 9, 45);
        $this->decide($sensors, $priya, 'approve', 'Within the quality budget.');
        $this->at(11, 15, 0);
        app(ChangePurchaseRequestStatus::class)->handle($sensors->refresh(), $tom, PurchaseRequestStatus::Ordered, 'KS-20931');
        $this->at(4, 11, 10);
        app(ReceivePurchaseRequest::class)->handle($sensors->refresh(), $tom, 24, $this->location('warehouse')->id);

        // Hydraulic leak, found and fixed.
        $this->at(9, 7, 40);
        app(CreateIssue::class)->handle($dana, [
            'project_id' => $this->project('quality')->id,
            'title' => 'Hydraulic leak on press 4',
            'description' => 'Oil pooling under the press after the night shift. Press stopped and area taped off.',
            'severity' => 'high',
            'status' => 'resolved',
            'assignee_id' => $sam->id,
            'resolved_at' => $this->day(-8),
        ]);

        // A replacement belt the manager turns down: there is a spare.
        $this->at(6, 9, 30);
        $belt = $this->purchase($dana, 'belt', 2, 'The Line 2 belt is fraying at the splice.', 7);
        $this->at(6, 12, 15);
        $this->decide($belt, $marcus, 'reject', 'There is a spare belt in Line 2 stores. Fit that first and we will reorder after.');

        $this->at(3, 10, 20);
        app(CreateIssue::class)->handle($tom, [
            'title' => 'Label printer jams on the packing line',
            'description' => 'Jams every 40 to 50 labels since the new roll stock arrived.',
            'severity' => 'medium',
            'status' => 'investigating',
            'assignee_id' => $tom->id,
            'due_date' => $this->day(2),
        ]);

        app(CreateIssue::class)->handle($sam, [
            'project_id' => $this->project('warehouse')->id,
            'title' => 'Forklift battery not holding charge',
            'description' => 'Forklift 2 needs charging twice a shift.',
            'severity' => 'low',
            'status' => 'open',
            'assignee_id' => $tom->id,
        ]);

        // A training expense over 1,000, so it waits on finance.
        $this->at(2, 14, 0);
        $expense = Workflow::query()->where('name', 'Expense approval')->sole();
        app(StartWorkflowRun::class)->handle($expense, $sam, [
            'what' => 'Forklift refresher course for the night shift',
            'category' => 'training',
            'amount' => '1,450.00',
            'spent_on' => $this->day(-3),
        ], 'northstar-expense-1');

        // Daily use of consumables; the gloves reach their reorder point and
        // the low stock workflow raises a purchase request on its own.
        $this->stockUse();

        // Weekend overtime, asked for directly rather than through a workflow.
        $this->at(1, 15, 10);
        app(RequestApproval::class)->handle($sam, [
            'title' => 'Overtime for the weekend shift',
            'description' => 'Two operators on Saturday to clear the batch 14 rework before the Kessler delivery.',
            'approver_role' => Role::Manager,
            'priority' => Priority::High,
        ]);

        // Hydraulic fluid: the manager has approved; finance has not yet.
        $this->at(0, 9, 5);
        $fluid = $this->purchase($sam, 'fluid', 30, 'Restock after the press 4 leak, and stock for the new Line 3 presses.', 6);
        $this->at(0, 10, 30);
        $this->decide($fluid, $marcus, 'approve', 'Approved. Priya, this one is over the limit.');

        // This morning: Line 2 stops, and the escalation workflow takes over.
        $this->at(0, 14, 50);
        $stoppage = app(CreateIssue::class)->handle($dana, [
            'project_id' => $this->project('quality')->id,
            'title' => 'Line 2 conveyor belt snapped',
            'description' => 'Belt snapped at the splice during the 2 pm run. Line 2 is stopped; two production tasks are waiting on it.',
            'severity' => 'critical',
            'status' => 'open',
        ]);

        $this->at(0, 15, 20);
        app(AddComment::class)->handle($stoppage, $sam, 'Fitting the spare belt from Line 2 stores now. Back up in about an hour.');
        app(AddComment::class)->handle($this->task('Install proximity sensors on the Line 2 rejects chute'), $marcus, 'The first 24 sensors are in. Dana, can you plan the install for Friday morning?');

        $this->at(0, 16, 30);
    }

    /**
     * Consumables used over the last four weeks.
     */
    private function stockUse(): void
    {
        $record = app(RecordMovement::class);
        $issue = fn (string $item, int $quantity, string $location, string $reference) => $record->handle(
            $this->item($item),
            $this->person('operations'),
            MovementType::Issue,
            ['quantity' => $quantity, 'location_id' => $this->location($location)->id, 'reference' => $reference],
        );

        // Gloves go from 46 to 20 boxes, their reorder point, on the last day.
        foreach ([[27, 3], [25, 2], [23, 3], [20, 2], [18, 3], [15, 2], [13, 2], [10, 3], [8, 2], [5, 2], [1, 2]] as [$daysAgo, $boxes]) {
            $this->at($daysAgo, 7, 20);
            $issue('gloves', $boxes, 'warehouse', 'Line 2 shift A');
        }

        foreach ([[26, 12], [19, 10], [12, 15], [6, 9], [2, 11]] as [$daysAgo, $bearings]) {
            $this->at($daysAgo, 13, 0);
            $issue('bearing', $bearings, 'line2', 'Planned maintenance');
        }

        foreach ([[24, 2], [14, 3], [9, 2]] as [$daysAgo, $drums]) {
            $this->at($daysAgo, 10, 30);
            $issue('fluid', $drums, 'maintenance', 'Press top-ups');
        }

        foreach ([[22, 6], [11, 5], [3, 4]] as [$daysAgo, $packs]) {
            $this->at($daysAgo, 11, 45);
            $issue('bolts', $packs, 'warehouse', 'Expansion floor fit-out');
        }
    }

    /**
     * A platform administrator for showing the FlowPilot team's side.
     */
    private function platformAdmin(string $password): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => self::PLATFORM_ADMIN_EMAIL],
            ['name' => 'FlowPilot Support', 'password' => $password],
        );

        $admin->forceFill(['email_verified_at' => now(), 'is_platform_admin' => true])->save();
    }

    private function purchase(User $requester, string $item, int $quantity, string $reason, int $neededInDays): PurchaseRequest
    {
        $record = $this->item($item);

        return app(SubmitPurchaseRequest::class)->handle($requester, [
            'inventory_item_id' => $record->id,
            'item_name' => $record->name,
            'supplier_id' => $record->supplier_id,
            'deliver_to_location_id' => $record->default_location_id,
            'quantity' => $quantity,
            'unit_cost_amount' => $record->unit_cost_amount,
            'needed_by' => $this->day($neededInDays),
            'reason' => $reason,
        ]);
    }

    /**
     * Decides the purchase request's waiting approval, as the workflow asked.
     *
     * @param  'approve'|'reject'|'request_changes'  $decision
     */
    private function decide(PurchaseRequest $request, User $decider, string $decision, string $note): void
    {
        $approval = Approval::query()
            ->where('subject_type', $request->getMorphClass())
            ->where('subject_id', $request->id)
            ->where('status', ApprovalStatus::Pending->value)
            ->latest('created_at')
            ->firstOrFail();

        app(DecideApproval::class)->handle($approval, $decider, $decision, $note);
    }

    /**
     * Decides the newest waiting approval a workflow run asked for.
     *
     * @param  'approve'|'reject'|'request_changes'  $decision
     */
    private function decideLatestWorkflowApproval(User $decider, string $decision, string $note): void
    {
        $approval = Approval::query()
            ->whereNotNull('workflow_run_id')
            ->where('status', ApprovalStatus::Pending->value)
            ->latest('created_at')
            ->firstOrFail();

        app(DecideApproval::class)->handle($approval, $decider, $decision, $note);
    }

    /**
     * Moves the clock to a time some days before the end of the demo history.
     */
    private function at(int $daysAgo, int $hour, int $minute): void
    {
        Date::setTestNow($this->today->subDays($daysAgo)->setTime($hour, $minute));
    }

    /**
     * A date relative to today, as Y-m-d.
     */
    private function day(int $offset): string
    {
        return $this->today->addDays($offset)->toDateString();
    }

    private function person(string $key): User
    {
        return $this->people[$key] ?? throw new RuntimeException("No demo person [{$key}].");
    }

    private function location(string $key): InventoryLocation
    {
        return $this->locations[$key] ?? throw new RuntimeException("No demo location [{$key}].");
    }

    private function item(string $key): InventoryItem
    {
        return $this->items[$key] ?? throw new RuntimeException("No demo item [{$key}].");
    }

    private function project(string $key): Project
    {
        return $this->projects[$key] ?? throw new RuntimeException("No demo project [{$key}].");
    }

    private function task(string $title): Task
    {
        return $this->tasks[$title] ?? throw new RuntimeException("No demo task [{$title}].");
    }

    private function templateName(string $template): string
    {
        return match ($template) {
            'purchase-approval' => 'Purchase approval',
            'low-stock-reorder' => 'Low stock alert',
            'leave-request' => 'Leave request',
            'expense-approval' => 'Expense approval',
            default => 'Critical issue escalation',
        };
    }
}
