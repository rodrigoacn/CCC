<?php
require 'menu.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['temas'])) {
    $temas = array_slice((array)$_POST['temas'], 0, 5);
    $qs = http_build_query(['materia' => 2, 'temas' => implode(',', $temas)]);
    header("Location: profesores.php?$qs");
    exit;
}

$materiaId    = 2;
$subjectName  = 'Biology';
$subjectImage = 'biology.png';

$secciones = [
    'Cellular Organization, Structure, and Activity' => [
        ['slug' => 'cell-types',       'title' => 'Prokaryotic and Eukaryotic Cells', 'desc' => 'Structural and functional differences; organelle structure and function.'],
        ['slug' => 'cell-membrane',    'title' => 'Cell Membrane',                   'desc' => 'Fluid mosaic model; passive transport (diffusion, osmosis) and active transport.'],
        ['slug' => 'bioenergetics',    'title' => 'Bioenergetic Processes',           'desc' => 'Photosynthesis (light/dark phases) and cellular respiration overview.'],
        ['slug' => 'macromolecules',   'title' => 'Organic Macromolecules',           'desc' => 'Structure and function of proteins, carbohydrates, lipids, and nucleic acids.'],
    ],
    'Ecosystem Processes and Ecology' => [
        ['slug' => 'energy-flow',          'title' => 'Energy and Matter Flow',            'desc' => 'Food webs, trophic levels, producers, consumers, and decomposers.'],
        ['slug' => 'biogeochemical-cycles','title' => 'Biogeochemical Cycles',             'desc' => 'Water, carbon, and nitrogen cycles and their global importance.'],
        ['slug' => 'population-dynamics',  'title' => 'Population and Community Dynamics', 'desc' => 'Growth curves, density, birth/death rates, and interspecific interactions.'],
        ['slug' => 'environmental-impact', 'title' => 'Environmental Impact',              'desc' => 'Global warming, biodiversity loss, pollution, and invasive species.'],
    ],
    'Inheritance, Genetics, and Evolution' => [
        ['slug' => 'mitosis',           'title' => 'Cell Cycle and Mitosis',    'desc' => 'G1/S/G2 phases, mitosis stages, and relation to cancer.'],
        ['slug' => 'meiosis',           'title' => 'Meiosis and Gametogenesis', 'desc' => 'Meiosis stages, crossing-over, independent assortment, and gamete formation.'],
        ['slug' => 'mendelian-genetics','title' => 'Mendelian Genetics',        'desc' => 'Phenotype/genotype, dominant/recessive alleles, monohybrid and dihybrid crosses.'],
        ['slug' => 'evolution',         'title' => 'Evolutionary Theories',     'desc' => 'Evidence for evolution (fossil, anatomical, molecular) and natural selection.'],
    ],
    'Human Body Systems, Health, and Reproduction' => [
        ['slug' => 'nervous-system',     'title' => 'Nervous System',              'desc' => 'Central/peripheral nervous system, neurons, synapses, and reflex arcs.'],
        ['slug' => 'endocrine-system',   'title' => 'Endocrine System',            'desc' => 'Hormones, glands (thyroid, adrenal, pituitary), and feedback regulation.'],
        ['slug' => 'circulatory-immune', 'title' => 'Circulatory and Immune System','desc' => 'Heart, blood vessels, blood types, and immune response.'],
        ['slug' => 'reproductive-system','title' => 'Reproductive System',         'desc' => 'Male and female anatomy, gametogenesis, fertilization, and development.'],
    ],
];

require '_subject_page.php';
