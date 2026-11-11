# Verificación real de respaldos y restauración

Esta guía se ejecuta **en el servidor administrado por la organización**.
Nunca se recupera una copia con datos reales dentro de GitHub Actions ni se
publican respaldos, claves o capturas con datos personales en el repositorio.

1. Congelar la versión SHA instalada y anotar la versión del motor MySQL/MariaDB.
2. Con un operador autorizado, crear un respaldo consistente y cifrado de la
   base completa (tablas, índices, adjuntos BLOB y bitácora). Los procedimientos
   `mysqldump --single-transaction` o su equivalente dependen del motor
   y de los permisos de la organización.
3. Preparar una instancia **aislada**, con nombre y credenciales distintos
   de producción; nunca ejecutar `tests/smoke.php` contra datos reales.
4. Restaurar el respaldo e iniciar AuditPro con configuración privada distinta.
5. Comparar el recuento de expedientes, evidencias, revisiones, aprobaciones,
   bitácora y volúmenes de bytes con el origen (sin exponer el contenido).
6. Descargar evidencias de prueba expresamente autorizadas y verificar sus
   huellas SHA-256; comprobar que evidencias purgadas siguen sin contenido.
7. Comprobar que la restauración conserva la política de retención y los
   bloqueos legales y no reactiva credenciales temporales de instalación.
8. Registrar tiempo de recuperación, pérdida de datos estimada, incidencias
   y decisión del responsable de continuidad.

## Acta de prueba

- Fecha, servidor aislado y responsable:
- SHA de AuditPro y versión SQL:
- Número de expedientes / adjuntos / bitácoras:
- Verificación SHA-256:
- Restauración y tiempo utilizado:
- Incidencias:
- Aceptación del responsable:
