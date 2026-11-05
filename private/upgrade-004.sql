CREATE TABLE IF NOT EXISTS grc_risks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 unit_id BIGINT UNSIGNED NOT NULL, title VARCHAR(180) NOT NULL,
 owner_name VARCHAR(180) NOT NULL, inherent TINYINT UNSIGNED NOT NULL,
 residual TINYINT UNSIGNED NOT NULL, appetite TINYINT UNSIGNED NOT NULL,
 treatment TEXT NOT NULL, review_on DATE NOT NULL,
 FOREIGN KEY(unit_id) REFERENCES auditable_units(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS grc_controls (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(180) NOT NULL, owner_name VARCHAR(180) NOT NULL,
 frequency ENUM('mensual','trimestral','anual','por_evento') NOT NULL,
 description TEXT NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS grc_obligations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 framework VARCHAR(180) NOT NULL, reference_code VARCHAR(120) NOT NULL,
 title VARCHAR(180) NOT NULL, owner_name VARCHAR(180) NOT NULL, review_on DATE NOT NULL,
 UNIQUE(framework,reference_code)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS grc_risk_controls (
 risk_id BIGINT UNSIGNED NOT NULL, control_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(risk_id,control_id), FOREIGN KEY(risk_id) REFERENCES grc_risks(id),
 FOREIGN KEY(control_id) REFERENCES grc_controls(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS grc_obligation_controls (
 obligation_id BIGINT UNSIGNED NOT NULL, control_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(obligation_id,control_id), FOREIGN KEY(obligation_id) REFERENCES grc_obligations(id),
 FOREIGN KEY(control_id) REFERENCES grc_controls(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS grc_control_tests (
 control_id BIGINT UNSIGNED NOT NULL, test_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(control_id,test_id), FOREIGN KEY(control_id) REFERENCES grc_controls(id),
 FOREIGN KEY(test_id) REFERENCES tests(id)
) ENGINE=InnoDB;
