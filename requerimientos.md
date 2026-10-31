# Requerimientos funcionales — Deseogo

## Información general

- **Proyecto:** DESEGO
- **Ámbito:** Sistema web de administración y aplicación móvil.
- **Origen:** Hoja de cálculo de módulos y requerimientos.
- **Tipo:** Requerimientos funcionales (RF).
- **Módulos registrados:** 67
- **Requerimientos registrados:** 460
- **Estado de implementación:** No especificado en el archivo original.

## Índice de categorías

- [USUARIOS Y PERFILES](#usuarios-y-perfiles) — 4 módulos
- [CATÁLOGO](#catalogo) — 5 módulos
- [PEDIDOS](#pedidos) — 5 módulos
- [ENTREGAS](#entregas) — 6 módulos
- [RESERVAS](#reservas) — 5 módulos
- [PAGOS](#pagos) — 4 módulos
- [PUNTO DE VENTA](#punto-de-venta) — 3 módulos
- [COCINA](#cocina) — 2 módulos
- [INVENTARIO](#inventario) — 1 módulos
- [COMPRAS](#compras) — 2 módulos
- [MARKETING](#marketing) — 4 módulos
- [SOCIAL](#social) — 4 módulos
- [NOTIFICACIONES](#notificaciones) — 4 módulos
- [BÚSQUEDA](#busqueda) — 2 módulos
- [AUTENTICACIÓN](#autenticacion) — 3 módulos
- [CONFIGURACIÓN](#configuracion) — 3 módulos
- [REPORTES](#reportes) — 2 módulos
- [INTEGRACIONES](#integraciones) — 3 módulos
- [ADMINISTRACIÓN](#administracion) — 5 módulos

## Convenciones

- `RF###`: identificador original de cada requerimiento funcional.
- **WEB**: funcionalidad definida para la interfaz web.
- **APP**: funcionalidad definida para la aplicación móvil.
- **APP - mismo que WEB**: indica relación funcional con la versión web; se conservan los requerimientos específicos de la app.
- Los códigos se mantienen tal como aparecen en el Excel, sin renumerarlos.

---

## USUARIOS Y PERFILES

### 1. Gestión de Clientes (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF001` | Registrar nuevo cliente con email, contraseña y datos básicos |
| `RF002` | Visualizar y editar perfil de cliente |
| `RF003` | Historial completo de pedidos del cliente |
| `RF004` | Listar y gestionar direcciones guardadas |
| `RF005` | Activar/desactivar cuenta de cliente |
| `RF006` | Ver estadísticas de compra (Total gastado, pedidos realizados) |
| `RF007` | Eliminar cuenta con confirmación |
| `RF008` | Búsqueda de clientes por email, teléfono, nombre |
| `RF009` | Exportar datos de clientes (CSV/Excel) |
| `RF010` | Verificación de email y número de teléfono |

### 2. Perfil de Usuario (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF011` | Ver datos personales en app |
| `RF012` | Editar foto de perfil |
| `RF013` | Cambiar contraseña desde app |
| `RF014` | Sincronizar datos entre web y app |
| `RF015` | Mostrar nivel de fidelización en perfil |
| `RF016` | Ver métodos de pago guardados |
| `RF017` | Preferencias de notificaciones |

### 3. Gestión de Personal (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF018` | Crear empleado con rol asignado |
| `RF019` | Asignar horarios y turnos |
| `RF020` | Ver historial de asistencia |
| `RF021` | Calificar desempeño de empleados |
| `RF022` | Generar nómina automática |
| `RF023` | Controlar permisos y ausencias |
| `RF024` | Cambiar estado: Activo/Inactivo |
| `RF025` | Ver historial de cambios en empleado |

### 4. Roles y Permisos (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF026` | Crear roles personalizados |
| `RF027` | Asignar permisos específicos por rol |
| `RF028` | Roles preestablecidos: Cliente, Mesero, Cocinero, Repartidor, Cajero, Admin, Dueño |
| `RF029` | Visualizar matriz de permisos por rol |
| `RF030` | Editar permisos de roles existentes |
| `RF031` | Historial de cambios de permisos |
| `RF032` | Duplicar roles existentes |
| `RF033` | Validar permisos en tiempo real |

---

## CATÁLOGO

### 5. Menú y Productos (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF034` | Crear producto con nombre, descripción, precio |
| `RF035` | Cargar imagen del producto |
| `RF036` | Asignar categoría y subcategoría |
| `RF037` | Definir ingredientes del producto |
| `RF038` | Marcar alérgenos presentes |
| `RF039` | Establecer precio base y variaciones |
| `RF040` | Stock por sucursal |
| `RF041` | Activar/desactivar producto |
| `RF042` | Ordenar productos por preferencia |
| `RF043` | Crear combinaciones/bundles |
| `RF044` | Historial de cambios de precio |

### 6. Menú y Productos (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF045` | Visualizar productos con imágenes |
| `RF046` | Ver detalles completos del producto |
| `RF047` | Mostrar alérgenos destacados |
| `RF048` | Rating y comentarios sobre producto |
| `RF049` | Información nutricional (si aplica) |
| `RF050` | Mostrarprecio y disponibilidad actual |

### 7. Categorías (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF051` | Crear categoría de productos |
| `RF052` | Ordenar categorías por posición |
| `RF053` | Asignar imagen a categoría |
| `RF054` | Editar nombre y descripción |
| `RF055` | Activar/desactivar categoría |
| `RF056` | Asociar múltiples productos a categoría |
| `RF057` | Listar productos por categoría |

### 8. Categorías (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF058` | Mostrar categorías en orden definido |
| `RF059` | Filtrar por categoría |
| `RF060` | Búsqueda dentro de categoría |
| `RF061` | Ícono visual para categoría |

### 9. Ingredientes y Alérgenos (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF062` | Crear ingrediente con información |
| `RF063` | Marcar alérgenos críticos (TOP 14) |
| `RF064` | Asignar costo a ingrediente |
| `RF065` | Vincular a productos automáticamente |
| `RF066` | Historial de cambios de precio de ingrediente |
| `RF067` | Alertas para alérgenos en productos |

---

## PEDIDOS

### 10. Carrito (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF068` | Agregar/eliminar productos al carrito |
| `RF069` | Modificar cantidad de producto |
| `RF070` | Aplicar cupón/código promocional |
| `RF071` | Calcular subtotal, impuestos, total |
| `RF072` | Mostrar tiempo estimado de preparación |
| `RF073` | Guardar carrito como favorito |
| `RF074` | Reestablecer carrito desde pedido anterior |
| `RF075` | Sincronizar carrito entre dispositivos |
| `RF076` | Validar disponibilidad en tiempo real |

### 11. Carrito (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF077` | Interfaz táctil optimizada |
| `RF078` | Atajos de cantidad (1-2-3...) |
| `RF079` | Deslizar para eliminar producto |
| `RF080` | Resumen flotante de carrito |

### 12. Pedidos (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF081` | Crear pedido desde carrito |
| `RF082` | Seleccionar tipo de pedido: Online, Delivery, Reserva, Para llevar |
| `RF083` | Estados de pedido: Pendiente → Confirmado → Preparando → Listo → Entregado/Retirado |
| `RF084` | Seguimiento en tiempo real |
| `RF085` | Estimación de tiempo de entrega |
| `RF086` | Notificación de cambios de estado |
| `RF087` | Posibilidad de cancelar antes de confirmación |
| `RF088` | Modificar pedido antes de prepararse |
| `RF089` | Historial de pedidos con filtros |
| `RF090` | Duplicar pedido anterior |
| `RF091` | Imprimir o descargar comprobante |

### 13. Pedidos (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF092` | Crear pedido simplificado |
| `RF093` | Seguimiento con mapa en tiempo real |
| `RF094` | Notificaciones push de cambios |
| `RF095` | Compartir pedido con contactos |
| `RF096` | Contacto directo con repartidor |
| `RF097` | Rating del pedido post-entrega |

### 14. Reordenar (APP)

| ID | Requerimiento funcional |
| --- | --- |
| `RF098` | Un clic para pedir nuevamente |
| `RF099` | Sugerir productos frecuentes |
| `RF100` | Opción de modificar antes de confirmar |
| `RF101` | Historial de reórdenes |

---

## ENTREGAS

### 15. Delivery (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF102` | Crear zona de reparto |
| `RF103` | Establecer costo de delivery por zona |
| `RF104` | Definir tiempo estimado de entrega |
| `RF105` | Asignar repartidores disponibles |
| `RF106` | Seguimiento GPS en tiempo real |
| `RF107` | Historial de entregas completadas |
| `RF108` | Calificar desempeño de repartidor |
| `RF109` | Reportar incidencias en entrega |
| `RF110` | Validar dirección antes de asignar |

### 16. Delivery (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF111` | Ver zona de cobertura en mapa |
| `RF112` | Costo de delivery calculado automático |
| `RF113` | Seguimiento live del repartidor |
| `RF114` | Notificación cuando repartidor está cerca |

### 17. Direcciones y Zonas (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF115` | Crear/editar zonas de reparto en mapa |
| `RF116` | Definir polígonos de cobertura |
| `RF117` | Asignar costo y tiempo por zona |
| `RF118` | Activar/desactivar zonas según horario |
| `RF119` | Importar direcciones desde CSV |
| `RF120` | Validación automática de dirección |

### 18. Direcciones (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF121` | Guardar dirección de entrega |
| `RF122` | Autocompletar dirección con Google Maps |
| `RF123` | Seleccionar de direcciones guardadas |
| `RF124` | Agregar referencia/indicaciones |
| `RF125` | Validar que dirección está en zona |

### 19. Geolocalización y Mapas (APP)

| ID | Requerimiento funcional |
| --- | --- |
| `RF126` | Ubicación automática del usuario |
| `RF127` | Integración con Google Maps |
| `RF128` | Ver mapa de zonas de cobertura |
| `RF129` | Seguimiento GPS del repartidor |
| `RF130` | Opción de Waze/Google Maps para navegar |

### 20. Repartidores (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF131` | Registrar repartidor con datos |
| `RF132` | Validar documentos: Cédula, Licencia |
| `RF133` | Ver disponibilidad en tiempo real |
| `RF134` | Asignar entregas automáticas |
| `RF135` | Historial de entregas y ganancias |
| `RF136` | Rating y calificación de repartidor |
| `RF137` | Cambiar estado: Disponible/No disponible |
| `RF138` | Generar reporte de ganancias del repartidor |

---

## RESERVAS

### 21. Reservas (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF139` | Crear reserva especificando fecha, hora, cantidad |
| `RF140` | Ver disponibilidad de mesas |
| `RF141` | Seleccionar preferencia de ubicación |
| `RF142` | Agregar notas especiales (cumpleaños, etc) |
| `RF143` | Confirmación automática o manual |
| `RF144` | Enviar recordatorio 24h antes |
| `RF145` | Cancelar reserva |
| `RF146` | Reprogramar reserva |
| `RF147` | Historial de reservas |
| `RF148` | No-show tracking |

### 22. Reservas (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF149` | Reserva rápida desde app |
| `RF150` | Notificaciones de confirmación |
| `RF151` | Cancelar desde app |
| `RF152` | Mostrar estado actual de reserva |

### 23. Mesas (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF153` | Crear mesa con capacidad |
| `RF154` | Asignar ubicación en plano |
| `RF155` | Estados de mesa: Disponible, Ocupada, Reservada, Mantenimiento |
| `RF156` | Duración estimada por reserva |
| `RF157` | Merge de mesas (juntar dos mesas) |
| `RF158` | Historial de uso de mesa |
| `RF159` | Plano visual interactivo de restaurante |

### 24. Mesas (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF160` | Ver disponibilidad en tiempo real |
| `RF161` | Visualizar plano del restaurante |
| `RF162` | Información de ubicación de mesa |
| `RF163` | Acceso QR para mesa |

### 25. QR (APP)

| ID | Requerimiento funcional |
| --- | --- |
| `RF164` | Generar QR por mesa |
| `RF165` | Acceder a menú escaneando QR |
| `RF166` | Hacer pedido desde QR |
| `RF167` | Pedir la cuenta desde QR |
| `RF168` | Historial de QR escaneados |

---

## PAGOS

### 26. Pagos (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF169` | Múltiples métodos: Efectivo, Tarjeta, Billetera digital, QR, Transferencia |
| `RF170` | Integración Stripe, MercadoPago, PayPal |
| `RF171` | Procesar pago automático |
| `RF172` | Validar tarjeta antes de pago |
| `RF173` | 3D Secure si es necesario |
| `RF174` | Reembolsos automáticos |
| `RF175` | Historial de transacciones |
| `RF176` | Descuentos/promociones aplicados |
| `RF177` | Recibir comprobante de pago |
| `RF178` | Pagos parciales o a plazos |

### 27. Pagos (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF179` | Pagar con métodos guardados |
| `RF180` | Apple Pay / Google Pay |
| `RF181` | Billetera digital integrada |
| `RF182` | Notificación de pago exitoso |

### 28. Facturación (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF183` | Generar factura automática |
| `RF184` | Incluir RUC/NIT del cliente |
| `RF185` | Desglose de impuestos |
| `RF186` | Numeración secuencial de facturas |
| `RF187` | Firmar electrónicamente (si requiere) |
| `RF188` | Descargar factura en PDF |
| `RF189` | Enviar factura por email |
| `RF190` | Anular factura con justificación |
| `RF191` | Consulta de facturas por período |

### 29. Comprobantes (APP)

| ID | Requerimiento funcional |
| --- | --- |
| `RF192` | Descargar comprobante |
| `RF193` | Compartir comprobante |
| `RF194` | Ver historial de comprobantes |
| `RF195` | Solicitar factura formal |

---

## PUNTO DE VENTA

### 30. Caja (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF196` | Crear sesión de caja |
| `RF197` | Registrar venta de mostrador |
| `RF198` | Generar cambio automático |
| `RF199` | Cerrar caja con resumen |
| `RF200` | Verificar cuadre de caja |
| `RF201` | Reportar diferencias de efectivo |
| `RF202` | Historial de ventas por caja |
| `RF203` | Operación por turno de cajero |

### 31. Caja (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF204` | Acceso rápido a caja desde tablet |
| `RF205` | Registro de venta táctil |
| `RF206` | Cierre de caja simplificado |
| `RF207` | Modo offline con sincronización |

### 32. Toma de Pedidos (APP)

| ID | Requerimiento funcional |
| --- | --- |
| `RF208` | Mesero toma pedido desde tablet |
| `RF209` | Búsqueda rápida de producto |
| `RF210` | Enviar pedido a cocina |
| `RF211` | Ver estado de preparación |
| `RF212` | Modificar pedido en tiempo real |
| `RF213` | Gestión de mesa desde tablet |
| `RF214` | Cambio rápido entre mesas |
| `RF215` | Historial de modificaciones de pedido |

---

## COCINA

### 33. Cocina/KDS (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF216` | Visualizar cola de pedidos |
| `RF217` | Filtrar por tipo: Bebidas, Postres, Platos |
| `RF218` | Cronómetro por plato |
| `RF219` | Cambiar estado: Pendiente → Preparando → Listo |
| `RF220` | Alertas de alérgenos |
| `RF221` | Priorizar pedidos |
| `RF222` | Sonidos/vibraciones de notificación |
| `RF223` | Ver detalles completos del plato |
| `RF224` | Historial de pedidos completados |

### 34. Cocina/KDS (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF225` | Interfaz optimizada para tablet |
| `RF226` | Pantalla grande de cocina |
| `RF227` | Modo fullscreen sin distracciones |
| `RF228` | Control mediante botones grandes |

---

## INVENTARIO

### 35. Inventario (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF229` | Listar todos los productos/ingredientes |
| `RF230` | Stock actual por sucursal |
| `RF231` | Registrar entrada de stock |
| `RF232` | Registrar salida/uso de stock |
| `RF233` | Ajuste de inventario |
| `RF234` | Alertas de stock bajo |
| `RF235` | Historial de movimientos de inventario |
| `RF236` | Costeo de inventario (FIFO/LIFO) |
| `RF237` | Valorización de existencias |
| `RF238` | Auditoría de inventario por fecha |

---

## COMPRAS

### 36. Compras a Proveedores (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF239` | Crear orden de compra |
| `RF240` | Listar proveedores registrados |
| `RF241` | Agregar productos a orden |
| `RF242` | Definir cantidad y precio |
| `RF243` | Fecha de entrega estimada |
| `RF244` | Enviar orden al proveedor |
| `RF245` | Registrar recepción de mercancía |
| `RF246` | Validar productos recibidos |
| `RF247` | Actualizar inventario automáticamente |
| `RF248` | Historial de compras por proveedor |

### 37. Proveedores (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF249` | Registrar proveedor |
| `RF250` | Contacto y teléfono de proveedor |
| `RF251` | Condiciones de pago |
| `RF252` | Productos que suministra |
| `RF253` | Historial de transacciones |
| `RF254` | Rating de proveedor |
| `RF255` | Documentos: RUC, Certificados |

---

## MARKETING

### 38. Promociones y Cupones (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF256` | Crear promoción con descuento % |
| `RF257` | Descuento por monto mínimo |
| `RF258` | Válida por período (Fecha inicio-fin) |
| `RF259` | Válida por hora (Happy hour) |
| `RF260` | Válida por sucursal |
| `RF261` | Válida para producto específico |
| `RF262` | Cantidad de usos limitado |
| `RF263` | Usar código o sin código |
| `RF264` | Aplicar automáticamente |
| `RF265` | Historial de usos de cupón |

### 39. Promociones y Cupones (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF266` | Ver promociones disponibles |
| `RF267` | Aplicar cupón en carrito |
| `RF268` | Ver cupones guardados |
| `RF269` | Notificación de nuevas promociones |

### 40. Fidelización y Puntos (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF270` | Sistema de puntos por compra |
| `RF271` | Asignar puntos por pedido (% del total) |
| `RF272` | Redimir puntos por descuento |
| `RF273` | Niveles de cliente: Bronce, Plata, Oro |
| `RF274` | Beneficios por nivel |
| `RF275` | Historial de puntos |
| `RF276` | Expiración de puntos configurable |
| `RF277` | Mostrar saldo de puntos |
| `RF278` | Ofertas exclusivas por nivel |

### 41. Fidelización y Puntos (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF279` | Ver saldo de puntos en perfil |
| `RF280` | Mostrar nivel actual |
| `RF281` | Redimir puntos desde app |
| `RF282` | Historial de puntos |
| `RF283` | Avisar cuando está cerca de nivel siguiente |

---

## SOCIAL

### 42. Reseñas y Calificaciones (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF284` | Permitir calificación de 1-5 estrellas |
| `RF285` | Reseña de texto por cliente |
| `RF286` | Fotos en reseña |
| `RF287` | Responder a reseñas |
| `RF288` | Moderar reseñas antes de publicar |
| `RF289` | Mostrar reseñas más útiles |
| `RF290` | Reseña por producto |
| `RF291` | Historial de reseñas por cliente |
| `RF292` | Promedio de calificación general |

### 43. Reseñas y Calificaciones (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF293` | Calificar pedido después de entregar |
| `RF294` | Fotos de la comida |
| `RF295` | Escribir reseña |
| `RF296` | Ver reseñas de otros clientes |
| `RF297` | Enviar feedback anónimo |

### 44. Favoritos (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF298` | Guardar producto como favorito |
| `RF299` | Crear lista de favoritos |
| `RF300` | Acceso rápido desde perfil |
| `RF301` | Compartir favoritos con amigos |

### 45. Favoritos (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF302` | Corazón para marcar favorito |
| `RF303` | Acceso desde sección Favoritos |
| `RF304` | Contador de favoritos |
| `RF305` | Notificación cuando favorito tiene descuento |

---

## NOTIFICACIONES

### 46. Notificaciones (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF306` | Email para estado de pedido |
| `RF307` | Email de confirmación de reserva |
| `RF308` | SMS de estado de pedido |
| `RF309` | Centro de notificaciones en panel |
| `RF310` | Marcar notificación como leída |
| `RF311` | Eliminar notificación |
| `RF312` | Historial de notificaciones |

### 47. Notificaciones (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF313` | Push notifications de estado |
| `RF314` | Push cuando repartidor está cerca |
| `RF315` | Push de promociones |
| `RF316` | Sonido y vibración |
| `RF317` | Configurar tipo de notificaciones |
| `RF318` | No molestar (horarios) |

### 48. Soporte y Chat (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF319` | Formulario de contacto |
| `RF320` | Chat en vivo con soporte |
| `RF321` | Historial de tickets de soporte |
| `RF322` | FAQ automático |
| `RF323` | Envío de evidencia (fotos, documentos) |
| `RF324` | Tiempo de respuesta SLA |

### 49. Soporte y Chat (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF325` | Chat desde app |
| `RF326` | Ver estado del ticket |
| `RF327` | Notificación de respuesta |
| `RF328` | Contacto de emergencia |

---

## BÚSQUEDA

### 50. Búsqueda y Filtros (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF329` | Buscar productos por nombre |
| `RF330` | Filtrar por categoría |
| `RF331` | Filtrar por precio (rango) |
| `RF332` | Filtrar por alérgenos |
| `RF333` | Filtrar por rating |
| `RF334` | Ordenar por: Relevancia, Precio, Rating |
| `RF335` | Guardar búsqueda frecuente |
| `RF336` | Autocompletar búsqueda |
| `RF337` | Búsqueda por ingredientes |

### 51. Búsqueda y Filtros (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF338` | Interfaz táctil de filtros |
| `RF339` | Búsqueda por voz (si aplica) |
| `RF340` | Búsqueda por imagen |
| `RF341` | Filtros rápidos en header |

---

## AUTENTICACIÓN

### 52. Autenticación (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF342` | Registro de usuario con email/teléfono |
| `RF343` | Verificación de email |
| `RF344` | Login con email/contraseña |
| `RF345` | Recuperación de contraseña |
| `RF346` | Login social: Google, Facebook |
| `RF347` | 2FA (Autenticación de dos factores) |
| `RF348` | Mantener sesión activa |
| `RF349` | Logout seguro |
| `RF350` | Expiración de sesión |

### 53. Autenticación (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF351` | Registro desde app |
| `RF352` | Biometría: Huella dactilar, Face ID |
| `RF353` | Login biométrico |
| `RF354` | Recuperación de contraseña |
| `RF355` | Mantener sesión en app |

### 54. Onboarding (APP)

| ID | Requerimiento funcional |
| --- | --- |
| `RF356` | Pantalla de bienvenida |
| `RF357` | Tutorial de funciones principales |
| `RF358` | Guía visual de navegación |
| `RF359` | Opción de saltar onboarding |
| `RF360` | Recordar preferencias seleccionadas |

---

## CONFIGURACIÓN

### 55. Configuración (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF361` | Horarios de atención por sucursal |
| `RF362` | Habilitar/deshabilitar servicios (Delivery, Reserva) |
| `RF363` | Configurar impuestos (IVA, IEPS) |
| `RF364` | Idioma de la plataforma |
| `RF365` | Moneda |
| `RF366` | Zona horaria |
| `RF367` | Logo y branding |
| `RF368` | Colores de tema |
| `RF369` | Configuración de email/SMS |
| `RF370` | Integraciones externas |

### 56. Configuración (APP - mismo que WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF371` | Idioma |
| `RF372` | Notificaciones |
| `RF373` | Privacidad y datos |
| `RF374` | Cerrar sesión |
| `RF375` | Versión de app |

### 57. Modo Offline (APP)

| ID | Requerimiento funcional |
| --- | --- |
| `RF376` | Caché local de productos |
| `RF377` | Visualizar menú sin internet |
| `RF378` | Guardar carrito localmente |
| `RF379` | Sincronizar cuando hay conexión |
| `RF380` | Indicador de conexión |
| `RF381` | Funcionalidades limitadas sin internet |

---

## REPORTES

### 58. Reportes y Estadísticas (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF382` | Ventas totales por período |
| `RF383` | Ingresos por producto |
| `RF384` | Top productos más vendidos |
| `RF385` | Clientes nuevos vs recurrentes |
| `RF386` | Ingresos por sucursal |
| `RF387` | Promedio de ticket |
| `RF388` | Cantidad de pedidos por día |
| `RF389` | Desempeño por repartidor |
| `RF390` | Cancelaciones y devoluciones |
| `RF391` | Exportar reportes (PDF, Excel) |

### 59. Analítica (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF392` | Gráficos de ventas en tiempo real |
| `RF393` | Tendencias de productos |
| `RF394` | Tasas de conversión |
| `RF395` | Comportamiento del cliente |
| `RF396` | Heatmap de horarios pico |
| `RF397` | Rentabilidad por producto |
| `RF398` | KPIs personalizados |
| `RF399` | Comparativo período anterior |
| `RF400` | Predicciones (Forecasting) |

---

## INTEGRACIONES

### 60. API (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF401` | REST API completa |
| `RF402` | Documentación de API |
| `RF403` | Autenticación OAuth2 |
| `RF404` | Rate limiting |
| `RF405` | Webhooks para eventos |
| `RF406` | Sandbox para pruebas |
| `RF407` | Claves API por aplicación |
| `RF408` | Historial de llamadas API |
| `RF409` | Monitoreo de uso |

### 61. Integraciones (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF410` | Pasarelas de pago (Stripe, MercadoPago) |
| `RF411` | SMS (Twilio) |
| `RF412` | Email (SendGrid, Gmail) |
| `RF413` | Google Maps |
| `RF414` | Redes sociales |
| `RF415` | Google Analytics |
| `RF416` | Zapier/IFTTT |
| `RF417` | Sistemas contables |
| `RF418` | Facebook Pixel |

### 62. SEO (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF419` | Meta descripción y títulos |
| `RF420` | URLs amigables |
| `RF421` | Sitemap XML |
| `RF422` | Robots.txt |
| `RF423` | Schema markup (JSON-LD) |
| `RF424` | Open Graph tags |
| `RF425` | Mobile-friendly checklist |
| `RF426` | Velocidad de página |

---

## ADMINISTRACIÓN

### 63. Auditoría (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF427` | Registro de todas las acciones |
| `RF428` | Quién, qué, cuándo, dónde |
| `RF429` | Cambios en datos sensibles |
| `RF430` | Intentos de acceso fallidos |
| `RF431` | Exportar logs de auditoría |
| `RF432` | Retención de logs configurable |
| `RF433` | Alertas de actividades sospechosas |

### 64. Backup y Recuperación (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF434` | Backup automático diario |
| `RF435` | Backup bajo demanda |
| `RF436` | Almacenamiento redundante |
| `RF437` | Encriptación de backups |
| `RF438` | Recuperación a punto específico |
| `RF439` | Validación de integridad de backup |
| `RF440` | Historial de backups |
| `RF441` | Período de retención configurable |

### 65. Horarios y Turnos (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF442` | Crear turno laboral |
| `RF443` | Asignar empleados a turno |
| `RF444` | Cambios de turno |
| `RF445` | Ausencias y permisos |
| `RF446` | Cálculo de horas trabajadas |
| `RF447` | Alertas de incumplimiento |
| `RF448` | Integración con nómina |

### 66. Sucursales (WEB)

| ID | Requerimiento funcional |
| --- | --- |
| `RF449` | Crear sucursal |
| `RF450` | Dirección y teléfono |
| `RF451` | Horarios por sucursal |
| `RF452` | Inventario independiente |
| `RF453` | Empleados asignados |
| `RF454` | Datos de contacto |
| `RF455` | Ventas por sucursal |

### 67. Usuarios y Permisos (APP)

| ID | Requerimiento funcional |
| --- | --- |
| `RF456` | Crear usuario en app (Empleado) |
| `RF457` | Asignar rol a usuario |
| `RF458` | Permisos específicos de app |
| `RF459` | Desactivar acceso |
| `RF460` | Cambiar contraseña |

---

## Notas de trazabilidad

Este archivo documenta los requerimientos según la fuente proporcionada. Las prioridades, responsables, historias de usuario, criterios de aceptación, pruebas y estado de desarrollo deberán definirse durante la planificación del proyecto.
