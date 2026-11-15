<?php

namespace Tests\Unit\Security;

use App\Support\Security\CloudflareTrustedProxies;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

class CloudflareTrustedProxiesTest extends TestCase
{
    protected function tearDown(): void
    {
        CloudflareTrustedProxies::fake(null);
        // Restore process-wide TrustProxies static state for later feature tests.
        TrustProxies::at(CloudflareTrustedProxies::cidrs());
        parent::tearDown();
    }

    public function test_snapshot_includes_ipv4_and_ipv6_cidrs(): void
    {
        CloudflareTrustedProxies::fake(null);
        $cidrs = CloudflareTrustedProxies::cidrs();

        $this->assertNotEmpty($cidrs);
        $this->assertTrue(collect($cidrs)->contains(fn ($c) => str_contains($c, '.')));
        $this->assertTrue(collect($cidrs)->contains(fn ($c) => str_contains($c, ':')));
        $this->assertFalse(in_array('*', $cidrs, true));
        $this->assertFalse(in_array('**', $cidrs, true));
    }

    public function test_cloudflare_proxy_honors_x_forwarded_for(): void
    {
        // 104.16.0.0/13 is a real Cloudflare range.
        CloudflareTrustedProxies::fake(['104.16.0.0/13', '2400:cb00::/32']);

        $request = $this->requestWith(
            remoteAddr: '104.16.10.20',
            forwardedFor: '41.33.10.20',
        );

        $this->assertSame('41.33.10.20', $request->ip());
    }

    public function test_direct_origin_cannot_spoof_x_forwarded_for(): void
    {
        CloudflareTrustedProxies::fake(['104.16.0.0/13', '2400:cb00::/32']);

        $request = $this->requestWith(
            remoteAddr: '203.0.113.77',
            forwardedFor: '8.8.8.8',
        );

        $this->assertSame('203.0.113.77', $request->ip());
        $this->assertNotSame('8.8.8.8', $request->ip());
    }

    public function test_untrusted_proxy_ignores_forwarded_headers(): void
    {
        CloudflareTrustedProxies::fake(['104.16.0.0/13']);

        $request = $this->requestWith(
            remoteAddr: '198.51.100.10',
            forwardedFor: '185.3.12.10, 198.51.100.10',
        );

        $this->assertSame('198.51.100.10', $request->ip());
    }

    public function test_ipv6_cloudflare_proxy_honors_forwarded_client(): void
    {
        CloudflareTrustedProxies::fake(['2400:cb00::/32']);

        $request = $this->requestWith(
            remoteAddr: '2400:cb00::1',
            forwardedFor: '2001:db8::abcd',
        );

        $this->assertSame('2001:db8::abcd', $request->ip());
    }

    public function test_ipv6_direct_origin_cannot_spoof_forwarded_header(): void
    {
        CloudflareTrustedProxies::fake(['2400:cb00::/32']);

        $request = $this->requestWith(
            remoteAddr: '2001:db8::1',
            forwardedFor: '2001:db8::ffff',
        );

        $this->assertSame('2001:db8::1', $request->ip());
    }

    public function test_star_env_override_is_rejected(): void
    {
        CloudflareTrustedProxies::fake(null);
        config(['trusted_proxies.env_override' => '*']);

        $cidrs = CloudflareTrustedProxies::cidrs();
        $this->assertNotEmpty($cidrs);
        $this->assertFalse(in_array('*', $cidrs, true));
    }

    private function requestWith(string $remoteAddr, ?string $forwardedFor): Request
    {
        $server = ['REMOTE_ADDR' => $remoteAddr];
        if ($forwardedFor !== null) {
            $server['HTTP_X_FORWARDED_FOR'] = $forwardedFor;
        }

        $request = Request::create('https://crm.example.test/api/auth/login', 'POST', [], [], [], $server);

        TrustProxies::at(CloudflareTrustedProxies::cidrs());
        $middleware = new TrustProxies(app());
        $middleware->handle($request, fn ($r) => response('ok'));

        return $request;
    }
}
