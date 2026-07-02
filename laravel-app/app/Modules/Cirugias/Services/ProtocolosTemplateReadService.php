<?php

declare(strict_types=1);

namespace App\Modules\Cirugias\Services;

use Illuminate\Database\ConnectionInterface;
use PDO;

class ProtocolosTemplateReadService
{
    private readonly PDO $db;

    public function __construct(ConnectionInterface $connection)
    {
        $this->db = $connection->getPdo();
    }

    public function obtenerProcedimientosAgrupados(): array
    {
        $sql = 'SELECT categoria, membrete, cirugia, imagen_link, id FROM procedimientos ORDER BY categoria, cirugia';
        $stmt = $this->db->query($sql);

        $grouped = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $grouped[$row['categoria']][] = $row;
        }

        return $grouped;
    }

    /**
     * Catálogo completo para /v2/protocolos (lista + rail de categorías + búsqueda).
     * Incluye códigos resumidos y conteo real de usos (protocolo_data.procedimiento_id).
     */
    public function obtenerProtocolosCatalogo(): array
    {
        $stmt = $this->db->query(
            'SELECT id, membrete, cirugia, categoria, horas, imagen_link, fecha_actualizacion
             FROM procedimientos ORDER BY fecha_actualizacion DESC'
        );
        $protocolos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($protocolos === []) {
            return [];
        }

        $ids = array_column($protocolos, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $codigosPorProtocolo = [];
        $codigosStmt = $this->db->prepare(
            "SELECT procedimiento_id, codigo, nombre FROM procedimientos_codigos WHERE procedimiento_id IN ({$placeholders})"
        );
        $codigosStmt->execute($ids);
        while ($row = $codigosStmt->fetch(PDO::FETCH_ASSOC)) {
            $codigosPorProtocolo[$row['procedimiento_id']][] = ['codigo' => $row['codigo'], 'nombre' => $row['nombre']];
        }

        $usosPorProtocolo = [];
        $usosStmt = $this->db->prepare(
            "SELECT procedimiento_id, COUNT(*) AS total FROM protocolo_data WHERE procedimiento_id IN ({$placeholders}) GROUP BY procedimiento_id"
        );
        $usosStmt->execute($ids);
        while ($row = $usosStmt->fetch(PDO::FETCH_ASSOC)) {
            $usosPorProtocolo[$row['procedimiento_id']] = (int) $row['total'];
        }

        foreach ($protocolos as &$protocolo) {
            $id = $protocolo['id'];
            $protocolo['codigos'] = $codigosPorProtocolo[$id] ?? [];
            $protocolo['usos'] = $usosPorProtocolo[$id] ?? 0;
            $protocolo['actualizado'] = $protocolo['fecha_actualizacion'] !== null
                ? substr((string) $protocolo['fecha_actualizacion'], 0, 10)
                : null;
            unset($protocolo['fecha_actualizacion']);
        }
        unset($protocolo);

        return $protocolos;
    }

    public function obtenerProtocoloPorId(string $id): ?array
    {
        $sql = 'SELECT p.*, e.pre_evolucion, e.pre_indicacion, e.post_evolucion, e.post_indicacion, e.alta_evolucion, e.alta_indicacion
            FROM procedimientos p
            JOIN evolucion005 e ON p.id = e.id
            WHERE p.id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function obtenerMedicamentosDeProtocolo(string $id): array
    {
        $stmt = $this->db->prepare('SELECT medicamentos FROM kardex WHERE procedimiento_id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $decoded = !empty($row['medicamentos']) ? (json_decode((string) $row['medicamentos'], true) ?: []) : [];

        $porId = [];
        $porNombreNormalizado = [];
        foreach ($this->obtenerOpcionesMedicamentos() as $opcion) {
            $porId[(string) $opcion['id']] = (string) $opcion['medicamento'];
            $key = $this->normalizarEspacios((string) $opcion['medicamento']);
            if (!isset($porNombreNormalizado[$key])) {
                $porNombreNormalizado[$key] = (string) $opcion['medicamento'];
            }
        }

        $out = [];
        foreach (array_filter($decoded, 'is_array') as $m) {
            // El editor legacy guardaba la vía como "via_administracion"; el wizard nuevo usa "via".
            $m['via'] = (string) ($m['via'] ?? $m['via_administracion'] ?? '');
            unset($m['via_administracion']);

            $nombreCrudo = (string) ($m['medicamento'] ?? '');
            $idCrudo = isset($m['id']) ? (string) $m['id'] : null;
            if ($idCrudo !== null && isset($porId[$idCrudo])) {
                $m['medicamento'] = $porId[$idCrudo];
            } elseif ($nombreCrudo !== '' && isset($porNombreNormalizado[$this->normalizarEspacios($nombreCrudo)])) {
                $m['medicamento'] = $porNombreNormalizado[$this->normalizarEspacios($nombreCrudo)];
            }

            $out[] = $m;
        }

        return $out;
    }

    public function obtenerOpcionesMedicamentos(): array
    {
        $stmt = $this->db->query('SELECT id, medicamento FROM medicamentos ORDER BY medicamento');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerInsumosDisponibles(): array
    {
        $stmt = $this->db->query('SELECT id, nombre, categoria FROM insumos ORDER BY nombre');
        $insumos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $insumos[$row['categoria']][] = $row;
        }

        return $insumos;
    }

    /**
     * Devuelve un arreglo plano [{categoria,nombre,cantidad}, ...]. El editor legacy guardaba
     * un objeto agrupado por categoría (p.ej. {"equipos": [...], "lentes intraoculares": [...]});
     * aquí se aplana. Muchos nombres legacy tienen saltos de línea/espacios pegados desde una
     * carga de datos antigua (p.ej. "BUPIVACAINA\n  (SIN EPINEFRINA)..."), que ya no calzan
     * ni siquiera consigo mismos en un <select> (el valor implícito de una <option> sin
     * atributo `value` normaliza espacios). Por eso cada ítem se resuelve contra el catálogo
     * real por `id` (más confiable) y, si no hay id, por nombre normalizado — así el wizard
     * muestra y guarda la ortografía canónica en vez de una copia "huérfana" del texto crudo.
     */
    public function obtenerInsumosDeProtocolo(string $id): array
    {
        $stmt = $this->db->prepare('SELECT insumos FROM insumos_pack WHERE procedimiento_id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($row['insumos'])) {
            return [];
        }

        $decoded = json_decode((string) $row['insumos'], true);
        if (!is_array($decoded)) {
            return [];
        }

        $catalogo = $this->obtenerInsumosDisponibles();
        $porId = [];
        $porNombreNormalizado = [];
        foreach ($catalogo as $categoria => $items) {
            foreach ($items as $item) {
                $entry = ['nombre' => (string) $item['nombre'], 'categoria' => (string) $categoria];
                $porId[(string) $item['id']] = $entry;
                $key = $this->normalizarEspacios((string) $item['nombre']);
                if (!isset($porNombreNormalizado[$key])) {
                    $porNombreNormalizado[$key] = $entry;
                }
            }
        }
        $categoriasReales = array_keys($catalogo);

        $out = [];
        if (array_is_list($decoded)) {
            foreach ($decoded as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $resolved = $this->resolverInsumo($it, (string) ($it['categoria'] ?? ''), $porId, $porNombreNormalizado);
                if ($resolved !== null) {
                    $out[] = $resolved;
                }
            }
            return $out;
        }

        foreach ($decoded as $categoriaLegacy => $items) {
            if (!is_array($items)) {
                continue;
            }
            $categoriaResuelta = $this->resolverCategoriaLegacy((string) $categoriaLegacy, $categoriasReales);
            foreach ($items as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $resolved = $this->resolverInsumo($it, $categoriaResuelta, $porId, $porNombreNormalizado);
                if ($resolved !== null) {
                    $out[] = $resolved;
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string, array{nombre: string, categoria: string}> $porId
     * @param array<string, array{nombre: string, categoria: string}> $porNombreNormalizado
     * @return array{categoria: string, nombre: string, cantidad: mixed}|null
     */
    private function resolverInsumo(array $it, string $categoriaFallback, array $porId, array $porNombreNormalizado): ?array
    {
        $nombreCrudo = (string) ($it['nombre'] ?? '');
        if ($nombreCrudo === '') {
            return null;
        }
        $cantidad = $it['cantidad'] ?? 1;

        $idCrudo = isset($it['id']) ? (string) $it['id'] : null;
        if ($idCrudo !== null && isset($porId[$idCrudo])) {
            return ['categoria' => $porId[$idCrudo]['categoria'], 'nombre' => $porId[$idCrudo]['nombre'], 'cantidad' => $cantidad];
        }

        $key = $this->normalizarEspacios($nombreCrudo);
        if (isset($porNombreNormalizado[$key])) {
            return ['categoria' => $porNombreNormalizado[$key]['categoria'], 'nombre' => $porNombreNormalizado[$key]['nombre'], 'cantidad' => $cantidad];
        }

        return ['categoria' => $categoriaFallback, 'nombre' => $nombreCrudo, 'cantidad' => $cantidad];
    }

    private function normalizarEspacios(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    /**
     * @param array<int, string> $categoriasReales
     */
    private function resolverCategoriaLegacy(string $categoriaLegacy, array $categoriasReales): string
    {
        foreach ($categoriasReales as $real) {
            if (strcasecmp($real, $categoriaLegacy) === 0) {
                return $real;
            }
        }

        return $categoriasReales[0] ?? $categoriaLegacy;
    }

    public function obtenerCodigosDeProcedimiento(string $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM procedimientos_codigos WHERE procedimiento_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerStaffDeProcedimiento(string $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM procedimientos_tecnicos WHERE procedimiento_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function crearProtocoloVacio(?string $categoria = null): array
    {
        return [
            'id' => '',
            'cirugia' => '',
            'membrete' => '',
            'categoria' => $categoria ?? '',
            'horas' => '',
            'imagen_link' => '',
            'operatorio' => '',
            'dieresis' => '',
            'exposicion' => '',
            'hallazgo' => '',
            'pre_evolucion' => '',
            'pre_indicacion' => '',
            'post_evolucion' => '',
            'post_indicacion' => '',
            'alta_evolucion' => '',
            'alta_indicacion' => '',
            'codigos' => [],
            'staff' => [],
            'insumos' => [],
            'medicamentos' => [],
        ];
    }
}

