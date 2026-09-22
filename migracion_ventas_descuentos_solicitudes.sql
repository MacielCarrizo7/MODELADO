-- Migración para venta por empaques, descuentos opcionales y solicitudes de atención de clientes
USE control_stock;

-- 1. Agregar columnas de empaque y descuentos a la tabla ventas
ALTER TABLE ventas
    ADD COLUMN IF NOT EXISTS tipo_venta ENUM('unidad', 'caja', 'bulto') NOT NULL DEFAULT 'unidad' AFTER producto_nombre,
    ADD COLUMN IF NOT EXISTS cantidad_empaque INT NOT NULL DEFAULT 1 AFTER tipo_venta,
    ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) NULL AFTER precio_unitario,
    ADD COLUMN IF NOT EXISTS descuento_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER subtotal,
    ADD COLUMN IF NOT EXISTS descuento_monto DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER descuento_porcentaje;

-- 2. Crear tabla para solicitudes de atención de clientes a vendedores / admin
CREATE TABLE IF NOT EXISTS solicitudes_vendedor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    mensaje VARCHAR(500) NULL,
    estado ENUM('PENDIENTE', 'ATENDIDA', 'CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_atencion DATETIME NULL,
    atendido_por INT NULL,
    INDEX idx_solicitudes_cliente (cliente_id, fecha),
    INDEX idx_solicitudes_estado (estado),
    CONSTRAINT fk_solicitudes_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_solicitudes_atendido FOREIGN KEY (atendido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
