<?php
declare(strict_types=1);

/**
 * La retención es opt-in. Sin fecha no existe eliminación automática.
 * El purgado borra contenido pero mantiene la huella y los metadatos
 * para auditoría; requiere expiración, expediente cerrado y confirmación.
 */
function retentionRows(PDO $pdo): array {
    return $pdo->query("SELECT b.id,b.filename,b.bytes,b.sha256,b.created_at,b.retention_until,b.legal_hold,
        b.purged_at,COALESCE(t.audit_id,at.audit_id) AS audit_id,d.title AS audit_title,d.status AS audit_status
        FROM attachments b LEFT JOIN tests t ON t.id=b.test_id
        LEFT JOIN actions a ON a.id=b.action_id
        LEFT JOIN findings f ON f.id=a.finding_id
        LEFT JOIN tests at ON at.id=f.test_id
        JOIN audits d ON d.id=COALESCE(t.audit_id,at.audit_id)
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
        record($pdo,$actor,(int)$item['audit_id'],'retencion_actualizada',"Archivo #$id · hasta $date · bloqueo $hold");
        return;
    }
    if ($op==='retention_purge') {
        if (field('confirmation',20)!=='PURGAR') throw new DomainException('Escribe PURGAR para confirmar la eliminación.');
        $minimum=(new DateTimeImmutable($item['created_at']))->modify('+365 days')->format('Y-m-d');
        if ($item['audit_status']!=='cerrada' || (int)$item['legal_hold']!==0 ||
            $item['retention_until']===null || $item['retention_until']>=date('Y-m-d') || $minimum>=date('Y-m-d')) {
            throw new DomainException('Solo pueden purgarse evidencias de expedientes cerrados cuyo plazo haya vencido, sin bloqueo legal.');
        }
        query($pdo,'UPDATE attachments SET content=NULL,purged_at=CURRENT_TIMESTAMP,purged_by=? WHERE id=?',[$actor,$id]);
        record($pdo,$actor,(int)$item['audit_id'],'evidencia_purgada',"Archivo #$id · SHA-256 ".$item['sha256']);
        return;
    }
    throw new DomainException('Operación de retención no autorizada.');
}
