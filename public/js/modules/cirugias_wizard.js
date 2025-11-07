$(function () {
    "use strict";

    const formIdInput = document.querySelector('input[name="form_id"]');
    const hcNumberInput = document.querySelector('input[name="hc_number"]');
    const autosaveEnabled = !!(formIdInput && hcNumberInput);

    const lastAutosavePayload = {
        insumos: null,
        medicamentos: null
    };

    const debouncedAutosave = debounce(() => {
        if (!autosaveEnabled) {
            return;
        }

        const payload = new FormData();
        payload.append('form_id', formIdInput.value);
        payload.append('hc_number', hcNumberInput.value);

        const insumosValue = $('#insumosInput').val() || '';
        const medicamentosValue = $('#medicamentosInput').val() || '';

        if (insumosValue !== '') {
            payload.append('insumos', insumosValue);
        }

        if (medicamentosValue !== '') {
            payload.append('medicamentos', medicamentosValue);
        }

        fetch('/cirugias/wizard/autosave', {
            method: 'POST',
            body: payload,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(async (response) => {
                const text = await response.text();
                let data = {};

                if (text) {
                    try {
                        data = JSON.parse(text);
                    } catch (error) {
                        console.warn('⚠️ Respuesta de autosave no es JSON válido', error, text);
                    }
                }

                if (!response.ok || data.success === false) {
                    const message = data.message || 'No se pudo completar el autosave del protocolo.';
                    throw new Error(message);
                }
            })
            .catch(error => {
                console.error('❌ Error en autosave de protocolo', error);
            });
    }, 1000);

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
    inicializarMedicamentos();

    function inicializarInsumos() {
        var afiliacion = afiliacionCirugia;
        var insumosDisponibles = insumosDisponiblesJSON;
        var categorias = categoriasInsumos;

        var table = $('#insumosTable').DataTable({"paging": false});
        $('#insumosTable').editableTableWidget().on('change', function () {
            actualizarInsumos();
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
                '<td contenteditable="true">1</td>',
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
                $(this).removeClass('categoria-equipos categoria-anestesia categoria-quirurgicos');
                switch (categoria) {
                    case 'equipos':
                        $(this).addClass('categoria-equipos');
                        break;
                    case 'anestesia':
                        $(this).addClass('categoria-anestesia');
                        break;
                    case 'quirurgicos':
                        $(this).addClass('categoria-quirurgicos');
                        break;
                }
            });
        }

        function actualizarInsumos() {
            const insumosObject = {equipos: [], anestesia: [], quirurgicos: []};

            $('#insumosTable tbody tr').each(function () {
                const categoria = $(this).find('.categoria-select').val().toLowerCase();
                const id = $(this).find('.nombre-select').val();
                const nombre = $(this).find('.nombre-select option:selected').text().trim();
                const cantidad = parseInt($(this).find('td:eq(2)').text()) || 0;

                if (categoria && id && cantidad > 0) {
                    const insumo = insumosDisponibles[categoria][id];
                    let codigo = "";

                    if (afiliacion.includes('issfa') && insumo.codigo_issfa) {
                        codigo = insumo.codigo_issfa;
                    } else if (afiliacion.includes('isspol') && insumo.codigo_isspol) {
                        codigo = insumo.codigo_isspol;
                    } else if (afiliacion.includes('msp') && insumo.codigo_msp) {
                        codigo = insumo.codigo_msp;
                    } else if ([
                        'contribuyente voluntario', 'conyuge', 'conyuge pensionista', 'seguro campesino',
                        'seguro campesino jubilado', 'seguro general', 'seguro general jubilado',
                        'seguro general por montepío', 'seguro general tiempo parcial', 'iess'
                    ].some(iess => afiliacion.includes(iess)) && insumo.codigo_iess) {
                        codigo = insumo.codigo_iess;
                    }

                    const obj = {id: parseInt(id), nombre, cantidad};
                    if (codigo) obj.codigo = codigo;
                    insumosObject[categoria].push(obj);
                }
            });

            $('#insumosInput').val(JSON.stringify(insumosObject));
            console.log("Actualizado JSON insumos con códigos:", insumosObject);
            scheduleAutosave();
        }

        $('#insumosTable').on('change', 'select', function () {
            actualizarInsumos();
            pintarFilas();
        });
        $('#insumosTable').on('blur', 'td', function () {
            actualizarInsumos();
            pintarFilas();
        });

        actualizarInsumos();
        pintarFilas();
    }

    function inicializarMedicamentos() {
        var medicamentosTable = $('#medicamentosTable').DataTable({paging: false});

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
                $(this).css('background-color', '');
                if (responsable === 'Anestesiólogo') $(this).css('background-color', '#f8d7da');
                else if (responsable === 'Cirujano Principal') $(this).css('background-color', '#cce5ff');
                else if (responsable === 'Asistente') $(this).css('background-color', '#d4edda');
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