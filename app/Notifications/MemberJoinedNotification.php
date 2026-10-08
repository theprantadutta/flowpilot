<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\OrganizationMembership;

class MemberJoinedNotification extends TenantNotification
{
    public function __construct(
        public string $memberName,
        public string $roleLabel,
    ) {
        parent::__construct();
    }

    public static function for(OrganizationMembership $membership): self
    {
        return new self($membership->user->name, $membership->role->label());
    }

    public function type(): NotificationType
    {
        return NotificationType::MemberJoined;
    }

    public function title(): string
    {
        return "{$this->memberName} joined as {$this->roleLabel}";
    }

    public function body(): ?string
    {
        return 'They accepted their invitation and can now sign in.';
    }

    public function url(): ?string
    {
        return $this->tenantRoute('members.index');
    }

    public function tone(): string
    {
        return 'success';
    }

    public function actorName(): ?string
    {
        return $this->memberName;
    }

    public function actionText(): string
    {
        return 'View members';
    }
}
