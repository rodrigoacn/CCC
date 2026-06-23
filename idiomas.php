<?php
require 'menu.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['temas'])) {
    $temas = array_slice((array)$_POST['temas'], 0, 5);
    $qs = http_build_query(['materia' => 8, 'temas' => implode(',', $temas)]);
    header("Location: profesores.php?$qs");
    exit;
}

$materiaId    = 8;
$subjectName  = 'Foreign Languages';
$subjectImage = 'foreign_languages.png';

$secciones = [
    'Theoretical Linguistics and the Language System' => [
        ['slug' => 'phonetics',    'title' => 'Phonetics and Phonology',   'desc' => 'Places and manners of articulation; stress, rhythm, and intonation patterns.'],
        ['slug' => 'morphosyntax', 'title' => 'Morphosyntax — Grammar',    'desc' => 'Parts of speech, sentence structure (SVO), tenses, and moods.'],
        ['slug' => 'vocabulary',   'title' => 'Vocabulary and Lexicology', 'desc' => 'Word families, collocations, false cognates, and register.'],
    ],
    'Comprehension and Receptive Mechanisms' => [
        ['slug' => 'reading-techniques',    'title' => 'Reading Techniques',       'desc' => 'Skimming (global idea) and scanning (locating specific data).'],
        ['slug' => 'inference',             'title' => 'Inference',                'desc' => 'Deducing unfamiliar word meanings using contextual clues.'],
        ['slug' => 'listening-comprehension','title' => 'Listening Comprehension',  'desc' => 'Identifying registers, dialects, and auditory discrimination of minimal pairs.'],
    ],
    'Production and Expressive Mechanisms' => [
        ['slug' => 'written-expression', 'title' => 'Written Expression', 'desc' => 'Cohesion/coherence, logical connectors, and text typologies (emails, essays, reviews).'],
        ['slug' => 'oral-expression',    'title' => 'Oral Expression',    'desc' => 'Fluency vs. accuracy; compensation strategies and conversational interaction.'],
    ],
    'Sociolinguistics and Culture' => [
        ['slug' => 'sociolinguistics',       'title' => 'Sociolinguistics',       'desc' => 'Language variation: dialects, sociolects, registers, and code-switching.'],
        ['slug' => 'intercultural-competence','title' => 'Intercultural Competence','desc' => 'Cultural values behind language; non-verbal communication norms across cultures.'],
        ['slug' => 'pragmatics',             'title' => 'Pragmatics',             'desc' => 'Speech acts, politeness strategies, and conversational implicature.'],
    ],
];

require '_subject_page.php';
