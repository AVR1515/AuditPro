CREATE TABLE IF NOT EXISTS pilot_gate_signoffs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 gate_key VARCHAR(32) NOT NULL,
 reviewer_id BIGINT UNSIGNED NOT NULL,
 reviewer_role ENUM('admin','supervisor','riesgos','cumplimiento') NOT NULL,
 result ENUM('cumple','no_cumple') NOT NULL,
 version_sha CHAR(40) NOT NULL,
 evidence_reference TEXT NOT NULL,
 notes TEXT NOT NULL,
 reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_pilot_gate_role (gate_key,reviewer_role,id),
 INDEX idx_pilot_gate_actor (reviewer_id,id),
 FOREIGN KEY(reviewer_id) REFERENCES users(id)
) ENGINE=InnoDB;
