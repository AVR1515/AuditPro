CREATE TABLE IF NOT EXISTS multiyear_plans (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(180) NOT NULL,
 start_year SMALLINT UNSIGNED NOT NULL,
 end_year SMALLINT UNSIGNED NOT NULL,
 rationale TEXT NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 approved_by BIGINT UNSIGNED NULL,
 approved_at TIMESTAMP NULL DEFAULT NULL,
 INDEX idx_multiyear_period (start_year,end_year),
 FOREIGN KEY(created_by) REFERENCES users(id),
 FOREIGN KEY(approved_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS multiyear_plan_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 plan_id BIGINT UNSIGNED NOT NULL,
 unit_id BIGINT UNSIGNED NOT NULL,
 target_year SMALLINT UNSIGNED NOT NULL,
 auditor_id BIGINT UNSIGNED NOT NULL,
 estimated_minutes INT UNSIGNED NOT NULL,
 unit_name VARCHAR(180) NOT NULL,
 risk_rating ENUM('bajo','medio','alto','critico') NOT NULL,
 risk_score TINYINT UNSIGNED NOT NULL,
 risk_assessment_id BIGINT UNSIGNED NOT NULL,
 added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_plan_unit_year (plan_id,unit_id,target_year),
 INDEX idx_multiyear_load (auditor_id,target_year),
 FOREIGN KEY(plan_id) REFERENCES multiyear_plans(id),
 FOREIGN KEY(unit_id) REFERENCES auditable_units(id),
 FOREIGN KEY(auditor_id) REFERENCES users(id),
 FOREIGN KEY(risk_assessment_id) REFERENCES risk_assessments(id)
) ENGINE=InnoDB;
