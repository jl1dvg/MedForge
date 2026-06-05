import React from 'react';
import { NODE_TYPES } from '../domain';

export function NodeCard({ node, selected, onSelect, onDelete, onDragStart }) {
    const meta = NODE_TYPES[node.type] || NODE_TYPES.message;
    const title = node.data?.name
        || node.data?.action?.message?.body
        || node.data?.settings?.body
        || meta.label;
    const detail = node.data?.keywords?.map((keyword) => keyword.value).filter(Boolean).join(', ')
        || node.data?.action?.type
        || meta.desc;

    return (
        <div
            className={`fm-node ${selected ? 'selected' : ''}`}
            data-node={node.id}
            style={{
                transform: `translate(${node.position.x}px, ${node.position.y}px)`,
                borderColor: `var(--nt-${meta.accent})`,
            }}
            onMouseDown={(event) => onDragStart(event, node)}
            onClick={(event) => {
                event.stopPropagation();
                onSelect(node.id);
            }}
        >
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
        </div>
    );
}
