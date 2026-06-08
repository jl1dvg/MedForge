import React from 'react';
import type { OpportunityView } from '../types';
import { STAGES, PHASES, type StageConfig } from '../stages';
import { hexToSoft, fmtMoney } from '../helpers';
import { OpCard } from './OpCard';

interface DndHandlers {
  draggingId: number | null;
  dropTarget: string | null;
  onDragStart: (e: React.DragEvent, op: OpportunityView) => void;
  onDragEnd: () => void;
  onDragOver: (e: React.DragEvent, slug: string) => void;
  onDragLeave: () => void;
  onDrop: (e: React.DragEvent, slug: string) => void;
}

interface ColumnProps {
  stage: StageConfig;
  ops: OpportunityView[];
  onOpen: (id: number) => void;
  onQuick: (op: OpportunityView, kind: string) => void;
  dnd: DndHandlers;
}

function Column({ stage, ops, onOpen, onQuick, dnd }: ColumnProps) {
  const bg = hexToSoft(stage.color);
  const total = ops.reduce((a, o) => a + (o.stage === 'perdido' ? 0 : (o.valor || 0)), 0);
  const isDrop = dnd.dropTarget === stage.slug;
  const closedCls = stage.slug === 'ganado' ? 'col-won' : stage.slug === 'perdido' ? 'col-lost' : '';

  return (
    <div
      className={`col ${closedCls}${isDrop ? ' is-droptarget' : ''}`}
      onDragOver={e => dnd.onDragOver(e, stage.slug)}
      onDragLeave={dnd.onDragLeave}
      onDrop={e => dnd.onDrop(e, stage.slug)}
    >
      <div className="col-head">
        <span className="col-ic" style={{ background: bg, color: stage.color }}>
          <i className={`mdi ${stage.icon}`}></i>
        </span>
        <h3 className="col-title">{stage.label}</h3>
        <span className="col-count">{ops.length}</span>
      </div>
      {ops.length > 0 && stage.slug !== 'perdido' && (
        <div className="col-sum">{fmtMoney(total)} en juego</div>
      )}
      <div className="col-body">
        {ops.length === 0 ? (
          <div className="col-empty">
            <i className="mdi mdi-tray-remove"></i>
            Sin oportunidades
          </div>
        ) : (
          ops.map(op => (
            <OpCard key={op.id} op={op} onOpen={onOpen} onQuick={onQuick} dnd={dnd} />
          ))
        )}
      </div>
    </div>
  );
}

interface BoardProps {
  byStage: Record<string, OpportunityView[]>;
  onOpen: (id: number) => void;
  onQuick: (op: OpportunityView, kind: string) => void;
  dnd: DndHandlers;
  groupPhases?: boolean;
}

export function Board({ byStage, onOpen, onQuick, dnd, groupPhases }: BoardProps) {
  if (groupPhases) {
    return (
      <div className="board-scroll">
        <div className="board">
          {PHASES.map(ph => {
            const stages = STAGES.filter(s => s.phase === ph.slug);
            const phaseN = stages.reduce((a, s) => a + (byStage[s.slug]?.length || 0), 0);
            return (
              <div className="phase-group" key={ph.slug}>
                <div className="phase-band">
                  <i className={`mdi ${ph.icon}`}></i>
                  {ph.label}
                  <span className="pb-val">· {phaseN} {phaseN === 1 ? 'oportunidad' : 'oportunidades'}</span>
                  <span className="ph-line"></span>
                </div>
                <div className="pg-cols">
                  {stages.map(s => (
                    <Column
                      key={s.slug}
                      stage={s}
                      ops={byStage[s.slug] || []}
                      onOpen={onOpen}
                      onQuick={onQuick}
                      dnd={dnd}
                    />
                  ))}
                </div>
              </div>
            );
          })}
        </div>
      </div>
    );
  }

  return (
    <div className="board-scroll">
      <div className="board" style={{ gap: '13px' }}>
        {STAGES.map(s => (
          <Column
            key={s.slug}
            stage={s}
            ops={byStage[s.slug] || []}
            onOpen={onOpen}
            onQuick={onQuick}
            dnd={dnd}
          />
        ))}
      </div>
    </div>
  );
}
