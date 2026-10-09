<?php

namespace App\Http\Controllers;

use App\Enums\Plan;
use App\Support\Billing\BillingSummary;
use App\Support\Marketing\Faq;
use App\Support\Marketing\LegalDocument;
use App\Support\Marketing\PageMeta;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public site: landing page, pricing, legal pages, and what search
 * engines read (sitemap and robots.txt).
 */
class MarketingController extends Controller
{
    public function home(BillingSummary $billing, Faq $faq): Response
    {
        $questions = $faq->product();

        return Inertia::render('marketing/Home', [
            'plans' => $billing->catalog(),
            'faqs' => $questions,
            'trial' => $this->trial(),
            'salesEmail' => (string) config('billing.sales_email'),
        ])->withViewData('meta', (new PageMeta(
            'Workflow automation for operations teams',
            'FlowPilot turns repetitive business processes into clear, connected workflows: approvals, tasks, issues, inventory and purchasing, with an AI brief of what needs attention.',
            route('home'),
            [$this->softwareSchema(), $this->faqSchema($questions)],
        ))->toArray());
    }

    public function pricing(BillingSummary $billing, Faq $faq): Response
    {
        $questions = $faq->billing();

        return Inertia::render('marketing/Pricing', [
            'plans' => $billing->catalog(),
            'comparison' => $billing->comparison(),
            'faqs' => $questions,
            'trial' => $this->trial(),
            'salesEmail' => (string) config('billing.sales_email'),
        ])->withViewData('meta', (new PageMeta(
            'Pricing',
            'Simple monthly plans for teams of every size. Start with a free trial of Business, then stay on Free or choose the plan that fits.',
            route('pricing'),
            [$this->faqSchema($questions)],
        ))->toArray());
    }

    public function terms(): Response
    {
        return $this->legal(LegalDocument::find('terms'), 'The terms that apply when you use FlowPilot.');
    }

    public function privacy(): Response
    {
        return $this->legal(LegalDocument::find('privacy'), 'What personal information FlowPilot collects, why, who it is shared with, and the choices you have.');
    }

    public function sitemap(): HttpResponse
    {
        $pages = [
            ['url' => route('home'), 'priority' => '1.0'],
            ['url' => route('pricing'), 'priority' => '0.8'],
            ['url' => route('legal.terms'), 'priority' => '0.3'],
            ['url' => route('legal.privacy'), 'priority' => '0.3'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($pages as $page) {
            $xml .= '  <url><loc>'.e($page['url']).'</loc><priority>'.$page['priority'].'</priority></url>'."\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Public pages may be crawled; the app, its settings and platform
     * administration may not.
     */
    public function robots(): HttpResponse
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /app/', 'Disallow: /dashboard', 'Disallow: /settings', 'Disallow: /onboarding', 'Disallow: /invitations/', 'Disallow: /platform', 'Allow: /', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function legal(LegalDocument $document, string $description): Response
    {
        $route = $document->key === 'terms' ? 'legal.terms' : 'legal.privacy';

        return Inertia::render('marketing/Legal', [
            'document' => [
                'title' => $document->title,
                'updated' => $document->updated,
                ...$document->render(),
            ],
            'contactEmail' => (string) config('flowpilot.legal.contact_email'),
            'salesEmail' => (string) config('billing.sales_email'),
        ])->withViewData('meta', (new PageMeta($document->title, $description, route($route)))->toArray());
    }

    /**
     * @return array{days: int, plan: string}
     */
    private function trial(): array
    {
        return [
            'days' => (int) config('billing.trial_days', 14),
            'plan' => (Plan::tryFrom((string) config('billing.trial_plan', 'business')) ?? Plan::Business)->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function softwareSchema(): array
    {
        $offers = [];

        foreach (Plan::cases() as $plan) {
            if (($price = $plan->monthlyPrice()) !== null) {
                $offers[] = ['@type' => 'Offer', 'name' => $plan->label(), 'price' => number_format($price / 100, 2, '.', ''), 'priceCurrency' => (string) config('billing.currency', 'USD')];
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => (string) config('app.name', 'FlowPilot'),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'url' => route('home'),
            'description' => 'Workflow automation for business operations: approvals, tasks, issues, inventory and purchasing.',
            'offers' => $offers,
        ];
    }

    /**
     * @param  list<array{question: string, answer: string}>  $questions
     * @return array<string, mixed>
     */
    private function faqSchema(array $questions): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
            ], $questions),
        ];
    }
}
