<?php

namespace App\Support\Files;

use App\Enums\Limit;
use App\Models\Attachment;
use App\Models\User;
use App\Support\Billing\Entitlements;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores attachments privately under random names, inside a folder per
 * organization. The original name is kept only as metadata for downloads.
 */
class AttachmentStorage
{
    public function disk(): string
    {
        return (string) config('flowpilot.uploads.disk', 'local');
    }

    /**
     * @param  Model  $attachable  A tenant-owned record using HasAttachments.
     */
    public function store(Model $attachable, UploadedFile $file, User $uploader): Attachment
    {
        $organizationId = (string) $attachable->getAttribute('organization_id');

        app(Entitlements::class)->ensureRoom(Limit::StorageMb, (int) ceil(((int) $file->getSize()) / 1_048_576), 'file');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        $path = $file->storeAs(
            "organizations/{$organizationId}/attachments/".now()->format('Y/m'),
            Str::uuid7()->toString().($extension !== '' ? ".{$extension}" : ''),
            $this->disk(),
        );

        $attachment = new Attachment([
            'uploaded_by' => $uploader->id,
            'disk' => $this->disk(),
            'path' => (string) $path,
            'original_name' => $this->safeName($file->getClientOriginalName()),
            'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
            'size' => (int) $file->getSize(),
        ]);

        $attachment->attachable()->associate($attachable);
        $attachment->save();

        return $attachment;
    }

    public function delete(Attachment $attachment): void
    {
        $this->deleteAll(collect([$attachment]));
    }

    /**
     * Delete the records now and the files once the surrounding transaction
     * has committed, so a rollback never leaves a record without its file.
     *
     * @param  Collection<int, Attachment>  $attachments
     */
    public function deleteAll(Collection $attachments): void
    {
        if ($attachments->isEmpty()) {
            return;
        }

        $files = $attachments->map(fn (Attachment $attachment): array => [$attachment->disk, $attachment->path])->all();

        Attachment::withoutOrganizationScope()->whereKey($attachments->map(fn (Attachment $attachment): string => $attachment->id)->all())->delete();

        DB::afterCommit(function () use ($files): void {
            foreach ($files as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
        });
    }

    /**
     * Keep the name readable for the download, without path separators or
     * control characters.
     */
    public function safeName(string $name): string
    {
        $name = (string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '-', $name);
        $name = trim($name, " .-\t");

        return Str::limit($name !== '' ? $name : 'file', 200, '');
    }
}
