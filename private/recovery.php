<?php
declare(strict_types=1);

/**
 * Operación excepcional de recuperación. El secreto de autorización queda
 * fuera de la web: solo se conserva su huella en la configuración privada.
 */
function recoverAdminPassword(PDO $pdo, array $config, string $email, string $token): string {
    if (PHP_SAPI !== 'cli') throw new DomainException('Recuperación disponible únicamente desde la consola.');
    $expected=$config['recovery_token_hash'] ?? null;
    if (!is_string($expected) || !preg_match('/^[a-f0-9]{64}$/D',$expected)) {
        throw new DomainException('La recuperación administrativa no está habilitada.');
    }
    if (!is_string($token) || strlen($token)<32 || strlen($token)>256 ||
        !hash_equals($expected,hash('sha256',$token))) {
        throw new DomainException('Credenciales de recuperación inválidas.');
    }
    if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254) {
        throw new DomainException('Selecciona un correo válido.');
    }
    $pdo->beginTransaction();
    try {
        $account=query($pdo,"SELECT id,password_hash FROM users
            WHERE email=? AND role='admin' AND active=1 FOR UPDATE",[$email])->fetch();
        if (!$account) throw new DomainException('No existe una cuenta administradora activa con ese correo.');
        // Contraseña nueva generada por el sistema, nunca suministrada como argumento CLI.
        $newPassword=bin2hex(random_bytes(24));
        $newHash=password_hash($newPassword,PASSWORD_DEFAULT);
        query($pdo,'UPDATE users SET password_hash=? WHERE id=?',[$newHash,$account['id']]);
        record($pdo,(int)$account['id'],null,'recuperacion_administrativa',
            'Recuperación local autorizada; sesiones previas invalidadas');
        $pdo->commit();
        return $newPassword;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}
