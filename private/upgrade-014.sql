CREATE TABLE IF NOT EXISTS obligation_attestations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 obligation_id BIGINT UNSIGNED NOT NULL,
 author_id BIGINT UNSIGNED NOT NULL,
 result ENUM('cumple','parcial','no_cumple','no_aplica') NOT NULL,
 evidence_reference TEXT NOT NULL,
 rationale TEXT NOT NULL,
 reviewed_on DATE NOT NULL,
 next_review_on DATE NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_attestation_obligation (obligation_id,id),
 INDEX idx_attestation_next_review (next_review_on),
 FOREIGN KEY(obligation_id) REFERENCES grc_obligations(id),
 FOREIGN KEY(author_id) REFERENCES users(id)
) ENGINE=InnoDB;
