import { useState, useEffect, useCallback } from 'react';
import { api, type OpportunityFilters } from '../api';
import type { CrmOpportunity, ApiMeta } from '../types';

interface State {
  data: CrmOpportunity[];
  meta: ApiMeta;
  loading: boolean;
  error: string | null;
}

const INITIAL: State = { data: [], meta: { total: 0, limit: 25, offset: 0 }, loading: true, error: null };

export function useOpportunities(filters: OpportunityFilters = {}) {
  const [state, setState] = useState<State>(INITIAL);

  const load = useCallback(async () => {
    setState(s => ({ ...s, loading: true, error: null }));
    try {
      const res = await api.opportunities.list(filters);
      setState({ data: res.data, meta: res.meta, loading: false, error: null });
    } catch {
      setState(s => ({ ...s, loading: false, error: 'No se pudo cargar las oportunidades' }));
    }
  }, [JSON.stringify(filters)]);

  useEffect(() => { void load(); }, [load]);

  return { ...state, refresh: load };
}
