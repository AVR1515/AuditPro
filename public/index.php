<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
require __DIR__ . '/../private/workflow.php';
require __DIR__ . '/../private/attachments.php';
require __DIR__ . '/../private/overview.php';
require __DIR__ . '/../private/universe.php';
require __DIR__ . '/../private/grc.php';
require __DIR__ . '/../private/inbox.php';
require __DIR__ . '/../private/plans.php';
require __DIR__ . '/../private/hours.php';
require __DIR__ . '/../private/reminders.php';
require __DIR__ . '/../private/revisions.php';
require __DIR__ . '/../private/account.php';
require __DIR__ . '/../private/obligation-import.php';
require __DIR__ . '/../private/requests.php';
require __DIR__ . '/../private/templates.php';
require __DIR__ . '/../private/capacity.php';
require __DIR__ . '/../private/risk-acceptance.php';
require __DIR__ . '/../private/attestations.php';
require __DIR__ . '/../private/exceptions.php';
require __DIR__ . '/../private/multiyear.php';
require __DIR__ . '/../private/kri.php';
require __DIR__ . '/../private/risk-dashboard.php';
require __DIR__ . '/../private/escalations.php';
require __DIR__ . '/../private/unit-access.php';
require __DIR__ . '/../private/scoped-overview.php';
require __DIR__ . '/../private/retention.php';
start();
$error = null;
$user = null;
$audit = null;
try {
    try {
        $pdo = db(config());
        if (!$pdo->query('SELECT id FROM installation WHERE id=1')->fetchColumn()) redirect('install.php');
    } catch (PDOException $exception) {
        if (in_array((int) ($exception->errorInfo[1] ?? 0),[1049,1146],true)) redirect('install.php');
        throw $exception;
    }
    if (isset($_SESSION['user'])) {
        $account = query($pdo,'SELECT id,name,role,unit_restricted,password_hash FROM users WHERE id=? AND active=1',[$_SESSION['user']])->fetch();
        if ($account && hash_equals(hash('sha256',$account['password_hash']),$_SESSION['password_version'] ?? '')) {
            unset($account['password_hash']);
            $user = $account;
        }
    }
    if (isset($_GET['export']) && $user && in_array($user['role'],['admin','supervisor','auditor'],true)) {
        planCsv(planRows($pdo,$user,planFilters()));
    }
    if (isset($_GET['download'])) {
        $download = filter_var($_GET['download'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$user || !$download) throw new DomainException('Archivo no disponible.');
        downloadAttachment($pdo,$user,$download);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $op = field('op');
        if ($op === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            redirect();
        }
        if (!$user && $op === 'login') {
            $email = strtolower(field('email',254));
            $password = $_POST['password'] ?? null;
            if (!is_string($password) || strlen($password)>72 || str_contains($password,"\0")) throw new DomainException('Credenciales inválidas.');
            $bucket = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $now = time();
            query($pdo, 'INSERT INTO login_limits(bucket,attempts,expires_at) VALUES (?,1,?)
                ON DUPLICATE KEY UPDATE attempts=IF(expires_at<=?,1,attempts+1), expires_at=IF(expires_at<=?,?,expires_at)',
                [$bucket,$now+900,$now,$now,$now+900]);
            if ((int) query($pdo,'SELECT attempts FROM login_limits WHERE bucket=?',[$bucket])->fetchColumn() > 15) {
                throw new DomainException('Demasiados intentos. Vuelve a intentarlo en unos minutos.');
            }
            $account = query($pdo,'SELECT id,password_hash FROM users WHERE email=? AND active=1',[$email])->fetch();
            $hash = $account['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
            if (!password_verify($password,$hash) || !$account) throw new DomainException('Credenciales inválidas.');
            session_regenerate_id(true);
            $_SESSION = ['user'=>$account['id'],'password_version'=>hash('sha256',$hash),'csrf'=>bin2hex(random_bytes(32))];
            redirect();
        }
        if (!$user) throw new DomainException('Inicia sesión para continuar.');
        $pdo->beginTransaction();
        if (in_array($op,['retention_set','retention_purge'],true)) {
            saveRetention($pdo,$user,$op);
            $pdo->commit();
            redirect('index.php?view=retention');
        }
        if (in_array($op,['unit_scope','unit_grant','unit_revoke'],true)) {
            saveUnitGrant($pdo,$user,$op);
            $pdo->commit();
            redirect('index.php?view=unit-access');
        }
        if (in_array($op,['unit','assess'],true)) {
            if (scopedGlobalDeny($user)) throw new DomainException('Administración global del universo no disponible para supervisores restringidos.');
            assess($pdo,$user,$op);
            $pdo->commit();
            redirect('index.php?view=universe');
        }
        if (in_array($op,KRI_OPS,true)) {
            if (scopedGlobalDeny($user)) throw new DomainException('Este módulo global no está disponible para supervisores restringidos.');
            saveKri($pdo,$user,$op);
            $pdo->commit();
            redirect('index.php?view=grc');
        }
        if (in_array($op,EXCEPTION_OPS,true)) {
            if (scopedGlobalDeny($user)) throw new DomainException('Este módulo global no está disponible para supervisores restringidos.');
            saveComplianceException($pdo,$user,$op);
            $pdo->commit();
            redirect('index.php?view=grc');
        }
        if ($op === 'obligation_attest') {
            if (scopedGlobalDeny($user)) throw new DomainException('Este módulo global no está disponible para supervisores restringidos.');
            saveAttestation($pdo,$user);
            $pdo->commit();
            redirect('index.php?view=grc');
        }
        if (in_array($op,GRC_OPS,true)) {
            if (scopedGlobalDeny($user)) throw new DomainException('Este módulo global no está disponible para supervisores restringidos.');
            saveGrc($pdo,$user,$op);
            $pdo->commit();
            redirect('index.php?view=grc');
        }
        if (in_array($op,MULTIYEAR_OPS,true)) {
            if (scopedGlobalDeny($user)) throw new DomainException('Planificación global no disponible para supervisores restringidos.');
            saveMultiyear($pdo,$user,$op);
            $pdo->commit();
            redirect('index.php?view=plans');
        }
        if ($op === 'save_capacity') {
            if (scopedGlobalDeny($user)) throw new DomainException('Capacidad global no disponible para supervisores restringidos.');
            saveAuditorCapacity($pdo,$user);
            $pdo->commit();
            redirect('index.php?view=plans&capacity_year=' . positiveId('year'));
        }
        if (in_array($op,['snapshot_plan','approve_plan'],true)) {
            if (scopedGlobalDeny($user)) throw new DomainException('Planificación global no disponible para supervisores restringidos.');
            savePlan($pdo,$user,$op);
            $pdo->commit();
            redirect('index.php?view=plans');
        }
        if ($op === 'save_escalation_policy') {
            saveEscalationPolicy($pdo,$user);
            $pdo->commit();
            redirect('index.php?view=inbox');
        }
        if ($op === 'read_escalation') {
            if (scopedGlobalDeny($user)) throw new DomainException('Escalaciones globales no disponibles para supervisores restringidos.');
            readEscalation($pdo,$user,positiveId('escalation_id'));
            $pdo->commit();
            redirect('index.php?view=inbox');
        }
        if ($op === 'read_reminder') {
            markReminderRead($pdo,$user,positiveId('reminder'));
            $pdo->commit();
            redirect('index.php?view=inbox');
        }
        if ($op === 'password') {
            updatePassword($pdo,$user);
            $pdo->commit();
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['notice'] = 'Contraseña actualizada. Inicia sesión de nuevo.';
            redirect();
        }
        if (in_array($op,['preview_obligations','confirm_obligations','cancel_obligations'],true)) {
            if (scopedGlobalDeny($user)) throw new DomainException('La importación global no está disponible para supervisores restringidos.');
            $destination = importObligations($pdo,$user,$op);
            $pdo->commit();
            if ($op !== 'preview_obligations') unset($_SESSION['obligation_import']);
            redirect($destination);
        }
        if (in_array($op,RISK_ACCEPTANCE_OPS,true)) {
            $id = saveRiskAcceptance($pdo,$user,$op);
            $pdo->commit();
            redirect('index.php?id=' . $id);
        }
        if (in_array($op,TEMPLATE_OPS,true)) {
            $id = saveTemplate($pdo,$user,$op);
            $pdo->commit();
            redirect($id ? 'index.php?id=' . $id : 'index.php?view=templates');
        }
        if (in_array($op,REQUEST_OPS,true)) {
            $id = saveEvidenceRequest($pdo,$user,$op);
            $pdo->commit();
            redirect('index.php?id=' . $id);
        }
        $id = mutate($pdo,$user,$op);
        $pdo->commit();
        redirect($id ? 'index.php?id=' . $id : 'index.php');
    }
} catch (DomainException $exception) {
    if (isset($_GET['download'])) { http_response_code(404); exit('Archivo no disponible.'); }
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('AuditPro: ' . get_class($exception) . ' ' . $exception->getCode());
    $error = 'No se pudo completar la operación. Si es un servidor nuevo, abre el instalador.';
} finally {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
}
if ($user && isset($_GET['id'])) {
    try {
        $id = filter_var($_GET['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) throw new DomainException('Expediente no disponible.');
        $audit = accessible($pdo,$user,$id);
    } catch (DomainException $exception) {
        http_response_code(404);
        $error = $exception->getMessage();
    }
}
$view = $_GET['view'] ?? '';
if ($user && isGrcSpecialist($user) && !isset($_GET['id']) && $view === '') $view = 'grc';
$reportView = $view === 'report';
if ($reportView && (!$audit || !$user || $user['role'] === 'responsable' || $audit['report'] === null)) {
    http_response_code(404);
    exit('Informe no disponible.');
}
function form(string $op, array $keys = [], bool $inline = false, bool $upload = false): void {
    echo '<form method="post"' . ($inline ? ' class="inline"' : '') . ($upload ? ' enctype="multipart/form-data"' : '') . '>' . csrf();
    foreach (['op'=>$op] + $keys as $name=>$value) {
        echo '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
    }
}
function options(array $values, string $selected = ''): void {
    foreach ($values as $value) echo '<option value="' . e($value) . '"' . ($value === $selected ? ' selected' : '') . '>' . e(ucfirst(str_replace('_',' ',$value))) . '</option>';
}
function people(array $users): void {
    foreach ($users as $person) echo '<option value="' . e($person['id']) . '">' . e($person['name']) . '</option>';
}
function copyBlock(string $label, ?string $value): void {
    echo '<p class="copy"><strong>' . e($label) . '</strong> ' . e($value) . '</p>';
}
page($audit && $user['role'] !== 'responsable' ? $audit['title'] : ($user ? 'Área de trabajo' : 'Iniciar sesión'),$user);
errorBox($error);
if (!$user): ?>
<?php if (isset($_SESSION['notice'])): ?><p role="status"><?= e($_SESSION['notice']) ?></p><?php unset($_SESSION['notice']); endif; ?>
<section><?php form('login'); ?>
<label>Correo<input name="email" type="email" autocomplete="username" required></label>
<label>Contraseña<input name="password" type="password" autocomplete="current-password" required></label>
<button>Entrar</button></form></section>
<?php if ($error && !isset($pdo)): ?><a href="install.php">Instalar AuditPro</a><?php endif;
endPage(); return; endif; ?>
<?php
$manager = manage($user);
$auditor = $user['role'] === 'auditor';
$responsible = $user['role'] === 'responsable';
if ($view === 'my-units' && restrictedSupervisor($user) && !$audit) {
    require __DIR__ . '/../private/scoped-overview-view.php';
    endPage(true);
    return;
}
if ($view === 'retention' && $user['role'] === 'admin' && !$audit) {
    require __DIR__ . '/../private/retention-view.php';
    endPage(true);
    return;
}
if ($view === 'unit-access' && $user['role'] === 'admin' && !$audit) {
    require __DIR__ . '/../private/unit-access-view.php';
    endPage(true);
    return;
}
if (($_GET['view'] ?? '') === 'universe' && $manager && !scopedGlobalDeny($user) && !$audit) {
    require __DIR__ . '/../private/universe-view.php';
    endPage(true);
    return;
}
if ($view === 'grc' && canGrc($user) && !scopedGlobalDeny($user) && !$audit) {
    require __DIR__ . '/../private/grc-view.php';
    endPage(true);
    return;
}
if ($view === 'templates' && canViewTemplates($user) && !$audit) {
    require __DIR__ . '/../private/templates-view.php';
    endPage(true);
    return;
}
if ($view === 'inbox' && !isGrcSpecialist($user) && !$audit) {
    require __DIR__ . '/../private/inbox-view.php';
    endPage(true);
    return;
}
if (($_GET['view'] ?? '') === 'plans' && $manager && !scopedGlobalDeny($user) && !$audit) {
    require __DIR__ . '/../private/plans-view.php';
    endPage(true);
    return;
}
if ($reportView) {
    require __DIR__ . '/../private/report.php';
    endPage(true);
    return;
}
if (($_GET['view'] ?? '') === 'account' && !$audit) {
    require __DIR__ . '/../private/account-view.php';
    endPage(true);
    return;
}
if ($view === 'import' && canCompliance($user) && !scopedGlobalDeny($user) && !$audit) {
    require __DIR__ . '/../private/import-view.php';
    endPage(true);
    return;
}
if (!$audit && isGrcSpecialist($user)) {
    if (!$error) errorBox('La vista solicitada está fuera del ámbito de tu cuenta.');
    endPage(true);
    return;
}
if (!$audit):
    if ($responsible) {
        $items = query($pdo,"SELECT a.description,a.due_date,a.status,t.audit_id FROM actions a JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id JOIN audits d ON d.id=t.audit_id WHERE a.owner_id=? AND d.status IN ('emitida','cerrada') ORDER BY a.due_date",[$user['id']])->fetchAll();
    } else {
        $filters = planFilters();
        $items = planRows($pdo,$user,$filters,true);
        $completed = count(array_filter($items,fn(array $item): bool => $item['status'] === 'cerrada'));
        $issued = count(array_filter($items,fn(array $item): bool => in_array($item['status'],['emitida','cerrada'],true)));
        $export = '?' . http_build_query(['export'=>'plan'] + $filters);
    } ?>
<section><h2><?= $responsible ? 'Mis acciones' : 'Plan y expedientes' ?></h2>
<?php if (!$responsible): ?>
<dl class="metrics"><div><dt>Expedientes filtrados</dt><dd><?= count($items) ?></dd></div><div><dt>Informes emitidos</dt><dd><?= $issued ?></dd></div><div><dt>Auditorías cerradas</dt><dd><?= $completed ?></dd></div></dl>
<form method="get" class="filters">
<label>Buscar<input type="search" name="q" value="<?= e($filters['q']) ?>" maxlength="180" placeholder="Título o entidad"></label>
<label>Año<input type="number" name="year" min="2000" max="2100" value="<?= e($filters['year']) ?>"></label>
<label>Estado<select name="status"><option value="">Todos</option><?php options(STATES,$filters['status']); ?></select></label>
<label>Riesgo<select name="risk"><option value="">Todos</option><?php options(LEVELS,$filters['risk']); ?></select></label>
<button>Filtrar</button><a href="index.php">Limpiar</a><a href="<?= e($export) ?>">Exportar CSV</a></form>
<?php require __DIR__ . '/../private/metrics-view.php'; ?>
<?php endif; ?>
<?php if (!$items): ?><p class="muted">Aún no hay registros asignados.</p><?php else: ?>
<div class="table"><table><thead><tr><?php foreach ($responsible ? ['Acción','Vence','Estado'] : ['Auditoría','Entidad','Año','Riesgo','Estado'] as $column): ?><th scope="col"><?= e($column) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr><?php if ($responsible): ?>
<td><a href="?id=<?= e($item['audit_id']) ?>"><?= e($item['description']) ?></a></td><td><?= e($item['due_date']) ?></td>
<?php else: ?><td><a href="?id=<?= e($item['id']) ?>"><?= e($item['title']) ?></a></td><td><?= e($item['entity']) ?></td><td><?= e($item['year']) ?></td><td><?= e($item['risk']) ?></td>
<?php endif; ?><td><?= e($item['status']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<div class="grid">
<?php if ($manager): $auditors = $pdo->query("SELECT id,name FROM users WHERE role='auditor' AND active=1 ORDER BY name")->fetchAll(); ?>
<section><h2>Nueva auditoría</h2><?php if (!$auditors): ?><p>Crea primero un usuario con rol auditor.</p><?php else: form('audit'); ?>
<label>Título<input name="title" required maxlength="180"></label>
<label>Evaluación de riesgo<select name="assessment"><option value="">Captura manual</option>
<?php foreach (array_filter(assessments($pdo,true),fn($evaluation)=>allowedUnit($pdo,$user,(int)$evaluation['unit_id'])) as $evaluation): ?><option value="<?= e($evaluation['id']) ?>"><?= e($evaluation['name']) ?> · <?= e($evaluation['score']) ?>/25</option><?php endforeach; ?>
</select></label><label>Entidad para captura manual<input name="entity" maxlength="180"></label>
<label>Año<input name="year" type="number" min="2000" max="2100" value="<?= e(date('Y')) ?>" required></label>
<label>Riesgo<select name="risk"><?php options(LEVELS); ?></select></label>
<label>Auditor<select name="owner"><?php people($auditors); ?></select></label>
<label>Objetivo y alcance<textarea name="objective" required maxlength="12000"></textarea></label><button>Guardar borrador</button></form><?php endif; ?></section>
<?php endif; if ($user['role'] === 'admin'): ?>
<section><h2>Crear usuario</h2><?php form('user'); ?>
<label>Nombre<input name="name" required maxlength="160"></label><label>Correo<input name="email" type="email" required></label>
<label>Contraseña inicial<input name="password" type="password" autocomplete="new-password" minlength="12" required></label>
<label>Rol<select name="role"><?php options(ROLES); ?></select></label><button>Crear usuario</button></form></section>
<?php endif; ?></div>
<?php else: require __DIR__ . '/../private/detail.php'; endif; endPage(true);
