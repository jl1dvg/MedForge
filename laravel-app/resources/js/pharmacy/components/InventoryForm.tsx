import React, { useState, useEffect } from 'react';
import type { InventoryItem, InventoryCategory } from '../types';

interface Props {
  item?: InventoryItem | null;
  onSaved: () => void;
  onCancel: () => void;
}

const CATEGORIES: InventoryCategory[] = [
  'colirios', 'unguentos', 'oral', 'inyectables', 'lagrimas',
  'antiglaucomatosos', 'antibioticos', 'antiinflamatorios', 'otros',
];

function getCsrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

const FIELD_STYLE: React.CSSProperties = {
  width: '100%', padding: '.375rem .75rem', fontSize: '.8125rem',
  border: '1px solid var(--border-1)', borderRadius: 'var(--radius)',
  background: 'var(--bg-surface)', color: 'var(--fg-1)', boxSizing: 'border-box',
};

const LABEL_STYLE: React.CSSProperties = {
  display: 'block', fontSize: '.75rem', fontWeight: 600,
  color: 'var(--fg-mute)', marginBottom: '.25rem',
};

export function InventoryForm({ item, onSaved, onCancel }: Props) {
  const [nombre, setNombre] = useState('');
  const [principioActivo, setPrincipioActivo] = useState('');
  const [categoria, setCategoria] = useState<InventoryCategory>('otros');
  const [presentacion, setPresentacion] = useState('');
  const [stock, setStock] = useState('0');
  const [stockMinimo, setStockMinimo] = useState('0');
  const [precio, setPrecio] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (item) {
      setNombre(item.nombre);
      setPrincipioActivo(item.principio_activo ?? '');
      setCategoria(item.categoria);
      setPresentacion(item.presentacion);
      setStock(String(item.stock));
      setStockMinimo(String(item.stock_minimo));
      setPrecio(item.precio != null ? String(item.precio) : '');
    }
  }, [item]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!nombre.trim()) { setError('El nombre es requerido.'); return; }
    setSaving(true);
    setError(null);

    const payload = {
      nombre: nombre.trim(),
      principio_activo: principioActivo.trim() || null,
      categoria,
      presentacion: presentacion.trim(),
      stock: parseInt(stock, 10) || 0,
      stock_minimo: parseInt(stockMinimo, 10) || 0,
      precio: precio !== '' ? parseFloat(precio) : null,
    };

    const url = item ? `/v2/pharmacy/api/inventory/${item.id}` : '/v2/pharmacy/api/inventory';
    const method = item ? 'PATCH' : 'POST';

    try {
      const res = await fetch(url, {
        method,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(payload),
      });
      if (!res.ok) {
        const json = await res.json().catch(() => ({}));
        throw new Error(json.message ?? 'Error al guardar');
      }
      onSaved();
    } catch (err: any) {
      setError(err.message ?? 'Error al guardar');
    } finally {
      setSaving(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} style={{
      background: 'var(--bg-card)', border: '1px solid var(--border-1)',
      borderRadius: 'var(--radius)', padding: '1rem', marginBottom: '1rem',
    }}>
      <div style={{
        display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))',
        gap: '.75rem', marginBottom: '.75rem',
      }}>
        <div>
          <label style={LABEL_STYLE}>Nombre *</label>
          <input value={nombre} onChange={e => setNombre(e.target.value)} style={FIELD_STYLE} required />
        </div>
        <div>
          <label style={LABEL_STYLE}>Principio activo</label>
          <input value={principioActivo} onChange={e => setPrincipioActivo(e.target.value)} style={FIELD_STYLE} />
        </div>
        <div>
          <label style={LABEL_STYLE}>Categoría</label>
          <select value={categoria} onChange={e => setCategoria(e.target.value as InventoryCategory)} style={FIELD_STYLE}>
            {CATEGORIES.map(c => <option key={c} value={c}>{c}</option>)}
          </select>
        </div>
        <div>
          <label style={LABEL_STYLE}>Presentación</label>
          <input value={presentacion} onChange={e => setPresentacion(e.target.value)} style={FIELD_STYLE} />
        </div>
        <div>
          <label style={LABEL_STYLE}>Stock</label>
          <input type="number" min={0} value={stock} onChange={e => setStock(e.target.value)} style={FIELD_STYLE} />
        </div>
        <div>
          <label style={LABEL_STYLE}>Stock mínimo</label>
          <input type="number" min={0} value={stockMinimo} onChange={e => setStockMinimo(e.target.value)} style={FIELD_STYLE} />
        </div>
        <div>
          <label style={LABEL_STYLE}>Precio</label>
          <input type="number" min={0} step="0.01" value={precio} onChange={e => setPrecio(e.target.value)} style={FIELD_STYLE} />
        </div>
      </div>

      {error && (
        <div style={{
          marginBottom: '.75rem', background: 'var(--danger-light)',
          border: '1px solid var(--danger)', color: 'var(--danger)',
          padding: '.5rem .75rem', borderRadius: 'var(--radius)', fontSize: '.8125rem',
        }}>
          {error}
        </div>
      )}

      <div style={{ display: 'flex', gap: '.5rem' }}>
        <button
          type="submit"
          disabled={saving}
          style={{
            padding: '.375rem .875rem', fontSize: '.8125rem',
            background: 'var(--accent, var(--primary))', color: '#fff',
            border: 'none', borderRadius: 'var(--radius)', cursor: 'pointer',
            opacity: saving ? 0.6 : 1,
          }}
        >
          {saving ? 'Guardando...' : item ? 'Guardar cambios' : 'Agregar'}
        </button>
        <button
          type="button"
          onClick={onCancel}
          style={{
            padding: '.375rem .875rem', fontSize: '.8125rem',
            background: 'var(--bg-surface)', color: 'var(--fg-2)',
            border: '1px solid var(--border-1)', borderRadius: 'var(--radius)', cursor: 'pointer',
          }}
        >
          Cancelar
        </button>
      </div>
    </form>
  );
}
