<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
start();
$error = null;
$ready = false;
$installedNow = false;
try {
    $config = config();
    if (empty($config['install_token'])) {
        http_response_code(404);
        exit('Instalador deshabilitado.');
    }
    try {
        $ready = (bool) db($config)->query('SELECT id FROM installation WHERE id=1')->fetchColumn();
    } catch (PDOException $exception) {
        if (!in_array((int) ($exception->errorInfo[1] ?? 0), [1049,1146], true)) throw $exception;
    }
    if (!$ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        if (!hash_equals($config['install_token'], field('token', 256))) throw new DomainException('Clave de instalación inválida.');
        $email = strtolower(field('email', 254));
        $name = field('name', 160);
        $password = $_POST['password'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password) || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new DomainException('Usa un correo válido y una contraseña de entre 12 y 72 bytes.');
        }
        $database = $config['database'];
        $installer = db($config, true);
        $lock = 'auditpro:' . substr(hash('sha256', $database), 0, 48);
        if ((int) query($installer, 'SELECT GET_LOCK(?, 5)', [$lock])->fetchColumn() !== 1) {
            throw new DomainException('Hay otra instalación en curso. Intenta nuevamente.');
        }
        try {
            $installer->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $installer->exec("USE `$database`");
            $schema = file_get_contents(__DIR__ . '/../private/schema.sql');
            if ($schema === false) throw new RuntimeException('Falta el esquema de instalación.');
            foreach (explode(';', $schema) as $sql) {
                if (trim($sql) !== '') $installer->exec($sql);
            }
            foreach (['upgrade-002.sql','upgrade-003.sql','upgrade-004.sql','upgrade-005.sql','upgrade-006.sql'] as $file) {
                $migration = file_get_contents(__DIR__ . '/../private/' . $file);
                if ($migration === false) throw new RuntimeException('Falta un archivo del esquema.');
                foreach (explode(';', $migration) as $sql) {
                    if (trim($sql) !== '') $installer->exec($sql);
                }
            }
            db($config)->query('SELECT id FROM installation LIMIT 1');
            $installer->beginTransaction();
            if (!$installer->query('SELECT id FROM installation WHERE id=1')->fetchColumn()) {
                foreach (['rol'=>ROLES, 'severidad'=>LEVELS, 'auditoria'=>STATES] as $category => $items) {
                    foreach ($items as $item) {
                        query($installer, 'INSERT INTO catalog(category,code,label) VALUES (?,?,?) ON DUPLICATE KEY UPDATE label=label',
                            [$category, $item, ucfirst(str_replace('_', ' ', $item))]);
                    }
                }
                query($installer, "INSERT INTO users(email,name,password_hash,role) VALUES (?,?,?,'admin')",
                    [$email, $name, password_hash($password, PASSWORD_DEFAULT)]);
                $installer->exec('INSERT INTO installation(id) VALUES (1)');
                $installedNow = true;
            }
            $installer->commit();
            $ready = true;
        } finally {
            if ($installer->inTransaction()) $installer->rollBack();
            query($installer, 'SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }
} catch (DomainException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('AuditPro instalación: ' . get_class($exception) . ' ' . $exception->getCode());
    $error = 'No se pudo completar la instalación. Revisa PHP, las credenciales y los permisos SQL con TI.';
}
if ($ready) http_response_code($installedNow ? 201 : 403);
page('Instalación');
errorBox($error);
if ($ready): ?>
<section><p>AuditPro ya está instalado. Retira del archivo privado la clave y las credenciales de instalación.</p><a href="index.php">Iniciar sesión</a></section>
<?php else: ?>
<section><form method="post"><?= csrf() ?>
<label>Clave de instalación<input name="token" type="password" required></label>
<label>Nombre del administrador<input name="name" maxlength="160" required></label>
<label>Correo<input name="email" type="email" maxlength="254" required></label>
<label>Contraseña<input name="password" type="password" minlength="12" autocomplete="new-password" required></label>
<button>Instalar AuditPro</button></form></section>
<?php endif; endPage();
