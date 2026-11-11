<?php
declare(strict_types=1);

function generateReminders(PDO $pdo, DateTimeImmutable $today): void {
    $date = $today->format('Y-m-d');
    query($pdo,"INSERT INTO action_reminders(action_id,recipient_id,due_date,kind)
        SELECT a.id,a.owner_id,a.due_date,IF(a.due_date<?,'vencida','proxima')
        FROM actions a JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id
        JOIN audits d ON d.id=t.audit_id JOIN users u ON u.id=a.owner_id
        WHERE a.status='abierta' AND d.status='emitida' AND u.active=1 AND u.role='responsable' AND a.due_date<=?
        ON DUPLICATE KEY UPDATE id=action_reminders.id",[$date,$today->modify('+7 days')->format('Y-m-d')]);
}

function unreadReminders(PDO $pdo, array $user): array {
    return query($pdo,"SELECT r.id,r.kind,r.due_date,r.action_id,a.description,t.audit_id
        FROM action_reminders r JOIN actions a ON a.id=r.action_id AND a.owner_id=r.recipient_id AND a.due_date=r.due_date
        JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id JOIN audits d ON d.id=t.audit_id
        WHERE r.recipient_id=? AND r.read_at IS NULL AND a.status='abierta' AND d.status='emitida'
        AND r.kind=IF(r.due_date<?,'vencida','proxima') ORDER BY r.due_date,r.id",[$user['id'],date('Y-m-d')])->fetchAll();
}

function markReminderRead(PDO $pdo, array $user, int $reminder): void {
    query($pdo,'UPDATE action_reminders SET read_at=CURRENT_TIMESTAMP WHERE id=? AND recipient_id=? AND read_at IS NULL',
        [$reminder,$user['id']]);
}

/** Pronóstico de acciones a notificar, sin crear avisos ni modificar datos. */
function reminderCandidates(PDO $pdo, DateTimeImmutable $today): array {
    return query($pdo,"SELECT
      COALESCE(SUM(a.due_date<?),0) AS overdue,
      COALESCE(SUM(a.due_date>=? AND a.due_date<=?),0) AS approaching
      FROM actions a
      JOIN findings f ON f.id=a.finding_id
      JOIN tests t ON t.id=f.test_id
      JOIN audits d ON d.id=t.audit_id
      JOIN users u ON u.id=a.owner_id
      WHERE a.status='abierta' AND d.status='emitida'
        AND u.active=1 AND u.role='responsable'",
      [$today->format('Y-m-d'),$today->format('Y-m-d'),
       $today->modify('+7 days')->format('Y-m-d')])->fetch();
}

/** Marca la ejecución solo después de procesar todos los avisos con éxito. */
function recordReminderRun(PDO $pdo, DateTimeImmutable $when): void {
    query($pdo,"INSERT INTO scheduled_job_runs(job_key,last_success_at,successful_runs)
        VALUES ('reminders',?,1)
        ON DUPLICATE KEY UPDATE last_success_at=VALUES(last_success_at),
        successful_runs=successful_runs+1",[$when->format('Y-m-d H:i:s')]);
}
/** Un cron diario debería tener al menos una ejecución correcta cada 36 horas. */
function reminderJobHealth(PDO $pdo, DateTimeImmutable $now): array {
    $row=query($pdo,"SELECT last_success_at,successful_runs FROM scheduled_job_runs
        WHERE job_key='reminders'")->fetch();
    if (!$row) return ['healthy'=>false,'last_success_at'=>null,'successful_runs'=>0];
    $last=new DateTimeImmutable($row['last_success_at']);
    return [
        'healthy'=>$last<=$now && $last->modify('+36 hours') >= $now,
        'last_success_at'=>$row['last_success_at'],
        'successful_runs'=>(int)$row['successful_runs'],
    ];
}
