<?php
declare(strict_types=1);

const MULTIYEAR_OPS = ['multiyear_create','multiyear_add','multiyear_approve'];

function saveMultiyear(PDO $pdo, array $user, string $op): void {
    if (!manage($user)) throw new DomainException('Solo supervisión puede preparar y aprobar planes multianuales.');
    $actor = (int)$user['id'];
    if ($op === 'multiyear_create') {
        $start = positiveId('start_year');
        $end = positiveId('end_year');
        if ($start < 2000 || $end > 2100 || $end < $start+1 || $end > $start+4)
            throw new DomainException('El horizonte debe contener de 2 a 5 años, entre 2000 y 2100.');
        $title = field('title',180);
        $reason = field('rationale',12000);
        query($pdo,'INSERT INTO multiyear_plans(title,start_year,end_year,rationale,created_by)
            VALUES (?,?,?,?,?)',[$title,$start,$end,$reason,$actor]);
        record($pdo,$actor,null,'plan_multianual_creado','Plan #'.$pdo->lastInsertId()." · $start-$end");
        return;
    }
    $planId = positiveId('plan_id');
    $plan = query($pdo,'SELECT * FROM multiyear_plans WHERE id=? FOR UPDATE',[$planId])->fetch();
    if (!$plan || $plan['approved_by'] !== null)
        throw new DomainException('El plan no existe o ya fue aprobado.');
    if ($op === 'multiyear_add') {
        $unit = positiveId('unit_id');
        $auditor = positiveId('auditor_id');
        $year = positiveId('target_year');
        $hours = positiveId('hours');
        if ($year < (int)$plan['start_year'] || $year > (int)$plan['end_year'] || $hours > 10000)
            throw new DomainException('El año u horas están fuera del horizonte autorizado.');
        if (!query($pdo,"SELECT 1 FROM users WHERE id=? AND active=1 AND role='auditor'",[$auditor])->fetchColumn())
            throw new DomainException('Selecciona un auditor activo.');
        $risk = query($pdo,'SELECT r.id,r.rating,r.score,u.name FROM risk_assessments r
            JOIN auditable_units u ON u.id=r.unit_id
            WHERE r.unit_id=? ORDER BY r.id DESC LIMIT 1',[$unit])->fetch();
        if (!$risk) throw new DomainException('La unidad debe tener una evaluación de riesgo antes de programarla.');
        if (query($pdo,'SELECT 1 FROM multiyear_plan_items WHERE plan_id=? AND unit_id=? AND target_year=?',
            [$planId,$unit,$year])->fetchColumn())
            throw new DomainException('Esta unidad ya está programada para ese año en este plan.');
        query($pdo,'INSERT INTO multiyear_plan_items
            (plan_id,unit_id,target_year,auditor_id,estimated_minutes,unit_name,risk_rating,risk_score,risk_assessment_id)
            VALUES (?,?,?,?,?,?,?,?,?)',
            [$planId,$unit,$year,$auditor,$hours*60,$risk['name'],$risk['rating'],$risk['score'],$risk['id']]);
        record($pdo,$actor,null,'plan_multianual_unidad','Plan #'.$planId.' · unidad #'.$unit." · $year");
        return;
    }
    if ($op === 'multiyear_approve') {
        if ((int)$plan['created_by'] === $actor)
            throw new DomainException('Debe aprobar una cuenta distinta a la que creó el plan.');
        if (!query($pdo,'SELECT 1 FROM multiyear_plan_items WHERE plan_id=? LIMIT 1',[$planId])->fetchColumn())
            throw new DomainException('Agrega al menos una unidad antes de aprobar el plan.');
        query($pdo,'UPDATE multiyear_plans SET approved_by=?,approved_at=CURRENT_TIMESTAMP WHERE id=?',
            [$actor,$planId]);
        record($pdo,$actor,null,'plan_multianual_aprobado','Plan #'.$planId);
        return;
    }
    throw new DomainException('Operación de planificación no autorizada.');
}

function multiyearPlans(PDO $pdo): array {
    return $pdo->query('SELECT p.*,creator.name AS creator_name,approver.name AS approver_name
        FROM multiyear_plans p JOIN users creator ON creator.id=p.created_by
        LEFT JOIN users approver ON approver.id=p.approved_by
        ORDER BY p.id DESC')->fetchAll();
}

function multiyearItems(PDO $pdo): array {
    $items=[];
    foreach ($pdo->query('SELECT i.*,u.name AS auditor_name,c.available_minutes FROM multiyear_plan_items i
        JOIN users u ON u.id=i.auditor_id
        LEFT JOIN auditor_capacity c ON c.user_id=i.auditor_id AND c.plan_year=i.target_year
        ORDER BY i.target_year,FIELD(i.risk_rating,\'critico\',\'alto\',\'medio\',\'bajo\'),i.id') as $row)
        $items[$row['plan_id']][]=$row;
    return $items;
}
