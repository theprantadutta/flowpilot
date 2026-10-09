<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

describe('landing page', function () {
    it('shows the plans, questions and trial from the billing configuration', function () {
        config(['billing.trial_days' => 21]);

        get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('marketing/Home')
                ->has('plans', 4)
                ->where('plans.0.label', 'Free')
                ->where('plans.1.price', '$29')
                ->where('plans.3.price', null)
                ->where('trial', ['days' => 21, 'plan' => 'Business'])
                ->where('faqs', fn ($faqs) => collect($faqs)->contains(fn (array $item) => str_contains($item['answer'], '21 days of the Business plan')))
                ->has('salesEmail'));
    });

    it('describes itself to search engines and link previews without JavaScript', function () {
        $html = get(route('home'))->assertOk()->getContent();

        expect($html)
            ->toContain('>Workflow automation for operations teams - FlowPilot</title>')
            ->toContain('<meta name="description" content="FlowPilot turns repetitive business processes')
            ->toContain('<link rel="canonical" href="'.route('home').'">')
            ->toContain('<meta property="og:image" content="'.asset('brand/og-card.png').'">')
            ->toContain('<meta name="twitter:card" content="summary_large_image">')
            ->toContain('"@type":"SoftwareApplication"')
            ->toContain('"@type":"FAQPage"')
            ->not->toContain('noindex');
    });

    it('keeps the app and sign-in pages out of search engines', function () {
        expect(get(route('login'))->getContent())->toContain('<meta name="robots" content="noindex, nofollow">');
    });

    it('still opens for someone who is signed in', function () {
        actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('marketing/Home')->whereNot('auth.user', null));
    });
});

describe('pricing', function () {
    it('compares every limit and feature across the plans', function () {
        get(route('pricing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('marketing/Pricing')
                ->where('comparison.plans', ['Free', 'Starter', 'Business', 'Enterprise'])
                ->where('comparison.limits.0', ['label' => 'Members', 'values' => ['3', '10', '50', 'Unlimited']])
                ->where('comparison.limits.3', ['label' => 'File storage', 'values' => ['1 GB', '10 GB', '100 GB', 'Unlimited']])
                ->where('comparison.limits.4', ['label' => 'AI briefs a day', 'values' => [null, null, '100', '500']])
                ->where('comparison.features.0', ['label' => 'Approvals and approval steps', 'included' => [false, true, true, true]])
                ->has('faqs', 6));
    });
});

describe('legal pages', function () {
    it('names the operator and how to reach them', function (string $route, string $title) {
        config(['flowpilot.legal.entity' => 'Pranta Dutta', 'flowpilot.legal.contact_email' => 'prantadutta1997@gmail.com']);

        get(route($route))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('marketing/Legal')
                ->where('document.title', $title)
                ->where('contactEmail', 'prantadutta1997@gmail.com')
                ->where('document.html', fn (string $html) => str_contains($html, 'Pranta Dutta')
                    && str_contains($html, 'prantadutta1997@gmail.com')
                    && ! str_contains($html, ':contact_email')
                    && ! str_contains($html, ':entity'))
                ->where('document.sections', fn ($sections) => count($sections) >= 8));
    })->with([
        'terms of service' => ['legal.terms', 'Terms of service'],
        'privacy policy' => ['legal.privacy', 'Privacy policy'],
    ]);

    it('states the governing law only when one is configured', function () {
        config(['flowpilot.legal.jurisdiction' => null]);
        get(route('legal.terms'))->assertInertia(fn (Assert $page) => $page
            ->where('document.html', fn (string $html) => str_contains($html, 'the laws of the place where')));

        config(['flowpilot.legal.jurisdiction' => 'England and Wales']);
        get(route('legal.terms'))->assertInertia(fn (Assert $page) => $page
            ->where('document.html', fn (string $html) => str_contains($html, 'the laws of England and Wales')));
    });

    it('gives each section an anchor for the table of contents', function () {
        get(route('legal.privacy'))->assertInertia(fn (Assert $page) => $page
            ->where('document.sections.0', ['id' => 'two-roles', 'title' => 'Two roles'])
            ->where('document.html', fn (string $html) => str_contains($html, '<h2 id="two-roles">Two roles</h2>')));
    });
});

describe('crawlers', function () {
    it('lists the public pages in the sitemap', function () {
        get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.route('home').'</loc>', false)
            ->assertSee('<loc>'.route('pricing').'</loc>', false)
            ->assertSee('<loc>'.route('legal.terms').'</loc>', false)
            ->assertSee('<loc>'.route('legal.privacy').'</loc>', false)
            ->assertDontSee('/app/', false);
    });

    it('lets crawlers in only in production, and never into the app', function () {
        expect(get(route('robots'))->assertOk()->getContent())->toBe("User-agent: *\nDisallow: /\n");

        app()->detectEnvironment(fn () => 'production');

        expect(get(route('robots'))->getContent())
            ->toContain('Disallow: /app/')
            ->toContain('Disallow: /platform')
            ->toContain('Sitemap: '.route('sitemap'));
    });
});
