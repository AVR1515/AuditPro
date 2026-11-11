<?php
declare(strict_types=1);

/**
 * La retención es opt-in. Sin fecha no existe eliminación automática.
 * El purgado borra contenido pero mantiene la huella y los metadatos
 * para auditoría; requiere expiración, expediente cerrado y confirmación.
 */
function retentionRows(PDO $pdo): array {
    return $pdo->query("SELECT b.id,b.filename,b.bytes,b.sha256,b.created_at,b.retention_until,b.legal_hold,
        b.purged_at,COALESCE(t.audit_id,at.audit_id) AS audit_id,d.title AS audit_title,d.status AS audit_status,
        pa.id AS authorization_id,pa.status AS authorization_status,pa.requested_by AS authorization_requester,
        pa.decided_by AS authorization_decider,pa.decided_at AS authorization_decided_at
        FROM attachments b LEFT JOIN tests t ON t.id=b.test_id
        LEFT JOIN actions a ON a.id=b.action_id
        LEFT JOIN findings f ON f.id=a.finding_id
        LEFT JOIN tests at ON at.id=f.test_id
        JOIN audits d ON d.id=COALESCE(t.audit_id,at.audit_id)
        LEFT JOIN attachment_purge_authorizations pa ON pa.id=(
            SELECT MAX(x.id) FROM attachment_purge_authorizations x WHERE x.attachment_id=b.id)
        ORDER BY b.id DESC LIMIT 250")->fetchAll();
}
function saveRetention(PDO $pdo, array $user, string $op): void {
    if ($user['role']!=='admin') throw new DomainException('Solo administración puede gestionar retención.');
    $id=positiveId('attachment_id');
    $item=query($pdo,"SELECT b.*,COALESCE(t.audit_id,at.audit_id) AS audit_id,d.status AS audit_status
        FROM attachments b LEFT JOIN tests t ON t.id=b.test_id
        LEFT JOIN actions a ON a.id=b.action_id LEFT JOIN findings f ON f.id=a.finding_id
        LEFT JOIN tests at ON at.id=f.test_id
        JOIN audits d ON d.id=COALESCE(t.audit_id,at.audit_id)
        WHERE b.id=? FOR UPDATE",[$id])->fetch();
    if (!$item || $item['purged_at']!==null) throw new DomainException('Evidencia no disponible para retención.');
    $actor=(int)$user['id'];
    if ($op==='retention_set') {
        $date=dateField('retention_until');
        $minimum=(new DateTimeImmutable($item['created_at']))->modify('+365 days')->format('Y-m-d');
        if ($date<$minimum) throw new DomainException('Retención mínima de 365 días desde la carga.');
        $hold=choice('legal_hold',['0','1']);
        query($pdo,'UPDATE attachments SET retention_until=?,legal_hold=? WHERE id=?',[$date,(int)$hold,$id]);
        query($pdo,"UPDATE attachment_purge_authorizations SET status='cancelada'
            WHERE attachment_id=? AND status IN ('pendiente','aprobada')",[$id]);
        record($pdo,$actor,(int)$item['audit_id'],'retencion_actualizada',"Archivo #$id · hasta $date · bloqueo $hold");
        return;
    }
    if ($op==='retention_request') {
        if (!retentionEligible($item,new DateTimeImmutable('today')))
            throw new DomainException('Esta evidencia todavía no cumple los requisitos de purgado.');
        query($pdo,"UPDATE attachment_purge_authorizations SET status='cancelada'
            WHERE attachment_id=? AND status='aprobada'
            AND decided_at<DATE_SUB(NOW(),INTERVAL 7 DAY)",[$id]);
        if (query($pdo,"SELECT 1 FROM attachment_purge_authorizations
            WHERE attachment_id=? AND status IN ('pendiente','aprobada') LIMIT 1",[$id])->fetchColumn())
            throw new DomainException('Ya existe una solicitud o autorización de purgado vigente.');
        $reason=field('rationale',12000);
        query($pdo,'INSERT INTO attachment_purge_authorizations
            (attachment_id,requested_by,rationale) VALUES (?,?,?)',[$id,$actor,$reason]);
        $requestId=(int)$pdo->lastInsertId();
        record($pdo,$actor,(int)$item['audit_id'],'purga_solicitada',
            "Archivo #$id · autorización #$requestId");
        return;
    }
    if ($op==='retention_decide') {
        $requestId=positiveId('authorization_id');
        $authorization=query($pdo,'SELECT * FROM attachment_purge_authorizations
            WHERE id=? AND attachment_id=? FOR UPDATE',[$requestId,$id])->fetch();
        if (!$authorization || $authorization['status']!=='pendiente')
            throw new DomainException('La solicitud ya no admite decisiones.');
        if ((int)$authorization['requested_by']===$actor)
            throw new DomainException('Otra cuenta administradora debe aprobar el purgado.');
        $decision=choice('decision',['aprobada','rechazada']);
        if ($decision==='aprobada' && !retentionEligible($item,new DateTimeImmutable('today')))
            throw new DomainException('La evidencia dejó de ser elegible para purgado.');
        $reason=field('decision_note',12000);
        query($pdo,'UPDATE attachment_purge_authorizations
            SET status=?,decided_by=?,decided_at=CURRENT_TIMESTAMP,decision_note=? WHERE id=?',
            [$decision,$actor,$reason,$requestId]);
        record($pdo,$actor,(int)$item['audit_id'],'purga_'.$decision,
            "Archivo #$id · autorización #$requestId");
        return;
    }
    if ($op==='retention_purge') {
        if (field('confirmation',20)!=='PURGAR') throw new DomainException('Escribe PURGAR para confirmar la eliminación.');
        if (!retentionEligible($item,new DateTimeImmutable('today'))) {
            throw new DomainException('Solo pueden purgarse evidencias de expedientes cerrados cuyo plazo haya vencido, sin bloqueo legal.');
        }
        $authorization=query($pdo,"SELECT * FROM attachment_purge_authorizations
            WHERE attachment_id=? AND status='aprobada' AND decided_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)
            ORDER BY id DESC LIMIT 1 FOR UPDATE",[$id])->fetch();
        if (!$authorization) throw new DomainException('Se requiere aprobación independiente vigente para purgar.');
        if ((int)$authorization['requested_by']===(int)$authorization['decided_by'])
            throw new DomainException('La autorización requiere aprobación independiente.');
        query($pdo,'UPDATE attachments SET content=NULL,purged_at=CURRENT_TIMESTAMP,purged_by=? WHERE id=?',[$actor,$id]);
        query($pdo,"UPDATE attachment_purge_authorizations
            SET status='ejecutada',executed_by=?,executed_at=CURRENT_TIMESTAMP WHERE id=?",
            [$actor,$authorization['id']]);
        record($pdo,$actor,(int)$item['audit_id'],'evidencia_purgada',"Archivo #$id · SHA-256 ".$item['sha256']);
        return;
    }
    throw new DomainException('Operación de retención no autorizada.');
}

/** Resumen de retención sobre el conjunto completo, sin el límite visual de 250 archivos. */
function retentionReadiness(PDO $pdo, DateTimeImmutable $today): array {
    return query($pdo,"SELECT
      COUNT(*) AS total,
      COALESCE(SUM(b.purged_at IS NOT NULL),0) AS purged,
      COALESCE(SUM(b.purged_at IS NULL AND b.legal_hold=1),0) AS legal_holds,
      COALESCE(SUM(b.purged_at IS NULL AND b.retention_until IS NULL),0) AS indefinite,
      COALESCE(SUM(b.purged_at IS NULL AND b.legal_hold=0 AND b.retention_until<?
        AND DATE_ADD(DATE(b.created_at),INTERVAL 365 DAY)<?
        AND d.status='cerrada'),0) AS eligible,
      COALESCE(SUM(b.purged_at IS NULL AND b.retention_until>=?
        AND b.retention_until<=DATE_ADD(?,INTERVAL 30 DAY)),0) AS upcoming
      FROM attachments b
      LEFT JOIN tests t ON t.id=b.test_id
      LEFT JOIN actions a ON a.id=b.action_id
      LEFT JOIN findings f ON f.id=a.finding_id
      LEFT JOIN tests at ON at.id=f.test_id
      JOIN audits d ON d.id=COALESCE(t.audit_id,at.audit_id)",
      array_fill(0,4,$today->format('Y-m-d')))->fetch();
}

/** Regla idéntica para solicitud, decisión y ejecución; nunca confía en la UI. */
function retentionEligible(array $file,DateTimeImmutable $today): bool {
    if (($file['purged_at'] ?? null)!==null || $file['audit_status']!=='cerrada' ||
        (int)$file['legal_hold']!==0 || $file['retention_until']===null) return false;
    $date=$today->format('Y-m-d');
    $minimum=(new DateTimeImmutable($file['created_at']))->modify('+365 days')->format('Y-m-d');
    return $file['retention_until']<$date && $minimum<$date;
}
