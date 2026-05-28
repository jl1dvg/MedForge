import React, { useState } from 'react';
import type { CrmOpportunity, Stage } from '../types';
import { api } from '../api';
import { StageSelector } from './StageSelector';
import { ActivityTimeline } from './ActivityTimeline';
import { NoteForm } from './NoteForm';

interface Props {
  opportunity: CrmOpportunity;
  onClose: () => void;
  onUpdated: (opp: CrmOpportunity) => void;
}

const RESOLUTION_BADGE: Record<string, string> = {
  provisional: 'bg-yellow-100 text-yellow-700',
  identified:  'bg-sky-100 text-sky-700',
  linked:      'bg-green-100 text-green-700',
};

export function DetailPanel({ opportunity: initial, onClose, onUpdated }: Props) {
  const [opp, setOpp] = useState(initial);
  const [stageLoading, setStageLoading] = useState(false);

  const handleStageChange = async (stage: Stage) => {
    setStageLoading(true);
    const updated = await api.opportunities.update(opp.id, { stage });
    setOpp(updated);
    onUpdated(updated);
    setStageLoading(false);
  };

  const handleSaveNote = async (type: string, description: string) => {
    await api.opportunities.addActivity(opp.id, type, description);
    const refreshed = await api.opportunities.get(opp.id);
    setOpp(refreshed);
    onUpdated(refreshed);
  };

  const contact = opp.contact;
  const activities = opp.activities ?? [];
  const resolution = contact?.resolution ?? 'provisional';

  return (
    <div className="fixed inset-y-0 right-0 w-1/2 bg-white shadow-2xl border-l border-slate-200 z-50 flex flex-col">
      <div className="px-5 py-4 border-b border-slate-200 flex items-start justify-between flex-shrink-0">
        <div>
          <h2 className="text-lg font-extrabold text-slate-900">{contact?.name ?? '—'}</h2>
          <div className="flex items-center gap-2 mt-1 flex-wrap">
            <span className={`text-xs px-2 py-0.5 rounded-full font-semibold ${RESOLUTION_BADGE[resolution] ?? ''}`}>
              {resolution === 'linked' ? 'vinculado' : resolution === 'identified' ? 'identificado' : 'provisional'}
            </span>
          </div>
        </div>
        <button onClick={onClose} className="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-slate-100">
          X
        </button>
      </div>

      <div className="flex flex-1 overflow-hidden">
        <div className="w-1/2 border-r border-slate-100 overflow-y-auto p-5 flex flex-col gap-5">
          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Contacto</p>
            <div className="bg-slate-50 rounded-xl p-3 text-sm space-y-1.5">
              <p className="text-slate-700">{contact?.phone ?? '—'}</p>
              {contact?.cedula && <p className="text-slate-700">{contact.cedula}</p>}
              {contact?.email && <p className="text-blue-500">{contact.email}</p>}
            </div>
          </div>

          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Origen</p>
            <div className="inline-flex items-center gap-2 bg-slate-100 rounded-lg px-3 py-2 text-sm text-blue-600 font-semibold">
              Ver {opp.source} #{opp.source_id}
            </div>
          </div>

          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Etapa actual</p>
            <StageSelector current={opp.stage} onChange={(s) => { void handleStageChange(s); }} loading={stageLoading} />
          </div>

          <div className="mt-auto grid grid-cols-2 gap-2">
            <button className="bg-amber-500 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-amber-600">Llamar</button>
            <button className="bg-violet-500 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-violet-600">Email</button>
            <button className="col-span-2 bg-red-100 text-red-600 text-sm font-semibold py-2.5 rounded-lg hover:bg-red-200">
              Marcar como perdido
            </button>
          </div>
        </div>

        <div className="w-1/2 overflow-y-auto p-5 bg-slate-50 flex flex-col gap-5">
          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Registrar actividad</p>
            <NoteForm onSave={handleSaveNote} />
          </div>
          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-wide mb-3">Historial</p>
            <ActivityTimeline activities={activities} />
          </div>
        </div>
      </div>
    </div>
  );
}
