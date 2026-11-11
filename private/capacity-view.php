<?php
$capacityYear = filter_var($_GET['capacity_year'] ?? date('Y'),FILTER_VALIDATE_INT);
if ($capacityYear === false || $capacityYear < 2000 || $capacityYear > 2100) $capacityYear = (int) date('Y');
$capacityRows = capacityRows($pdo,$capacityYear);
$capacitySet = array_filter($capacityRows,static fn(array $row): bool => $row['available_minutes'] !== null);
$overAllocated = count(array_filter($capacitySet,static fn(array $row): bool => (int) $row['planned_minutes'] > (int) $row['available_minutes']));
?>
<section><h2>Capacidad del equipo auditor</h2>
<p class="muted">Compara la disponibilidad anual declarada con las horas planificadas de los expedientes del mismo año. La carga no se limita automáticamente: las sobreasignaciones requieren revisión por supervisión.</p>
<form method="get" class="filters">
<input type="hidden" name="view" value="plans">
<label>Año de capacidad<input type="number" name="capacity_year" min="2000" max="2100" value="<?= e($capacityYear) ?>" required></label>
<button>Consultar</button></form>
<dl class="metrics"><div><dt>Auditores activos</dt><dd><?= count($capacityRows) ?></dd></div>
<div><dt>Capacidad sin registrar</dt><dd><?= count($capacityRows)-count($capacitySet) ?></dd></div>
<div><dt>Sobreasignados</dt><dd><?= $overAllocated ?></dd></div></dl>
<?php if (!$capacityRows): ?><p class="muted">Crea cuentas de auditor para gestionar capacidad.</p><?php else: ?>
<div class="table"><table><thead><tr><th scope="col">Auditor</th><th scope="col">Auditorías</th><th scope="col">Disponible</th><th scope="col">Planificado</th><th scope="col">Registrado</th><th scope="col">Balance</th></tr></thead><tbody>
<?php foreach ($capacityRows as $row):
$available = $row['available_minutes'] === null ? null : (int) $row['available_minutes'];
$planned = (int) $row['planned_minutes'];
$remaining = $available === null ? null : $available-$planned;
?>
<tr><td><?= e($row['name']) ?></td><td><?= e($row['audit_count']) ?></td>
<td><?= $available === null ? 'Sin configurar' : e(displayHours($available)) ?></td>
<td><?= e(displayHours($planned)) ?></td><td><?= e(displayHours((int) $row['worked_minutes'])) ?></td>
<td><?= $remaining === null ? 'Sin referencia' : ($remaining < 0 ? 'Exceso: '.e(displayHours(-$remaining)) : 'Libre: '.e(displayHours($remaining))) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<details><summary>Registrar o actualizar capacidad</summary>
<?php form('save_capacity'); ?>
<label>Auditor<select name="auditor_id" required><?php foreach ($capacityRows as $row): ?>
<option value="<?= e($row['id']) ?>"><?= e($row['name']) ?></option><?php endforeach; ?></select></label>
<label>Año<input name="year" type="number" min="2000" max="2100" value="<?= e($capacityYear) ?>" required></label>
<label>Horas disponibles en el año<input name="hours" type="number" min="0" max="10000" required></label>
<button>Guardar capacidad</button></form></details>
</section>
