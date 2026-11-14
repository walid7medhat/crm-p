<?php

namespace App\Console\Commands;

use App\Support\Security\CloudflareTrustedProxies;
use Illuminate\Console\Command;

class RefreshCloudflareIpsCommand extends Command
{
    protected $signature = 'cloudflare:refresh-ips';

    protected $description = 'Refresh trusted Cloudflare proxy CIDRs from official ips-v4 / ips-v6 lists';

    public function handle(): int
    {
        try {
            $cidrs = CloudflareTrustedProxies::refreshFromOfficialSources();
        } catch (\Throwable $e) {
            $this->error('Failed to refresh Cloudflare IPs: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Refreshed '.count($cidrs).' Cloudflare CIDRs (IPv4 + IPv6).');
        $this->line('Snapshot: '.config('trusted_proxies.snapshot_path'));
        $this->line('Bundled:  '.config('trusted_proxies.bundled_snapshot_path'));

        return self::SUCCESS;
    }
}
