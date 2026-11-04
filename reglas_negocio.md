# Reglas de negocio — DESEGO

**Estado:** reglas consolidadas según el código del sitio web, panel administrativo, API móvil y app Android revisados el 3 de noviembre de 2026. Estas reglas describen el comportamiento vigente. Las brechas se separan al final para no tratarlas como funciones disponibles.

## 1. Alcance y datos oficiales

1. La tienda web, el panel y la app Android usan el mismo backend Laravel y la misma base MySQL/MariaDB configurada en el servidor.
2. MySQL/MariaDB es la fuente oficial para usuarios, catálogo, precios, disponibilidad, pedidos, reservas, pagos, inventario, fidelización y configuración.
3. La app Android no se conecta directamente a MySQL. Su base SQLite (`desego-offline.db`) conserva datos locales para lectura y cambios pendientes; una copia local no confirma por sí sola una operación comercial.
4. Cada cambio de negocio debe validarse en el servidor, aunque la interfaz ya haya validado o calculado un valor.
5. Los procesos que requieren confirmar pedidos, dinero, inventario o disponibilidad de mesas necesitan respuesta satisfactoria del servidor antes de mostrarse como confirmados.

## 2. Cuentas, roles y sesiones

1. El registro público crea una cuenta de cliente. El cliente no puede elegir `role_id`, `role_code` ni asignarse permisos de personal desde formularios o solicitudes API.
2. La app móvil admite exclusivamente cuentas con rol de cliente. El personal usa el panel web y solo puede ejecutar acciones autorizadas por los permisos vigentes del servidor.
3. Una cuenta inactiva no debe iniciar una sesión válida. Los intentos de autenticación fallidos se limitan; cinco contraseñas incorrectas provocan un bloqueo temporal de 15 minutos.
4. El inicio móvil utiliza sesión Laravel mediante cookies y requiere CSRF para solicitudes que modifican datos. La app conserva la cookie únicamente si corresponde según la opción “Recordarme”; no almacena la contraseña.
5. Cuando “Recordarme” está habilitado, el servidor entrega un token opaco de larga duración con vencimiento de 14 días y lo rota al restaurar la sesión. Android guarda las cookies cifradas con Android Keystore.
6. El registro y el ingreso nativo con Google activan persistencia por defecto en la app. Para Google, el servidor valida firma, emisor, audiencia, email verificado y un `nonce` temporal de un solo uso antes de autenticar.
7. Cerrar sesión invalida la sesión, revoca el token de “Recordarme” recibido y elimina la identidad y los datos privados en caché de esa cuenta en el dispositivo.
8. Una solicitud de recuperación de contraseña no debe revelar si el correo existe. El cambio de contraseña invalida tokens persistentes anteriores y sesiones vinculadas a la credencial antigua.

## 3. Propiedad y sincronización de datos

1. Las consultas de cuenta, direcciones, pedidos, reservas, favoritos y recompensas deben limitarse al cliente autenticado en el servidor; nunca se debe confiar en un identificador de cliente aportado por la app.
2. La caché Android se separa por ámbito público o por correo de cuenta normalizado. La cola de cambios de una cuenta solo puede enviarse si Laravel confirma la misma identidad.
3. El catálogo se puede leer desde la última instantánea SQLite o desde la semilla incluida en la app mientras no haya conexión. Sus precios, disponibilidad e imágenes pueden estar desactualizados hasta completar la sincronización.
4. La sincronización actual envía cambios pendientes antes de guardar la nueva instantánea. Solo se eliminan de la cola los cambios que el servidor confirma; los errores de validación quedan marcados para revisión.
5. Actualmente pueden ponerse en cola favoritos y cambios básicos de perfil. Las operaciones de pedido, reserva, pago, canje de puntos, creación de direcciones y reseñas requieren conexión y confirmación remota.
6. Un error de red no equivale a rechazo ni aceptación del servidor. La app conserva los datos locales y no debe repetir automáticamente una operación comercial no idempotente.
7. Al cerrar sesión o detectar que el servidor ya no reconoce la sesión, se eliminan las instantáneas privadas y cambios pendientes del ámbito de esa cuenta para impedir cruces entre usuarios.

## 4. Catálogo, precios y pedidos

1. El servidor decide qué productos y categorías están activos y pueden pedirse. La app puede ocultar o deshabilitar productos según la última respuesta, pero el backend vuelve a validar su estado al recibir una compra.
2. El cliente envía producto y cantidad; el backend calcula los precios, descuentos, tarifas y total oficiales. El total calculado en Android nunca es autoridad de cobro.
3. Los pedidos deben contener líneas y cantidades válidas y referenciar productos existentes. La disponibilidad de ingredientes/recetas se vuelve a comprobar dentro de la operación de creación.
4. La creación del pedido y el descuento de inventario asociado deben ejecutarse de forma coherente. Si una receta requiere más ingredientes de los disponibles, se rechaza la operación; no se permite stock negativo por esa venta.
5. Si un producto no tiene receta de inventario completa en el flujo de mesa, se puede registrar la advertencia prevista para que cocina verifique manualmente; esa advertencia no debe ocultarse como si el control de inventario hubiera sido completo.
6. La clave de checkout identifica un intento de compra. Repetir la misma clave debe devolver o reconocer el mismo resultado en vez de crear un pedido duplicado.
7. Los estados de pedido solo cambian mediante acciones válidas del flujo y con los permisos del actor. La cancelación debe registrar motivo y restituir ingredientes únicamente cuando el estado y consumo previo permitan hacerlo, dejando el movimiento trazable.
8. Los pedidos de delivery deben usar una dirección válida y aplicar tarifas y condiciones configuradas en el servidor. La app no puede forzar una zona, tarifa o cupón que el backend rechace.
9. Un pedido desde el móvil no se confirma offline. Si la conexión se interrumpe durante el checkout, el cliente debe consultar el resultado mediante el identificador o clave idempotente antes de reintentar.

## 5. Reservas y mesas

1. La disponibilidad se calcula con los horarios, la duración y la capacidad de las mesas configurados para el restaurante. Una solicitud para una fecha u hora pasada no se acepta.
2. Una reserva no puede exceder la capacidad conjunta de las mesas asignadas ni ocupar mesas que se solapen con otra reserva activa. Los bloqueos temporales deben expirar o liberarse conforme al flujo de reserva.
3. La selección de varias mesas debe persistirse como una sola asignación lógica de reserva; la interfaz esquemática no representa dimensiones físicas reales del salón.
4. El personal puede confirmar, asignar, sentar, finalizar, cancelar y reembolsar únicamente con las acciones y permisos disponibles para su rol. Cada acción financiera o de estado debe conservar su registro.
5. Una reserva iniciada en web o app solo se muestra como aceptada después de la respuesta del servidor; su estado posterior depende de la disponibilidad, la verificación y el pago configurados. La app no crea reservas offline.
6. El QR de una mesa identifica el acceso a una visita. Un token de participante se limita a esa sesión de mesa y no equivale a una sesión de cuenta de cliente ni a un permiso administrativo.
7. Los pedidos de mesa deben incluir una clave idempotente. Solicitudes de atención y pagos deben asociarse a la visita y participante válidos y no deben exponer pedidos de otras visitas.

## 6. Pagos, cobros y reembolsos

1. El importe y la moneda del intento de pago se fijan en el servidor a partir del pedido, reserva o saldo de mesa correspondiente.
2. El estado pendiente no se convierte en pagado por una redirección del navegador, un mensaje del móvil o una captura de pantalla. La confirmación depende de verificar la respuesta del proveedor en el backend.
3. Las URL de checkout de Niubiz son firmadas y temporales. Las credenciales del comercio permanecen en el servidor; la app no almacena secretos del proveedor ni datos completos de tarjetas.
4. Los callbacks y reintentos del proveedor deben ser idempotentes: una misma autorización no registra dos pagos ni cambia dos veces el estado financiero.
5. Los reembolsos requieren una acción autorizada y no pueden exceder el importe cobrado. Deben conservar referencia al pago e identidad del actor.
6. Si el proveedor no está configurado o no confirma una operación, el sistema informa el resultado pendiente/error y no marca el pedido o reserva como pagado.

## 7. Inventario, compras y caja

1. El inventario se registra por ingrediente y unidad definida. Cada entrada, salida, venta, recepción o ajuste debe generar un movimiento con cantidades anterior y nueva, referencia y actor cuando corresponda.
2. Las salidas no pueden dejar stock negativo. Los ajustes deben reflejar el conteo físico autorizado, no permitir al cliente cambiar el stock desde la tienda.
3. La recepción de compras debe corresponder a una orden y proveedor válidos, y sus líneas deben coincidir con los ingredientes y cantidades ordenados. Las cantidades recibidas no pueden ser negativas ni exceder las reglas del proceso.
4. Los importes, cantidades y valoraciones deben respetar las restricciones de dominio del esquema: precios y costos no negativos, cantidades positivas para líneas, valoraciones dentro de 1–5 y coordenadas dentro de rangos válidos.
5. Las operaciones de caja se realizan con sesión/estado de caja abierto y permisos de personal. Los movimientos deben tener importe positivo y conservar su relación con caja, pedido y actor.

## 8. Promociones, puntos y reseñas

1. Una promoción debe estar activa y dentro de su vigencia; su descuento debe respetar el tipo, el porcentaje máximo, el subtotal mínimo y el descuento máximo configurados.
2. El backend vuelve a validar el cupón y su elegibilidad al cotizar y al crear el pedido. La app no aplica un descuento únicamente porque aparezca guardado localmente.
3. El saldo de puntos se deriva de transacciones de fidelización registradas. Un canje no puede superar el saldo disponible y debe registrar tanto el débito como la recompensa entregada.
4. Una reseña se limita a las reglas de compra/cliente establecidas por el backend y su calificación debe ser de 1 a 5. El móvil no puede crear o editar reseñas de otra cuenta.
5. Los favoritos son únicos por cliente y producto; repetir “favorito activado” o “desactivado” debe dejar el mismo resultado sin duplicar registros.

## 9. Chatbot, privacidad y auditoría

1. El chatbot puede informar carta, precio, disponibilidad y datos públicos del restaurante. No confirma pedidos ni reservas, no representa al personal y no garantiza ausencia de alérgenos o contaminación cruzada.
2. Ante preguntas de alergias, seguridad alimentaria o un dato no publicado, debe derivar al personal y evitar inventar una respuesta.
3. Los datos enviados a proveedores de IA se limitan al contexto público y a los mensajes necesarios para responder. No se envían contraseñas, tokens, credenciales de pago ni secretos de infraestructura.
4. El historial administrativo del chatbot solo está disponible para los roles autorizados. Cada conversación mantiene aislamiento por sesión propietaria.
5. Las acciones relevantes de acceso, pagos, caja, inventario, roles, usuarios y restauración deben quedar auditables. Los registros no deben guardar contraseñas, tokens completos ni credenciales; la cobertura integral de auditoría debe comprobarse por módulo.

## 10. Integridad, migraciones y copias

1. Las tablas de negocio usan InnoDB y `utf8mb4`; las relaciones deben tener claves compatibles, índices apropiados y reglas de borrado que no eliminen información financiera accidentalmente.
2. Las restricciones `CHECK`, claves foráneas y unicidad en base de datos son la última línea de defensa, además de las validaciones Laravel.
3. Una migración que incorpore nuevas restricciones debe comprobar datos incompatibles y abortar con un diagnóstico. No debe borrar ni normalizar datos de producción silenciosamente.
4. Antes de compartir/importar una base, se debe ejecutar la auditoría de integridad. La exportación SQL debe incluir definición, datos y restricciones, fijar `utf8mb4` y probarse en una base desechable compatible con el hosting.
5. El respaldo de recuperación se cifra y se verifica. Una restauración requiere identificar el respaldo, confirmación explícita y una copia de seguridad previa del estado actual.
6. Un archivo SQL de exportación y un respaldo cifrado son artefactos distintos: el primero sirve para migrar/importar; el segundo para recuperación controlada.
7. La publicación no debe usar `.env` de desarrollo ni incluir claves privadas. En producción `APP_DEBUG` debe permanecer desactivado y la conexión a base de datos debe apuntar al esquema y usuario asignados por el hosting.

## 11. Brechas y decisiones pendientes

- Definir `/api/v1` y un contrato OpenAPI antes de publicar el API para terceros.
- Sustituir la sincronización por instantánea completa por sincronización incremental con cursor/versión cuando el volumen lo requiera.
- Cifrar los datos privados de SQLite y decidir cuánto tiempo conservar perfil, cuenta y cambios rechazados localmente.
- Completar una política de resolución/reintento de conflictos para cambios offline y hacer visible al usuario el estado de cada elemento de la cola.
- Ampliar operaciones offline solo con una estrategia idempotente. Checkout, pago y reserva continúan siendo online.
- Probar en Android real el cierre forzado, reinicio, expiración, rotación del token, revocación, cambio de cuenta y pérdida de red.
- Configurar sesiones/cache compartidos y workers si el despliegue pasa a varias instancias o requiere trabajos asíncronos; la configuración actual de producción usa almacenamiento de archivos y cola sincrónica.
- Completar y verificar la cobertura de auditoría para todos los cambios sensibles y acciones administrativas.
- Programar y verificar respaldos externos con retención y una restauración de ensayo en un entorno separado.
