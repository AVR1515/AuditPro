<?php
declare(strict_types=1);

/** Supervisión global por defecto. El alcance restringido se habilita explícitamente. */
function restrictedSupervisor(array $user): bool {
    return $user['role']==='supervisor' && (int)($user['unit_restricted'] ?? 0)===1;
}
function allowedUnit(PDO $pdo, array $user, int $unitId): bool {
    if (!restrictedSupervisor($user)) return true;
    return (bool)query($pdo,'SELECT 1 FROM supervisor_unit_grants WHERE user_id=? AND unit_id=?',
        [(int)$user['id'],$unitId])->fetchColumn();
}
function allowedAudit(PDO $pdo, array $user, int $auditId): bool {
    if (!restrictedSupervisor($user)) return true;
    return (bool)query($pdo,'SELECT 1 FROM audit_assessment_links l
        JOIN risk_assessments r ON r.id=l.assessment_id
        JOIN supervisor_unit_grants g ON g.unit_id=r.unit_id
        WHERE l.audit_id=? AND g.user_id=?',
        [$auditId,(int)$user['id']])->fetchColumn();
}
function scopedGlobalDeny(array $user): bool {
    return restrictedSupervisor($user);
}
function saveUnitGrant(PDO $pdo, array $user, string $op): void {
    if ($user['role']!=='admin') throw new DomainException('Solo administración configura los permisos por unidad.');
    $supervisorId=positiveId('supervisor_id');
    $target=query($pdo,"SELECT id FROM users WHERE id=? AND role='supervisor' AND active=1 FOR UPDATE",
        [$supervisorId])->fetch();
    if (!$target) throw new DomainException('Selecciona un supervisor activo.');
    $actor=(int)$user['id'];
    if ($op==='unit_scope') {
        $restricted=choice('restricted',['0','1']);
        query($pdo,'UPDATE users SET unit_restricted=? WHERE id=?',[(int)$restricted,$supervisorId]);
        record($pdo,$actor,null,'alcance_supervisor',"Supervisor #$supervisorId · restringido $restricted");
        return;
    }
    $unitId=positiveId('unit_id');
    if (!query($pdo,'SELECT 1 FROM auditable_units WHERE id=?',[$unitId])->fetchColumn())
        throw new DomainException('La unidad seleccionada no existe.');
    if ($op==='unit_grant') {
        query($pdo,'INSERT INTO supervisor_unit_grants(user_id,unit_id,granted_by) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE granted_by=VALUES(granted_by)',[$supervisorId,$unitId,$actor]);
        record($pdo,$actor,null,'permiso_unidad_asignado',"Supervisor #$supervisorId · unidad #$unitId");
    } elseif ($op==='unit_revoke') {
        query($pdo,'DELETE FROM supervisor_unit_grants WHERE user_id=? AND unit_id=?',[$supervisorId,$unitId]);
        record($pdo,$actor,null,'permiso_unidad_revocado',"Supervisor #$supervisorId · unidad #$unitId");
    } else throw new DomainException('Operación de permisos no autorizada.');
}
