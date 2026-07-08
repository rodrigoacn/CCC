-- Crear tabla paises en la base de datos classexpress
USE classexpress;

CREATE TABLE IF NOT EXISTS paises (
    paisId INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    codigo_moneda VARCHAR(3) NOT NULL,
    simbolo VARCHAR(5) NOT NULL,
    tasa_usd DECIMAL(10,4) DEFAULT 1.0000
) ENGINE=InnoDB;

INSERT INTO paises (nombre, codigo_moneda, simbolo, tasa_usd) VALUES
('United States', 'USD', '$', 1.0000),
('Chile', 'CLP', '$', 950.0000),
('Argentina', 'ARS', '$', 850.0000),
('Mexico', 'MXN', '$', 17.0000),
('Spain', 'EUR', '€', 0.9200),
('Colombia', 'COP', '$', 4100.0000);
