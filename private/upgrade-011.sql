CREATE TABLE IF NOT EXISTS test_templates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(180) NOT NULL,
 category VARCHAR(120) NOT NULL,
 procedure_text TEXT NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 active BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_template_active (active,category,title),
 FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS test_template_uses (
 test_id BIGINT UNSIGNED PRIMARY KEY,
 template_id BIGINT UNSIGNED NOT NULL,
 applied_by BIGINT UNSIGNED NOT NULL,
 applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_template_uses (template_id),
 FOREIGN KEY(test_id) REFERENCES tests(id),
 FOREIGN KEY(template_id) REFERENCES test_templates(id),
 FOREIGN KEY(applied_by) REFERENCES users(id)
) ENGINE=InnoDB;
