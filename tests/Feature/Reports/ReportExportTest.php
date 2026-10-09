<?php

use App\Enums\ExportStatus;
use App\Enums\ReportType;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Jobs\GenerateReportExport;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\Task;
use App\Notifications\ReportExportFinishedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

beforeEach(function () {
    Storage::fake('local');
    config(['flowpilot.exports.disk' => 'local']);
});

/**
 * @param  array<string, mixed>  $parameters
 */
function requestExport($member, Organization $organization, string $report = 'task-completion', array $parameters = [])
{
    return actingAs($member)->post(route('reports.exports.store', [$organization, $report]), ['range' => 'last_30_days', ...$parameters]);
}

it('queues an export and records that data left FlowPilot', function () {
    Queue::fake();
    $organization = Organization::factory()->create();

    requestExport($organization->owner, $organization, parameters: ['group' => 'project'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $export = inTenant($organization, fn () => ReportExport::query()->sole());

    expect($export->report)->toBe(ReportType::TaskCompletion)
        ->and($export->status)->toBe(ExportStatus::Queued)
        ->and($export->parameters['group'])->toBe('project')
        ->and(inTenant($organization, fn () => ActivityLog::query()->where('action', 'report.exported')->exists()))->toBeTrue();

    Queue::assertPushed(GenerateReportExport::class, fn (GenerateReportExport $job) => $job->exportId === $export->id);
});

it('writes the CSV, keeps formulas as text and tells the member', function () {
    Notification::fake();
    $organization = Organization::factory()->create();

    inTenant($organization, fn () => Task::factory()->status(TaskStatus::Done)->create([
        'organization_id' => $organization->id,
        'assignee_id' => memberIn($organization, Role::Employee, ['name' => '=HYPERLINK("http://evil.test")'])->id,
    ]));

    requestExport($organization->owner, $organization);

    $export = inTenant($organization, fn () => ReportExport::query()->sole());
    $contents = Storage::disk('local')->get((string) $export->path);

    expect($export->status)->toBe(ExportStatus::Completed)
        ->and($export->row_count)->toBe(1)
        ->and($export->expires_at?->isFuture())->toBeTrue()
        ->and($contents)->toStartWith("\u{FEFF}Assignee,Created,Completed")
        ->and($contents)->toContain("\"'=HYPERLINK(\"\"http://evil.test\"\")\"");

    Notification::assertSentTo($organization->owner, ReportExportFinishedNotification::class, fn (ReportExportFinishedNotification $notification) => $notification->succeeded && $notification->rows === 1);
});

it('lets only the member who asked download the file', function () {
    $organization = Organization::factory()->create();
    $colleague = memberIn($organization, Role::Admin);
    $outsider = Organization::factory()->create();

    $export = inTenant($organization, function () use ($organization) {
        Storage::disk('local')->put('exports/test.csv', "SKU,Name\n");

        return ReportExport::factory()->completed('exports/test.csv')->create(['organization_id' => $organization->id, 'user_id' => $organization->owner_id]);
    });

    actingAs($organization->owner)->get(route('reports.exports.download', [$organization, $export]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload('task-completion.csv');
    actingAs($colleague)->get(route('reports.exports.download', [$organization, $export]))->assertForbidden();
    actingAs($outsider->owner)->get(route('reports.exports.download', [$outsider, $export]))->assertNotFound();
});

it('sends members back to the report when an export has expired', function () {
    $organization = Organization::factory()->create();

    $export = inTenant($organization, fn () => ReportExport::factory()->completed('exports/gone.csv')->create([
        'organization_id' => $organization->id,
        'user_id' => $organization->owner_id,
        'expires_at' => now()->subDay(),
    ]));

    actingAs($organization->owner)->get(route('reports.exports.download', [$organization, $export]))
        ->assertRedirect(route('reports.show', [$organization, 'task-completion']));
});

it('refuses exports to members without the export permission', function () {
    Queue::fake();
    $organization = Organization::factory()->create();

    requestExport(memberIn($organization, Role::Operations), $organization)->assertForbidden();

    Queue::assertNothingPushed();
});

it('limits how many exports can wait at once', function () {
    Queue::fake();
    $organization = Organization::factory()->create();
    inTenant($organization, fn () => ReportExport::factory()->count(3)->create(['organization_id' => $organization->id, 'user_id' => $organization->owner_id]));

    requestExport($organization->owner, $organization)->assertSessionHasErrors('export');
    requestExport($organization->owner, $organization, parameters: ['range' => 'custom', 'from' => '2026-10-09', 'to' => '2026-10-01'])->assertSessionHasErrors(['to', 'export']);
});

it('marks the export failed when the member lost access before it ran', function () {
    Notification::fake();
    $organization = Organization::factory()->create();
    $finance = memberIn($organization, Role::Finance);

    $export = inTenant($organization, fn () => ReportExport::factory()->create(['organization_id' => $organization->id, 'user_id' => $finance->id, 'report' => ReportType::InventoryStatus]));
    inTenant($organization, fn () => $organization->memberships()->where('user_id', $finance->id)->update(['role' => Role::Employee]));

    dispatch_sync(new GenerateReportExport($organization->id, $export->id));

    expect($export->refresh()->status)->toBe(ExportStatus::Failed);
    Notification::assertSentTo($finance, ReportExportFinishedNotification::class, fn (ReportExportFinishedNotification $notification) => ! $notification->succeeded);
});

it('prunes expired exports and their files', function () {
    $organization = Organization::factory()->create();

    [$expired, $fresh] = inTenant($organization, function () use ($organization) {
        Storage::disk('local')->put('exports/old.csv', 'old');
        Storage::disk('local')->put('exports/new.csv', 'new');

        return [
            ReportExport::factory()->completed('exports/old.csv')->create(['organization_id' => $organization->id, 'user_id' => $organization->owner_id, 'expires_at' => now()->subHour()]),
            ReportExport::factory()->completed('exports/new.csv')->create(['organization_id' => $organization->id, 'user_id' => $organization->owner_id]),
        ];
    });

    artisan('reports:prune-exports')->assertSuccessful();

    Storage::disk('local')->assertMissing('exports/old.csv');
    Storage::disk('local')->assertExists('exports/new.csv');
    expect(ReportExport::withoutOrganizationScope()->whereKey($expired->id)->exists())->toBeFalse()
        ->and(ReportExport::withoutOrganizationScope()->whereKey($fresh->id)->exists())->toBeTrue();
});
