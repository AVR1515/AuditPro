CREATE TABLE IF NOT EXISTS action_reminders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 action_id BIGINT UNSIGNED NOT NULL, recipient_id BIGINT UNSIGNED NOT NULL,
 due_date DATE NOT NULL, kind ENUM('proxima','vencida') NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, read_at TIMESTAMP NULL DEFAULT NULL,
 UNIQUE(action_id,recipient_id,due_date,kind), INDEX recipient_read(recipient_id,read_at),
 FOREIGN KEY(action_id) REFERENCES actions(id), FOREIGN KEY(recipient_id) REFERENCES users(id)
) ENGINE=InnoDB;
