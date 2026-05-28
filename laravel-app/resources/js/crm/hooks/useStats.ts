import { useState, useEffect } from 'react';
import { api } from '../api';
import type { PanelStats } from '../types';

interface State {
  stats: PanelStats | null;
  byStage: Record<string, number>;
  loading: boolean;
}

export function useStats() {
  const [state, setState] = useState<State>({ stats: null, byStage: {}, loading: true });

  const load = async () => {
    try {
      const res = await api.stats.panel();
      setState({ stats: res.panel, byStage: res.by_stage, loading: false });
    } catch {
      setState(s => ({ ...s, loading: false }));
    }
  };

  useEffect(() => {
    void load();
    const interval = setInterval(load, 60_000);
    return () => clearInterval(interval);
  }, []);

  return state;
}
