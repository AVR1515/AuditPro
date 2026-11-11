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
        if (coverageStatus($unit,$year)!=='Dentro del ciclo aproximado') {
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
    return [
        'units'=>$total,'unrated'=>$missing,'gaps'=>$gaps,
        'high'=>$high,'high_gaps'=>$highGaps,
        'risks'=>(int)$riskRows['total'],
        'above_appetite'=>(int)$riskRows['above_appetite'],
        'overdue_reviews'=>(int)$riskRows['overdue_reviews'],
        'kri_breaches'=>count($kriRows),
        'coverage'=>$coverage,
    ];
}
