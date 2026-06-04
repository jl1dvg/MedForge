import React, { useState } from 'react';
import { useInventory } from '../hooks/useInventory';
import { InventoryForm } from './InventoryForm';
import type { InventoryItem, InventoryCategory } from '../types';

const CATEGORIES: Array<{ value: InventoryCategory | ''; label: string }> = [
  { value: '', label: 'Todas las categorías' },
  { value: 'colirios', label: 'Colirios' },
  { value: 'unguentos', label: 'Ungüentos' },
  { value: 'oral', label: 'Oral' },
  { value: 'inyectables', label: 'Inyectables' },
  { value: 'lagrimas', label: 'Lágrimas' },
  { value: 'antiglaucomatosos', label: 'Antiglaucomatosos' },
  { value: 'antibioticos', label: 'Antibióticos' },
  { value: 'antiinflamatorios', label: 'Antiinflamatorios' },
  { value: 'otros', label: 'Otros' },
];

const TH: React.CSSProperties = {
  padding: '.5rem 1rem',
  textAlign: 'left',
  color: 'var(--fg-mute)',
  fontWeight: 600,
  fontSize: '.75rem',
  borderBottom: '1px solid var(--border-1)',
  background: 'var(--bg-surface)',
  whiteSpace: 'nowrap',
};

const TD: React.CSSProperties = {
  padding: '.5rem 1rem',
  fontSize: '.8125rem',
  color: 'var(--fg-2)',
  borderBottom: '1px solid var(--border-1)',
  verticalAlign: 'middle',
};

export function InventoryList() {
  const [categoria, setCategoria] = useState<InventoryCategory | ''>('');
  const [lowStock, setLowStock] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [editItem, setEditItem] = useState<InventoryItem | null>(null);

  const { data, loading, error, refresh } = useInventory({ categoria, low_stock: lowStock });

  const handleEdit = (item: InventoryItem) => {
    setEditItem(item);
    setShowForm(true);
  };

  const handleAdd = () => {
    setEditItem(null);
    setShowForm(true);
  };

  const handleFormDone = () => {
    setShowForm(false);
    setEditItem(null);
    void refresh();
  };

  const handleFormCancel = () => {
    setShowForm(false);
    setEditItem(null);
  };

  return (
    <div style={{ padding: '1rem' }}>
      {/* Filter bar */}
      <div style={{
        display: 'flex', gap: '.75rem', marginBottom: '1rem', flexWrap: 'wrap', alignItems: 'center',
      }}>
        <select
          value={categoria}
          onChange={e => setCategoria(e.target.value as InventoryCategory | '')}
          style={{
            padding: '.375rem .75rem', fontSize: '.8125rem',
            border: '1px solid var(--border-1)', borderRadius: 'var(--radius)',
            background: 'var(--bg-card)', color: 'var(--fg-1)',
          }}
        >
          {CATEGORIES.map(c => (
            <option key={c.value} value={c.value}>{c.label}</option>
          ))}
        </select>
        <label style={{ display: 'flex', alignItems: 'center', gap: '.375rem', fontSize: '.8125rem', color: 'var(--fg-2)', cursor: 'pointer' }}>
          <input
            type="checkbox"
            checked={lowStock}
            onChange={e => setLowStock(e.target.checked)}
          />
          Solo stock bajo
        </label>
        <button
          onClick={handleAdd}
          style={{
            marginLeft: 'auto',
            padding: '.375rem .875rem', fontSize: '.8125rem',
            background: 'var(--accent, var(--primary))', color: '#fff',
            border: 'none', borderRadius: 'var(--radius)', cursor: 'pointer',
          }}
        >
          + Agregar medicamento
        </button>
      </div>

      {/* Inline form */}
      {showForm && (
        <InventoryForm item={editItem} onSaved={handleFormDone} onCancel={handleFormCancel} />
      )}

      {error && (
        <div style={{
          marginBottom: '1rem', background: 'var(--danger-light)', border: '1px solid var(--danger)',
          color: 'var(--danger)', padding: '.75rem 1rem', borderRadius: 'var(--radius)', fontSize: '.8125rem',
        }}>
          {error}
        </div>
      )}

      {/* Table */}
      <div style={{
        background: 'var(--bg-card)', border: '1px solid var(--border-1)',
        borderRadius: 'var(--radius)', overflow: 'hidden',
      }}>
        <div style={{ overflowX: 'auto' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr>
                <th style={TH}>Nombre</th>
                <th style={TH}>Principio activo</th>
                <th style={TH}>Categoría</th>
                <th style={TH}>Presentación</th>
                <th style={TH}>Stock</th>
                <th style={TH}>Precio</th>
                <th style={TH}>Estado</th>
                <th style={TH}>Acciones</th>
              </tr>
            </thead>
            <tbody>
              {loading && (
                <tr>
                  <td colSpan={8} style={{ ...TD, textAlign: 'center', color: 'var(--fg-mute)', padding: '2rem' }}>
                    Cargando...
                  </td>
                </tr>
              )}
              {!loading && data.length === 0 && (
                <tr>
                  <td colSpan={8} style={{ ...TD, textAlign: 'center', color: 'var(--fg-mute)', padding: '2rem' }}>
                    Sin resultados
                  </td>
                </tr>
              )}
              {!loading && data.map(item => {
                const isLow = item.stock <= item.stock_minimo;
                return (
                  <tr key={item.id}>
                    <td style={{ ...TD, color: 'var(--fg-1)', fontWeight: 600 }}>{item.nombre}</td>
                    <td style={TD}>{item.principio_activo ?? '—'}</td>
                    <td style={TD}>{item.categoria}</td>
                    <td style={TD}>{item.presentacion}</td>
                    <td style={{ ...TD, color: isLow ? 'var(--danger)' : 'var(--fg-2)', fontWeight: isLow ? 700 : 400 }}>
                      {item.stock}
                      {isLow && (
                        <span style={{ fontSize: '.6875rem', marginLeft: '.25rem', color: 'var(--danger)' }}>
                          (min: {item.stock_minimo})
                        </span>
                      )}
                    </td>
                    <td style={TD}>{item.precio != null ? `$${Number(item.precio).toFixed(2)}` : '—'}</td>
                    <td style={TD}>
                      <span style={{
                        display: 'inline-block', fontSize: '.6875rem', fontWeight: 700,
                        padding: '.15rem .5rem', borderRadius: 'var(--radius-pill)',
                        background: item.estado === 'activo' ? 'var(--success-light)' : 'var(--bg-surface)',
                        color: item.estado === 'activo' ? 'var(--success)' : 'var(--fg-mute)',
                      }}>
                        {item.estado}
                      </span>
                    </td>
                    <td style={TD}>
                      <button
                        onClick={() => handleEdit(item)}
                        style={{
                          background: 'var(--bg-surface)', border: '1px solid var(--border-1)',
                          color: 'var(--fg-2)', fontSize: '.75rem', padding: '.25rem .6rem',
                          borderRadius: 'var(--radius)', cursor: 'pointer',
                        }}
                      >
                        Editar
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
