window.habilitarArrastre = function () {
    const button = document.getElementById("floatingButton");
    if (!button) {
        console.warn("Botón flotante no encontrado.");
        return;
    }

    let isDragging = false;
    let initialY, currentY = button.getBoundingClientRect().top, offsetY = 0;

    button.addEventListener("mousedown", function (e) {
        isDragging = true;
        initialY = e.clientY;
        button.style.transition = "none";
    });

    document.addEventListener("mousemove", function (e) {
        if (!isDragging) return;
        offsetY = e.clientY - initialY;
        let newTranslateY = currentY + offsetY;
        newTranslateY = Math.max(0, Math.min(window.innerHeight - button.offsetHeight, newTranslateY));
        button.style.transform = `translateY(${newTranslateY - currentY}px)`;
    });

    document.addEventListener("mouseup", function () {
        if (isDragging) {
            isDragging = false;
            currentY += offsetY;
            offsetY = 0;
            button.style.transition = "background 100ms ease-out";
        }
    });

    console.log("Arrastre del botón flotante habilitado.");
};