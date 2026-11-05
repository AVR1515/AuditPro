<?php
declare(strict_types=1);

function savePlan(PDO $pdo, array $user, string $op): void {
    if (!manage($user)) throw new DomainException('Acceso restringido a supervisión.');
    $actor = (int) $user['id'];
    if ($op === 'snapshot_plan') {
        $year = positiveId('year');
        if ($year < 2000 || $year > 2100) throw new DomainException('El año debe estar entre 2000 y 2100.');
        $reason = field('reason',12000);
        $revision = (int) query($pdo,'SELECT COALESCE(MAX(revision),0)+1 FROM annual_plans WHERE plan_year=?',[$year])->fetchColumn();
        query($pdo,'INSERT INTO annual_plans(plan_year,revision,reason,created_by) VALUES (?,?,?,?)',[$year,$revision,$reason,$actor]);
        $id = (int) $pdo->lastInsertId();
        $copied = query($pdo,'INSERT INTO annual_plan_items(plan_id,audit_id,title,entity,risk,owner_name,original_status)
            SELECT ?,a.id,a.title,a.entity,a.risk,u.name,a.status FROM audits a JOIN users u ON u.id=a.owner_id WHERE a.year=?',[$id,$year]);
        if (!$copied->rowCount()) throw new DomainException('Registra expedientes para el año antes de crear la versión.');
    } else {
        $id = positiveId('plan_id');
        $plan = query($pdo,'SELECT * FROM annual_plans WHERE id=? FOR UPDATE',[$id])->fetch();
        if (!$plan || $plan['approved_by'] !== null || (int) $plan['created_by'] === $actor) {
            throw new DomainException('La versión pendiente debe aprobarla un supervisor o administrador distinto de quien la preparó.');
        }
        query($pdo,'UPDATE annual_plans SET approved_by=?,approved_at=CURRENT_TIMESTAMP WHERE id=?',[$actor,$id]);
    }
    record($pdo,$actor,null,$op,(string) $id);
}
