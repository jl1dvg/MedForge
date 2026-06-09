import React, { useState, useEffect, useCallback } from 'react';

// ── Protocol Modal ────────────────────────────────────────────────────────────

export function ProtocolModal({ row, endpoints, onClose, onToast }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    const params = new URLSearchParams({ form_id: row.form_id, hc_number: row.hc_number });
    fetch(`${endpoints.protocolo}?${params}`)
      .then((r) => r.json())
      .then((d) => {
        if (d.error) throw new Error(d.error);
        setData(d);
        setLoading(false);
      })
      .catch((e) => {
        setError(e.message);
        setLoading(false);
      });
  }, [row, endpoints.protocolo]);

  useEffect(() => { load(); }, [load]);

  // Close on Escape
  useEffect(() => {
    const handler = (e) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [onClose]);

  const auditStatus = data?.auditoria?.status || 'warning';
  const auditChecks = data?.auditoria?.checks || [];
  const auditSummary = data?.auditoria?.summary || {};

  const auditClass = { ok: 'cir-audit-ok', warning: 'cir-audit-warn', error: 'cir-audit-err' }[auditStatus] || 'cir-audit-warn';
  const auditLabel = { ok: 'OK', warning: 'Advertencia', error: 'Alerta' }[auditStatus] || auditStatus;

  return (
    <div className="cir-modal-backdrop" onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="cir-modal">
        <div className="cir-modal-header">
          <div>
            <h3 className="cir-modal-title">Protocolo Quirúrgico</h3>
            <div className="cir-modal-sub">{row.full_name} · {row.hc_number} · {row.fecha_inicio}</div>
          </div>
          <button className="cir-modal-close" onClick={onClose}>&times;</button>
        </div>

        <div className="cir-modal-body">
          {loading && (
            <div className="cir-modal-loading">
              <span className="cir-spinner" /> Cargando protocolo...
            </div>
          )}

          {error && (
            <div className="cir-modal-error">
              <i className="mdi mdi-alert-circle-outline" /> {error}
            </div>
          )}

          {!loading && !error && data && (
            <>
              {/* Auditoría */}
              {auditChecks.length > 0 && (
                <div className={`cir-audit-panel ${auditClass}`}>
                  <div className="cir-audit-header">
                    <div>
                      <strong>Auditoría automática del protocolo</strong>
                      <div className="cir-audit-desc">Se validó concordancia con lo proyectado y la plantilla quirúrgica.</div>
                    </div>
                    <div className="cir-audit-badges">
                      <span className={`cir-badge cir-audit-badge-${auditStatus}`}>{auditLabel}</span>
                      <span className="cir-badge cir-badge-success">OK: {auditSummary.ok || 0}</span>
                      <span className="cir-badge cir-badge-warning">Advertencias: {auditSummary.warning || 0}</span>
                      <span className="cir-badge cir-badge-danger">Alertas: {auditSummary.error || 0}</span>
                    </div>
                  </div>
                  <div className="cir-audit-checks">
                    {auditChecks.map((c, i) => (
                      <AuditCheck key={i} check={c} />
                    ))}
                  </div>
                </div>
              )}

              {/* Timing */}
              <div className="cir-section">
                <div className="cir-section-title"><i className="mdi mdi-clock-outline" /> Tiempos quirúrgicos</div>
                <div className="cir-timing-grid">
                  <TimingCell label="Fecha" value={data.fecha_inicio} />
                  <TimingCell label="Hora inicio" value={data.hora_inicio} />
                  <TimingCell label="Hora fin" value={data.hora_fin} />
                  <TimingCell label="Duración" value={data.duracion} />
                </div>
              </div>

              {/* Diagnósticos */}
              {(data.diagnosticos || []).length > 0 && (
                <div className="cir-section">
                  <div className="cir-section-title"><i className="mdi mdi-stethoscope" /> Diagnósticos</div>
                  <table className="cir-inner-table">
                    <thead><tr><th>CIE-10</th><th>Detalle</th></tr></thead>
                    <tbody>
                      {data.diagnosticos.map((d, i) => (
                        <tr key={i}><td className="cir-code">{d.cie10}</td><td>{d.detalle}</td></tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              {/* Procedimientos */}
              {(data.procedimientos || []).length > 0 && (
                <div className="cir-section">
                  <div className="cir-section-title"><i className="mdi mdi-medical-bag" /> Procedimientos</div>
                  <table className="cir-inner-table">
                    <thead><tr><th>Código</th><th>Nombre</th></tr></thead>
                    <tbody>
                      {data.procedimientos.map((p, i) => (
                        <tr key={i}><td className="cir-code">{p.codigo}</td><td>{p.nombre}</td></tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              {/* Acto operatorio */}
              <div className="cir-section">
                <div className="cir-section-title"><i className="mdi mdi-scalpel" /> Acto operatorio</div>
                <div className="cir-operatorio-grid">
                  <OperField label="Diéresis" value={data.dieresis} />
                  <OperField label="Exposición" value={data.exposicion} />
                  <OperField label="Hallazgo" value={data.hallazgo} />
                  <OperField label="Operatorio" value={data.operatorio} />
                </div>
              </div>

              {/* Staff */}
              {Object.entries(data.staff || {}).some(([, v]) => v?.trim()) && (
                <div className="cir-section">
                  <div className="cir-section-title"><i className="mdi mdi-account-group" /> Staff quirúrgico</div>
                  <div className="cir-staff-grid">
                    {Object.entries(data.staff).map(([rol, nombre]) =>
                      nombre?.trim() ? (
                        <div key={rol} className="cir-staff-item">
                          <span className="cir-staff-rol">{rol}</span>
                          <span className="cir-staff-nombre">{nombre}</span>
                        </div>
                      ) : null,
                    )}
                  </div>
                </div>
              )}

              {/* Comentario */}
              {data.comentario && (
                <div className="cir-section">
                  <div className="cir-section-title"><i className="mdi mdi-comment-text-outline" /> Comentario / Complicaciones</div>
                  <div className="cir-comentario">{data.comentario}</div>
                </div>
              )}
            </>
          )}
        </div>

        <div className="cir-modal-footer">
          <button className="cir-btn cir-btn-ghost" onClick={onClose}>Cerrar</button>
          <a
            className="cir-btn cir-btn-primary"
            href={`${endpoints.wizard}?form_id=${encodeURIComponent(row.form_id)}&hc_number=${encodeURIComponent(row.hc_number)}`}
          >
            <i className="mdi mdi-pencil" /> Revisar protocolo
          </a>
        </div>
      </div>
    </div>
  );
}

function TimingCell({ label, value }) {
  return (
    <div className="cir-timing-cell">
      <div className="cir-timing-label">{label}</div>
      <div className="cir-timing-value">{value || '—'}</div>
    </div>
  );
}

function OperField({ label, value }) {
  if (!value) return null;
  return (
    <div className="cir-oper-field">
      <div className="cir-oper-label">{label}</div>
      <div className="cir-oper-value">{value}</div>
    </div>
  );
}

function AuditCheck({ check }) {
  const cls = { ok: 'cir-badge-success', warning: 'cir-badge-warning', error: 'cir-badge-danger' }[check.status] || 'cir-badge-muted';
  const label = { ok: 'OK', warning: 'Advertencia', error: 'Alerta' }[check.status] || check.status;
  const details = check.details || {};
  const faltantes = Array.isArray(details.faltantes) ? details.faltantes : [];

  return (
    <div className="cir-audit-check">
      <div className="cir-audit-check-head">
        <div>
          <div className="cir-audit-check-title">{check.title || 'Validación'}</div>
          <div className="cir-audit-check-msg">{check.message || ''}</div>
        </div>
        <span className={`cir-badge ${cls}`}>{label}</span>
      </div>
      {(details.proyectado || details.registrado || details.esperado || faltantes.length > 0) && (
        <div className="cir-audit-check-detail">
          {details.proyectado && <div><strong>Proyectado:</strong> {details.proyectado}</div>}
          {details.registrado && <div><strong>Registrado:</strong> {details.registrado}</div>}
          {details.esperado && <div><strong>Esperado:</strong> {details.esperado}</div>}
          {faltantes.length > 0 && <div><strong>Faltantes:</strong> {faltantes.join(', ')}</div>}
        </div>
      )}
    </div>
  );
}

// ── Certificado Prompt ────────────────────────────────────────────────────────

export function CertificadoPrompt({ row, onClose }) {
  const [dias, setDias] = useState('5');
  const [error, setError] = useState('');

  const handleConfirm = () => {
    const n = parseInt(dias, 10);
    if (!Number.isFinite(n) || n <= 0) {
      setError('Ingrese un número entero mayor a cero.');
      return;
    }
    const params = new URLSearchParams({
      form_id: row.form_id,
      hc_number: row.hc_number,
      dias_descanso: String(n),
    });
    window.open(`/v2/reports/cirugias/descanso/pdf?${params}`, '_blank');
    onClose();
  };

  return (
    <div className="cir-modal-backdrop" onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="cir-modal cir-modal-sm">
        <div className="cir-modal-header">
          <h3 className="cir-modal-title">
            <i className="mdi mdi-file-document-box" /> Certificado de descanso
          </h3>
          <button className="cir-modal-close" onClick={onClose}>&times;</button>
        </div>
        <div className="cir-modal-body">
          <p className="cir-cert-patient">
            <strong>{row.full_name}</strong> · C.I. {row.hc_number}
          </p>
          <div className="cir-filter-field">
            <label>Días de reposo</label>
            <input
              type="number"
              className="cir-input"
              min="1"
              value={dias}
              autoFocus
              onChange={(e) => { setDias(e.target.value); setError(''); }}
              onKeyDown={(e) => { if (e.key === 'Enter') handleConfirm(); }}
            />
            {error && <div className="cir-field-error">{error}</div>}
          </div>
        </div>
        <div className="cir-modal-footer">
          <button className="cir-btn cir-btn-ghost" onClick={onClose}>Cancelar</button>
          <button className="cir-btn cir-btn-primary" onClick={handleConfirm}>
            <i className="mdi mdi-file-pdf-box" /> Generar PDF
          </button>
        </div>
      </div>
    </div>
  );
}
