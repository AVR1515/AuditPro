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

/**
 * Trazabilidad de controles de riesgos autorizados. Los vínculos con
 * otras unidades no se devuelven, incluso si comparten el mismo control.
 */
function scopedRiskControls(PDO $pdo,array $user): array {
    if (!restrictedSupervisor($user)) throw new DomainException('La vista requiere ámbito restringido.');
    $result=[];
    $sql='SELECT r.id AS risk_id,c.id AS control_id,c.title,c.owner_name,c.frequency,c.description
        FROM grc_risks r
        JOIN supervisor_unit_grants g ON g.unit_id=r.unit_id AND g.user_id=?
        JOIN grc_risk_controls rc ON rc.risk_id=r.id
        JOIN grc_controls c ON c.id=rc.control_id
        ORDER BY r.id,c.title,c.id';
    foreach (query($pdo,$sql,[(int)$user['id']]) as $row) {
        $result[$row['risk_id']][]=$row;
    }
    return $result;
}

/**
 * Solo pruebas que comparten control con un riesgo permitido Y pertenecen a
 * expedientes autorizados; nunca se exponen pruebas de otras unidades.
 */
function scopedControlTests(PDO $pdo,array $user): array {
    if (!restrictedSupervisor($user)) throw new DomainException('La vista requiere ámbito restringido.');
    $result=[];
    $sql='SELECT DISTINCT r.id AS risk_id,c.id AS control_id,t.id AS test_id,
        t.title AS test_title,t.status AS test_status,t.audit_id,d.title AS audit_title
        FROM grc_risks r
        JOIN supervisor_unit_grants g ON g.unit_id=r.unit_id AND g.user_id=?
        JOIN grc_risk_controls rc ON rc.risk_id=r.id
        JOIN grc_controls c ON c.id=rc.control_id
        JOIN grc_control_tests ct ON ct.control_id=c.id
        JOIN tests t ON t.id=ct.test_id
        JOIN audits d ON d.id=t.audit_id
        WHERE EXISTS (
            SELECT 1 FROM audit_assessment_links l
            JOIN risk_assessments assessment ON assessment.id=l.assessment_id
            JOIN supervisor_unit_grants permitted ON permitted.unit_id=assessment.unit_id
            WHERE l.audit_id=t.audit_id AND permitted.user_id=?
        ) ORDER BY r.id,c.id,t.id';
    foreach (query($pdo,$sql,[(int)$user['id'],(int)$user['id']]) as $row) {
        $result[$row['risk_id']][$row['control_id']][]=$row;
    }
    return $result;
}
