<?php
$today = new DateTimeImmutable('today');
$tasks = actionInbox($pdo,$user);
$overdue = count(array_filter($tasks,fn(array $task): bool => inboxStatus($task,$today) === 'Vencida'));
$verification = count(array_filter($tasks,fn(array $task): bool => $task['status'] === 'en_verificacion'));
?>
<section><h2>Seguimiento de acciones</h2>
<dl class="metrics"><div><dt>Acciones abiertas</dt><dd><?= count($tasks) ?></dd></div><div><dt>Vencidas</dt><dd><?= $overdue ?></dd></div><div><dt>En verificación</dt><dd><?= $verification ?></dd></div></dl>
<?php if (!$tasks): ?><p class="muted">Sin acciones pendientes en tu ámbito.</p><?php else: ?>
<div class="table"><table><thead><tr><th scope="col">Acción</th><th scope="col">Responsable</th><th scope="col">Vence</th><th scope="col">Estado</th><th scope="col">Alerta</th></tr></thead><tbody>
<?php foreach ($tasks as $task): ?><tr><td><a href="index.php?id=<?= e($task['audit_id']) ?>#action-<?= e($task['id']) ?>"><?= e($task['description']) ?></a></td>
<td><?= e($task['name']) ?></td><td><?= e($task['due_date']) ?></td><td><?= e(str_replace('_',' ',$task['status'])) ?></td><td><?= e(inboxStatus($task,$today)) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<?php if (manage($user)): ?>
<section><h2>Revisiones de riesgo y cumplimiento</h2>
<?php $reviews = query($pdo,"SELECT title,review_on,'Riesgo' AS kind FROM grc_risks WHERE review_on<=?
    UNION ALL SELECT title,review_on,'Obligación' AS kind FROM grc_obligations WHERE review_on<=? ORDER BY review_on,title",
    array_fill(0,2,$today->modify('+7 days')->format('Y-m-d')))->fetchAll(); ?>
<?php if (!$reviews): ?><p class="muted">Sin revisiones vencidas o próximas.</p><?php else: ?>
<ul><?php foreach ($reviews as $review): ?><li><a href="index.php?view=grc"><?= e($review['title']) ?></a> · <?= e($review['kind']) ?> · <?= e($review['review_on']) ?></li><?php endforeach; ?></ul>
<?php endif; ?></section><?php endif; ?>
