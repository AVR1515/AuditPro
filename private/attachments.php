<?php
declare(strict_types=1);

const UPLOAD_LIMIT = 5 * 1024 * 1024;
const ALLOWED_FILES = ['application/pdf'=>'pdf','image/png'=>'png','image/jpeg'=>'jpg','text/plain'=>'txt'];

function storeAttachment(PDO $pdo, int $actor, string $kind, int $parent): void {
    $file = $_FILES['attachment'] ?? [];
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new DomainException('Selecciona un archivo válido de hasta 5 MiB.');
    }
    $content = file_get_contents($file['tmp_name'], false, null, 0, UPLOAD_LIMIT + 1);
    if ($content === false || $content === '' || strlen($content) > UPLOAD_LIMIT) {
        throw new DomainException('El archivo debe pesar entre 1 byte y 5 MiB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($content);
    if (!isset(ALLOWED_FILES[$mime])) throw new DomainException('Solo se admiten PDF, PNG, JPEG y texto plano.');
    $stem = pathinfo(basename(str_replace('\\','/',$file['name'])),PATHINFO_FILENAME);
    $stem = trim(substr(preg_replace('/[^A-Za-z0-9._-]+/','_',$stem),0,160),'.');
    $name = ($stem ?: 'evidencia') . '.' . ALLOWED_FILES[$mime];
    query($pdo,'INSERT INTO attachments(test_id,action_id,uploaded_by,filename,mime,bytes,sha256,content) VALUES (?,?,?,?,?,?,?,?)',
        [$kind === 'test' ? $parent : null, $kind === 'action' ? $parent : null, $actor,$name,$mime,strlen($content),hash('sha256',$content),$content]);
}

function listAttachments(PDO $pdo, string $kind, int $parent): void {
    $column = $kind === 'test' ? 'test_id' : 'action_id';
    $files = query($pdo,"SELECT id,filename,bytes,sha256,created_at FROM attachments WHERE $column=? ORDER BY id",[$parent])->fetchAll();
    if (!$files) return;
    echo '<ul aria-label="Archivos de evidencia">';
    foreach ($files as $file) {
        echo '<li><a href="index.php?download=' . e($file['id']) . '">' . e($file['filename']) . '</a> · ' .
            e($file['bytes']) . ' bytes · ' . e($file['created_at']) .
            '<details><summary>Huella SHA-256</summary><code class="copy">' . e($file['sha256']) . '</code></details></li>';
    }
    echo '</ul>';
}

function downloadAttachment(PDO $pdo, array $user, int $id): never {
    $file = query($pdo,'SELECT b.filename,b.bytes,b.sha256,COALESCE(t.audit_id,at.audit_id) AS audit_id,
        a.owner_id AS action_owner,d.status
        FROM attachments b LEFT JOIN tests t ON t.id=b.test_id LEFT JOIN actions a ON a.id=b.action_id
        LEFT JOIN findings f ON f.id=a.finding_id LEFT JOIN tests at ON at.id=f.test_id
        JOIN audits d ON d.id=COALESCE(t.audit_id,at.audit_id) WHERE b.id=?',[$id])->fetch();
    if (!$file || ($user['role'] === 'responsable' &&
        ((int) $file['action_owner'] !== (int) $user['id'] || !in_array($file['status'],['emitida','cerrada'],true)))) {
        throw new DomainException('Archivo no disponible.');
    }
    accessible($pdo,$user,(int) $file['audit_id']);
    $content = query($pdo,'SELECT content FROM attachments WHERE id=?',[$id])->fetchColumn();
    if (!is_string($content) || strlen($content) !== (int) $file['bytes'] || !hash_equals($file['sha256'],hash('sha256',$content))) {
        throw new RuntimeException('La huella de evidencia no coincide.');
    }
    record($pdo,(int) $user['id'],(int) $file['audit_id'],'evidencia_descargada',(string) $id);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file['filename'] . '"');
    header('Content-Length: ' . $file['bytes']);
    echo $content;
    exit;
}
