<?php
$gateEntries=pilotGateHistory($pdo,$user);
?>
<section><h2>Evidencias de aceptación del piloto</h2>
<p class="muted">Las declaraciones son inmutables e identifican a quien las registra y el commit de AuditPro que fue evaluado. No acreditan por sí solas autorizaciones jurídicas, seguridad externa ni el despliegue real. Una valoración «cumple» debe acompañarse de evidencia revisable; una valoración «no cumple» conserva el incidente.</p>
<?php foreach (PILOT_GATES as $gate=>$roles): if (!canSubmitPilotGate($user,$gate)) continue; ?>
<details><summary><?= e(PILOT_GATE_NAMES[$gate]) ?> · Registrar evaluación</summary>
<?php form('pilot_signoff',['gate_key'=>$gate]); ?>
<label>Resultado de la comprobación<select name="result"><option value="no_cumple">No cumple / pendiente de corrección</option><option value="cumple">Cumple según la evidencia indicada</option></select></label>
<label>SHA completo de la versión evaluada<input name="version_sha" minlength="40" maxlength="40" pattern="[a-fA-F0-9]{40}" required autocomplete="off"></label>
<label>Referencia al acta, caso o evidencia interna<input name="evidence_reference" maxlength="2000" required></label>
<label>Descripción, participantes, resultados y limitaciones<textarea name="notes" maxlength="12000" required></textarea></label>
<button>Registrar declaración inmutable</button></form>
</details>
<?php endforeach; ?>
<h3>Historial de declaraciones</h3>
<?php if (!$gateEntries): ?><p class="muted">Todavía no existen declaraciones de este usuario<?= $user['role']==='admin' ? ' ni del resto de la organización' : '' ?>.</p>
<?php else: ?><div class="table"><table><thead><tr><th scope="col">Control</th><th scope="col">Declarado por</th><th scope="col">Resultado</th><th scope="col">Versión</th><th scope="col">Evidencia</th><th scope="col">Fecha</th></tr></thead><tbody>
<?php foreach ($gateEntries as $entry): ?><tr>
<td><?= e(PILOT_GATE_NAMES[$entry['gate_key']] ?? $entry['gate_key']) ?></td>
<td><?= e($entry['reviewer_name']) ?> · <?= e($entry['reviewer_role']) ?></td>
<td><?= $entry['result']==='cumple'?'Declarado conforme':'Declarado no conforme' ?></td>
<td><code><?= e(substr($entry['version_sha'],0,12)) ?></code></td>
<td><?= e($entry['evidence_reference']) ?><details><summary>Notas</summary><p class="copy"><?= e($entry['notes']) ?></p></details></td>
<td><?= e($entry['reviewed_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
</section>
