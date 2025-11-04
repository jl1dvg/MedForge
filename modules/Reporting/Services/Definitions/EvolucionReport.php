<?php

namespace Modules\Reporting\Services\Definitions;

class EvolucionReport extends AbstractSolicitudReport
{
    public function template(): string
    {
        return 'views/pdf/007.php';
    }

    public function data(array $params): array
    {
        return $this->buildSolicitudData($params);
    }
}
