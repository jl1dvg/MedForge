<?php

namespace Modules\Billing\Services;

use DateTime;
use DOMDocument;
use Models\BillingSriDocumentModel;
use Modules\Pacientes\Services\PacienteService;
use PDO;

class SriService
{
    private BillingSriDocumentModel $documentModel;
    private PacienteService $pacienteService;
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(private readonly PDO $pdo)
    {
        $this->documentModel = new BillingSriDocumentModel($pdo);
        $this->pacienteService = new PacienteService($pdo);
        $this->config = [
            'mode' => getenv('SRI_MODE') ?: 'simulation',
            'api_url' => getenv('SRI_API_URL') ?: null,
            'company_name' => getenv('SRI_COMPANY_NAME') ?: 'Clínica Internacional de Visión del Ecuador',
            'commercial_name' => getenv('SRI_COMMERCIAL_NAME') ?: 'CIVE',
            'company_ruc' => getenv('SRI_COMPANY_RUC') ?: '9999999999999',
            'establishment_code' => getenv('SRI_ESTABLISHMENT_CODE') ?: '001',
            'emission_point' => getenv('SRI_EMISSION_POINT') ?: '001',
            'matrix_address' => getenv('SRI_MATRIX_ADDRESS') ?: 'Guayaquil, Ecuador',
            'establishment_address' => getenv('SRI_ESTABLISHMENT_ADDRESS') ?: 'Daule, km 12 Av. León Febres-Cordero',
        ];
    }

    /**
     * Intenta generar y enviar un comprobante electrónico al SRI.
     *
     * @param int $billingId Identificador interno de billing_main
     * @param string $formId Form ID asociado a la factura
     * @param string $hcNumber Historia clínica del paciente
     * @param array $detalle Datos completos de la factura devueltos por el legacy controller
     *
     * @return array{success:bool, estado:string, documento:array|null, error?:string}
     */
    public function registrarFactura(int $billingId, string $formId, string $hcNumber, array $detalle): array
    {
        try {
            $existing = $this->documentModel->findLatestByBillingId($billingId);
            $claveAcceso = $existing['clave_acceso'] ?? $this->generarClaveAcceso($detalle['billing'] ?? [], $formId);
            $xml = $this->generarXmlFactura($formId, $hcNumber, $detalle, $claveAcceso);

            if ($existing) {
                $documentId = (int) $existing['id'];
                $this->documentModel->update($documentId, [
                    'xml_enviado' => $xml,
                    'clave_acceso' => $claveAcceso,
                ]);

                $estadoActual = strtoupper((string) ($existing['estado'] ?? ''));
                if ($estadoActual === 'AUTORIZADO') {
                    $documento = $this->documentModel->findById($documentId);

                    return [
                        'success' => true,
                        'estado' => $estadoActual,
                        'documento' => $documento ?: null,
                    ];
                }
            } else {
                $documentId = $this->documentModel->create($billingId, [
                    'estado' => 'pendiente',
                    'clave_acceso' => $claveAcceso,
                    'xml_enviado' => $xml,
                ]);
            }

            $resultado = $this->procesarEnvio($documentId, $xml, $claveAcceso, $detalle);
            $documentoActualizado = $this->documentModel->findById($documentId);

            return [
                'success' => $resultado['success'],
                'estado' => strtoupper((string) ($documentoActualizado['estado'] ?? ($resultado['success'] ? 'SIMULADO' : 'ERROR'))),
                'documento' => $documentoActualizado ?: null,
                ...($resultado['success'] ? [] : ['error' => $resultado['message'] ?? 'Error desconocido']),
            ];
        } catch (\Throwable $exception) {
            error_log('Error al registrar factura en SRI: ' . $exception->getMessage());

            return [
                'success' => false,
                'estado' => 'ERROR',
                'documento' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param int $documentId
     * @param string $xml
     * @param string $claveAcceso
     * @param array $detalle
     * @return array{success:bool, message?:string, estado?:string}
     */
    private function procesarEnvio(int $documentId, string $xml, string $claveAcceso, array $detalle): array
    {
        $this->documentModel->incrementarIntentos($documentId);
        $now = (new DateTime())->format('Y-m-d H:i:s');

        $modo = strtolower((string) ($this->config['mode'] ?? 'simulation'));
        if ($modo === 'simulation' || empty($this->config['api_url'])) {
            $mensaje = [
                'modo' => $modo,
                'descripcion' => 'No se envió al SRI porque el modo está configurado como simulación.',
                'form_id' => $detalle['billing']['form_id'] ?? null,
            ];

            $this->documentModel->update($documentId, [
                'estado' => 'simulado',
                'respuesta' => json_encode($mensaje, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'errores' => null,
                'last_sent_at' => $now,
            ]);

            return ['success' => true, 'estado' => 'SIMULADO'];
        }

        $envio = $this->enviarComprobante($xml, $claveAcceso);
        $payload = $envio['data'] ?? null;
        $payloadSerializado = is_array($payload)
            ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : (string) ($payload ?? '');

        if ($envio['success']) {
            $estadoRespuesta = strtoupper((string) ($payload['estado'] ?? 'ENVIADO'));
            $numeroAutorizacion = $payload['numeroAutorizacion']
                ?? $payload['numero_autorizacion']
                ?? null;

            $this->documentModel->update($documentId, [
                'estado' => $estadoRespuesta,
                'numero_autorizacion' => $numeroAutorizacion,
                'respuesta' => $payloadSerializado ?: null,
                'errores' => null,
                'last_sent_at' => $now,
            ]);

            return ['success' => true, 'estado' => $estadoRespuesta];
        }

        $mensajeError = $envio['message'] ?? 'Error desconocido al enviar al SRI.';
        $errores = $payloadSerializado ?: $mensajeError;

        $this->documentModel->update($documentId, [
            'estado' => 'error',
            'errores' => $errores,
            'respuesta' => $payloadSerializado ?: null,
            'last_sent_at' => $now,
        ]);

        return ['success' => false, 'message' => $mensajeError];
    }

    /**
     * @return array{success:bool, data?:array|string, message?:string}
     */
    private function enviarComprobante(string $xml, string $claveAcceso): array
    {
        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'message' => 'La extensión cURL no está disponible en el servidor.',
            ];
        }

        $endpoint = rtrim((string) $this->config['api_url'], '/') . '/recepcion';
        $payload = [
            'claveAcceso' => $claveAcceso,
            'comprobante' => base64_encode($xml),
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->obtenerHeaders(),
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 30,
        ]);

        $respuesta = curl_exec($ch);
        if ($respuesta === false) {
            $error = curl_error($ch);
            curl_close($ch);

            return [
                'success' => false,
                'message' => $error ?: 'Error desconocido al enviar la factura al SRI.',
            ];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($respuesta, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $decoded ?? $respuesta,
            ];
        }

        return [
            'success' => false,
            'data' => $decoded ?? $respuesta,
            'message' => is_array($decoded) && isset($decoded['mensaje'])
                ? (string) $decoded['mensaje']
                : 'El SRI devolvió un estado HTTP ' . $httpCode,
        ];
    }

    /**
     * @return string[]
     */
    private function obtenerHeaders(): array
    {
        $headers = ['Content-Type: application/json'];
        $token = getenv('SRI_API_TOKEN');
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        return $headers;
    }

    private function generarClaveAcceso(array $billing, string $formId): string
    {
        $fecha = isset($billing['created_at']) ? (new DateTime($billing['created_at']))->format('dmY') : date('dmY');
        $tipoComprobante = '01';
        $ruc = preg_replace('/\D/', '', (string) ($this->config['company_ruc'] ?? '9999999999999')) ?: '9999999999999';
        $ambiente = strtolower((string) ($this->config['mode'] ?? 'simulation')) === 'production' ? '2' : '1';
        $serie = str_pad((string) ($this->config['establishment_code'] ?? '001'), 3, '0', STR_PAD_LEFT)
            . str_pad((string) ($this->config['emission_point'] ?? '001'), 3, '0', STR_PAD_LEFT);
        $numeroComprobante = str_pad((string) ($billing['id'] ?? 0), 9, '0', STR_PAD_LEFT);
        $codigoNumerico = substr(str_pad((string) abs(crc32($formId . microtime(true))), 8, '0', STR_PAD_LEFT), 0, 8);
        $tipoEmision = '1';

        $claveBase = $fecha . $tipoComprobante . $ruc . $ambiente . $serie . $numeroComprobante . $codigoNumerico . $tipoEmision;
        $digitoVerificador = $this->calcularDigitoVerificador($claveBase);

        return $claveBase . $digitoVerificador;
    }

    private function calcularDigitoVerificador(string $clave): string
    {
        $total = 0;
        $factor = 2;

        for ($i = strlen($clave) - 1; $i >= 0; $i--) {
            $total += (int) $clave[$i] * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        $modulo = 11 - ($total % 11);
        if ($modulo === 11) {
            return '0';
        }

        if ($modulo === 10) {
            return '1';
        }

        return (string) $modulo;
    }

    private function generarXmlFactura(string $formId, string $hcNumber, array $detalle, string $claveAcceso): string
    {
        $paciente = $detalle['paciente'] ?? $this->pacienteService->getPatientDetails($hcNumber);
        $fechaEmision = $detalle['billing']['created_at'] ?? date('Y-m-d');

        $items = $this->extraerItems($detalle);
        $totales = $this->calcularTotales($items);
        $secuencial = str_pad((string) ($detalle['billing']['id'] ?? 0), 9, '0', STR_PAD_LEFT);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $factura = $doc->createElement('factura');
        $factura->setAttribute('id', 'comprobante');
        $factura->setAttribute('version', '1.1.0');
        $doc->appendChild($factura);

        $factura->appendChild($this->crearInfoTributaria($doc, $claveAcceso, $secuencial));
        $factura->appendChild($this->crearInfoFactura($doc, $fechaEmision, $paciente, $totales));
        $factura->appendChild($this->crearDetalles($doc, $items));
        $factura->appendChild($this->crearInfoAdicional($doc, $paciente, $formId, $totales));

        return $doc->saveXML() ?: '';
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{baseImponible:float, baseExenta:float, iva:float, total:float}
     */
    private function calcularTotales(array &$items): array
    {
        $baseImponible = 0.0;
        $baseExenta = 0.0;
        $iva = 0.0;

        foreach ($items as &$item) {
            $cantidad = (float) $item['cantidad'];
            $precioUnitario = (float) $item['precioUnitario'];
            $subtotal = $cantidad * $precioUnitario;
            $item['precioTotalSinImpuesto'] = $subtotal;

            if (!empty($item['aplicaIva'])) {
                $valorIva = round($subtotal * 0.15, 2);
                $item['valorIva'] = $valorIva;
                $baseImponible += $subtotal;
                $iva += $valorIva;
            } else {
                $item['valorIva'] = 0.0;
                $baseExenta += $subtotal;
            }
        }
        unset($item);

        return [
            'baseImponible' => round($baseImponible, 2),
            'baseExenta' => round($baseExenta, 2),
            'iva' => round($iva, 2),
            'total' => round($baseImponible + $baseExenta + $iva, 2),
        ];
    }

    /**
     * @param array<string, mixed> $detalle
     * @return array<int, array<string, mixed>>
     */
    private function extraerItems(array $detalle): array
    {
        $items = [];

        foreach ($detalle['procedimientos'] ?? [] as $procedimiento) {
            $items[] = $this->mapItem($procedimiento, [
                'codigo' => $procedimiento['proc_codigo'] ?? $procedimiento['procCodigo'] ?? 'PROC',
                'descripcion' => $procedimiento['proc_detalle'] ?? $procedimiento['procDetalle'] ?? 'Procedimiento',
                'cantidad' => $procedimiento['cantidad'] ?? 1,
                'precio' => $procedimiento['proc_precio'] ?? $procedimiento['procPrecio'] ?? 0,
                'iva' => true,
            ]);
        }

        foreach ($detalle['derechos'] ?? [] as $derecho) {
            $items[] = $this->mapItem($derecho, [
                'codigo' => $derecho['codigo'] ?? 'DER',
                'descripcion' => $derecho['detalle'] ?? 'Derecho',
                'cantidad' => $derecho['cantidad'] ?? 1,
                'precio' => $derecho['precio_afiliacion'] ?? $derecho['precioAfiliacion'] ?? 0,
                'iva' => (int) ($derecho['iva'] ?? 0) === 1,
            ]);
        }

        foreach ($detalle['insumos'] ?? [] as $insumo) {
            $items[] = $this->mapItem($insumo, [
                'codigo' => $insumo['codigo'] ?? 'INS',
                'descripcion' => $insumo['nombre'] ?? 'Insumo',
                'cantidad' => $insumo['cantidad'] ?? 1,
                'precio' => $insumo['precio'] ?? 0,
                'iva' => (int) ($insumo['iva'] ?? 0) === 1,
            ]);
        }

        foreach ($detalle['medicamentos'] ?? [] as $medicamento) {
            $items[] = $this->mapItem($medicamento, [
                'codigo' => $medicamento['codigo'] ?? 'MED',
                'descripcion' => $medicamento['nombre'] ?? 'Medicamento',
                'cantidad' => $medicamento['cantidad'] ?? 1,
                'precio' => $medicamento['precio'] ?? 0,
                'iva' => false,
            ]);
        }

        foreach ($detalle['anestesia'] ?? [] as $anestesia) {
            $items[] = $this->mapItem($anestesia, [
                'codigo' => $anestesia['codigo'] ?? 'ANEST',
                'descripcion' => $anestesia['nombre'] ?? 'Servicio de anestesia',
                'cantidad' => $anestesia['tiempo'] ?? 1,
                'precio' => $anestesia['precio'] ?? 0,
                'iva' => true,
            ]);
        }

        foreach ($detalle['oxigeno'] ?? [] as $oxigeno) {
            $items[] = $this->mapItem($oxigeno, [
                'codigo' => $oxigeno['codigo'] ?? 'OXI',
                'descripcion' => $oxigeno['nombre'] ?? 'Oxígeno',
                'cantidad' => $oxigeno['tiempo'] ?? 1,
                'precio' => $oxigeno['precio'] ?? 0,
                'iva' => true,
            ]);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $item
     * @param array{codigo:string, descripcion:string, cantidad:mixed, precio:mixed, iva:bool} $valores
     * @return array<string, mixed>
     */
    private function mapItem(array $item, array $valores): array
    {
        return [
            'codigo' => (string) $valores['codigo'],
            'descripcion' => (string) $valores['descripcion'],
            'cantidad' => (float) $valores['cantidad'],
            'precioUnitario' => (float) $valores['precio'],
            'aplicaIva' => $valores['iva'],
            'datos' => $item,
        ];
    }

    private function crearInfoTributaria(DOMDocument $doc, string $claveAcceso, string $secuencial): \DOMElement
    {
        $info = $doc->createElement('infoTributaria');
        $info->appendChild($doc->createElement('razonSocial', $this->config['company_name']));
        $info->appendChild($doc->createElement('nombreComercial', $this->config['commercial_name']));
        $info->appendChild($doc->createElement('ruc', $this->config['company_ruc']));
        $ambiente = strtolower((string) ($this->config['mode'] ?? 'simulation')) === 'production' ? '2' : '1';
        $info->appendChild($doc->createElement('ambiente', $ambiente));
        $info->appendChild($doc->createElement('tipoEmision', '1'));
        $info->appendChild($doc->createElement('claveAcceso', $claveAcceso));
        $info->appendChild($doc->createElement('codDoc', '01'));
        $info->appendChild($doc->createElement('estab', str_pad((string) $this->config['establishment_code'], 3, '0', STR_PAD_LEFT)));
        $info->appendChild($doc->createElement('ptoEmi', str_pad((string) $this->config['emission_point'], 3, '0', STR_PAD_LEFT)));
        $info->appendChild($doc->createElement('secuencial', $secuencial));
        $info->appendChild($doc->createElement('dirMatriz', $this->config['matrix_address']));

        return $info;
    }

    /**
     * @param array<string, mixed> $paciente
     * @param array{baseImponible:float, baseExenta:float, iva:float, total:float} $totales
     */
    private function crearInfoFactura(DOMDocument $doc, string $fechaEmision, array $paciente, array $totales): \DOMElement
    {
        $infoFactura = $doc->createElement('infoFactura');
        $infoFactura->appendChild($doc->createElement('fechaEmision', date('d/m/Y', strtotime($fechaEmision))));
        $infoFactura->appendChild($doc->createElement('dirEstablecimiento', $this->config['establishment_address']));
        $infoFactura->appendChild($doc->createElement('obligadoContabilidad', 'SI'));
        $infoFactura->appendChild($doc->createElement('tipoIdentificacionComprador', $this->obtenerTipoIdentificacion($paciente)));
        $infoFactura->appendChild($doc->createElement('razonSocialComprador', $this->obtenerNombrePaciente($paciente)));
        $infoFactura->appendChild($doc->createElement('identificacionComprador', $this->obtenerIdentificacionPaciente($paciente)));
        $infoFactura->appendChild($doc->createElement('direccionComprador', $paciente['direccion'] ?? 'S/D'));
        $infoFactura->appendChild($doc->createElement('totalSinImpuestos', $this->formatearDecimal($totales['baseImponible'] + $totales['baseExenta'])));
        $infoFactura->appendChild($doc->createElement('totalDescuento', '0.00'));

        $totalImpuestos = $doc->createElement('totalConImpuestos');
        if ($totales['baseImponible'] > 0) {
            $totalImpuesto = $doc->createElement('totalImpuesto');
            $totalImpuesto->appendChild($doc->createElement('codigo', '2'));
            $totalImpuesto->appendChild($doc->createElement('codigoPorcentaje', '3'));
            $totalImpuesto->appendChild($doc->createElement('baseImponible', $this->formatearDecimal($totales['baseImponible'])));
            $totalImpuesto->appendChild($doc->createElement('tarifa', '15.00'));
            $totalImpuesto->appendChild($doc->createElement('valor', $this->formatearDecimal($totales['iva'])));
            $totalImpuestos->appendChild($totalImpuesto);
        }

        if ($totales['baseExenta'] > 0) {
            $totalImpuesto = $doc->createElement('totalImpuesto');
            $totalImpuesto->appendChild($doc->createElement('codigo', '2'));
            $totalImpuesto->appendChild($doc->createElement('codigoPorcentaje', '0'));
            $totalImpuesto->appendChild($doc->createElement('baseImponible', $this->formatearDecimal($totales['baseExenta'])));
            $totalImpuesto->appendChild($doc->createElement('tarifa', '0.00'));
            $totalImpuesto->appendChild($doc->createElement('valor', '0.00'));
            $totalImpuestos->appendChild($totalImpuesto);
        }

        $infoFactura->appendChild($totalImpuestos);
        $infoFactura->appendChild($doc->createElement('propina', '0.00'));
        $infoFactura->appendChild($doc->createElement('importeTotal', $this->formatearDecimal($totales['total'])));
        $infoFactura->appendChild($doc->createElement('moneda', 'DOLAR'));

        $pagos = $doc->createElement('pagos');
        $pago = $doc->createElement('pago');
        $pago->appendChild($doc->createElement('formaPago', '20'));
        $pago->appendChild($doc->createElement('total', $this->formatearDecimal($totales['total'])));
        $pago->appendChild($doc->createElement('plazo', '0'));
        $pago->appendChild($doc->createElement('unidadTiempo', 'dias'));
        $pagos->appendChild($pago);
        $infoFactura->appendChild($pagos);

        return $infoFactura;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function crearDetalles(DOMDocument $doc, array $items): \DOMElement
    {
        $detalles = $doc->createElement('detalles');

        foreach ($items as $item) {
            $detalle = $doc->createElement('detalle');
            $detalle->appendChild($doc->createElement('codigoPrincipal', $item['codigo']));
            $detalle->appendChild($doc->createElement('descripcion', substr($item['descripcion'], 0, 300)));
            $detalle->appendChild($doc->createElement('cantidad', $this->formatearDecimal($item['cantidad'])));
            $detalle->appendChild($doc->createElement('precioUnitario', $this->formatearDecimal($item['precioUnitario'])));
            $detalle->appendChild($doc->createElement('descuento', '0.00'));
            $detalle->appendChild($doc->createElement('precioTotalSinImpuesto', $this->formatearDecimal($item['precioTotalSinImpuesto'])));

            $impuestos = $doc->createElement('impuestos');
            $impuesto = $doc->createElement('impuesto');
            $impuesto->appendChild($doc->createElement('codigo', '2'));
            $impuesto->appendChild($doc->createElement('codigoPorcentaje', !empty($item['aplicaIva']) ? '3' : '0'));
            $impuesto->appendChild($doc->createElement('tarifa', !empty($item['aplicaIva']) ? '15.00' : '0.00'));
            $impuesto->appendChild($doc->createElement('baseImponible', $this->formatearDecimal($item['precioTotalSinImpuesto'])));
            $impuesto->appendChild($doc->createElement('valor', $this->formatearDecimal($item['valorIva'])));
            $impuestos->appendChild($impuesto);

            $detalle->appendChild($impuestos);
            $detalles->appendChild($detalle);
        }

        return $detalles;
    }

    private function crearInfoAdicional(DOMDocument $doc, array $paciente, string $formId, array $totales): \DOMElement
    {
        $infoAdicional = $doc->createElement('infoAdicional');

        $infoAdicional->appendChild($this->crearCampoAdicional($doc, 'Paciente', $this->obtenerNombrePaciente($paciente)));
        $infoAdicional->appendChild($this->crearCampoAdicional($doc, 'HC', (string) ($paciente['hc_number'] ?? '')));
        $infoAdicional->appendChild($this->crearCampoAdicional($doc, 'FormId', $formId));
        $infoAdicional->appendChild($this->crearCampoAdicional($doc, 'TotalFactura', $this->formatearDecimal($totales['total'])));

        if (!empty($paciente['telefono'])) {
            $infoAdicional->appendChild($this->crearCampoAdicional($doc, 'Telefono', (string) $paciente['telefono']));
        }

        if (!empty($paciente['email'])) {
            $infoAdicional->appendChild($this->crearCampoAdicional($doc, 'Email', (string) $paciente['email']));
        }

        return $infoAdicional;
    }

    private function crearCampoAdicional(DOMDocument $doc, string $nombre, string $valor): \DOMElement
    {
        $campo = $doc->createElement('campoAdicional');
        $campo->setAttribute('nombre', $nombre);
        $campo->appendChild($doc->createTextNode($valor));

        return $campo;
    }

    private function obtenerTipoIdentificacion(array $paciente): string
    {
        $ci = $paciente['ci'] ?? $paciente['documento'] ?? null;
        if ($ci && strlen(preg_replace('/\D/', '', (string) $ci)) === 13) {
            return '04'; // RUC
        }
        if ($ci && strlen(preg_replace('/\D/', '', (string) $ci)) === 10) {
            return '05'; // Cédula
        }

        return '07'; // Consumidor final
    }

    private function obtenerNombrePaciente(array $paciente): string
    {
        $partes = array_filter([
            $paciente['lname'] ?? null,
            $paciente['lname2'] ?? null,
            $paciente['fname'] ?? null,
            $paciente['mname'] ?? null,
        ]);

        return $partes ? implode(' ', $partes) : ($paciente['razon_social'] ?? 'Consumidor final');
    }

    private function obtenerIdentificacionPaciente(array $paciente): string
    {
        $ci = $paciente['ci'] ?? $paciente['documento'] ?? null;
        if ($ci) {
            return preg_replace('/\D/', '', (string) $ci) ?: '9999999999';
        }

        return '9999999999';
    }

    private function formatearDecimal(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }
}
