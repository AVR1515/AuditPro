<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/reminders.php';
try {
    $pdo = db(config());
    if (!$pdo->query('SELECT id FROM installation WHERE id=1')->fetchColumn()) throw new RuntimeException('Instalación pendiente.');
    generateReminders($pdo,new DateTimeImmutable('today'));
    fwrite(STDOUT,"Recordatorios procesados.\n");
} catch (Throwable $exception) {
    fwrite(STDERR,'No se procesaron los recordatorios (' . get_class($exception) . ' ' . $exception->getCode() . ").\n");
    exit(1);
}
