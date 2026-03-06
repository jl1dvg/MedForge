<?php

namespace App\Modules\Billing\Services;

use Illuminate\Support\Facades\DB;

class BillingUiService
{
    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarFacturas(?string $mes = null): array
    {
        $filters = $this->resolveMonthRange($mes);
        $whereSql = '';
        $params = [];

        if ($filters !== null) {
            $whereSql = 'WHERE COALESCE(pd.fecha_inicio, pp.fecha, bm.created_at) BETWEEN ? AND ?';
            $params[] = $filters['from'];
            $params[] = $filters['to'];
        }

        $sql = <<<SQL
            SELECT
                bm.id AS billing_id,
                bm.form_id,
                bm.hc_number,
                COALESCE(pd.fecha_inicio, pp.fecha, bm.created_at) AS fecha,
                TRIM(CONCAT_WS(' ', pa.lname, pa.lname2, pa.fname, pa.mname)) AS paciente,
                pa.afiliacion
            FROM billing_main bm
            LEFT JOIN patient_data pa ON pa.hc_number = bm.hc_number
            LEFT JOIN (
                SELECT form_id, MAX(fecha_inicio) AS fecha_inicio
                FROM protocolo_data
                GROUP BY form_id
            ) pd ON pd.form_id = bm.form_id
            LEFT JOIN (
                SELECT form_id, MAX(fecha) AS fecha
                FROM procedimiento_proyectado
                GROUP BY form_id
            ) pp ON pp.form_id = bm.form_id
            {$whereSql}
            ORDER BY COALESCE(pd.fecha_inicio, pp.fecha, bm.created_at) DESC, bm.id DESC
            LIMIT 500
        SQL;

        try {
            $rows = DB::select($sql, $params);
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'billing_id' => (int) ($row->billing_id ?? 0),
                'form_id' => (string) ($row->form_id ?? ''),
                'hc_number' => (string) ($row->hc_number ?? ''),
                'fecha' => (string) ($row->fecha ?? ''),
                'paciente' => trim((string) ($row->paciente ?? '')),
                'afiliacion' => (string) ($row->afiliacion ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function obtenerDetalleFactura(string $formId): ?array
    {
        $formId = trim($formId);
        if ($formId === '') {
            return null;
        }

        try {
            $billing = DB::selectOne(
                <<<SQL
                    SELECT
                        bm.id AS billing_id,
                        bm.form_id,
                        bm.hc_number,
                        bm.created_at,
                        bm.updated_at,
                        COALESCE(pd.fecha_inicio, pp.fecha, bm.created_at) AS fecha,
                        pa.fname,
                        pa.mname,
                        pa.lname,
                        pa.lname2,
                        pa.afiliacion,
                        pa.ci,
                        pa.fecha_nacimiento
                    FROM billing_main bm
                    LEFT JOIN patient_data pa ON pa.hc_number = bm.hc_number
                    LEFT JOIN (
                        SELECT form_id, MAX(fecha_inicio) AS fecha_inicio
                        FROM protocolo_data
                        GROUP BY form_id
                    ) pd ON pd.form_id = bm.form_id
                    LEFT JOIN (
                        SELECT form_id, MAX(fecha) AS fecha
                        FROM procedimiento_proyectado
                        GROUP BY form_id
                    ) pp ON pp.form_id = bm.form_id
                    WHERE bm.form_id = ?
                    LIMIT 1
                SQL,
                [$formId]
            );
        } catch (\Throwable) {
            return null;
        }

        if ($billing === null) {
            return null;
        }

        $billingId = (int) ($billing->billing_id ?? 0);
        if ($billingId <= 0) {
            return null;
        }

        $grupos = [
            'PROCEDIMIENTOS' => $this->cargarProcedimientos($billingId),
            'INSUMOS' => $this->cargarInsumos($billingId),
            'DERECHOS' => $this->cargarDerechos($billingId),
            'ANESTESIA' => $this->cargarAnestesia($billingId),
            'OXIGENO' => $this->cargarOxigeno($billingId),
        ];

        $subtotales = [];
        foreach ($grupos as $grupo => $items) {
            $subtotales[$grupo] = array_reduce($items, static function (float $carry, array $item): float {
                return $carry + (float) ($item['subtotal'] ?? 0);
            }, 0.0);
        }

        $totalSinIva = array_sum($subtotales);
        $iva = $totalSinIva * 0.15;
        $totalConIva = $totalSinIva + $iva;

        return [
            'billing' => [
                'billing_id' => $billingId,
                'form_id' => (string) ($billing->form_id ?? ''),
                'hc_number' => (string) ($billing->hc_number ?? ''),
                'fecha' => (string) ($billing->fecha ?? ''),
                'created_at' => (string) ($billing->created_at ?? ''),
                'updated_at' => (string) ($billing->updated_at ?? ''),
            ],
            'paciente' => [
                'fname' => (string) ($billing->fname ?? ''),
                'mname' => (string) ($billing->mname ?? ''),
                'lname' => (string) ($billing->lname ?? ''),
                'lname2' => (string) ($billing->lname2 ?? ''),
                'afiliacion' => (string) ($billing->afiliacion ?? ''),
                'ci' => (string) ($billing->ci ?? ''),
                'fecha_nacimiento' => (string) ($billing->fecha_nacimiento ?? ''),
            ],
            'metadata' => $this->cargarMetadataDerivacion($formId),
            'grupos' => $grupos,
            'subtotales' => $subtotales,
            'totalSinIva' => $totalSinIva,
            'iva' => $iva,
            'totalConIva' => $totalConIva,
        ];
    }

    /**
     * @return array<int, array{codigo:string,detalle:string,cantidad:float,precio:float,subtotal:float}>
     */
    private function cargarProcedimientos(int $billingId): array
    {
        if (!$this->tableExists('billing_procedimientos')) {
            return [];
        }

        try {
            $rows = DB::select('SELECT proc_codigo, proc_detalle, proc_precio FROM billing_procedimientos WHERE billing_id = ? ORDER BY id ASC', [$billingId]);
        } catch (\Throwable) {
            return [];
        }

        return $this->mapItems($rows, 'proc_codigo', 'proc_detalle', null, 'proc_precio');
    }

    /**
     * @return array<int, array{codigo:string,detalle:string,cantidad:float,precio:float,subtotal:float}>
     */
    private function cargarInsumos(int $billingId): array
    {
        if (!$this->tableExists('billing_insumos')) {
            return [];
        }

        try {
            $rows = DB::select('SELECT codigo, nombre, cantidad, precio FROM billing_insumos WHERE billing_id = ? ORDER BY id ASC', [$billingId]);
        } catch (\Throwable) {
            return [];
        }

        return $this->mapItems($rows, 'codigo', 'nombre', 'cantidad', 'precio');
    }

    /**
     * @return array<int, array{codigo:string,detalle:string,cantidad:float,precio:float,subtotal:float}>
     */
    private function cargarDerechos(int $billingId): array
    {
        if (!$this->tableExists('billing_derechos')) {
            return [];
        }

        try {
            $rows = DB::select('SELECT codigo, detalle, cantidad, precio_afiliacion FROM billing_derechos WHERE billing_id = ? ORDER BY id ASC', [$billingId]);
        } catch (\Throwable) {
            return [];
        }

        return $this->mapItems($rows, 'codigo', 'detalle', 'cantidad', 'precio_afiliacion');
    }

    /**
     * @return array<int, array{codigo:string,detalle:string,cantidad:float,precio:float,subtotal:float}>
     */
    private function cargarAnestesia(int $billingId): array
    {
        if (!$this->tableExists('billing_anestesia')) {
            return [];
        }

        try {
            $rows = DB::select('SELECT codigo, nombre, tiempo, precio FROM billing_anestesia WHERE billing_id = ? ORDER BY id ASC', [$billingId]);
        } catch (\Throwable) {
            return [];
        }

        return $this->mapItems($rows, 'codigo', 'nombre', 'tiempo', 'precio');
    }

    /**
     * @return array<int, array{codigo:string,detalle:string,cantidad:float,precio:float,subtotal:float}>
     */
    private function cargarOxigeno(int $billingId): array
    {
        if (!$this->tableExists('billing_oxigeno')) {
            return [];
        }

        try {
            $rows = DB::select('SELECT codigo, nombre, tiempo, precio FROM billing_oxigeno WHERE billing_id = ? ORDER BY id ASC', [$billingId]);
        } catch (\Throwable) {
            return [];
        }

        return $this->mapItems($rows, 'codigo', 'nombre', 'tiempo', 'precio');
    }

    /**
     * @param array<int, object> $rows
     * @return array<int, array{codigo:string,detalle:string,cantidad:float,precio:float,subtotal:float}>
     */
    private function mapItems(
        array $rows,
        string $codigoField,
        string $detalleField,
        ?string $cantidadField,
        string $precioField
    ): array {
        $items = [];

        foreach ($rows as $row) {
            $codigo = trim((string) data_get((array) $row, $codigoField, ''));
            $detalle = trim((string) data_get((array) $row, $detalleField, ''));
            $cantidad = $cantidadField !== null
                ? (float) data_get((array) $row, $cantidadField, 1)
                : 1.0;
            if ($cantidad <= 0) {
                $cantidad = 1.0;
            }

            $precio = (float) data_get((array) $row, $precioField, 0);
            $subtotal = $cantidad * $precio;

            $items[] = [
                'codigo' => $codigo,
                'detalle' => $detalle,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'subtotal' => $subtotal,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    private function cargarMetadataDerivacion(string $formId): array
    {
        if ($this->tableExists('derivaciones_forms') && $this->tableExists('derivaciones_referral_forms') && $this->tableExists('derivaciones_referrals')) {
            try {
                $row = DB::selectOne(
                    <<<SQL
                        SELECT
                            dr.referral_code AS cod_derivacion,
                            df.referido,
                            COALESCE(df.fecha_registro, dr.issued_at) AS fecha_registro,
                            COALESCE(df.fecha_vigencia, dr.valid_until) AS fecha_vigencia,
                            df.diagnostico
                        FROM derivaciones_forms df
                        LEFT JOIN derivaciones_referral_forms rf ON rf.form_id = df.id
                        LEFT JOIN derivaciones_referrals dr ON dr.id = rf.referral_id
                        WHERE df.iess_form_id = ?
                        ORDER BY COALESCE(rf.linked_at, df.updated_at) DESC, df.id DESC
                        LIMIT 1
                    SQL,
                    [$formId]
                );

                if ($row !== null) {
                    return [
                        'cod_derivacion' => (string) ($row->cod_derivacion ?? ''),
                        'referido' => (string) ($row->referido ?? ''),
                        'fecha_registro' => (string) ($row->fecha_registro ?? ''),
                        'fecha_vigencia' => (string) ($row->fecha_vigencia ?? ''),
                        'diagnostico' => (string) ($row->diagnostico ?? ''),
                    ];
                }
            } catch (\Throwable) {
                // fallback legacy table
            }
        }

        if (!$this->tableExists('derivaciones_form_id')) {
            return [
                'cod_derivacion' => '',
                'referido' => '',
                'fecha_registro' => '',
                'fecha_vigencia' => '',
                'diagnostico' => '',
            ];
        }

        try {
            $row = DB::selectOne(
                'SELECT cod_derivacion, referido, fecha_registro, fecha_vigencia, diagnostico FROM derivaciones_form_id WHERE form_id = ? LIMIT 1',
                [$formId]
            );
        } catch (\Throwable) {
            $row = null;
        }

        return [
            'cod_derivacion' => (string) ($row->cod_derivacion ?? ''),
            'referido' => (string) ($row->referido ?? ''),
            'fecha_registro' => (string) ($row->fecha_registro ?? ''),
            'fecha_vigencia' => (string) ($row->fecha_vigencia ?? ''),
            'diagnostico' => (string) ($row->diagnostico ?? ''),
        ];
    }

    /**
     * @return array{from:string,to:string}|null
     */
    private function resolveMonthRange(?string $mes): ?array
    {
        $mes = trim((string) $mes);
        if ($mes === '' || !preg_match('/^\d{4}-\d{2}$/', $mes)) {
            return null;
        }

        $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mes . '-01 00:00:00');
        if (!$start instanceof \DateTimeImmutable) {
            return null;
        }

        $end = $start->modify('last day of this month')->setTime(23, 59, 59);

        return [
            'from' => $start->format('Y-m-d H:i:s'),
            'to' => $end->format('Y-m-d H:i:s'),
        ];
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        try {
            $rows = DB::select('SHOW TABLES LIKE ?', [$table]);
            $exists = $rows !== [];
        } catch (\Throwable) {
            $exists = false;
        }

        $this->tableExistsCache[$table] = $exists;

        return $exists;
    }
}
