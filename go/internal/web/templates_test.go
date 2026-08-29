package web

import (
	"html/template"
	"net/http/httptest"
	"strings"
	"testing"
)

func TestSalaTemplateRenders(t *testing.T) {
	ts := NewTemplateSet()
	p := &Pages{Templates: ts}
	s := &Session{Values: map[string]string{
		"usuarioId": "100",
		"nombre":    "Demo",
		"rol":       "estudiante",
	}}
	chat := []salaChatMsg{{MensajeId: 1, Alias: "Ana", Mensaje: "Hola"}}
	data := map[string]any{
		"Lang":          "es",
		"NavData":       NavData{NavRol: "estudiante"},
		"ClaseId":       178,
		"SalaId":        42,
		"CsrfToken":     "deadbeef",
		"UID":           100,
		"IsTeacher":     false,
		"IsTeacherJS":   template.JS("false"),
		"FromJSON":      template.JS(`"explorar"`),
		"ProfUID":       101,
		"LastMsgId":     1,
		"Titulo":        "Clase demo",
		"Materia":       "Matemáticas",
		"Profesor":      "Profe",
		"Simbolo":       "$",
		"MontoLocal":    "10.00",
		"MonedaLocal":   "USD",
		"PrecioUSD":     "10.00",
		"Creditos":      "20.00",
		"PrecioTxt":     "$10.00 USD",
		"AlumnosMax":    5,
		"Activos":       1,
		"SpotsLeft":     4,
		"JoinDisabled":  false,
		"ClassFull":     false,
		"NeedCredits":   false,
		"NeedCreditsT":  false,
		"SalaP":         "#2563EB",
		"SalaPb":        "#1D4ED8",
		"Chat":          chat,
		"ChatEmpty":     false,
		"ShowPriceInfo": true,
	}
	rec := httptest.NewRecorder()
	if err := ts.RenderAuthed(rec, "sala", p, s, "es", data); err != nil {
		t.Fatalf("render sala: %v", err)
	}
	out := rec.Body.String()
	for _, want := range []string{"CLASE_ID", "178", "SALA_ID", "42", "CSRF_TOKEN", "deadbeef",
		"IS_TEACHER = false", "id=\"remote-video\"", "id=\"chat-box\"",
		"Unirse a la clase", "FROM", "explorar", "Ana:</strong>",
		"Enciende tu cámara para unirte a la clase", "¿Abandonar la clase?"} {
		if !strings.Contains(out, want) {
			t.Errorf("sala render missing %q", want)
		}
	}

	nc := data
	nc["NeedCredits"] = true
	rec2 := httptest.NewRecorder()
	if err := ts.RenderAuthed(rec2, "sala", p, s, "es", nc); err != nil {
		t.Fatalf("render sala (need credits): %v", err)
	}
	if out2 := rec2.Body.String(); !strings.Contains(out2, "Se necesitan créditos") {
		t.Errorf("sala render missing need_credits_btn string")
	}
}

func TestMiSalaTemplateRenders(t *testing.T) {
	ts := NewTemplateSet()
	p := &Pages{Templates: ts}
	s := &Session{Values: map[string]string{"usuarioId": "100"}}
	data := map[string]any{
		"Lang":     "es",
		"NavData":  NavData{NavRol: "estudiante"},
		"IsTeacher": false,
		"HasRoom":  false,
	}
	var rec2 = httptest.NewRecorder()
	if err := ts.RenderAuthed(rec2, "mi_sala", p, s, "es", data); err != nil {
		t.Fatalf("render mi_sala: %v", err)
	}
	if !strings.Contains(rec2.Body.String(), "ml-wrap") {
		t.Errorf("mi_sala render output suspicious")
	}
}

func TestLandingTemplateRenders(t *testing.T) {
	ts := NewTemplateSet()
	p := &Pages{Templates: ts}
	s := &Session{Values: map[string]string{}}
	data := map[string]any{
		"Lang":            "en",
		"TotalStudents":   154,
		"TotalTeachers":   201,
		"TotalRegistered": 355,
		"Year":            "2026",
	}
	rec := httptest.NewRecorder()
	if err := ts.Render(rec, "landing", p, s, "en", data); err != nil {
		t.Fatalf("render landing: %v", err)
	}
	out := rec.Body.String()
	for _, want := range []string{"Entrar como Estudiante", "Entrar como Profesor", "ClassExpress — Bunny Software E.I.R.L.", "Habla tu idioma"} {
		if !strings.Contains(out, want) {
			t.Errorf("landing render missing %q", want)
		}
	}
}

func TestEditedTemplatesRenderSmoke(t *testing.T) {
	ts := NewTemplateSet()
	p := &Pages{Templates: ts}
	s := &Session{Values: map[string]string{"usuarioId": "100"}}
	nav := NavData{NavRol: "estudiante"}
	render := func(method, name string, data map[string]any) string {
		t.Helper()
		rec := httptest.NewRecorder()
		var err error
		if method == "authed" {
			err = ts.RenderAuthed(rec, name, p, s, "es", data)
		} else {
			err = ts.Render(rec, name, p, s, "es", data)
		}
		if err != nil {
			t.Fatalf("render %s: %v", name, err)
		}
		return rec.Body.String()
	}

	// materias (authed)
	render("authed", "materias", map[string]any{
		"Lang": "es", "NavData": nav, "First": "Demo", "IsTeacher": false,
		"Subjects": []subjectItem{{ID: 1, Nombre: "Matemáticas", NombreURL: "Matem%C3%A1ticas", Color: "#2563EB", Icon: "hash", Activas: 2}},
	})

	// contenido (authed)
	render("authed", "contenido", map[string]any{
		"Lang": "es", "NavData": nav, "Nombre": "Matemáticas", "Count": 0, "PluralS": "s", "PluralS2": "s", "Classes": []classItem{},
	})

	// mp_* (authed)
	for _, kind := range []string{"mp_success", "mp_pending", "mp_failure"} {
		data := map[string]any{"Lang": "es", "NavData": nav, "TypeLabel": "créditos CE"}
		if kind == "mp_success" {
			data["ShowSuccess"] = true
			data["ShowPending"] = false
			data["UserName"] = "Demo"
			data["Quantity"] = "20"
		}
		render("authed", kind, data)
	}

	// perfil_usuario (authed)
	render("authed", "perfil_usuario", map[string]any{
		"Lang": "es", "NavData": nav, "NotFound": false, "Nombre": "Demo", "Username": "demo",
		"EsProfesor": false, "EsMiPerfil": true, "NumResenas": 0, "Resenas": []any{}, "Clases": []any{}, "CSRF": "x",
		"Pais": "Chile", "IdiomasJoined": "", "MiembroDesde": "2026",
	})

	// verify / pago / pre_sala (Render, sin base)
	render("", "verify", map[string]any{
		"Lang": "es", "Status": "success", "Message": "OK",
		"FooterParams": map[string]string{"bootstrap": "", "author": ""},
	})
	render("", "pago", map[string]any{
		"Lang": "es", "NavData": nav, "Simbolo": "$", "MontoLocal": "10.00", "MonLocal": "USD",
	})
	render("", "pre_sala", map[string]any{
		"Lang": "es", "NavData": nav, "ClaseId": 1, "Titulo": "Demo", "IsTeacher": false,
		"PrecioTxt": "$10.00", "Simbolo": "$", "MontoLocal": "10.00",
		"Duracion": 45, "Materia": "Matemáticas", "Profesor": "Profe", "Rating": 4.5,
		"DescripcionPresent": false, "Descripcion": "", "Precio": 10, "Creditos": 20,
		"TieneSaldo": true, "FromJSON": template.JS(`"explorar"`), "IsTeacherJS": template.JS("false"),
	})

	// subject (authed)
	ts2 := NewTemplateSet()
	p2 := &Pages{Templates: ts2}
	s2 := &Session{Values: map[string]string{"usuarioId": "100"}}
 	data := map[string]any{
 		"Lang":              "es",
 		"NavData":           nav,
 		"Self":              "tecnologia.php",
 		"TranslatedName":    "Technology",
 		"SubjectImage":      "technology.png",
 		"BreadcrumbSubject": "Materias",
 		"IsLoggedIn":        true,
		"Subtitle":          "Elige hasta 5 temas",
		"ThemesSelected":    "temas seleccionados",
		"MaxWarning":        "Máximo 5 temas",
		"FindTeacher":       "Buscar profesor",
		"Pick":              "Elegir",
		"ThemeCol":          "Tema",
		"DescriptionCol":    "Descripción",
		"DoneBadge":         "Completado",
		"AlreadyCompleted":  "Ya completado",
		"Completados":       map[string]bool{"hardware-architecture": true},
		"Sections": []subjectSection{
			{
				Caption: "Digital Literacy",
				Themes: []subjectTheme{
					{Slug: "hardware-architecture", Title: "Hardware Architecture", Desc: "Core components."},
					{Slug: "software-os", Title: "Software and Operating Systems", Desc: "System vs app."},
				},
			},
		},
	}
	rec := httptest.NewRecorder()
	if err := ts2.RenderAuthed(rec, "subject", p2, s2, "es", data); err != nil {
		t.Fatalf("render subject: %v", err)
	}
	out := rec.Body.String()
	for _, want := range []string{"technology.png", "Digital Literacy", "theme-form",
		"name=\"temas[]\" value=\"software-os\"", "data-done", "Completado", "sticky-bar",
		"find-teacher-btn", "Buscar profesor"} {
		if !strings.Contains(out, want) {
			t.Errorf("subject render missing %q", want)
		}
	}
}
