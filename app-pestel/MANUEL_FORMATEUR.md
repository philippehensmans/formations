# Manuel du Formateur - Application Analyse PESTEL

## Table des matieres

1. [Acces a l'administration](#1-acces-a-ladministration)
2. [Gestion des sessions](#2-gestion-des-sessions)
3. [Interface formateur](#3-interface-formateur)
4. [Suivi des participants](#4-suivi-des-participants)
5. [Guide participant](#5-guide-participant)

---

## 1. Acces a l'administration

### Premiere connexion (Admin)

1. Accedez a `admin_sessions.php`
2. Entrez le mot de passe administrateur: **formation2024**
3. Vous accedez a la page de gestion des sessions

### Connexion en tant que formateur

1. Accedez a `index.php`
2. Cliquez sur l'onglet **Formateur**
3. Selectionnez votre session dans le menu deroulant
4. Si un mot de passe est requis, entrez-le
5. Cliquez sur **Acceder au tableau de bord**

---

## 2. Gestion des sessions

### Creer une nouvelle session

1. Dans la page **Gestion des Sessions**, remplissez le formulaire:
   - **Nom de la session**: Nom descriptif (ex: "Formation PESTEL Juin 2024")
   - **Mot de passe formateur**: Optionnel - protege l'acces au dashboard

2. Cliquez sur **Creer la session**

3. Un code unique a 6 caracteres est genere automatiquement (ex: XYZ789)

4. **Communiquez ce code aux participants**

### Actions sur les sessions

| Action | Description |
|--------|-------------|
| **Ouvrir** | Accede au dashboard formateur de cette session |
| **Activer/Desactiver** | Une session inactive n'accepte plus de nouveaux participants |
| **Supprimer** | Supprime la session ET toutes les analyses (irreversible) |

### Indicateurs affiches

- Nombre de participants
- Nombre d'analyses soumises
- Completion moyenne (%)
- Date de creation

---

## 3. Interface formateur

### Tableau de bord

Le dashboard affiche 4 indicateurs:

| Indicateur | Description |
|------------|-------------|
| **Participants** | Nombre total de participants connectes |
| **Soumis** | Nombre d'analyses PESTEL finalisees |
| **Completion moyenne** | Pourcentage moyen de completion |
| **En cours** | Participants n'ayant pas encore soumis |

### Navigation

- **Code session**: Affiche en haut a droite pour reference rapide
- **Gerer Sessions**: Retour a l'administration des sessions
- **Deconnexion**: Ferme la session formateur

---

## 4. Suivi des participants

### Liste des participants

La colonne de gauche affiche tous les participants avec:
- Nom et prenom
- Organisation
- Titre du projet analyse
- Statut: **Soumis** (vert) ou **En cours** (orange)
- Barre de progression

### Visualiser une analyse PESTEL

1. Cliquez sur un participant dans la liste
2. Son analyse s'affiche a droite avec:
   - Informations du projet (zone geographique, participants)
   - Les 6 categories PESTEL avec leurs elements:

| Categorie | Couleur | Description |
|-----------|---------|-------------|
| 🏛️ **Politique** | Rouge | Stabilite politique, politiques publiques |
| 💰 **Economique** | Vert | Croissance, inflation, pouvoir d'achat |
| 👥 **Socioculturel** | Violet | Demographie, valeurs, modes de vie |
| 🔬 **Technologique** | Bleu | Innovation, R&D, digitalisation |
| 🌱 **Environnemental** | Teal | Climat, durabilite, ecologie |
| ⚖️ **Legal** | Ambre | Lois, reglementations, normes |

### Rafraichissement automatique

La page se rafraichit automatiquement toutes les 30 secondes.

---

## 5. Guide participant

### Connexion participant

1. Acceder a `index.php`
2. Selectionner la session dans le menu deroulant
3. Entrer prenom, nom et organisation (optionnel)
4. Cliquer sur **Acceder a l'exercice**

### Remplir l'analyse PESTEL

1. **Informations du projet**
   - Titre du projet/organisation/secteur analyse
   - Participants a l'analyse
   - Zone geographique concernee

2. **Les 6 dimensions PESTEL**
   - Pour chaque categorie, identifier les facteurs cles
   - Cliquer sur ➕ pour ajouter un element
   - Cliquer sur ❌ pour supprimer un element
   - Conseil: 3 a 5 facteurs par categorie

3. **Synthese**
   - Identifier les 3-5 facteurs les plus impactants
   - Decrire les implications strategiques

4. **Notes complementaires**
   - Sources, observations, precisions

### Sauvegarde

- **Automatique**: Toutes les modifications sont sauvegardees automatiquement (1 seconde apres la derniere modification)
- **Manuelle**: Cliquer sur le bouton **Sauvegarder**
- **Indicateur**: Le statut affiche "Enregistre" (vert) apres sauvegarde

### Soumission

1. Cliquer sur **Soumettre**
2. L'analyse doit etre completee a au moins 30%
3. Une fois soumise, l'analyse reste modifiable

### Export

- **JSON**: Exporte l'analyse au format JSON
- **Imprimer**: Ouvre la boite de dialogue d'impression

---

## Flux de travail recommande

### Avant la formation

1. Creez une session dans `admin_sessions.php`
2. Notez le code de session genere
3. Preparez le contexte de l'exercice (secteur, organisation a analyser)

### Pendant la formation

1. Presentez la methodologie PESTEL (introduction integree dans l'application)
2. Communiquez le code de session aux participants
3. Les participants se connectent et remplissent leur analyse
4. Suivez leur progression via le dashboard formateur
5. Comparez les analyses en temps reel

### Apres la formation

1. Consultez les analyses soumises
2. Exportez les donnees si necessaire
3. Desactivez ou supprimez la session si terminee

---

## Rappel methodologique PESTEL

L'analyse PESTEL examine 6 dimensions de l'environnement macro:

| Dimension | Questions cles |
|-----------|----------------|
| **Politique** | Quelle est la stabilite politique ? Quelles politiques gouvernementales impactent le secteur ? |
| **Economique** | Quel est le contexte economique ? Evolution du pouvoir d'achat ? Acces au financement ? |
| **Socioculturel** | Quelles tendances demographiques ? Evolutions des modes de vie ? Valeurs emergentes ? |
| **Technologique** | Quelles innovations impactent le secteur ? Niveau de digitalisation ? |
| **Environnemental** | Quelles contraintes ecologiques ? Attentes en matiere de durabilite ? |
| **Legal** | Quelles reglementations s'appliquent ? Evolutions legislatives prevues ? |

**Objectif**: Identifier les opportunites et menaces externes pour alimenter la reflexion strategique.

---

## Informations techniques

| Element | Valeur |
|---------|--------|
| Mot de passe admin | formation2024 |
| Format code session | 6 caracteres alphanumeriques |
| Sauvegarde | Automatique (toutes les secondes) |
| Completion minimale pour soumission | 30% |
| Base de donnees | SQLite (data/pestel.db) |
| Rafraichissement dashboard | 30 secondes |

---

## Support

En cas de probleme:
- Verifiez que le dossier `data/` est accessible en ecriture
- Consultez les logs d'erreur PHP du serveur
- Supprimez `data/pestel.db` pour reinitialiser l'application (perte de donnees)
