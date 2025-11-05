(function () {
    'use strict';

    function parseJSON(value, fallback) {
        try {
            return JSON.parse(value);
        } catch (error) {
            console.warn('No se pudo parsear la respuesta JSON', error);
            return fallback;
        }
    }

    function formatDiagnosticos(diagnosticos) {
        if (!Array.isArray(diagnosticos) || diagnosticos.length === 0) {
            return '—';
        }

        return diagnosticos
            .map(function (diagnostico, index) {
                var id = diagnostico && diagnostico.idDiagnostico ? diagnostico.idDiagnostico : 'N/A';
                var ojo = diagnostico && diagnostico.ojo ? diagnostico.ojo : '—';
                return (index + 1) + '. ' + id + ' (' + ojo + ')';
            })
            .join('<br>');
    }

    function actualizarSemaforo(estado, fechaStr) {
        var semaforo = document.getElementById('modalSemaforo');
        if (!semaforo) {
            return;
        }

        var color = 'gray';
        if (estado && estado.toLowerCase() === 'recibido' && fechaStr) {
            var fechaSolicitud = new Date(fechaStr);
            var hoy = new Date();
            if (!isNaN(fechaSolicitud)) {
                var diffDias = Math.floor((hoy - fechaSolicitud) / (1000 * 60 * 60 * 24));
                if (diffDias > 14) {
                    color = 'red';
                } else if (diffDias > 7) {
                    color = 'yellow';
                } else {
                    color = 'green';
                }
            }
        }

        semaforo.style.backgroundColor = color;
    }

    function formatFecha(fecha) {
        if (!fecha) {
            return '—';
        }
        var date = new Date(fecha);
        if (isNaN(date)) {
            return fecha;
        }
        return date.toLocaleDateString('es-EC');
    }

    window.filterDocuments = function filterDocuments(filter) {
        var items = document.querySelectorAll('.media-list .media');
        if (!items.length) {
            return;
        }

        var now = new Date();

        items.forEach(function (item) {
            var dateElement = item.querySelector('.text-fade');
            var dateText = dateElement ? dateElement.textContent.trim() : '';
            var itemDate = dateText ? new Date(dateText) : null;
            var showItem = true;

            if (itemDate instanceof Date && !isNaN(itemDate)) {
                switch (filter) {
                    case 'ultimo_mes':
                        var lastMonth = new Date(now);
                        lastMonth.setMonth(now.getMonth() - 1);
                        showItem = itemDate >= lastMonth;
                        break;
                    case 'ultimos_3_meses':
                        var last3Months = new Date(now);
                        last3Months.setMonth(now.getMonth() - 3);
                        showItem = itemDate >= last3Months;
                        break;
                    case 'ultimos_6_meses':
                        var last6Months = new Date(now);
                        last6Months.setMonth(now.getMonth() - 6);
                        showItem = itemDate >= last6Months;
                        break;
                    default:
                        showItem = true;
                }
            }

            item.style.display = showItem ? 'flex' : 'none';
        });
    };

    window.descargarPDFsSeparados = function descargarPDFsSeparados(formId, hcNumber) {
        if (!formId || !hcNumber) {
            return false;
        }

        var paginas = ['protocolo', '005', 'medicamentos', 'signos_vitales', 'insumos', 'saveqx', 'transanestesico'];
        var index = 0;

        function abrirVentana() {
            if (index >= paginas.length) {
                return;
            }

            var pagina = paginas[index];
            var url = '/reports/protocolo/pdf?form_id=' + encodeURIComponent(formId)
                + '&hc_number=' + encodeURIComponent(hcNumber)
                + '&modo=separado&pagina=' + encodeURIComponent(pagina);

            var ventana = window.open(url, '_blank');
            var tiempoEspera = pagina === 'transanestesico' ? 9000 : 2500;

            setTimeout(function () {
                if (ventana) {
                    ventana.close();
                }
                index += 1;
                setTimeout(abrirVentana, 300);
            }, tiempoEspera);
        }

        abrirVentana();
        return false;
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.filterDocuments('ultimos_3_meses');

        var modal = document.getElementById('modalSolicitud');
        if (!modal) {
            return;
        }

        modal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) {
                return;
            }

            var hcNumber = button.getAttribute('data-hc');
            var formId = button.getAttribute('data-form-id');
            if (!hcNumber || !formId) {
                return;
            }

            fetch('/public/ajax/detalle_solicitud.php?hc_number=' + encodeURIComponent(hcNumber) + '&form_id=' + encodeURIComponent(formId))
                .then(function (response) {
                    return response.text();
                })
                .then(function (text) {
                    var data = parseJSON(text, {});

                    var fechaEl = document.getElementById('modalFecha');
                    if (fechaEl) {
                        fechaEl.textContent = formatFecha(data.fecha);
                    }
                    var procedimientoEl = document.getElementById('modalProcedimiento');
                    if (procedimientoEl) {
                        procedimientoEl.textContent = data.procedimiento || '—';
                    }
                    var diagnosticoEl = document.getElementById('modalDiagnostico');
                    if (diagnosticoEl) {
                        diagnosticoEl.innerHTML = formatDiagnosticos(parseJSON(data.diagnosticos || '[]', []));
                    }
                    var doctorEl = document.getElementById('modalDoctor');
                    if (doctorEl) {
                        doctorEl.textContent = data.doctor || '—';
                    }
                    var descripcionEl = document.getElementById('modalDescripcion');
                    if (descripcionEl) {
                        descripcionEl.textContent = data.plan || '—';
                    }
                    var ojoEl = document.getElementById('modalOjo');
                    if (ojoEl) {
                        ojoEl.textContent = data.ojo || '—';
                    }
                    var estadoEl = document.getElementById('modalEstado');
                    if (estadoEl) {
                        estadoEl.textContent = data.estado || '—';
                    }
                    var motivoEl = document.getElementById('modalMotivo');
                    if (motivoEl) {
                        motivoEl.textContent = data.motivo_consulta || '—';
                    }
                    var enfermedadEl = document.getElementById('modalEnfermedad');
                    if (enfermedadEl) {
                        enfermedadEl.textContent = data.enfermedad_actual || '—';
                    }

                    actualizarSemaforo(data.estado || '', data.fecha || '');
                })
                .catch(function (error) {
                    console.error('Error cargando los detalles de la solicitud', error);
                });
        });
    });
})();
