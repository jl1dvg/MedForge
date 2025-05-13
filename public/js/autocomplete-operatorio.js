const operatorioEditor = document.getElementById("operatorio");
const autocompleteBox = document.getElementById("autocomplete-insumos");
const listaInsumos = Object.values(insumosDisponibles).flat();

["input", "keyup"].forEach(evt =>
    operatorioEditor.addEventListener(evt, function () {
        const sel = window.getSelection();
        if (!sel.rangeCount) {
            autocompleteBox.style.display = "none";
            return;
        }
        const range = sel.getRangeAt(0);
        const preRange = range.cloneRange();
        preRange.selectNodeContents(operatorioEditor);
        preRange.setEnd(range.endContainer, range.endOffset);
        const textoAntes = preRange.toString();
        const match = textoAntes.match(/@([a-zA-Z0-9 ]*)$/);
        if (match) {
            const searchTerm = match[1].toLowerCase();
            const sugerencias = listaInsumos.filter(i =>
                i.nombre.toLowerCase().includes(searchTerm)
            );
            mostrarSugerenciasOperatorio(sugerencias, range);
        } else {
            autocompleteBox.style.display = "none";
        }
    })
);

function mostrarSugerenciasOperatorio(items, range) {
    autocompleteBox.innerHTML = "";
    items.forEach(item => {
        const div = document.createElement("div");
        div.classList.add("suggestion");
        div.textContent = item.nombre;
        div.addEventListener('mousedown', function (e) {
            e.preventDefault(); // prevent editor from losing selection
            insertarCodigoOperatorio(item.id, item.nombre);
        });
        autocompleteBox.appendChild(div);
    });
    autocompleteBox.style.display = "block";
    const rect = range.getBoundingClientRect();
    // Position the autocomplete just below the caret, using fixed positioning
    autocompleteBox.style.position = "fixed";
    autocompleteBox.style.left = rect.left + "px";
    autocompleteBox.style.top = rect.bottom + "px";
    autocompleteBox.style.width = operatorioEditor.offsetWidth + "px";
}

function insertarCodigoOperatorio(id, nombre) {
    const sel = window.getSelection();
    if (!sel.rangeCount) return;
    const range = sel.getRangeAt(0);
    const preRange = range.cloneRange();
    preRange.selectNodeContents(operatorioEditor);
    preRange.setEnd(range.startContainer, range.startOffset);
    const textoAntes = preRange.toString();
    const match = textoAntes.match(/@([a-zA-Z0-9 ]*)$/);
    if (!match) return;
    const charsToRemove = match[0].length;
    range.setStart(range.startContainer, range.startOffset - charsToRemove);
    range.deleteContents();
    const span = document.createElement('span');
    span.className = 'tag';
    span.textContent = nombre.replace(/\s+/g, ' ').trim();
    span.setAttribute('data-id', id);
    range.insertNode(span);
    const space = document.createTextNode('\u00A0');
    span.after(space);
    range.setStartAfter(space);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
    autocompleteBox.style.display = 'none';
}