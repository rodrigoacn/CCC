<?php
require 'menu.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['temas'])) {
    $temas = array_slice((array)$_POST['temas'], 0, 5);
    $qs = http_build_query(['materia' => 1, 'temas' => implode(',', $temas)]);
    header("Location: profesores.php?$qs");
    exit;
}

$materiaId    = 1;
$subjectName  = 'Mathematics';
$subjectImage = 'mathematics.png';

$secciones = [
    'Numbers and Operations' => [
        ['slug' => 'number-sets',     'title' => 'Number Sets',           'desc' => 'Natural, integer, rational, and real numbers — properties and operations.'],
        ['slug' => 'percentages',     'title' => 'Percentages',           'desc' => 'Percentage calculation, increase/decrease, and everyday applications.'],
        ['slug' => 'powers-roots',    'title' => 'Powers and Roots',      'desc' => 'Integer and fractional exponents, n-th roots, and simplification rules.'],
        ['slug' => 'proportionality', 'title' => 'Proportionality',       'desc' => 'Direct and inverse proportionality, rule of three, and ratio applications.'],
        ['slug' => 'logarithms',      'title' => 'Logarithms',            'desc' => 'Definition, properties, change of base, and real-world applications.'],
        ['slug' => 'financial-math',  'title' => 'Financial Mathematics', 'desc' => 'Simple and compound interest, annuities, and loan calculations.'],
    ],
    'Algebra and Functions' => [
        ['slug' => 'algebraic-expressions', 'title' => 'Algebraic Expressions',      'desc' => 'Polynomials, factoring, and operations with algebraic fractions.'],
        ['slug' => 'linear-equations',      'title' => 'Linear Equations & Systems', 'desc' => 'Solving first-degree equations and 2×2/3×3 linear systems.'],
        ['slug' => 'inequalities',          'title' => 'Inequalities',               'desc' => 'First-degree inequalities and graphical representation on the number line.'],
        ['slug' => 'functions',             'title' => 'Functions',                  'desc' => 'Domain, range, and graphing of linear, quadratic, and piecewise functions.'],
        ['slug' => 'exp-log-functions',     'title' => 'Exponential & Log Functions','desc' => 'Graphs, transformations, and real-world modeling.'],
        ['slug' => 'trigonometry',          'title' => 'Trigonometry',               'desc' => 'Trigonometric ratios, the unit circle, and basic identities.'],
    ],
    'Geometry' => [
        ['slug' => 'congruence-similarity', 'title' => 'Congruence and Similarity',    'desc' => 'Triangle congruence criteria, similarity ratios, and scale.'],
        ['slug' => 'thales-pythagoras',     'title' => 'Thales & Pythagorean Theorem', 'desc' => 'Applications in right triangles and coordinate geometry.'],
        ['slug' => 'areas-volumes',         'title' => 'Areas and Volumes',            'desc' => 'Perimeters, areas of plane figures, and volumes of 3D solids.'],
        ['slug' => 'transformations',       'title' => 'Geometric Transformations',    'desc' => 'Translation, rotation, reflection, and glide reflection.'],
        ['slug' => 'vectors',               'title' => 'Vectors in a Plane',           'desc' => 'Vector addition, scalar multiplication, and dot product.'],
        ['slug' => 'analytic-geometry',     'title' => 'Analytic Geometry',            'desc' => 'Lines, circles, and conics in the Cartesian plane.'],
    ],
    'Probability and Statistics' => [
        ['slug' => 'data-analysis', 'title' => 'Data Analysis',       'desc' => 'Charts, frequency distributions, and measures of central tendency.'],
        ['slug' => 'probability',   'title' => 'Probability',         'desc' => 'Sample spaces, events, addition/multiplication rules, and conditional probability.'],
        ['slug' => 'dispersion',    'title' => 'Dispersion Measures', 'desc' => 'Variance, standard deviation, and interquartile range.'],
        ['slug' => 'combinatorics', 'title' => 'Combinatorics',       'desc' => 'Permutations, combinations, and the binomial theorem.'],
    ],
];

require '_subject_page.php';
