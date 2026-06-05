# EspecificaciÃ³n de Requisitos

## 1. REQUISITOS FUNCIONALES (RF)

### MÃ³dulo: AutenticaciÃ³n y SesiÃ³n

- **RF-01: AutenticaciÃ³n de Usuarios**
  - DescripciÃ³n: Permite al usuario autenticarse con un nombre de usuario y contraseÃ±a, obteniendo acceso a la aplicaciÃ³n. Aplica al usuario autenticado sin distinción de rol.
  - Entradas y Validaciones Detalladas:
    - `usuario`: requerido, tipo texto.
    - `password`: requerido, tipo contraseÃ±a.
    - Verifica existencia del usuario en la tabla `usuarios` con la columna `usuario`.
    - Comprueba la contraseÃ±a usando `password_verify()` contra la contraseÃ±a hasheada almacenada en la base de datos.
  - Comportamiento como Plantilla: lógica centralizada en `views/login.php`; puede personalizarse extendiendo la consulta de usuarios, pero no está abstraída en un controlador separado.

- **RF-02: Cierre de SesiÃ³n**
  - DescripciÃ³n: Cierra la sesiÃ³n activa y redirige al usuario a la pantalla de login. Aplica al usuario autenticado sin distinción de rol.
  - Entradas y Validaciones Detalladas:
    - No recibe datos de formulario.
    - Destruye la sesiÃ³n completa con `session_destroy()`.
  - Comportamiento como Plantilla: simple y directo; se mantiene como plantilla estÃ¡ndar de sesiÃ³n PHP.

### MÃ³dulo: Router y NavegaciÃ³n Principal

- **RF-03: Control de Rutas Internas**
  - DescripciÃ³n: Muestra las pÃ¡ginas principales `ingreso`, `formatos`, `historial` y `graficas` mediante el parÃ¡metro `page` en `index.php`, protegiendo el acceso con sesiÃ³n activa. Aplica al usuario autenticado sin distinción de rol.
  - Entradas y Validaciones Detalladas:
    - `page`: opcional, valor permitido entre `ingreso`, `historial`, `formatos`, `graficas`; valores invÃ¡lidos caen en `home`.
    - Comprueba `$_SESSION['usuario_id']` antes de renderizar cualquier pÃ¡gina.
  - Comportamiento como Plantilla: el router en `index.php` es extensible agregando nuevas pÃ¡ginas al arreglo `validPages`.

### MÃ³dulo: GestiÃ³n de Empleados

- **RF-04: Registro Individual de Empleados**
  - DescripciÃ³n: Permite registrar empleados manualmente con nombre, cÃ©dula, cargo y Ã¡rea. Aplica al usuario autenticado sin distinción de rol.
  - Entradas y Validaciones Detalladas:
    - `nombre`: requerido, tipo texto, se trimmea y no puede estar vacÃ­o.
    - `documento`: requerido, tipo texto, se trimmea y no puede estar vacÃ­o.
    - `cargo`: opcional, tipo texto.
    - `area`: opcional, tipo texto.
    - Verifica unicidad de `cedula` en la tabla `empleados` antes de insertar.
  - Comportamiento como Plantilla: la validaciÃ³n de unicidad estÃ¡ implementada en el backend y se puede extender con reglas adicionales para otros campos.

- **RF-05: ImportaciÃ³n Masiva de Empleados desde Excel**
  - DescripciÃ³n: Carga un archivo `.xls` o `.xlsx` para registrar empleados en lote, omitiendo filas vacÃ­as o duplicadas. Aplica al usuario autenticado sin distinción de rol.
  - Entradas y Validaciones Detalladas:
    - `archivo_excel`: requerido, tipo archivo, extensiones permitidas `.xls`, `.xlsx`.
    - La primera fila se asume encabezado y se omite.
    - Cada fila requiere `nombre` y `cedula`; si falta alguno, se omite la fila.
    - Si la cÃ©dula ya existe en `empleados`, se considera duplicado y se omite.
    - Contabiliza: guardados, omitidos, duplicados, vacÃ­os.
  - Comportamiento como Plantilla: la importaciÃ³n utiliza `PhpOffice\\PhpSpreadsheet` y puede modificarse para soportar otros formatos o mapeos de columnas.

### MÃ³dulo: Formatos de Entrega y DotaciÃ³n EPP

- **RF-06: Registro de Formato de Entrega de EPP**
  - DescripciÃ³n: Genera un registro de entrega de EPP asociado a un empleado, con fecha, nÃºmero de dotaciÃ³n, responsable, representante SST, firmas y PDF opcional. Aplica al usuario autenticado sin distinción de rol.
  - Entradas y Validaciones Detalladas:
    - `empleado_id`: requerido, validado como entero positivo.
    - `fecha_entrega`: requerido, no puede estar vacÃ­o.
    - `numero_dotacion`: opcional, texto/trimeado.
    - `responsable_entrega`: asignado desde la sesiÃ³n actual (`usuario_id`).
    - `sst_id`: opcional, validado como entero si es enviado.
    - `elementos`: puede ser array de elementos seleccionados.
    - `observaciones`: opcional, texto.
    - `firma_empleado`: requerido, debe ser data URL de imagen base64 (`data:image/...;base64,`).
    - `firma_responsable`: opcional si la columna existe en la tabla `entregas`.
    - `archivo_pdf`: opcional, si existe debe ser PDF vÃ¡lido y `size <= 5 MB`.
    - Requisito de contenido: al menos un elemento seleccionado, observaciones o archivo PDF para aceptar la entrega.
    - Se guarda firma de empleado como PNG y opcionalmente firma responsable.
    - Inserta los datos en transacciÃ³n, con commit/rollback.
  - Comportamiento como Plantilla: la lÃ³gica detecta columnas opcionales (`firma_responsable`, `firma_sst`, `usuario_id`) y admite extensiones por esquema de base de datos.

- **RF-07: GestiÃ³n DinÃ¡mica de Elementos Permitidos**
  - DescripciÃ³n: Permite extender la lista de elementos de entrega mediante la tabla opcional `elementos_permitidos`, cargando elementos base y personalizados por usuario. Aplica al usuario autenticado sin distinción de rol.
  - Entradas y Validaciones Detalladas:
    - `accion`: `guardar` o `obtener` vÃ­a POST en `includes/elementos_handler.php`.
    - `nombre`: requerido para `guardar`, no vacÃ­o.
    - Comprueba existencia de la tabla `elementos_permitidos`; si no existe, retorna lista estÃ¡ndar o error con mensaje de plantilla.
    - En `guardar`, verifica unicidad por `usuario_id` y `nombre_elemento`.
  - Comportamiento como Plantilla: diseÃ±ado para ser extensible; si la tabla `elementos_permitidos` existe, permite personalizar la plantilla de elementos sin cambiar el cÃ³digo base.

### MÃ³dulo: Historial y Reportes

- **RF-08: Listado de Empleados y Filtros de Historial**
  - DescripciÃ³n: Muestra empleados y permite filtrar por texto, cargo, Ã¡rea, fecha, mes y aÃ±o. Aplica al usuario autenticado sin distinción de rol.
  - Entradas y Validaciones Detalladas:
    - `buscador`: opcional, texto para buscar en `empleados.nombre` y `empleados.cedula`.
    - `filtroCargo`: opcional, texto exacto.
    - `filtroArea`: opcional, texto exacto.
    - `filtroFecha`: opcional, fecha en formato ISO (`YYYY-MM-DD`).
    - `filtroMes`: opcional, entero > 0.
    - `filtroAnio`: opcional, entero > 0.
    - La consulta utiliza `LIMIT 10 OFFSET ...` para paginar resultados.
    - Detecta entregas incompletas sin detalle ni PDF y alerta al usuario.
  - Comportamiento como Plantilla: los filtros se construyen dinÃ¡micamente en SQL y pueden extenderse con nuevos criterios de bÃºsqueda.

- **RF-09: VisualizaciÃ³n del Historial de Entregas de un Empleado**
  - DescripciÃ³n: Lista entregas de un empleado, permite ordenar por fecha y filtrar por fecha, y accede al detalle de cada entrega. Aplica al usuario autenticado sin distinción de rol.
  - Entradas y Validaciones Detalladas:
    - `empleado_id`: requerido, debe ser entero positivo.
    - `orden`: opcional, valores `asc` o `desc`; por defecto `desc`.
    - `fecha`: opcional, fecha en formato ISO.
    - PaginaciÃ³n de 10 entregas por pÃ¡gina.
  - Comportamiento como Plantilla: la salida ordenada y paginada puede ser extendida para otros criterios de orden o filtros adicionales.

- **RF-10: Detalle y EdiciÃ³n de una Entrega**
  - DescripciÃ³n: Muestra el detalle de una entrega, permite editar Ã­tems/observaciones, actualizar firmas y eliminar la entrega. Aplica al usuario autenticado sin distinción de rol.
  - Entradas y Validaciones Detalladas:
    - `entrega_id`: requerido, entero positivo.
    - `tipo`: para actualizaciÃ³n de firma, valores `empleado`, `responsable`, `sst`.
    - `firma_dibujada`: opcional data URL PNG de firma; si se proporciona, se decodifica y valida.
    - `firma`: opcional, archivo JPG/PNG mÃ¡ximo 2 MB para firma.
    - `elementos[]`: pueden enviarse con formato `elemento|cantidad` para editar detalles.
    - `observaciones`: opcional, texto.
    - En modo ediciÃ³n, se eliminan los detalles previos y se insertan los nuevos datos.
    - Al eliminar, se usa transacciÃ³n y limpia `entregas_detalle` y `entregas` en bloque.
  - Comportamiento como Plantilla: el detalle de la entrega es flexible y permite que el usuario ajuste Ã­tems con cantidades y observaciones sin alterar la estructura de base de datos principal.

- **RF-11: GeneraciÃ³n de Informe PDF Consolidado**
  - DescripciÃ³n: Crea un informe PDF de las entregas de un empleado e incluye PDF escaneado existente si existe. Aplica al usuario autenticado sin distinción de rol.
  - Entradas y Validaciones Detalladas:
    - `empleado_id`: requerido, entero positivo.
    - Recupera datos de `entregas` y `entregas_detalle` para el empleado.
    - Si existe `pdf_file` en la entrega, lo anexa al PDF final.
    - Genera pÃ¡ginas con Dompdf y FPDI.
  - Comportamiento como Plantilla: el formato HTML del informe estÃ¡ definido en `includes/informe_pdf.php` y puede personalizarse para nuevos campos, logos o estilos.

- **RF-12: VisualizaciÃ³n de PDF de Entrega**
  - DescripciÃ³n: Sirve el archivo PDF almacenado de una entrega para visualizaciÃ³n en lÃ­nea. Aplica al usuario autenticado sin distinción de rol.
  - Entradas y Validaciones Detalladas:
    - `id`: requerido, entero positivo.
    - Recupera `pdf_file` de la tabla `entregas` y verifica existencia de fichero en `uploads/`.
    - Retorna encabezado `Content-Type: application/pdf` y el contenido del archivo.
  - Comportamiento como Plantilla: mÃ³dulo mÃ­nimo que puede integrarse con visores PDF o almacenamiento externo.

### MÃ³dulo: VisualizaciÃ³n AnalÃ­tica

- **RF-13: GrÃ¡ficas de Entregas**
  - DescripciÃ³n: Genera grÃ¡ficas de entregas por Ã¡rea, empleado, elemento o tiempo mediante Chart.js. Aplica al usuario autenticado sin distinción de rol.
  - Entradas y Validaciones Detalladas:
    - `tipo`: opcional, valores `area`, `empleado`, `elemento`, `tiempo`; por defecto `area`.
    - Cada tipo ejecuta una consulta SQL distinta para agrupar registros y retorna JSON de etiquetas/datos.
    - `tiempo` limita a 12 meses, `empleado` y `elemento` se limitan a top 10.
  - Comportamiento como Plantilla: el sistema usa un solo endpoint para cambiar el tipo de grÃ¡fica y facilita aÃ±adir nuevos tipos de mÃ©tricas.

## 2. REQUISITOS NO FUNCIONALES (RNF)

### Rendimiento y UX (Restricciones de Render/Aiven)

- **RNF-01: Feedback de carga en transiciones**
  - El sistema debe mostrar indicadores de carga visibles en operaciones que implican cambio de secciÃ³n o envÃ­o de formularios extensos (`ingreso`, `formatos`, `graficas`, `historial`), para mitigar latencia de Render/Aiven en arranques en frÃ­o.
  - ObservaciÃ³n: el cÃ³digo actual no incluye spinner/skeleton; por tanto, debe aÃ±adirse UX que informe al usuario durante consultas y cargas.

- **RNF-02: PaginaciÃ³n de consultas para evitar sobrecarga**
  - El listado de empleados y entregas debe usar paginaciÃ³n de 10 registros por pÃ¡gina, como ya implementa `pages/historial.php`, para limitar el tamaÃ±o de las consultas y mejorar respuesta en bases de datos lentas.

- **RNF-03: ReducciÃ³n de cargas pesadas en cold start**
  - Las rutas que usan librerÃ­as pesadas (`PhpOffice\PhpSpreadsheet`, `Dompdf`, `Fpdi`) deben activarse solo cuando se requieren, evitando inicializaciÃ³n innecesaria en todas las solicitudes.
  - El sistema debe mantener la conexiÃ³n DB en `config/database.php` lo mÃ¡s ligera posible y usar variables de entorno para no recargar configuraciones estÃ¡ticas.

- **RNF-04: Uso de CDNs y recursos estÃ¡ticos**
  - El proyecto debe seguir utilizando recursos externos CDN para Bootstrap, jQuery y Chart.js, reduciendo el peso local del bundle y mejorando tiempos de primer render en despliegues gratuitos.

- **RNF-05: Manejo de cargas de archivos y operaciones de exportaciÃ³n**
  - Las importaciones de Excel y la generaciÃ³n de PDF no deben bloquear la interfaz sin seÃ±al visual; el proceso debe notificar Ã©xito/fallo claramente.
  - El sistema debe validar y rechazar archivos no permitidos antes de procesarlos para evitar retrabajos y latencias innecesarias.

### Mantenibilidad y Extensibilidad

- **RNF-06: Arquitectura monolÃ­tica modular**
  - La aplicaciÃ³n debe mantener la separaciÃ³n entre la capa de presentaciÃ³n (`pages/` y `views/`), la configuraciÃ³n de base de datos (`config/database.php`) y los servicios auxiliares (`includes/`). Esto ya estÃ¡ parcialmente implementado y facilita la extensiÃ³n.

- **RNF-07: ConfiguraciÃ³n mediante variables de entorno**
  - La conexiÃ³n a la base de datos debe basarse en `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_PORT`, `DB_SSL_MODE` y `DB_CHARSET`, permitiendo adaptarse a distintos entornos sin modificar cÃ³digo.

- **RNF-08: Esquema flexible y personalizaciÃ³n del template**
  - El sistema debe admitir tablas opcionales (`elementos_permitidos`) y columnas opcionales en `entregas` para personalizar campos de firma y otros campos sin romper la aplicación.
  - Esta capacidad de detecciÃ³n de esquema es un requisito de extensibilidad implÃ­cito en `pages/formatos.php` y `pages/historial_detalle.php`.

- **RNF-09: Legibilidad y reutilizaciÃ³n de cÃ³digo**
  - Las validaciones y sanitizaciones deben permanecer en el backend PHP, con mÃ­nimo acoplamiento entre lÃ³gica de datos y vista, para facilitar la modificaciÃ³n futura.

- **RNF-10: PersonalizaciÃ³n de listas de elementos**
  - Los elementos de plantilla base deben poder ampliarse con datos de la tabla `elementos_permitidos`, haciendo de la aplicaciÃ³n una plantilla personalizable para distintos procesos de entrega.

### Seguridad y Disponibilidad

- **RNF-11: ProtecciÃ³n de rutas y sesiones**
  - Todas las pÃ¡ginas que forman parte del panel (`index.php`, `pages/*`, `includes/*`) deben verificar `$_SESSION['usuario_id']` y redirigir a `views/login.php` si no existe sesiÃ³n activa.

- **RNF-12: PrevenciÃ³n de inyecciÃ³n SQL**
  - Todas las consultas sensibles deben usar consultas preparadas con `bind_param()` como ya se hace en `views/login.php`, `pages/ingreso.php`, `pages/formatos.php`, `pages/historial.php`, `pages/historial_detalle.php`, `includes/elementos_handler.php`, `includes/ver_pdf.php`.

- **RNF-13: SanitizaciÃ³n de salida**
  - Los datos mostrados en la interfaz deben escaparse con `htmlspecialchars()` para evitar XSS en nombres, cÃ©dulas, cargos, Ã¡reas y mensajes de la aplicaciÃ³n.

- **RNF-14: Restricciones de carga de archivos**
  - `archivo_pdf` debe tener MIME `application/pdf` y tamaÃ±o mÃ¡ximo 5 MB.
  - `archivo_excel` debe aceptar solo extensiones `.xls` y `.xlsx`.
  - Las firmas en archivos de imagen pueden subirse solo como `image/jpeg`, `image/pjpeg` o `image/png` y con tamaÃ±o mÃ¡ximo 2 MB.

- **RNF-15: Disponibilidad en operaciones crÃ­ticas**
  - Las operaciones de escritura complejas (registro de entrega, eliminaciÃ³n de entrega) deben usar transacciones para asegurar consistencia y disponibilidad de datos.

- **RNF-16: Manejo de errores y comunicaciÃ³n al usuario**
  - El sistema debe proporcionar mensajes claros de error y Ã©xito para el usuario en operaciones crÃ­ticas, como registro de empleado, importaciÃ³n Excel, guardado de formato, ediciÃ³n de entrega y eliminaciÃ³n.

---

### Observaciones generales

- La estructura actual usa un router basado en `page` que permite agregar nuevas pÃ¡ginas con un arreglo `validPages`.
- El proyecto estÃ¡ diseÃ±ado como una plantilla monolÃ­tica PHP con lÃ³gica de negocio incrustada en pÃ¡ginas PHP, por lo que las futuras mejoras deben centralizar validaciones y reutilizar funciones auxiliares.
- El documento se centra en los requisitos detectados directamente en el cÃ³digo fuente del proyecto y en las validaciones implementadas en cada mÃ³dulo.
