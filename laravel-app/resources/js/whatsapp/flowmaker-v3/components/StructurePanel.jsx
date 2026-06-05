import React from 'react';

export function StructurePanel({ graph, compiled }) {
    if (!graph) return null;

    const flow = compiled || {};
    const scenarios = Array.isArray(flow.scenarios) ? flow.scenarios : [];

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
                    <div className="fm-structure-summary">
                        {scenarios.length === 0 && (
                            <p>Selecciona o publica una estructura para ver escenarios, condiciones y acciones.</p>
                        )}
                        {scenarios.map((scenario) => (
                            <section className="fm-structure-card" key={scenario.id}>
                                <div>
                                    <h4>{scenario.name || scenario.id}</h4>
                                    <span>{scenario.status || 'published'} · {scenario.stage || 'custom'}</span>
                                </div>
                                <p>{(scenario.conditions || []).length} condiciones · {(scenario.actions || []).length} acciones</p>
                            </section>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
