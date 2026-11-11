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
