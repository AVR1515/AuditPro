<?php
$units = $pdo->query('SELECT * FROM auditable_units ORDER BY name')->fetchAll();
$evaluations = assessments($pdo);
?>
<section><h2>Universo auditable</h2>
<p class="muted">Registra las unidades y conserva una evaluación nueva cada vez que cambie el riesgo.</p>
<div class="table"><table><thead><tr><th scope="col">Unidad</th><th scope="col">Proceso</th><th scope="col">Responsable</th><th scope="col">Ciclo</th></tr></thead><tbody>
<?php foreach ($units as $unit): ?><tr><td><?= e($unit['name']) ?></td><td><?= e($unit['process_name']) ?></td><td><?= e($unit['accountable']) ?></td><td><?= e($unit['cycle_months']) ?> meses</td></tr><?php endforeach; ?>
</tbody></table></div></section>
<div class="grid"><section><h2>Nueva unidad</h2><?php form('unit'); ?>
<label>Nombre<input name="name" required maxlength="180"></label>
<label>Proceso<input name="process" required maxlength="180"></label>
<label>Responsable<input name="accountable" required maxlength="180"></label>
<label>Ciclo en meses<input type="number" name="cycle" min="1" max="120" value="12" required></label>
<button>Registrar unidad</button></form></section>
<?php if ($units): ?><section><h2>Nueva evaluación</h2>
<p>Probabilidad × impacto. Bajo 1–5, medio 6–11, alto 12–19, crítico 20–25.</p>
<?php form('assess'); ?>
<label>Unidad<select name="unit_id"><?php people($units); ?></select></label>
<label>Probabilidad<input type="number" name="likelihood" min="1" max="5" required></label>
<label>Impacto<input type="number" name="impact" min="1" max="5" required></label>
<label>Justificación<textarea name="rationale" maxlength="12000" required></textarea></label>
<button>Guardar evaluación</button></form></section><?php endif; ?></div>
<section><h2>Historial de evaluaciones</h2>
<?php foreach ($evaluations as $item): ?><article><h3><?= e($item['name']) ?> · <?= e($item['score']) ?> / 25</h3>
<p><?= e($item['rating']) ?> · <?= e($item['methodology']) ?> · <?= e($item['likelihood']) ?> × <?= e($item['impact']) ?></p>
<p class="muted"><?= e($item['created_at']) ?> · <?= e($item['assessor']) ?></p>
<?php copyBlock('Justificación',$item['rationale']); ?></article><?php endforeach; ?></section>
