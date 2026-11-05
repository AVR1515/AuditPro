ALTER TABLE users MODIFY role ENUM('admin','supervisor','auditor','responsable','riesgos','cumplimiento') NOT NULL;
INSERT INTO catalog(category,code,label) VALUES
 ('rol','riesgos','Riesgos'),('rol','cumplimiento','Cumplimiento')
ON DUPLICATE KEY UPDATE label=label;
