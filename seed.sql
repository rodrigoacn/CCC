-- ═══════════════════════════════════════════════════════════════════════════════
--  ClassExpress — Demo Seed Data
--  Run AFTER database.sql
--  All user passwords = demo1234
-- ═══════════════════════════════════════════════════════════════════════════════
USE ce;

-- ─────────────────────────────────────────────────────────────────────────────
--  1. USERS  (6 demo accounts — password: demo1234)
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO usuarios
    (usuarioId, nombre, email, password, verificado, telefono, sitio_web, biografia, privacidad, rol, calificacion, num_resenas, avatar, ultimoContenido, ultimaClase, ultimaSala)
VALUES
-- Director / Instructor
(1,
 'Rodrigo Conejeros',
 'rodrigo@classexpress.app',
 '$2y$10$xO0OYVm5BXkNQl/pUJmqbOgFcRN5sOEZqQpqtxU8VZ5tblZlmiBDe',
 1, '+1 555 010 0001', 'https://classexpress.app',
 'Founder of ClassExpress and lead instructor. Passionate about making education accessible.',
 'visible', 'director', 4.9, 312,
 'rostro_masculino_1.png', '4', '18', 'math-room'),

-- Instructor
(2,
 'Alexander V.',
 'alexander@classexpress.app',
 '$2y$10$sTR39Z5Np2D8HHwY07w6B.hguF3TKnMrYGgvnvw.RscGak6YjxuPK',
 1, '+1 555 010 0002', '',
 'Front-end and Mathematics instructor with 8 years of experience in online education.',
 'visible', 'instructor', 4.8, 123,
 'rostro_masculino_2.png', '4', '18', 'math-room'),

-- Assistant
(3,
 'Mary Lopez',
 'mary@classexpress.app',
 '$2y$10$M78XQhoi8nlUKpe8Y7glv.bHoer/NA8Hx6tTwxO7Nd24wKlrSpC7a',
 1, '+1 555 010 0003', '',
 'Teaching assistant specialising in Biology and Chemistry. Here to help students thrive.',
 'visible', 'assistant', 4.7, 89,
 'rostro_femenino_1.png', '8', '18', 'bio-room'),

-- Student A — active in Math
(4,
 'Charles Gomez',
 'charles@classexpress.app',
 '$2y$10$Ex7uUpnIZqUUy9v3rTmwYubHC1Z6kl3fSMPgkIYW6RrNcdM1ilPsS',
 1, '+1 555 010 0004', '',
 'Third-year student focused on Mathematics and Physics. Coffee-powered coder.',
 'private', 'student', 0.0, 0,
 'rostro_masculino_1.png', '4', '18', 'math-room'),

-- Student B — active in Biology
(5,
 'Sofia Reyes',
 'sofia@classexpress.app',
 '$2y$10$iMNk1CLd/VAgtWNlMECpNeaghaq7LvtHWlJPypOZZNzPdnG.SsFw2',
 1, '+1 555 010 0005', '',
 'Biology and Chemistry enthusiast. Future researcher.',
 'private', 'student', 0.0, 0,
 'rostro_femenino_2.png', '8', '18', 'bio-room'),

-- Student C — new, just signed up
(6,
 'Lucas Martini',
 'lucas@classexpress.app',
 '$2y$10$PASSuq7uJy69F7tq//uKyeUoUcG30Dt/WFAvKQOFfsRCsxRFCooGa',
 1, '+1 555 010 0006', '',
 'Just joined ClassExpress. Exploring all subjects.',
 'private', 'student', 0.0, 0,
 'rostro_masculino_2.png', '', '', '');

-- ─────────────────────────────────────────────────────────────────────────────
--  2. USER PROGRESS
--     temaId map (from database.sql inserts):
--       1–6   Math → Numbers and Operations
--       7–12  Math → Algebra and Functions
--       13–16 Math → Geometry
--       17–20 Math → Probability and Statistics
--       21–23 Math → Algebraic Structures
--       24–27 Math → Pre-Calculus
--       28–32 Math → Linear Algebra
--       33–37 Biology → Cellular Biology
--       38–42 Biology → Ecosystems
--       43–47 Biology → Genetics and Evolution
-- ─────────────────────────────────────────────────────────────────────────────

-- Charles (usuarioId=4) — strong Math student, 80 % through Numbers + all Algebra
INSERT INTO progreso_usuario (usuarioId, temaId, completado, fecha_completado) VALUES
-- Numbers and Operations (5/6 done)
(4, 1, 1, '2026-06-01 09:10:00'),
(4, 2, 1, '2026-06-01 09:25:00'),
(4, 3, 1, '2026-06-02 10:00:00'),
(4, 4, 1, '2026-06-02 10:30:00'),
(4, 5, 1, '2026-06-03 11:00:00'),
(4, 6, 0, NULL),
-- Algebra and Functions (all 6 done)
(4, 7,  1, '2026-06-05 08:00:00'),
(4, 8,  1, '2026-06-05 08:45:00'),
(4, 9,  1, '2026-06-06 09:00:00'),
(4, 10, 1, '2026-06-06 09:30:00'),
(4, 11, 1, '2026-06-07 10:00:00'),
(4, 12, 1, '2026-06-07 10:20:00'),
-- Geometry (2/4 done)
(4, 13, 1, '2026-06-10 08:00:00'),
(4, 14, 1, '2026-06-10 08:40:00'),
(4, 15, 0, NULL),
(4, 16, 0, NULL),
-- Probability (1/4 done)
(4, 17, 1, '2026-06-12 09:00:00'),
(4, 18, 0, NULL),
(4, 19, 0, NULL),
(4, 20, 0, NULL);

-- Sofia (usuarioId=5) — focused on Biology
INSERT INTO progreso_usuario (usuarioId, temaId, completado, fecha_completado) VALUES
-- Cellular Biology (all 5 done)
(5, 33, 1, '2026-06-03 14:00:00'),
(5, 34, 1, '2026-06-03 14:30:00'),
(5, 35, 1, '2026-06-04 13:00:00'),
(5, 36, 1, '2026-06-04 13:45:00'),
(5, 37, 1, '2026-06-05 14:00:00'),
-- Ecosystems (3/5 done)
(5, 38, 1, '2026-06-08 10:00:00'),
(5, 39, 1, '2026-06-08 10:30:00'),
(5, 40, 1, '2026-06-09 11:00:00'),
(5, 41, 0, NULL),
(5, 42, 0, NULL),
-- Genetics (2/5 done)
(5, 43, 1, '2026-06-12 15:00:00'),
(5, 44, 1, '2026-06-12 15:40:00'),
(5, 45, 0, NULL),
(5, 46, 0, NULL),
(5, 47, 0, NULL);

-- Lucas (usuarioId=6) — just started, only 2 items checked
INSERT INTO progreso_usuario (usuarioId, temaId, completado, fecha_completado) VALUES
(6, 1, 1, '2026-06-20 20:00:00'),
(6, 2, 1, '2026-06-20 20:25:00');

-- ─────────────────────────────────────────────────────────────────────────────
--  3. VIRTUAL CLASSROOMS
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO salas (salaId, titulo, curso, instructorId, activa) VALUES
(1, 'Class 04: Advanced Web Development', 'Front-End Master',  2, 1),
(2, 'Class 01: Introduction to Calculus',  'Mathematics Pro',  1, 1),
(3, 'Class 02: Cellular Biology Deep Dive','Biology Essentials',3, 0);

-- ─────────────────────────────────────────────────────────────────────────────
--  4. CLASSROOM PARTICIPANTS
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO participantes_sala (salaId, usuarioId, camara_activa, microfono_activo, mano_levantada) VALUES
-- Sala 1 — Web Dev
(1, 2, 1, 1, 0),  -- Alexander (instructor, cam+mic on)
(1, 4, 1, 0, 0),  -- Charles (cam on, muted)
(1, 6, 0, 0, 1),  -- Lucas (raised hand)
-- Sala 2 — Calculus
(2, 1, 1, 1, 0),  -- Rodrigo (instructor)
(2, 4, 0, 0, 0),  -- Charles
-- Sala 3 — Biology (inactive)
(3, 3, 1, 1, 0),  -- Mary (instructor)
(3, 5, 1, 0, 1);  -- Sofia (raised hand)

-- ─────────────────────────────────────────────────────────────────────────────
--  5. LIVE CHAT MESSAGES  (sala 1 — Web Dev class)
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO mensajes_chat (salaId, usuarioId, alias, mensaje, enviado_at) VALUES
(1, 2, 'Alexander V.',   'Welcome everyone! Today we cover CSS Grid layouts.',               '2026-06-21 09:00:10'),
(1, 4, 'Charles_Gomez',  'Ready! I have been practicing flexbox all week.',                  '2026-06-21 09:00:45'),
(1, 6, 'Lucas_Martini',  'Hi all! First class here, excited to learn.',                      '2026-06-21 09:01:05'),
(1, 2, 'Alexander V.',   'Great! Let\'s start. Open your code editors.',                     '2026-06-21 09:01:30'),
(1, 4, 'Charles_Gomez',  'Quick question — should we use auto-fill or auto-fit?',            '2026-06-21 09:05:20'),
(1, 2, 'Alexander V.',   'auto-fit collapses empty tracks; auto-fill keeps them. We\'ll see both.', '2026-06-21 09:05:55'),
(1, NULL,'Teacher_AI',   'Tip: grid-template-columns: repeat(auto-fit, minmax(200px,1fr)) is your best friend for responsive grids.', '2026-06-21 09:06:10'),
(1, NULL,'Moderator',    'Reminder: questions go in chat, not voice, to keep things orderly.','2026-06-21 09:10:00'),
(1, 6, 'Lucas_Martini',  'This is making a lot of sense now, thank you!',                    '2026-06-21 09:15:30'),
(1, 4, 'Charles_Gomez',  'Can we get a copy of today\'s code snippet after class?',          '2026-06-21 09:20:10'),
(1, 2, 'Alexander V.',   'Of course — I\'ll upload it to the Contenidos section.',           '2026-06-21 09:20:40');

-- Chat for sala 2 — Calculus
INSERT INTO mensajes_chat (salaId, usuarioId, alias, mensaje, enviado_at) VALUES
(2, 1, 'Rodrigo',       'Today we tackle limits. Don\'t panic!',              '2026-06-21 10:00:05'),
(2, 4, 'Charles_Gomez', 'I always confuse one-sided limits. Help!',           '2026-06-21 10:01:15'),
(2, 1, 'Rodrigo',       'Draw the graph first — it always clarifies things.', '2026-06-21 10:01:50');

-- ─────────────────────────────────────────────────────────────────────────────
--  6. FRIEND RELATIONSHIPS
--     Covers: example2 (friend list, follow/unfollow)
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO relaciones (seguidorId, seguidoId, estado) VALUES
(4, 2, 'following'),  -- Charles follows Alexander
(4, 1, 'following'),  -- Charles follows Rodrigo
(5, 3, 'following'),  -- Sofia follows Mary
(5, 4, 'following'),  -- Sofia follows Charles
(6, 1, 'following'),  -- Lucas follows Rodrigo
(6, 4, 'pending'),    -- Lucas sent a request to Charles
(2, 1, 'following'),  -- Alexander follows Rodrigo
(3, 1, 'following');  -- Mary follows Rodrigo

-- ─────────────────────────────────────────────────────────────────────────────
--  7. NOTIFICATIONS
--     Covers: example3 (Submit / Ignore notification cards)
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO notificaciones (usuarioId, remitenteId, mensaje, tipo, leida) VALUES
-- Charles's inbox
(4, 5, 'Sofia Reyes started following you.',                                 'follow',   0),
(4, 1, 'New class scheduled: Class 04 — Advanced Web Development.',          'class',    0),
(4, 2, 'Alexander V. uploaded new content to Mathematics → Algebra.',        'system',   1),
-- Sofia's inbox
(5, 4, 'Charles Gomez liked your progress on Cellular Biology.',             'system',   0),
(5, 1, 'Your Biology session recording is now available.',                   'class',    0),
(5, 3, 'Mary Lopez commented: "Great job on Ecosystems! Keep it up."',       'message',  0),
-- Lucas's inbox
(6, 1, 'Welcome to ClassExpress! Start by picking a subject.',               'system',   1),
(6, 4, 'Charles Gomez accepted your follow request.',                        'follow',   0),
-- Rodrigo's inbox
(1, 4, 'Charles Gomez completed the Algebra and Functions unit (100%).',     'system',   0),
(1, 5, 'Sofia Reyes completed the Cellular Biology unit (100%).',            'system',   0);

-- ─────────────────────────────────────────────────────────────────────────────
--  8. SCHEDULED CLASSES
--     Covers: example19 / example20 (price range, student count, title)
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO clases_programadas (salaId, instructorId, titulo, precio_min, precio_max, alumnos_min, alumnos_max, solo_yo) VALUES
(1, 2, 'Advanced CSS Grid Workshop',       10.00, 25.00, 5, 20, 0),
(2, 1, 'Limits and Derivatives Bootcamp',  15.00, 40.00, 3, 15, 0),
(NULL,3, 'Private Biology Tutoring',        0.00,  0.00, 1,  1, 1);

-- ─────────────────────────────────────────────────────────────────────────────
--  9. SAMPLE ORDER
--     Covers: example17 (checkout cart + billing form)
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO pedidos
    (pedidoId, usuarioId, nombre_factura, apellido_factura, email_factura,
     direccion, pais, estado_region, codigo_postal, metodo_pago, codigo_promo, total)
VALUES
(1, 4, 'Charles', 'Gomez', 'charles@classexpress.app',
 '1234 Main St', 'United States', 'California', '90001', 'credit', 'WELCOME10', 22.50);

INSERT INTO detalle_pedido (pedidoId, productoId, nombre_producto, precio_unitario, cantidad) VALUES
(1, 1, 'Course Access – Basic',    12.00, 1),
(1, 2, 'Course Access – Standard',  8.00, 1),
(1, 3, 'Course Access – Add-on',    5.00, 1);
