<?php
declare(strict_types=1);

/** Vista aislada para supervisores con unidades concedidas, sin datos globales GRC. */
function scopedRiskOverview(PDO $pdo,array $user): array {
    if (!restrictedSupervisor($user)) throw new DomainException('La vista requiere ámbito restringido.');
    return query($pdo,'SELECT r.id,r.unit_id,r.title,r.owner_name,r.inherent,r.residual,r.appetite,r.review_on,u.name AS unit_name
        FROM grc_risks r JOIN auditable_units u ON u.id=r.unit_id
        JOIN supervisor_unit_grants g ON g.unit_id=r.unit_id
        WHERE g.user_id=? ORDER BY r.review_on,r.id',[(int)$user['id']])->fetchAll();
}
function scopedUnitOverview(PDO $pdo,array $user): array {
    if (!restrictedSupervisor($user)) throw new DomainException('La vista requiere ámbito restringido.');
    return query($pdo,'SELECT u.id,u.name,u.process_name,u.accountable,u.cycle_months
        FROM auditable_units u JOIN supervisor_unit_grants g ON g.unit_id=u.id
        WHERE g.user_id=? ORDER BY u.name',[(int)$user['id']])->fetchAll();
}

/** Los KRI se consultan mediante el mismo grant que los riesgos, nunca por ID global. */
function scopedRiskIndicators(PDO $pdo,array $user): array {
    if (!restrictedSupervisor($user)) throw new DomainException('La vista requiere ámbito restringido.');
    $rows=[];
    $sql='SELECT k.id,k.risk_id,k.title,k.unit,k.direction,k.threshold,
        o.observed_value,o.observed_on
        FROM grc_kri_indicators k
        JOIN grc_risks r ON r.id=k.risk_id
        JOIN supervisor_unit_grants g ON g.unit_id=r.unit_id AND g.user_id=?
        LEFT JOIN grc_kri_observations o ON o.id=(
            SELECT MAX(m.id) FROM grc_kri_observations m WHERE m.indicator_id=k.id
        )
        WHERE k.active=1 ORDER BY k.risk_id,k.id';
    foreach (query($pdo,$sql,[(int)$user['id']]) as $indicator) {
        $rows[$indicator['risk_id']][]=$indicator;
    }
    return $rows;
}

/** Cobertura histórica en unidades concedidas, sin duplicar expedientes con varias evaluaciones. */
function scopedUnitCoverage(PDO $pdo,array $user): array {
    if (!restrictedSupervisor($user)) throw new DomainException('La vista requiere ámbito restringido.');
    return query($pdo,'SELECT u.id,u.name,u.cycle_months,
        COUNT(DISTINCT d.id) AS audit_count,MAX(d.year) AS last_audit_year
        FROM auditable_units u
        JOIN supervisor_unit_grants g ON g.unit_id=u.id AND g.user_id=?
        LEFT JOIN risk_assessments r ON r.unit_id=u.id
        LEFT JOIN audit_assessment_links l ON l.assessment_id=r.id
        LEFT JOIN audits d ON d.id=l.audit_id
        GROUP BY u.id,u.name,u.cycle_months ORDER BY u.name',[(int)$user['id']])->fetchAll();
}
