<?php
declare(strict_types=1);

const ROLES = ['admin', 'supervisor', 'auditor', 'responsable', 'riesgos', 'cumplimiento'];
const LEVELS = ['bajo', 'medio', 'alto', 'critico'];
const STATES = ['borrador', 'aprobada', 'en_campo', 'en_revision', 'emitida', 'cerrada'];

function config(): array {
    $path = getenv('AUDITPRO_CONFIG');
    if (!$path || !is_file($path)) {
        throw new RuntimeException('Configura AUDITPRO_CONFIG con la ruta del archivo privado.');
    }
    $root = realpath(PHP_SAPI === 'cli' ? __DIR__ . '/../public' : ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $resolved = realpath($path);
    if (!$root || !$resolved || str_starts_with(strtolower($resolved), strtolower(rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('El archivo de configuración debe estar fuera de la raíz pública.');
    }
    $config = require $resolved;
    if (!is_array($config) || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $config['database'] ?? '')) {
        throw new RuntimeException('El nombre de la base de datos no es válido.');
    }
    return $config;
}

function db(array $config, bool $install = false): PDO {
    $host = $config['host'] ?? 'localhost';
    $port = (int) ($config['port'] ?? 3306);
    if (!preg_match('/^[A-Za-z0-9.:-]+$/D', $host) || $port < 1 || $port > 65535) {
        throw new RuntimeException('El servidor SQL no es válido.');
    }
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    if (!$install) $dsn .= ';dbname=' . $config['database'];
    return new PDO($dsn, $install ? $config['install_user'] : $config['user'],
        $install ? $config['install_password'] : $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
}

function start(): void {
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
    if (!$https && !(getenv('AUDITPRO_DEV') === '1' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true))) {
        http_response_code(403);
        exit('AuditPro requiere HTTPS.');
    }
    header("Content-Security-Policy: default-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['secure'=>$https, 'httponly'=>true, 'samesite'=>'Lax']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function e(string|int|null $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrf(): string {
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function field(string $key, int $max = 180): string {
    $value = $_POST[$key] ?? null;
    if (!is_string($value) || trim($value) === '' || strlen($value) > $max) {
        throw new DomainException("Revisa el campo $key.");
    }
    return trim($value);
}
function validPassword(mixed $password): bool {
    return is_string($password) && strlen($password) >= 12 && strlen($password) <= 72 && !str_contains($password,"\0");
}
function dateField(string $key): string {
    $date = field($key,10);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < '1000-01-01') throw new DomainException('Fecha inválida.');
    return $date;
}
function choice(string $key, array $choices): string {
    $value = field($key);
    if (!in_array($value, $choices, true)) throw new DomainException("Valor inválido para $key.");
    return $value;
}
function verifyCsrf(): void {
    if (!hash_equals($_SESSION['csrf'], field('csrf', 64))) throw new DomainException('La sesión del formulario venció. Recarga la página.');
}
function query(PDO $pdo, string $sql, array $params = []): PDOStatement {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement;
}
function redirect(string $url = 'index.php'): never {
    header('Location: ' . $url, true, 303);
    exit;
}
function page(string $title, ?array $user = null): void { ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · AuditPro</title><link rel="stylesheet" href="app.css"><script src="theme.js" defer></script></head>
<body><header><a href="index.php">AuditPro</a><label>Tema <select id="theme"><option value="">Sistema</option><option value="light">Claro</option><option value="dark">Oscuro</option></select></label></header>
<?php if ($user): ?>
<div class="shell"><aside><nav aria-label="Módulos de AuditPro">
<p class="nav-caption">ESPACIO DE TRABAJO</p>
<?php
$sections = isGrcSpecialist($user) ? ['grc'=>'Riesgos y cumplimiento'] : [''=>'Expedientes','inbox'=>'Bandeja'];
if (manage($user) && !($user['role']==='supervisor' && (int)($user['unit_restricted'] ?? 0)===1)) $sections += ['plans'=>'Plan anual','universe'=>'Universo auditable','grc'=>'Riesgos y cumplimiento'];
if ($user['role']==='admin') $sections['unit-access']='Acceso por unidad';
if ($user['role'] === 'auditor' || manage($user)) $sections['templates'] = 'Plantillas de pruebas';
$sections['account'] = 'Mi cuenta';
foreach ($sections as $key=>$label):
    $href = 'index.php' . ($key ? '?view=' . $key : '');
    $selected = ($_GET['view'] ?? '') === $key;
?>
<a href="<?= e($href) ?>"<?= $selected ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
<div class="nav-account"><strong><?= e($user['name']) ?></strong><span><?= e($user['role']) ?></span>
<form method="post" action="index.php"><?= csrf() ?><input type="hidden" name="op" value="logout"><button>Salir</button></form></div>
</nav></aside>
<?php endif; ?>
<main><h1><?= e($title) ?></h1>
<?php }
function endPage(bool $authenticated = false): void { echo '</main>' . ($authenticated ? '</div>' : '') . '</body></html>'; }
function errorBox(?string $error): void {
    if ($error) echo '<p class="error" role="alert">' . e($error) . '</p>';
}
