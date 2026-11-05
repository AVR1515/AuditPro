<?php
declare(strict_types=1);

function revisionsForAudit(PDO $pdo, string $kind, int $auditId, ?int $owner = null): array {
    if ($kind === 'test') {
        $sql = 'SELECT r.test_id AS parent_id,r.result_text AS content,r.imported,r.created_at,u.name
            FROM test_revisions r JOIN tests t ON t.id=r.test_id JOIN users u ON u.id=r.author_id
            WHERE t.audit_id=?';
    } else {
        $sql = 'SELECT r.action_id AS parent_id,r.evidence AS content,r.imported,r.created_at,u.name
            FROM action_revisions r JOIN actions a ON a.id=r.action_id JOIN findings f ON f.id=a.finding_id
            JOIN tests t ON t.id=f.test_id JOIN users u ON u.id=r.author_id WHERE t.audit_id=?';
    }
    $params = [$auditId];
    if ($owner !== null) {
        $sql .= ' AND a.owner_id=?';
        $params[] = $owner;
    }
    $result = [];
    foreach (query($pdo,$sql . ' ORDER BY r.id DESC',$params) as $revision) $result[$revision['parent_id']][] = $revision;
    return $result;
}

function showRevisions(array $revisions): void {
    if (!$revisions) return;
    echo '<details><summary>Historial de versiones (' . count($revisions) . ')</summary>';
    foreach ($revisions as $revision) {
        $stamp = $revision['imported'] ? 'Versión recuperada durante la actualización' : $revision['created_at'];
        echo '<p class="muted">' . e($revision['name']) . ' · ' . e($stamp) . '</p>';
        echo '<p class="copy">' . e($revision['content']) . '</p>';
    }
    echo '</details>';
}
