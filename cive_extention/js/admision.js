window.inicializarDeteccionModalAdmision = () => {
    console.log("🟢 Esperando clic en el botón + para cargar el modal...");

    document.querySelectorAll('a[role="modal-remote"] .glyphicon-plus').forEach(iconoPlus => {
        const btnPlus = iconoPlus.closest('a');
        if (btnPlus) {
            btnPlus.addEventListener("click", (e) => {
                console.log("➕ Botón plus clickeado. Esperando modal...");

                let intentos = 0;
                const maxIntentos = 10;

                const esperarModal = setInterval(() => {
                    const modal = document.querySelector('.modal.show, .modal[style*="display: block"], .modal[aria-hidden="false"]');

                    if (modal) {
                        clearInterval(esperarModal);
                        console.log("✅ Modal detectado. Inicializando lógica...");

                        // Ejecutar tus funciones solo una vez que el modal esté visible
                        inicializarFormularioAdmision();

                    } else {
                        intentos++;
                        if (intentos >= maxIntentos) {
                            console.warn("❌ No se detectó el modal después de varios intentos.");
                            clearInterval(esperarModal);
                        }
                    }
                }, 500);
            });
        }
    });
};

// Ejecutar al cargar la página
window.addEventListener('DOMContentLoaded', () => {
    inicializarDeteccionModalAdmision();
    detectarConfirmacionAsistencia();
});

// Inicializamos modalData con los campos del formulario principal y del modal
let modalData = {
    sede: '',
    sedeId: '',
    area: '',
    areaId: '',
    afiliacion: '',
    afiliacionId: '',
    parentesco: '',
    parentescoId: '',
    hcNumber: '',
    tipoAfiliacion: '',
    numeroAprobacion: '',
    tipoPlan: '',
    fechaRegistro: '',
    fechaVigencia: '',
    codDerivacion: '',
    numSecuencialDerivacion: '',
    numHistoria: '',
    examenFisico: '',
    observacion: '',
    procedimientos: [],
    diagnosticos: [],
    apellidos: '',
    nombres: '',
    lname: '',
    lname2: '',
    fname: '',
    mname: '',
    sexo: '',
    fechaNacimiento: '',
    estadoCivil: '',
    telefonoMovil: '',
    email: '',
    direccion: '',
    ocupacion: '',
    lugarTrabajo: '',
    ciudad: '',
    parroquia: '',
    nacionalidad: '',
    idProcedencia: '',
    idReferido: '',
    form_id: '',
    idSolicitudPaciente: ''
};

let isSubmitting = false; // Evita envíos múltiples

// Función para registrar cambios en los campos
function registrarCambiosEnCampos() {
    const fields = [{
        id: '#docsolicitudpaciente-sede_id',
        key: 'sede',
        valueKey: 'sedeId',
        type: 'select'
    }, {
        id: '#docsolicitudpaciente-externa_hospitalizacion',
        key: 'area',
        valueKey: 'areaId',
        type: 'select'
    }, {
        id: '#docsolicitudpaciente-afiliacionid',
        key: 'afiliacion',
        valueKey: 'afiliacionId',
        type: 'select'
    }, {
        id: '#docsolicitudpaciente-parentescoid',
        key: 'parentesco',
        valueKey: 'parentescoId',
        type: 'select'
    }, {id: '#numero-historia-clinica', key: 'hcNumber', type: 'input'}, {
        id: '#docsolicitudpaciente-tipoafiliacion',
        key: 'tipoAfiliacion',
        type: 'select'
    }, {
        id: '#docsolicitudpaciente-numeroaprobacion',
        key: 'numeroAprobacion',
        type: 'input'
    }, {
        id: '#docsolicitudpaciente-tipoplan',
        key: 'tipoPlan',
        type: 'select'
    }, {
        id: '#docsolicitudpaciente-fecha_registro',
        key: 'fechaRegistro',
        type: 'input'
    }, {
        id: '#docsolicitudpaciente-fecha_vigencia',
        key: 'fechaVigencia',
        type: 'input'
    }, {
        id: '#docsolicitudpaciente-cod_derivacion',
        key: 'codDerivacion',
        type: 'input'
    }, {
        id: '#docsolicitudpaciente-num_secuencial_derivacion',
        key: 'numSecuencialDerivacion',
        type: 'input'
    }, {
        id: '#docsolicitudpaciente-num_historia',
        key: 'numHistoria',
        type: 'input'
    }, {
        id: '#docsolicitudpaciente-examenfisico',
        key: 'examenFisico',
        type: 'input'
    }, {id: '#docsolicitudpaciente-observacion', key: 'observacion', type: 'input'}, {
        id: '#paciente-apellidos',
        key: 'apellidos',
        type: 'input'
    }, {id: '#paciente-nombres', key: 'nombres', type: 'input'}, {
        id: '#paciente-sexo',
        key: 'sexo',
        type: 'select'
    }, {id: '#paciente-fecha_nac', key: 'fechaNacimiento', type: 'input'}, {
        id: '#paciente-estado_civil_id',
        key: 'estadoCivil',
        type: 'select'
    }, {id: '#paciente-celular', key: 'telefonoMovil', type: 'input'}, {
        id: '#paciente-email',
        key: 'email',
        type: 'input'
    }, {id: '#paciente-direccion', key: 'direccion', type: 'input'}, {
        id: '#paciente-ocupacion',
        key: 'ocupacion',
        type: 'input'
    }, {id: '#paciente-lugar_trabajo', key: 'lugarTrabajo', type: 'input'}, {
        id: '#paciente-ciudad_id',
        key: 'ciudad',
        type: 'select'
    }, {id: '#paciente-parroquia_id', key: 'parroquia', type: 'select'}, {
        id: '#paciente-pais_id',
        key: 'nacionalidad',
        type: 'select'
    }, {id: '#paciente-id_procedencia', key: 'idProcedencia', type: 'select'}, {
        id: '#paciente-referido_id',
        key: 'idReferido',
        type: 'select'
    }];

    fields.forEach(({id, key, type, valueKey}) => {
        const field = document.querySelector(id);
        if (field) {
            if (type === 'select') {
                modalData[key] = field.selectedOptions[0]?.textContent?.trim() || '';
                if (valueKey) modalData[valueKey] = (field.value ?? '').toString().trim();

                field.addEventListener('change', () => {
                    modalData[key] = field.selectedOptions[0]?.textContent?.trim() || '';
                    if (valueKey) modalData[valueKey] = (field.value ?? '').toString().trim();
                    //console.log(`Campo ${key} actualizado:`, modalData[key]);
                });
            } else if (type === 'input') {
                modalData[key] = field.value.trim() || '';
                field.addEventListener('input', () => {
                    modalData[key] = field.value.trim() || '';
                    //console.log(`Campo ${key} actualizado:`, modalData[key]);
                });
            }
        } else {
            //console.info(`Campo ${key} no encontrado o no es obligatorio.`);
        }
    });
    // Procesar apellidos y nombres en campos separados
    modalData.lname = modalData.apellidos.split(' ')[0] || '';
    modalData.lname2 = modalData.apellidos.split(' ')[1] || '';
    modalData.fname = modalData.nombres.split(' ')[0] || '';
    modalData.mname = modalData.nombres.split(' ')[1] || '';

    console.log('Listeners para cambios en campos relevantes configurados.');
}

// Extraer datos de la tabla de procedimientos
function extractTableRowData() {
    const rows = document.querySelectorAll('.multiple-input-list tbody tr');
    const rowData = [];

    rows.forEach((row) => {
        const getText = (selector) => row.querySelector(selector)?.textContent?.trim() || '';
        const getValueByName = (suffix) => row.querySelector(`[name$="[${suffix}]" i]`)?.value?.toString().trim() || '';

        const procedimiento = getText('.list-cell__Procedimiento .select2-selection__rendered');
        const procedimientoId = getValueByName('Procedimiento');
        const procedimientoAfiliacion = getText('.list-cell__ProcedimientoAfiliacion .select2-selection__rendered');
        const procedimientoAfiliacionId = getValueByName('ProcedimientoAfiliacion');
        const ojo = getText('.list-cell__ojo_id .select2-selection__rendered');
        const ojoId = getValueByName('ojo_id');
        const equipment = getText('.list-cell__equipment_id .select2-selection__rendered');
        const equipmentId = getValueByName('equipment_id');
        const precio = getValueByName('precio');

        const id = getValueByName('id');
        const ingreso = getValueByName('ingreso');
        const precioTarifario = getValueByName('precioTarifario');
        const disable = getValueByName('disable');

        if (procedimiento || procedimientoId || procedimientoAfiliacion || procedimientoAfiliacionId || equipment || equipmentId || precio) {
            rowData.push({
                id,
                procedimiento,
                procedimientoId,
                procedimientoAfiliacion,
                procedimientoAfiliacionId,
                ojo,
                ojoId,
                equipment,
                equipmentId,
                precio,
                ingreso,
                precioTarifario,
                disable,
            });
        }
    });

    if (rowData.length > 0) {
        modalData.procedimientos = rowData;
    } else {
        console.warn('No se encontraron datos válidos en la tabla.');
    }
    console.log('Datos extraídos de la tabla:', rowData);
}

// Extraer datos de diagnósticos
function extractDiagnosticosData() {
    console.group('Extracción de datos de diagnósticos');
    const rows = document.querySelectorAll('#diagnosticosconsultaexterna .multiple-input-list tbody tr');
    const diagnosticosData = [];

    rows.forEach((row, index) => {
        const getText = (selector) => row.querySelector(selector)?.textContent?.trim() || '';
        const getValueByName = (suffix) => row.querySelector(`[name$="[${suffix}]" i]`)?.value?.toString().trim() || '';

        const diagnostico = getText('.list-cell__idEnfermedades .select2-selection__rendered');
        const diagnosticoId = getValueByName('idEnfermedades');
        const ojo = getText('.list-cell__ojo_id .select2-selection__rendered');
        const ojoId = getValueByName('ojo_id');
        const id = getValueByName('id');

        const evidenciaCheckbox = row.querySelector('.list-cell__evidencia input[type="checkbox"]');
        const evidencia = evidenciaCheckbox ? (evidenciaCheckbox.checked ? '1' : '0') : (getValueByName('evidencia') || '0');

        if ((diagnostico || diagnosticoId || ojoId || evidencia === '1') && diagnostico !== 'SELECCIONE') {
            console.log(`Fila ${index}:`, {id, diagnostico, diagnosticoId, ojo, ojoId, evidencia});
            diagnosticosData.push({
                id, diagnostico, diagnosticoId, ojo, ojoId, evidencia,
            });
        }
    });

    if (diagnosticosData.length > 0) {
        modalData.diagnosticos = diagnosticosData;
    } else {
        console.warn('No se encontraron datos válidos en la tabla.');
    }
    console.log('Datos extraídos de la tabla:', diagnosticosData);
}

function attachSaveButtonListener() {
    const modal = document.getElementById('ajaxCrudModal');
    if (modal) {
        const form = modal.querySelector('form');
        window.__civeLastAdmisionForm = form || null;
        if (form && form.id === 'formprodoc1') {
            const botonGuardar = modal.querySelector('.modal-footer .btn-success[type="submit"]');
            if (botonGuardar) {
                // Elimina cualquier listener previo antes de asignar uno nuevo
                botonGuardar.removeEventListener('click', handleSaveButtonClick);
                botonGuardar.addEventListener('click', handleSaveButtonClick);
                console.log('Evento asignado al botón guardar.');
            }
        } else {
            console.log('Formulario no relevante, no se asignará evento al botón guardar.');
        }
    } else {
        console.error('El modal especificado no contiene el botón guardar.');
    }
}

async function handleSaveButtonClick(event) {
    event.preventDefault();

    // 1) Resumen humano (UI)
    extractTableRowData();
    extractDiagnosticosData();

    // 2) Fuente canónica (Network): FormData real
    const form = (event?.target && event.target.closest('form')) ? event.target.closest('form') : (window.__civeLastAdmisionForm || document.querySelector('#ajaxCrudModal form'));

    const sigcenterCapture = capturarPayloadSigCenterDesdeFormulario(form);
    if (sigcenterCapture) {
        console.log('🧾 Payload SigCenter (FormData) capturado:', sigcenterCapture);

        // Completar IDs canónicos desde el action/query
        const qp = sigcenterCapture?.meta?.queryParams || {};
        modalData.form_id = qp.id || modalData.form_id || '';
        modalData.idSolicitudPaciente = qp.idSolicitudPaciente || modalData.idSolicitudPaciente || '';
    } else {
        console.warn('⚠️ No se pudo capturar FormData del formulario del modal.');
    }

    // 3) Enviar híbrido a MedForge
    const ok = await extraerDatosYEnviarDesdeModal(sigcenterCapture);

    // 4) Dejar que SIGCenter guarde (submit real) SOLO si existe form
    // Evitamos bucles: requestSubmit dispara "submit", no "click".
    if (ok && form?.requestSubmit) {
        form.requestSubmit();
    }
}

function descargarDatosComoArchivo(data, nombreArchivo = 'datos_modal.json') {
    const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
    const url = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = nombreArchivo;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// ---- Helpers: capturar payload REAL (FormData) como en Network ----
function getQueryParamsFromUrl(url) {
    try {
        const u = new URL(url, window.location.origin);
        const out = {};
        u.searchParams.forEach((v, k) => {
            out[k] = v;
        });
        return out;
    } catch (e) {
        return {};
    }
}

function formDataToFlatObject(fd) {
    const out = {};
    for (const [key, value] of fd.entries()) {
        // value puede ser File; aquí solo serializamos texto/number.
        const v = (value instanceof File) ? {
            __file: true, name: value.name, type: value.type, size: value.size,
        } : String(value);

        if (Object.prototype.hasOwnProperty.call(out, key)) {
            // convertir a array si hay repetidos
            if (!Array.isArray(out[key])) out[key] = [out[key]];
            out[key].push(v);
        } else {
            out[key] = v;
        }
    }
    return out;
}

function capturarPayloadSigCenterDesdeFormulario(form) {
    if (!form) return null;

    // El request real es multipart/form-data, esto replica exactamente las keys.
    const fd = new FormData(form);
    const flat = formDataToFlatObject(fd);

    // Intentar obtener action real (a veces el action ya incluye id/idSolicitudPaciente)
    const actionUrl = form.getAttribute('action') || '';
    const qp = getQueryParamsFromUrl(actionUrl);

    // Algunos flujos colocan id/idSolicitudPaciente fuera del form (en la URL del POST)
    // Si no vienen en el action, intenta sacarlos del DOM (inputs hidden) o del URL actual.
    const currentQp = getQueryParamsFromUrl(window.location.href);

    const meta = {
        actionUrl, queryParams: qp, currentUrl: window.location.href, currentQueryParams: currentQp,
    };

    // Copia también campos raíz que suelen ir en query en el Network (por si existen en form)
    // pero no los forzamos: los guardamos tal como vengan.

    return {meta, formDataFlat: flat};
}

function mergeModalDataWithSigcenter(modalDataLocal, sigcenterCapture) {
    // modalDataLocal = tu estructura actual
    // sigcenterCapture = { meta, formDataFlat }
    return {
        ...modalDataLocal, __sigcenter: sigcenterCapture,
    };
}

// ---- /Helpers ----

// Extraer y enviar datos desde el modal
async function extraerDatosYEnviarDesdeModal(sigcenterCapture = null) {
    if (isSubmitting) return;
    isSubmitting = true;

    try {
        const payloadFinal = mergeModalDataWithSigcenter(modalData, sigcenterCapture);
        console.log('📦 Payload final (resumen + source):', payloadFinal);

        const result = await window.CiveApiClient.post('/prefactura/guardar.php', {
            body: payloadFinal,
        });

        if (result?.success) {
            console.log('✅ Datos enviados correctamente desde el modal.');
            return true;
        }

        console.error('❌ Error en la API:', result?.message || 'Respuesta no válida del API.');
        return false;
    } catch (error) {
        console.error('❌ Error al enviar los datos desde el modal:', error);
        return false;
    } finally {
        isSubmitting = false;
    }
}

function inicializarFormularioAdmision() {
    registrarCambiosEnCampos(); // Captura en tiempo real lo que se llena
    attachSaveButtonListener(); // La extracción final se hace al hacer clic en guardar
}

function detectarConfirmacionAsistencia() {
    document.querySelectorAll('button[id^="button-confirmar-"]').forEach(boton => {
        boton.addEventListener('click', async (e) => {
            const idTexto = boton.id.replace('button-confirmar-', '');
            const id = parseInt(idTexto, 10);
            if (!isNaN(id)) {
                const icono = boton.querySelector('.glyphicon');
                if (icono && icono.classList.contains('glyphicon-thumbs-down')) {
                    console.log(`🟡 Confirmando llegada para ID: ${id}`);
                    try {
                        const data = await window.CiveApiClient.post('/proyecciones/llegada.php', {
                            body: {form_id: id}, bodyType: 'form',
                        });
                        if (data?.success) {
                            console.log('✅ Confirmación de llegada enviada correctamente.');
                        } else {
                            console.log('❌ Error al confirmar llegada:', data?.message || 'Respuesta no válida del API.');
                        }
                    } catch (error) {
                        console.log('❌ Error al enviar la solicitud de llegada:', error.message || error);
                    }
                } else {
                    console.log(`🔵 Botón clickeado pero el icono no indica llegada (no es thumbs-up). ID: ${id}`);
                }
            }
        });
    });
}