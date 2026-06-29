import { useState, useEffect, useCallback } from 'react';
import type { Prescription, PaginatedMeta, PrescriptionStatus } from '../types';

export interface PrescriptionFilters {
  estado?: PrescriptionStatus | '';
  search?: string;
  page?: number;
}

export function usePrescriptions(filters: PrescriptionFilters) {
  const [data, setData] = useState<Prescription[]>([]);
  const [meta, setMeta] = useState<PaginatedMeta>({ total: 0, per_page: 25, current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams();
      if (filters.estado) params.set('estado', filters.estado);
      if (filters.search) params.set('search', filters.search);
      if (filters.page) params.set('page', String(filters.page));
      const res = await fetch(`/v2/pharmacy/api/prescriptions?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!res.ok) throw new Error('Error al cargar recetas');
      const json = await res.json();
      setData(json.data);
      setMeta(json.meta);
    } catch {
      setError('No se pudo cargar las recetas. Verifica tu sesión.');
    } finally {
      setLoading(false);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(filters)]);

  useEffect(() => { void load(); }, [load]);

  return { data, meta, loading, error, refresh: load };
}
