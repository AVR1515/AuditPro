<?php
declare(strict_types=1);

const TEMPLATE_OPS = ['template_create','template_archive','template_apply'];

function canViewTemplates(array $user): bool {
    return manage($user) || $user['role'] === 'auditor';
}

function templateCatalog(PDO $pdo): array {
    return query($pdo, 'SELECT t.*,u.name AS creator FROM test_templates t JOIN users u ON u.id=t.created_by WHERE t.active=1 ORDER BY t.category,t.title,t.id')->fetchAll();
}

function saveTemplate(PDO $pdo, array $user, string $op): ?int {
    $actor = (int) $user['id'];
    if ($op === 'template_create') {
        if (!manage($user)) throw new DomainException('Solo supervisión puede crear plantillas.');
        $title = field('title',180);
        $category = field('category',120);
        $procedure = field('procedure',12000);
        query($pdo,'INSERT INTO test_templates(title,category,procedure_text,created_by) VALUES (?,?,?,?)',
            [$title,$category,$procedure,$actor]);
        record($pdo,$actor,null,'plantilla_creada','Plantilla #' . $pdo->lastInsertId());
        return null;
    }

    if ($op === 'template_archive') {
        if (!manage($user)) throw new DomainException('Solo supervisión puede archivar plantillas.');
        $templateId = positiveId('template_id');
        $template = query($pdo,'SELECT id FROM test_templates WHERE id=? AND active=1 FOR UPDATE',[$templateId])->fetch();
        if (!$template) throw new DomainException('Plantilla no disponible.');
        query($pdo,'UPDATE test_templates SET active=0 WHERE id=?',[$templateId]);
        record($pdo,$actor,null,'plantilla_archivada','Plantilla #' . $templateId);
        return null;
    }

    if ($op === 'template_apply') {
        $auditId = positiveId('audit_id');
        $audit = accessible($pdo,$user,$auditId,true);
        if ($user['role'] !== 'auditor' || (int) $audit['owner_id'] !== $actor || $audit['status'] !== 'en_campo') {
            throw new DomainException('Solo el auditor asignado puede añadir pruebas durante el trabajo de campo.');
        }
        $templateId = positiveId('template_id');
        $template = query($pdo,'SELECT id,title,procedure_text FROM test_templates WHERE id=? AND active=1',[$templateId])->fetch();
        if (!$template) throw new DomainException('Selecciona una plantilla activa.');
        // Copia inmutable del procedimiento: archivar la plantilla no altera pruebas creadas.
        query($pdo,'INSERT INTO tests(audit_id,title,procedure_text) VALUES (?,?,?)',
            [$auditId,$template['title'],$template['procedure_text']]);
        $testId = (int) $pdo->lastInsertId();
        query($pdo,'INSERT INTO test_template_uses(test_id,template_id,applied_by) VALUES (?,?,?)',
            [$testId,$templateId,$actor]);
        record($pdo,$actor,$auditId,'plantilla_aplicada','Plantilla #' . $templateId . ' → prueba #' . $testId);
        return $auditId;
    }
    throw new DomainException('Operación de plantilla no autorizada.');
}
