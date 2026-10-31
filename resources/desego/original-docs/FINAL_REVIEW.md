# DESEGO CEVICHERÍA — Paso 16: revisión final

## Qué incluye este paso

El Excel entregado contiene 67 hojas de módulos (RF_001–RF_067) y 460 requisitos RF001–RF460. El dashboard del Excel menciona 66 módulos, por lo que esa cifra resumen conviene corregirla; esta matriz sigue las 67 hojas existentes.

- Cabeceras de seguridad centralizadas para páginas/API que cargan `includes/session.php`: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Resource-Policy`, `Cache-Control`; HSTS se envía solamente cuando la solicitud ya usa HTTPS.
- Protección adicional de rutas sensibles en Apache (`.htaccess`) para listados, exportaciones/backups y carpetas de configuración, scripts, almacenamiento, QA y documentación.
- Límite de solicitudes de la API externa por token con ventana de un minuto, configurable en `system_settings` y con valor inicial de 120 peticiones/minuto.
- Script de verificación de instalación de solo lectura (`php scripts/final_check.php`). Revisa PHP/extensiones, archivos y migraciones esperados, lint PHP, configuración no-placeholder, clave de backup, conexión MySQL y tablas principales cuando el entorno lo permite.
- Script de mantenimiento del contador de API (`php scripts/prune_api_rate_limits.php`), pensado para ejecutarse por cron.
- Matriz de trazabilidad basada en el Excel adjunto: 67 hojas de módulos y 460 requisitos RF, con estado inicial `Pendiente de validación funcional en servidor`.

## Cómo actualizar desde el Paso 15

1. Haz backup de archivos y base de datos antes de reemplazar nada.
2. Actualiza el proyecto con los archivos de este ZIP.
3. Conserva `config/database.php`, `config/mail.php` y `config/backup.php` con los valores que ya configuraste.
4. Importa solamente `database/step16_security_hardening.sql` en la misma base de datos. No vuelvas a importar `database/schema.sql` sobre una instalación existente.
5. Ejecuta `php scripts/final_check.php` desde la carpeta raíz del proyecto. Lee los resultados `[PASS]`, `[WARN]` y `[FAIL]`; corrige los elementos antes de usar en producción.
6. Si la API externa está habilitada, confirma que la migración se aplicó; el endpoint `api/external.php` usa `api_rate_limit_windows` para limitar cada token.
7. Programa `php scripts/prune_api_rate_limits.php` diariamente o semanalmente desde cron/Task Scheduler para limpiar ventanas de más de 7 días.

## Hallazgos / limitaciones importantes

- El proyecto no se pudo probar contra la base de datos real ni contra las credenciales del hosting desde este entorno. El test incluido hace una verificación de lectura al ejecutarse en el servidor.
- El presente paso no certifica que los 460 RF estén implementados. La matriz CSV mantiene todos los requisitos visibles para que se validen uno por uno en el entorno desplegado; una revisión estática no demuestra su funcionamiento.
- Requisitos móviles nativos, OAuth2, pagos reales, integración SUNAT, mapas/GPS, WhatsApp, entrega de correo y webhooks requieren una app/proveedor/credenciales y pruebas de integración reales cuando correspondan. No se deben anunciar como activos solo por tener una pantalla de configuración.
- Las reglas `.htaccess` solo funcionan en Apache compatible. En Nginx deben replicarse en la configuración del servidor.
- Para producción se recomienda HTTPS obligatorio, gestión de secretos mediante variables de entorno fuera del document root, usuarios MySQL con privilegios mínimos y pruebas de restauración de backups en una base aislada.
- La limitación de API añadida es por token/ventana de minuto. No reemplaza WAF, monitorización, OAuth2, cuotas por plan ni pruebas de penetración.

## Estructura de evidencias

- `docs/matriz-trazabilidad-460-RF.csv`: requisitos RF001–RF460 con módulo, hoja, categoría, texto y estado para que se registre la evidencia.
- `docs/resumen-67-modulos.csv`: resumen de los 67 módulos y número de requisitos por hoja.
- `docs/PRUEBAS-ACEPTACION.md`: casos de pruebas manuales para preparar la aceptación funcional final.
