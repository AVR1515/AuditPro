<?php
declare(strict_types=1);
const RISK_ACCEPTANCE_OPS = ['risk_accept_request','risk_accept_decide','risk_accept_refresh'];

function riskAcceptanceHistory(PDO $pdo, int $auditId): array {
    return query($pdo,'SELECT r.*,a.description AS action_description,requester.name AS requester_name,decider.name AS decider_name
        FROM action_risk_acceptances r JOIN actions a ON a.id=r.action_id
        JOIN users requester ON requester.id=r.requested_by
        LEFT JOIN users decider ON decider.id=r.decided_by
        JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id
        WHERE t.audit_id=? ORDER BY r.id DESC',[$auditId])->fetchAll();
}

function saveRiskAcceptance(PDO $pdo, array $user, string $op): int {
    if (!manage($user)) throw new DomainException('Solo supervisión puede gestionar aceptaciones de riesgo.');
    $auditId=positiveId('audit_id');
    $audit=accessible($pdo,$user,$auditId,true);
    if (!in_array($audit['status'],$op==='risk_accept_refresh'?['emitida','cerrada']:['emitida'],true))
        throw new DomainException('Se requiere un informe emitido o cerrado para revisar la aceptación.');
    $actor=(int)$user['id'];
    if ($op==='risk_accept_refresh') {
        $acceptId=positiveId('acceptance_id');
        $accept=query($pdo,'SELECT r.* FROM action_risk_acceptances r
            JOIN actions a ON a.id=r.action_id
            JOIN findings f ON f.id=a.finding_id
            JOIN tests t ON t.id=f.test_id
            WHERE r.id=? AND t.audit_id=? FOR UPDATE',[$acceptId,$auditId])->fetch();
        if (!$accept || $accept['status']!=='aceptada') {
            throw new DomainException('No existe una aceptación vigente de riesgo para revisar.');
        }
        if ((int)$accept['requested_by']===$actor)
            throw new DomainException('La revisión requiere una cuenta distinta del solicitante.');
        $next=dateField('next_review');
        if ($next<=date('Y-m-d')) throw new DomainException('La próxima revisión debe ser futura.');
        $conclusion=field('conclusion',12000);
        query($pdo,'INSERT INTO action_risk_reviews
            (acceptance_id,reviewed_by,previous_review_on,next_review_on,conclusion)
            VALUES (?,?,?,?,?)',
            [$acceptId,$actor,$accept['review_on'],$next,$conclusion]);
        query($pdo,'UPDATE action_risk_acceptances SET review_on=? WHERE id=?',[$next,$acceptId]);
        record($pdo,$actor,$auditId,'riesgo_revalidado',"Aceptación #$acceptId · próxima revisión $next");
        return $auditId;
    }

    if ($op==='risk_accept_request') {
        $actionId=positiveId('action_id');
        $action=query($pdo,'SELECT a.id,a.status FROM actions a
            JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id
            WHERE a.id=? AND t.audit_id=? FOR UPDATE',[$actionId,$auditId])->fetch();
        if (!$action || $action['status']!=='abierta') throw new DomainException('La acción debe estar abierta.');
        if (query($pdo,"SELECT 1 FROM action_risk_acceptances WHERE action_id=? AND status IN ('pendiente','aceptada') LIMIT 1",[$actionId])->fetchColumn()) {
            throw new DomainException('La acción ya tiene una aceptación vigente o pendiente.');
        }
        $rationale=field('rationale',12000);
        $controls=field('compensating',12000);
        $review=dateField('expires');
        if ($review<=date('Y-m-d')) throw new DomainException('La fecha de revisión debe ser futura.');
        query($pdo,'INSERT INTO action_risk_acceptances(action_id,requested_by,rationale,compensating_controls,review_on)
            VALUES (?,?,?,?,?)',[$actionId,$actor,$rationale,$controls,$review]);
        $requestId=(int)$pdo->lastInsertId();
        record($pdo,$actor,$auditId,'riesgo_solicitado',"Solicitud #$requestId · acción #$actionId");
        return $auditId;
    }
    if ($op==='risk_accept_decide') {
        $requestId=positiveId('acceptance_id');
        $request=query($pdo,'SELECT r.*,a.status AS action_status FROM action_risk_acceptances r
            JOIN actions a ON a.id=r.action_id JOIN findings f ON f.id=a.finding_id
            JOIN tests t ON t.id=f.test_id
            WHERE r.id=? AND t.audit_id=? FOR UPDATE',[$requestId,$auditId])->fetch();
        if (!$request || $request['status']!=='pendiente' || $request['action_status']!=='abierta')
            throw new DomainException('Solicitud no disponible o acción cerrada.');
        if ((int)$request['requested_by']===$actor)
            throw new DomainException('Otra cuenta de supervisión debe aprobar la solicitud.');
        $decision=choice('decision',['aceptada','rechazada']);
        $reason=field('decision_note',12000);
        query($pdo,'UPDATE action_risk_acceptances SET status=?,decided_by=?,decided_at=CURRENT_TIMESTAMP,decision_note=? WHERE id=?',
            [$decision,$actor,$reason,$requestId]);
        if ($decision==='aceptada') {
            // El cierre operativo no equivale a una verificación de remediación.
            query($pdo,"UPDATE actions SET status='cerrada' WHERE id=? AND status='abierta'",[$request['action_id']]);
        }
        record($pdo,$actor,$auditId,'riesgo_'.$decision,"Solicitud #$requestId · acción #".$request['action_id']);
        return $auditId;
    }
    throw new DomainException('Operación no autorizada.');
}

/** Historial de revalidaciones de aceptación; cada decisión previa permanece intacta. */
function riskAcceptanceReviews(PDO $pdo,int $auditId): array {
    $rows=[];
    foreach (query($pdo,'SELECT v.*,u.name AS reviewer_name FROM action_risk_reviews v
        JOIN users u ON u.id=v.reviewed_by
        JOIN action_risk_acceptances r ON r.id=v.acceptance_id
        JOIN actions a ON a.id=r.action_id
        JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id
        WHERE t.audit_id=? ORDER BY v.id DESC',[$auditId]) as $row) {
        $rows[$row['acceptance_id']][]=$row;
    }
    return $rows;
}
