# DESEGO — Plan de pruebas de aceptación

Marca cada caso como **Aprobado / Fallido / No aplica**, anota la fecha, usuario de prueba, evidencia y defecto observado. Ejecutar primero en un ambiente de pruebas con copia de la base, no en producción.

| ID | Área | Prueba | Criterio de aceptación |
|---|---|---|---|
| QA-01 | Configuración | Ejecutar `php scripts/final_check.php` | No quedan errores críticos; advertencias revisadas. |
| QA-02 | Acceso | Iniciar sesión con credencial válida | Sesión autenticada, token CSRF activo y cookie HttpOnly. |
| QA-03 | Acceso | Cinco o más intentos incorrectos | Bloqueo temporal y auditoría sin almacenar contraseña ni email en texto plano en detalles. |
| QA-04 | Autorización | Entrar a módulo sin permiso | Servidor responde 403 y no filtra los datos del módulo. |
| QA-05 | CSRF | Enviar POST sin token CSRF | Servidor rechaza la operación. |
| QA-06 | Catálogo | Crear/editar/desactivar producto y categoría | Cambios se persisten en MySQL y afectan la tienda según estado. |
| QA-07 | Inventario | Recepción de compra y venta con receta | Cantidades se actualizan una sola vez y el stock insuficiente no permite sobreventa. |
| QA-08 | Pedidos | Crear pedido y cambiar estado | Pedido y detalles persisten; transiciones inválidas son rechazadas. |
| QA-09 | Caja | Abrir, cobrar y cerrar caja | Totales, movimientos y diferencia se calculan con los cobros reales registrados. |
| QA-10 | Delivery | Asignar repartidor e informar entrega | Solo usuarios autorizados cambian el estado y queda trazabilidad. |
| QA-11 | Reservas | Reservar misma mesa en horarios solapados | La segunda reserva incompatible se rechaza en servidor. |
| QA-12 | Clientes | Crear cliente con varias direcciones | Dirección predeterminada y asociación al pedido se conservan. |
| QA-13 | Promociones | Aplicar cupón válido/expirado | El servidor vuelve a calcular descuento y rechaza códigos inválidos. |
| QA-14 | Fidelización | Pagar pedido y reembolsar | Puntos se acreditan y revierten exactamente una vez. |
| QA-15 | Reseñas | Intentar reseñar compra no elegible | La reseña no se publica; moderación controla visibilidad. |
| QA-16 | Facturación | Emitir documento y anularlo | Numeración interna consistente; no se presenta como documento SUNAT hasta integración homologada. |
| QA-17 | Compras | Recibir compra parcial y completa | Existencias, capas de costo y estado de orden se actualizan correctamente. |
| QA-18 | Personal | Crear turno solapado | El servidor impide solapes incompatibles y registra asistencia con permisos. |
| QA-19 | Comunicaciones | Crear ticket y publicar aviso | Usuario solo ve sus tickets; nota interna no visible a usuario sin permiso. |
| QA-20 | API | Crear token, consultar recurso autorizado y revocarlo | Solo se exponen los scopes autorizados; token revocado falla; exceso obtiene HTTP 429. |
| QA-21 | Backups | Crear, verificar y restaurar en base aislada | Hash/cifrado válidos; restauración confirmada y recuperable. |
| QA-22 | Privacidad | Abrir por URL un `.sql`, backup o archivo de config | Acceso denegado en el servidor web. |
| QA-23 | Seguridad HTTP | Revisar respuesta en DevTools/curl bajo HTTPS | Cabeceras documentadas presentes; cookies Secure/HttpOnly/SameSite. |
| QA-24 | Navegadores | Recorrer los módulos en móvil/tablet/escritorio | Sin desbordamientos, errores de consola ni acciones inaccesibles. |
| QA-25 | Trazabilidad Excel | Completar los 460 RF de la matriz | Cada RF tiene un resultado, evidencia y defecto o justificación de no aplica. |

## Notas

- Los casos que implican pagos, correos, mapas, WhatsApp o SUNAT deben ejecutarse en sandbox del proveedor antes de producción.
- Una comprobación de sintaxis/lint no sustituye esta prueba de aceptación end-to-end.
