import React from 'react';
import { fillVars, waFormat } from '../util';

export function PhonePreview({ nodes, edges, selectedNodeId, flowName, simulationResult }) {
    const scopedNodes = selectPreviewNodes(nodes, edges, selectedNodeId);
    const messages = scopedNodes
        .filter((node) => node.type === 'message')
        .map((node) => node.data?.settings?.body || node.data?.action?.message?.body || '')
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
                    {messages.length === 0 && (
                        <div className="fm-wa-restart">Agrega un mensaje para ver la conversación aquí.</div>
                    )}
                    {messages.map((message, index) => (
                        <div
                            key={`${message}-${index}`}
                            className="fm-wa-msg bot fm-md"
                            dangerouslySetInnerHTML={{ __html: waFormat(fillVars(message)) }}
                        />
                    ))}
                </div>
                <div className="fm-wa-input">
                    <input placeholder="Escribe un mensaje" readOnly />
                    <button className="fm-wa-send" type="button"><span className="mdi mdi-microphone" /></button>
                </div>
            </div>
        </aside>
    );
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
