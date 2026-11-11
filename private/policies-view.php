<?php
$policyRows=compliancePolicyRows($pdo);
$policyLinks=policyObligationLinks($pdo);
$policyObligations=$pdo->query('SELECT id,framework,reference_code,title FROM grc_obligations ORDER BY framework,reference_code')->fetchAll();
$policyGroups=[];
foreach ($policyRows as $entry) $policyGroups[$entry['id']][]=$entry;
?>
<section><h2>Políticas internas y versiones</h2>
<p class="muted">Las versiones aprobadas son inmutables y requieren revisión por una cuenta distinta de la autora. Cada versión conserva las obligaciones vinculadas. Las versiones anteriores siguen disponibles.</p>
<details><summary>Crear política</summary><?php form('policy_create'); ?>
<label>Nombre<input name="title" maxlength="180" required></label>
<label>Responsable<input name="owner_name" maxlength="180" required></label>
<button>Crear política</button></form></details>
<?php if (!$policyGroups): ?><p class="muted">Aún no hay políticas registradas.</p><?php endif; ?>
<?php foreach ($policyGroups as $entries): $policy=$entries[0]; ?>
<article><h3><?= e($policy['title']) ?></h3><p class="muted">Responsable: <?= e($policy['owner_name']) ?></p>
<?php foreach ($entries as $version): if ($version['version_id']===null) continue; ?>
<details><summary>Versión <?= e($version['version_number']) ?> · <?= $version['approved_at']===null ? 'Pendiente' : 'Aprobada' ?> · Efectiva <?= e($version['effective_on']) ?></summary>
<?php copyBlock('Contenido',$version['policy_text']); ?>
<?php copyBlock('Motivo de versión',$version['rationale']); ?>
<p class="muted">Preparó <?= e($version['author_name']) ?> · <?= $version['approved_at']===null ? 'Sin aprobación' : 'Aprobó '.e($version['approver_name']).' · '.e($version['approved_at']) ?></p>
<h4>Obligaciones vinculadas</h4>
<?php foreach ($policyLinks[$version['version_id']] ?? [] as $linked): ?>
<p><?= e($linked['framework']) ?> / <?= e($linked['reference_code']) ?> · <?= e($linked['title']) ?></p>
<?php endforeach; ?>
<?php if ($version['approved_at']===null): ?>
<?php if ($policyObligations): ?>
<?php form('policy_link',['policy_id'=>$policy['id'],'version_id'=>$version['version_id']]); ?>
<label>Vincular obligación<select name="obligation_id"><?php foreach ($policyObligations as $ob): ?>
<option value="<?= e($ob['id']) ?>"><?= e($ob['framework']) ?> / <?= e($ob['reference_code']) ?> · <?= e($ob['title']) ?></option>
<?php endforeach; ?></select></label>
<button>Vincular obligación</button></form>
<?php endif; ?>
<?php if ($manager && (int)$version['version_author']!==(int)$user['id']): ?>
<?php form('policy_approve',['policy_id'=>$policy['id'],'version_id'=>$version['version_id']]); ?>
<button>Aprobar versión</button></form><?php else: ?>
<p class="muted">Pendiente de otra cuenta de supervisión para aprobar.</p><?php endif; ?>
<?php endif; ?>
</details>
<?php endforeach; ?>
<?php $pending=false; foreach ($entries as $entry) if ($entry['version_id']!==null && $entry['approved_at']===null) $pending=true; ?>
<?php if (!$pending): ?><details><summary>Crear nueva versión</summary><?php form('policy_version',['policy_id'=>$policy['id']]); ?>
<label>Texto de política<textarea name="policy_text" maxlength="30000" required></textarea></label>
<label>Justificación del cambio<textarea name="rationale" maxlength="12000" required></textarea></label>
<label>Fecha de entrada en vigor<input name="effective_on" type="date" required></label>
<button>Guardar borrador de versión</button></form></details><?php endif; ?>
</article><?php endforeach; ?>
</section>