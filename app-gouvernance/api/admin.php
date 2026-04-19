<?php
/**
 * API d'administration (formateur uniquement)
 * Actions: import, export, scale, na, domain_save, domain_delete,
 *          question_save, question_delete, reorder, meta
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isFormateur()) {
    http_response_code(403); echo json_encode(['error' => 'Accès refusé']); exit;
}

$db = getDB();
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    switch ($action) {

        case 'export':
            echo json_encode(['success' => true, 'data' => exportConfig()], JSON_UNESCAPED_UNICODE);
            break;

        case 'import':
            $replace = !empty($input['replace']);
            $data = $input['data'] ?? null;
            if (!is_array($data)) throw new Exception('Données d\'import invalides');
            $r = importConfig($data, $replace);
            echo json_encode(['success' => empty($r['errors']), 'result' => $r]);
            break;

        case 'meta':
            if (isset($input['title'])) setConfig('app_title', (string)$input['title']);
            if (isset($input['subtitle'])) setConfig('app_subtitle', (string)$input['subtitle']);
            echo json_encode(['success' => true]);
            break;

        case 'scale_save':
            $levels = $input['levels'] ?? [];
            if (!is_array($levels) || empty($levels)) throw new Exception('Échelle vide');
            $db->beginTransaction();
            try {
                $db->exec("DELETE FROM scale_levels");
                $stmt = $db->prepare("INSERT INTO scale_levels (niveau, cle, label, description) VALUES (?, ?, ?, ?)");
                $seen = [];
                foreach ($levels as $l) {
                    $n = (int)($l['niveau'] ?? 0);
                    if ($n < 1 || $n > 10 || in_array($n, $seen, true)) continue;
                    $seen[] = $n;
                    $stmt->execute([$n, $l['cle'] ?? ('niveau_' . $n), $l['label'] ?? '', $l['description'] ?? '']);
                }
                // supprimer ancrages correspondant à des niveaux disparus
                $keep = implode(',', array_map('intval', $seen));
                if ($keep) $db->exec("DELETE FROM anchors WHERE niveau NOT IN ($keep)");
                $db->commit();
            } catch (Exception $e) { $db->rollBack(); throw $e; }
            echo json_encode(['success' => true]);
            break;

        case 'na_save':
            setConfig('na_enabled', !empty($input['enabled']) ? '1' : '0');
            setConfig('na_label', (string)($input['label'] ?? 'Non applicable / Ne sais pas'));
            setConfig('na_description', (string)($input['description'] ?? ''));
            echo json_encode(['success' => true]);
            break;

        case 'domain_save':
            $id = (int)($input['id'] ?? 0);
            $titre = trim($input['titre'] ?? '');
            if ($titre === '') throw new Exception('Titre requis');
            $description = (string)($input['description'] ?? '');
            $ordre = (int)($input['ordre'] ?? 0);
            $slug = trim($input['slug'] ?? '');
            if ($id > 0) {
                $db->prepare("UPDATE domains SET titre=?, description=?, ordre=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
                   ->execute([$titre, $description, $ordre, $id]);
                if ($slug) {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM domains WHERE slug=? AND id<>?");
                    $stmt->execute([$slug, $id]);
                    if ((int)$stmt->fetchColumn() === 0) {
                        $db->prepare("UPDATE domains SET slug=? WHERE id=?")->execute([$slug, $id]);
                    }
                }
                echo json_encode(['success' => true, 'id' => $id]);
            } else {
                $base = $slug ?: slugify($titre);
                $s = uniqueSlug('domains', $base);
                if ($ordre === 0) $ordre = (int)$db->query("SELECT COALESCE(MAX(ordre),0)+1 FROM domains")->fetchColumn();
                $db->prepare("INSERT INTO domains (slug, titre, description, ordre) VALUES (?, ?, ?, ?)")
                   ->execute([$s, $titre, $description, $ordre]);
                echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId(), 'slug' => $s]);
            }
            break;

        case 'domain_delete':
            $id = (int)($input['id'] ?? 0);
            if (!$id) throw new Exception('ID manquant');
            $db->prepare("DELETE FROM domains WHERE id=?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'question_save':
            $id = (int)($input['id'] ?? 0);
            $domainId = (int)($input['domain_id'] ?? 0);
            $intitule = trim($input['intitule'] ?? '');
            $texte = trim($input['texte'] ?? '');
            if ($intitule === '' || $texte === '') throw new Exception('Intitulé et texte requis');
            $aide = trim($input['aide'] ?? '');
            $aide = $aide === '' ? null : $aide;
            $ordre = (int)($input['ordre'] ?? 0);
            $slug = trim($input['slug'] ?? '');
            $ancrages = $input['ancrages'] ?? [];

            $db->beginTransaction();
            try {
                if ($id > 0) {
                    $db->prepare("UPDATE questions SET intitule=?, texte=?, aide=?, ordre=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
                       ->execute([$intitule, $texte, $aide, $ordre, $id]);
                } else {
                    if (!$domainId) throw new Exception('Domaine requis');
                    $base = $slug ?: slugify($intitule);
                    $s = uniqueSlug('questions', $base);
                    if ($ordre === 0) {
                        $stmt = $db->prepare("SELECT COALESCE(MAX(ordre),0)+1 FROM questions WHERE domain_id=?");
                        $stmt->execute([$domainId]);
                        $ordre = (int)$stmt->fetchColumn();
                    }
                    $db->prepare("INSERT INTO questions (domain_id, slug, intitule, texte, aide, ordre) VALUES (?, ?, ?, ?, ?, ?)")
                       ->execute([$domainId, $s, $intitule, $texte, $aide, $ordre]);
                    $id = (int)$db->lastInsertId();
                }
                if (is_array($ancrages)) {
                    $sa = $db->prepare("INSERT OR REPLACE INTO anchors (question_id, niveau, description) VALUES (?, ?, ?)");
                    foreach ($ancrages as $niveau => $desc) {
                        $n = (int)$niveau;
                        if ($n > 0) $sa->execute([$id, $n, (string)$desc]);
                    }
                }
                $db->commit();
            } catch (Exception $e) { $db->rollBack(); throw $e; }
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'question_delete':
            $id = (int)($input['id'] ?? 0);
            if (!$id) throw new Exception('ID manquant');
            $db->prepare("DELETE FROM questions WHERE id=?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'reorder_domains':
            $order = $input['order'] ?? [];
            if (!is_array($order)) throw new Exception('Ordre invalide');
            $stmt = $db->prepare("UPDATE domains SET ordre=? WHERE id=?");
            foreach ($order as $i => $id) $stmt->execute([$i + 1, (int)$id]);
            echo json_encode(['success' => true]);
            break;

        case 'reorder_questions':
            $order = $input['order'] ?? [];
            if (!is_array($order)) throw new Exception('Ordre invalide');
            $stmt = $db->prepare("UPDATE questions SET ordre=? WHERE id=?");
            foreach ($order as $i => $id) $stmt->execute([$i + 1, (int)$id]);
            echo json_encode(['success' => true]);
            break;

        case 'get':
            echo json_encode([
                'success' => true,
                'meta' => [
                    'title' => getConfig('app_title', APP_NAME),
                    'subtitle' => getConfig('app_subtitle', ''),
                ],
                'scale' => getScaleLevels(),
                'na' => getNaSettings(),
                'domains' => getAllDomains(),
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue: ' . $action]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
