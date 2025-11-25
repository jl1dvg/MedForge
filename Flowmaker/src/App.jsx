import React, { useEffect, useMemo, useState } from 'react';

const ENDPOINT_BASES = ['/whatsapp/flowmaker', '/whatsapp/api/flowmaker'];

function formatJson(value) {
    return JSON.stringify(value, null, 2);
}

function parseJson(value) {
    try {
        return JSON.parse(value);
    } catch (error) {
        console.error('No se pudo convertir el JSON del flujo', error);
        return null;
    }
}

export default function App() {
    const bootstrapData = useMemo(() => (typeof window !== 'undefined' ? window.data || {} : {}), []);
    const [activeBase, setActiveBase] = useState(null);
    const [contract, setContract] = useState(null);
    const [flowDraft, setFlowDraft] = useState(formatJson(bootstrapData.flow || {}));
    const [status, setStatus] = useState('');
    const [error, setError] = useState('');

    useEffect(() => {
        syncContract();
    }, []);

    async function syncContract() {
        setStatus('Sincronizando contrato...');
        setError('');

        for (const candidate of ENDPOINT_BASES) {
            try {
                const response = await fetch(`${candidate}/contract`);
                if (!response.ok) {
                    continue;
                }

                const payload = await response.json();
                setContract(payload);
                setActiveBase(candidate);
                setFlowDraft(formatJson(payload.flow || payload));
                setStatus(`Contrato obtenido desde ${candidate}.`);
                return;
            } catch (candidateError) {
                console.warn('No fue posible obtener el contrato desde', candidate, candidateError);
            }
        }

        setStatus('');
        setError('No fue posible obtener el contrato del backend. Revisa que el servidor de MedForge esté corriendo.');
    }

    async function publishFlow() {
        setStatus('Publicando flujo...');
        setError('');

        const parsedFlow = parseJson(flowDraft);
        if (!parsedFlow) {
            setStatus('');
            setError('El flujo no es un JSON válido. Corrige el contenido y vuelve a intentarlo.');
            return;
        }

        const base = activeBase || ENDPOINT_BASES[0];

        try {
            const response = await fetch(`${base}/publish`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ flow: parsedFlow }),
            });

            const payload = await response.json();
            if (!response.ok || (payload.status && payload.status !== 'ok')) {
                throw new Error(payload.message || 'El backend respondió con un error.');
            }

            setStatus('El flujo se publicó correctamente.');
            setContract((current) => ({ ...(current || {}), flow: payload.flow || parsedFlow }));
        } catch (publishError) {
            console.error('Error al publicar el flujo', publishError);
            setStatus('');
            setError(publishError.message || 'No fue posible publicar el flujo.');
        }
    }

    return (
        <div className="app">
            <header className="app__header">
                <div>
                    <p className="app__eyebrow">Flowmaker local</p>
                    <h1 className="app__title">Flowmaker embebido</h1>
                    <p className="app__subtitle">
                        Este build se sirve desde tu entorno local para probar la integración de WhatsApp.
                    </p>
                </div>
                <div className="app__status">
                    <span className={`status-dot ${activeBase ? 'status-dot--ok' : 'status-dot--warn'}`}></span>
                    <span>{activeBase ? `Usando ${activeBase}` : 'Sincroniza para detectar el endpoint'}</span>
                </div>
            </header>

            <div className="app__grid">
                <section className="panel">
                    <div className="panel__header">
                        <div>
                            <p className="panel__eyebrow">1. Contrato</p>
                            <h2 className="panel__title">Descarga del flujo activo</h2>
                        </div>
                        <button className="button button--ghost" type="button" onClick={syncContract}>
                            Volver a sincronizar
                        </button>
                    </div>
                    <p className="panel__body">
                        Se consulta primero <code>/whatsapp/flowmaker/contract</code> y luego
                        <code>/whatsapp/api/flowmaker/contract</code> para mantener compatibilidad con los endpoints existentes.
                    </p>
                    <pre className="code-block" aria-label="Contrato">
                        {contract ? formatJson(contract) : 'Aún no se ha descargado el contrato.'}
                    </pre>
                </section>

                <section className="panel">
                    <div className="panel__header">
                        <div>
                            <p className="panel__eyebrow">2. Publicación</p>
                            <h2 className="panel__title">Envía el flujo al backend</h2>
                        </div>
                        <button className="button" type="button" onClick={publishFlow}>
                            Publicar flujo
                        </button>
                    </div>
                    <p className="panel__body">
                        Se envía el JSON al endpoint disponible (<code>/whatsapp/flowmaker/publish</code> o
                        <code>/whatsapp/api/flowmaker/publish</code>), conservando la estructura que espera MedForge.
                    </p>
                    <textarea
                        className="textarea"
                        value={flowDraft}
                        onChange={(event) => setFlowDraft(event.target.value)}
                        rows={18}
                        spellCheck={false}
                    ></textarea>
                </section>
            </div>

            {(status || error) && (
                <div className={`notice ${error ? 'notice--error' : 'notice--success'}`}>
                    {error || status}
                </div>
            )}
        </div>
    );
}
