document.addEventListener("DOMContentLoaded", function () {
    const table = document.getElementById("insumosEditable");
    const agregarBtn = document.getElementById("agregarInsumoBtn");

    if (!table || !agregarBtn) return;

    // Guardar celda editada
    table.addEventListener("click", function (e) {
        if (e.target.classList.contains("save-btn")) {
            const row = e.target.closest("tr");
            guardarFila(row);
        }
    });

    // Agregar nueva fila
    agregarBtn.addEventListener("click", function () {
        const nuevaFila = table.insertRow(-1);
        nuevaFila.setAttribute("data-id", "nuevo");
        const campos = [
            "categoria", "codigo_isspol", "codigo_issfa", "codigo_iess", "codigo_msp",
            "nombre", "producto_issfa", "precio_base", "iva_15", "gestion_10", "precio_total", "precio_isspol"
        ];

        campos.forEach(campo => {
            const td = nuevaFila.insertCell();
            td.setAttribute("contenteditable", "true");
            td.classList.add("editable");
            td.dataset.field = campo;
            td.textContent = "";
        });

        const accionTd = nuevaFila.insertCell();
        const btn = document.createElement("button");
        btn.className = "btn btn-sm btn-success save-btn";
        btn.textContent = "Guardar";
        accionTd.appendChild(btn);

        if (nuevaFila.cells.length !== campos.length + 1) {
            console.warn("Número de columnas inesperado en nueva fila:", nuevaFila.cells.length);
        }
    });

    function guardarFila(row) {
        const id = row.getAttribute("data-id");
        const data = {id: id === "nuevo" ? null : id};

        let valido = true;

        row.querySelectorAll(".editable").forEach(cell => {
            const campo = cell.dataset.field;
            const valor = cell.textContent.trim();

            if (["precio_base", "iva_15", "gestion_10", "precio_total", "precio_isspol"].includes(campo)) {
                if (valor && isNaN(parseFloat(valor))) {
                    cell.style.backgroundColor = "#f8d7da";
                    valido = false;
                } else {
                    cell.style.backgroundColor = "";
                }
            }

            data[campo] = valor;
        });

        if (!valido) {
            Swal.fire("Error", "Revisa los campos numéricos.", "error");
            return;
        }

        fetch("../insumos/actualizar_insumo.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify(data)
        })
            .then(async res => {
                const text = await res.text();
                try {
                    const json = JSON.parse(text);
                    if (json.success) {
                        Swal.fire("Guardado", json.message, "success");
                        if (json.id && id === "nuevo") {
                            row.setAttribute("data-id", json.id);
                        }
                    } else {
                        Swal.fire("Error", json.message, "error");
                    }
                } catch (e) {
                    console.error("Error al parsear JSON:", text);
                    Swal.fire("Error", "Respuesta inesperada del servidor.", "error");
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire("Error", "No se pudo guardar el insumo.", "error");
            });
    }
});