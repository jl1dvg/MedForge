import { useState, useEffect, useCallback } from 'react';
import { api, type OpportunityFilters } from '../api';
import type { CrmOpportunity, ApiMeta } from '../types';

export function useOpportunities(filters: OpportunityFilters) {
  const [data, setData] = useState<CrmOpportunity[]>([]);
  const [meta, setMeta] = useState<ApiMeta>({ total: 0, limit: 25, offset: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.opportunities.list(filters);
      setData(res.data);
      setMeta(res.meta);
    } catch {
      setError('No se pudo cargar las oportunidades. Verifica tu sesión.');
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(filters)]);

  useEffect(() => { void load(); }, [load]);

  return { data, meta, loading, error, refresh: load };
}
