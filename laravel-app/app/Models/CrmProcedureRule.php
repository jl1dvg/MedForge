<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CrmProcedureRule extends Model
{
    protected $table = 'crm_procedure_rules';

    protected $fillable = [
        'codigo',
        'grupo_codigo',
        'nombre',
        'tipo',
        'ventana_dias',
        'agrupar_por_ojo',
        'genera_oportunidad',
        'activo',
    ];

    protected $casts = [
        'ventana_dias'      => 'integer',
        'agrupar_por_ojo'   => 'integer',
        'genera_oportunidad'=> 'integer',
        'activo'            => 'integer',
    ];

    private const CACHE_TTL = 600; // 10 minutes

    /**
     * Returns the active rule for a procedure code, or a conservative fallback.
     *
     * The fallback (matched=false) signals the caller that no rule exists yet.
     * Callers must never derive behavior from the code string itself.
     *
     * @return array{tipo:string, grupo_codigo:string|null, ventana_dias:int|null,
     *               agrupar_por_ojo:int, genera_oportunidad:int, matched:bool}
     */
    public static function forCodigo(string $codigo): array
    {
        $cacheKey = 'crm_procedure_rule:' . $codigo;

        return Cache::remember($cacheKey, self::CACHE_TTL, static function () use ($codigo): array {
            $row = static::query()
                ->where('codigo', $codigo)
                ->where('activo', 1)
                ->first();

            if ($row === null) {
                return self::fallback();
            }

            return [
                'tipo'              => $row->tipo,
                'grupo_codigo'      => $row->grupo_codigo,
                'ventana_dias'      => $row->ventana_dias,
                'agrupar_por_ojo'   => $row->agrupar_por_ojo,
                'genera_oportunidad'=> $row->genera_oportunidad,
                'matched'           => true,
            ];
        });
    }

    public static function clearCache(string $codigo): void
    {
        Cache::forget('crm_procedure_rule:' . $codigo);
    }

    /** Conservative fallback when no rule exists. genera_oportunidad=1, tipo=unica. */
    private static function fallback(): array
    {
        return [
            'tipo'              => 'unica',
            'grupo_codigo'      => null,
            'ventana_dias'      => null,
            'agrupar_por_ojo'   => 1,
            'genera_oportunidad'=> 1,
            'matched'           => false,
        ];
    }
}
