<?php
$requestFiles = query($pdo,'SELECT b.id,b.filename FROM attachments b
    JOIN tests t ON t.id=b.test_id WHERE t.audit_id=? AND b.purged_at IS NULL ORDER BY b.id DESC',[$id])->fetchAll();
$requests = query($pdo,
    'SELECT r.*,u.name AS requester FROM evidence_requests r JOIN users u ON u.id=r.requested_by WHERE r.audit_id=? ORDER BY r.id DESC',
    [$id])->fetchAll();
?>
<section><h2>Solicitudes de evidencia</h2>
<p class="muted">Supervisión solicita documentación al auditor del expediente. Las respuestas y devoluciones conservan su historial.</p>
<?php if ($manager && in_array($state,['en_campo','en_revision'],true)): ?>
<details><summary>Nueva solicitud</summary>
<?php form('request_create',$keys); ?>
<label>Asunto<input name="subject" maxlength="180" required></label>
<label>Documentación solicitada<textarea name="description" maxlength="12000" required></textarea></label>
<label>Fecha límite<input name="due" type="date" min="<?= e(date('Y-m-d')) ?>" required></label>
<button>Enviar solicitud</button></form></details>
<?php endif; ?>
<?php if (!$requests): ?><p class="muted">Todavía no hay solicitudes para este expediente.</p><?php endif; ?>
<?php foreach ($requests as $request):
    $requestKeys = $keys + ['request_id'=>$request['id']];
    $history = query($pdo,
        'SELECT h.*,u.name AS author,b.id AS attachment_id,b.filename,b.purged_at FROM evidence_request_responses h JOIN users u ON u.id=h.author_id
        LEFT JOIN evidence_request_attachments l ON l.response_id=h.id
        LEFT JOIN attachments b ON b.id=l.attachment_id
        WHERE h.request_id=? ORDER BY h.id',
        [$request['id']])->fetchAll();
?>
<article id="request-<?= e($request['id']) ?>">
<h3><?= e($request['subject']) ?></h3>
<p class="muted">#<?= e($request['id']) ?> · <?= e($request['status']) ?> · Límite: <?= e($request['due_on']) ?> · Solicitó: <?= e($request['requester']) ?></p>
<?php copyBlock('Documentación requerida',$request['description']); ?>
<?php if ($history): ?><details><summary>Historial de respuestas y devoluciones (<?= count($history) ?>)</summary>
<?php foreach ($history as $entry): ?>
<p class="copy"><strong><?= $entry['is_return'] ? 'Devolución' : 'Respuesta' ?> · <?= e($entry['author']) ?> · <?= e($entry['created_at']) ?></strong> <?= e($entry['response_text']) ?>
<?php if ($entry['attachment_id']): ?> · Evidencia: <?php if ($entry['purged_at']): ?><?= e($entry['filename']) ?> (contenido purgado)
<?php else: ?><a href="index.php?download=<?= e($entry['attachment_id']) ?>"><?= e($entry['filename']) ?></a><?php endif; ?><?php endif; ?></p>
<?php endforeach; ?></details><?php endif; ?>
<?php if ($auditor && in_array($state,['en_campo','en_revision'],true) && $request['status'] === 'abierta'): ?>
<?php form('request_answer',$requestKeys); ?>
<label>Respuesta y referencias de evidencia<textarea name="answer" maxlength="12000" required></textarea></label>
<?php if ($requestFiles): ?><label>Evidencia adjunta existente (opcional)<select name="attachment_id"><option value="">Solo respuesta textual</option>
<?php foreach ($requestFiles as $file): ?><option value="<?= e($file['id']) ?>"><?= e($file['filename']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
<button>Enviar respuesta</button></form>
<?php endif; ?>
<?php if ($manager && $request['status'] === 'respondida'): ?>
<?php form('request_close',$requestKeys,true); ?><button>Cerrar solicitud</button></form>
<?php if (in_array($state,['en_campo','en_revision'],true)): ?>
<details><summary>Devolver para corrección</summary><?php form('request_reopen',$requestKeys); ?>
<label>Motivo de devolución<textarea name="reason" maxlength="500" required></textarea></label>
<button>Devolver respuesta</button></form></details>
<?php endif; ?>
<?php endif; ?>
</article>
<?php endforeach; ?></section>
