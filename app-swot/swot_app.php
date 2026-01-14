<?php
/**
 * Application SWOT/TOWS - Version multi-utilisateurs
 */
session_start();

// Vérifier l'authentification
if (!isset($_SESSION['participant_id'])) {
    header('Location: index.php');
    exit;
}

// Configuration et système de traduction
require_once __DIR__ . '/../shared-auth/config.php';
require_once __DIR__ . '/../shared-auth/lang.php';

$participantId = $_SESSION['participant_id'];
$participantNom = $_SESSION['participant_nom'];
$participantPrenom = $_SESSION['participant_prenom'];
$sessionName = $_SESSION['session_name'];

// Obtenir la langue actuelle
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('swot.title') ?> - <?= htmlspecialchars($participantPrenom . ' ' . $participantNom) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .user-bar {
            max-width: 1200px;
            margin: 0 auto 15px auto;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }

        .user-details {
            line-height: 1.3;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .session-name {
            font-size: 12px;
            color: #7f8c8d;
        }

        .user-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-draft {
            background: #fff3cd;
            color: #856404;
        }

        .status-submitted {
            background: #d4edda;
            color: #155724;
        }

        .btn-logout {
            padding: 8px 16px;
            background: #ecf0f1;
            color: #2c3e50;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: #dfe6e9;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 30px;
            backdrop-filter: blur(10px);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 2.5em;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: #7f8c8d;
            font-size: 1.1em;
            font-weight: 300;
        }

        .controls {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }

        .btn-success {
            background: linear-gradient(45deg, #27ae60, #2ecc71);
            color: white;
        }

        .btn-secondary {
            background: #ecf0f1;
            color: #2c3e50;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .swot-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .swot-quadrant {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 3px solid transparent;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .swot-quadrant::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color);
        }

        .swot-quadrant:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .swot-quadrant.strengths {
            --accent-color: #27ae60;
            --accent-color-dark: #219a52;
            --accent-rgb: 39, 174, 96;
        }

        .swot-quadrant.weaknesses {
            --accent-color: #e74c3c;
            --accent-color-dark: #c0392b;
            --accent-rgb: 231, 76, 60;
        }

        .swot-quadrant.opportunities {
            --accent-color: #3498db;
            --accent-color-dark: #2980b9;
            --accent-rgb: 52, 152, 219;
        }

        .swot-quadrant.threats {
            --accent-color: #f39c12;
            --accent-color-dark: #e67e22;
            --accent-rgb: 243, 156, 18;
        }

        .quadrant-title {
            font-size: 1.4em;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--accent-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quadrant-icon {
            font-size: 1.2em;
        }

        .input-section {
            margin-bottom: 20px;
        }

        .input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .item-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #ecf0f1;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .item-input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(var(--accent-rgb), 0.1);
        }

        .add-btn {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .add-btn:hover {
            background: var(--accent-color-dark);
            transform: scale(1.05);
        }

        .items-list {
            list-style: none;
        }

        .item {
            background: #f8f9fa;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid var(--accent-color);
            transition: all 0.3s ease;
        }

        .item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .item-text {
            flex: 1;
            color: #2c3e50;
            font-weight: 500;
        }

        .delete-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .delete-btn:hover {
            background: #c0392b;
            transform: scale(1.1);
        }

        .export-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .export-title {
            color: #2c3e50;
            font-size: 1.3em;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .ngo-suggestions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .suggestion-title {
            color: #856404;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .suggestion-text {
            color: #856404;
            font-size: 13px;
            line-height: 1.4;
        }

        .tows-source {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #2c3e50;
        }

        .export-content {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #2c3e50;
        }

        .export-quadrant {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }

        .export-quadrant h3 {
            color: var(--accent-color);
            font-size: 1.2em;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid var(--accent-color);
        }

        .export-list {
            list-style: none;
            padding-left: 0;
        }

        .export-list li {
            padding: 8px 0;
            border-bottom: 1px solid #ecf0f1;
            position: relative;
            padding-left: 20px;
        }

        .export-list li:before {
            content: ">";
            color: var(--accent-color);
            position: absolute;
            left: 0;
            top: 8px;
        }

        #towsSection {
            display: none;
        }

        .save-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1000;
        }

        .save-indicator.saving {
            background: #fff3cd;
            color: #856404;
            opacity: 1;
        }

        .save-indicator.saved {
            background: #d4edda;
            color: #155724;
            opacity: 1;
        }

        .save-indicator.error {
            background: #f8d7da;
            color: #721c24;
            opacity: 1;
        }

        @media (max-width: 768px) {
            .swot-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 20px;
            }

            .header h1 {
                font-size: 2em;
            }

            .controls {
                flex-direction: column;
                align-items: center;
            }

            .user-bar {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }

        @media print {
            .modal-header, .btn, .user-bar {
                display: none;
            }

            .modal-content {
                box-shadow: none;
                margin: 0;
                padding: 20px;
                max-height: none;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Barre utilisateur -->
    <div class="user-bar">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($participantPrenom, 0, 1) . substr($participantNom, 0, 1)) ?></div>
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($participantPrenom . ' ' . $participantNom) ?></div>
                <div class="session-name"><?= htmlspecialchars($sessionName) ?></div>
            </div>
        </div>
        <div class="user-actions">
            <?= renderLanguageSelector('text-sm border rounded px-2 py-1') ?>
            <span class="status-badge status-draft" id="statusBadge"><?= t('swot.draft') ?></span>
            <?php if (isFormateur()): ?>
            <a href="formateur.php" class="btn-logout" style="background: #10b981; color: white; text-decoration: none;"><?= t('trainer.title') ?></a>
            <?php endif; ?>
            <button class="btn-logout" onclick="logout()"><?= t('swot.logout') ?></button>
        </div>
    </div>
    <?= renderLanguageScript() ?>

    <!-- Indicateur de sauvegarde -->
    <div class="save-indicator" id="saveIndicator"></div>

    <!-- Section SWOT -->
    <div class="container" id="swotSection">
        <div class="header">
            <h1><?= t('swot.swot_title') ?></h1>
            <p><?= t('swot.subtitle') ?></p>
        </div>

        <div class="ngo-suggestions">
            <div class="suggestion-title"><?= t('swot.tips_for_ngo') ?></div>
            <div class="suggestion-text">
                <?= t('swot.tips_general') ?>
            </div>
        </div>

        <div class="controls">
            <button class="btn btn-primary" onclick="exportSWOT()"><?= t('swot.export_analysis') ?></button>
            <button class="btn btn-secondary" onclick="clearAll()"><?= t('swot.clear_all') ?></button>
            <button class="btn btn-success" onclick="submitAnalysis()"><?= t('swot.submit_analysis') ?></button>
        </div>

        <div class="swot-grid">
            <!-- FORCES -->
            <div class="swot-quadrant strengths">
                <div class="quadrant-title">
                    <span class="quadrant-icon"><?= t('swot.strengths') ?></span>
                    (Strengths)
                </div>
                <div class="ngo-suggestions">
                    <div class="suggestion-title"><?= t('swot.examples_for_ngo') ?></div>
                    <div class="suggestion-text"><?= t('swot.examples_strengths') ?></div>
                </div>
                <div class="input-section">
                    <div class="input-group">
                        <input type="text" class="item-input" id="strengths-input" placeholder="<?= t('swot.add_strength') ?>">
                        <button class="add-btn" onclick="addItem('strengths')"><?= t('swot.add') ?></button>
                    </div>
                </div>
                <ul class="items-list" id="strengths-list"></ul>
            </div>

            <!-- FAIBLESSES -->
            <div class="swot-quadrant weaknesses">
                <div class="quadrant-title">
                    <span class="quadrant-icon"><?= t('swot.weaknesses') ?></span>
                    (Weaknesses)
                </div>
                <div class="ngo-suggestions">
                    <div class="suggestion-title"><?= t('swot.examples_for_ngo') ?></div>
                    <div class="suggestion-text"><?= t('swot.examples_weaknesses') ?></div>
                </div>
                <div class="input-section">
                    <div class="input-group">
                        <input type="text" class="item-input" id="weaknesses-input" placeholder="<?= t('swot.add_weakness') ?>">
                        <button class="add-btn" onclick="addItem('weaknesses')"><?= t('swot.add') ?></button>
                    </div>
                </div>
                <ul class="items-list" id="weaknesses-list"></ul>
            </div>

            <!-- OPPORTUNITES -->
            <div class="swot-quadrant opportunities">
                <div class="quadrant-title">
                    <span class="quadrant-icon"><?= t('swot.opportunities') ?></span>
                    (Opportunities)
                </div>
                <div class="ngo-suggestions">
                    <div class="suggestion-title"><?= t('swot.examples_for_ngo') ?></div>
                    <div class="suggestion-text"><?= t('swot.examples_opportunities') ?></div>
                </div>
                <div class="input-section">
                    <div class="input-group">
                        <input type="text" class="item-input" id="opportunities-input" placeholder="<?= t('swot.add_opportunity') ?>">
                        <button class="add-btn" onclick="addItem('opportunities')"><?= t('swot.add') ?></button>
                    </div>
                </div>
                <ul class="items-list" id="opportunities-list"></ul>
            </div>

            <!-- MENACES -->
            <div class="swot-quadrant threats">
                <div class="quadrant-title">
                    <span class="quadrant-icon"><?= t('swot.threats') ?></span>
                    (Threats)
                </div>
                <div class="ngo-suggestions">
                    <div class="suggestion-title"><?= t('swot.examples_for_ngo') ?></div>
                    <div class="suggestion-text"><?= t('swot.examples_threats') ?></div>
                </div>
                <div class="input-section">
                    <div class="input-group">
                        <input type="text" class="item-input" id="threats-input" placeholder="<?= t('swot.add_threat') ?>">
                        <button class="add-btn" onclick="addItem('threats')"><?= t('swot.add') ?></button>
                    </div>
                </div>
                <ul class="items-list" id="threats-list"></ul>
            </div>
        </div>

        <div class="export-section">
            <div class="export-title"><?= t('swot.ready_for_strategic') ?></div>
            <p style="color: #7f8c8d; margin-bottom: 20px;">
                <?= t('swot.complete_swot_first') ?>
            </p>
            <button class="btn btn-primary" onclick="goToTOWS()" style="margin-top: 10px;">
                <?= t('swot.go_to_tows') ?>
            </button>
        </div>
    </div>

    <!-- Section TOWS -->
    <div class="container" id="towsSection">
        <div class="header">
            <h1><?= t('swot.tows_title') ?></h1>
            <p><?= t('swot.tows_subtitle') ?></p>
            <button class="btn btn-secondary" onclick="backToSWOT()" style="margin-top: 15px;">
                <?= t('swot.back_to_swot') ?>
            </button>
        </div>

        <div class="ngo-suggestions">
            <div class="suggestion-title"><?= t('swot.tows_method') ?></div>
            <div class="suggestion-text">
                <?= t('swot.tows_method_desc') ?>
            </div>
        </div>

        <div class="controls">
            <button class="btn btn-primary" onclick="exportTOWS()"><?= t('swot.export_tows') ?></button>
            <button class="btn btn-secondary" onclick="clearTOWS()"><?= t('swot.clear_tows') ?></button>
            <button class="btn btn-secondary" onclick="generateTOWSSuggestions()"><?= t('swot.auto_suggestions') ?></button>
            <button class="btn btn-success" onclick="submitAnalysis()"><?= t('swot.submit_analysis') ?></button>
        </div>

        <div class="swot-grid">
            <!-- SO: Forces + Opportunites -->
            <div class="swot-quadrant strengths">
                <div class="quadrant-title">
                    <span class="quadrant-icon"><?= t('swot.strategies_so') ?></span>
                    (<?= t('swot.so_desc') ?>)
                </div>
                <div class="ngo-suggestions">
                    <div class="suggestion-title"><?= t('swot.maxi_maxi') ?></div>
                    <div class="suggestion-text"><?= t('swot.how_use_strengths_opp') ?></div>
                </div>
                <div class="tows-source">
                    <strong><?= t('swot.based_on_strengths') ?></strong> <span id="so-strengths"><?= t('swot.add_swot_first') ?></span><br>
                    <strong><?= t('swot.based_on_opportunities') ?></strong> <span id="so-opportunities"><?= t('swot.add_swot_first') ?></span>
                </div>
                <div class="input-section">
                    <div class="input-group">
                        <input type="text" class="item-input" id="so-input" placeholder="<?= t('swot.strategy_so') ?>">
                        <button class="add-btn" onclick="addTOWSItem('so')"><?= t('swot.add') ?></button>
                    </div>
                </div>
                <ul class="items-list" id="so-list"></ul>
            </div>

            <!-- WO: Faiblesses + Opportunites -->
            <div class="swot-quadrant opportunities">
                <div class="quadrant-title">
                    <span class="quadrant-icon"><?= t('swot.strategies_wo') ?></span>
                    (<?= t('swot.wo_desc') ?>)
                </div>
                <div class="ngo-suggestions">
                    <div class="suggestion-title"><?= t('swot.mini_maxi') ?></div>
                    <div class="suggestion-text"><?= t('swot.how_overcome_weaknesses') ?></div>
                </div>
                <div class="tows-source">
                    <strong><?= t('swot.based_on_weaknesses') ?></strong> <span id="wo-weaknesses"><?= t('swot.add_swot_first') ?></span><br>
                    <strong><?= t('swot.based_on_opportunities') ?></strong> <span id="wo-opportunities"><?= t('swot.add_swot_first') ?></span>
                </div>
                <div class="input-section">
                    <div class="input-group">
                        <input type="text" class="item-input" id="wo-input" placeholder="<?= t('swot.strategy_wo') ?>">
                        <button class="add-btn" onclick="addTOWSItem('wo')"><?= t('swot.add') ?></button>
                    </div>
                </div>
                <ul class="items-list" id="wo-list"></ul>
            </div>

            <!-- ST: Forces + Menaces -->
            <div class="swot-quadrant threats">
                <div class="quadrant-title">
                    <span class="quadrant-icon"><?= t('swot.strategies_st') ?></span>
                    (<?= t('swot.st_desc') ?>)
                </div>
                <div class="ngo-suggestions">
                    <div class="suggestion-title"><?= t('swot.maxi_mini') ?></div>
                    <div class="suggestion-text"><?= t('swot.how_use_strengths_threats') ?></div>
                </div>
                <div class="tows-source">
                    <strong><?= t('swot.based_on_strengths') ?></strong> <span id="st-strengths"><?= t('swot.add_swot_first') ?></span><br>
                    <strong><?= t('swot.based_on_threats') ?></strong> <span id="st-threats"><?= t('swot.add_swot_first') ?></span>
                </div>
                <div class="input-section">
                    <div class="input-group">
                        <input type="text" class="item-input" id="st-input" placeholder="<?= t('swot.strategy_st') ?>">
                        <button class="add-btn" onclick="addTOWSItem('st')"><?= t('swot.add') ?></button>
                    </div>
                </div>
                <ul class="items-list" id="st-list"></ul>
            </div>

            <!-- WT: Faiblesses + Menaces -->
            <div class="swot-quadrant weaknesses">
                <div class="quadrant-title">
                    <span class="quadrant-icon"><?= t('swot.strategies_wt') ?></span>
                    (<?= t('swot.wt_desc') ?>)
                </div>
                <div class="ngo-suggestions">
                    <div class="suggestion-title"><?= t('swot.mini_mini') ?></div>
                    <div class="suggestion-text"><?= t('swot.how_minimize_weaknesses') ?></div>
                </div>
                <div class="tows-source">
                    <strong><?= t('swot.based_on_weaknesses') ?></strong> <span id="wt-weaknesses"><?= t('swot.add_swot_first') ?></span><br>
                    <strong><?= t('swot.based_on_threats') ?></strong> <span id="wt-threats"><?= t('swot.add_swot_first') ?></span>
                </div>
                <div class="input-section">
                    <div class="input-group">
                        <input type="text" class="item-input" id="wt-input" placeholder="<?= t('swot.strategy_wt') ?>">
                        <button class="add-btn" onclick="addTOWSItem('wt')"><?= t('swot.add') ?></button>
                    </div>
                </div>
                <ul class="items-list" id="wt-list"></ul>
            </div>
        </div>

        <div class="export-section">
            <div class="export-title"><?= t('swot.action_plan') ?></div>
            <p style="color: #7f8c8d; margin-bottom: 20px;">
                <?= t('swot.prioritize_strategies') ?>
            </p>
        </div>
    </div>

    <!-- Modal d'export SWOT -->
    <div id="exportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?= t('swot.swot_export') ?></h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="controls" style="margin-bottom: 20px;">
                <button class="btn btn-primary" onclick="printSWOT()"><?= t('swot.print') ?></button>
                <button class="btn btn-primary" onclick="exportToPDF()"><?= t('swot.export_pdf') ?></button>
                <button class="btn btn-secondary" onclick="copySWOT()"><?= t('swot.copy_text') ?></button>
            </div>
            <div id="exportContent" class="export-content"></div>
        </div>
    </div>

    <!-- Modal d'export TOWS -->
    <div id="exportTOWSModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?= t('swot.tows_export') ?></h2>
                <span class="close" onclick="closeTOWSModal()">&times;</span>
            </div>
            <div class="controls" style="margin-bottom: 20px;">
                <button class="btn btn-primary" onclick="printTOWS()"><?= t('swot.print') ?></button>
                <button class="btn btn-primary" onclick="exportTOWSToPDF()"><?= t('swot.export_pdf') ?></button>
                <button class="btn btn-secondary" onclick="copyTOWS()"><?= t('swot.copy_text') ?></button>
            </div>
            <div id="exportTOWSContent" class="export-content"></div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
        // Traductions pour JavaScript
        const T = {
            draft: <?= json_encode(t('swot.draft')) ?>,
            submitted: <?= json_encode(t('swot.submitted')) ?>,
            saving: <?= json_encode(t('swot.saving')) ?>,
            save_ok: <?= json_encode(t('swot.save_ok')) ?>,
            save_error: <?= json_encode(t('swot.save_error')) ?>,
            enter_text_first: <?= json_encode(t('swot.enter_text_first')) ?>,
            enter_strategy_first: <?= json_encode(t('swot.enter_strategy_first')) ?>,
            confirm_clear_swot: <?= json_encode(t('swot.confirm_clear_swot')) ?>,
            confirm_clear_tows: <?= json_encode(t('swot.confirm_clear_tows')) ?>,
            complete_swot_quadrants: <?= json_encode(t('swot.complete_swot_quadrants')) ?>,
            complete_before_submit: <?= json_encode(t('swot.complete_before_submit')) ?>,
            confirm_submit: <?= json_encode(t('swot.confirm_submit')) ?>,
            submit_success: <?= json_encode(t('swot.submit_success')) ?>,
            suggestions_added: <?= json_encode(t('swot.suggestions_added')) ?>,
            enough_strategies: <?= json_encode(t('swot.enough_strategies')) ?>,
            swot_copied: <?= json_encode(t('swot.swot_copied')) ?>,
            tows_copied: <?= json_encode(t('swot.tows_copied')) ?>,
            copy_error: <?= json_encode(t('swot.copy_error')) ?>,
            pdf_success: <?= json_encode(t('swot.pdf_success')) ?>,
            pdf_tows_success: <?= json_encode(t('swot.pdf_tows_success')) ?>,
            pdf_error: <?= json_encode(t('swot.pdf_error')) ?>,
            pdf_tows_error: <?= json_encode(t('swot.pdf_tows_error')) ?>,
            network_error: <?= json_encode(t('swot.network_error')) ?>,
            no_strength_defined: <?= json_encode(t('swot.no_strength_defined')) ?>,
            no_weakness_defined: <?= json_encode(t('swot.no_weakness_defined')) ?>,
            no_opportunity_defined: <?= json_encode(t('swot.no_opportunity_defined')) ?>,
            no_threat_defined: <?= json_encode(t('swot.no_threat_defined')) ?>,
            no_element_added: <?= json_encode(t('swot.no_element_added')) ?>,
            no_strategy_defined: <?= json_encode(t('swot.no_strategy_defined')) ?>,
            swot_title: <?= json_encode(t('swot.swot_title')) ?>,
            tows_title: <?= json_encode(t('swot.tows_title')) ?>,
            by: <?= json_encode(t('swot.by')) ?>,
            strengths: <?= json_encode(t('swot.strengths')) ?>,
            weaknesses: <?= json_encode(t('swot.weaknesses')) ?>,
            opportunities: <?= json_encode(t('swot.opportunities')) ?>,
            threats: <?= json_encode(t('swot.threats')) ?>,
            strategies_so: <?= json_encode(t('swot.strategies_so')) ?>,
            strategies_wo: <?= json_encode(t('swot.strategies_wo')) ?>,
            strategies_st: <?= json_encode(t('swot.strategies_st')) ?>,
            strategies_wt: <?= json_encode(t('swot.strategies_wt')) ?>,
            so_desc: <?= json_encode(t('swot.so_desc')) ?>,
            wo_desc: <?= json_encode(t('swot.wo_desc')) ?>,
            st_desc: <?= json_encode(t('swot.st_desc')) ?>,
            wt_desc: <?= json_encode(t('swot.wt_desc')) ?>
        };

        // Donnees SWOT et TOWS
        let swotData = {
            strengths: [],
            weaknesses: [],
            opportunities: [],
            threats: []
        };

        let towsData = {
            so: [],
            wo: [],
            st: [],
            wt: []
        };

        let isSubmitted = false;
        let saveTimeout = null;

        // ========== FONCTIONS DE SAUVEGARDE SERVEUR ==========

        function showSaveIndicator(status) {
            const indicator = document.getElementById('saveIndicator');
            indicator.className = 'save-indicator ' + status;

            if (status === 'saving') {
                indicator.textContent = T.saving;
            } else if (status === 'saved') {
                indicator.textContent = T.save_ok;
                setTimeout(() => {
                    indicator.style.opacity = '0';
                }, 2000);
            } else if (status === 'error') {
                indicator.textContent = T.save_error;
                setTimeout(() => {
                    indicator.style.opacity = '0';
                }, 3000);
            }
        }

        function saveToServer() {
            // Debounce pour eviter trop de requetes
            if (saveTimeout) {
                clearTimeout(saveTimeout);
            }

            saveTimeout = setTimeout(() => {
                showSaveIndicator('saving');

                fetch('api/save.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        swot: swotData,
                        tows: towsData
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSaveIndicator('saved');
                    } else {
                        showSaveIndicator('error');
                        console.error('Erreur sauvegarde:', data.error);
                    }
                })
                .catch(error => {
                    showSaveIndicator('error');
                    console.error('Erreur reseau:', error);
                });
            }, 500);
        }

        function loadFromServer() {
            fetch('api/load.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        swotData = data.data.swot;
                        towsData = data.data.tows;
                        isSubmitted = data.data.submitted;

                        // Mettre a jour l'interface
                        Object.keys(swotData).forEach(category => {
                            updateList(category);
                        });
                        Object.keys(towsData).forEach(category => {
                            updateTOWSList(category);
                        });
                        updateTOWSSource();
                        updateStatusBadge();
                    }
                })
                .catch(error => {
                    console.error('Erreur chargement:', error);
                });
        }

        function updateStatusBadge() {
            const badge = document.getElementById('statusBadge');
            if (isSubmitted) {
                badge.textContent = T.submitted;
                badge.className = 'status-badge status-submitted';
            } else {
                badge.textContent = T.draft;
                badge.className = 'status-badge status-draft';
            }
        }

        function submitAnalysis() {
            const totalSwot = Object.values(swotData).reduce((sum, arr) => sum + arr.length, 0);
            const totalTows = Object.values(towsData).reduce((sum, arr) => sum + arr.length, 0);

            if (totalSwot < 4) {
                alert(T.complete_before_submit);
                return;
            }

            if (!confirm(T.confirm_submit)) {
                return;
            }

            fetch('api/submit.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    isSubmitted = true;
                    updateStatusBadge();
                    alert(T.submit_success);
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                alert(T.network_error);
                console.error(error);
            });
        }

        function logout() {
            window.location.href = 'logout.php';
        }

        // ========== FONCTIONS SWOT ==========

        function addItem(category) {
            const input = document.getElementById(category + '-input');
            const text = input.value.trim();

            if (text === '') {
                alert(T.enter_text_first);
                return;
            }

            swotData[category].push(text);
            input.value = '';
            updateList(category);
            saveToServer();
        }

        function removeItem(category, index) {
            swotData[category].splice(index, 1);
            updateList(category);
            saveToServer();
        }

        function updateList(category) {
            const list = document.getElementById(category + '-list');
            list.innerHTML = '';

            swotData[category].forEach((item, index) => {
                const li = document.createElement('li');
                li.className = 'item';
                li.innerHTML = `
                    <span class="item-text">${escapeHtml(item)}</span>
                    <button class="delete-btn" onclick="removeItem('${category}', ${index})">X</button>
                `;
                list.appendChild(li);
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function clearAll() {
            if (confirm(T.confirm_clear_swot)) {
                swotData = {
                    strengths: [],
                    weaknesses: [],
                    opportunities: [],
                    threats: []
                };

                Object.keys(swotData).forEach(category => {
                    updateList(category);
                });
                saveToServer();
            }
        }

        // ========== FONCTIONS TOWS ==========

        function goToTOWS() {
            const totalItems = Object.values(swotData).reduce((sum, arr) => sum + arr.length, 0);
            if (totalItems < 4) {
                alert(T.complete_swot_quadrants);
                return;
            }

            document.getElementById('swotSection').style.display = 'none';
            document.getElementById('towsSection').style.display = 'block';
            updateTOWSSource();
        }

        function backToSWOT() {
            document.getElementById('swotSection').style.display = 'block';
            document.getElementById('towsSection').style.display = 'none';
        }

        function addTOWSItem(category) {
            const input = document.getElementById(category + '-input');
            const text = input.value.trim();

            if (text === '') {
                alert(T.enter_strategy_first);
                return;
            }

            towsData[category].push(text);
            input.value = '';
            updateTOWSList(category);
            saveToServer();
        }

        function removeTOWSItem(category, index) {
            towsData[category].splice(index, 1);
            updateTOWSList(category);
            saveToServer();
        }

        function updateTOWSList(category) {
            const list = document.getElementById(category + '-list');
            list.innerHTML = '';

            towsData[category].forEach((item, index) => {
                const li = document.createElement('li');
                li.className = 'item';
                li.innerHTML = `
                    <span class="item-text">${escapeHtml(item)}</span>
                    <button class="delete-btn" onclick="removeTOWSItem('${category}', ${index})">X</button>
                `;
                list.appendChild(li);
            });
        }

        function updateTOWSSource() {
            document.getElementById('so-strengths').textContent =
                swotData.strengths.length > 0 ? swotData.strengths.slice(0, 2).join(', ') + '...' : T.no_strength_defined;
            document.getElementById('so-opportunities').textContent =
                swotData.opportunities.length > 0 ? swotData.opportunities.slice(0, 2).join(', ') + '...' : T.no_opportunity_defined;

            document.getElementById('wo-weaknesses').textContent =
                swotData.weaknesses.length > 0 ? swotData.weaknesses.slice(0, 2).join(', ') + '...' : T.no_weakness_defined;
            document.getElementById('wo-opportunities').textContent =
                swotData.opportunities.length > 0 ? swotData.opportunities.slice(0, 2).join(', ') + '...' : T.no_opportunity_defined;

            document.getElementById('st-strengths').textContent =
                swotData.strengths.length > 0 ? swotData.strengths.slice(0, 2).join(', ') + '...' : T.no_strength_defined;
            document.getElementById('st-threats').textContent =
                swotData.threats.length > 0 ? swotData.threats.slice(0, 2).join(', ') + '...' : T.no_threat_defined;

            document.getElementById('wt-weaknesses').textContent =
                swotData.weaknesses.length > 0 ? swotData.weaknesses.slice(0, 2).join(', ') + '...' : T.no_weakness_defined;
            document.getElementById('wt-threats').textContent =
                swotData.threats.length > 0 ? swotData.threats.slice(0, 2).join(', ') + '...' : T.no_threat_defined;
        }

        function clearTOWS() {
            if (confirm(T.confirm_clear_tows)) {
                towsData = { so: [], wo: [], st: [], wt: [] };
                Object.keys(towsData).forEach(category => {
                    updateTOWSList(category);
                });
                saveToServer();
            }
        }

        function generateTOWSSuggestions() {
            const suggestions = {
                so: [
                    "Developper de nouveaux programmes en exploitant notre expertise reconnue",
                    "Elargir notre territoire d'action grace a nos partenariats solides",
                    "Creer une offre de formation basee sur notre savoir-faire"
                ],
                wo: [
                    "Former l'equipe aux outils numeriques pour acceder aux financements digitaux",
                    "Developper une strategie de communication pour saisir les opportunites mediatiques",
                    "Creer des partenariats pour pallier nos manques de ressources"
                ],
                st: [
                    "Utiliser notre reseau pour diversifier nos sources de financement",
                    "Capitaliser sur notre reputation pour maintenir notre position face a la concurrence",
                    "Exploiter notre ancrage local pour resister aux changements reglementaires"
                ],
                wt: [
                    "Mutualiser les couts avec d'autres associations pour reduire notre vulnerabilite",
                    "Developper des partenariats de secours en cas de reduction des subventions",
                    "Creer une reserve financiere pour faire face aux crises"
                ]
            };

            let added = false;
            Object.keys(suggestions).forEach(category => {
                if (towsData[category].length < 2) {
                    const suggestion = suggestions[category][Math.floor(Math.random() * suggestions[category].length)];
                    towsData[category].push(suggestion);
                    updateTOWSList(category);
                    added = true;
                }
            });

            if (added) {
                alert(T.suggestions_added);
                saveToServer();
            } else {
                alert(T.enough_strategies);
            }
        }

        // ========== FONCTIONS D'EXPORT ==========

        function exportSWOT() {
            const modal = document.getElementById('exportModal');
            const content = document.getElementById('exportContent');

            const categories = {
                strengths: { title: T.strengths + ' (Strengths)', color: '#27ae60' },
                weaknesses: { title: T.weaknesses + ' (Weaknesses)', color: '#e74c3c' },
                opportunities: { title: T.opportunities + ' (Opportunities)', color: '#3498db' },
                threats: { title: T.threats + ' (Threats)', color: '#f39c12' }
            };

            let html = `
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #2c3e50; margin-bottom: 10px;">${T.swot_title}</h1>
                    <p style="color: #7f8c8d;">${T.by} <?= htmlspecialchars($participantPrenom . ' ' . $participantNom) ?> - ${new Date().toLocaleDateString()}</p>
                </div>
            `;

            Object.keys(categories).forEach(category => {
                const cat = categories[category];
                html += `
                    <div class="export-quadrant ${category}" style="--accent-color: ${cat.color};">
                        <h3>${cat.title}</h3>
                `;

                if (swotData[category].length > 0) {
                    html += '<ul class="export-list">';
                    swotData[category].forEach(item => {
                        html += `<li>${escapeHtml(item)}</li>`;
                    });
                    html += '</ul>';
                } else {
                    html += `<p style="color: #95a5a6; font-style: italic;">${T.no_element_added}</p>`;
                }

                html += '</div>';
            });

            content.innerHTML = html;
            modal.style.display = 'block';
        }

        function closeModal() {
            document.getElementById('exportModal').style.display = 'none';
        }

        function printSWOT() {
            window.print();
        }

        function copySWOT() {
            const categories = {
                strengths: T.strengths.toUpperCase() + ' (STRENGTHS)',
                weaknesses: T.weaknesses.toUpperCase() + ' (WEAKNESSES)',
                opportunities: T.opportunities.toUpperCase() + ' (OPPORTUNITIES)',
                threats: T.threats.toUpperCase() + ' (THREATS)'
            };

            let text = `${T.swot_title.toUpperCase()}\n`;
            text += `${T.by}: <?= htmlspecialchars($participantPrenom . ' ' . $participantNom) ?>\n`;
            text += `Date: ${new Date().toLocaleDateString()}\n\n`;

            Object.keys(categories).forEach(category => {
                text += `${categories[category]}\n`;
                text += '='.repeat(categories[category].length) + '\n';

                if (swotData[category].length > 0) {
                    swotData[category].forEach(item => {
                        text += `- ${item}\n`;
                    });
                } else {
                    text += T.no_element_added + '\n';
                }
                text += '\n';
            });

            navigator.clipboard.writeText(text).then(() => {
                alert(T.swot_copied);
            }).catch(() => {
                alert(T.copy_error);
            });
        }

        function exportToPDF() {
            try {
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                const pageWidth = pdf.internal.pageSize.getWidth();
                const margin = 20;
                const lineHeight = 8;
                let currentY = 30;

                pdf.setFontSize(20);
                pdf.setTextColor(44, 62, 80);
                pdf.text(T.swot_title.toUpperCase(), pageWidth / 2, currentY, { align: 'center' });

                currentY += 10;
                pdf.setFontSize(12);
                pdf.setTextColor(127, 140, 141);
                pdf.text(`${T.by}: <?= htmlspecialchars($participantPrenom . ' ' . $participantNom) ?>`, pageWidth / 2, currentY, { align: 'center' });
                pdf.text(`Date: ${new Date().toLocaleDateString()}`, pageWidth / 2, currentY + 5, { align: 'center' });

                currentY += 25;

                const categories = {
                    strengths: { title: T.strengths.toUpperCase() + ' (STRENGTHS)', color: [39, 174, 96] },
                    weaknesses: { title: T.weaknesses.toUpperCase() + ' (WEAKNESSES)', color: [231, 76, 60] },
                    opportunities: { title: T.opportunities.toUpperCase() + ' (OPPORTUNITIES)', color: [52, 152, 219] },
                    threats: { title: T.threats.toUpperCase() + ' (THREATS)', color: [243, 156, 18] }
                };

                Object.keys(categories).forEach(category => {
                    const cat = categories[category];

                    if (currentY > 250) {
                        pdf.addPage();
                        currentY = 30;
                    }

                    pdf.setFontSize(14);
                    pdf.setTextColor(cat.color[0], cat.color[1], cat.color[2]);
                    pdf.text(cat.title, margin, currentY);

                    pdf.setDrawColor(cat.color[0], cat.color[1], cat.color[2]);
                    pdf.setLineWidth(0.5);
                    pdf.line(margin, currentY + 2, pageWidth - margin, currentY + 2);

                    currentY += 10;

                    pdf.setFontSize(10);
                    pdf.setTextColor(44, 62, 80);

                    if (swotData[category].length > 0) {
                        swotData[category].forEach(item => {
                            const lines = pdf.splitTextToSize(`- ${item}`, pageWidth - 2 * margin);
                            lines.forEach(line => {
                                if (currentY > 270) {
                                    pdf.addPage();
                                    currentY = 30;
                                }
                                pdf.text(line, margin, currentY);
                                currentY += lineHeight;
                            });
                        });
                    } else {
                        pdf.setTextColor(149, 165, 166);
                        pdf.text(T.no_element_added, margin, currentY);
                        currentY += lineHeight;
                    }

                    currentY += 10;
                });

                const fileName = `SWOT_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $participantPrenom . '_' . $participantNom) ?>_${new Date().toISOString().split('T')[0]}.pdf`;
                pdf.save(fileName);
                alert(T.pdf_success);

            } catch (error) {
                alert(T.pdf_error);
                console.error(error);
            }
        }

        function exportTOWS() {
            const modal = document.getElementById('exportTOWSModal');
            const content = document.getElementById('exportTOWSContent');

            const categories = {
                so: { title: T.strategies_so + ' (' + T.so_desc + ')', color: '#27ae60' },
                wo: { title: T.strategies_wo + ' (' + T.wo_desc + ')', color: '#3498db' },
                st: { title: T.strategies_st + ' (' + T.st_desc + ')', color: '#f39c12' },
                wt: { title: T.strategies_wt + ' (' + T.wt_desc + ')', color: '#e74c3c' }
            };

            let html = `
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #2c3e50; margin-bottom: 10px;">${T.tows_title}</h1>
                    <p style="color: #7f8c8d;">${T.by} <?= htmlspecialchars($participantPrenom . ' ' . $participantNom) ?> - ${new Date().toLocaleDateString()}</p>
                </div>
            `;

            Object.keys(categories).forEach(category => {
                const cat = categories[category];
                html += `
                    <div class="export-quadrant ${category}" style="--accent-color: ${cat.color};">
                        <h3>${cat.title}</h3>
                `;

                if (towsData[category].length > 0) {
                    html += '<ul class="export-list">';
                    towsData[category].forEach(item => {
                        html += `<li>${escapeHtml(item)}</li>`;
                    });
                    html += '</ul>';
                } else {
                    html += `<p style="color: #95a5a6; font-style: italic;">${T.no_strategy_defined}</p>`;
                }

                html += '</div>';
            });

            content.innerHTML = html;
            modal.style.display = 'block';
        }

        function closeTOWSModal() {
            document.getElementById('exportTOWSModal').style.display = 'none';
        }

        function printTOWS() {
            window.print();
        }

        function copyTOWS() {
            const categories = {
                so: T.strategies_so.toUpperCase() + ' (' + T.so_desc.toUpperCase() + ')',
                wo: T.strategies_wo.toUpperCase() + ' (' + T.wo_desc.toUpperCase() + ')',
                st: T.strategies_st.toUpperCase() + ' (' + T.st_desc.toUpperCase() + ')',
                wt: T.strategies_wt.toUpperCase() + ' (' + T.wt_desc.toUpperCase() + ')'
            };

            let text = `${T.tows_title.toUpperCase()}\n`;
            text += `${T.by}: <?= htmlspecialchars($participantPrenom . ' ' . $participantNom) ?>\n`;
            text += `Date: ${new Date().toLocaleDateString()}\n\n`;

            Object.keys(categories).forEach(category => {
                text += `${categories[category]}\n`;
                text += '='.repeat(categories[category].length) + '\n';

                if (towsData[category].length > 0) {
                    towsData[category].forEach(item => {
                        text += `- ${item}\n`;
                    });
                } else {
                    text += T.no_strategy_defined + '\n';
                }
                text += '\n';
            });

            navigator.clipboard.writeText(text).then(() => {
                alert(T.tows_copied);
            }).catch(() => {
                alert(T.copy_error);
            });
        }

        function exportTOWSToPDF() {
            try {
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                const pageWidth = pdf.internal.pageSize.getWidth();
                const margin = 20;
                const lineHeight = 8;
                let currentY = 30;

                pdf.setFontSize(20);
                pdf.setTextColor(44, 62, 80);
                pdf.text(T.tows_title.toUpperCase(), pageWidth / 2, currentY, { align: 'center' });

                currentY += 10;
                pdf.setFontSize(12);
                pdf.setTextColor(127, 140, 141);
                pdf.text(`${T.by}: <?= htmlspecialchars($participantPrenom . ' ' . $participantNom) ?>`, pageWidth / 2, currentY, { align: 'center' });
                pdf.text(`Date: ${new Date().toLocaleDateString()}`, pageWidth / 2, currentY + 5, { align: 'center' });

                currentY += 25;

                const categories = {
                    so: { title: T.strategies_so.toUpperCase() + ' (' + T.so_desc.toUpperCase() + ')', color: [39, 174, 96] },
                    wo: { title: T.strategies_wo.toUpperCase() + ' (' + T.wo_desc.toUpperCase() + ')', color: [52, 152, 219] },
                    st: { title: T.strategies_st.toUpperCase() + ' (' + T.st_desc.toUpperCase() + ')', color: [243, 156, 18] },
                    wt: { title: T.strategies_wt.toUpperCase() + ' (' + T.wt_desc.toUpperCase() + ')', color: [231, 76, 60] }
                };

                Object.keys(categories).forEach(category => {
                    const cat = categories[category];

                    if (currentY > 250) {
                        pdf.addPage();
                        currentY = 30;
                    }

                    pdf.setFontSize(14);
                    pdf.setTextColor(cat.color[0], cat.color[1], cat.color[2]);
                    pdf.text(cat.title, margin, currentY);

                    pdf.setDrawColor(cat.color[0], cat.color[1], cat.color[2]);
                    pdf.setLineWidth(0.5);
                    pdf.line(margin, currentY + 2, pageWidth - margin, currentY + 2);

                    currentY += 10;

                    pdf.setFontSize(10);
                    pdf.setTextColor(44, 62, 80);

                    if (towsData[category].length > 0) {
                        towsData[category].forEach(item => {
                            const lines = pdf.splitTextToSize(`- ${item}`, pageWidth - 2 * margin);
                            lines.forEach(line => {
                                if (currentY > 270) {
                                    pdf.addPage();
                                    currentY = 30;
                                }
                                pdf.text(line, margin, currentY);
                                currentY += lineHeight;
                            });
                        });
                    } else {
                        pdf.setTextColor(149, 165, 166);
                        pdf.text(T.no_strategy_defined, margin, currentY);
                        currentY += lineHeight;
                    }

                    currentY += 10;
                });

                const fileName = `TOWS_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $participantPrenom . '_' . $participantNom) ?>_${new Date().toISOString().split('T')[0]}.pdf`;
                pdf.save(fileName);
                alert(T.pdf_tows_success);

            } catch (error) {
                alert(T.pdf_tows_error);
                console.error(error);
            }
        }

        // ========== EVENT LISTENERS ==========

        document.addEventListener('DOMContentLoaded', function() {
            // Charger les donnees depuis le serveur
            loadFromServer();

            // Event listeners pour les touches Entree - SWOT
            ['strengths', 'weaknesses', 'opportunities', 'threats'].forEach(category => {
                const input = document.getElementById(category + '-input');
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        addItem(category);
                    }
                });
            });

            // Event listeners pour les touches Entree - TOWS
            ['so', 'wo', 'st', 'wt'].forEach(category => {
                const input = document.getElementById(category + '-input');
                if (input) {
                    input.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            addTOWSItem(category);
                        }
                    });
                }
            });
        });

        // Fermer les modals en cliquant a l'exterieur
        window.onclick = function(event) {
            const modal = document.getElementById('exportModal');
            const towsModal = document.getElementById('exportTOWSModal');
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == towsModal) {
                closeTOWSModal();
            }
        }
    </script>
</body>
</html>
