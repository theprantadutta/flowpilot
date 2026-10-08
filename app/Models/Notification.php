<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A notification in a member's inbox, belonging to one organization.
 *
 * @property string|null $organization_id
 * @property array{type?: string, title?: string, body?: string|null, url?: string|null, tone?: string, actor?: string|null} $data
 */
class Notification extends DatabaseNotification
{
    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForOrganization(Builder $query, Organization $organization): void
    {
        $query->where('organization_id', $organization->id);
    }

    /**
     * The shape the notification center renders.
     *
     * @return array{id: string, type: string, title: string, body: string|null, url: string|null, tone: string, actor: string|null, read: bool, created_at: string|null}
     */
    public function toCenterArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->data['type'] ?? 'general',
            'title' => $this->data['title'] ?? 'Notification',
            'body' => $this->data['body'] ?? null,
            'url' => $this->data['url'] ?? null,
            'tone' => $this->data['tone'] ?? 'info',
            'actor' => $this->data['actor'] ?? null,
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
