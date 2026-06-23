<?php
require 'menu.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['temas'])) {
    $temas = array_slice((array)$_POST['temas'], 0, 5);
    $qs = http_build_query(['materia' => 4, 'temas' => implode(',', $temas)]);
    header("Location: profesores.php?$qs");
    exit;
}

$materiaId    = 4;
$subjectName  = 'Physics';
$subjectImage = 'physics.png';

$secciones = [
    'Mechanics' => [
        ['slug' => 'kinematics',      'title' => 'Kinematics',          'desc' => 'Motion in a straight line: position, displacement, velocity, acceleration, MRU and MRUA.'],
        ['slug' => 'dynamics',        'title' => 'Dynamics and Forces', 'desc' => 'Newton\'s three laws, weight, friction, tension, Hooke\'s law, and static equilibrium.'],
        ['slug' => 'linear-momentum', 'title' => 'Linear Momentum',     'desc' => 'Momentum, impulse, and conservation of linear momentum in collisions.'],
        ['slug' => 'energy-work',     'title' => 'Energy and Work',     'desc' => 'Kinetic and potential energy, conservation of mechanical energy, and power.'],
    ],
    'Waves and Optics' => [
        ['slug' => 'wave-properties', 'title' => 'Properties of Waves', 'desc' => 'Amplitude, frequency, period, wavelength, speed, and wave classification.'],
        ['slug' => 'wave-phenomena',  'title' => 'Wave Phenomena',      'desc' => 'Reflection, refraction (Snell\'s law), diffraction, and interference.'],
        ['slug' => 'sound',           'title' => 'Sound',               'desc' => 'Production and propagation, pitch/intensity/timbre, Doppler effect, and resonance.'],
        ['slug' => 'light-optics',    'title' => 'Light and Optics',    'desc' => 'Electromagnetic spectrum, mirrors, converging/diverging lenses, and eye defects.'],
    ],
    'Electricity and Magnetism' => [
        ['slug' => 'electrostatics',    'title' => 'Electrostatics',   'desc' => 'Electric charge, charging methods (friction, contact, induction), and Coulomb\'s law.'],
        ['slug' => 'electric-circuits', 'title' => 'Electric Circuits', 'desc' => 'Ohm\'s law, resistance, voltage, Joule effect, and series/parallel circuits.'],
        ['slug' => 'magnetism',         'title' => 'Magnetism',         'desc' => 'Magnetic fields, Oersted\'s discovery, and Faraday\'s electromagnetic induction.'],
    ],
    'Earth Sciences and the Universe' => [
        ['slug' => 'earth-structure',    'title' => 'Earth Structure and Dynamics', 'desc' => 'Internal layers, plate tectonics, earthquakes (P, S, R, L waves), and volcanism.'],
        ['slug' => 'universe-astronomy', 'title' => 'The Universe and Astronomy',   'desc' => 'Solar system, stellar evolution, Big Bang, and cosmological scales.'],
        ['slug' => 'atmosphere-climate', 'title' => 'Atmosphere and Climate',       'desc' => 'Layers of the atmosphere, weather vs. climate, and the greenhouse effect.'],
    ],
];

require '_subject_page.php';
