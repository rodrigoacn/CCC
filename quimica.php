<?php
require 'menu.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['temas'])) {
    $temas = array_slice((array)$_POST['temas'], 0, 5);
    $qs = http_build_query(['materia' => 3, 'temas' => implode(',', $temas)]);
    header("Location: profesores.php?$qs");
    exit;
}

$materiaId    = 3;
$subjectName  = 'Chemistry';
$subjectImage = 'chemistry.png';

$secciones = [
    'Atomic Structure and Properties of Matter' => [
        ['slug' => 'classification-matter', 'title' => 'Classification of Matter', 'desc' => 'Pure substances vs. mixtures; separation methods (filtration, distillation).'],
        ['slug' => 'atomic-theory',         'title' => 'Atomic Theory',            'desc' => 'Evolution of atomic models and fundamental subatomic particles.'],
        ['slug' => 'electron-configuration','title' => 'Electron Configuration',   'desc' => 'Build-up principles, orbital diagrams, valence electrons, and quantum numbers.'],
        ['slug' => 'periodic-table',        'title' => 'Periodic Table',           'desc' => 'Groups, periods, and periodic properties (radius, ionization energy, electronegativity).'],
        ['slug' => 'chemical-bonding',      'title' => 'Chemical Bonding',         'desc' => 'Ionic, covalent, and metallic bonds; intermolecular forces and polarity.'],
    ],
    'Solution Chemistry and Stoichiometry' => [
        ['slug' => 'aqueous-solutions', 'title' => 'Aqueous Solutions', 'desc' => 'Solubility, concentration units (molarity, molality), and dilution effects.'],
        ['slug' => 'stoichiometry',     'title' => 'Stoichiometry',     'desc' => 'Mole concept, molar mass, balancing equations, and limiting reagent.'],
        ['slug' => 'acid-base',         'title' => 'Acid-Base Chemistry','desc' => 'pH, strong/weak acids and bases, neutralization, and buffer solutions.'],
        ['slug' => 'redox-reactions',   'title' => 'Oxidation-Reduction','desc' => 'Oxidation states, balancing redox equations, and electrochemistry basics.'],
    ],
    'Organic Chemistry' => [
        ['slug' => 'hydrocarbons',         'title' => 'Hydrocarbons',            'desc' => 'Alkanes, alkenes, alkynes, and aromatic compounds; IUPAC nomenclature.'],
        ['slug' => 'functional-groups',    'title' => 'Functional Groups',       'desc' => 'Alcohols, aldehydes, ketones, carboxylic acids, and esters.'],
        ['slug' => 'polymers-biomolecules','title' => 'Polymers and Biomolecules','desc' => 'Natural and synthetic polymers; connection to biological macromolecules.'],
    ],
    'Thermochemistry and Reaction Kinetics' => [
        ['slug' => 'thermochemistry',      'title' => 'Thermochemistry',         'desc' => 'Enthalpy, endothermic/exothermic reactions, and Hess\'s law.'],
        ['slug' => 'reaction-kinetics',    'title' => 'Reaction Kinetics',       'desc' => 'Reaction rates, activation energy, catalysts, and collision theory.'],
        ['slug' => 'chemical-equilibrium', 'title' => 'Chemical Equilibrium',    'desc' => 'Le Chatelier\'s principle and the equilibrium constant (Keq).'],
    ],
];

require '_subject_page.php';
