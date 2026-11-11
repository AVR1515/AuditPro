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
    range(2,15)
)) as $migration) {
    $contents = file_get_contents(__DIR__.'/../private/'.$migration);
    if ($contents === false) throw new RuntimeException('Falta '.$migration);
    foreach (explode(';',$contents) as $sql) {
        if (trim($sql) !== '') $pdo->exec($sql);
    }
}
echo "Migraciones 002–015 y esquema instalados.\n";
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
echo "TODAS LAS PRUEBAS DE HUMO PASARON.\n";
