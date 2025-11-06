$(function () {
    "use strict";

    // === Helpers & Styles ===
    const normalizar = s => (s || '')
        .normalize('NFD').replace(/\p{Diacritic}/gu, '')
        .toLowerCase().trim();

    function getCodigoPorAfiliacion(afiliacionRaw, insumo) {
        const a = normalizar(afiliacionRaw);

        if (a.includes('issfa') && insumo.codigo_issfa) return insumo.codigo_issfa;
        if (a.includes('isspol') && insumo.codigo_isspol) return insumo.codigo_isspol;
        if (a.includes('msp') && insumo.codigo_msp) return insumo.codigo_msp;

        const palabrasIESS = [
            'contribuyente voluntario', 'conyuge', 'conyuge pensionista', 'seguro campesino',
            'seguro campesino jubilado', 'seguro general', 'seguro general jubilado',
            'seguro general por montepío', 'seguro general tiempo parcial', 'iess'
        ];
        if (palabrasIESS.some(p => a.includes(p)) && insumo.codigo_iess) return insumo.codigo_iess;

        // Por defecto: ISSPOL
        return insumo.codigo_isspol || '';
    }

    // Badge de estado de autosave + inyección de estilos de filas
    function injectWizardStyles() {
        if (!document.getElementById('wizardInjectedStyles')) {
            const style = document.createElement('style');
            style.id = 'wizardInjectedStyles';
            style.textContent = `
                .fila-equipos{background-color:#cce5ff;}
                .fila-anestesia{background-color:#f8d7da;}
                .fila-quirurgicos{background-color:#d4edda;}
                .fila-anestesiologo{background-color:#f8d7da;}
                .fila-cirujano{background-color:#cce5ff;}
                .fila-asistente{background-color:#d4edda;}
                #autosaveEstado{
                    position:fixed; right:12px; bottom:12px; z-index:9999;
                    background:#222; color:#fff; padding:6px 10px; border-radius:6px;
                    font-size:12px; opacity:.85;
                }`;
            document.head.appendChild(style);
        }
        if (!document.getElementById('autosaveEstado')) {
            const badge = document.createElement('span');
            badge.id = 'autosaveEstado';
            badge.textContent = '';
            document.body.appendChild(badge);
        }
    }

    // === Detectores de vacío para entradas persistidas ===
    function isInsumosVacioValue(raw) {
        if (raw === undefined || raw === null) return true;
        if (typeof raw !== 'string') return true;
        const v = raw.trim();
        if (v === '' || v.toUpperCase() === 'NULL') return true;
        try {
            const parsed = JSON.parse(v);
            if (Array.isArray(parsed)) {
                // Caso legado: "[]"
                return parsed.length === 0;
            }
            if (parsed && typeof parsed === 'object') {
                const keys = ['equipos', 'anestesia', 'quirurgicos'];
                return keys.every(k => Array.isArray(parsed[k]) && parsed[k].length === 0);
            }
            return true;
        } catch (e) {
            // Si no es JSON válido, trátalo como vacío para evitar bloquear plantillas
            return true;
        }
    }

    function isMedicamentosVacioValue(raw) {
        if (raw === undefined || raw === null) return true;
        if (typeof raw !== 'string') return true;
        const v = raw.trim();
        if (v === '' || v.toUpperCase() === 'NULL') return true;
        try {
            const parsed = JSON.parse(v);
            return Array.isArray(parsed) ? parsed.length === 0 : true;
        } catch (e) {
            return true;
        }
    }

    const formIdInput = document.querySelector('input[name="form_id"]');
    const hcNumberInput = document.querySelector('input[name="hc_number"]');
    const autosaveEnabled = !!(formIdInput && hcNumberInput);

    const lastAutosavePayload = {
        insumos: null,
        medicamentos: null
    };

    const debouncedAutosave = debounce(() => {
        if (!autosaveEnabled) return;

        const payload = new FormData();
        payload.append('form_id', formIdInput.value);
        payload.append('hc_number', hcNumberInput.value);

        const insumosValue = $('#insumosInput').val() || '';
        const medicamentosValue = $('#medicamentosInput').val() || '';

        if (insumosValue !== '') payload.append('insumos', insumosValue);
        if (medicamentosValue !== '') payload.append('medicamentos', medicamentosValue);

        autosave(payload);
    }, 1000);

    async function autosave(payload) {
        if (typeof window.csrfToken === 'string' && window.csrfToken) {
            payload.append('_token', window.csrfToken);
        }
        const $badge = $('#autosaveEstado');
        if ($badge.length) $badge.text('Guardando…');
        for (let intento = 1; intento <= 3; intento++) {
            try {
                const resp = await fetch('/cirugias/wizard/autosave', {
                    method: 'POST',
                    body: payload,
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                const text = await resp.text();
                let data = {};
                if (text) {
                    try {
                        data = JSON.parse(text);
                    } catch (e) { /* no-op */
                    }
                }
                if (!resp.ok || data.success === false) throw new Error(data.message || 'Error en autosave');
                if ($badge.length) $badge.text('Guardado ' + new Date().toLocaleTimeString());
                return;
            } catch (e) {
                if (intento === 3) {
                    console.error('❌ Autosave falló', e);
                    if ($badge.length) $badge.text('Error al guardar');
                } else {
                    await new Promise(r => setTimeout(r, intento * 1000));
                }
            }
        }
    }

    function scheduleAutosave() {
        if (!autosaveEnabled) {
            return;
        }

        const currentPayload = {
            insumos: $('#insumosInput').val() || '',
            medicamentos: $('#medicamentosInput').val() || ''
        };

        if (
            currentPayload.insumos === lastAutosavePayload.insumos &&
            currentPayload.medicamentos === lastAutosavePayload.medicamentos
        ) {
            return;
        }

        lastAutosavePayload.insumos = currentPayload.insumos;
        lastAutosavePayload.medicamentos = currentPayload.medicamentos;

        debouncedAutosave();
    }

    inicializarInsumos();
    injectWizardStyles();
    inicializarMedicamentos();

    // Intentar cargar plantillas por defecto si no hay datos persistidos (incluye casos "[]")
    try {
        const insumosRaw = ($('#insumosInput').val() || '').trim();
        const medsRaw = ($('#medicamentosInput').val() || '').trim();

        if (isInsumosVacioValue(insumosRaw) && typeof window.cargarPlantillaInsumos === 'function') {
            window.cargarPlantillaInsumos(); // opcionalmente acepta procedimientoId si tu implementación lo requiere
        }
        if (isMedicamentosVacioValue(medsRaw) && typeof window.cargarPlantillaMedicamentos === 'function') {
            window.cargarPlantillaMedicamentos();
        }
    } catch (e) {
        console.warn('No se pudo evaluar/cargar plantillas por defecto:', e);
    }

    function inicializarInsumos() {
        var afiliacion = afiliacionCirugia;
        var insumosDisponibles = insumosDisponiblesJSON;
        var categorias = categoriasInsumos;

        var table = $('#insumosTable').DataTable({
            paging: false, searching: false, ordering: false, info: false, autoWidth: false
        });
        $('#insumosTable').editableTableWidget().on('change', function () {
            actualizarInsumos();
        });
        // Asegurar que todas las filas existentes marquen la celda de cantidad
        $('#insumosTable tbody tr').each(function () {
            const $td = $(this).find('td').eq(2);
            if ($td.length && !$td.hasClass('cantidad-cell')) {
                $td.addClass('cantidad-cell');
            }
        });

        $('#insumosTable').on('click', '.delete-btn', function () {
            table.row($(this).closest('tr')).remove().draw(false);
            actualizarInsumos();
        });

        $('#insumosTable').on('click', '.add-row-btn', function (event) {
            event.preventDefault();

            var newRowHtml = [
                '<select class="form-control categoria-select" name="categoria">' + categoriaOptionsHTML + '</select>',
                '<select class="form-control nombre-select" name="id"><option value="">Seleccione una categoría primero</option></select>',
                '<td class="cantidad-cell" contenteditable="true">1</td>',
                '<button class="delete-btn btn btn-danger"><i class="fa fa-minus"></i></button> <button class="add-row-btn btn btn-success"><i class="fa fa-plus"></i></button>'
            ];

            var currentRow = $(this).closest('tr');
            var newRow = table.row.add(newRowHtml).draw(false).node();
            $(newRow).insertAfter(currentRow);

            actualizarInsumos();
        });

        $('#insumosTable').on('change', '.categoria-select', function () {
            var categoriaSeleccionada = $(this).val();
            var nombreSelect = $(this).closest('tr').find('.nombre-select');
            nombreSelect.empty();

            if (categoriaSeleccionada && insumosDisponibles[categoriaSeleccionada]) {
                var idsAgregados = [];
                $.each(insumosDisponibles[categoriaSeleccionada], function (id, insumo) {
                    if (!idsAgregados.includes(id)) {
                        nombreSelect.append('<option value="' + id + '">' + insumo.nombre + '</option>');
                        idsAgregados.push(id);
                    }
                });

                var idActual = nombreSelect.data('id');
                if (idActual) {
                    nombreSelect.val(idActual);
                }
            } else {
                nombreSelect.append('<option value="">Seleccione una categoría primero</option>');
            }

            actualizarInsumos();
        });

        function pintarFilas() {
            $('#insumosTable tbody tr').each(function () {
                const categoria = $(this).find('select.categoria-select').val();
                $(this).removeClass('fila-equipos fila-anestesia fila-quirurgicos');
                if (categoria === 'equipos') $(this).addClass('fila-equipos');
                else if (categoria === 'anestesia') $(this).addClass('fila-anestesia');
                else if (categoria === 'quirurgicos') $(this).addClass('fila-quirurgicos');
            });
        }

        function actualizarInsumos() {
            const acumulado = {equipos: {}, anestesia: {}, quirurgicos: {}};

            function pushInsumo(cat, id, nombre, cantidad, codigo) {
                if (!acumulado[cat][id]) {
                    acumulado[cat][id] = {id: Number(id), nombre, cantidad: 0};
                    if (codigo) acumulado[cat][id].codigo = codigo;
                }
                acumulado[cat][id].cantidad += cantidad;
            }

            $('#insumosTable tbody tr').each(function () {
                const categoria = (($(this).find('.categoria-select').val() || '').toLowerCase());
                const id = $(this).find('.nombre-select').val();
                const nombre = $(this).find('.nombre-select option:selected').text().trim();
                // Fallback: si la fila aún no tenía la clase, usar la tercera celda
                const $cantidadCell = $(this).find('.cantidad-cell');
                const cantidadText = ($cantidadCell.length ? $cantidadCell.text() : $(this).find('td').eq(2).text()).trim();
                const cantidad = Math.max(0, Number(cantidadText) || 0);
                if (!categoria || !id || cantidad <= 0) return;

                const pool = (insumosDisponibles[categoria] || {});
                const insumo = pool[id];
                if (!insumo) {
                    console.warn('⚠️ Insumo no encontrado', {categoria, id});
                    return;
                }
                const codigo = getCodigoPorAfiliacion(afiliacion, insumo);
                pushInsumo(categoria, id, nombre, cantidad, codigo);
            });

            const insumosObject = {
                equipos: Object.values(acumulado.equipos),
                anestesia: Object.values(acumulado.anestesia),
                quirurgicos: Object.values(acumulado.quirurgicos)
            };

            const isEmptyInsumos =
                insumosObject.equipos.length === 0 &&
                insumosObject.anestesia.length === 0 &&
                insumosObject.quirurgicos.length === 0;

            if (isEmptyInsumos) {
                $('#insumosInput').val('NULL');
                console.log("✅ JSON insumos: NULL (sin datos)");
            } else {
                $('#insumosInput').val(JSON.stringify(insumosObject));
                console.log("✅ JSON insumos:", insumosObject);
            }
            scheduleAutosave();
        }

        function cambiarColorFilaInsumos() {
            $('#insumosTable tbody tr').each(function () {
                $(this).removeClass('fila-equipos fila-anestesia fila-quirurgicos');
                const categoria = $(this).find('select.categoria-select').val();
                if (categoria === 'equipos') $(this).addClass('fila-equipos');
                else if (categoria === 'anestesia') $(this).addClass('fila-anestesia');
                else if (categoria === 'quirurgicos') $(this).addClass('fila-quirurgicos');
            });
        }

        // Solo números en cantidad
        $('#insumosTable').on('input', '.cantidad-cell', function () {
            const limpio = this.innerText.replace(/[^\d]/g, '');
            if (this.innerText !== limpio) this.innerText = limpio;
        });

        function refrescarInsumosUI() {
            pintarFilas();
            cambiarColorFilaInsumos();
            actualizarInsumos();
        }

        $('#insumosTable').on('change blur input', 'select, .cantidad-cell', refrescarInsumosUI);
        refrescarInsumosUI();
    }

    function inicializarMedicamentos() {
        var medicamentosTable = $('#medicamentosTable').DataTable({
            paging: false,
            searching: false,
            ordering: false,
            info: false,
            autoWidth: false
        });

        injectWizardStyles();

        $('#medicamentosTable').on('click', '.delete-btn', function () {
            medicamentosTable.row($(this).parents('tr')).remove().draw();
            actualizarMedicamentos();
        });

        $('#medicamentosTable').on('click', '.add-row-btn', function (e) {
            e.preventDefault();

            const newRow = $(
                '<tr>' +
                '<td><select class="form-control medicamento-select" name="medicamento[]">' + medicamentoOptionsHTML + '</select></td>' +
                '<td contenteditable="true"></td>' +
                '<td contenteditable="true"></td>' +
                '<td><select class="form-control via-select" name="via_administracion[]">' + viaOptionsHTML + '</select></td>' +
                '<td><select class="form-control responsable-select" name="responsable[]">' + responsableOptionsHTML + '</select></td>' +
                '<td><button class="delete-btn btn btn-danger"><i class="fa fa-minus"></i></button> <button class="add-row-btn btn btn-success"><i class="fa fa-plus"></i></button></td>' +
                '</tr>'
            );
            $(this).closest('tr').after(newRow);
            actualizarMedicamentos();
        });

        function actualizarMedicamentos() {
            var medicamentosArray = [];
            $('#medicamentosTable tbody tr').each(function () {
                const medicamentoId = $(this).find('select[name="medicamento[]"]').val();
                const medicamentoNombre = $(this).find('select[name="medicamento[]"] option:selected').text();
                const dosis = $(this).find('td:eq(1)').text().trim();
                const frecuencia = $(this).find('td:eq(2)').text().trim();
                const via = $(this).find('select[name="via_administracion[]"]').val();
                const responsable = $(this).find('select[name="responsable[]"]').val();

                if (medicamentoId || dosis || frecuencia || via || responsable) {
                    medicamentosArray.push({
                        id: medicamentoId,
                        medicamento: medicamentoNombre,
                        dosis: dosis,
                        frecuencia: frecuencia,
                        via_administracion: via,
                        responsable: responsable
                    });
                }
            });
            $('#medicamentosInput').val(JSON.stringify(medicamentosArray));
            console.log("✅ JSON medicamentos:", medicamentosArray);
            scheduleAutosave();
        }

        function cambiarColorFilaMedicamentos() {
            $('#medicamentosTable tbody tr').each(function () {
                const responsable = $(this).find('select[name="responsable[]"]').val();
                $(this).removeClass('fila-anestesiologo fila-cirujano fila-asistente');
                if (responsable === 'Anestesiólogo') $(this).addClass('fila-anestesiologo');
                else if (responsable === 'Cirujano Principal') $(this).addClass('fila-cirujano');
                else if (responsable === 'Asistente') $(this).addClass('fila-asistente');
            });
        }

        $('#medicamentosTable').on('change', 'select[name="responsable[]"]', function () {
            cambiarColorFilaMedicamentos();
            actualizarMedicamentos();
        });

        $('#medicamentosTable').on('input change', 'td[contenteditable="true"], select', function () {
            actualizarMedicamentos();
        });

        cambiarColorFilaMedicamentos();
        actualizarMedicamentos();
    }

    function debounce(fn, delay) {
        let timer = null;
        return function (...args) {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }
});