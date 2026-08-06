-- Extended schema for teacher token withdrawals (withdraw_tokens /
-- withdrawal_history / admin withdraw flow). Both the refactored
-- lib/app/WalletController.php and the old api_mobile.php insert/select these
-- columns, but local and VM schemas only had the minimal set -> any
-- withdrawal call returned HTTP 500. Plain ALTER for MySQL 8 + MariaDB
-- compatibility (apply.php ignores duplicate-column errors on re-runs).
ALTER TABLE retiros_tokens
  ADD COLUMN monto_usd DECIMAL(10,2) NULL DEFAULT NULL,
  ADD COLUMN monto_clp INT NULL DEFAULT NULL,
  ADD COLUMN comision DECIMAL(10,2) NULL DEFAULT NULL,
  ADD COLUMN neto_pagar DECIMAL(10,2) NULL DEFAULT NULL,
  ADD COLUMN tipo_cuenta VARCHAR(20) NULL DEFAULT NULL,
  ADD COLUMN paypal_email VARCHAR(150) NULL DEFAULT NULL,
  ADD COLUMN admin_note VARCHAR(255) NULL DEFAULT NULL,
  ADD COLUMN procesado_por INT NULL DEFAULT NULL,
  ADD COLUMN procesado_at DATETIME NULL DEFAULT NULL;
