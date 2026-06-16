<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait AltCRMLeadTrait
{
    /**
     * Send lead to AltCRM webhook
     */
    public function sendToAltCRM(array $additionalData = [])
    {
        try {
            // ✅ تحويل القيم لنص مع التأكد من وجودها
            $payload = [
                'phone' => (string) ($this->work_phone ?? $this->whatsapp_number ?? ''),
                'name' => (string) ($this->lead_name ?? $this->first_name . ' ' . $this->last_name ?? ''),
                'email' => (string) ($this->email ?? ''),
                'external_id' => (string) ($this->meta_lead_id ?? $this->id),
                'campaign' => (string) ($this->lead_source ?? ''),
                'project' => (string) ($this->project_id ? $this->project?->name : ''),
                'budget' => (string) ($this->budget ?? ''),
                'property_type' => (string) ($this->propertyType?->name ?? ''),
            ];

            // ✅ إزالة القيم الفارغة
            $payload = array_filter($payload, function($value) {
                return $value !== '' && $value !== null;
            });

            Log::info('Sending to AltCRM webhook', [
                'lead_id' => $this->id,
                'payload' => $payload
            ]);

            $response = Http::withHeaders([
                'X-Lead-Token' => 'oia_3aa033e6f47b10c0329a4c2afec903c9',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post('https://webhook.oiaproperties.com/webhook/lead/altcrm', $payload);

            Log::info('AltCRM webhook response', [
                'lead_id' => $this->id,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $currentMoreInfo = $this->more_information ? json_decode($this->more_information, true) : [];
                $currentMoreInfo['altcrm_webhook'] = [
                    'sent_at' => now()->toISOString(),
                    'response' => $response->json(),
                    'payload' => $payload,
                    'status' => 'success'
                ];
                
                $this->update([
                    'more_information' => json_encode($currentMoreInfo)
                ]);

                return true;
            }

            $currentMoreInfo = $this->more_information ? json_decode($this->more_information, true) : [];
            $currentMoreInfo['altcrm_webhook'] = [
                'sent_at' => now()->toISOString(),
                'error' => [
                    'status' => $response->status(),
                    'message' => $response->body(),
                    'payload' => $payload
                ],
                'status' => 'failed'
            ];
            
            $this->update([
                'more_information' => json_encode($currentMoreInfo)
            ]);

            Log::error('Failed to send lead to AltCRM webhook', [
                'lead_id' => $this->id,
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Error sending lead to AltCRM webhook', [
                'lead_id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Check if lead should be synced to AltCRM
     */
    public function shouldSyncToAltCRM(): bool
    {
        return !empty($this->work_phone) || !empty($this->whatsapp_number);
    }
}