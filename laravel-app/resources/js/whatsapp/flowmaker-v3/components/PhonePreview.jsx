import React from 'react';
import { fillVars, waFormat } from '../util';

export function PhonePreview({ nodes, flowName, simulationResult }) {
    const messages = nodes
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
