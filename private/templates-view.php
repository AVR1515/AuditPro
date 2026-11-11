<?php
$templates = templateCatalog($pdo);
?>
<section><h2>Catálogo de procedimientos</h2>
<p class="muted">Las plantillas permiten reutilizar procedimientos sin modificar pruebas de expedientes anteriores. Solo supervisión puede publicar o archivar plantillas.</p>
<?php if ($manager): ?>
<details><summary>Crear plantilla</summary>
<?php form('template_create'); ?>
<label>Nombre de la prueba<input name="title" maxlength="180" required></label>
<label>Categoría o proceso<input name="category" maxlength="120" required></label>
<label>Procedimiento estándar<textarea name="procedure" maxlength="12000" required></textarea></label>
<button>Guardar plantilla</button></form></details>
<?php endif; ?>
<?php if (!$templates): ?><p class="muted">No hay plantillas activas.</p><?php endif; ?>
<?php foreach ($templates as $template): ?>
<article>
<h3><?= e($template['title']) ?></h3>
<p class="muted"><?= e($template['category']) ?> · Publicada por <?= e($template['creator']) ?></p>
<?php copyBlock('Procedimiento',$template['procedure_text']); ?>
<?php if ($manager): ?>
<?php form('template_archive',['template_id'=>$template['id']],true); ?>
<button>Archivar plantilla</button></form>
<?php endif; ?>
</article>
<?php endforeach; ?></section>
