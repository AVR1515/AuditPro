<?php
$today = new DateTimeImmutable('today');
$tasks = actionInbox($pdo,$user);
$overdue = count(array_filter($tasks,fn(array $task): bool => inboxStatus($task,$today) === 'Vencida'));
$verification = count(array_filter($tasks,fn(array $task): bool => $task['status'] === 'en_verificacion'));
$reminders = unreadReminders($pdo,$user);
?>
<?php if ($reminders): ?><section><h2>Recordatorios sin leer</h2><ul>
<?php foreach ($reminders as $reminder): ?><li>
<a href="index.php?id=<?= e($reminder['audit_id']) ?>#action-<?= e($reminder['action_id']) ?>"><?= e($reminder['description']) ?></a>
· <?= $reminder['kind'] === 'vencida' ? 'Vencida' : 'Próxima a vencer' ?> · <?= e($reminder['due_date']) ?>
<?php form('read_reminder',['reminder'=>$reminder['id']],true); ?><button>Marcar como leído</button></form></li><?php endforeach; ?>
</ul></section><?php endif; ?>
<?php if (manage($user) || $user['role'] === 'auditor'):
$requestTasks = evidenceRequestInbox($pdo,$user);
$requestOverdue = count(array_filter($requestTasks,static fn(array $request): bool => $request['status'] === 'abierta' && $request['due_on'] < $today->format('Y-m-d')));
$requestToReview = count(array_filter($requestTasks,static fn(array $request): bool => $request['status'] === 'respondida'));
?>
<section><h2>Solicitudes de evidencia</h2>
<dl class="metrics"><div><dt>Solicitudes pendientes</dt><dd><?= count($requestTasks) ?></dd></div>
<div><dt>Vencidas sin respuesta</dt><dd><?= $requestOverdue ?></dd></div>
<div><dt>Respuestas por revisar</dt><dd><?= $requestToReview ?></dd></div></dl>
<?php if (!$requestTasks): ?><p class="muted">Sin solicitudes pendientes dentro de tu ámbito.</p><?php else: ?>
<div class="table"><table><thead><tr><th scope="col">Solicitud</th><th scope="col">Auditoría</th><th scope="col">Auditor</th><th scope="col">Límite</th><th scope="col">Estado</th></tr></thead><tbody>
<?php foreach ($requestTasks as $request): ?>
<tr><td><a href="index.php?id=<?= e($request['audit_id']) ?>#request-<?= e($request['id']) ?>"><?= e($request['subject']) ?></a></td>
<td><?= e($request['audit_title']) ?></td><td><?= e($request['assigned_auditor']) ?></td><td><?= e($request['due_on']) ?></td>
<td><?= e($request['status'] === 'respondida' ? 'Respuesta por revisar' : ($request['due_on'] < $today->format('Y-m-d') ? 'Vencida' : 'Pendiente')) ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php endif; ?>
<section><h2>Seguimiento de acciones</h2>
<dl class="metrics"><div><dt>Acciones abiertas</dt><dd><?= count($tasks) ?></dd></div><div><dt>Vencidas</dt><dd><?= $overdue ?></dd></div><div><dt>En verificación</dt><dd><?= $verification ?></dd></div></dl>
<?php if (!$tasks): ?><p class="muted">Sin acciones pendientes en tu ámbito.</p><?php else: ?>
<div class="table"><table><thead><tr><th scope="col">Acción</th><th scope="col">Responsable</th><th scope="col">Vence</th><th scope="col">Estado</th><th scope="col">Alerta</th></tr></thead><tbody>
<?php foreach ($tasks as $task): ?><tr><td><a href="index.php?id=<?= e($task['audit_id']) ?>#action-<?= e($task['id']) ?>"><?= e($task['description']) ?></a></td>
<td><?= e($task['name']) ?></td><td><?= e($task['due_date']) ?></td><td><?= e(str_replace('_',' ',$task['status'])) ?></td><td><?= e(inboxStatus($task,$today)) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<?php if (manage($user)): ?>
<section><h2>Alertas de indicadores de riesgo (KRI)</h2>
<?php $kriAlerts = breachedKriInbox($pdo); ?>
<p class="muted">Indicadores cuyo último valor documentado está fuera del umbral. Las mediciones se conservan en Riesgos y cumplimiento.</p>
<?php if (!$kriAlerts): ?><p class="muted">Sin umbrales excedidos en la última medición.</p><?php else: ?>
<dl class="metrics"><div><dt>Indicadores fuera de umbral</dt><dd><?= count($kriAlerts) ?></dd></div></dl>
<div class="table"><table><thead><tr><th scope="col">Indicador</th><th scope="col">Riesgo</th><th scope="col">Valor</th><th scope="col">Umbral</th><th scope="col">Fecha</th></tr></thead><tbody>
<?php foreach ($kriAlerts as $alert): ?>
<tr><td><a href="index.php?view=grc#kri-<?= e($alert['id']) ?>"><?= e($alert['title']) ?></a></td>
<td><?= e($alert['risk_title']) ?></td><td><?= e($alert['observed_value']) ?> <?= e($alert['unit']) ?></td>
<td><?= e($alert['direction']==='maximo' ? 'Máximo' : 'Mínimo') ?> <?= e($alert['threshold']) ?></td>
<td><?= e($alert['observed_on']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<section><h2>Revisión de riesgos aceptados</h2>
<?php $acceptedDue = riskAcceptanceDue($pdo,$today); ?>
<p class="muted">Incluye riesgos aceptados vencidos o próximos a revisión en 30 días, incluso si la auditoría está cerrada.</p>
<?php if (!$acceptedDue): ?><p class="muted">Sin aceptaciones que revisar en los próximos 30 días.</p><?php else: ?>
<ul><?php foreach ($acceptedDue as $accepted): ?>
<li><a href="index.php?id=<?= e($accepted['audit_id']) ?>#acceptance-<?= e($accepted['id']) ?>"><?= e($accepted['description']) ?></a>
· <?= e($accepted['audit_title']) ?> · <?= e($accepted['review_on']) ?> · <?= $accepted['review_on'] < $today->format('Y-m-d') ? 'Vencida' : 'Próxima revisión' ?></li>
<?php endforeach; ?></ul><?php endif; ?></section>
<section><h2>Revisiones de riesgo y cumplimiento</h2>
<?php $reviews = query($pdo,"SELECT title,review_on,'Riesgo' AS kind FROM grc_risks WHERE review_on<=?
    UNION ALL SELECT title,review_on,'Obligación' AS kind FROM grc_obligations WHERE review_on<=? ORDER BY review_on,title",
    array_fill(0,2,$today->modify('+7 days')->format('Y-m-d')))->fetchAll(); ?>
<?php if (!$reviews): ?><p class="muted">Sin revisiones vencidas o próximas.</p><?php else: ?>
<ul><?php foreach ($reviews as $review): ?><li><a href="index.php?view=grc"><?= e($review['title']) ?></a> · <?= e($review['kind']) ?> · <?= e($review['review_on']) ?></li><?php endforeach; ?></ul>
<?php endif; ?></section><?php endif; ?>
