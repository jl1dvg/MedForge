window.habilitarArrastre = function () {
    const button = document.getElementById("floatingButton");
    if (!button) {
        console.warn("Botón flotante no encontrado.");
        return;
    }

    let isDragging = false;
    let startX = 0, startY = 0;
    let currentX = button.getBoundingClientRect().left;
    let currentY = button.getBoundingClientRect().top;
    let offsetX = 0, offsetY = 0;

    button.addEventListener("mousedown", function (e) {
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        button.style.transition = "none";
    });

    document.addEventListener("mousemove", function (e) {
        if (!isDragging) return;
        offsetX = e.clientX - startX;
        offsetY = e.clientY - startY;
        let newX = currentX + offsetX;
        let newY = currentY + offsetY;

        // Limitar dentro del viewport
        newX = Math.max(8, Math.min(window.innerWidth - button.offsetWidth - 8, newX));
        newY = Math.max(8, Math.min(window.innerHeight - button.offsetHeight - 8, newY));

        button.style.left = `${newX}px`;
        button.style.top = `${newY}px`;
        button.style.right = 'auto';
        button.style.bottom = 'auto';
    });

    document.addEventListener("mouseup", function () {
        if (isDragging) {
            isDragging = false;
            currentX = parseFloat(button.style.left) || currentX;
            currentY = parseFloat(button.style.top) || currentY;
            offsetX = 0;
            offsetY = 0;
            button.style.transition = "background 120ms ease-out, box-shadow 120ms ease-out, transform 120ms ease-out";
            try {
                localStorage.setItem('civeFloatingPos', JSON.stringify({x: currentX, y: currentY}));
            } catch (e) {
                console.warn('No se pudo guardar la posición del botón:', e);
            }
        }
    });

    console.log("Arrastre del botón flotante habilitado.");
};
