<?php

namespace App\Support\Marketing;

use App\Enums\Limit;
use App\Enums\Plan;

/**
 * Questions people ask before signing up, answered from how FlowPilot
 * actually behaves. Plan names, the trial and the limits come from the
 * billing configuration, so the answers change with it.
 */
class Faq
{
    /**
     * @return list<array{question: string, answer: string}>
     */
    public function product(): array
    {
        $trialDays = (int) config('billing.trial_days', 14);
        $trialPlan = Plan::tryFrom((string) config('billing.trial_plan', 'business')) ?? Plan::Business;

        return [
            [
                'question' => 'What is FlowPilot?',
                'answer' => 'One place for the operational work of a company: projects and tasks, issues, approvals, inventory and purchasing, and reports. Workflows tie it together. When something happens, FlowPilot checks your conditions, asks the right people, and takes the next step, so work keeps moving without anyone chasing it.',
            ],
            [
                'question' => 'Do we need to write code to build a workflow?',
                'answer' => 'No. Workflows are drawn on a canvas from a fixed set of steps: conditions, branches, approvals, assigning work, creating and updating records, notifications, delays and webhooks. Nothing in a workflow runs code you supply, which keeps every workflow predictable and safe to hand to the whole team.',
            ],
            [
                'question' => 'What can start a workflow?',
                'answer' => 'A task being created or completed, an issue being reported, stock running low, a purchase request being submitted, or a person starting it by hand with the details it asks for.',
            ],
            [
                'question' => 'How do approvals work?',
                'answer' => 'An approval goes to a named person or to everyone holding a role, such as Finance. They can approve, reject or ask for changes, and every decision is recorded with a comment. Approvals can be due within a set time and either send reminders or reject automatically when they run late.',
            ],
            [
                'question' => 'What does the AI operations brief do?',
                'answer' => 'It reads the records you are allowed to see, such as overdue tasks, waiting approvals, serious issues and low stock, and writes a short brief of what needs attention first. Every point links back to the records behind it. It never sees what you cannot, and it only runs on plans that include it.',
            ],
            [
                'question' => 'Is our data kept apart from other companies?',
                'answer' => 'Yes. Every record belongs to exactly one organization, and every request is checked against the organization in its address and your membership of it. Inside your organization, roles decide what each person can see and do, and the activity log records who changed what, when and from where.',
            ],
            [
                'question' => 'Can we try it before paying?',
                'answer' => "Every new organization gets {$trialDays} days of the {$trialPlan->label()} plan, with no card needed. When the trial ends you move to the Free plan unless you choose another one. Nothing is deleted.",
            ],
        ];
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public function billing(): array
    {
        $runs = Plan::Free->limit(Limit::WorkflowRunsPerMonth);

        return [
            [
                'question' => 'What happens when the trial ends?',
                'answer' => 'Your organization moves to the Free plan. Everything you created stays where it is; anything the Free plan does not include, such as approvals or the AI brief, pauses until you choose a plan that has it.',
            ],
            [
                'question' => 'How do we upgrade?',
                'answer' => 'The owner chooses a plan under Plan and billing in your organization settings. The FlowPilot team confirms the details with you and switches the plan, and the new limits apply straight away.',
            ],
            [
                'question' => 'Can we move to a smaller plan or cancel?',
                'answer' => 'Yes. The owner can move to Free at any time from Plan and billing, and it takes effect immediately. Your records stay; only what Free does not include pauses.',
            ],
            [
                'question' => 'What counts as a workflow run?',
                'answer' => 'Each time a workflow starts counts as one run, however many steps it has. Runs are counted per calendar month in your organization\'s timezone'.($runs !== null ? ', so the Free plan allows '.number_format($runs).' a month' : '').'. If you reach the limit, new events wait for next month to start workflows and the owner is told.',
            ],
            [
                'question' => 'Do you charge per person?',
                'answer' => 'No. Each plan has one monthly price and includes a number of members. Inviting someone takes a seat until they accept or the invitation is withdrawn.',
            ],
            [
                'question' => 'What is different about Enterprise?',
                'answer' => 'Everything in Business, with limits set for your organization and a direct line to the FlowPilot team. The price is agreed with you; write to us and we will reply.',
            ],
        ];
    }
}
