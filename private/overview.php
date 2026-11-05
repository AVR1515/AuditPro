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

function planRows(PDO $pdo, array $user, array $filters): array {
    $where = [];
    $params = [];
    if (!manage($user)) {
        $where[] = 'owner_id=?';
        $params[] = $user['id'];
    }
    foreach (['year'=>'year','status'=>'status','risk'=>'risk'] as $key=>$column) {
        if ($filters[$key] !== '') {
            $where[] = "$column=?";
            $params[] = $filters[$key];
        }
    }
    if ($filters['q'] !== '') {
        $where[] = '(LOCATE(?,title)>0 OR LOCATE(?,entity)>0)';
        array_push($params,$filters['q'],$filters['q']);
    }
    $sql = 'SELECT id,title,entity,year,risk,status FROM audits' .
        ($where ? ' WHERE ' . implode(' AND ',$where) : '') . ' ORDER BY year DESC,id DESC';
    return query($pdo,$sql,$params)->fetchAll();
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
