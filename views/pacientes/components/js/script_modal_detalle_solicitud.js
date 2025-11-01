document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('modalSolicitud');
    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const hcNumber = button.getAttribute('data-hc');
        const formId = button.getAttribute('data-form-id');
        fetch(`/public/ajax/detalle_solicitud.php?hc_number=${hcNumber}&form_id=${formId}`)
            .then(response => response.text())
            .then(text => {
                console.log('Raw response:', text);
                const data = JSON.parse(text);
                // Fecha
                if (data.fecha) {
                    const parts = data.fecha.split('-');
                    document.getElementById('modalFecha').textContent = `${parts[2]}/${parts[1]}/${parts[0]}`;
                } else {
                    document.getElementById('modalFecha').textContent = '—';
                }
                document.getElementById('modalProcedimiento').textContent = data.procedimiento ?? '—';
                // Diagnóstico
                const diagnosticosArray = (() => {
                    try {
                        return JSON.parse(data.diagnosticos);
                    } catch {
                        return [];
                    }
                })();
                document.getElementById('modalDiagnostico').innerHTML = diagnosticosArray.length
                    ? diagnosticosArray.map((d, i) => `${i + 1}. ${d.idDiagnostico} (${d.ojo})`).join('<br>')
                    : '—';
                document.getElementById('modalDoctor').textContent = data.doctor ?? '—';
                document.getElementById('modalDescripcion').textContent = data.plan ?? '—';
                document.getElementById('modalOjo').textContent = data.ojo ?? '—';
                document.getElementById('modalEstado').textContent = data.estado ?? '—';
                document.getElementById('modalMotivo').textContent = data.motivo_consulta ?? '—';
                document.getElementById('modalEnfermedad').textContent = data.enfermedad_actual ?? '—';

                // Semaforización visual
                const semaforo = document.getElementById('modalSemaforo');
                const estado = data.estado?.toLowerCase();
                const fechaStr = data.fecha;
                let color = 'gray';

                if (estado === 'recibido' && fechaStr) {
                    const fechaSolicitud = new Date(fechaStr);
                    const hoy = new Date();
                    const diffDias = Math.floor((hoy - fechaSolicitud) / (1000 * 60 * 60 * 24));

                    if (diffDias > 14) {
                        color = 'red';
                    } else if (diffDias > 7) {
                        color = 'yellow';
                    } else {
                        color = 'green';
                    }
                }
                semaforo.style.backgroundColor = color;
            })
            .catch(error => {
                console.error('Error cargando los detalles:', error);
            });
    });
});