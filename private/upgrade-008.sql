CREATE TABLE IF NOT EXISTS test_revisions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 test_id BIGINT UNSIGNED NOT NULL, author_id BIGINT UNSIGNED NOT NULL,
 result_text TEXT NOT NULL, imported BOOLEAN NOT NULL DEFAULT FALSE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(test_id) REFERENCES tests(id), FOREIGN KEY(author_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS action_revisions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 action_id BIGINT UNSIGNED NOT NULL, author_id BIGINT UNSIGNED NOT NULL,
 evidence TEXT NOT NULL, imported BOOLEAN NOT NULL DEFAULT FALSE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(action_id) REFERENCES actions(id), FOREIGN KEY(author_id) REFERENCES users(id)
) ENGINE=InnoDB;
INSERT INTO test_revisions(test_id,author_id,result_text,imported)
SELECT t.id,t.prepared_by,t.result_text,TRUE FROM tests t
WHERE t.result_text IS NOT NULL AND t.prepared_by IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM test_revisions r WHERE r.test_id=t.id);
INSERT INTO action_revisions(action_id,author_id,evidence,imported)
SELECT a.id,a.owner_id,a.evidence,TRUE FROM actions a
WHERE a.evidence IS NOT NULL AND NOT EXISTS (SELECT 1 FROM action_revisions r WHERE r.action_id=a.id);
