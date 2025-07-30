<?php
require '../../bootstrap.php';  // Asegúrate de tener la conexión a la base de datos configurada

// Verificar si los datos se están enviando correctamente
print_r($_POST);  // Esto te permitirá ver los datos que llegan

// Obtener los parámetros enviados a través de POST
$form_id = $_POST['form_id'] ?? null;
$hc_number = $_POST['hc_number'] ?? null;
$status = $_POST['status'] ?? null;

// Verificar que los parámetros existan
if ($form_id && $hc_number !== null && $status !== null) {
    // Actualizar la columna "status" en la base de datos
    $sql = "UPDATE protocolo_data SET status = ? WHERE form_id = ? AND hc_number = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('iss', $status, $form_id, $hc_number);  // 'iss' significa: integer, string, string

    if ($stmt->execute()) {
        echo 'success';
    } else {
        echo 'error';
    }
} else {
    echo 'error';
}
?>