<?php
declare(strict_types=1);

/**
 * Solicitudes de evidencia internas al expediente. Todas las mutaciones se
 * ejecutan dentro de la transacción y con CSRF del controlador principal.
 */
const REQUEST_OPS = ['request_create','request_answer','request_close','request_reopen'];

function saveEvidenceRequest(PDO $pdo, array $user, string $op): int {
    $auditId = positiveId('audit_id');
    $audit = accessible($pdo,$user,$auditId,true);
    $actor = (int) $user['id'];
    $manager = manage($user);
    $auditor = $user['role'] === 'auditor' && (int) $audit['owner_id'] === $actor;
    $active = in_array($audit['status'],['en_campo','en_revision'],true);

    if ($op === 'request_create') {
        if (!$manager || !$active) throw new DomainException('Solo supervisión puede solicitar evidencia durante el trabajo o la revisión.');
        $due = dateField('due');
        if ($due < date('Y-m-d')) throw new DomainException('La fecha límite no puede ser anterior a hoy.');
        $subject = field('subject',180);
        $description = field('description',12000);
        query($pdo,'INSERT INTO evidence_requests(audit_id,requested_by,subject,description,due_on) VALUES (?,?,?,?,?)',
            [$auditId,$actor,$subject,$description,$due]);
        record($pdo,$actor,$auditId,'solicitud_creada','Solicitud #' . $pdo->lastInsertId() . ': ' . $subject);
        return $auditId;
    }

    $requestId = positiveId('request_id');
    $request = query($pdo,'SELECT * FROM evidence_requests WHERE id=? AND audit_id=? FOR UPDATE',[$requestId,$auditId])->fetch();
    if (!$request) throw new DomainException('Solicitud no disponible para este expediente.');

    if ($op === 'request_answer') {
        if (!$auditor || !$active || $request['status'] !== 'abierta') throw new DomainException('No se puede responder esta solicitud.');
        $answer = field('answer',12000);
        $fileId=null;
        if (isset($_POST['attachment_id']) && $_POST['attachment_id']!=='') {
            $fileId=positiveId('attachment_id');
            $file=query($pdo,'SELECT b.id FROM attachments b
                JOIN tests t ON t.id=b.test_id
                WHERE b.id=? AND t.audit_id=? AND b.purged_at IS NULL',
                [$fileId,$auditId])->fetch();
            if (!$file) throw new DomainException('Selecciona una evidencia de prueba disponible en este expediente.');
        }
        query($pdo,'INSERT INTO evidence_request_responses(request_id,author_id,response_text) VALUES (?,?,?)',[$requestId,$actor,$answer]);
        $responseId=(int)$pdo->lastInsertId();
        if ($fileId!==null) {
            query($pdo,'INSERT INTO evidence_request_attachments(response_id,attachment_id) VALUES (?,?)',
                [$responseId,$fileId]);
        }
        query($pdo,"UPDATE evidence_requests SET status='respondida' WHERE id=?",[$requestId]);
        record($pdo,$actor,$auditId,'solicitud_respondida','Solicitud #' . $requestId);
    } elseif ($op === 'request_close') {
        if (!$manager || $request['status'] !== 'respondida') throw new DomainException('Solo supervisión puede cerrar solicitudes respondidas.');
        query($pdo,"UPDATE evidence_requests SET status='cerrada',closed_by=?,closed_at=CURRENT_TIMESTAMP WHERE id=?",[$actor,$requestId]);
        record($pdo,$actor,$auditId,'solicitud_cerrada','Solicitud #' . $requestId);
    } elseif ($op === 'request_reopen') {
        if (!$manager || !$active || $request['status'] !== 'respondida') throw new DomainException('Solo se pueden devolver respuestas pendientes de aceptación.');
        $reason = field('reason',500);
        query($pdo,"UPDATE evidence_requests SET status='abierta',closed_by=NULL,closed_at=NULL WHERE id=?",[$requestId]);
        query($pdo,'INSERT INTO evidence_request_responses(request_id,author_id,response_text,is_return) VALUES (?,?,?,TRUE)',
            [$requestId,$actor,$reason]);
        record($pdo,$actor,$auditId,'solicitud_devuelta','Solicitud #' . $requestId . ': ' . substr($reason,0,180));
    } else {
        throw new DomainException('Operación de solicitud no autorizada.');
    }
    return $auditId;
}
