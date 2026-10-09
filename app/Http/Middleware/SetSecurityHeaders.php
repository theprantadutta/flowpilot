<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side protections on every page: no framing by other sites, no
 * content sniffing, a strict referrer, HTTPS pinned in production, and a
 * content security policy that only runs FlowPilot's own scripts.
 */
class SetSecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $policy = $this->usesContentSecurityPolicy();

        // Vite adds this nonce to the script tags it renders, and the layout
        // adds it to its own inline script.
        if ($policy) {
            Vite::useCspNonce();
        }

        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');

        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Laravel's debug error page relies on inline scripts; it is only ever
        // shown with APP_DEBUG on, never in production.
        $debugErrorPage = config('app.debug') && isset($response->exception);

        if ($policy && ! $debugErrorPage && ! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy((string) Vite::cspNonce(), $request->isSecure()));
        }

        return $response;
    }

    private function usesContentSecurityPolicy(): bool
    {
        return (bool) config('flowpilot.security.content_security_policy', true) && ! Vite::isRunningHot();
    }

    /**
     * Styles may be inline (Vue binds style attributes for charts and
     * progress bars); scripts may not, apart from the nonced ones.
     */
    private function contentSecurityPolicy(string $nonce, bool $secure): string
    {
        return implode('; ', array_filter([
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "media-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            // Over HTTPS, never load anything over plain HTTP.
            $secure ? 'upgrade-insecure-requests' : null,
        ]));
    }
}
