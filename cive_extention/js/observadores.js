// Variables globales para pacientes de optometría y prioritarios
let pacientesOptometriaHoy = [];
let pacientesPrioritarios = [];
let colaOptometriaActual = [];
const OPTO_FLOAT_STORAGE_KEY = 'civeOptometriaFloatVisible';
(function () {

    function isOptometriaFloatVisible() {
        try {
            return localStorage.getItem(OPTO_FLOAT_STORAGE_KEY) === '1';
        } catch (error) {
            return false;
        }
    }

    function setOptometriaFloatVisible(visible) {
        try {
            localStorage.setItem(OPTO_FLOAT_STORAGE_KEY, visible ? '1' : '0');
        } catch (error) {
            console.warn('No se pudo guardar preferencia del panel de optometría:', error);
        }
    }

    function buildOptometriaQueueMarkup(lista = []) {
        const enAtencion = lista.filter((item) => item?.estado_visual === 'en_atencion');
        const enCola = lista.filter((item) => item?.estado_visual === 'en_cola');
        const siguientes = enCola.slice(0, 10);

        if (!enAtencion.length && !siguientes.length) {
            return '';
        }

        return `
            <div class="cive-opto-panel__header">
                <div>
                    <strong>Cola de optometría</strong>
                    <div class="cive-opto-panel__meta">${enCola.length} en espera</div>
                </div>
            </div>
            ${enAtencion.length ? `
                <div class="cive-opto-panel__section">
                    <div class="cive-opto-panel__label">En atención</div>
                    ${enAtencion.map((item) => `
                        <div class="cive-opto-panel__row cive-opto-panel__row--active">
                            <span class="cive-opto-panel__pill">ATENDIENDO</span>
                            <div>
                                <div class="cive-opto-panel__name">${item.nombre || item.form_id}</div>
                                <div class="cive-opto-panel__detail">${item.doctor || 'Sin doctor'} · ${construirDetallePrioridad(item)}</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            ` : ''}
            ${siguientes.length ? `
                <div class="cive-opto-panel__section">
                    <div class="cive-opto-panel__label">Quién sigue</div>
                    ${siguientes.map((item) => `
                        <div class="cive-opto-panel__row">
                            <span class="cive-opto-panel__pill">#${item.turno_visual}</span>
                            <div>
                                <div class="cive-opto-panel__name">${item.nombre || item.form_id}</div>
                                <div class="cive-opto-panel__detail">${construirDetallePrioridad(item)}</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            ` : ''}
        `;
    }

    function renderOptometriaPopupSection(lista = []) {
        const contenedor = document.getElementById('contenedorOptometriaCola');
        const estado = document.getElementById('estado-optometria');
        const btnToggle = document.getElementById('btnToggleOptometriaFloat');
        if (!contenedor) return;

        const markup = buildOptometriaQueueMarkup(lista);
        contenedor.classList.add('cive-opto-panel', 'cive-opto-panel--embedded');

        if (!markup) {
            contenedor.innerHTML = '';
            if (estado) {
                estado.textContent = 'No hay pacientes activos en la cola de optometría.';
            }
        } else {
            contenedor.innerHTML = markup;
            if (estado) {
                estado.textContent = '';
            }
        }

        if (btnToggle) {
            const visible = isOptometriaFloatVisible();
            btnToggle.innerHTML = visible
                ? '<i class="fas fa-window-minimize" aria-hidden="true"></i><span>Ocultar panel</span>'
                : '<i class="fas fa-window-restore" aria-hidden="true"></i><span>Mostrar panel</span>';
        }
    }

    function actualizarColorFilasPorTiempoYAfiliacion() {
        const tabla = document.querySelector('table.kv-grid-table');
        if (!tabla) return;

        const excluirAfiliaciones = ['CONTRIBUYENTE VOLUNTARIO', 'CONYUGE', 'CONYUGE PENSIONISTA', 'ISSFA', 'ISSPOL', 'MSP', 'SEGURO CAMPESINO', 'SEGURO CAMPESINO JUBILADO', 'SEGURO GENERAL', 'SEGURO GENERAL JUBILADO', 'SEGURO GENERAL POR MONTEPIO', 'SEGURO GENERAL TIEMPO PARCIAL'];

        const filas = tabla.querySelectorAll('tbody tr');
        filas.forEach((fila) => {
            const afiliacionTd = fila.querySelector('td[data-col-seq="11"]');
            const tiempoTd = fila.querySelector('td[data-col-seq="17"]');

            if (afiliacionTd && tiempoTd) {
                const afiliacionTexto = afiliacionTd.textContent.trim();
                const tiempoTexto = tiempoTd.querySelector('span[name="intervalos"]')?.textContent.trim();

                if (!excluirAfiliaciones.includes(afiliacionTexto) && tiempoTexto) {
                    const [horas, minutos] = tiempoTexto.split(':').map(Number);
                    const tiempoTotalMinutos = horas * 60 + minutos;

                    // Aplicar clases en función del tiempo de espera
                    if (tiempoTotalMinutos >= 30) {
                        fila.classList.add('espera-prolongada-particular');
                        fila.classList.remove('llegado-particular');
                    } else if (tiempoTotalMinutos > 0) {
                        fila.classList.add('llegado-particular');
                        fila.classList.remove('espera-prolongada-particular');
                    }
                } else {
                    // Remover cualquier clase previa si no cumple la condición
                    fila.classList.remove('llegado-particular', 'espera-prolongada-particular');
                }
            }
        });
    }

    // Nueva función: marcar filas en atención optometría
    async function actualizarEstadoAtencionOptometria() {
        if (window.__bloqueoActualizacionOpto) return;
        window.__bloqueoActualizacionOpto = true;
        setTimeout(() => window.__bloqueoActualizacionOpto = false, 10000); // Bloqueo de 10s

        const tabla = document.querySelector('table.kv-grid-table');
        if (!tabla) return;

        const fechaHoy = new Date().toISOString().split('T')[0];

        try {
            const respuesta = await new Promise((resolve, reject) => {
                if (!chrome?.runtime?.sendMessage) {
                    reject(new Error('runtime no disponible'));
                    return;
                }
                chrome.runtime.sendMessage({
                    action: 'proyeccionesGet',
                    path: '/proyecciones/estado_optometria.php',
                    query: {fecha: fechaHoy},
                }, (resp) => {
                    const err = chrome.runtime.lastError;
                    if (err) return reject(err);
                    if (resp && resp.success === false) return reject(new Error(resp.error || 'Error proyeccionesGet'));
                    resolve(resp && resp.data !== undefined ? resp.data : resp);
                });
            });
            const pacientesEnAtencion = Array.isArray(respuesta?.data)
                ? respuesta.data
                : Array.isArray(respuesta?.pacientes)
                    ? respuesta.pacientes
                    : Array.isArray(respuesta)
                        ? respuesta
                        : [];
            // Normalizar los IDs recibidos del API a strings limpias
            const pacientesFormIds = pacientesEnAtencion.map((id) => id.toString().trim());
            console.log('📋 Pacientes en atención OPTOMETRIA hoy:', pacientesEnAtencion);

            const filas = tabla.querySelectorAll('tbody tr');
            filas.forEach((fila) => {
                const formIdTd = fila.querySelector('td[data-col-seq="5"]');
                if (formIdTd) {
                    const formId = formIdTd.textContent.trim();
                    if (pacientesFormIds.includes(formId.toString().trim())) {
                        fila.classList.add('atendiendo-optometria');
                        // Cambios solicitados:
                        fila.title = 'Paciente actualmente en atención en optometría';

                        const primeraCelda = fila.querySelector('td');
                        if (primeraCelda && !primeraCelda.querySelector('.icono-optometria')) {
                            const icono = document.createElement('span');
                            icono.classList.add('glyphicon', 'glyphicon-eye-open', 'icono-optometria');
                            icono.style.marginLeft = '5px';
                            icono.style.color = '#007bff';
                            icono.title = 'Paciente en atención en optometría';
                            primeraCelda.appendChild(icono);
                        }
                    } else {
                        fila.classList.remove('atendiendo-optometria');
                        // Cambios solicitados:
                        fila.removeAttribute('title');
                        const iconoExistente = fila.querySelector('.icono-optometria');
                        if (iconoExistente) iconoExistente.remove();
                    }
                }
            });
        } catch (error) {
            //console.error('Error al actualizar el estado de atención en optometría:', error);
        }
    }

    function obtenerFechaAgendaActual() {
        let fechaBusqueda = new URLSearchParams(window.location.search).get('DocSolicitudProcedimientosDoctorSearch[fechaBusqueda]');
        if (!fechaBusqueda) {
            const inputFecha = document.querySelector('#docsolicitudprocedimientosdoctorsearch-fechabusqueda');
            if (inputFecha && inputFecha.value) {
                fechaBusqueda = inputFecha.value.trim();
            }
        }

        return fechaBusqueda || null;
    }

    function limpiarJerarquiaOptometria(tabla) {
        if (!tabla) return;

        tabla.querySelectorAll('tbody tr').forEach((fila) => {
            fila.classList.remove('opto-prioridad-alta', 'opto-prioridad-media', 'opto-prioridad-baja', 'opto-en-atencion');
            fila.removeAttribute('data-opto-prioridad');
            const badge = fila.querySelector('.cive-opto-order');
            if (badge) badge.remove();
        });
    }

    function parseTiempoFila(fila) {
        const texto = fila.querySelector('td[data-col-seq="17"] [name="intervalos"]')?.textContent?.trim()
            || fila.querySelector('td[data-col-seq="17"] .badge')?.textContent?.trim()
            || '';

        if (!texto) return 0;
        if (/^0\s*min$/i.test(texto)) return 0;

        const hhmmss = texto.match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);
        if (hhmmss) {
            const horas = Number(hhmmss[1] || 0);
            const minutos = Number(hhmmss[2] || 0);
            return (horas * 60) + minutos;
        }

        const minutosMatch = texto.match(/(\d+)\s*min/i);
        if (minutosMatch) {
            return Number(minutosMatch[1] || 0);
        }

        return 0;
    }

    function filaTienePresenciaReal(fila) {
        const minutos = parseTiempoFila(fila);
        if (minutos > 0) return true;

        const badge = fila.querySelector('td[data-col-seq="17"] .badge');
        const badgeTexto = badge?.textContent?.trim() || '';
        const badgeColor = (badge?.style?.backgroundColor || '').toLowerCase().trim();

        if (badgeTexto && !/^0\s*min$/i.test(badgeTexto) && badgeTexto !== '00:00:00') {
            return true;
        }

        return badgeColor === 'red' || badgeColor === '#f39c12' || badgeColor === 'yellow';
    }

    function filaFueAtendidaEnOptometria(fila) {
        const badge = fila.querySelector('td[data-col-seq="17"] .badge');
        const badgeColor = (badge?.style?.backgroundColor || '').toLowerCase().replace(/\s+/g, '');
        return badgeColor === '#f39c12' || badgeColor === 'yellow' || badgeColor === 'rgb(243,156,18)';
    }

    function parseFechaAgenda(fechaTexto) {
        if (!fechaTexto || typeof fechaTexto !== 'string') return null;

        const match = fechaTexto.trim().match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
        if (!match) return null;

        const [, year, month, day, hour, minute, second = '00'] = match;
        return new Date(
            Number(year),
            Number(month) - 1,
            Number(day),
            Number(hour),
            Number(minute),
            Number(second)
        );
    }

    function obtenerEsperaPriorizable(item, esperaTablaMin = 0) {
        const esperaBackend = Number(item?.espera_priorizable_min);
        if (Number.isFinite(esperaBackend)) {
            return Math.max(0, esperaBackend);
        }

        const cita = parseFechaAgenda(String(item?.cita_programada || ''));
        if (!cita) {
            return Math.max(0, Number(esperaTablaMin) || 0);
        }

        const ahora = new Date();
        const esperaDesdeCita = Math.floor((ahora.getTime() - cita.getTime()) / 60000);
        return Math.max(0, Math.min(esperaDesdeCita, Number(esperaTablaMin) || 0));
    }

    function obtenerDesfaseLlegada(item, esperaTablaMin = 0) {
        const cita = parseFechaAgenda(String(item?.cita_programada || ''));
        if (!cita) {
            return {
                tiempoFavorMin: 0,
                atrasoMin: 0,
            };
        }

        const ahora = new Date();
        const esperaDesdeCita = Math.floor((ahora.getTime() - cita.getTime()) / 60000);
        const esperaReal = Math.max(0, Number(esperaTablaMin) || 0);
        const esperaCitaPositiva = Math.max(0, esperaDesdeCita);

        return {
            tiempoFavorMin: Math.max(esperaReal - esperaCitaPositiva, 0),
            atrasoMin: Math.max(esperaCitaPositiva - esperaReal, 0),
        };
    }

    function obtenerVentanaPrioridad(item) {
        if (item?.hora_llegada_real && Number.isFinite(Number(item?.ventana_prioridad_rank))) {
            return {
                rank: Number(item.ventana_prioridad_rank),
                label: String(item?.puntualidad || 'sin-referencia'),
            };
        }

        const cita = parseFechaAgenda(String(item?.cita_programada || ''));
        if (!cita) {
            return {
                rank: Number.isFinite(Number(item?.ventana_prioridad_rank))
                    ? Number(item.ventana_prioridad_rank)
                    : 3,
                label: String(item?.puntualidad || 'sin-referencia'),
            };
        }

        const ahora = new Date();
        const deltaMin = Math.floor((ahora.getTime() - cita.getTime()) / 60000);

        if (deltaMin < -5) {
            return {rank: 2, label: 'temprano'};
        }

        if (deltaMin <= 10) {
            return {rank: 0, label: 'en-ventana'};
        }

        return {rank: 1, label: 'vencido'};
    }

    function construirDetallePrioridad(item) {
        const partes = [];
        const afiliacion = String(item?.afiliacion_categoria_label || item?.afiliacion || '').trim();
        if (afiliacion) {
            partes.push(afiliacion);
        }

        if (item?.estado_visual === 'atendido_tabla') {
            partes.push('Atendido en optometría');
        } else if (item?.ventana_prioridad_label === 'en-ventana') {
            partes.push('En ventana de cita');
        } else if (item?.ventana_prioridad_label === 'vencido') {
            partes.push('Cita vencida');
        } else if (item?.ventana_prioridad_label === 'temprano') {
            partes.push('Llegó muy temprano');
        }

        const esperaReal = Number(item?.espera_real_agenda_min);
        if (Number.isFinite(esperaReal) && esperaReal > 0) {
            partes.push(`Espera real ${esperaReal} min`);
        }

        const esperaPriorizable = Number(item?.espera_priorizable_min);
        if (Number.isFinite(esperaPriorizable)) {
            partes.push(`Espera priorizable ${Math.max(0, esperaPriorizable)} min`);
        }

        const tiempoFavor = Number(item?.tiempo_a_favor_min);
        if (Number.isFinite(tiempoFavor) && tiempoFavor > 0) {
            partes.push(`Tiempo a favor ${tiempoFavor} min`);
        }

        const atrasoInferido = Number(item?.atraso_inferido_min);
        if (Number.isFinite(atrasoInferido) && atrasoInferido > 0) {
            partes.push(`Llegó tarde ${atrasoInferido} min`);
        }

        if (!partes.length && item?.estado_visual === 'en_cola') {
            partes.push('Presente en agenda');
        }

        return partes.join(' · ');
    }

    function priorizarColaSegunTabla(cola = [], tabla) {
        if (!tabla || !Array.isArray(cola) || cola.length === 0) return cola;

        const filas = Array.from(tabla.querySelectorAll('tbody tr[data-key]'));
        const presenciaPorFormId = new Map();

        filas.forEach((fila) => {
            const formId = fila.querySelector('td[data-col-seq="5"]')?.textContent?.trim() || '';
            if (!formId) return;

            presenciaPorFormId.set(formId, {
                presente: filaTienePresenciaReal(fila),
                esperaMin: parseTiempoFila(fila),
                atendido: filaFueAtendidaEnOptometria(fila),
            });
        });

        const colaNormalizada = cola.map((item) => {
            const presencia = presenciaPorFormId.get(String(item.form_id || '').trim());
            const estaPresente = Boolean(presencia?.presente);
            const fueAtendido = Boolean(presencia?.atendido);
            const esperaTablaMin = typeof presencia?.esperaMin === 'number' ? presencia.esperaMin : 0;
            const esperaPriorizable = obtenerEsperaPriorizable(item, esperaTablaMin);
            const ventanaPrioridad = obtenerVentanaPrioridad(item);
            const desfase = obtenerDesfaseLlegada(item, esperaTablaMin);
            const estadoActual = String(item.estado_actual || '').toUpperCase();

            let estadoVisual = item.estado_visual || 'sin_llegada';
            if (estadoActual === 'OPTOMETRIA') {
                estadoVisual = 'en_atencion';
            } else if (fueAtendido) {
                estadoVisual = 'atendido_tabla';
            } else if (estaPresente) {
                estadoVisual = 'en_cola';
            } else {
                estadoVisual = 'sin_llegada';
            }

            let score = Number(item.score_prioridad || 0);
            if (estadoVisual === 'en_cola' && !item.hora_llegada_real) {
                score -= 90;
            }
            if (esperaPriorizable > 0) {
                score -= Math.min(Math.floor(esperaPriorizable / 5), 24);
            }
            if (desfase.atrasoMin > 0) {
                score += Math.min(Math.floor(desfase.atrasoMin / 5), 18);
            }

            const motivos = Array.isArray(item.motivos_prioridad)
                ? item.motivos_prioridad.filter((m) => !/a[uú]n no registra llegada/i.test(String(m)))
                : [];

            return {
                ...item,
                estado_visual: estadoVisual,
                ventana_prioridad_rank: ventanaPrioridad.rank,
                ventana_prioridad_label: ventanaPrioridad.label,
                afiliacion_categoria_label: String(item?.motivos_prioridad?.[0] || item?.afiliacion || '').trim(),
                espera_desde_llegada_min: estadoVisual === 'en_cola'
                    ? (esperaPriorizable || item.espera_desde_llegada_min || 0)
                    : item.espera_desde_llegada_min,
                espera_real_agenda_min: estadoVisual === 'en_cola' ? esperaTablaMin : item.espera_real_agenda_min,
                espera_priorizable_min: estadoVisual === 'en_cola'
                    ? esperaPriorizable
                    : item.espera_priorizable_min,
                tiempo_a_favor_min: estadoVisual === 'en_cola' ? desfase.tiempoFavorMin : 0,
                atraso_inferido_min: estadoVisual === 'en_cola' ? desfase.atrasoMin : 0,
                score_prioridad: score,
                motivos_prioridad: motivos,
            };
        });

        colaNormalizada.sort((a, b) => {
            const rankEstado = (value) => {
                if (value === 'en_atencion') return 0;
                if (value === 'en_cola') return 1;
                if (value === 'atendido_tabla') return 2;
                return 3;
            };

            const estadoDiff = rankEstado(a.estado_visual) - rankEstado(b.estado_visual);
            if (estadoDiff !== 0) return estadoDiff;

            const ventanaDiff = Number(a.ventana_prioridad_rank || 99) - Number(b.ventana_prioridad_rank || 99);
            if (ventanaDiff !== 0) return ventanaDiff;

            const scoreDiff = Number(a.score_prioridad || 0) - Number(b.score_prioridad || 0);
            if (scoreDiff !== 0) return scoreDiff;

            return String(a.cita_programada || '').localeCompare(String(b.cita_programada || ''));
        });

        let turno = 0;
        return colaNormalizada.map((item) => {
            if (item.estado_visual === 'en_atencion') {
                return {
                    ...item,
                    turno_visual: 'ATENDIENDO',
                    nivel_visual: 'atencion',
                };
            }

            if (item.estado_visual === 'en_cola') {
                turno += 1;
                return {
                    ...item,
                    turno_visual: turno,
                    nivel_visual: turno <= 3 ? 'alta' : (turno <= 6 ? 'media' : 'baja'),
                };
            }

            return {
                ...item,
                turno_visual: '',
                nivel_visual: 'baja',
            };
        });
    }

    function renderPanelColaOptometria(lista = []) {
        let panel = document.getElementById('cive-opto-panel');
        if (!panel) {
            panel = document.createElement('aside');
            panel.id = 'cive-opto-panel';
            panel.className = 'cive-opto-panel';
            document.body.appendChild(panel);
        }

        const markup = buildOptometriaQueueMarkup(lista);
        const visible = isOptometriaFloatVisible();

        if (!markup || !visible) {
            panel.style.display = 'none';
            panel.innerHTML = '';
            return;
        }

        panel.style.display = 'block';
        panel.innerHTML = `
            <button type="button" class="cive-opto-panel__close" aria-label="Cerrar panel de optometría">&times;</button>
            ${markup}
        `;
        panel.querySelector('.cive-opto-panel__close')?.addEventListener('click', () => {
            setOptometriaFloatVisible(false);
            renderPanelColaOptometria(colaOptometriaActual);
            renderOptometriaPopupSection(colaOptometriaActual);
        });
    }

    async function actualizarJerarquiaOptometria() {
        const tabla = document.querySelector('table.kv-grid-table');
        if (!tabla) return;

        const fecha = obtenerFechaAgendaActual();
        if (!fecha) return;

        try {
            const respuesta = await new Promise((resolve, reject) => {
                if (!chrome?.runtime?.sendMessage) {
                    reject(new Error('runtime no disponible'));
                    return;
                }

                chrome.runtime.sendMessage({
                    action: 'proyeccionesGet',
                    path: '/proyecciones/optometria.php',
                    query: {action: 'cola', fecha},
                }, (resp) => {
                    const err = chrome.runtime.lastError;
                    if (err) return reject(err);
                    if (resp && resp.success === false) return reject(new Error(resp.error || 'Error proyeccionesGet'));
                    resolve(resp && resp.data !== undefined ? resp.data : resp);
                });
            });

            const cola = Array.isArray(respuesta?.cola)
                ? respuesta.cola
                : Array.isArray(respuesta?.data?.cola)
                    ? respuesta.data.cola
                    : [];

            const colaAjustada = priorizarColaSegunTabla(cola, tabla);
            colaOptometriaActual = colaAjustada;
            pacientesPrioritarios = colaAjustada
                .filter((item) => item?.estado_visual === 'en_cola')
                .slice(0, 3)
                .map((item) => ({
                    id: item.form_id,
                    form_id: item.form_id,
                    nombre: item.nombre,
                    afiliacion: item.afiliacion,
                }));

            const colaPorFormId = new Map(
                colaAjustada.map((item) => [String(item.form_id || '').trim(), item])
            );

            limpiarJerarquiaOptometria(tabla);

            tabla.querySelectorAll('tbody tr').forEach((fila) => {
                const formIdTd = fila.querySelector('td[data-col-seq="5"]');
                const primeraCelda = fila.querySelector('td');
                const formId = formIdTd?.textContent?.trim() || '';
                if (!formId) return;

                const item = colaPorFormId.get(formId);
                if (!item) return;

                let claseNivel = 'opto-prioridad-baja';
                if (item.estado_visual === 'en_atencion') {
                    claseNivel = 'opto-en-atencion';
                } else if (item.nivel_visual === 'alta') {
                    claseNivel = 'opto-prioridad-alta';
                } else if (item.nivel_visual === 'media') {
                    claseNivel = 'opto-prioridad-media';
                }

                fila.classList.add(claseNivel);
                fila.setAttribute('data-opto-prioridad', String(item.turno_visual || ''));
                fila.title = construirDetallePrioridad(item);

                const debeMostrarBadge = item.estado_visual === 'en_atencion' || item.estado_visual === 'en_cola';
                if (!debeMostrarBadge) {
                    const badgeExistente = primeraCelda?.querySelector('.cive-opto-order');
                    if (badgeExistente) badgeExistente.remove();
                    return;
                }

                if (primeraCelda && !primeraCelda.querySelector('.cive-opto-order')) {
                    const badge = document.createElement('span');
                    badge.className = 'cive-opto-order';
                    badge.textContent = item.estado_visual === 'en_atencion'
                        ? 'AT'
                        : `#${item.turno_visual}`;
                    badge.title = fila.title;
                    primeraCelda.prepend(badge);
                }
            });

            renderOptometriaPopupSection(colaAjustada);
            renderPanelColaOptometria(colaAjustada);
        } catch (error) {
            console.warn('No se pudo actualizar la cola priorizada de optometría:', error);
        }
    }

    window.toggleOptometriaFloatingPanel = function () {
        const nextVisible = !isOptometriaFloatVisible();
        setOptometriaFloatVisible(nextVisible);
        renderOptometriaPopupSection(colaOptometriaActual);
        renderPanelColaOptometria(colaOptometriaActual);
    };

    function mostrarNotificacionPrioridadOptometria(listaPacientes = []) {
        if (!Array.isArray(listaPacientes) || listaPacientes.length === 0) {
            ///console.warn('⚠️ Lista de pacientes prioritarios no válida o vacía:', listaPacientes);
            return;
        }

        listaPacientes.forEach((p) => {
            const nombre = p?.nombre || p?.nombre_completo || p?.paciente || p?.form_id || p?.id;
            if (!nombre) return; // Asegura que el paciente tenga datos válidos

            const alerta = document.createElement("div");
            alerta.className =
                "myadmin-alert myadmin-alert-img myadmin-alert-click alert-warning myadmin-alert-bottom alertbottom2";
            alerta.style.display = "block";
            alerta.style.position = "fixed";
            alerta.style.bottom = "0";
            alerta.style.left = "0";
            alerta.style.width = "100%";
            alerta.style.zIndex = "9999";
            alerta.innerHTML = `
      <img src="https://cdn-icons-png.flaticon.com/512/2920/2920050.png" class="img" alt="img" style="width:40px; height:40px;">
      <a href="#" class="closed" onclick="this.parentElement.remove()">×</a>
      <h4>Paciente en atención OPTOMETRÍA</h4>
      <b>${nombre}</b> está siendo atendido.
    `;

            document.body.appendChild(alerta);
            setTimeout(() => {
                alerta.remove();
            }, 10000); // Desaparece tras 10 segundos
        });
    }

    // Variable global para controlar el bloqueo de notificación
    let _bloqueoNotificacion = false;
    let columnMapCache = null;

    function observarCambiosEnTablaYPaginacion() {
        const contenedorTabla = document.querySelector('.kv-grid-container');
        if (!contenedorTabla) return;
        const observer = new MutationObserver(() => {
            if (_bloqueoNotificacion) return;
            _bloqueoNotificacion = true;

            setTimeout(() => {
                actualizarColorFilasPorTiempoYAfiliacion();
                actualizarJerarquiaOptometria();
                _bloqueoNotificacion = false;
            }, 500);
        });

        observer.observe(contenedorTabla, {childList: true, subtree: true});
    }

    function iniciarObservadores() {
        const intervalo = setInterval(() => {
            const contenedorTabla = document.querySelector('.kv-grid-container');
            if (contenedorTabla) {
                clearInterval(intervalo);
                observarCambiosEnTablaYPaginacion();
                actualizarColorFilasPorTiempoYAfiliacion();
                actualizarJerarquiaOptometria();
                actualizarEstadoAtencionOptometria();
            }
        }, 250);
    }

    function obtenerColumnMap() {
        if (window.__mapeoYaEjecutado && columnMapCache) {
            return columnMapCache;
        }

        const map = {};
        const ths = document.querySelectorAll('#crud-datatable-por-atender thead tr th');

        ths.forEach((th, index) => {
            const texto = th.textContent.trim().toLowerCase().replace(/[\n\r]+/g, ' ').replace(/\s+/g, ' ');
            if (texto && !map[texto]) {
                map[texto] = index;
            }
        });

        const tieneColumnas = Object.keys(map).length > 0;
        if (tieneColumnas) {
            columnMapCache = map;
        }

        // Prevenir ejecución repetida, pero devolviendo el último mapeo conocido
        window.__mapeoYaEjecutado = true;
        setTimeout(() => {
            window.__mapeoYaEjecutado = false;
        }, 10000); // Espera 10s antes de permitir otro mapeo
        //console.log("🔍 Mapeo de columnas detectado:", columnMapCache);
        return columnMapCache;
    }

    function observarPacientesPorAtender() {
        const selectorTabla = '#crud-datatable-por-atender table.kv-grid-table';
        const tabla = document.querySelector(selectorTabla);
        if (!tabla) return;

        const columnMap = obtenerColumnMap();
        if (!columnMap) {
            //console.warn("No se pudo construir el mapa de columnas.");
            return;
        }

        const filas = tabla.querySelectorAll('tbody tr[data-key]');
        const pacientes = [];
        let fechaBusqueda = new URLSearchParams(window.location.search).get('DocSolicitudProcedimientosDoctorSearch[fechaBusqueda]');
        if (!fechaBusqueda) {
            // Busca el valor del input de fecha si no está en la URL
            const inputFecha = document.querySelector('#docsolicitudprocedimientosdoctorsearch-fechabusqueda');
            if (inputFecha && inputFecha.value) {
                fechaBusqueda = inputFecha.value.trim();
                //console.log('🗓️ Fecha extraída del input:', fechaBusqueda);
            }
        }

        // Si sigue sin fecha, muestra un warning y NO envíes datos
        if (!fechaBusqueda) {
            alert('No se pudo determinar la fecha de la agenda. Selecciona una fecha antes de sincronizar.');
            return;
        }
        filas.forEach(fila => {
            const celdas = fila.querySelectorAll('td');
            // Ajusta los nombres de clave según los textos reales en el thead (minúsculas y espacios normalizados)
            const paciente = {
                id: celdas[columnMap["id"]]?.textContent.trim() || '',
                doctor: celdas[columnMap["doctor"]]?.textContent.trim() || '',
                hora: celdas[columnMap["hora"]]?.textContent.trim() || '',
                nombre: celdas[columnMap["paciente"]]?.textContent.trim() || '',
                identificacion: celdas[columnMap["identificación"]]?.textContent.trim() || '',
                afiliacion: celdas[columnMap["afiliación"]]?.textContent.trim() || '',
                procedimiento: celdas[columnMap["procedimiento"]]?.textContent.trim() || '',
                estado: celdas[columnMap["estado"]]?.textContent.trim() || '',
                fechaCaducidad: celdas[columnMap["fecha caducidad"]]?.textContent.trim() || ''
            };

            if (!paciente.id || !paciente.identificacion || !paciente.procedimiento) {
                //console.warn("⛔ Faltan datos clave, omitiendo fila:", paciente);
                return;
            }

            const partesNombre = paciente.nombre.split(/\s+/);
            const datosNombre = {
                lname: partesNombre[0] || '',
                lname2: partesNombre[1] || '',
                fname: partesNombre[2] || '',
                mname: partesNombre.slice(3).join(' ') || ''
            };

            //console.log("🧪 Datos extraídos:", paciente);

            pacientes.push({
                hcNumber: paciente.identificacion,
                form_id: paciente.id,
                procedimiento_proyectado: paciente.procedimiento,
                fname: datosNombre.fname,
                mname: datosNombre.mname,
                lname: datosNombre.lname,
                lname2: datosNombre.lname2,
                doctor: paciente.doctor,
                hora: paciente.hora,
                afiliacion: paciente.afiliacion,
                estado: paciente.estado,
                fecha: fechaBusqueda,
                fechaCaducidad: paciente.fechaCaducidad,
                nombre_completo: paciente.nombre
            });
        });

        pacientes.forEach(p => {
            if (!p || !p.form_id) return;
            // continuar lógica...
        });

        if (pacientes.length > 0) {
            console.log("📦 Enviando al API /proyecciones/guardar.php:", JSON.parse(JSON.stringify(pacientes)));
            window.CiveApiClient.post('/proyecciones/guardar.php', {
                body: pacientes,
            })
                .then((data) => {
                    console.log('✅ Sincronización exitosa:', data);
                    // Asignar pacientesOptometriaHoy y pacientesPrioritarios tras sincronización
                    if (data && Array.isArray(data.detalles)) {
                        pacientesOptometriaHoy = data.detalles.map((p) => p.id);
                    }
                    actualizarJerarquiaOptometria();
                })
                .catch((err) => {
                    console.error('❌ Error en la sincronización', err);
                });
        }
    }

    // Inicializar variable global para evitar bucles infinitos al seleccionar "ver todo"
    window._ultimaClaveProcesada = null;

    // --- localStorage parseo seguro para 'professional' ---
    let professional = localStorage.getItem("professional");
    try {
        professional = professional ? JSON.parse(professional) : null;
    } catch (e) {
        console.warn("Error al parsear 'professional':", e);
        professional = null;
    }

    function observarTablaPorAtenderSiCambio() {
        const urlParams = new URLSearchParams(window.location.search);
        const paginaActual = urlParams.get('por-atender-page') || '1'; // tratar sin parámetro como página 1
        const claveMostrarTodos = Array.from(urlParams).find(([k, v]) => k.includes('_tog') && v === 'all');
        const claveActual = claveMostrarTodos ? claveMostrarTodos[0] : null;

        const claveCombinada = `p${paginaActual}_all${claveActual ? '_yes' : '_no'}`;

        if (claveCombinada !== window._ultimaClaveProcesada) {
            window._ultimaClaveProcesada = claveCombinada;
            setTimeout(() => {
                // Esperar a que la tabla esté actualizada antes de sincronizar
                requestAnimationFrame(() => {
                    observarPacientesPorAtender();
                });
            }, 500);
        }
    }

    // Observador principal para detectar cambios en la tabla de por atender
    const observadorPorAtender = new MutationObserver((mutations) => {
        const tabla = document.querySelector('#crud-datatable-por-atender table.kv-grid-table');
        if (tabla) observarTablaPorAtenderSiCambio();
    });

    // Incluir estilos para notificación
    const estilo = document.createElement('style');
    estilo.innerHTML = `
    .llegado-particular {
        background-color: #FFD700 !important; /* Color para menos de 30 min */
    }

    .espera-prolongada-particular {
        background-color: #FF6347 !important; /* Color para más de 30 min */
    }

    .atendiendo-optometria {
        background-color: #add8e6 !important; /* Azul claro */
        color: black !important;
    }
    .icono-optometria {
        font-size: 14px;
        vertical-align: middle;
    }
    .opto-prioridad-alta {
        background: linear-gradient(90deg, rgba(220,53,69,0.18), rgba(255,255,255,0.98)) !important;
    }
    .opto-prioridad-media {
        background: linear-gradient(90deg, rgba(255,193,7,0.20), rgba(255,255,255,0.98)) !important;
    }
    .opto-prioridad-baja {
        background: linear-gradient(90deg, rgba(13,110,253,0.12), rgba(255,255,255,0.98)) !important;
    }
    .opto-en-atencion {
        outline: 2px solid #0d6efd;
        outline-offset: -2px;
    }
    .cive-opto-order {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 22px;
        margin-right: 6px;
        padding: 0 6px;
        border-radius: 999px;
        background: #1f2937;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        vertical-align: middle;
    }
    .cive-opto-panel {
        position: fixed;
        top: 88px;
        right: 20px;
        width: 340px;
        max-height: calc(100vh - 120px);
        overflow: auto;
        z-index: 9998;
        background: rgba(17, 24, 39, 0.95);
        color: #f9fafb;
        border-radius: 16px;
        box-shadow: 0 18px 48px rgba(15, 23, 42, 0.28);
        padding: 16px;
        font-family: 'Segoe UI', Arial, sans-serif;
        backdrop-filter: blur(8px);
    }
    .cive-opto-panel--embedded {
        position: static;
        top: auto;
        right: auto;
        width: 100%;
        max-height: none;
        overflow: visible;
        z-index: auto;
        background: rgba(17, 24, 39, 0.98);
        box-shadow: none;
        margin: 0;
    }
    .cive-opto-panel__close {
        position: absolute;
        top: 10px;
        right: 12px;
        border: 0;
        background: transparent;
        color: #cbd5e1;
        font-size: 24px;
        line-height: 1;
        cursor: pointer;
    }
    .cive-opto-panel__close:hover {
        color: #fff;
    }
    .cive-opto-panel__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
        padding-right: 26px;
    }
    .cive-opto-panel__meta {
        color: #cbd5e1;
        font-size: 12px;
        margin-top: 2px;
    }
    .cive-opto-panel__section + .cive-opto-panel__section {
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid rgba(148, 163, 184, 0.18);
    }
    .cive-opto-panel__label {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #93c5fd;
        margin-bottom: 8px;
    }
    .cive-opto-panel__row {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 10px;
        align-items: start;
        padding: 8px 0;
    }
    .cive-opto-panel__row--active .cive-opto-panel__pill {
        background: #2563eb;
    }
    .cive-opto-panel__pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 44px;
        height: 24px;
        padding: 0 8px;
        border-radius: 999px;
        background: #7c2d12;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
    }
    .cive-opto-panel__name {
        font-size: 13px;
        font-weight: 700;
        color: #fff;
    }
    .cive-opto-panel__detail {
        font-size: 12px;
        color: #cbd5e1;
        line-height: 1.4;
        margin-top: 2px;
    }
    /* Notificación estilos básicos para .myadmin-alert y .alertbottom2 si no existen */
    .myadmin-alert {
        position: fixed;
        left: 0;
        right: 0;
        width: 100%;
        max-width: none;
        border-radius: 0;
        z-index: 9999;
        background: #fff8e1;
        color: #8a6d3b;
        border: 1px solid #faebcc;
        box-shadow: 0 4px 10px rgba(0,0,0,0.18);
        padding: 20px 30px 20px 70px;
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 15px;
        transition: opacity 0.4s;
    }
    .myadmin-alert-img .img {
        position: absolute;
        left: 25px;
        top: 20px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
    }
    .myadmin-alert h4 {
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 17px;
        font-weight: bold;
    }
    .myadmin-alert .closed {
        position: absolute;
        top: 8px;
        right: 12px;
        color: #a94442;
        font-size: 22px;
        text-decoration: none;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
    }
    .myadmin-alert-bottom.alertbottom2 {
        bottom: 40px;
        top: auto;
    }
    @keyframes slideUp {
        from {
            transform: translateY(100%);
            opacity: 0;
        }
        to {
            transform: translateY(0%);
            opacity: 1;
        }
    }
    .slide-up {
        animation: slideUp 0.5s ease-out;
    }
    `;
    document.head.appendChild(estilo);

    // Asignar funciones a `window` para que sean accesibles globalmente
    window.actualizarColorFilasPorTiempoYAfiliacion = actualizarColorFilasPorTiempoYAfiliacion;
    window.observarCambiosEnTablaYPaginacion = observarCambiosEnTablaYPaginacion;
    window.iniciarObservadores = iniciarObservadores;

    const contenedorGeneral = document.body;
    if (contenedorGeneral) {
        // Inicialización de la variable global justo antes de iniciar la observación
        window._ultimaClaveProcesada = null;
        observadorPorAtender.observe(contenedorGeneral, {
            childList: true, subtree: true
        });
    }
})();
