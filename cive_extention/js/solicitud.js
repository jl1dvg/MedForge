if (window.location.href.includes('/documentacion/doc-solicitud-procedimientos/historia-automatica')) {

    function extraerTexto(opEl) {
        const t = (opEl?.textContent || '').trim();
        // filtra placeholders típicos
        if (!t || /^seleccione/i.test(t) || /^ninguno$/i.test(t) || /^seleccione un/i.test(t)) return '';
        return t;
    }

    function limpiarTextoPlano(str) {
        if (!str) return '';
        const t = String(str).trim();
        if (!t || /^seleccione/i.test(t) || /^ninguno$/i.test(t)) return '';
        return t;
    }

    function normalizarFecha(input) {
        const s = limpiarTextoPlano(input);
        if (!s) return '';
        // Reemplaza T por espacio y quita segundos si vienen vacíos
        const tryParse = s.replace('T', ' ').trim();
        const d = new Date(tryParse);
        if (isNaN(d.getTime())) return s; // deja tal cual si no se puede parsear
        const pad = (n) => String(n).padStart(2, '0');
        const yyyy = d.getFullYear();
        const mm = pad(d.getMonth() + 1);
        const dd = pad(d.getDate());
        const hh = pad(d.getHours());
        const mi = pad(d.getMinutes());
        const ss = pad(d.getSeconds());
        return `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
    }

    function toIntOrEmpty(v) {
        const t = limpiarTextoPlano(v);
        if (!t) return '';
        const n = parseInt(t, 10);
        return isNaN(n) ? '' : String(n);
    }

    function unico(arr) {
        return Array.from(new Set(arr));
    }

    function normalizarPrioridad(txt) {
        const t = (txt || '').toString().trim().toUpperCase();
        return t === 'SI' ? 'SI' : 'NO';
    }

    function extraerDatosSolicitudYEnviar(btnGuardar) {
        const url = 'https://asistentecive.consulmed.me/api/solicitudes/guardar.php';
        const data = {};

        const div = document.querySelector('.media-body.responsive');
        if (!div) return;

        // Paciente
        data.hcNumber = (div.querySelector('p:nth-of-type(2)')?.textContent || '')
            .replace('HC #:', '').trim();
        data.form_id = new URLSearchParams(window.location.search).get('idSolicitud') || '';

        if (!data.hcNumber || !data.form_id) return;

        // Solicitudes (solo textos)
        data.solicitudes = [];
        document.querySelectorAll('#interconsultas .multiple-input-list__item').forEach((item, i) => {
            const tipoTxt = extraerTexto(item.querySelector('.list-cell__tipo select')?.selectedOptions?.[0]);
            const afiliacionTxt = extraerTexto(item.querySelector('.list-cell__afiliacion_id select')?.selectedOptions?.[0]);
            const procedimientoTxt = extraerTexto(item.querySelector('.list-cell__procedimiento_id select')?.selectedOptions?.[0]);
            const doctorTxt = extraerTexto(item.querySelector('.list-cell__doctor select')?.selectedOptions?.[0]);

            const fechaRaw = limpiarTextoPlano(item.querySelector('.list-cell__fecha input')?.value);
            const duracionRaw = limpiarTextoPlano(item.querySelector('.list-cell__duracion input')?.value);
            const fecha = normalizarFecha(fechaRaw);
            const duracion = toIntOrEmpty(duracionRaw);

            let ojos = Array.from(item.querySelectorAll('.list-cell__ojo_id input[type="checkbox"]'))
                .filter(cb => cb.checked)
                .map(cb => limpiarTextoPlano(cb.nextSibling?.textContent || ''))
                .filter(Boolean);
            ojos = unico(ojos);
            if (ojos.includes('AMBOS OJOS')) {
                ojos = ['AMBOS OJOS'];
            }

            const prioridadTxt = normalizarPrioridad(item.querySelector('.list-cell__prioridad .cbx-icon')?.textContent || '');

            // Producto (si aplica en esa fila)
            const productoTxt = extraerTexto(item.querySelector('.list-cell__producto_id select')?.selectedOptions?.[0]);

            const observacion = limpiarTextoPlano(item.querySelector('.list-cell__observacionInterconsulta textarea')?.value);

            // Sesiones (visible solo si tipo = TERAPIA, igual tomamos si tiene valor)
            const sesiones = limpiarTextoPlano(item.querySelector('.list-cell__sesiones input')?.value);
            const sesionesVal = (tipoTxt === 'TERAPIA' && sesiones) ? sesiones : '';

            // Detalles (subtabla) como textos
            const detalles = [];
            const sub = item.querySelector('.list-cell__detalles .multiple-input');
            if (sub) {
                sub.querySelectorAll('tbody .multiple-input-list__item').forEach(row => {
                    const principal = row.querySelector('.list-cell__tipo input[type="checkbox"]')?.checked ? 'SI' : 'NO';
                    const lente = extraerTexto(row.querySelector('.list-cell__lente select')?.selectedOptions?.[0]);
                    const poder = extraerTexto(row.querySelector('.list-cell__poder select')?.selectedOptions?.[0]);
                    const lateralidad = extraerTexto(row.querySelector('.list-cell__lateralidad select')?.selectedOptions?.[0]);
                    const obs = limpiarTextoPlano(row.querySelector('.list-cell__observaciones textarea')?.value);

                    // evita empujar filas totalmente vacías
                    if (principal === 'SI' || lente || poder || lateralidad || obs) {
                        detalles.push({principal, lente, poder, lateralidad, observaciones: obs});
                    }
                });
            }

            // Ignora filas vacías (sin procedimiento ni tipo ni afiliación ni doctor ni fecha)
            const tieneContenido = tipoTxt || afiliacionTxt || procedimientoTxt || doctorTxt || fecha || observacion || productoTxt || ojos.length || detalles.length;
            if (!tieneContenido) return;

            data.solicitudes.push({
                secuencia: i + 1,
                tipo: tipoTxt,
                afiliacion: afiliacionTxt,
                procedimiento: procedimientoTxt,
                doctor: doctorTxt || '',
                fecha,                      // normalizada a YYYY-MM-DD HH:mm:ss si fue parseable
                duracion,                   // solo dígitos o ''
                ojo: ojos,                  // array de textos, deduplicado
                prioridad: prioridadTxt,    // SI | NO
                producto: productoTxt || '',
                observacion: observacion || '',
                sesiones: sesionesVal,
                detalles
            });
        });

        if (!data.solicitudes.length) return;

        if (btnGuardar) btnGuardar.disabled = true;

        // --- DEBUG/LOG: ver lo que se envía y lo que responde el API ---
        const DEBUG_SOLICITUD = true; // poner en false si no quieres logs
        const cleaned = JSON.parse(JSON.stringify(data, (k, v) => v === '' ? null : v));
        const payload = JSON.stringify(cleaned);
        if (DEBUG_SOLICITUD) {
            console.groupCollapsed('📤 Envío a API (Solicitud)');
            console.log('URL:', url);
            console.log('Payload:', cleaned);
            console.groupEnd();
        }

        fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: payload
        })
            .then(async (res) => {
                let bodyText = '';
                try {
                    bodyText = await res.text();
                } catch (_) {
                }
                let parsed;
                try {
                    parsed = JSON.parse(bodyText);
                } catch (_) {
                    parsed = null;
                }

                if (DEBUG_SOLICITUD) {
                    console.groupCollapsed('📥 Respuesta API (Solicitud)');
                    console.log('Status:', res.status, res.ok ? '(OK)' : '(ERROR)');
                    try {
                        console.log('Headers:', Object.fromEntries(res.headers.entries()));
                    } catch (_) {
                    }
                    console.log('Body:', parsed ?? bodyText);
                    console.groupEnd();
                }

                // Opcional: feedback visual mínimo
                if (!res.ok) throw new Error('Error HTTP ' + res.status);
                return parsed ?? bodyText;
            })
            .catch((err) => {
                if (DEBUG_SOLICITUD) console.error('❌ Error al enviar solicitud:', err);
            })
            .finally(() => {
                if (btnGuardar) btnGuardar.disabled = false;
            });
    }

    window.extraerDatosSolicitudYEnviar = extraerDatosSolicitudYEnviar;
}