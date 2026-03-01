<?php
// app/Console/Commands/RefreshMetaAppToken.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshMetaAppToken extends Command
{
    protected $signature = 'meta:refresh-app-token';
    protected $description = 'Refresh Meta App Access Token and update .env file';

    private const META_GRAPH_VERSION = 'v18.0';
    private const META_GRAPH_BASE = 'https://graph.facebook.com';

    public function handle()
    {
        $this->info('Starting Meta App Token refresh...');

        $appId = env('META_APP_ID');
        $appSecret = env('META_APP_SECRET');
        $currentToken = env('META_ACCESS_TOKEN');

        $this->line("App ID: " . ($appId ?: 'NOT SET'));
        $this->line("App Secret: " . ($appSecret ? '****' : 'NOT SET'));
        $this->line("Current Token: " . ($currentToken ? substr($currentToken, 0, 20) . '...' : 'NOT SET'));

        if (!$appId || !$appSecret) {
            $this->error('META_APP_ID or META_APP_SECRET not set in .env');
            return Command::FAILURE;
        }

        if (!$currentToken) {
            $this->error('META_ACCESS_TOKEN not set in .env');
            return Command::FAILURE;
        }

        try {
            $this->info('Calling Meta API...');
            $result = $this->getLongLivedToken($currentToken, $appId, $appSecret);
            
            if (!$result) {
                $this->error('Failed to get new token - check logs for details');
                return Command::FAILURE;
            }

            $this->info('✅ Got new token successfully!');
            $this->line("New token: " . substr($result, 0, 30) . '...');
            
            // تحديث ملف .env
            $this->updateEnvFile('META_ACCESS_TOKEN', $result);
            
            $this->info('✅ Meta App Token refreshed successfully!');
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('Refresh Meta token error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getLongLivedToken($shortToken, $appId, $appSecret)
    {
        $url = self::META_GRAPH_BASE . '/' . self::META_GRAPH_VERSION . '/oauth/access_token';
        
        $this->line("URL: $url");
        
        try {
            $response = Http::timeout(30)->get($url, [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'fb_exchange_token' => $shortToken
            ]);

            $this->line("Response Status: " . $response->status());
            
            if (!$response->successful()) {
                $error = $response->json();
                $this->error("Meta API Error Response:");
                $this->error(json_encode($error, JSON_PRETTY_PRINT));
                Log::error('Meta API error:', $error);
                return null;
            }

            $data = $response->json();
            $this->info("✅ Meta API responded successfully");
            
            return $data['access_token'] ?? null;
            
        } catch (\Exception $e) {
            $this->error('HTTP Request Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function updateEnvFile($key, $newValue)
    {
        $path = base_path('.env');
        
        if (!file_exists($path)) {
            $this->error(".env file not found at $path");
            return;
        }
        
        $this->line("Updating .env file...");
        
        $content = file_get_contents($path);
        $originalContent = $content;
        
        $pattern = "/^{$key}=.*/m";
        
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, "{$key}={$newValue}", $content);
            $this->line("✓ Replaced existing $key");
        } else {
            $content .= "\n{$key}={$newValue}";
            $this->line("✓ Added new $key");
        }
        
        if ($content !== $originalContent) {
            file_put_contents($path, $content);
            $this->info("✅ .env file updated");
            
            // Clear cache
            $this->call('config:clear');
        } else {
            $this->warn("No changes made to .env");
        }
    }
}