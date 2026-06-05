# Documentación Técnica de la Base de Datos

## 1. Diseño actual de la base de datos

El sistema almacena la gestión de usuarios, empleados, entregas de EPP y los detalles de cada entrega. La base de datos actual está definida en `schema.sql` y contiene las siguientes tablas principales:

### Tablas principales

- `usuarios`
  - Propósito: almacenar credenciales del usuario que accede al sistema.
  - Uso principal: autenticación en `views/login.php` y referencia de usuarios en entregas.

- `empleados`
  - Propósito: registro del personal que recibe los elementos de protección personal.
  - Uso principal: alta de empleados (`pages/ingreso.php`), búsqueda y listado de empleados (`pages/listado_empleados.php`, `pages/historial.php`).

- `entregas`
  - Propósito: cabecera de cada entrega de EPP realizada a un empleado.
  - Uso principal: registro de entregas (`pages/formatos.php`), consulta de historial y generación de informes (`includes/informe_pdf.php`).

- `entregas_detalle`
  - Propósito: guardar los elementos concretos entregados en cada entrega.
  - Uso principal: definición de ítems entregados y observaciones en `pages/formatos.php` y `pages/historial_detalle.php`.

- `elementos_permitidos`
  - Propósito: catálogo personalizado de elementos que el usuario puede seleccionar al registrar una entrega.
  - Uso principal: disponible en `pages/formatos.php` y `includes/elementos_handler.php`.

### Relaciones clave

- `usuarios.id` → `entregas.responsable_entrega`
  - Tipo: Uno a Muchos
  - Descripción: un usuario puede ser responsable de muchas entregas.

- `usuarios.id` → `entregas.usuario_id`
  - Tipo: Uno a Muchos
  - Descripción: un usuario registra muchas entregas. Esta relación existe en la tabla, pero el campo se usa sólo para guardar el autor y no se consulta posteriormente en la mayoría de las rutas de negocio.

- `empleados.id` → `entregas.empleado_id`
  - Tipo: Uno a Muchos
  - Descripción: un empleado puede recibir muchas entregas.

- `empleados.id` → `entregas.sst_id`
  - Tipo: Uno a Muchos
  - Descripción: un empleado puede ser identificado como representante SST para varias entregas.

- `entregas.id` → `entregas_detalle.entrega_id`
  - Tipo: Uno a Muchos
  - Descripción: una entrega contiene múltiples ítems entregados.

- `usuarios.id` → `elementos_permitidos.usuario_id`
  - Tipo: Uno a Muchos
  - Descripción: un usuario puede tener su propio catálogo de elementos permitidos.

## 2. Diccionario de datos

| Tabla | Columna | Tipo | Comentario / Propósito |
|------|---------|------|-------------------------|
| usuarios | id | INT AUTO_INCREMENT PK | Identificador único de usuario |
| usuarios | nombre | VARCHAR(100) | Nombre completo del usuario |
| usuarios | usuario | VARCHAR(50) UNIQUE | Login del usuario |
| usuarios | password | VARCHAR(255) | Contraseña hasheada con bcrypt |
| usuarios | fecha_creacion | TIMESTAMP | Fecha de creación del registro (automática) |

| empleados | id | INT AUTO_INCREMENT PK | Identificador único del empleado |
| empleados | nombre | VARCHAR(100) | Nombre completo del empleado |
| empleados | cedula | VARCHAR(20) UNIQUE | Documento único del empleado |
| empleados | cargo | VARCHAR(100) | Cargo del empleado |
| empleados | area | VARCHAR(100) | Área o departamento |
| empleados | fecha_registro | TIMESTAMP | Fecha de registro del empleado (automática) |

| entregas | id | INT AUTO_INCREMENT PK | Identificador único de entrega |
| entregas | empleado_id | INT | FK a `empleados.id` |
| entregas | fecha_entrega | DATE | Fecha en que se realizó la entrega |
| entregas | numero_dotacion | VARCHAR(50) | Número de dotación asociada |
| entregas | responsable_entrega | INT | FK a `usuarios.id` que realizó la entrega |
| entregas | sst_id | INT | FK a `empleados.id` como representante SST |
| entregas | firma_empleado | VARCHAR(255) | Nombre de archivo de la firma del empleado |
| entregas | pdf_file | VARCHAR(255) | Nombre de archivo PDF adjunto |
| entregas | usuario_id | INT NOT NULL | FK a `usuarios.id` que registra la entrega |
| entregas | fecha_registro | TIMESTAMP | Fecha de registro de la entrega (automática) |

| entregas_detalle | id | INT AUTO_INCREMENT PK | Identificador único de detalle |
| entregas_detalle | entrega_id | INT | FK a `entregas.id` |
| entregas_detalle | elemento | VARCHAR(100) | Nombre del elemento entregado |
| entregas_detalle | observacion | TEXT | Observaciones asociadas al elemento |

| elementos_permitidos | id | INT AUTO_INCREMENT PK | Identificador del elemento permitido |
| elementos_permitidos | usuario_id | INT | FK a `usuarios.id` para catálogo por usuario |
| elementos_permitidos | nombre_elemento | VARCHAR(100) | Nombre del elemento permitido |
| elementos_permitidos | fecha_creacion | TIMESTAMP | Fecha de creación del elemento (automática) |

## 3. Análisis de tipos de usuario: `usuario` vs `admin`

### Diferencias a nivel de base de datos

- El esquema mantiene solo la tabla `usuarios` sin distinción activa de roles.
- No existe ninguna tabla o columna en la aplicación que defina diferencias funcionales entre `admin` y `usuario`.
- La autentificación se basa únicamente en `usuarios.usuario` y `usuarios.password`.

### Diferencias en la lógica de negocio

- En `views/login.php`, tras validar credenciales se guarda `$_SESSION['usuario_id']` y `$_SESSION['nombre']`.
- No se encontraron verificaciones de rol en el resto del código del sistema.
- No hay ningún `if ($_SESSION['rol'] === 'admin')`, ni ninguna consulta SQL que filtre por `rol`, ni menús diferentes, ni autorizaciones condicionadas en `pages/`, `includes/` o `views/`.

### Conclusión crítica

- Actualmente no existe ninguna diferencia real de comportamiento entre `admin` y `usuario` en el código del proyecto.
- El sistema opera ahora sin distinción de rol; la sesión se autentica solo sobre el usuario.

### Recomendación

- Eliminar la columna `rol` de `usuarios` y tratar al usuario autenticado como único actor de la aplicación.
- Mantener la simplificación del modelo hasta que se requiera una autorización por perfil real.

> Cambios aplicados:
> 1. Se eliminó la columna `rol` de `usuarios` en `schema.sql`.
> 2. Se quitó la asignación de `$_SESSION['rol']` en `views/login.php`.
> 3. Se eliminó la tabla residual `elementos_personalizados` del esquema.

## 4. Detección de campos / entidades no utilizados

### Campos/tables evaluados con filtro estricto

- Se excluyen como NO obsoletos: `id` autoincrementales, `fecha_creacion`, `fecha_registro` y otras marcas de tiempo automáticas.
- También se excluyen campos que, aunque no se lean explícitamente, son generados por la base de datos y sirven para auditoría.

### Hallazgos relevantes

- `elementos_personalizados`:
  - Esta tabla existía en `schema.sql`, pero no se usaba en ningún archivo PHP del proyecto.
  - Se eliminó del esquema para limpiar el diseño de la base de datos.

- `usuarios.rol`:
  - Era un campo de rol presente en el diseño inicial, pero nunca se usó para autorización ni control de flujo.
  - Se eliminó de la tabla `usuarios` para simplificar el modelo y evitar ambigüedad en el control de acceso.

### Recomendaciones de eliminación segura

1. Eliminar la tabla `elementos_personalizados` si no se requiere su funcionalidad histórica.
   - Migración sugerida:
     - `DROP TABLE IF EXISTS elementos_personalizados;`
   - Evaluar antes si hay datos históricos que convenga conservar; de ser así, exportar el contenido previo a la eliminación.

2. Evaluar la utilidad de `usuarios.rol`:
   - Si se elige eliminarlo: ejecutar una migración `ALTER TABLE usuarios DROP COLUMN rol;` y retirar la asignación de `$_SESSION['rol']` en `views/login.php`.
   - Si se prefiere conservarlo como metadato, debe hacerse efectivo con verificaciones de acceso en la aplicación.

## 5. Observaciones adicionales

- La tabla `elementos_permitidos` sí está activa y forma parte de la lógica actual de `pages/formatos.php` y `includes/elementos_handler.php`.
- La aplicación tiene un flujo de autorización basado únicamente en la existencia de `$_SESSION['usuario_id']`.
- El sidebar y el menú no adaptan su presentación según rol.

---

**Nota:** este documento reemplaza por completo el contenido anterior de `DOCUMENTACION_RESUMIDA.md` y está basado exclusivamente en `schema.sql` y el código fuente PHP actual del proyecto.
