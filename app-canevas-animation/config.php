<?php
/**
 * Configuration Canevas d'animation IA (90 min)
 * Utilise le système d'authentification partagé
 */

require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Canevas d\'animation IA');
define('APP_COLOR', 'indigo');
define('DB_PATH', __DIR__ . '/data/canevas_animation.db');

function getDB() {
    static $db = null;
    if ($db === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            initDatabase($db);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }
    return $db;
}

function initDatabase($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code VARCHAR(10) UNIQUE NOT NULL,
        nom VARCHAR(255) NOT NULL,
        formateur_id INTEGER,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        prenom VARCHAR(100),
        nom VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id),
        UNIQUE(session_id, user_id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS canevas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER,
        data TEXT DEFAULT '{}',
        is_shared INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, session_id)
    )");
}

function sanitize($input) { return h($input); }

/**
 * Les 7 points d'attention à transmettre
 */
function getPointsAttention() {
    return [
        'biais' => [
            'titre' => 'Biais',
            'description' => 'L\'IA reflète les données d\'entraînement. Démo : 6 images de PDG, 6 de secrétaires.',
            'modalite' => 'Image / texte',
            'icon' => "\xE2\x9A\x96\xEF\xB8\x8F",
            'color' => 'amber'
        ],
        'hallucinations' => [
            'titre' => 'Hallucinations',
            'description' => 'L\'IA invente quand elle ne sait pas. Démo : biographie d\'une personne fictive.',
            'modalite' => 'Texte',
            'icon' => "\xF0\x9F\x92\xAD",
            'color' => 'purple'
        ],
        'donnees_perso' => [
            'titre' => 'Données personnelles',
            'description' => 'Règle de la « carte postale » : rien que tu n\'écrirais sur une carte postale.',
            'modalite' => 'Discussion / quiz',
            'icon' => "\xF0\x9F\x94\x90",
            'color' => 'red'
        ],
        'dependance' => [
            'titre' => 'Dépendance cognitive',
            'description' => 'Analogie du muscle : si tu ne calcules plus, tu perds en math.',
            'modalite' => 'Débat court',
            'icon' => "\xF0\x9F\xA7\xA0",
            'color' => 'blue'
        ],
        'manipulation' => [
            'titre' => 'Manipulation / deepfakes',
            'description' => 'Voix clonées, faux contenus. Démo voix générée 30 sec.',
            'modalite' => 'Audio / vidéo',
            'icon' => "\xF0\x9F\x8E\xAD",
            'color' => 'pink'
        ],
        'sante_mentale' => [
            'titre' => 'Santé mentale',
            'description' => 'Pas un thérapeute. Où aller : 113, Télé-Accueil 107.',
            'modalite' => 'Information',
            'icon' => "\xE2\x9D\xA4\xEF\xB8\x8F",
            'color' => 'rose'
        ],
        'environnement' => [
            'titre' => 'Environnement et CGU',
            'description' => 'Consommation énergétique réelle. Pourquoi 13+ / 18+.',
            'modalite' => 'Information',
            'icon' => "\xF0\x9F\x8C\xB1",
            'color' => 'green'
        ],
    ];
}

/**
 * Publics cibles
 */
function getPublics() {
    return [
        'inferieur' => 'Secondaire inférieur (12-13 ans, 1ère-2e secondaire)',
        'moyen' => 'Secondaire moyen (14-15 ans, 3e secondaire)',
        'superieur' => 'Secondaire supérieur (16-18 ans, 4e à Rhéto)',
        'mixte' => 'Public mixte / atypique',
    ];
}

/**
 * Modalités d'évaluation à chaud
 */
function getModalitesEval() {
    return [
        'tour_table' => 'Tour de table « 1 mot que je retiens »',
        'post_it' => 'Post-it 3 questions (retiens / fais différemment / reste flou)',
        'qr_form' => 'QR code Google Forms 3 questions',
        'vote' => 'Vote à main levée sur 3 affirmations',
    ];
}

/**
 * Formats de créneau disponibles
 */
function getFormats() {
    return [
        '60' => '60 minutes (version courte)',
        '90' => '90 minutes (référence)',
        '120' => '120 minutes (version longue)',
    ];
}
