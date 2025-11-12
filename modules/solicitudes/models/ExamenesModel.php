<?php

namespace Models;

use Modules\Examenes\Models\ExamenesModel as BaseExamenesModel;

if (!class_exists(__NAMESPACE__ . '\\ExamenesModel', false)) {
    /**
     * @deprecated Compatibilidad para código legado que esperaba Models\\ExamenesModel.
     */
    class ExamenesModel extends BaseExamenesModel
    {
    }
}
