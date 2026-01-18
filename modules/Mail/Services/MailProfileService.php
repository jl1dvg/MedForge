<?php

namespace Modules\Mail\Services;

use PDO;

class MailProfileService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getProfileSlugForContext(string $context): ?string
    {
        $normalized = trim(strtolower($context));
        if ($normalized === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT profile_slug FROM mail_profile_assignments WHERE context = :context LIMIT 1'
        );
        $stmt->execute([':context' => $normalized]);
        $slug = $stmt->fetchColumn();

        return is_string($slug) && $slug !== '' ? $slug : null;
    }
}
