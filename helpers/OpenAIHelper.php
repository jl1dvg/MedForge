<?php

namespace Helpers;

use RuntimeException;

class OpenAIHelper
{
    private string $apiKey;
    private string $endpoint;
    private string $model;
    private int $defaultMaxOutputTokens;
    /** @var array<string, string> */
    private array $headers = [];

    /**
     * @param array<string, mixed>|string|null $config
     */
    public function __construct(array|string|null $config = null)
    {
        $options = [];
        if (is_array($config)) {
            $options = $config;
        } elseif ($config !== null) {
            $options['api_key'] = $config;
        }

        $apiKey = trim((string) ($options['api_key'] ?? ''));
        if ($apiKey === '') {
            $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '';
        }

        if ($apiKey === '') {
            throw new RuntimeException('Falta la clave API de OpenAI en la configuración o en el entorno.');
        }

        $this->apiKey = $apiKey;

        $endpoint = $options['endpoint'] ?? ($options['base_url'] ?? null);
        $endpoint = trim((string) ($endpoint ?? ''));
        if ($endpoint === '') {
            $endpoint = 'https://api.openai.com/v1/responses';
        }

        $normalizedEndpoint = rtrim($endpoint, '/');
        if (!preg_match('#/responses$#', $normalizedEndpoint)) {
            $normalizedEndpoint .= '/responses';
        }
        $this->endpoint = $normalizedEndpoint;

        $model = trim((string) ($options['model'] ?? ''));
        $this->model = $model !== '' ? $model : 'gpt-4o-mini';

        $maxTokens = (int) ($options['max_output_tokens'] ?? 400);
        $this->defaultMaxOutputTokens = $maxTokens > 0 ? $maxTokens : 400;

        if (!empty($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $header => $value) {
                $headerName = trim((string) $header);
                $headerValue = trim((string) $value);
                if ($headerName !== '' && $headerValue !== '') {
                    $this->headers[$headerName] = $headerValue;
                }
            }
        }

        if (!empty($options['organization']) && !isset($this->headers['OpenAI-Organization'])) {
            $organization = trim((string) $options['organization']);
            if ($organization !== '') {
                $this->headers['OpenAI-Organization'] = $organization;
            }
        }
    }

    /**
     * Llamada genérica a Responses API.
     * Devuelve el "output_text" o lanza excepción con el error.
     */
    public function respond(string $input, ?int $maxOutputTokens = null): string
    {
        $maxTokens = $maxOutputTokens ?? $this->defaultMaxOutputTokens;
        $payload = [
            'model' => $this->model,
            'input' => $input,
            'max_output_tokens' => $maxTokens
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        foreach ($this->headers as $header => $value) {
            $headers[] = $header . ': ' . $value;
        }

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("cURL error: $err");
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($raw, true);
        if ($code >= 400) {
            $msg = $data['error']['message'] ?? 'Error desconocido';
            throw new \RuntimeException("OpenAI API ($code): $msg");
        }

        // Intentar extraer texto de diferentes formas soportadas por Responses API
        $text = '';
        if (isset($data['output_text'])) {
            $text = (string)$data['output_text'];
        }

        // Si output_text vino vacío, intentar reconstruir desde 'output' -> 'content'
        if ($text === '' && isset($data['output']) && is_array($data['output'])) {
            $parts = [];
            foreach ($data['output'] as $item) {
                // Mensajes con contenido (p. ej., type: 'message', role: 'assistant')
                if (isset($item['content']) && is_array($item['content'])) {
                    foreach ($item['content'] as $c) {
                        // Algunos backends usan type 'output_text' o 'text'
                        if (isset($c['type']) && ($c['type'] === 'output_text' || $c['type'] === 'text')) {
                            $parts[] = (string)($c['text'] ?? '');
                        } elseif (isset($c['text'])) {
                            $parts[] = (string)$c['text'];
                        }
                    }
                }
            }
            $text = trim(implode('', $parts));
        }

        // Compatibilidad hacia atrás con chat/completions en caso de proxies o gateways
        if ($text === '' && isset($data['choices'][0]['message']['content'])) {
            $text = (string)$data['choices'][0]['message']['content'];
        }

        // Debug opcional si no hay texto: exporta respuesta cruda al log
        $debug = $_ENV['OPENAI_DEBUG'] ?? getenv('OPENAI_DEBUG') ?? '';
        if ($text === '' && $debug) {
            error_log('OpenAIHelper respond(): respuesta cruda sin texto detectable: ' . $raw);
        }

        return $text;
    }

    /** ===== Casos de uso clínicos (tus funciones) ===== */

    public function generateEnfermedadProblemaActual(string $examenFisico): string
    {
        $prompt = <<<TXT
Examen físico oftalmológico: {$examenFisico}

Redacta los hallazgos del examen físico de manera profesional, clara y sintetizada. Sigue este esquema y considera las siguientes instrucciones:

1. Combina el Motivo de consulta y enfermedad actual en una sola frase concisa que describa de manera específica la razón de la consulta y la situación actual del paciente. Evita frases introductorias como 'Motivo de consulta:' o 'Enfermedad actual:'.
2. Biomicroscopia: Presenta los hallazgos separados por ojo con las siglas OD y OI exclusivamente. Si no se menciona un ojo, omítelo. Usa frases completas y bien estructuradas.
3. Fondo de Ojo: Incluye únicamente si hay detalles reportados. Si no se mencionan hallazgos, no lo incluyas.
4. PIO: Si está disponible, escribe la presión intraocular en el formato OD/OI (por ejemplo, 18/18.5). Si no está reportada, omítela.

Instrucciones adicionales:
- Usa mayúsculas y minúsculas correctamente; solo usa siglas para OD, OI y PIO.
- No incluyas secciones vacías ni detalles no reportados.
- Sintetiza la información eliminando redundancias y enfocándote en lo relevante.
- Evita líneas separadas para frases importantes; presenta la información de forma continua y bien organizada.
- No inventes datos.

Ejemplo de formato:
[Frase que combine el motivo de consulta y la enfermedad actual.]
Biomicroscopia: OD: [detalles]. OI: [detalles].
Fondo de Ojo: OD: [detalles]. OI: [detalles].
PIO: [valor].
TXT;

        return $this->respond($prompt, 400);
    }

    /**
     * Elimina prefijos de códigos internos al inicio del nombre del procedimiento.
     * Ejemplos a limpiar:
     *  - "CYP-GLA-002 - Trabeculectomía" -> "Trabeculectomía"
     *  - "ABC-123 - Faco + LIO"          -> "Faco + LIO"
     *  - "PROC_001: Vitrectomía"         -> "Vitrectomía"
     */
    private function cleanProcedureName(string $name): string
    {
        $out = trim($name);

        // Patrones de prefijos comunes "CODIGO - Nombre"
        // 1) Códigos tipo AAA-BBB-001 - Nombre
        $out = preg_replace('/^\s*[A-Z0-9]{2,}(?:-[A-Z0-9]{2,}){1,}(?:-\d{1,})?\s*-\s*/u', '', $out);

        // 2) Códigos tipo ABC-123 - Nombre
        $out = preg_replace('/^\s*[A-Z0-9]{2,}-\d{1,}\s*-\s*/u', '', $out);

        // 3) Prefijos con dos puntos "CODIGO: Nombre"
        $out = preg_replace('/^\s*[A-Z0-9\-_]{2,}\s*:\s*/u', '', $out);

        // 4) Prefijos entre corchetes o paréntesis, p. ej. "[CYP-GLA-002] Nombre" o "(CYP-002) Nombre"
        $out = preg_replace('/^\s*[\[\(][A-Z0-9\-_]{2,}[\]\)]\s*/u', '', $out);

        return trim($out);
    }

    /** Alias para limpiar nombres de estudios diagnósticos usando las mismas reglas de códigos internos. */
    private function cleanExamName(string $name): string
    {
        return $this->cleanProcedureName($name);
    }

    public function generatePlanTratamiento(string $plan, string $insurance, ?string $procedimiento = null, ?string $ojo = null): string
    {
        // 1) Normaliza el plan (texto libre escrito por el doctor)
        $plan = preg_replace("/[ \t]+/", " ", str_replace(["\r\n", "\n", "\r"], " ", (string)$plan));

        // 2) Normaliza el 'ojo' recibido desde el formulario (puede venir como D/I/AO o texto)
        $ojoNorm = '';
        if ($ojo !== null) {
            $o = trim(mb_strtoupper($ojo));
            // Acepta "D", "I", "AO", "B" o frases como "ojo derecho", "ojo izquierdo", "ambos ojos"
            if ($o === 'D' || strpos($o, 'DERECH') !== false) {
                $ojoNorm = 'OD';
            } elseif ($o === 'I' || strpos($o, 'IZQUIERD') !== false) {
                $ojoNorm = 'OI';
            } elseif ($o === 'AO' || $o === 'B' || strpos($o, 'AMBOS') !== false) {
                $ojoNorm = 'AO';
            }
        }

        // 3) Construye bloque de datos explícitos (prioritarios) provenientes del formulario
        $procTrim = trim((string)$procedimiento);
        $procTrim = $this->cleanProcedureName($procTrim);

        $extra = '';
        if ($procTrim !== '' || $ojoNorm !== '') {
            $extra = "Datos explícitos adicionales (prioritarios):\n";
            if ($procTrim !== '') $extra .= "- Procedimiento sugerido: {$procTrim}\n";
            if ($ojoNorm !== '') $extra .= "- Ojo: {$ojoNorm}\n";
            $extra .= "\n";
        }

        // 4) Prompt: pedimos JSON estructurado
        $prompt = <<<TXT
{$extra}A partir del siguiente texto entre comillas, devuelve SOLO este JSON. Sin comentarios, sin texto extra.

"{$plan}"

{
  "procedimientos": [
    {
      "nombre": "string (nombre exacto del procedimiento quirúrgico oftalmológico detectado, con ortografía y acentos correctos)",
      "ojo": "OD|OI|AO|\"\" si no claro",
      "anestesia": "string si está explícita, de lo contrario \"\"",
      "justificacion_breve": "string opcional (<= 15 palabras, solo si aporta un dato relevante que NO esté incluido en el nombre del procedimiento ni en anestesia; si no hay dato adicional útil, dejar vacío)"
    }
  ],
  "examenes": "Se solicita a {$insurance} autorización para valoración y tratamiento integral en cardiología y electrocardiograma."
}

Reglas:
- Solo incluye procedimientos quirúrgicos oftalmológicos (omitír medicación/notas administrativas).
- Usa terminología estándar de cirugía oftalmológica (corrigiendo acentos).
- Si se proporcionaron 'Datos explícitos adicionales', respétalos y priorízalos sobre inferencias del texto fuente.
- Si no hay procedimiento claro: procedimientos = [].
TXT;

        // 5) Llamada a la IA
        $raw = $this->respond($prompt, 700);

        // 6) Decodifica JSON; si falla, usa fallback mínimo
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return "Procedimientos:\n- No aplica.\n\nExámenes prequirúrgicos y valoración cardiológica:\n- Se solicita a {$insurance} autorización para valoración y tratamiento integral en cardiología y electrocardiograma.";
        }

        // 7) Si tenemos datos explícitos, los imponemos sobre lo devuelto por IA (primer procedimiento)
        if (!empty($json['procedimientos']) && is_array($json['procedimientos'])) {
            // Asegura que sea un array indexado
            $first = &$json['procedimientos'][0];

            if ($procTrim !== '') {
                $first['nombre'] = $procTrim;
            }
            if ($ojoNorm !== '') {
                $first['ojo'] = $ojoNorm;
            }
        }

        // 8) Renderiza el resultado final
        $lineas = [];
        $lineas[] = "Procedimientos:";
        if (!empty($json['procedimientos']) && is_array($json['procedimientos'])) {
            foreach ($json['procedimientos'] as $p) {
                $nombre = $this->cleanProcedureName(trim((string)($p['nombre'] ?? '')));
                if ($nombre === '') continue;

                $ojoOut = strtoupper(trim((string)($p['ojo'] ?? '')));
                // Mapea abreviaturas a texto legible
                if ($ojoOut === 'OD') $ojoOut = '(ojo derecho)';
                elseif ($ojoOut === 'OI') $ojoOut = '(ojo izquierdo)';
                elseif ($ojoOut === 'AO') $ojoOut = '(ambos ojos)';
                else $ojoOut = '';

                $anest = trim((string)($p['anestesia'] ?? ''));
                $just = trim((string)($p['justificacion_breve'] ?? ''));

                $partes = [$nombre];
                if ($ojoOut !== '') $partes[] = $ojoOut;
                if ($anest !== '') $partes[] = "($anest)";

                $linea = '- ' . implode(' ', $partes);
                if ($just !== '') {
                    $linea .= '. ' . ucfirst($just);
                } else {
                    $linea .= '.';
                }
                $lineas[] = $linea;
            }
        } else {
            $lineas[] = '- No aplica.';
        }

        $lineas[] = "";
        $lineas[] = "Exámenes prequirúrgicos y valoración cardiológica:";
        $lineas[] = "- Se solicita a {$insurance} autorización para valoración y tratamiento integral en cardiología y electrocardiograma.";

        return implode("\n", $lineas);
    }

    /**
     * Genera el texto para "E. PLAN DE DIAGNÓSTICO PROPUESTO" usando solo:
     * - $texto: libre, escrito por el médico en la HC.
     * - $estudiosExplicitos: array de strings de estudios seleccionados en el formulario (opcional).
     *
     * No recibe ni usa aseguradora ni ojo desde formulario. La lateralidad se infiere del texto si el modelo la detecta.
     */
    public function generatePlanDiagnostico(string $texto, ?array $estudiosExplicitos = null): string
    {
        // 1) Normaliza texto libre
        $texto = preg_replace("/[ \t]+/", " ", str_replace(["\r\n", "\n", "\r"], " ", (string)$texto));

        // 2) Bloque de datos explícitos desde formulario
        $extra = '';
        $listaExp = [];
        if (is_array($estudiosExplicitos)) {
            foreach ($estudiosExplicitos as $e) {
                $e = $this->cleanExamName((string)$e);
                if ($e !== '') $listaExp[] = $e;
            }
        }
        if (!empty($listaExp)) {
            $extra = "Datos explícitos adicionales (prioritarios):\n";
            $extra .= "- Estudios sugeridos: " . implode("; ", $listaExp) . "\n\n";
        }

        // 3) Prompt con JSON estructurado (enfocado a LISTAR SOLICITUDES de exámenes)
        $prompt = <<<TXT
{$extra}A partir del siguiente texto entre comillas, devuelve SOLO este JSON. Sin comentarios, sin texto extra.

"{$texto}"

{
  "estudios": [
    {
      "nombre": "string (nombre estándar del examen/imagen/laboratorio OFTALMOLÓGICO SOLICITADO; p. ej., OCT macular, OCT de nervio óptico, campimetría Humphrey, paquimetría, tonometría, biometría óptica, topografía corneal, UBM, ecografía modo B, fotografía de fondo, angiografía fluoresceínica, etc.)",
      "ojo": "OD|OI|AO|\"\" si no claro",
      "prioridad": "rutina|urgente|\"\"",
      "motivo_breve": "string opcional (<= 10 palabras; no repetir nombre del examen)",
      "indicaciones": "string opcional breve (p. ej., midriasis, suspensión LC 24h, ayuno)"
    }
  ]
}

CRITERIO DE INCLUSIÓN (muy importante):
- Incluye SOLO exámenes que estén **solicitados** explícitamente.
- Considera como solicitud términos como: "solicitar", "pedir", "indicar", "ordenar", "programar", "realizar", "enviar para", "se requiere".
- Si el texto menciona exámenes **ya realizados** (históricos) o **descartados/no indicados**, **NO los incluyas**.

PRIORIDAD DE FUENTES:
1) Si existen "Datos explícitos adicionales (prioritarios)" (lista proveniente del formulario), **inclúyelos primero** y respétalos tal cual como estudios solicitados.
2) Luego, extrae del texto las SOLICITUDES adicionales (sin duplicar los ya listados).

REGLAS DE SALIDA:
- Deduplica por nombre (normaliza mayúsculas/minúsculas y quita códigos internos antes de comparar).
- Usa terminología estándar oftalmológica y corrige acentos.
- Determina **ojo** si el texto lo aclara (OD/OI/AO o frases equivalentes); si no es claro, deja "".
- Asigna **prioridad = "urgente"** si el texto indica urgencia (p. ej., "urgente", "hoy", "prioritario"); en caso contrario deja "rutina" si el contexto lo sugiere o "" si no hay señal.
- "motivo_breve" solo si aporta valor (p. ej., "control de edema macular"), máximo 10 palabras.
- "indicaciones" solo si están explícitas (p. ej., "midriasis", "suspender LC 24h").
- NO incluir procedimientos quirúrgicos, medicación, interconsultas ni trámites administrativos.
- Si no hay exámenes solicitados, devuelve `"estudios": []`.
TXT;

        // 4) Llamada IA
        $raw = $this->respond($prompt, 650);

        // 5) Decodifica
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            $json = ["estudios" => []];
        }

        // 6) Impone datos del formulario sobre el primer estudio (si existen)
        if (!empty($json["estudios"]) && is_array($json["estudios"]) && !empty($listaExp)) {
            $json["estudios"][0]["nombre"] = $listaExp[0];
        }

        // 7) Renderiza
        $out = [];
        $out[] = "Plan de diagnóstico propuesto:";
        if (!empty($json["estudios"]) && is_array($json["estudios"])) {
            foreach ($json["estudios"] as $e) {
                $nombre = $this->cleanExamName(trim((string)($e["nombre"] ?? "")));
                if ($nombre === "") continue;

                $ojoOut = strtoupper(trim((string)($e["ojo"] ?? "")));
                if ($ojoOut === "OD") $ojoOut = "(ojo derecho)";
                elseif ($ojoOut === "OI") $ojoOut = "(ojo izquierdo)";
                elseif ($ojoOut === "AO") $ojoOut = "(ambos ojos)";
                else $ojoOut = "";

                $prio = strtolower(trim((string)($e["prioridad"] ?? "")));
                $motivo = trim((string)($e["motivo_breve"] ?? ""));
                $indic = trim((string)($e["indicaciones"] ?? ""));

                $partes = ["- " . $nombre];
                if ($ojoOut !== "") $partes[] = $ojoOut;
                if ($prio === "urgente") $partes[] = "[URGENTE]";

                $linea = implode(" ", $partes);
                if ($motivo !== "") $linea .= ". " . ucfirst($motivo);
                if ($indic !== "") $linea .= " Indicaciones: " . $indic . ".";
                else                 $linea .= ".";

                $out[] = $linea;
            }
        } else {
            $out[] = "- No se identifican estudios diagnósticos específicos.";
        }

        return implode("\n", $out);
    }
}