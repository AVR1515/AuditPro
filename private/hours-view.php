<?php
$schedule = query($pdo,'SELECT * FROM audit_schedule WHERE audit_id=?',[$id])->fetch();
$entries = query($pdo,'SELECT l.*,u.name FROM audit_time l JOIN users u ON u.id=l.user_id WHERE l.audit_id=? ORDER BY l.worked_on DESC,l.id DESC',[$id])->fetchAll();
?>
<section><h2>Calendario y horas</h2>
<?php if ($schedule): ?><p>Previsto del <?= e($schedule['start_on']) ?> al <?= e($schedule['end_on']) ?> · <?= e(displayHours((int) $schedule['planned_minutes'])) ?></p>
<?php else: ?><p class="muted">Sin programación registrada.</p><?php endif; ?>
<p>Tiempo de campo registrado · <?= e(displayHours((int) array_sum(array_column($entries,'minutes')))) ?></p>
<?php if ($manager && !in_array($state,['emitida','cerrada'],true)): ?>
<details><summary>Programar o reprogramar</summary><?php form('schedule',$keys); ?>
<label>Inicio<input name="start" type="date" value="<?= e($schedule['start_on'] ?? null) ?>" required></label>
<label>Fin<input name="end" type="date" value="<?= e($schedule['end_on'] ?? null) ?>" required></label>
<label>Horas previstas<input name="hours" type="number" min="1" max="10000" value="<?= $schedule ? (int) $schedule['planned_minutes']/60 : '' ?>" required></label>
<label>Justificación<input name="note" maxlength="150" required></label><button>Guardar programación</button></form></details><?php endif; ?>
<?php if ($auditor && $state === 'en_campo'): ?>
<details><summary>Registrar tiempo</summary><?php form('log_time',$keys); ?>
<label>Fecha<input name="worked" type="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
<label>Minutos<input name="minutes" type="number" min="1" max="1440" required></label>
<label>Actividad<input name="description" maxlength="180" required></label><button>Guardar tiempo</button></form></details><?php endif; ?>
<?php if ($entries): ?><div class="table"><table><thead><tr><th scope="col">Fecha</th><th scope="col">Auditor</th><th scope="col">Actividad</th><th scope="col">Tiempo</th></tr></thead><tbody>
<?php foreach ($entries as $entry): ?><tr><td><?= e($entry['worked_on']) ?></td><td><?= e($entry['name']) ?></td><td><?= e($entry['description']) ?></td><td><?= e(displayHours((int) $entry['minutes'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
