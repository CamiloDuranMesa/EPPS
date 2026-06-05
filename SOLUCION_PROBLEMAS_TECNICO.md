# Resolución de Problemas Técnicos - Análisis y Soluciones

**Fecha:** 10 de Enero de 2026  
**Ingeniero Senior:** Sistema de Entrega EPP  
**Estado:** ✅ Completado

---

## PROBLEMA 1: Falta de Funcionalidad para Editar/Eliminar Empleados

### Situación Inicial
- Página `ingreso.php` solo permitía **crear** empleados (y importarlos vía Excel)
- No había opción para **editar** datos de empleados existentes
- No había opción para **eliminar** empleados registrados
- Los usuarios debían depender de SQL directo o contactar a soporte técnico

### Solución Implementada

#### A. Interfaz de Usuario (Tabla de Empleados)
- Agregada tabla HTML que lista todos los empleados registrados con columnas: Nombre, Cédula, Cargo, Área
- Cada fila incluye botones de acción:
  - **Editar:** Abre modal con formulario pre-cargado
  - **Eliminar:** Con confirmación "¿Estás seguro?"

#### B. Modal de Edición
- Formulario modal (`editarEmpleadoModal`) con campos:
  - Nombre (obligatorio)
  - Cédula (obligatorio, validación de unicidad excepto el empleado actual)
  - Cargo (opcional)
  - Área (opcional)
- JavaScript `cargarEmpleadoParaEditar()` pre-carga datos del empleado seleccionado

#### C. Lógica de Backend PHP

**Edición:**
```php
POST action='editar':
- Valida campos obligatorios
- Verifica que no exista otra cédula duplicada (excepto el empleado actual)
- Ejecuta UPDATE en tabla empleados
- Retorna mensaje de éxito/error con tipo (success/danger)
```

**Eliminación:**
```php
POST action='eliminar':
- Verifica que empleado no tenga entregas registradas
- Si tiene entregas: rechaza eliminación (integridad referencial)
- Si no tiene: ejecuta DELETE
- Retorna mensaje de éxito/error con tipo (success/danger)
```

#### D. Validaciones
- No se puede eliminar empleado con entregas (protege integridad referencial)
- Cédula debe ser única (excepto en edición del mismo empleado)
- Campos obligatorios validados en cliente y servidor

---

## PROBLEMA 2: Almacenamiento Incompleto de Firmas

### Situación Inicial
**Causa Raíz Identificada:**
- Tabla `entregas` en `schema.sql` solo define columna `firma_empleado`
- **Falta:** `firma_responsable` y `firma_sst` (no existen en la base de datos)
- Código PHP en `formatos.php` intenta usar estas columnas con chequeos dinámicos (`columnaExiste()`)
- Resultado: Solo se guarda firma del empleado; firmas del responsable y SST se pierden

### Solución Implementada

#### A. Migración SQL (New File: `migrations/002_add_firma_responsable_sst.sql`)
```sql
ALTER TABLE entregas ADD COLUMN IF NOT EXISTS firma_responsable VARCHAR(255) 
    COMMENT 'Archivo de firma digital del responsable de entrega' AFTER firma_empleado;

ALTER TABLE entregas ADD COLUMN IF NOT EXISTS firma_sst VARCHAR(255) 
    COMMENT 'Archivo de firma digital del representante SST' AFTER firma_responsable;
```
- Segura: usa `IF NOT EXISTS` para idempotencia
- Ubicación: después de `firma_empleado` para mantener coherencia
- No destructiva: solo agrega nuevas columnas

#### B. Actualización de Interfaz (formatos.php - Líneas 540-590)

**Canvas de Firma del Responsable:**
- Ya existía en HTML (estaba funcional pero sin base de datos)
- Se mantiene como está (captura la firma correctamente)

**Canvas de Firma del SST (NEW):**
```html
<div class="col-12 col-md-4">
    <label class="form-label fw-bold">Representante SST</label>
    <select name="sst_id" id="sstSelect" class="form-select mb-3">
        <!-- dropdown de SST -->
    </select>
    <label class="form-label small">Firma SST</label>
    <canvas id="firmaSst" class="border border-2 d-block w-100 bg-light" 
            style="height: 180px; touch-action: none; cursor: crosshair;"></canvas>
    <input type="hidden" name="firma_sst" id="firma_sst">
    <button type="button" class="btn btn-sm btn-secondary mt-2 w-100" 
            onclick="limpiarCanvas('firmaSst')">
        Limpiar firma
    </button>
</div>
```
- Antes: mostraba solo "Sin firma configurada en esta plantilla"
- Ahora: canvas interactivo para capturar firma del representante SST

#### C. Actualización de Backend PHP (formatos.php - Líneas 225-240)

**Captura de datos POST:**
```php
$firma_sst = $_POST['firma_sst'] ?? '';
$soporta_firma_sst = columnaExiste($conn, 'entregas', 'firma_sst');
```

**Procesamiento:**
```php
$archivo_firma_sst = $soporta_firma_sst && !empty($firma_sst)
    ? guardarFirma($firma_sst)
    : null;
```

**Inserción en BD:**
```php
if ($soporta_firma_sst) {
    $parametros[] = &$archivo_firma_sst;
}
```

#### D. Actualización de JavaScript (formatos.php - Líneas 706-720, 750-752)

**Función `guardarFirmas()`:**
```javascript
function guardarFirmas() {
    ['firmaEmpleado', 'firmaResponsable', 'firmaSst'].forEach(canvasId => {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return; // Skip si canvas no existe
        
        let inputId;
        if (canvasId === 'firmaEmpleado') inputId = 'firma_empleado';
        else if (canvasId === 'firmaResponsable') inputId = 'firma_responsable';
        else if (canvasId === 'firmaSst') inputId = 'firma_sst';
        
        const dataURL = canvas.toDataURL('image/png');
        document.getElementById(inputId).value = dataURL;
    });
    return true;
}
```

**Inicialización DOMContentLoaded:**
```javascript
configurarCanvasFirma('firmaEmpleado');
configurarCanvasFirma('firmaResponsable');
configurarCanvasFirma('firmaSst');  // NUEVO
```

---

## PROBLEMA 3: Campo de Firma del Responsable SST No Funcional

### Situación Inicial
- Código PHP estaba listo para usar `firma_responsable` y `firma_sst` con chequeos dinámicos
- Interfaz HTML tenía canvas para `firma_responsable` pero SST mostraba mensaje de no configurado
- Base de datos no tenía las columnas necesarias

### Solución Implementada
**Integrado con Problema 2 - Ver arriba**

Adicional a lo anterior:
- Se agregó dropdown para seleccionar representante SST (campo `sst_id` existía, solo faltaba captura de firma)
- Canvas y botones Limpiar firma para SST
- JavaScript configura el canvas para dibujar la firma
- Sistema de guardado de firmas PNG en directorio `firmas/`

---

## Flujo de Funcionamiento Completo (Post-Solución)

### 1. Ingreso de Empleados
1. Usuario accede a `ingreso.php`
2. Opción A: Ingresa manualmente (nombre, cédula, cargo, área)
3. Opción B: Carga masiva desde Excel
4. Tabla muestra empleados existentes
5. Usuario puede editar (abre modal) o eliminar (con confirmación)
6. Validaciones: cédula única, no eliminar si tiene entregas

### 2. Registro de Entrega
1. Usuario accede a `formatos.php`
2. Selecciona empleado, fecha, elementos a entregar
3. Captura **tres firmas**:
   - Firma del empleado (obligatoria)
   - Firma del responsable de entrega (si aplica)
   - Firma del representante SST (si aplica)
4. Sistema convierte cada firma a PNG y guarda en `firmas/`
5. Guarda referencias en tabla `entregas`:
   - `firma_empleado`
   - `firma_responsable`
   - `firma_sst`

### 3. Consulta de Historial
- `historial_detalle.php` puede ver/editar/eliminar entregas
- Acceso a firmas almacenadas
- Opción de regenerar PDF con todas las firmas

---

## Cambios de Archivos Realizados

### Nuevos Archivos
- `migrations/002_add_firma_responsable_sst.sql` - Migración para agregar columnas de firma

### Archivos Modificados
1. **`migrations/001_drop_rol_elementos_personalizados.sql`**
   - Mejorados comentarios de seguridad
   - Cambios: `IF EXISTS` en DROP COLUMN

2. **`pages/ingreso.php`**
   - Lógica POST para acción 'editar' (UPDATE empleado)
   - Lógica POST para acción 'eliminar' (DELETE empleado con validación)
   - Tabla HTML de empleados con botones de acción
   - Modal de edición con formulario pre-cargado
   - Sistema de mensajes con tipo (success/danger/info)
   - JavaScript para cargar empleado en modal

3. **`pages/formatos.php`**
   - HTML: Canvas para captura de firma SST (líneas 570-585)
   - PHP: Captura de `$_POST['firma_sst']` (línea ~235)
   - PHP: Guardado de firma SST en archivos y BD (líneas ~235-240, 298-299)
   - JavaScript: `guardarFirmas()` incluye 'firmaSst' (líneas 707-720)
   - JavaScript: Inicialización de canvas SST en DOMContentLoaded (línea 752)

---

## Validaciones y Seguridad

### Edición/Eliminación de Empleados
- ✅ Validación de campos obligatorios (servidor)
- ✅ Verificación de cédula única (excepto empleado actual)
- ✅ Protección de integridad referencial (no eliminar si hay entregas)
- ✅ Confirmación de eliminación (cliente)
- ✅ Mensajes de error detallados

### Captura de Firmas
- ✅ Conversión de canvas a PNG (base64)
- ✅ Almacenamiento en directorio `firmas/`
- ✅ Chequeo dinámico de columnas (`columnaExiste()`) - compatible con DB antiguas
- ✅ Manejo de datos NULL para compatibilidad

---

## Testing Recomendado

### Antes de Ejecutar Migraciones
1. Backup completo de base de datos
2. Testing en ambiente staging

### Casos de Prueba
1. **Edición de empleado:**
   - ✓ Cambiar nombre, cédula, cargo, área
   - ✓ Intentar usar cédula duplicada
   - ✓ Limpiar campos y reintentar
   - ✓ Cancelar modal

2. **Eliminación de empleado:**
   - ✓ Eliminar empleado sin entregas
   - ✓ Intentar eliminar empleado con entregas
   - ✓ Confirmar/rechazar eliminación

3. **Captura de firmas:**
   - ✓ Firmar en los tres canvas
   - ✓ Limpiar y re-firmar
   - ✓ Guardar entrega
   - ✓ Verificar archivos PNG en `firmas/`

4. **Compatibilidad:**
   - ✓ Entregar sin firmas opcionales (solo empleado)
   - ✓ Entregar con todas las firmas
   - ✓ Verificar en historial

---

## Impacto en el Sistema

### Funcionalidades Nuevas
- ✅ Edición de empleados
- ✅ Eliminación de empleados (con protecciones)
- ✅ Captura de firma del representante SST
- ✅ Captura de firma del responsable de entrega (UI mejorada)

### Mejoras
- ✅ Integridad referencial mantenida
- ✅ Sistema más seguro contra datos inconsistentes
- ✅ Trazabilidad mejorada con tres firmas
- ✅ Base de datos consistent con el código

### Compatibilidad
- ✅ Totalmente backward compatible (columnas nuevas opcionales)
- ✅ Funciona con BD antigas sin columnas de firma
- ✅ Funciona con BD nuevas con columnas de firma

---

## Instrucciones para Producción

### Paso 1: Backup
```bash
mysqldump -h DB_HOST -u DB_USER -p DB_NAME > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Paso 2: Ejecutar Migraciones
```sql
-- Migración 1 (solo si no está ejecutada)
-- source migrations/001_drop_rol_elementos_personalizados.sql;

-- Migración 2 (NUEVA)
source migrations/002_add_firma_responsable_sst.sql;
```

### Paso 3: Verificar
```sql
-- Confirmar que las columnas existen
DESC entregas;
-- Debe mostrar: firma_empleado, firma_responsable, firma_sst

-- Confirmar que 'rol' no existe
DESC usuarios;
-- NO debe mostrar 'rol'
```

### Paso 4: Deploy de Código
- Desplegar cambios en `pages/ingreso.php`
- Desplegar cambios en `pages/formatos.php`

### Paso 5: Smoke Testing
- Crear empleado
- Editar empleado
- Intentar eliminar empleado
- Registrar entrega con firmas
- Verificar guardado de archivos

---

## Notas Técnicas

### Almacenamiento de Firmas
- Formato: PNG en directorio `firmas/`
- Nomenclatura: basada en timestamp y empleado
- Tamaño típico: 20-50 KB por firma
- Espacio requerido: ~100 KB por entrega (3 firmas)

### Base de Datos
- Columnas de firma: VARCHAR(255) para almacenar nombres de archivo
- No se almacena la imagen en la BD, solo la referencia
- Permite respaldo y gestión fácil de archivos

### Compatibilidad
- PHP 8+
- MySQL 5.7+
- Bootstrap 5 (CSS)
- Chart.js (no afectado)

---

## Resumen del Trabajo Realizado

**Ingeniero Senior Resolvió:**
1. ✅ Implementó CRUD completo para empleados (faltaba Editar/Eliminar)
2. ✅ Agregó captura de firma del responsable de SST (estaba parcial)
3. ✅ Agregó almacenamiento de firmas en base de datos (faltaban columnas)
4. ✅ Mejoró interfaz de usuario con tabla y modal
5. ✅ Implementó validaciones y protecciones de datos
6. ✅ Documentó todas las soluciones con detalles técnicos

**Resultado:** Sistema completamente funcional y listo para capturar y almacenar todas las firmas requeridas en entregas de EPP.
