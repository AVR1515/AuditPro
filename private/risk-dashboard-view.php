<?php
$dash=riskDashboard($pdo,(int)date('Y'));
?>
<section><h2>Tablero ejecutivo de cobertura y riesgos</h2>
<p class="muted">Indicadores globales del universo auditable. Las brechas utilizan la fecha de cierre efectiva cuando existe y el año de auditoría como estimación para expedientes históricos sin fecha; no sustituyen la valoración profesional de cobertura.</p>
<dl class="metrics">
<div><dt>Unidades auditables</dt><dd><?= e($dash['units']) ?></dd></div>
<div><dt>Brechas de cobertura</dt><dd><?= e($dash['gaps']) ?></dd></div>
<div><dt>Brechas de riesgo alto/crítico</dt><dd><?= e($dash['high_gaps']) ?></dd></div>
<div><dt>Riesgos sobre apetito</dt><dd><?= e($dash['above_appetite']) ?></dd></div>
<div><dt>Revisiones de riesgo vencidas</dt><dd><?= e($dash['overdue_reviews']) ?></dd></div>
<div><dt>Alertas KRI vigentes</dt><dd><?= e($dash['kri_breaches']) ?></dd></div>
<div><dt>KRI sin medición reciente</dt><dd><?= e($dash['kri_stale']) ?></dd></div>
</dl>
<details><summary>Ver unidades y brechas por prioridad</summary>
<?php if (!$dash['coverage']): ?><p class="muted">No se han creado unidades auditables.</p><?php else: ?>
<div class="table"><table><thead><tr>
<th scope="col">Unidad</th><th scope="col">Riesgo actual</th><th scope="col">Último año auditado</th><th scope="col">Ciclo</th><th scope="col">Situación</th>
</tr></thead><tbody>
<?php foreach ($dash['coverage'] as $unit): ?>
<tr><td><?= e($unit['name']) ?></td><td><?= e($unit['rating'] ?? 'Sin evaluación') ?></td>
<td><?= e($unit['last_audit_year'] ?? 'Sin registro') ?></td><td><?= e($unit['cycle_months']) ?> meses</td>
<td><?= e(coverageStatus($unit,(int)date('Y'))) ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
</details></section>
