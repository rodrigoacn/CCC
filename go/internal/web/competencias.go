package web

// competenciasMateriaNombre holds the Spanish subject names used to group the
// competencias catalog in the teacher profile picker.
var competenciasMateriaNombre = map[int64]string{
	1:  "Matemáticas",
	2:  "Biología",
	3:  "Química",
	4:  "Física",
	5:  "Historia",
	6:  "Geografía",
	7:  "Lenguaje y Literatura",
	8:  "Idiomas",
	9:  "Arte y Música",
	10: "Tecnología",
	11: "Educación Física",
}

// competenciaCatalogo lists, per materia, the topics a teacher can pick as
// competencias. The entries come from the subject syllabi and are stored, when
// selected, joined with ", " in usuarios.competencias (the matching layer
// normalizes accents/case with normalizeTokens).
var competenciaCatalogo = map[int64][]string{
	1: {
		"Números y operaciones", "Fracciones", "Porcentajes", "Potencias y raíces",
		"Proporcionalidad", "Logaritmos", "Álgebra", "Expresiones algebraicas",
		"Ecuaciones lineales", "Sistemas de ecuaciones", "Inecuaciones", "Funciones",
		"Funciones exponenciales y logarítmicas", "Trigonometría", "Geometría",
		"Congruencia y semejanza", "Teorema de Pitágoras", "Áreas y volúmenes",
		"Transformaciones geométricas", "Vectores", "Geometría analítica",
		"Análisis de datos", "Probabilidad", "Medidas de dispersión", "Combinatoria",
		"Cálculo", "Derivadas", "Integrales",
	},
	2: {
		"Células procariotas y eucariotas", "Membrana celular", "Fotosíntesis",
		"Respiración celular", "Macromoléculas orgánicas", "Ecología", "Cadenas tróficas",
		"Ciclos biogeoquímicos", "Dinámica de poblaciones", "Impacto ambiental",
		"Ciclo celular", "Mitosis", "Meiosis", "Genética mendeliana", "Evolución",
		"Sistema nervioso", "Sistema endocrino", "Sistema circulatorio", "Sistema respiratorio",
		"Sistema digestivo", "Sistema inmune", "Reproducción", "Genética molecular",
		"Microbiología",
	},
	3: {
		"Materia y sus cambios", "Átomos y tabla periódica", "Enlaces químicos",
		"Estequiometría", "Reacciones químicas", "Ácidos y bases", "pH", "Redox",
		"Termoquímica", "Cinética química", "Equilibrio químico", "Química orgánica",
		"Hidrocarburos", "Polímeros", "Química analítica", "Bioquímica",
	},
	4: {
		"Mecánica", "Cinemática", "Dinámica", "Leyes de Newton", "Energía",
		"Cantidad de movimiento", "Gravitación", "Ondas", "Sonido", "Óptica",
		"Electricidad", "Circuitos", "Magnetismo", "Termodinámica", "Fluidos",
		"Física moderna", "Relatividad", "Física nuclear",
	},
	5: {
		"Historia antigua", "Edad Media", "Edad Moderna", "Edad Contemporánea",
		"Revolución francesa", "Revolución industrial", "Guerras mundiales", "Guerra Fría",
		"Historia de Chile", "Colonización de América", "Independencia de América",
		"Historia económica", "Historia política", "Historia universal",
	},
	6: {
		"Cartografía", "Geografía física", "Relieve", "Climas", "Hidrografía", "Biomas",
		"Geografía humana", "Población", "Urbanización", "Geografía económica",
		"Recursos naturales", "Geografía política", "Geografía de Chile",
		"Desarrollo sustentable", "Riesgos naturales",
	},
	7: {
		"Gramática", "Ortografía", "Sintaxis", "Comprensión lectora", "Redacción",
		"Géneros literarios", "Narrativa", "Poesía", "Teatro", "Ensayo",
		"Análisis literario", "Literatura chilena", "Literatura hispanoamericana",
		"Literatura universal", "Medios de comunicación",
	},
	8: {
		"Inglés", "Español para extranjeros", "Francés", "Alemán", "Portugués",
		"Italiano", "Vocabulario", "Gramática aplicada", "Conversación",
		"Pronunciación", "Comprensión auditiva", "Lectura", "Escritura",
		"Preparación de exámenes", "Traducción",
	},
	9: {
		"Dibujo", "Pintura", "Historia del arte", "Teoría del color",
		"Composición visual", "Escultura", "Diseño gráfico", "Fotografía",
		"Historia de la música", "Teoría musical", "Instrumentos", "Canto",
		"Composición musical", "Apreciación musical", "Arte digital",
	},
	10: {
		"Programación", "Algoritmos", "Bases de datos", "Desarrollo web", "Python",
		"JavaScript", "Ofimática", "Hardware", "Redes", "Seguridad informática",
		"Inteligencia artificial", "Robótica", "Diseño web", "Aplicaciones móviles",
		"Excel", "Sistemas operativos", "Ciberseguridad",
	},
	11: {
		"Anatomía del ejercicio", "Fisiología del ejercicio", "Acondicionamiento físico",
		"Fuerza", "Resistencia", "Flexibilidad", "Deportes", "Fútbol", "Básquetbol",
		"Vóleibol", "Atletismo", "Natación", "Entrenamiento funcional",
		"Nutrición deportiva", "Prevención de lesiones", "Salud y bienestar",
	},
}

// competenciaGrupo is one subject section of the competencias catalog passed
// to the teacher profile template.
type competenciaGrupo struct {
	MateriaID   int64
	MateriaName string
	Temas       []string
}

// competenciasCatalogoData builds the ordered list of subjects with their
// topics for the profile picker.
func competenciasCatalogoData() []competenciaGrupo {
	out := make([]competenciaGrupo, 0, len(competenciaCatalogo))
	for id := int64(1); id <= 11; id++ {
		temas, ok := competenciaCatalogo[id]
		if !ok {
			continue
		}
		out = append(out, competenciaGrupo{MateriaID: id, MateriaName: competenciasMateriaNombre[id], Temas: temas})
	}
	return out
}