<?php
require_once __DIR__ . '/../bootstrap.php';


use PhpOffice\PhpSpreadsheet\IOFactory;

// Cargar Excel de medicamentos
$medPath = __DIR__ . '/data/PRECIO MEDICAMENTOS SEGPUB.xlsx';
$insPath = __DIR__ . '/data/PRECIO INSUMOS SEGPUB.xlsx';

function actualizarDesdeExcel($filePath, $tipo = 'med')
{
    global $pdo;
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    // Detectar columnas dinámicamente
    $headers = array_map('strtoupper', $rows[1]);
    $colCodigo = $tipo === 'med' ? 'CODIGO PRODUTO SIGCENTER' : 'ISSPOL';

    $actualizados = 0;
    $no_encontrados = [];

    for ($i = 2; $i <= count($rows); $i++) {
        $row = $rows[$i];

        $codigoKey = array_search($colCodigo, $headers);
        $codigo = isset($row[$codigoKey]) ? trim((string)$row[$codigoKey]) : '';
        if (empty($codigo)) continue;

        $precio_iess = floatval(str_replace(',', '.', $row[array_search('PRECIO IESS', $headers)] ?? 0));
        $precio_issfa = floatval(str_replace(',', '.', $row[array_search('PRECIO ISSFA', $headers)] ?? 0));
        $precio_isspol = floatval(str_replace(',', '.', $row[array_search('PRECIO ISSPOL', $headers)] ?? 0));
        $precio_msp = floatval(str_replace(',', '.', $row[array_search('PRECIO MSP', $headers)] ?? 0));

        $stmt = $pdo->prepare("
            UPDATE insumos
            SET precio_iess = :precio_iess,
                precio_issfa = :precio_issfa,
                precio_isspol = :precio_isspol,
                precio_msp = :precio_msp
            WHERE codigo_isspol = :codigo
        ");
        $stmt->execute([
            ':precio_iess' => $precio_iess,
            ':precio_issfa' => $precio_issfa,
            ':precio_isspol' => $precio_isspol,
            ':precio_msp' => $precio_msp,
            ':codigo' => $codigo,
        ]);
        if ($stmt->rowCount() > 0) {
            $actualizados++;
        } else {
            $no_encontrados[] = $codigo;
        }
    }
    return ['actualizados' => $actualizados, 'no_encontrados' => $no_encontrados];
}

// Ejecutar
actualizarDesdeExcel($medPath, 'med');
$resultados = actualizarDesdeExcel($insPath, 'ins');

echo "✔ Total de insumos actualizados: {$resultados['actualizados']}\n";
if (!empty($resultados['no_encontrados'])) {
    echo "❌ No se encontraron " . count($resultados['no_encontrados']) . " códigos en la base de datos:\n";
    foreach ($resultados['no_encontrados'] as $codigo) {
        echo "- $codigo\n";
    }
}

// echo "✔ Precios actualizados correctamente.\n";