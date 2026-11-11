<?php
declare(strict_types=1);

/**
 * Diagnóstico de solo lectura previo al piloto. No escribe, no imprime credenciales
 * y no intenta simular las comprobaciones de HTTPS del servidor web.
 */
function preflightChecks(PDO $pdo, array $config): array {
    $checks=[];
    $add=static function(string $key,bool $ok,string $description) use (&$checks): void {
        $checks[]=['key'=>$key,'ok'=>$ok,'description'=>$description];
    };
    $add('php',PHP_VERSION_ID>=80200,'PHP 8.2 o superior');
    $add('pdo_mysql',extension_loaded('pdo_mysql'),'Extensión PDO MySQL');
    $add('fileinfo',extension_loaded('fileinfo'),'Extensión fileinfo para adjuntos');
    $add('db_connection',$pdo->query('SELECT 1')->fetchColumn()!==false,'Conexión SQL');
    $installed=(bool)$pdo->query('SELECT id FROM installation WHERE id=1')->fetchColumn();
    $add('installed',$installed,'Instalación inicial completada');
    $required=[
        'users'=>['unit_restricted'],
        'attachments'=>['retention_until','legal_hold','purged_at','purged_by'],
        'supervisor_unit_grants'=>['user_id','unit_id'],
        'action_escalations'=>['id'],
        'escalation_policy'=>['id'],
        'multiyear_plans'=>['id'],
        'grc_kri_indicators'=>['id','review_interval_days'],
        'obligation_exceptions'=>['id'],
        'action_risk_acceptances'=>['id'],
        'action_risk_reviews'=>['id','acceptance_id','reviewed_by'],
        'audit_log'=>['id'],
        'scheduled_job_runs'=>['job_key','last_success_at','successful_runs'],
        'audits'=>['closed_on'],
        'evidence_request_attachments'=>['response_id','attachment_id'],
        'compliance_policies'=>['id','title','owner_name'],
        'compliance_policy_versions'=>['id','policy_id','version_number','approved_by'],
        'compliance_policy_obligations'=>['version_id','obligation_id'],
    ];
    $schema=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($schema==='') throw new RuntimeException('No se ha seleccionado una base de datos.');
    foreach ($required as $table=>$columns) {
        $present=query($pdo,'SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=? AND TABLE_NAME=?',[$schema,$table])->fetchAll(PDO::FETCH_COLUMN);
        $add('schema_'.$table,!array_diff($columns,$present),'Esquema actualizado: '.$table);
    }
    $add('app_credentials',isset($config['user'],$config['password']) &&
        is_string($config['user']) && $config['user']!=='','Cuenta SQL de la aplicación configurada');
    $add('install_disabled',empty($config['install_token']),'Token de instalación retirado');
    $add('temporary_install_removed',empty($config['install_user']) &&
        empty($config['install_password']),'Credenciales temporales de instalación retiradas');
    $add('private_config',is_string(getenv('AUDITPRO_CONFIG')) &&
        realpath((string)getenv('AUDITPRO_CONFIG'))!==false,'Archivo de configuración privado accesible');
    return $checks;
}
function preflightReady(array $checks): bool {
    foreach ($checks as $check) if (!$check['ok']) return false;
    return true;
}
