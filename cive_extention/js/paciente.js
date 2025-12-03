(function () {
    // Helper para llamar al background desde cualquier flujo de este archivo
    const sendBg = (action, payload) => new Promise((resolve, reject) => {
        if (!chrome || !chrome.runtime || !chrome.runtime.sendMessage) {
            reject(new Error('Contexto de extensión no disponible'));
            return;
        }
        chrome.runtime.sendMessage({action, ...payload}, (resp) => {
            const err = chrome.runtime.lastError;
            if (err) return reject(new Error(err.message || 'Error de runtime'));
            if (resp && resp.success === false) return reject(new Error(resp.error || 'Fallo en background'));
            resolve(resp && resp.data !== undefined ? resp.data : resp);
        });
    });

    function detectarConfirmacionAsistencia() {
        document.querySelectorAll('button[id^="button-confirmar-"], .swal2-container button[id^="button-confirmar-"]').forEach(boton => {
            if (boton.dataset.civeLlegadaListener === 'true') {
                return;
            }
            boton.dataset.civeLlegadaListener = 'true';

            boton.addEventListener('click', (e) => {
                const idTexto = boton.id.replace('button-confirmar-', '');
                const id = parseInt(idTexto, 10);
                if (!isNaN(id)) {
                    const icono = boton.querySelector('.glyphicon');
                    if (icono && icono.classList.contains('glyphicon-thumbs-down')) {
                        console.log(`🟡 Confirmando llegada para ID: ${id}`);
                        window.CiveApiClient.post('/proyecciones/optometria.php', {
                            body: {form_id: id, estado: 'iniciar_atencion'},
                            bodyType: 'auto',
                        })
                            .then(data => {
                                if (data.success) {
                                    console.log('✅ Confirmación de llegada enviada correctamente.');
                                } else {
                                    console.log('❌ Error al confirmar llegada:', data.message);
                                }
                            })
                            .catch(error => {
                                console.log('❌ Error al enviar la solicitud de llegada:', error.message || error);
                            });
                    } else {
                        console.log(`🔵 Botón clickeado pero el icono no indica llegada (no es thumbs-up). ID: ${id}`);
                    }
                }
            });
        });
    }

    window.detectarConfirmacionAsistencia = detectarConfirmacionAsistencia;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', detectarConfirmacionAsistencia);
    } else {
        detectarConfirmacionAsistencia();
    }

    window.detectarConfirmacionAsistencia = detectarConfirmacionAsistencia;

    function iniciarDeteccionLlegadas() {
        detectarConfirmacionAsistencia();

        if (!document.body || window.__civeLlegadaObserver) {
            return;
        }

        let escaneoPendiente = false;
        const observer = new MutationObserver(() => {
            if (escaneoPendiente) return;
            escaneoPendiente = true;
            setTimeout(() => {
                escaneoPendiente = false;
                detectarConfirmacionAsistencia();
            }, 150);
        });

        observer.observe(document.body, {childList: true, subtree: true});
        window.__civeLlegadaObserver = observer;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciarDeteccionLlegadas);
    } else {
        iniciarDeteccionLlegadas();
    }

    /**
     * Bloquea o desbloquea los campos del formulario, excepto los botones.
     * Solo los elementos input, textarea y select serán afectados.
     * Los botones permanecerán siempre habilitados.
     */
    function establecerBloqueoFormulario(bloqueado = true) {
        document.querySelectorAll('input, textarea, select').forEach(element => {
            element.disabled = bloqueado;
        });
        // Los botones NO se desactivan: no modificar su estado.
    }

    // (function () {
    const filas = document.querySelectorAll('tr[data-key]');
    filas.forEach(fila => {
        fila.addEventListener('click', function () {
            const doctor = fila.querySelector('td[data-col-seq="6"]')?.textContent.trim() || null;
            const patientName = fila.querySelector('td[data-col-seq="8"]')?.textContent.trim() || null;
            const identificacion = fila.querySelector('td[data-col-seq="9"]')?.textContent.trim() || null;
            const afiliacion = fila.querySelector('td[data-col-seq="11"]')?.textContent.trim() || null;
            const procedimiento_proyectado = fila.querySelector('td[data-col-seq="13"]')?.textContent.trim() || null;

            const enlace = fila.querySelector('td[data-col-seq="13"]');
            const form_id = enlace?.getAttribute('onclick')?.match(/id=(\d+)/)?.[1] || null;

            let fechaCaducidad = fila.querySelector('td[data-col-seq="16"]')?.textContent.trim();
            if (!fechaCaducidad || fechaCaducidad === '(no definido)') {
                fechaCaducidad = null;
            }

            const badge = fila.querySelector('td[data-col-seq="17"] span.badge');
            const colorFondo = badge ? badge.style.backgroundColor.trim() : null;

            const pacienteNoAdmitido = colorFondo === 'green';

            if (identificacion && patientName && form_id) {
                const datosPaciente = {
                    identificacion,
                    nombreCompleto: patientName,
                    afiliacion,
                    doctor,
                    procedimiento_proyectado,
                    fechaCaducidad,
                    form_id,
                    pacienteNoAdmitido
                };
                localStorage.setItem('datosPacienteSeleccionado', JSON.stringify(datosPaciente));
                const destino = fila.querySelector('td[onclick*="location.href"]')?.getAttribute('onclick')?.match(/'(.+?)'/)?.[1];
                if (destino) {
                    localStorage.setItem('datosPacienteSeleccionado', JSON.stringify(datosPaciente));
                    window.location.href = destino;
                }
            }
        });
    });

    if (window.location.href.includes('/doc-solicitud-procedimientos/historia-automatica')) {
        const datos = localStorage.getItem('datosPacienteSeleccionado');
        if (datos) {
            const paciente = JSON.parse(datos);
            console.log("🧾 Datos del paciente cargados en la vista destino:", paciente);

            // ✅ Claves de control: prompt por pestaña (session) y estado persistente (local)
            const KEY_PROMPT = `prompt_iniciar_${paciente.form_id}`;         // sessionStorage (anti-duplicado modal)
            const KEY_ESTADO = `estado_atencion_${paciente.form_id}`;        // localStorage (persistente entre cierres)
            const KEY_ALERT_SOL = `alert_solicitudes_${paciente.identificacion || paciente.hcNumber || ''}`;

            // 1) Arranque rápido desde localStorage (UX inmediata)
            const estadoLocal = localStorage.getItem(KEY_ESTADO);
            if (estadoLocal === 'en_proceso') {
                establecerBloqueoFormulario(false);
            }

            // 1b) Chequear solicitudes quirúrgicas previas y notificar (una vez por sesión por HC)
            const hc = paciente.identificacion || paciente.hcNumber || '';
            if (hc && !sessionStorage.getItem(KEY_ALERT_SOL)) {
                sessionStorage.setItem(KEY_ALERT_SOL, '1');
                const mostrarAlertSolicitudes = (lista) => {
                    const masReciente = lista[0];
                    const resumen = `${masReciente.tipo || 'Solicitud'} · ${masReciente.estado || 'N/D'} · ${masReciente.fecha || ''}`;
                    const textoCorto = `HC ${hc}: ${lista.length} solicitudes. Más reciente: ${resumen}`;

                    // Preferir toastr para no bloquear el flujo de "Iniciar consulta"
                    if (window.toastr && typeof window.toastr.info === 'function') {
                        window.toastr.info(textoCorto, 'Solicitudes existentes');
                        return;
                    }

                    // Fallback no bloqueante
                    console.info('Solicitudes existentes:', textoCorto);
                };

                sendBg('solicitudesEstado', {hcNumber: hc})
                    .then((resp) => {
                        const lista = Array.isArray(resp?.solicitudes) ? resp.solicitudes : [];
                        if (!lista.length) return;
                        mostrarAlertSolicitudes(lista);
                    })
                    .catch(() => {
                        sessionStorage.removeItem(KEY_ALERT_SOL); // permitir reintento si falla
                    });
            }

            // 2) Reconciliar con el backend (usa mismo endpoint con action=estado)
            const endpointPath = paciente.procedimiento_proyectado === 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-001 - OPTOMETRIA - AMBOS OJOS'
                ? '/proyecciones/optometria.php'
                : '/proyecciones/consulta.php';

            sendBg('proyeccionesGet', {path: endpointPath, query: {form_id: paciente.form_id, action: 'estado'}})
                .then(data => {
                    if (!data || data.success === false) return;
                    const estado = data.estado;
                    if (estado === 'en_proceso') {
                        establecerBloqueoFormulario(false);
                        localStorage.setItem(KEY_ESTADO, 'en_proceso');
                    } else if (estado === 'terminado_dilatar' || estado === 'terminado_sin_dilatar') {
                        localStorage.setItem(KEY_ESTADO, estado);
                    } else if (estado === 'pendiente' || !estado) {
                        localStorage.setItem(KEY_ESTADO, 'pendiente');
                    }
                })
                .catch(() => {/* silencioso */});

            // Definir constante para saber si es optometría
            const esOptometria = paciente.procedimiento_proyectado === 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-001 - OPTOMETRIA - AMBOS OJOS';

            if (esOptometria) {
                sendBg('proyeccionesGet', {
                    path: '/proyecciones/optometria.php',
                    query: {form_id: paciente.form_id, action: 'historial'}
                })
                    .then((detalle) => {
                        if (detalle && detalle.success !== false) {
                            try {
                                sessionStorage.setItem(`trazabilidad_${paciente.form_id}`, JSON.stringify(detalle));
                            } catch (err) {
                                console.warn('⚠️ No fue posible persistir la trazabilidad en sessionStorage:', err);
                            }
                            console.log('📊 Línea de tiempo de optometría cargada:', detalle);
                        } else {
                            console.warn('⚠️ No se encontró trazabilidad para el paciente:', detalle?.message);
                        }
                    })
                    .catch(error => {
                        console.error('❌ Error al consultar trazabilidad de optometría:', error);
                    });
            }

            if (paciente.pacienteNoAdmitido) {
                establecerBloqueoFormulario(true); // 🔒 Bloquea campos
                setTimeout(() => {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: '⚠️ Paciente no admitido',
                            confirmButtonText: 'OK',
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        });
                    } else {
                        console.warn("⚠️ SweetAlert no está disponible. Verifica que se haya cargado correctamente.");
                    }
                }, 500);
            } else if (!paciente.pacienteNoAdmitido && esOptometria) {
                if (
                    !sessionStorage.getItem(KEY_PROMPT) &&
                    !['en_proceso', 'terminado_dilatar', 'terminado_sin_dilatar', 'finalizado'].includes(localStorage.getItem(KEY_ESTADO))
                ) {
                    sessionStorage.setItem(KEY_PROMPT, '1');
                    setTimeout(() => {
                        if (typeof Swal !== 'undefined') {
                            if (Swal.isVisible && Swal.isVisible()) return;
                            Swal.fire({
                                title: '¿Iniciar atención?',
                                icon: 'question',
                                confirmButtonText: 'Sí',
                                cancelButtonText: 'No',
                                showCancelButton: true,
                                reverseButtons: true
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    establecerBloqueoFormulario(false);
                                    console.log(`🟡 Confirmando llegada para ID: ${paciente.form_id}`);
                                    window.CiveApiClient.post('/proyecciones/optometria.php', {
                                        body: {form_id: paciente.form_id, estado: 'iniciar_atencion'},
                                        bodyType: 'auto',
                                    })
                                        .then(data => {
                                            if (data.success) {
                                                console.log('✅ Estado actualizado a "en proceso" correctamente.');
                                                localStorage.setItem(KEY_ESTADO, 'en_proceso');
                                            } else {
                                                console.error('❌ Error al actualizar el estado:', data.message);
                                            }
                                        })
                                        .catch(error => {
                                            console.error('❌ Error al enviar la solicitud:', error.message);
                                        });
                                } else {
                                    // permitir volver a preguntar más adelante en esta pestaña
                                    sessionStorage.removeItem(KEY_PROMPT);
                                    establecerBloqueoFormulario(true);
                                }
                            });
                        } else {
                            console.warn("⚠️ SweetAlert no está disponible. Verifica que se haya cargado correctamente.");
                        }
                    }, 500);
                }
                // Usar función refactorizada para el botón guardar en optometría
                manejarFinalizacionConsulta(
                    paciente,
                    '/proyecciones/optometria.php',
                    true
                );
            } else if (!paciente.pacienteNoAdmitido && !esOptometria) {
                if (
                    !sessionStorage.getItem(KEY_PROMPT) &&
                    !['en_proceso', 'terminado_dilatar', 'terminado_sin_dilatar', 'finalizado'].includes(localStorage.getItem(KEY_ESTADO))
                ) {
                    sessionStorage.setItem(KEY_PROMPT, '1');
                    setTimeout(() => {
                        if (typeof Swal !== 'undefined') {
                            if (Swal.isVisible && Swal.isVisible()) return;
                            Swal.fire({
                                title: '¿Iniciar atención?',
                                icon: 'question',
                                confirmButtonText: 'Sí',
                                cancelButtonText: 'No',
                                showCancelButton: true,
                                reverseButtons: true
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    establecerBloqueoFormulario(false);
                                    console.log(`🟡 Confirmando llegada para ID: ${paciente.form_id}`);
                sendBg('proyeccionesPost', {
                    path: '/proyecciones/consulta.php',
                    body: {form_id: paciente.form_id, estado: 'iniciar_atencion'},
                })
                    .then(data => {
                        if (data && data.success) {
                            console.log('✅ Estado actualizado a "en proceso" correctamente.');
                            localStorage.setItem(KEY_ESTADO, 'en_proceso');
                        } else {
                            console.error('❌ Error al actualizar el estado:', data?.message || 'sin mensaje');
                        }
                    })
                    .catch(error => {
                        console.error('❌ Error al enviar la solicitud:', error.message);
                    });
                                } else {
                                    sessionStorage.removeItem(KEY_PROMPT);
                                    establecerBloqueoFormulario(true);
                                }
                            });
                        } else {
                            console.warn("⚠️ SweetAlert no está disponible. Verifica que se haya cargado correctamente.");
                        }
                    }, 500);
                }
                // Usar función refactorizada para el botón guardar en consulta general
                manejarFinalizacionConsulta(
                    paciente,
                    '/proyecciones/consulta.php',
                    false
                );
            }
        }
    }

    const ESTADOS = {
        TERMINADO_DILATAR: 'terminado_dilatar',
        TERMINADO_SIN_DILATAR: 'terminado_sin_dilatar',
        EN_PROCESO: 'iniciar_atencion'
    };

    // Espera a que no haya un Swal visible (útil si la página muestra su propio "Guardado con éxito")
    async function waitForSwalIdle({maxWaitMs = 15000, settleMs = 300, tickMs = 150} = {}) {
        const hasVisible = () => (typeof Swal !== 'undefined' && Swal.isVisible && Swal.isVisible());
        const sleep = (ms) => new Promise(r => setTimeout(r, ms));
        const start = Date.now();
        while (Date.now() - start < maxWaitMs) {
            if (!hasVisible()) {
                // pequeña espera de asentamiento para evitar superposición de modales consecutivos
                await sleep(settleMs);
                if (!hasVisible()) return true;
            }
            await sleep(tickMs);
        }
        return false; // timeout: seguimos de todos modos
    }

    // ==== CIVE: Cola/Lock para SweetAlert2 durante el flujo de finalización ====
    (function () {
        if (typeof window.__civeSwalSetup === 'boolean') return; // evitar doble setup
        window.__civeSwalSetup = true;
        window.__civeSwalQueue = [];
        window.__civeSwalLockDepth = 0;
        const originalFire = (typeof Swal !== 'undefined' && Swal.fire) ? Swal.fire.bind(Swal) : null;
        window.__civeSwalOriginalFire = originalFire;

        // 🧼 Quita opciones personalizadas NO reconocidas por SweetAlert2
        function __stripSwalCustomOptions(opts) {
            if (!opts || typeof opts !== 'object') return opts;
            const {civeBypass, ...rest} = opts; // quita civeBypass
            return rest;
        }

        function patchedFire(opts, ...rest) {
            const isBypass = opts && typeof opts === 'object' && opts.civeBypass === true;
            if (window.__civeSwalLockDepth > 0 && !isBypass) {
                return new Promise((resolve, reject) => {
                    // Guardamos los args originales; sanitizaremos al ejecutar
                    window.__civeSwalQueue.push({args: [opts, ...rest], resolve, reject});
                });
            }
            // Siempre sanitizar antes de delegar a SweetAlert2
            const cleanOpts = __stripSwalCustomOptions(opts);
            return originalFire ? originalFire(cleanOpts, ...rest) : Promise.resolve();
        }

        window.__civeEnableSwalLock = function __civeEnableSwalLock() {
            window.__civeSwalLockDepth++;
            if (typeof Swal !== 'undefined' && Swal.fire !== patchedFire) {
                Swal.fire = patchedFire;
            }
        };

        window.__civeDisableSwalLock = function __civeDisableSwalLock() {
            if (window.__civeSwalLockDepth > 0) window.__civeSwalLockDepth--;
            if (window.__civeSwalLockDepth === 0) {
                // Restaurar y drenar cola
                if (typeof Swal !== 'undefined' && window.__civeSwalOriginalFire) Swal.fire = window.__civeSwalOriginalFire;
                const q = window.__civeSwalQueue.splice(0);
                if (q.length && window.__civeSwalOriginalFire) {
                    const play = () => {
                        const item = q.shift();
                        if (!item) return;
                        // Sanitizar justo antes de llamar al SweetAlert real
                        const [opts, ...rest] = item.args;
                        const cleanOpts = __stripSwalCustomOptions(opts);
                        window.__civeSwalOriginalFire(cleanOpts, ...rest)
                            .then(item.resolve)
                            .catch(item.reject)
                            .finally(() => {
                                setTimeout(play, 50);
                            });
                    };
                    play();
                }
            }
        };
    })();

    // ==== FIN Cola/Lock ====

    function manejarFinalizacionConsulta(paciente, endpointPath, esOptometria) {
        const botonGuardar = document.getElementById('botonGuardar');
        if (botonGuardar && botonGuardar.dataset.listenerInicializado === 'true') return;
        if (botonGuardar) botonGuardar.dataset.listenerInicializado = 'true';

        // Flujo de dilatación/finalización SIN bloquear el guardado de la página
        const lanzarFlujoFinalizacion = async () => {
            // Evitar múltiples ejecuciones simultáneas
            if (lanzarFlujoFinalizacion._running) return;
            lanzarFlujoFinalizacion._running = true;
            if (typeof window.__civeEnableSwalLock === 'function') window.__civeEnableSwalLock();

            // Esperar a que la página termine de mostrar su propio Swal (p.ej. "Guardado con éxito")
            try {
                await waitForSwalIdle({maxWaitMs: 15000, settleMs: 300, tickMs: 150});
            } catch (_) {
            }

            Swal.fire({
                title: '¿Dilataste al paciente?',
                icon: 'question',
                showDenyButton: true,
                confirmButtonText: '✅ Sí',
                denyButtonText: '🆗 No',
                reverseButtons: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                civeBypass: true
            }).then((result) => {
                if (!result.isConfirmed && !result.isDenied) {
                    if (typeof window.__civeDisableSwalLock === 'function') window.__civeDisableSwalLock();
                    lanzarFlujoFinalizacion._running = false;
                    return; // usuario cerró sin elegir; no interrumpimos nada
                }

                const estado = result.isConfirmed
                    ? ESTADOS.TERMINADO_DILATAR
                    : ESTADOS.TERMINADO_SIN_DILATAR;

                Swal.fire({
                    title: '¿Finalizar atención?',
                    text: 'Se registrará como terminada.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '✅ Finalizar',
                    cancelButtonText: '🔙 Cancelar',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    civeBypass: true
                }).then((finalResult) => {
                    if (!finalResult.isConfirmed) {
                        if (typeof window.__civeDisableSwalLock === 'function') window.__civeDisableSwalLock();
                        Swal.fire('Atención en curso', 'Puedes seguir trabajando.', 'info');
                        lanzarFlujoFinalizacion._running = false;
                        return;
                    }

                    Swal.fire({
                        title: 'Enviando...',
                        text: 'Actualizando estado del paciente.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        },
                        civeBypass: true
                    });

                    sendBg('proyeccionesPost', {
                        path: endpointPath,
                        body: {form_id: paciente.form_id, estado},
                    })
                        .then((data) => {
                            if (typeof Swal !== 'undefined' && Swal.isVisible && Swal.isVisible()) {
                                try {
                                    Swal.close();
                                } catch (_) {
                                }
                            }
                            if (data && data.success) {
                                const mensajeFinal = estado === ESTADOS.TERMINADO_DILATAR
                                    ? 'Consulta finalizada y se procederá a dilatar al paciente.'
                                    : 'Consulta finalizada sin dilatación.';
                                // Persistencia local del estado; NO tocamos el submit de la página
                                localStorage.setItem(`estado_atencion_${paciente.form_id}`, 'finalizado');
                                sessionStorage.removeItem(`prompt_iniciar_${paciente.form_id}`);

                                Swal.fire({
                                    title: 'Éxito',
                                    text: mensajeFinal,
                                    icon: 'success',
                                    civeBypass: true
                                }).then(() => {
                                    if (typeof window.__civeDisableSwalLock === 'function') window.__civeDisableSwalLock();
                                    lanzarFlujoFinalizacion._running = false;
                                });
                            } else {
                                const msg = (data && (data.message || data.error)) ? (data.message || data.error) : 'No se pudo enviar la información correctamente.';
                                Swal.fire({title: 'Error', text: msg, icon: 'error', civeBypass: true});
                                if (typeof window.__civeDisableSwalLock === 'function') window.__civeDisableSwalLock();
                                lanzarFlujoFinalizacion._running = false;
                            }
                        })
                        .catch((error) => {
                            if (typeof Swal !== 'undefined' && Swal.isVisible && Swal.isVisible()) {
                                try {
                                    Swal.close();
                                } catch (_) {
                                }
                            }
                            let msg = 'Fallo de red';
                            if (error && error.name === 'AbortError') {
                                msg = '⏱️ El servidor tardó demasiado en responder.';
                            } else if (error && /Failed to fetch/i.test(error.message || '')) {
                                msg = '🌐 Error de red: no se pudo contactar al servidor.';
                            } else if (error && /HTTP\s+\d+/.test(error.message || '')) {
                                msg = `💥 Error del servidor: ${error.message}`;
                            } else if (error && error.message) {
                                msg = error.message;
                            }
                            Swal.fire({title: 'Error', text: msg, icon: 'error', civeBypass: true});
                            if (typeof window.__civeDisableSwalLock === 'function') window.__civeDisableSwalLock();
                            console.error('Error:', error);
                            lanzarFlujoFinalizacion._running = false;
                        });
                });
            });
        };

        // 1) Si la página define su propio guardado, lo envolvemos para ejecutar nuestro flujo DESPUÉS
        try {
            if (typeof window.guardarTodaLaConsulta === 'function' && !window.__patchedGuardarConsulta) {
                const originalGuardar = window.guardarTodaLaConsulta;
                window.guardarTodaLaConsulta = function patchedGuardarTodaLaConsulta(...args) {
                    const ret = originalGuardar.apply(this, args);
                    // Ejecutar nuestro flujo después de que el guardado propio haya corrido
                    Promise.resolve(ret).then(() => setTimeout(lanzarFlujoFinalizacion, 0));
                    return ret;
                };
                window.__patchedGuardarConsulta = true;
                return; // listo: no necesitamos listeners adicionales
            }
        } catch (_) { /* si falla el patch, seguimos con fallback */
        }

        // 2) Fallback: si no existe esa función, agregamos un listener NO intrusivo al botón
        if (botonGuardar) {
            botonGuardar.addEventListener('click', function () {
                // No prevenimos nada; dejamos que la página guarde normalmente
                setTimeout(lanzarFlujoFinalizacion, 0); // siguiente tick, después de los handlers nativos
            }, false);
        }
    }
})();
