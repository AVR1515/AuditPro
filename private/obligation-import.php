<?php
declare(strict_types=1);

const OBLIGATION_COLUMNS = ['marco','referencia','titulo','responsable','revision'];

function parseObligations(): array {
    $file = $_FILES['obligations'] ?? [];
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > 1024*1024) {
        throw new DomainException('Selecciona un CSV de hasta 1 MiB.');
    }
    $stream = fopen($file['tmp_name'],'rb');
    if (!$stream) throw new DomainException('No se pudo leer el CSV.');
    try {
        $header = fgetcsv($stream,0,',','"','');
        if ($header) $header[0] = preg_replace('/^\xEF\xBB\xBF/','',$header[0] ?? '');
        if ($header !== OBLIGATION_COLUMNS) throw new DomainException('El encabezado debe ser marco,referencia,titulo,responsable,revision.');
        $rows = [];
        $line = 1;
        while (($row = fgetcsv($stream,0,',','"','')) !== false) {
            $line++;
            if ($row === [null]) continue;
            $row = array_map(fn ($value): string => trim((string) $value),$row);
            if (count($row) !== 5 || count($rows) >= 500) throw new DomainException("Fila $line inválida o se superó el máximo de 500 registros.");
            foreach ([180,120,180,180,10] as $i=>$limit) {
                if ($row[$i] === '' || strlen($row[$i]) > $limit || !preg_match('//u',$row[$i]) || str_contains($row[$i],"\0")) {
                    throw new DomainException("Revisa los campos de la fila $line.");
                }
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d',$row[4]);
            if (!$date || $date->format('Y-m-d') !== $row[4] || $row[4] < '1000-01-01') {
                throw new DomainException("Usa AAAA-MM-DD en la fila $line.");
            }
            $rows[] = $row;
        }
        if (!$rows) throw new DomainException('El archivo no contiene obligaciones.');
        return $rows;
    } finally {
        fclose($stream);
    }
}

function importObligations(PDO $pdo, array $user, string $op): string {
    if (!canCompliance($user)) throw new DomainException('Tu rol no puede importar obligaciones.');
    if ($op === 'preview_obligations') {
        unset($_SESSION['obligation_import']);
        $_SESSION['obligation_import'] = ['token'=>bin2hex(random_bytes(16)), 'rows'=>parseObligations()];
        return 'index.php?view=import';
    }
    $pending = $_SESSION['obligation_import'] ?? null;
    if (!$pending || !hash_equals($pending['token'],field('import_token',32))) {
        throw new DomainException('La vista previa venció. Vuelve a seleccionar el archivo.');
    }
    if ($op === 'confirm_obligations') {
        $statement = $pdo->prepare('INSERT INTO grc_obligations(framework,reference_code,title,owner_name,review_on) VALUES (?,?,?,?,?)');
        foreach ($pending['rows'] as $index=>$row) {
            try {
                $statement->execute($row);
            } catch (PDOException $exception) {
                if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                    throw new DomainException('La fila ' . ($index+1) . ' repite una referencia del mismo marco. Corrige el archivo y vuelve a cargarlo.');
                }
                throw $exception;
            }
        }
        record($pdo,(int) $user['id'],null,'obligaciones_importadas',(string) count($pending['rows']));
    }
    return 'index.php?view=grc';
}
