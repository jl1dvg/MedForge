import React from 'react';
import { nodeOutputHandles } from '../actionCatalog';
import { NODE_TYPES } from '../domain';

export function NodeCard({
    node,
    selected,
    connectedHandles = new Set(),
    connectingHandle,
    onSelect,
    onDelete,
    onDragStart,
    onConnectStart,
    onConnectEnd,
}) {
    const meta = NODE_TYPES[node.type] || NODE_TYPES.message;
    const title = node.data?.name
        || node.data?.action?.message?.body
        || node.data?.settings?.body
        || meta.label;
    const detail = node.data?.keywords?.map((keyword) => keyword.value).filter(Boolean).join(', ')
        || node.data?.action?.type
        || meta.desc;
    const handles = nodeOutputHandles(node);

    return (
        <div
            className={`fm-node ${selected ? 'selected sel' : ''}`}
            data-node={node.id}
            style={{
                transform: `translate(${node.position.x}px, ${node.position.y}px)`,
                borderColor: `var(--nt-${meta.accent})`,
                '--accent': `var(--nt-${meta.accent})`,
            }}
            onMouseDown={(event) => onDragStart(event, node)}
            onClick={(event) => {
                event.stopPropagation();
                onSelect(node.id);
            }}
        >
            <button
                type="button"
                className={`fm-handle in ${connectingHandle ? 'drop-ok' : ''}`}
                title="Conectar aquí"
                onMouseDown={(event) => event.stopPropagation()}
                onMouseUp={(event) => {
                    event.stopPropagation();
                    onConnectEnd?.(node.id);
                }}
            />
            <div className="fm-node-head">
                <div className="fm-node-ic" style={{ background: `var(--nt-${meta.accent})` }}>
                    <span className={`mdi ${meta.icon || 'mdi-dots-horizontal'}`} />
                </div>
                <div>
                    <b>{meta.label}</b>
                    <span>{node.data?.stage || node.data?.scenarioId || 'acción'}</span>
                </div>
                <button
                    type="button"
                    className="fm-node-del"
                    title="Eliminar"
                    onMouseDown={(event) => event.stopPropagation()}
                    onClick={(event) => {
                        event.stopPropagation();
                        onDelete(node.id);
                    }}
                >
                    <span className="mdi mdi-close" />
                </button>
            </div>
            <div className="fm-node-body">
                <div className="fm-node-title">{String(title).slice(0, 80)}</div>
                <div className="fm-node-text">{String(detail || '').slice(0, 120)}</div>
            </div>
            {handles.length > 0 && (
                <div className="fm-outs">
                    {handles.map((handle) => (
                        <div className={`fm-out-row ${connectedHandles.has(handle.id) ? 'connected' : ''}`} key={handle.id}>
                            <span className="fm-out-label">{handle.label}</span>
                            <span className="fm-out-dot" />
                            <button
                                type="button"
                                className={`fm-handle out ${connectingHandle?.nodeId === node.id && connectingHandle?.handleId === handle.id ? 'armed' : ''}`}
                                title={`Conectar: ${handle.label}`}
                                onMouseDown={(event) => {
                                    event.stopPropagation();
                                    onConnectStart?.(event, node.id, handle.id);
                                }}
                            />
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
