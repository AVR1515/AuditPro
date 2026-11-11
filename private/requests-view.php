<?php
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
        'SELECT h.*,u.name AS author FROM evidence_request_responses h JOIN users u ON u.id=h.author_id WHERE h.request_id=? ORDER BY h.id',
        [$request['id']])->fetchAll();
?>
<article>
<h3><?= e($request['subject']) ?></h3>
<p class="muted">#<?= e($request['id']) ?> · <?= e($request['status']) ?> · Límite: <?= e($request['due_on']) ?> · Solicitó: <?= e($request['requester']) ?></p>
<?php copyBlock('Documentación requerida',$request['description']); ?>
<?php if ($history): ?><details><summary>Historial de respuestas y devoluciones (<?= count($history) ?>)</summary>
<?php foreach ($history as $entry): ?>
<p class="copy"><strong><?= $entry['is_return'] ? 'Devolución' : 'Respuesta' ?> · <?= e($entry['author']) ?> · <?= e($entry['created_at']) ?></strong> <?= e($entry['response_text']) ?></p>
<?php endforeach; ?></details><?php endif; ?>
<?php if ($auditor && in_array($state,['en_campo','en_revision'],true) && $request['status'] === 'abierta'): ?>
<?php form('request_answer',$requestKeys); ?>
<label>Respuesta y referencias de evidencia<textarea name="answer" maxlength="12000" required></textarea></label>
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
