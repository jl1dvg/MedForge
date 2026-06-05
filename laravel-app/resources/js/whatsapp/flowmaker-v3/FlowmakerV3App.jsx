import React, { useEffect, useMemo, useState } from 'react';
import { flowmakerApi } from './flowmakerApi';
import { contractToGraph } from './graphAdapter';
import { graphToFlow } from './graphCompiler';
import { createNode } from './domain';
import { FlowCanvas } from './components/FlowCanvas';
import { NodeInspector } from './components/NodeInspector';
import { NodePalette } from './components/NodePalette';
import { PhonePreview } from './components/PhonePreview';
import { uid } from './util';

export function FlowmakerV3App() {
    const api = useMemo(() => flowmakerApi(), []);
    const [status, setStatus] = useState('loading');
    const [graph, setGraph] = useState(null);
    const [error, setError] = useState('');
    const [selectedNodeId, setSelectedNodeId] = useState(null);
    const [selectedEdgeId, setSelectedEdgeId] = useState(null);
    const [simulationResult, setSimulationResult] = useState(null);
    const [simulationText, setSimulationText] = useState('hola');
    const [simulationNumber, setSimulationNumber] = useState('593999111222');
    const [actionStatus, setActionStatus] = useState('');
    const [isBusy, setIsBusy] = useState(false);

    useEffect(() => {
        let alive = true;

        api.contract()
            .then((payload) => {
                if (!alive) return;
                setGraph(contractToGraph(payload));
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

    const nodes = graph?.nodes || [];
    const edges = graph?.edges || [];
    const selectedNode = nodes.find((node) => node.id === selectedNodeId) || null;
    const selectedScenarioId = selectedNode?.data?.scenarioId || selectedNode?.data?.name || '';

    async function simulateFlow() {
        if (!graph || isBusy) return;

        setIsBusy(true);
        setActionStatus('Simulando con backend...');
        setSimulationResult(null);

        try {
            const flow = graphToFlow(graph);
            const payload = await api.simulate({
                flow,
                scenario_id: selectedScenarioId,
                wa_number: simulationNumber,
                text: simulationText,
                context: '{}',
            });
            setSimulationResult(payload);
            setActionStatus(payload?.matched ? 'Simulación lista: escenario encontrado.' : 'Simulación lista: sin match.');
        } catch (err) {
            setActionStatus(err?.message || 'No se pudo simular el flujo.');
        } finally {
            setIsBusy(false);
        }
    }

    async function publishFlow() {
        if (!graph || isBusy) return;

        setIsBusy(true);
        setActionStatus('Publicando versión...');

        try {
            const payload = await api.publish(graphToFlow(graph));
            setActionStatus(payload?.message || 'Flujo publicado.');
        } catch (err) {
            setActionStatus(err?.message || 'No se pudo publicar el flujo.');
        } finally {
            setIsBusy(false);
        }
    }

    function updateGraph(updater) {
        setGraph((current) => {
            if (!current) return current;
            return updater(current);
        });
    }

    function addNode(type, position = { x: 520, y: 240 }) {
        const node = createNode(type, position, defaultDataForType(type));
        updateGraph((current) => ({
            ...current,
            nodes: [...current.nodes, node],
        }));
        setSelectedNodeId(node.id);
        setSelectedEdgeId(null);
    }

    function moveNode(id, x, y) {
        updateGraph((current) => ({
            ...current,
            nodes: current.nodes.map((node) => node.id === id ? { ...node, position: { x, y } } : node),
        }));
    }

    function updateNode(id, patch) {
        updateGraph((current) => ({
            ...current,
            nodes: current.nodes.map((node) => node.id === id ? { ...node, ...patch } : node),
        }));
    }

    function deleteNode(id) {
        updateGraph((current) => ({
            ...current,
            nodes: current.nodes.filter((node) => node.id !== id),
            edges: current.edges.filter((edge) => edge.source !== id && edge.target !== id),
        }));
        if (selectedNodeId === id) {
            setSelectedNodeId(null);
        }
    }

    function addEdge(source, target) {
        if (!source || !target || source === target) return;
        updateGraph((current) => ({
            ...current,
            edges: [
                ...current.edges.filter((edge) => !(edge.source === source && edge.target === target)),
                { id: uid('edge'), source, sourceHandle: 'source', target, targetHandle: 'in' },
            ],
        }));
    }

    return (
        <main className="fm-app">
            <header className="fm-topbar">
                <div className="fm-brand">Flowmaker V3</div>
                <div className="fm-flowname">{graph?.flowName || 'Flowmaker'}</div>
                {status === 'ready' && (
                    <div className="fm-runtime-tools">
                        <input
                            className="fm-runtime-input fm-runtime-phone"
                            value={simulationNumber}
                            aria-label="Número de prueba"
                            onChange={(event) => setSimulationNumber(event.target.value)}
                        />
                        <input
                            className="fm-runtime-input"
                            value={simulationText}
                            aria-label="Texto de simulación"
                            onChange={(event) => setSimulationText(event.target.value)}
                        />
                        <button className="fm-btn" type="button" onClick={simulateFlow} disabled={isBusy}>
                            <span className="mdi mdi-play-circle-outline" /> Simular
                        </button>
                        <button className="fm-btn fm-btn-primary" type="button" onClick={publishFlow} disabled={isBusy}>
                            <span className="mdi mdi-cloud-upload-outline" /> Publicar
                        </button>
                    </div>
                )}
                {actionStatus && (
                    <span className={`fm-flow-status ${actionStatus.includes('lista') || actionStatus.includes('public') ? 'saved' : ''}`}>
                        <span className="dot" /> {actionStatus}
                    </span>
                )}
                <div className="fm-spacer" />
                <a className="fm-btn" href={api.fallbackV2}>Volver a V2</a>
            </header>

            {status === 'loading' && <section className="fm-loading">Cargando contrato real...</section>}
            {status === 'error' && <section className="fm-error">{error}</section>}
            {status === 'ready' && (
                <div className="fm-main" style={{ gridTemplateColumns: selectedNode ? '240px 1fr 316px 332px' : '240px 1fr 332px' }}>
                    <NodePalette onAdd={addNode} />
                    <FlowCanvas
                        nodes={nodes}
                        edges={edges}
                        selectedNodeId={selectedNodeId}
                        selectedEdgeId={selectedEdgeId}
                        onSelectNode={(id) => {
                            setSelectedNodeId(id);
                            setSelectedEdgeId(null);
                        }}
                        onSelectEdge={(id) => {
                            setSelectedEdgeId(id);
                            setSelectedNodeId(null);
                        }}
                        onClearSelection={() => {
                            setSelectedNodeId(null);
                            setSelectedEdgeId(null);
                        }}
                        onMoveNode={moveNode}
                        onAddEdge={addEdge}
                        onDeleteEdge={() => {}}
                        onDeleteNode={deleteNode}
                        onDropNode={addNode}
                        edgeStyle="bezier"
                        showMinimap
                    />
                    {selectedNode && (
                        <NodeInspector
                            node={selectedNode}
                            onUpdate={updateNode}
                            onDelete={deleteNode}
                        />
                    )}
                    <PhonePreview nodes={nodes} edges={edges} flowName={graph?.flowName} simulationResult={simulationResult} />
                </div>
            )}
        </main>
    );
}

function defaultDataForType(type) {
    if (type === 'keyword_trigger') {
        return {
            scenarioId: uid('scenario'),
            name: 'Nuevo escenario',
            status: 'published',
            stage: 'custom',
            keywords: [{ id: uid('kw'), value: 'hola', matchType: 'contains' }],
        };
    }

    if (type === 'message') {
        return { settings: { body: 'Nuevo mensaje' } };
    }

    if (type === 'quick_replies') {
        return { settings: { body: 'Elige una opción', buttons: ['Opción 1', 'Opción 2'] } };
    }

    if (type === 'end') {
        return { settings: { action: 'end' } };
    }

    return { settings: {} };
}
