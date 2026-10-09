<?php

namespace App\Enums;

/**
 * Parts of FlowPilot that depend on the organization's plan.
 */
enum Feature: string
{
    case Approvals = 'approvals';
    case AllReports = 'all_reports';
    case ReportExports = 'report_exports';
    case AiInsights = 'ai_insights';
    case Webhooks = 'webhooks';

    public function label(): string
    {
        return match ($this) {
            self::Approvals => 'Approvals and approval steps',
            self::AllReports => 'All reports',
            self::ReportExports => 'CSV exports',
            self::AiInsights => 'AI operations brief',
            self::Webhooks => 'Webhook steps in workflows',
        };
    }
}
