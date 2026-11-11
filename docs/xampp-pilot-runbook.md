# AuditPro — ejecución del piloto en XAMPP y cierre de las siete fases

**Estado inicial: pendiente de aprobación.** Este procedimiento no afirma que se haya
ejecutado en un servidor real. Está destinado al responsable de infraestructura,
Auditoría, Riesgos, Cumplimiento y Jurídico.

## 1. Identificar el código y proteger el entorno

1. Anota el **SHA de 40 caracteres** de la versión de `main` que se instalará.
2. Descarga o actualiza el repositorio en un entorno piloto, sin datos de producción.
3. Respaldar la base existente **antes** de las migraciones. Nunca ejecutar
   `tests/smoke.php` contra una base real: el script prepara y modifica
   exclusivamente `auditpro_test`.
4. Configura `AUDITPRO_CONFIG` hacia un archivo fuera de `public/` (idealmente
   también fuera de `htdocs/`) y limita sus permisos al operador del servidor.
   Mantén el token de instalación deshabilitado tras la instalación.
5. Ejecuta las migraciones pendientes, en orden, hasta
   `private/upgrade-028.sql`. No volver a ejecutar manualmente una migración
   ya aplicada. Utiliza el instalador o la bitácora de versiones para verificar.
6. Desde consola de PHP del servidor, con las variables de entorno correctas:
   `php private/preflight-cli.php`. Soluciona cualquier resultado «FALTA».

## 2. Configurar y confirmar el proceso diario

**Windows/XAMPP:** abre *Programador de tareas → Crear tarea*, con una cuenta
de servicio restringida que pueda leer la configuración privada y acceder a
MySQL. Programa una ejecución diaria, por ejemplo 07:00. La acción debe
ejecutar la ruta absoluta a `C:\xampp\php\php.exe` con argumento
`C:\xampp\htdocs\auditpro\private\remind.php` y directorio de inicio
`C:\xampp\htdocs\auditpro`. Configura `AUDITPRO_CONFIG` para esa cuenta
y comprueba que también es accesible al proceso no interactivo. No escribas
contraseñas en los argumentos ni guardes el archivo de configuración en
`public/`.

**Linux:** programa diariamente el comando PHP con una cuenta de servicio
sin privilegios de administración, una variable `AUDITPRO_CONFIG` adecuada,
logs privados, reintento controlado y alertas sobre el código de salida.

Ejecuta primero `php private/remind.php --dry-run`; después, en un entorno de
prueba, la ejecución real. Comprueba **Bandeja → Salud de la tarea diaria**:
el último éxito debe estar dentro de 36 horas. Confirma las notificaciones con
usuarios destinatarios y que ejecuciones repetidas no crean duplicados.

## 3. Seguridad de acceso y servidor

- Administrador: prueba configuraciones de unidad y una cuenta supervisora
  restringida. Comprueba que otra unidad no aparece ni en expedientes, KRI,
  adjuntos, obligaciones, pruebas ni exportaciones.
- Auditor: prueba ejecución, adjuntos, devolución, cierre de solicitudes y
  emisión de informe. Asegura que no aprueba sus propias revisiones.
- Responsable: registra evidencia y prórrogas; no debe ver papeles de trabajo
  ajenos.
- Riesgos y Cumplimiento: recorren KRI, obligaciones, políticas y excepciones;
  cada especialista debe conservar los permisos de su rol.
- Ejecuta `bash tests/deployment-probe.sh https://dominio/auditpro` **contra el
  sitio real**, con un certificado válido. El probe falla si no hay HSTS, CSP
  u otra cabecera requerida, o si puede abrir archivos privados.
- Confirma `AUDITPRO_DEV` desactivado en producción y que HTTPS funciona
  detrás del proxy real si lo hubiera.

## 4. Retención y recuperación

- Jurídico aprueba plazos, bases de conservación y reglas de copias de respaldo.
- En una evidencia sintética elegible: administrador A solicita eliminación,
  administrador B la decide. A no puede aprobar su solicitud y las autorizaciones
  caducan tras siete días; cambiar bloqueo o plazo cancela el permiso.
- Confirma que el purgado no permite descargar el contenido y conserva bitácora.
- **Restaura un respaldo auténtico cifrado en una instancia aislada**. Registra
  fecha, SHA, motor SQL, recuentos, verificación de adjuntos SHA-256, duración
  real, incidencias y autorización. La prueba automática de CI usa solo datos
  ficticios y no sustituye este paso.

## 5. Accesibilidad y aceptación por los siete controles

Prueba navegación completa con Tab/Shift+Tab/Enter, salto al contenido,
contraste claro y oscuro, escala del navegador, formularios con errores,
lector de pantalla y comportamiento móvil. Guarda capturas **reales** y
registros de incidencia del entorno piloto, nunca imágenes generadas.

Usa **Evidencias del piloto** para registrar declaraciones por rol, con el
resultado conforme/no conforme, acta y SHA completo; después, en
**Preparación del lanzamiento**, consulta ese mismo SHA. Comprueba que las
declaraciones pertenecen a la versión instalada y que las más recientes
«no cumple» predominan. La aplicación **no** debe declarar el lanzamiento
aprobado automáticamente.

| Fase | Evidencia indispensable |
|---|---|
| 01 Alcance | Acta firmada de Auditoría, Riesgos y Cumplimiento |
| 07 Retención | Aprobación jurídica y restauración real |
| 12 Acciones | Recorrido y aceptación de responsables/supervisión |
| 15 Avisos | Cron funcionando y recepción real de destinatarios |
| 17 Piloto | Capturas reales, accesibilidad, incidencias y acta |
| 18 Producción | TLS, permisos SQL, respaldo restaurado y reversión |
| 19 GRC | Umbrales KRI, controles y políticas aprobados por propietarios |

**Condición final:** ninguna fase se marca «Entregado» por tener solo código o
una declaración de un usuario. La decisión del responsable debe contrastarse
con los documentos `docs/production-release-gates.md` y
`docs/restore-verification.md`.
