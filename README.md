# 📋 Sistema de Gestión de EPP (Equipos de Protección Personal)

<div align="center">

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?logo=mysql)
![License](https://img.shields.io/badge/license-MIT-green.svg)

**Sistema web para gestión, control y seguimiento de la entrega de Elementos de Protección Personal en organizaciones**

[Características](#-características-principales) • [Instalación](#-instalación) • [Uso](#-uso-del-sistema) • [Arquitectura](#-arquitectura-del-proyecto) • [API](#-endpoints-y-funcionalidades) • [Contribuir](#-guía-para-desarrolladores)

</div>

---

## 📑 Tabla de Contenidos

1. [Descripción General](#-descripción-general)
2. [Características Principales](#-características-principales)
3. [Requisitos del Sistema](#-requisitos-del-sistema)
4. [Instalación](#-instalación)
5. [Configuración](#-configuración)
6. [Arquitectura del Proyecto](#-arquitectura-del-proyecto)
7. [Estructura de la Base de Datos](#-estructura-de-la-base-de-datos)
8. [Módulos del Sistema](#-módulos-del-sistema)
9. [Flujo de Trabajo](#-flujo-de-trabajo)
10. [API y Funcionalidades](#-endpoints-y-funcionalidades)
11. [Seguridad](#-seguridad)
12. [Guía para Desarrolladores](#-guía-para-desarrolladores)
13. [Solución de Problemas](#-solución-de-problemas)
14. [Contribución](#-contribución)
15. [Licencia](#-licencia)

---

## 📖 Descripción General

El **Sistema de Gestión de EPP** es una aplicación web desarrollada en PHP que permite a las organizaciones gestionar de manera eficiente la entrega, control y seguimiento de los Elementos de Protección Personal (EPP) para sus trabajadores.

### 🎯 Objetivo

Garantizar la seguridad de los trabajadores mediante un sistema centralizado que permite:
- Registrar y administrar empleados
- Controlar la entrega de EPP
- Generar reportes y documentación
- Visualizar estadísticas de entregas
- Mantener un historial completo de entregas

### 👥 Usuarios Target

- **Administradores de SST** (Seguridad y Salud en el Trabajo)
- **Responsables de almacén**
- **Personal de recursos humanos**
- **Supervisores de área**

---

## ✨ Características Principales

### 🔐 Autenticación y Seguridad
- Sistema de login con contraseñas hasheadas (bcrypt)
- Control de sesiones PHP
- Validación de permisos por rol

### 👤 Gestión de Empleados
- Registro individual de empleados
- Importación masiva desde archivos Excel (.xls, .xlsx)
- Campos: Nombre, Cédula, Cargo, Área
- Validación de duplicados por cédula
- Búsqueda y filtrado avanzado

### 📦 Registro de Entregas
- Formulario dinámico de entrega de EPP
- Más de 60 elementos predefinidos para su selección
- Elementos personalizados por usuario
- Captura de firmas digitales
- Adjuntar documentos PDF
- Observaciones por elemento
- Registro de responsable y representante SST

### 📊 Reportes y Visualización
- Generación de PDFs con historial completo
- Exportación a Excel
- Gráficas interactivas (Chart.js):
  - Por área
  - Por empleado
  - Por elemento
  - Por periodo de tiempo

### 🔍 Historial y Seguimiento
- Historial completo por empleado
- Filtros múltiples (fecha, área, cargo, mes, año)
- Paginación de resultados
- Vista detallada de entregas

---

## 💻 Requisitos del Sistema

### Software Necesario

| Componente | Versión Mínima | Versión Recomendada |
|------------|----------------|---------------------|
| **PHP** | 7.4+ | 8.0+ |
| **MySQL** | 5.7+ | 8.0+ |
| **MAMP** | 5.0.0+ | Última |
| **Composer** | 2.0+ | Última |

### Extensiones PHP Requeridas

```ini
extension=mysqli
extension=pdo_mysql
extension=gd
extension=zip
extension=mbstring
extension=xml
extension=dom
```

### Dependencias PHP (Composer)

```json
{
  "phpoffice/phpspreadsheet": "^5.0",  // Manejo de Excel
  "dompdf/dompdf": "^3.1",             // Generación de PDF
  "setasign/fpdi": "^2.6",             // Manipulación de PDF
  "setasign/fpdf": "^1.8"              // Creación de PDF
}
```

---

## 🚀 Instalación

### Paso 1: Clonar o Descargar el Proyecto

Directamente desde el hostinger.

> **Nota**: Si por el momento no se posee repositorio git del proyecto se recomienda usar esa versión.

### Paso 2: Instalar Dependencias

```bash
cd sistema-epp
composer install
```

> **Nota para principiantes**: Si no tienes Composer instalado, descárgalo desde [getcomposer.org](https://getcomposer.org/)

### Paso 3: Crear la Base de Datos

```sql
-- Conectarse a MySQL
mysql -u root -p

-- Crear la base de datos
CREATE DATABASE prueba_epp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Salir de MySQL
exit;
```

### Paso 4: Importar Estructura de Tablas

Ejecutar el siguiente script SQL (crear archivo `schema.sql` en la raíz):

```sql
USE prueba_epp;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'usuario') DEFAULT 'usuario',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de empleados
CREATE TABLE empleados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    cedula VARCHAR(20) UNIQUE NOT NULL,
    cargo VARCHAR(100),
    area VARCHAR(100),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de entregas (cabecera)
CREATE TABLE entregas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    fecha_entrega DATE NOT NULL,
    numero_dotacion VARCHAR(50),
    responsable_entrega INT,
    sst_id INT,
    firma_empleado VARCHAR(255),
    pdf_file VARCHAR(255),
    usuario_id INT NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
    FOREIGN KEY (responsable_entrega) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de detalle de entregas
CREATE TABLE entregas_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entrega_id INT NOT NULL,
    elemento VARCHAR(100) NOT NULL,
    observacion TEXT,
    FOREIGN KEY (entrega_id) REFERENCES entregas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de elementos personalizados
CREATE TABLE elementos_personalizados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre_elemento VARCHAR(100) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Crear índices para mejorar rendimiento
CREATE INDEX idx_empleado_cedula ON empleados(cedula);
CREATE INDEX idx_entrega_fecha ON entregas(fecha_entrega);
CREATE INDEX idx_entrega_empleado ON entregas(empleado_id);
CREATE INDEX idx_detalle_entrega ON entregas_detalle(entrega_id);
```

Importar el schema:

```bash
mysql -u root -p prueba_epp < schema.sql
```

### Paso 5: Crear Usuario Administrador

```sql
USE prueba_epp;

-- Crear usuario admin (contraseña: admin123)
INSERT INTO usuarios (nombre, usuario, password, rol) 
VALUES ('Administrador', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
```

> **Nota**: La contraseña por defecto es `admin123`. **CAMBIARLA INMEDIATAMENTE** en producción.

### Paso 6: Configurar Permisos de Directorios

```bash
# Linux/Mac
chmod -R 755 uploads/
chmod -R 755 firmas/
chmod -R 755 temp/

# Windows (no requiere cambios generalmente)
```

---

## ⚙️ Configuración

### Archivo: `config/database.php`

```php
<?php
$host = "localhost";
$dbname = "prueba_epp";
$user = "root";
$pass = "root";  // Cambiar según tu configuración

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error){
    die("Error de conexión: " . $conn->connect_error);
}

// Configurar charset
$conn->set_charset("utf8mb4");
?>
```

### Variables de Entorno (Recomendado para Producción)

Crear archivo `.env` en la raíz:

```env
DB_HOST=localhost
DB_NAME=prueba_epp
DB_USER=root
DB_PASS=tu_password_seguro
DB_CHARSET=utf8mb4

APP_ENV=production
APP_DEBUG=false
```

---

## 🏗️ Arquitectura del Proyecto

### Estructura de Directorios

```
public_html/
│
├── 📁 assets/              # Recursos estáticos
│   └── 📁 css/            # Hojas de estilo
│       ├── empleados.css
│       ├── formatos.css
│       ├── graficas.css
│       ├── historial.css
│       ├── index.css
│       ├── ingreso.css
│       ├── login.css
│       └── sidebar.css
│
├── 📁 config/             # Configuración
│   └── database.php       # Conexión a BD
│
├── 📁 firmas/             # Firmas digitales (generado)
│   └── firma_*.png
│
├── 📁 img/                # Imágenes
│   └── logo_test.png
│
├── 📁 includes/           # Componentes reutilizables
│   ├── elementos_handler.php    # AJAX handler para elementos
│   ├── footer.php               # Footer común
│   ├── header.php               # Header común
│   ├── informe_pdf.php          # Generador de informes PDF
│   ├── sidebar.php              # Menú lateral
│   └── ver_pdf.php              # Visualizador de PDFs
│
├── 📁 pages/              # Páginas principales
│   ├── formatos.php              # Registro de entregas
│   ├── graficas.php              # Visualización de gráficas
│   ├── historial_detalle.php    # Detalle de historial
│   ├── historial.php             # Historial por empleado
│   ├── ingreso.php               # Alta de empleados
│   └── listado_empleados.php    # Lista y búsqueda de empleados
│
├── 📁 temp/               # Archivos temporales (generado)
│
├── 📁 uploads/            # PDFs adjuntos (generado)
│   └── pdf_*.pdf
│
├── 📁 vendor/             # Dependencias de Composer
│
├── 📁 views/              # Vistas de autenticación
│   ├── login.php
│   └── logout.php
│
├── composer.json          # Dependencias PHP
├── dashboard.php          # Dashboard alternativo
├── index.php              # Punto de entrada principal
└── README.md              # Esta documentación
```

### Patrón de Arquitectura

El sistema sigue una arquitectura **híbrida** basada en:

1. **MVC Simplificado**
   - **Modelo**: Consultas SQL directas en cada archivo
   - **Vista**: Archivos PHP con HTML embebido
   - **Controlador**: Lógica en los mismos archivos PHP

2. **Componentes Modulares**
   - Header y Footer reutilizables
   - Sidebar común
   - Handlers independientes para AJAX

### Flujo de Aplicación

```
┌─────────────┐
│  Usuario    │
└──────┬──────┘
       │
       ▼
┌─────────────────┐
│  views/login.php│ ◄── Autenticación
└──────┬──────────┘
       │ (Sesión válida)
       ▼
┌─────────────────┐
│   index.php     │ ◄── Enrutador principal
└──────┬──────────┘
       │
       ├───► pages/ingreso.php (Registro empleados)
       │
       ├───► pages/formatos.php (Registro entregas)
       │
       ├───► pages/listado_empleados.php (Búsqueda)
       │
       ├───► pages/historial.php (Historial detallado)
       │
       └───► pages/graficas.php (Visualización)
```

---

## 🗄️ Estructura de la Base de Datos

### Diagrama de Relaciones

```
┌─────────────────┐         ┌──────────────────┐
│   usuarios      │         │   empleados      │
├─────────────────┤         ├──────────────────┤
│ id (PK)         │         │ id (PK)          │
│ nombre          │         │ nombre           │
│ usuario         │         │ cedula (UNIQUE)  │
│ password        │         │ cargo            │
│ rol             │         │ area             │
│                 │         │                  │
└────────┬────────┘         └────────┬─────────┘
         │                           │
         │                           │
         │     ┌────────────────────┐│
         │     │                    ││
         │     ▼                    ▼│
         │  ┌─────────────────────┐ │
         └──│    entregas         │◄┘
            ├─────────────────────┤
            │ id (PK)             │
            │ empleado_id (FK)    │
            │ fecha_entrega       │
            │ numero_dotacion     │
            │ responsable_entrega │
            │ sst_id              │
            │ firma_empleado      │
            │ pdf_file            │
            │ usuario_id (FK)     │
            └──────────┬──────────┘
                       │
                       │
                       ▼
            ┌─────────────────────┐
            │ entregas_detalle    │
            ├─────────────────────┤
            │ id (PK)             │
            │ entrega_id (FK)     │
            │ elemento            │
            │ observacion         │
            └─────────────────────┘

┌──────────────────────────────┐
│ elementos_personalizados     │
├──────────────────────────────┤
│ id (PK)                      │
│ usuario_id (FK) ─────────────┼──► usuarios
│ nombre_elemento              │
│ fecha_agregado               │
└──────────────────────────────┘
```

### Descripción de Tablas

#### `usuarios`
Almacena los usuarios del sistema con sus credenciales.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | Identificador único |
| nombre | VARCHAR(100) | Nombre completo |
| usuario | VARCHAR(50) | Usuario único para login |
| password | VARCHAR(255) | Contraseña hasheada (bcrypt) |
| rol | ENUM | Rol: 'admin' o 'usuario' |
| fecha_creacion | TIMESTAMP | Fecha de registro |

#### `empleados`
Registro de empleados de la organización.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | Identificador único |
| nombre | VARCHAR(100) | Nombre completo |
| cedula | VARCHAR(20) | Cédula única |
| cargo | VARCHAR(100) | Cargo del empleado |
| area | VARCHAR(100) | Área/Departamento |
| fecha_registro | TIMESTAMP | Fecha de registro |

#### `entregas`
Cabecera de cada entrega de EPP.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | Identificador único |
| empleado_id | INT | FK a empleados |
| fecha_entrega | DATE | Fecha de la entrega |
| numero_dotacion | VARCHAR(50) | Número de dotación |
| responsable_entrega | INT | FK a usuarios (quien entrega) |
| sst_id | INT | FK a empleados (representante SST) |
| firma_empleado | VARCHAR(255) | Archivo de firma digital del empleado |
| pdf_file | VARCHAR(255) | Archivo PDF adjunto (opcional) |
| usuario_id | INT | FK a usuarios (quien registra) |
| fecha_registro | TIMESTAMP | Fecha de registro |

#### `entregas_detalle`
Detalle de elementos entregados en cada entrega.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | Identificador único |
| entrega_id | INT | FK a entregas |
| elemento | VARCHAR(100) | Nombre del elemento EPP |
| observacion | TEXT | Observaciones del elemento |

#### `elementos_personalizados`
Elementos personalizados creados por usuarios.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | Identificador único |
| usuario_id | INT | FK a usuarios |
| nombre_elemento | VARCHAR(100) | Nombre del elemento personalizado |
| fecha_creacion | TIMESTAMP | Fecha de creación |

---

## 🧩 Módulos del Sistema

### 1. 🔐 Módulo de Autenticación

**Archivos**: `views/login.php`, `views/logout.php`

#### Funcionalidades:
- Login con usuario y contraseña
- Verificación con `password_verify()`
- Creación de sesión PHP
- Redirección según estado de autenticación

#### Código clave:

```php
// Login
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['usuario_id'] = $user['id'];
    $_SESSION['nombre'] = $user['nombre'];
    $_SESSION['rol'] = $user['rol'];
    header("Location: ../index.php");
}
```

#### Crear nuevo usuario (hash de contraseña):

```php
$password_hasheado = password_hash("contraseña", PASSWORD_BCRYPT);
```

---

### 2. 👥 Módulo de Gestión de Empleados

**Archivos**: `pages/ingreso.php`

#### Funcionalidades:

##### Registro Individual
- Formulario con validación
- Verificación de cédula duplicada
- Campos: nombre, cédula, cargo, área

##### Importación desde Excel
- Formatos soportados: `.xls`, `.xlsx`
- Validación de datos
- Detección de duplicados
- Reporte de registros: guardados, omitidos, duplicados, vacíos

#### Estructura del Excel:

| Columna A | Columna B | Columna C | Columna D |
|-----------|-----------|-----------|-----------|
| Nombre | Cédula | Cargo | Área |
| Juan Pérez | 123456789 | Operario | Producción |

#### Código clave de importación:

```php
use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = IOFactory::load($fileTmpPath);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray();

foreach ($rows as $index => $row) {
    if ($index == 0) continue; // Saltar encabezados
    
    $nombre = trim($row[0]);
    $cedula = trim($row[1]);
    $cargo = trim($row[2]);
    $area = trim($row[3]);
    
    // Validar y guardar...
}
```

---

### 3. 📦 Módulo de Registro de Entregas

**Archivos**: `pages/formatos.php`

#### Funcionalidades:

##### Formulario Dinámico
- Búsqueda de empleado por cédula (AJAX)
- Selección múltiple de elementos EPP (con Select2)
- Campos por elemento:
  - Elemento (select dinámico)
  - Observación (textarea)
- Botón para agregar más elementos
- Firma digital (canvas HTML5)
- Adjuntar PDF
- Datos adicionales:
  - Fecha de entrega
  - Número de dotación
  - Responsable de entrega
  - Representante SST

##### Elementos EPP Predefinidos (60+)
```
Casco, Careta tipo visor, Gafas lente claro, Barbuquejo, 
Tapa oído inserción, Botas industriales, Guantes de nitrilo, 
Camisa antifluido, Pantalón antifluido, Arnés, Careta para soldar,
Eslinga, Peto para soldar, Traje impermeable, etc.
```

##### Elementos Personalizados
Los usuarios pueden crear sus propios elementos que se guardan en `elementos_personalizados`.

#### Flujo de Trabajo:

```
1. Usuario busca empleado por cédula (AJAX)
   └─► includes/elementos_handler.php?action=buscarEmpleado

2. Llena el formulario de entrega
   ├─► Selecciona elementos
   ├─► Agrega observaciones
   ├─► Firma digitalmente
   └─► Adjunta PDF (opcional)

3. Al enviar (POST):
   ├─► Guarda firma (función guardarFirma())
   ├─► Guarda PDF (función guardarPDF())
   ├─► Inserta en tabla 'entregas'
   └─► Inserta detalles en 'entregas_detalle'
```

#### Código clave - Búsqueda AJAX:

```javascript
$("#cedula_buscar").on("input", function() {
    let ced = $(this).val().trim();
    if (ced.length >= 3) {
        $.ajax({
            url: "includes/elementos_handler.php",
            method: "GET",
            data: { action: "buscarEmpleado", cedula: ced },
            dataType: "json",
            success: function(data) {
                if (data.success) {
                    $("#empleado_id").val(data.empleado.id);
                    $("#nombre_empleado").val(data.empleado.nombre);
                    // Llenar más campos...
                }
            }
        });
    }
});
```

#### Código clave - Firma Digital:

```javascript
// Capturar firma
const canvas = document.getElementById("firma_canvas");
const ctx = canvas.getContext("2d");
let dibujando = false;

canvas.addEventListener("mousedown", () => { dibujando = true; });
canvas.addEventListener("mouseup", () => { dibujando = false; ctx.beginPath(); });

canvas.addEventListener("mousemove", (e) => {
    if (!dibujando) return;
    ctx.lineWidth = 2;
    ctx.lineCap = "round";
    ctx.strokeStyle = "#000";
    ctx.lineTo(e.offsetX, e.offsetY);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(e.offsetX, e.offsetY);
});

// Al enviar, convertir a base64
const firmaBase64 = canvas.toDataURL("image/png");
$("#firma_empleado").val(firmaBase64);
```

---

### 4. 🔍 Módulo de Historial

**Archivos**: `pages/listado_empleados.php`, `pages/historial.php`, `pages/historial_detalle.php`

#### Funcionalidades:

##### Listado de Empleados
- Búsqueda por nombre o cédula
- Filtros:
  - Cargo
  - Área
  - Fecha específica
  - Mes
  - Año
- Paginación (10 registros por página)
- Vista de resumen de entregas

##### Historial Detallado
- Ver todas las entregas de un empleado
- Información mostrada:
  - Elementos entregados
  - Observaciones
  - Fechas
  - Responsables
  - Firmas
- Generación de PDF completo del historial

#### Consulta SQL con Filtros:

```php
$where = [];
$params = [];
$types = '';

if ($filtroTexto !== '') {
    $where[] = '(empleados.nombre LIKE ? OR empleados.cedula LIKE ?)';
    $params[] = "%$filtroTexto%";
    $params[] = "%$filtroTexto%";
    $types .= 'ss';
}

if ($filtroCargo !== '') {
    $where[] = 'empleados.cargo = ?';
    $params[] = $filtroCargo;
    $types .= 's';
}

if ($filtroMes > 0 && $filtroAnio > 0) {
    $where[] = 'MONTH(entregas.fecha_entrega) = ? AND YEAR(entregas.fecha_entrega) = ?';
    $params[] = $filtroMes;
    $params[] = $filtroAnio;
    $types .= 'ii';
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT DISTINCT empleados.* 
        FROM empleados 
        LEFT JOIN entregas ON entregas.empleado_id = empleados.id 
        $whereSQL 
        LIMIT $por_pagina OFFSET $offset";
```

---

### 5. 📊 Módulo de Gráficas

**Archivos**: `pages/graficas.php`

#### Funcionalidades:

Visualización de estadísticas mediante gráficas interactivas (Chart.js):

##### Tipos de Gráficas:

1. **Por Área**
   - Total de elementos entregados por área
   - Identifica áreas con mayor uso de EPP

2. **Por Empleado**
   - Top 10 empleados con más entregas
   - Útil para detectar patrones

3. **Por Elemento**
   - Top 10 elementos más entregados
   - Ayuda en planificación de inventario

4. **Por Tiempo**
   - Entregas mensuales (últimos 12 meses)
   - Análisis de tendencias

#### Implementación con Chart.js:

```javascript
const ctx = document.getElementById('myChart').getContext('2d');
const myChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= $labelsJSON ?>,  // Desde PHP
        datasets: [{
            label: 'Total de Entregas',
            data: <?= $datosJSON ?>,   // Desde PHP
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});
```

---

### 6. 📄 Módulo de Generación de PDFs

**Archivos**: `includes/informe_pdf.php`, `includes/ver_pdf.php`

#### Funcionalidades:

##### Informe Completo de Empleado
- Datos del empleado
- Tabla con todas las entregas
- Elementos entregados
- Firmas digitales embebidas
- Logo de la empresa

#### Implementación con DomPDF:

```php
use Dompdf\Dompdf;

// Convertir logo a base64
$logoFile = __DIR__ . '/../img/logo_test.png';
$logoData = base64_encode(file_get_contents($logoFile));
$logoSrc = 'data:image/png;base64,' . $logoData;

// Construir HTML
$html = '
<style>
    body { font-family: Arial, sans-serif; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #000; padding: 6px; }
</style>
<img src="' . $logoSrc . '" style="width: 100px;">
<h2>Informe de Entregas</h2>
<table>
    <tr><th>Empleado</th><td>' . $nombre . '</td></tr>
    <!-- Más filas... -->
</table>
';

// Generar PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("informe_$cedula.pdf", ["Attachment" => false]);
```

---

## 🔄 Flujo de Trabajo

### Flujo Completo de Uso del Sistema

```
┌────────────────────────────────────────────────────────────┐
│                   1. INICIO DE SESIÓN                      │
│   Usuario ingresa con credenciales (views/login.php)      │
└────────────────────┬───────────────────────────────────────┘
                     │
                     ▼
┌────────────────────────────────────────────────────────────┐
│              2. DASHBOARD / PÁGINA PRINCIPAL               │
│        (index.php - Selección de módulo)                   │
└──┬────────────┬────────────┬────────────┬──────────────────┘
   │            │            │            │
   ▼            ▼            ▼            ▼
┌───────┐  ┌─────────┐  ┌──────────┐  ┌──────────┐
│INGRESO│  │FORMATOS │  │HISTORIAL │  │ GRÁFICAS │
└───┬───┘  └────┬────┘  └─────┬────┘  └──────┬───┘
    │           │             │              │
    │           │             │              │
    ▼           ▼             ▼              ▼
┌────────┐  ┌────────┐   ┌────────┐    ┌────────┐
│ Agregar│  │Registrar  │ Ver     │    │Ver     │
│empleado│  │entrega de│ historial│    │estadís │
│indivi- │  │EPP con   │ de       │    │ticas   │
│dual o  │  │firma y   │ entregas │    │visual. │
│masivo  │  │PDF       │ por      │    │        │
│        │  │          │ empleado │    │        │
└────────┘  └─────┬────┘  └────┬───┘   └────────┘
                  │            │
                  │            ▼
                  │      ┌──────────┐
                  │      │Generar   │
                  │      │PDF       │
                  │      │completo  │
                  │      └──────────┘
                  │
                  ▼
            ┌──────────┐
            │ Almacena │
            │ en BD    │
            │+ archivos│
            └──────────┘
```

### Flujo de Registro de Entrega (Detallado)

```
USUARIO                    FRONTEND                  BACKEND                    BD
   │                          │                        │                         │
   │  1. Ingresa cédula       │                        │                         │
   ├─────────────────────────>│                        │                         │
   │                          │  2. AJAX buscar        │                         │
   │                          ├───────────────────────>│                         │
   │                          │                        │  3. SELECT empleado     │
   │                          │                        ├────────────────────────>│
   │                          │                        │<────────────────────────┤
   │                          │<───────────────────────┤  4. Datos empleado     │
   │  5. Muestra datos        │                        │                         │
   │<─────────────────────────┤                        │                         │
   │                          │                        │                         │
   │  6. Llena formulario     │                        │                         │
   │     - Elementos EPP      │                        │                         │
   │     - Observaciones      │                        │                         │
   │     - Firma en canvas    │                        │                         │
   │     - Adjunta PDF        │                        │                         │
   ├─────────────────────────>│                        │                         │
   │                          │                        │                         │
   │  7. Click "Guardar"      │                        │                         │
   ├─────────────────────────>│                        │                         │
   │                          │  8. POST con form-data │                         │
   │                          ├───────────────────────>│                         │
   │                          │                        │  9. Validar datos       │
   │                          │                        │  10. Guardar firma.png  │
   │                          │                        │  11. Guardar pdf        │
   │                          │                        │  12. INSERT entregas    │
   │                          │                        ├────────────────────────>│
   │                          │                        │  13. INSERT detalles    │
   │                          │                        ├────────────────────────>│
   │                          │<───────────────────────┤  14. Confirmación       │
   │  15. Mensaje éxito       │                        │                         │
   │<─────────────────────────┤                        │                         │
```

---

## 🔌 Endpoints y Funcionalidades

### AJAX Endpoints

#### `includes/elementos_handler.php`

##### 1. Buscar Empleado

```
GET /includes/elementos_handler.php?action=buscarEmpleado&cedula=123456

Response:
{
    "success": true,
    "empleado": {
        "id": 5,
        "nombre": "Juan Pérez",
        "cedula": "123456789",
        "cargo": "Operario",
        "area": "Producción"
    }
}
```

##### 2. Obtener Elementos Personalizados

```
GET /includes/elementos_handler.php?action=getElementos

Response:
{
    "success": true,
    "elementos": [
        "Elemento Personalizado 1",
        "Elemento Personalizado 2"
    ]
}
```

##### 3. Agregar Elemento Personalizado

```
POST /includes/elementos_handler.php
action=addElemento&nombre=Nuevo+Elemento

Response:
{
    "success": true,
    "message": "Elemento agregado correctamente"
}
```

---

## 🔒 Seguridad

### Medidas Implementadas

#### 1. Autenticación
```php
// Contraseñas hasheadas con bcrypt
$password_hash = password_hash($password, PASSWORD_BCRYPT);

// Verificación segura
if (password_verify($password_input, $password_hash)) {
    // Acceso concedido
}
```

#### 2. Control de Sesiones
```php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: views/login.php");
    exit();
}
```

#### 3. Prevención de SQL Injection
```php
// Prepared Statements
$stmt = $conn->prepare("SELECT * FROM empleados WHERE cedula = ?");
$stmt->bind_param("s", $cedula);
$stmt->execute();
```

#### 4. Validación de Archivos
```php
// Validar tipo de archivo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);

if ($mime !== 'application/pdf') {
    throw new Exception("Archivo inválido");
}

// Limitar tamaño (5 MB)
if ($file['size'] > 5 * 1024 * 1024) {
    throw new Exception("Archivo muy grande");
}
```

#### 5. Sanitización de Salidas
```php
// Escapar HTML
echo htmlspecialchars($variable, ENT_QUOTES, 'UTF-8');
```

### Recomendaciones de Seguridad

#### Para Producción:

1. **Cambiar credenciales por defecto**
   ```sql
   UPDATE usuarios SET password = '$2y$10$...' WHERE usuario = 'admin';
   ```

2. **Usar HTTPS**
   - Obtener certificado SSL/TLS
   - Configurar Apache/Nginx para HTTPS

3. **Proteger directorios sensibles**
   ```apache
   # .htaccess en /config/
   Deny from all
   ```

4. **Configurar permisos**
   ```bash
   chmod 600 config/database.php
   chmod 755 uploads/
   chmod 755 firmas/
   ```

5. **Implementar límite de intentos de login**

6. **Agregar logs de auditoría**

7. **Usar variables de entorno para credenciales**

---

## 👨‍💻 Guía para Desarrolladores

### Ambiente de Desarrollo

#### Herramientas Recomendadas

- **IDE**: Visual Studio Code, PHPStorm
- **Servidor Local**: XAMPP, WAMP, Laragon
- **Control de Versiones**: Git
- **Cliente MySQL**: phpMyAdmin, MySQL Workbench
- **Postman**: Para pruebas de AJAX

### Configuración del Entorno

```bash
# 1. Instalar XAMPP (Windows)
# Descargar desde https://www.mamp.info/en/downloads/

# 2. Clonar proyecto en htdocs
cd C:\MAMP\htdocs
git clone [URL_REPO] sistema-epp

# 3. Instalar dependencias
cd sistema-epp
composer install

# 4. Configurar base de datos
#Inicia los servidores desde el panel de control de MAMP.

#Abre phpMyAdmin (haz clic en "Open WebStart page" en MAMP y busca el enlace de phpMyAdmin o ve a http://localhost:8888/phpmyadmin).

#Crea una nueva base de datos llamada: prueba_epp

#Importa el archivo schema.sql.

# 5. Configurar database.php
# Editar config/database.php con credenciales

# 6. Acceder al sistema
# http://localhost/sistema-epp
```

### Convenciones de Código

#### PHP

```php
// Nombres de variables: camelCase
$nombreEmpleado = "Juan";
$fechaEntrega = date('Y-m-d');

// Nombres de funciones: camelCase
function guardarFirma($base64) {
    // ...
}

// Constantes: UPPER_CASE
define('MAX_FILE_SIZE', 5242880);

// Clases (si se usan): PascalCase
class EmpleadoManager {
    // ...
}
```

#### SQL

```sql
-- Tablas: minúsculas con guión bajo
CREATE TABLE entregas_detalle (
    -- Columnas: minúsculas con guión bajo
    empleado_id INT
);

-- Nombres descriptivos
SELECT 
    e.nombre AS nombre_empleado,
    COUNT(*) AS total_entregas
FROM empleados e;
```

#### JavaScript

```javascript
// Variables: camelCase
let empleadoId = 123;
const maxElementos = 10;

// Funciones: camelCase
function buscarEmpleado(cedula) {
    // ...
}

// Constantes globales: UPPER_CASE
const API_URL = 'includes/elementos_handler.php';
```

### Agregar Nuevas Funcionalidades

#### Ejemplo: Agregar un nuevo módulo "Reportes"

1. **Crear la página**
   ```php
   // pages/reportes.php
   <?php
   if (session_status() === PHP_SESSION_NONE) {
       session_start();
   }
   require_once __DIR__ . "/../config/database.php";
   
   if (!isset($_SESSION['usuario_id'])) {
       header("Location: ../views/login.php");
       exit();
   }
   
   // Tu código aquí...
   ?>
   ```

2. **Agregar enlace en sidebar**
   ```php
   // includes/sidebar.php
   <li class="nav-item mb-2">
       <a href="index.php?page=reportes" class="nav-link">
           📈 Reportes
       </a>
   </li>
   ```

3. **Agregar ruta en index.php**
   ```php
   // index.php
   $validPages = ['ingreso', 'historial', 'formatos', 'graficas', 'reportes'];
   
   // ...
   
   elseif ($page === 'reportes') {
       include __DIR__ . '/pages/reportes.php';
   }
   ```

4. **Crear estilos (opcional)**
   ```css
   /* assets/css/reportes.css */
   .reporte-container {
       padding: 20px;
   }
   ```

### Debugging

#### Habilitar Errores PHP

```php
// Agregar al inicio de archivos para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

#### Logs Personalizados

```php
// Escribir en log
error_log("Debug: Empleado ID = " . $empleado_id);

// Ver logs en:
// XAMPP: C:\xampp\apache\logs\error.log
// Linux: /var/log/apache2/error.log
```

#### Debugging de AJAX

```javascript
$.ajax({
    url: "includes/elementos_handler.php",
    method: "GET",
    data: { action: "buscarEmpleado", cedula: cedula },
    success: function(response) {
        console.log("Response:", response); // Ver en consola del navegador
    },
    error: function(xhr, status, error) {
        console.error("Error:", error);
        console.log("Response Text:", xhr.responseText);
    }
});
```

### Testing

#### Pruebas Manuales Recomendadas

1. **Login**
   - [ ] Login exitoso con credenciales correctas
   - [ ] Login fallido con credenciales incorrectas
   - [ ] Redirección a login si no hay sesión

2. **Empleados**
   - [ ] Registrar empleado individual
   - [ ] Detectar cédula duplicada
   - [ ] Importar Excel válido
   - [ ] Rechazar Excel con datos inválidos
   - [ ] Búsqueda por nombre
   - [ ] Búsqueda por cédula
   - [ ] Filtros por cargo y área

3. **Entregas**
   - [ ] Buscar empleado por cédula (AJAX)
   - [ ] Agregar elementos dinámicamente
   - [ ] Capturar firma digital
   - [ ] Adjuntar PDF
   - [ ] Guardar entrega completa
   - [ ] Validar campos requeridos

4. **Historial**
   - [ ] Ver listado de empleados
   - [ ] Aplicar filtros
   - [ ] Ver historial detallado
   - [ ] Generar PDF de historial

5. **Gráficas**
   - [ ] Mostrar gráfica por área
   - [ ] Mostrar gráfica por empleado
   - [ ] Mostrar gráfica por elemento
   - [ ] Mostrar gráfica por tiempo
   - [ ] Usar filtros

### Base de Datos - Consultas Útiles

```sql
-- Ver todas las entregas con empleados
SELECT 
    e.fecha_entrega,
    emp.nombre,
    emp.cedula,
    ed.elemento,
    ed.observacion
FROM entregas e
INNER JOIN empleados emp ON e.empleado_id = emp.id
LEFT JOIN entregas_detalle ed ON e.id = ed.entrega_id
ORDER BY e.fecha_entrega DESC;

-- Contar entregas por área
SELECT 
    emp.area,
    COUNT(DISTINCT e.id) AS total_entregas,
    COUNT(ed.id) AS total_elementos
FROM empleados emp
LEFT JOIN entregas e ON emp.id = e.empleado_id
LEFT JOIN entregas_detalle ed ON e.id = ed.entrega_id
GROUP BY emp.area;

-- Empleados sin entregas
SELECT * FROM empleados
WHERE id NOT IN (SELECT DISTINCT empleado_id FROM entregas);

-- Top 10 elementos más entregados
SELECT 
    elemento,
    COUNT(*) AS veces_entregado
FROM entregas_detalle
GROUP BY elemento
ORDER BY veces_entregado DESC
LIMIT 10;
```

### Optimización de Rendimiento

#### Consultas SQL

```php
// MAL: N+1 queries
foreach ($empleados as $emp) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM entregas WHERE empleado_id = ?");
    $stmt->bind_param("i", $emp['id']);
    $stmt->execute();
    // ...
}

// BIEN: Una sola query con JOIN
$query = "
    SELECT 
        e.id,
        e.nombre,
        COUNT(ent.id) AS total_entregas
    FROM empleados e
    LEFT JOIN entregas ent ON e.id = ent.empleado_id
    GROUP BY e.id
";
```

#### Caché de Elementos

```php
// Cachear elementos en sesión
if (!isset($_SESSION['elementos_estandar'])) {
    $_SESSION['elementos_estandar'] = obtenerElementosEstandar();
}
$elementos = $_SESSION['elementos_estandar'];
```

#### Índices de Base de Datos

```sql
-- Ya implementados en schema.sql
CREATE INDEX idx_empleado_cedula ON empleados(cedula);
CREATE INDEX idx_entrega_fecha ON entregas(fecha_entrega);
CREATE INDEX idx_entrega_empleado ON entregas(empleado_id);
```

---

## ❗ Solución de Problemas

### Problemas Comunes

#### 1. Error de Conexión a Base de Datos

**Error**: `Error de conexión: Access denied for user 'root'@'localhost'`

**Solución**:
```php
// Verificar credenciales en config/database.php
$host = "localhost";
$dbname = "prueba_epp";
$user = "root";
$pass = "tu_password";  // ← Verificar este valor
```

#### 2. Páginas en Blanco

**Error**: La página carga en blanco sin mensajes

**Solución**:
```php
// Agregar al inicio del archivo para ver errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

#### 3. No se Cargan Estilos CSS

**Error**: La página se ve sin estilos

**Solución**:
```html
<!-- Verificar rutas en archivos PHP -->
<!-- Si estás en pages/*, usa ../ -->
<link rel="stylesheet" href="../assets/css/estilos.css">

<!-- Si estás en index.php, usa ruta directa -->
<link rel="stylesheet" href="assets/css/estilos.css">
```

#### 4. Error al Subir Archivos

**Error**: `File upload error` o archivos no se guardan

**Solución**:
```php
// Verificar php.ini
upload_max_filesize = 10M
post_max_size = 10M
```

```bash
# Verificar permisos de directorios
chmod 755 uploads/
chmod 755 firmas/
```

#### 5. AJAX No Funciona

**Error**: `404 Not Found` en llamadas AJAX

**Solución**:
```javascript
// Verificar ruta según ubicación del archivo
// Si estás en index.php:
$.ajax({ url: "includes/elementos_handler.php", ... });

// Si estás en pages/*:
$.ajax({ url: "../includes/elementos_handler.php", ... });
```

#### 6. Sesión se Pierde

**Error**: El usuario es redirigido al login constantemente

**Solución**:
```php
// Verificar que session_start() se llame antes de cualquier salida
<?php
session_start();  // ← Debe estar al principio
?>
<!DOCTYPE html>
...
```

#### 7. Firmas No se Guardan

**Error**: Firmas digitales no aparecen en PDFs

**Solución**:
```javascript
// Verificar que el canvas tenga contenido antes de enviar
const canvas = document.getElementById("firma_canvas");
const firma = canvas.toDataURL("image/png");

if (firma === "data:image/png;base64,iVBORw0KGgo...") {
    alert("Por favor, firme antes de enviar");
    return false;
}
```

#### 8. Error en Importación de Excel

**Error**: `Error al leer el archivo Excel`

**Solución**:
```bash
# Verificar que las dependencias estén instaladas
composer install

# Verificar que el archivo sea .xls o .xlsx válido
# Verificar que tenga la estructura correcta:
# Columna A: Nombre | B: Cédula | C: Cargo | D: Área
```

### Logs y Debugging

#### Ver Errores de PHP

```bash
# MAMP (macOS)
/Applications/MAMP/logs/php_error.log
/Applications/MAMP/logs/apache_error.log

# MAMP (Windows)
C:\MAMP\logs\php_error.log
C:\MAMP\logs\apache_error.log

# XAMPP (Windows - alternativo)
C:\xampp\apache\logs\error.log

# Linux
/var/log/apache2/error.log
```

#### Ver Consultas SQL

```php
// Agregar antes de ejecutar una consulta
echo "Query: " . $sql . "<br>";
echo "Params: " . print_r($params, true) . "<br>";
```

---

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

```
MIT License

Copyright (c) 2026 Camilo Durán Mesa

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

[...]
```

---

### Tutoriales Útiles

- [PHP MySQL Tutorial](https://www.w3schools.com/php/php_mysql_intro.asp)
- [AJAX con jQuery](https://api.jquery.com/jquery.ajax/)
- [Canvas API](https://developer.mozilla.org/es/docs/Web/API/Canvas_API)
- [Prepared Statements](https://www.php.net/manual/es/mysqli.quickstart.prepared-statements.php)

---
