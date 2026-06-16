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
            $payload = array_merge([
                'id' => $this->id,
                'phone' => $this->work_phone ?? $this->whatsapp_number,
                'name' => $this->lead_name ?? $this->first_name . ' ' . $this->last_name,
                'email' => $this->email,
                'external_id' => $this->meta_lead_id ?? $this->id,
                'campaign' => $this->lead_source ?? null,
                'project' => $this->project_id ? $this->project?->name : null,
                'project_id' => $this->project_id ? $this->project?->id : null,
                'budget' => $this->budget,
                'property_type' => $this->propertyType?->name ?? null,
                'property_type_id' => $this->propertyType?->id ?? null,
            ], $additionalData);

            $payload = array_filter($payload, fn($value) => !is_null($value));

            $response = Http::withHeaders([
                'X-Lead-Token' => 'oia_3aa033e6f47b10c0329a4c2afec903c9',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post('https://webhook.oiaproperties.com/webhook/lead/altcrm', $payload);

            // ✅ تخزين الـ response في more_information كـ JSON string
            $currentMoreInfo = $this->more_information ? json_decode($this->more_information, true) : [];
            
            if ($response->successful()) {
                $currentMoreInfo['altcrm_webhook'] = [
                    'sent_at' => now()->toISOString(),
                    'response' => $response->json(),
                    'payload' => $payload,
                    'status' => 'success'
                ];
                
                $this->update([
                    'more_information' => json_encode($currentMoreInfo)
                ]);

                Log::info('Lead sent to AltCRM webhook successfully', [
                    'lead_id' => $this->id
                ]);

                return true;
            }

            // فشل الإرسال
            $currentMoreInfo['altcrm_webhook'] = [
                'sent_at' => now()->toISOString(),
                'error' => [
                    'status' => $response->status(),
                    'message' => $response->body()
                ],
                'payload' => $payload,
                'status' => 'failed'
            ];
            
            $this->update([
                'more_information' => json_encode($currentMoreInfo)
            ]);

            Log::error('Failed to send lead to AltCRM webhook', [
                'lead_id' => $this->id,
                'status' => $response->status()
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Error sending lead to AltCRM webhook', [
                'lead_id' => $this->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Check if lead should be synced to AltCRM
     */
    public function shouldSyncToAltCRM(): bool
    {
        return $this->work_phone || $this->whatsapp_number;
    }
}