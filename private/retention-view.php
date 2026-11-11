<?php
$attachments=retentionRows($pdo);
?>
<section><h2>Retención de evidencias</h2>
<p class="muted">Administración puede fijar un plazo mínimo de un año y un bloqueo legal. Por defecto, los archivos se conservan indefinidamente. El purgado solo está permitido tras el vencimiento del plazo, sin bloqueo legal y con la auditoría cerrada. Se conserva la huella SHA-256 y la bitácora.</p>
<?php if (!$attachments): ?><p class="muted">No hay evidencias.</p><?php endif; ?>
<?php foreach ($attachments as $entry):
$minimum=(new DateTimeImmutable($entry['created_at']))->modify('+365 days')->format('Y-m-d');
$canPurge=$entry['purged_at']===null && !$entry['legal_hold'] && $entry['audit_status']==='cerrada' &&
    $entry['retention_until']!==null && $entry['retention_until']<date('Y-m-d') && $minimum<date('Y-m-d');
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
<details><summary>Purgar contenido vencido</summary><?php form('retention_purge',['attachment_id'=>$entry['id']]); ?>
<p>Se eliminará permanentemente el contenido de este archivo. Se conservarán su huella y la anotación del purgado. No se puede deshacer.</p>
<label>Escribe PURGAR<input name="confirmation" autocomplete="off" pattern="PURGAR" required></label>
<button>Purgar contenido definitivamente</button></form></details>
<?php endif; ?><?php endif; ?>
</article><?php endforeach; ?></section>
