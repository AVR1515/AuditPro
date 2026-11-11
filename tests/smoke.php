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
    range(2,17)
)) as $migration) {
    $contents = file_get_contents(__DIR__.'/../private/'.$migration);
    if ($contents === false) throw new RuntimeException('Falta '.$migration);
    foreach (explode(';',$contents) as $sql) {
        if (trim($sql) !== '') $pdo->exec($sql);
    }
}
echo "Migraciones 002–017 y esquema instalados.\n";
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

$_POST=['audit_id'=>(string)$auditId,'subject'=>'Actas','description'=>'Adjuntar actas de conciliación','due'=>date('Y-m-d',strtotime('+5 days'))];
saveEvidenceRequest($pdo,$supervisor,'request_create');
$requestId=(int)query($pdo,'SELECT id FROM evidence_requests WHERE audit_id=? ORDER BY id DESC LIMIT 1',[$auditId])->fetchColumn();
check($requestId>0,'Supervisión crea solicitud');
$_POST=['audit_id'=>(string)$auditId,'request_id'=>(string)$requestId,'answer'=>'Ver expediente de respaldo'];
deny(static fn()=>saveEvidenceRequest($pdo,$responsable,'request_answer'),'Responsable no puede responder solicitud');
saveEvidenceRequest($pdo,$auditor,'request_answer');
check(query($pdo,'SELECT status FROM evidence_requests WHERE id=?',[$requestId])->fetchColumn()==='respondida','Auditor responde solicitud');
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
echo "TODAS LAS PRUEBAS DE HUMO PASARON.\n";
