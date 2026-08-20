-- Migración: tabla de compras "sin anuncios" (oferta 5000 CLP por 1 mes)
CREATE TABLE IF NOT EXISTS ads_free_compras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    monto_clp DECIMAL(10,2) NOT NULL DEFAULT 5000,
    valido_hasta DATETIME NOT NULL,
    estado ENUM('activo','expirado') NOT NULL DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);