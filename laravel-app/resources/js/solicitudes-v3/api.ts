import type { Filters, KanbanData, KanbanSlug, Solicitud } from './types';

async function json<T>(url: string, init?: RequestInit): Promise<T> {
  const res = await fetch(url, {
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    ...init,
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json() as Promise<T>;
}

function csrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

export async function fetchKanbanData(endpoint: string, filters: Partial<Filters>): Promise<KanbanData> {
  const params = new URLSearchParams();
  for (const [k, v] of Object.entries(filters)) {
    if (v) params.set(k, v);
  }
  const qs = params.toString();
  return json<KanbanData>(`${endpoint}${qs ? '?' + qs : ''}`);
}

export async function fetchSolicitudDetail(estadoEndpoint: string, hcNumber: string): Promise<Solicitud[]> {
  const data = await json<{ solicitudes: Solicitud[] }>(`${estadoEndpoint}/${hcNumber}`);
  return data.solicitudes ?? [];
}

export async function updateEstado(
  endpoint: string,
  id: number,
  nuevoEstado: KanbanSlug,
): Promise<void> {
  await json<unknown>(endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
    },
    body: JSON.stringify({ id, estado: nuevoEstado }),
  });
}
