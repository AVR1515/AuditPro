CREATE TABLE IF NOT EXISTS evidence_request_attachments (
 response_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 attachment_id BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY(response_id) REFERENCES evidence_request_responses(id),
 FOREIGN KEY(attachment_id) REFERENCES attachments(id)
) ENGINE=InnoDB;
