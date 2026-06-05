import React, { useEffect, useMemo, useState } from 'react';
import { flowmakerApi } from './flowmakerApi';

export function FlowmakerV3App() {
    const api = useMemo(() => flowmakerApi(), []);
    const [status, setStatus] = useState('loading');
    const [contract, setContract] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        let alive = true;

        api.contract()
            .then((payload) => {
                if (!alive) return;
                setContract(payload);
                setStatus('ready');
            })
            .catch((err) => {
                if (!alive) return;
                setError(err.message || 'No se pudo cargar Flowmaker.');
                setStatus('error');
            });

        return () => {
            alive = false;
        };
    }, [api]);

    const flowName = contract?.schema?.name || 'Flowmaker';
    const scenarios = Array.isArray(contract?.schema?.scenarios) ? contract.schema.scenarios : [];

    return (
        <main className="fm-app">
            <header className="fm-topbar">
                <div className="fm-brand">Flowmaker V3</div>
                <div className="fm-flowname">{flowName}</div>
                <a className="fm-btn" href={api.fallbackV2}>Volver a V2</a>
            </header>

            {status === 'loading' && <section className="fm-loading">Cargando contrato real...</section>}
            {status === 'error' && <section className="fm-error">{error}</section>}
            {status === 'ready' && (
                <section className="fm-loading">
                    Contrato cargado: {scenarios.length} escenario{scenarios.length === 1 ? '' : 's'}
                </section>
            )}
        </main>
    );
}
