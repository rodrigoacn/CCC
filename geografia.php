<?php
require 'menu.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['temas'])) {
    $temas = array_slice((array)$_POST['temas'], 0, 5);
    $qs = http_build_query(['materia' => 6, 'temas' => implode(',', $temas)]);
    header("Location: profesores.php?$qs");
    exit;
}

$materiaId    = 6;
$subjectName  = 'Geography';
$subjectImage = 'geography.png';

$secciones = [
    'Physical Geography and Natural Environment' => [
        ['slug' => 'geomorphology',   'title' => 'Geomorphology',    'desc' => 'Landforms: plains, mountains, plateaus, valleys, and coastal features.'],
        ['slug' => 'climate-systems', 'title' => 'Climate Systems',  'desc' => 'Climate classification, factors determining climate, and world biomes.'],
        ['slug' => 'hydrology',       'title' => 'Hydrology',        'desc' => 'Water cycle, drainage basins, river systems, oceans, and groundwater.'],
        ['slug' => 'biogeography',    'title' => 'Biogeography',     'desc' => 'Distribution of ecosystems (rainforests, deserts, tundra) based on climate and soil.'],
    ],
    'Human and Population Geography' => [
        ['slug' => 'demographics',    'title' => 'Demographics',     'desc' => 'Population density, birth/death rates, life expectancy, and population pyramids.'],
        ['slug' => 'migration',       'title' => 'Migration',        'desc' => 'Push/pull factors, internal vs. international migration, and socioeconomic impacts.'],
        ['slug' => 'urban-geography', 'title' => 'Urban Geography',  'desc' => 'Urbanization, city structures, megacities, and urban challenges.'],
        ['slug' => 'rural-geography', 'title' => 'Rural Geography',  'desc' => 'Settlement patterns, agricultural systems, and rural space transformation.'],
    ],
    'Economic and Political Geography' => [
        ['slug' => 'economic-sectors', 'title' => 'Economic Sectors', 'desc' => 'Primary, secondary, tertiary, and quaternary economic activities.'],
        ['slug' => 'globalization',    'title' => 'Globalization',    'desc' => 'Global trade networks, transnational corporations, and economic disparities.'],
        ['slug' => 'geopolitics',      'title' => 'Geopolitics',      'desc' => 'State, nation, territory, borders, conflicts, and supranational organizations.'],
        ['slug' => 'natural-resources','title' => 'Natural Resources', 'desc' => 'Renewable vs. non-renewable resources and geopolitical control of strategic assets.'],
    ],
    'Environmental Geography and Sustainability' => [
        ['slug' => 'human-impact',          'title' => 'Human Impact',            'desc' => 'Deforestation, soil degradation, water pollution, and urban sprawl.'],
        ['slug' => 'climate-change-geo',    'title' => 'Climate Change',          'desc' => 'Global warming, greenhouse effect, and regional consequences.'],
        ['slug' => 'sustainable-development','title' => 'Sustainable Development', 'desc' => 'Development goals, circular economy, and international environmental agreements.'],
    ],
];

require '_subject_page.php';
