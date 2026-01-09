-- ============================================================================
-- SCHEMA DE BASE DE DATOS - SISTEMA DE GESTIÓN DE EPP
-- ============================================================================
-- Versión: 1.0.0
-- Fecha: 2026-01-09
-- Descripción: Estructura completa de base de datos para el sistema de
--              gestión de Elementos de Protección Personal
-- ============================================================================

-- Crear base de datos (comentar si ya existe)
CREATE DATABASE IF NOT EXISTS prueba_epp 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE prueba_epp;

-- ============================================================================
-- TABLA: usuarios
-- Descripción: Almacena los usuarios del sistema con sus credenciales
-- ============================================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL COMMENT 'Nombre completo del usuario',
    usuario VARCHAR(50) UNIQUE NOT NULL COMMENT 'Usuario único para login',
    password VARCHAR(255) NOT NULL COMMENT 'Contraseña hasheada con bcrypt',
    rol ENUM('admin', 'usuario') DEFAULT 'usuario' COMMENT 'Rol del usuario en el sistema',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro del usuario'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: empleados
-- Descripción: Registro de empleados de la organización
-- ============================================================================
CREATE TABLE IF NOT EXISTS empleados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL COMMENT 'Nombre completo del empleado',
    cedula VARCHAR(20) UNIQUE NOT NULL COMMENT 'Cédula única del empleado',
    cargo VARCHAR(100) COMMENT 'Cargo del empleado',
    area VARCHAR(100) COMMENT 'Área o departamento del empleado',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro en el sistema'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: entregas
-- Descripción: Cabecera de cada entrega de EPP (información general)
-- ============================================================================
CREATE TABLE IF NOT EXISTS entregas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL COMMENT 'ID del empleado que recibe',
    fecha_entrega DATE NOT NULL COMMENT 'Fecha de la entrega',
    numero_dotacion VARCHAR(50) COMMENT 'Número de dotación',
    responsable_entrega INT COMMENT 'ID del usuario responsable de la entrega',
    sst_id INT COMMENT 'ID del empleado representante de SST',
    firma_empleado VARCHAR(255) COMMENT 'Nombre del archivo de firma digital',
    pdf_file VARCHAR(255) COMMENT 'Nombre del archivo PDF adjunto',
    usuario_id INT NOT NULL COMMENT 'ID del usuario que registró la entrega',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro en el sistema',
    
    -- Claves foráneas
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
    FOREIGN KEY (responsable_entrega) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: entregas_detalle
-- Descripción: Detalle de elementos entregados en cada entrega
-- ============================================================================
CREATE TABLE IF NOT EXISTS entregas_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entrega_id INT NOT NULL COMMENT 'ID de la entrega (cabecera)',
    elemento VARCHAR(100) NOT NULL COMMENT 'Nombre del elemento EPP entregado',
    observacion TEXT COMMENT 'Observaciones sobre el elemento entregado',
    
    -- Clave foránea
    FOREIGN KEY (entrega_id) REFERENCES entregas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: elementos_personalizados
-- Descripción: Elementos personalizados creados por usuarios
-- ============================================================================
CREATE TABLE IF NOT EXISTS elementos_personalizados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL COMMENT 'ID del usuario que creó el elemento',
    nombre_elemento VARCHAR(100) NOT NULL COMMENT 'Nombre del elemento personalizado',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación del elemento',
    
    -- Clave foránea
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ÍNDICES PARA MEJORAR RENDIMIENTO
-- ============================================================================

-- Índice en cédula de empleados (búsquedas frecuentes)
CREATE INDEX idx_empleado_cedula ON empleados(cedula);

-- Índice en nombre de empleados (búsquedas por nombre)
CREATE INDEX idx_empleado_nombre ON empleados(nombre);

-- Índice en fecha de entrega (filtros por fecha)
CREATE INDEX idx_entrega_fecha ON entregas(fecha_entrega);

-- Índice en empleado_id de entregas (consultas por empleado)
CREATE INDEX idx_entrega_empleado ON entregas(empleado_id);

-- Índice en entrega_id de detalle (JOIN frecuente)
CREATE INDEX idx_detalle_entrega ON entregas_detalle(entrega_id);

-- Índice en área de empleados (filtros por área)
CREATE INDEX idx_empleado_area ON empleados(area);

-- Índice en cargo de empleados (filtros por cargo)
CREATE INDEX idx_empleado_cargo ON empleados(cargo);

-- ============================================================================
-- DATOS INICIALES
-- ============================================================================

-- Crear usuario administrador por defecto
-- Usuario: admin
-- Contraseña: admin123
-- IMPORTANTE: Cambiar la contraseña inmediatamente después de la instalación
INSERT INTO usuarios (nombre, usuario, password, rol) 
VALUES ('Administrador', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- ============================================================================
-- VISTAS ÚTILES (OPCIONAL)
-- ============================================================================

-- Vista de empleados con total de entregas
CREATE OR REPLACE VIEW v_empleados_entregas AS
SELECT 
    e.id,
    e.nombre,
    e.cedula,
    e.cargo,
    e.area,
    COUNT(DISTINCT ent.id) AS total_entregas,
    COUNT(ed.id) AS total_elementos,
    MAX(ent.fecha_entrega) AS ultima_entrega
FROM empleados e
LEFT JOIN entregas ent ON e.id = ent.empleado_id
LEFT JOIN entregas_detalle ed ON ent.id = ed.entrega_id
GROUP BY e.id, e.nombre, e.cedula, e.cargo, e.area;

-- Vista de elementos más entregados
CREATE OR REPLACE VIEW v_elementos_top AS
SELECT 
    elemento,
    COUNT(*) AS total_entregas,
    COUNT(DISTINCT entrega_id) AS total_entregas_unicas
FROM entregas_detalle
GROUP BY elemento
ORDER BY total_entregas DESC;

-- Vista de entregas por área
CREATE OR REPLACE VIEW v_entregas_por_area AS
SELECT 
    emp.area,
    COUNT(DISTINCT e.id) AS total_entregas,
    COUNT(ed.id) AS total_elementos,
    COUNT(DISTINCT emp.id) AS total_empleados
FROM empleados emp
LEFT JOIN entregas e ON emp.id = e.empleado_id
LEFT JOIN entregas_detalle ed ON e.id = ed.entrega_id
WHERE emp.area IS NOT NULL AND emp.area <> ''
GROUP BY emp.area
ORDER BY total_entregas DESC;

-- ============================================================================
-- PROCEDIMIENTOS ALMACENADOS (OPCIONAL)
-- ============================================================================

DELIMITER $$

-- Procedimiento para obtener historial completo de un empleado
CREATE PROCEDURE sp_historial_empleado(IN p_empleado_id INT)
BEGIN
    SELECT 
        e.id AS entrega_id,
        e.fecha_entrega,
        e.numero_dotacion,
        ed.elemento,
        ed.observacion,
        u.nombre AS responsable,
        emp_sst.nombre AS representante_sst,
        e.firma_empleado,
        e.pdf_file
    FROM entregas e
    LEFT JOIN entregas_detalle ed ON e.id = ed.entrega_id
    LEFT JOIN usuarios u ON e.responsable_entrega = u.id
    LEFT JOIN empleados emp_sst ON e.sst_id = emp_sst.id
    WHERE e.empleado_id = p_empleado_id
    ORDER BY e.fecha_entrega DESC, e.id DESC, ed.id;
END$$

-- Procedimiento para estadísticas generales
CREATE PROCEDURE sp_estadisticas_generales()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM empleados) AS total_empleados,
        (SELECT COUNT(*) FROM entregas) AS total_entregas,
        (SELECT COUNT(*) FROM entregas_detalle) AS total_elementos_entregados,
        (SELECT COUNT(DISTINCT elemento) FROM entregas_detalle) AS total_tipos_elementos,
        (SELECT COUNT(*) FROM usuarios) AS total_usuarios;
END$$

DELIMITER ;

-- ============================================================================
-- TRIGGERS (OPCIONAL)
-- ============================================================================

DELIMITER $$

-- Trigger para validar que la fecha de entrega no sea futura
CREATE TRIGGER trg_validar_fecha_entrega
BEFORE INSERT ON entregas
FOR EACH ROW
BEGIN
    IF NEW.fecha_entrega > CURDATE() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La fecha de entrega no puede ser futura';
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- VERIFICACIÓN DE INSTALACIÓN
-- ============================================================================

-- Mostrar resumen de tablas creadas
SELECT 
    TABLE_NAME AS 'Tabla',
    TABLE_ROWS AS 'Filas',
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS 'Tamaño (MB)'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'prueba_epp'
ORDER BY TABLE_NAME;

-- ============================================================================
-- NOTAS DE INSTALACIÓN
-- ============================================================================
-- 
-- 1. Ejecutar este script completo en MySQL:
--    mysql -u root -p < schema.sql
--
-- 2. O ejecutar sección por sección en phpMyAdmin
--
-- 3. Verificar que todas las tablas se hayan creado correctamente:
--    SHOW TABLES;
--
-- 4. Verificar usuario admin:
--    SELECT * FROM usuarios WHERE usuario = 'admin';
--
-- 5. Credenciales por defecto:
--    Usuario: admin
--    Contraseña: admin123
--    ¡CAMBIAR INMEDIATAMENTE EN PRODUCCIÓN!
--
-- 6. Para cambiar la contraseña:
--    UPDATE usuarios 
--    SET password = '[nuevo_hash]' 
--    WHERE usuario = 'admin';
--
--    Generar hash en PHP:
--    password_hash("nueva_contraseña", PASSWORD_BCRYPT);
--
-- ============================================================================
