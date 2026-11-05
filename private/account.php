<?php
declare(strict_types=1);

function updatePassword(PDO $pdo, array $user): void {
    $current = $_POST['current'] ?? null;
    $next = $_POST['password'] ?? null;
    if (!validPassword($next) || $next !== ($_POST['confirm'] ?? null)) {
        throw new DomainException('La contraseña nueva debe tener entre 12 y 72 bytes y coincidir en ambos campos.');
    }
    $hash = query($pdo,'SELECT password_hash FROM users WHERE id=? AND active=1 FOR UPDATE',[$user['id']])->fetchColumn();
    if (!is_string($hash) || !is_string($current) || strlen($current)>72 || str_contains($current,"\0") || !password_verify($current,$hash)) {
        throw new DomainException('La contraseña actual es incorrecta.');
    }
    if (password_verify($next,$hash)) throw new DomainException('La contraseña nueva debe ser distinta de la actual.');
    query($pdo,'UPDATE users SET password_hash=? WHERE id=?',[password_hash($next,PASSWORD_DEFAULT),$user['id']]);
    record($pdo,(int) $user['id'],null,'contrasena_actualizada');
}
