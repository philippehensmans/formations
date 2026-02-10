<?php
/**
 * Configuration des categories d'applications
 *
 * Ce fichier definit les categories et l'association des applications.
 * Chaque application peut appartenir a plusieurs categories.
 * Modifiez ce fichier pour organiser vos applications par formation.
 *
 * Structure:
 *   'categories' => liste des categories avec label, couleur et icone
 *   'apps'       => pour chaque app-*, liste de ses categories
 */

return [
    // Definition des categories
    'categories' => [
        'gestion_projet' => [
            'label' => 'Gestion de projet',
            'color' => 'blue',
            'icon' => "\xF0\x9F\x93\x8B", // clipboard
        ],
        'analyse_strategique' => [
            'label' => 'Analyse strategique',
            'color' => 'purple',
            'icon' => "\xF0\x9F\x94\x8D", // magnifying glass
        ],
        'intelligence_artificielle' => [
            'label' => 'Intelligence artificielle',
            'color' => 'violet',
            'icon' => "\xF0\x9F\xA4\x96", // robot
        ],
        'environnement' => [
            'label' => 'Environnement & Climat',
            'color' => 'green',
            'icon' => "\xF0\x9F\x8C\x8D", // globe
        ],
        'communication' => [
            'label' => 'Communication',
            'color' => 'cyan',
            'icon' => "\xF0\x9F\x93\xA2", // loudspeaker
        ],
        'evaluation' => [
            'label' => 'Evaluation & Retrospective',
            'color' => 'amber',
            'icon' => "\xF0\x9F\x93\x8A", // chart
        ],
        'collaboration' => [
            'label' => 'Outils collaboratifs',
            'color' => 'pink',
            'icon' => "\xF0\x9F\xA4\x9D", // handshake
        ],
        'creativite' => [
            'label' => 'Creativite & Reflexion',
            'color' => 'orange',
            'icon' => "\xF0\x9F\x92\xA1", // lightbulb
        ],
    ],

    // Association des applications aux categories
    // Chaque application peut appartenir a plusieurs categories
    'apps' => [
        'app-activites'            => ['intelligence_artificielle', 'analyse_strategique'],
        'app-agile'                => ['gestion_projet', 'collaboration'],
        'app-arbreproblemes'       => ['analyse_strategique', 'gestion_projet'],
        'app-atelier-ia'           => ['intelligence_artificielle', 'creativite'],
        'app-cadrelogique'         => ['gestion_projet', 'analyse_strategique'],
        'app-cahier-charges'       => ['gestion_projet'],
        'app-calculateur-carbone'  => ['environnement'],
        'app-carte-identite'       => ['gestion_projet'],
        'app-carte-projet'         => ['gestion_projet'],
        'app-empreinte-carbone'    => ['environnement'],
        'app-guide-prompting'      => ['intelligence_artificielle'],
        'app-journey-mapping'      => ['communication', 'analyse_strategique'],
        'app-mesure-impact'        => ['evaluation', 'gestion_projet'],
        'app-mindmap'              => ['collaboration', 'creativite'],
        'app-objectifs-smart'      => ['gestion_projet', 'evaluation'],
        'app-parties-prenantes'    => ['analyse_strategique', 'gestion_projet'],
        'app-pestel'               => ['analyse_strategique'],
        'app-prompt-jeunes'        => ['intelligence_artificielle', 'creativite'],
        'app-six-chapeaux'         => ['creativite', 'collaboration'],
        'app-stop-start-continue'  => ['evaluation', 'collaboration'],
        'app-swot'                 => ['analyse_strategique'],
        'app-whiteboard'           => ['collaboration', 'creativite'],
    ],
];
