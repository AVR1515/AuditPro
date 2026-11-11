# Auditoría de siete fases técnicas — AuditPro
Fecha de corte: 2026-11-11. Rama: `main`.

Este documento separa **funcionalidad entregada por código** de **aprobación del
piloto/producción**. Ningún resultado automatizado sustituye la revisión de
usuarios, la evaluación legal, las copias restauradas o la seguridad del servidor.

| Fase del roadmap | Entregable de código |
|---|---|
| 06 — Permisos | Supervisión limitada ve únicamente riesgos, controles, pruebas, obligaciones y plan operativo vinculados a unidades concedidas; mutaciones sobre otros riesgos se rechazan. Los módulos globales no aislados permanecen bloqueados. |
| 09 — Plan anual y cobertura | `audits.closed_on` almacena la fecha efectiva del cierre nuevo; el cálculo del ciclo utiliza meses exactos cuando existe fecha; el histórico sin ella indica que es aproximado. |
| 10 — Pruebas y evidencia | La respuesta de una solicitud puede apuntar a un adjunto real de prueba del mismo expediente. Se valida su pertenencia y se preserva el historial incluso tras el purgado del contenido. |
| 13 — Riesgos y KRI | Cada indicador define un intervalo de actualización de 1–365 días. Alertas de medición caduca, incluso sin primera medición. |
| 14 — Cumplimiento | Vista de obligaciones por unidad vinculadas mediante controles de riesgo; el rol restringido no hereda facultades de atestación o aprobación global. Versionado, excepciones y atestaciones ya implementados. |
| 16 — Indicadores | Tablero ejecutivo muestra riesgos, KRI, solicitudes de evidencia, obligaciones y aceptaciones de riesgo o excepciones vencidas. |
| 20 — Planificación multianual | Previsión por auditor y año basada en planes aprobados; una revisión posterior sustituye la misma unidad/año, evitando sumar dos veces el compromiso. |

## Verificación automática

- `tests/smoke.php` monta el esquema limpio, migraciones 002–024 y comprueba los flujos en MySQL 8 temporal.
- `tests/http-smoke.sh` valida autenticación, CSRF, rutas privadas y ausencia de datos ajenos en la interfaz del supervisor restringido.
- `.github/workflows/php-lint.yml` verifica sintaxis PHP y requisitos de migración.

## Fuera del alcance de estas siete entregas

- Recorrido presencial/remoto con usuarios por rol, capturas **reales** del entorno de cliente, accesibilidad visual y firma de aceptación del piloto (fase 17).
- Validación jurídica de conservación y de políticas de cumplimiento (fase 07), respaldos y restauración del servidor del cliente y HTTPS (fase 18).
- Aprobación de umbrales y matrices de riesgo por sus propietarios, y capacitación de equipos (fases 01 y 19).
- Auditoría independiente de seguridad y operación en producción.

## Actualización existente

Aplicar, por este orden y después de respaldar la base, las migraciones
`private/upgrade-022.sql`, `private/upgrade-023.sql` y
`private/upgrade-024.sql`. Revisar permisos SQL mínimos del README y ejecutar
`php private/preflight-cli.php`. No ejecutar el script de humo contra bases
de producción.
