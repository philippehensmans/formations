<?php
// Configuration et initialisation de la base de données pour l'app Empreinte Carbone

function getDB() {
    $dbPath = __DIR__ . '/database.sqlite';
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Table des scénarios (un par session, défini par le formateur)
    $db->exec("CREATE TABLE IF NOT EXISTS scenarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        option1_name VARCHAR(100) DEFAULT '🚀 IA Puissante (Cloud)',
        option1_desc TEXT DEFAULT 'Utilisation d''un grand modèle d''IA via API cloud (GPT-4, Claude, etc.)',
        option2_name VARCHAR(100) DEFAULT '⚖️ IA Légère (Locale)',
        option2_desc TEXT DEFAULT 'Modèle d''IA plus petit, exécuté localement ou solution hybride',
        option3_name VARCHAR(100) DEFAULT '👥 Sans IA (Humain)',
        option3_desc TEXT DEFAULT 'Approche traditionnelle sans intelligence artificielle',
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Table des votes des participants
    $db->exec("CREATE TABLE IF NOT EXISTS votes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        scenario_id INTEGER NOT NULL,
        participant_id INTEGER NOT NULL,
        option_number INTEGER NOT NULL,
        impact INTEGER DEFAULT 0,
        qualite INTEGER DEFAULT 0,
        temps INTEGER DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(scenario_id, participant_id, option_number)
    )");

    return $db;
}

// Obtenir ou créer le scénario actif pour une session
function getActiveScenario($db, $sessionId) {
    $stmt = $db->prepare("SELECT * FROM scenarios WHERE session_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sessionId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtenir tous les votes pour un scénario
function getVotes($db, $scenarioId) {
    $stmt = $db->prepare("SELECT * FROM votes WHERE scenario_id = ?");
    $stmt->execute([$scenarioId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculer les moyennes des votes par option
function calculateAverages($db, $scenarioId) {
    $results = [];

    for ($opt = 1; $opt <= 3; $opt++) {
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as voters,
                AVG(impact) as avg_impact,
                AVG(qualite) as avg_qualite,
                AVG(temps) as avg_temps
            FROM votes
            WHERE scenario_id = ? AND option_number = ? AND (impact > 0 OR qualite > 0 OR temps > 0)
        ");
        $stmt->execute([$scenarioId, $opt]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $results[$opt] = [
            'voters' => (int)$row['voters'],
            'impact' => round($row['avg_impact'] ?: 0, 1),
            'qualite' => round($row['avg_qualite'] ?: 0, 1),
            'temps' => round($row['avg_temps'] ?: 0, 1),
            'score_env' => round((4 - ($row['avg_impact'] ?: 0)) * 33.33, 0), // Inverser: moins d'impact = meilleur score
            'score_global' => 0
        ];

        // Score global = qualité + temps - impact (normalisé)
        if ($row['voters'] > 0) {
            $results[$opt]['score_global'] = round(
                (($row['avg_qualite'] ?: 0) / 5 * 40) +
                (($row['avg_temps'] ?: 0) / 3 * 30) +
                ((4 - ($row['avg_impact'] ?: 0)) / 3 * 30)
            , 0);
        }
    }

    return $results;
}
