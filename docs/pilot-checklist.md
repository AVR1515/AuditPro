# AuditPro — lista de aceptación para piloto

Este documento no certifica el despliegue ni reemplaza la aprobación del equipo de seguridad, legal y auditoría.

## Antes de iniciar

- [ ] Ejecutar `php private/preflight-cli.php` desde consola; todos los controles automatizables muestran OK.
- [ ] Resguardar una copia coherente de la base y verificar una restauración en un entorno independiente.
- [ ] Confirmar `https://` y que `/private/`, archivos SQL, Git y la configuración privada no son accesibles por navegador.
- [ ] Confirmar permisos SQL mínimos, usuarios únicos y política de contraseñas.
- [ ] Verificar los resultados de pruebas HTTP, de MySQL y sintaxis del commit instalado.
- [ ] Registrar aprobación del responsable para el plazo de retención y cualquier eliminación irreversible.
- [ ] Verificar programación y bitácora de `private/remind.php`.

## Recorridos por rol

- [ ] **Admin:** crea auditor, supervisor y responsable; asigna una unidad y restringe a un supervisor.
- [ ] **Supervisor restringido:** puede abrir un expediente de su unidad; NO puede abrir otro, descargar sus adjuntos ni exportarlo.
- [ ] **Supervisor restringido:** no puede administrar permisos, importar obligaciones ni consultar tableros globales.
- [ ] **Auditor:** registra prueba y evidencia, responde solicitudes; no aprueba su propia prueba.
- [ ] **Supervisor:** revisa una prueba, solicita evidencia y emite el informe tras cerrar solicitudes.
- [ ] **Responsable:** aporta evidencia de su acción, pero no visualiza acciones ajenas ni papeles de trabajo.
- [ ] **Supervisión independiente:** revisa cierre, prórroga, aceptación de riesgo y excepción de cumplimiento.
- [ ] **Cumplimiento:** atesta una obligación y conserva historial; no accede a funciones de auditor.
- [ ] **Políticas:** redacta versión, vincula una obligación y verifica que solo otra cuenta de supervisión apruebe; comprueba que versiones aprobadas sean inmutables.
- [ ] **Supervisor restringido:** consulta «Mis unidades y riesgos» y confirma que no aparecen riesgos de otras unidades.
- [ ] **Supervisor restringido:** actualiza un residual y mide un KRI autorizado; confirma que IDs manipulados de otra unidad no modifican datos.
- [ ] **Supervisor restringido:** revisa la cobertura histórica de sus unidades y verifica que la tabla no contenga unidades ajenas.
- [ ] **Controles compartidos:** vincular un mismo control a riesgos de dos unidades; el supervisor restringido ve el control de su riesgo pero no las pruebas del expediente ajeno ni controles exclusivos de otra unidad.
- [ ] **Supervisor restringido:** no puede crear ni archivar indicadores globales.
- [ ] **Riesgos:** mide KRI, revisa el riesgo residual y verifica alertas cuando hay exceso de umbral.

## Datos y retención

- [ ] Descargar un adjunto autorizado y comprobar su SHA-256.
- [ ] Denegar descarga de un adjunto fuera de unidad o sin permiso.
- [ ] Confirmar que el bloqueo legal impide purgar incluso con el plazo vencido.
- [ ] Confirmar que un archivo sin fecha de retención se conserva indefinidamente.
- [ ] Comprobar que un archivo purgado mantiene metadatos y bitácora pero no puede descargarse.
- [ ] Confirmar qué ocurre con copias en respaldos y sistemas externos.

## Aceptación del piloto

Registrar fecha, versión SHA, servidor, participantes, incidencias, evidencias de pruebas, responsables, correcciones y firma de aprobación.

**No marcar el piloto como aprobado basándose únicamente en GitHub Actions.**
