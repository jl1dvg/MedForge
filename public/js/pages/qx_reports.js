function togglePrintStatus(form_id, hc_number, button, currentStatus) {
    // Verificar si el botón está activo
    var isActive = button.classList.contains('active');
    var newStatus = isActive ? 1 : 0;  // Si el botón está activo (on), el nuevo estado será 0 (off); si no, será 1 (on)

    // Cambiar visualmente el estado del botón
    if (isActive) {
        button.classList.add('active');
        button.setAttribute('aria-pressed', 'true');
    } else {
        button.classList.remove('active');
        button.setAttribute('aria-pressed', 'false');
    }

    // Realizar la petición AJAX para actualizar el estado en la base de datos
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cirugias/protocolo/printed', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('form_id=' + form_id + '&hc_number=' + hc_number + '&printed=' + newStatus);

    xhr.onload = function () {
        if (xhr.status === 200 && xhr.responseText === 'success') {
            console.log('Estado actualizado en la base de datos');

            // Solo si el nuevo estado es "on" (printed = 1), generamos el PDF
            if (newStatus === 1) {
                window.open('../../generate_pdf.php?form_id=' + form_id + '&hc_number=' + hc_number, '_blank');
            }
        } else {
            console.log('Error al actualizar el estado');
        }
    };
}

let currentFormId;  // Variable para almacenar el form_id actual
let currentHcNumber;  // Variable para almacenar el hc_number actual
function loadResult(rowData) {
    // Guardar form_id y hc_number para uso posterior
    currentFormId = rowData.form_id;
    currentHcNumber = rowData.hc_number;

    // Actualizar el contenido del modal con los datos de la fila seleccionada
    document.getElementById('result-popup').innerHTML = "QX realizada - " + rowData.membrete;
    document.getElementById('lab-order-id').innerHTML = "Protocolo: " + rowData.form_id;

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

    procedimientoData.forEach(procedimiento => {
        let codigo = '';
        let nombre = '';

        // Dividir el campo procInterno en código y nombre
        if (procedimiento.procInterno) {
            const parts = procedimiento.procInterno.split(' - ', 3);  // Separar por " - "
            codigo = parts[1];  // Código del procedimiento
            nombre = parts[2];  // Nombre del procedimiento
        }

        // Agregar una fila a la tabla de procedimientos
        procedimientoTable += `
                <tr>
                    <td>${codigo}</td>
                    <td>${nombre}</td>
                </tr>
            `;
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

    // Iterar sobre los campos del staff y añadir solo los que no están vacíos
    for (const [label, value] of Object.entries(staffFields)) {
        if (value && value.trim() !== '') {
            staffTable += `
                    <tr>
                        <td>${label}</td>
                        <td>${value}</td>
                    </tr>
                `;
        }
    }

    // Agregar el contenido del staff al modal
    document.getElementById('staff-table').innerHTML = staffTable;

    // Actualizar los comentarios y las firmas
    document.querySelector('.comment-here').innerHTML = rowData.complicaciones_operatorio || 'Sin comentarios';
    document.getElementById('test-by').innerHTML = rowData.cirujano_1;
    document.getElementById('signed-by').innerHTML = rowData.anestesiologo;
}

function updateProtocolStatus() {
    // Obtener si el checkbox está marcado
    const isReviewed = document.getElementById('markAsReviewed').checked ? 1 : 0;

    // Depurar: mostrar valores que se enviarán al servidor
    console.log(`Form ID: ${currentFormId}, HC Number: ${currentHcNumber}, Status: ${isReviewed}`);

    // Realizar la petición AJAX para actualizar el campo "status" en la base de datos
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/cirugias/protocolo/status', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            console.log(xhr.responseText);  // Ver la respuesta del servidor
            if (xhr.responseText === 'success') {
                // Mostrar mensaje de éxito
                alert('Estado del protocolo actualizado correctamente');

                // Cerrar el modal
                $('#resultModal').modal('hide');

                // Recargar la tabla general
                reloadPatientTable();
            } else {
                alert('Error al actualizar el estado del protocolo');
            }
        }
    };
    // Enviar el form_id, hc_number y el nuevo estado (revisado o no)
    xhr.send(`form_id=${encodeURIComponent(currentFormId)}&hc_number=${encodeURIComponent(currentHcNumber)}&status=${isReviewed}`);
}

function reloadPatientTable() {
    // Aquí puedes realizar una recarga AJAX para actualizar la tabla sin recargar toda la página
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'get_patient_data.php', true);  // Archivo PHP que devuelve los datos de la tabla de pacientes
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            document.getElementById('patientTable').innerHTML = xhr.responseText;  // Actualiza la tabla
        }
    };
    xhr.send();
}