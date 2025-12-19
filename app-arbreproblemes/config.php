<?php
/**
 * Configuration et connexion à la base de données SQLite
 * Arbre à Problèmes - Application multi-utilisateurs
 */

session_start();

// Configuration
define('APP_NAME', 'Arbre à Problèmes');
define('DB_PATH', __DIR__ . '/data/arbre_problemes.db');

// Connexion à la base de données
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            initDatabase($db);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }
    return $db;
}

// Initialisation des tables
function initDatabase($db) {
    // Table des utilisateurs
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        is_admin INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Table des arbres (données des participants)
    $db->exec("CREATE TABLE IF NOT EXISTS arbres (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        nom_projet VARCHAR(255),
        participants VARCHAR(255),
        probleme_central TEXT,
        objectif_central TEXT,
        consequences TEXT,
        causes TEXT,
        objectifs TEXT,
        moyens TEXT,
        is_shared INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Créer un compte admin par défaut si aucun n'existe
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 1");
    $result = $stmt->fetch();
    if ($result['count'] == 0) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password, is_admin) VALUES ('formateur', '$adminPassword', 1)");
    }
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
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php');
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email, is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
