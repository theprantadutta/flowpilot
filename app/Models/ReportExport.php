<?php

namespace App\Models;

use App\Enums\ExportStatus;
use App\Enums\ReportType;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\ReportExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A report exported to a file in the background, kept for a week for the
 * member who asked for it.
 *
 * @property string $id
 * @property string $organization_id
 * @property int $user_id
 * @property ReportType $report
 * @property string $format
 * @property array<string, mixed> $parameters
 * @property ExportStatus $status
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $filename
 * @property int|null $row_count
 * @property int|null $size
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'report', 'format', 'parameters', 'status', 'disk', 'path', 'filename', 'row_count', 'size', 'error', 'started_at', 'completed_at', 'expires_at'])]
class ReportExport extends Model
{
    /** @use HasFactory<ReportExportFactory> */
    use BelongsToOrganization, HasFactory, HasUuids;

    /**
     * How long a finished export can be downloaded.
     */
    public static function keepDays(): int
    {
        return max(1, (int) config('flowpilot.exports.keep_days', 7));
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'format' => 'csv',
        'status' => 'queued',
        'disk' => null,
        'path' => null,
        'filename' => null,
        'row_count' => null,
        'size' => null,
        'error' => null,
        'started_at' => null,
        'completed_at' => null,
        'expires_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report' => ReportType::class,
            'status' => ExportStatus::class,
            'parameters' => 'array',
            'row_count' => 'integer',
            'size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === ExportStatus::Completed
            && $this->path !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
