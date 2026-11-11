ALTER TABLE attachments
 ADD COLUMN retention_until DATE NULL,
 ADD COLUMN legal_hold BOOLEAN NOT NULL DEFAULT FALSE,
 ADD COLUMN purged_at TIMESTAMP NULL DEFAULT NULL,
 ADD COLUMN purged_by BIGINT UNSIGNED NULL,
 MODIFY COLUMN content MEDIUMBLOB NULL,
 ADD INDEX idx_attachment_retention(retention_until,legal_hold,purged_at),
 ADD CONSTRAINT fk_attachment_purge_actor FOREIGN KEY(purged_by) REFERENCES users(id);
