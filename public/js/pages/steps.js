$(document).ready(function () {
    $(".tab-wizard").validate({
        onsubmit: false,  // Desactivar el envío automático
    });

    // Inicializar el plugin de steps
    $(".tab-wizard").steps({
        headerTag: "h6",
        bodyTag: "section",
        transitionEffect: "none",
        titleTemplate: '<span class="step">#index#</span> #title#',
        labels: {
            finish: "Submit"
        },
        onFinished: function (event, currentIndex) {
            // Verificar si el formulario es válido manualmente
            if ($(".tab-wizard").valid()) {
                const form = document.querySelector('.tab-wizard');
                const formData = new FormData(form);

                fetch('/cirugias/wizard/guardar', {
                    method: 'POST',
                    body: formData
                })
                    .then(async (response) => {
                        const text = await response.text();
                        console.log("🔍 Respuesta cruda del servidor:", text);

                        let data;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            console.error("❌ No es un JSON válido:", e);
                            throw new Error("Respuesta no válida del servidor");
                        }

                        if (!response.ok || !data.success) {
                            const message = data.message || 'No se pudo guardar el protocolo quirúrgico.';
                            const error = new Error(message);
                            error.payload = data;
                            throw error;
                        }

                        return data;
                    })
                    .then(data => {
                        const revisado = document.getElementById('statusCheckbox')?.checked;

                        Swal.fire({
                            title: 'Datos actualizados',
                            text: revisado ? data.message + ' ¿Desea imprimir el PDF?' : data.message,
                                icon: 'success',
                                showCancelButton: revisado,
                                confirmButtonText: revisado ? 'Imprimir PDF' : 'OK',
                                cancelButtonText: 'Cerrar'
                            }).then((resultSwal) => {
                                if (resultSwal.isConfirmed && revisado) {
                                    const formId = form.querySelector('input[name="form_id"]').value;
                                    const hcNumber = form.querySelector('input[name="hc_number"]').value;
                                    window.open('/reports/protocolo/pdf?form_id=' + formId + '&hc_number=' + hcNumber, '_blank');
                                }
                            });
                    })
                    .catch(error => {
                        console.error('Error al actualizar los datos:', error);
                        const message = error.message || 'Ocurrió un error al actualizar los datos. Por favor, intenta nuevamente.';
                        swal("Error", message, "error");
                    });
            } else {
                swal("Error", "Por favor, completa los campos obligatorios.", "error");
            }
        }
    });
});