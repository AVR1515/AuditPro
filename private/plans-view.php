<?php
$versions = $pdo->query('SELECT p.*,author.name AS author,approver.name AS approver FROM annual_plans p
    JOIN users author ON author.id=p.created_by LEFT JOIN users approver ON approver.id=p.approved_by
    ORDER BY p.plan_year DESC,p.revision DESC')->fetchAll();
$itemsByPlan = [];
foreach ($pdo->query("SELECT * FROM annual_plan_items ORDER BY FIELD(risk,'critico','alto','medio','bajo'),title") as $item) {
    $itemsByPlan[$item['plan_id']][] = $item;
}
?>
<?php require __DIR__ . '/capacity-view.php'; ?>
<?php require __DIR__ . '/multiyear-view.php'; ?>
<section><h2>Crear versión del plan anual</h2>
<p>La versión conserva los expedientes del año y sus datos al momento de crearla. Otra cuenta de supervisión debe aprobarla.</p>
<?php form('snapshot_plan'); ?>
<label>Año<input type="number" name="year" min="2000" max="2100" value="<?= e(date('Y')) ?>" required></label>
<label>Justificación de esta versión<textarea name="reason" maxlength="12000" required></textarea></label>
<button>Crear versión</button></form></section>
<section><h2>Versiones</h2>
<?php if (!$versions): ?><p class="muted">Aún no hay versiones.</p><?php endif; ?>
<?php $current = []; foreach ($versions as $version): $planId = $version['id']; ?>
<article><h3><?= e($version['plan_year']) ?> · Versión <?= e($version['revision']) ?></h3>
<p>Preparada por <?= e($version['author']) ?> · <?= e($version['created_at']) ?></p>
<?php if ($version['approver'] !== null): ?>
<p>Aprobada por <?= e($version['approver']) ?> · <?= e($version['approved_at']) ?><?= isset($current[$version['plan_year']]) ? '' : ' · Vigente' ?></p>
<?php $current[$version['plan_year']] = true; else: ?><p>Pendiente de aprobación</p><?php endif; ?>
<?php copyBlock('Justificación',$version['reason']); ?>
<details><summary>Ver expedientes incluidos</summary><div class="table"><table><thead><tr>
<th scope="col">Auditoría</th><th scope="col">Entidad</th><th scope="col">Riesgo</th><th scope="col">Auditor</th><th scope="col">Estado al preparar</th>
</tr></thead><tbody>
<?php foreach ($itemsByPlan[$planId] ?? [] as $item): ?><tr>
<td><a href="index.php?id=<?= e($item['audit_id']) ?>"><?= e($item['title']) ?></a></td><td><?= e($item['entity']) ?></td>
<td><?= e($item['risk']) ?></td><td><?= e($item['owner_name']) ?></td><td><?= e($item['original_status']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></details>
<?php if ($version['approved_by'] === null && (int) $version['created_by'] !== (int) $user['id']): form('approve_plan',['plan_id'=>$planId]); ?>
<button>Aprobar versión</button></form><?php endif; ?>
</article><?php endforeach; ?></section>
