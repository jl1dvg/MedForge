import React from 'react';
import { fillVars, waFormat } from '../util';

export function PhonePreview({ nodes, edges, selectedNodeId, flowName, simulationResult }) {
    const scopedNodes = selectPreviewNodes(nodes, edges, selectedNodeId);
    const previewItems = scopedNodes
        .map(nodeToPreviewItem)
        .filter(Boolean);
    const simulatedMessages = Array.isArray(simulationResult?.actions)
        ? simulationResult.actions
            .map((action) => action?.outbound_message?.body || action?.outbound_message?.text || action?.message?.body || '')
            .filter(Boolean)
        : [];

    return (
        <aside className="fm-phone-pane">
            <div className="fm-phone-head">
                <b><span className="mdi mdi-whatsapp" /> Vista previa</b>
            </div>
            {simulationResult && <SimulationTrace simulationResult={simulationResult} />}
            <div className="fm-phone">
                <div className="fm-wa-top">
                    <div className="fm-wa-av"><span className="mdi mdi-robot-happy" /></div>
                    <div className="who">
                        <b>{flowName || 'CIVE · Bot'}</b>
                        <small>preview</small>
                    </div>
                </div>
                <div className="fm-wa-body">
                    {simulationResult && (
                        <div className="fm-wa-restart">
                            Simulación backend: {simulationResult.matched ? simulationResult.scenario?.name || 'escenario encontrado' : 'sin match'}
                        </div>
                    )}
                    {simulatedMessages.map((message, index) => (
                        <div
                            key={`simulation-${index}-${message}`}
                            className="fm-wa-msg bot fm-md"
                            dangerouslySetInnerHTML={{ __html: waFormat(fillVars(message)) }}
                        />
                    ))}
                    {previewItems.length === 0 && (
                        <div className="fm-wa-restart">Agrega un mensaje para ver la conversación aquí.</div>
                    )}
                    {previewItems.map((item, index) => <PreviewBubble item={item} key={`${item.type}-${index}-${item.body}`} />)}
                </div>
                <div className="fm-wa-input">
                    <input placeholder="Escribe un mensaje" readOnly />
                    <button className="fm-wa-send" type="button"><span className="mdi mdi-microphone" /></button>
                </div>
            </div>
        </aside>
    );
}

function PreviewBubble({ item }) {
    return (
        <div className="fm-wa-msg bot fm-md">
            {item.header && <div className="hdr">{item.header}</div>}
            <div dangerouslySetInnerHTML={{ __html: waFormat(fillVars(item.body)) }} />
            {item.footer && <div className="ftr">{item.footer}</div>}
            {item.buttons.length > 0 && (
                <div className="fm-wa-qr-btns">
                    {item.buttons.slice(0, 10).map((button) => (
                        <button type="button" key={button.id || button.title}>{button.title}</button>
                    ))}
                </div>
            )}
        </div>
    );
}

function nodeToPreviewItem(node) {
    const actionType = node.data?.actionType || node.data?.action?.type || '';
    const settings = node.data?.settings || {};
    const actionMessage = node.data?.action?.message || {};

    if (node.type === 'message' || actionType === 'send_message') {
        const body = settings.body || actionMessage.body || '';
        return body ? { type: 'message', body, header: '', footer: '', buttons: [] } : null;
    }

    if (actionType === 'send_buttons') {
        const buttons = Array.isArray(settings.buttons) ? settings.buttons : actionMessage.buttons || [];
        const body = settings.body || actionMessage.body || '';
        return {
            type: 'buttons',
            body,
            header: settings.header || actionMessage.header || '',
            footer: settings.footer || actionMessage.footer || '',
            buttons: buttons.map((button, index) => ({
                id: button?.id || `button_${index + 1}`,
                title: button?.title || button?.label || button?.text || String(button),
            })).filter((button) => button.title),
        };
    }

    if (actionType === 'send_list') {
        const sections = Array.isArray(settings.sections) ? settings.sections : actionMessage.sections || [];
        const rows = sections.flatMap((section) => Array.isArray(section.rows) ? section.rows : []);
        const body = settings.body || actionMessage.body || '';
        return {
            type: 'list',
            body,
            header: settings.header || actionMessage.header || '',
            footer: settings.footer || actionMessage.footer || '',
            buttons: rows.map((row, index) => ({
                id: row?.id || `row_${index + 1}`,
                title: row?.title || row?.label || row?.text || `Opción ${index + 1}`,
            })),
        };
    }

    return null;
}

function SimulationTrace({ simulationResult }) {
    const actions = Array.isArray(simulationResult?.actions) ? simulationResult.actions : [];
    const scenarioName = simulationResult?.scenario?.name || simulationResult?.scenario?.id || 'Sin escenario';

    return (
        <div className="fm-sim-trace">
            <div className="fm-sim-head">
                <b><span className="mdi mdi-routes" /> Trace</b>
                <span className={simulationResult?.matched ? 'ok' : 'warn'}>
                    {simulationResult?.matched ? 'match' : 'sin match'}
                </span>
            </div>
            <div className="fm-sim-scenario">{scenarioName}</div>
            <div className="fm-sim-actions">
                {actions.length === 0 && <span>No se ejecutaron acciones.</span>}
                {actions.slice(0, 8).map((action, index) => (
                    <div className="fm-sim-action" key={`${action.type || 'action'}-${index}`}>
                        <span>{index + 1}</span>
                        <b>{action.type || action.action_type || 'acción'}</b>
                        <small>{traceLabel(action)}</small>
                    </div>
                ))}
                {actions.length > 8 && <span>+{actions.length - 8} acciones más</span>}
            </div>
        </div>
    );
}

function traceLabel(action) {
    if (action.route_handle) return `ruta ${action.route_handle}`;
    if (action.branch) return `rama ${action.branch}`;
    if (action.operation) return action.operation;
    if (action.outbound_message?.body) return action.outbound_message.body.slice(0, 52);
    if (action.message?.body) return action.message.body.slice(0, 52);
    if (action.state) return `estado ${action.state}`;
    return 'ejecutada';
}

function selectPreviewNodes(nodes, edges, selectedNodeId) {
    if (!selectedNodeId) {
        return nodes;
    }

    const byId = new Map(nodes.map((node) => [node.id, node]));
    const selected = byId.get(selectedNodeId);
    if (!selected) {
        return nodes;
    }

    const start = selected.type === 'keyword_trigger' || selected.type === 'incoming_message'
        ? selected
        : findInboundTrigger(selected.id, nodes, edges) || selected;

    const outgoing = new Map();
    edges.forEach((edge) => {
        if (!outgoing.has(edge.source)) outgoing.set(edge.source, []);
        outgoing.get(edge.source).push(edge.target);
    });

    const seen = new Set();
    const ordered = [];
    const queue = [start.id];

    while (queue.length > 0 && ordered.length < 25) {
        const id = queue.shift();
        if (seen.has(id)) continue;
        seen.add(id);

        const node = byId.get(id);
        if (!node) continue;
        ordered.push(node);
        queue.push(...(outgoing.get(id) || []));
    }

    return ordered;
}

function findInboundTrigger(nodeId, nodes, edges) {
    const byId = new Map(nodes.map((node) => [node.id, node]));
    const inbound = new Map();
    edges.forEach((edge) => inbound.set(edge.target, edge.source));

    let current = nodeId;
    const seen = new Set();

    while (current && !seen.has(current)) {
        seen.add(current);
        const node = byId.get(current);
        if (node?.type === 'keyword_trigger' || node?.type === 'incoming_message') {
            return node;
        }
        current = inbound.get(current);
    }

    return null;
}
