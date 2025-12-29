<?php
/**
 * Fonctions helper pour Objectifs SMART
 */

/**
 * Objectifs a analyser (etape 1)
 */
function getObjectifsAnalyse() {
    return [
        [
            'id' => 1,
            'texte' => "Ameliorer la satisfaction des clients",
            'niveau' => 'facile',
            'corrections' => [
                'S' => ['attendu' => 'non', 'explication' => 'Quels clients ? Quel aspect de la satisfaction ?'],
                'M' => ['attendu' => 'non', 'explication' => "Pas d'indicateur ni de cible chiffree"],
                'A' => ['attendu' => 'partiellement', 'explication' => 'Possible mais moyens non precises'],
                'R' => ['attendu' => 'oui', 'explication' => 'La satisfaction client est un enjeu business pertinent'],
                'T' => ['attendu' => 'non', 'explication' => 'Aucune echeance']
            ]
        ],
        [
            'id' => 2,
            'texte' => "Reduire l'absenteisme de 20% d'ici decembre 2025",
            'niveau' => 'moyen',
            'corrections' => [
                'S' => ['attendu' => 'partiellement', 'explication' => 'Absenteisme de qui ? Tous les services ?'],
                'M' => ['attendu' => 'oui', 'explication' => '20% est un indicateur clair'],
                'A' => ['attendu' => 'partiellement', 'explication' => "Depend des causes de l'absenteisme"],
                'R' => ['attendu' => 'oui', 'explication' => "L'absenteisme a un cout, objectif pertinent"],
                'T' => ['attendu' => 'oui', 'explication' => 'Decembre 2025 est une echeance claire']
            ]
        ],
        [
            'id' => 3,
            'texte' => "Former 100% des managers aux techniques de feedback constructif via un programme de 2 jours, avant les entretiens annuels de mars 2025",
            'niveau' => 'smart',
            'corrections' => [
                'S' => ['attendu' => 'oui', 'explication' => 'Cible claire (managers), contenu precis'],
                'M' => ['attendu' => 'oui', 'explication' => '100% des managers, 2 jours de formation'],
                'A' => ['attendu' => 'oui', 'explication' => 'Programme de 2 jours est realisable'],
                'R' => ['attendu' => 'oui', 'explication' => 'Lie aux entretiens annuels, timing pertinent'],
                'T' => ['attendu' => 'oui', 'explication' => 'Avant mars 2025']
            ]
        ],
        [
            'id' => 4,
            'texte' => "Etre plus present sur les reseaux sociaux",
            'niveau' => 'facile',
            'corrections' => [
                'S' => ['attendu' => 'non', 'explication' => 'Quels reseaux ? Quel type de contenu ?'],
                'M' => ['attendu' => 'non', 'explication' => "'Plus present' n'est pas mesurable"],
                'A' => ['attendu' => 'partiellement', 'explication' => 'Faisable mais vague'],
                'R' => ['attendu' => 'partiellement', 'explication' => 'Pertinence depend de la strategie'],
                'T' => ['attendu' => 'non', 'explication' => "Pas d'echeance"]
            ]
        ],
        [
            'id' => 5,
            'texte' => "Publier 3 articles par semaine sur LinkedIn pendant 3 mois pour generer 500 nouveaux leads",
            'niveau' => 'moyen',
            'corrections' => [
                'S' => ['attendu' => 'oui', 'explication' => 'Canal, format et cible clairs'],
                'M' => ['attendu' => 'oui', 'explication' => '3 articles/semaine, 500 leads'],
                'A' => ['attendu' => 'partiellement', 'explication' => '3 articles/semaine est ambitieux'],
                'R' => ['attendu' => 'oui', 'explication' => 'Generation de leads est un objectif business'],
                'T' => ['attendu' => 'oui', 'explication' => '3 mois']
            ]
        ]
    ];
}

/**
 * Objectifs a reformuler (etape 2)
 */
function getObjectifsReformulation() {
    return [
        [
            'id' => 1,
            'texte_vague' => "Reduire les dechets dans l'entreprise",
            'pistes' => "Preciser : quel type de dechets ? ou ? de combien ? comment ? pourquoi maintenant ?",
            'exemple' => "Reduire de 40% le volume de dechets non recycles au siege d'ici decembre 2025, en mettant en place le tri selectif."
        ],
        [
            'id' => 2,
            'texte_vague' => "Apprendre l'anglais",
            'pistes' => "Preciser : quel niveau ? pour quoi faire ? en combien de temps ? avec quels moyens ?",
            'exemple' => "Atteindre le niveau B2 en anglais d'ici juin 2025, en suivant 2h de cours par semaine."
        ],
        [
            'id' => 3,
            'texte_vague' => "Augmenter le chiffre d'affaires",
            'pistes' => "Preciser : de combien ? sur quels produits/marches ? par quels moyens ? pour quand ?",
            'exemple' => "Augmenter le CA de la gamme premium de 25% d'ici Q4 2025 en recrutant 2 commerciaux."
        ]
    ];
}

/**
 * Aide contextuelle SMART
 */
function getSmartHelp() {
    return [
        'S' => [
            'titre' => 'Specifique',
            'definition' => "L'objectif doit etre clair, precis et sans ambiguite.",
            'questions' => ['Quoi exactement ?', 'Qui est concerne ?', 'Ou ?'],
            'exemple_non' => 'Ameliorer les ventes',
            'exemple_oui' => 'Augmenter les ventes de la gamme bio de 15% en Belgique'
        ],
        'M' => [
            'titre' => 'Mesurable',
            'definition' => "L'objectif doit inclure des criteres concrets de mesure.",
            'questions' => ['Quel indicateur ?', 'Quelle valeur cible ?', 'Comment savoir si c\'est atteint ?'],
            'exemple_non' => 'Avoir plus de clients',
            'exemple_oui' => 'Acquerir 50 nouveaux clients (+25%)'
        ],
        'A' => [
            'titre' => 'Atteignable',
            'definition' => "L'objectif doit etre ambitieux mais realisable.",
            'questions' => ['Ai-je les ressources ?', 'Quelles actions concretes ?', 'Est-ce realiste ?'],
            'exemple_non' => 'Doubler le CA en 1 mois',
            'exemple_oui' => 'Augmenter le CA de 15% en formant l\'equipe commerciale'
        ],
        'R' => [
            'titre' => 'Realiste / Pertinent',
            'definition' => "L'objectif doit avoir du sens et etre coherent avec le contexte.",
            'questions' => ['Pourquoi cet objectif ?', 'Est-ce le bon moment ?', 'Quelle valeur ajoutee ?'],
            'exemple_non' => 'Apprendre le japonais (sans raison)',
            'exemple_oui' => 'Apprendre le japonais B1 pour communiquer avec nos fournisseurs de Tokyo'
        ],
        'T' => [
            'titre' => 'Temporel',
            'definition' => "L'objectif doit avoir une echeance claire.",
            'questions' => ['Pour quand ?', 'Quelles etapes intermediaires ?', 'Echeance realiste ?'],
            'exemple_non' => 'Lancer le nouveau site web bientot',
            'exemple_oui' => 'Lancer le site le 15 mars 2025, avec tests du 1er au 14 mars'
        ]
    ];
}

/**
 * Exemples par domaine
 */
function getExemplesParDomaine() {
    return [
        'professionnel' => [
            ['thematique' => 'Vente', 'objectif' => "Augmenter le panier moyen de 15% d'ici juin 2025 en formant l'equipe aux techniques de vente additionnelle."],
            ['thematique' => 'RH', 'objectif' => "Reduire le delai de recrutement de 45 a 30 jours d'ici septembre 2025 en digitalisant la preselection."],
            ['thematique' => 'Formation', 'objectif' => "Former 80% des collaborateurs aux outils IA d'ici juin 2025 via un parcours e-learning de 8h."]
        ],
        'personnel' => [
            ['thematique' => 'Sante', 'objectif' => "Courir 10 km en moins de 55 minutes d'ici mai 2025 avec 3 entrainements par semaine."],
            ['thematique' => 'Finances', 'objectif' => "Epargner 5000€ d'ici decembre 2025 en mettant 420€/mois de cote automatiquement."],
            ['thematique' => 'Apprentissage', 'objectif' => "Lire 24 livres en 2025 (2/mois) en lisant 30 minutes chaque soir."]
        ],
        'associatif' => [
            ['thematique' => 'Collecte', 'objectif' => "Collecter 15000€ lors du gala du 20 novembre 2025 en augmentant les participants de 100 a 150."],
            ['thematique' => 'Benevolat', 'objectif' => "Recruter et former 20 nouveaux benevoles d'ici septembre 2025 via 4 sessions d'information."]
        ]
    ];
}
