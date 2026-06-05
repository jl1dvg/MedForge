import React from 'react';
import { NODE_TYPES } from '../domain';

export function NodeInspector({ node, onUpdate, onDelete }) {
    if (!node) {
        return (
            <aside className="fm-inspector">
                <div className="fm-empty-insp">
                    <span className="mdi mdi-cursor-default-click-outline" />
                    <p>Selecciona un nodo para editar su contenido.</p>
                </div>
            </aside>
        );
    }

    const meta = NODE_TYPES[node.type] || NODE_TYPES.message;
    const settings = node.data?.settings || {};
    const action = node.data?.action || {};
    const messageBody = settings.body ?? action.message?.body ?? '';

    function patchData(patch) {
        onUpdate(node.id, { data: { ...node.data, ...patch } });
    }

    function patchSettings(patch) {
        patchData({ settings: { ...settings, ...patch } });
    }

    return (
        <aside className="fm-inspector">
            <div className="fm-insp-head">
                <div className="fm-insp-ic" style={{ background: `var(--nt-${meta.accent})` }}>
                    <span className={`mdi ${meta.icon || 'mdi-pencil'}`} />
                </div>
                <div>
                    <h4>{meta.label}</h4>
                    <p>{meta.desc}</p>
                </div>
            </div>
            <div className="fm-insp-body">
                {meta.isTrigger && (
                    <>
                        <Field label="Nombre del escenario">
                            <input
                                className="fm-input"
                                value={node.data?.name || ''}
                                onChange={(event) => patchData({ name: event.target.value })}
                            />
                        </Field>
                        <Field label="Palabras clave">
                            <textarea
                                className="fm-textarea"
                                value={(node.data?.keywords || []).map((keyword) => keyword.value).join('\n')}
                                onChange={(event) => patchData({
                                    keywords: event.target.value.split('\n').filter(Boolean).map((value, index) => ({
                                        id: `kw_${index + 1}`,
                                        value,
                                        matchType: 'contains',
                                    })),
                                })}
                            />
                        </Field>
                    </>
                )}

                {node.type === 'message' && (
                    <Field label="Texto del mensaje">
                        <textarea
                            className="fm-textarea"
                            value={messageBody}
                            onChange={(event) => patchSettings({ body: event.target.value })}
                        />
                    </Field>
                )}

                {node.type === 'quick_replies' && (
                    <>
                        <Field label="Texto principal">
                            <textarea
                                className="fm-textarea"
                                value={settings.body || ''}
                                onChange={(event) => patchSettings({ body: event.target.value })}
                            />
                        </Field>
                        {[0, 1, 2].map((index) => (
                            <Field key={index} label={`Botón ${index + 1}`}>
                                <input
                                    className="fm-input"
                                    value={(settings.buttons || [])[index] || ''}
                                    onChange={(event) => {
                                        const buttons = [...(settings.buttons || [])];
                                        buttons[index] = event.target.value;
                                        patchSettings({ buttons: buttons.filter(Boolean) });
                                    }}
                                />
                            </Field>
                        ))}
                    </>
                )}

                {!meta.isTrigger && node.type !== 'message' && node.type !== 'quick_replies' && (
                    <Field label="Configuración JSON">
                        <textarea
                            className="fm-textarea"
                            value={JSON.stringify(settings, null, 2)}
                            onChange={(event) => {
                                try {
                                    patchData({ settings: JSON.parse(event.target.value || '{}') });
                                } catch {
                                    patchSettings({ raw: event.target.value });
                                }
                            }}
                        />
                    </Field>
                )}

                <div className="fm-danger-zone">
                    <button className="fm-btn fm-btn-danger" type="button" onClick={() => onDelete(node.id)}>
                        <span className="mdi mdi-delete-outline" /> Eliminar nodo
                    </button>
                </div>
            </div>
        </aside>
    );
}

function Field({ label, children }) {
    return (
        <div className="fm-field">
            <label>{label}</label>
            {children}
        </div>
    );
}
