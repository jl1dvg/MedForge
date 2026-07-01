import React from 'react';
import { useCatMeta } from './kit';

function DocSection({ title, icon, empty, children }) {
  return (
    <div className="doc-sec">
      <h6><i className={`mdi ${icon}`} style={{ fontSize: 12 }}></i>{title}</h6>
      {empty ? <p className="doc-empty">{empty}</p> : children}
    </div>
  );
}

export function ProtocolDoc({ data, logoUrl }) {
  const catMeta = useCatMeta();
  const cm = catMeta(data.categoria);
  const codigos = data.codigos || [];
  const staff = (data.staff || []).filter((s) => s.nombre);
  const meds = (data.medicamentos || []).filter((m) => m.medicamento);
  const insumos = (data.insumos || []).filter((i) => i.nombre);
  const evoRows = [
    ['Pre-quirúrgica', data.pre_evolucion, data.pre_indicacion],
    ['Post-quirúrgica', data.post_evolucion, data.post_indicacion],
    ['Alta', data.alta_evolucion, data.alta_indicacion],
  ].filter((r) => r[1] || r[2]);

  return (
    <div className="doc">
      <div className="doc-head">
        {logoUrl && <img src={logoUrl} alt="MedForge" />}
        <div className="dh-tx">
          <div className="dh-tt">{data.membrete || 'Título del protocolo'}</div>
          <div className="dh-sub">Protocolo quirúrgico · {data.cirugia || 'sin nombre corto'}</div>
        </div>
        {data.categoria && <span className="dh-cat" style={{ background: cm.color }}>{data.categoria}</span>}
      </div>

      <div className="doc-body">
        <div className="doc-sec">
          <div className="doc-grid2">
            <div className="doc-kv"><span className="k">Categoría</span><span className="v">{data.categoria || '—'}</span></div>
            <div className="doc-kv"><span className="k">Duración estimada</span><span className="v">{data.horas ? data.horas + ' h' : '—'}</span></div>
          </div>
        </div>

        <DocSection title="Códigos quirúrgicos" icon="mdi-barcode"
                    empty={codigos.length === 0 ? 'Aún no se agregan códigos.' : null}>
          <ul className="doc-list">
            {codigos.map((c, i) => (
              <li key={c.codigo || i}><span className="d"></span>{c.nombre}{c.codigo && <span className="q">{c.codigo}</span>}</li>
            ))}
          </ul>
        </DocSection>

        <DocSection title="Equipo quirúrgico" icon="mdi-account-group-outline"
                    empty={staff.length === 0 ? 'Sin equipo asignado.' : null}>
          <table className="doc-mtable">
            <tbody>
              {staff.map((s, i) => (
                <tr key={i}><td>{s.nombre}</td><td className="r">{s.funcion}</td></tr>
              ))}
            </tbody>
          </table>
        </DocSection>

        <DocSection title="Técnica operatoria" icon="mdi-scalpel"
                    empty={!data.operatorio && !data.dieresis && !data.exposicion && !data.hallazgo ? 'Sin descripción operatoria.' : null}>
          {(data.dieresis || data.exposicion || data.hallazgo) && (
            <div className="doc-grid2" style={{ marginBottom: data.operatorio ? 8 : 0 }}>
              {data.dieresis && <div className="doc-kv"><span className="k">Diéresis</span><span className="v" style={{ fontWeight: 400 }}>{data.dieresis}</span></div>}
              {data.exposicion && <div className="doc-kv"><span className="k">Exposición</span><span className="v" style={{ fontWeight: 400 }}>{data.exposicion}</span></div>}
              {data.hallazgo && <div className="doc-kv"><span className="k">Hallazgo</span><span className="v" style={{ fontWeight: 400 }}>{data.hallazgo}</span></div>}
            </div>
          )}
          {data.operatorio && <p className="doc-p">{data.operatorio}</p>}
        </DocSection>

        <DocSection title="Evolución e indicaciones" icon="mdi-clipboard-pulse-outline"
                    empty={evoRows.length === 0 ? 'Sin evoluciones registradas.' : null}>
          {evoRows.map((r, i) => (
            <div key={i} style={{ marginBottom: i < evoRows.length - 1 ? 8 : 0 }}>
              <span className="k" style={{ color: 'var(--fg-mute)', fontSize: 9.5, textTransform: 'uppercase', letterSpacing: '.04em', fontWeight: 700 }}>{r[0]}</span>
              {r[1] && <p className="doc-p" style={{ marginTop: 2 }}>{r[1]}</p>}
              {r[2] && <p className="doc-mini" style={{ marginTop: 2 }}><strong>Indicación:</strong> {r[2]}</p>}
            </div>
          ))}
        </DocSection>

        <DocSection title="Kardex de medicación" icon="mdi-pill"
                    empty={meds.length === 0 ? 'Sin medicación registrada.' : null}>
          <table className="doc-mtable">
            <tbody>
              {meds.map((m, i) => (
                <tr key={i}><td>{m.medicamento}</td><td className="r">{[m.dosis, m.frecuencia].filter(Boolean).join(' · ')}</td></tr>
              ))}
            </tbody>
          </table>
        </DocSection>

        <DocSection title="Lista de insumos" icon="mdi-package-variant-closed"
                    empty={insumos.length === 0 ? 'Sin insumos registrados.' : null}>
          <ul className="doc-list">
            {insumos.map((it, i) => (
              <li key={i}><span className="d"></span>{it.nombre}<span className="q">×{it.cantidad || 1}</span></li>
            ))}
          </ul>
        </DocSection>
      </div>
    </div>
  );
}
