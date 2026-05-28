import React from 'react';
import type { Stage } from '../types';

const STAGES: { value: Stage; label: string; classes: string }[] = [
  { value: 'nuevo',             label: 'Nuevo',        classes: 'bg-sky-100 text-sky-700 border-sky-200' },
  { value: 'en_contacto',       label: 'En contacto',  classes: 'bg-yellow-100 text-yellow-700 border-yellow-200' },
  { value: 'interesado',        label: 'Interesado',   classes: 'bg-violet-100 text-violet-700 border-violet-200' },
  { value: 'propuesta_enviada', label: 'Propuesta',    classes: 'bg-pink-100 text-pink-700 border-pink-200' },
  { value: 'ganado',            label: 'Ganado',       classes: 'bg-green-100 text-green-700 border-green-200' },
  { value: 'perdido',           label: 'Perdido',      classes: 'bg-red-100 text-red-700 border-red-200' },
];

interface Props {
  current: Stage;
  onChange: (s: Stage) => void;
  loading?: boolean;
}

export function StageSelector({ current, onChange, loading }: Props) {
  return (
    <div className="flex gap-2 flex-wrap">
      {STAGES.map(({ value, label, classes }) => (
        <button
          key={value}
          disabled={loading}
          onClick={() => onChange(value)}
          className={`px-3 py-1 rounded-lg text-xs font-semibold border-2 transition-all
            ${current === value ? `${classes} ring-2 ring-offset-1 ring-blue-400` : 'bg-slate-50 text-slate-400 border-slate-200 hover:border-slate-400'}
            disabled:opacity-50`}
        >
          {label}
        </button>
      ))}
    </div>
  );
}
