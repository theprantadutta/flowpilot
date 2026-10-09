<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

afterEach(function () {
    TrustProxies::flushState();
});

it('sends browser protections with every page', function () {
    $response = get(route('home'))->assertOk();

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeaderMissing('Strict-Transport-Security');

    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
});

it('only runs scripts carrying the page nonce', function () {
    $response = get(route('home'))->assertOk();
    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'self'")
        ->not->toContain('unsafe-eval')
        ->not->toContain('upgrade-insecure-requests');

    preg_match("/script-src 'self' 'nonce-([^']+)'/", $policy, $match);

    expect($match[1] ?? null)->not->toBeNull()
        ->and($response->getContent())->toContain('<script nonce="'.$match[1].'">');
});

it('pins HTTPS and upgrades requests only for secure production traffic', function () {
    app()->detectEnvironment(fn () => 'production');

    $response = get('https://localhost/')->assertOk();

    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    expect($response->headers->get('Content-Security-Policy'))->toContain('upgrade-insecure-requests');
});

it('can switch the content security policy off', function () {
    config(['flowpilot.security.content_security_policy' => false]);

    get(route('home'))->assertOk()->assertHeaderMissing('Content-Security-Policy');
});

it('takes the visitor address and scheme from trusted proxies only', function () {
    Route::get('/_proxy-check', fn () => [
        'ip' => request()->ip(),
        'secure' => request()->isSecure(),
    ]);

    $forwarded = ['X-Forwarded-For' => '203.0.113.7', 'X-Forwarded-Proto' => 'https'];

    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->get('/_proxy-check', $forwarded)
        ->assertExactJson(['ip' => '172.18.0.5', 'secure' => false]);

    TrustProxies::at('*');

    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->get('/_proxy-check', $forwarded)
        ->assertExactJson(['ip' => '203.0.113.7', 'secure' => true]);
});
