<?php
$scopedUnits=scopedUnitOverview($pdo,$user);
$scopedRisks=scopedRiskOverview($pdo,$user);
$scopedIndicators=scopedRiskIndicators($pdo,$user);
?>
<section><h2>Mis unidades y riesgos</h2>
<p class="muted">Solo se muestran unidades y riesgos concedidos a tu cuenta. Puedes revisar el riesgo residual y registrar mediciones de indicadores existentes dentro de tus unidades. Crear o archivar indicadores, gestionar controles y consultar datos globales continúa reservado.</p>
<dl class="metrics"><div><dt>Unidades autorizadas</dt><dd><?= count($scopedUnits) ?></dd></div>
<div><dt>Riesgos visibles</dt><dd><?= count($scopedRisks) ?></dd></div>
<div><dt>Sobre el apetito</dt><dd><?= count(array_filter($scopedRisks,static fn(array $r): bool => (int)$r['residual']>(int)$r['appetite'])) ?></dd></div></dl>
<?php if (!$scopedUnits): ?><p class="muted">Administración todavía no te ha concedido unidades.</p><?php else: ?>
<div class="table"><table><thead><tr><th scope="col">Unidad</th><th scope="col">Proceso</th><th scope="col">Responsable</th><th scope="col">Ciclo</th></tr></thead><tbody>
<?php foreach ($scopedUnits as $unit): ?>
<tr><td><?= e($unit['name']) ?></td><td><?= e($unit['process_name']) ?></td><td><?= e($unit['accountable']) ?></td><td><?= e($unit['cycle_months']) ?> meses</td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
<h3>Riesgos de mis unidades</h3>
<?php if (!$scopedRisks): ?><p class="muted">Sin riesgos registrados dentro de las unidades autorizadas.</p><?php else: ?>
<div class="table"><table><thead><tr><th scope="col">Riesgo</th><th scope="col">Unidad</th><th scope="col">Residual</th><th scope="col">Apetito</th><th scope="col">Revisión</th></tr></thead><tbody>
<?php foreach ($scopedRisks as $risk): ?>
<tr><td><?= e($risk['title']) ?></td><td><?= e($risk['unit_name']) ?></td>
<td><?= e($risk['residual']) ?><?= (int)$risk['residual']>(int)$risk['appetite'] ? ' · Sobre apetito' : '' ?></td>
<td><?= e($risk['appetite']) ?></td><td><?= e($risk['review_on']) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php foreach ($scopedRisks as $risk): ?>
<details id="risk-<?= e($risk['id']) ?>"><summary><?= e($risk['title']) ?> — revisión y mediciones</summary>
<p class="muted">Unidad: <?= e($risk['unit_name']) ?>. Próxima revisión actual: <?= e($risk['review_on']) ?>.</p>
<?php form('risk_review',['risk_id'=>$risk['id']]); ?>
<label>Nuevo riesgo residual<input type="number" name="residual" min="1" max="25" value="<?= e($risk['residual']) ?>" required></label>
<label>Fecha de evaluación<input type="date" name="reviewed_on" max="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>" required></label>
<label>Próxima revisión<input type="date" name="next_review_on" min="<?= e(date('Y-m-d',strtotime('+1 day'))) ?>" required></label>
<label>Justificación y evidencia<textarea name="note" maxlength="12000" required></textarea></label>
<button>Registrar revisión</button></form>
<?php foreach ($scopedIndicators[$risk['id']] ?? [] as $indicator): ?>
<article id="kri-<?= e($indicator['id']) ?>">
<h4><?= e($indicator['title']) ?></h4>
<p>Umbral <?= e($indicator['direction']==='maximo' ? 'máximo' : 'mínimo') ?>: <?= e($indicator['threshold']) ?> <?= e($indicator['unit']) ?>.
<?php if ($indicator['observed_value']===null): ?>Sin mediciones
<?php else: ?>Último valor <?= e($indicator['observed_value']) ?> <?= e($indicator['unit']) ?> · <?= e($indicator['observed_on']) ?> ·
<?= kriBreached($indicator) ? 'Fuera del umbral' : 'Dentro del umbral' ?><?php endif; ?></p>
<?php form('kri_measure',['risk_id'=>$risk['id'],'indicator_id'=>$indicator['id']]); ?>
<label>Valor medido<input name="value" type="number" min="0" step="0.001" required></label>
<label>Fecha de medición<input name="observed_on" type="date" max="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>" required></label>
<label>Referencia de evidencia<textarea name="evidence" maxlength="12000" required></textarea></label>
<button>Guardar medición</button></form></article>
<?php endforeach; ?>
</details>
<?php endforeach; ?>
<?php endif; ?>
</section>