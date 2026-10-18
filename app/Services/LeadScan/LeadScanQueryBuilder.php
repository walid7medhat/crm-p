<?php

namespace App\Services\LeadScan;

class LeadScanQueryBuilder
{
    /**
     * Targeted queries. The returned "q" is sent to the provider and is not stored.
     * "kind" is the only metadata kept on the scan.
     *
     * @param  array<string, mixed>  $lead
     * @return array<int, array{kind: string, q: string}>
     */
    public function queries(array $lead): array
    {
        $max = max(1, (int) config('lead_scan.max_queries', 3));
        $queries = [];
        $name = $this->clean($lead['name'] ?? '');
        $company = $this->clean($lead['company'] ?? '');
        $location = $this->clean($lead['location'] ?? '');
        $email = $this->email($lead['email'] ?? '');
        $phone = $this->phone($lead['phone'] ?? '');

        if ($email !== null) {
            $queries[] = ['kind' => 'email', 'q' => '"'.$email.'"'];
        }
        if ($name !== '' && $company !== '') {
            $queries[] = ['kind' => 'name_company', 'q' => '"'.$name.'" "'.$company.'"'];
        }
        if ($name !== '' && $location !== '') {
            $queries[] = ['kind' => 'name_location', 'q' => '"'.$name.'" "'.$location.'"'];
        }
        if ($email === null && $phone !== null && count($queries) < $max) {
            $queries[] = ['kind' => 'phone', 'q' => '"'.$phone.'"'];
        }
        if ($queries === [] && $name !== '') {
            $queries[] = ['kind' => 'name_only', 'q' => '"'.$name.'"'];
        }

        return array_slice($queries, 0, $max);
    }

    /**
     * Public search link that does not include an email address or phone number.
     *
     * @param  array<string, mixed>  $lead
     */
    public function manualSearchUrl(array $lead): ?string
    {
        $parts = array_filter([
            $this->clean($lead['name'] ?? ''),
            $this->clean($lead['company'] ?? ''),
            $this->clean($lead['location'] ?? ''),
        ]);
        if ($parts === []) {
            return null;
        }

        return 'https://duckduckgo.com/?q='.rawurlencode(implode(' ', $parts));
    }

    /**
     * @param  array<string, mixed>  $lead
     * @return array<string, mixed>
     */
    public function snapshot(object $lead): array
    {
        $name = trim((string) ($lead->lead_name ?? ''));
        if ($name === '') {
            $name = trim((string) (($lead->first_name ?? '').' '.($lead->last_name ?? '')));
        }

        $location = trim((string) ($lead->nationality ?: $lead->branch ?: ''));

        return [
            'name' => $this->clean($name),
            'email' => $this->email($lead->email ?? ''),
            'phone' => $this->phone($lead->whatsapp_number ?: $lead->work_phone ?: ''),
            'company' => $this->clean($lead->company_name ?? ''),
            'position' => $this->clean($lead->position ?? ''),
            'location' => $this->clean($location),
            'website' => $this->website($lead->website ?? ''),
        ];
    }

    private function clean(mixed $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');

        return mb_substr($value, 0, 120);
    }

    private function email(mixed $value): ?string
    {
        $email = strtolower(trim((string) $value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function phone(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return strlen($digits) >= 8 ? $digits : null;
    }

    private function website(mixed $value): ?string
    {
        $url = trim((string) $value);
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || in_array($host, ['localhost'], true)) {
            return null;
        }

        return mb_substr($url, 0, 300);
    }
}
