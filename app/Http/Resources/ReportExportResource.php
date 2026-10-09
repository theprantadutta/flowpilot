<?php

namespace App\Http\Resources;

use App\Models\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReportExport
 */
class ReportExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report' => ['value' => $this->report->value, 'label' => $this->report->label()],
            'status' => $this->status->toOption(),
            'is_finished' => $this->status->isFinished(),
            'range' => is_string($this->parameters['label'] ?? null) ? $this->parameters['label'] : null,
            'row_count' => $this->row_count,
            'size' => $this->size,
            'filename' => $this->filename,
            'download_url' => $this->isDownloadable() ? route('reports.exports.download', ['export' => $this->id]) : null,
            'is_expired' => $this->expires_at !== null && $this->expires_at->isPast(),
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
