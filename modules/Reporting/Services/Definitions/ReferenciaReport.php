<?php

namespace Modules\Reporting\Services\Definitions;

class ReferenciaReport extends AbstractSolicitudReport
{
    public function template(): string
    {
        return 'views/pdf/referencia.php';
    }

    public function data(array $params): array
    {
        return $this->buildSolicitudData($params);
    }
}
