<?php
declare(strict_types=1);

/** Escalamiento interno desactivado hasta configurarlo explícitamente. */
function escalationPolicy(PDO $pdo): array {
    $policy=query($pdo,'SELECT * FROM escalation_policy WHERE id=1')->fetch();
    if (!$policy) throw new RuntimeException('Falta ejecutar upgrade-018.sql.');
    return $policy;
}
function saveEscalationPolicy(PDO $pdo, array $user): void {
    if ($user['role']!=='admin') throw new DomainException('Solo administración puede modificar el escalamiento.');
    $days=positiveId('overdue_days');
    if ($days>90) throw new DomainException('Selecciona de 1 a 90 días de retraso.');
    $enabled=choice('enabled',['0','1']);
    query($pdo,'UPDATE escalation_policy SET enabled=?,overdue_days=?,updated_by=? WHERE id=1',
        [(int)$enabled,$days,(int)$user['id']]);
    record($pdo,(int)$user['id'],null,'escalamiento_configurado',"Activo $enabled · $days días");
}
function generateEscalations(PDO $pdo, DateTimeImmutable $today): void {
    $policy=escalationPolicy($pdo);
    if (!(int)$policy['enabled']) return;
    $days=(int)$policy['overdue_days'];
    $cutoff=$today->modify("-$days days")->format('Y-m-d');
    // Una notificación por acción, destinatario, vencimiento y umbral.
    // Las prórrogas generan una nueva clave; la lectura consulta el vencimiento actual.
    query($pdo,"INSERT INTO action_escalations(action_id,recipient_id,due_date,overdue_days)
        SELECT a.id,u.id,a.due_date,?
        FROM actions a JOIN findings f ON f.id=a.finding_id
        JOIN tests t ON t.id=f.test_id JOIN audits d ON d.id=t.audit_id
        JOIN users u ON u.active=1 AND u.role IN ('admin','supervisor')
        WHERE d.status='emitida' AND a.status='abierta' AND a.due_date<=?
        ON DUPLICATE KEY UPDATE id=action_escalations.id",[$days,$cutoff]);
}
function unreadEscalations(PDO $pdo, array $user): array {
    if (!manage($user)) return [];
    $policy=escalationPolicy($pdo);
    if (!(int)$policy['enabled']) return [];
    return query($pdo,"SELECT n.id,n.action_id,n.due_date,n.overdue_days,a.description,t.audit_id
        FROM action_escalations n JOIN actions a ON a.id=n.action_id AND a.due_date=n.due_date
        JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id
        JOIN audits d ON d.id=t.audit_id
        WHERE n.recipient_id=? AND n.read_at IS NULL AND n.overdue_days=?
        AND d.status='emitida' AND a.status='abierta'
        ORDER BY n.due_date,n.id",[$user['id'],$policy['overdue_days']])->fetchAll();
}
function readEscalation(PDO $pdo, array $user, int $id): void {
    if (!manage($user)) throw new DomainException('Solo supervisión puede gestionar escalaciones.');
    query($pdo,'UPDATE action_escalations SET read_at=CURRENT_TIMESTAMP
        WHERE id=? AND recipient_id=? AND read_at IS NULL',[$id,(int)$user['id']]);
}
