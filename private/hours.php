<?php
declare(strict_types=1);

function saveHours(PDO $pdo, array $user, array $audit, string $op): string {
    if ($op === 'schedule' && manage($user) && !in_array($audit['status'],['emitida','cerrada'],true)) {
        $start = dateField('start');
        $end = dateField('end');
        $hours = positiveId('hours');
        if ($end < $start || $hours > 10000) throw new DomainException('Revisa el rango de fechas y estima entre 1 y 10.000 horas.');
        $old = query($pdo,'SELECT * FROM audit_schedule WHERE audit_id=?',[$audit['id']])->fetch();
        query($pdo,'INSERT INTO audit_schedule(audit_id,start_on,end_on,planned_minutes) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE start_on=VALUES(start_on),end_on=VALUES(end_on),planned_minutes=VALUES(planned_minutes)',
            [$audit['id'],$start,$end,$hours*60]);
        $before = $old ? $old['start_on'] . ' / ' . $old['end_on'] . ' / ' . ($old['planned_minutes']/60) . ' h → ' : '';
        return $before . "$start / $end / $hours h. " . field('note',150);
    }
    if ($op === 'log_time' && $user['role'] === 'auditor' && $audit['status'] === 'en_campo') {
        $day = dateField('worked');
        $minutes = positiveId('minutes');
        if ($day > date('Y-m-d') || $minutes > 1440) throw new DomainException('Registra una fecha pasada o de hoy y hasta 1.440 minutos.');
        query($pdo,'SELECT id FROM users WHERE id=? FOR UPDATE',[$user['id']]);
        $total = (int) query($pdo,'SELECT COALESCE(SUM(minutes),0) FROM audit_time WHERE user_id=? AND worked_on=?',[$user['id'],$day])->fetchColumn();
        if ($total + $minutes > 1440) throw new DomainException('La jornada superaría las 24 horas registradas.');
        query($pdo,'INSERT INTO audit_time(audit_id,user_id,worked_on,minutes,description) VALUES (?,?,?,?,?)',
            [$audit['id'],$user['id'],$day,$minutes,field('description')]);
        return "$day · $minutes min";
    }
    throw new DomainException('Este expediente no permite registrar la programación o el tiempo.');
}

function displayHours(int $minutes): string {
    return intdiv($minutes,60) . ' h ' . ($minutes % 60) . ' min';
}
