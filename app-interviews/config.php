<?php
/**
 * Configuration — Préparation à l'interview journalistique
 */

require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Préparation à l\'interview');
define('APP_COLOR', 'rose');
define('DB_PATH', __DIR__ . '/data/interviews.db');

function getDB() {
    static $db = null;
    if ($db === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec("PRAGMA foreign_keys = ON");
        initDatabase($db);
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

    $db->exec("CREATE TABLE IF NOT EXISTS fiches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER NOT NULL,
        sujet TEXT DEFAULT '',
        message1 TEXT DEFAULT '',
        message2 TEXT DEFAULT '',
        message3 TEXT DEFAULT '',
        anecdote TEXT DEFAULT '',
        a_eviter TEXT DEFAULT '',
        is_submitted INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, session_id)
    )");
}

function getJournalisteProfiles() {
    return [
        'A' => [
            'label' => 'Profil A',
            'nom' => 'Journaliste Bienveillant·e',
            'couleur' => 'green',
            'emoji' => '😊',
            'posture' => "Prends des notes, souris, hoche la tête. Laisse ton interlocuteur·trice parler sans l'interrompre. Montre de l'intérêt sincère pour ce qu'il·elle dit.",
            'rappel' => "Ton rôle n'est pas de piéger ton collègue, mais de l'aider à s'entraîner.",
            'questions' => [
                "Pouvez-vous vous présenter et nous expliquer votre rôle dans l'organisation ?",
                "Quelle est la principale réalisation de votre organisation cette année ?",
                "Quels sont vos projets et priorités pour les prochains mois ?",
                "Comment travaillez-vous avec d'autres organisations partenaires ?",
                "Qu'est-ce qui vous rend fier·ère de votre travail au quotidien ?",
                "Y a-t-il un message particulier que vous souhaiteriez faire passer à nos lecteurs·trices ?",
            ],
        ],
        'B' => [
            'label' => 'Profil B',
            'nom' => 'Journaliste Pressé·e',
            'couleur' => 'amber',
            'emoji' => '⏱️',
            'posture' => "Regarde ton téléphone entre les questions. Coupe la parole après 30 secondes si la réponse s'éternise. Enchaîne les questions sans laisser de temps mort.",
            'rappel' => "Ton rôle n'est pas de piéger ton collègue, mais de l'aider à s'entraîner.",
            'questions' => [
                "Qu'est-ce que vous faites concrètement ? En deux phrases.",
                "En quoi est-ce différent de ce qui existe déjà ?",
                "Combien ça coûte — ou rapporte — à votre organisation ?",
                "Les résultats, c'est quoi exactement ? Des chiffres ?",
                "Dernier mot — vite.",
            ],
        ],
        'C' => [
            'label' => 'Profil C',
            'nom' => 'Journaliste Sceptique',
            'couleur' => 'red',
            'emoji' => '🤨',
            'posture' => "Garde le silence 5 secondes après chaque réponse avant de poser la suivante. Pose des questions courtes et directes. Prends des notes avec un air dubitatif.",
            'rappel' => "Ton rôle n'est pas de piéger ton collègue, mais de l'aider à s'entraîner.",
            'questions' => [
                "Vous dites que c'est important. Mais pourquoi les gens devraient-ils s'en préoccuper ?",
                "Ces chiffres, d'où viennent-ils exactement ?",
                "Vos concurrents font la même chose. En quoi êtes-vous différent·e ?",
                "Des critiques disent que votre approche est inefficace. Que répondez-vous ?",
                "Pouvez-vous réellement prouver l'impact de vos actions ?",
                "[5 secondes de silence] Vous n'avez rien à ajouter ?",
            ],
        ],
    ];
}

function getAideMemoireItems() {
    return [
        [
            'titre' => 'La technique du pont',
            'texte' => 'Si une question vous met mal à l\'aise, faites un "pont" : "C\'est une bonne question. Ce que je voudrais souligner, c\'est que..." puis revenez à vos messages clés.',
        ],
        [
            'titre' => 'Je ne sais pas',
            'texte' => 'Dire "je ne sais pas" est acceptable — mais redirigez : "Je n\'ai pas cette information sous la main, mais ce que je peux vous dire, c\'est..."',
        ],
        [
            'titre' => 'Les questions pièges',
            'texte' => 'Ne répétez jamais un mot négatif dans votre réponse. Reformulez d\'abord : "Ce que vous demandez en réalité, c\'est..." puis répondez positivement.',
        ],
        [
            'titre' => 'Le silence',
            'texte' => 'Un silence après votre réponse est normal. Ne remplissez pas le vide à tout prix — vous risquez de dire quelque chose de non préparé. Attendez la prochaine question.',
        ],
    ];
}
