CREATE TABLE IF NOT EXISTS compliance_policies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(180) NOT NULL,
 owner_name VARCHAR(180) NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS compliance_policy_versions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 policy_id BIGINT UNSIGNED NOT NULL,
 version_number INT UNSIGNED NOT NULL,
 policy_text TEXT NOT NULL,
 rationale TEXT NOT NULL,
 effective_on DATE NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 approved_by BIGINT UNSIGNED NULL,
 approved_at TIMESTAMP NULL DEFAULT NULL,
 UNIQUE KEY uq_policy_version (policy_id,version_number),
 INDEX idx_policy_approved (policy_id,approved_at),
 FOREIGN KEY(policy_id) REFERENCES compliance_policies(id),
 FOREIGN KEY(created_by) REFERENCES users(id),
 FOREIGN KEY(approved_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS compliance_policy_obligations (
 version_id BIGINT UNSIGNED NOT NULL,
 obligation_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (version_id,obligation_id),
 FOREIGN KEY(version_id) REFERENCES compliance_policy_versions(id),
 FOREIGN KEY(obligation_id) REFERENCES grc_obligations(id)
) ENGINE=InnoDB;
