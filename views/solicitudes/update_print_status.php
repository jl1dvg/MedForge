<?php
require '../../bootstrap.php';  // Asegúrate de tener la conexión a la base de datos configurada

// Obtener los parámetros enviados a través de POST
$form_id = $_POST['form_id'] ?? null;
$hc_number = $_POST['hc_number'] ?? null;
$printed = $_POST['printed'] ?? null;

if ($form_id && $hc_number && $printed !== null) {
    // Actualizar la columna "printed" en la base de datos
    $sql = "UPDATE protocolo_data SET printed = ? WHERE form_id = ? AND hc_number = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('iss', $printed, $form_id, $hc_number);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
}
?>