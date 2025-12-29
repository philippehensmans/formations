<?php
/**
 * Export des bilans carbone au format CSV (compatible Excel)
 */
require_once __DIR__ . '/config.php';

$user = getLoggedUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$sharedDb = getSharedDB();
$type = $_GET['type'] ?? '';
$sessionId = intval($_GET['session'] ?? $_SESSION['current_session_id'] ?? 0);

// Charger les estimations pour les noms
$estimations = getEstimations();
$useCases = $estimations['use_cases'] ?? [];
$categories = $estimations['categories'] ?? [];

// Configuration CSV pour Excel (utiliser ; comme separateur pour FR)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . getFilename($type, $sessionId) . '"');

// BOM UTF-8 pour Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

if ($type === 'participant') {
    // Export du bilan personnel du participant
    exportParticipantData($output, $db, $user, $sessionId, $useCases, $categories);
} elseif ($type === 'session' && ($user['is_formateur'] || $user['is_admin'])) {
    // Export des donnees de session (formateur uniquement)
    exportSessionData($output, $db, $sharedDb, $sessionId, $useCases, $categories);
} else {
    fputcsv($output, ['Erreur: Type d\'export non valide ou acces refuse'], ';');
}

fclose($output);
exit;

function getFilename($type, $sessionId) {
    $date = date('Y-m-d');
    if ($type === 'participant') {
        return "mon-bilan-carbone-ia-{$date}.csv";
    } else {
        return "session-{$sessionId}-bilan-carbone-{$date}.csv";
    }
}

function exportParticipantData($output, $db, $user, $sessionId, $useCases, $categories) {
    // En-tete
    fputcsv($output, ['MON BILAN CARBONE IA'], ';');
    fputcsv($output, ['Genere le', date('d/m/Y H:i')], ';');
    fputcsv($output, ['Utilisateur', $user['prenom'] ?? $user['username']], ';');
    fputcsv($output, [], ';');

    // Recuperer les calculs
    $stmt = $db->prepare("SELECT * FROM calculs WHERE session_id = ? AND user_id = ? ORDER BY created_at");
    $stmt->execute([$sessionId, $user['id']]);
    $calculs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total
    $total = array_sum(array_column($calculs, 'co2_total'));
    fputcsv($output, ['TOTAL ANNUEL', number_format($total, 0, ',', ' ') . ' g CO2'], ';');
    fputcsv($output, ['Equivalent km voiture', round($total / 1000 / 0.21, 1)], ';');
    fputcsv($output, ['Equivalent emails', round($total / 4)], ';');
    fputcsv($output, [], ';');

    // Detail des usages
    fputcsv($output, ['DETAIL DES USAGES'], ';');
    fputcsv($output, ['Categorie', 'Cas d\'usage', 'Frequence', 'Quantite', 'CO2 (g/an)'], ';');

    foreach ($calculs as $calc) {
        $uc = $useCases[$calc['use_case_id']] ?? null;
        $catName = '';
        if ($uc && isset($categories[$uc['categorie']])) {
            $catName = $categories[$uc['categorie']]['nom'];
        }

        fputcsv($output, [
            $catName,
            $uc['nom'] ?? $calc['use_case_id'],
            getFrequenceLabel($calc['frequence']),
            $calc['quantite'],
            number_format($calc['co2_total'], 0, ',', ' ')
        ], ';');
    }

    fputcsv($output, [], ';');
    fputcsv($output, ['', '', '', 'TOTAL', number_format($total, 0, ',', ' ')], ';');
}

function exportSessionData($output, $db, $sharedDb, $sessionId, $useCases, $categories) {
    // Infos session
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        fputcsv($output, ['Erreur: Session non trouvee'], ';');
        return;
    }

    // En-tete
    fputcsv($output, ['BILAN CARBONE IA - SESSION'], ';');
    fputcsv($output, ['Genere le', date('d/m/Y H:i')], ';');
    fputcsv($output, ['Session', $session['nom']], ';');
    fputcsv($output, ['Code', $session['code']], ';');
    fputcsv($output, [], ';');

    // Stats globales
    $stmt = $db->prepare("SELECT SUM(co2_total) as total, COUNT(DISTINCT user_id) as nb_participants FROM calculs WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $stats['total'] ?? 0;

    fputcsv($output, ['RESUME'], ';');
    fputcsv($output, ['Nombre de participants', $stats['nb_participants'] ?? 0], ';');
    fputcsv($output, ['Total CO2 session', number_format($total, 0, ',', ' ') . ' g'], ';');
    fputcsv($output, ['Moyenne par participant', $stats['nb_participants'] > 0 ? number_format($total / $stats['nb_participants'], 0, ',', ' ') . ' g' : '0 g'], ';');
    fputcsv($output, [], ';');

    // Par participant
    fputcsv($output, ['DETAIL PAR PARTICIPANT'], ';');
    fputcsv($output, ['Participant', 'Nb usages', 'CO2 total (g)', 'Km voiture eq.'], ';');

    $stmt = $db->prepare("
        SELECT user_id, COUNT(*) as nb, SUM(co2_total) as total
        FROM calculs WHERE session_id = ?
        GROUP BY user_id ORDER BY total DESC
    ");
    $stmt->execute([$sessionId]);
    $byUser = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($byUser as $u) {
        $stmtUser = $sharedDb->prepare("SELECT prenom, username FROM users WHERE id = ?");
        $stmtUser->execute([$u['user_id']]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $name = $userData['prenom'] ?? $userData['username'] ?? 'Inconnu';

        fputcsv($output, [
            $name,
            $u['nb'],
            number_format($u['total'], 0, ',', ' '),
            round($u['total'] / 1000 / 0.21, 1)
        ], ';');
    }

    fputcsv($output, [], ';');

    // Par cas d'usage
    fputcsv($output, ['DETAIL PAR CAS D\'USAGE'], ';');
    fputcsv($output, ['Categorie', 'Cas d\'usage', 'Nb utilisations', 'CO2 total (g)'], ';');

    $stmt = $db->prepare("
        SELECT use_case_id, COUNT(*) as nb, SUM(co2_total) as total
        FROM calculs WHERE session_id = ?
        GROUP BY use_case_id ORDER BY total DESC
    ");
    $stmt->execute([$sessionId]);
    $byUseCase = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($byUseCase as $uc) {
        $ucData = $useCases[$uc['use_case_id']] ?? null;
        $catName = '';
        if ($ucData && isset($categories[$ucData['categorie']])) {
            $catName = $categories[$ucData['categorie']]['nom'];
        }

        fputcsv($output, [
            $catName,
            $ucData['nom'] ?? $uc['use_case_id'],
            $uc['nb'],
            number_format($uc['total'], 0, ',', ' ')
        ], ';');
    }

    fputcsv($output, [], ';');

    // Detail complet
    fputcsv($output, ['DETAIL COMPLET'], ';');
    fputcsv($output, ['Participant', 'Categorie', 'Cas d\'usage', 'Frequence', 'Quantite', 'CO2 (g)'], ';');

    $stmt = $db->prepare("SELECT * FROM calculs WHERE session_id = ? ORDER BY user_id, created_at");
    $stmt->execute([$sessionId]);
    $allCalcs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $userCache = [];
    foreach ($allCalcs as $calc) {
        if (!isset($userCache[$calc['user_id']])) {
            $stmtUser = $sharedDb->prepare("SELECT prenom, username FROM users WHERE id = ?");
            $stmtUser->execute([$calc['user_id']]);
            $userCache[$calc['user_id']] = $stmtUser->fetch(PDO::FETCH_ASSOC);
        }
        $userData = $userCache[$calc['user_id']];
        $name = $userData['prenom'] ?? $userData['username'] ?? 'Inconnu';

        $ucData = $useCases[$calc['use_case_id']] ?? null;
        $catName = '';
        if ($ucData && isset($categories[$ucData['categorie']])) {
            $catName = $categories[$ucData['categorie']]['nom'];
        }

        fputcsv($output, [
            $name,
            $catName,
            $ucData['nom'] ?? $calc['use_case_id'],
            getFrequenceLabel($calc['frequence']),
            $calc['quantite'],
            number_format($calc['co2_total'], 0, ',', ' ')
        ], ';');
    }
}

function getFrequenceLabel($freq) {
    $labels = [
        'ponctuel' => 'Ponctuel',
        'quotidien' => 'Quotidien',
        'hebdomadaire' => 'Hebdomadaire',
        'mensuel' => 'Mensuel',
        'trimestriel' => 'Trimestriel',
        'annuel' => 'Annuel'
    ];
    return $labels[$freq] ?? $freq;
}
