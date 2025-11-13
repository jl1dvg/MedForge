function generatePDF() {
    {
        // Extraer los datos del paciente
        const patientName = document.querySelector('.media-body p:nth-of-type(1)').innerText.trim(); // Nombre del paciente
        const historyNumber = document.querySelector('.media-body p:nth-of-type(2)').innerText.replace('HC #:', '').trim(); // Número de historia clínica
        const birthDate = document.querySelector('.media-body p:nth-of-type(4)').innerText.replace('Fecha de Nacimiento:', '').trim(); // Fecha de nacimiento
        const gender = document.querySelector('.media-body p:nth-of-type(6)').innerText.trim(); // Género
        const insurance = document.querySelector('.media-body p:nth-of-type(11) b').innerText.trim(); // Seguro

        // Extraer solo los diagnósticos de la tabla y generar parámetros GET para cada uno
        let diagnosticsParams = '';
        const rows = document.querySelectorAll('#diagnosticossub11111 tbody tr');

        rows.forEach((row, index) => {
            const diagnostic = row.querySelector('.list-cell__idDiagnostico .select2-selection__rendered').title.trim();
            diagnosticsParams += `&diagnostic${index + 1}=${encodeURIComponent(diagnostic)}`;
        });

        // Extraer todos los diagnósticos de "ENFERMEDAD DEFINITIVO"
        let definitiveDiseasesParams = '';

        function getContentAfterBold(parent, text) {
            const boldElement = Array.from(parent.querySelectorAll('b')).find(b => b.textContent.includes(text));
            return boldElement ? boldElement.nextSibling.textContent.trim() : null;
        }

        // Encuentra todos los <div> con clase "timeline-item"
        const timelineItems = document.querySelectorAll('.timeline-item');

        let definitiveIndex = 1; // Contador para los diagnósticos de "ENFERMEDAD DEFINITIVO"
        timelineItems.forEach((item) => {
            const enfermedadDefinitivo = getContentAfterBold(item, "ENFERMEDAD DEFINITIVO:");
            if (enfermedadDefinitivo) {
                definitiveDiseasesParams += `&definitiveDisease${definitiveIndex}=${encodeURIComponent(enfermedadDefinitivo)}`;
                definitiveIndex++;
            }
        });

        // Extraer los procedimientos proyectados y realizados
        let procedimientosParams = '';
        let procedimientosProyectado = '';
        let diagnosticosPost = [];
        const postOperatorioHeader = Array.from(document.querySelectorAll('th')).find(th => th.textContent.includes('Postoperatorio'));

        if (postOperatorioHeader) {
            let row = postOperatorioHeader.parentElement.nextElementSibling;
            while (row && row.querySelector('th') && !row.querySelector('th').textContent.includes('C. PROCEDIMIENTO')) {
                const diagnosticoCell = row.querySelector('th.descripcion:nth-child(2)');
                if (diagnosticoCell) {
                    const diagnostico = diagnosticoCell.textContent.trim();
                    diagnosticosPost.push(diagnostico);
                }
                row = row.nextElementSibling;
            }
        }

        diagnosticosPost.forEach((diagnostico, index) => {
            procedimientosParams += `&proyectedProcedure${index + 1}=${encodeURIComponent(diagnostico)}`;
        });

        // Extrae el primer código de procedimiento proyectado
        const liElement = Array.from(document.querySelectorAll('li')).find(li => li.textContent.includes("PROTOCOLO CIRUGIA"));
        if (liElement) {
            const procedimientoHeader = Array.from(liElement.querySelectorAll('th')).find(th => th.textContent.includes('Proyectada:'));
            let procedimiento = '';
            if (procedimientoHeader) {
                const procedimientoElement = procedimientoHeader.nextElementSibling;
                if (procedimientoElement) {
                    procedimiento = procedimientoElement.textContent.trim();
                }
            }
            if (procedimiento) {
                procedimientosProyectado += `&projectProcedure=${encodeURIComponent(procedimiento)}`;
            }
        }

        // Extrae el primer código de procedimiento realizado
        if (liElement) {
            const procedimientoHeader = Array.from(liElement.querySelectorAll('th')).find(th => th.textContent.includes('Realizado:'));
            let procedimiento = '';
            if (procedimientoHeader) {
                const procedimientoElement = procedimientoHeader.nextElementSibling;
                if (procedimientoElement) {
                    procedimiento = procedimientoElement.textContent.trim();
                }
            }
            if (procedimiento) {
                procedimientosParams += `&realizedProcedure=${encodeURIComponent(procedimiento)}`;
            }
        }

        // Extraer los integrantes del equipo quirúrgico
        let equipoQuirurgicoParams = '';
        const equipoRows = document.querySelectorAll('#trabajadorprotocolo-input-subsecuente .multiple-input-list__item');
        const funcionCounter = {}; // Objeto para contar las funciones

        equipoRows.forEach((row) => {
            let funcion = row.querySelector('.list-cell__funcion .select2-selection__rendered').title.trim().replace(/\s+/g, '_').toLowerCase();
            const doctor = row.querySelector('.list-cell__doctor .select2-selection__rendered').title.trim();

            if (funcionCounter[funcion]) {
                funcionCounter[funcion]++;
                funcion += funcionCounter[funcion]; // Añadir el número si ya existe
            } else {
                funcionCounter[funcion] = 1;
            }

            equipoQuirurgicoParams += `&${encodeURIComponent(funcion)}=${encodeURIComponent(doctor)}`;
        });

        // Extraer los datos adicionales solicitados
        const code = document.querySelector('#consultasubsecuente-piepagina').value.trim();
        const dieresis = document.querySelector('#consultasubsecuente-dieresis').value.trim();
        const exposicion = document.querySelector('#consultasubsecuente-exposicion').value.trim();
        const hallazgo = document.querySelector('#consultasubsecuente-hallazgo').value.trim();
        const operatorio = document.querySelector('#consultasubsecuente-operatorio').value.trim().replace(/\n/g, '%0A');
        const fechaInicio = document.querySelector('#consultasubsecuente-fecha_inicio').value.trim();
        const horaInicio = document.querySelector('#consultasubsecuente-horainicio').value.trim();
        const fechaFin = document.querySelector('#consultasubsecuente-fecha_fin').value.trim();
        const horaFin = document.querySelector('#consultasubsecuente-horafin').value.trim();
        const tipoAnestesia = document.querySelector('#select2-consultasubsecuente-anestesia_id-container').title.trim();
        const nombreAnestesia = document.querySelector('#consultasubsecuente-nombreanestesia') ? document.querySelector('#consultasubsecuente-nombreanestesia').value.trim() : '';

        const medforgeOrigin = (window.CiveApiClient && typeof window.CiveApiClient.apiOrigin === 'function')
            ? window.CiveApiClient.apiOrigin()
            : (window.location && window.location.origin ? window.location.origin.replace(/\/$/, '') : 'https://cive.consulmed.me');
        // Crear la URL con los parámetros GET
        const url = `${medforgeOrigin}/generate_pdf.php?patientName=${encodeURIComponent(patientName)}&historyNumber=${encodeURIComponent(historyNumber)}&birthDate=${encodeURIComponent(birthDate)}&gender=${encodeURIComponent(gender)}&insurance=${encodeURIComponent(insurance)}${diagnosticsParams}${definitiveDiseasesParams}${procedimientosProyectado}${procedimientosParams}${equipoQuirurgicoParams}&code=${encodeURIComponent(code)}&dieresis=${encodeURIComponent(dieresis)}&exposicion=${encodeURIComponent(exposicion)}&hallazgo=${encodeURIComponent(hallazgo)}&operatorio=${encodeURIComponent(operatorio)}&fechaInicio=${encodeURIComponent(fechaInicio)}&horaInicio=${encodeURIComponent(horaInicio)}&fechaFin=${encodeURIComponent(fechaFin)}&horaFin=${encodeURIComponent(horaFin)}&tipoAnestesia=${encodeURIComponent(tipoAnestesia)}${nombreAnestesia ? `&nombreAnestesia=${encodeURIComponent(nombreAnestesia)}` : ''}`;

        // Abrir la URL en una nueva pestaña
        window.open(url, '_blank');
    }
}
