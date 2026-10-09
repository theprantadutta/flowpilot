<?php

namespace Database\Factories;

use App\Enums\ExportStatus;
use App\Enums\ReportType;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportExport>
 */
class ReportExportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'report' => ReportType::TaskCompletion,
            'parameters' => ['range' => 'last_30_days', 'from' => now()->subDays(29)->toDateString(), 'to' => now()->toDateString(), 'bucket' => 'day', 'group' => 'assignee', 'filters' => []],
            'status' => ExportStatus::Queued,
        ];
    }

    /**
     * A finished export with its file on the local disk.
     */
    public function completed(string $path = 'exports/test.csv'): static
    {
        return $this->state(fn (): array => [
            'status' => ExportStatus::Completed,
            'disk' => 'local',
            'path' => $path,
            'filename' => 'task-completion.csv',
            'row_count' => 3,
            'size' => 120,
            'started_at' => now(),
            'completed_at' => now(),
            'expires_at' => now()->addDays(ReportExport::keepDays()),
        ]);
    }
}
