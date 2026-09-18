<?php
ob_start();
session_start();
include_once('../../../bdBASI.php');
ob_end_clean();

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
date_default_timezone_set('Africa/Dakar');

// ─── Session ──────────────────────────────────────────────────────────────────
if (empty($_SESSION['tmpIdBASI'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['status'=>'error','code'=>'sessionExpired','message'=>'Session expirée.']);
    exit;
}

// ─── DB ───────────────────────────────────────────────────────────────────────
$BDBASI = new BDBASI();
$bdBASI = $BDBASI->connect();
if (!$bdBASI) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Connexion base de données impossible.']);
    exit;
}

function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

$option = trim($_GET['option'] ?? '');
if ($option === '') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Paramètre option manquant.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

switch ($option) {

// ═══ CASE 1 : Détails du budget par ID (investissement OU fonctionnement) ════
// POST { budgetId: N }
    case 1:
        try {
            $data = getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("budgetId requis.");
            $id = (int)$data['budgetId'];

            $stmt = $bdBASI->prepare("
                SELECT b.*, tb.nom AS type_budget_nom, tb.id AS type_budget_id
                FROM budget b
                JOIN typeBudget tb ON b.type_budget_id = tb.id
                WHERE b.id = ? AND b.statut NOT IN ('Supprimer','supprimer')
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $budget = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$budget) {
                http_response_code(404);
                echo json_encode(['status'=>'error','message'=>'Budget non trouvé.']);
                exit;
            }
            echo json_encode(['status'=>'success','data'=>$budget]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
        exit;

// ═══ CASE 2 : Lignes INVESTISSEMENT (rubrique/sous-rubrique/nature/service) ══
// POST { budgetId: N }
    case 2:
        try {
            $data = getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("budgetId requis.");
            $id = (int)$data['budgetId'];

            // Vérifier que le budget existe
            $chk = $bdBASI->prepare("SELECT id FROM budget WHERE id=? AND statut NOT IN ('Supprimer','supprimer')");
            $chk->execute([$id]);
            if (!$chk->fetch()) throw new Exception("Budget non trouvé.", 404);

            $stmt = $bdBASI->prepare("
                SELECT lb.*,
                       r.nom_rubrique,
                       sr.nom_sous_rubrique,
                       tbi.nom        AS nature_nom,
                       tbi.categorie  AS nature_categorie,
                       s.nom_services AS service_nom,
                       d.nom_direction,
                       p.nomproduit,
                       p.code_produit,
                       u.unite        AS unite_nom
                FROM ligneBudget lb
                LEFT JOIN rubrique                   r   ON lb.rubrique_id                  = r.id
                LEFT JOIN sousRubrique               sr  ON lb.sous_rubrique_id             = sr.id
                LEFT JOIN type_budget_investissement tbi ON lb.id_type_budget_investissement = tbi.id
                LEFT JOIN services                   s   ON lb.service_id                   = s.id
                LEFT JOIN direction                  d   ON s.id_direction                  = d.id
                LEFT JOIN product                    p   ON lb.id_produit                   = p.idP
                LEFT JOIN listeUnites                u   ON lb.unite_id                     = u.id
                WHERE lb.budget_id = ? AND lb.statut NOT IN ('Inactif','inactif')
                ORDER BY lb.id ASC
            ");
            $stmt->execute([$id]);
            $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Stats par nature pour graphe côté JS
            $byNature    = [];
            $byDirection = [];
            $totalMontant = 0;
            foreach ($lines as $l) {
                $nature = $l['nature_nom'] ?? 'N/A';
                $dir    = $l['nom_direction'] ?? 'N/A';
                $mt     = (float)($l['montant_total'] ?? 0);
                $totalMontant += $mt;
                $byNature[$nature]    = ($byNature[$nature]    ?? 0) + $mt;
                $byDirection[$dir]    = ($byDirection[$dir]    ?? 0) + $mt;
            }

            echo json_encode([
                'status'       => 'success',
                'data'         => $lines,
                'lineCount'    => count($lines),
                'totalMontant' => $totalMontant,
                'byNature'     => $byNature,
                'byDirection'  => $byDirection,
                'lastUpdate'   => date('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
        exit;

// ═══ CASE 3 : Lignes FONCTIONNEMENT (catégorie/sous-catégorie/produit) ════════
// POST { budgetId: N }
    case 3:
        try {
            $data = getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("budgetId requis.");
            $id = (int)$data['budgetId'];

            $chk = $bdBASI->prepare("SELECT id FROM budget WHERE id=? AND statut NOT IN ('Supprimer','supprimer')");
            $chk->execute([$id]);
            if (!$chk->fetch()) throw new Exception("Budget non trouvé.", 404);

            $stmt = $bdBASI->prepare("
                SELECT lb.*,
                       c.nom_categorie,
                       sc.nom_sous_categorie,
                       p.nomproduit,
                       p.code_produit,
                       u.unite AS unite_nom
                FROM ligneBudget lb
                LEFT JOIN product        p  ON lb.id_produit        = p.idP
                LEFT JOIN souscategorie  sc ON p.id_Sous_categorie  = sc.id
                LEFT JOIN categorie      c  ON sc.categorie_id      = c.id
                LEFT JOIN listeUnites    u  ON lb.unite_id          = u.id
                WHERE lb.budget_id = ? AND lb.statut NOT IN ('Inactif','inactif')
                ORDER BY lb.id ASC
            ");
            $stmt->execute([$id]);
            $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $byCategorie  = [];
            $totalMontant = 0;
            foreach ($lines as $l) {
                $cat  = $l['nom_categorie'] ?? 'N/A';
                $mt   = (float)($l['montant_total'] ?? 0);
                $totalMontant += $mt;
                $byCategorie[$cat] = ($byCategorie[$cat] ?? 0) + $mt;
            }

            echo json_encode([
                'status'       => 'success',
                'data'         => $lines,
                'lineCount'    => count($lines),
                'totalMontant' => $totalMontant,
                'byCategorie'  => $byCategorie,
                'lastUpdate'   => date('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
        exit;

// ═══ CASE 4 : Historique du budget ════════════════════════════════════════════
// POST { budgetId: N }
    case 4:
        try {
            $data = getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("budgetId requis.");
            $id = (int)$data['budgetId'];

            // Jointure souple : la table utilisateur peut s'appeler "utilisateur" ou "Utilisateur"
            $stmt = $bdBASI->prepare("
                SELECT h.*, u.tmpPrenom AS prenom, u.tmpNom AS nom
                FROM historique_Budget h
                LEFT JOIN utilisateur u ON h.idUtilisateur = u.id
                WHERE h.idBudget = ?
                ORDER BY h.dateEnregistrement DESC, h.id DESC
                LIMIT 50
            ");
            $stmt->execute([$id]);
            echo json_encode(['status'=>'success','data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code(400);  echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>"Option '{$option}' non reconnue."]);
        exit;
}