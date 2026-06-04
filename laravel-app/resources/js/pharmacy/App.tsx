import React, { useState } from 'react';
import { Dashboard } from './components/Dashboard';
import { PrescriptionList } from './components/PrescriptionList';
import { PrescriptionDetail } from './components/PrescriptionDetail';
import { InventoryList } from './components/InventoryList';

type Tab = 'dashboard' | 'recetas' | 'inventario' | 'detalle';

const TAB_LABELS: Record<Exclude<Tab, 'detalle'>, string> = {
  dashboard: 'Dashboard',
  recetas:   'Recetas',
  inventario: 'Inventario',
};

export default function App() {
  const [activeTab, setActiveTab] = useState<Tab>('dashboard');
  const [selectedPrescriptionId, setSelectedPrescriptionId] = useState<number | null>(null);

  const handleSelectPrescription = (id: number) => {
    setSelectedPrescriptionId(id);
    setActiveTab('detalle');
  };

  const handleBack = () => {
    setSelectedPrescriptionId(null);
    setActiveTab('recetas');
  };

  const visibleTabs = (['dashboard', 'recetas', 'inventario'] as const);

  return (
    <div style={{ minHeight: '100%', fontFamily: 'var(--font-body, sans-serif)' }}>
      {/* Header */}
      <div style={{
        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
        padding: '.75rem 1.25rem',
        borderBottom: '1px solid var(--border-1)',
        background: 'var(--bg-card)',
      }}>
        <div>
          <h1 style={{ margin: 0, fontSize: '1rem', fontWeight: 700, color: 'var(--fg-1)', fontFamily: 'var(--font-display)' }}>
            Farmacia Pro
          </h1>
          <p style={{ margin: 0, fontSize: '.75rem', color: 'var(--fg-mute)' }}>
            Gestión de recetas e inventario
          </p>
        </div>
      </div>

      {/* Tabs */}
      <div style={{
        display: 'flex', gap: 0,
        borderBottom: '1px solid var(--border-1)',
        background: 'var(--bg-card)',
        padding: '0 1.25rem',
      }}>
        {visibleTabs.map(tab => (
          <button
            key={tab}
            onClick={() => { setActiveTab(tab); if (tab !== 'detalle') setSelectedPrescriptionId(null); }}
            style={{
              padding: '.625rem 1rem',
              background: 'transparent',
              border: 'none',
              borderBottom: (activeTab === tab || (activeTab === 'detalle' && tab === 'recetas'))
                ? '2px solid var(--accent, var(--primary))'
                : '2px solid transparent',
              color: (activeTab === tab || (activeTab === 'detalle' && tab === 'recetas'))
                ? 'var(--fg-1)'
                : 'var(--fg-mute)',
              fontWeight: (activeTab === tab || (activeTab === 'detalle' && tab === 'recetas')) ? 600 : 400,
              fontSize: '.875rem',
              cursor: 'pointer',
              transition: 'color .15s, border-color .15s',
            }}
          >
            {TAB_LABELS[tab]}
          </button>
        ))}
        {activeTab === 'detalle' && (
          <button
            style={{
              padding: '.625rem 1rem',
              background: 'transparent',
              border: 'none',
              borderBottom: '2px solid var(--accent, var(--primary))',
              color: 'var(--fg-1)',
              fontWeight: 600,
              fontSize: '.875rem',
              cursor: 'default',
            }}
          >
            Detalle
          </button>
        )}
      </div>

      {/* Content */}
      <div>
        {activeTab === 'dashboard' && <Dashboard />}
        {activeTab === 'recetas' && (
          <PrescriptionList onSelect={handleSelectPrescription} />
        )}
        {activeTab === 'inventario' && <InventoryList />}
        {activeTab === 'detalle' && selectedPrescriptionId !== null && (
          <PrescriptionDetail prescriptionId={selectedPrescriptionId} onBack={handleBack} />
        )}
      </div>
    </div>
  );
}
