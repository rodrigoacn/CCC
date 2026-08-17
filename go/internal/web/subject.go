package web

import (
	"fmt"
	"net/http"
	"net/url"
	"strings"

	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

// subjectTheme is one selectable theme row inside a subject section.
type subjectTheme struct {
	Slug  string
	Title string
	Desc  string
}

// subjectSection groups themes under a caption (ports the $secciones arrays).
type subjectSection struct {
	Caption string
	Themes  []subjectTheme
}

// subjectPage is the static per-subject data that matematicas.php, biologia.php,
// ... pass to _subject_page.php.
type subjectPage struct {
	MateriaID    int64
	SubjectName  string
	SubjectImage string
	Sections     []subjectSection
}

// subjectPageMap ports the eleven subject pages. Section captions use the
// English text (tecnologia.php's tecnologia.section_* keys resolve to literal
// keys in the originals, so English captions are used uniformly).
var subjectPageMap = map[string]subjectPage{
	"matematicas.php": {
		MateriaID: 1, SubjectName: "Mathematics", SubjectImage: "mathematics.png",
		Sections: []subjectSection{
			{
				Caption: "Numbers and Operations",
				Themes: []subjectTheme{
					{"number-sets", "Number Sets", "Natural, integer, rational, and real numbers — properties and operations."},
					{"percentages", "Percentages", "Percentage calculation, increase/decrease, and everyday applications."},
					{"powers-roots", "Powers and Roots", "Integer and fractional exponents, n-th roots, and simplification rules."},
					{"proportionality", "Proportionality", "Direct and inverse proportionality, rule of three, and ratio applications."},
					{"logarithms", "Logarithms", "Definition, properties, change of base, and real-world applications."},
					{"financial-math", "Financial Mathematics", "Simple and compound interest, annuities, and loan calculations."},
				},
			},
			{
				Caption: "Algebra and Functions",
				Themes: []subjectTheme{
					{"algebraic-expressions", "Algebraic Expressions", "Polynomials, factoring, and operations with algebraic fractions."},
					{"linear-equations", "Linear Equations & Systems", "Solving first-degree equations and 2×2/3×3 linear systems."},
					{"inequalities", "Inequalities", "First-degree inequalities and graphical representation on the number line."},
					{"functions", "Functions", "Domain, range, and graphing of linear, quadratic, and piecewise functions."},
					{"exp-log-functions", "Exponential & Log Functions", "Graphs, transformations, and real-world modeling."},
					{"trigonometry", "Trigonometry", "Trigonometric ratios, the unit circle, and basic identities."},
				},
			},
			{
				Caption: "Geometry",
				Themes: []subjectTheme{
					{"congruence-similarity", "Congruence and Similarity", "Triangle congruence criteria, similarity ratios, and scale."},
					{"thales-pythagoras", "Thales & Pythagorean Theorem", "Applications in right triangles and coordinate geometry."},
					{"areas-volumes", "Areas and Volumes", "Perimeters, areas of plane figures, and volumes of 3D solids."},
					{"transformations", "Geometric Transformations", "Translation, rotation, reflection, and glide reflection."},
					{"vectors", "Vectors in a Plane", "Vector addition, scalar multiplication, and dot product."},
					{"analytic-geometry", "Analytic Geometry", "Lines, circles, and conics in the Cartesian plane."},
				},
			},
			{
				Caption: "Probability and Statistics",
				Themes: []subjectTheme{
					{"data-analysis", "Data Analysis", "Charts, frequency distributions, and measures of central tendency."},
					{"probability", "Probability", "Sample spaces, events, addition/multiplication rules, and conditional probability."},
					{"dispersion", "Dispersion Measures", "Variance, standard deviation, and interquartile range."},
					{"combinatorics", "Combinatorics", "Permutations, combinations, and the binomial theorem."},
				},
			},
		},
	},
	"biologia.php": {
		MateriaID: 2, SubjectName: "Biology", SubjectImage: "biology.png",
		Sections: []subjectSection{
			{
				Caption: "Cellular Organization, Structure, and Activity",
				Themes: []subjectTheme{
					{"cell-types", "Prokaryotic and Eukaryotic Cells", "Structural and functional differences; organelle structure and function."},
					{"cell-membrane", "Cell Membrane", "Fluid mosaic model; passive transport (diffusion, osmosis) and active transport."},
					{"bioenergetics", "Bioenergetic Processes", "Photosynthesis (light/dark phases) and cellular respiration overview."},
					{"macromolecules", "Organic Macromolecules", "Structure and function of proteins, carbohydrates, lipids, and nucleic acids."},
				},
			},
			{
				Caption: "Ecosystem Processes and Ecology",
				Themes: []subjectTheme{
					{"energy-flow", "Energy and Matter Flow", "Food webs, trophic levels, producers, consumers, and decomposers."},
					{"biogeochemical-cycles", "Biogeochemical Cycles", "Water, carbon, and nitrogen cycles and their global importance."},
					{"population-dynamics", "Population and Community Dynamics", "Growth curves, density, birth/death rates, and interspecific interactions."},
					{"environmental-impact", "Environmental Impact", "Global warming, biodiversity loss, pollution, and invasive species."},
				},
			},
			{
				Caption: "Inheritance, Genetics, and Evolution",
				Themes: []subjectTheme{
					{"mitosis", "Cell Cycle and Mitosis", "G1/S/G2 phases, mitosis stages, and relation to cancer."},
					{"meiosis", "Meiosis and Gametogenesis", "Meiosis stages, crossing-over, independent assortment, and gamete formation."},
					{"mendelian-genetics", "Mendelian Genetics", "Phenotype/genotype, dominant/recessive alleles, monohybrid and dihybrid crosses."},
					{"evolution", "Evolutionary Theories", "Evidence for evolution (fossil, anatomical, molecular) and natural selection."},
				},
			},
			{
				Caption: "Human Body Systems, Health, and Reproduction",
				Themes: []subjectTheme{
					{"nervous-system", "Nervous System", "Central/peripheral nervous system, neurons, synapses, and reflex arcs."},
					{"endocrine-system", "Endocrine System", "Hormones, glands (thyroid, adrenal, pituitary), and feedback regulation."},
					{"circulatory-immune", "Circulatory and Immune System", "Heart, blood vessels, blood types, and immune response."},
					{"reproductive-system", "Reproductive System", "Male and female anatomy, gametogenesis, fertilization, and development."},
				},
			},
		},
	},
	"quimica.php": {
		MateriaID: 3, SubjectName: "Chemistry", SubjectImage: "chemistry.png",
		Sections: []subjectSection{
			{
				Caption: "Atomic Structure and Properties of Matter",
				Themes: []subjectTheme{
					{"classification-matter", "Classification of Matter", "Pure substances vs. mixtures; separation methods (filtration, distillation)."},
					{"atomic-theory", "Atomic Theory", "Evolution of atomic models and fundamental subatomic particles."},
					{"electron-configuration", "Electron Configuration", "Build-up principles, orbital diagrams, valence electrons, and quantum numbers."},
					{"periodic-table", "Periodic Table", "Groups, periods, and periodic properties (radius, ionization energy, electronegativity)."},
					{"chemical-bonding", "Chemical Bonding", "Ionic, covalent, and metallic bonds; intermolecular forces and polarity."},
				},
			},
			{
				Caption: "Solution Chemistry and Stoichiometry",
				Themes: []subjectTheme{
					{"aqueous-solutions", "Aqueous Solutions", "Solubility, concentration units (molarity, molality), and dilution effects."},
					{"stoichiometry", "Stoichiometry", "Mole concept, molar mass, balancing equations, and limiting reagent."},
					{"acid-base", "Acid-Base Chemistry", "pH, strong/weak acids and bases, neutralization, and buffer solutions."},
					{"redox-reactions", "Oxidation-Reduction", "Oxidation states, balancing redox equations, and electrochemistry basics."},
				},
			},
			{
				Caption: "Organic Chemistry",
				Themes: []subjectTheme{
					{"hydrocarbons", "Hydrocarbons", "Alkanes, alkenes, alkynes, and aromatic compounds; IUPAC nomenclature."},
					{"functional-groups", "Functional Groups", "Alcohols, aldehydes, ketones, carboxylic acids, and esters."},
					{"polymers-biomolecules", "Polymers and Biomolecules", "Natural and synthetic polymers; connection to biological macromolecules."},
				},
			},
			{
				Caption: "Thermochemistry and Reaction Kinetics",
				Themes: []subjectTheme{
					{"thermochemistry", "Thermochemistry", "Enthalpy, endothermic/exothermic reactions, and Hess's law."},
					{"reaction-kinetics", "Reaction Kinetics", "Reaction rates, activation energy, catalysts, and collision theory."},
					{"chemical-equilibrium", "Chemical Equilibrium", "Le Chatelier's principle and the equilibrium constant (Keq)."},
				},
			},
		},
	},
	"fisica.php": {
		MateriaID: 4, SubjectName: "Physics", SubjectImage: "physics.png",
		Sections: []subjectSection{
			{
				Caption: "Mechanics",
				Themes: []subjectTheme{
					{"kinematics", "Kinematics", "Motion in a straight line: position, displacement, velocity, acceleration, MRU and MRUA."},
					{"dynamics", "Dynamics and Forces", "Newton's three laws, weight, friction, tension, Hooke's law, and static equilibrium."},
					{"linear-momentum", "Linear Momentum", "Momentum, impulse, and conservation of linear momentum in collisions."},
					{"energy-work", "Energy and Work", "Kinetic and potential energy, conservation of mechanical energy, and power."},
				},
			},
			{
				Caption: "Waves and Optics",
				Themes: []subjectTheme{
					{"wave-properties", "Properties of Waves", "Amplitude, frequency, period, wavelength, speed, and wave classification."},
					{"wave-phenomena", "Wave Phenomena", "Reflection, refraction (Snell's law), diffraction, and interference."},
					{"sound", "Sound", "Production and propagation, pitch/intensity/timbre, Doppler effect, and resonance."},
					{"light-optics", "Light and Optics", "Electromagnetic spectrum, mirrors, converging/diverging lenses, and eye defects."},
				},
			},
			{
				Caption: "Electricity and Magnetism",
				Themes: []subjectTheme{
					{"electrostatics", "Electrostatics", "Electric charge, charging methods (friction, contact, induction), and Coulomb's law."},
					{"electric-circuits", "Electric Circuits", "Ohm's law, resistance, voltage, Joule effect, and series/parallel circuits."},
					{"magnetism", "Magnetism", "Magnetic fields, Oersted's discovery, and Faraday's electromagnetic induction."},
				},
			},
			{
				Caption: "Earth Sciences and the Universe",
				Themes: []subjectTheme{
					{"earth-structure", "Earth Structure and Dynamics", "Internal layers, plate tectonics, earthquakes (P, S, R, L waves), and volcanism."},
					{"universe-astronomy", "The Universe and Astronomy", "Solar system, stellar evolution, Big Bang, and cosmological scales."},
					{"atmosphere-climate", "Atmosphere and Climate", "Layers of the atmosphere, weather vs. climate, and the greenhouse effect."},
				},
			},
		},
	},
	"historia.php": {
		MateriaID: 5, SubjectName: "History", SubjectImage: "history.png",
		Sections: []subjectSection{
			{
				Caption: "World and American History",
				Themes: []subjectTheme{
					{"19th-century", "The 19th Century", "Liberalism, the Industrial Revolution, idea of progress, and geopolitical transformations."},
					{"first-half-20th", "The First Half of the 20th Century", "WWI, the Great Depression, totalitarian regimes (Fascism, Nazism, Stalinism), and WWII."},
					{"second-half-20th", "The Second Half of the 20th Century", "Cold War, Latin American dictatorships, and accelerated globalization under neoliberalism."},
				},
			},
			{
				Caption: "Civic Education and Human Rights",
				Themes: []subjectTheme{
					{"democratic-state", "The Democratic State of Law", "Democracy principles, constitutions, separation of powers, and citizen participation."},
					{"human-rights", "Human Rights", "Universality of rights, international responsibilities, diversity, and non-discrimination."},
				},
			},
			{
				Caption: "Economy and Society",
				Themes: []subjectTheme{
					{"economic-problem", "The Economic Problem", "Scarcity, resource allocation, and the fundamental economic questions."},
					{"market-mechanics", "Market Mechanics", "Supply/demand, economic agents, inflation, unemployment, and fiscal policy."},
					{"responsible-consumption", "Responsible Consumption", "Financial markets, consumer rights, debt management, and sustainable development."},
				},
			},
			{
				Caption: "Evaluated Historical Skills",
				Themes: []subjectTheme{
					{"temporal-spatial", "Temporal and Spatial Thinking", "Locating and contextualizing historical processes within specific eras and territories."},
					{"source-analysis", "Analysis of Information Sources", "Extracting conclusions from texts, graphs, maps, and historical cartoons."},
					{"critical-thinking", "Critical Thinking", "Multiple causation, identifying historical continuities, and change-over-time analysis."},
				},
			},
		},
	},
	"geografia.php": {
		MateriaID: 6, SubjectName: "Geography", SubjectImage: "geography.png",
		Sections: []subjectSection{
			{
				Caption: "Physical Geography and Natural Environment",
				Themes: []subjectTheme{
					{"geomorphology", "Geomorphology", "Landforms: plains, mountains, plateaus, valleys, and coastal features."},
					{"climate-systems", "Climate Systems", "Climate classification, factors determining climate, and world biomes."},
					{"hydrology", "Hydrology", "Water cycle, drainage basins, river systems, oceans, and groundwater."},
					{"biogeography", "Biogeography", "Distribution of ecosystems (rainforests, deserts, tundra) based on climate and soil."},
				},
			},
			{
				Caption: "Human and Population Geography",
				Themes: []subjectTheme{
					{"demographics", "Demographics", "Population density, birth/death rates, life expectancy, and population pyramids."},
					{"migration", "Migration", "Push/pull factors, internal vs. international migration, and socioeconomic impacts."},
					{"urban-geography", "Urban Geography", "Urbanization, city structures, megacities, and urban challenges."},
					{"rural-geography", "Rural Geography", "Settlement patterns, agricultural systems, and rural space transformation."},
				},
			},
			{
				Caption: "Economic and Political Geography",
				Themes: []subjectTheme{
					{"economic-sectors", "Economic Sectors", "Primary, secondary, tertiary, and quaternary economic activities."},
					{"globalization", "Globalization", "Global trade networks, transnational corporations, and economic disparities."},
					{"geopolitics", "Geopolitics", "State, nation, territory, borders, conflicts, and supranational organizations."},
					{"natural-resources", "Natural Resources", "Renewable vs. non-renewable resources and geopolitical control of strategic assets."},
				},
			},
			{
				Caption: "Environmental Geography and Sustainability",
				Themes: []subjectTheme{
					{"human-impact", "Human Impact", "Deforestation, soil degradation, water pollution, and urban sprawl."},
					{"climate-change-geo", "Climate Change", "Global warming, greenhouse effect, and regional consequences."},
					{"sustainable-development", "Sustainable Development", "Development goals, circular economy, and international environmental agreements."},
				},
			},
		},
	},
	"literatura.php": {
		MateriaID: 7, SubjectName: "Language & Literature", SubjectImage: "literature.png",
		Sections: []subjectSection{
			{
				Caption: "Evaluated Reading Skills",
				Themes: []subjectTheme{
					{"locate-info", "Locate Information", "Find specific, explicit information within a text accurately and quickly."},
					{"interpret-text", "Interpret Texts", "Connect ideas, infer implicit information, and determine global meaning."},
					{"evaluate-text", "Evaluate Texts", "Judge content, form, argument validity, and the author's intent."},
				},
			},
			{
				Caption: "Text Types",
				Themes: []subjectTheme{
					{"literary-texts", "Literary Texts", "Narrative texts: short stories, novel fragments, myths, and legends."},
					{"non-literary-texts", "Non-Literary Texts", "Informative (news, reports) and argumentative (editorials, opinion columns) texts."},
				},
			},
			{
				Caption: "Reading Situations",
				Themes: []subjectTheme{
					{"personal-reading", "Personal Reading", "Leisure, entertainment, and individual interest texts."},
					{"public-reading", "Public Reading", "Community interest, civic, and social texts."},
					{"educational-reading", "Educational Reading", "Academic, scientific, and textbook materials."},
				},
			},
			{
				Caption: "Language and Literary Devices",
				Themes: []subjectTheme{
					{"narrative-elements", "Narrative Elements", "Narrator, character types, setting, plot structure, and narrative point of view."},
					{"figurative-language", "Figurative Language", "Metaphor, simile, personification, irony, and hyperbole."},
					{"text-structure", "Text Structure", "Introduction, development, conclusion; cohesion and coherence devices."},
					{"literary-movements", "Literary Movements", "Romanticism, Realism, Modernism, and the Latin American Boom."},
				},
			},
		},
	},
	"idiomas.php": {
		MateriaID: 8, SubjectName: "Foreign Languages", SubjectImage: "foreign_languages.png",
		Sections: []subjectSection{
			{
				Caption: "Theoretical Linguistics and the Language System",
				Themes: []subjectTheme{
					{"phonetics", "Phonetics and Phonology", "Places and manners of articulation; stress, rhythm, and intonation patterns."},
					{"morphosyntax", "Morphosyntax — Grammar", "Parts of speech, sentence structure (SVO), tenses, and moods."},
					{"vocabulary", "Vocabulary and Lexicology", "Word families, collocations, false cognates, and register."},
				},
			},
			{
				Caption: "Comprehension and Receptive Mechanisms",
				Themes: []subjectTheme{
					{"reading-techniques", "Reading Techniques", "Skimming (global idea) and scanning (locating specific data)."},
					{"inference", "Inference", "Deducing unfamiliar word meanings using contextual clues."},
					{"listening-comprehension", "Listening Comprehension", "Identifying registers, dialects, and auditory discrimination of minimal pairs."},
				},
			},
			{
				Caption: "Production and Expressive Mechanisms",
				Themes: []subjectTheme{
					{"written-expression", "Written Expression", "Cohesion/coherence, logical connectors, and text typologies (emails, essays, reviews)."},
					{"oral-expression", "Oral Expression", "Fluency vs. accuracy; compensation strategies and conversational interaction."},
				},
			},
			{
				Caption: "Sociolinguistics and Culture",
				Themes: []subjectTheme{
					{"sociolinguistics", "Sociolinguistics", "Language variation: dialects, sociolects, registers, and code-switching."},
					{"intercultural-competence", "Intercultural Competence", "Cultural values behind language; non-verbal communication norms across cultures."},
					{"pragmatics", "Pragmatics", "Speech acts, politeness strategies, and conversational implicature."},
				},
			},
		},
	},
	"arte.php": {
		MateriaID: 9, SubjectName: "Art and Music", SubjectImage: "art.png",
		Sections: []subjectSection{
			{
				Caption: "Assessed Skills",
				Themes: []subjectTheme{
					{"analyze-art", "Analyze", "Examine elements of visual language and contexts across diverse artistic expressions."},
					{"interpret-art", "Interpret", "Assign grounded meanings to artworks based on their context or materiality."},
					{"evaluate-art", "Evaluate", "Form critical judgments on the impact, aesthetics, and purpose of a cultural manifestation."},
				},
			},
			{
				Caption: "Elements of Visual Language and Technical Procedures",
				Themes: []subjectTheme{
					{"line", "Line", "Expressive role, contour, directionality, and types of lines in composition."},
					{"shape-volume", "Shape and Volume", "Figurative, abstract, geometric, and organic forms."},
					{"color-theory", "Color Theory", "Saturation, contrast, color harmonies, and color psychology."},
					{"space-perspective", "Space and Perspective", "Depth, overlapping, framing, and the vanishing point in perspective drawing."},
					{"texture-light", "Texture and Lighting", "Visual vs. tactile texture; direct/diffused light and chiaroscuro."},
					{"mediums-techniques", "Mediums and Techniques", "Printmaking, sculpture, painting, photography, and digital media."},
				},
			},
			{
				Caption: "Art History and Artistic Movements",
				Themes: []subjectTheme{
					{"classical-modern", "Classical and Modern Western Art", "Renaissance, Baroque, Impressionism, and 20th-century avant-gardes."},
					{"visual-ruptures", "Visual Ruptures", "Cubism, Surrealism, and Abstract Expressionism."},
					{"recent-movements", "Recent Movements", "Pop Art, Minimalism, Conceptual Art, and Performance Art."},
					{"latin-american-art", "Latin American Art", "Muralism, magical realism in visual arts, and contemporary LATAM artists."},
				},
			},
			{
				Caption: "Music",
				Themes: []subjectTheme{
					{"music-elements", "Elements of Music", "Rhythm, melody, harmony, dynamics, and timbre — how music is structured."},
					{"music-history", "Music History", "Western classical periods through jazz, rock, and contemporary genres."},
				},
			},
		},
	},
	"tecnologia.php": {
		MateriaID: 10, SubjectName: "Technology", SubjectImage: "technology.png",
		Sections: []subjectSection{
			{
				Caption: "Digital Literacy, Hardware and Software Systems",
				Themes: []subjectTheme{
					{"hardware-architecture", "Hardware Architecture", "Core components: CPU, RAM, storage units (SSD/HDD), motherboard, and input/output peripherals."},
					{"software-os", "Software and Operating Systems", "System software versus application software; how the OS manages resources."},
					{"file-management", "File Management", "Directory structures, file extensions, and cloud storage systems."},
					{"data-representation", "Data Representation", "Binary code, bits, bytes, and data compression basics."},
				},
			},
			{
				Caption: "Networks, Internet and Cybersecurity",
				Themes: []subjectTheme{
					{"network-fundamentals", "Network Fundamentals", "LAN/WAN networks, IP addresses, routers, and the Client-Server model."},
					{"internet-protocols", "Internet Protocols", "HTTP, HTTPS, FTP, and DNS — how data travels across the web."},
					{"information-security", "Information Security", "Threats: Malware, phishing, ransomware, and social engineering."},
					{"cybersecurity-prevention", "Cybersecurity Prevention", "Firewalls, multi-factor authentication, encryption, and strong password policies."},
					{"digital-footprint", "Digital Footprint & Privacy", "Managing personal data online, cookies, terms of service, and digital identity."},
				},
			},
			{
				Caption: "Algorithmic Thinking, Programming and Automation",
				Themes: []subjectTheme{
					{"algorithms-logic", "Algorithms and Logic", "Flowcharts, pseudocode, and decomposition of complex problems."},
					{"programming-variables", "Programming Core — Variables", "Variables, constants, and data types (strings, integers, booleans)."},
					{"programming-control", "Programming Core — Control", "Conditionals (If-Else) and loops (For, While)."},
					{"emerging-tech", "Emerging Technologies", "Intro to AI / Machine Learning, automation, and the Internet of Things (IoT)."},
				},
			},
			{
				Caption: "Technological Design, Projects and Social Impact",
				Themes: []subjectTheme{
					{"design-process", "The Design Process", "Problem identification, wireframing, iterative testing, and user-centred design (UX/UI)."},
					{"tech-ethics", "Technological Ethics", "E-waste, environmental impact of data centres, digital divide, and intellectual property."},
					{"digital-divide", "Accessibility & Open Source", "Digital accessibility standards, Creative Commons, and Open Source licensing."},
				},
			},
		},
	},
	"educacion_fisica.php": {
		MateriaID: 11, SubjectName: "Physical Education", SubjectImage: "physical_education.png",
		Sections: []subjectSection{
			{
				Caption: "Physical Fitness, Health, and Exercise Physiology",
				Themes: []subjectTheme{
					{"physical-qualities", "Basic Physical Qualities", "Endurance, strength, speed, and flexibility — definitions and training methods."},
					{"energy-systems", "Energy Systems", "ATP-PC, glycolytic, and oxidative metabolic pathways during exercise."},
					{"cardio-respiratory", "Cardiorespiratory Response", "Heart rate, VO2 max, oxygen debt, and respiratory adaptations to exercise."},
					{"health-wellness", "Health and Wellness", "Body composition (BMI, body fat %), hypokinetic diseases, and sedentary lifestyle risks."},
				},
			},
			{
				Caption: "Principles and Methods of Sports Training",
				Themes: []subjectTheme{
					{"training-principles", "Training Principles", "Supercompensation, progressive overload, specificity, reversibility, and individuality."},
					{"training-load", "Components of Training Load", "Volume, intensity, density, frequency, and duration of the stimulus."},
					{"assessment-methods", "Assessment Methods", "Beep test, Cooper test, strength tests, and Borg perceived exertion scale."},
					{"prevention-safety", "Prevention and Safety", "Warm-up/cool-down phases, fatigue management, hydration, and injury prevention."},
				},
			},
			{
				Caption: "Motor Skills, Body Expression, and Movement Capabilities",
				Themes: []subjectTheme{
					{"motor-skills", "Motor Skills", "Fundamental (locomotion, manipulation, stability) and specialized sports-specific skills."},
					{"coordinative-capabilities", "Coordinative Capabilities", "Spatial orientation, rhythm, balance, reaction time, and motor differentiation."},
					{"body-expression", "Body Expression and Dance", "Movement qualities, improvisation, choreography, and expressive communication."},
				},
			},
			{
				Caption: "Sports and Games",
				Themes: []subjectTheme{
					{"collective-sports", "Collective Sports", "Tactical principles, team roles, and rules in soccer, basketball, and volleyball."},
					{"individual-sports", "Individual Sports", "Technique and performance principles in athletics, swimming, and gymnastics."},
					{"adapted-physical-education", "Inclusive Physical Education", "Adapting activities for different abilities and special educational needs."},
				},
			},
		},
	},
}

// SubjectPages returns the registered subject page filenames for routing.
func SubjectPages() []string {
	pages := make([]string, 0, len(subjectPageMap))
	for p := range subjectPageMap {
		pages = append(pages, p)
	}
	return pages
}

// HandleSubjectPage ports matematicas.php, biologia.php, ... + _subject_page.php.
// GET renders the theme picker; POST (temas[]) validates CSRF, caps to 5 and
// redirects to the teacher search, mirroring ce_handle_subject_themes().
func (p *Pages) HandleSubjectPage(w http.ResponseWriter, r *http.Request, page string) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	if !p.GuardPage(w, r, s) {
		return
	}
	meta, ok := subjectPageMap[page]
	if !ok {
		http.NotFound(w, r)
		return
	}
	lang := p.ResolveLang(s, r)
	nav, stop := p.MenuData(w, r, s, page, lang)
	if stop {
		return
	}

	// POST: theme selection caps to 5 and goes to the teacher search.
	_ = r.ParseForm()
	if r.Method == http.MethodPost && len(r.PostForm["temas[]"]) > 0 {
		if !p.RequireCSRFOnPost(w, r, s) {
			return
		}
		temas := r.PostForm["temas[]"]
		if len(temas) > 5 {
			temas = temas[:5]
		}
		if page == "tecnologia.php" {
			s.Set("temas_tecnologia", strings.Join(temas, ","))
		}
		qs := url.Values{"materia": {fmt.Sprint(meta.MateriaID)}, "temas": {strings.Join(temas, ",")}}
		redirect(w, r, "profesores.php?"+qs.Encode())
		return
	}

	// Completed topic slugs for the logged-in student.
	completados := map[string]bool{}
	rows, err := p.DB.QueryAll(ctx,
		"SELECT slug FROM progreso_usuario WHERE usuarioid = ? AND slug != '' AND completado = 1", UID(s))
	if err == nil {
		for _, row := range rows {
			if slug := store.Str(row["slug"]); slug != "" {
				completados[slug] = true
			}
		}
	}

	translatedName := i18n.T(lang, "subject.name."+fmt.Sprint(meta.MateriaID), nil)
	if translatedName == "" {
		translatedName = meta.SubjectName
	}

	data := map[string]any{
		"Lang":             lang,
		"NavData":          nav,
		"Self":             page,
		"TranslatedName":   translatedName,
		"SubjectImage":     meta.SubjectImage,
		"Sections":         meta.Sections,
		"Completados":      completados,
		"BreadcrumbSubject": i18n.T(lang, "tecnologia.breadcrumb_subjects", nil),
		"Subtitle":         i18n.T(lang, "tecnologia.subtitle", map[string]string{"max": "5"}),
		"ThemesSelected":   i18n.T(lang, "tecnologia.themes_selected", map[string]string{"max": "5"}),
		"MaxWarning":       i18n.T(lang, "tecnologia.max_warning", map[string]string{"max": "5"}),
		"FindTeacher":      i18n.T(lang, "tecnologia.find_teacher", nil),
		"Pick":             i18n.T(lang, "tecnologia.pick", nil),
		"ThemeCol":         i18n.T(lang, "tecnologia.theme_col", nil),
		"DescriptionCol":   i18n.T(lang, "tecnologia.description_col", nil),
		"DoneBadge":        i18n.T(lang, "tecnologia.done_badge", nil),
		"AlreadyCompleted": i18n.T(lang, "tecnologia.already_completed", nil),
	}
	if err := p.Templates.RenderAuthed(w, "subject", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}
