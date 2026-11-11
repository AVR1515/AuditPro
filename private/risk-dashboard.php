<?php
declare(strict_types=1);

/** Indicadores agregados del universo auditable. Solo supervisión. */
function riskDashboard(PDO $pdo, int $year): array {
    $coverage=unitCoverage($pdo);
    $total=count($coverage);
    $missing=0;$gaps=0;$high=0;$highGaps=0;
    foreach ($coverage as $unit) {
        if ($unit['rating']===null) $missing++;
        if (in_array($unit['rating'],['alto','critico'],true)) $high++;
        if (coverageNeedsReview($unit,$year)) {
            $gaps++;
            if (in_array($unit['rating'],['alto','critico'],true)) $highGaps++;
        }
    }
    $riskRows=query($pdo,'SELECT
        COUNT(*) AS total,
        COALESCE(SUM(residual>appetite),0) AS above_appetite,
        COALESCE(SUM(review_on<CURRENT_DATE()),0) AS overdue_reviews
        FROM grc_risks')->fetch();
    $kriRows=breachedKriInbox($pdo);
    $kriStale=staleKriInbox($pdo,new DateTimeImmutable('today'));
    $evidenceQueue=query($pdo,"SELECT
        COALESCE(SUM(r.status='abierta' AND r.due_on<CURRENT_DATE()),0) AS overdue,
        COALESCE(SUM(r.status='respondida'),0) AS pending_review
        FROM evidence_requests r JOIN audits d ON d.id=r.audit_id
        WHERE d.status IN ('en_campo','en_revision')")->fetch();
    $complianceDue=(int)query($pdo,"SELECT COUNT(*) FROM grc_obligations o
        WHERE COALESCE((SELECT a.next_review_on FROM obligation_attestations a
            WHERE a.obligation_id=o.id ORDER BY a.id DESC LIMIT 1),o.review_on)<CURRENT_DATE()")->fetchColumn();
    $riskAcceptanceDue=(int)query($pdo,"SELECT COUNT(*) FROM action_risk_acceptances
        WHERE status='aceptada' AND review_on<=CURRENT_DATE()")->fetchColumn();
    $exceptionsDue=(int)query($pdo,"SELECT COUNT(*) FROM obligation_exceptions
        WHERE status='aprobada' AND expires_on<=CURRENT_DATE()")->fetchColumn();
    return [
        'units'=>$total,'unrated'=>$missing,'gaps'=>$gaps,
        'high'=>$high,'high_gaps'=>$highGaps,
        'risks'=>(int)$riskRows['total'],
        'above_appetite'=>(int)$riskRows['above_appetite'],
        'overdue_reviews'=>(int)$riskRows['overdue_reviews'],
        'kri_breaches'=>count($kriRows),
        'kri_stale'=>count($kriStale),
        'overdue_evidence_requests'=>(int)$evidenceQueue['overdue'],
        'evidence_answers_waiting'=>(int)$evidenceQueue['pending_review'],
        'overdue_compliance_reviews'=>$complianceDue,
        'accepted_risks_due'=>$riskAcceptanceDue,
        'exceptions_due'=>$exceptionsDue,
        'coverage'=>$coverage,
    ];
}
