import React from 'react';
import type { Stage } from '../types';

const STAGES: { value: Stage; label: string }[] = [
  { value: 'nuevo',         label: 'Nuevo'         },
  { value: 'contactado',    label: 'Contactado'    },
  { value: 'en_evaluacion', label: 'En evaluación' },
  { value: 'propuesta',     label: 'Propuesta'     },
  { value: 'comprometido',  label: 'Comprometido'  },
  { value: 'ganado',        label: 'Ganado'        },
  { value: 'perdido',       label: 'Perdido'       },
];

interface Props {
  current: Stage;
  onChange: (s: Stage) => void;
  loading?: boolean;
}

export function StageSelector({ current, onChange, loading }: Props) {
  return (
    <div className="crm-stage-selector">
      {STAGES.map(({ value, label }) => (
        <button
          key={value}
          disabled={loading}
          onClick={() => onChange(value)}
          className={`crm-stage-btn${current === value ? ' active' : ''}`}
        >
          {label}
        </button>
      ))}
    </div>
  );
}
