CREATE TABLE IF NOT EXISTS action_risk_acceptances (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 action_id BIGINT UNSIGNED NOT NULL,
 requested_by BIGINT UNSIGNED NOT NULL,
 rationale TEXT NOT NULL,
 compensating_controls TEXT NOT NULL,
 review_on DATE NOT NULL,
 status ENUM('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 decided_by BIGINT UNSIGNED NULL,
 decided_at TIMESTAMP NULL DEFAULT NULL,
 decision_note TEXT NULL,
 INDEX idx_risk_acceptance_action (action_id,status),
 INDEX idx_risk_acceptance_review (status,review_on),
 FOREIGN KEY(action_id) REFERENCES actions(id),
 FOREIGN KEY(requested_by) REFERENCES users(id),
 FOREIGN KEY(decided_by) REFERENCES users(id)
) ENGINE=InnoDB;
