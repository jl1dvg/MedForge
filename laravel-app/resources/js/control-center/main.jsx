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
  if (!response.ok) throw new Error(payload.message || payload.error || 'No se pudo completar la solicitud.');
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
  const [organizations, setOrganizations] = useState([]);
  const [instances, setInstances] = useState([]);
  const [selectedOrganizationId, setSelectedOrganizationId] = useState(null);
  const [selectedInstanceId, setSelectedInstanceId] = useState(null);
  const [detail, setDetail] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [filter, setFilter] = useState('all');

  async function load() {
    setLoading(true);
    setError('');
    try {
      const [overviewData, orgData, instanceData] = await Promise.all([
        request('/v2/control-center/overview'),
        request('/v2/control-center/organizations?per_page=100'),
        request('/v2/control-center/instances?per_page=100'),
      ]);
      setOverview(overviewData);
      setOrganizations(orgData);
      setInstances(instanceData);
      setSelectedOrganizationId((current) => current || orgData[0]?.id || null);
      setSelectedInstanceId((current) => current || instanceData[0]?.id || null);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }

  async function loadDetail(instanceId) {
    if (!instanceId) return;
    setError('');
    try {
      setDetail(await request(`/v2/control-center/instances/${instanceId}`));
    } catch (err) {
      setError(err.message);
    }
  }

  useEffect(() => { load(); }, []);
  useEffect(() => { loadDetail(selectedInstanceId); }, [selectedInstanceId]);

  const visibleInstances = useMemo(() => {
    return instances.filter((instance) => {
      if (selectedOrganizationId && instance.organization_id !== selectedOrganizationId) return false;
      if (filter !== 'all' && instance.status !== filter) return false;
      return true;
    });
  }, [instances, selectedOrganizationId, filter]);

  const selectedInstance = detail?.instance || instances.find((instance) => instance.id === selectedInstanceId);
  const selectedOrganization = detail?.organization || organizations.find((organization) => organization.id === selectedOrganizationId);

  async function changeState(nextState) {
    if (!selectedInstance) return;
    const reason = window.prompt(`Motivo para cambiar ${selectedInstance.name} a ${stateMeta[nextState].label}:`, '');
    if (reason === null) return;

    setSaving(true);
    setError('');
    try {
      const data = await request(`/v2/control-center/instances/${selectedInstance.id}/state`, {
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
    if (!selectedInstance) return;
    setSaving(true);
    setError('');
    try {
      const data = await request(`/v2/control-center/instances/${selectedInstance.id}/features`, {
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
        <Kpi icon="mdi-domain" label="Organizaciones" value={overview?.summary?.organizations_total || 0} />
        <Kpi icon="mdi-server-network" label="Instancias" value={overview?.summary?.instances_total || 0} />
        <Kpi icon="mdi-lock-outline" label="Solo lectura" value={overview?.summary?.readonly || 0} tone="info" />
        <Kpi icon="mdi-alert" label="Servicios degradados" value={overview?.summary?.services_degraded || 0} tone="warn" />
      </section>

      <main className="cc-grid">
        <section className="cc-panel cc-clients">
          <div className="cc-panel-head">
            <div>
              <p className="cc-eyebrow">Empresa legal/comercial</p>
              <h2>Organizaciones</h2>
            </div>
          </div>
          <div className="cc-client-list">
            {organizations.map((organization) => (
              <button
                type="button"
                className={`cc-client-row ${organization.id === selectedOrganizationId ? 'is-active' : ''}`}
                key={organization.id}
                onClick={() => {
                  setSelectedOrganizationId(organization.id);
                  const firstInstance = instances.find((instance) => instance.organization_id === organization.id);
                  if (firstInstance) setSelectedInstanceId(firstInstance.id);
                }}
              >
                <span className="cc-avatar" style={{ backgroundColor: organization.color || '#006b75' }}>{organization.initials}</span>
                <span>
                  <strong>{organization.name}</strong>
                  <small>{organization.plan_name || 'Sin plan'}</small>
                </span>
                <span className="cc-count">{instances.filter((instance) => instance.organization_id === organization.id).length}</span>
              </button>
            ))}
          </div>
        </section>

        <section className="cc-panel cc-detail">
          <div className="cc-panel-head compact">
            <div>
              <p className="cc-eyebrow">Instalaciones MedForge</p>
              <h2>Instancias</h2>
            </div>
            <select value={filter} onChange={(event) => setFilter(event.target.value)}>
              <option value="all">Todos</option>
              <option value="production">Produccion</option>
              <option value="maintenance">Mantenimiento</option>
              <option value="readonly">Solo lectura</option>
              <option value="suspended">Suspendidos</option>
            </select>
          </div>

          <div className="cc-instance-strip">
            {visibleInstances.map((instance) => (
              <button
                type="button"
                className={`cc-instance-chip ${instance.id === selectedInstanceId ? 'is-active' : ''}`}
                key={instance.id}
                onClick={() => setSelectedInstanceId(instance.id)}
              >
                <span>{instance.name}</span>
                <StatePill state={instance.status} />
              </button>
            ))}
          </div>

          {selectedInstance ? (
            <>
              <div className="cc-detail-head">
                <div className="cc-titleline">
                  <span className="cc-avatar cc-avatar-lg" style={{ backgroundColor: selectedInstance.organization_color || '#006b75' }}>
                    {selectedInstance.organization_initials || selectedOrganization?.initials}
                  </span>
                  <div>
                    <p className="cc-eyebrow">{selectedOrganization?.name || selectedInstance.organization_name} · {selectedInstance.environment}</p>
                    <h2>{selectedInstance.name}</h2>
                    <span>{selectedInstance.domain}</span>
                  </div>
                </div>
                <StatePill state={selectedInstance.status} />
              </div>

              <div className="cc-meta-grid">
                <Meta label="Servidor" value={selectedInstance.server_label} />
                <Meta label="Base de datos" value={selectedInstance.database_name} />
                <Meta label="Version" value={selectedInstance.current_version} />
                <Meta label="Release channel" value={selectedInstance.release_channel} />
              </div>

              <div className="cc-section">
                <div className="cc-panel-head compact">
                  <div>
                    <p className="cc-eyebrow">Estado operativo por instancia</p>
                    <h3>{stateMeta[detail?.state?.state || selectedInstance.status]?.label}</h3>
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
                    <p className="cc-eyebrow">Feature flags por instancia</p>
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
            <div className="cc-empty">Selecciona una instancia.</div>
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
              <div className="cc-service-row" key={`${service.instance_id}-${service.key}`}>
                <i className={`mdi ${service.icon || 'mdi-server'}`} aria-hidden="true" />
                <span>
                  <strong>{service.name}</strong>
                  <small>{service.instance_name || selectedInstance?.name}</small>
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
                  <small>{entry.actor_name || 'Sistema'} · {entry.instance_name || entry.organization_name || 'Global'}</small>
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
        {['Overview', 'Organizaciones', 'Instancias', 'Estado', 'Features', 'Servicios', 'Deploys', 'Consumo', 'Auditoria'].map((item, index) => (
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
if (root) createRoot(root).render(<App />);
