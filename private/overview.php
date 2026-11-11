<?php
declare(strict_types=1);

function planFilters(): array {
    $year = filter_input(INPUT_GET,'year',FILTER_VALIDATE_INT,['options'=>['min_range'=>2000,'max_range'=>2100]]);
    $state = $_GET['status'] ?? '';
    $risk = $_GET['risk'] ?? '';
    $search = $_GET['q'] ?? '';
    return [
        'year'=>$year ?: '',
        'status'=>in_array($state,STATES,true) ? $state : '',
        'risk'=>in_array($risk,LEVELS,true) ? $risk : '',
        'q'=>is_string($search) ? trim(substr($search,0,180)) : '',
    ];
}

function planScope(array $user, array $filters): array {
    $where = [];
    $params = [];
    if (restrictedSupervisor($user)) {
        $where[] = 'EXISTS (SELECT 1 FROM audit_assessment_links l JOIN risk_assessments r ON r.id=l.assessment_id JOIN supervisor_unit_grants g ON g.unit_id=r.unit_id WHERE l.audit_id=d.id AND g.user_id=?)';
        $params[]=$user['id'];
    } elseif (!manage($user)) {
        $where[] = 'd.owner_id=?';
        $params[] = $user['id'];
    }
    foreach (['year'=>'year','status'=>'status','risk'=>'risk'] as $key=>$column) {
        if ($filters[$key] !== '') {
            $where[] = "d.$column=?";
            $params[] = $filters[$key];
        }
    }
    if ($filters['q'] !== '') {
        $where[] = '(LOCATE(?,d.title)>0 OR LOCATE(?,d.entity)>0)';
        array_push($params,$filters['q'],$filters['q']);
    }
    return [$where ? ' WHERE ' . implode(' AND ',$where) : '',$params];
}

function planRows(PDO $pdo, array $user, array $filters, bool $withHours = false): array {
    [$where,$params] = planScope($user,$filters);
    $columns = 'd.id,d.title,d.entity,d.year,d.risk,d.status';
    if ($withHours) {
        $columns .= ', (SELECT planned_minutes FROM audit_schedule WHERE audit_id=d.id) AS planned,
            (SELECT SUM(minutes) FROM audit_time WHERE audit_id=d.id) AS worked';
    }
    return query($pdo,"SELECT $columns FROM audits d$where ORDER BY d.year DESC,d.id DESC",$params)->fetchAll();
}

function planSeverity(PDO $pdo, array $user, array $filters): array {
    [$where,$params] = planScope($user,$filters);
    return query($pdo,"SELECT f.severity,COUNT(*) FROM findings f JOIN tests t ON t.id=f.test_id
        JOIN audits d ON d.id=t.audit_id$where GROUP BY f.severity",$params)->fetchAll(PDO::FETCH_KEY_PAIR);
}

function planAging(PDO $pdo, array $user, array $filters, DateTimeImmutable $today): array {
    [$where,$params] = planScope($user,$filters);
    $where .= ($where ? ' AND ' : ' WHERE ') . "d.status='emitida' AND a.status<>'cerrada'";
    $cutoff = fn (int $days): string => $today->modify("-$days days")->format('Y-m-d');
    return query($pdo,"SELECT CASE WHEN a.due_date>=? THEN 'en_plazo' WHEN a.due_date>=? THEN 'hasta_30'
        WHEN a.due_date>=? THEN 'hasta_60' ELSE 'mas_60' END AS bucket,COUNT(*)
        FROM actions a JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id
        JOIN audits d ON d.id=t.audit_id$where GROUP BY bucket",
        [$cutoff(0),$cutoff(30),$cutoff(60),...$params])->fetchAll(PDO::FETCH_KEY_PAIR);
}

function planCsv(array $rows): never {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="auditpro-plan.csv"');
    $stream = fopen('php://output','wb');
    fwrite($stream,"\xEF\xBB\xBF");
    fputcsv($stream,['Auditoría','Entidad','Año','Riesgo','Estado'],',','"','');
    foreach ($rows as $row) {
        $cells = [];
        foreach (['title','entity','year','risk','status'] as $key) {
            $value = (string) $row[$key];
            $cells[] = preg_match('/^(?:[\x00-\x20]*[=+@-]|[\t\r\n])/', $value) ? "'" . $value : $value;
        }
        fputcsv($stream,$cells,',','"','');
    }
    fclose($stream);
    exit;
}
