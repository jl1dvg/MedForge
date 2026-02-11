CREATE TABLE IF NOT EXISTS whatsapp_handoffs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id INT UNSIGNED NOT NULL,
    wa_number VARCHAR(32) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'queued',
    priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    topic VARCHAR(120) DEFAULT NULL,
    handoff_role_id INT NULL,
    assigned_agent_id INT NULL,
    assigned_at DATETIME NULL,
    assigned_until DATETIME NULL,
    queued_at DATETIME NULL,
    last_activity_at DATETIME NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_whatsapp_handoffs_conversation (conversation_id, status),
    KEY idx_whatsapp_handoffs_assigned (assigned_agent_id, status),
    KEY idx_whatsapp_handoffs_role (handoff_role_id, status),
    KEY idx_whatsapp_handoffs_assigned_until (assigned_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_handoff_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    handoff_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    actor_user_id INT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_whatsapp_handoff_events_handoff (handoff_id, created_at),
    KEY idx_whatsapp_handoff_events_actor (actor_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
