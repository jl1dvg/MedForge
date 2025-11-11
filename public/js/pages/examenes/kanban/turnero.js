const ENDPOINT = '/examenes/turnero-llamar';

const parseEntero = valor => {
    const numero = Number.parseInt(valor, 10);
    return Number.isNaN(numero) ? null : numero;
};

export const formatTurno = valor => {
    const numero = parseEntero(valor);
    if (!numero || numero <= 0) {
        return null;
    }

    return String(numero).padStart(2, '0');
};

export async function llamarTurnoExamen({ id = null, turno = null, estado = 'Llamado' } = {}) {
    const payload = {};

    const idNormalizado = parseEntero(id);
    const turnoNormalizado = parseEntero(turno);

    if (idNormalizado) {
        payload.id = idNormalizado;
    }

    if (turnoNormalizado) {
        payload.turno = turnoNormalizado;
    }

    if (!payload.id && !payload.turno) {
        throw new Error('Debe especificar el examen a llamar');
    }

    payload.estado = estado;

    const respuesta = await fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });

    if (!respuesta.ok) {
        if (respuesta.status === 401) {
            throw new Error('Sesión expirada, vuelva a iniciar sesión');
        }

        throw new Error('No se pudo comunicar con el turnero');
    }

    const datos = await respuesta.json();

    if (!datos?.success) {
        const mensaje = datos?.error || 'No se pudo asignar el turno';
        throw new Error(mensaje);
    }

    return datos.data ?? {};
}
