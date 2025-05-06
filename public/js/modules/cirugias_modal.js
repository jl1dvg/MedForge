function loadResult(rowData) {
    currentFormId = rowData.form_id;
    currentHcNumber = rowData.hc_number;

    const procedimientoParts = rowData.procedimiento_proyectado?.split(' - ') || [];
    const nombreCirugia = procedimientoParts.slice(-2).join(' - ');
    document.getElementById('result-proyectado').innerHTML = "QX proyectada - " + nombreCirugia;
    document.getElementById('result-popup').innerHTML = "QX realizada - " + rowData.membrete;
    document.getElementById('lab-order-id').innerHTML = "Protocolo: " + rowData.form_id;

    //document.getElementById('markAsReviewed').checked = rowData.status == 1;

    // Diagnósticos
    let diagnosticoData = [];
    try {
        diagnosticoData = JSON.parse(rowData.diagnosticos);
    } catch {
    }
    let diagnosticoTable = '';
    diagnosticoData.forEach(diagnostico => {
        let cie10 = '', detalle = '';
        if (diagnostico.idDiagnostico) {
            const parts = diagnostico.idDiagnostico.split(' - ', 2);
            cie10 = parts[0];
            detalle = parts[1];
        }
        diagnosticoTable += `<tr><td>${cie10}</td><td>${detalle}</td></tr>`;
    });
    document.getElementById('diagnostico-table').innerHTML = diagnosticoTable;

    // Procedimientos
    let procedimientoData = [];
    try {
        procedimientoData = JSON.parse(rowData.procedimientos);
    } catch {
    }
    let procedimientoTable = '';
    let procedimientoCodes = new Set();
    procedimientoData.forEach(procedimiento => {
        let codigo = '', nombre = '';
        if (procedimiento.procInterno) {
            const parts = procedimiento.procInterno.split(' - ', 3);
            codigo = parts[1];
            nombre = parts[2];
        }
        if (procedimientoCodes.has(codigo)) {
            procedimientoTable += `<tr class="bg-warning"><td>${codigo}</td><td>${nombre}</td></tr>`;
        } else {
            procedimientoCodes.add(codigo);
            procedimientoTable += `<tr><td>${codigo}</td><td>${nombre}</td></tr>`;
        }
    });
    document.getElementById('procedimientos-table').innerHTML = procedimientoTable;

    // Resultados operatorios
    document.getElementById('result-table').innerHTML = `
            <tr><td>Dieresis</td><td>${rowData.dieresis}</td></tr>
            <tr><td>Exposición</td><td>${rowData.exposicion}</td></tr>
            <tr><td>Hallazgo</td><td>${rowData.hallazgo}</td></tr>
            <tr><td>Operatorio</td><td>${rowData.operatorio}</td></tr>`;

    // Duración
    let horaInicio = new Date('1970-01-01T' + rowData.hora_inicio + 'Z');
    let horaFin = new Date('1970-01-01T' + rowData.hora_fin + 'Z');
    let diff = new Date(horaFin - horaInicio);
    let duration = `${diff.getUTCHours().toString().padStart(2, '0')}:${diff.getUTCMinutes().toString().padStart(2, '0')}`;
    document.getElementById('timing-row').innerHTML = `
            <td>${rowData.fecha_inicio}</td>
            <td>${rowData.hora_inicio}</td>
            <td>${rowData.hora_fin}</td>
            <td>${duration}</td>`;

    // Staff
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
    let staffTable = '';
    let staffCount = 0;
    for (const [label, value] of Object.entries(staffFields)) {
        if (value && value.trim() !== '') {
            staffTable += `<tr><td>${label}</td><td>${value}</td></tr>`;
            staffCount++;
        }
    }
    const staffTableElement = document.getElementById('staff-table');
    staffTableElement.innerHTML = staffTable;
    if (staffCount < 5) {
        staffTableElement.parentElement.classList.add('bg-danger');
    } else {
        staffTableElement.parentElement.classList.remove('bg-danger');
    }

    // Comentarios
    document.querySelector('.comment-here').innerText = rowData.complicaciones_operatorio || 'Sin comentarios';
}

function loadResultFromElement(element) {
    try {
        const json = element.getAttribute('data-cirugia');
        const cirugia = JSON.parse(json);
        loadResult(cirugia);
    } catch (e) {
        console.error("Error al analizar los datos del modal:", e);
    }
}

// ✅ Exportar al scope global
window.loadResult = loadResult;
window.loadResultFromElement = loadResultFromElement;