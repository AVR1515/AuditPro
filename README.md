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

Para actualizar una instalación anterior, respalda la base, ejecuta en orden `private/upgrade-002.sql` , `private/upgrade-003.sql` `private/upgrade-004.sql` `private/upgrade-005.sql` `private/upgrade-006.sql` `private/upgrade-007.sql` `private/upgrade-008.sql`, `private/upgrade-009.sql` `private/upgrade-010.sql` y `private/upgrade-011.sql` en el esquema de AuditPro con una cuenta de mantenimiento, aplica los permisos de adjuntos, de riesgos y de universo (SELECT e INSERT en `auditable_units`, `risk_assessments` y `audit_assessment_links`) y luego publica los nuevos archivos. En las tablas con prefijo `grc_`, otorga SELECT e INSERT y permite UPDATE en las tres tablas de vínculos para los reintentos idempotentes. Otorga SELECT e INSERT sobre `annual_plan_items` y SELECT, INSERT y UPDATE sobre `annual_plans`. Otorga SELECT e INSERT sobre `audit_time` y SELECT, INSERT y UPDATE sobre `audit_schedule`. Otorga SELECT, INSERT y UPDATE sobre `action_reminders`. Otorga SELECT e INSERT sobre `test_revisions` y `action_revisions`. En una instalación limpia el instalador ya aplica ese SQL.

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
