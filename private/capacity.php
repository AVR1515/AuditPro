<?php
declare(strict_types=1);

/** Capacidad anual por auditor sin sobrescribir los historiales de trabajo. */
function saveAuditorCapacity(PDO $pdo, array $user): void {
    if (!manage($user)) throw new DomainException('Solo supervisión puede asignar capacidad.');
    $year = positiveId('year');
    if ($year < 2000 || $year > 2100) throw new DomainException('El año debe estar entre 2000 y 2100.');
    $auditorId = positiveId('auditor_id');
    if (!query($pdo,"SELECT 1 FROM users WHERE id=? AND active=1 AND role='auditor'",[$auditorId])->fetchColumn()) {
        throw new DomainException('Selecciona un auditor activo.');
    }
    $hours = filter_var($_POST['hours'] ?? null,FILTER_VALIDATE_INT);
    if ($hours === false || $hours === null || $hours < 0 || $hours > 10000) {
        throw new DomainException('La capacidad anual debe estar entre 0 y 10.000 horas.');
    }
    query($pdo,'INSERT INTO auditor_capacity(user_id,plan_year,available_minutes,updated_by) VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE available_minutes=VALUES(available_minutes),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP',
        [$auditorId,$year,$hours*60,(int) $user['id']]);
    record($pdo,(int) $user['id'],null,'capacidad_actualizada',"$year · auditor #$auditorId · $hours h");
}

function capacityRows(PDO $pdo, int $year): array {
    return query($pdo,"SELECT u.id,u.name,c.available_minutes,
        COALESCE((SELECT SUM(s.planned_minutes) FROM audits a JOIN audit_schedule s ON s.audit_id=a.id
            WHERE a.owner_id=u.id AND a.year=?),0) AS planned_minutes,
        COALESCE((SELECT SUM(l.minutes) FROM audit_time l JOIN audits a ON a.id=l.audit_id
            WHERE a.owner_id=u.id AND a.year=?),0) AS worked_minutes,
        (SELECT COUNT(*) FROM audits a WHERE a.owner_id=u.id AND a.year=?) AS audit_count
        FROM users u LEFT JOIN auditor_capacity c ON c.user_id=u.id AND c.plan_year=?
        WHERE u.role='auditor' AND u.active=1 ORDER BY u.name,u.id",[$year,$year,$year,$year])->fetchAll();
}
