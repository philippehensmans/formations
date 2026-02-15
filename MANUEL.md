# Formation Interactive - Manuel d'utilisation

## Table des matieres

1. [Presentation de la plateforme](#1-presentation-de-la-plateforme)
2. [Demarrage rapide](#2-demarrage-rapide)
3. [Systeme d'authentification](#3-systeme-dauthentification)
4. [Guide du Participant](#4-guide-du-participant)
5. [Guide du Formateur](#5-guide-du-formateur)
6. [Guide de l'Administrateur](#6-guide-de-ladministrateur)
7. [Les Applications](#7-les-applications)
   - [Gestion de Projet](#gestion-de-projet)
   - [Analyse Strategique](#analyse-strategique)
   - [Intelligence Artificielle](#intelligence-artificielle)
   - [Environnement & Climat](#environnement--climat)
   - [Communication](#communication)
   - [Evaluation & Retrospective](#evaluation--retrospective)
   - [Outils Collaboratifs](#outils-collaboratifs)
   - [Creativite & Reflexion](#creativite--reflexion)
8. [FAQ et depannage](#8-faq-et-depannage)

---

## 1. Presentation de la plateforme

**Formation Interactive** est une suite d'outils pedagogiques collaboratifs destines aux formations en gestion de projet, analyse strategique, communication et intelligence artificielle. La plateforme est conçue pour les associations, ONG et organisations a but non lucratif.

### Caracteristiques principales

- **25 applications** couvrant 8 categories thematiques
- **Systeme de sessions** : chaque formation utilise un code unique a 6 caracteres
- **Roles differencies** : participant, formateur, administrateur, super-administrateur
- **Multilingue** : français, anglais, espagnol, slovene
- **Temps reel** : suivi en direct des contributions des participants
- **Export** : impression, PDF, JSON selon les applications

### Pre-requis techniques

- Navigateur web moderne (Chrome, Firefox, Safari, Edge)
- Connexion internet
- Pas d'installation requise cote participant

---

## 2. Demarrage rapide

### Pour un participant

1. Ouvrir le lien de l'application fourni par le formateur
2. Entrer le **code de session** (ex: `ABC123`)
3. Se connecter ou creer un compte (prenom, nom, organisation)
4. Commencer a travailler dans l'application

### Pour un formateur

1. Acceder a la page formateur de l'application (`formateur.php`)
2. Se connecter avec un compte formateur
3. Creer une session et noter le **code de session** genere
4. Communiquer le code aux participants
5. Suivre les contributions en temps reel depuis le tableau de bord

---

## 3. Systeme d'authentification

### Comptes et roles

| Role | Acces | Description |
|------|-------|-------------|
| **Participant** | Applications uniquement | Peut rejoindre des sessions et y contribuer |
| **Formateur** | Applications + interface formateur | Cree et gere des sessions de formation |
| **Administrateur** | Tout + panneau admin | Gere les utilisateurs et les parametres |
| **Super-administrateur** | Acces total a toutes les apps | Administration complete de la plateforme |

### Inscription

L'inscription se fait depuis la page de connexion de n'importe quelle application :

1. Cliquer sur **S'inscrire**
2. Remplir le formulaire : identifiant, mot de passe, prenom, nom, organisation, email (optionnel)
3. Le compte est immediatement utilisable

### Reinitialisation du mot de passe

1. Cliquer sur **Mot de passe oublie** sur la page de connexion
2. Entrer son identifiant ou email
3. Un lien de reinitialisation est envoye par email (valable 1 heure)

### Selecteur de langue

Un selecteur est disponible sur chaque page. Les langues supportees sont :
- 🇫🇷 Français (par defaut)
- 🇬🇧 English
- 🇪🇸 Español
- 🇸🇮 Slovenščina

---

## 4. Guide du Participant

### Rejoindre une session

1. Acceder a l'application via le lien ou la page d'accueil
2. Entrer le code de session (6 caracteres) fourni par le formateur
3. Se connecter ou creer un compte
4. L'interface de travail s'ouvre automatiquement

### Travailler dans une application

- La plupart des applications proposent une **sauvegarde automatique** (toutes les secondes)
- Certaines applications ont un bouton **Partager** pour rendre le travail visible au formateur
- Un indicateur de progression (%) est affiche quand applicable
- Les donnees ne sont visibles du formateur qu'apres partage (sauf pour les applications a partage automatique)

### Bonnes pratiques

- Utiliser un identifiant memorable (pas d'adresse email)
- Completer un maximum de champs pour une analyse pertinente
- Partager son travail une fois termine pour que le formateur puisse le voir
- Ne pas fermer le navigateur pendant le travail (risque de perte si pas sauvegarde)

---

## 5. Guide du Formateur

### Acceder a l'interface formateur

Chaque application a une page `formateur.php` accessible depuis :
- La page d'accueil (section "Espace Formateur" en bas)
- L'URL directe : `[app]/formateur.php`

### Creer une session

1. Se connecter avec un compte formateur
2. Entrer un **nom de session** (ex: "Formation Mars 2025 - ASBL XYZ")
3. Cliquer sur **Creer**
4. Un code unique a 6 caracteres est genere automatiquement
5. Communiquer ce code aux participants (tableau, projection, email)

### Tableau de bord formateur

Le tableau de bord affiche pour chaque session :
- **Nombre de participants** inscrits
- **Travaux soumis / en cours**
- **Taux de completion moyen**
- **Liste des participants** avec leur statut

### Fonctionnalites communes

| Fonction | Description |
|----------|-------------|
| Voir tout | Vue globale de tous les travaux de la session |
| Vue individuelle | Voir le travail d'un participant specifique |
| Activer/Desactiver | Ouvrir ou fermer l'acces a une session |
| Supprimer | Supprimer une session et toutes ses donnees |
| Imprimer | Version imprimable des resultats |
| Rafraichissement auto | Mise a jour toutes les 30 secondes |

### Definir un sujet de reflexion

Dans les applications qui le supportent (Six Chapeaux, SWOT, etc.) :
1. Acceder a la vue de session
2. Cliquer sur **Modifier** a cote du sujet
3. Entrer la question ou le theme de reflexion
4. Enregistrer

### Conseils d'animation

1. **Preparer** : creer la session et definir le sujet avant la formation
2. **Expliquer** : presenter la methodologie avant de faire travailler les participants
3. **Accompagner** : suivre la progression en temps reel et relancer si necessaire
4. **Synthetiser** : utiliser les vues globales pour debriefing collectif
5. **Conserver** : imprimer ou exporter les resultats avant de cloturer

---

## 6. Guide de l'Administrateur

### Panneau d'administration

Accessible via `shared-auth/admin.php` pour les administrateurs et super-administrateurs.

### Gestion des utilisateurs

- **Lister** tous les utilisateurs inscrits
- **Promouvoir/Retrograder** : accorder ou retirer le role formateur
- **Supprimer** un compte utilisateur (sauf comptes admin)
- **Affecter** un formateur a des sessions specifiques (mode restriction)

### Affectation des formateurs aux sessions

Par defaut, un formateur a acces a toutes les sessions. L'administrateur peut restreindre l'acces :

1. Dans le panneau admin, selectionner un formateur
2. Cocher les sessions auxquelles il a acces
3. Une fois qu'au moins une affectation existe, le formateur ne voit plus que ses sessions

### Controle d'acces aux applications IA

Certaines applications utilisent l'API Claude (Anthropic) et sont restreintes pour controler les couts :
- Seuls les super-admins et formateurs y ont acces automatiquement
- Les participants doivent recevoir une autorisation explicite
- L'autorisation se gere depuis le panneau admin, section "Applications IA"

### Gestion des categories

Accessible via `admin-categories.php`, cette interface permet de :
- Creer, modifier, supprimer des categories d'applications
- Affecter des applications a des categories
- Personnaliser les icones et couleurs des categories

---

## 7. Les Applications

---

### Gestion de Projet

#### Cadre Logique

> Construction de cadres logiques pour la gestion de projets.

**Methodologie** : Le cadre logique est une matrice qui structure un projet en 4 niveaux hierarchiques.

**Fonctionnement** :
1. Le participant definit les informations du projet (titre, organisation, zone, duree)
2. Il complete la matrice sur 4 niveaux :
   - **Objectif global** : impact a long terme (indicateurs, sources de verification, hypotheses)
   - **Objectif specifique** : changement attendu du projet
   - **Resultats** : produits concrets du projet (plusieurs possibles)
   - **Activites** : actions concretes pour chaque resultat
3. Chaque niveau peut avoir des indicateurs, sources de verification et hypotheses
4. Progression affichee en pourcentage

**Vue formateur** : tableau de bord avec barre de progression par participant, vue comparative de tous les cadres logiques.

---

#### Cahier des Charges

> Redaction collaborative de cahiers des charges associatifs.

**Fonctionnement** :
1. Le participant remplit les sections du cahier des charges :
   - Informations projet (titre, dates, parties prenantes)
   - Alignement strategique et aspects numeriques
   - Description, objectifs (global et specifiques)
   - Resultats attendus et contraintes
   - Strategies, budget, ressources
   - Phases de mise en oeuvre
   - Plan de communication
2. Sauvegarde automatique pendant la saisie

---

#### Carte d'identite du Projet

> Fiche outil synthetique a completer par le porteur du projet.

**Fonctionnement** :
1. Remplir une fiche resume du projet :
   - Titre et objectifs
   - Public(s) cible(s)
   - Zone d'action / territoire
   - Partenaires (ajout dynamique avec nom, role, contact)
   - Ressources humaines, materielles, financieres
   - Calendrier
   - Resultats attendus
2. Progression affichee en pourcentage

---

#### Carte Projet

> Visualisation et planification de projets sous forme de carte.

**Fonctionnement** :
- Interface visuelle pour cartographier les elements d'un projet
- Donnees stockees en format JSON pour flexibilite maximale
- Partage avec le formateur une fois termine

---

#### Objectifs SMART

> Definition d'objectifs Specifiques, Mesurables, Atteignables, Realistes et Temporels.

**Methodologie SMART** :
- **S**pecifique : clairement defini
- **M**esurable : avec des indicateurs quantifiables
- **A**tteignable : realiste avec les moyens disponibles
- **R**ealiste : pertinent par rapport au contexte
- **T**emporel : avec une echeance definie

**Fonctionnement en 3 etapes** :
1. **Analyse** : etat des lieux, identification des problemes
2. **Reformulation** : transformation des constats en objectifs SMART
3. **Creation** : finalisation des objectifs SMART

---

#### Arbre a Problemes

> Analyse des causes et effets pour identifier les problemes racines.

**Methodologie** : Outil de planification qui structure un probleme central, ses causes (racines) et ses consequences (branches), puis les transforme en objectifs et moyens.

**Fonctionnement** :
1. Definir le **probleme central**
2. Identifier les **consequences** (effets du probleme)
3. Identifier les **causes** (origines du probleme)
4. Transformer le probleme en **objectif central**
5. Transformer les consequences en **objectifs specifiques**
6. Transformer les causes en **moyens** d'action

---

#### Pilotage de Projet

> Structurez votre projet des objectifs aux taches concretes, avec phases, points de controle et lecons apprises.

**Acces restreint** : cette application utilise l'IA (API Claude) et necessite une autorisation.

**Fonctionnement** :
1. Decrire le contexte du projet (nom, description, contraintes)
2. **Generation IA** : Claude genere automatiquement un plan de projet structure
3. Le plan inclut :
   - Objectifs avec criteres de succes
   - Phases avec livrables et taches
   - Points de controle (validation, revue, livraison, feedback, decision)
4. Les taches peuvent etre gerees avec les statuts : a faire, en cours, en revue, termine, bloque
5. Suivi des lecons apprises et synthese

---

#### Methodes Agiles

> Planification et retrospectives avec les methodologies Agile et Scrum.

**Fonctionnement** :
1. Creer un **projet agile** avec description
2. Gerer les **user stories** (recits utilisateur)
3. Planifier les **sprints** :
   - Numero, dates de debut et fin
   - Objectifs du sprint
   - Cartes de taches (style post-it/kanban)
4. Conduire des **retrospectives** :
   - Ce qui a bien fonctionne
   - Ce qu'il faut ameliorer
   - Actions concretes
5. Partager le projet avec le formateur

---

#### Mesure d'Impact

> Evaluation et suivi de l'impact des projets.

**Fonctionnement** :
1. Decrire le contexte du projet
2. Definir les objectifs a mesurer
3. Structurer les phases de suivi
4. Placer des points de controle (validation, revue, livraison, feedback, decision)
5. Documenter les lecons apprises
6. Rediger une synthese

---

### Analyse Strategique

#### Analyse SWOT

> Analyse des Forces, Faiblesses, Opportunites et Menaces.

**Methodologie** : La matrice SWOT croise l'analyse interne (forces/faiblesses) et externe (opportunites/menaces) d'un projet ou d'une organisation.

| | Positif | Negatif |
|---|---------|---------|
| **Interne** | Forces (Strengths) | Faiblesses (Weaknesses) |
| **Externe** | Opportunites (Opportunities) | Menaces (Threats) |

**Fonctionnement** :
1. Definir le titre du projet ou de l'organisation
2. Remplir les 4 quadrants de la matrice SWOT
3. Partager l'analyse avec le formateur

**Vue formateur** : tableau de bord avec statistiques, vue globale de toutes les analyses.

---

#### Analyse PESTEL

> Analyse Politique, Economique, Social, Technologique, Environnemental et Legal.

**Methodologie** : Analyse de l'environnement externe d'un projet selon 6 dimensions.

| Dimension | Icone | Description |
|-----------|-------|-------------|
| Politique | 🏛️ | Lois, reglementations, stabilite politique |
| Economique | 💰 | Croissance, inflation, financement |
| Socioculturel | 👥 | Demographe, tendances, valeurs |
| Technologique | 🔬 | Innovation, numerique, infrastructure |
| Environnemental | 🌱 | Ecologie, climat, ressources naturelles |
| Legal | ⚖️ | Droit du travail, normes, obligations |

**Fonctionnement** :
1. Definir le titre du projet
2. Remplir chaque dimension PESTEL
3. Sauvegarde automatique
4. Partager l'analyse une fois terminee

---

#### Cartographie des Parties Prenantes

> Cartographie et analyse des parties prenantes d'un projet.

**Methodologie** : Identification et positionnement des acteurs cles sur une matrice influence/interet.

**Categories de parties prenantes** :
- Membres
- Beneficiaires
- Partenaires
- Bailleurs/Financeurs
- Autorites publiques
- Autres

**Matrice Influence/Interet** :

| | Interet faible | Interet eleve |
|---|---------------|---------------|
| **Influence elevee** | Satisfaire | Gerer de pres |
| **Influence faible** | Surveiller | Informer |

**Fonctionnement** :
1. Identifier les parties prenantes par categorie
2. Positionner chaque acteur sur la matrice influence/interet
3. La strategie recommandee est affichee automatiquement
4. Exporter en Excel, Word, JSON ou imprimer

---

### Intelligence Artificielle

#### Atelier IA pour Associations

> Decouverte et experimentation de l'intelligence artificielle pour les associations.

**Fonctionnement** :
1. Decrire son association (nom, mission)
2. Ajouter des idees sur des **post-its virtuels** (brainstorming)
3. Identifier les themes communs
4. Cartographier les interactions possibles avec l'IA
5. Definir les conditions de succes
6. Partager le travail avec le formateur

---

#### Guide de Prompting sur Mesure

> Creation de guides personnalises pour l'utilisation de l'IA.

**Fonctionnement en plusieurs etapes** :
1. Decrire le contexte de son organisation
2. Identifier les taches cibles pour l'IA
3. Experimenter des prompts sur differentes taches
4. Creer des modeles de prompts reutilisables
5. Progression guidee etape par etape

---

#### Prompt Engineering Jeunes

> Atelier pour maitriser le prompt engineering et creer du contenu adapte au public jeune.

**Fonctionnement** :
1. Plusieurs exercices pratiques (navigation par exercice)
2. Pour chaque exercice :
   - Rediger un **prompt initial** et observer le resultat
   - Rediger un **prompt ameliore** et comparer
   - Analyser les differences
3. Obtenir du **feedback** d'un binome et de l'IA
4. Consigner les enseignements cles (takeaways)
5. Partager son travail

---

#### Inventaire des Activites

> Cartographie des activites d'une association pour identifier le potentiel IA.

**Fonctionnement** :
1. Lister toutes les activites de l'organisation
2. Pour chaque activite, definir :
   - Description
   - Categorie (communication, admin, evenements, membres, comptabilite, RH, projets, formation, autre)
   - Frequence (quotidienne, hebdomadaire, mensuelle, trimestrielle, annuelle, ponctuelle)
   - Priorite (basse, moyenne, haute, critique)
   - Potentiel IA (oui/non avec notes)
3. Statistiques automatiques par categorie, frequence et priorite

---

### Environnement & Climat

#### Calculateur Carbone IA

> Estimation de l'empreinte carbone des activites liees a l'IA.

**Fonctionnement** :
1. Selectionner des cas d'usage IA
2. Indiquer la frequence d'utilisation (quotidienne, hebdomadaire, mensuelle, etc.)
3. Le calcul de CO2 est effectue automatiquement
4. **Equivalences** affichees :
   - Kilometres en voiture
   - Emails envoyes
   - Heures de streaming
   - Charges de telephone
   - Tasses de cafe
5. Exporter les resultats

---

#### Empreinte Carbone IA

> Analyse detaillee de l'impact environnemental - comparaison de 3 approches.

**Fonctionnement** :
1. Comparer 3 scenarios pour une meme tache :
   - **Option 1** : IA Cloud puissante (GPT-4, Claude, etc.)
   - **Option 2** : IA locale/hybride legere
   - **Option 3** : Approche humaine (sans IA)
2. Les participants votent sur chaque option selon :
   - Impact environnemental
   - Qualite du resultat
   - Efficacite temporelle
3. Les moyennes sont calculees et affichees
4. Un score composite est genere pour chaque option

---

### Communication

#### Journey Mapping

> Cartographie des parcours de communication et audit des points de contact avec vos publics.

**Fonctionnement** :
1. Decrire l'organisation et les objectifs d'audit
2. Definir le public cible
3. Cartographier le parcours utilisateur etape par etape :
   - Points de contact (12 canaux : site web, reseaux sociaux, email, telephone, physique, courrier, evenement, media, bouche-a-oreille, app mobile, chat, autre)
   - Emotion ressentie a chaque etape (satisfaction, confusion, frustration, enthousiasme, indifference, inquietude, questionnement, surprise positive)
4. Rediger une synthese et des recommandations

---

#### Publics & Personas

> Cartographie des parties prenantes et creation de personas pour definir et connaitre ses publics.

**Fonctionnement** :
1. Identifier les publics cibles
2. Creer des fiches personas detaillees
3. Partager avec le formateur

---

#### Mini-Plan de Communication

> Construisez un plan de communication concret autour d'une action precise.

**Fonctionnement** :
1. Nommer l'organisation et l'action de communication
2. Definir un **objectif SMART**
3. Identifier le **public prioritaire**
4. Rediger les **messages cles**
5. Choisir les **canaux de communication** (11 canaux : Facebook, Instagram, site web, email/newsletter, flyers/affiches, presse, radio, bouche-a-oreille, evenements, WhatsApp, autre)
6. Planifier le **calendrier** des actions
7. Lister les **ressources** necessaires

---

### Evaluation & Retrospective

#### Stop Start Continue

> Retrospective pour identifier ce qu'il faut arreter, commencer ou continuer.

**Methodologie** : Methode de retrospective simple et efficace en 3 colonnes.

| Colonne | Question | Couleur |
|---------|----------|---------|
| **Stop** | Qu'est-ce qu'on arrete de faire ? | Rouge |
| **Start** | Qu'est-ce qu'on commence a faire ? | Vert |
| **Continue** | Qu'est-ce qu'on continue de faire ? | Bleu |

**Fonctionnement** :
1. Decrire le contexte (projet, equipe)
2. Ajouter des elements dans chaque colonne
3. Ajouter des notes complementaires
4. Partager la retrospective avec le formateur

---

### Outils Collaboratifs

#### Carte Mentale (Mind Map)

> Creation collaborative de cartes mentales.

**Fonctionnement** :
1. Une carte mentale unique par session (collaborative)
2. Le noeud central represente l'idee principale
3. Ajouter des branches (noeuds enfants) :
   - Texte principal
   - Note complementaire
   - Couleur (violet, bleu, vert, jaune, orange, rouge, rose, gris)
   - Icone (idee, question, check, avertissement, etoile, cible, personnes, outils, calendrier, argent)
   - Fichier attache (URL)
4. Organisation hierarchique par glisser-deposer
5. Collaboration en temps reel entre participants

---

#### Tableau Blanc (Whiteboard)

> Tableau blanc collaboratif avec post-its, dessins et formes.

**Fonctionnement** :
1. Un tableau blanc unique par session (collaboratif)
2. Outils disponibles :
   - **Post-its** : notes adhesives colorees
   - **Texte** : ajout de texte libre
   - **Formes** : rectangle, cercle
   - **Fleches et lignes** : pour relier des elements
   - **Dessin libre** : trait a main levee
3. 8 couleurs disponibles (jaune, rose, bleu, vert, orange, violet, blanc, gris)
4. Gestion de la superposition (z-index)
5. Verrouillage d'elements
6. Collaboration en temps reel

---

### Creativite & Reflexion

#### Six Chapeaux de Bono

> Methode des six chapeaux de la reflexion pour structurer la pensee de groupe.

**Methodologie** : Chaque chapeau represente un mode de pensee different, permettant d'explorer un sujet sous tous les angles.

| Chapeau | Couleur | Mode de pensee |
|---------|---------|----------------|
| ⬜ Blanc | Gris/Blanc | Faits, donnees, informations objectives |
| 🟥 Rouge | Rouge | Emotions, sentiments, intuitions |
| ⬛ Noir | Noir | Prudence, critique, risques, problemes |
| 🟨 Jaune | Jaune | Optimisme, avantages, aspects positifs |
| 🟩 Vert | Vert | Creativite, nouvelles idees, alternatives |
| 🟦 Bleu | Bleu | Organisation, controle du processus, synthese |

**Fonctionnement** :
1. Le formateur definit un **sujet de reflexion**
2. Les participants cliquent sur un chapeau pour ajouter un avis dans ce mode de pensee
3. Chaque participant peut ajouter autant d'avis que souhaite sous chaque chapeau
4. Les avis sont modifiables et supprimables
5. Le bouton **Partager tous mes avis** rend les contributions visibles au formateur

**Vue formateur - 3 modes d'affichage** :
- **Grille par chapeau** : avis regroupes par couleur
- **Liste chronologique** : tous les avis tries par date
- **Par participant** : avis regroupes par personne

**Filtres** : cliquer sur un chapeau pour ne voir que les avis de ce type

**Synthese IA** (super-admin uniquement) : bouton "Synthese IA" dans la vue session, genere via Claude un resume couleur par couleur et une synthese globale positif/negatif avec recommandations. La synthese est imprimable.

---

## 8. FAQ et depannage

### Questions frequentes

**Q : J'ai perdu mon code de session, que faire ?**
R : Demandez au formateur de vous communiquer le code a nouveau. Le code est visible dans son tableau de bord.

**Q : Je ne vois pas mes avis dans la vue formateur**
R : Avez-vous clique sur **Partager** ? Les avis non partages ne sont visibles que de vous. Le formateur peut activer l'option "Voir tous les avis" pour voir les avis non partages.

**Q : Mon travail a-t-il ete sauvegarde ?**
R : La plupart des applications sauvegardent automatiquement toutes les secondes. Si vous voyez un indicateur de completion (%), vos donnees sont sauvegardees.

**Q : Puis-je modifier mon travail apres l'avoir partage ?**
R : Oui, vous pouvez modifier vos contributions a tout moment. Les modifications sont visibles en temps reel par le formateur.

**Q : Comment changer de session ?**
R : Deconnectez-vous (bouton Deconnexion), puis reconnectez-vous avec un nouveau code de session.

**Q : L'application ne repond plus**
R : Rechargez la page (F5). Vos donnees sont sauvegardees sur le serveur et ne seront pas perdues.

### Pour les formateurs

**Q : Comment empecher de nouveaux participants de rejoindre ?**
R : Desactivez la session depuis le tableau de bord formateur. Les participants existants peuvent toujours travailler, mais aucun nouveau participant ne peut rejoindre.

**Q : Puis-je supprimer un participant ?**
R : Oui, un bouton de suppression est disponible dans la liste des participants du tableau de bord.

**Q : Comment recuperer les resultats ?**
R : Utilisez le bouton **Imprimer** depuis la vue globale, ou exportez en JSON/Excel selon l'application.

### Support technique

- **Navigateur recommande** : Chrome ou Firefox, derniere version
- **Cookies** : doivent etre actives (necessaire pour la session)
- **JavaScript** : doit etre active (necessaire pour l'interactivite)
- En cas de probleme persistant, contacter l'administrateur de la plateforme
