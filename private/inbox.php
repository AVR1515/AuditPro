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
    } elseif (restrictedSupervisor($user)) {
        $sql .= ' AND EXISTS (SELECT 1 FROM audit_assessment_links l JOIN risk_assessments r ON r.id=l.assessment_id JOIN supervisor_unit_grants g ON g.unit_id=r.unit_id WHERE l.audit_id=d.id AND g.user_id=?)';
        $params[]=$user['id'];
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

/** Aceptaciones vigentes que necesitan revisión, incluso en auditorías cerradas. */
function riskAcceptanceDue(PDO $pdo, DateTimeImmutable $today): array {
    return query($pdo,"SELECT r.id,r.review_on,a.description,t.audit_id,d.title AS audit_title
        FROM action_risk_acceptances r
        JOIN actions a ON a.id=r.action_id
        JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id
        JOIN audits d ON d.id=t.audit_id
        WHERE r.status='aceptada' AND r.review_on<=?
        ORDER BY r.review_on,r.id",[$today->modify('+30 days')->format('Y-m-d')])->fetchAll();
}

/** Indicadores que exceden su umbral usando la última medición disponible. */
function breachedKriInbox(PDO $pdo): array {
    return query($pdo,"SELECT k.id,k.title,k.unit,k.direction,k.threshold,
        r.id AS risk_id,r.title AS risk_title,
        o.observed_value,o.observed_on
        FROM grc_kri_indicators k
        JOIN grc_risks r ON r.id=k.risk_id
        JOIN grc_kri_observations o ON o.id=(
            SELECT MAX(h.id) FROM grc_kri_observations h WHERE h.indicator_id=k.id
        )
        WHERE k.active=1 AND
        ((k.direction='maximo' AND o.observed_value>k.threshold)
        OR (k.direction='minimo' AND o.observed_value<k.threshold))
        ORDER BY o.observed_on DESC,k.id")->fetchAll();
}
