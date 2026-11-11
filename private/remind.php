<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/reminders.php';
require __DIR__ . '/escalations.php';
try {
    $pdo = db(config());
    if (!$pdo->query('SELECT id FROM installation WHERE id=1')->fetchColumn()) throw new RuntimeException('Instalación pendiente.');
    $today=new DateTimeImmutable('today');
    if (($argv[1] ?? '')==='--dry-run') {
        $candidates=reminderCandidates($pdo,$today);
        $policy=escalationPolicy($pdo);
        fwrite(STDOUT,"Previsión sin escritura: próximas ".
            (int)$candidates['approaching'].", vencidas ".
            (int)$candidates['overdue'].". Escalamiento ".
            ((int)$policy['enabled'] ? 'activo' : 'desactivado').".\n");
        exit(0);
    }
    if (count($argv)>1) throw new DomainException('Solo se admite la opción --dry-run.');
    $pdo->beginTransaction();
    generateReminders($pdo,$today);
    generateEscalations($pdo,$today);
    recordReminderRun($pdo,new DateTimeImmutable());
    $pdo->commit();
    fwrite(STDOUT,"Recordatorios procesados y ejecución registrada.\n");
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,'No se procesaron los recordatorios (' . get_class($exception) . ' ' . $exception->getCode() . ").\n");
    exit(1);
}
