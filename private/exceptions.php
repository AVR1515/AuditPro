<?php
declare(strict_types=1);

const EXCEPTION_OPS = ['exception_request','exception_decide','exception_close'];

function complianceExceptions(PDO $pdo): array {
    $result=[];
    foreach (query($pdo,'SELECT e.*,requester.name AS requester_name,decider.name AS decider_name,
         closer.name AS closer_name FROM obligation_exceptions e
         JOIN users requester ON requester.id=e.requested_by
         LEFT JOIN users decider ON decider.id=e.decided_by
         LEFT JOIN users closer ON closer.id=e.closed_by
         ORDER BY e.id DESC') as $row) $result[$row['obligation_id']][]=$row;
    return $result;
}

function saveComplianceException(PDO $pdo, array $user, string $op): void {
    if (!canCompliance($user)) throw new DomainException('Solo Cumplimiento o Supervisión gestiona excepciones.');
    $actor=(int)$user['id'];
    $obligationId=positiveId('obligation_id');
    if (!query($pdo,'SELECT id FROM grc_obligations WHERE id=?',[$obligationId])->fetchColumn())
        throw new DomainException('Obligación no disponible.');
    if ($op==='exception_request') {
        $title=field('title',180);
        $rationale=field('rationale',12000);
        $mitigation=field('mitigating',12000);
        $expires=dateField('expires');
        if ($expires<=date('Y-m-d')) throw new DomainException('La fecha de expiración debe ser futura.');
        query($pdo,'INSERT INTO obligation_exceptions
           (obligation_id,requested_by,title,rationale,mitigating_controls,expires_on)
           VALUES (?,?,?,?,?,?)',[$obligationId,$actor,$title,$rationale,$mitigation,$expires]);
        $id=(int)$pdo->lastInsertId();
        record($pdo,$actor,null,'excepcion_solicitada',"Obligación #$obligationId · excepción #$id");
        return;
    }
    $exceptionId=positiveId('exception_id');
    $exception=query($pdo,'SELECT * FROM obligation_exceptions WHERE id=? AND obligation_id=? FOR UPDATE',
        [$exceptionId,$obligationId])->fetch();
    if (!$exception) throw new DomainException('Excepción no disponible para esta obligación.');
    if ($op==='exception_decide') {
        if (!manage($user)) throw new DomainException('Solo Supervisión puede aprobar o rechazar.');
        if ($exception['status']!=='pendiente') throw new DomainException('La excepción ya fue decidida.');
        if ((int)$exception['requested_by']===$actor)
            throw new DomainException('Una cuenta distinta debe evaluar esta excepción.');
        if ($exception['expires_on']<=date('Y-m-d'))
            throw new DomainException('No se puede aprobar una solicitud vencida.');
        $decision=choice('decision',['aprobada','rechazada']);
        $note=field('decision_note',12000);
        query($pdo,'UPDATE obligation_exceptions SET status=?,decided_by=?,decided_at=CURRENT_TIMESTAMP,
            decision_note=? WHERE id=?',[$decision,$actor,$note,$exceptionId]);
        record($pdo,$actor,null,'excepcion_'.$decision,"Obligación #$obligationId · excepción #$exceptionId");
        return;
    }
    if ($op==='exception_close') {
        if (!manage($user) || $exception['status']!=='aprobada')
            throw new DomainException('Solo Supervisión puede cerrar una excepción aprobada.');
        $note=field('closure_note',12000);
        query($pdo,"UPDATE obligation_exceptions SET status='cerrada',closed_by=?,closed_at=CURRENT_TIMESTAMP,
          closure_note=? WHERE id=?",[$actor,$note,$exceptionId]);
        record($pdo,$actor,null,'excepcion_cerrada',"Obligación #$obligationId · excepción #$exceptionId");
        return;
    }
    throw new DomainException('Operación de excepción no autorizada.');
}
