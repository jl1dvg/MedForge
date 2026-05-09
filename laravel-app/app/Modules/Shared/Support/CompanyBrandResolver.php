<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

class CompanyBrandResolver
{
    public function __construct(
        private readonly SettingsOptionResolver $settings = new SettingsOptionResolver(),
    ) {
    }

    /**
     * @return array{name:string,legal_name:string|null,logo_url:string|null,logo_path:string|null}
     */
    public function resolve(): array
    {
        $options = $this->settings->getOptions([
            'companyname',
            'company_legal_name',
            'company_logo',
            'company_logo_dark',
            'company_logo_small',
        ]);

        $name = trim((string) ($options['companyname'] ?? ''));
        if ($name === '') {
            $name = trim((string) config('app.name', 'Consulmed')) ?: 'Consulmed';
        }

        $logo = $this->resolveLogo([
            (string) ($options['company_logo'] ?? ''),
            (string) ($options['company_logo_dark'] ?? ''),
            (string) ($options['company_logo_small'] ?? ''),
            '/images/logo-light-text.png',
        ]);

        return [
            'name' => $name,
            'legal_name' => trim((string) ($options['company_legal_name'] ?? '')) ?: null,
            'logo_url' => $logo['url'],
            'logo_path' => $logo['path'],
        ];
    }

    /**
     * @param array<int,string> $candidates
     * @return array{url:string|null,path:string|null}
     */
    private function resolveLogo(array $candidates): array
    {
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            if (preg_match('#^https?://#i', $candidate) === 1) {
                return ['url' => $candidate, 'path' => $candidate];
            }

            $normalized = '/' . ltrim($candidate, '/');
            foreach ($this->candidatePublicPaths($normalized) as $publicRoot) {
                $absolute = rtrim($publicRoot, DIRECTORY_SEPARATOR) . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
                if (is_file($absolute)) {
                    return ['url' => $normalized, 'path' => $absolute];
                }
            }

            foreach ($this->candidateRelativePaths($candidate) as $relative) {
                foreach ($this->candidatePublicPaths('/' . $relative) as $publicRoot) {
                    $absolute = rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                    if (is_file($absolute)) {
                        return ['url' => '/' . $relative, 'path' => $absolute];
                    }
                }
            }
        }

        return ['url' => null, 'path' => null];
    }

    /**
     * @return array<int,string>
     */
    private function candidatePublicPaths(string $relative): array
    {
        $paths = [];
        if (function_exists('public_path')) {
            $paths[] = public_path();
        }
        if (function_exists('base_path')) {
            $paths[] = base_path('../public');
        }

        return array_values(array_unique(array_filter($paths, static fn(string $path): bool => $path !== '')));
    }

    /**
     * @return array<int,string>
     */
    private function candidateRelativePaths(string $value): array
    {
        $value = ltrim($value, '/');

        return array_values(array_unique([
            $value,
            'uploads/company/' . basename($value),
            'uploads/settings/' . basename($value),
            'uploads/' . basename($value),
            'images/' . basename($value),
        ]));
    }
}
