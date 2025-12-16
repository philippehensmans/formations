# Manuel Formateur & Administrateur
## Application Cartographie des Parties Prenantes

---

## 1. Presentation

Cette application permet aux participants de realiser une **cartographie des parties prenantes** avec une matrice influence/interet. Chaque participant peut identifier les acteurs cles de son projet et les positionner selon leur niveau d'influence et d'interet.

### Fonctionnalites principales
- Matrice visuelle influence/interet (4 quadrants)
- 6 categories de parties prenantes avec codes couleur
- Strategies recommandees selon le positionnement
- Sauvegarde automatique et manuelle
- Export Excel, Word, JSON et impression
- Suivi en temps reel par le formateur

---

## 2. Acces Administrateur

### Connexion
1. Acceder a `admin_sessions.php`
2. Entrer le mot de passe : **Formation2024!**

### Creer une session
1. Remplir le **nom de la session** (ex: "Formation Gestion de Projet - Mars 2024")
2. Definir un **mot de passe formateur** (ex: "projet2024")
3. Cliquer sur **Creer**
4. Un **code session** unique est genere (ex: ABC123)

### Gerer les sessions
| Action | Description |
|--------|-------------|
| **Reset** | Supprime toutes les donnees (participants + cartographies) mais conserve la session |
| **Supprimer** | Supprime completement la session et toutes ses donnees |

### Informations affichees
- Code de session (a communiquer aux participants)
- Mot de passe formateur
- Nombre de participants connectes
- Nombre de travaux soumis
- Date de creation

---

## 3. Acces Formateur

### Connexion
1. Acceder a `index.php`
2. Cliquer sur l'onglet **Formateur**
3. Selectionner la session dans le menu deroulant
4. Entrer le mot de passe formateur (defini par l'admin)

### Tableau de bord formateur

#### Vue d'ensemble
- Liste de tous les participants de la session
- Statut de chaque travail (En cours / Soumis)
- Nombre de parties prenantes identifiees par participant
- Pourcentage de completion

#### Details des cartographies
Pour chaque participant, le formateur peut voir :
- **Titre du projet** et **contexte**
- **Tableau des parties prenantes** avec :
  - Nom de la partie prenante
  - Categorie (Membres, Beneficiaires, Partenaires, etc.)
  - Position (Influence / Interet)
  - Strategie recommandee
  - Attentes et contributions

#### Actions disponibles
- **Actualiser** : Recharger les donnees en temps reel
- **Retour** : Revenir a l'accueil

---

## 4. Guide du Participant

### Connexion
1. Acceder a `index.php`
2. Entrer le **code session** (fourni par le formateur)
3. Remplir **prenom**, **nom** et **organisation** (optionnel)
4. Cliquer sur **Rejoindre la session**

### Interface de travail

#### Informations projet
- **Titre du projet** : Nom du projet analyse
- **Contexte** : Description du contexte et des enjeux

#### Ajouter une partie prenante
1. Cliquer sur **+ Ajouter une partie prenante**
2. Remplir les champs :
   - **Nom** : Nom de l'acteur/organisation
   - **Categorie** : Type de partie prenante
   - **Influence** (0-100) : Pouvoir d'impact sur le projet
   - **Interet** (0-100) : Niveau d'implication/concernement
   - **Attentes** : Ce que cette partie prenante attend du projet
   - **Contributions** : Ce qu'elle peut apporter au projet
3. Cliquer sur **Ajouter**

#### Categories disponibles
| Categorie | Couleur | Description |
|-----------|---------|-------------|
| Membres | Rouge | Membres de l'equipe projet |
| Beneficiaires | Bleu | Cibles/destinataires du projet |
| Partenaires | Vert | Organisations partenaires |
| Financeurs | Jaune | Bailleurs de fonds |
| Autorites | Violet | Pouvoirs publics, regulateurs |
| Autres | Gris | Autres parties prenantes |

#### Matrice Influence/Interet
La matrice affiche 4 quadrants avec strategies recommandees :

| Quadrant | Position | Strategie |
|----------|----------|-----------|
| **Haut-Droite** | Forte influence + Fort interet | **Gerer etroitement** - Partenaires cles |
| **Haut-Gauche** | Forte influence + Faible interet | **Tenir satisfait** - Garder informes |
| **Bas-Droite** | Faible influence + Fort interet | **Tenir informe** - Communication reguliere |
| **Bas-Gauche** | Faible influence + Faible interet | **Surveiller** - Effort minimal |

#### Gerer les parties prenantes
- **Modifier** : Cliquer sur le bouton modifier (crayon)
- **Supprimer** : Cliquer sur le bouton supprimer (corbeille)
- **Visualiser** : Les points sur la matrice sont cliquables

#### Sauvegarde
- **Automatique** : Toutes les modifications sont sauvegardees automatiquement
- **Manuelle** : Bouton "Sauvegarder" pour forcer la sauvegarde
- **Indicateur** : "Sauvegarde..." apparait pendant l'enregistrement

#### Export
| Format | Description |
|--------|-------------|
| **Excel** | Tableau des parties prenantes (fichier .xlsx) |
| **Word** | Document complet avec matrice et details (fichier .doc) |
| **JSON** | Donnees brutes pour traitement externe |
| **Imprimer** | Impression directe ou PDF via le navigateur |

#### Soumission finale
1. Verifier que toutes les parties prenantes sont ajoutees
2. Cliquer sur **Soumettre le travail**
3. Confirmer la soumission
4. Le travail devient visible par le formateur avec le statut "Soumis"

**Attention** : Apres soumission, les modifications ne sont plus possibles.

---

## 5. Conseils d'animation

### Avant la session
1. Creer la session dans `admin_sessions.php`
2. Noter le code session et le mot de passe formateur
3. Tester la connexion participant
4. Preparer les consignes (projet a analyser, temps alloue)

### Pendant la session
1. Communiquer le code session aux participants
2. Expliquer les categories de parties prenantes
3. Rappeler les criteres influence/interet :
   - **Influence** : Capacite a impacter le projet (decisions, ressources, blocages)
   - **Interet** : Degre de concernement par le projet (enjeux, benefices, risques)
4. Suivre l'avancement via `formateur.php`
5. Encourager l'ajout d'au moins 8-10 parties prenantes

### Apres la session
1. Exporter les travaux pour archivage
2. Faire un debriefing sur les strategies identifiees
3. Optionnel : Reset de la session pour reutilisation

### Points d'attention
- Encourager la reflexion sur les **attentes** et **contributions**
- Verifier que les 4 quadrants sont representes
- Discuter des strategies adaptees a chaque quadrant
- Identifier les parties prenantes "critiques" (haute influence)

---

## 6. Resolution de problemes

| Probleme | Solution |
|----------|----------|
| Code session invalide | Verifier le code dans admin_sessions.php |
| Mot de passe formateur refuse | Verifier le mot de passe dans admin_sessions.php |
| Donnees non sauvegardees | Verifier la connexion internet, recharger la page |
| Participant non visible | Demander au participant de se reconnecter |
| Export ne fonctionne pas | Utiliser Chrome ou Firefox, verifier les popups |

---

## 7. Informations techniques

- **Navigateurs supportes** : Chrome, Firefox, Edge, Safari
- **Connexion** : Requise pour la sauvegarde automatique
- **Donnees** : Stockees localement sur le serveur (SQLite)
- **Mot de passe admin** : Formation2024! (modifiable dans admin_sessions.php)
