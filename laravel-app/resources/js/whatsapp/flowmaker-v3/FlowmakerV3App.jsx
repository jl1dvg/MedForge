import React, { useEffect, useMemo, useState } from 'react';
import { flowmakerApi } from './flowmakerApi';
import { contractToGraph } from './graphAdapter';
import { graphToFlow } from './graphCompiler';
import { validateGraph } from './flowValidator';
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
    const validation = useMemo(() => graph ? validateGraph(graph) : { errors: [], warnings: [], issues: [] }, [graph]);
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

        if (validation.errors.length > 0) {
            const first = validation.errors[0];
            setSelectedNodeId(first.nodeId || null);
            setSelectedEdgeId(first.edgeId || null);
            setActionStatus(`Corrige ${validation.errors.length} error(es) antes de publicar.`);
            return;
        }

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

    function addEdge(source, target, sourceHandle = 'source') {
        if (!source || !target || source === target) return;
        updateGraph((current) => ({
            ...current,
            edges: [
                ...current.edges.filter((edge) => !(edge.source === source && (edge.sourceHandle || 'source') === sourceHandle)),
                { id: uid('edge'), source, sourceHandle, target, targetHandle: 'in' },
            ],
        }));
        setSelectedEdgeId(null);
    }

    function updateEdges(nextEdges) {
        updateGraph((current) => ({
            ...current,
            edges: nextEdges,
        }));
    }

    function deleteEdge(id) {
        updateGraph((current) => ({
            ...current,
            edges: current.edges.filter((edge) => edge.id !== id),
        }));
        if (selectedEdgeId === id) {
            setSelectedEdgeId(null);
        }
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
                <div
                    className="fm-main"
                    style={{
                        '--palette-w': '204px',
                        '--inspect-w': '300px',
                        '--phone-w': '300px',
                        gridTemplateColumns: selectedNode
                            ? 'var(--palette-w) 1fr var(--inspect-w) var(--phone-w)'
                            : 'var(--palette-w) 1fr var(--phone-w)',
                    }}
                >
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
                        onDeleteEdge={deleteEdge}
                        onDeleteNode={deleteNode}
                        onDropNode={addNode}
                        edgeStyle="bezier"
                        showMinimap
                    />
                    {(validation.errors.length > 0 || validation.warnings.length > 0) && (
                        <ValidationPanel
                            validation={validation}
                            onSelect={(issue) => {
                                if (issue.nodeId) {
                                    setSelectedNodeId(issue.nodeId);
                                    setSelectedEdgeId(null);
                                }
                                if (issue.edgeId) {
                                    setSelectedEdgeId(issue.edgeId);
                                    setSelectedNodeId(null);
                                }
                            }}
                        />
                    )}
                    {selectedNode && (
                        <NodeInspector
                            node={selectedNode}
                            nodes={nodes}
                            edges={edges}
                            catalogs={graph?.catalogs || {}}
                            onUpdate={updateNode}
                            onDelete={deleteNode}
                            onEdgesChange={updateEdges}
                        />
                    )}
                    <PhonePreview
                        nodes={nodes}
                        edges={edges}
                        selectedNodeId={selectedNodeId}
                        flowName={graph?.flowName}
                        simulationResult={simulationResult}
                    />
                </div>
            )}
        </main>
    );
}

function ValidationPanel({ validation, onSelect }) {
    const groupedIssues = groupValidationIssues(validation.issues);
    const issues = groupedIssues.slice(0, 6);

    return (
        <aside className="fm-validation-panel" aria-label="Validación del flujo">
            <div className="fm-validation-head">
                <b><span className="mdi mdi-shield-check-outline" /> Validación</b>
                <span>{validation.errors.length} errores · {validation.warnings.length} avisos</span>
            </div>
            <div className="fm-validation-list">
                {issues.map((issue, index) => (
                    <button
                        key={`${issue.level}-${issue.nodeId || issue.edgeId || index}-${issue.message}`}
                        type="button"
                        className={`fm-validation-item ${issue.level}`}
                        onClick={() => onSelect(issue)}
                    >
                        <span className={`mdi ${issue.level === 'error' ? 'mdi-alert-circle-outline' : 'mdi-alert-outline'}`} />
                        <span>{issue.message}{issue.count > 1 ? ` (${issue.count})` : ''}</span>
                    </button>
                ))}
                {groupedIssues.length > issues.length && (
                    <div className="fm-validation-more">+{groupedIssues.length - issues.length} grupos pendientes más</div>
                )}
            </div>
        </aside>
    );
}

function groupValidationIssues(issues) {
    const groups = new Map();

    issues.forEach((issue) => {
        const key = `${issue.level}:${issue.nodeId || ''}:${issue.edgeId || ''}:${issue.message}`;
        const current = groups.get(key);
        if (current) {
            current.count += 1;
            return;
        }
        groups.set(key, { ...issue, count: 1 });
    });

    return Array.from(groups.values());
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
        return { actionType: 'send_buttons', settings: { body: 'Elige una opción', buttons: ['Opción 1', 'Opción 2'] } };
    }

    if (type === 'branch') {
        return { actionType: 'conditional', settings: { condition: { type: 'always' } } };
    }

    if (type === 'state') {
        return { actionType: 'set_state', settings: { state: '' } };
    }

    if (type === 'consent') {
        return { actionType: 'store_consent', settings: { consent_type: 'datos_protegidos', granted: true } };
    }

    if (type === 'sigcenter_agenda') {
        return { actionType: 'sigcenter_agenda', settings: { operation: 'list_specialties', send_result: true } };
    }

    if (type === 'handoff') {
        return { actionType: 'handoff_agent', settings: { reason: '' } };
    }

    if (type === 'ai_agent') {
        return { actionType: 'ai_agent', settings: { instructions: '', handoff: true } };
    }

    if (type === 'end') {
        return { actionType: 'handoff_agent', settings: { action: 'end' } };
    }

    return { settings: {} };
}
