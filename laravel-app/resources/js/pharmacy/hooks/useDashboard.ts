import { useState, useEffect } from 'react';
import type { DashboardMetrics } from '../types';

export function useDashboard() {
  const [data, setData] = useState<DashboardMetrics | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetch('/v2/pharmacy/api/dashboard', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(res => {
        if (!res.ok) throw new Error('Error al cargar métricas');
        return res.json();
      })
      .then(json => setData(json.data))
      .catch(() => setError('No se pudo cargar el dashboard.'))
      .finally(() => setLoading(false));
  }, []);

  return { data, loading, error };
}
