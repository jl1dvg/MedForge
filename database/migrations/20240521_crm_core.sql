-- Crea la infraestructura CRM para leads, proyectos, tareas y tickets

CREATE TABLE IF NOT EXISTS crm_leads (
    id INT NOT NULL AUTO_INCREMENT,
    customer_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    status ENUM('nuevo','en_proceso','convertido','perdido') NOT NULL DEFAULT 'nuevo',
    source VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crm_leads_status (status),
    KEY idx_crm_leads_assigned (assigned_to),
    KEY idx_crm_leads_customer (customer_id),
    CONSTRAINT fk_crm_leads_customer FOREIGN KEY (customer_id) REFERENCES crm_customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_leads_assigned FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_leads_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_projects (
    id INT NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('planificado','en_proceso','en_espera','completado','cancelado') NOT NULL DEFAULT 'planificado',
    owner_id INT DEFAULT NULL,
    lead_id INT DEFAULT NULL,
    customer_id INT DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crm_projects_status (status),
    KEY idx_crm_projects_owner (owner_id),
    KEY idx_crm_projects_lead (lead_id),
    KEY idx_crm_projects_customer (customer_id),
    CONSTRAINT fk_crm_projects_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_projects_lead FOREIGN KEY (lead_id) REFERENCES crm_leads (id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_projects_customer FOREIGN KEY (customer_id) REFERENCES crm_customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_projects_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_tasks (
    id INT NOT NULL AUTO_INCREMENT,
    project_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('pendiente','en_progreso','bloqueada','completada') NOT NULL DEFAULT 'pendiente',
    assigned_to INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crm_tasks_project (project_id),
    KEY idx_crm_tasks_status (status),
    KEY idx_crm_tasks_assigned (assigned_to),
    CONSTRAINT fk_crm_tasks_project FOREIGN KEY (project_id) REFERENCES crm_projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_crm_tasks_assigned FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_tasks_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_task_reminders (
    id INT NOT NULL AUTO_INCREMENT,
    task_id INT NOT NULL,
    remind_at DATETIME NOT NULL,
    channel ENUM('email','in_app') NOT NULL DEFAULT 'in_app',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crm_task_reminders_task (task_id),
    KEY idx_crm_task_reminders_datetime (remind_at),
    CONSTRAINT fk_crm_task_reminders_task FOREIGN KEY (task_id) REFERENCES crm_tasks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_tickets (
    id INT NOT NULL AUTO_INCREMENT,
    subject VARCHAR(255) NOT NULL,
    status ENUM('abierto','en_progreso','resuelto','cerrado') NOT NULL DEFAULT 'abierto',
    priority ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    reporter_id INT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    related_lead_id INT DEFAULT NULL,
    related_project_id INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crm_tickets_status (status),
    KEY idx_crm_tickets_assigned (assigned_to),
    KEY idx_crm_tickets_priority (priority),
    CONSTRAINT fk_crm_tickets_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_tickets_assigned FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_tickets_lead FOREIGN KEY (related_lead_id) REFERENCES crm_leads (id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_tickets_project FOREIGN KEY (related_project_id) REFERENCES crm_projects (id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_tickets_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_ticket_messages (
    id INT NOT NULL AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    author_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crm_ticket_messages_ticket (ticket_id),
    CONSTRAINT fk_crm_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES crm_tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_crm_ticket_messages_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
