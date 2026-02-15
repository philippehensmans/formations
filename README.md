# Formation Interactive

Suite d'outils pedagogiques collaboratifs pour formations en gestion de projet, analyse strategique, communication et intelligence artificielle. Conçue pour les associations, ONG et organisations a but non lucratif.

**25 applications** | **8 categories** | **4 langues** | PHP + SQLite | Zero dependance externe

## Pre-requis

| Composant | Version | Notes |
|-----------|---------|-------|
| PHP | **8.0+** | Requis (`match`, `??`, fonctions fleche) |
| SQLite | 3.x | Via l'extension PDO |
| Serveur web | Apache ou Nginx | Avec support PHP |

### Extensions PHP requises

```
pdo            # Abstraction base de donnees
pdo_sqlite     # Driver SQLite
json           # Encodage/decodage JSON
session        # Gestion des sessions utilisateur
mbstring       # Support multi-octets (UTF-8)
curl           # Appels API Claude (fonctionnalites IA uniquement)
```

Extension optionnelle :
```
mail           # Envoi d'emails (reinitialisation mot de passe, notifications)
```

### Verification rapide

```bash
php -m | grep -E "pdo_sqlite|json|session|mbstring|curl"
```

---

## Installation

### 1. Cloner le depot

```bash
git clone https://github.com/philippehensmans/formations.git
cd formations
```

### 2. Creer les repertoires de donnees

Les repertoires `data/` sont exclus du depot (`.gitignore`). Ils sont crees automatiquement au premier acces a chaque application, mais vous pouvez les pre-creer :

```bash
# Repertoire partage (base utilisateurs)
mkdir -p shared-auth/data

# Repertoires des 25 applications
for app in app-*/; do
    mkdir -p "$app/data"
done
```

### 3. Configurer les permissions

Le serveur web doit pouvoir ecrire dans les repertoires `data/` (bases SQLite) :

```bash
# Adapter www-data au groupe de votre serveur web
chown -R www-data:www-data shared-auth/data
for app in app-*/; do
    chown -R www-data:www-data "$app/data"
done

# Ou bien :
chmod -R 775 shared-auth/data
for app in app-*/; do
    chmod -R 775 "$app/data"
done
```

### 4. Configurer le serveur web

#### Apache

Ajouter un VirtualHost pointant vers le repertoire racine du projet :

```apache
<VirtualHost *:80>
    ServerName formations.example.com
    DocumentRoot /chemin/vers/formations

    <Directory /chemin/vers/formations>
        AllowOverride All
        Require all granted
    </Directory>

    # Proteger les repertoires de donnees
    <DirectoryMatch "^.*/data/">
        Require all denied
    </DirectoryMatch>
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name formations.example.com;
    root /chemin/vers/formations;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Proteger les repertoires de donnees
    location ~ /data/ {
        deny all;
        return 403;
    }
}
```

### 5. Configurer l'IA (optionnel)

Requis uniquement pour les applications utilisant l'API Claude (Pilotage de Projet, Synthese IA Six Chapeaux) :

```bash
cp ai-config.example.php ai-config.php
```

Editer `ai-config.php` et remplacer la cle API :

```php
define('ANTHROPIC_API_KEY', 'sk-ant-...');          // Votre cle API Anthropic
define('ANTHROPIC_MODEL', 'claude-sonnet-4-5-20250929'); // Modele a utiliser
define('ANTHROPIC_MAX_TOKENS', 4096);                // Tokens max par reponse
```

> Le fichier `ai-config.php` est dans `.gitignore` et ne sera jamais commite.

### 6. Premier acces

1. Ouvrir `https://formations.example.com/` dans le navigateur
2. La base de donnees utilisateur est creee automatiquement
3. Un compte super-administrateur est cree par defaut :
   - **Identifiant** : `formateur`
   - **Mot de passe** : `Formation2024!`

> **Changez ce mot de passe immediatement en production.**

---

## Arborescence du projet

```
formations/
├── index.php                    # Page d'accueil (catalogue d'applications)
├── categories.json              # Definition des categories
├── categories.php               # Chargement des categories
├── admin-categories.php         # Gestion des categories (admin)
├── ai-config.example.php        # Template configuration API Claude
├── logo.png                     # Logo de la plateforme
├── MANUEL.md                    # Manuel d'utilisation complet
├── README.md                    # Ce fichier
│
├── shared-auth/                 # Systeme d'authentification partage
│   ├── config.php               # Configuration, fonctions auth, roles
│   ├── sessions.php             # Gestion des sessions de formation
│   ├── lang.php                 # Systeme de traduction
│   ├── lang/                    # Fichiers de traduction (fr, en, es, sl)
│   ├── admin.php                # Panneau d'administration
│   ├── login-template.php       # Template page de connexion
│   ├── register-template.php    # Template page d'inscription
│   ├── formateur-template.php   # Template interface formateur
│   ├── forgot-password.php      # Mot de passe oublie
│   ├── reset-password.php       # Reinitialisation mot de passe
│   └── data/                    # Base utilisateurs (users.sqlite)
│
└── app-*/                       # 25 applications (structure type ci-dessous)
    ├── config.php               # Configuration et schema DB
    ├── login.php                # Page de connexion (utilise le template)
    ├── register.php             # Page d'inscription
    ├── app.php                  # Interface participant
    ├── formateur.php            # Interface formateur
    ├── view.php                 # Vue individuelle (formateur)
    ├── session-view.php         # Vue globale session (formateur)
    ├── api/                     # Endpoints API (save, delete, load, etc.)
    └── data/                    # Base SQLite de l'application
```

---

## Les 25 applications

### Gestion de Projet

| Application | Description |
|-------------|-------------|
| **Cadre Logique** | Matrice a 4 niveaux : objectif global, specifique, resultats, activites |
| **Cahier des Charges** | Redaction structuree de cahier des charges associatif |
| **Carte d'identite du Projet** | Fiche synthese du projet (objectifs, publics, partenaires, ressources) |
| **Carte Projet** | Visualisation et cartographie de projet |
| **Objectifs SMART** | Definition d'objectifs en 3 etapes (analyse, reformulation, creation) |
| **Arbre a Problemes** | Analyse causes-effets et transformation en objectifs-moyens |
| **Pilotage de Projet** | Planification IA avec phases, taches et checkpoints (acces restreint) |
| **Methodes Agiles** | Sprints, user stories, kanban et retrospectives |
| **Mesure d'Impact** | Suivi d'impact avec objectifs, phases et lecons apprises |

### Analyse Strategique

| Application | Description |
|-------------|-------------|
| **Analyse SWOT** | Forces, Faiblesses, Opportunites, Menaces |
| **Analyse PESTEL** | Politique, Economique, Social, Technologique, Environnemental, Legal |
| **Parties Prenantes** | Cartographie influence/interet avec strategies recommandees |

### Intelligence Artificielle

| Application | Description |
|-------------|-------------|
| **Atelier IA** | Brainstorming IA pour associations (post-its, themes, interactions) |
| **Guide de Prompting** | Creation guidee de prompts personnalises |
| **Prompt Engineering Jeunes** | Exercices pratiques de prompt engineering |
| **Inventaire des Activites** | Cartographie des activites et identification du potentiel IA |

### Environnement & Climat

| Application | Description |
|-------------|-------------|
| **Calculateur Carbone** | Estimation CO2 des usages IA avec equivalences |
| **Empreinte Carbone** | Comparaison de 3 approches (IA cloud, IA locale, humain) |

### Communication

| Application | Description |
|-------------|-------------|
| **Journey Mapping** | Cartographie des parcours et points de contact |
| **Publics & Personas** | Creation de fiches personas pour connaitre ses publics |
| **Mini-Plan de Communication** | Plan d'action : objectif SMART, public, message, canaux, calendrier |

### Evaluation & Retrospective

| Application | Description |
|-------------|-------------|
| **Stop Start Continue** | Retrospective en 3 colonnes (arreter, commencer, continuer) |

### Outils Collaboratifs

| Application | Description |
|-------------|-------------|
| **Carte Mentale** | Mind map collaboratif avec noeuds, couleurs et icones |
| **Tableau Blanc** | Dessin collaboratif avec post-its, formes et trait libre |

### Creativite & Reflexion

| Application | Description |
|-------------|-------------|
| **Six Chapeaux de Bono** | Reflexion structuree selon 6 modes de pensee (blanc, rouge, noir, jaune, vert, bleu) |

---

## Roles et permissions

| Role | Acces |
|------|-------|
| **Participant** | Rejoindre des sessions, contribuer, partager son travail |
| **Formateur** | Creer/gerer des sessions, voir les resultats, suivi temps reel |
| **Administrateur** | Gestion des utilisateurs, affectation des formateurs |
| **Super-administrateur** | Acces total, gestion des categories, controle d'acces IA |

---

## Securite

### Mesures en place

- **Mots de passe** : hashes avec `password_hash()` (bcrypt)
- **XSS** : echappement HTML via `htmlspecialchars()` (fonction `h()`)
- **Injection SQL** : requetes preparees PDO exclusivement
- **Sessions** : validation croisee code + ID de session
- **Donnees** : repertoires `data/` proteges par `.htaccess` (`Deny from all`)
- **API** : cle dans fichier `.gitignore`, jamais commitee

### Recommandations pour la production

1. **Changer le mot de passe** du compte `formateur` par defaut
2. **HTTPS** : configurer un certificat SSL (Let's Encrypt)
3. **Pare-feu** : limiter l'acces au port 80/443
4. **Sauvegardes** : planifier des sauvegardes regulieres des repertoires `data/`
5. **Mises a jour** : maintenir PHP a jour (correctifs de securite)
6. **Email** : configurer un serveur SMTP pour les emails de reinitialisation

---

## Sauvegarde et restauration

### Sauvegarder

Toutes les donnees sont dans les fichiers SQLite des repertoires `data/` :

```bash
# Sauvegarde complete
tar czf formations-backup-$(date +%Y%m%d).tar.gz \
    shared-auth/data/ \
    app-*/data/ \
    ai-config.php \
    categories.json
```

### Restaurer

```bash
tar xzf formations-backup-YYYYMMDD.tar.gz
```

---

## Langues

La plateforme est disponible en 4 langues. Le selecteur de langue est present sur chaque page.

| Langue | Code | Fichier |
|--------|------|---------|
| Français | `fr` | `shared-auth/lang/fr.php` |
| English | `en` | `shared-auth/lang/en.php` |
| Español | `es` | `shared-auth/lang/es.php` |
| Slovenščina | `sl` | `shared-auth/lang/sl.php` |

Pour ajouter une langue : creer un fichier `shared-auth/lang/{code}.php` en suivant le modele du fichier `fr.php`, puis ajouter le code dans la constante `SUPPORTED_LANGUAGES` de `shared-auth/lang.php`.

---

## Licence

Projet prive - Tous droits reserves.
