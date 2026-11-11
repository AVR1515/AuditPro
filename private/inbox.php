<?php
declare(strict_types=1);

function actionInbox(PDO $pdo, array $user): array {
    $sql = "SELECT a.id,a.description,a.due_date,a.status,t.audit_id,u.name
        FROM actions a JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id
        JOIN audits d ON d.id=t.audit_id JOIN users u ON u.id=a.owner_id
        WHERE d.status='emitida' AND a.status<>'cerrada'";
    $params = [];
    if ($user['role'] === 'responsable') {
        $sql .= ' AND a.owner_id=?';
        $params[] = $user['id'];
    } elseif (!manage($user)) {
        $sql .= ' AND d.owner_id=?';
        $params[] = $user['id'];
    }
    return query($pdo,$sql . ' ORDER BY a.due_date,a.id',$params)->fetchAll();
}

function inboxStatus(array $row, DateTimeImmutable $today): string {
    $due = DateTimeImmutable::createFromFormat('!Y-m-d',$row['due_date'],$today->getTimezone());
    if ($due < $today) return 'Vencida';
    if ($due <= $today->modify('+7 days')) return 'Próxima a vencer';
    return 'En plazo';
}

/**
 * Las solicitudes son visibles solo para supervisión y para el auditor
 * asignado; los responsables de acciones no reciben información de campo.
 */
function evidenceRequestInbox(PDO $pdo, array $user): array {
    if (!manage($user) && $user['role'] !== 'auditor') return [];
    $sql = "SELECT r.id,r.audit_id,r.subject,r.due_on,r.status,d.title AS audit_title,
            u.name AS assigned_auditor
        FROM evidence_requests r
        JOIN audits d ON d.id=r.audit_id
        JOIN users u ON u.id=d.owner_id
        WHERE r.status<>'cerrada' AND d.status IN ('en_campo','en_revision')";
    $params = [];
    if (!manage($user)) {
        $sql .= ' AND d.owner_id=? AND r.status=\'abierta\'';
        $params[] = $user['id'];
    }
    return query($pdo,$sql.' ORDER BY r.due_on,r.id',$params)->fetchAll();
}
