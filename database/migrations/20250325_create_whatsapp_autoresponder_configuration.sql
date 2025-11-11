CREATE TABLE IF NOT EXISTS whatsapp_message_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_code VARCHAR(128) NOT NULL,
    display_name VARCHAR(191) NOT NULL,
    language VARCHAR(12) NOT NULL DEFAULT 'es',
    category ENUM('marketing','utility','authentication','service','session') NOT NULL DEFAULT 'utility',
    status ENUM('draft','pending','approved','rejected','disabled') NOT NULL DEFAULT 'draft',
    current_revision_id BIGINT UNSIGNED DEFAULT NULL,
    wa_business_account VARCHAR(64) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    approval_requested_at DATETIME DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    rejected_at DATETIME DEFAULT NULL,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_whatsapp_message_templates_code (template_code),
    KEY idx_whatsapp_message_templates_status (status),
    KEY idx_whatsapp_message_templates_language (language),
    KEY idx_whatsapp_message_templates_current_revision (current_revision_id),
    CONSTRAINT fk_whatsapp_message_templates_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_whatsapp_message_templates_editor FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_template_revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id INT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    status ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft',
    header_type ENUM('none','text','image','video','document') NOT NULL DEFAULT 'none',
    header_text VARCHAR(255) DEFAULT NULL,
    body_text TEXT NOT NULL,
    footer_text VARCHAR(255) DEFAULT NULL,
    buttons JSON DEFAULT NULL,
    variables JSON DEFAULT NULL,
    quality_rating ENUM('unknown','green','yellow','red') NOT NULL DEFAULT 'unknown',
    rejection_reason TEXT DEFAULT NULL,
    submitted_at DATETIME DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    rejected_at DATETIME DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_whatsapp_template_revision (template_id, version),
    KEY idx_whatsapp_template_revisions_status (status),
    CONSTRAINT fk_whatsapp_template_revisions_template FOREIGN KEY (template_id)
        REFERENCES whatsapp_message_templates (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_whatsapp_template_revisions_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE whatsapp_message_templates
    ADD CONSTRAINT fk_whatsapp_message_templates_current_revision FOREIGN KEY (current_revision_id)
        REFERENCES whatsapp_template_revisions (id)
        ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS whatsapp_autoresponder_flows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flow_key VARCHAR(100) NOT NULL,
    name VARCHAR(191) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('draft','active','inactive','archived') NOT NULL DEFAULT 'draft',
    timezone VARCHAR(64) DEFAULT NULL,
    active_from DATETIME DEFAULT NULL,
    active_until DATETIME DEFAULT NULL,
    active_version_id BIGINT UNSIGNED DEFAULT NULL,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_whatsapp_autoresponder_flow_key (flow_key),
    KEY idx_whatsapp_autoresponder_flows_status (status),
    KEY idx_whatsapp_autoresponder_flows_active_version (active_version_id),
    CONSTRAINT fk_whatsapp_autoresponder_flows_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_whatsapp_autoresponder_flows_editor FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_autoresponder_flow_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flow_id INT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    status ENUM('draft','ready','published','archived') NOT NULL DEFAULT 'draft',
    changelog TEXT DEFAULT NULL,
    audience_filters JSON DEFAULT NULL,
    entry_settings JSON DEFAULT NULL,
    published_at DATETIME DEFAULT NULL,
    published_by INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_whatsapp_autoresponder_flow_version (flow_id, version),
    KEY idx_whatsapp_autoresponder_flow_versions_status (status),
    CONSTRAINT fk_whatsapp_autoresponder_flow_versions_flow FOREIGN KEY (flow_id)
        REFERENCES whatsapp_autoresponder_flows (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_whatsapp_autoresponder_flow_versions_publisher FOREIGN KEY (published_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_whatsapp_autoresponder_flow_versions_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE whatsapp_autoresponder_flows
    ADD CONSTRAINT fk_whatsapp_autoresponder_flows_active_version FOREIGN KEY (active_version_id)
        REFERENCES whatsapp_autoresponder_flow_versions (id)
        ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS whatsapp_autoresponder_steps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flow_version_id BIGINT UNSIGNED NOT NULL,
    step_key VARCHAR(100) NOT NULL,
    step_type ENUM('trigger','condition','message','wait','assignment','end','webhook') NOT NULL,
    name VARCHAR(191) NOT NULL,
    description TEXT DEFAULT NULL,
    order_index INT UNSIGNED NOT NULL DEFAULT 0,
    is_entry_point TINYINT(1) NOT NULL DEFAULT 0,
    settings JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_whatsapp_autoresponder_step_key (flow_version_id, step_key),
    KEY idx_whatsapp_autoresponder_steps_order (flow_version_id, order_index),
    CONSTRAINT fk_whatsapp_autoresponder_steps_version FOREIGN KEY (flow_version_id)
        REFERENCES whatsapp_autoresponder_flow_versions (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_autoresponder_step_transitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    step_id BIGINT UNSIGNED NOT NULL,
    target_step_id BIGINT UNSIGNED DEFAULT NULL,
    condition_label VARCHAR(191) DEFAULT NULL,
    condition_type ENUM('always','match','timeout','fallback') NOT NULL DEFAULT 'always',
    condition_payload JSON DEFAULT NULL,
    priority INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_whatsapp_autoresponder_transitions_step (step_id),
    KEY idx_whatsapp_autoresponder_transitions_target (target_step_id),
    CONSTRAINT fk_whatsapp_autoresponder_transitions_step FOREIGN KEY (step_id)
        REFERENCES whatsapp_autoresponder_steps (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_whatsapp_autoresponder_transitions_target FOREIGN KEY (target_step_id)
        REFERENCES whatsapp_autoresponder_steps (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_autoresponder_step_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    step_id BIGINT UNSIGNED NOT NULL,
    action_type ENUM('send_template','send_session_message','wait','assign_tag','remove_tag','handoff','update_field','webhook','mark_opt_out') NOT NULL,
    template_revision_id BIGINT UNSIGNED DEFAULT NULL,
    message_body TEXT DEFAULT NULL,
    media_url VARCHAR(500) DEFAULT NULL,
    delay_seconds INT UNSIGNED DEFAULT 0,
    metadata JSON DEFAULT NULL,
    order_index INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_whatsapp_autoresponder_actions_step (step_id),
    KEY idx_whatsapp_autoresponder_actions_template (template_revision_id),
    CONSTRAINT fk_whatsapp_autoresponder_actions_step FOREIGN KEY (step_id)
        REFERENCES whatsapp_autoresponder_steps (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_whatsapp_autoresponder_actions_template FOREIGN KEY (template_revision_id)
        REFERENCES whatsapp_template_revisions (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_autoresponder_version_filters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flow_version_id BIGINT UNSIGNED NOT NULL,
    filter_type ENUM('tag','contact_field','consent_status','timezone','custom') NOT NULL,
    operator ENUM('equals','not_equals','in','not_in','contains','greater_than','less_than','between','exists','not_exists') NOT NULL DEFAULT 'equals',
    value JSON DEFAULT NULL,
    is_exclusion TINYINT(1) NOT NULL DEFAULT 0,
    order_index INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_whatsapp_autoresponder_version_filters_version (flow_version_id),
    CONSTRAINT fk_whatsapp_autoresponder_version_filters_version FOREIGN KEY (flow_version_id)
        REFERENCES whatsapp_autoresponder_flow_versions (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_autoresponder_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flow_version_id BIGINT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED DEFAULT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    timezone VARCHAR(64) DEFAULT NULL,
    allow_holidays TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_whatsapp_autoresponder_schedules_version (flow_version_id),
    KEY idx_whatsapp_autoresponder_schedules_day (flow_version_id, day_of_week),
    CONSTRAINT fk_whatsapp_autoresponder_schedules_version FOREIGN KEY (flow_version_id)
        REFERENCES whatsapp_autoresponder_flow_versions (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
