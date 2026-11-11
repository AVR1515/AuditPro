<?php
$acceptances=riskAcceptanceHistory($pdo,(int)$id);
$acceptanceReviews=riskAcceptanceReviews($pdo,(int)$id);
?>
<section><h2>Aceptaciones de riesgo residual</h2>
<p class="muted">La aceptación requiere justificación, controles compensatorios, fecha futura de revisión y aprobación independiente. Cerrar una acción por riesgo aceptado no acredita remediación verificada.</p>
<?php if (!$acceptances): ?><p class="muted">No hay aceptaciones de riesgo registradas.</p><?php endif; ?>
<?php foreach ($acceptances as $acceptance): ?>
<article id="acceptance-<?= e($acceptance['id']) ?>">
<h3>Solicitud #<?= e($acceptance['id']) ?> · Acción #<?= e($acceptance['action_id']) ?></h3>
<p><?= e($acceptance['status']) ?> · Revisión <?= e($acceptance['review_on']) ?></p>
<?php copyBlock('Acción',$acceptance['action_description']); ?>
<?php copyBlock('Justificación',$acceptance['rationale']); ?>
<?php copyBlock('Controles compensatorios',$acceptance['compensating_controls']); ?>
<?php if ($acceptance['status']==='aceptada'): ?>
<h4>Revisiones de la aceptación</h4>
<?php foreach ($acceptanceReviews[$acceptance['id']] ?? [] as $review): ?>
<p class="muted"><?= e($review['reviewed_at']) ?> · <?= e($review['reviewer_name']) ?>
 · Antes <?= e($review['previous_review_on']) ?> · Próxima <?= e($review['next_review_on']) ?></p>
<?php copyBlock('Conclusión de revalidación',$review['conclusion']); ?>
<?php endforeach; ?>
<?php if ((int)$acceptance['requested_by']!==(int)$user['id']): ?>
<details><summary>Revalidar riesgo aceptado</summary><?php form('risk_accept_refresh',$keys+['acceptance_id'=>$acceptance['id']]); ?>
<label>Próxima revisión<input type="date" name="next_review" min="<?= e(date('Y-m-d',strtotime('+1 day'))) ?>" required></label>
<label>Fundamento y evidencia<textarea name="conclusion" maxlength="12000" required></textarea></label>
<button>Guardar revalidación</button></form></details>
<?php else: ?><p class="muted">La revisión debe efectuarla otra cuenta de supervisión.</p><?php endif; ?>
<?php endif; ?>
<p class="muted">Solicitada por <?= e($acceptance['requester_name']) ?> · <?= e($acceptance['created_at']) ?></p>
<?php if ($acceptance['decider_name']!==null): ?>
<p class="muted">Decidida por <?= e($acceptance['decider_name']) ?> · <?= e($acceptance['decided_at']) ?></p>
<?php copyBlock('Fundamento',$acceptance['decision_note']); ?>
<?php elseif ((int)$acceptance['requested_by']!==(int)$user['id']): ?>
<?php form('risk_accept_decide',$keys+['acceptance_id'=>$acceptance['id']]); ?>
<label>Decisión<select name="decision"><option value="aceptada">Aceptar riesgo residual</option><option value="rechazada">Rechazar y mantener acción abierta</option></select></label>
<label>Fundamento<textarea name="decision_note" maxlength="12000" required></textarea></label>
<button>Registrar decisión</button></form>
<?php else: ?><p class="muted">Pendiente de otra cuenta de supervisión.</p><?php endif; ?>
</article><?php endforeach; ?></section>
