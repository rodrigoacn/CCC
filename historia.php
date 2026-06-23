<?php
require 'menu.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['temas'])) {
    $temas = array_slice((array)$_POST['temas'], 0, 5);
    $qs = http_build_query(['materia' => 5, 'temas' => implode(',', $temas)]);
    header("Location: profesores.php?$qs");
    exit;
}

$materiaId    = 5;
$subjectName  = 'History';
$subjectImage = 'history.png';

$secciones = [
    'World and American History' => [
        ['slug' => '19th-century',    'title' => 'The 19th Century',                   'desc' => 'Liberalism, the Industrial Revolution, idea of progress, and geopolitical transformations.'],
        ['slug' => 'first-half-20th', 'title' => 'The First Half of the 20th Century', 'desc' => 'WWI, the Great Depression, totalitarian regimes (Fascism, Nazism, Stalinism), and WWII.'],
        ['slug' => 'second-half-20th','title' => 'The Second Half of the 20th Century','desc' => 'Cold War, Latin American dictatorships, and accelerated globalization under neoliberalism.'],
    ],
    'Civic Education and Human Rights' => [
        ['slug' => 'democratic-state', 'title' => 'The Democratic State of Law', 'desc' => 'Democracy principles, constitutions, separation of powers, and citizen participation.'],
        ['slug' => 'human-rights',     'title' => 'Human Rights',                'desc' => 'Universality of rights, international responsibilities, diversity, and non-discrimination.'],
    ],
    'Economy and Society' => [
        ['slug' => 'economic-problem',        'title' => 'The Economic Problem',     'desc' => 'Scarcity, resource allocation, and the fundamental economic questions.'],
        ['slug' => 'market-mechanics',        'title' => 'Market Mechanics',         'desc' => 'Supply/demand, economic agents, inflation, unemployment, and fiscal policy.'],
        ['slug' => 'responsible-consumption', 'title' => 'Responsible Consumption',  'desc' => 'Financial markets, consumer rights, debt management, and sustainable development.'],
    ],
    'Evaluated Historical Skills' => [
        ['slug' => 'temporal-spatial', 'title' => 'Temporal and Spatial Thinking',   'desc' => 'Locating and contextualizing historical processes within specific eras and territories.'],
        ['slug' => 'source-analysis',  'title' => 'Analysis of Information Sources', 'desc' => 'Extracting conclusions from texts, graphs, maps, and historical cartoons.'],
        ['slug' => 'critical-thinking','title' => 'Critical Thinking',               'desc' => 'Multiple causation, identifying historical continuities, and change-over-time analysis.'],
    ],
];

require '_subject_page.php';
