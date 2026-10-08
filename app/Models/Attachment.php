<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A file attached to a project, task, issue or approval. Stored privately
 * under a random name; served only through an authorized download route.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $attachable_type
 * @property string $attachable_id
 * @property int|null $uploaded_by
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $uploader
 */
#[Fillable(['uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
#[Hidden(['disk', 'path'])]
class Attachment extends Model
{
    use BelongsToOrganization, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    /**
     * @return array{id: string, name: string, extension: string, mime_type: string, size: int, uploaded_by: string|null, created_at: string|null, download_url: string}
     */
    public function toFileArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'extension' => $this->extension(),
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'uploaded_by' => $this->uploader?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'download_url' => route('attachments.download', ['attachment' => $this->id]),
        ];
    }
}
