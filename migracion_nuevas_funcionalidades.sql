-- Migración incremental para nuevas funcionalidades de inventario, auditoría de ingresos y trazabilidad.
USE control_stock;

-- 1. Asegurar columnas de usuarios si no estuviesen aplicadas
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS apellido VARCHAR(100) NULL AFTER nombre,
    ADD COLUMN IF NOT EXISTS totp_secret_encrypted TEXT NULL AFTER rol,
    ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret_encrypted,
    ADD COLUMN IF NOT EXISTS totp_confirmed_at DATETIME NULL AFTER totp_enabled,
    ADD COLUMN IF NOT EXISTS totp_last_timeslice BIGINT UNSIGNED NULL AFTER totp_confirmed_at;

-- 2. Nuevos campos en la tabla productos (presentación, empaque, vencimiento y proveedor)
ALTER TABLE productos
    ADD COLUMN IF NOT EXISTS presentacion ENUM('unidad', 'caja', 'bulto') NOT NULL DEFAULT 'unidad' AFTER stock,
    ADD COLUMN IF NOT EXISTS unidades_por_bulto INT NOT NULL DEFAULT 1 AFTER presentacion,
    ADD COLUMN IF NOT EXISTS fecha_vencimiento DATE NULL AFTER unidades_por_bulto,
    ADD COLUMN IF NOT EXISTS proveedor VARCHAR(150) NULL AFTER fecha_vencimiento;

-- 3. Asegurar columnas en la tabla ventas si no estuviesen aplicadas
ALTER TABLE ventas
    ADD COLUMN IF NOT EXISTS cliente_id INT NULL AFTER usuario_id,
    ADD COLUMN IF NOT EXISTS estado ENUM('ACTIVA', 'MODIFICADA', 'CANCELADA') NOT NULL DEFAULT 'ACTIVA' AFTER fecha,
    ADD COLUMN IF NOT EXISTS fecha_modificacion DATETIME NULL AFTER estado,
    ADD COLUMN IF NOT EXISTS motivo_cancelacion VARCHAR(500) NULL AFTER fecha_modificacion;

-- 4. Crear tabla para historial de ventas si no existe
CREATE TABLE IF NOT EXISTS venta_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    usuario_id INT NULL,
    tipo VARCHAR(40) NOT NULL,
    cantidad_anterior INT NULL,
    cantidad_nueva INT NULL,
    total_anterior DECIMAL(10,2) NULL,
    total_nuevo DECIMAL(10,2) NULL,
    estado_anterior VARCHAR(20) NOT NULL,
    estado_nuevo VARCHAR(20) NOT NULL,
    motivo VARCHAR(500) NULL,
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_historial_venta_fecha (venta_id, fecha),
    CONSTRAINT fk_historial_venta FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    CONSTRAINT fk_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Crear tabla para el Kardex / Historial de ingresos de stock
CREATE TABLE IF NOT EXISTS ingresos_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    producto_nombre VARCHAR(150) NOT NULL,
    cantidad INT NOT NULL,
    presentacion ENUM('unidad', 'caja', 'bulto') NOT NULL DEFAULT 'unidad',
    unidades_por_bulto INT NOT NULL DEFAULT 1,
    total_unidades INT NOT NULL,
    precio_unitario DECIMAL(10,2) NULL,
    proveedor VARCHAR(150) NULL,
    fecha_vencimiento DATE NULL,
    usuario_id INT NULL,
    motivo VARCHAR(255) NULL,
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ingresos_producto (producto_id, fecha),
    INDEX idx_ingresos_proveedor (proveedor),
    INDEX idx_ingresos_fecha (fecha),
    CONSTRAINT fk_ingresos_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    CONSTRAINT fk_ingresos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
