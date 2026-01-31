// ===== Auditar campos de Consulta/Optometría =====

// === Utils de normalización/limpieza ===
function isSelectPlaceholder(txt) {
    if (!txt) return false;
    return txt.trim().toUpperCase() === 'SELECCIONE';
}

function norm(val) {
    return (val || '').toString().trim();
}

function normOrEmpty(val) {
    const v = norm(val);
    return isSelectPlaceholder(v) ? '' : v;
}

function stripHtml(s) {
    const tmp = document.createElement('div');
    tmp.innerHTML = s || '';
    return (tmp.textContent || '').trim();
}

function cleanText(val) {
    // Quita HTML, normaliza espacios y aplica norma/placeholder
    const plain = stripHtml(val).replace(/\s+/g, ' ').trim();
    return normOrEmpty(plain);
}

function pruneEmpty(obj) {
    Object.keys(obj).forEach(k => (obj[k] === '' || obj[k] === null || obj[k] === undefined) && delete obj[k]);
    return obj;
}

function hasAny(obj, keys) {
    return keys.some(k => !!norm(obj[k]));
}

function pruneEmptyCollections(payload, keys = []) {
    keys.forEach((key) => {
        if (Array.isArray(payload[key]) && payload[key].length === 0) {
            delete payload[key];
        }
    });
}

function findRows(selectors, fallbackSelector = '.multiple-input-list__item') {
    for (const selector of selectors) {
        const rows = document.querySelectorAll(selector);
        if (rows.length) {
            return rows;
        }
    }
    return document.querySelectorAll(fallbackSelector);
}


// ==== Notificación no intrusiva (evita solaparse con los flujos de paciente.js) ====
function notifySwal({icon = 'info', title = '', text = '', timer = 2000}) {
    try {
        // Usar toast compacto para no bloquear; si hay lock activo, dejar que el patch de paciente.js lo encole.
        const Toast = Swal && Swal.mixin ? Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: timer,
            timerProgressBar: true
        }) : null;

        const opts = {icon, title, text /* no civeBypass: respetar lock/cola */};

        if (Toast) {
            // Si el lock está activo o hay un modal visible, deferir un poco; el patch lo encola de ser necesario
            if ((typeof window.__civeSwalLockDepth !== 'undefined' && window.__civeSwalLockDepth > 0) ||
                (typeof Swal !== 'undefined' && Swal.isVisible && Swal.isVisible())) {
                setTimeout(() => Toast.fire(opts), 300);
            } else {
                Toast.fire(opts);
            }
        } else if (typeof Swal !== 'undefined' && Swal.fire) {
            // Fallback a modal estándar (se encola gracias al patch si el lock está activo)
            Swal.fire(opts);
        } else {
            console.log(`${icon === 'success' ? '✅' : icon === 'error' ? '❌' : 'ℹ️'} ${title} - ${text}`);
        }
    } catch (e) {
        console.log(`${icon === 'success' ? '✅' : icon === 'error' ? '❌' : 'ℹ️'} ${title} - ${text}`);
    }
}

// Función para extraer datos del div y enviar al servidor

async function extraerDatosYEnviar() {
    const btnGuardar = document.getElementById('interconsulta-btn-guardar');
    if (btnGuardar) btnGuardar.disabled = true;

    // Inicializar el objeto data
    const data = {};

    // Determinar el URL según el tipo de formato
    const isProtocoloQuirurgico = document.querySelector('#consultasubsecuente-membrete') !== null;
    const apiPath = isProtocoloQuirurgico ? '/protocolos/guardar.php' : '/consultas/guardar.php';

    if (isProtocoloQuirurgico) {
        // Extraer datos para protocolo quirúrgico
        console.log('Protocolo Quirúrgico detectado. Extrayendo datos...');

        const div = document.querySelector('.media-body.responsive');

        if (!div) {
            console.warn('No se encontró el div con los datos del paciente.');
            return;
        }

        // Datos básicos del paciente
        data.hcNumber = div.querySelector('p:nth-of-type(2)')?.textContent.replace('HC #:', '').trim() || '';
        data.fechaNacimiento = div.querySelector('p:nth-of-type(4)')?.textContent.replace('Fecha de Nacimiento:', '').trim() || '';
        data.sexo = div.querySelector('p:nth-of-type(6)')?.textContent.trim() || '';
        data.celular = div.querySelector('p:nth-of-type(7)')?.textContent.replace('Celular:', '').trim() || '';
        data.ciudad = div.querySelector('p:nth-of-type(8)')?.textContent.trim() || '';

        // Validación básica
        if (!data.hcNumber) {
            console.error('El número de HC es obligatorio.');
            return;
        }

        // Extraer el `idSolicitud` del URL actual
        const urlParams = new URLSearchParams(window.location.search);
        data.form_id = urlParams.get('idSolicitud') || 'N/A';
        data.fechaActual = new Date().toISOString().slice(0, 10);

        // Extraer valores de los textareas
        data.membrete = document.querySelector('#consultasubsecuente-membrete')?.value.trim() || '';
        data.procedimiento_id = document.querySelector('#consultasubsecuente-piepagina')?.value.trim() || '';
        const piePaginaInput = document.querySelector('#consultasubsecuente-piepagina');
        if (!data.procedimiento_id && piePaginaInput) {
            piePaginaInput.style.border = '2px solid red';
            Swal.fire({
                icon: 'warning',
                title: 'Campo obligatorio',
                text: 'El ID del procedimiento (#consultasubsecuente-piepagina) no puede estar vacío.',
                confirmButtonText: 'Aceptar'
            });
            if (btnGuardar) btnGuardar.disabled = false;
            return;
        }
        data.dieresis = document.querySelector('#consultasubsecuente-dieresis')?.value.trim() || '';
        data.exposicion = document.querySelector('#consultasubsecuente-exposicion')?.value.trim() || '';
        data.hallazgo = document.querySelector('#consultasubsecuente-hallazgo')?.value.trim() || '';
        data.operatorio = document.querySelector('#consultasubsecuente-operatorio')?.value.trim() || '';
        data.complicaciones_operatorio = document.querySelector('#consultasubsecuente-complicacionesoperatorio')?.value.trim() || '';
        data.datos_cirugia = document.querySelector('#consultasubsecuente-datoscirugia')?.value.trim() || '';

        // Extraer lateralidad
        data.lateralidad = document.querySelector('.list-cell__lateralidadProcedimiento select')?.selectedOptions[0]?.textContent.trim() || '';

        // Extraer fechas y horas
        data.fechaInicio = document.querySelector('#consultasubsecuente-fecha_inicio')?.value || '';
        data.horaInicio = document.querySelector('#consultasubsecuente-horainicio')?.value || '';
        data.fechaFin = document.querySelector('#consultasubsecuente-fecha_fin')?.value || '';
        data.horaFin = document.querySelector('#consultasubsecuente-horafin')?.value || '';

        // Extraer tipo de anestesia
        data.tipoAnestesia = document.querySelector('#consultasubsecuente-anestesia_id')?.selectedOptions[0]?.textContent || '';

        // Recopilar datos del protocolo
        const staffRows = findRows([
            '#trabajadorprotocolo-input-subsecuente .multiple-input-list__item',
            '#trabajadorprotocolo-input .multiple-input-list__item'
        ]);

        staffRows.forEach((item) => {
            const funcion = item.querySelector('.list-cell__funcion select')?.selectedOptions[0]?.textContent.trim() || '';
            const doctor = item.querySelector('.list-cell__doctor select')?.selectedOptions[0]?.textContent.trim() || '';

            // Asignar los valores con los nombres correctos según lo esperado en el backend
            if (funcion && doctor) {
                switch (funcion.toLowerCase()) {
                    case 'cirujano 1':
                        data.cirujano_1 = doctor;
                        break;
                    case 'cirujano 2':
                        data.cirujano_2 = doctor;
                        break;
                    case 'instrumentista':
                        data.instrumentista = doctor;
                        break;
                    case 'circulante':
                        data.circulante = doctor;
                        break;
                    case 'anestesiologo':
                        data.anestesiologo = doctor;
                        break;
                    case 'ayudante anestesiologo':
                        data.ayudante_anestesiologo = doctor;
                        break;
                    case 'primer ayudante':
                        data.primer_ayudante = doctor;
                        break;
                    case 'segundo ayudante':
                        data.segundo_ayudante = doctor;
                        break;
                    case 'tercer ayudante':
                        data.tercer_ayudante = doctor;
                        break;
                    default:
                        data.otros = doctor;
                }
            }
        });

        // Extraer procedimientos
        data.procedimientos = [];
        const procedureRows = findRows([
            '#procedimientoprotocolo-input-subsecuente .multiple-input-list__item',
            '#procedimientoprotocolo-input .multiple-input-list__item'
        ]);

        procedureRows.forEach((item) => {
            const procInterno = item.querySelector('.list-cell__procInterno select')?.selectedOptions[0]?.textContent.trim() || '';
            if (procInterno) data.procedimientos.push({procInterno});
        });

        // Extraer diagnósticos
        data.diagnosticos = [];
        const diagnosticoRows = findRows([
            '#diagnosticossub11111 .multiple-input-list__item',
            '#diagnosticosconsultaexterna .multiple-input-list__item'
        ]);

        diagnosticoRows.forEach((item) => {
            const idDiagnostico = item.querySelector('.list-cell__idDiagnostico select')?.selectedOptions[0]?.textContent.trim() || '';
            const evidencia = item.querySelector('.list-cell__evidencia .cbx-icon')?.textContent.trim() || '';
            const ojo = item.querySelector('.list-cell__ojo_id select')?.selectedOptions[0]?.textContent.trim() || '';
            const observaciones = item.querySelector('.list-cell__observaciones textarea')?.value.trim() || '';

            if (idDiagnostico) {
                data.diagnosticos.push({idDiagnostico, evidencia, ojo, observaciones});
            }
        });

    } else {
        console.log('Formato de consulta u optometría detectado');

        // Extraer datos para una consulta normal
        const div = document.querySelector('.media-body.responsive');

        if (!div) {
            console.warn('No se encontró el div con los datos del paciente.');
            return;
        }

        // Datos básicos del paciente
        data.hcNumber = div.querySelector('p:nth-of-type(2)')?.textContent.replace('HC #:', '').trim() || '';
        data.fechaNacimiento = div.querySelector('p:nth-of-type(4)')?.textContent.replace('Fecha de Nacimiento:', '').trim() || '';
        data.sexo = div.querySelector('p:nth-of-type(6)')?.textContent.trim() || '';
        data.celular = div.querySelector('p:nth-of-type(7)')?.textContent.replace('Celular:', '').trim() || '';
        data.ciudad = div.querySelector('p:nth-of-type(8)')?.textContent.trim() || '';

        // Validación básica
        if (!data.hcNumber) {
            console.error('El número de HC es obligatorio.');
            return;
        }

        // Extraer el `idSolicitud` del URL actual
        const urlParams = new URLSearchParams(window.location.search);
        data.form_id = urlParams.get('idSolicitud') || 'N/A';
        data.fechaActual = new Date().toISOString().slice(0, 10);

        data.motivoConsulta = document.querySelector('#consultas-motivoconsulta')?.value.trim() || '';
        data.enfermedadActual = document.querySelector('#consultas-enfermedadactual')?.value.trim() || '';
        data.examenFisico = document.querySelector('#consultas-fisico-0-observacion')?.value.trim() || '';
        data.plan = document.querySelector('#docsolicitudprocedimientos-observacion_consulta')?.value.trim() || '';

        // Extraer diagnósticos de consulta normal
        data.diagnosticos = [];
        document.querySelectorAll('#diagnosticosconsultaexterna .multiple-input-list__item').forEach((item) => {
            const idDiagnostico = item.querySelector('.list-cell__idEnfermedades select')?.selectedOptions[0]?.textContent.trim() || '';
            const ojo = item.querySelector('.list-cell__ojo_id select')?.selectedOptions[0]?.textContent.trim() || '';
            const evidencia = item.querySelector('.list-cell__evidencia input[type="checkbox"]')?.checked ? '1' : '0';

            if (idDiagnostico) {
                data.diagnosticos.push({idDiagnostico, ojo, evidencia});
            }
        });

        // Extraer los exámenes seleccionados
        data.examenes = [];
        document.querySelectorAll('.examendiv, .examendivimagen').forEach(examenDiv => {
            const examenCheckbox = examenDiv.querySelector('input[type="text"]');
            const wrapper = examenDiv.querySelector('.cbx');
            const hasIcon = !!wrapper?.querySelector('.cbx-icon i');
            const isChecked = Boolean(examenCheckbox && examenCheckbox.value === '1') || hasIcon;

            if (isChecked) { // Verificar si el examen está seleccionado
                const examenNombreCompleto = examenDiv.querySelector('.cbx-label').textContent.trim();

                // Descomponer el nombre de forma flexible
                const examenPartes = examenNombreCompleto.split('-');
                if (examenPartes.length >= 2) {
                    const examenCodigo = examenPartes[0].trim(); // Código del examen (número)
                    let examenNombre = examenPartes.slice(1).join('-').trim(); // Nombre del examen
                    let examenLateralidad = '';

                    // Verificar si el nombre contiene la lateralidad (OD, OI, AO)
                    if (examenNombre.includes('(OD)')) {
                        examenLateralidad = 'OD';
                        examenNombre = examenNombre.replace('(OD)', '').trim();
                    } else if (examenNombre.includes('(OI)')) {
                        examenLateralidad = 'OI';
                        examenNombre = examenNombre.replace('(OI)', '').trim();
                    } else if (examenNombre.includes('(AO)')) {
                        examenLateralidad = 'AO';
                        examenNombre = examenNombre.replace('(AO)', '').trim();
                    }

                    // Almacenar el examen con código, nombre y lateralidad
                    data.examenes.push({
                        codigo: examenCodigo, nombre: examenNombre, lateralidad: examenLateralidad
                    });
                } else {
                    console.error(`No se pudo descomponer el nombre del examen: ${examenNombreCompleto}`);
                }
            }
        });

        console.log('Exámenes recopilados:', data.examenes);

        // ====== RECETAS INTERNAS (normalizadas y validadas) ======
        data.recetas = [];
        document.querySelectorAll('#recetas-input .multiple-input-list__item').forEach((row) => {
            const idRecetas = norm(row.querySelector('[id$="-idrecetas"][name$="[idRecetas]"]')?.value || '');
            const estadoRecetaid = norm(row.querySelector('[id$="-estadorecetaid"][name$="[estadoRecetaid]"]')?.value || '');

            const productoSel = row.querySelector('[id$="-producto_id"][name$="[producto_id]"]');
            const producto_id = norm(productoSel?.value || '');
            let producto_text = cleanText(
                (productoSel?.selectedOptions?.[0]?.innerHTML) ||
                (productoSel?.selectedOptions?.[0]?.textContent) ||
                ''
            );

            const viasSel = row.querySelector('[id$="-vias"][name$="[vias]"]');
            const vias = normOrEmpty(viasSel?.value || '');
            const vias_text = cleanText(
                (viasSel?.selectedOptions?.[0]?.innerHTML) ||
                (viasSel?.selectedOptions?.[0]?.textContent) ||
                ''
            );

            const dosis = norm(row.querySelector('[id$="-dosis"][name$="[dosis]"]')?.value || '');

            const unidadSel = row.querySelector('[id$="-unidad_id"][name$="[unidad_id]"]');
            const unidad_id = normOrEmpty(unidadSel?.value || '');
            const unidad_text = cleanText(
                (unidadSel?.selectedOptions?.[0]?.innerHTML) ||
                (unidadSel?.selectedOptions?.[0]?.textContent) ||
                ''
            );

            const pautaSel = row.querySelector('[id$="-pauta"][name$="[pauta]"]');
            const pauta = normOrEmpty(pautaSel?.value || '');
            const pauta_text = cleanText(
                (pautaSel?.selectedOptions?.[0]?.innerHTML) ||
                (pautaSel?.selectedOptions?.[0]?.textContent) ||
                ''
            );

            const cantidad = norm(row.querySelector('[id$="-cantidad"][name$="[cantidad]"]')?.value || '');
            const total_farmacia = norm(row.querySelector('[id$="-total_farmacia"][name$="[total_farmacia]"]')?.value || '');
            const observaciones = norm(row.querySelector('[id$="-observaciones"][name$="[observaciones]"]')?.value || '');
            // productoDespachado_id is not sent in new schema

            const receta = {
                idRecetas,
                estadoRecetaid,
                // Enviar textos en lugar de IDs porque la BD local no maneja esos IDs
                producto: producto_text, // e.g. "ACETATO DE FLUOROMETOLONA 0.1% (FLUMETOL NF OFTENO)"
                vias: vias_text,         // e.g. "TÓPICA"
                dosis,
                unidad: unidad_text,     // e.g. "GOTAS", "ML"
                pauta: pauta_text,       // e.g. "CADA 8 HORAS"
                cantidad,
                total_farmacia,
                observaciones
            };

            // Validación mínima:
            // 1) sin producto (texto) -> descartar fila
            if (!receta.producto) return;
            // 2) si hay producto, exigir al menos dosis (el resto se envía si no está vacío)
            // (si quisieras forzar unidad_id/pauta/cantidad, aquí harías return si faltan)

            data.recetas.push(pruneEmpty(receta));
        });

        // ===== Extraer PIO (Presión Intraocular) si existe la tabla dinámica =====
        data.pio = [];
        const pioRows = document.querySelectorAll('#seriales-input-pio .multiple-input-list__item');
        pioRows.forEach((row, idx) => {
            const tonometroSel = row.querySelector('[id$="-po_tonometro_id"]');
            const tonometro = tonometroSel ? (tonometroSel.selectedOptions?.[0]?.textContent?.trim() || tonometroSel.value || '') : '';
            const od = row.querySelector('[id$="-presionintraocularod"]')?.value?.trim() || '';
            const oi = row.querySelector('[id$="-presionintraocularoi"]')?.value?.trim() || '';
            const patologico = (() => {
                const cb = row.querySelector('[id$="-po_patologico"][type="checkbox"]');
                return cb ? (cb.checked ? '1' : '0') : (row.querySelector('[name$="[po_patologico]"]')?.value || '');
            })();
            const horaToma = row.querySelector('[id$="-po_hora"]')?.value?.trim() || '';
            const horaFin = row.querySelector('[id$="-hora_fin"]')?.value?.trim() || '';
            const observacion = row.querySelector('[id$="-po_observacion"]')?.value?.trim() || '';

            // Opcional: ID interno si existe
            const registroId = row.querySelector('[id$="-id"][type="hidden"]')?.value || '';

            const payload = {
                id: registroId,
                tonometro,
                od,
                oi,
                patologico,
                hora_toma: horaToma,
                hora_fin: horaFin,
                observacion
            };

            const hasData = hasAny(payload, [
                'tonometro',
                'od',
                'oi',
                'patologico',
                'hora_toma',
                'hora_fin',
                'observacion',
                'id',
            ]);

            if (hasData) {
                data.pio.push(payload);
            }
        });

        // Remover campos no requeridos por el backend (por seguridad)
        delete data.recetas_externas;
        delete data.estadoEnfermedad;
        delete data.antecedente_alergico;
        delete data.vigenciaReceta;
        delete data.signos_alarma;
        delete data.recomen_no_farmaco;
        delete data.signos_alarma_externa;
        delete data.recomen_no_farmaco_externa;
        // Puedes agregar otros campos relevantes de la consulta normal aquí
    }

    pruneEmptyCollections(data, ['diagnosticos', 'examenes', 'recetas', 'pio', 'procedimientos']);

    // Enviar los datos al backend
    console.log('Datos a enviar:', data);
    try {
        const resultado = await window.CiveApiClient.post(apiPath, {
            body: data,
        });

        console.group('%c📤 Envío a API', 'color: green; font-weight: bold;');
        console.log('✅ Datos enviados:', data);
        console.log('📥 Respuesta normalizada:', resultado);
        console.groupEnd();

        if (resultado?.success) {
            notifySwal({
                icon: 'success',
                title: 'Guardado exitoso',
                text: resultado.message || 'Datos guardados correctamente.',
                timer: 2000
            });
        } else {
            notifySwal({
                icon: 'error',
                title: 'Error al guardar',
                text: resultado?.message || 'Ha ocurrido un error inesperado.',
                timer: 3500
            });
        }
    } catch (error) {
        console.error('❌ Error al enviar los datos:', error);
        notifySwal({
            icon: 'error',
            title: 'Error al enviar',
            text: error?.message || 'No se pudo comunicar con el API.',
            timer: 3500
        });
    } finally {
        if (btnGuardar) btnGuardar.disabled = false;
    }
}
