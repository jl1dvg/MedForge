(function () {
    'use strict';

    var SECTION_CONFIG = {
        solicitudes: {
            label: 'Solicitudes',
            columns: [
                { key: 'fecha', label: 'Fecha' },
                { key: 'procedimiento', label: 'Procedimiento' },
                { key: 'estado', label: 'Estado' },
                { key: 'prioridad', label: 'Prioridad' },
                { key: 'doctor', label: 'Doctor' },
                { key: 'form_id', label: 'Formulario' }
            ]
        },
        examenes: {
            label: 'Exámenes',
            columns: [
                { key: 'fecha', label: 'Fecha' },
                { key: 'examen', label: 'Examen' },
                { key: 'estado', label: 'Estado' },
                { key: 'prioridad', label: 'Prioridad' },
                { key: 'doctor', label: 'Doctor' },
                { key: 'turno', label: 'Turno' },
                { key: 'form_id', label: 'Formulario' }
            ]
        },
        agenda: {
            label: 'Agenda',
            columns: [
                { key: 'fecha', label: 'Fecha' },
                { key: 'hora', label: 'Hora' },
                { key: 'procedimiento', label: 'Procedimiento' },
                { key: 'estado', label: 'Estado' },
                { key: 'doctor', label: 'Doctor' },
                { key: 'sede', label: 'Sede' },
                { key: 'historial_estados', label: 'Historial' },
                { key: 'form_id', label: 'Formulario' }
            ]
        },
        consultas: {
            label: 'Consultas',
            columns: [
                { key: 'fecha', label: 'Fecha' },
                { key: 'motivo_consulta', label: 'Motivo' },
                { key: 'enfermedad_actual', label: 'Enfermedad Actual' },
                { key: 'plan', label: 'Plan' },
                { key: 'form_id', label: 'Formulario' }
            ]
        },
        protocolos: {
            label: 'Protocolos',
            columns: [
                { key: 'fecha_inicio', label: 'Fecha' },
                { key: 'membrete', label: 'Membrete' },
                { key: 'status', label: 'Estado' },
                { key: 'form_id', label: 'Formulario' }
            ]
        },
        prefacturas: {
            label: 'Prefacturas',
            columns: [
                { key: 'fecha_creacion', label: 'Fecha Creación' },
                { key: 'cod_derivacion', label: 'Código Derivación' },
                { key: 'fecha_vigencia', label: 'Vigencia' },
                { key: 'referido', label: 'Referido' },
                { key: 'sede', label: 'Sede' },
                { key: 'form_id', label: 'Formulario' }
            ]
        },
        derivaciones: {
            label: 'Derivaciones',
            columns: [
                { key: 'fecha', label: 'Fecha' },
                { key: 'codigo', label: 'Código' },
                { key: 'fecha_vigencia', label: 'Vigencia' },
                { key: 'referido', label: 'Referido' },
                { key: 'diagnostico', label: 'Diagnóstico' },
                { key: 'sede', label: 'Sede' },
                { key: 'parentesco', label: 'Parentesco' },
                { key: 'form_id', label: 'Formulario' },
                { key: 'origen', label: 'Origen' }
            ]
        },
        recetas: {
            label: 'Recetas',
            columns: [
                { key: 'fecha', label: 'Fecha' },
                { key: 'producto', label: 'Producto' },
                { key: 'dosis', label: 'Dosis' },
                { key: 'cantidad', label: 'Cantidad' },
                { key: 'estado', label: 'Estado' },
                { key: 'via', label: 'Vía' },
                { key: 'doctor', label: 'Doctor' },
                { key: 'form_id', label: 'Formulario' }
            ]
        },
        crm: {
            label: 'CRM',
            columns: [
                { key: 'fecha', label: 'Fecha' },
                { key: 'tipo', label: 'Tipo' },
                { key: 'titulo', label: 'Título' },
                { key: 'estado', label: 'Estado' },
                { key: 'detalle', label: 'Detalle' },
                { key: 'responsable', label: 'Responsable' },
                { key: 'form_id', label: 'Formulario' }
            ]
        }
    };

    function parseJSON(value, fallback) {
        if (typeof value !== 'string') {
            return fallback;
        }

        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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
            return String(fecha);
        }

        return date.toLocaleDateString('es-EC');
    }

    function formatValue(value, key) {
        if (value === null || value === undefined) {
            return '—';
        }

        if (Array.isArray(value)) {
            if (key === 'historial_estados') {
                if (value.length === 0) {
                    return '—';
                }

                return value.map(function (item) {
                    var estado = item && item.estado ? item.estado : '—';
                    var fechaCambio = item && item.fecha_hora_cambio ? formatFecha(item.fecha_hora_cambio) : '—';
                    return estado + ' (' + fechaCambio + ')';
                }).join(' | ');
            }

            return value.join(', ');
        }

        if (typeof value === 'object') {
            return JSON.stringify(value);
        }

        var normalized = String(value).trim();
        if (normalized === '') {
            return '—';
        }

        if (key && key.indexOf('fecha') !== -1) {
            return formatFecha(normalized);
        }

        return normalized;
    }

    function fetchJson(url) {
        return fetch(url, { credentials: 'same-origin' })
            .then(function (response) {
                return response.json()
                    .catch(function () {
                        return {};
                    })
                    .then(function (payload) {
                        if (!response.ok) {
                            var message = payload && payload.error ? payload.error : 'No se pudo cargar la información';
                            throw new Error(message);
                        }

                        return payload;
                    });
            });
    }

    function renderPaciente360Summary(summary, sections) {
        var container = document.getElementById('paciente360Summary');
        if (!container) {
            return;
        }

        if (!summary || typeof summary !== 'object') {
            container.innerHTML = '<span class="badge bg-light text-dark">Sin resumen disponible.</span>';
            return;
        }

        var html = sections.map(function (section) {
            var config = SECTION_CONFIG[section] || { label: section };
            var total = summary[section] !== undefined ? summary[section] : 0;
            return '<span class="badge bg-primary">' + escapeHtml(config.label) + ': ' + escapeHtml(total) + '</span>';
        }).join(' ');

        container.innerHTML = html !== '' ? html : '<span class="badge bg-light text-dark">Sin datos.</span>';
    }

    function renderPaciente360Section(section, payload) {
        var panel = document.getElementById('paciente360-panel-' + section);
        if (!panel) {
            return;
        }

        var config = SECTION_CONFIG[section];
        if (!config) {
            panel.innerHTML = '<p class="text-muted mb-0">Sección no configurada.</p>';
            return;
        }

        var rows = Array.isArray(payload.data) ? payload.data : [];
        var totalRows = payload.meta && typeof payload.meta.total_rows === 'number' ? payload.meta.total_rows : rows.length;

        if (rows.length === 0) {
            panel.innerHTML = '<p class="text-muted mb-0">Sin registros para esta sección.</p>';
            return;
        }

        var thead = '<tr>' + config.columns.map(function (column) {
            return '<th>' + escapeHtml(column.label) + '</th>';
        }).join('') + '</tr>';

        var tbody = rows.map(function (row) {
            var tds = config.columns.map(function (column) {
                return '<td>' + escapeHtml(formatValue(row[column.key], column.key)) + '</td>';
            }).join('');
            return '<tr>' + tds + '</tr>';
        }).join('');

        var moreInfo = '';
        if (totalRows > rows.length) {
            moreInfo = '<p class="text-muted mt-10 mb-0">Mostrando ' + escapeHtml(rows.length)
                + ' de ' + escapeHtml(totalRows) + ' registros.</p>';
        }

        panel.innerHTML = '<table class="table table-sm table-striped mb-0"><thead>' + thead + '</thead><tbody>' + tbody + '</tbody></table>' + moreInfo;
    }

    function renderPaciente360Error(section, message) {
        var panel = document.getElementById('paciente360-panel-' + section);
        if (!panel) {
            return;
        }

        panel.innerHTML = '<p class="text-danger mb-0">' + escapeHtml(message || 'No se pudo cargar la sección.') + '</p>';
    }

    function loadPaciente360() {
        var container = document.getElementById('paciente360');
        if (!container) {
            return;
        }

        var hcNumber = container.getAttribute('data-hc');
        if (!hcNumber) {
            return;
        }

        var sectionsRaw = container.getAttribute('data-sections') || '';
        var sections = sectionsRaw
            .split(',')
            .map(function (item) {
                return item.trim().toLowerCase();
            })
            .filter(function (item) {
                return item !== '' && SECTION_CONFIG[item];
            });

        if (sections.length === 0) {
            return;
        }

        sections.forEach(function (section) {
            var panel = document.getElementById('paciente360-panel-' + section);
            if (panel) {
                panel.innerHTML = '<p class="text-muted mb-0">Cargando...</p>';
            }
        });

        var lastSummary = null;

        Promise.all(sections.map(function (section) {
            var url = '/pacientes/detalles/section?hc_number=' + encodeURIComponent(hcNumber)
                + '&section=' + encodeURIComponent(section)
                + '&limit=15';

            return fetchJson(url)
                .then(function (payload) {
                    if (payload && payload.meta && payload.meta.summary) {
                        lastSummary = payload.meta.summary;
                    }
                    renderPaciente360Section(section, payload || {});
                })
                .catch(function (error) {
                    renderPaciente360Error(section, error && error.message ? error.message : 'Error de carga');
                });
        })).then(function () {
            renderPaciente360Summary(lastSummary, sections);
        });
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
        loadPaciente360();

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

            var endpoint = '/pacientes/detalles/solicitud?hc_number=' + encodeURIComponent(hcNumber)
                + '&form_id=' + encodeURIComponent(formId);

            fetchJson(endpoint)
                .then(function (payload) {
                    var data = payload && payload.data ? payload.data : {};
                    var diagnosticos = Array.isArray(data.diagnosticos)
                        ? data.diagnosticos
                        : parseJSON(data.diagnosticos || '[]', []);

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
                        diagnosticoEl.innerHTML = formatDiagnosticos(diagnosticos);
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
