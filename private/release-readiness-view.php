<?php
$readiness=releaseReadiness($pdo,config(),$user);
$checks=$readiness['preflight'];
$cron=$readiness['scheduler'];
$stats=$readiness['retention'];
$pending=$readiness['operations'];
$declared=pilotGateHistory($pdo,$user);
$latestByGate=[];
foreach ($declared as $entry) {
    $key=$entry['gate_key'].':'.$entry['reviewer_role'];
    if (!isset($latestByGate[$key])) $latestByGate[$key]=$entry;
}

?>
<section><h2>Preparación para el piloto y producción</h2>
<p class="muted">Este panel es de solo lectura y no autoriza el lanzamiento. El código en GitHub, los controles técnicos locales y las aprobaciones del entorno real se verifican por separado.</p>
<dl class="metrics">
<div><dt>Preflight del servidor</dt><dd><?= $readiness['preflight_ready'] ? 'Conforme' : 'Incompleto' ?></dd></div>
<div><dt>Proceso diario de avisos</dt><dd><?= $cron['healthy'] ? 'Vigente' : 'Pendiente' ?></dd></div>
<div><dt>Evidencias bajo bloqueo legal</dt><dd><?= e($stats['legal_holds']) ?></dd></div>
<div><dt>Solicitudes de evidencia abiertas</dt><dd><?= e($pending['evidence_open']) ?></dd></div>
<div><dt>Aceptaciones pendientes</dt><dd><?= e($pending['risk_pending']) ?></dd></div>
<div><dt>Excepciones pendientes</dt><dd><?= e($pending['exceptions_pending']) ?></dd></div>
</dl>
<h3>Declaraciones de las siete verificaciones</h3>
<p class="muted">Son resultados declarados por los roles responsables, no una certificación independiente de producción. La aceptación requiere revisar las referencias, el SHA exacto y los controles externos.</p>
<div class="table"><table><thead><tr><th scope="col">Control</th><th scope="col">Rol responsable</th><th scope="col">Última declaración</th></tr></thead><tbody>
<?php foreach (PILOT_GATES as $gate=>$roles): foreach ($roles as $reviewerRole):
$declaration=$latestByGate[$gate.':'.$reviewerRole] ?? null;
?><tr><td><?= e(PILOT_GATE_NAMES[$gate]) ?></td>
<td><?= e($reviewerRole) ?></td>
<td><?= !$declaration?'Sin evidencia registrada':(
    ($declaration['result']==='cumple'?'Conforme declarado':'Incidencia declarada').
    ' · '.e(substr($declaration['version_sha'],0,12)).' · '.e($declaration['reviewed_at'])) ?></td>
</tr><?php endforeach; endforeach; ?></tbody></table></div>
<p><a href="index.php?view=pilot-signoffs">Registrar y consultar evidencias del piloto</a>.</p>
<h3>Controles técnicos de esta instalación</h3>
<div class="table"><table><thead><tr><th scope="col">Comprobación</th><th scope="col">Resultado</th></tr></thead><tbody>
<?php foreach ($checks as $check): ?><tr><td><?= e($check['description']) ?></td>
<td><?= $check['ok'] ? 'Conforme' : 'Falta completar' ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<p>Última ejecución correcta de recordatorios: <?= e($cron['last_success_at'] ?? 'Sin registrar') ?>.
El servidor debe programar <code>php private/remind.php</code> diariamente y registrar un éxito al menos cada 36 horas.</p>
</section>
<section><h2>Siete verificaciones que requieren evidencias externas</h2>
<p class="muted">El estado queda pendiente hasta que los responsables entreguen actas, pruebas reales y autorizaciones. No se pueden acreditar automáticamente desde el código.</p>
<ol>
<li><strong>Alcance y criterios:</strong> aprobación de Auditoría, Riesgos y Cumplimiento y matriz de usuarios reales.</li>
<li><strong>Retención:</strong> aprobación jurídica, bloqueo legal y recuperación de evidencias desde un respaldo aislado.</li>
<li><strong>Acciones:</strong> recorrido real de prórrogas, cierres, aceptación de riesgo y revisión independiente.</li>
<li><strong>Notificaciones:</strong> cron real, avisos y escalaciones recibidos por destinatarios autorizados.</li>
<li><strong>Piloto:</strong> pruebas de accesibilidad, capturas auténticas, incidencias y aceptación firmada.</li>
<li><strong>Producción:</strong> certificado HTTPS, protección de archivos privados, respaldo restaurado y reversión ensayada.</li>
<li><strong>Equipos especializados:</strong> talleres y aprobación de umbrales KRI, políticas y controles con propietarios reales.</li>
</ol>
<p>Guías de aceptación:
<a href="https://github.com/AVR1515/AuditPro/blob/main/docs/production-release-gates.md">Puertas de liberación</a>
· <a href="https://github.com/AVR1515/AuditPro/blob/main/docs/pilot-checklist.md">Checklist del piloto</a>
· <a href="https://github.com/AVR1515/AuditPro/blob/main/docs/restore-verification.md">Restauración verificada</a>.</p>
<p><strong>Estado del lanzamiento: PENDIENTE DE APROBACIÓN EXTERNA.</strong></p>
</section>
