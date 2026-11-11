# AuditPro

Aplicación PHP y SQL para gestionar expedientes de auditoría interna. El [roadmap interactivo](roadmap-auditpro.html) se puede abrir directamente en el navegador.

## Instalación

1. Instala PHP 8.2 o superior con PDO MySQL y un servidor MySQL o MariaDB. Configura HTTPS y apunta la raíz web exclusivamente a `public/`.
2. Copia `private/config.example.php` a un archivo fuera de la raíz web. Restringe su lectura al usuario de PHP y configura la ruta absoluta en la variable de entorno `AUDITPRO_CONFIG`.
3. Prepara una cuenta SQL temporal con permisos para crear la base y sus tablas, consultar el esquema e insertar datos iniciales. Crea una cuenta independiente para la app con permisos SELECT, INSERT y UPDATE sobre el esquema dedicado. Otorga solo SELECT e INSERT en `audit_log` y solo SELECT en `installation` y `catalog`. Si el motor exige que existan las tablas para otorgar permisos específicos, configura esos permisos tras la instalación.
4. Rellena ambas cuentas y una clave aleatoria de instalación en la configuración privada. Abre `install.php`, ingresa la clave y crea tu cuenta administradora. El instalador crea las tablas, precarga roles, severidades y estados, y registra la instalación para bloquear intentos posteriores.
5. Retira la clave y las credenciales temporales de la configuración, revoca la cuenta temporal y verifica el acceso con la cuenta de aplicación. Haz respaldos de la base de datos y de la configuración privada.

## Recorrido disponible

Crea un auditor y un responsable desde la cuenta administradora. Registra un expediente y aprueba su paso a trabajo de campo. En la cuenta del auditor documenta pruebas y hallazgos. Revisa las pruebas y asigna acciones con la cuenta supervisora o administradora. Emite el informe y usa la cuenta responsable para presentar la descripción y las referencias de evidencia. La cuenta supervisora o administradora verifica las acciones y cierra la auditoría.

Las pruebas y acciones admiten archivos PDF, PNG, JPEG y TXT de hasta 5 MiB. Se guardan en SQL con huella SHA-256 y el acceso se verifica antes de cada descarga. Inclúyelos en el respaldo de la base. Habilita `fileinfo`, configura `upload_max_filesize` a 5M o más, `post_max_size` a un valor superior y `max_allowed_packet` de SQL a 8 MiB o más. Otorga a la app SELECT e INSERT sobre `attachments`.

Para actualizar una instalación anterior, respalda la base, ejecuta en orden `private/upgrade-002.sql` , `private/upgrade-003.sql` `private/upgrade-004.sql` `private/upgrade-005.sql` `private/upgrade-006.sql` `private/upgrade-007.sql` `private/upgrade-008.sql`, `private/upgrade-009.sql` `private/upgrade-010.sql` `private/upgrade-011.sql` `private/upgrade-012.sql` `private/upgrade-013.sql` `private/upgrade-014.sql` `private/upgrade-015.sql` `private/upgrade-016.sql` `private/upgrade-017.sql` `private/upgrade-018.sql`, `private/upgrade-019.sql`, `private/upgrade-020.sql` y `private/upgrade-021.sql` en el esquema de AuditPro con una cuenta de mantenimiento, aplica los permisos de adjuntos, de riesgos y de universo (SELECT e INSERT en `auditable_units`, `risk_assessments` y `audit_assessment_links`) y luego publica los nuevos archivos. En las tablas con prefijo `grc_`, otorga SELECT e INSERT y permite UPDATE en las tres tablas de vínculos para los reintentos idempotentes. Otorga SELECT e INSERT sobre `annual_plan_items` y SELECT, INSERT y UPDATE sobre `annual_plans`. Otorga SELECT e INSERT sobre `audit_time` y SELECT, INSERT y UPDATE sobre `audit_schedule`. Otorga SELECT, INSERT y UPDATE sobre `action_reminders`. Otorga SELECT e INSERT sobre `test_revisions` y `action_revisions`. En una instalación limpia el instalador ya aplica ese SQL.

Supervisión puede registrar unidades auditables, crear evaluaciones históricas con la escala probabilidad × impacto y vincular una evaluación a un expediente. El expediente conserva la evaluación y el riesgo originales.

La vista del plan incluye búsqueda por título o entidad, filtros por año, estado y riesgo, indicadores del conjunto filtrado y exportación CSV con el mismo alcance. Supervisión puede registrar riesgos, controles y obligaciones y vincular un control con varias obligaciones, riesgos y pruebas. El roadmap conserva las revisiones recurrentes, los flujos por rol, la planificación avanzada y los tableros ampliados.

CSS propio y JavaScript local. Para pruebas con el servidor embebido de PHP en loopback, define `AUDITPRO_DEV=1` y sirve `public/`. En producción usa HTTPS.
La bandeja calcula automáticamente acciones vencidas, próximas a vencer y en verificación dentro del ámbito de cada usuario. Para supervisión también muestra las revisiones de riesgos y obligaciones de los próximos siete días.
Supervisión puede prorrogar acciones emitidas con una justificación. El historial conserva las fechas anterior y nueva.
El plan anual guarda una versión histórica de los expedientes del año y exige que otra cuenta de supervisión la apruebe. La versión aprobada de número más alto se muestra como vigente.
El informe emitido tiene una vista de impresión y guardado como PDF mediante el navegador, disponible para supervisión y para el auditor del expediente.
En el expediente, supervisión registra fechas y horas previstas, con justificación para cada reprogramación. El auditor registra tiempo de campo y el expediente muestra el total ejecutado.
Para generar recordatorios internos, programa diariamente `php /ruta/a/AuditPro/private/remind.php` desde cron o el Programador de tareas de Windows con `AUDITPRO_CONFIG` y la misma zona horaria PHP que usa el servidor web. El proceso se puede repetir sin duplicar avisos del mismo vencimiento. El responsable los ve y marca como leídos en su bandeja.
Los indicadores del plan muestran horas previstas y registradas, hallazgos por severidad y antigüedad de acciones sin cerrar de informes emitidos. Usan los mismos filtros y permisos que la tabla del plan.
Las correcciones conservan versiones de los resultados de pruebas y de la evidencia textual de acciones. La actualización recupera el último texto disponible de los expedientes anteriores y lo identifica como importado.
Cada usuario puede cambiar su contraseña en Mi cuenta. El cambio invalida las sesiones existentes. Tras actualizar a esta versión, las sesiones de versiones anteriores vuelven a pedir inicio de sesión.
En Riesgos y cumplimiento puedes importar obligaciones desde un CSV UTF-8 con vista previa. Las referencias duplicadas dentro de un mismo marco impiden guardar el lote completo.
Las cuentas Riesgos y Cumplimiento acceden a su registro y al catálogo común de controles. Cumplimiento también importa obligaciones. Supervisión conserva la trazabilidad completa y es el único perfil operativo que vincula controles con pruebas de auditoría.
## Solicitudes de evidencia (upgrade-010)

Supervisión puede crear solicitudes de documentación con fecha límite en expedientes en campo o revisión. El auditor asignado responde con texto y referencias de evidencia; supervisión puede aceptar y cerrar, o devolver para corrección. Cada respuesta y devolución conserva su historial, y las transiciones se anotan en la bitácora del expediente. La gestión de solicitudes requiere permisos SELECT, INSERT y UPDATE en `evidence_requests` y SELECT e INSERT en `evidence_request_responses`. En instalaciones existentes ejecuta `private/upgrade-010.sql` con una cuenta de mantenimiento antes de publicar esta versión. Las solicitudes no sustituyen los adjuntos protegidos de las pruebas.

La bandeja también muestra solicitudes de evidencia abiertas, vencidas o respondidas dentro del ámbito de supervisión y del auditor asignado, con enlace directo a la solicitud. Las respuestas pendientes de revisión se resaltan por estado sin notificaciones externas.

## Plantillas reutilizables de pruebas (upgrade-011)

Supervisión crea y archiva plantillas de procedimientos desde **Plantillas de pruebas**. El auditor asignado puede agregar una prueba desde una plantilla activa durante el trabajo de campo. El título y procedimiento se copian al expediente; la relación de origen se conserva en `test_template_uses`, de modo que el historial no depende de cambios posteriores en el catálogo. La creación y el archivo quedan en `audit_log`. En instalaciones existentes, ejecuta `private/upgrade-011.sql` después de la migración 010, y otorga SELECT, INSERT y UPDATE en `test_templates` y SELECT e INSERT en `test_template_uses`. Para instalación limpia, el instalador ya ejecuta la migración 011.

La vista **Universo auditable** incluye una tabla de cobertura histórica por unidad y riesgo actual. Considera únicamente expedientes vinculados a evaluaciones de riesgo y compara el último año auditado con el ciclo en meses como aproximación anual; no sustituye el análisis de fechas reales ni la evaluación de calidad de la cobertura.

## Capacidad anual del equipo auditor (upgrade-012)

En **Plan anual**, supervisión puede registrar las horas disponibles de cada auditor por año, consultar las horas planificadas y ejecutadas de sus expedientes y detectar sobreasignaciones. Un valor de capacidad no registrado se muestra como **Sin configurar**, nunca como cero. El balance no bloquea la planificación; es una alerta para revisión. Ejecuta `private/upgrade-012.sql` después de la migración 011 y otorga SELECT, INSERT y UPDATE sobre `auditor_capacity`. Para instalaciones nuevas el instalador incluye la migración.

## Pruebas automatizadas

El workflow de GitHub Actions ejecuta comprobaciones de sintaxis PHP y pruebas de humo con MySQL 8 temporal sobre los flujos de plantillas, solicitudes, autorizaciones, capacidad y cobertura. El script de pruebas `tests/smoke.php` usa exclusivamente la base de prueba indicada por `AUDITPRO_TEST_DSN`; no debe dirigirse a la base de producción.

## Aceptación formal de riesgos residuales (upgrade-013)

Supervisión puede solicitar la aceptación excepcional de una acción abierta de un informe emitido. La solicitud guarda justificación, controles compensatorios y fecha futura de revisión. Solo otra cuenta de supervisor o administrador puede aprobar o rechazar. La aceptación cierra la acción operativamente, pero `action_risk_acceptances` distingue esta decisión de un cierre por evidencia de remediación. La decisión y el actor quedan en la bitácora. En instalaciones existentes ejecuta `private/upgrade-013.sql` después de 012 y otorga SELECT, INSERT y UPDATE sobre `action_risk_acceptances`.

La bandeja de supervisión destaca las aceptaciones aprobadas cuya revisión vence dentro de 30 días, incluso cuando la auditoría ya está cerrada. El expediente conserva el historial de decisiones después del cierre.

## Atestaciones de obligaciones de cumplimiento (upgrade-014)

La interfaz destaca vencimientos a partir de la próxima revisión definida en la atestación más reciente; cuando no hay atestaciones usa la fecha original de la obligación. El script `tests/smoke.php` se niega a operar si la base seleccionada no se llama `auditpro_test`.

El personal de Cumplimiento y Supervisión puede registrar evaluaciones sucesivas de obligaciones: cumple, parcial, no cumple o no aplica, con referencia de evidencia, razonamiento, fecha evaluada y próxima revisión. Cada atestación se guarda como registro histórico independiente; no se sobreescriben evaluaciones previas. Auditoría y responsables no pueden emitir atestaciones. Ejecuta `private/upgrade-014.sql` después de 013 en instalaciones existentes y otorga SELECT e INSERT sobre `obligation_attestations`.

## Excepciones de cumplimiento (upgrade-015)

Cumplimiento o supervisión pueden solicitar excepciones con justificación, controles mitigantes y fecha futura de expiración. Solo una cuenta distinta de supervisión puede aprobar o rechazar. Las aprobadas continúan visibles para su revisión y pueden cerrarse con motivo, sin eliminar el historial de decisión. Una aprobación vencida se señala en la vista de obligaciones. Ejecuta `private/upgrade-015.sql` después de 014 y otorga SELECT, INSERT y UPDATE sobre `obligation_exceptions`.

## Planificación multianual (upgrade-016)

En **Plan anual**, supervisión crea horizontes de dos a cinco años, programa unidades auditables con evaluación vigente de riesgo, asigna auditor y horas estimadas, y exige aprobación por otra cuenta de supervisión. Cada propuesta conserva una copia de la valoración de riesgo al planificar, separada de las evaluaciones posteriores. Los horizontes aprobados quedan de solo lectura y no crean automáticamente expedientes. La capacidad anual sirve de referencia para evaluar la carga. En instalaciones existentes ejecuta `private/upgrade-016.sql` después de 015 y concede SELECT, INSERT y UPDATE sobre `multiyear_plans`, y SELECT e INSERT sobre `multiyear_plan_items`.

## Revisiones periódicas de riesgo e indicadores KRI (upgrade-017)

Riesgos y Supervisión pueden revisar el riesgo residual y definir la siguiente fecha de revisión, conservando la puntuación previa y el motivo en `grc_risk_reviews`. También pueden definir indicadores KRI con umbral máximo o mínimo, registrar mediciones fechadas con evidencia y consultar su historial. La última medición determina la alerta mostrada en el registro de riesgos y en la bandeja de supervisión. Los indicadores se archivan sin borrar mediciones. Ejecuta `private/upgrade-017.sql` después de 016; concede SELECT e INSERT en `grc_risk_reviews` y `grc_kri_observations`, y SELECT, INSERT y UPDATE en `grc_kri_indicators`, además de UPDATE en `grc_risks` para modificar su residual y fecha de revisión.

## Tablero ejecutivo de riesgos y cobertura

La sección **Plan anual** incluye un tablero de supervisión con brechas del universo auditable, brechas de unidades de riesgo alto o crítico, riesgos residuales sobre apetito, revisiones vencidas e indicadores KRI fuera de umbral. Los cálculos de cobertura usan los expedientes vinculados a las evaluaciones del universo y el ciclo anual aproximado; no se interpretan como un dictamen de suficiencia de auditoría. El tablero no requiere una migración adicional a `upgrade-017.sql`.

## Escalamiento configurable de acciones vencidas (upgrade-018)

Administración activa o desactiva el escalamiento y define un umbral de 1 a 90 días desde la bandeja. Al ejecutar el proceso diario `private/remind.php`, se crean alertas idempotentes para las cuentas activas de supervisor y administrador respecto de acciones aún abiertas en expedientes emitidos. Cada destinatario marca sus avisos como leídos. Las prórrogas y cierres dejan de mostrar avisos previos; cambiar o desactivar la política impide mostrar avisos con el umbral anterior. No se envían mensajes externos. La política viene **desactivada** por defecto. Ejecuta `private/upgrade-018.sql` después de 017, y otorga SELECT y UPDATE en `escalation_policy` y SELECT, INSERT, UPDATE en `action_escalations`.

## Recuperación administrativa local (solo consola)

El personal autorizado a operar el servidor puede recuperar la contraseña de una cuenta **admin activa** sin desactivar el inicio de sesión ni crear accesos por HTTP. La función no está habilitada por defecto. Genera un token único de 32 bytes aleatorios, fuera del repositorio, usando `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` y almacénalo en un gestor de secretos. Para configurar su huella, ejecuta `php -r '$token=trim(fgets(STDIN)); echo hash("sha256",$token), PHP_EOL;'` y copia **solo el hash** en `recovery_token_hash` del archivo privado `AUDITPRO_CONFIG`, que debe quedar fuera de `public/` y `htdocs/`.

Cuando sea imprescindible, ejecuta desde la consola del servidor `php private/recover-admin.php admin@empresa.com`, introduce el token por entrada estándar y conserva la contraseña temporal generada en un gestor seguro; inicia sesión y cámbiala de inmediato. En sistemas que no ocultan la entrada de terminal (por ejemplo, algunas consolas Windows), utiliza una sesión privada sin grabación, evita archivos temporales y protege el token de observadores. El script no recibe secretos por argumentos de comandos, solo modifica una cuenta administradora activa y registra el evento en la bitácora. El cambio de hash invalida las sesiones existentes. Una vez recuperado el acceso puedes vaciar `recovery_token_hash` para deshabilitar esta función. Esta recuperación no modifica la base de datos ni requiere migraciones.

## Permisos por unidad auditable (upgrade-019)

Administración puede habilitar alcance restringido a un supervisor desde **Acceso por unidad**, otorgar unidades y revocar accesos. Una cuenta supervisora restringida solo puede abrir expedientes con evaluación vinculada a una unidad concedida. Los expedientes de captura manual sin evaluación de unidad quedan fuera de su ámbito. El filtrado se aplica al listado, las métricas, la exportación de expedientes, la descarga de evidencias y las solicitudes. Como los paneles globales de universo, GRC y planes aún no tienen ámbito granular, quedan deshabilitados para supervisores restringidos; administración conserva la visibilidad global. El ajuste queda apagado por defecto para conservar el comportamiento anterior. Ejecuta `private/upgrade-019.sql` y concede SELECT, INSERT, UPDATE y DELETE sobre `supervisor_unit_grants`, y SELECT y UPDATE sobre `users.unit_restricted`.

## Retención y purgado auditado de evidencias (upgrade-020)

Administración configura por archivo fechas de conservación (mínimo 365 días desde su carga) y bloqueo legal desde **Retención de evidencias**. Sin fecha configurada, la evidencia permanece indefinidamente. No existe purgado automático: para purgar se exige que el expediente esté cerrado, el plazo esté vencido y no haya bloqueo legal. Se requiere confirmación explícita. El contenido se elimina irreversiblemente pero se conservan metadatos, SHA-256 y bitácora. **Realiza un respaldo y valida los requisitos legales antes de purgar**; el respaldo puede contener copias que requieren un procedimiento independiente de retención. Ejecuta `private/upgrade-020.sql` y concede UPDATE en `attachments` además de SELECT/INSERT. No habilites el purgado a administradores sin autorización de tu organización.

## Preparación del piloto (fase 3)

Ejecuta `php private/preflight-cli.php` desde el servidor configurado con `AUDITPRO_CONFIG`. Este comando no modifica la base de datos ni imprime contraseñas: comprueba PHP/extensiones, conexión, instalación, esquema hasta `upgrade-021.sql`, credenciales temporales deshabilitadas y configuración de aplicación. Un diagnóstico exitoso **no significa que el sistema esté aprobado para producción**. Completa los escenarios del archivo [docs/pilot-checklist.md](docs/pilot-checklist.md), validación de HTTPS, restauración real, accesibilidad y controles de autorización con usuarios reales.

## Políticas de cumplimiento versionadas (upgrade-021)

El módulo **Políticas** permite a Cumplimiento y Supervisión crear políticas, generar versiones textuales y vincular obligaciones. Una versión pendiente puede modificarse solo mediante sus vínculos; el texto se conserva como instantánea. No puede abrirse otra versión mientras exista una pendiente. La aprobación exige una cuenta distinta y al menos una obligación vinculada. Las versiones aprobadas no pueden modificarse. El historial identifica autor y aprobador. Ejecuta `private/upgrade-021.sql` después de 020, y concede SELECT e INSERT en `compliance_policies`, SELECT e INSERT y UPDATE en `compliance_policy_versions`, SELECT e INSERT en `compliance_policy_obligations`. La funcionalidad no reemplaza una aprobación legal de las políticas internas.

## Consulta de riesgos para supervisores por unidad

Los supervisores con alcance restringido disponen de **Mis unidades y riesgos**, una vista de solo lectura que muestra exclusivamente unidades concedidas y sus riesgos residuales/apetito. Continúan bloqueados los paneles de obligaciones, controles, políticas, planificación general y retención que no admiten aún un filtro equivalente. También queda bloqueado el catálogo global de plantillas para estos supervisores. La prueba de integración verifica que un riesgo de otra unidad no se filtre accidentalmente.

## Pruebas HTTP de acceso (integración continua)

Además de las pruebas de integración con MySQL, GitHub Actions inicia un servidor PHP temporal con una base `auditpro_test` y verifica la pantalla de inicio de sesión, el login de un auditor, la denegación de vistas administrativas, la protección CSRF y la imposibilidad de descargar SQL privados desde la raíz pública. El entorno de prueba utiliza exclusivamente datos ficticios y se destruye al finalizar. Esto no reemplaza la revisión de accesibilidad ni un piloto en el servidor de cliente.

## XAMPP en localhost

Si el proyecto completo está en `C:/xampp/htdocs/auditpro`, abre `http://localhost/auditpro/`. Apache redirige a `public/`. Los archivos `.htaccess` incluidos requieren `AllowOverride All` y bloquean el listado de carpetas, `private/` y los metadatos de Git. Conserva estos archivos al copiar o descomprimir el proyecto.

Para pruebas por HTTP, define `AUDITPRO_DEV=1` y `AUDITPRO_CONFIG` en la configuración local de Apache y reinícialo. Por ejemplo, coloca este bloque en `httpd.conf` para el directorio público de AuditPro, con el archivo de configuración privado fuera de `htdocs`:

```apache
<Directory "C:/xampp/htdocs/auditpro/public">
    SetEnv AUDITPRO_DEV 1
    SetEnv AUDITPRO_CONFIG "C:/xampp/auditpro-config.php"
</Directory>
```

Copia `private/config.example.php` a la ruta configurada, completa las credenciales SQL y el token, e inicia MySQL en XAMPP. La primera visita abrirá el instalador si la cuenta SQL de ejecución puede conectarse y el esquema aún no existe. También puedes abrir `http://localhost/auditpro/public/install.php`. Verifica que `/auditpro/private/schema.sql` responda con acceso denegado. En el servidor de la empresa, apunta el sitio directamente a `public/` y habilita HTTPS.
