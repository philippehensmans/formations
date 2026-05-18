# Carte Mentale — App Mac avec synchronisation iCloud

Application macOS native (SwiftUI) reproduisant les fonctionnalités de l'app PHP `app-mindmap`, avec synchronisation automatique via iCloud Drive.

## Fonctionnalités

- Canvas infini avec **pan** (glisser le fond) et **zoom** (pinch trackpad ou boutons +/−)
- Nœuds avec **couleur**, **icône**, **note** et **lien URL**
- **Glisser-déposer** des nœuds pour les repositionner
- **Réparentage** par drag d'un nœud sur un autre
- Double-clic sur un nœud pour l'éditer
- **Export PDF** et **RTF**
- Synchronisation **iCloud Drive** automatique (fichiers `.mindmap`)

## Prérequis

- macOS 13 Ventura ou plus récent
- Xcode 15+
- Un compte Apple Developer (gratuit suffit pour usage personnel, payant pour distribuer)

## Installation

1. Ouvrir `MindMapMac.xcodeproj` dans Xcode
2. Sélectionner votre **Team** (Apple ID) dans *Signing & Capabilities*
3. Activer **iCloud** dans l'onglet *Signing & Capabilities* → *+ Capability* → *iCloud*
   - Cocher **CloudKit** si vous voulez la synchro base de données (optionnel)
   - Cocher **iCloud Documents** (obligatoire pour les fichiers `.mindmap`)
   - Le container `iCloud.com.formations.mindmap-mac` sera créé automatiquement
4. Compiler et lancer (⌘R)

## Synchronisation iCloud

Les fichiers `.mindmap` sont des documents JSON standard. La synchronisation se fait par **iCloud Drive** :

- Par défaut, les fichiers s'enregistrent où l'utilisateur choisit (Fichier > Enregistrer…)
- Pour une synchro automatique, l'utilisateur doit enregistrer dans son dossier **iCloud Drive**
- Les fichiers enregistrés dans iCloud Drive apparaissent sur tous les Macs connectés au même compte Apple

Pour une synchro transparente sans action de l'utilisateur, il faudrait utiliser le container iCloud ubiquitaire — activez `CloudDocuments` dans les entitlements et configurez `NSFileManager.default.url(forUbiquityContainerIdentifier:)` dans `MindMapMacApp.swift`.

## Structure du projet

```
MindMapMac/
├── MindMapMacApp.swift       — Point d'entrée (@main)
├── MindMapDocument.swift     — FileDocument (lecture/écriture JSON)
├── MindMap.swift             — Modèle de données de la carte
├── MindNode.swift            — Modèle d'un nœud + enums couleur/icône
├── ContentView.swift         — Vue principale (fenêtre)
├── MindMapCanvasView.swift   — Canvas infini (pan, zoom, nœuds, connexions)
├── NodeView.swift            — Vue d'un nœud individuel
├── NodeEditSheet.swift       — Feuille d'édition d'un nœud
├── ExportManager.swift       — Export PDF et RTF
├── Color+Hex.swift           — Extension Color(hex:)
├── Info.plist                — Configuration de l'app
└── MindMapMac.entitlements   — Permissions iCloud et sandbox
```

## Raccourcis clavier

| Action | Raccourci |
|--------|-----------|
| Nouveau fichier | ⌘N |
| Ouvrir | ⌘O |
| Enregistrer | ⌘S |
| Zoom + | Bouton + (toolbar) |
| Zoom − | Bouton − (toolbar) |
| Pinch zoom | Trackpad pinch |
| Éditer un nœud | Double-clic |
| Ajouter un enfant | Bouton + sur le nœud |
| Supprimer | Bouton × sur le nœud |
