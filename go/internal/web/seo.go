package web

import "net/http"

// SeoMeta holds the per-page search engine metadata rendered by base.html.
type SeoMeta struct {
	Title string
	Desc  string
	// Intro is a keyword-rich paragraph shown on public subject pages.
	Intro string
}

// siteURL is the canonical origin used for canonical links and sitemap URLs.
const siteURL = "https://classexpress.online"

// seoMap ports the per-page <title>/<meta description> that the legacy PHP
// pages lacked, targeting long-tail local keywords.
var seoMap = map[string]SeoMeta{
	"materias.php": {
		Title: "Materias y ramos con clases particulares online | ClassExpress",
		Desc:  "Encuentra profesor particular de matemáticas, biología, química, física, inglés y más. Clases en vivo por videoconferencia desde cualquier lugar de Chile y Latinoamérica.",
	},
	"matematicas.php": {
		Title: "Profesor de Matemáticas Online | Clases Particulares de Matemáticas — ClassExpress",
		Desc:  "Clases particulares de matemáticas online en vivo: álgebra, geometría, funciones, probabilidades y preparación PAES. Profesores verificados, agenda tu primera clase hoy.",
		Intro: "<strong>Clases particulares de matemáticas online</strong> en vivo con profesores verificados. Cubrimos álgebra, geometría, trigonometría, cálculo, estadística y <em>preparación PAES de matemáticas</em> para estudiantes de Chile y Latinoamérica. Aprende a tu ritmo, resuelve tus dudas de tarea y sube tus calificaciones con un <strong>profesor de matemáticas online</strong> elegido por ti.",
	},
	"biologia.php": {
		Title: "Profesor de Biología Online | Clases Particulares de Biología — ClassExpress",
		Desc:  "Clases particulares de biología online: célula, genética, evolución, sistemas del cuerpo humano y biología PAES. Profesores particulares verificados en vivo.",
		Intro: "<strong>Clases particulares de biología online</strong> en vivo: citología, genética, evolución, ecología y fisiología humana. Si buscas un <strong>profesor de biología online</strong> para entender la materia, preparar pruebas o rendir la <em>PAES de ciencias</em>, aquí encuentras docentes verificados que explican a tu ritmo.",
	},
	"quimica.php": {
		Title: "Profesor de Química Online | Clases Particulares de Química — ClassExpress",
		Desc:  "Clases particulares de química online en vivo: estequiometría, tabla periódica, enlaces, ácidos y bases, orgánica y química PAES con profesores verificados.",
		Intro: "<strong>Clases particulares de química online</strong> para estudiantes que quieren entender de verdad la materia: nomenclatura, estequiometría, soluciones, ácidos y bases y química orgánica. Nuestros <strong>profesores de química online</strong> te preparan también para la <em>PAES de ciencias</em> con ejercicios reales y resolución de dudas en vivo.",
	},
	"fisica.php": {
		Title: "Profesor de Física Online | Clases Particulares de Física — ClassExpress",
		Desc:  "Clases particulares de física online: cinemática, dinámica, energía, electricidad, ondas y física PAES. Profesores particulares verificados por videoconferencia.",
		Intro: "<strong>Clases particulares de física online</strong> en vivo: mecánica, cinemática, leyes de Newton, trabajo y energía, electricidad y magnetismo. Encuentra un <strong>profesor de física online</strong> que explique los conceptos con ejemplos claros y te prepare para la <em>PAES de física</em> paso a paso.",
	},
	"historia.php": {
		Title: "Profesor de Historia Online | Clases Particulares de Historia — ClassExpress",
		Desc:  "Clases particulares de historia online: historia de Chile, universal, economía y sociedad, historia PAES. Profesores particulares verificados en vivo.",
		Intro: "<strong>Clases particulares de historia online</strong>: historia de Chile, historia universal, formación cívica y análisis de fuentes. Con un <strong>profesor de historia online</strong> entenderás los procesos, fechas y causas detrás de cada periodo, ideal para pruebas, trabajos y la <em>PAES de historia y ciencias sociales</em>.",
	},
	"geografia.php": {
		Title: "Profesor de Geografía Online | Clases Particulares de Geografía — ClassExpress",
		Desc:  "Clases particulares de geografía online: geografía física, humana, de Chile y mundial, cartografía y geografía PAES con profesores verificados.",
		Intro: "<strong>Clases particulares de geografía online</strong> en vivo: relieve, clima, recursos naturales, población y territorio de Chile y el mundo. Un <strong>profesor de geografía online</strong> te ayuda a interpretar mapas, gráficos y datos para destacar en pruebas y en la <em>PAES</em>.",
	},
	"literatura.php": {
		Title: "Profesor de Lenguaje y Literatura Online | Clases Particulares — ClassExpress",
		Desc:  "Clases particulares de lenguaje y literatura online: comprensión lectora, redacción, análisis literario y lenguaje PAES. Profesores verificados en vivo.",
		Intro: "<strong>Clases particulares de lenguaje y literatura online</strong>: comprensión lectora, gramática, redacción de ensayos y análisis de obras. Mejora tu escritura y tu rendimiento con un <strong>profesor de literatura online</strong>, y prepárate para la <em>PAES de competencias lectoras</em> con estrategias probadas.",
	},
	"idiomas.php": {
		Title: "Profesor de Inglés y Idiomas Online | Clases Particulares de Idiomas — ClassExpress",
		Desc:  "Clases particulares de inglés online y otros idiomas: conversación, gramática, exámenes internacionales. Profesores nativos y verificados por videoconferencia.",
		Intro: "<strong>Clases particulares de inglés online</strong> y otros idiomas con profesores nativos y verificados. Practica conversación, refuerza gramática, prepara exámenes internacionales o mejora tu inglés para el trabajo con un <strong>profesor de inglés online</strong> flexible, a tu horario y desde cualquier lugar.",
	},
	"arte.php": {
		Title: "Profesor de Arte Online | Clases Particulares de Arte y Música — ClassExpress",
		Desc:  "Clases particulares de arte online: dibujo, pintura, música y expresión artística. Profesores particulares verificados por videoconferencia.",
		Intro: "<strong>Clases particulares de arte online</strong> para todas las edades: dibujo, pintura, historia del arte y expresión creativa. Aprende técnicas nuevas o refuerza tus estudios con un <strong>profesor de arte online</strong> paciente y verificado.",
	},
	"tecnologia.php": {
		Title: "Profesor de Tecnología y Programación Online | Clases Particulares — ClassExpress",
		Desc:  "Clases particulares de tecnología online: programación, robótica, ofimática y pensamiento computacional. Profesores verificados en vivo.",
		Intro: "<strong>Clases particulares de tecnología online</strong>: programación, pensamiento computacional, herramientas ofimáticas y cultura digital. Un <strong>profesor de tecnología online</strong> te acompaña desde lo básico hasta proyectos propios, en vivo y a tu ritmo.",
	},
	"educacion_fisica.php": {
		Title: "Profesor de Educación Física y Salud Online | Clases Particulares — ClassExpress",
		Desc:  "Clases particulares de educación física y salud online: anatomía, nutrición, vida sana y primeros auxilios. Profesores verificados en vivo.",
		Intro: "<strong>Clases particulares de educación física y salud online</strong>: anatomía, fisiología del ejercicio, nutrición y hábitos saludables. Refuerza la teoría de la asignatura con un <strong>profesor particular online</strong> verificado.",
	},
}

// seoFor returns the SEO metadata for a page ('' when unknown).
func seoFor(page string) SeoMeta {
	if m, ok := seoMap[page]; ok {
		return m
	}
	return SeoMeta{}
}

// HandleRobots serves robots.txt pointing crawlers at the sitemap.
func (p *Pages) HandleRobots(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/plain; charset=utf-8")
	_, _ = w.Write([]byte("User-agent: *\nAllow: /\nDisallow: /sala.php\nDisallow: /mi_sala.php\nSitemap: " + siteURL + "/sitemap.xml\n"))
}

// HandleSitemap serves an XML sitemap with the public, indexable URLs.
func (p *Pages) HandleSitemap(w http.ResponseWriter, r *http.Request) {
	urls := []string{siteURL + "/", siteURL + "/login.php", siteURL + "/materias.php"}
	for _, page := range SubjectPages() {
		urls = append(urls, siteURL+"/"+page)
	}
	w.Header().Set("Content-Type", "application/xml; charset=utf-8")
	w.Write([]byte(`<?xml version="1.0" encoding="UTF-8"?>` + "\n"))
	w.Write([]byte(`<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">` + "\n"))
	for _, u := range urls {
		w.Write([]byte("  <url><loc>" + u + "</loc></url>\n"))
	}
	w.Write([]byte("</urlset>\n"))
}
