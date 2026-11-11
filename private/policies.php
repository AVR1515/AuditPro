<?php
declare(strict_types=1);

const POLICY_OPS=['policy_create','policy_version','policy_link','policy_approve'];

function saveCompliancePolicy(PDO $pdo,array $user,string $op): void {
    if (!canCompliance($user)) throw new DomainException('Se requiere acceso a Cumplimiento.');
    $actor=(int)$user['id'];
    if ($op==='policy_create') {
        query($pdo,'INSERT INTO compliance_policies(title,owner_name,created_by) VALUES (?,?,?)',
            [field('title',180),field('owner_name',180),$actor]);
        $id=(int)$pdo->lastInsertId();
        record($pdo,$actor,null,'politica_creada',"Política #$id");
        return;
    }
    $policyId=positiveId('policy_id');
    $policy=query($pdo,'SELECT id FROM compliance_policies WHERE id=?',[$policyId])->fetch();
    if (!$policy) throw new DomainException('Política no disponible.');
    if ($op==='policy_version') {
        // Bloquear el registro matriz serializa el número de versión.
        query($pdo,'SELECT id FROM compliance_policies WHERE id=? FOR UPDATE',[$policyId]);
        $pending=query($pdo,'SELECT 1 FROM compliance_policy_versions WHERE policy_id=? AND approved_by IS NULL LIMIT 1',
            [$policyId])->fetchColumn();
        if ($pending) throw new DomainException('Aprueba primero la versión pendiente.');
        $number=(int)query($pdo,'SELECT COALESCE(MAX(version_number),0)+1 FROM compliance_policy_versions WHERE policy_id=?',
            [$policyId])->fetchColumn();
        $effective=dateField('effective_on');
        query($pdo,'INSERT INTO compliance_policy_versions
            (policy_id,version_number,policy_text,rationale,effective_on,created_by) VALUES (?,?,?,?,?,?)',
            [$policyId,$number,field('policy_text',30000),field('rationale',12000),$effective,$actor]);
        $versionId=(int)$pdo->lastInsertId();
        record($pdo,$actor,null,'politica_version_creada',"Política #$policyId · v$number · #$versionId");
        return;
    }
    $versionId=positiveId('version_id');
    $version=query($pdo,'SELECT * FROM compliance_policy_versions WHERE id=? AND policy_id=? FOR UPDATE',
        [$versionId,$policyId])->fetch();
    if (!$version) throw new DomainException('Versión de política no disponible.');
    if ($op==='policy_link') {
        if ($version['approved_by']!==null) throw new DomainException('Una versión aprobada es inmutable.');
        $obligationId=positiveId('obligation_id');
        if (!query($pdo,'SELECT 1 FROM grc_obligations WHERE id=?',[$obligationId])->fetchColumn())
            throw new DomainException('Obligación no disponible.');
        query($pdo,'INSERT INTO compliance_policy_obligations(version_id,obligation_id) VALUES (?,?)
            ON DUPLICATE KEY UPDATE version_id=version_id',[$versionId,$obligationId]);
        record($pdo,$actor,null,'politica_obligacion',"Versión #$versionId · obligación #$obligationId");
        return;
    }
    if ($op==='policy_approve') {
        if (!manage($user)) throw new DomainException('Solo supervisión aprueba políticas.');
        if ($version['approved_by']!==null) throw new DomainException('Versión ya aprobada.');
        if ((int)$version['created_by']===$actor)
            throw new DomainException('La aprobación requiere una cuenta distinta del autor.');
        if (!query($pdo,'SELECT 1 FROM compliance_policy_obligations WHERE version_id=? LIMIT 1',
            [$versionId])->fetchColumn())
            throw new DomainException('Vincula al menos una obligación antes de aprobar.');
        query($pdo,'UPDATE compliance_policy_versions SET approved_by=?,approved_at=CURRENT_TIMESTAMP WHERE id=?',
            [$actor,$versionId]);
        record($pdo,$actor,null,'politica_aprobada',"Política #$policyId · versión #$versionId");
        return;
    }
    throw new DomainException('Operación de política no autorizada.');
}
function compliancePolicyRows(PDO $pdo): array {
    return $pdo->query('SELECT p.*,v.id AS version_id,v.version_number,v.policy_text,v.rationale,
        v.effective_on,v.created_by AS version_author,v.approved_by,v.approved_at,
        creator.name AS author_name,approver.name AS approver_name
        FROM compliance_policies p
        LEFT JOIN compliance_policy_versions v ON v.policy_id=p.id
        LEFT JOIN users creator ON creator.id=v.created_by
        LEFT JOIN users approver ON approver.id=v.approved_by
        ORDER BY p.id DESC,v.version_number DESC')->fetchAll();
}
function policyObligationLinks(PDO $pdo): array {
    $links=[];
    foreach ($pdo->query('SELECT l.version_id,o.framework,o.reference_code,o.title FROM compliance_policy_obligations l
        JOIN grc_obligations o ON o.id=l.obligation_id ORDER BY o.framework,o.reference_code') as $row) {
        $links[$row['version_id']][]=$row;
    }
    return $links;
}
