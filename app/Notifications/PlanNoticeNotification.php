<?php

namespace App\Notifications;

use App\Enums\NotificationType;

/**
 * Something about the organization's plan that its owners and admins should
 * know: a trial ending, a limit reached, a plan changing.
 */
class PlanNoticeNotification extends TenantNotification
{
    public function __construct(
        public string $headline,
        public string $message,
        public string $level = 'info',
    ) {
        parent::__construct();
    }

    public function type(): NotificationType
    {
        return NotificationType::PlanNotice;
    }

    public function title(): string
    {
        return $this->headline;
    }

    public function body(): ?string
    {
        return $this->message;
    }

    public function url(): ?string
    {
        return $this->tenantRoute('billing.show');
    }

    public function tone(): string
    {
        return $this->level;
    }

    public function actionText(): string
    {
        return 'Open plan and billing';
    }
}
