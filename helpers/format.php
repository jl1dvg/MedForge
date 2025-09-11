<?php

function obtenerNombrePaciente(string $hcNumber, PDO $db): string
{
    $stmt = $db->prepare("SELECT fname, mname, lname, lname2 FROM patient_data WHERE hc_number = ?");
    $stmt->execute([$hcNumber]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) return 'Desconocido';
    return $row['lname'] . ' ' . $row['lname2'] . ' ' . $row['fname'] . ' ' . $row['mname'];
}