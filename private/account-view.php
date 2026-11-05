<section><h2>Contraseña de acceso</h2>
<p>Al guardar, se cerrarán las sesiones abiertas de esta cuenta.</p>
<?php form('password'); ?>
<label>Contraseña actual<input type="password" name="current" autocomplete="current-password" required></label>
<label>Contraseña nueva<input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
<label>Confirmar contraseña<input type="password" name="confirm" autocomplete="new-password" minlength="12" required></label>
<button>Guardar contraseña</button></form></section>
