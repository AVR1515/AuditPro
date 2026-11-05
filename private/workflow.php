<?php
declare(strict_types=1);

function record(PDO $pdo, int $actor, ?int $audit, string $event, string $detail = ''): void {
    query($pdo, 'INSERT INTO audit_log(actor_id,audit_id,event,detail) VALUES (?,?,?,?)', [$actor,$audit,$event,$detail]);
}
function positiveId(string $key): int {
    $id = filter_var(field($key), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!$id) throw new DomainException("Identificador inválido: $key.");
    return $id;
}
function manage(array $user): bool {
    return in_array($user['role'], ['admin','supervisor'], true);
}
function accessible(PDO $pdo, array $user, int $id, bool $lock = false): array {
    $audit = query($pdo, 'SELECT * FROM audits WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), [$id])->fetch();
    if (!$audit) throw new DomainException('Expediente no disponible.');
    if (manage($user) || ($user['role'] === 'auditor' && (int) $audit['owner_id'] === (int) $user['id'])) return $audit;
    if ($user['role'] === 'responsable' && in_array($audit['status'],['emitida','cerrada'],true) && query($pdo, 'SELECT 1 FROM actions a JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id WHERE t.audit_id=? AND a.owner_id=? LIMIT 1', [$id,$user['id']])->fetchColumn()) return $audit;
    throw new DomainException('Expediente no disponible.');
}
function nextState(PDO $pdo, array $audit): ?string {
    $id = $audit['id'];
    return match ($audit['status']) {
        'borrador' => 'aprobada',
        'aprobada' => 'en_campo',
        'en_campo' => query($pdo, "SELECT COUNT(*)=SUM(status='revisada') FROM tests WHERE audit_id=?", [$id])->fetchColumn() ? 'en_revision' : null,
        'en_revision' => query($pdo, 'SELECT 1 FROM findings f JOIN tests t ON t.id=f.test_id WHERE t.audit_id=? AND NOT EXISTS (SELECT 1 FROM actions a WHERE a.finding_id=f.id) LIMIT 1', [$id])->fetchColumn() ? null : 'emitida',
        'emitida' => query($pdo, "SELECT 1 FROM actions a JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id WHERE t.audit_id=? AND a.status<>'cerrada' LIMIT 1", [$id])->fetchColumn() ? null : 'cerrada',
        default => null
    };
}
function mutate(PDO $pdo, array $user, string $op): ?int {
    $actor = (int) $user['id'];
    if ($op === 'user' && $user['role'] === 'admin') {
        $email = strtolower(field('email',254));
        $password = $_POST['password'] ?? null;
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || !is_string($password) || strlen($password)<12 || strlen($password)>72 || str_contains($password,"\0")) throw new DomainException('Revisa el correo y usa una contraseña de 12 a 72 bytes.');
        if (query($pdo, 'SELECT 1 FROM users WHERE email=?', [$email])->fetchColumn()) throw new DomainException('Este correo ya está registrado.');
        query($pdo,'INSERT INTO users(email,name,password_hash,role) VALUES (?,?,?,?)',[$email,field('name',160),password_hash($password,PASSWORD_DEFAULT),choice('role',ROLES)]);
        record($pdo,$actor,null,'usuario_creado',$pdo->lastInsertId());
        return null;
    }
    if ($op === 'audit' && manage($user)) {
        $year = positiveId('year');
        if ($year < 2000 || $year > 2100) throw new DomainException('El año debe estar entre 2000 y 2100.');
        $owner = positiveId('owner');
        if (!query($pdo,"SELECT 1 FROM users WHERE id=? AND active=1 AND role='auditor'",[$owner])->fetchColumn()) throw new DomainException('Selecciona un auditor activo.');
        $assessment = null;
        if (isset($_POST['assessment']) && $_POST['assessment'] !== '') {
            $assessment = query($pdo,'SELECT r.id,r.rating,u.name FROM risk_assessments r JOIN auditable_units u ON u.id=r.unit_id WHERE r.id=?',[positiveId('assessment')])->fetch();
            if (!$assessment) throw new DomainException('La evaluación seleccionada no existe.');
        }
        query($pdo,'INSERT INTO audits(title,entity,objective,year,risk,owner_id) VALUES (?,?,?,?,?,?)',
            [field('title'),$assessment['name'] ?? field('entity'),field('objective',12000),$year,$assessment['rating'] ?? choice('risk',LEVELS),$owner]);
        $id = (int) $pdo->lastInsertId();
        if ($assessment) query($pdo,'INSERT INTO audit_assessment_links(audit_id,assessment_id) VALUES (?,?)',[$id,$assessment['id']]);
        record($pdo,$actor,$id,'auditoria_creada');
        return $id;
    }
    $id = positiveId('audit_id');
    $audit = accessible($pdo,$user,$id,true);
    $state = $audit['status'];
    $auditor = $user['role'] === 'auditor';
    $manager = manage($user);
    $detail = '';
    switch ($op) {
        case 'schedule':
        case 'log_time':
            $detail = saveHours($pdo,$user,$audit,$op);
            break;
        case 'advance':
            if (!$manager || !($next = nextState($pdo,$audit))) throw new DomainException('La transición requiere revisar pruebas o acciones pendientes.');
            $report = $next === 'emitida' ? field('report',30000) : $audit['report'];
            query($pdo,'UPDATE audits SET status=?, report=? WHERE id=?',[$next,$report,$id]);
            $detail = "$state → $next";
            break;
        case 'test':
            if (!$auditor || $state !== 'en_campo') throw new DomainException('La auditoría debe estar en campo.');
            query($pdo,'INSERT INTO tests(audit_id,title,procedure_text) VALUES (?,?,?)',[$id,field('title'),field('procedure',12000)]);
            $detail = $pdo->lastInsertId();
            break;
        case 'attach_test':
        case 'result':
        case 'review':
        case 'finding':
            $test = positiveId('test_id');
            $row = query($pdo,'SELECT * FROM tests WHERE id=? AND audit_id=?',[$test,$id])->fetch();
            if (!$row || $state !== 'en_campo') throw new DomainException('Prueba no disponible.');
            if ($op === 'attach_test' && $auditor && $row['status'] !== 'revisada') {
                storeAttachment($pdo,$actor,'test',$test);
            } elseif ($op === 'result' && $auditor && $row['status'] !== 'revisada') {
                query($pdo,"UPDATE tests SET result_text=?,prepared_by=?,status='documentada' WHERE id=?",[field('result',12000),$actor,$test]);
            } elseif ($op === 'review' && $manager && $row['status'] === 'documentada' && $actor !== (int) $row['prepared_by']) {
                $decision = choice('decision',['revisada','pendiente']);
                query($pdo,'UPDATE tests SET status=?, reviewed_by=? WHERE id=?',[$decision,$decision === 'revisada' ? $actor : null,$test]);
                $detail = $decision . ': ' . field('note',150);
            } elseif ($op === 'finding' && $auditor && $row['status'] !== 'pendiente') {
                query($pdo,'INSERT INTO findings(test_id,title,severity,criterion,condition_text,cause,effect,recommendation) VALUES (?,?,?,?,?,?,?,?)',
                    [$test,field('title'),choice('severity',LEVELS),field('criterion',5000),field('condition',5000),field('cause',5000),field('effect',5000),field('recommendation',5000)]);
            } else throw new DomainException('La prueba no admite esta operación.');
            $detail = trim("$test $detail");
            break;
        case 'action':
            if (!$manager || !in_array($state,['en_campo','en_revision'],true)) throw new DomainException('No se puede asignar una acción en este estado.');
            $finding = positiveId('finding_id');
            $owner = positiveId('owner');
            if (!query($pdo,'SELECT 1 FROM findings f JOIN tests t ON t.id=f.test_id WHERE f.id=? AND t.audit_id=?',[$finding,$id])->fetchColumn() ||
                !query($pdo,"SELECT 1 FROM users WHERE id=? AND active=1 AND role='responsable'",[$owner])->fetchColumn()) throw new DomainException('Selecciona un hallazgo y un responsable válidos.');
            $date = dateField('due');
            query($pdo,'INSERT INTO actions(finding_id,owner_id,description,due_date) VALUES (?,?,?,?)',[$finding,$owner,field('description',12000),$date]);
            $detail = $pdo->lastInsertId();
            break;
        case 'extend':
        case 'attach_action':
        case 'evidence':
        case 'verify':
            $action = positiveId('action_id');
            $row = query($pdo,'SELECT a.* FROM actions a JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id WHERE a.id=? AND t.audit_id=?',[$action,$id])->fetch();
            if (!$row || $state !== 'emitida') throw new DomainException('Acción no disponible.');
            if ($op === 'extend' && $manager && $row['status'] !== 'cerrada') {
                $newDue = dateField('due');
                if ($newDue <= $row['due_date']) throw new DomainException('La prórroga debe ser posterior al vencimiento actual.');
                $detail = $row['due_date'] . ' → ' . $newDue . ': ' . field('note',150);
                query($pdo,'UPDATE actions SET due_date=? WHERE id=?',[$newDue,$action]);
            } elseif ($op === 'attach_action' && $user['role'] === 'responsable' && $actor === (int) $row['owner_id'] && $row['status'] === 'abierta') {
                storeAttachment($pdo,$actor,'action',$action);
            } elseif ($op === 'evidence' && $user['role'] === 'responsable' && $actor === (int) $row['owner_id'] && $row['status'] === 'abierta') {
                query($pdo,"UPDATE actions SET evidence=?,status='en_verificacion' WHERE id=?",[field('evidence',12000),$action]);
            } elseif ($op === 'verify' && $manager && $row['status'] === 'en_verificacion') {
                $decision = choice('decision',['abierta','cerrada']);
                query($pdo,'UPDATE actions SET status=? WHERE id=?',[$decision,$action]);
                $detail = $decision . ': ' . field('note',150);
            } else throw new DomainException('La acción no admite esta operación.');
            $detail = trim("$action $detail");
            break;
        default:
            throw new DomainException('Operación no autorizada.');
    }
    record($pdo,$actor,$id,$op,$detail);
    return $id;
}
