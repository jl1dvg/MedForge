export function inicializarBotonesModal() {
    // El bloque duplicado de SortableJS ha sido eliminado para evitar conflictos.
    const revisarBtn = document.getElementById('btnRevisarCodigos');
    if (revisarBtn) {
        revisarBtn.addEventListener('click', function () {
            console.log('🔘 Clic en botón Revisión de Códigos');
            const modal = document.getElementById('prefacturaModal');
            const tarjeta = document.querySelector('.kanban-card.view-details.active');
            if (!tarjeta) return;

            const newEstado = revisarBtn.dataset.estado || 'revision-codigos';
            fetch('actualizar_estado.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    id: tarjeta.getAttribute('data-id'),
                    estado: newEstado
                })
            })
                .then(response => {
                    if (!response.ok) throw new Error('Error en el servidor');
                    return response.json();
                })
                .then(data => {
                    console.log('📬 Respuesta del servidor al botón:', data);
                    if (data.success) {
                        showToast('✅ Estado actualizado correctamente');
                        const formId = tarjeta.getAttribute('data-form');
                        const solicitud = allSolicitudes.find(s => s.form_id === formId);
                        if (solicitud) solicitud.estado = newEstado;
                        renderKanban();
                        bootstrap.Modal.getInstance(modal).hide();
                    } else {
                        showToast('❌ Error en la respuesta: ' + (data.error || 'Error desconocido'), false);
                        console.error(data.error);
                    }
                })
                .catch(error => {
                    showToast('❌ No se pudo actualizar el estado', false);
                    console.error('❌ Error en fetch:', error);
                });
        });
    } else {
        console.warn('⚠️ No se encontró el botón #btnRevisarCodigos');
    }

    // --- Botón Solicitar Cobertura ---
    const coberturaBtn = document.getElementById('btnSolicitarCobertura');
    if (coberturaBtn) {
        coberturaBtn.addEventListener('click', function () {
            console.log('📨 Clic en botón Solicitar Cobertura');
            const modal = document.getElementById('prefacturaModal');
            const tarjeta = document.querySelector('.kanban-card.view-details.active');
            if (!tarjeta) return;

            const newEstado = 'esperando-cobertura';
            if (!tarjeta) {
                console.warn('❌ No se encontró ninguna tarjeta activa (.kanban-card.view-details.active)');
                return;
            }

            const formId = tarjeta.getAttribute('data-form');
            const hcNumber = tarjeta.getAttribute('data-hc');

            if (!formId || !hcNumber) {
                console.warn('⚠️ Falta formId o hcNumber en la tarjeta');
                console.log('formId:', formId, 'hcNumber:', hcNumber);
                return;
            }

            const url = `/public/ajax/generate_cobertura.php?form_id=${formId}&hc_number=${hcNumber}`;
            console.log('🌐 URL generada:', url);
            window.open(url, '_blank');

            // Actualizar estado del backend
            fetch('actualizar_estado.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    id: tarjeta.getAttribute('data-id'),
                    estado: newEstado
                })
            })
                .then(response => {
                    if (!response.ok) throw new Error('Error en el servidor');
                    return response.json();
                })
                .then(data => {
                    console.log('📬 Respuesta del servidor al botón cobertura:', data);
                    if (data.success) {
                        showToast('✅ Estado actualizado correctamente');
                        const solicitud = allSolicitudes.find(s => s.form_id === formId);
                        if (solicitud) solicitud.estado = newEstado;
                        renderKanban();
                        bootstrap.Modal.getInstance(modal).hide();
                    } else {
                        showToast('❌ Error en la respuesta: ' + (data.error || 'Error desconocido'), false);
                        console.error(data.error);
                    }
                })
                .catch(error => {
                    showToast('❌ No se pudo actualizar el estado', false);
                    console.error('❌ Error en fetch:', error);
                });
        });
    } else {
        console.warn('⚠️ No se encontró el botón #btnSolicitarCobertura');
    }
}