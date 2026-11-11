# AuditPro — puertas de liberación a producción

Estado inicial: **NO APROBADO**. Las declaraciones por rol se registran en **Evidencias del piloto** (migración 028), identificadas por SHA completo y acta. Una declaración no sustituye la revisión de la evidencia ni las firmas externas. El código en `main` y las pruebas automáticas
no prueban por sí solos que AuditPro sea seguro para operar con datos reales.

## Puerta 1 — Alcance y roles (roadmap 01 y 19)

- [ ] El dueño funcional de Auditoría firma el alcance, los criterios de materialidad y el flujo de pruebas.
- [ ] Riesgos aprueba las matrices probabilidad/impacto y cada umbral KRI de los procesos piloto.
- [ ] Cumplimiento aprueba marcos, obligaciones, atestaciones y procedimientos de excepción.
- [ ] Usuarios reales de admin, supervisor, supervisor restringido, auditor, responsable, riesgos y cumplimiento ejecutan la matriz de pruebas en `docs/pilot-checklist.md`.
- [ ] Se prueba una negativa: un usuario no obtiene acceso a expediente, evidencia, riesgo ni KRI de otra unidad manipulando IDs.

**Evidencia requerida:** acta fechada, participantes, versión SHA, resultados y discrepancias.

## Puerta 2 — Evidencia y retención (roadmap 07)

- [ ] Jurídico/DPO aprueba el plazo mínimo y las excepciones de conservación de la organización.
- [ ] Se verifica un respaldo y una restauración completos, incluidos BLOB y SHA-256.
- [ ] Se comprueba que un bloqueo legal impide purgar; que el purgado se audita y no permite descarga.
- [ ] Una segunda cuenta administradora autoriza o rechaza la solicitud de purgado; una autorización antigua o cancelada no permite borrar contenido.
- [ ] Se establece y documenta el ciclo de vida de copias de respaldo, incluso cuando un contenido ha sido purgado.

**Evidencia requerida:** política aprobada, acta jurídica y bitácora de restauración.

## Puerta 3 — Acciones y alertas (roadmap 12 y 15)

- [ ] Supervisión recorre la creación, evidencia, prórroga, devolución, verificación y cierre de una acción.
- [ ] Se revalida un riesgo aceptado mediante una cuenta distinta del solicitante y se conserva el historial.
- [ ] Se prueba `php private/remind.php --dry-run` y la tarea programada real, sin avisos duplicados.
- [ ] Administración confirma en «Preparación del lanzamiento» que el último éxito del proceso es reciente (máximo 36 horas).
- [ ] Se verifica el tratamiento de prórrogas, acciones cerradas, escalaciones y buzones de destinatarios reales.
- [ ] Se revisa que la prueba CLI del proceso diario con datos ficticios esté aprobada en GitHub Actions.

**Evidencia requerida:** registro por rol, fecha y captura auténtica de la aplicación.

## Puerta 4 — Piloto visual y funcional (roadmap 17)

- [ ] Instalar en el entorno piloto mediante migraciones hasta la 028 y ejecutar `private/preflight-cli.php`.
- [ ] Revisar contraste, navegación con teclado, lectores de pantalla y formularios con errores.
- [ ] Adjuntar capturas **reales** (no diseños generados) de login, expedientes, KRI, GRC, políticas, bandeja, permisos por unidad y retención.
- [ ] Documentar incidentes críticos, responsables y su solución; obtener la aceptación del dueño del proceso.

**Evidencia requerida:** acta de aceptación o rechazo, incidentes y SHA ensayado.

## Puerta 5 — Servidor y recuperación (roadmap 18)

- [ ] El administrador comprueba certificado TLS y las cabeceras ejecutando `bash tests/deployment-probe.sh https://dominio/auditpro`.
- [ ] Se confirma en un navegador externo la denegación de `private/`, SQL, configuración y `.git/`.
- [ ] Se comprueba que la prueba automatizada de restauración con datos ficticios de GitHub Actions pasó para el SHA instalado.
- [ ] Se prueba restauración íntegra desde una copia cifrada **real** en un servidor aislado y se documenta tiempo de recuperación.
- [ ] Se prueban privilegios mínimos de la cuenta SQL, cron, logs y procedimiento de reversión.
- [ ] No se usa `AUDITPRO_DEV=1` en la instalación de producción.

**Evidencia requerida:** salida del preflight, sonda HTTPS, respaldo/restore y aprobación de infraestructura.

## Decisión

- **Versión SHA:** ______________________
- **Fecha y entorno:** ______________________
- **Responsable Auditoría:** ______________________
- **Responsable Riesgos/Cumplimiento:** ______________________
- **Responsable Seguridad/Jurídico:** ______________________
- **Responsable Infraestructura:** ______________________
- **Decisión:** [ ] Aprobado [ ] Rechazado [ ] Pendiente
- **Evidencias y enlace a incidentes:** ______________________

**Cualquier casilla pendiente mantiene la liberación pendiente.** La comparación de las declaraciones por SHA en «Preparación del lanzamiento» es una ayuda a la revisión y no sustituye las firmas. Las declaraciones de la aplicación se revisan por separado junto con actas firmadas y el SHA efectivamente instalado.
