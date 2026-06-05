import React from 'react';
import { ACTION_TYPE_OPTIONS, STAGE_OPTIONS, STATUS_OPTIONS, nodeOutputHandles } from '../actionCatalog';
import { NODE_TYPES } from '../domain';
import { uid } from '../util';

const CONDITION_TYPES = [
    { value: 'always', label: 'Siempre' },
    { value: 'message_contains', label: 'Mensaje contiene' },
    { value: 'message_equals', label: 'Mensaje exacto' },
    { value: 'state_equals', label: 'Estado es' },
    { value: 'context_equals', label: 'Variable es igual a' },
    { value: 'context_contains', label: 'Variable contiene' },
];

export function NodeInspector({ node, catalogs = {}, nodes = [], edges = [], onUpdate, onDelete, onEdgesChange }) {
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
                    <TriggerEditor node={node} catalogs={catalogs} patchData={patchData} />
                ) : (
                    <ActionEditor
                        node={node}
                        nodes={nodes}
                        edges={edges}
                        catalogs={catalogs}
                        patchData={patchData}
                        patchSettings={patchSettings}
                        onEdgesChange={onEdgesChange}
                    />
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

function TriggerEditor({ node, catalogs, patchData }) {
    const keywords = Array.isArray(node.data?.keywords) ? node.data.keywords : [];
    const conditions = Array.isArray(node.data?.conditions) ? node.data.conditions : [];

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
            <ConditionEditor
                conditions={conditions}
                variables={catalogs.variables || []}
                onChange={(nextConditions) => patchData({ conditions: nextConditions, conditionsEditedFromKeywords: false })}
            />
        </>
    );
}

function ActionEditor({ node, nodes, edges, catalogs, patchData, patchSettings, onEdgesChange }) {
    const actionType = node.data?.actionType || node.data?.action?.type || defaultActionTypeForNode(node.type);
    const settings = node.data?.settings || {};

    return (
        <>
            <Field label="Tipo de acción">
                <select className="fm-select" value={actionType} onChange={(event) => patchData({ actionType: event.target.value })}>
                    {ACTION_TYPE_OPTIONS.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
            </Field>

            {actionType === 'send_message' && <MessageEditor settings={settings} variables={catalogs.variables || []} patchSettings={patchSettings} />}
            {(actionType === 'send_buttons' || actionType === 'send_list') && <ButtonsEditor settings={settings} variables={catalogs.variables || []} patchSettings={patchSettings} />}
            {actionType === 'send_template' && <TemplateEditor settings={settings} variables={catalogs.variables || []} patchSettings={patchSettings} />}
            {actionType === 'conditional' && <BranchEditor settings={settings} variables={catalogs.variables || []} patchSettings={patchSettings} />}
            {actionType === 'set_state' && <StateEditor settings={settings} patchSettings={patchSettings} />}
            {actionType === 'store_consent' && <ConsentEditor settings={settings} patchSettings={patchSettings} />}
            {actionType === 'sigcenter_agenda' && <SigcenterEditor settings={settings} operations={catalogs.sigcenter_operations || []} patchSettings={patchSettings} />}
            {actionType === 'handoff_agent' && <HandoffEditor settings={settings} variables={catalogs.variables || []} patchSettings={patchSettings} />}
            {actionType === 'ai_agent' && <AiEditor settings={settings} variables={catalogs.variables || []} patchSettings={patchSettings} />}
            <RouteEditor node={node} nodes={nodes} edges={edges} onEdgesChange={onEdgesChange} />
            {!ACTION_TYPE_OPTIONS.some((option) => option.value === actionType) && (
                <UnsupportedActionNotice actionType={actionType} />
            )}
        </>
    );
}

function BranchEditor({ settings, variables, patchSettings }) {
    const condition = settings.condition || { type: 'always' };

    return (
        <>
            <div className="fm-insp-section-title">Regla de decisión</div>
            <div className="fm-subcard">
                <ConditionFields
                    condition={condition}
                    variables={variables}
                    onChange={(nextCondition) => patchSettings({ condition: nextCondition })}
                />
            </div>
            <div className="fm-help-list">
                <span>Sí cumple</span>
                <span>No cumple</span>
            </div>
        </>
    );
}

function MessageEditor({ settings, variables, patchSettings }) {
    const bodyRef = React.useRef(null);

    return (
        <>
            <Field label="Texto del mensaje" hint="Soporta variables como {{nombre}} y formato WhatsApp.">
                <textarea ref={bodyRef} className="fm-textarea" value={settings.body || ''} onChange={(event) => patchSettings({ body: event.target.value })} />
                <VariableChips variables={variables} onInsert={(token) => insertText(bodyRef.current, settings.body || '', token, (body) => patchSettings({ body }))} />
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

function ButtonsEditor({ settings, variables, patchSettings }) {
    const buttons = normalizeButtonsForEditor(settings.buttons);
    const bodyRef = React.useRef(null);

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
                <textarea ref={bodyRef} className="fm-textarea" value={settings.body || ''} onChange={(event) => patchSettings({ body: event.target.value })} />
                <VariableChips variables={variables} onInsert={(token) => insertText(bodyRef.current, settings.body || '', token, (body) => patchSettings({ body }))} />
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

function TemplateEditor({ settings, variables, patchSettings }) {
    const parameters = settings.parameters || {};
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
            <KeyValueEditor
                title="Parámetros"
                rows={objectToRows(parameters)}
                variables={variables}
                keyPlaceholder="body_1"
                valuePlaceholder="Valor o variable"
                onChange={(rows) => patchSettings({ parameters: rowsToObject(rows) })}
            />
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

function SigcenterEditor({ settings, operations, patchSettings }) {
    const availableOperations = operations.length > 0
        ? operations.map((operation) => ({ value: operation.id || operation.value, label: operation.label }))
        : [
            { value: 'list_specialties', label: 'Listar especialidades' },
            { value: 'list_doctors', label: 'Listar médicos' },
            { value: 'list_times', label: 'Listar horarios' },
            { value: 'book_appointment', label: 'Agendar cita' },
            { value: 'cancel_appointment', label: 'Cancelar cita' },
            { value: 'reschedule_appointment', label: 'Reagendar cita' },
        ];

    return (
        <>
            <Field label="Operación">
                <select className="fm-select" value={settings.operation || 'list_specialties'} onChange={(event) => patchSettings({ operation: event.target.value })}>
                    {availableOperations.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
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
            <div className="fm-insp-section-title">Salidas esperadas</div>
            <div className="fm-help-list">
                <span>Éxito</span>
                <span>Dato faltante</span>
                <span>Sin disponibilidad</span>
                <span>Error / derivar</span>
            </div>
        </>
    );
}

function HandoffEditor({ settings, variables, patchSettings }) {
    const messageRef = React.useRef(null);

    return (
        <>
            <Field label="Motivo">
                <input className="fm-input" value={settings.reason || ''} onChange={(event) => patchSettings({ reason: event.target.value })} />
            </Field>
            <Field label="Cola / equipo">
                <input className="fm-input" value={settings.queue || ''} onChange={(event) => patchSettings({ queue: event.target.value })} />
            </Field>
            <Field label="Mensaje al paciente">
                <textarea ref={messageRef} className="fm-textarea" value={settings.message || ''} onChange={(event) => patchSettings({ message: event.target.value })} />
                <VariableChips variables={variables} onInsert={(token) => insertText(messageRef.current, settings.message || '', token, (message) => patchSettings({ message }))} />
            </Field>
        </>
    );
}

function AiEditor({ settings, variables, patchSettings }) {
    const instructionsRef = React.useRef(null);

    return (
        <>
            <Field label="Instrucciones">
                <textarea ref={instructionsRef} className="fm-textarea" value={settings.instructions || ''} onChange={(event) => patchSettings({ instructions: event.target.value })} />
                <VariableChips variables={variables} onInsert={(token) => insertText(instructionsRef.current, settings.instructions || '', token, (instructions) => patchSettings({ instructions }))} />
            </Field>
            <CheckboxField label="Derivar si no hay confianza suficiente" checked={Boolean(settings.handoff ?? true)} onChange={(checked) => patchSettings({ handoff: checked })} />
            <KeyValueEditor
                title="Filtros de conocimiento"
                rows={objectToRows(settings.kb_filters || {})}
                keyPlaceholder="tipo_contenido"
                valuePlaceholder="consentimiento"
                onChange={(rows) => patchSettings({ kb_filters: rowsToObject(rows) })}
            />
        </>
    );
}

function ConditionEditor({ conditions, variables, onChange }) {
    const rows = conditions.length > 0 ? conditions : [{ type: 'always' }];

    return (
        <div>
            <div className="fm-insp-section-title">Condiciones</div>
            {rows.map((condition, index) => (
                <div className="fm-subcard" key={index}>
                    <div className="fm-subcard-head">
                        <b>Condición {index + 1}</b>
                        <button className="fm-mini-del" type="button" onClick={() => onChange(rows.filter((_, rowIndex) => rowIndex !== index))}>
                            <span className="mdi mdi-delete-outline" />
                        </button>
                    </div>
                    <Field label="Tipo">
                        <select className="fm-select" value={condition.type || 'always'} onChange={(event) => updateCondition(rows, index, { type: event.target.value }, onChange)}>
                            {CONDITION_TYPES.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                    </Field>
                    <ConditionFields
                        condition={condition}
                        variables={variables}
                        skipType
                        onChange={(patch) => updateCondition(rows, index, patch, onChange)}
                    />
                </div>
            ))}
            <button type="button" className="fm-add-btn" onClick={() => onChange([...rows, { type: 'always' }])}>
                <span className="mdi mdi-plus" /> Agregar condición
            </button>
        </div>
    );
}

function ConditionFields({ condition, variables, onChange, skipType = false }) {
    const type = condition.type || 'always';

    function patch(patchValue) {
        onChange({ ...condition, ...patchValue });
    }

    return (
        <>
            {!skipType && (
                <Field label="Tipo">
                    <select className="fm-select" value={type} onChange={(event) => patch({ type: event.target.value })}>
                        {CONDITION_TYPES.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                </Field>
            )}
            {type !== 'always' && (
                <>
                    {type.startsWith('context_') && (
                        <Field label="Variable">
                            <select className="fm-select" value={condition.field || condition.variable || ''} onChange={(event) => patch({ field: event.target.value })}>
                                <option value="">Selecciona variable</option>
                                {variables.map((variable) => <option key={variable.id} value={variable.id}>{variable.label}</option>)}
                            </select>
                        </Field>
                    )}
                    <Field label={type === 'message_contains' ? 'Palabras o valor' : 'Valor'}>
                        <input
                            className="fm-input"
                            value={condition.value || (Array.isArray(condition.keywords) ? condition.keywords.join(', ') : '')}
                            onChange={(event) => {
                                const value = event.target.value;
                                patch(type === 'message_contains'
                                    ? { keywords: value.split(',').map((item) => item.trim()).filter(Boolean), value }
                                    : { value });
                            }}
                        />
                    </Field>
                </>
            )}
        </>
    );
}

function RouteEditor({ node, nodes, edges, onEdgesChange }) {
    if (!onEdgesChange) return null;

    const handles = nodeOutputHandles(node);
    if (handles.length === 0) return null;

    const targetNodes = nodes.filter((candidate) => candidate.id !== node.id);

    function currentTarget(handleId) {
        return edges.find((edge) => edge.source === node.id && (edge.sourceHandle || 'source') === handleId)?.target || '';
    }

    function updateRoute(handleId, targetId) {
        const existing = edges.find((edge) => edge.source === node.id && (edge.sourceHandle || 'source') === handleId);
        const nextEdges = edges.filter((edge) => !(edge.source === node.id && (edge.sourceHandle || 'source') === handleId));

        if (!targetId) {
            onEdgesChange(nextEdges);
            return;
        }

        onEdgesChange([
            ...nextEdges,
            {
                id: existing?.id || uid('edge'),
                source: node.id,
                sourceHandle: handleId,
                target: targetId,
                targetHandle: 'in',
            },
        ]);
    }

    return (
        <div>
            <div className="fm-insp-section-title">Rutas de salida</div>
            <div className="fm-route-list">
                {handles.map((handle) => (
                    <Field key={handle.id} label={handle.label}>
                        <select className="fm-select" value={currentTarget(handle.id)} onChange={(event) => updateRoute(handle.id, event.target.value)}>
                            <option value="">Sin conectar</option>
                            {targetNodes.map((target) => (
                                <option key={target.id} value={target.id}>
                                    {routeNodeLabel(target)}
                                </option>
                            ))}
                        </select>
                    </Field>
                ))}
            </div>
        </div>
    );
}

function KeyValueEditor({ title, rows, variables = [], keyPlaceholder, valuePlaceholder, onChange }) {
    return (
        <div>
            <div className="fm-insp-section-title">{title}</div>
            {rows.map((row, index) => (
                <div className="fm-subcard" key={row.id || index}>
                    <div className="fm-subcard-head">
                        <b>Campo {index + 1}</b>
                        <button type="button" className="fm-mini-del" onClick={() => onChange(rows.filter((_, rowIndex) => rowIndex !== index))}>
                            <span className="mdi mdi-delete-outline" />
                        </button>
                    </div>
                    <Field label="Clave">
                        <input className="fm-input" value={row.key} placeholder={keyPlaceholder} onChange={(event) => updateRow(rows, index, { key: event.target.value }, onChange)} />
                    </Field>
                    <Field label="Valor">
                        <input className="fm-input" value={row.value} placeholder={valuePlaceholder} onChange={(event) => updateRow(rows, index, { value: event.target.value }, onChange)} />
                        {variables.length > 0 && (
                            <VariableChips variables={variables} onInsert={(token) => updateRow(rows, index, { value: `${row.value || ''}${token}` }, onChange)} />
                        )}
                    </Field>
                </div>
            ))}
            <button type="button" className="fm-add-btn" onClick={() => onChange([...rows, { id: `row_${Date.now()}`, key: '', value: '' }])}>
                <span className="mdi mdi-plus" /> Agregar campo
            </button>
        </div>
    );
}

function UnsupportedActionNotice({ actionType }) {
    return (
        <div className="fm-unsupported-action">
            <span className="mdi mdi-lock-alert-outline" />
            <div>
                <b>Acción avanzada no-code pendiente</b>
                <p>Este nodo usa <code>{actionType}</code>. V3 preserva el payload al publicar, pero aún no permite editarlo visualmente.</p>
            </div>
        </div>
    );
}

function VariableChips({ variables, onInsert }) {
    if (!Array.isArray(variables) || variables.length === 0) {
        return null;
    }

    return (
        <div className="fm-var-bar">
            {variables.map((variable) => (
                <button key={variable.id} type="button" className="fm-var-chip" title={variable.label} onClick={() => onInsert(variable.token || `{{${variable.id}}}`)}>
                    {variable.token || `{{${variable.id}}}`}
                </button>
            ))}
        </div>
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

function routeNodeLabel(node) {
    const meta = NODE_TYPES[node.type] || NODE_TYPES.message;
    const title = node.data?.name
        || node.data?.settings?.body
        || node.data?.action?.message?.body
        || node.data?.actionType
        || meta.label;

    return `${meta.label} - ${String(title).slice(0, 44)}`;
}

function defaultActionTypeForNode(type) {
    if (type === 'quick_replies') return 'send_buttons';
    if (type === 'template') return 'send_template';
    if (type === 'branch') return 'conditional';
    if (type === 'state') return 'set_state';
    if (type === 'consent') return 'store_consent';
    if (type === 'sigcenter_agenda') return 'sigcenter_agenda';
    if (type === 'handoff' || type === 'end') return 'handoff_agent';
    if (type === 'ai_agent') return 'ai_agent';
    return 'send_message';
}

function insertText(element, currentValue, token, onChange) {
    if (!element) {
        onChange(`${currentValue}${token}`);
        return;
    }

    const start = element.selectionStart ?? currentValue.length;
    const end = element.selectionEnd ?? currentValue.length;
    const next = `${currentValue.slice(0, start)}${token}${currentValue.slice(end)}`;
    onChange(next);

    window.requestAnimationFrame(() => {
        element.focus();
        element.setSelectionRange(start + token.length, start + token.length);
    });
}

function updateCondition(rows, index, patch, onChange) {
    onChange(rows.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)));
}

function updateRow(rows, index, patch, onChange) {
    onChange(rows.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)));
}

function objectToRows(value) {
    const entries = value && typeof value === 'object' && !Array.isArray(value)
        ? Object.entries(value)
        : [];

    if (entries.length === 0) {
        return [{ id: 'row_1', key: '', value: '' }];
    }

    return entries.map(([key, entryValue], index) => ({
        id: `row_${index + 1}`,
        key,
        value: String(entryValue ?? ''),
    }));
}

function rowsToObject(rows) {
    return rows.reduce((carry, row) => {
        const key = String(row.key || '').trim();
        if (key !== '') {
            carry[key] = row.value || '';
        }
        return carry;
    }, {});
}
