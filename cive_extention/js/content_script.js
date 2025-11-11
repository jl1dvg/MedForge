window.guardarTodaLaConsulta = function () {
    console.log("🟢 Iniciando guardado unificado de la consulta...");

    if (window.extraerDatosYEnviar) {
        window.extraerDatosYEnviar();
    }

    if (window.extraerDatosSolicitudYEnviar) {
        const botonGuardar = document.querySelector("#botonGuardar");
        if (botonGuardar) {
            window.extraerDatosSolicitudYEnviar(botonGuardar);
        }
    }
};

window.addEventListener("load", () => {
    if (window.inicializarUI) window.inicializarUI();
    if (window.habilitarArrastre) window.habilitarArrastre();
    if (window.inicializarEventos) window.inicializarEventos();

    if (window.inicializarTablaPacientes) window.inicializarTablaPacientes();
    if (window.iniciarObservadores) window.iniciarObservadores();

    // Verificar si las funciones de admision.js están disponibles
    if (window.extraerDatosYEnviarDesdeModal) {
        console.log("extraerDatosYEnviarDesdeModal disponible.");
    } else {
        console.warn("⚠️ extraerDatosYEnviarDesdeModal no está definido.");
    }

    if (window.registrarCambiosEnCampos) {
        console.log("registrarCambiosEnCampos disponible.");
        window.registrarCambiosEnCampos(); // Ejecutar la inicialización de los campos
    } else {
        console.warn("⚠️ registrarCambiosEnCampos no está definido.");
    }

    const botonGuardar = document.querySelector("#botonGuardar");
    if (botonGuardar && window.guardarTodaLaConsulta) {
        botonGuardar.addEventListener("click", (e) => {
            e.preventDefault();
            window.guardarTodaLaConsulta();
        });
    }

    // Ejecutar detección de insumos si la función existe
    if (window.detectarInsumosPaciente) {
        console.log("🟢 detectInsumosPaciente disponible. Iniciando...");
        window.detectarInsumosPaciente();
    } else {
        console.warn("⚠️ detectarInsumosPaciente no está definida.");
    }

    // Ejecutar la extracción de insumos si la función existe
    if (window.detectarProcedimientosAlGuardar) {
        console.log("🟢 detectarProcedimientosAlGuardar disponible. Iniciando...");
        window.detectarProcedimientosAlGuardar();
    } else {
        console.warn("⚠️ detectarProcedimientosAlGuardar no está definida.");
    }

    // Ejecutar detección de admisión si la función existe
    if (window.inicializarDeteccionModalAdmision) {
        console.log("🟢 inicializarDeteccionModalAdmision disponible. Iniciando...");
        window.inicializarDeteccionModalAdmision();
    } else {
        console.warn("⚠️ inicializarDeteccionModalAdmision no está definida.");
    }

    // Mostrar resultado de envío previo si está disponible
    const log = localStorage.getItem('logAHC');
    if (log) {
        try {
            const datos = JSON.parse(log);
            console.log('📦 Resultado del envío anterior:', datos);
            localStorage.removeItem('logAHC');
            if (datos.estadoPaciente === 'no_admitido') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Paciente no admitido. No deberías escribir en la historia clínica.',
                    text: '⚠️ Este paciente aún no ha llegado.',
                    confirmButtonText: 'Entendido',
                    timer: 10000
                });
            }
        } catch (e) {
            console.error('❌ Error al parsear logAHC:', e);
        }
    }

    if (window.detectarConfirmacionAsistencia) {
        console.log("🟢 detectarConfirmacionAsistencia disponible. Iniciando...");
        window.detectarConfirmacionAsistencia();
    } else {
        console.warn("⚠️ detectarConfirmacionAsistencia no está definida.");
    }

    //if (window.iniciarAnalisisOcuDx) {
    //    console.log("🧠 iniciarAnalisisOcuDx disponible. Ejecutando...");
    //    window.iniciarAnalisisOcuDx();
    //} else {
    //    console.warn("⚠️ iniciarAnalisisOcuDx no está definida.");
    //}
});