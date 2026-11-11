CREATE TABLE IF NOT EXISTS attachment_purge_authorizations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 attachment_id BIGINT UNSIGNED NOT NULL,
 requested_by BIGINT UNSIGNED NOT NULL,
 requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 rationale TEXT NOT NULL,
 status ENUM('pendiente','aprobada','rechazada','cancelada','ejecutada') NOT NULL DEFAULT 'pendiente',
 decided_by BIGINT UNSIGNED NULL,
 decided_at TIMESTAMP NULL DEFAULT NULL,
 decision_note TEXT NULL,
 executed_by BIGINT UNSIGNED NULL,
 executed_at TIMESTAMP NULL DEFAULT NULL,
 INDEX idx_purge_attachment (attachment_id,status,id),
 FOREIGN KEY(attachment_id) REFERENCES attachments(id),
 FOREIGN KEY(requested_by) REFERENCES users(id),
 FOREIGN KEY(decided_by) REFERENCES users(id),
 FOREIGN KEY(executed_by) REFERENCES users(id)
) ENGINE=InnoDB;
