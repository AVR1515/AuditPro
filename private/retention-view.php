<?php
$attachments=retentionRows($pdo);
$retentionMetrics=retentionReadiness($pdo,new DateTimeImmutable('today'));
?>
<section><h2>Retención de evidencias</h2>
<dl class="metrics">
<div><dt>Archivos registrados</dt><dd><?= e($retentionMetrics['total']) ?></dd></div>
<div><dt>Con bloqueo legal</dt><dd><?= e($retentionMetrics['legal_holds']) ?></dd></div>
<div><dt>Conservación indefinida</dt><dd><?= e($retentionMetrics['indefinite']) ?></dd></div>
<div><dt>Elegibles para purga</dt><dd><?= e($retentionMetrics['eligible']) ?></dd></div>
<div><dt>Plazo vence en 30 días</dt><dd><?= e($retentionMetrics['upcoming']) ?></dd></div>
<div><dt>Contenido purgado</dt><dd><?= e($retentionMetrics['purged']) ?></dd></div>
</dl>
<p class="muted">Las cifras consideran todos los archivos; el listado inferior muestra únicamente los 250 más recientes. Una cifra elegible no autoriza por sí sola el purgado: se necesita solicitud, decisión de otra cuenta administradora, revisión jurídica y respaldo verificado.</p>
<p class="muted">Administración puede fijar un plazo mínimo de un año y un bloqueo legal. Por defecto, los archivos se conservan indefinidamente. El purgado solo está permitido tras el vencimiento del plazo, sin bloqueo legal, con la auditoría cerrada y con aprobación independiente vigente. Se conserva la huella SHA-256 y la bitácora.</p>
<?php if (!$attachments): ?><p class="muted">No hay evidencias.</p><?php endif; ?>
<?php foreach ($attachments as $entry):
$minimum=(new DateTimeImmutable($entry['created_at']))->modify('+365 days')->format('Y-m-d');
$canPurge=retentionEligible($entry,new DateTimeImmutable('today'));
$expiredApproval=$entry['authorization_status']==='aprobada' &&
    $entry['authorization_decided_at']!==null &&
    new DateTimeImmutable($entry['authorization_decided_at']) < new DateTimeImmutable('-7 days');

?>
<article><h3><?= e($entry['filename']) ?> · #<?= e($entry['id']) ?></h3>
<p class="muted"><a href="index.php?id=<?= e($entry['audit_id']) ?>"><?= e($entry['audit_title']) ?></a>
 · <?= e($entry['audit_status']) ?> · <?= e($entry['bytes']) ?> bytes · Carga <?= e($entry['created_at']) ?></p>
<p>Conservar hasta: <?= e($entry['retention_until'] ?? 'Indefinidamente') ?> · Bloqueo legal:
<?= $entry['legal_hold'] ? 'Sí' : 'No' ?> · <?= $entry['purged_at'] ? 'Purgado el '.e($entry['purged_at']) : 'Disponible' ?></p>
<?php if ($entry['purged_at']===null): ?>
<details><summary>Cambiar retención</summary>
<?php form('retention_set',['attachment_id'=>$entry['id']]); ?>
<label>Fecha de retención<input type="date" name="retention_until" min="<?= e($minimum) ?>" value="<?= e($entry['retention_until'] ?? $minimum) ?>" required></label>
<label>Bloqueo legal<select name="legal_hold"><option value="1" <?= $entry['legal_hold'] ? 'selected' : '' ?>>Activado</option>
<option value="0" <?= !$entry['legal_hold'] ? 'selected' : '' ?>>Desactivado</option></select></label>
<button>Guardar política</button></form></details>
<?php if ($canPurge): ?>
<?php if (!$entry['authorization_id'] || in_array($entry['authorization_status'],['rechazada','cancelada'],true) || $expiredApproval): ?>
<details><summary>Solicitar purgado irreversible</summary>
<?php form('retention_request',['attachment_id'=>$entry['id']]); ?>
<p>La solicitud requiere la aprobación de otra cuenta administradora. Las autorizaciones caducan después de siete días. Antes de solicitar, verifica las obligaciones legales y el respaldo.</p>
<label>Justificación y referencia a aprobación jurídica<textarea name="rationale" maxlength="12000" required></textarea></label>
<button>Solicitar autorización</button></form></details>
<?php elseif ($entry['authorization_status']==='pendiente'): ?>
<p>Solicitud #<?= e($entry['authorization_id']) ?> pendiente de decisión independiente.</p>
<?php if ((int)$entry['authorization_requester']!==(int)$user['id']): ?>
<details><summary>Decidir solicitud de purgado</summary>
<?php form('retention_decide',['attachment_id'=>$entry['id'],'authorization_id'=>$entry['authorization_id']]); ?>
<label>Decisión<select name="decision"><option value="rechazada">Rechazar</option><option value="aprobada">Aprobar purgado</option></select></label>
<label>Fundamento, respaldo y autorización legal<textarea name="decision_note" maxlength="12000" required></textarea></label>
<button>Registrar decisión</button></form></details>
<?php endif; ?>
<?php elseif ($entry['authorization_status']==='aprobada'): ?>
<p>Autorización #<?= e($entry['authorization_id']) ?> aprobada por otra cuenta. Válida durante siete días desde la decisión.</p>
<details><summary>Purgar contenido vencido</summary><?php form('retention_purge',['attachment_id'=>$entry['id']]); ?>
<p>Se eliminará permanentemente el contenido de este archivo. Se conservarán su huella y la anotación del purgado. No se puede deshacer.</p>
<label>Escribe PURGAR<input name="confirmation" autocomplete="off" pattern="PURGAR" required></label>
<button>Purgar contenido definitivamente</button></form></details>
<?php endif; ?>
<?php endif; ?><?php endif; ?>
</article><?php endforeach; ?></section>
