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
