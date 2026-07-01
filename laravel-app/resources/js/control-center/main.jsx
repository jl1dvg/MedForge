import '@mdi/font/css/materialdesignicons.css';
import '../../css/control-center.css';
import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

async function request(path, options = {}) {
  const response = await fetch(path, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      ...(options.headers || {}),
    },
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.message || payload.error || 'No se pudo completar la solicitud.');
  }
  return payload.data;
}

const stateMeta = {
  production: { label: 'Produccion', tone: 'ok', icon: 'mdi-check-circle' },
  maintenance: { label: 'Mantenimiento', tone: 'warn', icon: 'mdi-tools' },
  readonly: { label: 'Solo lectura', tone: 'info', icon: 'mdi-lock-outline' },
  suspended: { label: 'Suspendido', tone: 'danger', icon: 'mdi-cancel' },
};

function App() {
  const [overview, setOverview] = useState(null);
  const [clients, setClients] = useState([]);
  const [selectedId, setSelectedId] = useState(null);
  const [detail, setDetail] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [filter, setFilter] = useState('all');

  async function load() {
    setLoading(true);
    setError('');
    try {
      const [overviewData, clientsData] = await Promise.all([
        request('/v2/control-center/overview'),
        request('/v2/control-center/clients?per_page=100'),
      ]);
      setOverview(overviewData);
      setClients(clientsData);
      setSelectedId((current) => current || clientsData[0]?.id || null);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }

  async function loadDetail(clientId) {
    if (!clientId) return;
    setError('');
    try {
      setDetail(await request(`/v2/control-center/clients/${clientId}`));
    } catch (err) {
      setError(err.message);
    }
  }

  useEffect(() => {
    load();
  }, []);

  useEffect(() => {
    loadDetail(selectedId);
  }, [selectedId]);

  const visibleClients = useMemo(() => {
    if (filter === 'all') return clients;
    return clients.filter((client) => client.status === filter);
  }, [clients, filter]);

  const selectedClient = detail?.client || clients.find((client) => client.id === selectedId);

  async function changeState(nextState) {
    if (!selectedClient) return;
    const reason = window.prompt(`Motivo para cambiar ${selectedClient.name} a ${stateMeta[nextState].label}:`, '');
    if (reason === null) return;

    setSaving(true);
    setError('');
    try {
      const data = await request(`/v2/control-center/clients/${selectedClient.id}/state`, {
        method: 'POST',
        body: JSON.stringify({
          state: nextState,
          reason,
          confirm: nextState === 'production' ? undefined : nextState,
        }),
      });
      setDetail((current) => ({ ...(current || {}), ...data }));
      await load();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }

  async function toggleFeature(feature) {
    if (!selectedClient) return;
    setSaving(true);
    setError('');
    try {
      const data = await request(`/v2/control-center/clients/${selectedClient.id}/features`, {
        method: 'POST',
        body: JSON.stringify({
          features: [{ key: feature.key, enabled: !feature.enabled, reason: 'Cambio desde Control Center MVP' }],
        }),
      });
      setDetail((current) => ({ ...(current || {}), features: data.features }));
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }

  if (loading && !overview) {
    return <Shell><div className="cc-empty">Cargando Control Center...</div></Shell>;
  }

  return (
    <Shell>
      <header className="cc-topbar">
        <div>
          <p className="cc-eyebrow">MedForge Ops</p>
          <h1>Control Center</h1>
        </div>
        <div className="cc-actions">
          <button type="button" onClick={load} disabled={loading || saving}>
            <i className="mdi mdi-refresh" aria-hidden="true" />
            Actualizar
          </button>
        </div>
      </header>

      {error ? <div className="cc-alert">{error}</div> : null}

      <section className="cc-kpis">
        <Kpi icon="mdi-domain" label="Clientes" value={overview?.summary?.clients_total || 0} />
        <Kpi icon="mdi-check-decagram" label="Produccion" value={overview?.summary?.production || 0} tone="ok" />
        <Kpi icon="mdi-lock-outline" label="Solo lectura" value={overview?.summary?.readonly || 0} tone="info" />
        <Kpi icon="mdi-alert" label="Servicios degradados" value={overview?.summary?.services_degraded || 0} tone="warn" />
      </section>

      <main className="cc-grid">
        <section className="cc-panel cc-clients">
          <div className="cc-panel-head">
            <div>
              <p className="cc-eyebrow">Instancias</p>
              <h2>Clientes</h2>
            </div>
            <select value={filter} onChange={(event) => setFilter(event.target.value)}>
              <option value="all">Todos</option>
              <option value="production">Produccion</option>
              <option value="maintenance">Mantenimiento</option>
              <option value="readonly">Solo lectura</option>
              <option value="suspended">Suspendidos</option>
            </select>
          </div>

          <div className="cc-client-list">
            {visibleClients.map((client) => (
              <button
                type="button"
                className={`cc-client-row ${client.id === selectedId ? 'is-active' : ''}`}
                key={client.id}
                onClick={() => setSelectedId(client.id)}
              >
                <span className="cc-avatar" style={{ backgroundColor: client.color || '#006b75' }}>{client.initials}</span>
                <span>
                  <strong>{client.name}</strong>
                  <small>{client.domain || client.slug}</small>
                </span>
                <StatePill state={client.status} />
              </button>
            ))}
          </div>
        </section>

        <section className="cc-panel cc-detail">
          {selectedClient ? (
            <>
              <div className="cc-detail-head">
                <div className="cc-titleline">
                  <span className="cc-avatar cc-avatar-lg" style={{ backgroundColor: selectedClient.color || '#006b75' }}>{selectedClient.initials}</span>
                  <div>
                    <p className="cc-eyebrow">{selectedClient.environment} · {selectedClient.city || 'Sin ciudad'}</p>
                    <h2>{selectedClient.name}</h2>
                    <span>{selectedClient.domain}</span>
                  </div>
                </div>
                <StatePill state={selectedClient.status} />
              </div>

              <div className="cc-meta-grid">
                <Meta label="Servidor" value={selectedClient.server_label} />
                <Meta label="Base de datos" value={selectedClient.database_name} />
                <Meta label="Version" value={selectedClient.current_version} />
                <Meta label="Plan" value={selectedClient.plan_name} />
              </div>

              <div className="cc-section">
                <div className="cc-panel-head compact">
                  <div>
                    <p className="cc-eyebrow">Estado operativo</p>
                    <h3>{stateMeta[detail?.state?.state || selectedClient.status]?.label}</h3>
                  </div>
                </div>
                <div className="cc-state-actions">
                  {Object.keys(stateMeta).map((state) => (
                    <button type="button" key={state} className="cc-state-button" disabled={saving} onClick={() => changeState(state)}>
                      <i className={`mdi ${stateMeta[state].icon}`} aria-hidden="true" />
                      {stateMeta[state].label}
                    </button>
                  ))}
                </div>
              </div>

              <div className="cc-section">
                <div className="cc-panel-head compact">
                  <div>
                    <p className="cc-eyebrow">Feature flags</p>
                    <h3>Modulos activos</h3>
                  </div>
                </div>
                <div className="cc-feature-list">
                  {(detail?.features || []).map((feature) => (
                    <button type="button" key={feature.key} className="cc-feature-row" disabled={saving} onClick={() => toggleFeature(feature)}>
                      <span>
                        <strong>{feature.name}</strong>
                        <small>{feature.module} · riesgo {feature.risk_level}</small>
                      </span>
                      <span className={`cc-toggle ${feature.enabled ? 'is-on' : ''}`} aria-label={feature.enabled ? 'Activo' : 'Inactivo'} />
                    </button>
                  ))}
                </div>
              </div>
            </>
          ) : (
            <div className="cc-empty">Selecciona un cliente.</div>
          )}
        </section>

        <aside className="cc-panel cc-side">
          <div className="cc-panel-head">
            <div>
              <p className="cc-eyebrow">Servicios</p>
              <h2>Estado manual MVP</h2>
            </div>
          </div>
          <div className="cc-service-list">
            {(detail?.services || overview?.services || []).slice(0, 8).map((service) => (
              <div className="cc-service-row" key={`${service.client_id}-${service.key}`}>
                <i className={`mdi ${service.icon || 'mdi-server'}`} aria-hidden="true" />
                <span>
                  <strong>{service.name}</strong>
                  <small>{service.client_name || selectedClient?.name}</small>
                </span>
                <span className={`cc-dot ${service.state}`} />
              </div>
            ))}
          </div>

          <div className="cc-section">
            <p className="cc-eyebrow">Auditoria reciente</p>
            <div className="cc-audit-list">
              {(detail?.audit || overview?.audit || []).slice(0, 6).map((entry) => (
                <div className="cc-audit-row" key={entry.id}>
                  <strong>{entry.action}</strong>
                  <small>{entry.actor_name || 'Sistema'} · {entry.client_name || 'Global'}</small>
                </div>
              ))}
            </div>
          </div>
        </aside>
      </main>
    </Shell>
  );
}

function Shell({ children }) {
  return (
    <div className="cc-app">
      <nav className="cc-nav" aria-label="Control Center">
        <div className="cc-brand">
          <span className="cc-mark">MF</span>
          <strong>MedForge</strong>
        </div>
        {['Overview', 'Clientes', 'Estado', 'Features', 'Servicios', 'Deploys', 'Consumo', 'Auditoria'].map((item, index) => (
          <span className={`cc-nav-item ${index === 0 ? 'is-active' : ''}`} key={item}>{item}</span>
        ))}
      </nav>
      <div className="cc-content">{children}</div>
    </div>
  );
}

function Kpi({ icon, label, value, tone = 'neutral' }) {
  return (
    <div className={`cc-kpi ${tone}`}>
      <i className={`mdi ${icon}`} aria-hidden="true" />
      <span>
        <strong>{value}</strong>
        <small>{label}</small>
      </span>
    </div>
  );
}

function StatePill({ state }) {
  const meta = stateMeta[state] || stateMeta.production;
  return <span className={`cc-pill ${meta.tone}`}><i className={`mdi ${meta.icon}`} aria-hidden="true" />{meta.label}</span>;
}

function Meta({ label, value }) {
  return (
    <div className="cc-meta">
      <small>{label}</small>
      <strong>{value || 'No definido'}</strong>
    </div>
  );
}

const root = document.getElementById('control-center-root');
if (root) {
  createRoot(root).render(<App />);
}
