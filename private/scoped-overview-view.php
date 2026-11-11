<?php
$scopedUnits=scopedUnitOverview($pdo,$user);
$scopedRisks=scopedRiskOverview($pdo,$user);
$scopedIndicators=scopedRiskIndicators($pdo,$user);
$scopedCoverage=scopedUnitCoverage($pdo,$user);
$scopedControls=scopedRiskControls($pdo,$user);
$scopedTests=scopedControlTests($pdo,$user);
$scopedObligations=scopedObligations($pdo,$user);
$scopedPlan=scopedAnnualPlanning($pdo,$user);
?>
<section><h2>Mis unidades y riesgos</h2>
<p class="muted">Solo se muestran unidades y riesgos concedidos a tu cuenta. Puedes revisar el riesgo residual y registrar mediciones de indicadores existentes dentro de tus unidades. Crear o archivar indicadores y gestionar vínculos globales continúa reservado. Se muestra la trazabilidad de controles con pruebas exclusivamente de expedientes autorizados.</p>
<dl class="metrics"><div><dt>Unidades autorizadas</dt><dd><?= count($scopedUnits) ?></dd></div>
<div><dt>Riesgos visibles</dt><dd><?= count($scopedRisks) ?></dd></div>
<div><dt>Sobre el apetito</dt><dd><?= count(array_filter($scopedRisks,static fn(array $r): bool => (int)$r['residual']>(int)$r['appetite'])) ?></dd></div></dl>
<?php if (!$scopedUnits): ?><p class="muted">Administración todavía no te ha concedido unidades.</p><?php else: ?>
<div class="table"><table><thead><tr><th scope="col">Unidad</th><th scope="col">Proceso</th><th scope="col">Responsable</th><th scope="col">Ciclo</th></tr></thead><tbody>
<?php foreach ($scopedUnits as $unit): ?>
<tr><td><?= e($unit['name']) ?></td><td><?= e($unit['process_name']) ?></td><td><?= e($unit['accountable']) ?></td><td><?= e($unit['cycle_months']) ?> meses</td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
<h3>Plan operativo de expedientes autorizados</h3>
<p class="muted">Vista de solo lectura de expedientes vinculados a tus unidades; los planes anuales globales y su aprobación permanecen reservados a supervisión general.</p>
<?php if (!$scopedPlan): ?><p class="muted">No hay expedientes vinculados a tus unidades.</p>
<?php else: ?><div class="table"><table><thead><tr><th scope="col">Expediente</th><th scope="col">Año</th><th scope="col">Estado</th><th scope="col">Auditor</th><th scope="col">Horas previstas</th><th scope="col">Horas realizadas</th></tr></thead><tbody>
<?php foreach ($scopedPlan as $work): ?><tr>
<td><a href="index.php?id=<?= e($work['id']) ?>"><?= e($work['title']) ?></a></td>
<td><?= e($work['year']) ?></td><td><?= e($work['status']) ?></td>
<td><?= e($work['auditor']) ?></td>
<td><?= e(displayHours((int)$work['planned_minutes'])) ?></td>
<td><?= e(displayHours((int)$work['worked_minutes'])) ?></td>
</tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<h3>Obligaciones relacionadas con mis riesgos</h3>
<p class="muted">Vista de solo lectura. Solo se incluyen obligaciones enlazadas a controles de riesgos pertenecientes a las unidades autorizadas; ninguna aprobación de cumplimiento se concede por este acceso.</p>
<?php if (!$scopedObligations): ?><p class="muted">No hay obligaciones vinculadas a controles de tus unidades.</p>
<?php else: ?><div class="table"><table><thead><tr><th scope="col">Obligación</th><th scope="col">Marco</th><th scope="col">Responsable</th><th scope="col">Próxima revisión</th></tr></thead><tbody>
<?php foreach ($scopedObligations as $obligation): ?><tr>
<td><?= e($obligation['title']) ?></td>
<td><?= e($obligation['framework']) ?> / <?= e($obligation['reference_code']) ?></td>
<td><?= e($obligation['owner_name']) ?></td>
<td><?= e($obligation['next_review']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<h3>Cobertura de auditorías por unidad</h3>
<p class="muted">Las auditorías solo se cuentan cuando están vinculadas a una evaluación de la unidad. El ciclo se estima por años y no sustituye las fechas efectivas de trabajo.</p>
<?php if ($scopedCoverage): ?>
<div class="table"><table><thead><tr><th scope="col">Unidad</th><th scope="col">Auditorías vinculadas</th><th scope="col">Último año</th><th scope="col">Cierre efectivo</th><th scope="col">Ciclo</th><th scope="col">Situación</th></tr></thead><tbody>
<?php foreach ($scopedCoverage as $coverage): ?>
<tr><td><?= e($coverage['name']) ?></td><td><?= e($coverage['audit_count']) ?></td>
<td><?= e($coverage['last_audit_year'] ?? 'Sin registro') ?></td><td><?= e($coverage['last_closed_on'] ?? 'Sin fecha') ?></td><td><?= e($coverage['cycle_months']) ?> meses</td>
<td><?= e(coverageStatus($coverage,(int)date('Y'))) ?></td></tr>
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
<h4>Controles asociados y pruebas accesibles</h4>
<?php if (empty($scopedControls[$risk['id']])): ?>
<p class="muted">Sin controles vinculados a este riesgo.</p>
<?php else: foreach ($scopedControls[$risk['id']] as $control): ?>
<article>
<h5><?= e($control['title']) ?></h5>
<p class="muted">Responsable: <?= e($control['owner_name']) ?> · <?= e(str_replace('_',' ',$control['frequency'])) ?></p>
<?php copyBlock('Descripción del control',$control['description']); ?>
<?php $linkedTests=$scopedTests[$risk['id']][$control['control_id']] ?? []; ?>
<?php if (!$linkedTests): ?><p class="muted">Sin pruebas accesibles asociadas.</p>
<?php else: ?><ul><?php foreach ($linkedTests as $linked): ?>
<li><a href="index.php?id=<?= e($linked['audit_id']) ?>"><?= e($linked['audit_title']) ?> · <?= e($linked['test_title']) ?></a>
· <?= e($linked['test_status']) ?></li>
<?php endforeach; ?></ul><?php endif; ?>
</article>
<?php endforeach; endif; ?>

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