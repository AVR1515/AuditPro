<?php
$acceptances=riskAcceptanceHistory($pdo,(int)$id);
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
