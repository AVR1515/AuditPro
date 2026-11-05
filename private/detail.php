<?php
$id = $audit['id'];
$keys = ['audit_id'=>$id];
$state = $audit['status'];
$actions = query($pdo,'SELECT a.*,u.name FROM actions a JOIN users u ON u.id=a.owner_id JOIN findings f ON f.id=a.finding_id JOIN tests t ON t.id=f.test_id WHERE t.audit_id=?' .
    ($responsible ? " AND a.owner_id=? AND EXISTS (SELECT 1 FROM audits d WHERE d.id=t.audit_id AND d.status IN ('emitida','cerrada'))" : '') . ' ORDER BY a.due_date',
    $responsible ? [$id,$user['id']] : [$id])->fetchAll();
if (!$responsible):
$tests = query($pdo,'SELECT * FROM tests WHERE audit_id=? ORDER BY id',[$id])->fetchAll();
$findings = query($pdo,'SELECT f.*,t.title AS test_title FROM findings f JOIN tests t ON t.id=f.test_id WHERE t.audit_id=? ORDER BY f.id',[$id])->fetchAll();
$evaluation = query($pdo,'SELECT r.* FROM audit_assessment_links a JOIN risk_assessments r ON r.id=a.assessment_id WHERE a.audit_id=?',[$id])->fetch();
$testControls = [];
foreach (query($pdo,'SELECT m.test_id,c.title FROM grc_control_tests m JOIN grc_controls c ON c.id=m.control_id JOIN tests t ON t.id=m.test_id WHERE t.audit_id=? ORDER BY c.title',[$id]) as $control) {
    $testControls[$control['test_id']][] = $control['title'];
}
$next = $manager ? nextState($pdo,$audit) : null;
?>
<section>
<p><?= e($audit['entity']) ?> · <?= e($audit['year']) ?> · Riesgo <?= e($audit['risk']) ?> · <?= e(str_replace('_',' ',$state)) ?></p>
<?php if ($evaluation): ?><p>Evaluación vinculada · <?= e($evaluation['methodology']) ?> · <?= e($evaluation['score']) ?>/25 · <?= e($evaluation['created_at']) ?></p><?php endif; ?>
<?php copyBlock('Objetivo y alcance', $audit['objective']); ?>
<?php if ($audit['report'] !== null): ?><h2>Informe emitido</h2><p class="copy"><?= e($audit['report']) ?></p><p><a href="index.php?id=<?= e($id) ?>&amp;view=report">Abrir informe para impresión</a></p><?php endif; ?>
<?php if ($next): form('advance',$keys); ?>
<?php if ($next === 'emitida'): ?><label>Informe final<textarea name="report" required maxlength="30000"></textarea></label><?php endif; ?>
<button><?= e(['aprobada'=>'Aprobar auditoría','en_campo'=>'Iniciar trabajo de campo','en_revision'=>'Enviar a revisión','emitida'=>'Emitir informe','cerrada'=>'Cerrar auditoría'][$next]) ?></button></form>
<?php elseif ($manager && in_array($state,['en_campo','en_revision','emitida'],true)): ?><p class="muted">Completa las pruebas, la asignación de acciones o la verificación de cierres antes de avanzar.</p><?php endif; ?>
</section>
<?php require __DIR__ . '/hours-view.php'; ?>
<section><h2>Pruebas y papeles de trabajo</h2>
<?php if ($auditor && $state === 'en_campo'): ?><details><summary>Nueva prueba</summary><?php form('test',$keys); ?>
<label>Título<input name="title" required maxlength="180"></label><label>Procedimiento<textarea name="procedure" required maxlength="12000"></textarea></label><button>Crear prueba</button></form></details><?php endif; ?>
<?php foreach ($tests as $test): $testKeys = $keys + ['test_id'=>$test['id']]; ?>
<article><h3><?= e($test['title']) ?></h3><p class="muted"><?= e($test['status']) ?></p>
<?php copyBlock('Procedimiento',$test['procedure_text']); if ($test['result_text'] !== null) copyBlock('Resultado',$test['result_text']); ?>
<?php if (!empty($testControls[$test['id']])) copyBlock('Controles vinculados',implode(' · ',$testControls[$test['id']])); ?>
<?php listAttachments($pdo,'test',(int) $test['id']); ?>
<?php if ($auditor && $state === 'en_campo' && $test['status'] !== 'revisada'): ?>
<details><summary>Adjuntar evidencia de prueba</summary><?php form('attach_test',$testKeys,false,true); ?>
<label>Archivo PDF, PNG, JPEG o TXT de hasta 5 MiB<input type="file" name="attachment" accept=".pdf,.png,.jpg,.jpeg,.txt" required></label><button>Adjuntar</button></form></details>
<details><summary>Documentar resultado</summary><?php form('result',$testKeys); ?>
<label>Resultado y referencias<textarea name="result" required maxlength="12000"><?= e($test['result_text']) ?></textarea></label><button>Guardar resultado</button></form></details>
<?php endif; if ($manager && $state === 'en_campo' && $test['status'] === 'documentada'): ?>
<details><summary>Revisar prueba</summary><?php form('review',$testKeys); ?>
<label>Decisión<select name="decision"><option value="revisada">Aprobar</option><option value="pendiente">Devolver</option></select></label>
<label>Nota<input name="note" required maxlength="150"></label><button>Registrar revisión</button></form></details>
<?php endif; if ($auditor && $state === 'en_campo' && $test['status'] !== 'pendiente'): ?>
<details><summary>Registrar hallazgo</summary><?php form('finding',$testKeys); ?>
<label>Título<input name="title" required maxlength="180"></label><label>Severidad<select name="severity"><?php options(LEVELS); ?></select></label>
<?php foreach (['criterion'=>'Criterio','condition'=>'Condición','cause'=>'Causa','effect'=>'Efecto','recommendation'=>'Recomendación'] as $key=>$label): ?>
<label><?= e($label) ?><textarea name="<?= e($key) ?>" required maxlength="5000"></textarea></label><?php endforeach; ?>
<button>Guardar hallazgo</button></form></details><?php endif; ?></article>
<?php endforeach; ?></section>
<section><h2>Hallazgos</h2>
<?php if (!$findings): ?><p class="muted">Sin hallazgos registrados.</p><?php endif;
$owners = $manager && in_array($state,['en_campo','en_revision'],true) ? $pdo->query("SELECT id,name FROM users WHERE role='responsable' AND active=1 ORDER BY name")->fetchAll() : [];
foreach ($findings as $finding): ?>
<article><h3><?= e($finding['title']) ?></h3><p><?= e($finding['severity']) ?> · <?= e($finding['test_title']) ?></p>
<?php foreach (['criterion'=>'Criterio','condition_text'=>'Condición','cause'=>'Causa','effect'=>'Efecto','recommendation'=>'Recomendación'] as $key=>$label) copyBlock($label,$finding[$key]); ?>
<?php if ($owners): ?><details><summary>Asignar acción</summary><?php form('action',$keys + ['finding_id'=>$finding['id']]); ?>
<label>Responsable<select name="owner"><?php people($owners); ?></select></label><label>Vencimiento<input name="due" type="date" required></label>
<label>Acción y evidencia esperada<textarea name="description" required maxlength="12000"></textarea></label><button>Asignar</button></form></details>
<?php elseif ($manager && in_array($state,['en_campo','en_revision'],true)): ?><p class="muted">Hace falta un usuario con rol responsable para asignar una acción.</p><?php endif; ?>
</article><?php endforeach; ?></section><?php endif; ?>
<section><h2>Acciones correctivas</h2>
<?php if (!$actions): ?><p class="muted">Sin acciones disponibles.</p><?php endif;
foreach ($actions as $action): $actionKeys = $keys + ['action_id'=>$action['id']]; ?>
<article id="action-<?= e($action['id']) ?>"><?php copyBlock('Acción',$action['description']); ?>
<p><?= e($action['name']) ?> · Vence <?= e($action['due_date']) ?> · <?= e(str_replace('_',' ',$action['status'])) ?></p>
<?php if ($action['evidence'] !== null) copyBlock('Evidencia presentada',$action['evidence']); ?>
<?php if ($state === 'emitida' && $manager && $action['status'] !== 'cerrada'): ?>
<details><summary>Registrar prórroga</summary><?php form('extend',$actionKeys); ?>
<label>Nuevo vencimiento<input type="date" name="due" required></label>
<label>Justificación<input name="note" maxlength="150" required></label><button>Guardar prórroga</button></form></details>
<?php endif; ?>
<?php listAttachments($pdo,'action',(int) $action['id']); ?>
<?php if ($state === 'emitida' && $responsible && $action['status'] === 'abierta'): ?>
<details><summary>Adjuntar evidencia de acción</summary><?php form('attach_action',$actionKeys,false,true); ?>
<label>Archivo PDF, PNG, JPEG o TXT de hasta 5 MiB<input type="file" name="attachment" accept=".pdf,.png,.jpg,.jpeg,.txt" required></label><button>Adjuntar</button></form></details>
<?php form('evidence',$actionKeys); ?>
<label>Descripción y referencia de evidencia<textarea name="evidence" required maxlength="12000"></textarea></label><button>Enviar a verificación</button></form>
<?php elseif ($state === 'emitida' && $manager && $action['status'] === 'en_verificacion'): form('verify',$actionKeys); ?>
<label>Decisión<select name="decision"><option value="cerrada">Verificar y cerrar</option><option value="abierta">Devolver al responsable</option></select></label>
<label>Nota<input name="note" required maxlength="150"></label><button>Registrar decisión</button></form><?php endif; ?>
</article><?php endforeach; ?></section>
<?php if (!$responsible): ?>
<section><h2>Historial del expediente</h2>
<div class="table"><table><thead><tr><th scope="col">Fecha</th><th scope="col">Usuario</th><th scope="col">Evento</th><th scope="col">Detalle</th></tr></thead><tbody>
<?php foreach (query($pdo,'SELECT l.*,u.name FROM audit_log l JOIN users u ON u.id=l.actor_id WHERE audit_id=? ORDER BY l.id DESC',[$id]) as $event): ?>
<tr><td><?= e($event['occurred_at']) ?></td><td><?= e($event['name']) ?></td><td><?= e($event['event']) ?></td><td><?= e($event['detail']) ?></td></tr>
<?php endforeach; ?></tbody></table></div></section><?php endif; ?>
