<?php
$planned = (int) array_sum(array_column($items,'planned'));
$worked = (int) array_sum(array_column($items,'worked'));
$findings = planSeverity($pdo,$user,$filters);
$aging = planAging($pdo,$user,$filters,new DateTimeImmutable('today'));
?>
<details class="plan-metrics"><summary>Ver indicadores del conjunto filtrado</summary>
<div class="grid"><div><h3>Horas</h3>
<p>Previstas <?= e(displayHours($planned)) ?> · Registradas <?= e(displayHours($worked)) ?></p>
<p class="muted">La previsión suma la programación vigente de los expedientes filtrados.</p></div>
<div><h3>Hallazgos por severidad</h3><ul><?php foreach (array_reverse(LEVELS) as $severity): ?>
<li><?= e(ucfirst($severity)) ?> · <?= e($findings[$severity] ?? 0) ?></li><?php endforeach; ?></ul></div>
<div><h3>Acciones abiertas de informes emitidos</h3><ul><?php foreach (['en_plazo'=>'En plazo','hasta_30'=>'1–30 días vencidas','hasta_60'=>'31–60 días vencidas','mas_60'=>'Más de 60 días vencidas'] as $key=>$label): ?>
<li><?= e($label) ?> · <?= e($aging[$key] ?? 0) ?></li><?php endforeach; ?></ul></div></div>
</details>
