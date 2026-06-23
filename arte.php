<?php
require 'menu.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['temas'])) {
    $temas = array_slice((array)$_POST['temas'], 0, 5);
    $qs = http_build_query(['materia' => 9, 'temas' => implode(',', $temas)]);
    header("Location: profesores.php?$qs");
    exit;
}

$materiaId    = 9;
$subjectName  = 'Art and Music';
$subjectImage = 'art.png';

$secciones = [
    'Assessed Skills' => [
        ['slug' => 'analyze-art',  'title' => 'Analyze',   'desc' => 'Examine elements of visual language and contexts across diverse artistic expressions.'],
        ['slug' => 'interpret-art','title' => 'Interpret', 'desc' => 'Assign grounded meanings to artworks based on their context or materiality.'],
        ['slug' => 'evaluate-art', 'title' => 'Evaluate',  'desc' => 'Form critical judgments on the impact, aesthetics, and purpose of a cultural manifestation.'],
    ],
    'Elements of Visual Language and Technical Procedures' => [
        ['slug' => 'line',              'title' => 'Line',                 'desc' => 'Expressive role, contour, directionality, and types of lines in composition.'],
        ['slug' => 'shape-volume',      'title' => 'Shape and Volume',     'desc' => 'Figurative, abstract, geometric, and organic forms.'],
        ['slug' => 'color-theory',      'title' => 'Color Theory',         'desc' => 'Saturation, contrast, color harmonies, and color psychology.'],
        ['slug' => 'space-perspective', 'title' => 'Space and Perspective','desc' => 'Depth, overlapping, framing, and the vanishing point in perspective drawing.'],
        ['slug' => 'texture-light',     'title' => 'Texture and Lighting', 'desc' => 'Visual vs. tactile texture; direct/diffused light and chiaroscuro.'],
        ['slug' => 'mediums-techniques','title' => 'Mediums and Techniques','desc' => 'Printmaking, sculpture, painting, photography, and digital media.'],
    ],
    'Art History and Artistic Movements' => [
        ['slug' => 'classical-modern',    'title' => 'Classical and Modern Western Art', 'desc' => 'Renaissance, Baroque, Impressionism, and 20th-century avant-gardes.'],
        ['slug' => 'visual-ruptures',     'title' => 'Visual Ruptures',                 'desc' => 'Cubism, Surrealism, and Abstract Expressionism.'],
        ['slug' => 'recent-movements',    'title' => 'Recent Movements',                'desc' => 'Pop Art, Minimalism, Conceptual Art, and Performance Art.'],
        ['slug' => 'latin-american-art',  'title' => 'Latin American Art',              'desc' => 'Muralism, magical realism in visual arts, and contemporary LATAM artists.'],
    ],
    'Music' => [
        ['slug' => 'music-elements', 'title' => 'Elements of Music', 'desc' => 'Rhythm, melody, harmony, dynamics, and timbre — how music is structured.'],
        ['slug' => 'music-history',  'title' => 'Music History',     'desc' => 'Western classical periods through jazz, rock, and contemporary genres.'],
    ],
];

require '_subject_page.php';
