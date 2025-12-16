<?php
session_start();

// Configuration base de donnees SQLite
$dbPath = __DIR__ . '/data/agile.db';
$dbDir = dirname($dbPath);

// Creer le dossier data s'il n'existe pas
if (!file_exists($dbDir)) {
    mkdir($dbDir, 0755, true);
}

// Connexion a la base de donnees
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Creer les tables si elles n'existent pas
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            is_admin INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            project_name TEXT DEFAULT '',
            team_name TEXT DEFAULT '',
            cards TEXT DEFAULT '[]',
            user_stories TEXT DEFAULT '[]',
            retrospective TEXT DEFAULT '{\"good\":[],\"improve\":[],\"actions\":[]}',
            sprint TEXT DEFAULT '{\"number\":1,\"start\":\"\",\"end\":\"\",\"goal\":\"\"}',
            is_shared INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
    ");

    // Creer un admin par defaut s'il n'existe pas
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE is_admin = 1");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password, is_admin) VALUES ('formateur', '$adminPassword', 1)");
    }
} catch (PDOException $e) {
    die("Erreur de base de donnees: " . $e->getMessage());
}

// Fonctions utilitaires
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: index.php');
        exit;
    }
}

function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
