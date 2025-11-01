<?php
namespace Modules\Pacientes\Helpers;

class PacientesHelper
{
    public static function badgeAfiliacion(?string $afi): string
    {
        if (!$afi) return '<span class="badge bg-secondary">N/A</span>';
        $afiLow = mb_strtolower($afi);
        $color  = in_array($afiLow, ['iess','issfa','isspol','msp']) ? 'info' : 'secondary';
        return "<span class=\"badge bg-{$color}\">".htmlspecialchars($afi)."</span>";
    }
}