<?php
require_once __DIR__ . '/../bootstrap.php'; // Ajusta la ruta según tu estructura

$alterStatements = [
    "ALTER TABLE `billing_procedimientos`
    ADD CONSTRAINT `fk_billing_procedimientos_billing_id`
    FOREIGN KEY (`billing_id`) REFERENCES `billing_main`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;",

    "ALTER TABLE `billing_anestesia`
    ADD CONSTRAINT `fk_billing_anestesia_billing_id`
    FOREIGN KEY (`billing_id`) REFERENCES `billing_main`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;",

    "ALTER TABLE `billing_oxigeno`
    ADD CONSTRAINT `fk_billing_oxigeno_billing_id`
    FOREIGN KEY (`billing_id`) REFERENCES `billing_main`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;",

    "ALTER TABLE `billing_derechos`
    ADD CONSTRAINT `fk_billing_derechos_billing_id`
    FOREIGN KEY (`billing_id`) REFERENCES `billing_main`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;",

    "ALTER TABLE `billing_insumos`
    ADD CONSTRAINT `fk_billing_insumos_billing_id`
    FOREIGN KEY (`billing_id`) REFERENCES `billing_main`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;",

    "ALTER TABLE `protocolo_data`
    ADD CONSTRAINT `fk_protocolo_data_hc_number`
    FOREIGN KEY (`hc_number`) REFERENCES `patient_data`(`hc_number`)
    ON DELETE CASCADE ON UPDATE CASCADE;",

    "ALTER TABLE `consulta_data`
    ADD CONSTRAINT `fk_consulta_data_hc_number`
    FOREIGN KEY (`hc_number`) REFERENCES `patient_data`(`hc_number`)
    ON DELETE CASCADE ON UPDATE CASCADE;"
];

foreach ($alterStatements as $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ Ejecutado: $sql\n";
    } catch (PDOException $e) {
        echo "❌ Error en: $sql\nMensaje: " . $e->getMessage() . "\n";
    }
}