$(function () {
    console.log('⚡ editar_protocolo script loaded');
    console.log('jQuery version:', $.fn.jquery);
    "use strict";

    const operatorioEditor = document.getElementById('operatorio');

    // ---------- CÓDIGOS QUIRÚRGICOS ----------
    function cargarCodigos() {
        if (Array.isArray(codigos)) {
            codigos.forEach(c => {
                if (typeof agregarFilaCodigo === 'function') {
                    agregarFilaCodigo(c.nombre, c.lateralidad, c.selector);
                }
            });
        }
    }

    // ---------- STAFF QUIRÚRGICO ----------
    function cargarStaff() {
        if (Array.isArray(staff)) {
            staff.forEach((miembro, i) => {
                if (typeof agregarFilaStaff === 'function') {
                    agregarFilaStaff(miembro.funcion, miembro.trabajador, miembro.nombre, miembro.selector);
                }
            });
        }
    }

    // Convert editor spans to placeholder string [[ID:...]]
    function getOperatorioValue() {
        let result = '';
        operatorioEditor.childNodes.forEach(node => {
            if (node.nodeType === Node.TEXT_NODE) {
                result += node.textContent;
            } else if (node.nodeType === Node.ELEMENT_NODE && node.classList.contains('tag')) {
                result += `[[ID:${node.dataset.id}]]`;
            } else {
                result += node.textContent;
            }
        });
        return result;
    }

    // Render placeholders [[ID:x]] as spans
    function renderOperatorioPlaceholders(raw) {
        return raw.replace(/\[\[ID:(\d+)\]\]/g, (match, id) => {
            const insumo = listaInsumos.find(i => String(i.id) === id);
            const nombre = insumo ? insumo.nombre : '';
            return `<span class="tag" data-id="${id}">${nombre}</span>&nbsp;`;
        });
    }

    // ---------- MEDICAMENTOS ----------
    var medicamentosTable = $('#medicamentosTable').DataTable({paging: false});

    $('#medicamentosTable').on('click', '.delete-btn', function () {
        medicamentosTable.row($(this).parents('tr')).remove().draw();
        actualizarMedicamentos();
    });

    $('#medicamentosTable').on('click', '.add-row-btn', function (e) {
        e.preventDefault();

        let medicamentoOptions = '';
        opcionesMedicamentos.forEach(function (med) {
            medicamentoOptions += '<option value="' + med.id + '">' + med.medicamento + '</option>';
        });

        let viaOptions = '';
        vias.forEach(function (via) {
            viaOptions += '<option value="' + via + '">' + via + '</option>';
        });

        let responsableOptions = '';
        responsables.forEach(function (r) {
            responsableOptions += '<option value="' + r + '">' + r + '</option>';
        });

        const newRow = $('<tr>' + '<td><select class="form-control medicamento-select" name="medicamento[]">' + medicamentoOptions + '</select></td>' + '<td contenteditable="true"></td>' + '<td contenteditable="true"></td>' + '<td><select class="form-control via-select" name="via_administracion[]">' + viaOptions + '</select></td>' + '<td><select class="form-control responsable-select" name="responsable[]">' + responsableOptions + '</select></td>' + '<td><button class="delete-btn btn btn-danger"><i class="fa fa-minus"></i></button> <button class="add-row-btn btn btn-success"><i class="fa fa-plus"></i></button></td>' + '</tr>');
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
    }

    function cambiarColorFila() {
        $('#medicamentosTable tbody tr').each(function () {
            const responsable = $(this).find('select[name="responsable[]"]').val();
            $(this).css('background-color', '');
            if (responsable === 'Anestesiólogo') $(this).css('background-color', '#f8d7da'); else if (responsable === 'Cirujano Principal') $(this).css('background-color', '#cce5ff'); else if (responsable === 'Asistente') $(this).css('background-color', '#d4edda');
        });
    }

    $('#medicamentosTable').on('change', 'select[name="responsable[]"]', function () {
        cambiarColorFila();
        actualizarMedicamentos();
    });

    $('#medicamentosTable').on('input change', 'td[contenteditable="true"], select', function () {
        actualizarMedicamentos();
    });

    cambiarColorFila();


    // ---------- INSUMOS ----------
    var insumosTable = $('#insumosTable').DataTable({paging: false});
    $('#insumosTable').editableTableWidget();
    // Limpiar filas existentes antes de cargar insumos desde JSON
    insumosTable.clear().draw();

    // Cargar insumos existentes
    var initialInsumosJson = $('#insumosInput').val();
    if (initialInsumosJson) {
        try {
            var initialInsumos = JSON.parse(initialInsumosJson);
            console.log('🔍 initialInsumos parsed:', initialInsumos);
            for (const cat in initialInsumos) {
                console.log(`🔍 Cargando categoría "${cat}" con ${initialInsumos[cat].length} ítems`);
                // Build category options
                var categoriaOptions = '';
                for (const c in insumosDisponibles) {
                    categoriaOptions += `<option value="${c}">${c.replace('_', ' ')}</option>`;
                }
                initialInsumos[cat].forEach(function (item) {
                    console.log('  ➕ Agregando item:', item);
                    // Build name options with selected item
                    var nombreOptions = '';
                    if (insumosDisponibles[cat]) {
                        insumosDisponibles[cat].forEach(function (ins) {
                            var sel = ins.id == item.id ? ' selected' : '';
                            nombreOptions += `<option value="${ins.id}"${sel}>${ins.nombre}</option>`;
                        });
                    }
                    // Add row to table
                    var newRowData = [`<select class="form-control categoria-select" name="categoria">${categoriaOptions}</select>`, `<select class="form-control nombre-select" name="nombre">${nombreOptions}</select>`, `${item.cantidad}`, '<button class="delete-btn btn btn-danger"><i class="fa fa-minus"></i></button> <button class="add-row-btn btn btn-success"><i class="fa fa-plus"></i></button>'];
                    insumosTable.row.add(newRowData).draw(false);
                    // Set the category select value
                    var newRow = insumosTable.row(':last').nodes().to$();
                    newRow.find('select[name="categoria"]').val(cat).trigger('change');
                    newRow.find('td:eq(2)').attr('contenteditable', 'true');
                    console.log('  ✅ Fila agregada para categoría:', cat);
                });
            }
        } catch (e) {
            console.error('Error parsing initial insumos JSON:', e);
        }
    }

    $('#insumosTable').on('click', '.delete-btn', function () {
        insumosTable.row($(this).parents('tr')).remove().draw();
        actualizarInsumos();
    });

    $('#insumosTable').on('click', '.add-row-btn', function (event) {
        event.preventDefault();
        var categoriaOptions = '<option value="">Seleccione categoría</option>';
        for (const cat in insumosDisponibles) {
            categoriaOptions += `<option value="${cat}">${cat.replace('_', ' ')}</option>`;
        }

        var newData = [`<select class="form-control categoria-select" name="categoria">${categoriaOptions}</select>`, '<select class="form-control nombre-select" name="nombre"><option value="">Seleccione una categoría</option></select>', '<td contenteditable="true">1</td>', '<button class="delete-btn btn btn-danger"><i class="fa fa-minus"></i></button> <button class="add-row-btn btn btn-success"><i class="fa fa-plus"></i></button>'];

        const currentRow = $(this).parents('tr');
        const rowIndex = insumosTable.row(currentRow).index();
        insumosTable.row.add(newData).draw(false);
        const newRow = insumosTable.row(rowIndex + 1).nodes().to$();
        newRow.insertAfter(currentRow);
        // Hacer scroll hacia la nueva fila
        $('html, body').animate({
            scrollTop: newRow.offset().top - 100
        }, 300);
        $('#insumosTable').editableTableWidget(); // Re-inicializa para nuevas celdas
        actualizarInsumos();
    });

    $('#insumosTable').on('change', '.categoria-select', function () {
        const categoria = $(this).val();
        const nombreSelect = $(this).closest('tr').find('.nombre-select');
        nombreSelect.empty();
        if (categoria && insumosDisponibles[categoria]) {
            insumosDisponibles[categoria].forEach(insumo => {
                nombreSelect.append(`<option value="${insumo.id}">${insumo.nombre}</option>`);
            });
        } else {
            nombreSelect.append('<option value="">Seleccione una categoría primero</option>');
        }
    }).trigger('change');

    $('#insumosTable tbody tr').each(function () {
        // Handle both singular and array naming, and class selector
        var selectElem = $(this).find('select[name="categoria"], select[name="categoria[]"], select.categoria-select');
        var categoria = '';
        if (selectElem.length) {
            categoria = selectElem.val() || '';
            categoria = categoria.toLowerCase();
        }
        if (categoria === 'equipos') {
            $(this).css('background-color', '#d4edda');
        } else if (categoria === 'anestesia') {
            $(this).css('background-color', '#fff3cd');
        } else if (categoria === 'quirurgicos') {
            $(this).css('background-color', '#cce5ff');
        }
    });

    $('#insumosTable').on('change input', 'td', function () {
        actualizarInsumos();
    });

    window.actualizarInsumos = function () {
        var insumosObject = {
            equipos: [], anestesia: [], quirurgicos: []
        };
        $('#insumosTable tbody tr').each(function () {
            // Handle both singular and array naming, and class selector
            var selectElem = $(this).find('select[name="categoria"], select[name="categoria[]"], select.categoria-select');
            var categoria = '';
            if (selectElem.length) {
                categoria = selectElem.val() || '';
                categoria = categoria.toLowerCase();
            }
            const insumoId = $(this).find('select[name="nombre"]').val();
            const insumoNombre = $(this).find('select[name="nombre"] option:selected').text();
            const cantidad = $(this).find('td:eq(2)').text().trim();
            if (categoria && insumoId && insumoNombre && cantidad) {
                insumosObject[categoria].push({
                    id: insumoId, nombre: insumoNombre, cantidad: parseInt(cantidad)
                });
            }
        });
        const json = JSON.stringify(insumosObject);
        $('#insumosInput').val(json);
        console.log("✅ INSUMOS JSON ACTUALIZADO:", json);
    };

    const rawOperatorio = document.getElementById('operatorioInput').value || '';
    operatorioEditor.innerHTML = renderOperatorioPlaceholders(rawOperatorio);

    // Cargar códigos y staff si existen
    cargarCodigos();
    cargarStaff();

    // SUBMIT DEL FORMULARIO
    $('#guardarProtocolo').on('click', function (e) {
        console.log('✅ #guardarProtocolo clicked');
        e.preventDefault();
        actualizarInsumos();
        actualizarMedicamentos();

        // Populate hidden operatorio input with editor content converted to placeholders
        document.getElementById('operatorioInput').value = getOperatorioValue();
        const form = document.getElementById('editarProtocoloForm');
        const formData = new FormData(form);

        console.log('🚀 Enviando formulario por fetch...');

        fetch(form.action, {
            method: 'POST', body: formData
        })
            .then(response => response.json())
            .then(data => {
                console.log('✅ JSON recibido:', data);

                if (data.success) {
                    Swal.fire({
                        icon: 'success', title: 'Datos Actualizados!', text: data.message, confirmButtonText: 'Ok'
                    }).then(() => {
                        window.location.href = '/protocolos?saved=1';
                    });
                } else {
                    Swal.fire({
                        icon: 'error', title: 'Error', text: data.message, confirmButtonText: 'Ok'
                    });
                }
            })
            .catch(error => {
                console.error('💥 Error general en fetch:', error);
                Swal.fire('Error', 'No se pudo actualizar el protocolo.', 'error');
            });
    });
    // Permite agregar filas de códigos quirúrgicos dinámicamente
    function agregarFilaCodigo(nombre = '', lateralidad = '', selector = '') {
        const fila = `
            <tr>
                <td><input type="text" class="form-control" name="codigos[]" value="${nombre}"></td>
                <td><input type="text" class="form-control" name="lateralidades[]" value="${lateralidad}"></td>
                <td><input type="text" class="form-control" name="selectores[]" value="${selector}"></td>
                <td><button type="button" class="btn btn-danger remove-codigo"><i class="fa fa-trash"></i></button></td>
            </tr>
        `;
        $('#tablaCodigos tbody').append(fila);
    }

    $('#agregar-codigo').on('click', function () {
        agregarFilaCodigo();
    });

    $('#tablaCodigos').on('click', '.remove-codigo', function () {
        $(this).closest('tr').remove();
    });

    // Permite agregar filas de staff quirúrgico dinámicamente
    function agregarFilaStaff(funcion = '', trabajador = '', nombre = '', selector = '') {
        const fila = `
            <tr>
                <td><input type="text" class="form-control" name="funciones[]" value="${funcion}"></td>
                <td><input type="text" class="form-control" name="trabajadores[]" value="${trabajador}"></td>
                <td><input type="text" class="form-control" name="nombres_staff[]" value="${nombre}"></td>
                <td><input type="text" class="form-control" name="selectores[]" value="${selector}"></td>
                <td><button type="button" class="btn btn-danger remove-staff"><i class="fa fa-trash"></i></button></td>
            </tr>
        `;
        $('#tablaStaff tbody').append(fila);
    }
});