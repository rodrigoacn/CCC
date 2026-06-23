<?php
require 'menu.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['temas'])) {
    $temas = array_slice((array)$_POST['temas'], 0, 5);
    $qs = http_build_query(['materia' => 11, 'temas' => implode(',', $temas)]);
    header("Location: profesores.php?$qs");
    exit;
}

$materiaId    = 11;
$subjectName  = 'Physical Education';
$subjectImage = 'physical_education.png';

$secciones = [
    'Physical Fitness, Health, and Exercise Physiology' => [
        ['slug' => 'physical-qualities', 'title' => 'Basic Physical Qualities',    'desc' => 'Endurance, strength, speed, and flexibility — definitions and training methods.'],
        ['slug' => 'energy-systems',     'title' => 'Energy Systems',              'desc' => 'ATP-PC, glycolytic, and oxidative metabolic pathways during exercise.'],
        ['slug' => 'cardio-respiratory', 'title' => 'Cardiorespiratory Response',  'desc' => 'Heart rate, VO2 max, oxygen debt, and respiratory adaptations to exercise.'],
        ['slug' => 'health-wellness',    'title' => 'Health and Wellness',         'desc' => 'Body composition (BMI, body fat %), hypokinetic diseases, and sedentary lifestyle risks.'],
    ],
    'Principles and Methods of Sports Training' => [
        ['slug' => 'training-principles', 'title' => 'Training Principles',        'desc' => 'Supercompensation, progressive overload, specificity, reversibility, and individuality.'],
        ['slug' => 'training-load',       'title' => 'Components of Training Load','desc' => 'Volume, intensity, density, frequency, and duration of the stimulus.'],
        ['slug' => 'assessment-methods',  'title' => 'Assessment Methods',         'desc' => 'Beep test, Cooper test, strength tests, and Borg perceived exertion scale.'],
        ['slug' => 'prevention-safety',   'title' => 'Prevention and Safety',      'desc' => 'Warm-up/cool-down phases, fatigue management, hydration, and injury prevention.'],
    ],
    'Motor Skills, Body Expression, and Movement Capabilities' => [
        ['slug' => 'motor-skills',           'title' => 'Motor Skills',               'desc' => 'Fundamental (locomotion, manipulation, stability) and specialized sports-specific skills.'],
        ['slug' => 'coordinative-capabilities','title' => 'Coordinative Capabilities', 'desc' => 'Spatial orientation, rhythm, balance, reaction time, and motor differentiation.'],
        ['slug' => 'body-expression',        'title' => 'Body Expression and Dance',  'desc' => 'Movement qualities, improvisation, choreography, and expressive communication.'],
    ],
    'Sports and Games' => [
        ['slug' => 'collective-sports',         'title' => 'Collective Sports',         'desc' => 'Tactical principles, team roles, and rules in soccer, basketball, and volleyball.'],
        ['slug' => 'individual-sports',         'title' => 'Individual Sports',         'desc' => 'Technique and performance principles in athletics, swimming, and gymnastics.'],
        ['slug' => 'adapted-physical-education','title' => 'Inclusive Physical Education','desc' => 'Adapting activities for different abilities and special educational needs.'],
    ],
];

require '_subject_page.php';
