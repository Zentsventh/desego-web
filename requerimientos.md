# Requerimientos funcionales — Deseogo

## Información general

- **Proyecto:** DESEGO
- **Ámbito:** Sistema web de administración y aplicación móvil.
- **Origen:** Hoja de cálculo de módulos y requerimientos.
- **Tipo:** Requerimientos funcionales (RF).
- **Módulos registrados:** 69 (67 originales y 2 ampliaciones)
- **Requerimientos registrados:** 505 (460 originales y 45 ampliaciones)
- **Estado de implementación:** Parcial; este documento distingue capacidades comprobadas en el código, alcance existente y brechas pendientes. La existencia de una pantalla o un controlador no certifica por sí sola un requerimiento.

### Alcance vigente

- Alcance vigente: tienda web, panel de administración y aplicación Android nativa para clientes.
- La app permite consultar el catálogo, mantener carrito local, autenticarse, administrar parte de la cuenta, comprar, reservar y usar flujos de mesa QR. No es una app operativa para empleados.
- Los módulos móviles de caja (31), toma de pedidos por empleados (32), cocina (34) y administración de usuarios (67) quedan diferidos. Se conservan sus RF originales por trazabilidad.
- La autenticación y sesión de `/api/mobile` solo admiten clientes; el dashboard administrativo no está disponible desde ese acceso. El personal utiliza el panel web y sus permisos.

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
- [ASISTENCIA VIRTUAL](#asistencia-virtual) — 1 módulo
- [PLATAFORMA MÓVIL Y DATOS](#plataforma-movil-y-datos) — 1 módulo

## Convenciones

- `RF###`: identificador original de cada requerimiento funcional.
- **WEB**: funcionalidad definida para la interfaz web.
- **APP**: funcionalidad definida para la aplicación móvil.
- **APP - mismo que WEB**: indica relación funcional con la versión web; se conservan los requerimientos específicos de la app.
- Los códigos originales se mantienen tal como aparecen en el Excel, sin renumerarlos.
- Las ampliaciones continúan desde los módulos 68–69 y `RF461`.
- El chatbot amplía la FAQ automática de `RF322`. El chat humano y los tickets conservan sus requerimientos originales y no se consideran implementados por incorporar el asistente web.
- El módulo 69 registra el contrato móvil, el almacenamiento local, la sincronización y las reglas de integridad y exportación de datos observadas en el proyecto.

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
| `RF481` | Crear y gestionar cuentas desde el panel web, asignarles un rol existente, cambiar su estado y aplicar sus permisos en la siguiente solicitud; proteger las cuentas administradoras frente a cambios no autorizados. Ampliación del alcance web. |

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
| `RF482` | Ofrecer únicamente fechas y horarios actuales o futuros según America/Lima, actualizarlos mientras el formulario está abierto y volver a validarlos en el servidor. Aplicar el horario de atención cuando la sede lo tenga configurado. |
| `RF483` | Seleccionar visualmente una o varias mesas disponibles para un mismo horario, consultar su zona y capacidad y crear una sola reserva con todas las mesas elegidas. |
| `RF484` | Informar si el grupo ocupará una sola mesa, mesas separadas o mesas que pueden unirse. El personal configura los grupos de unión según la ubicación física real; la vista esquemática no acredita proximidad entre mesas. |
| `RF485` | Validar la capacidad conjunta y la disponibilidad de todas las mesas al guardar, impedir solapamientos desde web y panel y liberar todas las mesas al cancelar la reserva. |
| `RF486` | Mostrar las mesas asignadas y la distribución del grupo en la confirmación de la web, el historial del cliente y el listado administrativo de reservas. |

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

## ASISTENCIA VIRTUAL

### 68. Asistente virtual y chatbot (WEB adaptable)

**Relación con módulos existentes:** Soporte y Chat (WEB), módulo 48, especialmente `RF322` (FAQ automático); Menú y Productos (WEB), módulo 5; Delivery (WEB), módulo 15; Reservas (WEB), módulo 21; Integraciones (WEB), módulo 61. Este módulo complementa esos flujos y orienta al usuario hacia sus formularios.

| ID | Requerimiento funcional |
| --- | --- |
| `RF461` | Abrir y cerrar un asistente flotante desde la tienda, con bienvenida y preguntas sugeridas sobre carta, delivery y reservas. |
| `RF462` | Responder consultas sobre platos y precios usando el catálogo real consultado en cada mensaje, sin inventar información comercial. |
| `RF463` | Mostrar la disponibilidad actual de los platos y distinguir expresamente los agotados. |
| `RF464` | Orientar sobre delivery y recojo, informar la tarifa configurada cuando exista y solicitar confirmar la cobertura con el local. |
| `RF465` | Explicar cómo solicitar una reserva y ofrecer acceso directo al formulario, sin confirmar mesas ni crear reservas desde el chat. |
| `RF466` | Informar dirección, distrito, teléfono y horario de la sucursal cuando estén publicados; indicar cuando falten datos. |
| `RF467` | Remitir consultas de ingredientes, alérgenos e intolerancias al personal, sin garantizar ausencia de alérgenos ni contaminación cruzada. |
| `RF468` | Conservar hasta 40 mensajes en la pestaña y usar los últimos 10 turnos guardados por el servidor como contexto de una conversación existente, excluyendo errores. El contexto de IA se limita a 1200 caracteres por turno. |
| `RF469` | Iniciar una nueva conversación, eliminando el historial visible y sustituyendo el historial guardado en la pestaña por la bienvenida. |
| `RF470` | Integrar Gemini y Groq con instrucciones restringidas al restaurante y sus datos publicados; mantener las credenciales exclusivamente en el servidor y configurar los modelos mediante variables de entorno. |
| `RF471` | Ejecutar la cadena Gemini principal → Gemini secundaria → Groq principal → Groq secundaria → carta local ante errores, cuota agotada, demora de conexión o respuestas vacías; sin mostrar credenciales ni detalles internos al usuario. |
| `RF472` | Responder preguntas frecuentes desde el catálogo cuando no haya claves, el proveedor falle, devuelva una respuesta vacía o no pueda conectarse. Las respuestas locales cubren carta, precios, disponibilidad, delivery, reservas, contacto y advertencias sobre alérgenos. |
| `RF473` | Mostrar mensajes comprensibles ante fallos de red, demora excesiva, sesión expirada, límite de solicitudes o catálogo inaccesible, conservando el texto para reintentar. |
| `RF474` | Validar mensajes de hasta 600 caracteres e historial de hasta 12 turnos de 1200 caracteres; admitir únicamente roles usuario/asistente, impedir envíos simultáneos y limitar a 20 solicitudes por minuto por cliente. |
| `RF475` | Proteger los envíos con la sesión y CSRF, renderizar las respuestas como texto y limitar la información compartida con el proveedor al catálogo público y la conversación enviada. |
| `RF476` | Adaptar el chat a escritorio y móvil, permitir cerrar con Escape y devolver el foco al lanzador, anunciar mensajes de manera accesible y respetar la preferencia de movimiento reducido. |
| `RF477` | Guardar conversaciones y mensajes del chatbot en la base de datos, con fecha, rol, proveedor/modelo y errores; informar al cliente que se conserva la conversación para seguimiento. |
| `RF478` | Permitir al personal autorizado buscar y consultar chats desde el panel, con paginación de conversaciones e identificación del cliente cuando está autenticado. |
| `RF479` | Vincular cada conversación a su sesión propietaria, impedir que otra sesión la continúe y recuperar el contexto guardado en el servidor. Reiniciar el chat inicia otra conversación sin borrar el historial administrativo. |
| `RF480` | Omitir temporalmente credenciales fallidas mediante un período de espera, respetar `Retry-After` numérico dentro de límites y limitar el tiempo de cada intento para que la cadena termine antes del tiempo máximo del cliente. |

**Criterios de aceptación:** el usuario puede abrir el chat, consultar un precio real, identificar un plato agotado, recibir orientación de delivery y acceder a reservas. Sin credenciales o con el proveedor fuera de servicio, las consultas frecuentes siguen funcionando desde el catálogo. Si el catálogo falla, se informa el problema sin inventar datos. La conversación se conserva al recargar la misma pestaña y puede reiniciarse.

**Validación técnica:** `tests/Feature/ChatbotTest.php` cubre proveedores, cadena de claves, espera tras errores de cuota, contexto guardado, respuestas locales, catálogo actualizado, disponibilidad, aislamiento de sesiones, errores, validación y límite de solicitudes. `tests/Feature/AdminAccessAndChatbotTest.php` cubre acceso al historial, gestión de roles, asignación de usuarios y restricción móvil. Se comprobaron respuestas reales de las cuatro credenciales configuradas sin registrar sus valores. Las pruebas automatizadas simulan los proveedores. El almacenamiento del navegador conserva la vista durante la sesión de la pestaña; los mensajes registrados en la base de datos permanecen disponibles para seguimiento. Las respuestas locales usan la consulta actual y no interpretan todo el historial como un modelo de IA.

## PLATAFORMA MÓVIL Y DATOS

### 69. Aplicación Android, API móvil y protección de datos

Estos requisitos describen el comportamiento actual del cliente Android nativo y la conexión con el sistema web. “Implementado en código” no sustituye pruebas en un dispositivo ni certifica la operación en producción.

| ID | Requerimiento funcional | Estado revisado |
| --- | --- | --- |
| `RF487` | Exponer operaciones de la app mediante respuestas JSON en `/api/mobile/*`, con sesión Laravel y protección CSRF para solicitudes que modifican datos. | Implementado en código. API versionada y especificación OpenAPI pendientes. |
| `RF488` | Admitir cuentas de clientes en la app y reservar el panel de personal y sus permisos para la web. | Implementado en código; las rutas móviles rechazan el acceso administrativo. |
| `RF489` | Mantener MySQL del servidor como fuente oficial para cuentas, catálogo, precios, inventario, pedidos, pagos y reservas compartidos con la web. | Implementado mediante el backend Laravel. Android no accede directamente a MySQL. |
| `RF490` | Ofrecer en Android tienda de cliente con catálogo, carrito, checkout, direcciones, cuenta, favoritos, reseñas, fidelización, reservas y atención en mesa con QR. | Parcialmente implementado; consultar brechas de la revisión móvil al final. |
| `RF491` | Permitir elegir “Recordarme” en el inicio por contraseña; con la opción activa guardar cookies cifradas en Android Keystore y utilizar el token persistente del servidor, renovable por hasta 14 días. | Implementado en código. Compilación Android en entorno JDK pendiente. |
| `RF492` | Aplicar persistencia coherente a registro e ingreso nativo con Google; validar identidad, audiencia, emisor, firma, email verificado y desafío de un solo uso antes de crear sesión. | Implementado en código; el inicio Google y el registro activan persistencia por defecto. |
| `RF493` | Cerrar sesión invalidando la sesión remota, revocando el token persistente y eliminando del dispositivo la identidad y los datos privados de esa cuenta. | Implementado en código; se debe comprobar en dispositivo y contra el hosting. |
| `RF494` | Mantener una base SQLite privada de la app para instantáneas locales del catálogo y cuenta, carrito, estado local y operaciones pendientes, separando los datos por cuenta. | Implementado parcialmente. Los datos de cuenta en SQLite no tienen cifrado a nivel de base de datos. |
| `RF495` | Mostrar el catálogo guardado y el carrito local si no hay red; indicar que los datos pueden estar desactualizados. | Implementado parcialmente; el catálogo también incluye una semilla local inicial. |
| `RF496` | Poner en cola favoritos y cambios básicos de perfil cuando no haya red; reenviarlos al recuperar conexión solo si la sesión remota pertenece a la misma cuenta. | Implementado en código para favoritos y perfil básico; el resto de mutaciones no se sincroniza offline. |
| `RF497` | Confirmar una mutación pendiente únicamente cuando el servidor responde correctamente y conservar los errores recuperables para que puedan revisarse. | Implementado parcialmente mediante la bandeja local de sincronización; falta una pantalla completa de resolución de conflictos. |
| `RF498` | No confirmar pedidos, reservas ni pagos offline; recalcular y validar cantidades, precios, promociones, disponibilidad y totales en el servidor. | Implementado en código para checkout y flujos remotos. |
| `RF499` | Resolver reservas contra disponibilidad del servidor y mantener las operaciones que comprometen una mesa sujetas a confirmación remota. | Implementado en flujos online; reservas offline no disponibles. |
| `RF500` | Separar el acceso a mesa QR de la sesión de cuenta, usar un token de participante por visita y una clave idempotente para impedir pedidos duplicados por reintentos. | Implementado en código para el flujo de mesa. |
| `RF501` | Abrir pagos Niubiz desde un intento firmado y registrar el resultado solo tras validar la respuesta del proveedor. | Implementado en los flujos disponibles; requiere credenciales y pruebas del comercio en el entorno de destino. |
| `RF502` | Usar InnoDB, `utf8mb4`, claves primarias, índices, relaciones y restricciones de dominio para preservar integridad en MySQL/MariaDB. | Implementado en esquema y migraciones; la auditoría debe ejecutarse sobre cada instalación. |
| `RF503` | Detener migraciones de integridad si los datos existentes no cumplen las nuevas restricciones y reportar la causa antes de modificar el esquema. | Implementado con verificaciones previas en migraciones. |
| `RF504` | Generar una exportación SQL portable desde el servidor después de auditar relaciones e integridad, incluyendo esquema, datos y restricciones con codificación `utf8mb4`. | Implementado mediante `php artisan desego:export`; importar primero en una base de prueba del hosting. |
| `RF505` | Crear respaldos cifrados verificables y exigir confirmación explícita y respaldo previo para una restauración destructiva. | Implementado mediante los comandos de respaldo y restauración; retención y copia externa deben configurarse en producción. |

## Revisión de web y panel: implementación y brechas

| Área | Resultado de la revisión |
| --- | --- |
| Tienda web y cuenta de cliente | La web comparte catálogo y cuenta de cliente con Android mediante Laravel/MySQL. Hay flujos de carrito, pedidos, direcciones, reservas, favoritos, reseñas y fidelización; los datos privados dependen de sesión y permisos del servidor. La verificación por RF de impuestos, comprobantes y todas las variantes de checkout sigue abierta. |
| Roles y usuarios, RF026–RF033 y RF481 | Gestión separada de usuarios y roles, creación y edición, duplicación, matriz, historial de cambios, asignación y activación/desactivación. Corregido el acceso que exigía el permiso inexistente `roles.view`. El conjunto exacto de roles preestablecidos de RF028 conserva una diferencia: el rol cliente se crea al registrarse y Dueño puede definirse como rol personalizado. |
| Caja y personal | Corregidos los permisos inexistentes `cash.view` y `staff.view`, y los nombres de acciones enviados por los formularios de caja y personal. No se certifican nómina, desempeño ni ausencias por esta corrección. |
| Catálogo, ingredientes e inventario | Gestión de categorías, ingredientes y alérgenos accesible desde el panel, con asociación de alérgenos a ingredientes, stock inicial según permisos y activación/desactivación de categorías y alérgenos. Corregidos el listado principal de productos, las acciones de creación/edición y el envío de ajustes al endpoint de inventario. Pendientes de desarrollo o verificación individual: imágenes administrables, subcategorías, variantes, bundles, orden manual y stock independiente por sucursal. |
| Pedidos, cocina y reparto | Corregidos tipo de pedido, pago pendiente inicial y acciones/identificadores de cocina y reparto. El panel permite asignar repartidor, editar contacto/dirección y hora estimada, y registrar incidencias. La gestión avanzada de modificaciones, prioridades y seguimiento GPS no queda certificada por esos cambios. |
| Clientes y reservas | Fechas y horas pasadas bloqueadas, selección visual de varias mesas con capacidad conjunta, grupos de unión configurables y asignación visible en confirmación, cuenta y panel. La vista de mesas es esquemática; no representa un plano físico medido. La eliminación de cuentas, validación de email/teléfono y recordatorios automáticos requieren trabajo adicional. |
| Proveedores, promociones y fidelización | Corregidas las acciones de altas y edición, código de empleado/recompensa, fechas de vigencia y tipo/importe de recompensa. Se corrigió un parámetro SQL sobrante que impedía crear promociones. La vigencia se edita en hora de Perú y se convierte al formato UTC esperado por el servidor. El personal puede vincularse a una cuenta con rol y a una sucursal. Niveles, beneficios por nivel y promociones por producto/sucursal u horario siguen pendientes de validación o ampliación. |
| Soporte y chatbot | Historial persistente del bot, búsqueda administrativa, permisos de lectura y cadena de IA implementados. La atención humana, adjuntos, SLA y notificaciones externas son funciones distintas y conservan su propia validación pendiente. |
| App Android para clientes | Aplicación nativa Kotlin/Compose con catálogo, carrito, cuenta, favoritos, checkout, reservas y mesa QR. Comparte el backend Laravel y MySQL de la web. La app de caja/cocina/personal queda fuera del alcance actual. El funcionamiento offline es parcial y no confirma pedidos ni pagos. |
| Sesión móvil | Sesión Laravel con cookies y CSRF; “Recordarme” persiste cookies cifradas en Android y habilita el token renovable del servidor. Registro y Google usan persistencia por defecto. La compilación Kotlin y las pruebas en dispositivos reales siguen pendientes de un entorno Android con JDK/SDK. |
| API y sincronización móvil | Endpoints JSON bajo `/api/mobile/*`, sin versión `/v1` ni contrato OpenAPI. Sincronización actual descarga una instantánea completa; el cliente encola favoritos y perfil básico. API versionada, sincronización incremental y resolución completa de conflictos quedan pendientes. |
| Base de datos y despliegue | MySQL/MariaDB es la fuente central; migraciones agregan restricciones y verificaciones previas. El exportador `desego:export` audita la base antes de producir SQL portable; `desego:backup` y `desego:restore` manejan respaldos cifrados. La importación debe probarse en una base vacía de cPanel antes de producción. |
| Pagos y facturación | Existen flujos Niubiz para pedidos/reservas/mesa según configuración. Esto no equivale a completar Stripe/MercadoPago/PayPal, pagos parciales, 3D Secure ni emisión electrónica SUNAT. Esas integraciones necesitan implementación y pruebas propias con el proveedor elegido. |
| Mapas, zonas y repartidores | Faltan o requieren ampliación los polígonos de cobertura, seguimiento GPS, documentos de repartidores, asignación automática y liquidaciones. La tarifa general y los datos de entrega existentes no sustituyen esos RF. |
| Reseñas, favoritos y búsqueda | Hay controladores y vistas de cliente, pero fotos, respuestas a reseñas, listas compartidas, búsqueda guardada, autocompletado y filtros avanzados requieren revisión funcional específica. |
| Notificaciones, autenticación e integraciones | Google está integrado para la app y web con validación de identidad; la autenticación Android conserva sesión persistente según RF491–RF493. Email/SMS y push, verificación general de email/teléfono, 2FA, OAuth2 para terceros y automatizaciones externas siguen pendientes. La configuración de correo en modo log no acredita entrega. |
| Reportes, analítica, auditoría, backups y sucursales | Existen controladores y comandos, con cobertura parcial. Forecasting, conversión, rentabilidad, exportaciones, auditoría integral y aislamiento de sucursales requieren pruebas por RF. El respaldo manual/verificable existe; horarios automáticos, redundancia y almacenamiento externo dependen de configuración operativa del servidor. |
| Seguridad de datos móviles | Las cookies de sesión están cifradas con Android Keystore; la base SQLite que guarda perfiles/cuentas no tiene cifrado propio. Prioridad pendiente: cifrar datos privados locales, definir retención/borrado y probar revocación de sesión en reinstalación y cambio de dispositivo. |
| Escalado y operación | `.env.production` usa sesiones/cache de archivos y cola sincrónica. Adecuado para despliegue de una instancia con almacenamiento persistente; para varias instancias o trabajos asíncronos se debe migrar a almacenes compartidos (Redis/DB) y operar workers. |
| SEO | Debe verificarse individualmente sitemap, robots, metadatos, Schema.org, Open Graph y rendimiento; no se declara completado por renderizar la tienda. |

Los RF pendientes de esta tabla siguen siendo trabajo abierto. Esta revisión no declara completados los 505 requerimientos y no cambia su definición original para hacerla coincidir con la implementación actual.

---

## Notas de trazabilidad

Este archivo conserva los 67 módulos y 460 RF de la fuente original. Añade el módulo 68 con RF461–RF480 (chatbot web), RF481 al módulo 4 (usuarios y roles), RF482–RF486 al módulo 21 (reservas con selección de mesas) y el módulo 69 con RF487–RF505 (app, API y datos). Total actual: 69 módulos y 505 RF, de los cuales 45 son ampliaciones. Los CSV de `resources/desego/original-docs/` se conservan como copia de los requerimientos originales. Las prioridades y responsables de las brechas indicadas deben definirse durante la planificación del proyecto.
