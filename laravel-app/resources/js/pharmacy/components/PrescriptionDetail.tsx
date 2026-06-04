import React, { useState, useEffect, useCallback } from 'react';
import { StatusBadge, AvailabilityBadge } from './StatusBadge';
import type { Prescription, PrescriptionStatus } from '../types';

interface Props {
  prescriptionId: number;
  onBack: () => void;
}

const STATUS_OPTIONS: PrescriptionStatus[] = ['pendiente', 'procesada', 'parcial', 'entregada', 'cancelada'];

function getCsrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

export function PrescriptionDetail({ prescriptionId, onBack }: Props) {
  const [prescription, setPrescription] = useState<Prescription | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [newEstado, setNewEstado] = useState<PrescriptionStatus>('pendiente');
  const [saving, setSaving] = useState(false);
  const [saveMsg, setSaveMsg] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`/v2/pharmacy/api/prescriptions/${prescriptionId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!res.ok) throw new Error('Error al cargar receta');
      const json = await res.json();
      const rx: Prescription = json.data;
      setPrescription(rx);
      setNewEstado(rx.estado);
    } catch {
      setError('No se pudo cargar la receta.');
    } finally {
      setLoading(false);
    }
  }, [prescriptionId]);

  useEffect(() => { void load(); }, [load]);

  const handleSaveEstado = async () => {
    if (!prescription) return;
    setSaving(true);
    setSaveMsg(null);
    try {
      const res = await fetch(`/v2/pharmacy/api/prescriptions/${prescriptionId}/estado`, {
        method: 'PATCH',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ estado: newEstado }),
      });
      if (!res.ok) throw new Error('Error al guardar');
      const json = await res.json();
      setPrescription(json.data);
      setSaveMsg('Estado actualizado correctamente.');
    } catch {
      setSaveMsg('Error al actualizar el estado.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: '3rem', color: 'var(--fg-mute)', fontSize: '.8125rem' }}>
        Cargando...
      </div>
    );
  }

  if (error || !prescription) {
    return (
      <div style={{ padding: '1rem' }}>
        <div style={{
          background: 'var(--danger-light)', border: '1px solid var(--danger)',
          color: 'var(--danger)', padding: '.75rem 1rem', borderRadius: 'var(--radius)', fontSize: '.8125rem',
        }}>
          {error ?? 'Receta no encontrada.'}
        </div>
        <button onClick={onBack} style={{ marginTop: '1rem', cursor: 'pointer' }} className="btn btn-sm">
          ← Volver
        </button>
      </div>
    );
  }

  const p = prescription.patient;

  return (
    <div style={{ padding: '1rem', maxWidth: 800 }}>
      {/* Back */}
      <button
        onClick={onBack}
        style={{
          marginBottom: '1rem', background: 'transparent', border: 'none',
          color: 'var(--fg-mute)', fontSize: '.8125rem', cursor: 'pointer', padding: 0,
        }}
      >
        ← Volver a recetas
      </button>

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '1rem' }}>
        <div>
          <h2 style={{ margin: 0, fontSize: '1rem', fontWeight: 700, color: 'var(--fg-1)' }}>
            Receta #{prescription.id}
          </h2>
          <div style={{ marginTop: '.375rem' }}>
            <StatusBadge estado={prescription.estado} />
          </div>
        </div>
        <div style={{ fontSize: '.75rem', color: 'var(--fg-mute)' }}>
          {prescription.fecha_prescripcion.slice(0, 10)}
        </div>
      </div>

      {/* Patient card */}
      <div style={{
        background: 'var(--bg-card)', border: '1px solid var(--border-1)',
        borderRadius: 'var(--radius)', padding: '1rem', marginBottom: '1rem',
      }}>
        <p style={{ margin: '0 0 .5rem', fontSize: '.75rem', fontWeight: 700, color: 'var(--fg-mute)', textTransform: 'uppercase', letterSpacing: '.05em' }}>
          Paciente
        </p>
        <p style={{ margin: '0 0 .25rem', fontSize: '.9375rem', fontWeight: 700, color: 'var(--fg-1)' }}>
          {p.nombres} {p.apellidos}
        </p>
        <div style={{ display: 'flex', gap: '1.5rem', flexWrap: 'wrap', fontSize: '.8125rem', color: 'var(--fg-2)' }}>
          <span>CI: {p.identificacion}</span>
          {p.telefono && <span>Tel: {p.telefono}</span>}
          {p.whatsapp && <span>WA: {p.whatsapp}</span>}
          {p.email && <span>{p.email}</span>}
          {p.clinica && <span>Clínica: {p.clinica}</span>}
        </div>
        {prescription.medico && (
          <p style={{ margin: '.5rem 0 0', fontSize: '.8125rem', color: 'var(--fg-2)' }}>
            Médico: {prescription.medico}
          </p>
        )}
        {prescription.clinica && (
          <p style={{ margin: '.25rem 0 0', fontSize: '.8125rem', color: 'var(--fg-2)' }}>
            Clínica: {prescription.clinica}
          </p>
        )}
        {prescription.notas && (
          <p style={{ margin: '.5rem 0 0', fontSize: '.8125rem', color: 'var(--fg-mute)', fontStyle: 'italic' }}>
            {prescription.notas}
          </p>
        )}
      </div>

      {/* Items */}
      <div style={{
        background: 'var(--bg-card)', border: '1px solid var(--border-1)',
        borderRadius: 'var(--radius)', overflow: 'hidden', marginBottom: '1rem',
      }}>
        <div style={{
          padding: '.75rem 1rem', borderBottom: '1px solid var(--border-1)',
          fontSize: '.8125rem', fontWeight: 700, color: 'var(--fg-1)',
        }}>
          Medicamentos ({prescription.items.length})
        </div>
        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '.8125rem' }}>
          <thead>
            <tr style={{ background: 'var(--bg-surface)' }}>
              <th style={{ padding: '.5rem 1rem', textAlign: 'left', color: 'var(--fg-mute)', fontWeight: 600 }}>Medicamento</th>
              <th style={{ padding: '.5rem 1rem', textAlign: 'left', color: 'var(--fg-mute)', fontWeight: 600 }}>Presentación</th>
              <th style={{ padding: '.5rem 1rem', textAlign: 'left', color: 'var(--fg-mute)', fontWeight: 600 }}>Dosis / Frecuencia</th>
              <th style={{ padding: '.5rem 1rem', textAlign: 'left', color: 'var(--fg-mute)', fontWeight: 600 }}>Disponibilidad</th>
            </tr>
          </thead>
          <tbody>
            {prescription.items.map(item => (
              <tr key={item.id} style={{ borderTop: '1px solid var(--border-1)' }}>
                <td style={{ padding: '.5rem 1rem', color: 'var(--fg-1)', fontWeight: 600 }}>{item.nombre_medicamento}</td>
                <td style={{ padding: '.5rem 1rem', color: 'var(--fg-2)' }}>{item.presentacion}</td>
                <td style={{ padding: '.5rem 1rem', color: 'var(--fg-2)' }}>
                  {item.dosis} — {item.frecuencia}
                  {item.duracion_dias != null && <span style={{ color: 'var(--fg-mute)' }}> ({item.duracion_dias}d)</span>}
                  {item.indicaciones && <div style={{ color: 'var(--fg-mute)', fontSize: '.75rem' }}>{item.indicaciones}</div>}
                </td>
                <td style={{ padding: '.5rem 1rem' }}>
                  <AvailabilityBadge disponibilidad={item.disponibilidad} />
                </td>
              </tr>
            ))}
            {prescription.items.length === 0 && (
              <tr>
                <td colSpan={4} style={{ padding: '1.5rem', textAlign: 'center', color: 'var(--fg-mute)' }}>
                  Sin medicamentos
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* Status change */}
      <div style={{
        background: 'var(--bg-card)', border: '1px solid var(--border-1)',
        borderRadius: 'var(--radius)', padding: '1rem', marginBottom: '1rem',
      }}>
        <p style={{ margin: '0 0 .75rem', fontSize: '.75rem', fontWeight: 700, color: 'var(--fg-mute)', textTransform: 'uppercase', letterSpacing: '.05em' }}>
          Actualizar estado
        </p>
        <div style={{ display: 'flex', gap: '.75rem', alignItems: 'center', flexWrap: 'wrap' }}>
          <select
            value={newEstado}
            onChange={e => setNewEstado(e.target.value as PrescriptionStatus)}
            style={{
              padding: '.375rem .75rem', fontSize: '.8125rem',
              border: '1px solid var(--border-1)', borderRadius: 'var(--radius)',
              background: 'var(--bg-surface)', color: 'var(--fg-1)',
            }}
          >
            {STATUS_OPTIONS.map(s => (
              <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
            ))}
          </select>
          <button
            onClick={handleSaveEstado}
            disabled={saving || newEstado === prescription.estado}
            style={{
              padding: '.375rem .875rem', fontSize: '.8125rem',
              background: 'var(--accent, var(--primary))', color: '#fff',
              border: 'none', borderRadius: 'var(--radius)', cursor: 'pointer',
              opacity: (saving || newEstado === prescription.estado) ? 0.6 : 1,
            }}
          >
            {saving ? 'Guardando...' : 'Guardar'}
          </button>
          {saveMsg && (
            <span style={{ fontSize: '.8125rem', color: saveMsg.startsWith('Error') ? 'var(--danger)' : 'var(--success)' }}>
              {saveMsg}
            </span>
          )}
        </div>
      </div>

      {/* WhatsApp logs */}
      {prescription.whatsapp_logs && prescription.whatsapp_logs.length > 0 && (
        <div style={{
          background: 'var(--bg-card)', border: '1px solid var(--border-1)',
          borderRadius: 'var(--radius)', overflow: 'hidden',
        }}>
          <div style={{
            padding: '.75rem 1rem', borderBottom: '1px solid var(--border-1)',
            fontSize: '.8125rem', fontWeight: 700, color: 'var(--fg-1)',
          }}>
            Historial WhatsApp ({prescription.whatsapp_logs.length})
          </div>
          <div style={{ padding: '.5rem' }}>
            {prescription.whatsapp_logs.map(log => (
              <div key={log.id} style={{
                padding: '.5rem .75rem', borderRadius: 'var(--radius)',
                marginBottom: '.375rem',
                background: 'var(--bg-surface)',
                fontSize: '.8125rem',
              }}>
                <div style={{ color: 'var(--fg-2)', marginBottom: '.25rem' }}>{log.mensaje}</div>
                <div style={{ display: 'flex', gap: '1rem', fontSize: '.75rem', color: 'var(--fg-mute)' }}>
                  <span>{log.estado}</span>
                  <span>{log.created_at.slice(0, 16).replace('T', ' ')}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
