<?php
$findings = query($pdo,'SELECT f.*,t.title AS test_title FROM findings f JOIN tests t ON t.id=f.test_id WHERE t.audit_id=? ORDER BY f.id',[$audit['id']])->fetchAll();
?>
<section class="report">
<p class="muted">Informe emitido · Expediente <?= e($audit['id']) ?></p>
<p><?= e($audit['entity']) ?> · <?= e($audit['year']) ?> · Riesgo <?= e($audit['risk']) ?></p>
<?php copyBlock('Objetivo y alcance',$audit['objective']); ?>
<h2>Informe final</h2><p class="copy"><?= e($audit['report']) ?></p>
<h2>Hallazgos</h2>
<?php if (!$findings): ?><p>El expediente se emitió sin hallazgos registrados.</p><?php endif; ?>
<?php foreach ($findings as $finding): ?>
<article><h3><?= e($finding['title']) ?></h3><p><?= e($finding['severity']) ?> · <?= e($finding['test_title']) ?></p>
<?php foreach (['criterion'=>'Criterio','condition_text'=>'Condición','cause'=>'Causa','effect'=>'Efecto','recommendation'=>'Recomendación'] as $key=>$label) copyBlock($label,$finding[$key]); ?>
</article><?php endforeach; ?>
<div class="bar no-print"><a href="index.php?id=<?= e($audit['id']) ?>">Volver al expediente</a><button type="button" id="print-report">Imprimir o guardar como PDF</button></div>
</section>
