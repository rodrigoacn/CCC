<?php
require 'menu.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['temas'])) {
    $temas = array_slice((array)$_POST['temas'], 0, 5);
    $qs = http_build_query(['materia' => 7, 'temas' => implode(',', $temas)]);
    header("Location: profesores.php?$qs");
    exit;
}

$materiaId    = 7;
$subjectName  = 'Language & Literature';
$subjectImage = 'literature.png';

$secciones = [
    'Evaluated Reading Skills' => [
        ['slug' => 'locate-info',    'title' => 'Locate Information', 'desc' => 'Find specific, explicit information within a text accurately and quickly.'],
        ['slug' => 'interpret-text', 'title' => 'Interpret Texts',    'desc' => 'Connect ideas, infer implicit information, and determine global meaning.'],
        ['slug' => 'evaluate-text',  'title' => 'Evaluate Texts',     'desc' => 'Judge content, form, argument validity, and the author\'s intent.'],
    ],
    'Text Types' => [
        ['slug' => 'literary-texts',     'title' => 'Literary Texts',     'desc' => 'Narrative texts: short stories, novel fragments, myths, and legends.'],
        ['slug' => 'non-literary-texts', 'title' => 'Non-Literary Texts', 'desc' => 'Informative (news, reports) and argumentative (editorials, opinion columns) texts.'],
    ],
    'Reading Situations' => [
        ['slug' => 'personal-reading',    'title' => 'Personal Reading',    'desc' => 'Leisure, entertainment, and individual interest texts.'],
        ['slug' => 'public-reading',      'title' => 'Public Reading',      'desc' => 'Community interest, civic, and social texts.'],
        ['slug' => 'educational-reading', 'title' => 'Educational Reading', 'desc' => 'Academic, scientific, and textbook materials.'],
    ],
    'Language and Literary Devices' => [
        ['slug' => 'narrative-elements', 'title' => 'Narrative Elements',  'desc' => 'Narrator, character types, setting, plot structure, and narrative point of view.'],
        ['slug' => 'figurative-language', 'title' => 'Figurative Language', 'desc' => 'Metaphor, simile, personification, irony, and hyperbole.'],
        ['slug' => 'text-structure',     'title' => 'Text Structure',       'desc' => 'Introduction, development, conclusion; cohesion and coherence devices.'],
        ['slug' => 'literary-movements', 'title' => 'Literary Movements',   'desc' => 'Romanticism, Realism, Modernism, and the Latin American Boom.'],
    ],
];

require '_subject_page.php';
