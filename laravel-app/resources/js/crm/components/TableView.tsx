import React from 'react';
import type { OpportunityView } from '../types';
import { STAGE_MAP } from '../stages';
import { fmtMoney, relTime, nextActionState, hexToSoft } from '../helpers';

const ACTION_ICON: Record<string, string> = {
  llamada: 'mdi-phone-outline',
  whatsapp: 'mdi-whatsapp',
  cotizar: 'mdi-file-document-outline',
  cobertura: 'mdi-shield-check-outline',
  agenda: 'mdi-calendar-clock-outline',
  recordatorio: 'mdi-bell-ring-outline',
};

interface SortState { key: string; dir: 'asc' | 'desc'; }

interface TableViewProps {
  rows: OpportunityView[];
  onOpen: (id: number) => void;
  sort: SortState;
  setSort: React.Dispatch<React.SetStateAction<SortState>>;
}

function StageBadge({ slug }: { slug: string }) {
  const s = STAGE_MAP[slug];
  if (!s) return null;
  const bg = hexToSoft(s.color);
  return (
    <span className="t-stage" style={{ background: bg, color: s.color }}>
      <i className={`mdi ${s.icon}`}></i>{s.label}
    </span>
  );
}

export function TableView({ rows, onOpen, sort, setSort }: TableViewProps) {
  const Th = ({ k, children, style }: { k?: string; children: React.ReactNode; style?: React.CSSProperties }) => (
    <th
      style={style}
      onClick={k ? () => setSort(s => ({ key: k, dir: s.key === k && s.dir === 'asc' ? 'desc' : 'asc' })) : undefined}
      className={k ? 'sortable' : ''}
    >
      {children}
      {sort.key === k && (
        <i className={`mdi mdi-menu-${sort.dir === 'asc' ? 'up' : 'down'}`} style={{ fontSize: 14, verticalAlign: 'middle' }}></i>
      )}
    </th>
  );

  return (
    <div className="table-wrap">
      <table className="op-table">
        <thead>
          <tr>
            <Th k="full_name">Paciente</Th>
            <Th>Procedimiento</Th>
            <Th k="stage">Etapa</Th>
            <Th k="valor" style={{ textAlign: 'right' }}>Valor</Th>
            <Th k="probabilidad">Probabilidad</Th>
            <Th>Próxima acción</Th>
            <Th>Responsable</Th>
          </tr>
        </thead>
        <tbody>
          {rows.map(op => {
            const na = nextActionState(op);
            return (
              <tr key={op.id} onClick={() => onOpen(op.id)}>
                <td>
                  <div className="tr-name">
                    <span className="tr-av">{op.initials}</span>
                    <div>
                      <div className="nm">{op.full_name}</div>
                      <div className="sb">
                        {op.hc_number ? `HC ${op.hc_number}` : 'Sin HC'}
                        {op.edad !== '—' ? ` · ${op.edad} años` : ''}
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <div style={{ fontWeight: 600, color: 'var(--fg-1)' }}>
                    {op.procedimiento_short}
                    {op.ojo && (
                      <span style={{ fontSize: 10, fontWeight: 700, color: 'var(--fg-3)', background: 'var(--bg-softer)', borderRadius: 5, padding: '1px 6px', marginLeft: 5 }}>
                        {op.ojo}
                      </span>
                    )}
                  </div>
                  <div style={{ fontSize: 11, color: 'var(--fg-mute)' }}>
                    {op.afiliacion_label} · {op.sede}
                  </div>
                </td>
                <td><StageBadge slug={op.stage} /></td>
                <td style={{ textAlign: 'right' }} className="t-val">
                  {op.stage === 'perdido' ? '—' : fmtMoney(op.valor)}
                </td>
                <td>
                  <div className="t-prob">
                    <span className="pbar"><i style={{ width: `${op.probabilidad}%` }}></i></span>
                    <span style={{ fontSize: 12, fontWeight: 700, color: 'var(--fg-3)' }}>{op.probabilidad}%</span>
                  </div>
                </td>
                <td>
                  {op.cierre ? (
                    <span style={{ fontSize: 12, color: 'var(--fg-mute)' }}>
                      {op.stage === 'ganado' ? 'Cerrada · ganada' : 'Cerrada · perdida'}
                    </span>
                  ) : op.proxima_accion ? (
                    <span className={`t-next estado-${na}`}>
                      <i className={`mdi ${ACTION_ICON[op.proxima_accion.tipo] || 'mdi-arrow-right'}`}></i>
                      {op.proxima_accion.label} · {relTime(op.proxima_accion.due_at)}
                    </span>
                  ) : (
                    <span style={{ color: 'var(--fg-fade)' }}>—</span>
                  )}
                </td>
                <td>
                  <span style={{ fontSize: 12.5, color: 'var(--fg-2)' }}>{op.responsable_name}</span>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
