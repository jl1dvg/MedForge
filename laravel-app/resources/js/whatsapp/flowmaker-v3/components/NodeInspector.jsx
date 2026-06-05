import React from 'react';
import { ACTION_TYPE_OPTIONS, STAGE_OPTIONS, STATUS_OPTIONS } from '../actionCatalog';
import { NODE_TYPES } from '../domain';

const SIGCENTER_OPERATIONS = [
    { value: 'list_specialties', label: 'Listar especialidades' },
    { value: 'list_doctors', label: 'Listar doctores' },
    { value: 'list_times', label: 'Listar horarios' },
    { value: 'book_appointment', label: 'Agendar cita' },
    { value: 'cancel_appointment', label: 'Cancelar cita' },
    { value: 'reschedule_appointment', label: 'Reagendar cita' },
];

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

    function patchData(patch) {
        onUpdate(node.id, { data: { ...node.data, ...patch } });
    }

    function patchSettings(patch) {
        patchData({ settings: { ...(node.data?.settings || {}), ...patch } });
    }

    return (
        <aside className="fm-inspector">
            <div className="fm-insp-head">
                <div className="fm-insp-ic" style={{ background: `var(--nt-${meta.accent})` }}>
                    <span className={`mdi ${meta.icon || 'mdi-pencil'}`} />
                </div>
                <div>
                    <h4>{meta.label}</h4>
                    <p>{node.data?.actionType || meta.desc}</p>
                </div>
            </div>
            <div className="fm-insp-body">
                {meta.isTrigger ? (
                    <TriggerEditor node={node} patchData={patchData} />
                ) : (
                    <ActionEditor node={node} patchData={patchData} patchSettings={patchSettings} />
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

function TriggerEditor({ node, patchData }) {
    const keywords = Array.isArray(node.data?.keywords) ? node.data.keywords : [];

    function updateKeyword(index, patch) {
        patchData({
            keywords: keywords.map((keyword, keywordIndex) => (
                keywordIndex === index ? { ...keyword, ...patch } : keyword
            )),
            conditionsEditedFromKeywords: true,
        });
    }

    return (
        <>
            <div className="fm-insp-section-title">Escenario</div>
            <Field label="Nombre">
                <input className="fm-input" value={node.data?.name || ''} onChange={(event) => patchData({ name: event.target.value })} />
            </Field>
            <div className="fm-row2">
                <Field label="Estado">
                    <select className="fm-select" value={node.data?.status || 'published'} onChange={(event) => patchData({ status: event.target.value })}>
                        {STATUS_OPTIONS.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                </Field>
                <Field label="Stage">
                    <select className="fm-select" value={node.data?.stage || 'custom'} onChange={(event) => patchData({ stage: event.target.value })}>
                        {STAGE_OPTIONS.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                </Field>
            </div>
            <CheckboxField
                label="Interceptar menú"
                checked={Boolean(node.data?.intercept_menu)}
                onChange={(checked) => patchData({ intercept_menu: checked })}
            />

            <div className="fm-insp-section-title">Disparadores</div>
            {keywords.map((keyword, index) => (
                <div className="fm-subcard" key={keyword.id || index}>
                    <div className="fm-subcard-head">
                        <b>Palabra {index + 1}</b>
                        <button className="fm-mini-del" type="button" onClick={() => patchData({
                            keywords: keywords.filter((_, keywordIndex) => keywordIndex !== index),
                            conditionsEditedFromKeywords: true,
                        })}>
                            <span className="mdi mdi-delete-outline" />
                        </button>
                    </div>
                    <Field>
                        <input className="fm-input" value={keyword.value || ''} placeholder="ej. agendar" onChange={(event) => updateKeyword(index, { value: event.target.value })} />
                    </Field>
                    <div className="fm-seg">
                        <button type="button" className={(keyword.matchType || 'contains') === 'contains' ? 'on' : ''} onClick={() => updateKeyword(index, { matchType: 'contains' })}>Contiene</button>
                        <button type="button" className={keyword.matchType === 'exact' ? 'on' : ''} onClick={() => updateKeyword(index, { matchType: 'exact' })}>Exacto</button>
                    </div>
                </div>
            ))}
            <button
                type="button"
                className="fm-add-btn"
                onClick={() => patchData({
                    keywords: [...keywords, { id: `kw_${Date.now()}`, value: '', matchType: 'contains' }],
                    conditionsEditedFromKeywords: true,
                })}
            >
                <span className="mdi mdi-plus" /> Agregar palabra clave
            </button>
            <JsonField
                label="Condiciones avanzadas JSON"
                value={node.data?.conditions || []}
                onValid={(conditions) => patchData({ conditions, conditionsEditedFromKeywords: false })}
                hint="Úsalo para condiciones V2 que todavía no tengan formulario visual."
            />
        </>
    );
}

function ActionEditor({ node, patchData, patchSettings }) {
    const actionType = node.data?.actionType || node.data?.action?.type || 'send_message';
    const settings = node.data?.settings || {};

    return (
        <>
            <Field label="Tipo de acción">
                <select className="fm-select" value={actionType} onChange={(event) => patchData({ actionType: event.target.value })}>
                    {ACTION_TYPE_OPTIONS.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
            </Field>

            {actionType === 'send_message' && <MessageEditor settings={settings} patchSettings={patchSettings} />}
            {(actionType === 'send_buttons' || actionType === 'send_list') && <ButtonsEditor settings={settings} patchSettings={patchSettings} />}
            {actionType === 'send_template' && <TemplateEditor settings={settings} patchSettings={patchSettings} />}
            {actionType === 'set_state' && <StateEditor settings={settings} patchSettings={patchSettings} />}
            {actionType === 'store_consent' && <ConsentEditor settings={settings} patchSettings={patchSettings} />}
            {actionType === 'sigcenter_agenda' && <SigcenterEditor settings={settings} patchSettings={patchSettings} />}
            {actionType === 'handoff_agent' && <HandoffEditor settings={settings} patchSettings={patchSettings} />}
            {actionType === 'ai_agent' && <AiEditor settings={settings} patchSettings={patchSettings} />}
            {!ACTION_TYPE_OPTIONS.some((option) => option.value === actionType) && (
                <JsonField
                    label="Acción JSON"
                    value={node.data?.action || {}}
                    onValid={(action) => patchData({ action, actionType: action.type || actionType, settings: {} })}
                    hint="Fallback para acciones V2 aún no cubiertas por formulario."
                />
            )}
        </>
    );
}

function MessageEditor({ settings, patchSettings }) {
    return (
        <>
            <Field label="Texto del mensaje" hint="Soporta variables como {{nombre}} y formato WhatsApp.">
                <textarea className="fm-textarea" value={settings.body || ''} onChange={(event) => patchSettings({ body: event.target.value })} />
            </Field>
            <div className="fm-row2">
                <Field label="Tipo media">
                    <select className="fm-select" value={settings.media_type || ''} onChange={(event) => patchSettings({ media_type: event.target.value })}>
                        <option value="">Texto</option>
                        <option value="image">Imagen</option>
                        <option value="video">Video</option>
                        <option value="document">Documento</option>
                    </select>
                </Field>
                <Field label="Archivo / link">
                    <input className="fm-input" value={settings.link || settings.fileUrl || ''} onChange={(event) => patchSettings({ link: event.target.value, fileUrl: event.target.value })} />
                </Field>
            </div>
        </>
    );
}

function ButtonsEditor({ settings, patchSettings }) {
    const buttons = normalizeButtonsForEditor(settings.buttons);

    function updateButton(index, value) {
        patchSettings({
            buttons: buttons.map((button, buttonIndex) => (
                buttonIndex === index ? { ...button, title: value } : button
            )),
        });
    }

    return (
        <>
            <Field label="Encabezado">
                <input className="fm-input" value={settings.header || ''} onChange={(event) => patchSettings({ header: event.target.value })} />
            </Field>
            <Field label="Texto principal">
                <textarea className="fm-textarea" value={settings.body || ''} onChange={(event) => patchSettings({ body: event.target.value })} />
            </Field>
            <Field label="Pie de página">
                <input className="fm-input" value={settings.footer || ''} onChange={(event) => patchSettings({ footer: event.target.value })} />
            </Field>
            <div className="fm-insp-section-title">Botones</div>
            {[0, 1, 2].map((index) => (
                <Field key={index} label={`Botón ${index + 1}`}>
                    <input className="fm-input" value={buttons[index]?.title || ''} onChange={(event) => updateButton(index, event.target.value)} />
                </Field>
            ))}
        </>
    );
}

function TemplateEditor({ settings, patchSettings }) {
    return (
        <>
            <div className="fm-row2">
                <Field label="Nombre template">
                    <input className="fm-input" value={settings.name || ''} onChange={(event) => patchSettings({ name: event.target.value })} />
                </Field>
                <Field label="Idioma">
                    <input className="fm-input" value={settings.language || 'es'} onChange={(event) => patchSettings({ language: event.target.value })} />
                </Field>
            </div>
            <JsonField label="Parámetros JSON" valueText={settings.parametersJson || '{}'} onText={(value) => patchSettings({ parametersJson: value })} />
        </>
    );
}

function StateEditor({ settings, patchSettings }) {
    return (
        <>
            <Field label="Estado">
                <input className="fm-input" value={settings.state || ''} placeholder="agenda_esperando_cedula" onChange={(event) => patchSettings({ state: event.target.value })} />
            </Field>
            <div className="fm-row2">
                <Field label="Guardar respuesta como">
                    <input className="fm-input" value={settings.save_response_as || ''} placeholder="cedula" onChange={(event) => patchSettings({ save_response_as: event.target.value })} />
                </Field>
                <Field label="Campo esperado">
                    <input className="fm-input" value={settings.awaiting_field || ''} placeholder="correo" onChange={(event) => patchSettings({ awaiting_field: event.target.value })} />
                </Field>
            </div>
            <Field label="Siguiente estado">
                <input className="fm-input" value={settings.next_state || ''} onChange={(event) => patchSettings({ next_state: event.target.value })} />
            </Field>
        </>
    );
}

function ConsentEditor({ settings, patchSettings }) {
    return (
        <>
            <Field label="Tipo de consentimiento">
                <input className="fm-input" value={settings.consent_type || 'datos_protegidos'} onChange={(event) => patchSettings({ consent_type: event.target.value })} />
            </Field>
            <CheckboxField label="Consentimiento otorgado" checked={Boolean(settings.granted ?? true)} onChange={(checked) => patchSettings({ granted: checked })} />
            <Field label="Mensaje opcional">
                <textarea className="fm-textarea" value={settings.body || ''} onChange={(event) => patchSettings({ body: event.target.value })} />
            </Field>
            <Field label="Siguiente estado">
                <input className="fm-input" value={settings.next_state || ''} onChange={(event) => patchSettings({ next_state: event.target.value })} />
            </Field>
        </>
    );
}

function SigcenterEditor({ settings, patchSettings }) {
    return (
        <>
            <Field label="Operación">
                <select className="fm-select" value={settings.operation || 'list_specialties'} onChange={(event) => patchSettings({ operation: event.target.value })}>
                    {SIGCENTER_OPERATIONS.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
            </Field>
            <CheckboxField label="Enviar resultado al paciente" checked={Boolean(settings.send_result ?? true)} onChange={(checked) => patchSettings({ send_result: checked })} />
            <div className="fm-row2">
                <Field label="Guardar resultado">
                    <input className="fm-input" value={settings.store_result_as || ''} onChange={(event) => patchSettings({ store_result_as: event.target.value })} />
                </Field>
                <Field label="Guardar respuesta">
                    <input className="fm-input" value={settings.save_response_as || ''} onChange={(event) => patchSettings({ save_response_as: event.target.value })} />
                </Field>
            </div>
            <div className="fm-row2">
                <Field label="Trabajador ID">
                    <input className="fm-input" value={settings.trabajador_id || ''} onChange={(event) => patchSettings({ trabajador_id: event.target.value })} />
                </Field>
                <Field label="Sede">
                    <input className="fm-input" value={settings.ID_SEDE || ''} onChange={(event) => patchSettings({ ID_SEDE: event.target.value })} />
                </Field>
            </div>
            <Field label="Siguiente estado">
                <input className="fm-input" value={settings.next_state || ''} onChange={(event) => patchSettings({ next_state: event.target.value })} />
            </Field>
        </>
    );
}

function HandoffEditor({ settings, patchSettings }) {
    return (
        <>
            <Field label="Motivo">
                <input className="fm-input" value={settings.reason || ''} onChange={(event) => patchSettings({ reason: event.target.value })} />
            </Field>
            <Field label="Cola / equipo">
                <input className="fm-input" value={settings.queue || ''} onChange={(event) => patchSettings({ queue: event.target.value })} />
            </Field>
            <Field label="Mensaje al paciente">
                <textarea className="fm-textarea" value={settings.message || ''} onChange={(event) => patchSettings({ message: event.target.value })} />
            </Field>
        </>
    );
}

function AiEditor({ settings, patchSettings }) {
    return (
        <>
            <Field label="Instrucciones">
                <textarea className="fm-textarea" value={settings.instructions || ''} onChange={(event) => patchSettings({ instructions: event.target.value })} />
            </Field>
            <CheckboxField label="Derivar si no hay confianza suficiente" checked={Boolean(settings.handoff ?? true)} onChange={(checked) => patchSettings({ handoff: checked })} />
            <JsonField label="Filtros KB JSON" valueText={settings.kbFiltersJson || '{}'} onText={(value) => patchSettings({ kbFiltersJson: value })} />
        </>
    );
}

function JsonField({ label, value, valueText, onValid, onText, hint }) {
    const text = valueText ?? JSON.stringify(value, null, 2);
    return (
        <Field label={label} hint={hint}>
            <textarea
                className="fm-textarea fm-codearea"
                value={text}
                onChange={(event) => {
                    if (onText) {
                        onText(event.target.value);
                        return;
                    }

                    try {
                        onValid(JSON.parse(event.target.value || '{}'));
                    } catch {
                        // Keep invalid draft text local to the textarea until valid JSON is entered.
                    }
                }}
            />
        </Field>
    );
}

function CheckboxField({ label, checked, onChange }) {
    return (
        <label className="fm-check-row">
            <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />
            <span>{label}</span>
        </label>
    );
}

function Field({ label, hint, children }) {
    return (
        <div className="fm-field">
            {label && <label>{label}</label>}
            {children}
            {hint && <div className="hint">{hint}</div>}
        </div>
    );
}

function normalizeButtonsForEditor(buttons) {
    const list = Array.isArray(buttons) ? buttons : [];
    return [0, 1, 2].map((index) => {
        const button = list[index];
        if (typeof button === 'string') {
            return { id: `opcion_${index + 1}`, title: button };
        }
        return button || { id: `opcion_${index + 1}`, title: '' };
    });
}
