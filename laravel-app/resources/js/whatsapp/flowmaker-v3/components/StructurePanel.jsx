import React from 'react';

export function StructurePanel({ graph, compiled }) {
    if (!graph) return null;

    return (
        <div className="fm-modal-backdrop">
            <div className="fm-modal">
                <div className="fm-modal-head">
                    <div>
                        <h3>Estructura del flujo</h3>
                        <p>{graph.nodes.length} nodos · {graph.edges.length} conexiones</p>
                    </div>
                </div>
                <div className="fm-modal-body">
                    <pre className="fm-json">{JSON.stringify(compiled || graph, null, 2)}</pre>
                </div>
            </div>
        </div>
    );
}
