<?php

namespace Controllers;

if (class_exists(__NAMESPACE__ . '\\ExamenesController', false)) {
    return;
}

/**
 * @deprecated Legacy stub — no active callers. modules/solicitudes/ retired.
 *             All solicitudes flows now live in the Laravel application.
 */
class ExamenesController
{
    public function __construct(private \PDO $pdo)
    {
    }
}
