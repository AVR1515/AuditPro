<?php
declare(strict_types=1);

/**
 * Panel administrativo de solo lectura. No declara aprobado ningún control
 * jurídico, humano, visual, de respaldo o de despliegue externo.
 */
function releaseReadiness(PDO $pdo, array $config, array $user): array {
    if ($user['role']!=='admin') throw new DomainException('Solo administración puede consultar preparación de salida.');
    $preflight=preflightChecks($pdo,$config);
    $scheduler=reminderJobHealth($pdo,new DateTimeImmutable());
    $retention=retentionReadiness($pdo,new DateTimeImmutable('today'));
    $operations=query($pdo,"SELECT
        (SELECT COUNT(*) FROM evidence_requests WHERE status IN ('abierta','respondida')) AS evidence_open,
        (SELECT COUNT(*) FROM action_risk_acceptances WHERE status='pendiente') AS risk_pending,
        (SELECT COUNT(*) FROM obligation_exceptions WHERE status='pendiente') AS exceptions_pending,
        (SELECT COUNT(*) FROM compliance_policy_versions WHERE approved_by IS NULL) AS policies_pending")->fetch();
    return [
        'preflight'=>$preflight,
        'preflight_ready'=>preflightReady($preflight),
        'scheduler'=>$scheduler,
        'retention'=>$retention,
        'operations'=>$operations,
    ];
}
