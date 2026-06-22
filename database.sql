-- ═══════════════════════════════════════════════════════════════════════════════
--  ClassExpress — Full Database Schema
--  Database: ce
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS ce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ce;

-- ─────────────────────────────────────────────────────────────────────────────
--  1. USERS
--     Covers: example16 (profile), example2 (friends), example15 (directory),
--             login.php / verify.php (auth), menu.php (session tracking)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
    usuarioId           INT AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(150)    NOT NULL,
    email               VARCHAR(255)    NOT NULL UNIQUE,
    password            VARCHAR(255)    NOT NULL,
    verificado          TINYINT(1)      NOT NULL DEFAULT 0,
    token_verificacion  VARCHAR(100)    DEFAULT '',
    telefono            VARCHAR(30)     DEFAULT '',
    sitio_web           VARCHAR(255)    DEFAULT '',
    biografia           TEXT,
    privacidad          ENUM('private','visible') DEFAULT 'private',
    rol                 ENUM('student','instructor','director','assistant','researcher','adviser') DEFAULT 'student',
    calificacion        DECIMAL(2,1)    DEFAULT 0.0,
    num_resenas         INT             DEFAULT 0,
    avatar              VARCHAR(255)    DEFAULT '',
    -- session tracking (kept from original menu.php logic)
    ultimoContenido     VARCHAR(10)     DEFAULT '',
    ultimaClase         VARCHAR(10)     DEFAULT '',
    ultimaSala          VARCHAR(100)    DEFAULT '',
    created_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  2. SUBJECTS
--     Covers: example1 (subject cards)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS materias (
    materiaId   INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100)    NOT NULL,
    imagen      VARCHAR(255)    DEFAULT '',   -- e.g. 'mathematics.png'
    pagina      VARCHAR(30)     DEFAULT '',   -- e.g. 'example4.php'
    orden       INT             DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO materias (nombre, imagen, pagina, orden) VALUES
    ('Mathematics',       'mathematics.png',       'example4.php',  1),
    ('Biology',           'biology.png',            'example8.php',  2),
    ('Chemistry',         'chemistry.png',          'example7.php',  3),
    ('Physics',           'physics.png',            'example9.php',  4),
    ('History',           'history.png',            'example5.php',  5),
    ('Geography',         'geography.png',          'example10.php', 6),
    ('Literature',        'literature.png',         'example6.php',  7),
    ('Foreign Languages', 'foreign_languages.png',  'example13.php', 8),
    ('Art and Music',     'art.png',                'example11.php', 9),
    ('Technology',        'technology.png',         'example14.php', 10),
    ('Physical Education','physical_education.png', 'example12.php', 11);

-- ─────────────────────────────────────────────────────────────────────────────
--  3. SYLLABUS CATEGORIES
--     Each subject has several table captions (e.g. "Numbers and Operations")
--     Covers: example4–14 (table captions)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categorias_temario (
    categoriaId INT AUTO_INCREMENT PRIMARY KEY,
    materiaId   INT         NOT NULL,
    caption     VARCHAR(255) NOT NULL,
    orden       INT          DEFAULT 0,
    FOREIGN KEY (materiaId) REFERENCES materias(materiaId) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Mathematics (materiaId = 1)
INSERT INTO categorias_temario (materiaId, caption, orden) VALUES
    (1, 'Numbers and Operations',           1),
    (1, 'Algebra and Functions',            2),
    (1, 'Geometry',                         3),
    (1, 'Probability and Statistics',       4),
    (1, 'Algebraic Structures',             5),
    (1, 'Pre-Calculus',                     6),
    (1, 'Linear Algebra',                   7);

-- Biology (materiaId = 2)
INSERT INTO categorias_temario (materiaId, caption, orden) VALUES
    (2, 'Cellular Biology',                 1),
    (2, 'Ecosystems and Environment',       2),
    (2, 'Genetics and Evolution',           3),
    (2, 'Human Body Systems',               4),
    (2, 'Microbiology',                     5),
    (2, 'Plant Biology',                    6),
    (2, 'Biotechnology',                    7);

-- Chemistry (materiaId = 3)
INSERT INTO categorias_temario (materiaId, caption, orden) VALUES
    (3, 'Atomic Structure',                 1),
    (3, 'Solution Chemistry',               2),
    (3, 'Stoichiometry',                    3),
    (3, 'Organic Chemistry',                4),
    (3, 'Thermodynamics',                   5),
    (3, 'Electrochemistry',                 6),
    (3, 'Nuclear Chemistry',                7),
    (3, 'Biochemistry',                     8);

-- Physics (materiaId = 4)
INSERT INTO categorias_temario (materiaId, caption, orden) VALUES
    (4, 'Mechanics',                        1),
    (4, 'Waves and Optics',                 2),
    (4, 'Electricity and Magnetism',        3),
    (4, 'Earth Sciences',                   4),
    (4, 'Advanced Mechanics',               5),
    (4, 'Electromagnetism',                 6),
    (4, 'Modern Physics',                   7),
    (4, 'Quantum Physics',                  8);

-- History (materiaId = 5)
INSERT INTO categorias_temario (materiaId, caption, orden) VALUES
    (5, 'World and American History',       1),
    (5, 'Civic Education and Human Rights', 2),
    (5, 'Economy and Society',              3),
    (5, 'Evaluated Skills',                 4);

-- Geography (materiaId = 6)
INSERT INTO categorias_temario (materiaId, caption, orden) VALUES
    (6, 'Physical Geography',               1),
    (6, 'Human and Population Geography',   2),
    (6, 'Economic and Political Geography', 3),
    (6, 'Sustainability and Environment',   4);

-- Literature (materiaId = 7)
INSERT INTO categorias_temario (materiaId, caption, orden) VALUES
    (7, 'Locate and Access Information',    1),
    (7, 'Interpret and Integrate',          2),
    (7, 'Evaluate and Reflect',             3),
    (7, 'Text Types',                       4),
    (7, 'Reading Situations',               5);

-- Foreign Languages (materiaId = 8)
INSERT INTO categorias_temario (materiaId, caption, orden) VALUES
    (8, 'Phonetics and Phonology',          1),
    (8, 'Receptive Mechanisms',             2),
    (8, 'Expressive Mechanisms',            3),
    (8, 'Sociolinguistics',                 4);

-- Art and Music (materiaId = 9)
INSERT INTO categorias_temario (materiaId, caption, orden) VALUES
    (9, 'Visual Arts – Language',           1),
    (9, 'Visual Arts – History',            2),
    (9, 'Visual Arts – Movements',          3),
    (9, 'Music – Theory',                   4),
    (9, 'Music – Styles',                   5),
    (9, 'Music – Instruments',              6),
    (9, 'Music – Technology',               7),
    (9, 'Music – Composition',              8);

-- Technology (materiaId = 10)
INSERT INTO categorias_temario (materiaId, caption, orden) VALUES
    (10, 'Hardware and Software',           1),
    (10, 'Networks and Cybersecurity',      2),
    (10, 'Programming and Algorithms',      3),
    (10, 'Social Impact of Technology',     4);

-- Physical Education (materiaId = 11)
INSERT INTO categorias_temario (materiaId, caption, orden) VALUES
    (11, 'Fitness and Physiology',          1),
    (11, 'Training Principles',             2),
    (11, 'Motor Skills',                    3),
    (11, 'Nutrition and Health',            4);

-- ─────────────────────────────────────────────────────────────────────────────
--  4. SYLLABUS THEMES  (individual checkbox rows inside each category table)
--     Covers: example4–14 (every checkbox/label row)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS temas (
    temaId      INT AUTO_INCREMENT PRIMARY KEY,
    categoriaId INT     NOT NULL,
    descripcion TEXT    NOT NULL,
    orden       INT     DEFAULT 0,
    FOREIGN KEY (categoriaId) REFERENCES categorias_temario(categoriaId) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Mathematics → Numbers and Operations (categoriaId = 1)
INSERT INTO temas (categoriaId, descripcion, orden) VALUES
    (1, 'Number sets (natural numbers, integers, and rational numbers)', 1),
    (1, 'Percentages', 2),
    (1, 'Powers and n-th roots', 3),
    (1, 'Direct and inverse proportionality', 4),
    (1, 'Logarithms (properties and change of base)', 5),
    (1, 'Financial mathematics (simple and compound interest)', 6);

-- ── Mathematics → Algebra and Functions (categoriaId = 2)
INSERT INTO temas (categoriaId, descripcion, orden) VALUES
    (2, 'Polynomials and factoring', 1),
    (2, 'Equations and systems of equations', 2),
    (2, 'Inequalities', 3),
    (2, 'Functions: domain, range, and types', 4),
    (2, 'Linear and quadratic functions', 5),
    (2, 'Exponential and logarithmic functions', 6);

-- ── Mathematics → Geometry (categoriaId = 3)
INSERT INTO temas (categoriaId, descripcion, orden) VALUES
    (3, 'Plane figures: perimeter and area', 1),
    (3, 'Solid figures: surface area and volume', 2),
    (3, 'Trigonometry (sine, cosine, tangent)', 3),
    (3, 'Analytical geometry: lines and conics', 4);

-- ── Mathematics → Probability and Statistics (categoriaId = 4)
INSERT INTO temas (categoriaId, descripcion, orden) VALUES
    (4, 'Descriptive statistics: mean, median, mode', 1),
    (4, 'Probability fundamentals', 2),
    (4, 'Combinatorics: permutations and combinations', 3),
    (4, 'Probability distributions', 4);

-- ── Mathematics → Algebraic Structures (categoriaId = 5)
INSERT INTO temas (categoriaId, descripcion, orden) VALUES
    (5, 'Groups, rings, and fields', 1),
    (5, 'Modular arithmetic', 2),
    (5, 'Boolean algebra', 3);

-- ── Mathematics → Pre-Calculus (categoriaId = 6)
INSERT INTO temas (categoriaId, descripcion, orden) VALUES
    (6, 'Limits and continuity', 1),
    (6, 'Derivatives and differentiation rules', 2),
    (6, 'Integrals and the fundamental theorem of calculus', 3),
    (6, 'Applications of derivatives and integrals', 4);

-- ── Mathematics → Linear Algebra (categoriaId = 7)
INSERT INTO temas (categoriaId, descripcion, orden) VALUES
    (7, 'Vectors and vector spaces', 1),
    (7, 'Matrices and matrix operations', 2),
    (7, 'Determinants and their properties', 3),
    (7, 'Inverse matrix: Gauss-Jordan method', 4),
    (7, 'Eigenvalues and eigenvectors', 5);

-- ── Biology → Cellular Biology (categoriaId = 8)
INSERT INTO temas (categoriaId, descripcion, orden) VALUES
    (8, 'Cell structure: prokaryotic vs eukaryotic', 1),
    (8, 'Cell organelles and their functions', 2),
    (8, 'Cell division: mitosis and meiosis', 3),
    (8, 'Cellular respiration and ATP production', 4),
    (8, 'Photosynthesis: light and dark reactions', 5);

-- ── Biology → Ecosystems (categoriaId = 9)
INSERT INTO temas (categoriaId, descripcion, orden) VALUES
    (9, 'Food chains and food webs', 1),
    (9, 'Energy flow in ecosystems', 2),
    (9, 'Biogeochemical cycles (C, N, P)', 3),
    (9, 'Biomes and their characteristics', 4),
    (9, 'Human impact on ecosystems', 5);

-- ── Biology → Genetics and Evolution (categoriaId = 10)
INSERT INTO temas (categoriaId, descripcion, orden) VALUES
    (10, 'Mendelian inheritance', 1),
    (10, 'DNA structure and replication', 2),
    (10, 'Protein synthesis: transcription and translation', 3),
    (10, 'Mutations and genetic disorders', 4),
    (10, 'Natural selection and evolution', 5);

-- ── History → World and American History (categoriaId = 18 — adjust if offsets differ)
-- (Keeping it simple; offsets continue automatically via AUTO_INCREMENT)
INSERT INTO temas (categoriaId, descripcion, orden) VALUES
    (18, 'Ancient civilizations and classical empires', 1),
    (18, 'Medieval period and the rise of nation-states', 2),
    (18, 'Renaissance, Reformation, and Scientific Revolution', 3),
    (18, 'Colonial America and independence movements', 4),
    (18, 'Industrial Revolution and social change', 5),
    (18, '20th century conflicts: World War I and II', 6);

-- ── History → Civic Education and Human Rights (categoriaId = 19)
INSERT INTO temas (categoriaId, descripcion, orden) VALUES
    (19, 'Universal Declaration of Human Rights', 1),
    (19, 'Democratic systems and institutions', 2),
    (19, 'Citizenship rights and responsibilities', 3),
    (19, 'International organizations (UN, UNESCO, etc.)', 4);

-- ─────────────────────────────────────────────────────────────────────────────
--  5. USER PROGRESS  (which theme checkboxes each user has completed)
--     Covers: example4–14 (checkbox state), script.js (actualizarMétricaProgreso)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS progreso_usuario (
    progresoId      INT AUTO_INCREMENT PRIMARY KEY,
    usuarioId       INT         NOT NULL,
    temaId          INT         NOT NULL,
    completado      TINYINT(1)  NOT NULL DEFAULT 0,
    fecha_completado TIMESTAMP  NULL,
    UNIQUE KEY uq_progreso (usuarioId, temaId),
    FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE,
    FOREIGN KEY (temaId)    REFERENCES temas(temaId)       ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  6. VIRTUAL CLASSROOMS
--     Covers: example18 (classroom title, course, controls)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS salas (
    salaId          INT AUTO_INCREMENT PRIMARY KEY,
    titulo          VARCHAR(255)    NOT NULL,          -- 'Class 04: Advanced Web Development'
    curso           VARCHAR(255)    DEFAULT '',        -- 'Front-End Master'
    instructorId    INT,
    activa          TINYINT(1)      DEFAULT 1,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructorId) REFERENCES usuarios(usuarioId) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  7. CLASSROOM PARTICIPANTS
--     Covers: example18 (mic, camera, raise-hand controls)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS participantes_sala (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    salaId          INT         NOT NULL,
    usuarioId       INT         NOT NULL,
    camara_activa   TINYINT(1)  DEFAULT 0,
    microfono_activo TINYINT(1) DEFAULT 0,
    mano_levantada  TINYINT(1)  DEFAULT 0,
    joined_at       TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sala_usuario (salaId, usuarioId),
    FOREIGN KEY (salaId)    REFERENCES salas(salaId)       ON DELETE CASCADE,
    FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  8. LIVE CHAT MESSAGES
--     Covers: example18 (Charles_Gomez, Mary_Lopez, Teacher_AI, Moderator chat)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mensajes_chat (
    mensajeId   INT AUTO_INCREMENT PRIMARY KEY,
    salaId      INT         NOT NULL,
    usuarioId   INT,
    alias       VARCHAR(100) DEFAULT '',              -- display name fallback
    mensaje     TEXT        NOT NULL,
    enviado_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (salaId)    REFERENCES salas(salaId)       ON DELETE CASCADE,
    FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  9. FRIENDS / FOLLOWS
--     Covers: example2 (Follow / Unfollow buttons, friend list)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS relaciones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    seguidorId  INT NOT NULL,
    seguidoId   INT NOT NULL,
    estado      ENUM('pending','following') DEFAULT 'following',
    created_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_relacion (seguidorId, seguidoId),
    FOREIGN KEY (seguidorId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE,
    FOREIGN KEY (seguidoId)  REFERENCES usuarios(usuarioId) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  10. PRODUCTS (SHOP)
--      Covers: example17 cart ($12, $8, $5 items)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS productos (
    productoId  INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(255)    NOT NULL,
    descripcion TEXT,
    precio      DECIMAL(8,2)    NOT NULL DEFAULT 0.00,
    imagen      VARCHAR(255)    DEFAULT ''
) ENGINE=InnoDB;

INSERT INTO productos (nombre, precio) VALUES
    ('Course Access – Basic',    12.00),
    ('Course Access – Standard',  8.00),
    ('Course Access – Add-on',    5.00);

-- ─────────────────────────────────────────────────────────────────────────────
--  11. ORDERS
--      Covers: example17 (billing form, payment method)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pedidos (
    pedidoId        INT AUTO_INCREMENT PRIMARY KEY,
    usuarioId       INT,
    nombre_factura  VARCHAR(150)    DEFAULT '',
    apellido_factura VARCHAR(150)   DEFAULT '',
    email_factura   VARCHAR(255)    DEFAULT '',
    direccion       VARCHAR(255)    DEFAULT '',
    pais            VARCHAR(100)    DEFAULT '',
    estado_region   VARCHAR(100)    DEFAULT '',
    codigo_postal   VARCHAR(20)     DEFAULT '',
    metodo_pago     ENUM('credit','debit','paypal') DEFAULT 'credit',
    codigo_promo    VARCHAR(50)     DEFAULT '',
    total           DECIMAL(10,2)   DEFAULT 0.00,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detalle_pedido (
    detalleId       INT AUTO_INCREMENT PRIMARY KEY,
    pedidoId        INT         NOT NULL,
    productoId      INT,
    nombre_producto VARCHAR(255) DEFAULT '',
    precio_unitario DECIMAL(8,2) NOT NULL,
    cantidad        INT          DEFAULT 1,
    FOREIGN KEY (pedidoId)   REFERENCES pedidos(pedidoId)   ON DELETE CASCADE,
    FOREIGN KEY (productoId) REFERENCES productos(productoId) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  12. SCHEDULED CLASSES (class creation form)
--      Covers: example19 (price range, student count)
--              example20 (adds title field)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS clases_programadas (
    claseId         INT AUTO_INCREMENT PRIMARY KEY,
    salaId          INT,
    instructorId    INT,
    titulo          VARCHAR(255)    DEFAULT '',
    precio_min      DECIMAL(8,2)    DEFAULT 0.00,
    precio_max      DECIMAL(8,2)    DEFAULT 0.00,
    alumnos_min     INT             DEFAULT 0,
    alumnos_max     INT             DEFAULT 0,
    solo_yo         TINYINT(1)      DEFAULT 0,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (salaId)        REFERENCES salas(salaId)       ON DELETE SET NULL,
    FOREIGN KEY (instructorId)  REFERENCES usuarios(usuarioId) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  13. NOTIFICATIONS
--      Covers: example3 (notification cards with Submit / Ignore actions)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notificaciones (
    notificacionId  INT AUTO_INCREMENT PRIMARY KEY,
    usuarioId       INT         NOT NULL,
    remitenteId     INT,                               -- who triggered it
    mensaje         TEXT        NOT NULL,
    tipo            ENUM('follow','class','system','message') DEFAULT 'system',
    leida           TINYINT(1)  DEFAULT 0,
    created_at      TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuarioId)    REFERENCES usuarios(usuarioId) ON DELETE CASCADE,
    FOREIGN KEY (remitenteId)  REFERENCES usuarios(usuarioId) ON DELETE SET NULL
) ENGINE=InnoDB;
