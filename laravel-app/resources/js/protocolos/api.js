function headers(json) {
  const h = { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.csrfToken || '' };
  if (json) h['Content-Type'] = 'application/json';
  return h;
}

export function createApi(endpoints) {
  return {
    async searchCodigos(q) {
      const res = await fetch(`${endpoints.searchCodigos}?q=${encodeURIComponent(q)}`, { headers: headers(false) });
      if (!res.ok) return [];
      const body = await res.json();
      return (body && body.ok && Array.isArray(body.data)) ? body.data : [];
    },

    async staffOptions() {
      const res = await fetch(endpoints.staffOptions, { headers: headers(false) });
      if (!res.ok) return {};
      const body = await res.json();
      return body && body.data ? body.data : {};
    },

    async guardar(payload) {
      const res = await fetch(endpoints.guardar, {
        method: 'POST',
        headers: headers(true),
        body: JSON.stringify(payload),
      });
      const body = await res.json().catch(() => ({ success: false, message: 'Respuesta inválida del servidor.' }));
      return { ok: res.ok, ...body };
    },

    async eliminar(id) {
      const res = await fetch(endpoints.eliminar, {
        method: 'POST',
        headers: headers(true),
        body: JSON.stringify({ id }),
      });
      const body = await res.json().catch(() => ({ success: false, message: 'Respuesta inválida del servidor.' }));
      return { ok: res.ok, ...body };
    },
  };
}
