<?php
$scopedUnits=scopedUnitOverview($pdo,$user);
$scopedRisks=scopedRiskOverview($pdo,$user);
?>
<section><h2>Mis unidades y riesgos</h2>
<p class="muted">Solo se muestran unidades concedidas a tu cuenta de supervisión y sus riesgos. Los controles, obligaciones, pruebas y paneles globales permanecen reservados hasta disponer de filtros equivalentes.</p>
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
<?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>