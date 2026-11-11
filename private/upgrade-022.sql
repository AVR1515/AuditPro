ALTER TABLE audits ADD COLUMN closed_on DATE NULL;
CREATE INDEX idx_audit_closed_on ON audits(closed_on);
