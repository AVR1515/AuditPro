<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
require __DIR__ . '/../private/workflow.php';
require __DIR__ . '/../private/attachments.php';
start();
$error = null;
$user = null;
$audit = null;
try {
    $pdo = db(config());
    if (!$pdo->query('SELECT id FROM installation WHERE id=1')->fetchColumn()) redirect('install.php');
    if (isset($_SESSION['user'])) $user = query($pdo,'SELECT id,name,role FROM users WHERE id=? AND active=1',[$_SESSION['user']])->fetch() ?: null;
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
            $_SESSION = ['user'=>$account['id'],'csrf'=>bin2hex(random_bytes(32))];
            redirect();
        }
        if (!$user) throw new DomainException('Inicia sesión para continuar.');
        $pdo->beginTransaction();
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
function form(string $op, array $keys = [], bool $inline = false, bool $upload = false): void {
    echo '<form method="post"' . ($inline ? ' class="inline"' : '') . ($upload ? ' enctype="multipart/form-data"' : '') . '>' . csrf();
    foreach (['op'=>$op] + $keys as $name=>$value) {
        echo '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
    }
}
function options(array $values): void {
    foreach ($values as $value) echo '<option value="' . e($value) . '">' . e(ucfirst(str_replace('_',' ',$value))) . '</option>';
}
function people(array $users): void {
    foreach ($users as $person) echo '<option value="' . e($person['id']) . '">' . e($person['name']) . '</option>';
}
function copyBlock(string $label, ?string $value): void {
    echo '<p class="copy"><strong>' . e($label) . '</strong> ' . e($value) . '</p>';
}
page($audit && $user['role'] !== 'responsable' ? $audit['title'] : ($user ? 'Área de trabajo' : 'Iniciar sesión'));
errorBox($error);
if (!$user): ?>
<section><?php form('login'); ?>
<label>Correo<input name="email" type="email" autocomplete="username" required></label>
<label>Contraseña<input name="password" type="password" autocomplete="current-password" required></label>
<button>Entrar</button></form></section>
<?php if ($error && !isset($pdo)): ?><a href="install.php">Instalar AuditPro</a><?php endif;
endPage(); return; endif; ?>
<nav><a href="index.php">Área de trabajo</a><span><?= e($user['name']) ?> · <?= e($user['role']) ?></span><?php form('logout',[],true); ?><button>Salir</button></form></nav>
<?php
$manager = manage($user);
$auditor = $user['role'] === 'auditor';
$responsible = $user['role'] === 'responsable';
if (!$audit):
    if ($responsible) {
        $items = query($pdo,"SELECT a.description,a.due_date,a.status,t.audit_id FROM actions a JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id JOIN audits d ON d.id=t.audit_id WHERE a.owner_id=? AND d.status IN ('emitida','cerrada') ORDER BY a.due_date",[$user['id']])->fetchAll();
    } else {
        $items = query($pdo,'SELECT id,title,entity,year,risk,status FROM audits' . ($manager ? '' : ' WHERE owner_id=?') . ' ORDER BY year DESC,id DESC',$manager ? [] : [$user['id']])->fetchAll();
    } ?>
<section><h2><?= $responsible ? 'Mis acciones' : 'Plan y expedientes' ?></h2>
<?php if (!$items): ?><p class="muted">Aún no hay registros asignados.</p><?php else: ?>
<div class="table"><table><thead><tr><?php foreach ($responsible ? ['Acción','Vence','Estado'] : ['Auditoría','Entidad','Año','Riesgo','Estado'] as $column): ?><th scope="col"><?= e($column) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr><?php if ($responsible): ?>
<td><a href="?id=<?= e($item['audit_id']) ?>"><?= e($item['description']) ?></a></td><td><?= e($item['due_date']) ?></td>
<?php else: ?><td><a href="?id=<?= e($item['id']) ?>"><?= e($item['title']) ?></a></td><td><?= e($item['entity']) ?></td><td><?= e($item['year']) ?></td><td><?= e($item['risk']) ?></td>
<?php endif; ?><td><?= e($item['status']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<div class="grid">
<?php if ($manager): $auditors = $pdo->query("SELECT id,name FROM users WHERE role='auditor' AND active=1 ORDER BY name")->fetchAll(); ?>
<section><h2>Nueva auditoría</h2><?php if (!$auditors): ?><p>Crea primero un usuario con rol auditor.</p><?php else: form('audit'); ?>
<label>Título<input name="title" required maxlength="180"></label><label>Entidad<input name="entity" required maxlength="180"></label>
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
<?php else: require __DIR__ . '/../private/detail.php'; endif; endPage();
