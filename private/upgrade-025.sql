CREATE TABLE IF NOT EXISTS action_risk_reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 acceptance_id BIGINT UNSIGNED NOT NULL,
 reviewed_by BIGINT UNSIGNED NOT NULL,
 previous_review_on DATE NOT NULL,
 next_review_on DATE NOT NULL,
 conclusion TEXT NOT NULL,
 reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_risk_review_acceptance (acceptance_id,id),
 FOREIGN KEY (acceptance_id) REFERENCES action_risk_acceptances(id),
 FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB;
