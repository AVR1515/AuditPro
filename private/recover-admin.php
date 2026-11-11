<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/recovery.php';

$email=$argv[1] ?? '';
if (!is_string($email) || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR,"Uso: php private/recover-admin.php admin@empresa.com\n");
    exit(2);
}
$hidden=false;
try {
    $config=config();
    if (!isset($config['recovery_token_hash']) || $config['recovery_token_hash']==='') {
        throw new DomainException('Configura primero recovery_token_hash en el archivo privado.');
    }
    if (function_exists('stream_isatty') && stream_isatty(STDIN) &&
        PHP_OS_FAMILY!=='Windows' && function_exists('system')) {
        system('stty -echo 2>/dev/null', $status);
        $hidden=$status===0;
    }
    fwrite(STDERR,$hidden ? 'Introduce el token de recuperación (oculto): ' :
        'Introduce el token de recuperación por entrada estándar (puede verse en la terminal): ');
    $token=fgets(STDIN,512);
    if ($hidden) { system('stty echo 2>/dev/null'); $hidden=false; fwrite(STDERR,"\n"); }
    if ($token===false) throw new DomainException('No se recibió el token.');
    $password=recoverAdminPassword(db($config),$config,$email,rtrim($token,"\r\n"));
    unset($token);
    fwrite(STDOUT,"Contraseña temporal para $email: $password\n");
    fwrite(STDOUT,"Guárdala en un gestor seguro, inicia sesión y cámbiala inmediatamente.\n");
} catch (Throwable $exception) {
    error_log('AuditPro recuperación: '.get_class($exception).' '.$exception->getCode());
    fwrite(STDERR,"No se completó la recuperación. Verifica el token y la cuenta administradora.\n");
    exit(1);
} finally {
    if ($hidden) system('stty echo 2>/dev/null');
}
