<?php
declare(strict_types=1);

/** Las atestaciones son eventos inmutables, no estados sobrescritos. */
function saveAttestation(PDO $pdo, array $user): void {
    if (!canCompliance($user)) throw new DomainException('La atestación requiere rol de cumplimiento o supervisión.');
    $id=positiveId('obligation_id');
    if (!query($pdo,'SELECT 1 FROM grc_obligations WHERE id=?',[$id])->fetchColumn()) {
        throw new DomainException('Obligación no disponible.');
    }
    $result=choice('result',['cumple','parcial','no_cumple','no_aplica']);
    $evidence=field('evidence',12000);
    $rationale=field('rationale',12000);
    $reviewed=dateField('reviewed');
    $next=dateField('next_review');
    if ($reviewed>date('Y-m-d') || $next<=$reviewed) {
        throw new DomainException('La revisión no puede ser futura y el próximo vencimiento debe ser posterior.');
    }
    query($pdo,'INSERT INTO obligation_attestations
      (obligation_id,author_id,result,evidence_reference,rationale,reviewed_on,next_review_on)
      VALUES (?,?,?,?,?,?,?)',[$id,(int)$user['id'],$result,$evidence,$rationale,$reviewed,$next]);
    $attestationId=(int)$pdo->lastInsertId();
    record($pdo,(int)$user['id'],null,'obligacion_atestada',"Obligación #$id · atestación #$attestationId · $result");
}
function obligationAttestations(PDO $pdo): array {
    $grouped=[];
    foreach (query($pdo,'SELECT a.*,u.name AS author_name FROM obligation_attestations a
       JOIN users u ON u.id=a.author_id ORDER BY a.id DESC') as $item) {
        $grouped[$item['obligation_id']][]=$item;
    }
    return $grouped;
}
