<?php

namespace App\Workflows\Webhooks;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Keeps webhooks pointed at the public internet. A workflow author must not
 * be able to make FlowPilot call its own network (databases, metadata
 * services, the local machine), so every address is resolved and every
 * resolved IP must be public. The request is then pinned to the checked IP,
 * so a DNS answer cannot change between the check and the call.
 */
class WebhookUrlGuard
{
    /**
     * Ranges refused on top of Symfony's private list: multicast and the
     * IETF protocol assignments block.
     */
    private const array EXTRA_BLOCKED = ['224.0.0.0/4', '192.0.0.0/24', 'ff00::/8'];

    /**
     * Static checks that need no network, for the builder.
     */
    public function problem(string $url): ?string
    {
        if (mb_strlen($url) > 2048) {
            return 'The address is too long.';
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return 'Enter a full address, like https://hooks.example.com/flowpilot.';
        }

        $scheme = strtolower($parts['scheme']);

        if ($scheme !== 'https' && ! ($scheme === 'http' && config('flowpilot.webhooks.allow_http'))) {
            return 'Webhooks must use https.';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'Put credentials in the receiving system, not in the address.';
        }

        $host = trim($parts['host'], '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false && ! $this->isPublic($host)) {
            return 'Webhooks cannot be sent to private or local addresses.';
        }

        if (in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true) || str_ends_with(strtolower($host), '.localhost') || str_ends_with(strtolower($host), '.internal') || str_ends_with(strtolower($host), '.local')) {
            return 'Webhooks cannot be sent to private or local addresses.';
        }

        return null;
    }

    /**
     * Resolve the address and confirm it is safe to call.
     *
     * @return array{host: string, port: int, ip: string}
     *
     * @throws UnsafeWebhookUrl
     */
    public function check(string $url): array
    {
        if (($problem = $this->problem($url)) !== null) {
            throw new UnsafeWebhookUrl($problem);
        }

        /** @var array{scheme: string, host: string, port?: int} $parts */
        $parts = parse_url($url);
        $host = trim($parts['host'], '[]');
        $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolve($host);

        if ($addresses === []) {
            throw new UnsafeWebhookUrl("The address {$host} could not be found.");
        }

        foreach ($addresses as $address) {
            if (! $this->isPublic($address)) {
                throw new UnsafeWebhookUrl('Webhooks cannot be sent to private or local addresses.');
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $addresses[0]];
    }

    public function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return ! IpUtils::checkIp($ip, [...IpUtils::PRIVATE_SUBNETS, ...self::EXTRA_BLOCKED]);
    }

    /**
     * Every IPv4 and IPv6 address the host resolves to.
     *
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }
}
