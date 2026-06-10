import React from 'react';
import { NODE_TYPES } from '../domain';

const ORDER = ['Disparadores', 'Enviar', 'Interacción', 'Lógica', 'Inteligencia Artificial'];

export function NodePalette({ onAdd }) {
    const categories = Object.entries(NODE_TYPES).reduce((acc, [type, meta]) => {
        acc[meta.cat] = acc[meta.cat] || [];
        acc[meta.cat].push([type, meta]);
        return acc;
    }, {});

    return (
        <aside className="fm-palette">
            <p className="fm-palette-hint">Arrastra un bloque al lienzo o haz clic para agregarlo al centro.</p>
            {ORDER.map((category) => (
                <div key={category}>
                    <h6>{category}</h6>
                    {(categories[category] || []).map(([type, meta]) => (
                        <button
                            key={type}
                            type="button"
                            className="fm-pal-item"
                            draggable
                            onDragStart={(event) => {
                                event.dataTransfer.setData('nodeType', type);
                                event.dataTransfer.effectAllowed = 'copy';
                            }}
                            onClick={() => onAdd(type)}
                        >
                            <div className="fm-pal-ic" style={{ background: `var(--nt-${meta.accent})` }}>
                                <span className={`mdi ${meta.icon || 'mdi-plus'}`} />
                            </div>
                            <div className="fm-pal-tx">
                                <b>{meta.label}</b>
                                <span>{meta.desc}</span>
                            </div>
                        </button>
                    ))}
                </div>
            ))}
        </aside>
    );
}
