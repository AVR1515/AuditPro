<?php
$supervisors=$pdo->query("SELECT id,name,email,unit_restricted FROM users WHERE role='supervisor' AND active=1 ORDER BY name")->fetchAll();
$units=$pdo->query('SELECT id,name FROM auditable_units ORDER BY name')->fetchAll();
$grants=$pdo->query('SELECT g.*,u.name AS supervisor_name,a.name AS unit_name
    FROM supervisor_unit_grants g JOIN users u ON u.id=g.user_id
    JOIN auditable_units a ON a.id=g.unit_id ORDER BY u.name,a.name')->fetchAll();
?>
<section><h2>Permisos por unidad auditable</h2>
<p class="muted">Los administradores tienen acceso global. Los supervisores pueden restringirse a las auditorías vinculadas a evaluaciones de unidades autorizadas. Sin acceso concedido, un supervisor restringido no puede abrir ningún expediente. Las auditorías de captura manual sin unidad no son visibles para supervisores restringidos.</p>
<?php if (!$supervisors): ?><p class="muted">No hay supervisores activos.</p><?php else: ?>
<div class="table"><table><thead><tr><th scope="col">Supervisor</th><th scope="col">Correo</th><th scope="col">Ámbito</th></tr></thead><tbody>
<?php foreach ($supervisors as $s): ?><tr><td><?= e($s['name']) ?></td><td><?= e($s['email']) ?></td>
<td><?= $s['unit_restricted'] ? 'Restringido por unidad' : 'Global (compatibilidad)' ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<details><summary>Activar o desactivar restricción</summary>
<?php form('unit_scope'); ?>
<label>Supervisor<select name="supervisor_id" required><?php people($supervisors); ?></select></label>
<label>Acceso<select name="restricted"><option value="1">Restringido a unidades autorizadas</option><option value="0">Global</option></select></label>
<p class="muted">Asigna las unidades antes de activar la restricción; la activación sin permisos deja al supervisor sin expedientes visibles.</p>
<button>Guardar alcance</button></form></details>
<?php endif; ?>
<?php if ($supervisors && $units): ?><details><summary>Otorgar o revocar permiso</summary>
<?php form('unit_grant'); ?>
<label>Supervisor<select name="supervisor_id"><?php people($supervisors); ?></select></label>
<label>Unidad<select name="unit_id"><?php people($units); ?></select></label>
<button>Otorgar acceso</button></form>
<p class="muted">Para revocar un acceso, utiliza el control de su fila en la tabla siguiente.</p></details><?php endif; ?>
<?php if ($grants): ?><h3>Unidades concedidas</h3>
<div class="table"><table><thead><tr><th scope="col">Supervisor</th><th scope="col">Unidad</th><th scope="col">Acción</th></tr></thead><tbody>
<?php foreach ($grants as $g): ?><tr><td><?= e($g['supervisor_name']) ?></td><td><?= e($g['unit_name']) ?></td><td>
<?php form('unit_revoke',['supervisor_id'=>$g['user_id'],'unit_id'=>$g['unit_id']],true); ?>
<button>Revocar</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
