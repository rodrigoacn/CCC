# Pruebas de Integración - ClassExpress

Este directorio contiene las pruebas de integración para el proyecto ClassExpress.

## Pruebas de API PHP (PHPUnit)

### Instalación

```bash
composer require --dev phpunit/phpunit
```

### Configuración

1. Iniciar XAMPP (Apache y MySQL)
2. Importar el archivo SQL de prueba en phpMyAdmin:
   - Ve a `http://localhost/phpmyadmin`
   - Importa `tests/test_database.sql`
   - Este archivo crea automáticamente la base de datos `classexpress_test` con todas las tablas y datos de prueba

3. Configurar variables de entorno (opcional):
   ```bash
   export DB_HOST=localhost
   export DB_PORT=3306
   export DB_NAME=classexpress_test
   export DB_USER=root
   export DB_PASS=
   ```

### Ejecutar pruebas

```bash
vendor/bin/phpunit tests/IntegrationTest.php
```

### Pruebas incluidas

- **Autenticación**: Registro, login, verificación de email
- **Referidos**: Sistema de referidos, minutos espectador, comisiones
- **Materias y Profesores**: Obtención de materias y profesores
- **Clases**: Creación, listado, filtrado de clases
- **Pagos**: Procesamiento de pagos, cálculo de comisiones
- **Espectadores**: Sistema de aprobación de espectadores
- **Créditos**: Balance, recarga, historial

## Notas

- Las pruebas de PHP requieren que XAMPP esté corriendo con Apache y MySQL
- Las pruebas cubren toda la lógica del backend de la API
- El archivo `test_database.sql` crea una base de datos fresca con todas las tablas necesarias
