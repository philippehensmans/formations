<?php
/**
 * Systeme de traduction multilingue
 *
 * Langues supportees: fr (francais), en (anglais), es (espagnol), sl (slovene)
 *
 * Usage:
 *   require_once __DIR__ . '/../shared-auth/lang.php';
 *   echo t('login.title'); // Affiche le titre traduit
 */

define('SUPPORTED_LANGUAGES', ['fr', 'en', 'es', 'sl']);
define('DEFAULT_LANGUAGE', 'fr');

/**
 * Obtenir la langue actuelle
 */
function getCurrentLanguage() {
    // 1. Verifier la session
    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], SUPPORTED_LANGUAGES)) {
        return $_SESSION['lang'];
    }

    // 2. Verifier le cookie
    if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], SUPPORTED_LANGUAGES)) {
        $_SESSION['lang'] = $_COOKIE['lang'];
        return $_COOKIE['lang'];
    }

    // 3. Detection automatique du navigateur
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        if (in_array($browserLang, SUPPORTED_LANGUAGES)) {
            $_SESSION['lang'] = $browserLang;
            return $browserLang;
        }
    }

    // 4. Langue par defaut
    return DEFAULT_LANGUAGE;
}

/**
 * Definir la langue
 */
function setLanguage($lang) {
    if (in_array($lang, SUPPORTED_LANGUAGES)) {
        $_SESSION['lang'] = $lang;
        setcookie('lang', $lang, time() + (365 * 24 * 60 * 60), '/'); // 1 an
        return true;
    }
    return false;
}

/**
 * Charger les traductions pour une langue
 */
function loadTranslations($lang) {
    static $translations = [];

    if (!isset($translations[$lang])) {
        $file = __DIR__ . '/lang/' . $lang . '.php';
        if (file_exists($file)) {
            $translations[$lang] = require $file;
        } else {
            // Fallback vers francais
            $translations[$lang] = require __DIR__ . '/lang/fr.php';
        }
    }

    return $translations[$lang];
}

/**
 * Traduire une cle
 *
 * @param string $key Cle de traduction (ex: 'login.title')
 * @param array $params Parametres de remplacement (ex: ['name' => 'Jean'])
 * @return string Texte traduit
 */
function t($key, $params = []) {
    $lang = getCurrentLanguage();
    $translations = loadTranslations($lang);

    // Naviguer dans les cles imbriquees (ex: 'login.title' => $translations['login']['title'])
    $keys = explode('.', $key);
    $value = $translations;

    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            // Cle non trouvee, retourner la cle elle-meme
            return $key;
        }
    }

    // Remplacement des parametres
    if (!empty($params) && is_string($value)) {
        foreach ($params as $param => $val) {
            $value = str_replace(':' . $param, $val, $value);
        }
    }

    return $value;
}

/**
 * Generer le selecteur de langue HTML
 */
function renderLanguageSelector($class = '') {
    $currentLang = getCurrentLanguage();
    $languages = [
        'fr' => ['name' => 'Français', 'flag' => '🇫🇷'],
        'en' => ['name' => 'English', 'flag' => '🇬🇧'],
        'es' => ['name' => 'Español', 'flag' => '🇪🇸'],
        'sl' => ['name' => 'Slovenščina', 'flag' => '🇸🇮']
    ];

    $html = '<select name="lang" onchange="changeLanguage(this.value)" class="' . htmlspecialchars($class) . '">';
    foreach ($languages as $code => $info) {
        $selected = ($code === $currentLang) ? ' selected' : '';
        $html .= '<option value="' . $code . '"' . $selected . '>';
        $html .= $info['flag'] . ' ' . $info['name'];
        $html .= '</option>';
    }
    $html .= '</select>';

    return $html;
}

/**
 * Script JavaScript pour le changement de langue
 */
function renderLanguageScript() {
    return <<<'JS'
<script>
function changeLanguage(lang) {
    // Envoyer requete pour changer la langue
    fetch('?action=change_lang&lang=' + lang)
        .then(() => location.reload());
}
</script>
JS;
}

/**
 * Traiter le changement de langue via GET
 */
function handleLanguageChange() {
    if (isset($_GET['action']) && $_GET['action'] === 'change_lang' && isset($_GET['lang'])) {
        setLanguage($_GET['lang']);
        // Si c'est une requete AJAX, retourner JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'lang' => getCurrentLanguage()]);
            exit;
        }
        // Sinon, rediriger sans le parametre
        $url = strtok($_SERVER['REQUEST_URI'], '?');
        header('Location: ' . $url);
        exit;
    }
}

// Traiter automatiquement le changement de langue
handleLanguageChange();
