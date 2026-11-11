<?php
declare(strict_types=1);

/**
 * Declaraciones de evidencia, NO certificados de producción.
 * Solo el rol autorizado registra su propia valoración, sin editar historial.
 */
const PILOT_GATES = [
 'scope'=>['admin','supervisor'],
 'retention'=>['admin'],
 'actions'=>['supervisor'],
 'notifications'=>['admin'],
 'visual_pilot'=>['admin','supervisor'],
 'deployment'=>['admin'],
 'grc'=>['riesgos','cumplimiento'],
];
const PILOT_GATE_NAMES = [
 'scope'=>'Alcance y permisos',
 'retention'=>'Retención y restauración',
 'actions'=>'Acciones y riesgos aceptados',
 'notifications'=>'Recordatorios y escalaciones',
 'visual_pilot'=>'Piloto y accesibilidad',
 'deployment'=>'Servidor y recuperación',
 'grc'=>'Riesgos y cumplimiento',
];

function canSubmitPilotGate(array $user,string $gate): bool {
    return in_array($user['role'],PILOT_GATES[$gate] ?? [],true);
}
function savePilotGateSignoff(PDO $pdo,array $user): void {
    $gate=choice('gate_key',array_keys(PILOT_GATES));
    if (!canSubmitPilotGate($user,$gate))
        throw new DomainException('Este rol no puede registrar la evaluación del control solicitado.');
    $result=choice('result',['cumple','no_cumple']);
    $sha=strtolower(field('version_sha',40));
    if (!preg_match('/^[a-f0-9]{40}$/D',$sha))
        throw new DomainException('Indica el SHA completo de 40 caracteres que se probó.');
    $reference=field('evidence_reference',2000);
    $notes=field('notes',12000);
    query($pdo,'INSERT INTO pilot_gate_signoffs
        (gate_key,reviewer_id,reviewer_role,result,version_sha,evidence_reference,notes)
        VALUES (?,?,?,?,?,?,?)',
        [$gate,(int)$user['id'],$user['role'],$result,$sha,$reference,$notes]);
    record($pdo,(int)$user['id'],null,'evidencia_piloto',
        'Control '.$gate.' · '.$result.' · versión '.$sha);
}
function pilotGateHistory(PDO $pdo,array $user): array {
    if (!in_array($user['role'],['admin','supervisor','riesgos','cumplimiento'],true))
        throw new DomainException('El historial de aceptación no está disponible para este rol.');
    $global=$user['role']==='admin';
    return query($pdo,'SELECT s.*,u.name AS reviewer_name
        FROM pilot_gate_signoffs s JOIN users u ON u.id=s.reviewer_id
        '.($global?'':'WHERE s.reviewer_id=?').'
        ORDER BY s.id DESC LIMIT 200',
        $global?[]:[(int)$user['id']])->fetchAll();
}
