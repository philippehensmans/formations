# Manuel du Formateur - Application Cadre Logique

## Table des matieres

1. [Acces a l'administration](#1-acces-a-ladministration)
2. [Gestion des sessions](#2-gestion-des-sessions)
3. [Interface formateur](#3-interface-formateur)
4. [Suivi des participants](#4-suivi-des-participants)

---

## 1. Acces a l'administration

### Premiere connexion

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
   - **Nom de la session**: Nom descriptif (ex: "Formation Mars 2024")
   - **Mot de passe formateur**: Optionnel - protege l'acces au dashboard formateur

2. Cliquez sur **Creer la session**

3. Un code unique a 6 caracteres est genere automatiquement (ex: ABC123)

4. **Communiquez ce code aux participants** - ils en auront besoin pour se connecter

### Actions sur les sessions

| Action | Description |
|--------|-------------|
| **Ouvrir** | Accede au dashboard formateur de cette session |
| **Activer/Desactiver** | Une session inactive n'accepte plus de nouveaux participants |
| **Supprimer** | Supprime la session ET tous les travaux des participants (irreversible) |

### Indicateurs affiches

- Nombre de participants
- Nombre de cadres soumis
- Completion moyenne (%)
- Date de creation

---

## 3. Interface formateur

### Tableau de bord

Le dashboard affiche 4 indicateurs:

| Indicateur | Description |
|------------|-------------|
| **Participants** | Nombre total de participants connectes |
| **Soumis** | Nombre de cadres logiques finalises |
| **Completion moyenne** | Pourcentage moyen de completion |
| **En cours** | Participants n'ayant pas encore soumis |

### Navigation

- **Code session**: Affiche en haut a droite pour reference
- **Gerer Sessions**: Retour a l'administration des sessions
- **Deconnexion**: Ferme la session formateur

---

## 4. Suivi des participants

### Liste des participants

La colonne de gauche affiche tous les participants avec:
- Nom et prenom
- Organisation
- Titre du projet (si renseigne)
- Statut: **Soumis** (vert) ou **En cours** (orange)
- Barre de progression

### Visualiser un cadre logique

1. Cliquez sur un participant dans la liste
2. Son cadre logique s'affiche a droite avec:
   - Informations du projet (titre, organisation, zone, duree)
   - La matrice complete avec les 4 niveaux:
     - **Objectif Global** (bleu)
     - **Objectif Specifique** (vert)
     - **Resultats** (jaune)
     - **Activites** (rouge)

### Tableau comparatif

En bas de page, un tableau recapitule tous les participants:
- Nom
- Projet
- Organisation
- Completion (%)
- Statut
- Derniere activite

### Rafraichissement automatique

La page se rafraichit automatiquement toutes les 30 secondes pour afficher les mises a jour en temps reel.

---

## Flux de travail recommande

### Avant la formation

1. Creez une session dans `admin_sessions.php`
2. Notez le code de session genere
3. Preparez le code a communiquer aux participants

### Pendant la formation

1. Communiquez le code de session aux participants
2. Les participants se connectent via `index.php`:
   - Selectionnent la session
   - Entrent prenom, nom et organisation
3. Suivez leur progression via le dashboard formateur

### Apres la formation

1. Consultez les cadres logiques soumis
2. Exportez les donnees si necessaire (JSON depuis l'interface participant)
3. Desactivez ou supprimez la session si terminee

---

## Informations techniques

| Element | Valeur |
|---------|--------|
| Mot de passe admin | formation2024 |
| Format code session | 6 caracteres alphanumeriques |
| Sauvegarde | Automatique (toutes les secondes) |
| Base de donnees | SQLite (data/formation.db) |

---

## Support

En cas de probleme:
- Verifiez que le dossier `data/` est accessible en ecriture
- Consultez les logs d'erreur PHP du serveur
- Supprimez `data/formation.db` pour reinitialiser l'application (perte de donnees)
