const operatorioTextarea = document.getElementById("operatorio");
const autocompleteBox = document.getElementById("autocomplete-insumos");
const listaInsumos = Object.values(insumosDisponibles).flat();

// Listen for input events on the textarea
operatorioTextarea.addEventListener("input", function () {
    const cursor = operatorioTextarea.selectionStart;
    const textoAntes = operatorioTextarea.value.substring(0, cursor);
    const match = textoAntes.match(/@([a-zA-Z0-9 ]*)$/);

    if (match) {
        const searchTerm = match[1].toLowerCase();
        const sugerencias = listaInsumos.filter(i =>
            i.nombre.toLowerCase().includes(searchTerm)
        );
        mostrarSugerenciasOperatorio(sugerencias, cursor);
    } else {
        autocompleteBox.style.display = "none";
    }
});

function mostrarSugerenciasOperatorio(items, cursorPos) {
    autocompleteBox.innerHTML = "";
    items.forEach(item => {
        const div = document.createElement("div");
        div.classList.add("suggestion");
        div.textContent = item.nombre;
        div.onclick = () => insertarCodigoOperatorio(item.id, item.nombre, cursorPos);
        autocompleteBox.appendChild(div);
    });
    autocompleteBox.style.display = "block";
    const {offsetLeft, offsetTop} = operatorioTextarea;
    const lineHeight = 24; // approximate line height in pixels
    const lines = operatorioTextarea.value.substr(0, cursorPos).split('\n');
    const topOffset = lineHeight * lines.length;

    autocompleteBox.style.position = "absolute";
    autocompleteBox.style.left = offsetLeft + "px";
    autocompleteBox.style.top = (offsetTop + topOffset) + "px";
    autocompleteBox.style.width = operatorioTextarea.offsetWidth + "px";
}

function insertarCodigoOperatorio(id, nombre, cursorPos) {
    const texto = operatorioTextarea.value;
    const match = texto.substring(0, cursorPos).match(/@([a-zA-Z0-9 ]*)$/);
    if (!match) return;

    const inicio = match.index;
    const nuevoTexto =
        texto.substring(0, inicio) + nombre + ' ' + texto.substring(cursorPos);
    operatorioTextarea.value = nuevoTexto;
    operatorioTextarea.setSelectionRange(inicio + nombre.length + 1, inicio + nombre.length + 1);
    operatorioTextarea.focus();
    autocompleteBox.style.display = "none";
}