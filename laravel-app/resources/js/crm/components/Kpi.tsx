import React from 'react';

interface KpiProps {
  tone: 'pipeline' | 'money' | 'today' | 'overdue' | 'win' | 'warn';
  icon: string;
  value: string | number;
  unit?: string;
  label: string;
  active?: boolean;
  onClick?: () => void;
  spark?: { dir: 'up' | 'down'; txt: string };
  hideSmall?: boolean;
}

export function Kpi({ tone, icon, value, unit, label, active, onClick, spark, hideSmall }: KpiProps) {
  return (
    <button
      className={`kpi tone-${tone}${active ? ' is-active' : ''}${hideSmall ? ' kpi-hide-sm' : ''}`}
      onClick={onClick}
    >
      <span className="kpi-ic"><i className={`mdi ${icon}`}></i></span>
      <span className="kpi-body">
        <span className="kpi-value">
          {value}
          {unit && <span className="unit">{unit}</span>}
        </span>
        <span className="kpi-label">{label}</span>
      </span>
      {spark && (
        <span className={`kpi-spark ${spark.dir}`}>
          <i className={`mdi mdi-trending-${spark.dir}`}></i>
          {spark.txt}
        </span>
      )}
    </button>
  );
}
