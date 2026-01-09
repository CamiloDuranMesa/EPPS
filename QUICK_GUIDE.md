# 🚀 Guía Rápida - Sistema de Gestión de EPP

## ⚡ Inicio Rápido (5 minutos)

### Para Usuarios Nuevos

1. **Acceder al Sistema**
   - URL: `http://localhost/sistema-epp` (o tu dominio)
   - Usuario: `admin`
   - Contraseña: `admin123`

2. **Cambiar Contraseña**
   - Ve a tu perfil
   - Cambia la contraseña por defecto

3. **Registrar Primer Empleado**
   - Click en "Ingreso de empleados"
   - Llena el formulario
   - Click en "Guardar"

4. **Registrar Primera Entrega**
   - Click en "Formato de entrega"
   - Busca empleado por cédula
   - Selecciona elementos EPP
   - Firma digitalmente
   - Click en "Guardar"

### Para Desarrolladores

```bash
# 1. Clonar proyecto
git clone [URL] sistema-epp
cd sistema-epp

# 2. Instalar dependencias
composer install

# 3. Crear base de datos
mysql -u root -p -e "CREATE DATABASE prueba_epp"
mysql -u root -p prueba_epp < schema.sql

# 4. Configurar
# Editar config/database.php con tus credenciales

# 5. Iniciar servidor MAMP
# Abrir MAMP y hacer clic en "Start Servers"
# O usar línea de comandos:
php -S localhost:8000

# 6. Acceder
# Con MAMP: http://localhost:8888/sistema-epp
# O con PHP built-in: http://localhost:8000
```

---

## 📊 Diagramas de Flujo

### Flujo de Registro de Entrega

```
INICIO
  │
  ▼
┌─────────────────────┐
│ Usuario busca       │
│ empleado por cédula │
└──────────┬──────────┘
           │
           ▼
     ┌─────────┐
     │¿Existe? │
     └────┬────┘
          │
    ┌─────┴─────┐
    │           │
   SÍ          NO
    │           │
    ▼           ▼
┌────────┐  ┌─────────┐
│Mostrar │  │Mensaje: │
│datos   │  │"No      │
│empleado│  │encontr."│
└───┬────┘  └─────────┘
    │
    ▼
┌─────────────────────┐
│ Llena formulario:   │
│ - Elementos EPP     │
│ - Observaciones     │
│ - Firma digital     │
│ - Adjunta PDF       │
└──────────┬──────────┘
           │
           ▼
     ┌──────────┐
     │Validar   │
     │datos     │
     └────┬─────┘
          │
    ┌─────┴─────┐
    │           │
 VÁLIDO    INVÁLIDO
    │           │
    ▼           ▼
┌────────┐  ┌──────────┐
│Guardar │  │Mostrar   │
│en BD   │  │errores   │
└───┬────┘  └──────────┘
    │
    ▼
┌─────────────┐
│Guardar firma│
│y PDF en     │
│servidor     │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│Mensaje de   │
│éxito        │
└─────────────┘
       │
       ▼
     FIN
```

### Flujo de Búsqueda con Filtros

```
INICIO
  │
  ▼
┌──────────────────┐
│Usuario aplica    │
│filtros:          │
│- Texto búsqueda  │
│- Cargo           │
│- Área            │
│- Fecha/Mes/Año   │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│Construir query   │
│SQL dinámica      │
│con WHERE clauses │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│Ejecutar consulta │
│con LIMIT/OFFSET  │
│para paginación   │
└────────┬─────────┘
         │
         ▼
   ┌─────────┐
   │¿Hay     │
   │resultad?│
   └────┬────┘
        │
   ┌────┴────┐
   │         │
  SÍ        NO
   │         │
   ▼         ▼
┌──────┐ ┌────────┐
│Mostrar│ │Mensaje │
│lista  │ │"Sin    │
│con    │ │result."│
│paginac│ └────────┘
└───┬───┘
    │
    ▼
┌──────────┐
│Botones de│
│paginación│
└──────────┘
    │
    ▼
  FIN
```

---

## 🎨 Estructura de Componentes

### Componentes Reutilizables

```
┌─────────────────────────────────────────┐
│           includes/header.php           │
│  - Meta tags                            │
│  - Bootstrap CSS                        │
│  - Custom CSS                           │
│  - jQuery, Chart.js                     │
└─────────────────────────────────────────┘
                    │
    ┌───────────────┼───────────────┐
    │               │               │
    ▼               ▼               ▼
┌─────────┐  ┌──────────┐  ┌──────────────┐
│Sidebar  │  │Contenido │  │Footer        │
│includes/│  │Principal │  │includes/     │
│sidebar  │  │(páginas) │  │footer.php    │
│.php     │  │          │  │              │
└─────────┘  └──────────┘  └──────────────┘
    │             │              │
    │             │              │
    ▼             ▼              ▼
┌─────────────────────────────────────────┐
│       Elementos Comunes:                │
│  - Mensajes de alerta                   │
│  - Modales                              │
│  - Tooltips                             │
└─────────────────────────────────────────┘
```

### Flujo de Inclusión de Archivos

```php
index.php (Router)
    │
    ├─► includes/header.php
    │       └─► assets/css/*.css
    │
    ├─► includes/sidebar.php
    │
    ├─► pages/[pagina_solicitada].php
    │       ├─► config/database.php
    │       └─► includes/elementos_handler.php (AJAX)
    │
    └─► includes/footer.php
            └─► Bootstrap JS, jQuery
```

---

## 🔧 Configuración Avanzada

> **Nota para usuarios de MAMP**: La configuración predeterminada de MAMP funciona bien para desarrollo. Para producción, considera usar un servidor dedicado con Apache o Nginx.

### Variables de Entorno (.env)

```env
# Base de Datos
DB_HOST=localhost
DB_PORT=3306
DB_NAME=prueba_epp
DB_USER=root
DB_PASS=tu_password
DB_CHARSET=utf8mb4

# Aplicación
APP_NAME="Sistema de Gestión de EPP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

# Sesión
SESSION_LIFETIME=7200
SESSION_SECURE=true
SESSION_HTTPONLY=true

# Archivos
MAX_FILE_SIZE=5242880
ALLOWED_FILE_TYPES=pdf
MAX_IMAGE_SIZE=2097152

# Email (futuro)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=tu-password
MAIL_FROM=noreply@tu-dominio.com
```

### Configuración de Apache (.htaccess)

> **Nota**: Si usas MAMP, el archivo .htaccess debe estar en la raíz del proyecto.

```apache
# Redirigir a HTTPS (producción)
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Prevenir acceso a archivos sensibles
<FilesMatch "(\.env|\.git|composer\.json|composer\.lock|schema\.sql)$">
    Require all denied
</FilesMatch>

# Prevenir listado de directorios
Options -Indexes

# Proteger carpeta config
<Directory "/ruta/a/config">
    Require all denied
</Directory>

# Headers de seguridad
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"

# Compresión GZIP
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>

# Cache de archivos estáticos
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

### PHP ini Recomendado

```ini
; Desarrollo
display_errors = On
error_reporting = E_ALL
log_errors = On
error_log = /ruta/a/php_errors.log

; Producción
display_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
log_errors = On
error_log = /var/log/php_errors.log

; Uploads
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20

; Memoria
memory_limit = 256M
max_execution_time = 60

; Sesión
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
session.gc_maxlifetime = 7200

; Timezone
date.timezone = America/Bogota
```

---

## 📝 Plantillas de Código

### Crear Nueva Página

```php
<?php
/**
 * Nombre: mi_nueva_pagina.php
 * Descripción: [Descripción de la página]
 * Autor: [Tu Nombre]
 * Fecha: [Fecha]
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Requerir base de datos
require_once __DIR__ . "/../config/database.php";

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../views/login.php");
    exit();
}

// Variables
$mensaje = "";
$error = "";

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tu lógica aquí
    
    try {
        // Validar datos
        if (empty($_POST['campo'])) {
            throw new Exception("Campo requerido");
        }
        
        // Procesar
        $stmt = $conn->prepare("INSERT INTO tabla (campo) VALUES (?)");
        $stmt->bind_param("s", $_POST['campo']);
        
        if ($stmt->execute()) {
            $mensaje = "Operación exitosa";
        } else {
            throw new Exception("Error al guardar");
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Consultar datos
$query = "SELECT * FROM tabla ORDER BY id DESC";
$result = $conn->query($query);
?>

<!-- HTML -->
<link rel="stylesheet" href="../assets/css/mi_estilo.css">

<div class="container-fluid">
    <h2 class="mt-4">Mi Página</h2>
    
    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="mb-3">
            <label>Campo</label>
            <input type="text" name="campo" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
    
    <hr>
    
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Campo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['campo']) ?></td>
                    <td>
                        <a href="?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">Ver</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
```

### Crear AJAX Handler

```php
<?php
/**
 * Nombre: mi_handler.php
 * Descripción: Handler AJAX para [funcionalidad]
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/database.php";

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'obtener':
            $id = (int)($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception("ID inválido");
            }
            
            $stmt = $conn->prepare("SELECT * FROM tabla WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();
            
            if (!$data) {
                throw new Exception("No encontrado");
            }
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;
            
        case 'guardar':
            $campo = trim($_POST['campo'] ?? '');
            
            if (empty($campo)) {
                throw new Exception("Campo requerido");
            }
            
            $stmt = $conn->prepare("INSERT INTO tabla (campo) VALUES (?)");
            $stmt->bind_param("s", $campo);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al guardar");
            }
            
            $id = $conn->insert_id;
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'id' => $id,
                'message' => 'Guardado correctamente'
            ]);
            break;
            
        default:
            throw new Exception("Acción no válida");
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
```

### JavaScript/jQuery AJAX Call

```javascript
/**
 * Función para llamar AJAX handler
 */
function llamarAjax(action, data, callback) {
    $.ajax({
        url: 'includes/mi_handler.php',
        method: 'POST',
        data: { action: action, ...data },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                callback(null, response);
            } else {
                callback(response.message, null);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            console.log('Response:', xhr.responseText);
            callback('Error de conexión', null);
        }
    });
}

// Uso
$('#btnGuardar').click(function() {
    const campo = $('#campo').val();
    
    llamarAjax('guardar', { campo: campo }, function(error, response) {
        if (error) {
            alert('Error: ' + error);
        } else {
            alert('Guardado con ID: ' + response.id);
            // Actualizar UI
        }
    });
});
```

---

## 📋 Checklist de Producción

### Antes de Desplegar

- [ ] **Seguridad**
  - [ ] Cambiar contraseña de admin
  - [ ] Cambiar credenciales de BD en `config/database.php`
  - [ ] Configurar HTTPS
  - [ ] Agregar `.htaccess` con restricciones
  - [ ] Validar permisos de archivos y carpetas
  - [ ] Desactivar `display_errors` en PHP

- [ ] **Base de Datos**
  - [ ] Backup de BD
  - [ ] Verificar índices
  - [ ] Optimizar tablas: `OPTIMIZE TABLE tabla`
  - [ ] Verificar integridad: `CHECK TABLE tabla`

- [ ] **Rendimiento**
  - [ ] Habilitar compresión GZIP
  - [ ] Configurar cache de navegador
  - [ ] Minificar CSS/JS
  - [ ] Optimizar imágenes

- [ ] **Testing**
  - [ ] Probar login/logout
  - [ ] Probar registro de empleados
  - [ ] Probar registro de entregas
  - [ ] Probar generación de PDFs
  - [ ] Probar búsquedas y filtros
  - [ ] Probar en diferentes navegadores

- [ ] **Documentación**
  - [ ] README actualizado
  - [ ] Manual de usuario
  - [ ] Comentarios en código
  - [ ] Diagrama de BD

- [ ] **Backup**
  - [ ] Configurar backup automático de BD
  - [ ] Backup de archivos (uploads/, firmas/)
  - [ ] Plan de recuperación

---

## 🎓 Recursos de Aprendizaje

### Para Principiantes

1. **HTML/CSS/JavaScript**
   - [W3Schools](https://www.w3schools.com/)
   - [MDN Web Docs](https://developer.mozilla.org/)

2. **PHP**
   - [PHP Manual](https://www.php.net/manual/es/)
   - [PHP The Right Way](https://phptherightway.com/)

3. **MySQL**
   - [MySQL Tutorial](https://www.mysqltutorial.org/)
   - [SQL en W3Schools](https://www.w3schools.com/sql/)

4. **Bootstrap**
   - [Bootstrap Docs](https://getbootstrap.com/docs/)
   - [Bootstrap Examples](https://getbootstrap.com/docs/5.0/examples/)

### Para Desarrolladores Intermedios

1. **Seguridad**
   - [OWASP Top 10](https://owasp.org/www-project-top-ten/)
   - [PHP Security Guide](https://phpsecurity.readthedocs.io/)

2. **Optimización**
   - [PHP Performance](https://www.php.net/manual/es/features.gc.performance-considerations.php)
   - [MySQL Optimization](https://dev.mysql.com/doc/refman/8.0/en/optimization.html)

3. **Patrones de Diseño**
   - [PHP Design Patterns](https://designpatternsphp.readthedocs.io/)
   - [Refactoring Guru](https://refactoring.guru/design-patterns)

---

## 🆘 Comandos Útiles

### MySQL

```bash
# Conectar a MySQL
# En MAMP: Usar phpMyAdmin desde http://localhost:8888/phpMyAdmin
# O desde terminal:
mysql -u root -p

# Exportar BD
mysqldump -u root -p prueba_epp > backup.sql

# Importar BD
mysql -u root -p prueba_epp < backup.sql

# Ver tamaño de BD
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema = 'prueba_epp';

# Optimizar tabla
OPTIMIZE TABLE empleados;

# Reparar tabla
REPAIR TABLE empleados;
```

### Composer

```bash
# Instalar dependencias
composer install

# Actualizar dependencias
composer update

# Agregar nueva dependencia
composer require vendor/package

# Ver dependencias instaladas
composer show
```

### Git

```bash
# Clonar repositorio
git clone [URL]

# Ver estado
git status

# Agregar cambios
git add .

# Commit
git commit -m "Mensaje"

# Push
git push origin main

# Pull
git pull origin main

# Crear rama
git checkout -b feature/nueva-funcionalidad

# Cambiar rama
git checkout main

# Merge
git merge feature/nueva-funcionalidad
```

---

## 📝 Logs y Debugging

### Ver Errores de PHP

```bash
# MAMP (macOS)
/Applications/MAMP/logs/php_error.log
/Applications/MAMP/logs/apache_error.log

# MAMP (Windows)
C:\\MAMP\\logs\\php_error.log
C:\\MAMP\\logs\\apache_error.log

# Linux (Apache)
/var/log/apache2/error.log
/var/log/php/error.log
```

### Monitorear logs en tiempo real

```bash
# macOS/Linux
tail -f /Applications/MAMP/logs/php_error.log

# Windows (PowerShell)
Get-Content C:\\MAMP\\logs\\php_error.log -Wait -Tail 50
```

---

**Última actualización**: Enero 9, 2026
