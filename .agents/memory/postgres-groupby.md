---
name: PostgreSQL GROUP BY strictness
description: PostgreSQL requires all non-aggregated SELECT columns in GROUP BY; MySQL allowed partial GROUP BY.
---

## Rule
Any SQL query using GROUP BY must include every non-aggregated column in the GROUP BY clause.

**Why:** PostgreSQL strictly enforces SQL standard GROUP BY. MySQL (with sql_mode not set) allowed partial GROUP BY, silently picking arbitrary values for non-aggregated columns.

## How to apply
When writing queries like:
```sql
SELECT cp.claseId, cp.titulo, u.nombre, COUNT(sc.sesionId) AS n
FROM clases_programadas cp
JOIN usuarios u ON ...
LEFT JOIN sesiones_clase sc ON ...
GROUP BY cp.claseId
```
PostgreSQL will reject this. Fix: add all non-aggregated columns to GROUP BY:
```sql
GROUP BY cp.claseId, cp.titulo, u.nombre, ...
```
This affects `profesores.php` and `dashboard_profesor.php` which both use aggregate COUNT with many joined columns.
