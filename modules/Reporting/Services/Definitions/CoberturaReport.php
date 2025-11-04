<?php

namespace Modules\Reporting\Services\Definitions;

class CoberturaReport extends AbstractSolicitudReport
{
    public function template(): string
    {
        return 'views/pdf/010.php';
    }

    public function data(array $params): array
    {
        return $this->buildSolicitudData($params);
    }
}
