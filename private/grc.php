<?php
declare(strict_types=1);

const GRC_OPS = ['grc_risk','grc_control','grc_obligation','grc_link_risk','grc_link_obligation','grc_link_test'];
const FREQUENCIES = ['mensual','trimestral','anual','por_evento'];

function isGrcSpecialist(array $user): bool {
    return in_array($user['role'],['riesgos','cumplimiento'],true);
}
function canGrc(array $user): bool {
    return manage($user) || isGrcSpecialist($user);
}
function canRisk(array $user): bool {
    return manage($user) || $user['role'] === 'riesgos';
}
function canCompliance(array $user): bool {
    return manage($user) || $user['role'] === 'cumplimiento';
}

function saveGrc(PDO $pdo, array $user, string $op): void {
    $allowed = match ($op) {
        'grc_risk','grc_link_risk' => canRisk($user),
        'grc_obligation','grc_link_obligation' => canCompliance($user),
        'grc_control' => canGrc($user),
        'grc_link_test' => manage($user),
    };
    if (!$allowed) throw new DomainException('Tu rol no puede realizar esta operación.');
    $auditId = null;
    $detail = '';
    switch ($op) {
        case 'grc_risk':
            $unit = positiveId('unit');
            $inherent = positiveId('inherent');
            $residual = positiveId('residual');
            $appetite = positiveId('appetite');
            if (max($inherent,$residual,$appetite)>25 || !query($pdo,'SELECT 1 FROM auditable_units WHERE id=?',[$unit])->fetchColumn()) {
                throw new DomainException('Selecciona una unidad y puntuaciones entre 1 y 25.');
            }
            query($pdo,'INSERT INTO grc_risks(unit_id,title,owner_name,inherent,residual,appetite,treatment,review_on) VALUES (?,?,?,?,?,?,?,?)',
                [$unit,field('title'),field('owner_name'),$inherent,$residual,$appetite,field('treatment',12000),dateField('review')]);
            break;
        case 'grc_control':
            query($pdo,'INSERT INTO grc_controls(title,owner_name,frequency,description) VALUES (?,?,?,?)',
                [field('title'),field('owner_name'),choice('frequency',FREQUENCIES),field('description',12000)]);
            break;
        case 'grc_obligation':
            $framework = field('framework');
            $reference = field('reference',120);
            if (query($pdo,'SELECT 1 FROM grc_obligations WHERE framework=? AND reference_code=?',[$framework,$reference])->fetchColumn()) {
                throw new DomainException('La referencia ya existe en ese marco.');
            }
            query($pdo,'INSERT INTO grc_obligations(framework,reference_code,title,owner_name,review_on) VALUES (?,?,?,?,?)',
                [$framework,$reference,field('title'),field('owner_name'),dateField('review')]);
            break;
        default:
            [$table,$column,$parentTable] = [
                'grc_link_risk'=>['grc_risk_controls','risk_id','grc_risks'],
                'grc_link_obligation'=>['grc_obligation_controls','obligation_id','grc_obligations'],
                'grc_link_test'=>['grc_control_tests','test_id','tests'],
            ][$op];
            $parent = positiveId('parent');
            $control = positiveId('control');
            if (!query($pdo,"SELECT 1 FROM $parentTable WHERE id=?",[$parent])->fetchColumn() ||
                !query($pdo,'SELECT 1 FROM grc_controls WHERE id=?',[$control])->fetchColumn()) {
                throw new DomainException('Selecciona dos registros válidos.');
            }
            if ($op === 'grc_link_test') {
                $auditId = (int) query($pdo,'SELECT audit_id FROM tests WHERE id=?',[$parent])->fetchColumn();
                $audit = accessible($pdo,$user,$auditId,true);
                if (!in_array($audit['status'],['en_campo','en_revision'],true)) throw new DomainException('Vincula pruebas en campo o revisión.');
            }
            query($pdo,"INSERT INTO $table($column,control_id) VALUES (?,?) ON DUPLICATE KEY UPDATE control_id=control_id",[$parent,$control]);
            $detail = "$parent → control $control";
    }
    record($pdo,(int) $user['id'],$auditId,$op,$detail ?: (string) $pdo->lastInsertId());
}

function grcMappings(PDO $pdo, array $user): array {
    $sql = [
        'risks'=>'SELECT m.control_id,r.title FROM grc_risk_controls m JOIN grc_risks r ON r.id=m.risk_id ORDER BY r.title',
        'obligations'=>"SELECT m.control_id,CONCAT(o.framework,' / ',o.reference_code,' · ',o.title) AS title FROM grc_obligation_controls m JOIN grc_obligations o ON o.id=m.obligation_id ORDER BY o.framework,o.reference_code",
        'tests'=>'SELECT m.control_id,t.title,t.status,t.audit_id FROM grc_control_tests m JOIN tests t ON t.id=m.test_id ORDER BY t.id',
    ];
    if (!canRisk($user)) unset($sql['risks']);
    if (!canCompliance($user)) unset($sql['obligations']);
    if (!manage($user)) unset($sql['tests']);
    $result = [];
    foreach ($sql as $type=>$statement) {
        foreach ($pdo->query($statement) as $row) $result[$row['control_id']][$type][] = $row;
    }
    return $result;
}
