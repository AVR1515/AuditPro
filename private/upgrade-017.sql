CREATE TABLE IF NOT EXISTS grc_risk_reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 risk_id BIGINT UNSIGNED NOT NULL,
 reviewed_by BIGINT UNSIGNED NOT NULL,
 previous_residual TINYINT UNSIGNED NOT NULL,
 residual TINYINT UNSIGNED NOT NULL,
 review_note TEXT NOT NULL,
 reviewed_on DATE NOT NULL,
 next_review_on DATE NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_risk_review_history (risk_id,id),
 FOREIGN KEY(risk_id) REFERENCES grc_risks(id),
 FOREIGN KEY(reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS grc_kri_indicators (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 risk_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(180) NOT NULL,
 unit VARCHAR(80) NOT NULL,
 direction ENUM('maximo','minimo') NOT NULL,
 threshold DECIMAL(14,3) NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 active BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_kri_risk (risk_id,active),
 FOREIGN KEY(risk_id) REFERENCES grc_risks(id),
 FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS grc_kri_observations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 indicator_id BIGINT UNSIGNED NOT NULL,
 recorded_by BIGINT UNSIGNED NOT NULL,
 observed_on DATE NOT NULL,
 observed_value DECIMAL(14,3) NOT NULL,
 evidence_reference TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_kri_observation (indicator_id,id),
 FOREIGN KEY(indicator_id) REFERENCES grc_kri_indicators(id),
 FOREIGN KEY(recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;
