<?php
$multiPlans = multiyearPlans($pdo);
$multiItems = multiyearItems($pdo);
$multiUnits = $pdo->query('SELECT u.id,u.name FROM auditable_units u WHERE EXISTS
    (SELECT 1 FROM risk_assessments r WHERE r.unit_id=u.id) ORDER BY u.name')->fetchAll();
$multiAuditors = $pdo->query("SELECT id,name FROM users WHERE role='auditor' AND active=1 ORDER BY name")->fetchAll();
?>
<section><h2>Planificación multianual de auditoría</h2>
<p class="muted">Horizonte de 2 a 5 años, prioridades basadas en la última evaluación de riesgo y aprobación independiente. Las propuestas no crean automáticamente expedientes ni sustituyen el plan anual aprobado.</p>
<details><summary>Crear horizonte multianual</summary>
<?php form('multiyear_create'); ?>
<label>Título<input name="title" required maxlength="180"></label>
<label>Primer año<input name="start_year" type="number" min="2000" max="2099" value="<?= e(date('Y')) ?>" required></label>
<label>Último año<input name="end_year" type="number" min="2001" max="2100" value="<?= e((int)date('Y')+2) ?>" required></label>
<label>Motivo y criterios<textarea name="rationale" maxlength="12000" required></textarea></label>
<button>Crear borrador</button></form></details>
<?php if (!$multiPlans): ?><p class="muted">No hay horizontes multianuales registrados.</p><?php endif; ?>
<?php foreach ($multiPlans as $plan): $rows = $multiItems[$plan['id']] ?? []; ?>
<article>
<h3><?= e($plan['title']) ?> · <?= e($plan['start_year']) ?>–<?= e($plan['end_year']) ?></h3>
<p class="muted">Creado por <?= e($plan['creator_name']) ?> · <?= e($plan['created_at']) ?>
<?php if ($plan['approver_name']): ?> · Aprobado por <?= e($plan['approver_name']) ?> · <?= e($plan['approved_at']) ?>
<?php else: ?> · Borrador pendiente<?php endif; ?></p>
<?php copyBlock('Criterios',$plan['rationale']); ?>
<?php if ($rows): ?>
<div class="table"><table><thead><tr>
<th scope="col">Año</th><th scope="col">Unidad</th><th scope="col">Riesgo fijado</th>
<th scope="col">Auditor</th><th scope="col">Horas estimadas</th><th scope="col">Capacidad anual</th>
</tr></thead><tbody>
<?php foreach ($rows as $item):
$capacity = $item['available_minutes'] === null ? null : (int) $item['available_minutes'];
?>
<tr><td><?= e($item['target_year']) ?></td><td><?= e($item['unit_name']) ?></td>
<td><?= e($item['risk_rating']) ?> · <?= e($item['risk_score']) ?>/25</td>
<td><?= e($item['auditor_name']) ?></td><td><?= e(displayHours((int)$item['estimated_minutes'])) ?></td>
<td><?= $capacity === null ? 'Sin configurar' : e(displayHours($capacity)) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php else: ?><p class="muted">Todavía no hay unidades programadas.</p><?php endif; ?>
<?php if ($plan['approved_by'] === null): ?>
<?php if ($multiUnits && $multiAuditors): ?>
<details><summary>Programar unidad evaluada</summary><?php form('multiyear_add',['plan_id'=>$plan['id']]); ?>
<label>Unidad<select name="unit_id" required><?php people($multiUnits); ?></select></label>
<label>Auditor<select name="auditor_id" required><?php people($multiAuditors); ?></select></label>
<label>Año objetivo<input name="target_year" type="number" min="<?= e($plan['start_year']) ?>" max="<?= e($plan['end_year']) ?>" value="<?= e($plan['start_year']) ?>" required></label>
<label>Horas estimadas<input name="hours" type="number" min="1" max="10000" required></label>
<button>Incorporar unidad</button></form></details>
<?php else: ?><p class="muted">Registra evaluaciones de riesgo y auditores activos antes de programar unidades.</p><?php endif; ?>
<?php if ($rows && (int)$plan['created_by'] !== (int)$user['id']): ?>
<?php form('multiyear_approve',['plan_id'=>$plan['id']]); ?><button>Aprobar horizonte multianual</button></form>
<?php elseif ($rows): ?><p class="muted">La aprobación requiere otra cuenta de supervisión.</p><?php endif; ?>
<?php endif; ?>
</article><?php endforeach; ?>
</section>
