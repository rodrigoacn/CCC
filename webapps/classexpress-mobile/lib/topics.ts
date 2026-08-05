export interface Topic {
  slug: string;
  title: string;
  desc: string;
}

export interface SubjectTopics {
  id: number;
  name: string;
  image: string;
  sections: Record<string, Topic[]>;
}

export const TOPICS_DATA: Record<number, SubjectTopics> = {
  1: {
    id: 1, name: 'Mathematics', image: 'mathematics.png',
    sections: {
      'Numbers and Operations': [
        { slug: 'number-sets',        title: 'Number Sets',        desc: 'Natural, integer, rational, and real numbers — properties and operations.' },
        { slug: 'percentages',        title: 'Percentages',        desc: 'Percentage calculation, increase/decrease, and everyday applications.' },
        { slug: 'powers-roots',       title: 'Powers and Roots',   desc: 'Integer and fractional exponents, n-th roots, and simplification rules.' },
        { slug: 'proportionality',     title: 'Proportionality',    desc: 'Direct and inverse proportionality, rule of three, and ratio applications.' },
        { slug: 'logarithms',         title: 'Logarithms',         desc: 'Definition, properties, change of base, and real-world applications.' },
        { slug: 'financial-math',     title: 'Financial Mathematics', desc: 'Simple and compound interest, annuities, and loan calculations.' },
      ],
      'Algebra and Functions': [
        { slug: 'algebraic-expressions', title: 'Algebraic Expressions',       desc: 'Polynomials, factoring, and operations with algebraic fractions.' },
        { slug: 'linear-equations',      title: 'Linear Equations & Systems',  desc: 'Solving first-degree equations and 2×2/3×3 linear systems.' },
        { slug: 'inequalities',          title: 'Inequalities',                desc: 'First-degree inequalities and graphical representation on the number line.' },
        { slug: 'functions',             title: 'Functions',                   desc: 'Domain, range, and graphing of linear, quadratic, and piecewise functions.' },
        { slug: 'exp-log-functions',     title: 'Exponential & Log Functions', desc: 'Graphs, transformations, and real-world modeling.' },
        { slug: 'trigonometry',          title: 'Trigonometry',                desc: 'Trigonometric ratios, the unit circle, and basic identities.' },
      ],
      'Geometry': [
        { slug: 'congruence-similarity', title: 'Congruence and Similarity',     desc: 'Triangle congruence criteria, similarity ratios, and scale.' },
        { slug: 'thales-pythagoras',     title: 'Thales & Pythagorean Theorem',  desc: 'Applications in right triangles and coordinate geometry.' },
        { slug: 'areas-volumes',         title: 'Areas and Volumes',             desc: 'Perimeters, areas of plane figures, and volumes of 3D solids.' },
        { slug: 'transformations',       title: 'Geometric Transformations',     desc: 'Translation, rotation, reflection, and glide reflection.' },
        { slug: 'vectors',               title: 'Vectors in a Plane',            desc: 'Vector addition, scalar multiplication, and dot product.' },
        { slug: 'analytic-geometry',     title: 'Analytic Geometry',             desc: 'Lines, circles, and conics in the Cartesian plane.' },
      ],
      'Probability and Statistics': [
        { slug: 'data-analysis',   title: 'Data Analysis',       desc: 'Charts, frequency distributions, and measures of central tendency.' },
        { slug: 'probability',     title: 'Probability',         desc: 'Sample spaces, events, addition/multiplication rules, and conditional probability.' },
        { slug: 'dispersion',      title: 'Dispersion Measures', desc: 'Variance, standard deviation, and interquartile range.' },
        { slug: 'combinatorics',   title: 'Combinatorics',       desc: 'Permutations, combinations, and the binomial theorem.' },
      ],
    },
  },
  2: {
    id: 2, name: 'Biology', image: 'biology.png',
    sections: {
      'Cellular Organization, Structure, and Activity': [
        { slug: 'cell-types',       title: 'Prokaryotic and Eukaryotic Cells', desc: 'Structural and functional differences; organelle structure and function.' },
        { slug: 'cell-membrane',    title: 'Cell Membrane',                    desc: 'Fluid mosaic model; passive transport (diffusion, osmosis) and active transport.' },
        { slug: 'bioenergetics',    title: 'Bioenergetic Processes',           desc: 'Photosynthesis (light/dark phases) and cellular respiration overview.' },
        { slug: 'macromolecules',   title: 'Organic Macromolecules',           desc: 'Structure and function of proteins, carbohydrates, lipids, and nucleic acids.' },
      ],
      'Ecosystem Processes and Ecology': [
        { slug: 'energy-flow',          title: 'Energy and Matter Flow',            desc: 'Food webs, trophic levels, producers, consumers, and decomposers.' },
        { slug: 'biogeochemical-cycles', title: 'Biogeochemical Cycles',            desc: 'Water, carbon, and nitrogen cycles and their global importance.' },
        { slug: 'population-dynamics',  title: 'Population and Community Dynamics', desc: 'Growth curves, density, birth/death rates, and interspecific interactions.' },
        { slug: 'environmental-impact', title: 'Environmental Impact',              desc: 'Global warming, biodiversity loss, pollution, and invasive species.' },
      ],
      'Inheritance, Genetics, and Evolution': [
        { slug: 'cell-cycle-mitosis', title: 'Cell Cycle and Mitosis',    desc: 'G1/S/G2 phases, mitosis stages, and relation to cancer.' },
        { slug: 'meiosis',            title: 'Meiosis and Gametogenesis', desc: 'Meiosis stages, crossing-over, independent assortment, and gamete formation.' },
        { slug: 'mendelian-genetics', title: 'Mendelian Genetics',        desc: 'Phenotype/genotype, dominant/recessive alleles, monohybrid and dihybrid crosses.' },
        { slug: 'evolution',          title: 'Evolutionary Theories',     desc: 'Evidence for evolution (fossil, anatomical, molecular) and natural selection.' },
      ],
      'Human Body Systems, Health, and Reproduction': [
        { slug: 'nervous-system',     title: 'Nervous System',               desc: 'Central/peripheral nervous system, neurons, synapses, and reflex arcs.' },
        { slug: 'endocrine-system',   title: 'Endocrine System',             desc: 'Hormones, glands (thyroid, adrenal, pituitary), and feedback regulation.' },
        { slug: 'circulatory-immune', title: 'Circulatory and Immune System', desc: 'Heart, blood vessels, blood types, and immune response.' },
        { slug: 'reproductive-system',title: 'Reproductive System',          desc: 'Male and female anatomy, gametogenesis, fertilization, and development.' },
      ],
    },
  },
  3: {
    id: 3, name: 'Chemistry', image: 'chemistry.png',
    sections: {
      'Atomic Structure and Properties of Matter': [
        { slug: 'classification-matter',  title: 'Classification of Matter',  desc: 'Pure substances vs. mixtures; separation methods (filtration, distillation).' },
        { slug: 'atomic-theory',          title: 'Atomic Theory',             desc: 'Evolution of atomic models and fundamental subatomic particles.' },
        { slug: 'electron-configuration', title: 'Electron Configuration',    desc: 'Build-up principles, orbital diagrams, valence electrons, and quantum numbers.' },
        { slug: 'periodic-table',         title: 'Periodic Table',            desc: 'Groups, periods, and periodic properties (radius, ionization energy, electronegativity).' },
        { slug: 'chemical-bonding',       title: 'Chemical Bonding',          desc: 'Ionic, covalent, and metallic bonds; intermolecular forces and polarity.' },
      ],
      'Solution Chemistry and Stoichiometry': [
        { slug: 'aqueous-solutions', title: 'Aqueous Solutions',  desc: 'Solubility, concentration units (molarity, molality), and dilution effects.' },
        { slug: 'stoichiometry',     title: 'Stoichiometry',      desc: 'Mole concept, molar mass, balancing equations, and limiting reagent.' },
        { slug: 'acid-base',         title: 'Acid-Base Chemistry', desc: 'pH, strong/weak acids and bases, neutralization, and buffer solutions.' },
        { slug: 'redox-reactions',   title: 'Oxidation-Reduction', desc: 'Oxidation states, balancing redox equations, and electrochemistry basics.' },
      ],
      'Organic Chemistry': [
        { slug: 'hydrocarbons',          title: 'Hydrocarbons',             desc: 'Alkanes, alkenes, alkynes, and aromatic compounds; IUPAC nomenclature.' },
        { slug: 'functional-groups',     title: 'Functional Groups',        desc: 'Alcohols, aldehydes, ketones, carboxylic acids, and esters.' },
        { slug: 'polymers-biomolecules', title: 'Polymers and Biomolecules', desc: 'Natural and synthetic polymers; connection to biological macromolecules.' },
      ],
      'Thermochemistry and Reaction Kinetics': [
        { slug: 'thermochemistry',      title: 'Thermochemistry',      desc: 'Enthalpy, endothermic/exothermic reactions, and Hess\'s law.' },
        { slug: 'reaction-kinetics',    title: 'Reaction Kinetics',    desc: 'Reaction rates, activation energy, catalysts, and collision theory.' },
        { slug: 'chemical-equilibrium', title: 'Chemical Equilibrium', desc: 'Le Chatelier\'s principle and the equilibrium constant (Keq).' },
      ],
    },
  },
  4: {
    id: 4, name: 'Physics', image: 'physics.png',
    sections: {
      'Mechanics': [
        { slug: 'kinematics',      title: 'Kinematics',          desc: 'Motion in a straight line: position, displacement, velocity, acceleration, MRU and MRUA.' },
        { slug: 'dynamics',        title: 'Dynamics and Forces', desc: 'Newton\'s three laws, weight, friction, tension, Hooke\'s law, and static equilibrium.' },
        { slug: 'linear-momentum', title: 'Linear Momentum',     desc: 'Momentum, impulse, and conservation of linear momentum in collisions.' },
        { slug: 'energy-work',     title: 'Energy and Work',     desc: 'Kinetic and potential energy, conservation of mechanical energy, and power.' },
      ],
      'Waves and Optics': [
        { slug: 'wave-properties', title: 'Properties of Waves',  desc: 'Amplitude, frequency, period, wavelength, speed, and wave classification.' },
        { slug: 'wave-phenomena',  title: 'Wave Phenomena',       desc: 'Reflection, refraction (Snell\'s law), diffraction, and interference.' },
        { slug: 'sound',           title: 'Sound',                desc: 'Production and propagation, pitch/intensity/timbre, Doppler effect, and resonance.' },
        { slug: 'light-optics',    title: 'Light and Optics',     desc: 'Electromagnetic spectrum, mirrors, converging/diverging lenses, and eye defects.' },
      ],
      'Electricity and Magnetism': [
        { slug: 'electrostatics',    title: 'Electrostatics',    desc: 'Electric charge, charging methods (friction, contact, induction), and Coulomb\'s law.' },
        { slug: 'electric-circuits', title: 'Electric Circuits', desc: 'Ohm\'s law, resistance, voltage, Joule effect, and series/parallel circuits.' },
        { slug: 'magnetism',         title: 'Magnetism',         desc: 'Magnetic fields, Oersted\'s discovery, and Faraday\'s electromagnetic induction.' },
      ],
      'Earth Sciences and the Universe': [
        { slug: 'earth-structure',    title: 'Earth Structure and Dynamics', desc: 'Internal layers, plate tectonics, earthquakes (P, S, R, L waves), and volcanism.' },
        { slug: 'universe-astronomy', title: 'The Universe and Astronomy',   desc: 'Solar system, stellar evolution, Big Bang, and cosmological scales.' },
        { slug: 'atmosphere-climate', title: 'Atmosphere and Climate',       desc: 'Layers of the atmosphere, weather vs. climate, and the greenhouse effect.' },
      ],
    },
  },
  5: {
    id: 5, name: 'History', image: 'history.png',
    sections: {
      'World and American History': [
        { slug: '19th-century',    title: 'The 19th Century',                    desc: 'Liberalism, the Industrial Revolution, idea of progress, and geopolitical transformations.' },
        { slug: 'first-half-20th', title: 'The First Half of the 20th Century',  desc: 'WWI, the Great Depression, totalitarian regimes (Fascism, Nazism, Stalinism), and WWII.' },
        { slug: 'second-half-20th',title: 'The Second Half of the 20th Century', desc: 'Cold War, Latin American dictatorships, and accelerated globalization under neoliberalism.' },
      ],
      'Civic Education and Human Rights': [
        { slug: 'democratic-state', title: 'The Democratic State of Law', desc: 'Democracy principles, constitutions, separation of powers, and citizen participation.' },
        { slug: 'human-rights',     title: 'Human Rights',                desc: 'Universality of rights, international responsibilities, diversity, and non-discrimination.' },
      ],
      'Economy and Society': [
        { slug: 'economic-problem',        title: 'The Economic Problem',     desc: 'Scarcity, resource allocation, and the fundamental economic questions.' },
        { slug: 'market-mechanics',        title: 'Market Mechanics',         desc: 'Supply/demand, economic agents, inflation, unemployment, and fiscal policy.' },
        { slug: 'responsible-consumption', title: 'Responsible Consumption',  desc: 'Financial markets, consumer rights, debt management, and sustainable development.' },
      ],
      'Evaluated Historical Skills': [
        { slug: 'temporal-spatial', title: 'Temporal and Spatial Thinking',   desc: 'Locating and contextualizing historical processes within specific eras and territories.' },
        { slug: 'source-analysis',  title: 'Analysis of Information Sources', desc: 'Extracting conclusions from texts, graphs, maps, and historical cartoons.' },
        { slug: 'critical-thinking',title: 'Critical Thinking',               desc: 'Multiple causation, identifying historical continuities, and change-over-time analysis.' },
      ],
    },
  },
  6: {
    id: 6, name: 'Geography', image: 'geography.png',
    sections: {
      'Physical Geography and Natural Environment': [
        { slug: 'geomorphology',   title: 'Geomorphology',    desc: 'Landforms: plains, mountains, plateaus, valleys, and coastal features.' },
        { slug: 'climate-systems', title: 'Climate Systems',  desc: 'Climate classification, factors determining climate, and world biomes.' },
        { slug: 'hydrology',       title: 'Hydrology',        desc: 'Water cycle, drainage basins, river systems, oceans, and groundwater.' },
        { slug: 'biogeography',    title: 'Biogeography',     desc: 'Distribution of ecosystems (rainforests, deserts, tundra) based on climate and soil.' },
      ],
      'Human and Population Geography': [
        { slug: 'demographics',    title: 'Demographics',     desc: 'Population density, birth/death rates, life expectancy, and population pyramids.' },
        { slug: 'migration',       title: 'Migration',        desc: 'Push/pull factors, internal vs. international migration, and socioeconomic impacts.' },
        { slug: 'urban-geography', title: 'Urban Geography',  desc: 'Urbanization, city structures, megacities, and urban challenges.' },
        { slug: 'rural-geography', title: 'Rural Geography',  desc: 'Settlement patterns, agricultural systems, and rural space transformation.' },
      ],
      'Economic and Political Geography': [
        { slug: 'economic-sectors',    title: 'Economic Sectors',  desc: 'Primary, secondary, tertiary, and quaternary economic activities.' },
        { slug: 'globalization',       title: 'Globalization',     desc: 'Global trade networks, transnational corporations, and economic disparities.' },
        { slug: 'geopolitics',         title: 'Geopolitics',       desc: 'State, nation, territory, borders, conflicts, and supranational organizations.' },
        { slug: 'natural-resources',   title: 'Natural Resources', desc: 'Renewable vs. non-renewable resources and geopolitical control of strategic assets.' },
      ],
      'Environmental Geography and Sustainability': [
        { slug: 'human-impact',           title: 'Human Impact',             desc: 'Deforestation, soil degradation, water pollution, and urban sprawl.' },
        { slug: 'climate-change-geo',     title: 'Climate Change',           desc: 'Global warming, greenhouse effect, and regional consequences.' },
        { slug: 'sustainable-development', title: 'Sustainable Development',  desc: 'Development goals, circular economy, and international environmental agreements.' },
      ],
    },
  },
  7: {
    id: 7, name: 'Language & Literature', image: 'literature.png',
    sections: {
      'Evaluated Reading Skills': [
        { slug: 'locate-info',    title: 'Locate Information', desc: 'Find specific, explicit information within a text accurately and quickly.' },
        { slug: 'interpret-text', title: 'Interpret Texts',    desc: 'Connect ideas, infer implicit information, and determine global meaning.' },
        { slug: 'evaluate-text',  title: 'Evaluate Texts',     desc: 'Judge content, form, argument validity, and the author\'s intent.' },
      ],
      'Text Types': [
        { slug: 'literary-texts',     title: 'Literary Texts',     desc: 'Narrative texts: short stories, novel fragments, myths, and legends.' },
        { slug: 'non-literary-texts', title: 'Non-Literary Texts', desc: 'Informative (news, reports) and argumentative (editorials, opinion columns) texts.' },
      ],
      'Reading Situations': [
        { slug: 'personal-reading',    title: 'Personal Reading',    desc: 'Leisure, entertainment, and individual interest texts.' },
        { slug: 'public-reading',      title: 'Public Reading',      desc: 'Community interest, civic, and social texts.' },
        { slug: 'educational-reading', title: 'Educational Reading', desc: 'Academic, scientific, and textbook materials.' },
      ],
      'Language and Literary Devices': [
        { slug: 'narrative-elements',  title: 'Narrative Elements',  desc: 'Narrator, character types, setting, plot structure, and narrative point of view.' },
        { slug: 'figurative-language', title: 'Figurative Language', desc: 'Metaphor, simile, personification, irony, and hyperbole.' },
        { slug: 'text-structure',      title: 'Text Structure',       desc: 'Introduction, development, conclusion; cohesion and coherence devices.' },
        { slug: 'literary-movements',  title: 'Literary Movements',   desc: 'Romanticism, Realism, Modernism, and the Latin American Boom.' },
      ],
    },
  },
  8: {
    id: 8, name: 'Foreign Languages', image: 'foreign_languages.png',
    sections: {
      'Theoretical Linguistics and the Language System': [
        { slug: 'phonetics',    title: 'Phonetics and Phonology',   desc: 'Places and manners of articulation; stress, rhythm, and intonation patterns.' },
        { slug: 'morphosyntax', title: 'Morphosyntax — Grammar',    desc: 'Parts of speech, sentence structure (SVO), tenses, and moods.' },
        { slug: 'vocabulary',   title: 'Vocabulary and Lexicology', desc: 'Word families, collocations, false cognates, and register.' },
      ],
      'Comprehension and Receptive Mechanisms': [
        { slug: 'reading-techniques',     title: 'Reading Techniques',        desc: 'Skimming (global idea) and scanning (locating specific data).' },
        { slug: 'inference',              title: 'Inference',                 desc: 'Deducing unfamiliar word meanings using contextual clues.' },
        { slug: 'listening-comprehension', title: 'Listening Comprehension',   desc: 'Identifying registers, dialects, and auditory discrimination of minimal pairs.' },
      ],
      'Production and Expressive Mechanisms': [
        { slug: 'written-expression', title: 'Written Expression', desc: 'Cohesion/coherence, logical connectors, and text typologies (emails, essays, reviews).' },
        { slug: 'oral-expression',    title: 'Oral Expression',    desc: 'Fluency vs. accuracy; compensation strategies and conversational interaction.' },
      ],
      'Sociolinguistics and Culture': [
        { slug: 'sociolinguistics',        title: 'Sociolinguistics',        desc: 'Language variation: dialects, sociolects, registers, and code-switching.' },
        { slug: 'intercultural-competence', title: 'Intercultural Competence', desc: 'Cultural values behind language; non-verbal communication norms across cultures.' },
        { slug: 'pragmatics',              title: 'Pragmatics',              desc: 'Speech acts, politeness strategies, and conversational implicature.' },
      ],
    },
  },
  9: {
    id: 9, name: 'Art and Music', image: 'art.png',
    sections: {
      'Assessed Skills': [
        { slug: 'analyze-art',  title: 'Analyze',   desc: 'Examine elements of visual language and contexts across diverse artistic expressions.' },
        { slug: 'interpret-art',title: 'Interpret', desc: 'Assign grounded meanings to artworks based on their context or materiality.' },
        { slug: 'evaluate-art', title: 'Evaluate',  desc: 'Form critical judgments on the impact, aesthetics, and purpose of a cultural manifestation.' },
      ],
      'Elements of Visual Language and Technical Procedures': [
        { slug: 'line',              title: 'Line',                 desc: 'Expressive role, contour, directionality, and types of lines in composition.' },
        { slug: 'shape-volume',      title: 'Shape and Volume',     desc: 'Figurative, abstract, geometric, and organic forms.' },
        { slug: 'color-theory',      title: 'Color Theory',         desc: 'Saturation, contrast, color harmonies, and color psychology.' },
        { slug: 'space-perspective', title: 'Space and Perspective', desc: 'Depth, overlapping, framing, and the vanishing point in perspective drawing.' },
        { slug: 'texture-light',     title: 'Texture and Lighting', desc: 'Visual vs. tactile texture; direct/diffused light and chiaroscuro.' },
        { slug: 'mediums-techniques',title: 'Mediums and Techniques', desc: 'Printmaking, sculpture, painting, photography, and digital media.' },
      ],
      'Art History and Artistic Movements': [
        { slug: 'classical-modern',   title: 'Classical and Modern Western Art', desc: 'Renaissance, Baroque, Impressionism, and 20th-century avant-gardes.' },
        { slug: 'visual-ruptures',    title: 'Visual Ruptures',                  desc: 'Cubism, Surrealism, and Abstract Expressionism.' },
        { slug: 'recent-movements',   title: 'Recent Movements',                 desc: 'Pop Art, Minimalism, Conceptual Art, and Performance Art.' },
        { slug: 'latin-american-art', title: 'Latin American Art',               desc: 'Muralism, magical realism in visual arts, and contemporary LATAM artists.' },
      ],
      'Music': [
        { slug: 'music-elements', title: 'Elements of Music', desc: 'Rhythm, melody, harmony, dynamics, and timbre — how music is structured.' },
        { slug: 'music-history',  title: 'Music History',     desc: 'Western classical periods through jazz, rock, and contemporary genres.' },
      ],
    },
  },
  10: {
    id: 10, name: 'Technology', image: 'technology.png',
    sections: {
      'Digital Literacy, Hardware and Software Systems': [
        { slug: 'hardware-architecture', title: 'Hardware Architecture',              desc: 'Core components: CPU, RAM, storage units (SSD/HDD), motherboard, and input/output peripherals.' },
        { slug: 'software-os',           title: 'Software and Operating Systems',     desc: 'System software versus application software; how the OS manages resources.' },
        { slug: 'file-management',       title: 'File Management',                    desc: 'Directory structures, file extensions, and cloud storage systems.' },
        { slug: 'data-representation',   title: 'Data Representation',                desc: 'Binary code, bits, bytes, and data compression basics.' },
      ],
      'Networks, Internet and Cybersecurity': [
        { slug: 'network-fundamentals',     title: 'Network Fundamentals',       desc: 'LAN/WAN networks, IP addresses, routers, and the Client-Server model.' },
        { slug: 'internet-protocols',       title: 'Internet Protocols',         desc: 'HTTP, HTTPS, FTP, and DNS — how data travels across the web.' },
        { slug: 'information-security',     title: 'Information Security',       desc: 'Threats: Malware, phishing, ransomware, and social engineering.' },
        { slug: 'cybersecurity-prevention', title: 'Cybersecurity Prevention',   desc: 'Firewalls, multi-factor authentication, encryption, and strong password policies.' },
        { slug: 'digital-footprint',        title: 'Digital Footprint & Privacy', desc: 'Managing personal data online, cookies, terms of service, and digital identity.' },
      ],
      'Algorithmic Thinking, Programming and Automation': [
        { slug: 'algorithms-logic',      title: 'Algorithms and Logic',          desc: 'Flowcharts, pseudocode, and decomposition of complex problems.' },
        { slug: 'programming-variables', title: 'Programming Core — Variables',  desc: 'Variables, constants, and data types (strings, integers, booleans).' },
        { slug: 'programming-control',   title: 'Programming Core — Control',    desc: 'Conditionals (If-Else) and loops (For, While).' },
        { slug: 'emerging-tech',         title: 'Emerging Technologies',         desc: 'Intro to AI / Machine Learning, automation, and the Internet of Things (IoT).' },
      ],
      'Technological Design, Projects and Social Impact': [
        { slug: 'design-process',  title: 'The Design Process',            desc: 'Problem identification, wireframing, iterative testing, and user-centred design (UX/UI).' },
        { slug: 'tech-ethics',     title: 'Technological Ethics',          desc: 'E-waste, environmental impact of data centres, digital divide, and intellectual property.' },
        { slug: 'digital-divide',  title: 'Accessibility & Open Source',   desc: 'Digital accessibility standards, Creative Commons, and Open Source licensing.' },
      ],
    },
  },
  11: {
    id: 11, name: 'Physical Education', image: 'physical_education.png',
    sections: {
      'Physical Fitness, Health, and Exercise Physiology': [
        { slug: 'physical-qualities',  title: 'Basic Physical Qualities',    desc: 'Endurance, strength, speed, and flexibility — definitions and training methods.' },
        { slug: 'energy-systems',      title: 'Energy Systems',              desc: 'ATP-PC, glycolytic, and oxidative metabolic pathways during exercise.' },
        { slug: 'cardio-respiratory',  title: 'Cardiorespiratory Response',  desc: 'Heart rate, VO2 max, oxygen debt, and respiratory adaptations to exercise.' },
        { slug: 'health-wellness',     title: 'Health and Wellness',         desc: 'Body composition (BMI, body fat %), hypokinetic diseases, and sedentary lifestyle risks.' },
      ],
      'Principles and Methods of Sports Training': [
        { slug: 'training-principles', title: 'Training Principles',         desc: 'Supercompensation, progressive overload, specificity, reversibility, and individuality.' },
        { slug: 'training-load',       title: 'Components of Training Load', desc: 'Volume, intensity, density, frequency, and duration of the stimulus.' },
        { slug: 'assessment-methods',  title: 'Assessment Methods',          desc: 'Beep test, Cooper test, strength tests, and Borg perceived exertion scale.' },
        { slug: 'prevention-safety',   title: 'Prevention and Safety',       desc: 'Warm-up/cool-down phases, fatigue management, hydration, and injury prevention.' },
      ],
      'Motor Skills, Body Expression, and Movement Capabilities': [
        { slug: 'motor-skills',            title: 'Motor Skills',                desc: 'Fundamental (locomotion, manipulation, stability) and specialized sports-specific skills.' },
        { slug: 'coordinative-capabilities',title: 'Coordinative Capabilities',  desc: 'Spatial orientation, rhythm, balance, reaction time, and motor differentiation.' },
        { slug: 'body-expression',         title: 'Body Expression and Dance',   desc: 'Movement qualities, improvisation, choreography, and expressive communication.' },
      ],
      'Sports and Games': [
        { slug: 'collective-sports',          title: 'Collective Sports',          desc: 'Tactical principles, team roles, and rules in soccer, basketball, and volleyball.' },
        { slug: 'individual-sports',          title: 'Individual Sports',          desc: 'Technique and performance principles in athletics, swimming, and gymnastics.' },
        { slug: 'adapted-physical-education', title: 'Inclusive Physical Education', desc: 'Adapting activities for different abilities and special educational needs.' },
      ],
    },
  },
};

export function getSubjectTopics(id: number): SubjectTopics | undefined {
  return TOPICS_DATA[id];
}

export function getAllTopicSlugs(id: number): string[] {
  const data = TOPICS_DATA[id];
  if (!data) return [];
  return Object.values(data.sections).flat().map(t => t.slug);
}
