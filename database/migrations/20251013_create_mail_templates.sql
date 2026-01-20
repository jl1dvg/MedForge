CREATE TABLE IF NOT EXISTS mail_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    context VARCHAR(100) NOT NULL,
    template_key VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    subject_template VARCHAR(255) DEFAULT NULL,
    body_template_html LONGTEXT DEFAULT NULL,
    body_template_text LONGTEXT DEFAULT NULL,
    recipients_to TEXT DEFAULT NULL,
    recipients_cc TEXT DEFAULT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mail_templates_context_key (context, template_key),
    KEY idx_mail_templates_context (context),
    KEY idx_mail_templates_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO mail_templates (
    context,
    template_key,
    name,
    subject_template,
    body_template_html,
    body_template_text,
    recipients_to,
    recipients_cc,
    enabled
)
VALUES
    (
        'cobertura',
        'iess_cive',
        'IESS - Solicitud de nuevo código',
        'Solicitud de nuevo código {PACIENTE} - {HC}',
        '<p>Buenos días,</p>\n<p>De su gentil ayuda solicitando nuevo código para el paciente <strong>{PACIENTE}</strong> con número de cédula <strong>{HC}</strong> para el siguiente procedimiento:</p>\n<p><strong>TRATAMIENTO / OBSERVACIONES FINALES DE LA CONSULTA:</strong><br><strong>Procedimiento solicitado:</strong> {PROC}</p>\n<p><strong>Plan de consulta:</strong> {PLAN}</p>\n<p><a href="{PDF_URL}" target="_blank" rel="noopener">Ver PDF de derivación</a></p>\n<p><strong>Pedido:</strong> {FORM_ID}</p>\n<p>Información que notifico para los fines pertinentes</p><br><br>\n<p>Coordinacion Quirúrgica</p>\n<p>Clínica Internacional de la Vision del Ecuador</p>\n<p>Telefono: 043 3729340 Ext. 200</p>\n<p>Celular : 099 879 6124</p>\n<p>Email: coordinacionquirurgica@cive.ec</p>\n<p>Dir. Km 12.5 Av. Leon Febres Cordero junto a la Piazza de Villa Club</p>\n<p><a href="https://www.cive.ec" target="_blank" rel="noopener">www.cive.ec</a></p>',
        'Buenos días,\n\nDe su gentil ayuda solicitando nuevo código para el paciente {PACIENTE} con número de cédula {HC} para el siguiente procedimiento:\n\nTRATAMIENTO / OBSERVACIONES FINALES DE LA CONSULTA:\nProcedimiento solicitado: {PROC}\nPlan de consulta: {PLAN}\nDerivación (PDF): {PDF_URL}\n\nPedido: {FORM_ID}\n\nInformación que notifico para los fines pertinentes\n\nCoordinacion Quirúrgica\n\nClínica Internacional de la Vision del Ecuador\n\nTelefono: 043 3729340 Ext. 200\n\nCelular : 099 879 6124\n\nEmail: coordinacionquirurgica@cive.ec\n\nDir. Km 12.5 Av. Leon Febres Cordero junto a la Piazza de Villa Club\n\nwww.cive.ec',
        'cespinoza@cive.ec',
        'oespinoza@cive.ec',
        1
    ),
    (
        'cobertura',
        'msp_informe',
        'MSP - Solicitud de informe',
        'Solicitud de informe MSP {PACIENTE} - {HC}',
        '<p>Buenos días,</p>\n<p>Por favor gestionar informe de cobertura MSP para el paciente <strong>{PACIENTE}</strong> con cédula <strong>{HC}</strong> para el siguiente procedimiento:</p>\n<p><strong>TRATAMIENTO / OBSERVACIONES FINALES DE LA CONSULTA:</strong><br><strong>Procedimiento solicitado:</strong> {PROC}</p>\n<p><strong>Plan de consulta:</strong> {PLAN}</p>\n<p><a href="{PDF_URL}" target="_blank" rel="noopener">Ver PDF de derivación</a></p>\n<p><strong>Pedido:</strong> {FORM_ID}</p>\n<p>Información que notifico para los fines pertinentes</p><br><br>\n<p>Coordinacion Quirúrgica</p>\n<p>Clínica Internacional de la Vision del Ecuador</p>\n<p>Telefono: 043 3729340 Ext. 200</p>\n<p>Celular : 099 879 6124</p>\n<p>Email: coordinacionquirurgica@cive.ec</p>\n<p>Dir. Km 12.5 Av. Leon Febres Cordero junto a la Piazza de Villa Club</p>\n<p><a href="https://www.cive.ec" target="_blank" rel="noopener">www.cive.ec</a></p>',
        'Buenos días,\n\nPor favor gestionar informe de cobertura MSP para el paciente {PACIENTE} con cédula {HC} para el siguiente procedimiento:\n\nTRATAMIENTO / OBSERVACIONES FINALES DE LA CONSULTA:\nProcedimiento solicitado: {PROC}\nPlan de consulta: {PLAN}\nDerivación (PDF): {PDF_URL}\n\nPedido: {FORM_ID}\n\nInformación que notifico para los fines pertinentes\n\nCoordinacion Quirúrgica\n\nClínica Internacional de la Vision del Ecuador\n\nTelefono: 043 3729340 Ext. 200\n\nCelular : 099 879 6124\n\nEmail: coordinacionquirurgica@cive.ec\n\nDir. Km 12.5 Av. Leon Febres Cordero junto a la Piazza de Villa Club\n\nwww.cive.ec',
        'cespinoza@cive.ec',
        'oespinoza@cive.ec',
        1
    ),
    (
        'cobertura',
        'isspol_informe',
        'ISSPOL - Solicitud de informe',
        'Solicitud de informe ISSPOL {PACIENTE} - {HC}',
        '<p>Buenos días,</p>\n<p>Por favor gestionar informe de cobertura ISSPOL para el paciente <strong>{PACIENTE}</strong> con cédula <strong>{HC}</strong> para el siguiente procedimiento:</p>\n<p><strong>TRATAMIENTO / OBSERVACIONES FINALES DE LA CONSULTA:</strong><br><strong>Procedimiento solicitado:</strong> {PROC}</p>\n<p><strong>Plan de consulta:</strong> {PLAN}</p>\n<p><a href="{PDF_URL}" target="_blank" rel="noopener">Ver PDF de derivación</a></p>\n<p><strong>Pedido:</strong> {FORM_ID}</p>\n<p>Información que notifico para los fines pertinentes</p><br><br>\n<p>Coordinacion Quirúrgica</p>\n<p>Clínica Internacional de la Vision del Ecuador</p>\n<p>Telefono: 043 3729340 Ext. 200</p>\n<p>Celular : 099 879 6124</p>\n<p>Email: coordinacionquirurgica@cive.ec</p>\n<p>Dir. Km 12.5 Av. Leon Febres Cordero junto a la Piazza de Villa Club</p>\n<p><a href="https://www.cive.ec" target="_blank" rel="noopener">www.cive.ec</a></p>',
        'Buenos días,\n\nPor favor gestionar informe de cobertura ISSPOL para el paciente {PACIENTE} con cédula {HC} para el siguiente procedimiento:\n\nTRATAMIENTO / OBSERVACIONES FINALES DE LA CONSULTA:\nProcedimiento solicitado: {PROC}\nPlan de consulta: {PLAN}\nDerivación (PDF): {PDF_URL}\n\nPedido: {FORM_ID}\n\nInformación que notifico para los fines pertinentes\n\nCoordinacion Quirúrgica\n\nClínica Internacional de la Vision del Ecuador\n\nTelefono: 043 3729340 Ext. 200\n\nCelular : 099 879 6124\n\nEmail: coordinacionquirurgica@cive.ec\n\nDir. Km 12.5 Av. Leon Febres Cordero junto a la Piazza de Villa Club\n\nwww.cive.ec',
        'cespinoza@cive.ec',
        'oespinoza@cive.ec',
        1
    ),
    (
        'cobertura',
        'issfa_informe',
        'ISSFA - Solicitud de informe',
        'Solicitud de informe ISSFA {PACIENTE} - {HC}',
        '<p>Buenos días,</p>\n<p>Por favor gestionar informe de cobertura ISSFA para el paciente <strong>{PACIENTE}</strong> con cédula <strong>{HC}</strong> para el siguiente procedimiento:</p>\n<p><strong>TRATAMIENTO / OBSERVACIONES FINALES DE LA CONSULTA:</strong><br><strong>Procedimiento solicitado:</strong> {PROC}</p>\n<p><strong>Plan de consulta:</strong> {PLAN}</p>\n<p><a href="{PDF_URL}" target="_blank" rel="noopener">Ver PDF de derivación</a></p>\n<p><strong>Pedido:</strong> {FORM_ID}</p>\n<p>Información que notifico para los fines pertinentes</p><br><br>\n<p>Coordinacion Quirúrgica</p>\n<p>Clínica Internacional de la Vision del Ecuador</p>\n<p>Telefono: 043 3729340 Ext. 200</p>\n<p>Celular : 099 879 6124</p>\n<p>Email: coordinacionquirurgica@cive.ec</p>\n<p>Dir. Km 12.5 Av. Leon Febres Cordero junto a la Piazza de Villa Club</p>\n<p><a href="https://www.cive.ec" target="_blank" rel="noopener">www.cive.ec</a></p>',
        'Buenos días,\n\nPor favor gestionar informe de cobertura ISSFA para el paciente {PACIENTE} con cédula {HC} para el siguiente procedimiento:\n\nTRATAMIENTO / OBSERVACIONES FINALES DE LA CONSULTA:\nProcedimiento solicitado: {PROC}\nPlan de consulta: {PLAN}\nDerivación (PDF): {PDF_URL}\n\nPedido: {FORM_ID}\n\nInformación que notifico para los fines pertinentes\n\nCoordinacion Quirúrgica\n\nClínica Internacional de la Vision del Ecuador\n\nTelefono: 043 3729340 Ext. 200\n\nCelular : 099 879 6124\n\nEmail: coordinacionquirurgica@cive.ec\n\nDir. Km 12.5 Av. Leon Febres Cordero junto a la Piazza de Villa Club\n\nwww.cive.ec',
        'cespinoza@cive.ec',
        'oespinoza@cive.ec',
        1
    )
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    subject_template = VALUES(subject_template),
    body_template_html = VALUES(body_template_html),
    body_template_text = VALUES(body_template_text),
    recipients_to = VALUES(recipients_to),
    recipients_cc = VALUES(recipients_cc),
    enabled = VALUES(enabled);
