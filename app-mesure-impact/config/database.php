<?php
// Configuration et initialisation de la base de données SQLite

function getDB() {
    static $db = null;
    if ($db === null) {
        $dbPath = __DIR__ . '/../data/mesure_impact.sqlite';
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        initDB($db);
    }
    return $db;
}

function initDB($db) {
    // Table des sessions de formation
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code VARCHAR(10) UNIQUE NOT NULL,
        nom VARCHAR(255) NOT NULL,
        formateur_nom VARCHAR(100),
        mot_de_passe VARCHAR(255),
        active INTEGER DEFAULT 1,
        config TEXT DEFAULT '{}',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Table des participants
    $db->exec("CREATE TABLE IF NOT EXISTS participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        nom VARCHAR(100) NOT NULL,
        organisation VARCHAR(255),
        email VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME,
        FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
        UNIQUE(session_id, prenom, nom)
    )");

    // Table principale des réponses
    $db->exec("CREATE TABLE IF NOT EXISTS mesure_impact (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        participant_id INTEGER NOT NULL UNIQUE,
        session_id INTEGER NOT NULL,
        etape_courante INTEGER DEFAULT 1,
        etape1_classification TEXT DEFAULT '{}',
        etape2_theorie_changement TEXT DEFAULT '{}',
        etape3_indicateurs TEXT DEFAULT '{}',
        etape4_plan_collecte TEXT DEFAULT '{}',
        etape5_synthese TEXT DEFAULT '{}',
        completion_percent INTEGER DEFAULT 0,
        is_submitted INTEGER DEFAULT 0,
        submitted_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
        FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
    )");

    // Table des énoncés à classifier (Étape 1)
    $db->exec("CREATE TABLE IF NOT EXISTS enonces_classification (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER,
        texte TEXT NOT NULL,
        categorie_correcte VARCHAR(20) NOT NULL,
        explication TEXT,
        niveau VARCHAR(20) DEFAULT 'standard',
        piege TEXT,
        ordre INTEGER NOT NULL,
        actif INTEGER DEFAULT 1,
        FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
    )");

    // Insérer les énoncés par défaut s'ils n'existent pas
    $count = $db->query("SELECT COUNT(*) FROM enonces_classification WHERE session_id IS NULL")->fetchColumn();
    if ($count == 0) {
        insertDefaultEnonces($db);
    }

    // Index pour performances
    $db->exec("CREATE INDEX IF NOT EXISTS idx_participants_session ON participants(session_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_mesure_impact_participant ON mesure_impact(participant_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_mesure_impact_session ON mesure_impact(session_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_enonces_session ON enonces_classification(session_id)");
}

function insertDefaultEnonces($db) {
    $enonces = [
        [
            'texte' => "45 jeunes ont participé aux ateliers d'expression orale",
            'categorie' => 'output',
            'explication' => "C'est un produit direct de l'action : le nombre de participants. Cela ne dit rien sur ce qu'ils en ont retiré.",
            'niveau' => 'facile',
            'piege' => null
        ],
        [
            'texte' => "12 ateliers de 2 heures ont été organisés",
            'categorie' => 'output',
            'explication' => "C'est une activité réalisée, directement sous le contrôle de l'organisation.",
            'niveau' => 'facile',
            'piege' => null
        ],
        [
            'texte' => "Les participants se sentent plus à l'aise pour prendre la parole en public",
            'categorie' => 'outcome',
            'explication' => "C'est un changement chez les bénéficiaires (leur ressenti, leur confiance). C'est un effet de l'action.",
            'niveau' => 'facile',
            'piege' => null
        ],
        [
            'texte' => "Réduction du décrochage scolaire dans le quartier",
            'categorie' => 'impact',
            'explication' => "C'est un changement à l'échelle de la société/du quartier, auquel l'action contribue mais qu'elle ne peut pas produire seule.",
            'niveau' => 'facile',
            'piege' => null
        ],
        [
            'texte' => "8 jeunes ont trouvé un stage grâce au réseau créé pendant le projet",
            'categorie' => 'outcome',
            'explication' => "C'est un changement concret dans la vie des bénéficiaires (ils ont trouvé un stage). Ce n'est pas un output car ce n'est pas directement produit par l'organisation.",
            'niveau' => 'moyen',
            'piege' => "Souvent confondu avec un output car c'est chiffré. Mais c'est bien un changement de situation pour les jeunes."
        ],
        [
            'texte' => "Les jeunes sont davantage acteurs de leur parcours d'insertion",
            'categorie' => 'outcome',
            'explication' => "C'est un changement de posture chez les bénéficiaires. C'est un outcome de long terme (empowerment).",
            'niveau' => 'moyen',
            'piege' => "Souvent confondu avec un impact car formulé de façon large. Mais ça reste un changement chez les personnes accompagnées."
        ],
        [
            'texte' => "Un guide pédagogique de 50 pages a été produit",
            'categorie' => 'output',
            'explication' => "C'est un produit tangible de l'action, directement sous le contrôle de l'organisation.",
            'niveau' => 'facile',
            'piege' => null
        ],
        [
            'texte' => "75% des participants déclarent avoir acquis de nouvelles compétences",
            'categorie' => 'outcome',
            'explication' => "C'est un changement perçu par les bénéficiaires. Le fait que ce soit chiffré (75%) n'en fait pas un output.",
            'niveau' => 'moyen',
            'piege' => "Le chiffre peut faire penser à un output, mais on mesure ici un changement chez les personnes."
        ],
        [
            'texte' => "Amélioration de la cohésion sociale dans le quartier",
            'categorie' => 'impact',
            'explication' => "C'est un changement sociétal de long terme, qui dépasse les seuls bénéficiaires directs.",
            'niveau' => 'facile',
            'piege' => null
        ],
        [
            'texte' => "Les parents s'impliquent davantage dans le suivi scolaire de leurs enfants",
            'categorie' => 'outcome',
            'explication' => "C'est un changement de comportement chez un groupe cible (les parents). C'est un effet indirect de l'action.",
            'niveau' => 'moyen',
            'piege' => null
        ],
        [
            'texte' => "3 partenariats ont été conclus avec des entreprises locales",
            'categorie' => 'output',
            'explication' => "C'est un résultat direct de l'action de l'organisation (un partenariat signé). Cela ne dit rien sur les effets de ces partenariats.",
            'niveau' => 'moyen',
            'piege' => null
        ],
        [
            'texte' => "Les jeunes continuent à utiliser les techniques apprises 6 mois après la fin du projet",
            'categorie' => 'outcome',
            'explication' => "C'est un changement durable chez les bénéficiaires : ils ont intégré les apprentissages dans leur vie.",
            'niveau' => 'difficile',
            'piege' => null
        ]
    ];

    $stmt = $db->prepare("INSERT INTO enonces_classification (session_id, texte, categorie_correcte, explication, niveau, piege, ordre) VALUES (NULL, ?, ?, ?, ?, ?, ?)");

    foreach ($enonces as $index => $enonce) {
        $stmt->execute([
            $enonce['texte'],
            $enonce['categorie'],
            $enonce['explication'],
            $enonce['niveau'],
            $enonce['piege'],
            $index + 1
        ]);
    }
}

function generateSessionCode() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function getCurrentParticipant() {
    if (!isset($_SESSION['participant_id'])) {
        return null;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom
                          FROM participants p
                          JOIN sessions s ON p.session_id = s.id
                          WHERE p.id = ?");
    $stmt->execute([$_SESSION['participant_id']]);
    return $stmt->fetch();
}

function getOrCreateMesureImpact($participantId, $sessionId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM mesure_impact WHERE participant_id = ?");
    $stmt->execute([$participantId]);
    $mesure = $stmt->fetch();

    if (!$mesure) {
        $stmt = $db->prepare("INSERT INTO mesure_impact (participant_id, session_id) VALUES (?, ?)");
        $stmt->execute([$participantId, $sessionId]);
        $stmt = $db->prepare("SELECT * FROM mesure_impact WHERE participant_id = ?");
        $stmt->execute([$participantId]);
        $mesure = $stmt->fetch();
    }

    return $mesure;
}

function getEnonces($sessionId = null) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM enonces_classification WHERE (session_id = ? OR session_id IS NULL) AND actif = 1 ORDER BY ordre");
    $stmt->execute([$sessionId]);
    return $stmt->fetchAll();
}

// Définitions pour l'aide contextuelle
function getDefinitions() {
    return [
        'output' => [
            'titre' => 'Output (Produit/Réalisation)',
            'definition' => "Les produits directs et quantifiables de vos activités. Ce que vous avez fait ou produit.",
            'caracteristiques' => [
                'Directement sous votre contrôle',
                'Facilement comptable',
                'Ne dit rien sur les effets produits'
            ],
            'exemples' => [
                'Nombre de participants aux activités',
                'Nombre d\'ateliers organisés',
                'Documents/supports produits',
                'Heures de formation dispensées'
            ],
            'question_test' => "Puis-je le compter facilement ? Est-ce quelque chose que j'ai directement produit ou réalisé ?",
            'verbes' => ['organiser', 'produire', 'distribuer', 'former', 'accueillir']
        ],
        'outcome' => [
            'titre' => 'Outcome (Effet/Changement)',
            'definition' => "Les changements qui se produisent chez vos bénéficiaires grâce à votre action.",
            'caracteristiques' => [
                'Changement chez les personnes',
                'Court, moyen ou long terme',
                'Partiellement sous votre contrôle',
                'Nécessite d\'interroger les bénéficiaires'
            ],
            'exemples' => [
                'Nouvelles connaissances acquises',
                'Changement de comportement',
                'Développement de compétences',
                'Amélioration du bien-être'
            ],
            'question_test' => "Est-ce un changement chez les personnes ? Dois-je leur demander ou les observer pour le savoir ?",
            'verbes' => ['comprendre', 'adopter', 'développer', 'acquérir', 'renforcer']
        ],
        'impact' => [
            'titre' => 'Impact (Changement sociétal)',
            'definition' => "Le changement durable et à grande échelle sur la société, auquel votre action contribue.",
            'caracteristiques' => [
                'Changement au niveau société/territoire',
                'Long terme (plusieurs années)',
                'Contribution partagée avec d\'autres',
                'Difficile à attribuer à une seule action'
            ],
            'exemples' => [
                'Réduction de la pauvreté',
                'Amélioration de la cohésion sociale',
                'Meilleure insertion des jeunes',
                'Renforcement de la démocratie locale'
            ],
            'question_test' => "Est-ce un changement pour la société ? D'autres acteurs y contribuent-ils ?",
            'verbes' => ['contribuer à', 'participer à', 'réduire (niveau sociétal)']
        ]
    ];
}

// Méthodes de collecte de données
function getMethodesCollecte() {
    return [
        'questionnaire' => [
            'nom' => 'Questionnaire',
            'icone' => '📋',
            'description' => 'Série de questions standardisées (papier ou en ligne)',
            'adapte_pour' => ['Indicateurs quantitatifs', 'Grands groupes', 'Comparaison avant/après'],
            'temps_moyen' => '10-15 min/répondant',
            'outils' => ['Google Forms', 'Framaforms', 'Microsoft Forms']
        ],
        'echelle_auto_evaluation' => [
            'nom' => 'Échelle d\'auto-évaluation',
            'icone' => '📊',
            'description' => 'Le participant évalue lui-même son niveau sur une échelle',
            'adapte_pour' => ['Évolution perçue', 'Confiance, bien-être', 'Comparaison rapide'],
            'temps_moyen' => '2-5 min',
            'outils' => ['Fiche papier', 'Mentimeter', 'Wooclap']
        ],
        'entretien' => [
            'nom' => 'Entretien individuel',
            'icone' => '🎤',
            'description' => 'Conversation approfondie en face-à-face',
            'adapte_pour' => ['Comprendre en profondeur', 'Témoignages riches', 'Sujets sensibles'],
            'temps_moyen' => '30-60 min + retranscription',
            'outils' => ['Guide d\'entretien', 'Dictaphone']
        ],
        'focus_group' => [
            'nom' => 'Focus group',
            'icone' => '👥',
            'description' => 'Discussion de groupe animée (6-10 participants)',
            'adapte_pour' => ['Perceptions collectives', 'Faire émerger des idées', 'Dynamiques de groupe'],
            'temps_moyen' => '1h à 1h30',
            'outils' => ['Salle + paperboard', 'Post-it', 'Zoom/Teams']
        ],
        'observation' => [
            'nom' => 'Observation directe',
            'icone' => '👁️',
            'description' => 'Observer les comportements sans poser de questions',
            'adapte_pour' => ['Comportements réels', 'Jeunes enfants', 'Compétences pratiques'],
            'temps_moyen' => 'Variable',
            'outils' => ['Grille d\'observation', 'Checklist']
        ],
        'journal_portfolio' => [
            'nom' => 'Journal de bord / Portfolio',
            'icone' => '📓',
            'description' => 'Les participants documentent eux-mêmes leur parcours',
            'adapte_pour' => ['Suivi sur la durée', 'Projets créatifs', 'Apprentissage'],
            'temps_moyen' => '5-10 min/entrée',
            'outils' => ['Carnet papier', 'Padlet', 'Blog']
        ],
        'recit_temoignage' => [
            'nom' => 'Témoignage / Récit de changement',
            'icone' => '📖',
            'description' => 'Récit structuré d\'une personne sur son évolution',
            'adapte_pour' => ['Illustrer l\'impact', 'Communication externe', 'Histoires inspirantes'],
            'temps_moyen' => '30-45 min + mise en forme',
            'outils' => ['Guide de récit', 'Méthode Most Significant Change']
        ],
        'donnees_existantes' => [
            'nom' => 'Analyse de données existantes',
            'icone' => '📁',
            'description' => 'Utiliser des données déjà collectées',
            'adapte_pour' => ['Outputs', 'Suivi de présence', 'Comparaisons historiques'],
            'temps_moyen' => 'Variable',
            'outils' => ['Excel', 'Vos fichiers de suivi']
        ],
        'photo_video' => [
            'nom' => 'Photo / Vidéo participative',
            'icone' => '📷',
            'description' => 'Les participants documentent en images',
            'adapte_pour' => ['Rendre visible l\'invisible', 'Publics peu à l\'aise avec l\'écrit'],
            'temps_moyen' => 'Variable',
            'outils' => ['Smartphones', 'Méthode Photovoice']
        ]
    ];
}

// Critères d'un bon indicateur
function getCriteresIndicateur() {
    return [
        ['nom' => 'Pertinent', 'description' => "L'indicateur mesure bien ce qu'on veut savoir"],
        ['nom' => 'Faisable', 'description' => "La collecte est réaliste avec vos moyens"],
        ['nom' => 'Fiable', 'description' => "L'indicateur donne des résultats cohérents"],
        ['nom' => 'Utile', 'description' => "L'indicateur aide à prendre des décisions"],
        ['nom' => 'Sensible', 'description' => "L'indicateur peut détecter un changement"]
    ];
}
