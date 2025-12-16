<?php
/**
 * Configuration et initialisation de la base de donnees SQLite
 * Application de formation - Cadre Logique
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_PATH', __DIR__ . '/../data/formation.db');
define('ADMIN_PASSWORD', 'formation2024'); // Mot de passe admin pour gestion des sessions

/**
 * Connexion a la base de donnees avec cache statique
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        $dbDir = dirname(DB_PATH);
        if (!file_exists($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            initDatabase($db);
        } catch (PDOException $e) {
            die("Erreur de base de donnees: " . $e->getMessage());
        }
    }
    return $db;
}

/**
 * Initialisation des tables
 */
function initDatabase($db) {
    // Table des sessions de formation
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code VARCHAR(10) UNIQUE NOT NULL,
        nom VARCHAR(255) NOT NULL,
        description TEXT,
        formateur_password VARCHAR(255),
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Table des participants
    $db->exec("CREATE TABLE IF NOT EXISTS participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        nom VARCHAR(100) NOT NULL,
        organisation VARCHAR(255),
        is_submitted INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id),
        UNIQUE(session_id, prenom, nom)
    )");

    // Table des cadres logiques
    $db->exec("CREATE TABLE IF NOT EXISTS cadre_logique (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        participant_id INTEGER NOT NULL UNIQUE,
        session_id INTEGER NOT NULL,
        titre_projet TEXT DEFAULT '',
        organisation TEXT DEFAULT '',
        zone_geo TEXT DEFAULT '',
        duree TEXT DEFAULT '',
        matrice_data TEXT DEFAULT '{}',
        completion_percent INTEGER DEFAULT 0,
        is_submitted INTEGER DEFAULT 0,
        submitted_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (participant_id) REFERENCES participants(id),
        FOREIGN KEY (session_id) REFERENCES sessions(id)
    )");

    // Creer une session par defaut si aucune n'existe
    $stmt = $db->query("SELECT COUNT(*) as count FROM sessions");
    if ($stmt->fetch()['count'] == 0) {
        $db->exec("INSERT INTO sessions (code, nom, is_active) VALUES ('DEMO01', 'Session Demo', 1)");
    }
}

/**
 * Genere un code de session unique (6 caracteres)
 */
function generateSessionCode() {
    $db = getDB();
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM sessions WHERE code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetch()['count'] > 0;
    } while ($exists);
    return $code;
}

/**
 * Verifie si une session existe et est active
 */
function getSession($code) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM sessions WHERE code = ? AND is_active = 1");
    $stmt->execute([strtoupper($code)]);
    return $stmt->fetch();
}

/**
 * Verifie si le participant est connecte
 */
function isParticipantLoggedIn() {
    return isset($_SESSION['participant_id']);
}

/**
 * Verifie si le formateur est connecte
 */
function isFormateurLoggedIn() {
    return isset($_SESSION['formateur_session_id']);
}

/**
 * Verifie si l'admin est connecte
 */
function isAdminLoggedIn() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Redirige si non connecte
 */
function requireParticipant() {
    if (!isParticipantLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function requireFormateur() {
    if (!isFormateurLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: admin_sessions.php');
        exit;
    }
}

/**
 * Obtient les infos du participant connecte
 */
function getCurrentParticipant() {
    if (!isParticipantLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, s.code as session_code, s.nom as session_nom
        FROM participants p
        JOIN sessions s ON p.session_id = s.id
        WHERE p.id = ?
    ");
    $stmt->execute([$_SESSION['participant_id']]);
    return $stmt->fetch();
}

/**
 * Obtient les stats d'une session
 */
function getSessionStats($sessionId) {
    $db = getDB();

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM participants WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $total = $stmt->fetch()['total'];

    $stmt = $db->prepare("SELECT COUNT(*) as soumis FROM participants WHERE session_id = ? AND is_submitted = 1");
    $stmt->execute([$sessionId]);
    $soumis = $stmt->fetch()['soumis'];

    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT p.id) as en_cours
        FROM participants p
        JOIN cadre_logique c ON p.id = c.participant_id
        WHERE p.session_id = ? AND p.is_submitted = 0 AND c.matrice_data != '{}'
    ");
    $stmt->execute([$sessionId]);
    $enCours = $stmt->fetch()['en_cours'];

    $nonCommence = $total - $soumis - $enCours;

    return [
        'total' => $total,
        'soumis' => $soumis,
        'en_cours' => $enCours,
        'non_commence' => max(0, $nonCommence)
    ];
}

/**
 * Calcule le pourcentage de completion d'un cadre logique
 */
function calculateCompletion($data) {
    $total = 0;
    $filled = 0;

    // En-tete (4 champs)
    $headerFields = ['titre_projet', 'organisation', 'zone_geo', 'duree'];
    foreach ($headerFields as $field) {
        $total++;
        if (!empty($data[$field])) $filled++;
    }

    // Matrice
    if (isset($data['matrice_data'])) {
        $matrice = is_string($data['matrice_data']) ? json_decode($data['matrice_data'], true) : $data['matrice_data'];

        // Objectif global (4 champs)
        if (isset($matrice['objectif_global'])) {
            foreach (['description', 'indicateurs', 'sources', 'hypotheses'] as $field) {
                $total++;
                if (!empty($matrice['objectif_global'][$field])) $filled++;
            }
        }

        // Objectif specifique (4 champs)
        if (isset($matrice['objectif_specifique'])) {
            foreach (['description', 'indicateurs', 'sources', 'hypotheses'] as $field) {
                $total++;
                if (!empty($matrice['objectif_specifique'][$field])) $filled++;
            }
        }

        // Resultats
        if (isset($matrice['resultats']) && is_array($matrice['resultats'])) {
            foreach ($matrice['resultats'] as $resultat) {
                foreach (['description', 'indicateurs', 'sources', 'hypotheses'] as $field) {
                    $total++;
                    if (!empty($resultat[$field])) $filled++;
                }
                // Activites
                if (isset($resultat['activites']) && is_array($resultat['activites'])) {
                    foreach ($resultat['activites'] as $activite) {
                        foreach (['description', 'ressources', 'budget', 'preconditions'] as $field) {
                            $total++;
                            if (!empty($activite[$field])) $filled++;
                        }
                    }
                }
            }
        }
    }

    return $total > 0 ? round(($filled / $total) * 100) : 0;
}

/**
 * Structure vide d'un cadre logique
 */
function getEmptyMatrice() {
    return [
        'objectif_global' => [
            'description' => '',
            'indicateurs' => '',
            'sources' => '',
            'hypotheses' => ''
        ],
        'objectif_specifique' => [
            'description' => '',
            'indicateurs' => '',
            'sources' => '',
            'hypotheses' => ''
        ],
        'resultats' => [
            [
                'id' => 'R1',
                'description' => '',
                'indicateurs' => '',
                'sources' => '',
                'hypotheses' => '',
                'activites' => [
                    [
                        'id' => 'A1.1',
                        'description' => '',
                        'ressources' => '',
                        'budget' => '',
                        'preconditions' => ''
                    ]
                ]
            ]
        ]
    ];
}

/**
 * Templates predefinies
 */
function getTemplates() {
    return [
        'sante_ist' => [
            'nom' => 'Sante sexuelle et prevention des IST - Auberge de jeunesse',
            'entete' => [
                'titre_projet' => 'Bien informes, bien proteges : sensibilisation a la sante sexuelle et aux IST',
                'organisation' => 'Auberge de Jeunesse [Nom]',
                'zone_geo' => 'Commune de [Nom], Belgique',
                'duree' => '12 mois'
            ],
            'matrice' => json_decode(file_get_contents(__DIR__ . '/template_sante.json'), true) ?? getEmptyMatrice()
        ]
    ];
}

/**
 * Sanitize pour affichage HTML
 */
function sanitize($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Protection .htaccess pour le dossier data
 */
$htaccessPath = dirname(DB_PATH) . '/.htaccess';
if (!file_exists($htaccessPath)) {
    file_put_contents($htaccessPath, "Deny from all\n");
}
?>
