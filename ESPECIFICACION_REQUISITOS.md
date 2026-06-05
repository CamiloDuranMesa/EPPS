# Especificación de Requisitos

Este documento describe los requisitos funcionales y no funcionales que reflejan el software actualmente implementado en el proyecto.

## Requisitos Funcionales (RF)

| Código | Título | Descripción | Valor de negocio | Prioridad | Encargado |
|--------|--------|-------------|------------------:|----------:|-----------|
| RF-01 | Inicio de sesión | Acceso al sistema mediante usuario y contraseña válidos. | Habilita uso seguro de la aplicación. | Alta | Backend |
| RF-02 | Cierre de sesión | Cierra la sesión activa y redirige al login. | Protege la cuenta en dispositivos compartidos. | Alta | Frontend |
| RF-03 | Acceso autenticado | Restringir todas las páginas internas a usuarios autenticados. | Evita acceso no autorizado al panel. | Alta | Backend |
| RF-04 | Registro y listado de empleados | Crear empleados con nombre, cédula, cargo y área; listar empleados. | Permite llevar el catálogo de personal para entregas. | Alta | Backend |
| RF-05 | Importación desde Excel | Cargar empleados desde archivos `.xls` y `.xlsx` con validación de duplicados y campos obligatorios. | Reduce carga manual y acelera la puesta en marcha. | Media | Backend |
| RF-06 | Registro de entregas de EPP | Registrar entregas con selección de ítems, firma del empleado y PDF opcional. | Genera evidencia y controla la dotación de EPP. | Alta | Backend |
| RF-07 | Catálogo de elementos permitidos | Seleccionar ítems de un catálogo estándar y/o personalizado para entregas. | Estandariza las entregas y mantiene control operativo. | Media | Backend |
| RF-08 | Búsqueda y filtros | Buscar y filtrar empleados/entregas por nombre, cédula, cargo, área y fecha. | Mejora la eficiencia de consultas operativas. | Media | Frontend |
| RF-09 | Historial y detalle de entregas | Visualizar historial por empleado, ver detalle, editar ítems y eliminar entregas. | Soporta auditoría y corrección de registros. | Media | Backend/Frontend |
| RF-10 | Informes PDF | Generar informe PDF consolidado de entregas de un empleado. | Proporciona reportes formales y exportables. | Media | Backend |
| RF-11 | Visualización de PDFs | Ver archivos PDF adjuntos desde la entrega en el navegador. | Facilita la revisión de comprobantes digitales. | Baja | Frontend |
| RF-12 | Gráficas métricas | Mostrar métricas de entregas por área, empleado, elemento y tiempo. | Apoya la toma de decisiones y seguimiento de tendencias. | Baja | Frontend |

## Requisitos No Funcionales (RNF)

| Código | Título | Descripción | Justificación | Prioridad | Encargado |
|--------|--------|-------------|-------------:|----------:|-----------|
| RNF-01 | Paginación | Listas paginadas en empleados y historial de entregas. | Mantiene la interfaz rápida con datos crecientes. | Alta | Backend |
| RNF-02 | Validación de archivos | Validar formatos y tamaños: Excel `.xls/.xlsx`, PDF ≤5MB, imágenes de firma ≤2MB. | Evita errores de carga y protege almacenamiento. | Alta | Backend |
| RNF-03 | Operaciones bajo demanda | Importación y generación de PDF solo en acción del usuario. | Reduce uso de recursos continuos en entornos limitados. | Media | Backend |
| RNF-04 | Feedback de resultado | Mostrar mensajes de éxito/error al procesar formularios y cargas. | Mejora la experiencia y reduce incertidumbre del usuario. | Media | Frontend |
| RNF-05 | Seguridad de entrada | Uso de sesiones y consultas preparadas para el acceso y la base de datos. | Minimiza riesgo de inyección y accesos no autorizados. | Alta | Backend |
| RNF-06 | Consistencia transaccional | Uso de transacciones en guardado/eliminación de entregas. | Garantiza integridad entre cabeceras y detalles. | Alta | Backend |
| RNF-07 | Configuración por entorno | Conexión a base de datos mediante variables de entorno. | Facilita despliegues en diferentes entornos. | Alta | DevOps |
| RNF-08 | Uso de CDN | Cargar Bootstrap, jQuery y Chart.js desde CDN. | Reduce el peso del despliegue y acelera cargas. | Baja | DevOps/Frontend |
| RNF-09 | Rendimiento | Uso de índices y filtros en consultas críticas. | Minimiza latencia en planes gratuitos. | Alta | Backend |
| RNF-10 | Gestión de archivos | Verificar rutas y existencia de archivos en `uploads/` y `firmas/`. | Reduce fallos en visualización y protege datos adjuntos. | Media | Backend |

## Observaciones clave

- El sistema no distingue funciones por `rol`; la autorización se basa en usuarios autenticados y en el acceso a las páginas protegidas.
- La funcionalidad de personalización de elementos se sustenta en `elementos_permitidos`; el esquema contempla un catálogo estándar si la tabla no existe.
- No hay edición de empleados en el código actual, solo registro y listado.

## Migración propuesta (segura)

Script SQL recomendado para eliminar `rol` y `elementos_personalizados`.

```sql
-- 001_backup_and_drop_rol_elementos_personalizados.sql
CREATE TABLE IF NOT EXISTS elementos_personalizados_backup LIKE elementos_personalizados;
INSERT INTO elementos_personalizados_backup SELECT * FROM elementos_personalizados;
ALTER TABLE usuarios DROP COLUMN IF EXISTS rol;
DROP TABLE IF EXISTS elementos_personalizados;
```

Antes de ejecutar, haga un respaldo completo y pruebe la migración en staging.
