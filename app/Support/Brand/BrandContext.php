<?php

namespace App\Support\Brand;

/**
 * Request-scoped active brand. Resolved once by ResolveBrandFromHost.
 */
class BrandContext
{
    private ?array $brand = null;

    public function set(array $brand): void
    {
        $this->brand = $brand;
    }

    public function has(): bool
    {
        return is_array($this->brand) && isset($this->brand['key']);
    }

    public function get(): array
    {
        if (!$this->has()) {
            return BrandResolver::defaultBrand();
        }

        return $this->brand;
    }

    public function key(): string
    {
        return (string) ($this->get()['key'] ?? 'oia');
    }

    public function is(string $key): bool
    {
        return $this->key() === $key;
    }

    public function isLuxvant(): bool
    {
        return $this->is('luxvant');
    }

    public function isOia(): bool
    {
        return $this->is('oia');
    }

    public function companyName(): string
    {
        return (string) ($this->get()['company_name'] ?? 'OIA Properties');
    }

    public function companyNameDisplay(): string
    {
        return (string) ($this->get()['company_name_display'] ?? 'OIA PROPERTIES');
    }

    public function companyNameShort(): string
    {
        return (string) ($this->get()['company_name_short'] ?? 'OIA');
    }

    public function productName(): string
    {
        return (string) ($this->get()['product_name'] ?? 'Alt CRM');
    }

    public function documentTitle(): string
    {
        return (string) ($this->get()['document_title'] ?? 'Alt CRM');
    }

    public function logos(): array
    {
        return (array) ($this->get()['logos'] ?? []);
    }

    public function colors(): array
    {
        return (array) ($this->get()['colors'] ?? []);
    }

    public function copy(): array
    {
        return (array) ($this->get()['copy'] ?? []);
    }

    public function access(): array
    {
        return (array) ($this->get()['access'] ?? ['mode' => 'open']);
    }

    /**
     * Public payload for Blade + Vue (no internal allowlist emails beyond what's needed).
     */
    public function toFrontendArray(): array
    {
        $brand = $this->get();

        return [
            'key' => $brand['key'],
            'company_name' => $brand['company_name'],
            'company_name_short' => $brand['company_name_short'],
            'company_name_display' => $brand['company_name_display'],
            'product_name' => $brand['product_name'],
            'product_mark' => $brand['product_mark'],
            'document_title' => $brand['document_title'],
            'email_domain' => $brand['email_domain'] ?? null,
            'logos' => $brand['logos'],
            'colors' => $brand['colors'],
            'copy' => $brand['copy'],
        ];
    }

    public function cssVariableMap(): array
    {
        $c = $this->colors();

        return [
            '--color-primary' => $c['primary'] ?? null,
            '--color-primary-hover' => $c['primary_hover'] ?? null,
            '--color-primary-active' => $c['primary_active'] ?? null,
            '--color-primary-rgb' => $c['primary_rgb'] ?? null,
            '--color-primary-hover-rgb' => $c['primary_hover_rgb'] ?? null,
            '--color-primary-active-rgb' => $c['primary_active_rgb'] ?? null,
            '--color-primary-soft' => $c['primary_soft'] ?? null,
            '--color-primary-border' => $c['primary_border'] ?? null,
            '--color-primary-muted' => $c['primary_muted'] ?? null,
            '--color-primary-glow' => $c['primary_glow'] ?? null,
            '--color-primary-bright' => $c['primary_bright'] ?? null,
            '--color-primary-ink' => $c['primary_ink'] ?? null,
            '--color-on-primary' => $c['on_primary'] ?? null,
            '--color-background' => $c['background'] ?? null,
            '--color-background-plain' => $c['background_plain'] ?? null,
            '--color-surface' => $c['surface'] ?? null,
            '--color-border' => $c['border'] ?? null,
            '--color-text-primary' => $c['text'] ?? null,
            '--gradient-primary' => $c['gradient_primary'] ?? null,
            '--gradient-primary-hover' => $c['gradient_primary_hover'] ?? null,
            '--gradient-primary-active' => $c['gradient_primary_active'] ?? null,
            '--brand-primary' => $c['primary'] ?? null,
            '--brand-secondary' => $c['secondary'] ?? null,
            '--brand-accent' => $c['accent'] ?? null,
            '--brand-background' => $c['background'] ?? null,
            '--brand-surface' => $c['surface'] ?? null,
            '--brand-sidebar' => $c['sidebar'] ?? null,
            '--brand-text' => $c['text'] ?? null,
            '--brand-border' => $c['border'] ?? null,
        ];
    }
}
