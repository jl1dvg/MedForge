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
            label: 'Exámenes realizados',
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
    var recetasRowsCache = [];
    var recetaPrintHtml = '';

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

    function parseDateTimestamp(fecha) {
        if (!fecha) {
            return null;
        }

        var value = String(fecha).trim();
        if (value === '' || value === '0000-00-00') {
            return null;
        }

        var parsed = Date.parse(value);
        if (!isNaN(parsed)) {
            return parsed;
        }

        var parts = value.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (!parts) {
            return null;
        }

        var day = parseInt(parts[1], 10);
        var month = parseInt(parts[2], 10) - 1;
        var year = parseInt(parts[3], 10);
        var localDate = new Date(year, month, day);
        return isNaN(localDate.getTime()) ? null : localDate.getTime();
    }

    function formatRelativeDays(fecha) {
        var ts = parseDateTimestamp(fecha);
        if (ts === null) {
            return 'Fecha no disponible';
        }

        var now = new Date();
        var diff = Math.floor((now.getTime() - ts) / 86400000);
        if (diff <= 0) {
            return 'Hoy';
        }
        if (diff === 1) {
            return 'Hace 1 día';
        }
        return 'Hace ' + diff + ' días';
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

    function getPatientSectionsContainer() {
        return document.getElementById('patient-sections') || document.getElementById('paciente360');
    }

    function formatAgendaTime(value) {
        if (value === null || value === undefined) {
            return 'Sin hora';
        }

        var normalized = String(value).trim();
        if (normalized === '' || normalized === '—') {
            return 'Sin hora';
        }

        if (normalized.indexOf(' ') !== -1) {
            normalized = normalized.split(' ').pop() || normalized;
        }

        var match = normalized.match(/^(\d{1,2}):(\d{2})/);
        if (!match) {
            return normalized;
        }

        var hour = parseInt(match[1], 10);
        var minute = match[2];
        if (isNaN(hour)) {
            return normalized;
        }

        var suffix = hour >= 12 ? 'PM' : 'AM';
        var hour12 = hour % 12;
        if (hour12 === 0) {
            hour12 = 12;
        }

        return hour12 + ':' + minute + ' ' + suffix;
    }

    function agendaStatusPresentation(estado) {
        var normalized = String(estado || '').toLowerCase();

        if (normalized.indexOf('cancel') !== -1 || normalized.indexOf('no_show') !== -1 || normalized.indexOf('ausen') !== -1) {
            return { icon: 'fa-times-circle', iconClass: 'text-danger', textClass: 'text-danger' };
        }

        if (normalized.indexOf('atendido') !== -1 || normalized.indexOf('terminado') !== -1 || normalized.indexOf('pagado') !== -1) {
            return { icon: 'fa-check-circle', iconClass: 'text-lightgreen', textClass: 'text-lightgreen' };
        }

        if (normalized.indexOf('confirm') !== -1 || normalized.indexOf('agenda') !== -1 || normalized.indexOf('dilatar') !== -1 || normalized.indexOf('consulta') !== -1) {
            return { icon: 'fa-check-circle', iconClass: 'text-primary', textClass: 'text-primary' };
        }

        return { icon: 'fa-clock-o', iconClass: 'text-fade', textClass: 'text-fade' };
    }

    function normalizeProcedureText(value) {
        return String(value || '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function shortAgendaProcedureName(value) {
        var normalized = normalizeProcedureText(value);
        if (normalized === '' || normalized === '—') {
            return '—';
        }

        var parts = normalized.split(' - ').map(function (part) {
            return part.trim();
        }).filter(function (part) {
            return part !== '';
        });

        if (parts.length < 3) {
            return normalized;
        }

        var shortName = parts.slice(2).join(' - ');
        var isImagen = parts[0].toUpperCase().indexOf('IMAGENES') === 0;

        if (isImagen) {
            var examName = parts[2].replace(/^\d+\s*-\s*/, '').trim();
            var tail = parts.slice(3).join(' - ').trim();
            shortName = examName + (tail !== '' ? ' - ' + tail : '');
        }

        return shortName !== '' ? shortName : normalized;
    }

    function agendaProcedureTheme(procedimiento, presentation) {
        var normalized = normalizeProcedureText(procedimiento).toUpperCase();

        if (normalized.indexOf('CIRUGIAS -') === 0 || normalized.indexOf(' FACOEMULSIFICACION') !== -1 || normalized.indexOf('CYP-') !== -1) {
            return {
                titleClass: 'text-danger',
                iconClass: 'text-danger',
                textClass: 'text-danger',
                accentStyle: 'border-left:4px solid #e74c3c; padding-left:12px;'
            };
        }

        if (normalized.indexOf('SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-010') === 0
            || normalized.indexOf('SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-006') === 0) {
            return {
                titleClass: 'text-lightgreen',
                iconClass: 'text-lightgreen',
                textClass: 'text-lightgreen',
                accentStyle: 'border-left:4px solid #2ecc71; padding-left:12px;'
            };
        }

        if (normalized.indexOf('IMAGENES -') === 0) {
            return {
                titleClass: 'text-primary',
                iconClass: 'text-primary',
                textClass: 'text-primary',
                accentStyle: 'border-left:4px solid #3498db; padding-left:12px;'
            };
        }

        return {
            titleClass: 'hover-blue',
            iconClass: presentation.iconClass,
            textClass: presentation.textClass,
            accentStyle: ''
        };
    }

    function renderAgendaCards(panel, rows, totalRows) {
        if (!panel) {
            return;
        }

        var cards = rows.map(function (row, index) {
            var doctorAvatar = String(row.doctor_avatar || '').trim();
            var avatarSrc = doctorAvatar !== '' ? doctorAvatar : '/images/avatar/avatar-13.png';
            var procedimientoRaw = String(row.procedimiento || '').trim();
            var procedimiento = shortAgendaProcedureName(procedimientoRaw !== '' ? procedimientoRaw : formatValue(row.procedimiento, 'procedimiento'));
            var estado = formatValue(row.estado, 'estado');
            var doctor = formatValue(row.doctor, 'doctor');
            var sede = formatValue(row.sede, 'sede');
            var fecha = formatValue(row.fecha, 'fecha');
            var hora = formatAgendaTime(row.hora);
            var historial = formatValue(row.historial_estados, 'historial_estados');
            var formId = formatValue(row.form_id, 'form_id');
            var presentation = agendaStatusPresentation(estado);
            var theme = agendaProcedureTheme(procedimientoRaw, presentation);
            var titleClass = theme.titleClass || 'hover-blue';
            var iconClass = theme.iconClass || presentation.iconClass;
            var textClass = theme.textClass || presentation.textClass;
            var accentStyle = theme.accentStyle !== '' ? ' style="' + escapeHtml(theme.accentStyle) + '"' : '';
            var rowBorderClass = index < rows.length - 1 ? ' bb-gray-1' : '';

            return '<div class="py-25 d-flex justify-content-between align-items-center px-25' + rowBorderClass + '">'
                + '<div class="w-p100 rounded10 justify-content-between align-items-center d-flex">'
                + '<div class="d-flex justify-content-between align-items-center">'
                + '<img src="' + escapeHtml(avatarSrc) + '" class="me-20 avatar bg-light rounded-circle" alt="Doctor" onerror="this.onerror=null;this.src=\'/images/avatar/avatar-13.png\';">'
                + '<div' + accentStyle + '>'
                + '<p class="m-0 fs-16 fw-600 ' + escapeHtml(titleClass) + '">' + escapeHtml(procedimiento) + '</p>'
                + '<p class="m-0 fs-12 fw-600 text-fade">Doctor: ' + escapeHtml(doctor) + ' | Sede: ' + escapeHtml(sede) + '</p>'
                + '<p class="m-0 fs-12 fw-600 text-fade">Fecha: ' + escapeHtml(fecha) + ' | Formulario: ' + escapeHtml(formId) + '</p>'
                + '<p class="m-0 fs-11 fw-500 text-fade text-truncate" style="max-width: 620px;">Historial: ' + escapeHtml(historial) + '</p>'
                + '</div>'
                + '</div>'
                + '<div class="text-end ms-20">'
                + '<p class="mb-0 fs-16"><i class="fa ' + escapeHtml(presentation.icon) + ' ' + escapeHtml(iconClass) + ' fs-18 me-5" aria-hidden="true"></i> ' + escapeHtml(hora) + '</p>'
                + '<p class="m-0 fs-12 fw-600 ' + escapeHtml(textClass) + '">' + escapeHtml(estado) + '</p>'
                + '</div>'
                + '</div>'
                + '</div>';
        }).join('');

        var moreInfo = '';
        if (totalRows > rows.length) {
            moreInfo = '<p class="text-muted mt-10 mb-0 px-25 pb-20">Mostrando ' + escapeHtml(rows.length)
                + ' de ' + escapeHtml(totalRows) + ' registros.</p>';
        }

        panel.innerHTML = '<div class="bg-lightgray rounded appointment overflow-hidden">'
            + '<div class="patient-scroll-inner">'
            + cards
            + '</div>'
            + moreInfo
            + '</div>';
    }

    function getExamNasListUrl(row) {
        var links = row && typeof row.links === 'object' ? row.links : {};
        var fromLinks = String(links.nas_list || '').trim();
        if (fromLinks !== '') {
            return fromLinks;
        }

        var hcNumber = String(row && row.hc_number ? row.hc_number : '').trim();
        var formId = String(row && row.form_id ? row.form_id : '').trim();
        if (hcNumber === '' || formId === '') {
            return '';
        }

        return '/imagenes/examenes-realizados/nas/list?hc_number='
            + encodeURIComponent(hcNumber)
            + '&form_id='
            + encodeURIComponent(formId);
    }

    function getExamNasPageUrl(row) {
        var links = row && typeof row.links === 'object' ? row.links : {};
        var fromLinks = String(links.imagenes || '').trim();
        if (fromLinks !== '') {
            return fromLinks;
        }

        var hcNumber = String(row && row.hc_number ? row.hc_number : '').trim();
        var formId = String(row && row.form_id ? row.form_id : '').trim();
        if (hcNumber === '' || formId === '') {
            return '/imagenes/examenes-realizados';
        }

        return '/imagenes/examenes-realizados?hc_number='
            + encodeURIComponent(hcNumber)
            + '&form_id='
            + encodeURIComponent(formId);
    }

    function pickPreferredNasFile(files) {
        if (!Array.isArray(files) || files.length === 0) {
            return null;
        }

        var preferred = files.find(function (file) {
            var ext = String(file && file.ext ? file.ext : '').toLowerCase().trim();
            return ext === 'png' || ext === 'jpg' || ext === 'jpeg';
        });

        return preferred || files[0] || null;
    }

    function openExamNasFile(nasListUrl, fallbackUrl) {
        var listUrl = String(nasListUrl || '').trim();
        var pageUrl = String(fallbackUrl || '').trim();
        var popup = window.open('about:blank', '_blank');

        if (listUrl === '') {
            if (pageUrl !== '') {
                if (popup) {
                    popup.location.href = pageUrl;
                } else {
                    window.open(pageUrl, '_blank');
                }
                return;
            }

            if (popup) {
                popup.close();
            }
            window.alert('No hay archivos del NAS disponibles para este examen.');
            return;
        }

        fetchJson(listUrl)
            .then(function (payload) {
                var files = payload && Array.isArray(payload.files) ? payload.files : [];
                var preferredFile = pickPreferredNasFile(files);
                var targetUrl = preferredFile && preferredFile.url ? String(preferredFile.url).trim() : '';

                if (targetUrl === '' && pageUrl !== '') {
                    targetUrl = pageUrl;
                }

                if (targetUrl !== '') {
                    if (popup) {
                        popup.location.href = targetUrl;
                    } else {
                        window.open(targetUrl, '_blank');
                    }
                    return;
                }

                if (popup) {
                    popup.close();
                }

                var backendError = payload && payload.error ? String(payload.error).trim() : '';
                window.alert(backendError !== '' ? backendError : 'No se encontraron imágenes del NAS para este examen.');
            })
            .catch(function (error) {
                if (pageUrl !== '') {
                    if (popup) {
                        popup.location.href = pageUrl;
                    } else {
                        window.open(pageUrl, '_blank');
                    }
                    return;
                }

                if (popup) {
                    popup.close();
                }

                window.alert(error && error.message ? error.message : 'No se pudo abrir la imagen del NAS.');
            });
    }

    function renderExamenesFiles(panel, rows, totalRows) {
        if (!panel) {
            return;
        }

        var cards = rows.map(function (row, index) {
            var examen = formatValue(row.examen, 'examen');
            var fecha = formatValue(row.fecha, 'fecha');
            var estado = formatValue(row.estado, 'estado');
            var prioridad = formatValue(row.prioridad, 'prioridad');
            var doctor = formatValue(row.doctor, 'doctor');
            var turno = formatValue(row.turno, 'turno');
            var formId = formatValue(row.form_id, 'form_id');

            var nasListUrl = getExamNasListUrl(row);
            var nasPageUrl = getExamNasPageUrl(row);
            var wrapperClass = index < rows.length - 1 ? ' mb-15' : '';
            var detalleLink = row && row.links && row.links.derivacion ? String(row.links.derivacion) : '';
            var detalleAction = detalleLink !== ''
                ? '<a class="dropdown-item" href="' + escapeHtml(detalleLink) + '" target="_blank" rel="noopener noreferrer"><i class="ti-import"></i> Details</a>'
                : '<a class="dropdown-item disabled text-muted" href="#" tabindex="-1" aria-disabled="true"><i class="ti-import"></i> Details</a>';

            return '<div class="d-flex justify-content-between align-items-center' + wrapperClass + '">'
                + '<div class="w-p100 rounded10 justify-content-between align-items-center d-flex">'
                + '<div class="d-flex justify-content-between align-items-center">'
                + '<img src="/images/pdf.png" class="me-10 avatar-sm" alt="Examen">'
                + '<div>'
                + '<p class="m-0 fs-16 fw-500 hover-blue">' + escapeHtml(examen) + '</p>'
                + '<p class="m-0 fs-12 fw-500 text-fade">Fecha: ' + escapeHtml(fecha) + ' | Estado: ' + escapeHtml(estado) + ' | Turno: ' + escapeHtml(turno) + '</p>'
                + '<p class="m-0 fs-12 fw-500 text-fade">Doctor: ' + escapeHtml(doctor) + ' | Formulario: ' + escapeHtml(formId) + ' | Prioridad: ' + escapeHtml(prioridad) + '</p>'
                + '</div>'
                + '</div>'
                + '<div class="dropdown">'
                + '<a href="#" data-action="open-exam-nas" data-nas-list-url="' + escapeHtml(nasListUrl) + '" data-nas-page-url="' + escapeHtml(nasPageUrl) + '"><i class="fa fa-download bg-light rounded p-5 me-5 text-dark" aria-hidden="true"></i></a>'
                + '<a data-bs-toggle="dropdown" href="#" aria-expanded="false"><i class="ti-more-alt rotate-90"></i></a>'
                + '<div class="dropdown-menu dropdown-menu-end">'
                + detalleAction
                + '<a class="dropdown-item" href="#" data-action="open-exam-nas" data-nas-list-url="' + escapeHtml(nasListUrl) + '" data-nas-page-url="' + escapeHtml(nasPageUrl) + '"><i class="ti-export"></i> Lab Reports</a>'
                + '</div>'
                + '</div>'
                + '</div>'
                + '</div>';
        }).join('');

        var moreInfo = '';
        if (totalRows > rows.length) {
            moreInfo = '<p class="text-muted mt-10 mb-0">Mostrando ' + escapeHtml(rows.length)
                + ' de ' + escapeHtml(totalRows) + ' registros.</p>';
        }

        panel.innerHTML = '<div class="patient-scroll-inner">' + cards + '</div>' + moreInfo;
    }

    function recetaStatePresentation(estado) {
        var normalized = String(estado || '').toLowerCase();
        if (normalized.indexOf('cancel') !== -1 || normalized === '0' || normalized.indexOf('caduc') !== -1) {
            return { pointClass: 'timeline-point-danger bg-danger', icon: 'fa-times' };
        }

        if (normalized.indexOf('entreg') !== -1 || normalized.indexOf('enviado') !== -1 || normalized === '1' || normalized.indexOf('act') !== -1) {
            return { pointClass: 'timeline-point-success bg-success', icon: 'fa-check' };
        }

        return { pointClass: 'timeline-point-primary bg-primary', icon: 'fa-refresh' };
    }

    function extractPositiveInt(rawValue) {
        var value = String(rawValue || '').trim();
        var match = value.match(/(\d+)/);
        if (!match) {
            return null;
        }

        var number = parseInt(match[1], 10);
        return isNaN(number) || number <= 0 ? null : number;
    }

    function recetaDaysSent(row) {
        var cantidadDays = extractPositiveInt(row && row.cantidad ? row.cantidad : '');
        if (cantidadDays !== null) {
            return cantidadDays + ' Days';
        }

        var totalDays = extractPositiveInt(row && row.total_farmacia ? row.total_farmacia : '');
        if (totalDays !== null) {
            return totalDays + ' Days';
        }

        return '—';
    }

    function recetaFrequency(row) {
        var pauta = String(row && row.pauta ? row.pauta : '').trim();
        if (pauta !== '') {
            return pauta;
        }

        var dosis = String(row && row.dosis ? row.dosis : '').trim();
        if (dosis !== '') {
            return dosis;
        }

        return 'Sin frecuencia';
    }

    function renderRecetasTimeline(panel, rows, totalRows) {
        if (!panel) {
            return;
        }

        recetasRowsCache = rows.slice();

        var items = rows.map(function (row) {
            var producto = formatValue(row.producto, 'producto');
            var fecha = formatValue(row.fecha, 'fecha');
            var estado = formatValue(row.estado, 'estado');
            var via = formatValue(row.via, 'via');
            var frecuencia = recetaFrequency(row);
            var dias = recetaDaysSent(row);
            var formId = String(row.form_id || '').trim();
            var rowId = String(row.id || '').trim();
            var point = recetaStatePresentation(estado);

            return '<div class="timeline-item timeline-item-arrow-sm">'
                + '<div class="timeline-point ' + escapeHtml(point.pointClass) + '">'
                + '<i class="fa ' + escapeHtml(point.icon) + ' text-white" aria-hidden="true"></i>'
                + '</div>'
                + '<div>'
                + '<div class="timeline-heading d-flex justify-content-between">'
                + '<h5 class="timeline-title fw-500 mb-0">'
                + '<a href="#" class="hover-primary" data-action="open-receta-modal" data-receta-form-id="' + escapeHtml(formId) + '" data-receta-row-id="' + escapeHtml(rowId) + '">' + escapeHtml(producto) + '</a>'
                + '</h5>'
                + '<p class="text-fade mb-0 fw-500">' + escapeHtml(dias) + '</p>'
                + '</div>'
                + '<div class="timeline-body">'
                + '<p class="text-fade mb-0 fw-500">' + escapeHtml(fecha) + ' | Frecuencia: ' + escapeHtml(frecuencia) + ' | Vía: ' + escapeHtml(via) + '</p>'
                + '</div>'
                + '</div>'
                + '</div>';
        }).join('');

        var moreInfo = '';
        if (totalRows > rows.length) {
            moreInfo = '<p class="text-muted mt-10 mb-0">Mostrando ' + escapeHtml(rows.length)
                + ' de ' + escapeHtml(totalRows) + ' registros.</p>';
        }

        panel.innerHTML = '<div class="patient-scroll-inner">'
            + '<div class="pt-20"><div class="timeline timeline-single-column timeline-single-full-column">'
            + items
            + '</div></div>'
            + '</div>'
            + moreInfo;
    }

    function derivacionStatusPresentation(fechaVigencia) {
        var ts = parseDateTimestamp(fechaVigencia);
        if (ts === null) {
            return { label: 'Sin vigencia', badgeClass: 'badge-secondary-light' };
        }

        var now = new Date();
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
        if (ts >= today) {
            return { label: 'Vigente', badgeClass: 'badge-success-light' };
        }

        return { label: 'Caducada', badgeClass: 'badge-danger-light' };
    }

    function openAndPrintUrl(url) {
        var printableUrl = String(url || '').trim();
        if (printableUrl === '') {
            window.alert('No hay PDF disponible para imprimir.');
            return;
        }

        var popup = window.open(printableUrl, '_blank');
        if (!popup) {
            return;
        }

        setTimeout(function () {
            try {
                popup.focus();
                popup.print();
            } catch (error) {
                // Ignorar: algunos navegadores bloquean print por políticas del visor PDF.
            }
        }, 1200);
    }

    function renderDerivacionesTickets(panel, rows, totalRows) {
        if (!panel) {
            return;
        }

        var items = rows.map(function (row, index) {
            var referido = formatValue(row.referido, 'referido');
            var diagnostico = formatValue(row.diagnostico, 'diagnostico');
            var codigo = formatValue(row.codigo, 'codigo');
            var sede = formatValue(row.sede, 'sede');
            var fecha = formatValue(row.fecha, 'fecha');
            var vigencia = formatValue(row.fecha_vigencia, 'fecha_vigencia');
            var status = derivacionStatusPresentation(row.fecha_vigencia);
            var relativeTime = formatRelativeDays(row.fecha);
            var avatarIndex = (index % 12) + 1;
            var avatar = '/images/avatar/avatar-' + avatarIndex + '.png';
            var linkArchivo = row && row.links && row.links.archivo ? String(row.links.archivo).trim() : '';
            var linkDetalle = row && row.links && row.links.detalle ? String(row.links.detalle).trim() : '';
            var lineClass = index < rows.length - 1 ? ' bb-1 bb-dashed border-light' : '';
            var title = referido !== '—' ? referido : ('Derivación ' + (codigo !== '—' ? ('#' + codigo) : 'sin código'));

            var actions = '';
            if (linkArchivo !== '') {
                actions += '<a class="ms-10 text-primary" href="' + escapeHtml(linkArchivo) + '" target="_blank" rel="noopener noreferrer" title="Abrir PDF"><i class="fa fa-file-pdf-o"></i></a>';
                actions += '<a class="ms-10 text-dark" href="#" data-action="print-derivacion" data-print-url="' + escapeHtml(linkArchivo) + '" title="Imprimir PDF"><i class="fa fa-print"></i></a>';
            } else if (linkDetalle !== '') {
                actions += '<a class="ms-10 text-primary" href="' + escapeHtml(linkDetalle) + '" target="_blank" rel="noopener noreferrer" title="Ver detalle"><i class="fa fa-external-link"></i></a>';
            }

            return '<div class="media-list p-0' + lineClass + '">'
                + '<div class="media align-items-center">'
                + '<a class="avatar avatar-lg status-success" href="#">'
                + '<img src="' + escapeHtml(avatar) + '" class="bg-success-light" alt="Derivación" onerror="this.onerror=null;this.src=\'/images/avatar/avatar-13.png\';">'
                + '</a>'
                + '<div class="media-body">'
                + '<p class="fs-16 mb-0"><a class="hover-primary" href="#"><strong>' + escapeHtml(title) + '</strong></a></p>'
                + escapeHtml(relativeTime)
                + '</div>'
                + '<div class="media-right d-flex align-items-center">'
                + '<span class="badge ' + escapeHtml(status.badgeClass) + '">' + escapeHtml(status.label) + '</span>'
                + actions
                + '</div>'
                + '</div>'
                + '<div class="media pt-0">'
                + '<p class="mb-0">Fecha: ' + escapeHtml(fecha) + ' | Vigencia: ' + escapeHtml(vigencia) + ' | Código: ' + escapeHtml(codigo) + ' | Sede: ' + escapeHtml(sede) + '<br>Diagnóstico: ' + escapeHtml(diagnostico) + '</p>'
                + '</div>'
                + '</div>';
        }).join('');

        var moreInfo = '';
        if (totalRows > rows.length) {
            moreInfo = '<p class="text-muted mt-10 mb-0">Mostrando ' + escapeHtml(rows.length)
                + ' de ' + escapeHtml(totalRows) + ' registros.</p>';
        }

        panel.innerHTML = '<div class="patient-scroll-inner">'
            + items
            + '</div>'
            + moreInfo;
    }

    function getRecetaRowsForModal(formId, rowId) {
        var normalizedFormId = String(formId || '').trim();
        if (normalizedFormId !== '') {
            var byForm = recetasRowsCache.filter(function (item) {
                return String(item && item.form_id ? item.form_id : '').trim() === normalizedFormId;
            });
            if (byForm.length > 0) {
                return byForm;
            }
        }

        var normalizedRowId = String(rowId || '').trim();
        if (normalizedRowId !== '') {
            return recetasRowsCache.filter(function (item) {
                return String(item && item.id ? item.id : '').trim() === normalizedRowId;
            });
        }

        return [];
    }

    function buildRecetaPrintMarkup(rows, formId) {
        var container = getPatientSectionsContainer();
        var hcNumber = container ? String(container.getAttribute('data-hc') || '').trim() : '';
        var patientNameEl = document.querySelector('.wed-up .fw-600');
        var patientName = patientNameEl ? String(patientNameEl.textContent || '').trim() : 'Paciente';

        var bodyRows = rows.map(function (row, index) {
            return '<tr>'
                + '<td>' + (index + 1) + '</td>'
                + '<td>' + escapeHtml(formatValue(row.producto, 'producto')) + '</td>'
                + '<td>' + escapeHtml(recetaFrequency(row)) + '</td>'
                + '<td>' + escapeHtml(formatValue(row.via, 'via')) + '</td>'
                + '<td>' + escapeHtml(formatValue(row.cantidad, 'cantidad')) + '</td>'
                + '<td>' + escapeHtml(formatValue(row.estado, 'estado')) + '</td>'
                + '</tr>';
        }).join('');

        return '<h2 style="margin-bottom:8px;">Receta médica</h2>'
            + '<p style="margin:0 0 4px 0;"><strong>Paciente:</strong> ' + escapeHtml(patientName) + '</p>'
            + '<p style="margin:0 0 4px 0;"><strong>HC:</strong> ' + escapeHtml(hcNumber !== '' ? hcNumber : '—') + '</p>'
            + '<p style="margin:0 0 16px 0;"><strong>Formulario:</strong> ' + escapeHtml(formId !== '' ? formId : '—') + '</p>'
            + '<table style="width:100%; border-collapse: collapse;" border="1" cellpadding="6" cellspacing="0">'
            + '<thead><tr><th>#</th><th>Producto</th><th>Frecuencia</th><th>Vía</th><th>Cantidad</th><th>Estado</th></tr></thead>'
            + '<tbody>' + bodyRows + '</tbody></table>';
    }

    function showRecetaModal(formId, rowId) {
        var rows = getRecetaRowsForModal(formId, rowId);
        var modalContent = document.getElementById('recetaModalContent');
        if (!modalContent) {
            return;
        }

        if (rows.length === 0) {
            modalContent.innerHTML = '<p class="text-muted mb-0">No se encontró detalle de la receta.</p>';
            recetaPrintHtml = '';
        } else {
            var normalizedFormId = String(formId || (rows[0] && rows[0].form_id ? rows[0].form_id : '')).trim();
            var itemsHtml = rows.map(function (row) {
                var producto = formatValue(row.producto, 'producto');
                var fecha = formatValue(row.fecha, 'fecha');
                var doctor = formatValue(row.doctor, 'doctor');
                var estado = formatValue(row.estado, 'estado');
                var frecuencia = recetaFrequency(row);
                var via = formatValue(row.via, 'via');
                var cantidad = formatValue(row.cantidad, 'cantidad');
                var totalFarmacia = formatValue(row.total_farmacia, 'total_farmacia');

                return '<div class="mb-10 p-10 border rounded">'
                    + '<p class="mb-5"><strong>Producto:</strong> ' + escapeHtml(producto) + '</p>'
                    + '<p class="mb-5"><strong>Frecuencia:</strong> ' + escapeHtml(frecuencia) + '</p>'
                    + '<p class="mb-5"><strong>Vía:</strong> ' + escapeHtml(via) + '</p>'
                    + '<p class="mb-5"><strong>Cantidad:</strong> ' + escapeHtml(cantidad) + ' | <strong>Días enviados:</strong> ' + escapeHtml(recetaDaysSent(row)) + '</p>'
                    + '<p class="mb-5"><strong>Estado:</strong> ' + escapeHtml(estado) + ' | <strong>Fecha:</strong> ' + escapeHtml(fecha) + '</p>'
                    + '<p class="mb-0"><strong>Doctor:</strong> ' + escapeHtml(doctor) + ' | <strong>Total farmacia:</strong> ' + escapeHtml(totalFarmacia) + '</p>'
                    + '</div>';
            }).join('');

            modalContent.innerHTML = '<p class="mb-10"><strong>Formulario:</strong> ' + escapeHtml(normalizedFormId !== '' ? normalizedFormId : '—') + '</p>' + itemsHtml;
            recetaPrintHtml = buildRecetaPrintMarkup(rows, normalizedFormId);
        }

        var modalEl = document.getElementById('modalReceta');
        if (!modalEl || !window.bootstrap || !bootstrap.Modal) {
            return;
        }

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function printRecetaModal() {
        if (recetaPrintHtml === '') {
            window.alert('No hay información de receta para imprimir.');
            return;
        }

        var popup = window.open('', '_blank');
        if (!popup) {
            return;
        }

        popup.document.open();
        popup.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Receta</title></head><body style="font-family:Arial,sans-serif;padding:20px;">' + recetaPrintHtml + '</body></html>');
        popup.document.close();
        popup.focus();
        setTimeout(function () {
            popup.print();
        }, 250);
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

        if (section === 'agenda') {
            renderAgendaCards(panel, rows, totalRows);
            return;
        }

        if (section === 'examenes') {
            renderExamenesFiles(panel, rows, totalRows);
            return;
        }

        if (section === 'recetas') {
            renderRecetasTimeline(panel, rows, totalRows);
            return;
        }

        if (section === 'derivaciones') {
            renderDerivacionesTickets(panel, rows, totalRows);
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

    function renderSolicitudesPanel(payload, hcNumber) {
        var panel = document.getElementById('patient-solicitudes-panel');
        if (!panel) {
            return;
        }

        var rows = Array.isArray(payload.data) ? payload.data : [];
        rows = rows.filter(function (row) {
            var procedimiento = String(row && row.procedimiento ? row.procedimiento : '').trim();
            return procedimiento !== '' && procedimiento.toUpperCase() !== 'SELECCIONE';
        });
        var totalRows = payload.meta && typeof payload.meta.total_rows === 'number' ? payload.meta.total_rows : rows.length;

        if (rows.length === 0) {
            panel.innerHTML = '<p class="text-muted mb-0">Sin solicitudes registradas.</p>';
            return;
        }

        var items = rows.map(function (row, index) {
            var formId = String(row.form_id || '').trim();
            var fecha = formatValue(row.fecha, 'fecha');
            var estado = formatValue(row.estado, 'estado');
            var prioridad = formatValue(row.prioridad, 'prioridad');
            var doctor = formatValue(row.doctor, 'doctor');
            var procedimiento = formatValue(row.procedimiento, 'procedimiento');

            if (procedimiento === '—' && formId !== '') {
                procedimiento = 'Solicitud #' + formId;
            }

            var estadoLower = String(row.estado || '').trim().toLowerCase();
            var prioridadLower = String(row.prioridad || '').trim().toLowerCase();

            var bulletColor = 'bg-primary';
            if (estadoLower.indexOf('atras') !== -1 || estadoLower.indexOf('venc') !== -1) {
                bulletColor = 'bg-danger';
            } else if (estadoLower.indexOf('atend') !== -1 || estadoLower.indexOf('pagad') !== -1 || estadoLower.indexOf('archiv') !== -1) {
                bulletColor = 'bg-success';
            } else if (estadoLower.indexOf('agend') !== -1 || estadoLower.indexOf('confirm') !== -1 || estadoLower.indexOf('recib') !== -1) {
                bulletColor = 'bg-info';
            }

            var estadoBadgeClass = 'bg-secondary';
            if (bulletColor === 'bg-danger') {
                estadoBadgeClass = 'bg-danger';
            } else if (bulletColor === 'bg-success') {
                estadoBadgeClass = 'bg-success';
            } else if (bulletColor === 'bg-info') {
                estadoBadgeClass = 'bg-info';
            }

            var prioridadAlta = prioridadLower === 'si' || prioridadLower === 'sí'
                || prioridadLower === 'alta' || prioridadLower === '1' || prioridadLower === 'true';
            var prioridadBadgeClass = prioridadAlta ? 'bg-danger' : 'bg-light text-dark';

            var checkboxId = 'patientSolicitudItem_' + index + '_' + formId.replace(/[^a-z0-9_-]/gi, '');
            var actionHtml = '';
            if (formId !== '') {
                actionHtml = '<div class="dropdown">'
                    + '<a class="px-10 pt-5" href="#" data-bs-toggle="dropdown"><i class="ti-more-alt"></i></a>'
                    + '<div class="dropdown-menu dropdown-menu-end">'
                    + '<a class="dropdown-item flexbox" href="#" data-bs-toggle="modal" data-bs-target="#modalSolicitud"'
                    + ' data-form-id="' + escapeHtml(formId) + '" data-hc="' + escapeHtml(hcNumber) + '">'
                    + '<span>Ver detalles</span></a>'
                    + '</div></div>';
            }

            var procedimientoHtml = '<a href="#" class="text-dark fw-500 fs-16"'
                + (formId !== '' ? ' data-bs-toggle="modal" data-bs-target="#modalSolicitud" data-form-id="' + escapeHtml(formId) + '" data-hc="' + escapeHtml(hcNumber) + '"' : '')
                + '>' + escapeHtml(procedimiento) + '</a>';

            return '<div class="d-flex align-items-center mb-25">'
                + '<span class="bullet bullet-bar ' + escapeHtml(bulletColor) + ' align-self-stretch"></span>'
                + '<div class="h-20 mx-20 flex-shrink-0">'
                + '<input type="checkbox" id="' + escapeHtml(checkboxId) + '" class="filled-in" disabled>'
                + '<label for="' + escapeHtml(checkboxId) + '" class="h-20 p-10 mb-0"></label>'
                + '</div>'
                + '<div class="d-flex flex-column flex-grow-1">'
                + procedimientoHtml
                + '<span class="text-fade fw-500">Creada el ' + escapeHtml(fecha)
                + ' | Doctor: ' + escapeHtml(doctor)
                + ' | Formulario: ' + escapeHtml(formId !== '' ? formId : '—') + '</span>'
                + '<div class="mt-5">'
                + '<span class="badge ' + escapeHtml(estadoBadgeClass) + '">' + escapeHtml(estado) + '</span>'
                + ' <span class="badge ' + escapeHtml(prioridadBadgeClass) + '">Prioridad: ' + escapeHtml(prioridad) + '</span>'
                + '</div>'
                + '</div>'
                + actionHtml
                + '</div>';
        }).join('');

        var moreInfo = '';
        if (totalRows > rows.length) {
            moreInfo = '<p class="text-muted mt-10 mb-0">Mostrando ' + escapeHtml(rows.length)
                + ' de ' + escapeHtml(totalRows) + ' registros.</p>';
        }

        panel.innerHTML = items + moreInfo;
    }

    function renderPaciente360Error(section, message) {
        var panel = document.getElementById('paciente360-panel-' + section);
        if (!panel) {
            return;
        }

        panel.innerHTML = '<p class="text-danger mb-0">' + escapeHtml(message || 'No se pudo cargar la sección.') + '</p>';
    }

    function readPaciente360FiltersFromUrl() {
        var params = new URLSearchParams(window.location.search || '');
        var dateFrom = (params.get('p360_date_from') || params.get('date_from') || '').trim();
        var dateTo = (params.get('p360_date_to') || params.get('date_to') || '').trim();
        var estado = (params.get('p360_estado') || params.get('estado') || '').trim();
        var search = (params.get('p360_search') || params.get('search') || '').trim();

        return {
            date_from: dateFrom,
            date_to: dateTo,
            estado: estado,
            search: search
        };
    }

    function loadSolicitudesPanel() {
        var panel = document.getElementById('patient-solicitudes-panel');
        var container = getPatientSectionsContainer();
        if (!panel || !container) {
            return;
        }

        var hcNumber = container.getAttribute('data-hc');
        if (!hcNumber) {
            panel.innerHTML = '<p class="text-muted mb-0">Sin solicitudes registradas.</p>';
            return;
        }

        panel.innerHTML = '<p class="text-muted mb-0">Cargando solicitudes...</p>';

        var filters = readPaciente360FiltersFromUrl();
        var query = new URLSearchParams();
        query.set('hc_number', hcNumber);
        query.set('section', 'solicitudes');
        query.set('limit', '15');

        if (filters.date_from) {
            query.set('date_from', filters.date_from);
        }
        if (filters.date_to) {
            query.set('date_to', filters.date_to);
        }
        if (filters.estado) {
            query.set('estado', filters.estado);
        }
        if (filters.search) {
            query.set('search', filters.search);
        }

        fetchJson('/v2/pacientes/detalles/section?' + query.toString())
            .then(function (payload) {
                renderSolicitudesPanel(payload || {}, hcNumber);
            })
            .catch(function (error) {
                panel.innerHTML = '<p class="text-danger mb-0">'
                    + escapeHtml(error && error.message ? error.message : 'No se pudo cargar solicitudes.')
                    + '</p>';
            });
    }

    function loadPaciente360() {
        var container = getPatientSectionsContainer();
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

        var filters = readPaciente360FiltersFromUrl();

        sections.forEach(function (section) {
            var panel = document.getElementById('paciente360-panel-' + section);
            if (panel) {
                panel.innerHTML = '<p class="text-muted mb-0">Cargando...</p>';
            }
        });

        var lastSummary = null;

        Promise.all(sections.map(function (section) {
            var query = new URLSearchParams();
            query.set('hc_number', hcNumber);
            query.set('section', section);
            query.set('limit', '15');

            if (filters.date_from) {
                query.set('date_from', filters.date_from);
            }
            if (filters.date_to) {
                query.set('date_to', filters.date_to);
            }
            if (filters.estado) {
                query.set('estado', filters.estado);
            }
            if (filters.search) {
                query.set('search', filters.search);
            }

            var url = '/v2/pacientes/detalles/section?' + query.toString();

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
            var url = '/v2/reports/protocolo/pdf?form_id=' + encodeURIComponent(formId)
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

    function renderStatsChart() {
        var chartContainer = document.querySelector('#chart123');
        if (!chartContainer) {
            return;
        }

        var chartData = window.patientDetailChartData || {};
        var series = Array.isArray(chartData.series) ? chartData.series : [];
        var labels = Array.isArray(chartData.labels) ? chartData.labels : [];

        if (typeof ApexCharts === 'undefined' || series.length === 0) {
            chartContainer.innerHTML = '<p class="text-muted mb-0">Sin datos suficientes para mostrar estadísticas.</p>';
            return;
        }

        var options = {
            series: series,
            chart: { type: 'donut' },
            colors: ['#3246D3', '#00D0FF', '#ee3158', '#ffa800', '#05825f'],
            legend: { position: 'bottom' },
            plotOptions: { pie: { donut: { size: '45%' } } },
            labels: labels,
            responsive: [
                { breakpoint: 1600, options: { chart: { width: 330 } } },
                { breakpoint: 500, options: { chart: { width: 280 } } }
            ]
        };

        new ApexCharts(chartContainer, options).render();
    }

    function initPatientDetailPage() {
        window.filterDocuments('ultimos_3_meses');
        loadSolicitudesPanel();
        loadPaciente360();
        renderStatsChart();

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-action="open-exam-nas"]');
            if (!trigger) {
                var recetaTrigger = event.target.closest('[data-action="open-receta-modal"]');
                if (recetaTrigger) {
                    event.preventDefault();
                    showRecetaModal(
                        recetaTrigger.getAttribute('data-receta-form-id') || '',
                        recetaTrigger.getAttribute('data-receta-row-id') || ''
                    );
                    return;
                }

                var printDerivacionTrigger = event.target.closest('[data-action="print-derivacion"]');
                if (printDerivacionTrigger) {
                    event.preventDefault();
                    openAndPrintUrl(printDerivacionTrigger.getAttribute('data-print-url') || '');
                }

                return;
            }

            event.preventDefault();
            openExamNasFile(
                trigger.getAttribute('data-nas-list-url') || '',
                trigger.getAttribute('data-nas-page-url') || ''
            );
        });

        var printRecetaBtn = document.getElementById('btnPrintReceta');
        if (printRecetaBtn) {
            printRecetaBtn.addEventListener('click', function () {
                printRecetaModal();
            });
        }

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

            var endpoint = '/v2/pacientes/detalles/solicitud?hc_number=' + encodeURIComponent(hcNumber)
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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPatientDetailPage);
    } else {
        initPatientDetailPage();
    }
})();
