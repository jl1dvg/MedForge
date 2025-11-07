<?php

namespace Modules\WhatsApp\Support;

class PhoneNumberFormatter
{
    /**
     * @param string|array<int, string> $recipients
     * @param array<string, string> $config
     *
     * @return array<int, string>
     */
    public static function normalizeRecipients($recipients, array $config): array
    {
        $list = is_array($recipients) ? $recipients : [$recipients];
        $normalized = [];

        foreach ($list as $recipient) {
            $formatted = self::formatPhoneNumber((string) $recipient, $config);
            if ($formatted !== null) {
                $normalized[$formatted] = $formatted;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param array<string, string> $config
     */
    public static function formatPhoneNumber(string $phone, array $config): ?string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }

        if (str_starts_with($phone, '+')) {
            $digits = '+' . preg_replace('/\D+/', '', substr($phone, 1));

            return strlen($digits) > 1 ? $digits : null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $country = preg_replace('/\D+/', '', $config['default_country_code'] ?? '');
        if ($country !== '' && !str_starts_with($digits, $country)) {
            $digits = $country . $digits;
        }

        return '+' . $digits;
    }
}
