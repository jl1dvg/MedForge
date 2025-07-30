function loadResult(rowData) {
    // Guardar form_id y hc_number para uso posterior
    currentFormId = rowData.form_id;
    currentHcNumber = rowData.hc_number;

    // Actualizar el contenido del modal con los datos de la fila seleccionada
    const procedimientoParts = rowData.procedimiento_proyectado.split(' - ');
    const nombreCirugia = procedimientoParts[procedimientoParts.length - 2] + ' - ' + procedimientoParts[procedimientoParts.length - 1];
    document.getElementById('result-proyectado').innerHTML = "QX proyectada - " + nombreCirugia;
    document.getElementById('result-popup').innerHTML = "QX realizada - " + rowData.membrete;
    document.getElementById('lab-order-id').innerHTML = "Protocolo: " + rowData.form_id;

    // Marcar o desmarcar el checkbox basado en el estado del protocolo (status)
    const markAsReviewedCheckbox = document.getElementById('markAsReviewed');
    markAsReviewedCheckbox.checked = rowData.status == 1 ? true : false;  // Si el estado es 1, marcar el checkbox

    // Procesar los diagnósticos
    let diagnosticoData = JSON.parse(rowData.diagnosticos);  // Asegurarse de que esté en formato JSON
    let diagnosticoTable = '';

    diagnosticoData.forEach(diagnostico => {
        let cie10 = '';
        let detalle = '';

        // Dividir el campo idDiagnostico en código y detalle
        if (diagnostico.idDiagnostico) {
            const parts = diagnostico.idDiagnostico.split(' - ', 2);  // Separar por " - "
            cie10 = parts[0];  // CIE10 Code
            detalle = parts[1];  // Detail
        }

        // Agregar una fila a la tabla
        diagnosticoTable += `
                <tr>
                    <td>${cie10}</td>
                    <td>${detalle}</td>
                </tr>
            `;
    });

    // Insertar la tabla de diagnóstico en el modal
    document.getElementById('diagnostico-table').innerHTML = diagnosticoTable;

    // Procesar los procedimientos
    let procedimientoData = JSON.parse(rowData.procedimientos);  // Convertir a JSON
    let procedimientoTable = '';
    let procedimientoCodes = new Set();  // Para detectar códigos duplicados

    procedimientoData.forEach(procedimiento => {
        let codigo = '';
        let nombre = '';

        // Dividir el campo procInterno en código y nombre
        if (procedimiento.procInterno) {
            const parts = procedimiento.procInterno.split(' - ', 3);  // Separar por " - "
            codigo = parts[1];  // Código del procedimiento
            nombre = parts[2];  // Nombre del procedimiento
        }

        // Verificar si el código ya existe
        if (procedimientoCodes.has(codigo)) {
            // Código duplicado encontrado, cambiar el color de fondo para alertar
            procedimientoTable += `
                <tr class="bg-warning">
                    <td>${codigo}</td>
                    <td>${nombre}</td>
                </tr>
            `;
        } else {
            procedimientoCodes.add(codigo);
            // Agregar una fila a la tabla de procedimientos
            procedimientoTable += `
                <tr>
                    <td>${codigo}</td>
                    <td>${nombre}</td>
                </tr>
            `;
        }
    });

    // Insertar la tabla de procedimientos en el modal
    document.getElementById('procedimientos-table').innerHTML = procedimientoTable;


    // Llenar otras tablas como antes (resultados, tiempos, staff, etc.)
    document.getElementById('result-table').innerHTML = `
                <tr>
                    <td>Dieresis</td>
                <td>${rowData.dieresis}</td>
            </tr>
                <tr>
                    <td>Exposición</td>
                <td>${rowData.exposicion}</td>
            </tr>
                <tr>
                    <td>Hallazgo</td>
                <td>${rowData.hallazgo}</td>
            </tr>
                <tr>
                    <td>Operatorio</td>
                <td>${rowData.operatorio}</td>
            </tr>
            `;

    // Calcular la duración entre hora_inicio y hora_fin
    let horaInicio = new Date('1970-01-01T' + rowData.hora_inicio + 'Z');
    let horaFin = new Date('1970-01-01T' + rowData.hora_fin + 'Z');
    let diff = new Date(horaFin - horaInicio);  // Diferencia de tiempo

    let duration = `${diff.getUTCHours().toString().padStart(2, '0')}:${diff.getUTCMinutes().toString().padStart(2, '0')}`;

    // Actualizar la fila con la fecha de inicio, hora de inicio, hora de fin y duración
    document.getElementById('timing-row').innerHTML = `
            <td>${rowData.fecha_inicio}</td>
            <td>${rowData.hora_inicio}</td>
            <td>${rowData.hora_fin}</td>
            <td>${duration}</td>
        `;

    // Inicializar el staffTable vacía
    let staffTable = '';

    // Campos del staff que queremos mostrar si no están vacíos
    const staffFields = {
        'Cirujano Principal': rowData.cirujano_1,
        'Instrumentista': rowData.instrumentista,
        'Cirujano Asistente': rowData.cirujano_2,
        'Circulante': rowData.circulante,
        'Primer Ayudante': rowData.primer_ayudante,
        'Anestesiólogo': rowData.anestesiologo,
        'Segundo Ayudante': rowData.segundo_ayudante,
        'Ayudante de Anestesia': rowData.ayudante_anestesia,
        'Tercer Ayudante': rowData.tercer_ayudante
    };

    let staffCount = 0;  // Contador de miembros del staff

    // Iterar sobre los campos del staff y añadir solo los que no están vacíos
    for (const [label, value] of Object.entries(staffFields)) {
        if (value && value.trim() !== '') {
            staffTable += `
                    <tr>
                        <td>${label}</td>
                        <td>${value}</td>
                    </tr>
                `;
            staffCount++;
        }
    }

    // Agregar el contenido del staff al modal
    const staffTableElement = document.getElementById('staff-table');
    staffTableElement.innerHTML = staffTable;

    // Si el número de miembros del staff es menor a 5, cambiar el color de fondo
    if (staffCount < 5) {
        staffTableElement.parentElement.classList.add('bg-danger');
    } else {
        staffTableElement.parentElement.classList.remove('bg-danger');
    }

    // Actualizar los comentarios y las firmas
    document.querySelector('.comment-here').innerHTML = rowData.complicaciones_operatorio || 'Sin comentarios';
}
