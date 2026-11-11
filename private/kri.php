<?php
declare(strict_types=1);

const KRI_OPS = ['risk_review','kri_create','kri_measure','kri_archive'];

function kriDecimal(string $key): string {
    $value = field($key,24);
    if (!preg_match('/^(?:0|[1-9][0-9]{0,10})(?:\.[0-9]{1,3})?$/D',$value)) {
        throw new DomainException('El valor debe ser positivo o cero, con hasta tres decimales.');
    }
    return $value;
}
function kriBreached(array $row): bool {
    if ($row['observed_value'] === null) return false;
    $value=(float)$row['observed_value'];
    $threshold=(float)$row['threshold'];
    return $row['direction'] === 'maximo' ? $value > $threshold : $value < $threshold;
}
function riskReviewHistory(PDO $pdo): array {
    $rows=[];
    foreach (query($pdo,'SELECT r.*,u.name AS reviewer_name FROM grc_risk_reviews r
        JOIN users u ON u.id=r.reviewed_by ORDER BY r.id DESC') as $row)
        $rows[$row['risk_id']][]=$row;
    return $rows;
}
function kriIndicators(PDO $pdo): array {
    $rows=[];
    foreach (query($pdo,'SELECT k.*,o.observed_value,o.observed_on,o.evidence_reference
        FROM grc_kri_indicators k
        LEFT JOIN grc_kri_observations o ON o.id=(
            SELECT MAX(h.id) FROM grc_kri_observations h WHERE h.indicator_id=k.id
        ) WHERE k.active=1 ORDER BY k.risk_id,k.id') as $row)
        $rows[$row['risk_id']][]=$row;
    return $rows;
}
function kriObservations(PDO $pdo, int $indicatorId): array {
    return query($pdo,'SELECT o.*,u.name AS recorder_name FROM grc_kri_observations o
        JOIN users u ON u.id=o.recorded_by
        WHERE o.indicator_id=? ORDER BY o.id DESC',[$indicatorId])->fetchAll();
}

function saveKri(PDO $pdo, array $user, string $op): void {
    if (!canRisk($user)) throw new DomainException('Solo Riesgos o Supervisión pueden gestionar revisiones e indicadores.');
    $actor=(int)$user['id'];
    $riskId=positiveId('risk_id');
    $risk=query($pdo,'SELECT * FROM grc_risks WHERE id=? FOR UPDATE',[$riskId])->fetch();
    if (!$risk) throw new DomainException('Riesgo no disponible.');

    if ($op==='risk_review') {
        $residual=positiveId('residual');
        if ($residual>25) throw new DomainException('El riesgo residual debe estar entre 1 y 25.');
        $reviewed=dateField('reviewed_on');
        $next=dateField('next_review_on');
        if ($reviewed>date('Y-m-d') || $next<=$reviewed || $next<=date('Y-m-d'))
            throw new DomainException('La revisión no puede ser futura y la siguiente debe ser posterior a hoy.');
        $note=field('note',12000);
        query($pdo,'INSERT INTO grc_risk_reviews
            (risk_id,reviewed_by,previous_residual,residual,review_note,reviewed_on,next_review_on)
            VALUES (?,?,?,?,?,?,?)',
            [$riskId,$actor,$risk['residual'],$residual,$note,$reviewed,$next]);
        query($pdo,'UPDATE grc_risks SET residual=?,review_on=? WHERE id=?',[$residual,$next,$riskId]);
        record($pdo,$actor,null,'riesgo_revisado',"Riesgo #$riskId · $residual/25 · próxima $next");
        return;
    }
    if ($op==='kri_create') {
        $title=field('title',180);
        $unit=field('unit',80);
        $direction=choice('direction',['maximo','minimo']);
        $threshold=kriDecimal('threshold');
        query($pdo,'INSERT INTO grc_kri_indicators
            (risk_id,title,unit,direction,threshold,created_by)
            VALUES (?,?,?,?,?,?)',[$riskId,$title,$unit,$direction,$threshold,$actor]);
        record($pdo,$actor,null,'kri_creado','Riesgo #'.$riskId.' · indicador #'.$pdo->lastInsertId());
        return;
    }
    $indicatorId=positiveId('indicator_id');
    $indicator=query($pdo,'SELECT * FROM grc_kri_indicators WHERE id=? AND risk_id=? AND active=1 FOR UPDATE',
        [$indicatorId,$riskId])->fetch();
    if (!$indicator) throw new DomainException('Indicador no disponible o archivado.');
    if ($op==='kri_measure') {
        $observed=dateField('observed_on');
        if ($observed>date('Y-m-d')) throw new DomainException('No pueden registrarse mediciones futuras.');
        $value=kriDecimal('value');
        $evidence=field('evidence',12000);
        query($pdo,'INSERT INTO grc_kri_observations
            (indicator_id,recorded_by,observed_on,observed_value,evidence_reference)
            VALUES (?,?,?,?,?)',[$indicatorId,$actor,$observed,$value,$evidence]);
        record($pdo,$actor,null,'kri_medido','Indicador #'.$indicatorId.' · '.$value);
        return;
    }
    if ($op==='kri_archive') {
        if (!manage($user)) throw new DomainException('Solo Supervisión puede archivar indicadores.');
        query($pdo,'UPDATE grc_kri_indicators SET active=0 WHERE id=?',[$indicatorId]);
        record($pdo,$actor,null,'kri_archivado','Indicador #'.$indicatorId);
        return;
    }
    throw new DomainException('Operación no autorizada.');
}
