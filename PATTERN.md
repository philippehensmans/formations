# Pattern des applications de formation

Ce document décrit la structure canonique d'une application dans le dépôt `formations`. Il sert de référence pour créer ou refactoriser une app.

---

## 1. Structure des fichiers

```
app-xxx/
├── index.php          # Point d'entrée : redirige vers login ou app
├── login.php          # Connexion (délègue au template partagé)
├── register.php       # Inscription (délègue au template partagé)
├── logout.php         # Déconnexion (une ligne)
├── config.php         # Config locale + DB SQLite + wrappers
├── app.php            # Interface participant (HTML + JS inline)
├── formateur.php      # Tableau de bord formateur (délègue au template)
├── view.php           # Vue formateur d'un participant (?id=X)
├── session-view.php   # Synthèse agrégée d'une session (?id=X)
├── api/
│   ├── save.php       # POST JSON → sauvegarde en DB
│   ├── load.php       # GET → renvoie les données du participant
│   └── submit.php     # POST → marque comme soumis
└── data/
    └── xxx.db         # SQLite auto-créé (ignoré par git via */data/)
```

---

## 2. Authentification partagée (`shared-auth/`)

Toutes les apps utilisent **le même système d'auth** situé dans `shared-auth/`. Ne jamais réécrire l'auth dans une app.

### Fichiers à inclure

| Fichier | Contient |
|---|---|
| `shared-auth/config.php` | Connexion DB users, fonctions auth, helpers |
| `shared-auth/sessions.php` | Gestion des sessions de formation |
| `shared-auth/lang.php` | Traductions, sélecteur de langue |

### Fonctions clés disponibles après inclusion

```php
// Auth
isLoggedIn()                    // bool
isFormateur()                   // bool : formateur ou admin
getLoggedUser()                 // array : id, username, prenom, nom, email
login($user)                    // démarre la session PHP
logout()                        // détruit la session
requireFormateur()              // redirige si pas formateur

// Sessions de formation
validateCurrentSession($db)     // valide la session locale, retourne $sessionId ou false
ensureParticipant($db, $sessionId, $user)  // crée le participant local si absent

// Helpers HTML
h($str)                         // htmlspecialchars()
renderLanguageSelector($class)  // <select> de langue
renderHomeLink($class)          // lien retour vers ../
renderLanguageScript()          // <script> pour changer la langue

// Traductions
t('clé.sous-clé')              // string traduit selon langue courante
getCurrentLanguage()            // 'fr' | 'en' | 'es' | 'sl' | 'de'
```

---

## 3. `config.php` — template minimal

```php
<?php
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/sessions.php';
require_once __DIR__ . '/../shared-auth/lang.php';

define('APP_NAME', 'Nom de l\'application');
define('APP_COLOR', 'indigo');          // couleur Tailwind (blue, purple, green…)
define('DB_PATH', __DIR__ . '/data/xxx.db');

function getDB() {
    static $db = null;
    if ($db === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        initDatabase($db);
    }
    return $db;
}

function initDatabase($db) {
    // Table obligatoire : sessions (synchronisée avec shared-auth)
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code VARCHAR(10) UNIQUE NOT NULL,
        nom VARCHAR(255) NOT NULL,
        formateur_id INTEGER,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Table obligatoire : participants (lien user ↔ session locale)
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

    // Table métier propre à l'app
    $db->exec("CREATE TABLE IF NOT EXISTS mon_contenu (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER,
        data TEXT DEFAULT '{}',
        is_submitted INTEGER DEFAULT 0,
        submitted_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, session_id)
    )");
}

// Alias local de h() si besoin
function sanitize($s) { return h($s); }
```

---

## 4. Fichiers délégués au template partagé

Ces 3 fichiers sont quasi identiques dans toutes les apps :

### `login.php`
```php
<?php
$appName  = 'Nom de l\'application';
$appColor = 'indigo';
$redirectAfterLogin = 'index.php';
$showRegister = true;

require_once __DIR__ . '/config.php';
$db = getDB();
require_once __DIR__ . '/../shared-auth/login-template.php';
```

### `register.php`
```php
<?php
$appName  = 'Nom de l\'application';
$appColor = 'indigo';
require_once __DIR__ . '/../shared-auth/register-template.php';
```

### `logout.php`
```php
<?php
require_once __DIR__ . '/config.php';
logout();
header('Location: login.php');
exit;
```

### `formateur.php`
```php
<?php
$appName  = 'Nom de l\'application';
$appColor = 'indigo';
$appKey   = 'app-xxx';          // nom du dossier

require_once __DIR__ . '/config.php';
$db = getDB();
require_once __DIR__ . '/../shared-auth/formateur-template.php';
```

Le template formateur gère automatiquement :
- Connexion du formateur
- Liste des sessions
- Liste des participants avec lien vers `view.php?id=X`
- Lien vers `session-view.php?id=X` si le fichier existe

---

## 5. `index.php`

```php
<?php
require_once __DIR__ . '/config.php';

if (isLoggedIn() && isset($_SESSION['current_session_id'])) {
    header('Location: app.php');
    exit;
}
header('Location: login.php');
exit;
```

---

## 6. `app.php` — structure HTML type

```php
<?php
require_once __DIR__ . '/config.php';

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    header('Location: login.php'); exit;
}

$db      = getDB();
$user    = getLoggedUser();
$sessionId  = validateCurrentSession($db);
if (!$sessionId) { header('Location: login.php'); exit; }
$sessionNom = $_SESSION['current_session_nom'] ?? '';

// Charger ou créer les données du participant
$stmt = $db->prepare("SELECT * FROM mon_contenu WHERE user_id = ? AND session_id = ?");
$stmt->execute([$user['id'], $sessionId]);
$row = $stmt->fetch();
if (!$row) {
    $db->prepare("INSERT INTO mon_contenu (user_id, session_id) VALUES (?, ?)")->execute([$user['id'], $sessionId]);
    $row = $db->query("SELECT * FROM mon_contenu WHERE user_id = {$user['id']} AND session_id = $sessionId")->fetch();
}

$data        = json_decode($row['data'] ?? '{}', true) ?: [];
$isSubmitted = ($row['is_submitted'] ?? 0) == 1;
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nom App — <?= h($user['prenom']) ?> <?= h($user['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,...">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* CSS inline uniquement — pas de fichier .css externe */
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- Barre utilisateur (sticky, toujours visible) -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-3 shadow-lg sticky top-0 z-50 no-print">
        <div class="max-w-5xl mx-auto flex flex-wrap justify-between items-center gap-3">
            <div>
                <span class="font-medium"><?= h($user['prenom']) ?> <?= h($user['nom']) ?></span>
                <span class="text-indigo-200 text-sm ml-2"><?= h($sessionNom) ?></span>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <?= renderLanguageSelector('text-sm bg-white/10 text-white px-2 py-1 rounded border border-white/20') ?>
                <button onclick="manualSave()"
                    class="text-sm bg-green-500 hover:bg-green-400 px-4 py-1.5 rounded font-medium transition">
                    Sauvegarder
                </button>
                <span id="saveStatus"
                    class="text-sm px-3 py-1 rounded-full bg-white/20">
                    <?= $isSubmitted ? 'Soumis' : 'Brouillon' ?>
                </span>
                <?php if (isFormateur()): ?>
                <a href="formateur.php"
                    class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded transition">Formateur</a>
                <?php endif; ?>
                <?= renderHomeLink() ?>
                <a href="logout.php"
                    class="text-sm bg-white/10 hover:bg-white/20 px-3 py-1 rounded transition">Déconnexion</a>
            </div>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="max-w-5xl mx-auto p-4">
        <!-- ... contenu métier ... -->
    </div>

    <script>
        let data        = <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
        let isSubmitted = <?= $isSubmitted ? 'true' : 'false' ?>;
        let autoSaveTimeout = null;

        function scheduleAutoSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(saveData, 1500);
        }

        function saveData() {
            if (isSubmitted) return;
            setStatus('Sauvegarde…', 'bg-blue-200 text-blue-800');
            fetch('api/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    setStatus('Enregistré', 'bg-green-200 text-green-800');
                    setTimeout(() => setStatus('Brouillon', 'bg-white/20'), 2000);
                } else {
                    setStatus('Erreur', 'bg-red-200 text-red-800');
                }
            })
            .catch(() => setStatus('Erreur réseau', 'bg-red-200 text-red-800'));
        }

        function manualSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            saveData();
        }

        function setStatus(text, cls) {
            const el = document.getElementById('saveStatus');
            el.textContent = text;
            el.className = `text-sm px-3 py-1 rounded-full ${cls}`;
        }

        function escapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }
    </script>
    <?= renderLanguageScript() ?>
</body>
</html>
```

---

## 7. APIs (`api/`)

### `api/save.php` — patron minimal
```php
<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401); echo json_encode(['error' => 'Non authentifié']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400); echo json_encode(['error' => 'Données invalides']); exit;
}

$db        = getDB();
$userId    = getLoggedUser()['id'];
$sessionId = $_SESSION['current_session_id'];

// Vérifier que l'évaluation n'est pas soumise
$existing = $db->prepare("SELECT id, is_submitted FROM mon_contenu WHERE user_id = ? AND session_id = ?");
$existing->execute([$userId, $sessionId]);
$row = $existing->fetch();

if ($row && $row['is_submitted']) {
    http_response_code(403); echo json_encode(['error' => 'Déjà soumis']); exit;
}

// Nettoyer / valider $input selon le domaine métier...
$clean = json_encode($input);

if ($row) {
    $db->prepare("UPDATE mon_contenu SET data = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?")
       ->execute([$clean, $userId, $sessionId]);
} else {
    $db->prepare("INSERT INTO mon_contenu (user_id, session_id, data) VALUES (?, ?, ?)")
       ->execute([$userId, $sessionId, $clean]);
}

echo json_encode(['success' => true]);
```

### `api/submit.php`
```php
<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isset($_SESSION['current_session_id'])) {
    http_response_code(401); echo json_encode(['error' => 'Non authentifié']); exit;
}

$db        = getDB();
$userId    = getLoggedUser()['id'];
$sessionId = $_SESSION['current_session_id'];

$db->prepare("UPDATE mon_contenu SET is_submitted = 1, submitted_at = CURRENT_TIMESTAMP WHERE user_id = ? AND session_id = ?")
   ->execute([$userId, $sessionId]);

echo json_encode(['success' => true]);
```

---

## 8. `view.php` — vue formateur d'un participant

```php
<?php
require_once __DIR__ . '/config.php';
if (!isFormateur()) { header('Location: login.php'); exit; }

$appKey = 'app-xxx';   // pour canAccessSession()
$participantId = (int)($_GET['id'] ?? 0);
if (!$participantId) { header('Location: formateur.php'); exit; }

$db = getDB();
$stmt = $db->prepare("
    SELECT p.*, s.code as session_code, s.nom as session_nom, s.id as session_id
    FROM participants p JOIN sessions s ON p.session_id = s.id
    WHERE p.id = ?
");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();
if (!$participant) die('Participant non trouvé');
if (!canAccessSession($appKey, $participant['session_id'])) die('Accès refusé');

$stmt = $db->prepare("SELECT * FROM mon_contenu WHERE user_id = ? AND session_id = ?");
$stmt->execute([$participant['user_id'], $participant['session_id']]);
$row  = $stmt->fetch();
$data = json_decode($row['data'] ?? '{}', true) ?: [];
$isSubmitted = ($row['is_submitted'] ?? 0) == 1;
?>
<!-- HTML de présentation read-only des données du participant -->
```

---

## 9. `categories.json` — référencer l'app

Ajouter une entrée dans `categories.json` à la racine :

```json
"apps": {
    "app-xxx": ["evaluation", "analyse_strategique"]
}
```

Catégories disponibles : `gestion_projet`, `analyse_strategique`, `intelligence_artificielle`, `environnement`, `communication`, `evaluation`, `collaboration`, `creativite`.

---

## 10. Traductions — ajouter le titre de l'app

Dans `shared-auth/lang/fr.php`, dans le tableau `'apps'` :

```php
'xxx' => [
    'title'       => 'Titre affiché sur la page d\'accueil',
    'description' => 'Courte description visible sur la carte.',
    'color'       => 'indigo',
],
```

La page d'accueil (`index.php`) auto-découvre les dossiers `app-*` et affiche le titre depuis cette traduction.

---

## 11. Règles CSS / JS

- **Pas de fichier `.css` ou `.js` externe** — tout est inline dans `<style>` et `<script>`.
- **Tailwind via CDN** : `<script src="https://cdn.tailwindcss.com"></script>`.
- **Vanilla JS uniquement** — pas de jQuery, Vue, React.
- **Auto-save** : `setTimeout(saveData, 1500)` après chaque modification.
- **Impression** : classe `.no-print` sur tout ce qu'il ne faut pas imprimer.
- **Escape HTML** côté JS : toujours via `escapeHtml()` (div + textContent).

---

## 12. Base de données — règles

- **SQLite local** par app dans `data/xxx.db` — le dossier `data/` est dans `.gitignore`.
- Toujours les tables `sessions` et `participants` (synchronisées avec shared-auth via `syncLocalSessions()`).
- `is_submitted = 0/1` pour distinguer brouillon / soumis.
- Données métier stockées en JSON dans un champ `TEXT`.
- Migrations : `try { ALTER TABLE ... } catch {}` pour les colonnes ajoutées après coup.

---

## 13. Checklist pour créer une nouvelle app

```
[ ] Créer le dossier app-xxx/
[ ] config.php   (APP_NAME, APP_COLOR, DB_PATH, getDB(), initDatabase())
[ ] index.php    (redirect login ou app)
[ ] login.php    (4 lignes)
[ ] register.php (3 lignes)
[ ] logout.php   (3 lignes)
[ ] formateur.php (5 lignes, avec $appKey)
[ ] app.php      (interface participant + auto-save)
[ ] view.php     (vue formateur, lecture seule)
[ ] session-view.php (synthèse session, optionnel)
[ ] api/save.php
[ ] api/load.php
[ ] api/submit.php
[ ] Ajouter l'entrée dans categories.json
[ ] Ajouter la traduction dans shared-auth/lang/fr.php
[ ] php -l sur tous les fichiers
[ ] git add + commit + push
```
