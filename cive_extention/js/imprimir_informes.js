(async function () {
    // Obtener todas las filas de la tabla
    const rows = document.querySelectorAll('tr[data-key]');

    // Crear un conjunto para almacenar las identificaciones de los pacientes ya procesadas
    const processedPatients = new Set();

    for (const row of rows) {
        // Extraer la identificación del paciente
        const patientId = row.querySelector('td[data-col-seq="14"]').innerText.trim();

        // Verificar si la identificación ya está en el conjunto
        if (!processedPatients.has(patientId)) {
            // Encontrar la celda con el ícono de la impresora verde
            const printerIconCell = row.querySelector('td[data-col-seq="49"] a i.glyphicon-print[style*="color:green"]');
            if (printerIconCell) {
                // Hacer clic en el ícono de la impresora verde
                console.log(`Clic en el ícono de la impresora para el paciente ${patientId}`);
                printerIconCell.parentElement.click();

                // Esperar un momento para asegurarse de que el popup esté completamente cargado
                await new Promise(resolve => setTimeout(resolve, 300));

                console.log('Verificando el botón "Marcar Todo"');
                const marcarTodoButton = document.querySelector('label[for="check-marcar-todo"]');
                if (marcarTodoButton) {
                    console.log('Haciendo clic en el botón "Marcar Todo" para desmarcar todas las casillas...');
                    marcarTodoButton.click();
                } else {
                    console.log('No se encontró el botón "Marcar Todo".');
                }

                // Esperar un momento para asegurarse de que las casillas se hayan desmarcado
                await new Promise(resolve => setTimeout(resolve, 300));

                // Marcar "Documentos Informes"
                const documentosInformesCheckbox = document.querySelector('input[name="DocMultipleDocumentos[check][]"][value="14"]');
                if (documentosInformesCheckbox) {
                    if (!documentosInformesCheckbox.checked) {
                        console.log('Marcando "Documentos Informes"...');
                        documentosInformesCheckbox.click();
                    }
                } else {
                    console.log('No se encontró el checkbox "Documentos Informes".');
                }

                // Esperar un momento para asegurarse de que el checkbox se haya marcado
                await new Promise(resolve => setTimeout(resolve, 300));

                // Hacer clic en el botón de imprimir
                const imprimirButton = Array.from(document.querySelectorAll('button.btn.btn-success')).find(button => button.innerText.includes('IMPRIMIR TODO'));

                if (imprimirButton) {
                    console.log('Haciendo clic en el botón de imprimir...');
                    imprimirButton.click();
                } else {
                    console.log('No se encontró el botón de imprimir.');
                }

                // Esperar un momento para asegurarse de que el proceso de impresión se haya iniciado
                await new Promise(resolve => setTimeout(resolve, 500)); // Ajustar este tiempo según sea necesario

                // Agregar la identificación del paciente al conjunto
                processedPatients.add(patientId);
            } else {
                console.log(`No se encontró el ícono de la impresora para el paciente ${patientId}`);
            }
        }

        // Esperar un momento antes de pasar al siguiente paciente
        await new Promise(resolve => setTimeout(resolve, 500)); // Ajustar este tiempo según sea necesario
    }

    console.log('Proceso completado.');
})();
