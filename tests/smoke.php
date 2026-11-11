<?php
declare(strict_types=1);

// Pruebas de humo aisladas: una base MySQL temporal, sin cuentas ni datos reales.
require __DIR__ . '/../private/bootstrap.php';
require __DIR__ . '/../private/workflow.php';
require __DIR__ . '/../private/requests.php';
require __DIR__ . '/../private/templates.php';
require __DIR__ . '/../private/capacity.php';
require __DIR__ . '/../private/universe.php';
require __DIR__ . '/../private/risk-acceptance.php';
require __DIR__ . '/../private/grc.php';
require __DIR__ . '/../private/attestations.php';
require __DIR__ . '/../private/exceptions.php';
require __DIR__ . '/../private/multiyear.php';
require __DIR__ . '/../private/kri.php';
require __DIR__ . '/../private/inbox.php';
require __DIR__ . '/../private/risk-dashboard.php';
require __DIR__ . '/../private/escalations.php';
require __DIR__ . '/../private/recovery.php';
require __DIR__ . '/../private/reminders.php';
require __DIR__ . '/../private/unit-access.php';
require __DIR__ . '/../private/scoped-overview.php';
require __DIR__ . '/../private/policies.php';
require __DIR__ . '/../private/overview.php';
require __DIR__ . '/../private/retention.php';
require __DIR__ . '/../private/preflight.php';

function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('FALLÓ: ' . $message);
    echo 'OK: ' . $message . PHP_EOL;
}
function deny(callable $action, string $message): void {
    try {
        $action();
    } catch (DomainException $exception) {
        check(true,$message);
        return;
    }
    throw new RuntimeException('FALLÓ autorización: ' . $message);
}
$pdo = new PDO(
    getenv('AUDITPRO_TEST_DSN') ?: 'mysql:host=127.0.0.1;dbname=auditpro_test;charset=utf8mb4',
    getenv('AUDITPRO_TEST_USER') ?: 'root',
    getenv('AUDITPRO_TEST_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'auditpro_test') {
    throw new RuntimeException('Las pruebas solo se permiten en la base auditpro_test.');
}
foreach (array_merge(['schema.sql'],array_map(
    static fn(int $number): string => sprintf('upgrade-%03d.sql',$number),
    range(2,23)
)) as $migration) {
    $contents = file_get_contents(__DIR__.'/../private/'.$migration);
    if ($contents === false) throw new RuntimeException('Falta '.$migration);
    foreach (explode(';',$contents) as $sql) {
        if (trim($sql) !== '') $pdo->exec($sql);
    }
}
echo "Migraciones 002–023 y esquema instalados.\n";
foreach (['admin','supervisor','auditor','responsable'] as $role) {
    query($pdo,'INSERT INTO users(email,name,password_hash,role) VALUES (?,?,?,?)',
        [$role.'@example.test',ucfirst($role),password_hash('TestPass123456!',PASSWORD_DEFAULT),$role]);
}
$admin = ['id'=>1,'role'=>'admin'];
$supervisor = ['id'=>2,'role'=>'supervisor'];
$auditor = ['id'=>3,'role'=>'auditor'];
$responsable = ['id'=>4,'role'=>'responsable'];
$_POST = ['title'=>'Conciliaciones','category'=>'Finanzas','procedure'=>'Comparar saldos y documentar las diferencias'];
saveTemplate($pdo,$supervisor,'template_create');
$templateId = (int)query($pdo,'SELECT id FROM test_templates WHERE title=?',['Conciliaciones'])->fetchColumn();
check($templateId > 0,'Supervisión crea plantilla');
deny(static fn() => saveTemplate($pdo,$auditor,'template_create'),'Auditor no puede crear plantilla');
query($pdo,"INSERT INTO audits(title,entity,objective,year,risk,owner_id,status) VALUES ('Auditoría anual','Finanzas','Evaluar controles',?,'alto',?,'en_campo')",
    [(int)date('Y'),3]);
$auditId = (int)$pdo->lastInsertId();
$_POST = ['audit_id'=>(string)$auditId,'template_id'=>(string)$templateId];
saveTemplate($pdo,$auditor,'template_apply');
$test = query($pdo,'SELECT * FROM tests WHERE audit_id=?',[$auditId])->fetch();
check($test && $test['procedure_text']==='Comparar saldos y documentar las diferencias','Aplicar plantilla copia procedimiento');
check((int)query($pdo,'SELECT template_id FROM test_template_uses WHERE test_id=?',[$test['id']])->fetchColumn()===$templateId,'Aplicación conserva origen');
deny(static fn()=>saveTemplate($pdo,$responsable,'template_apply'),'Responsable no puede aplicar plantilla');
$_POST=['template_id'=>(string)$templateId];
saveTemplate($pdo,$admin,'template_archive');
check((int)query($pdo,'SELECT active FROM test_templates WHERE id=?',[$templateId])->fetchColumn()===0,'Archivar sin borrar plantilla');
check((bool)query($pdo,'SELECT id FROM tests WHERE id=?',[$test['id']])->fetchColumn(),'La prueba original persiste');

query($pdo,"INSERT INTO attachments(test_id,uploaded_by,filename,mime,bytes,sha256,content)
 VALUES (? ,3,'acta.txt','text/plain',4,?,?)",[$test['id'],hash('sha256','ACTA'),'ACTA']);
$requestAttachmentId=(int)$pdo->lastInsertId();
$_POST=['audit_id'=>(string)$auditId,'subject'=>'Actas','description'=>'Adjuntar actas de conciliación','due'=>date('Y-m-d',strtotime('+5 days'))];
saveEvidenceRequest($pdo,$supervisor,'request_create');
$requestId=(int)query($pdo,'SELECT id FROM evidence_requests WHERE audit_id=? ORDER BY id DESC LIMIT 1',[$auditId])->fetchColumn();
check($requestId>0,'Supervisión crea solicitud');
$_POST=['audit_id'=>(string)$auditId,'request_id'=>(string)$requestId,'answer'=>'Ver expediente de respaldo','attachment_id'=>(string)$requestAttachmentId];
$wrongAttachment=$_POST;
$_POST['attachment_id']='999999';
deny(static fn()=>saveEvidenceRequest($pdo,$auditor,'request_answer'),'Rechaza evidencia que no pertenece al expediente');
$_POST=$wrongAttachment;
deny(static fn()=>saveEvidenceRequest($pdo,$responsable,'request_answer'),'Responsable no puede responder solicitud');
saveEvidenceRequest($pdo,$auditor,'request_answer');
check(query($pdo,'SELECT status FROM evidence_requests WHERE id=?',[$requestId])->fetchColumn()==='respondida','Auditor responde solicitud');
check((int)query($pdo,'SELECT l.attachment_id FROM evidence_request_attachments l
 JOIN evidence_request_responses h ON h.id=l.response_id WHERE h.request_id=?',[$requestId])->fetchColumn()===$requestAttachmentId,
 'Solicitud conserva referencia verificable a evidencia del expediente');
query($pdo,"UPDATE audits SET status='en_revision' WHERE id=?",[$auditId]);
$audit=query($pdo,'SELECT * FROM audits WHERE id=?',[$auditId])->fetch();
check(nextState($pdo,$audit)===null,'Solicitud abierta bloquea emisión de informe');
$_POST=['audit_id'=>(string)$auditId,'request_id'=>(string)$requestId];
saveEvidenceRequest($pdo,$supervisor,'request_close');
check(nextState($pdo,$audit)==='emitida','Cerrar solicitud permite avanzar cuando no hay más bloqueos');

$_POST=['year'=>(string)date('Y'),'auditor_id'=>'3','hours'=>'120'];
saveAuditorCapacity($pdo,$supervisor);
query($pdo,'INSERT INTO audit_schedule(audit_id,start_on,end_on,planned_minutes) VALUES (?,?,?,?)',
    [$auditId,date('Y-m-d'),date('Y-m-d',strtotime('+5 days')),150*60]);
$rows=capacityRows($pdo,(int)date('Y'));
check(count($rows)===1 && (int)$rows[0]['available_minutes']===120*60,'Capacidad anual configurada');
check((int)$rows[0]['planned_minutes']>(int)$rows[0]['available_minutes'],'Detecta sobreasignación');
deny(static fn()=>saveAuditorCapacity($pdo,$auditor),'Auditor no modifica capacidad');
query($pdo,"INSERT INTO auditable_units(name,process_name,accountable,cycle_months) VALUES ('Finanzas','Cierre','Gerencia',12)");
$unit=(int)$pdo->lastInsertId();
query($pdo,"INSERT INTO risk_assessments(unit_id,assessor_id,methodology,likelihood,impact,score,rating,rationale) VALUES (?,?,'P×I v1',4,4,16,'alto','Prueba')",[$unit,2]);
$assessment=(int)$pdo->lastInsertId();
query($pdo,'INSERT INTO audit_assessment_links(audit_id,assessment_id) VALUES (?,?)',[$auditId,$assessment]);
$coverage=unitCoverage($pdo);
check(count($coverage)===1 && (int)$coverage[0]['audit_count']===1,'Cobertura histórica por unidad vinculada');
check(coverageStatus(['last_closed_on'=>date('Y-m-d',strtotime('-100 days')),'cycle_months'=>12,'last_audit_year'=>2024],(int)date('Y'))==='Dentro del ciclo verificado',
 'El ciclo usa la fecha de cierre efectiva, no solo el año');
check(coverageStatus(['last_closed_on'=>date('Y-m-d',strtotime('-14 months')),'cycle_months'=>12,'last_audit_year'=>(int)date('Y')],(int)date('Y'))==='Revisión de cobertura requerida',
 'Fecha exacta vencida genera brecha incluso con año nominal reciente');
check(str_contains(coverageStatus(['last_closed_on'=>null,'cycle_months'=>12,'last_audit_year'=>(int)date('Y')],(int)date('Y')),'aproximado'),
 'Históricos sin fecha se muestran como aproximación');


// Riesgo residual: solicitud, independencia y aprobación rastreable.
query($pdo,"INSERT INTO findings(test_id,title,severity,criterion,condition_text,cause,effect,recommendation)
 VALUES (?,'Diferencia material','alto','Política','Diferencia','Proceso','Riesgo','Conciliar')",[$test['id']]);
$findingId=(int)$pdo->lastInsertId();
query($pdo,"INSERT INTO actions(finding_id,owner_id,description,due_date) VALUES (?,4,'Subsanar diferencia',?)",
 [$findingId,date('Y-m-d',strtotime('+30 days'))]);
$actionId=(int)$pdo->lastInsertId();
query($pdo,"UPDATE audits SET status='emitida' WHERE id=?",[$auditId]);
$_POST=['audit_id'=>(string)$auditId,'action_id'=>(string)$actionId,
 'rationale'=>'Costo desproporcionado','compensating'=>'Revisión mensual',
 'expires'=>date('Y-m-d',strtotime('+40 days'))];
deny(static fn()=>saveRiskAcceptance($pdo,$auditor,'risk_accept_request'),'Auditor no solicita aceptación');
saveRiskAcceptance($pdo,$supervisor,'risk_accept_request');
$acceptId=(int)query($pdo,'SELECT id FROM action_risk_acceptances WHERE action_id=? ORDER BY id DESC LIMIT 1',
 [$actionId])->fetchColumn();
check($acceptId>0,'Se registra solicitud de aceptación');
$_POST=['audit_id'=>(string)$auditId,'acceptance_id'=>(string)$acceptId,
 'decision'=>'aceptada','decision_note'=>'Aprobación separada'];
deny(static fn()=>saveRiskAcceptance($pdo,$supervisor,'risk_accept_decide'),'Nadie aprueba su propia solicitud');
saveRiskAcceptance($pdo,$admin,'risk_accept_decide');
check(query($pdo,'SELECT status FROM action_risk_acceptances WHERE id=?',
 [$acceptId])->fetchColumn()==='aceptada','Aceptación aprobada independientemente');
check(query($pdo,'SELECT status FROM actions WHERE id=?',
 [$actionId])->fetchColumn()==='cerrada','Aceptación cierra acción con motivo registrado');
check(nextState($pdo,query($pdo,'SELECT * FROM audits WHERE id=?',[$auditId])->fetch())==='cerrada',
 'Puede cerrar expediente sin acciones adicionales');

// Historial inmutable de atestaciones y separación de responsabilidades.
query($pdo,"INSERT INTO grc_obligations(framework,reference_code,title,owner_name,review_on)
 VALUES ('SOC','CC1','Monitoreo','Cumplimiento',?)",[date('Y-m-d',strtotime('+40 days'))]);
$obligationId=(int)$pdo->lastInsertId();
$_POST=['obligation_id'=>(string)$obligationId,'result'=>'parcial',
 'evidence'=>'Acta de revisión interna','rationale'=>'Existe una excepción documentada',
 'reviewed'=>date('Y-m-d'),'next_review'=>date('Y-m-d',strtotime('+30 days'))];
deny(static fn()=>saveAttestation($pdo,$auditor),'Auditor no atesta obligaciones');
saveAttestation($pdo,$supervisor);
$history=obligationAttestations($pdo);
check(count($history[$obligationId]??[])===1 && $history[$obligationId][0]['result']==='parcial',
 'Atestación histórica documentada');
$_POST['result']='cumple';
saveAttestation($pdo,$supervisor);
$history=obligationAttestations($pdo);
check(count($history[$obligationId]??[])===2 && $history[$obligationId][0]['result']==='cumple'
 && $history[$obligationId][1]['result']==='parcial','Nueva atestación no borra la anterior');

$_POST=['obligation_id'=>(string)$obligationId,'title'=>'Excepción temporal',
 'rationale'=>'Migración de controles','mitigating'=>'Monitoreo diario',
 'expires'=>date('Y-m-d',strtotime('+60 days'))];
deny(static fn()=>saveComplianceException($pdo,$auditor,'exception_request'),
 'Auditor no solicita excepciones de cumplimiento');
saveComplianceException($pdo,$supervisor,'exception_request');
$exceptionId=(int)query($pdo,'SELECT id FROM obligation_exceptions WHERE obligation_id=? ORDER BY id DESC LIMIT 1',[$obligationId])->fetchColumn();
check($exceptionId>0,'Excepción creada');
$_POST=['obligation_id'=>(string)$obligationId,'exception_id'=>(string)$exceptionId,
 'decision'=>'aprobada','decision_note'=>'Compensación suficiente'];
deny(static fn()=>saveComplianceException($pdo,$supervisor,'exception_decide'),
 'Se impide la autoaprobación de excepciones');
saveComplianceException($pdo,$admin,'exception_decide');
check(query($pdo,'SELECT status FROM obligation_exceptions WHERE id=?',[$exceptionId])->fetchColumn()==='aprobada',
 'Excepción aprobada independientemente');
$_POST=['obligation_id'=>(string)$obligationId,'exception_id'=>(string)$exceptionId,
 'closure_note'=>'Medida compensatoria sustituida'];
saveComplianceException($pdo,$admin,'exception_close');
check(query($pdo,'SELECT status FROM obligation_exceptions WHERE id=?',[$exceptionId])->fetchColumn()==='cerrada',
 'Excepción cerrada conservando historial');

// Horizonte multianual: riesgo congelado, segregación de funciones y carga estimada.
$_POST=['title'=>'Horizonte 2027–2029','start_year'=>'2027','end_year'=>'2029',
 'rationale'=>'Priorizar unidades con mayor exposición'];
deny(static fn()=>saveMultiyear($pdo,$auditor,'multiyear_create'),
 'Auditor no puede crear horizonte multianual');
saveMultiyear($pdo,$supervisor,'multiyear_create');
$multiId=(int)query($pdo,'SELECT id FROM multiyear_plans ORDER BY id DESC LIMIT 1')->fetchColumn();
check($multiId>0,'Horizonte multianual creado');
$_POST=['plan_id'=>(string)$multiId,'unit_id'=>(string)$unit,
 'auditor_id'=>'3','target_year'=>'2027','hours'=>'180'];
saveMultiyear($pdo,$supervisor,'multiyear_add');
deny(static fn()=>saveMultiyear($pdo,$supervisor,'multiyear_add'),
 'No permite duplicar la misma unidad y año');
$multiItems=multiyearItems($pdo);
check(count($multiItems[$multiId]??[])===1 && (int)$multiItems[$multiId][0]['risk_score']===16,
 'Congela evaluación de riesgo al programar');
$multiLoad=multiyearLoad($pdo,$multiId);
check((int)$multiLoad['3:2027']['planned_minutes']===10800,'Carga estimada por auditor y año');
$_POST=['plan_id'=>(string)$multiId];
deny(static fn()=>saveMultiyear($pdo,$supervisor,'multiyear_approve'),
 'Prohíbe aprobar el propio plan multianual');
saveMultiyear($pdo,$admin,'multiyear_approve');
check((bool)query($pdo,'SELECT approved_by FROM multiyear_plans WHERE id=?',[$multiId])->fetchColumn(),
 'Aprueba mediante revisor independiente');
$_POST=['plan_id'=>(string)$multiId,'unit_id'=>(string)$unit,'auditor_id'=>'3',
 'target_year'=>'2028','hours'=>'100'];
deny(static fn()=>saveMultiyear($pdo,$supervisor,'multiyear_add'),
 'Plan aprobado es inmutable');

// Riesgos periódicos e indicadores KRI, con roles y conservación de historial.
query($pdo,'INSERT INTO grc_risks(unit_id,title,owner_name,inherent,residual,appetite,treatment,review_on)
 VALUES (?,?,?,?,?,?,?,?)',[$unit,'Liquidez','Riesgos',20,15,10,'Monitoreo',date('Y-m-d',strtotime('+20 days'))]);
$riskId=(int)$pdo->lastInsertId();
$_POST=['risk_id'=>(string)$riskId,'residual'=>'12','reviewed_on'=>date('Y-m-d'),
 'next_review_on'=>date('Y-m-d',strtotime('+30 days')),'note'=>'Se revisaron indicadores de liquidez'];
deny(static fn()=>saveKri($pdo,$auditor,'risk_review'),'Auditor no revisa riesgos');
saveKri($pdo,$admin,'risk_review');
check((int)query($pdo,'SELECT residual FROM grc_risks WHERE id=?',[$riskId])->fetchColumn()===12,
 'Actualiza riesgo residual');
check((int)query($pdo,'SELECT previous_residual FROM grc_risk_reviews WHERE risk_id=?',[$riskId])->fetchColumn()===15,
 'Conserva revisión previa');
$_POST=['risk_id'=>(string)$riskId,'title'=>'Indicador de mora','unit'=>'%',
 'direction'=>'maximo','threshold'=>'3.5'];
saveKri($pdo,$admin,'kri_create');
$kriId=(int)query($pdo,'SELECT id FROM grc_kri_indicators WHERE risk_id=? ORDER BY id DESC LIMIT 1',[$riskId])->fetchColumn();
check($kriId>0,'Crea KRI con umbral');
$_POST=['risk_id'=>(string)$riskId,'indicator_id'=>(string)$kriId,
 'observed_on'=>date('Y-m-d'),'value'=>'4.25','evidence'=>'Informe de cobranza'];
deny(static fn()=>saveKri($pdo,$auditor,'kri_measure'),'Auditor no registra mediciones KRI');
saveKri($pdo,$admin,'kri_measure');
$kriRow=kriIndicators($pdo)[$riskId][0]??null;
check($kriRow!==null && kriBreached($kriRow),'Detecta medición superior al máximo');
check(count(breachedKriInbox($pdo))===1,'Alerta KRI en bandeja');
$_POST['value']='2.8';
saveKri($pdo,$admin,'kri_measure');
$kriRow=kriIndicators($pdo)[$riskId][0]??null;
check($kriRow!==null && !kriBreached($kriRow),'Alerta desaparece con la última medición válida');
check(count(kriObservations($pdo,$kriId))===2,'Conserva historial de mediciones');
$_POST=['risk_id'=>(string)$riskId,'indicator_id'=>(string)$kriId];
deny(static fn()=>saveKri($pdo,$auditor,'kri_archive'),'Auditor no archiva indicadores');
saveKri($pdo,$admin,'kri_archive');
check(count(breachedKriInbox($pdo))===0,'Indicador archivado deja de alertar');

$dashboard=riskDashboard($pdo,(int)date('Y'));
check($dashboard['units']===1 && $dashboard['risks']===1,'Tablero cuenta unidades y riesgos');
check($dashboard['above_appetite']===1,'Tablero reconoce riesgo por encima del apetito');
check($dashboard['kri_breaches']===0,'Tablero excluye indicadores archivados');
check($dashboard['gaps']===0,'Tablero no marca brecha en auditoría del año actual');

// Escalamiento por días: política administrativa, idempotencia, cierre y prórroga.
$today=new DateTimeImmutable('today');
check((int)escalationPolicy($pdo)['enabled']===0,'Escalamiento comienza desactivado');
$_POST=['enabled'=>'1','overdue_days'=>'3'];
deny(static fn()=>saveEscalationPolicy($pdo,$supervisor),
 'Solo administración configura el escalamiento');
saveEscalationPolicy($pdo,$admin);
check((int)escalationPolicy($pdo)['overdue_days']===3,'Umbral administrativo actualizado');
query($pdo,"UPDATE audits SET status='emitida' WHERE id=?",[$auditId]);
query($pdo,"UPDATE actions SET status='abierta',due_date=? WHERE id=?",
 [$today->modify('-5 days')->format('Y-m-d'),$actionId]);
generateEscalations($pdo,$today);
generateEscalations($pdo,$today);
$count=(int)query($pdo,'SELECT COUNT(*) FROM action_escalations WHERE action_id=?',[$actionId])->fetchColumn();
check($count===2,'Escalamiento idempotente para administrador y supervisor');
check(count(unreadEscalations($pdo,$supervisor))===1,'Supervisión recibe su aviso');
check(count(unreadEscalations($pdo,$responsable))===0,'Responsable no ve escalaciones de supervisión');
$noticeId=(int)unreadEscalations($pdo,$supervisor)[0]['id'];
deny(static fn()=>readEscalation($pdo,$responsable,$noticeId),
 'Responsable no marca escalaciones como leídas');
readEscalation($pdo,$supervisor,$noticeId);
check(count(unreadEscalations($pdo,$supervisor))===0,'Aviso marcado como leído');
query($pdo,'UPDATE actions SET due_date=? WHERE id=?',[$today->modify('+10 days')->format('Y-m-d'),$actionId]);
check(count(unreadEscalations($pdo,$admin))===0,'Prórroga oculta avisos del vencimiento anterior');
query($pdo,"UPDATE actions SET status='cerrada' WHERE id=?",[$actionId]);
generateEscalations($pdo,$today);
check((int)query($pdo,'SELECT COUNT(*) FROM action_escalations WHERE action_id=?',[$actionId])->fetchColumn()===2,
 'Acciones cerradas no crean nuevas escalaciones');
$_POST=['enabled'=>'0','overdue_days'=>'3'];
saveEscalationPolicy($pdo,$admin);
check((int)escalationPolicy($pdo)['enabled']===0,'Administración desactiva el escalamiento');

// Recuperación local: requiere token fuera de la aplicación, cuenta admin activa y bitácora.
$recoveryToken=bin2hex(random_bytes(32));
$recoveryConfig=['recovery_token_hash'=>hash('sha256',$recoveryToken)];
$oldHash=query($pdo,'SELECT password_hash FROM users WHERE id=1')->fetchColumn();
deny(static fn()=>recoverAdminPassword($pdo,$recoveryConfig,'admin@example.test','token-equivocado'),
 'No recupera con token incorrecto');
check(query($pdo,'SELECT password_hash FROM users WHERE id=1')->fetchColumn()===$oldHash,
 'Intento denegado no cambia contraseña');
deny(static fn()=>recoverAdminPassword($pdo,$recoveryConfig,'auditor@example.test',$recoveryToken),
 'No recupera cuentas que no son administradoras');
deny(static fn()=>recoverAdminPassword($pdo,[],'admin@example.test',$recoveryToken),
 'Recuperación deshabilitada sin configuración');
$newPassword=recoverAdminPassword($pdo,$recoveryConfig,'admin@example.test',$recoveryToken);
$newHash=query($pdo,'SELECT password_hash FROM users WHERE id=1')->fetchColumn();
check(validPassword($newPassword) && password_verify($newPassword,$newHash),
 'Genera contraseña temporal segura para administrador');
check($newHash!==$oldHash,'La recuperación invalida el hash de sesiones anteriores');
check((int)query($pdo,"SELECT COUNT(*) FROM audit_log WHERE event='recuperacion_administrativa'")->fetchColumn()===1,
 'Registra recuperación en bitácora sin guardar el secreto');

// Supervisión restringida: acceso a unidades explícitas y bloqueo por defecto.
$_POST=['supervisor_id'=>'2','restricted'=>'1'];
deny(static fn()=>saveUnitGrant($pdo,$supervisor,'unit_scope'),'Supervisor no administra sus permisos');
saveUnitGrant($pdo,$admin,'unit_scope');
$restrictedUser=['id'=>2,'role'=>'supervisor','unit_restricted'=>1];
check(!canViewTemplates($restrictedUser),'Supervisor restringido no ve catálogo global de plantillas');
$_POST=['title'=>'Plantilla no autorizada','category'=>'Global','procedure'=>'Datos ajenos'];
deny(static fn()=>saveTemplate($pdo,$restrictedUser,'template_create'),
 'Supervisor restringido no modifica plantillas globales');
deny(static fn()=>accessible($pdo,$restrictedUser,$auditId),'Supervisor restringido no accede sin concesión');
check(!allowedUnit($pdo,$restrictedUser,$unit),'Unidad sin permiso denegada');
$_POST=['supervisor_id'=>'2','unit_id'=>(string)$unit];
saveUnitGrant($pdo,$admin,'unit_grant');
check(allowedUnit($pdo,$restrictedUser,$unit),'Grant autoriza unidad específica');
check(count(scopedUnitOverview($pdo,$restrictedUser))===1,
 'Supervisor restringido solo recibe las unidades concedidas');
$coverageAllowed=scopedUnitCoverage($pdo,$restrictedUser);
check(count($coverageAllowed)===1 && (int)$coverageAllowed[0]['audit_count']===1,
 'Cobertura por unidad no duplica auditorías de otras unidades');
check(count(scopedRiskOverview($pdo,$restrictedUser))===1,
 'Riesgos filtrados por unidad asignada');
check((int)accessible($pdo,$restrictedUser,$auditId)['id']===$auditId,'Supervisor ve expediente vinculado a su unidad');
$filtered=planRows($pdo,$restrictedUser,['year'=>'','status'=>'','risk'=>'','q'=>'']);
check(count($filtered)===1,'Listado filtra expedientes al ámbito permitido');
query($pdo,"INSERT INTO auditable_units(name,process_name,accountable,cycle_months) VALUES ('Unidad B','Otra','Gerencia',12)");
$otherUnit=(int)$pdo->lastInsertId();
query($pdo,"INSERT INTO risk_assessments(unit_id,assessor_id,methodology,likelihood,impact,score,rating,rationale)
 VALUES (?,2,'P×I v1',3,3,9,'medio','Prueba')",[$otherUnit]);
$otherAssessment=(int)$pdo->lastInsertId();
query($pdo,"INSERT INTO audits(title,entity,objective,year,risk,owner_id)
 VALUES ('Auditoría privada','Unidad B','Alcance reservado',?,'medio',3)",[(int)date('Y')]);
$otherAudit=(int)$pdo->lastInsertId();
query($pdo,'INSERT INTO audit_assessment_links(audit_id,assessment_id) VALUES (?,?)',[$otherAudit,$otherAssessment]);
deny(static fn()=>accessible($pdo,$restrictedUser,$otherAudit),'Supervisor no ve otra unidad');
query($pdo,"INSERT INTO grc_risks(unit_id,title,owner_name,inherent,residual,appetite,treatment,review_on) VALUES (?,'Riesgo B','Área B',12,10,8,'Control',?)",[$otherUnit,date('Y-m-d',strtotime('+10 days'))]);
check(count(scopedRiskOverview($pdo,$restrictedUser))===1,'No expone riesgos de otras unidades');
check(count(scopedUnitCoverage($pdo,$restrictedUser))===1,
 'Cobertura excluye unidades no autorizadas');

$authorizedRisk=(int)query($pdo,'SELECT id FROM grc_risks WHERE unit_id=? ORDER BY id DESC LIMIT 1',[$unit])->fetchColumn();
$foreignRisk=(int)query($pdo,'SELECT id FROM grc_risks WHERE unit_id=? ORDER BY id DESC LIMIT 1',[$otherUnit])->fetchColumn();
$_POST=['risk_id'=>(string)$foreignRisk,'residual'=>'4','reviewed_on'=>date('Y-m-d'),
 'next_review_on'=>date('Y-m-d',strtotime('+25 days')),'note'=>'Acceso indebido'];
deny(static fn()=>saveKri($pdo,$restrictedUser,'risk_review'),
 'Supervisor no revisa riesgo de otra unidad');
check((int)query($pdo,'SELECT residual FROM grc_risks WHERE id=?',[$foreignRisk])->fetchColumn()===10,
 'Riesgo ajeno no se modifica');
$_POST=['risk_id'=>(string)$authorizedRisk,'residual'=>'9','reviewed_on'=>date('Y-m-d'),
 'next_review_on'=>date('Y-m-d',strtotime('+25 days')),'note'=>'Evaluación autorizada'];
saveKri($pdo,$restrictedUser,'risk_review');
check((int)query($pdo,'SELECT residual FROM grc_risks WHERE id=?',[$authorizedRisk])->fetchColumn()===9,
 'Supervisor revisa riesgo de unidad concedida');
$_POST=['risk_id'=>(string)$authorizedRisk,'title'=>'KRI no permitido',
 'unit'=>'%','direction'=>'maximo','threshold'=>'2'];
deny(static fn()=>saveKri($pdo,$restrictedUser,'kri_create'),
 'Supervisor restringido no crea indicadores globales');
$_POST=['risk_id'=>(string)$authorizedRisk,'title'=>'Incidencias','unit'=>'%',
 'direction'=>'maximo','threshold'=>'3'];
saveKri($pdo,$admin,'kri_create');
$authorizedKri=(int)query($pdo,'SELECT MAX(id) FROM grc_kri_indicators WHERE risk_id=?',[$authorizedRisk])->fetchColumn();
$_POST=['risk_id'=>(string)$foreignRisk,'title'=>'Indicador reservado','unit'=>'%',
 'direction'=>'maximo','threshold'=>'4'];
saveKri($pdo,$admin,'kri_create');
$foreignKri=(int)query($pdo,'SELECT MAX(id) FROM grc_kri_indicators WHERE risk_id=?',[$foreignRisk])->fetchColumn();
$scopedIndicators=scopedRiskIndicators($pdo,$restrictedUser);
check(count($scopedIndicators[$authorizedRisk]??[])===1 && empty($scopedIndicators[$foreignRisk]),
 'Solo muestra KRI de unidad concedida');
$_POST=['risk_id'=>(string)$foreignRisk,'indicator_id'=>(string)$foreignKri,
 'observed_on'=>date('Y-m-d'),'value'=>'8','evidence'=>'Intento ajeno'];
deny(static fn()=>saveKri($pdo,$restrictedUser,'kri_measure'),
 'Supervisor no mide indicadores fuera de unidad');
$_POST=['risk_id'=>(string)$authorizedRisk,'indicator_id'=>(string)$foreignKri,
 'observed_on'=>date('Y-m-d'),'value'=>'8','evidence'=>'Indicador ajeno'];
deny(static fn()=>saveKri($pdo,$restrictedUser,'kri_measure'),
 'No permite sustituir el riesgo autorizado por un indicador ajeno');
$_POST=['risk_id'=>(string)$authorizedRisk,'indicator_id'=>(string)$authorizedKri,
 'observed_on'=>date('Y-m-d'),'value'=>'5','evidence'=>'Seguimiento local'];
saveKri($pdo,$restrictedUser,'kri_measure');
check((int)query($pdo,'SELECT COUNT(*) FROM grc_kri_observations WHERE indicator_id=?',[$authorizedKri])->fetchColumn()===1,
 'Supervisor registra medición en KRI autorizado');
$_POST=['risk_id'=>(string)$authorizedRisk,'indicator_id'=>(string)$authorizedKri];
deny(static fn()=>saveKri($pdo,$restrictedUser,'kri_archive'),
 'Supervisor restringido no archiva indicadores');

check(count(planRows($pdo,$restrictedUser,['year'=>'','status'=>'','risk'=>'','q'=>'']))===1,
 'Listado no filtra información de otras unidades');
// Trazabilidad: un control compartido no expone pruebas de expedientes ajenos.
query($pdo,"INSERT INTO grc_controls(title,owner_name,frequency,description)
 VALUES ('Control compartido','Gerencia','mensual','Revisión documentada')");
$controlShared=(int)$pdo->lastInsertId();
query($pdo,'INSERT INTO grc_risk_controls(risk_id,control_id) VALUES (?,?)',[$authorizedRisk,$controlShared]);
query($pdo,'INSERT INTO grc_risk_controls(risk_id,control_id) VALUES (?,?)',[$foreignRisk,$controlShared]);
query($pdo,'INSERT INTO grc_control_tests(test_id,control_id) VALUES (?,?)',[$test['id'],$controlShared]);
query($pdo,"INSERT INTO tests(audit_id,title,procedure_text) VALUES (?,'Prueba privada','No divulgar')",[$otherAudit]);
$foreignTest=(int)$pdo->lastInsertId();
query($pdo,'INSERT INTO grc_control_tests(test_id,control_id) VALUES (?,?)',[$foreignTest,$controlShared]);
query($pdo,"INSERT INTO grc_controls(title,owner_name,frequency,description)
 VALUES ('Control ajeno','Otra unidad','anual','No autorizado')");
$foreignControl=(int)$pdo->lastInsertId();
query($pdo,'INSERT INTO grc_risk_controls(risk_id,control_id) VALUES (?,?)',[$foreignRisk,$foreignControl]);
$visibleControls=scopedRiskControls($pdo,$restrictedUser);
check(count($visibleControls[$authorizedRisk]??[])===1 &&
    empty($visibleControls[$foreignRisk]),'Solo revela controles vinculados a riesgos concedidos');
$linkedTests=scopedControlTests($pdo,$restrictedUser);
check(count($linkedTests[$authorizedRisk][$controlShared]??[])===1 &&
    (int)$linkedTests[$authorizedRisk][$controlShared][0]['test_id']===(int)$test['id'],
    'Control compartido solo muestra prueba de expediente autorizado');
check(empty($linkedTests[$foreignRisk]),'No filtra trazabilidad de riesgo ajeno');
// Las obligaciones deben filtrarse por riesgo, nunca por rol de supervisor.
query($pdo,'INSERT INTO grc_obligation_controls(obligation_id,control_id) VALUES (?,?)',
 [$obligationId,$controlShared]);
query($pdo,"INSERT INTO grc_obligations(framework,reference_code,title,owner_name,review_on)
 VALUES ('SOC','RESERVADA','Obligación privada','Otra gerencia',?)",[date('Y-m-d',strtotime('+25 days'))]);
$foreignObligation=(int)$pdo->lastInsertId();
query($pdo,'INSERT INTO grc_obligation_controls(obligation_id,control_id) VALUES (?,?)',
 [$foreignObligation,$foreignControl]);
$visibleObligations=scopedObligations($pdo,$restrictedUser);
check(count($visibleObligations)===1 &&
 (int)$visibleObligations[0]['id']===$obligationId,
 'Cumplimiento filtrado: obligación autorizada visible, obligación de otra unidad oculta');

$_POST=['supervisor_id'=>'2','unit_id'=>(string)$unit];
saveUnitGrant($pdo,$admin,'unit_revoke');
deny(static fn()=>accessible($pdo,$restrictedUser,$auditId),'Revocación se aplica inmediatamente');
$_POST=['supervisor_id'=>'2','restricted'=>'0'];
saveUnitGrant($pdo,$admin,'unit_scope');
check(allowedAudit($pdo,['id'=>2,'role'=>'supervisor','unit_restricted'=>0],$otherAudit),
 'Desactivar restricción recupera acceso global del supervisor');

// Conservación legal, elegibilidad de purga y protección frente a usuarios sin permiso.
$queryDate=(new DateTimeImmutable('today'))->modify('+400 days')->format('Y-m-d');
query($pdo,"INSERT INTO attachments(test_id,uploaded_by,filename,mime,bytes,sha256,content)
 VALUES (?,?,'prueba.txt','text/plain',5,?,?)",
 [$test['id'],3,hash('sha256','ABCDE'),'ABCDE']);
$evidenceId=(int)$pdo->lastInsertId();
$_POST=['attachment_id'=>(string)$evidenceId,'retention_until'=>$queryDate,'legal_hold'=>'1'];
deny(static fn()=>saveRetention($pdo,$supervisor,'retention_set'),'Supervisor no cambia retención');
saveRetention($pdo,$admin,'retention_set');
check((int)query($pdo,'SELECT legal_hold FROM attachments WHERE id=?',[$evidenceId])->fetchColumn()===1,
 'Bloqueo legal de evidencia registrado');
$_POST=['attachment_id'=>(string)$evidenceId,'confirmation'=>'PURGAR'];
deny(static fn()=>saveRetention($pdo,$admin,'retention_purge'),'Bloqueo legal impide purgar');
check(query($pdo,'SELECT content FROM attachments WHERE id=?',[$evidenceId])->fetchColumn()==='ABCDE',
 'Purgado denegado conserva el archivo');
$_POST=['attachment_id'=>(string)$evidenceId,'retention_until'=>$queryDate,'legal_hold'=>'0'];
saveRetention($pdo,$admin,'retention_set');
$_POST=['attachment_id'=>(string)$evidenceId,'confirmation'=>'PURGAR'];
deny(static fn()=>saveRetention($pdo,$admin,'retention_purge'),'Plazo no vencido impide purgar');
check(query($pdo,'SELECT content FROM attachments WHERE id=?',[$evidenceId])->fetchColumn()==='ABCDE',
 'La evidencia se conserva hasta que expire el plazo');
// Simular evidencia antigua permite comprobar el purgado sin alterar el reloj del servidor.
query($pdo,"UPDATE attachments SET created_at='2024-01-01 00:00:00',
 retention_until='2025-01-15' WHERE id=?",[$evidenceId]);
query($pdo,"UPDATE audits SET status='cerrada' WHERE id=?",[$auditId]);
saveRetention($pdo,$admin,'retention_purge');
check(query($pdo,'SELECT content FROM attachments WHERE id=?',[$evidenceId])->fetchColumn()===null,
 'Contenido vencido se elimina tras confirmación');
$purged=query($pdo,'SELECT sha256,purged_at FROM attachments WHERE id=?',[$evidenceId])->fetch();
check($purged['sha256']===hash('sha256','ABCDE') && $purged['purged_at']!==null,
 'Huella y fecha de purga permanecen');
check((int)query($pdo,"SELECT COUNT(*) FROM audit_log WHERE event='evidencia_purgada'")->fetchColumn()===1,
 'Purga deja bitácora rastreable');

// Diagnóstico de preparación: detecta instalaciones incompletas sin modificar datos.
query($pdo,'INSERT INTO installation(id) VALUES (1)');
$privatePath=realpath(__DIR__.'/../private/config.example.php');
check(is_string($privatePath) && $privatePath!=='','Archivo privado de configuración de muestra localizable');
putenv('AUDITPRO_CONFIG='.$privatePath);
$diagnostics=preflightChecks($pdo,[
 'user'=>'test_runner','password'=>'fake_local_only',
 'install_user'=>'','install_password'=>'','install_token'=>''
]);
check(preflightReady($diagnostics),'Preflight confirma migraciones 019 y 020 y entorno de pruebas');
$missing=preflightChecks($pdo,[
 'user'=>'test_runner','password'=>'fake_local_only',
 'install_user'=>'','install_password'=>'','install_token'=>'token-pendiente'
]);
check(!preflightReady($missing),'Preflight detecta instalador no deshabilitado');
putenv('AUDITPRO_CONFIG');

// Políticas: roles, obligaciones, segregación de aprobación y versiones inmutables.
$_POST=['title'=>'Política de conciliaciones','owner_name'=>'Cumplimiento'];
deny(static fn()=>saveCompliancePolicy($pdo,$auditor,'policy_create'),'Auditor no crea políticas');
saveCompliancePolicy($pdo,$supervisor,'policy_create');
$policyId=(int)query($pdo,'SELECT MAX(id) FROM compliance_policies')->fetchColumn();
check($policyId>0,'Política registrada');
$_POST=['policy_id'=>(string)$policyId,'policy_text'=>'Procedimiento de validación',
 'rationale'=>'Primera edición','effective_on'=>date('Y-m-d',strtotime('+10 days'))];
saveCompliancePolicy($pdo,$supervisor,'policy_version');
$versionId=(int)query($pdo,'SELECT MAX(id) FROM compliance_policy_versions WHERE policy_id=?',[$policyId])->fetchColumn();
$_POST=['policy_id'=>(string)$policyId,'version_id'=>(string)$versionId];
deny(static fn()=>saveCompliancePolicy($pdo,$supervisor,'policy_approve'),'El autor no aprueba su versión');
deny(static fn()=>saveCompliancePolicy($pdo,$admin,'policy_approve'),'Sin obligación no se permite aprobar');
$_POST=['policy_id'=>(string)$policyId,'version_id'=>(string)$versionId,'obligation_id'=>(string)$obligationId];
saveCompliancePolicy($pdo,$supervisor,'policy_link');
$_POST=['policy_id'=>(string)$policyId,'version_id'=>(string)$versionId];
saveCompliancePolicy($pdo,$admin,'policy_approve');
$approved=query($pdo,'SELECT approved_by,policy_text FROM compliance_policy_versions WHERE id=?',[$versionId])->fetch();
check((int)$approved['approved_by']===1 && $approved['policy_text']==='Procedimiento de validación',
 'La versión conserva texto y aprobación independiente');
$_POST=['policy_id'=>(string)$policyId,'version_id'=>(string)$versionId,'obligation_id'=>(string)$obligationId];
deny(static fn()=>saveCompliancePolicy($pdo,$supervisor,'policy_link'),
 'Versión aprobada impide modificar vínculos');
$_POST=['policy_id'=>(string)$policyId,'policy_text'=>'Nueva edición',
 'rationale'=>'Actualización','effective_on'=>date('Y-m-d',strtotime('+20 days'))];
saveCompliancePolicy($pdo,$supervisor,'policy_version');
check((int)query($pdo,'SELECT COUNT(*) FROM compliance_policy_versions WHERE policy_id=?',[$policyId])->fetchColumn()===2,
 'Nueva versión conserva el historial previo');
deny(static fn()=>saveCompliancePolicy($pdo,$supervisor,'policy_version'),
 'No crea versión adicional con otra pendiente');
// Fixture final para las pruebas HTTP de supervisor restringido.
query($pdo,'UPDATE users SET unit_restricted=1 WHERE id=2');
query($pdo,'INSERT INTO supervisor_unit_grants(user_id,unit_id,granted_by) VALUES (2,?,1)
 ON DUPLICATE KEY UPDATE granted_by=VALUES(granted_by)',[$unit]);
check(count(scopedRiskOverview($pdo,['id'=>2,'role'=>'supervisor','unit_restricted'=>1]))===1,
 'Entorno HTTP usa supervisor restringido con un solo riesgo visible');
echo "TODAS LAS PRUEBAS DE HUMO PASARON.\n";
