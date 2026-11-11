<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/preflight.php';
try {
    $config=config();
    $checks=preflightChecks(db($config),$config);
    foreach ($checks as $item) {
        fwrite(STDOUT,($item['ok']?'[OK] ':'[FALTA] ').$item['description']."\n");
    }
    fwrite(STDOUT,"\nVALIDACIONES MANUALES PENDIENTES ANTES DE PRODUCCIÓN:\n");
    fwrite(STDOUT,"- HTTPS real, cabeceras y denegación HTTP de private/.\n");
    fwrite(STDOUT,"- Copia de seguridad, restauración y almacenamiento cifrado.\n");
    fwrite(STDOUT,"- Recorrido por roles, adjuntos, CSRF, accesibilidad y permisos por unidad.\n");
    fwrite(STDOUT,"- Política de retención, autorización legal de purgado y procedimiento de incidentes.\n");
    fwrite(STDOUT,"- Ejecución programada del proceso diario de recordatorios.\n");
    if (!preflightReady($checks)) exit(1);
    fwrite(STDOUT,"\nComprobaciones automatizables completadas. El piloto manual sigue pendiente.\n");
} catch (Throwable $exception) {
    error_log('AuditPro preflight: '.get_class($exception).' '.$exception->getCode());
    fwrite(STDERR,"El diagnóstico no pudo completarse. Revisa conectividad, configuración o migraciones.\n");
    exit(1);
}
