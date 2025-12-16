<?php
require_once 'config.php';
requireAdmin();

$user = getCurrentUser();
$db = getDB();

// Récupérer tous les arbres (partagés ou non pour l'admin)
$showAll = isset($_GET['all']);
$query = $showAll
    ? "SELECT a.*, u.username FROM arbres a JOIN users u ON a.user_id = u.id ORDER BY a.updated_at DESC"
    : "SELECT a.*, u.username FROM arbres a JOIN users u ON a.user_id = u.id WHERE a.is_shared = 1 ORDER BY a.updated_at DESC";
$stmt = $db->query($query);
$arbres = $stmt->fetchAll();

// Récupérer un arbre spécifique si demandé
$selectedArbre = null;
if (isset($_GET['view'])) {
    $stmt = $db->prepare("SELECT a.*, u.username FROM arbres a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
    $stmt->execute([$_GET['view']]);
    $selectedArbre = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interface Formateur - <?= APP_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header h1 { font-size: 1.5rem; }
        .header-actions { display: flex; gap: 10px; align-items: center; }
        .header a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
        }
        .header a:hover { background: rgba(255,255,255,0.3); }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number { font-size: 2.5rem; font-weight: bold; color: #667eea; }
        .stat-label { color: #666; margin-top: 5px; }

        .main-content {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
        }
        @media (max-width: 900px) {
            .main-content { grid-template-columns: 1fr; }
        }

        .participants-list {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .list-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .list-header a { font-size: 0.85rem; color: #667eea; text-decoration: none; }
        .participant-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }
        .participant-item:hover { background: #f8f9fa; }
        .participant-item.active { background: #e8f0fe; border-left: 4px solid #667eea; }
        .participant-name { font-weight: 600; color: #333; }
        .participant-project { font-size: 0.9rem; color: #666; margin-top: 3px; }
        .participant-meta {
            font-size: 0.8rem;
            color: #999;
            margin-top: 5px;
            display: flex;
            gap: 15px;
        }
        .shared-badge {
            background: #d4edda;
            color: #155724;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
        }
        .not-shared-badge {
            background: #f8d7da;
            color: #721c24;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
        }

        .arbre-detail {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .detail-header {
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .detail-title { font-size: 1.5rem; color: #333; margin-bottom: 10px; }
        .detail-meta { color: #666; font-size: 0.9rem; }

        .arbre-section {
            margin-bottom: 30px;
            padding: 20px;
            border-radius: 8px;
        }
        .section-problemes { background: #fff7ed; border: 2px solid #fb923c; }
        .section-solutions { background: #f0fdf4; border: 2px solid #22c55e; }
        .section-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-problemes .section-title { color: #ea580c; }
        .section-solutions .section-title { color: #16a34a; }

        .central-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
            border: 2px dashed #ccc;
        }
        .central-label { font-size: 0.85rem; color: #666; margin-bottom: 8px; }
        .central-text { font-size: 1.1rem; font-weight: 600; }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        @media (max-width: 600px) {
            .items-grid { grid-template-columns: 1fr; }
        }
        .items-column h4 {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .item-card {
            background: white;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 4px solid;
        }
        .consequence-card { border-color: #dc2626; }
        .cause-card { border-color: #d97706; }
        .objectif-card { border-color: #16a34a; }
        .moyen-card { border-color: #2563eb; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state h3 { margin-bottom: 10px; color: #333; }

        .no-selection {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 400px;
            color: #999;
            font-size: 1.1rem;
        }

        .btn-print {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-print:hover { background: #5a6fd6; }

        @media print {
            .header, .participants-list, .btn-print { display: none !important; }
            .main-content { grid-template-columns: 1fr; }
            .arbre-detail { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Interface Formateur</h1>
                <span style="opacity: 0.8;">Connecté : <?= sanitize($user['username']) ?></span>
            </div>
            <div class="header-actions">
                <a href="index.php">Mon Arbre</a>
                <a href="logout.php">Déconnexion</a>
            </div>
        </div>

        <?php
        $totalParticipants = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();
        $totalArbres = $db->query("SELECT COUNT(*) FROM arbres")->fetchColumn();
        $arbresPartages = $db->query("SELECT COUNT(*) FROM arbres WHERE is_shared = 1")->fetchColumn();
        ?>
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= $totalParticipants ?></div>
                <div class="stat-label">Participants inscrits</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalArbres ?></div>
                <div class="stat-label">Arbres créés</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $arbresPartages ?></div>
                <div class="stat-label">Arbres partagés</div>
            </div>
        </div>

        <div class="main-content">
            <div class="participants-list">
                <div class="list-header">
                    <span>Participants</span>
                    <?php if ($showAll): ?>
                        <a href="admin.php">Voir partagés seulement</a>
                    <?php else: ?>
                        <a href="admin.php?all=1">Voir tous</a>
                    <?php endif; ?>
                </div>

                <?php if (empty($arbres)): ?>
                    <div class="empty-state">
                        <h3>Aucun arbre <?= $showAll ? '' : 'partagé' ?></h3>
                        <p>Les participants n'ont pas encore <?= $showAll ? 'créé' : 'partagé' ?> d'arbres.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($arbres as $arbre): ?>
                        <a href="?view=<?= $arbre['id'] ?><?= $showAll ? '&all=1' : '' ?>" style="text-decoration: none; color: inherit;">
                            <div class="participant-item <?= ($selectedArbre && $selectedArbre['id'] == $arbre['id']) ? 'active' : '' ?>">
                                <div class="participant-name"><?= sanitize($arbre['username']) ?></div>
                                <div class="participant-project"><?= sanitize($arbre['nom_projet'] ?: 'Sans titre') ?></div>
                                <div class="participant-meta">
                                    <span><?= date('d/m/Y H:i', strtotime($arbre['updated_at'])) ?></span>
                                    <?php if ($arbre['is_shared']): ?>
                                        <span class="shared-badge">Partagé</span>
                                    <?php else: ?>
                                        <span class="not-shared-badge">Non partagé</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="arbre-detail">
                <?php if ($selectedArbre): ?>
                    <div class="detail-header">
                        <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 15px;">
                            <div>
                                <div class="detail-title"><?= sanitize($selectedArbre['nom_projet'] ?: 'Sans titre') ?></div>
                                <div class="detail-meta">
                                    <strong>Participant :</strong> <?= sanitize($selectedArbre['username']) ?><br>
                                    <strong>Groupe :</strong> <?= sanitize($selectedArbre['participants'] ?: 'Non spécifié') ?><br>
                                    <strong>Dernière modification :</strong> <?= date('d/m/Y à H:i', strtotime($selectedArbre['updated_at'])) ?>
                                </div>
                            </div>
                            <button onclick="window.print()" class="btn-print">Imprimer</button>
                        </div>
                    </div>

                    <!-- Arbre à Problèmes -->
                    <div class="arbre-section section-problemes">
                        <div class="section-title">Arbre à Problèmes</div>

                        <div class="central-box">
                            <div class="central-label">Problème Central</div>
                            <div class="central-text"><?= sanitize($selectedArbre['probleme_central'] ?: 'Non défini') ?></div>
                        </div>

                        <div class="items-grid">
                            <div class="items-column">
                                <h4>Conséquences</h4>
                                <?php
                                $consequences = json_decode($selectedArbre['consequences'] ?? '[]', true);
                                if (empty($consequences)): ?>
                                    <p style="color: #999; font-style: italic;">Aucune conséquence</p>
                                <?php else:
                                    foreach ($consequences as $c): ?>
                                        <div class="item-card consequence-card"><?= sanitize($c) ?></div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                            <div class="items-column">
                                <h4>Causes</h4>
                                <?php
                                $causes = json_decode($selectedArbre['causes'] ?? '[]', true);
                                if (empty($causes)): ?>
                                    <p style="color: #999; font-style: italic;">Aucune cause</p>
                                <?php else:
                                    foreach ($causes as $c): ?>
                                        <div class="item-card cause-card"><?= sanitize($c) ?></div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Arbre à Solutions -->
                    <div class="arbre-section section-solutions">
                        <div class="section-title">Arbre à Solutions</div>

                        <div class="central-box">
                            <div class="central-label">Objectif Central</div>
                            <div class="central-text"><?= sanitize($selectedArbre['objectif_central'] ?: 'Non défini') ?></div>
                        </div>

                        <div class="items-grid">
                            <div class="items-column">
                                <h4>Objectifs</h4>
                                <?php
                                $objectifs = json_decode($selectedArbre['objectifs'] ?? '[]', true);
                                if (empty($objectifs)): ?>
                                    <p style="color: #999; font-style: italic;">Aucun objectif</p>
                                <?php else:
                                    foreach ($objectifs as $o): ?>
                                        <div class="item-card objectif-card"><?= sanitize($o) ?></div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                            <div class="items-column">
                                <h4>Moyens</h4>
                                <?php
                                $moyens = json_decode($selectedArbre['moyens'] ?? '[]', true);
                                if (empty($moyens)): ?>
                                    <p style="color: #999; font-style: italic;">Aucun moyen</p>
                                <?php else:
                                    foreach ($moyens as $m): ?>
                                        <div class="item-card moyen-card"><?= sanitize($m) ?></div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="no-selection">
                        Sélectionnez un participant pour voir son arbre
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
