<?php
$riskAccess = canRisk($user);
$complianceAccess = canCompliance($user);
$units = $riskAccess ? $pdo->query('SELECT id,name FROM auditable_units ORDER BY name')->fetchAll() : [];
$risks = $riskAccess ? $pdo->query('SELECT r.*,u.name AS unit FROM grc_risks r JOIN auditable_units u ON u.id=r.unit_id ORDER BY r.review_on,r.id')->fetchAll() : [];
$riskReviews = $riskAccess ? riskReviewHistory($pdo) : [];
$riskKris = $riskAccess ? kriIndicators($pdo) : [];
$controls = $pdo->query('SELECT * FROM grc_controls ORDER BY title,id')->fetchAll();
$obligations = $complianceAccess ? $pdo->query('SELECT * FROM grc_obligations ORDER BY review_on,id')->fetchAll() : [];
$attestations = $complianceAccess ? obligationAttestations($pdo) : [];
$exceptions = $complianceAccess ? complianceExceptions($pdo) : [];
$eligibleTests = $manager ? $pdo->query("SELECT t.id,CONCAT(d.title,' / ',t.title) AS name FROM tests t JOIN audits d ON d.id=t.audit_id WHERE d.status IN ('en_campo','en_revision') ORDER BY d.title,t.id")->fetchAll() : [];
$mappings = grcMappings($pdo,$user);
?>
<?php if ($riskAccess): ?><section><h2>Registro de riesgos</h2>
<?php foreach ($risks as $risk): ?>
<article><h3><?= e($risk['title']) ?></h3><p><?= e($risk['unit']) ?> · <?= e($risk['owner_name']) ?> · Revisión <?= e($risk['review_on']) ?></p>
<p>Inherente <?= e($risk['inherent']) ?> · Residual <?= e($risk['residual']) ?> · Apetito <?= e($risk['appetite']) ?>
<?= $risk['residual'] > $risk['appetite'] ? ' · Sobre el apetito' : '' ?></p>
<?php copyBlock('Tratamiento',$risk['treatment']); ?>
<?php $history = $riskReviews[$risk['id']] ?? []; ?>
<?php if ($history): ?><details><summary>Historial de revisiones (<?= count($history) ?>)</summary>
<?php foreach ($history as $review): ?>
<p class="copy"><?= e($review['reviewed_on']) ?> · <?= e($review['reviewer_name']) ?> · Residual <?= e($review['previous_residual']) ?> → <?= e($review['residual']) ?>/25 · Próxima <?= e($review['next_review_on']) ?> · <?= e($review['review_note']) ?></p>
<?php endforeach; ?></details><?php endif; ?>
<details><summary>Registrar revisión periódica</summary><?php form('risk_review',['risk_id'=>$risk['id']]); ?>
<label>Riesgo residual<input name="residual" type="number" min="1" max="25" value="<?= e($risk['residual']) ?>" required></label>
<label>Fecha revisada<input name="reviewed_on" type="date" max="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>" required></label>
<label>Próxima revisión<input name="next_review_on" type="date" min="<?= e(date('Y-m-d',strtotime('+1 day'))) ?>" required></label>
<label>Conclusión y evidencia<textarea name="note" maxlength="12000" required></textarea></label>
<button>Guardar revisión</button></form></details>
<?php foreach ($riskKris[$risk['id']] ?? [] as $kri): ?>
<article id="kri-<?= e($kri['id']) ?>">
<h4>KRI: <?= e($kri['title']) ?></h4>
<p>Umbral <?= $kri['direction']==='maximo' ? 'máximo' : 'mínimo' ?> <?= e($kri['threshold']) ?> <?= e($kri['unit']) ?>.
<?php if ($kri['observed_value']===null): ?> Sin mediciones.
<?php else: ?> Último valor <?= e($kri['observed_value']) ?> <?= e($kri['unit']) ?> · <?= e($kri['observed_on']) ?> ·
<?= kriBreached($kri) ? 'Umbral excedido' : 'Dentro del umbral' ?><?= kriStale($kri,new DateTimeImmutable('today')) ? ' · Medición desactualizada' : '' ?><?php endif; ?></p>
<details><summary>Registrar medición</summary><?php form('kri_measure',['risk_id'=>$risk['id'],'indicator_id'=>$kri['id']]); ?>
<label>Valor<input name="value" type="number" min="0" step="0.001" required></label>
<label>Fecha<input name="observed_on" type="date" max="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>" required></label>
<label>Referencia de evidencia<textarea name="evidence" maxlength="12000" required></textarea></label>
<button>Guardar medición</button></form></details>
<details><summary>Historial de mediciones</summary>
<?php foreach (kriObservations($pdo,(int)$kri['id']) as $measurement): ?>
<p class="copy"><?= e($measurement['observed_on']) ?> · <?= e($measurement['observed_value']) ?> <?= e($kri['unit']) ?> · <?= e($measurement['recorder_name']) ?> · <?= e($measurement['evidence_reference']) ?></p>
<?php endforeach; ?></details>
<?php if ($manager): ?><?php form('kri_archive',['risk_id'=>$risk['id'],'indicator_id'=>$kri['id']],true); ?><button>Archivar indicador</button></form><?php endif; ?>
</article><?php endforeach; ?>
<details><summary>Crear indicador KRI</summary><?php form('kri_create',['risk_id'=>$risk['id']]); ?>
<label>Nombre<input name="title" maxlength="180" required></label>
<label>Unidad de medida<input name="unit" maxlength="80" required></label>
<label>Condición de alerta<select name="direction"><option value="maximo">Superior al máximo</option><option value="minimo">Inferior al mínimo</option></select></label>
<label>Umbral<input name="threshold" type="number" min="0" step="0.001" required></label>
<label>Frecuencia máxima de medición (días)<input name="review_interval_days" type="number" min="1" max="365" value="90" required></label>
<button>Crear indicador</button></form></details>
</article><?php endforeach; ?>
<?php if ($units): ?><details><summary>Registrar riesgo</summary><?php form('grc_risk'); ?>
<label>Unidad<select name="unit"><?php people($units); ?></select></label>
<label>Riesgo<input name="title" maxlength="180" required></label><label>Responsable<input name="owner_name" maxlength="180" required></label>
<?php foreach (['inherent'=>'Inherente','residual'=>'Residual','appetite'=>'Apetito'] as $name=>$label): ?>
<label><?= e($label) ?><input type="number" name="<?= e($name) ?>" min="1" max="25" required></label><?php endforeach; ?>
<label>Revisar el<input type="date" name="review" required></label><label>Tratamiento<textarea name="treatment" maxlength="12000" required></textarea></label>
<button>Guardar riesgo</button></form></details><?php else: ?><p>Registra una unidad auditable para asociar riesgos.</p><?php endif; ?></section>
<?php endif; ?>
<?php if ($complianceAccess): ?><section><h2>Obligaciones</h2>
<p><a href="index.php?view=import">Importar obligaciones desde CSV</a></p>
<?php foreach ($obligations as $obligation): ?><article><h3><?= e($obligation['title']) ?></h3>
<p><?= e($obligation['framework']) ?> · <?= e($obligation['reference_code']) ?> · <?= e($obligation['owner_name']) ?> · Revisión <?= e($obligation['review_on']) ?></p>
<?php $history = $attestations[$obligation['id']] ?? []; ?>
<?php $reviewDue = $history ? $history[0]['next_review_on'] : $obligation['review_on']; ?>
<p><strong>Próxima revisión efectiva:</strong> <?= e($reviewDue) ?> · <?= $reviewDue < date('Y-m-d') ? 'Vencida' : ($reviewDue <= date('Y-m-d',strtotime('+30 days')) ? 'Próxima a vencer' : 'En plazo') ?></p>
<?php if ($history): ?><p><strong>Última atestación:</strong> <?= e(str_replace('_',' ',$history[0]['result'])) ?> · <?= e($history[0]['reviewed_on']) ?> · Próxima revisión <?= e($history[0]['next_review_on']) ?></p>
<details><summary>Historial de atestaciones (<?= count($history) ?>)</summary>
<?php foreach ($history as $entry): ?><p class="copy"><strong><?= e($entry['author_name']) ?> · <?= e($entry['reviewed_on']) ?> · <?= e($entry['result']) ?></strong>
<?= e($entry['rationale']) ?> · Evidencia: <?= e($entry['evidence_reference']) ?> · Próxima: <?= e($entry['next_review_on']) ?></p><?php endforeach; ?>
</details><?php endif; ?>
<?php $exceptionHistory = $exceptions[$obligation['id']] ?? []; ?>
<?php if ($exceptionHistory): ?><details><summary>Excepciones registradas (<?= count($exceptionHistory) ?>)</summary>
<?php foreach ($exceptionHistory as $exception): ?>
<article id="exception-<?= e($exception['id']) ?>">
<h4><?= e($exception['title']) ?> · <?= e($exception['status']) ?></h4>
<p>Solicitada por <?= e($exception['requester_name']) ?> · Expira <?= e($exception['expires_on']) ?>
<?php if ($exception['status']==='aprobada' && $exception['expires_on']<date('Y-m-d')): ?> · Vencida<?php endif; ?></p>
<?php copyBlock('Motivo',$exception['rationale']); ?>
<?php copyBlock('Controles mitigantes',$exception['mitigating_controls']); ?>
<?php if ($exception['decider_name']): ?>
<p class="muted">Revisada por <?= e($exception['decider_name']) ?> · <?= e($exception['decided_at']) ?></p>
<?php copyBlock('Decisión',$exception['decision_note']); ?>
<?php endif; ?>
<?php if ($exception['closer_name']): ?>
<p class="muted">Cerrada por <?= e($exception['closer_name']) ?> · <?= e($exception['closed_at']) ?></p>
<?php copyBlock('Motivo de cierre',$exception['closure_note']); ?>
<?php endif; ?>
<?php if ($manager && $exception['status']==='pendiente' && (int)$exception['requested_by']!==(int)$user['id'] && $exception['expires_on']>date('Y-m-d')): ?>
<?php form('exception_decide',['obligation_id'=>$obligation['id'],'exception_id'=>$exception['id']]); ?>
<label>Decisión<select name="decision"><option value="aprobada">Aprobar</option><option value="rechazada">Rechazar</option></select></label>
<label>Fundamento<textarea name="decision_note" maxlength="12000" required></textarea></label>
<button>Registrar decisión independiente</button></form>
<?php endif; ?>
<?php if ($manager && $exception['status']==='aprobada'): ?>
<?php form('exception_close',['obligation_id'=>$obligation['id'],'exception_id'=>$exception['id']]); ?>
<label>Justificación de cierre<textarea name="closure_note" maxlength="12000" required></textarea></label>
<button>Cerrar excepción</button></form>
<?php endif; ?>
</article><?php endforeach; ?></details><?php endif; ?>
<details><summary>Solicitar excepción de cumplimiento</summary>
<?php form('exception_request',['obligation_id'=>$obligation['id']]); ?>
<label>Motivo o nombre<input name="title" maxlength="180" required></label>
<label>Justificación<textarea name="rationale" maxlength="12000" required></textarea></label>
<label>Controles mitigantes<textarea name="mitigating" maxlength="12000" required></textarea></label>
<label>Fecha de expiración<input name="expires" type="date" min="<?= e(date('Y-m-d',strtotime('+1 day'))) ?>" required></label>
<button>Registrar solicitud</button></form></details>
<details><summary>Nueva atestación</summary><?php form('obligation_attest',['obligation_id'=>$obligation['id']]); ?>
<label>Conclusión<select name="result"><option value="cumple">Cumple</option><option value="parcial">Cumple parcialmente</option><option value="no_cumple">No cumple</option><option value="no_aplica">No aplica</option></select></label>
<label>Referencia de evidencia<textarea name="evidence" maxlength="12000" required></textarea></label>
<label>Fundamento y excepciones<textarea name="rationale" maxlength="12000" required></textarea></label>
<label>Fecha evaluada<input type="date" name="reviewed" max="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>" required></label>
<label>Próxima revisión<input type="date" name="next_review" min="<?= e(date('Y-m-d',strtotime('+1 day'))) ?>" required></label>
<button>Registrar atestación</button></form></details>
</article><?php endforeach; ?>
<details><summary>Registrar obligación</summary><?php form('grc_obligation'); ?>
<label>Marco<input name="framework" maxlength="180" required></label><label>Referencia<input name="reference" maxlength="120" required></label>
<label>Título<input name="title" maxlength="180" required></label><label>Responsable<input name="owner_name" maxlength="180" required></label>
<label>Revisar el<input name="review" type="date" required></label><button>Guardar obligación</button></form></details></section>
<?php endif; ?>
<section><h2>Controles y trazabilidad</h2>
<?php foreach ($controls as $control): $mapping = $mappings[$control['id']] ?? []; ?>
<article><h3><?= e($control['title']) ?></h3><p><?= e($control['owner_name']) ?> · <?= e(str_replace('_',' ',$control['frequency'])) ?></p>
<?php copyBlock('Descripción',$control['description']); ?>
<?php foreach (['risks'=>'Riesgos','obligations'=>'Obligaciones','tests'=>'Pruebas'] as $kind=>$label):
if (($kind === 'risks' && !$riskAccess) || ($kind === 'obligations' && !$complianceAccess) || ($kind === 'tests' && !$manager)) continue;
?>
<h4><?= e($label) ?></h4>
<?php if (empty($mapping[$kind])): ?><p class="muted">Sin vínculos.</p><?php else: ?><ul><?php foreach ($mapping[$kind] as $item): ?>
<li><?php if ($kind === 'tests'): ?><a href="index.php?id=<?= e($item['audit_id']) ?>"><?= e($item['title']) ?></a> · <?= e($item['status']) ?>
<?php else: ?><?= e($item['title']) ?><?php endif; ?></li><?php endforeach; ?></ul><?php endif; endforeach; ?>
</article><?php endforeach; ?>
<details><summary>Registrar control</summary><?php form('grc_control'); ?>
<label>Control<input name="title" maxlength="180" required></label><label>Responsable<input name="owner_name" maxlength="180" required></label>
<label>Frecuencia<select name="frequency"><?php options(FREQUENCIES); ?></select></label>
<label>Descripción<textarea name="description" maxlength="12000" required></textarea></label><button>Guardar control</button></form></details>
<?php foreach ([
    'grc_link_risk'=>['riesgo',$risks],
    'grc_link_obligation'=>['obligación',$obligations],
    'grc_link_test'=>['prueba',$eligibleTests],
] as $op=>[$label,$parents]): if (!$controls || !$parents) continue; ?>
<details><summary>Vincular control con <?= e($label) ?></summary><?php form($op); ?>
<label>Control<select name="control"><?php foreach ($controls as $control): ?><option value="<?= e($control['id']) ?>"><?= e($control['title']) ?></option><?php endforeach; ?></select></label>
<label><?= e(ucfirst($label)) ?><select name="parent"><?php foreach ($parents as $parent): ?><option value="<?= e($parent['id']) ?>"><?= e(isset($parent['framework']) ? $parent['framework'] . ' / ' . $parent['reference_code'] . ' · ' . $parent['title'] : ($parent['name'] ?? $parent['title'])) ?></option><?php endforeach; ?></select></label>
<button>Vincular</button></form></details><?php endforeach; ?></section>
