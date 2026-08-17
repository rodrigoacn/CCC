package api

import (
	"net/http"

	"classexpress/internal/store"
)

// teacherDashboard mirrors TeacherController::teacherDashboard.
func (a *API) teacherDashboard(r *http.Request) *resp {
	user, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return errResp
	}
	if store.Str(user["rol"]) != "instructor" && store.Str(user["rol"]) != "both" {
		return errOut(http.StatusForbidden, "Solo instructores")
	}
	uid := store.Int(user["id"])

	me, err := a.DB.QueryOne(ctx(r),
		`SELECT u.nombre, u.rol, u.calificacion, u.num_resenas, u.avatar,
			pa.nombre AS pais, pa.simbolo, pa.codigo_moneda
		 FROM usuarios u
		 LEFT JOIN paises pa ON pa.paisId = u.pais_id
		 WHERE u.usuarioId = ?`, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	stats, err := a.DB.QueryOne(ctx(r),
		`SELECT
			COUNT(DISTINCT cp.claseId) AS total_clases,
			COALESCE(SUM(cp.activa), 0) AS clases_activas,
			COUNT(DISTINCT sc.sesionId) AS total_sesiones,
			COUNT(DISTINCT CASE WHEN sc.pagado=1 THEN sc.sesionId END) AS sesiones_pagadas,
			COALESCE(SUM(CASE WHEN p.estado='completado' THEN p.monto_usd END), 0) AS ganancias_usd
		 FROM clases_programadas cp
		 LEFT JOIN sesiones_clase sc ON sc.claseId = cp.claseId
		 LEFT JOIN pagos p ON p.sesionId = sc.sesionId
		 WHERE cp.instructorId = ?`, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	live, err := a.DB.QueryOne(ctx(r),
		`SELECT COUNT(*) AS n
		 FROM participantes_sala ps
		 JOIN salas s ON s.salaId = ps.salaId
		 JOIN clases_programadas cp ON cp.salaId = s.salaId
		 WHERE cp.instructorId = ?`, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	liveN := int64(0)
	if live != nil {
		liveN = store.Int(live["n"])
	}

	earningsByCurrency, err := a.DB.QueryAll(ctx(r),
		`SELECT p.moneda_local, p.simbolo_local,
			SUM(p.monto_local) AS total, COUNT(*) AS num_pagos
		 FROM pagos p
		 WHERE p.profesorId = ? AND p.estado = 'completado'
		 GROUP BY p.moneda_local, p.simbolo_local
		 ORDER BY total DESC`, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	clases, err := a.DB.QueryAll(ctx(r),		`SELECT cp.claseId AS id, cp.instructorId AS profesor_id, cp.materiaId AS materia_id,
			cp.precio_base AS precio, cp.precio_min, cp.precio_max, cp.codigo_moneda,
			cp.alumnos_min, cp.alumnos_max,
			cp.duracion_min AS duracion_minutos, cp.calificacion AS rating,
			cp.titulo, cp.descripcion, cp.activa, cp.created_at,
			m.nombre AS materia, s.salaId AS sala_id, s.activa AS sala_activa,
			COUNT(sc.sesionId) AS num_sesiones,
			COALESCE(SUM(CASE WHEN sc.pagado=1 THEN 1 ELSE 0 END), 0) AS num_pagados
		 FROM clases_programadas cp
		 JOIN materias m ON m.materiaId = cp.materiaId
		 LEFT JOIN salas s ON s.claseId = cp.claseId AND s.activa = true
		 LEFT JOIN sesiones_clase sc ON sc.claseId = cp.claseId
		 WHERE cp.instructorId = ?
		 GROUP BY cp.claseId, cp.instructorId, cp.materiaId, cp.precio_base, cp.precio_min, cp.precio_max,
			cp.codigo_moneda, cp.alumnos_min, cp.alumnos_max, cp.duracion_min, cp.calificacion,
			cp.titulo, cp.descripcion, cp.activa, cp.created_at, m.nombre, s.salaId, s.activa
		 ORDER BY cp.activa DESC, cp.created_at DESC`, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	ganRow, err := a.DB.QueryOne(ctx(r),
		`SELECT COALESCE(SUM(ABS(p.monto_usd)), 0) AS total
		 FROM pagos p
		 WHERE p.monto_usd < 0
		   AND p.profesorId IN (
			SELECT DISTINCT ps.usuarioId FROM participantes_sala ps
			JOIN salas s ON s.salaId = ps.salaId
			JOIN clases_programadas cp ON cp.claseId = s.claseId
			WHERE cp.instructorId = ?
		   )`, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	ganancias := float64(0)
	if ganRow != nil {
		ganancias = store.Float(ganRow["total"])
	}

	sesiones, err := a.DB.QueryAll(ctx(r),
		`SELECT sc.sesionId AS id, sc.inicio, sc.fin, sc.ultima_salida, sc.duracion_min,
			sc.monto_local, sc.moneda_local, sc.simbolo_local, sc.pagado,
			u.nombre AS estudiante, cp.titulo AS clase, m.nombre AS materia
		 FROM sesiones_clase sc
		 JOIN clases_programadas cp ON cp.claseId = sc.claseId
		 JOIN usuarios u ON u.usuarioId = sc.estudianteId
		 LEFT JOIN materias m ON m.materiaId = cp.materiaId
		 WHERE cp.instructorId = ?
		 ORDER BY sc.inicio DESC LIMIT 15`, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if earningsByCurrency == nil {
		earningsByCurrency = []map[string]any{}
	}

	return okOut(map[string]any{
		"me":                 me,
		"stats":              stats,
		"live":               liveN,
		"earningsByCurrency": earningsByCurrency,
		"ganancias":          ganancias,
		"clases":             clases,
		"sesiones":           sesiones,
	})
}

// createClass mirrors TeacherController::createClass.
func (a *API) createClass(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	if store.Str(user["rol"]) != "instructor" && store.Str(user["rol"]) != "both" {
		return errOut(http.StatusForbidden, "Solo instructores")
	}

	titulo := store.Str(body["titulo"])
	materiaID := bodyInt(body, "materia_id")
	precio := store.Float(body["precio"])
	descripcion := store.Str(body["descripcion"])
	duracion := bodyInt(body, "duracion")
	if duracion == 0 {
		duracion = 60
	}

	if titulo == "" || materiaID == 0 || precio <= 0 {
		return errOut(http.StatusBadRequest, "Datos requeridos")
	}

	if _, err := a.DB.Exec(ctx(r),
		"INSERT INTO clases_programadas (titulo, materiaId, instructorId, precio_base, descripcion, duracion_min, activa) VALUES (?, ?, ?, ?, ?, ?, true)",
		titulo, materiaID, user["id"], precio, descripcion, duracion); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	clase, err := a.DB.QueryOne(ctx(r),
		`SELECT cp.claseId AS id, cp.instructorId AS profesor_id, cp.materiaId AS materia_id, cp.precio_base AS precio,
			cp.duracion_min AS duracion_minutos, cp.calificacion AS rating,
			cp.titulo, cp.descripcion, cp.alumnos_max, cp.activa, cp.created_at,
			m.nombre AS materia
		 FROM clases_programadas cp
		 JOIN materias m ON m.materiaId = cp.materiaId
		 WHERE cp.instructorId = ? ORDER BY cp.claseId DESC LIMIT 1`, user["id"])
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"clase": clase})
}

// classAction mirrors TeacherController::classAction.
func (a *API) classAction(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	if store.Str(user["rol"]) != "instructor" && store.Str(user["rol"]) != "both" {
		return errOut(http.StatusForbidden, "Solo instructores")
	}
	uid := store.Int(user["id"])

	action := store.Str(body["action"])
	claseID := bodyInt(body, "clase_id")
	if claseID == 0 {
		return errOut(http.StatusBadRequest, "Clase requerida")
	}

	clase, err := a.DB.QueryOne(ctx(r),
		"SELECT claseId, activa FROM clases_programadas WHERE claseId = ? AND instructorId = ?", claseID, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if clase == nil {
		return errOut(http.StatusNotFound, "Clase no encontrada")
	}

	switch action {
	case "activate", "deactivate":
		activa := 0
		if action == "activate" {
			activa = 1
		}
		if _, err := a.DB.Exec(ctx(r),
			"UPDATE clases_programadas SET activa = ? WHERE claseId = ? AND instructorId = ?", activa, claseID, uid); err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
		return okOut(map[string]any{"ok": true, "activa": activa == 1})
	case "delete":
		if _, err := a.DB.Exec(ctx(r),
			"DELETE FROM clases_programadas WHERE claseId = ? AND instructorId = ?", claseID, uid); err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
		return okOut(map[string]any{"ok": true})
	default:
		return errOut(http.StatusBadRequest, "Acción no válida")
	}
}
