import { useState, useEffect, useCallback } from 'react';
import type { InventoryItem, InventoryCategory } from '../types';

export interface InventoryFilters {
  categoria?: InventoryCategory | '';
  low_stock?: boolean;
}

export function useInventory(filters: InventoryFilters) {
  const [data, setData] = useState<InventoryItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams();
      if (filters.categoria) params.set('categoria', filters.categoria);
      if (filters.low_stock) params.set('low_stock', '1');
      const res = await fetch(`/v2/pharmacy/api/inventory?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!res.ok) throw new Error('Error al cargar inventario');
      const json = await res.json();
      setData(json.data);
    } catch {
      setError('No se pudo cargar el inventario. Verifica tu sesión.');
    } finally {
      setLoading(false);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(filters)]);

  useEffect(() => { void load(); }, [load]);

  return { data, loading, error, refresh: load };
}
