<?php $preview = $_SESSION['obligation_import'] ?? null; ?>
<section><h2>Importar obligaciones</h2>
<p>Usa un CSV UTF-8 separado por comas, con fechas en formato AAAA-MM-DD y hasta 500 registros.</p>
<p><code class="copy">marco,referencia,titulo,responsable,revision</code></p>
<?php form('preview_obligations',[],false,true); ?>
<label>Archivo CSV<input type="file" name="obligations" accept=".csv,text/csv" required></label>
<button>Ver vista previa</button></form></section>
<?php if ($preview): ?>
<section><h2>Vista previa · <?= count($preview['rows']) ?> registros</h2>
<p>Si una referencia del mismo marco está repetida, se revierte toda la importación.</p>
<div class="table"><table><thead><tr><?php foreach (['Marco','Referencia','Título','Responsable','Revisión'] as $column): ?>
<th scope="col"><?= e($column) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($preview['rows'] as $row): ?><tr><?php foreach ($row as $value): ?><td><?= e($value) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table></div>
<div class="bar">
<?php form('confirm_obligations',['import_token'=>$preview['token']],true); ?><button>Confirmar importación</button></form>
<?php form('cancel_obligations',['import_token'=>$preview['token']],true); ?><button>Cancelar</button></form>
</div></section><?php endif; ?>
