# AuditPro

Aplicación PHP y SQL para gestionar expedientes de auditoría interna. El [roadmap interactivo](roadmap-auditpro.html) se puede abrir directamente en el navegador.

## Instalación

1. Instala PHP 8.2 o superior con PDO MySQL y un servidor MySQL o MariaDB. Configura HTTPS y apunta la raíz web exclusivamente a `public/`.
2. Copia `private/config.example.php` a un archivo fuera de la raíz web. Restringe su lectura al usuario de PHP y configura la ruta absoluta en la variable de entorno `AUDITPRO_CONFIG`.
3. Prepara una cuenta SQL temporal con permisos para crear la base y sus tablas e insertar datos iniciales. Crea una cuenta independiente para la app con permisos SELECT, INSERT y UPDATE sobre el esquema dedicado. Otorga solo SELECT e INSERT en `audit_log` y solo SELECT en `installation` y `catalog`. Si el motor exige que existan las tablas para otorgar permisos específicos, configura esos permisos tras la instalación.
4. Rellena ambas cuentas y una clave aleatoria de instalación en la configuración privada. Abre `install.php`, ingresa la clave y crea tu cuenta administradora. El instalador crea las tablas, precarga roles, severidades y estados, y registra la instalación para bloquear intentos posteriores.
5. Retira la clave y las credenciales temporales de la configuración, revoca la cuenta temporal y verifica el acceso con la cuenta de aplicación. Haz respaldos de la base de datos y de la configuración privada.

## Recorrido disponible

Crea un auditor y un responsable desde la cuenta administradora. Registra un expediente y aprueba su paso a trabajo de campo. En la cuenta del auditor documenta pruebas y hallazgos. Revisa las pruebas y asigna acciones con la cuenta supervisora o administradora. Emite el informe y usa la cuenta responsable para presentar la descripción y las referencias de evidencia. La cuenta supervisora o administradora verifica las acciones y cierra la auditoría.

Las pruebas y acciones admiten archivos PDF, PNG, JPEG y TXT de hasta 5 MiB. Se guardan en SQL con huella SHA-256 y el acceso se verifica antes de cada descarga. Inclúyelos en el respaldo de la base. Habilita `fileinfo`, configura `upload_max_filesize` a 5M o más, `post_max_size` a un valor superior y `max_allowed_packet` de SQL a 8 MiB o más. Otorga a la app SELECT e INSERT sobre `attachments`.

Para actualizar una instalación anterior, respalda la base, ejecuta en orden `private/upgrade-002.sql` , `private/upgrade-003.sql` `private/upgrade-004.sql` y `private/upgrade-005.sql` en el esquema de AuditPro con una cuenta de mantenimiento, aplica los permisos de adjuntos, de riesgos y de universo (SELECT e INSERT en `auditable_units`, `risk_assessments` y `audit_assessment_links`) y luego publica los nuevos archivos. En las tablas con prefijo `grc_`, otorga SELECT e INSERT y permite UPDATE en las tres tablas de vínculos para los reintentos idempotentes. Otorga SELECT e INSERT sobre `annual_plan_items` y SELECT, INSERT y UPDATE sobre `annual_plans`. En una instalación limpia el instalador ya aplica ese SQL.

Supervisión puede registrar unidades auditables, crear evaluaciones históricas con la escala probabilidad × impacto y vincular una evaluación a un expediente. El expediente conserva la evaluación y el riesgo originales.

La vista del plan incluye búsqueda por título o entidad, filtros por año, estado y riesgo, indicadores del conjunto filtrado y exportación CSV con el mismo alcance. Supervisión puede registrar riesgos, controles y obligaciones y vincular un control con varias obligaciones, riesgos y pruebas. El roadmap conserva las revisiones recurrentes, los flujos por rol, la planificación avanzada y los tableros ampliados.

CSS propio y JavaScript local. Para pruebas con el servidor embebido de PHP en loopback, define `AUDITPRO_DEV=1` y sirve `public/`. En producción usa HTTPS.
La bandeja calcula automáticamente acciones vencidas, próximas a vencer y en verificación dentro del ámbito de cada usuario. Para supervisión también muestra las revisiones de riesgos y obligaciones de los próximos siete días.
Supervisión puede prorrogar acciones emitidas con una justificación. El historial conserva las fechas anterior y nueva.
El plan anual guarda una versión histórica de los expedientes del año y exige que otra cuenta de supervisión la apruebe. La versión aprobada de número más alto se muestra como vigente.
El informe emitido tiene una vista de impresión y guardado como PDF mediante el navegador, disponible para supervisión y para el auditor del expediente.
