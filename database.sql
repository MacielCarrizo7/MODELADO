-- ==============================================================================
-- BASE DE DATOS PARA EL SISTEMA DE STOCK Y VENTAS
-- Codificación: UTF8MB4 (Máxima compatibilidad para caracteres y acentos)
-- Compatible al 100% con XAMPP (Apache, MySQL/MariaDB, PHP) y phpMyAdmin
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `control_stock` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `control_stock`;

-- Desactivar verificación de claves foráneas temporalmente para recreación limpia
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `detalle_ventas`;
DROP TABLE IF EXISTS `venta_historial`;
DROP TABLE IF EXISTS `ingresos_stock`;
DROP TABLE IF EXISTS `solicitudes_vendedor`;
DROP TABLE IF EXISTS `ventas`;
DROP TABLE IF EXISTS `productos`;
DROP TABLE IF EXISTS `usuarios`;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- 1. TABLA: usuarios
-- Gestión de cuentas con roles: admin, vendedor, cliente
-- ------------------------------------------------------------------------------
CREATE TABLE `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `dni` VARCHAR(20) NOT NULL UNIQUE,
    `nombre` VARCHAR(100) NOT NULL,
    `apellido` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `rol` ENUM('admin', 'vendedor', 'cliente') NOT NULL DEFAULT 'cliente',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `totp_secret_encrypted` TEXT NULL,
    `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `totp_confirmed_at` DATETIME NULL,
    `totp_last_timeslice` BIGINT UNSIGNED NULL,
    INDEX `idx_usuarios_rol` (`rol`),
    INDEX `idx_usuarios_dni` (`dni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. TABLA: productos
-- Inventario con control de empaques, FIFO y auditoría
-- ------------------------------------------------------------------------------
CREATE TABLE `productos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NULL UNIQUE,
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `presentacion` ENUM('unidad', 'caja', 'bulto') NOT NULL DEFAULT 'unidad',
    `precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `stock` INT NOT NULL DEFAULT 0,
    `categoria` VARCHAR(100) NULL,
    `unidades_por_bulto` INT NOT NULL DEFAULT 1,
    `fecha_vencimiento` DATE NULL,
    `proveedor` VARCHAR(150) NULL,
    `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_productos_nombre` (`nombre`),
    INDEX `idx_productos_proveedor` (`proveedor`),
    INDEX `idx_productos_vencimiento` (`fecha_vencimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. TABLA: ventas
-- Cabecera y registro de transacciones comerciales
-- ------------------------------------------------------------------------------
CREATE TABLE `ventas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `producto_id` INT NULL,
    `producto_nombre` VARCHAR(150) NULL,
    `tipo_venta` ENUM('unidad', 'caja', 'bulto') NOT NULL DEFAULT 'unidad',
    `cantidad_empaque` INT NOT NULL DEFAULT 1,
    `cantidad` INT NOT NULL DEFAULT 1,
    `precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `descuento_porcentaje` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `descuento_monto` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `usuario_id` INT NOT NULL,
    `cliente_id` INT NULL,
    `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `estado` ENUM('ACTIVA', 'MODIFICADA', 'CANCELADA') NOT NULL DEFAULT 'ACTIVA',
    `fecha_modificacion` DATETIME NULL,
    `motivo_cancelacion` VARCHAR(500) NULL,
    INDEX `idx_ventas_fecha` (`fecha`),
    INDEX `idx_ventas_estado` (`estado`),
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON UPDATE CASCADE,
    FOREIGN KEY (`cliente_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. TABLA: detalle_ventas
-- Relación detallada de productos por venta
-- ------------------------------------------------------------------------------
CREATE TABLE `detalle_ventas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `venta_id` INT NOT NULL,
    `producto_id` INT NOT NULL,
    `producto_nombre` VARCHAR(150) NOT NULL,
    `tipo_venta` ENUM('unidad', 'caja', 'bulto') NOT NULL DEFAULT 'unidad',
    `cantidad_empaque` INT NOT NULL DEFAULT 1,
    `cantidad` INT NOT NULL,
    `precio_unitario` DECIMAL(10,2) NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `descuento_monto` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(10,2) NOT NULL,
    INDEX `idx_detalle_venta` (`venta_id`),
    INDEX `idx_detalle_producto` (`producto_id`),
    FOREIGN KEY (`venta_id`) REFERENCES `ventas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. TABLA: venta_historial
-- Auditoría de modificaciones y cancelaciones de ventas
-- ------------------------------------------------------------------------------
CREATE TABLE `venta_historial` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `venta_id` INT NOT NULL,
    `usuario_id` INT NULL,
    `tipo` VARCHAR(40) NOT NULL,
    `cantidad_anterior` INT NULL,
    `cantidad_nueva` INT NULL,
    `total_anterior` DECIMAL(10,2) NULL,
    `total_nuevo` DECIMAL(10,2) NULL,
    `estado_anterior` VARCHAR(20) NOT NULL,
    `estado_nuevo` VARCHAR(20) NOT NULL,
    `motivo` VARCHAR(500) NULL,
    `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_historial_venta` (`venta_id`),
    FOREIGN KEY (`venta_id`) REFERENCES `ventas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. TABLA: ingresos_stock
-- Kardex y auditoría de entradas de mercadería
-- ------------------------------------------------------------------------------
CREATE TABLE `ingresos_stock` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `producto_id` INT NOT NULL,
    `producto_nombre` VARCHAR(150) NOT NULL,
    `cantidad` INT NOT NULL,
    `presentacion` ENUM('unidad', 'caja', 'bulto') NOT NULL DEFAULT 'unidad',
    `unidades_por_bulto` INT NOT NULL DEFAULT 1,
    `total_unidades` INT NOT NULL,
    `precio_unitario` DECIMAL(10,2) NULL,
    `proveedor` VARCHAR(150) NULL,
    `fecha_vencimiento` DATE NULL,
    `usuario_id` INT NULL,
    `motivo` VARCHAR(255) NULL,
    `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ingresos_producto` (`producto_id`, `fecha`),
    INDEX `idx_ingresos_proveedor` (`proveedor`),
    INDEX `idx_ingresos_fecha` (`fecha`),
    FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. TABLA: solicitudes_vendedor
-- Solicitudes de atención de clientes para vendedores / administradores
-- ------------------------------------------------------------------------------
CREATE TABLE `solicitudes_vendedor` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cliente_id` INT NOT NULL,
    `mensaje` VARCHAR(500) NULL,
    `estado` ENUM('PENDIENTE', 'ATENDIDA', 'CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
    `fecha` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_atencion` DATETIME NULL,
    `atendido_por` INT NULL,
    INDEX `idx_solicitudes_cliente` (`cliente_id`, `fecha`),
    INDEX `idx_solicitudes_estado` (`estado`),
    FOREIGN KEY (`cliente_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`atendido_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 8. USUARIO ADMINISTRADOR INICIAL POR DEFECTO
-- DNI: 12345678
-- Nombre: Admin
-- Apellido: General
-- Contraseña: admin123 (Hasheada de forma segura con BCRYPT / password_hash)
-- Rol: admin
-- Activo: 1
-- ------------------------------------------------------------------------------
INSERT INTO `usuarios` (`id`, `dni`, `nombre`, `apellido`, `password`, `rol`, `activo`)
VALUES (
    1,
    '12345678',
    'Admin',
    'General',
    '$2y$10$mHHaStKeZxvmyUKUiMxh8.DscdJ6zFsDMUpkP0hhhN/X30VkvmZia',
    'admin',
    1
);
