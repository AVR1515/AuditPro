<?php
declare(strict_types=1);

function assess(PDO $pdo, array $user, string $op): void {
    if (!manage($user)) throw new DomainException('Acceso restringido a supervisión.');
    $actor = (int) $user['id'];
    if ($op === 'unit') {
        $months = positiveId('cycle');
        if ($months > 120) throw new DomainException('El ciclo debe estar entre 1 y 120 meses.');
        $name = field('name');
        if (query($pdo,'SELECT 1 FROM auditable_units WHERE name=?',[$name])->fetchColumn()) throw new DomainException('Ya existe una unidad con ese nombre.');
        query($pdo,'INSERT INTO auditable_units(name,process_name,accountable,cycle_months) VALUES (?,?,?,?)',
            [$name,field('process'),field('accountable'),$months]);
    } else {
        $unit = positiveId('unit_id');
        $likelihood = positiveId('likelihood');
        $impact = positiveId('impact');
        if (max($likelihood,$impact) > 5 || !query($pdo,'SELECT 1 FROM auditable_units WHERE id=?',[$unit])->fetchColumn()) {
            throw new DomainException('Selecciona una unidad y valores de 1 a 5.');
        }
        $score = $likelihood * $impact;
        $rating = match (true) {
            $score >= 20 => 'critico',
            $score >= 12 => 'alto',
            $score >= 6 => 'medio',
            default => 'bajo',
        };
        query($pdo,'INSERT INTO risk_assessments(unit_id,assessor_id,methodology,likelihood,impact,score,rating,rationale) VALUES (?,?,?,?,?,?,?,?)',
            [$unit,$actor,'P×I v1',$likelihood,$impact,$score,$rating,field('rationale',12000)]);
    }
    record($pdo,$actor,null,$op,(string) $pdo->lastInsertId());
}

function assessments(PDO $pdo, bool $latest = false): array {
    return $pdo->query('SELECT r.*,u.name, p.name AS assessor FROM risk_assessments r
        JOIN auditable_units u ON u.id=r.unit_id JOIN users p ON p.id=r.assessor_id' .
        ($latest ? ' WHERE r.id=(SELECT MAX(x.id) FROM risk_assessments x WHERE x.unit_id=r.unit_id)' : '') .
        ' ORDER BY r.score DESC, r.id DESC')->fetchAll();
}

/** Cobertura histórica vinculada al universo: no confunde auditorías manuales con unidades evaluadas. */
function unitCoverage(PDO $pdo): array {
    return $pdo->query("SELECT u.id,u.name,u.process_name,u.cycle_months,
        r.rating,r.score,r.created_at AS assessed_at,
        MAX(a.year) AS last_audit_year,
        COUNT(DISTINCT a.id) AS audit_count,
        COUNT(DISTINCT CASE WHEN a.status='cerrada' THEN a.id END) AS closed_count
        FROM auditable_units u
        LEFT JOIN risk_assessments r ON r.id=(
            SELECT MAX(x.id) FROM risk_assessments x WHERE x.unit_id=u.id
        )
        LEFT JOIN risk_assessments historic ON historic.unit_id=u.id
        LEFT JOIN audit_assessment_links l ON l.assessment_id=historic.id
        LEFT JOIN audits a ON a.id=l.audit_id
        GROUP BY u.id,u.name,u.process_name,u.cycle_months,r.rating,r.score,r.created_at
        ORDER BY CASE r.rating WHEN 'critico' THEN 4 WHEN 'alto' THEN 3 WHEN 'medio' THEN 2 WHEN 'bajo' THEN 1 ELSE 0 END DESC,u.name")->fetchAll();
}

function coverageStatus(array $row, int $year): string {
    if ($row['last_audit_year'] === null) return 'Sin auditorías vinculadas';
    $cycleYears = max(1,(int) ceil(((int) $row['cycle_months']) / 12));
    return $year-(int) $row['last_audit_year'] >= $cycleYears ? 'Revisión de cobertura requerida' : 'Dentro del ciclo aproximado';
}
