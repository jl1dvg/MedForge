import type { AppointmentForm, AppointmentResponse } from './types';

function csrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

export async function createAppointment(payload: AppointmentForm): Promise<AppointmentResponse> {
  const response = await fetch('/v2/api/agenda/citas', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken(),
    },
    body: JSON.stringify(payload),
  });
  const data = await response.json().catch(() => null) as AppointmentResponse | null;
  if (!response.ok || data?.ok === false) {
    const message = data?.error ?? `HTTP ${response.status}`;
    const error = new Error(message) as Error & { details?: AppointmentResponse };
    error.details = data ?? undefined;
    throw error;
  }
  return data ?? { ok: true };
}
