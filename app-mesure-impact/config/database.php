<?php
/**
 * Configuration Mesure d'Impact Social
 * Utilise le systeme d'authentification partage
 */

// Charger le systeme d'authentification partage
require_once __DIR__ . '/../../shared-auth/config.php';
require_once __DIR__ . '/../../shared-auth/sessions.php';

define('APP_NAME', 'Mesure d\'Impact Social');
define('APP_COLOR', 'indigo');

/**
 * Connexion a la base de donnees locale de l'application
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        $dbPath = __DIR__ . '/../data/mesure_impact.sqlite';

        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

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
        formateur_id INTEGER,
        is_active INTEGER DEFAULT 1,
        config TEXT DEFAULT '{}',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Table des participants
    $db->exec("CREATE TABLE IF NOT EXISTS participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
        UNIQUE(session_id, user_id)
    )");

    // Table principale des reponses
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

    // Table des enonces a classifier (Etape 1)
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

    // Migration: ajouter formateur_id et is_active si colonnes differentes
    try {
        $db->exec("ALTER TABLE sessions ADD COLUMN formateur_id INTEGER");
    } catch (Exception $e) {}
    try {
        $db->exec("ALTER TABLE sessions ADD COLUMN is_active INTEGER DEFAULT 1");
    } catch (Exception $e) {}
    try {
        $db->exec("ALTER TABLE participants ADD COLUMN user_id INTEGER");
    } catch (Exception $e) {}

    // Inserer les enonces par defaut s'ils n'existent pas
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
        ['texte' => "45 jeunes ont participe aux ateliers d'expression orale", 'categorie' => 'output', 'explication' => "C'est un produit direct de l'action : le nombre de participants.", 'niveau' => 'facile', 'piege' => null],
        ['texte' => "12 ateliers de 2 heures ont ete organises", 'categorie' => 'output', 'explication' => "C'est une activite realisee, directement sous le controle de l'organisation.", 'niveau' => 'facile', 'piege' => null],
        ['texte' => "Les participants se sentent plus a l'aise pour prendre la parole en public", 'categorie' => 'outcome', 'explication' => "C'est un changement chez les beneficiaires.", 'niveau' => 'facile', 'piege' => null],
        ['texte' => "Reduction du decrochage scolaire dans le quartier", 'categorie' => 'impact', 'explication' => "C'est un changement a l'echelle de la societe.", 'niveau' => 'facile', 'piege' => null],
        ['texte' => "8 jeunes ont trouve un stage grace au reseau cree pendant le projet", 'categorie' => 'outcome', 'explication' => "C'est un changement concret dans la vie des beneficiaires.", 'niveau' => 'moyen', 'piege' => "Souvent confondu avec un output car c'est chiffre."],
        ['texte' => "Les jeunes sont davantage acteurs de leur parcours d'insertion", 'categorie' => 'outcome', 'explication' => "C'est un changement de posture chez les beneficiaires.", 'niveau' => 'moyen', 'piege' => "Souvent confondu avec un impact car formule de facon large."],
        ['texte' => "Un guide pedagogique de 50 pages a ete produit", 'categorie' => 'output', 'explication' => "C'est un produit tangible de l'action.", 'niveau' => 'facile', 'piege' => null],
        ['texte' => "75% des participants declarent avoir acquis de nouvelles competences", 'categorie' => 'outcome', 'explication' => "C'est un changement percu par les beneficiaires.", 'niveau' => 'moyen', 'piege' => "Le chiffre peut faire penser a un output."],
        ['texte' => "Amelioration de la cohesion sociale dans le quartier", 'categorie' => 'impact', 'explication' => "C'est un changement societal de long terme.", 'niveau' => 'facile', 'piege' => null],
        ['texte' => "Les parents s'impliquent davantage dans le suivi scolaire de leurs enfants", 'categorie' => 'outcome', 'explication' => "C'est un changement de comportement chez un groupe cible.", 'niveau' => 'moyen', 'piege' => null],
        ['texte' => "3 partenariats ont ete conclus avec des entreprises locales", 'categorie' => 'output', 'explication' => "C'est un resultat direct de l'action.", 'niveau' => 'moyen', 'piege' => null],
        ['texte' => "Les jeunes continuent a utiliser les techniques apprises 6 mois apres la fin du projet", 'categorie' => 'outcome', 'explication' => "C'est un changement durable chez les beneficiaires.", 'niveau' => 'difficile', 'piege' => null]
    ];

    $stmt = $db->prepare("INSERT INTO enonces_classification (session_id, texte, categorie_correcte, explication, niveau, piege, ordre) VALUES (NULL, ?, ?, ?, ?, ?, ?)");
    foreach ($enonces as $index => $enonce) {
        $stmt->execute([$enonce['texte'], $enonce['categorie'], $enonce['explication'], $enonce['niveau'], $enonce['piege'], $index + 1]);
    }
}

function getCurrentParticipant() {
    if (!isset($_SESSION['participant_id'])) {
        return null;
    }
    $db = getDB();
    $sharedDb = getSharedDB();

    $stmt = $db->prepare("SELECT p.*, s.code as session_code, s.nom as session_nom
                          FROM participants p
                          JOIN sessions s ON p.session_id = s.id
                          WHERE p.id = ?");
    $stmt->execute([$_SESSION['participant_id']]);
    $participant = $stmt->fetch();

    if ($participant && isset($participant['user_id'])) {
        $userStmt = $sharedDb->prepare("SELECT username, prenom, nom, organisation FROM users WHERE id = ?");
        $userStmt->execute([$participant['user_id']]);
        $user = $userStmt->fetch();
        if ($user) {
            $participant['prenom'] = $user['prenom'] ?? '';
            $participant['nom'] = $user['nom'] ?? '';
            $participant['organisation'] = $user['organisation'] ?? '';
            $participant['username'] = $user['username'];
        }
    }

    return $participant;
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

function getDefinitions() {
    return [
        'output' => [
            'titre' => 'Output (Produit/Realisation)',
            'definition' => "Les produits directs et quantifiables de vos activites.",
            'caracteristiques' => ['Directement sous votre controle', 'Facilement comptable', 'Ne dit rien sur les effets produits'],
            'exemples' => ['Nombre de participants', 'Nombre d\'ateliers', 'Documents produits'],
            'question_test' => "Puis-je le compter facilement ?"
        ],
        'outcome' => [
            'titre' => 'Outcome (Effet/Changement)',
            'definition' => "Les changements qui se produisent chez vos beneficiaires.",
            'caracteristiques' => ['Changement chez les personnes', 'Court, moyen ou long terme', 'Partiellement sous votre controle'],
            'exemples' => ['Nouvelles connaissances', 'Changement de comportement', 'Amelioration du bien-etre'],
            'question_test' => "Est-ce un changement chez les personnes ?"
        ],
        'impact' => [
            'titre' => 'Impact (Changement societal)',
            'definition' => "Le changement durable et a grande echelle sur la societe.",
            'caracteristiques' => ['Changement niveau societe', 'Long terme', 'Contribution partagee'],
            'exemples' => ['Reduction de la pauvrete', 'Amelioration de la cohesion sociale'],
            'question_test' => "Est-ce un changement pour la societe ?"
        ]
    ];
}

function getMethodesCollecte() {
    return [
        'questionnaire' => ['nom' => 'Questionnaire', 'icone' => '📋', 'description' => 'Serie de questions standardisees'],
        'echelle_auto_evaluation' => ['nom' => 'Echelle d\'auto-evaluation', 'icone' => '📊', 'description' => 'Le participant evalue son niveau'],
        'entretien' => ['nom' => 'Entretien individuel', 'icone' => '🎤', 'description' => 'Conversation approfondie'],
        'focus_group' => ['nom' => 'Focus group', 'icone' => '👥', 'description' => 'Discussion de groupe animee'],
        'observation' => ['nom' => 'Observation directe', 'icone' => '👁️', 'description' => 'Observer les comportements'],
        'journal_portfolio' => ['nom' => 'Journal de bord', 'icone' => '📓', 'description' => 'Documentation par les participants'],
        'recit_temoignage' => ['nom' => 'Temoignage', 'icone' => '📖', 'description' => 'Recit structure'],
        'donnees_existantes' => ['nom' => 'Donnees existantes', 'icone' => '📁', 'description' => 'Utiliser des donnees deja collectees'],
        'photo_video' => ['nom' => 'Photo / Video', 'icone' => '📷', 'description' => 'Documentation en images']
    ];
}

function getCriteresIndicateur() {
    return [
        ['nom' => 'Pertinent', 'description' => "L'indicateur mesure bien ce qu'on veut savoir"],
        ['nom' => 'Faisable', 'description' => "La collecte est realiste avec vos moyens"],
        ['nom' => 'Fiable', 'description' => "L'indicateur donne des resultats coherents"],
        ['nom' => 'Utile', 'description' => "L'indicateur aide a prendre des decisions"],
        ['nom' => 'Sensible', 'description' => "L'indicateur peut detecter un changement"]
    ];
}
