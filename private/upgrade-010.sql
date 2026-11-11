CREATE TABLE IF NOT EXISTS evidence_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 audit_id BIGINT UNSIGNED NOT NULL,
 requested_by BIGINT UNSIGNED NOT NULL,
 subject VARCHAR(180) NOT NULL,
 description TEXT NOT NULL,
 due_on DATE NOT NULL,
 status ENUM('abierta','respondida','cerrada') NOT NULL DEFAULT 'abierta',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 closed_by BIGINT UNSIGNED NULL,
 closed_at TIMESTAMP NULL DEFAULT NULL,
 INDEX idx_request_audit_status (audit_id,status,due_on),
 FOREIGN KEY(audit_id) REFERENCES audits(id),
 FOREIGN KEY(requested_by) REFERENCES users(id),
 FOREIGN KEY(closed_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS evidence_request_responses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 request_id BIGINT UNSIGNED NOT NULL,
 author_id BIGINT UNSIGNED NOT NULL,
 response_text TEXT NOT NULL,
 is_return BOOLEAN NOT NULL DEFAULT FALSE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_request_history (request_id,id),
 FOREIGN KEY(request_id) REFERENCES evidence_requests(id),
 FOREIGN KEY(author_id) REFERENCES users(id)
) ENGINE=InnoDB;
