<?php
// ─── Capture de tout output parasite ─────────────────────────────────────────
ob_start();
session_start();
include_once('../../../bdBASI.php');
ob_end_clean();

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

date_default_timezone_set('Africa/Dakar');

// ─── Vérification sessions obligatoires ──────────────────────────────────────
function checkSession(): void {
    foreach (['tmpIdBASI', 'tmpMatricule'] as $key) {
        if (empty($_SESSION[$key])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['status'=>'error','code'=>'sessionExpired','message'=>'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }
    }
}
checkSession();

$sessionUserId    = (int)$_SESSION['tmpIdBASI'];
$sessionMatricule = trim($_SESSION['tmpMatricule']);

// ─── Classe contrôleur ────────────────────────────────────────────────────────
class comptableController extends BDBASI
{
    function tokenencrypt($data) {
        $key = hash('sha256','U@hbENTDRI@TCRI@T2022');
        $iv  = substr(hash('sha256','www.ent.uahb.sn'),0,16);
        return base64_encode(openssl_encrypt($data,"AES-256-CBC",$key,0,$iv));
    }
    function tokendecrypt($data) {
        $key = hash('sha256','U@hbENTDRI@TCRI@T2022');
        $iv  = substr(hash('sha256','www.ent.uahb.sn'),0,16);
        return openssl_decrypt(base64_decode($data),"AES-256-CBC",$key,0,$iv);
    }
    function fctRetirerAccents($s) {
        $search  = ['À','Á','Â','Ã','Ä','Å','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ò','Ó','Ô','Õ','Ö','Ù','Ú','Û','Ü','Ý','à','á','â','ã','ä','å','ç','è','é','ê','ë','ì','í','î','ï','ð','ò','ó','ô','õ','ö','ù','ú','û','ü','ý','ÿ'];
        $replace = ['A','A','A','A','A','A','C','E','E','E','E','I','I','I','I','O','O','O','O','O','U','U','U','U','Y','a','a','a','a','a','a','c','e','e','e','e','i','i','i','i','o','o','o','o','o','o','u','u','u','u','y','y'];
        return str_replace($search,$replace,$s);
    }
    public function numberFormat($n,$t1,$t2,$t3) {
        return ($n!=null&&$n!='') ? number_format($n,0,',',' ') : $n;
    }
}

// ─── Connexion DB ─────────────────────────────────────────────────────────────
$BDBASI         = new BDBASI();
$bdBASI         = $BDBASI->connect();
$basiController = new comptableController();

if (!$bdBASI) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Connexion base de données impossible.']);
    exit;
}

function valid_donnees($d) { return htmlspecialchars(stripslashes(trim($d))); }

// ─── Lecture de l'option ──────────────────────────────────────────────────────
$option = isset($_GET['option']) ? trim($_GET['option']) : '';
if ($option==='' && isset($_POST['option'])) $option = trim($_POST['option']);
if ($option==='') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Paramètre option manquant.']);
    exit;
}

// ─── Helpers globaux ──────────────────────────────────────────────────────────

// Type produit "Stock" fixé côté serveur (jamais lu depuis le client)
define('ID_TYPE_PRODUCT_STOCK', 1);

// Type budget "Fonctionnement" fixé côté serveur (jamais lu depuis le client)
// Investissement = 2 (cf. chef_service_basi_controller) → Fonctionnement = 1
define('TYPE_BUDGET_FONCTIONNEMENT', 1);

function getJsonBody(): array {
    $raw = file_get_contents("php://input");
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function getIdStatut(string $statut): int {
    return ['En cours'=>1,'Dépassé'=>2,'Sauvegarder'=>3,'Terminer'=>4,'Rejeter'=>5,'Valider'=>6,'Accepter'=>7,'Réajuster'=>9][$statut] ?? 1;
}

function sqlIdStatut(string $alias='b'): string {
    return "CASE {$alias}.statut
        WHEN 'En cours'    THEN 1 WHEN 'Dépassé'     THEN 2
        WHEN 'Sauvegarder' THEN 3 WHEN 'Terminer'    THEN 4
        WHEN 'Rejeter'     THEN 5 WHEN 'Valider'     THEN 6
        WHEN 'Accepter'    THEN 7 WHEN 'Réajuster'  THEN 9 ELSE 1
    END AS idStatut";
}

// INSERT historique_budget — colonnes réelles (mêmes que chef_service_basi_controller)
function insertHistoriqueBudget(PDO $pdo, int $budgetId, array $row, int $userId, string $motif): void {
    $pdo->prepare("
        INSERT INTO historique_budget
            (idBudget, annee, matricule, direction_id, type_budget_id,
             date_creation, statut, idStatut, plafond, idUtilisateur, dateEnregistrement, motif)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([
        $budgetId, $row['annee'], $row['matricule'], $row['direction_id'],
        $row['type_budget_id'], $row['date_creation'], $row['statut'],
        $row['idStatut'], $row['plafond'], $userId, date('Y-m-d H:i:s'), $motif
    ]);
}

// INSERT historique_ligneBudget — colonnes réelles (mêmes que chef_service_basi_controller)
function insertHistoriqueLigneBudgetFonct(PDO $pdo, array $ligne, string $statut, int $idStatut, string $motif): void {
    $pdo->prepare("
        INSERT INTO historique_ligneBudget
            (ligne_budget_id, budget_id, rubrique_id, sous_rubrique_id,
             id_type_budget_investissement, designation, description,
             quantite, unite_id, prix_unitaire, montant_total, service_id, id_produit,
             date_creation, statut, idStatut, periode_d_utilisation,
             motif, dateEnregistrement)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
    ")->execute([
        $ligne['id']                            ?? null,
        $ligne['budget_id']                     ?? null,
        $ligne['rubrique_id']                   ?? null,
        $ligne['sous_rubrique_id']               ?? null,
        $ligne['id_type_budget_investissement']  ?? null,
        $ligne['designation']                   ?? null,
        $ligne['description']                   ?? null,
        $ligne['quantite']                      ?? null,
        $ligne['unite_id']                      ?? null,
        $ligne['prix_unitaire']                 ?? null,
        $ligne['montant_total']                 ?? null,
        $ligne['service_id']                    ?? null,
        $ligne['id_produit']                    ?? null,
        $ligne['date_creation']                 ?? date('Y-m-d H:i:s'),
        $statut,
        $idStatut,
        $ligne['periode_d_utilisation']         ?? null,
        $motif,
    ]);
}

// INSERT historique_categorie (structure réelle : id_categorie, nom_categorie, action enum, dateEnregistrement, idUtilisateur)
function insertHistoriqueCategorie(PDO $pdo, int $idCategorie, string $nom, string $action, int $userId): void {
    $pdo->prepare("
        INSERT INTO historique_categorie (id_categorie, nom_categorie, action, dateEnregistrement, idUtilisateur)
        VALUES (?,?,?,NOW(),?)
    ")->execute([$idCategorie, $nom, $action, $userId]);
}

// INSERT historique_sous_categorie (structure réelle : id_sous_categorie, categorie_id, nom_sous_categorie, action enum, dateEnregistrement, idUtilisateur)
function insertHistoriqueSousCategorie(PDO $pdo, int $idSousCategorie, int $categorieId, string $nom, string $action, int $userId): void {
    $pdo->prepare("
        INSERT INTO historique_sous_categorie (id_sous_categorie, categorie_id, nom_sous_categorie, action, dateEnregistrement, idUtilisateur)
        VALUES (?,?,?,?,NOW(),?)
    ")->execute([$idSousCategorie, $categorieId, $nom, $action, $userId]);
}

// INSERT historique_product — colonnes réelles :
// product_id, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total,
// id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, motif, dateEnregistrement
function insertHistoriqueProduit(
    PDO $pdo,
    int $productId,
    string $nomproduit,
    ?string $codeProduit,
    int $stockActuel,
    int $seuilLimite,
    int $total,
    int $idSousCategorie,
    int $retrait,
    int $idStatut,
    string $dateCreation,
    int $idTypeProduct,
    string $motif
): void {
    $pdo->prepare("
        INSERT INTO historique_product
            (product_id, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total,
             id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, motif, dateEnregistrement)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())
    ")->execute([
        $productId, $nomproduit, $codeProduit, $stockActuel, $seuilLimite, $total,
        $idSousCategorie, $retrait, $idStatut, $dateCreation, $idTypeProduct, $motif,
    ]);
}

//function voirSuiviDemande(PDO $bdBASI, $basiController): void
//{
//    try {
//        // ── Adapter à votre helper de lecture d'input existant (inputValue,
//        // inputValueCompta, etc.) — exemple générique ci-dessous.
//
//        echo $_POST['demandeId'];
//        die;
//        $demandeId = (int)($_POST['demandeId'] ?? $_GET['demandeId'] ?? 0);
//
//        echo $demandeId;
//        die;
//
//        if ($demandeId <= 0) {
//            echo json_encode(['success' => false, 'message' => 'Identifiant de demande manquant.']);
//            return;
//        }
//
//        // ── En-tête de la demande ────────────────────────────────────────────
//        $stmtD = $bdBASI->prepare("
//            SELECT d.idD, d.type_demande, d.statut, d.date_creation, d.idUtilisateur,
//                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
//            FROM demandes d
//            LEFT JOIN utilisateurs u ON d.idUtilisateur = u.id
//            WHERE d.idD = ?
//            LIMIT 1
//        ");
//        $stmtD->execute([$demandeId]);
//        $demande = $stmtD->fetch(PDO::FETCH_ASSOC);
//        if (!$demande) {
//            echo json_encode(['success' => false, 'message' => 'Demande introuvable.']);
//            return;
//        }
//
//        // ── Lignes de la demande (même logique que l'option "lignes" déjà en place) ─
//        $stmtLignes = $bdBASI->prepare("
//            SELECT dl.idDL, lb.designation, c.nom_categorie, dl.quantite, dl.unite
//            FROM demandes_ligne dl
//            JOIN ligneBudget lb ON dl.idLB = lb.id
//            LEFT JOIN categorie c ON lb.idCategorie = c.id
//            WHERE dl.idD = ?
//            ORDER BY dl.idDL ASC
//        ");
//        // ⚠️ Si votre option "lignes" (case 38) utilise une jointure différente
//        // pour nom_categorie/quantite/unite, réutilisez EXACTEMENT cette même
//        // requête ici plutôt que celle-ci (reconstituée par déduction du JS).
//        $stmtLignes->execute([$demandeId]);
//        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
//
//        // ── Commande(s) issues de cette demande, via le pont idDL ───────────
//        $stmtIdsPAP = $bdBASI->prepare("
//            SELECT DISTINCT papl.idPAP
//            FROM demandes_ligne dl
//            JOIN passer_achat_et_paiement_ligne papl ON papl.idDL = dl.idDL
//            WHERE dl.idD = ?
//        ");
//        $stmtIdsPAP->execute([$demandeId]);
//        $idsPAP = array_map('intval', array_column($stmtIdsPAP->fetchAll(PDO::FETCH_ASSOC), 'idPAP'));
//
//        $commandes = [];
//        foreach ($idsPAP as $idPAP) {
//            $stmtCmd = $bdBASI->prepare("
//                SELECT id AS idPAP, nom_commande, montant_total, montant_paye, idStatut, idTypePAP
//                FROM passer_achat_et_paiement
//                WHERE id = ?
//                LIMIT 1
//            ");
//            $stmtCmd->execute([$idPAP]);
//            $cmd = $stmtCmd->fetch(PDO::FETCH_ASSOC);
//            if (!$cmd) continue;
//
//            // Paiements en caisse (déjà établi ailleurs dans le projet).
//            $stmtPaiements = $bdBASI->prepare("
//                SELECT pp.id, pp.montant, pp.date_paiement, pp.banque, pp.numero_cheque_virement, pp.recu,
//                       mr.mode_reglement AS mode_reglement_nom,
//                       CONCAT(uc.prenom, ' ', uc.nom) AS caissier
//                FROM paiement_pap pp
//                LEFT JOIN mode_reglement mr ON pp.mode_reglement = mr.id
//                LEFT JOIN utilisateurs uc ON pp.idCaissier = uc.id
//                WHERE pp.idPAP = ?
//                ORDER BY pp.date_paiement ASC
//            ");
//            $stmtPaiements->execute([$idPAP]);
//            $cmd['paiements'] = $stmtPaiements->fetchAll(PDO::FETCH_ASSOC);
//
//            // Livraisons + détail produit/quantité/bénéficiaire (déjà établi
//            // ailleurs dans le projet — livraison / livraison_produit).
//            $stmtLivraisons = $bdBASI->prepare("
//                SELECT id, numero_livraison, fichier_bon_livraison, date_livraison, idStatut
//                FROM livraison
//                WHERE idPAP = ?
//                ORDER BY date_livraison ASC
//            ");
//            $stmtLivraisons->execute([$idPAP]);
//            $livraisons = $stmtLivraisons->fetchAll(PDO::FETCH_ASSOC);
//
//            $stmtProduitsLivres = $bdBASI->prepare("
//                SELECT lp.quantite, p.nomproduit AS designation
//                FROM livraison_produit lp
//                JOIN product p ON lp.idP = p.idP
//                WHERE lp.idL = ?
//            ");
//            foreach ($livraisons as &$liv) {
//                $stmtProduitsLivres->execute([(int)$liv['id']]);
//                $produits = $stmtProduitsLivres->fetchAll(PDO::FETCH_ASSOC);
//                // ⚠️ "Bénéficiaire" : pas de colonne dédiée connue — repli sur
//                // le demandeur de la `demandes` d'origine (à ajuster si une
//                // colonne dédiée existe réellement).
//                foreach ($produits as &$p) {
//                    $p['beneficiaire'] = $demande['demandeur'] ?? null;
//                }
//                unset($p);
//                $liv['produits'] = $produits;
//            }
//            unset($liv);
//            $cmd['livraisons'] = $livraisons;
//
//            // Historique de la commande (statuts successifs).
//            $stmtHisto = $bdBASI->prepare("
//                SELECT h.idStatut, h.motif, h.dateEnregistrement,
//                       CONCAT(u2.prenom, ' ', u2.nom) AS utilisateur
//                FROM historique_passer_achat_et_paiement h
//                LEFT JOIN utilisateurs u2 ON h.idUtilisateur = u2.id
//                WHERE h.idPAP = ?
//                ORDER BY h.dateEnregistrement ASC
//            ");
//            $stmtHisto->execute([$idPAP]);
//            $cmd['historique'] = $stmtHisto->fetchAll(PDO::FETCH_ASSOC);
//
//            $commandes[] = $cmd;
//        }
//
//        echo json_encode([
//            'success' => true,
//            'data' => [
//                'demande' => $demande,
//                'lignes' => $lignes,
//                'commandes' => $commandes,
//            ],
//        ]);
//    } catch (\Throwable $e) {
//        error_log('[Compta][voirSuiviDemande] ' . $e->getMessage());
//        http_response_code(500);
//        echo json_encode(['success' => false, 'message' => 'Impossible de charger le suivi de la demande.']);
//    }
//}


switch ($option) {

// ─── CASE 1 : POST — liste des catégories actives (statut=1) ────────────────
// Reprend la logique de l'ancien case 54, mais retourne id + nom_categorie
    case 1:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $stmt = $bdBASI->prepare("SELECT id, nom_categorie FROM categorie WHERE statut=1 ORDER BY nom_categorie ASC");
            $stmt->execute();
            echo json_encode(['status'=>'success','data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { error_log("Case 1 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 1: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 2 : POST — liste des sous-catégories actives, filtrées par catégorie ──
// Reprend la logique de l'ancien case 57
// Body JSON : { "categorie_id"?: N }  (optionnel — si absent, retourne tout)
    case 2:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data        = getJsonBody();
            $categorieId = isset($data['categorie_id']) && $data['categorie_id']!=='' ? (int)$data['categorie_id'] : null;

            $sql = "SELECT sc.id, sc.nom_sous_categorie AS nom, sc.categorie_id, c.nom_categorie
                    FROM souscategorie sc
                    LEFT JOIN categorie c ON c.id = sc.categorie_id
                    WHERE sc.statut=1";
            $params = [];
            if ($categorieId) { $sql .= " AND sc.categorie_id=?"; $params[] = $categorieId; }
            $sql .= " ORDER BY sc.nom_sous_categorie ASC";

            $stmt = $bdBASI->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['status'=>'success','data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { error_log("Case 2 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 2: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 3 : POST — créer une catégorie ─────────────────────────────────────
// Body JSON : { "nom": "Fournitures" }
    case 3:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data = getJsonBody();
            $nom  = trim($data['nom'] ?? '');
            if (!$nom) throw new Exception("Le nom de la catégorie est requis.");
            if (!preg_match('/^[a-zA-Z0-9\s\'\-éèêëàâùûüîïôçœæÉÈÊËÀÂÙÛÜÎÏÔÇŒÆ]+$/', $nom)) throw new Exception("Caractères spéciaux non autorisés.");

            $chk = $bdBASI->prepare("SELECT COUNT(*) FROM categorie WHERE nom_categorie=? AND statut=1");
            $chk->execute([$nom]);
            if ($chk->fetchColumn() > 0) throw new Exception("Cette catégorie existe déjà.");

            $bdBASI->beginTransaction();
            $stmt = $bdBASI->prepare("INSERT INTO categorie (nom_categorie, statut, date_creation, idUtilisateur) VALUES (?,1,NOW(),?)");
            $stmt->execute([$nom, $sessionUserId]);
            $newId = (int)$bdBASI->lastInsertId();

            insertHistoriqueCategorie($bdBASI, $newId, $nom, 'insertion', $sessionUserId);
            $bdBASI->commit();

            echo json_encode(['status'=>'success','new_id'=>$newId,'message'=>'Catégorie créée avec succès.']);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 3 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 3: ".$e->getMessage()); http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
        }
        exit;

// ─── CASE 4 : POST — modifier une catégorie ──────────────────────────────────
// Body JSON : { "id": 3, "nom": "Fournitures bureau" }
    case 4:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data = getJsonBody();
            $id   = isset($data['id']) ? (int)$data['id'] : null;
            $nom  = trim($data['nom'] ?? '');
            if (!$id)  throw new Exception("Champ requis manquant : id.");
            if (!$nom) throw new Exception("Le nom de la catégorie est requis.");
            if (!preg_match('/^[a-zA-Z0-9\s\'\-éèêëàâùûüîïôçœæÉÈÊËÀÂÙÛÜÎÏÔÇŒÆ]+$/', $nom)) throw new Exception("Caractères spéciaux non autorisés.");

            $chkExist = $bdBASI->prepare("SELECT COUNT(*) FROM categorie WHERE id=? AND statut=1");
            $chkExist->execute([$id]);
            if ($chkExist->fetchColumn() == 0) throw new Exception("Catégorie non trouvée.",404);

            $chkDup = $bdBASI->prepare("SELECT COUNT(*) FROM categorie WHERE nom_categorie=? AND id!=? AND statut=1");
            $chkDup->execute([$nom, $id]);
            if ($chkDup->fetchColumn() > 0) throw new Exception("Cette catégorie existe déjà.");

            $bdBASI->beginTransaction();
            $bdBASI->prepare("UPDATE categorie SET nom_categorie=?, date_derniere_modification=NOW() WHERE id=?")->execute([$nom, $id]);
            insertHistoriqueCategorie($bdBASI, $id, $nom, 'modification', $sessionUserId);
            $bdBASI->commit();

            echo json_encode(['status'=>'success','message'=>'Catégorie modifiée avec succès.']);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 4 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 4: ".$e->getMessage()); http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
        }
        exit;

// ─── CASE 5 : POST — supprimer une catégorie (soft delete, statut=0) ────────
// Body JSON : { "id": 3 }
// Refuse si des sous-catégories actives sont rattachées
    case 5:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data = getJsonBody();
            $id   = isset($data['id']) ? (int)$data['id'] : null;
            if (!$id) throw new Exception("Champ requis manquant : id.");

            $chk = $bdBASI->prepare("SELECT nom_categorie FROM categorie WHERE id=? AND statut=1");
            $chk->execute([$id]);
            $cat = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$cat) throw new Exception("Catégorie non trouvée.",404);

            $chkSub = $bdBASI->prepare("SELECT COUNT(*) FROM souscategorie WHERE categorie_id=? AND statut=1");
            $chkSub->execute([$id]);
            if ($chkSub->fetchColumn() > 0) throw new Exception("Impossible de supprimer : des sous-catégories actives sont rattachées à cette catégorie.");

            $bdBASI->beginTransaction();
            $bdBASI->prepare("UPDATE categorie SET statut=0, date_derniere_modification=NOW() WHERE id=?")->execute([$id]);
            insertHistoriqueCategorie($bdBASI, $id, $cat['nom_categorie'], 'suppression', $sessionUserId);
            $bdBASI->commit();

            echo json_encode(['status'=>'success','message'=>'Catégorie supprimée avec succès.']);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 5 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 5: ".$e->getMessage()); http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
        }
        exit;

// ─── CASE 6 : POST — créer une sous-catégorie ────────────────────────────────
// Body JSON : { "nom": "Stylos", "categorie_id": 2 }
    case 6:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data        = getJsonBody();
            $nom         = trim($data['nom'] ?? '');
            $categorieId = isset($data['categorie_id']) ? (int)$data['categorie_id'] : null;
            if (!$nom)         throw new Exception("Le nom de la sous-catégorie est requis.");
            if (!$categorieId) throw new Exception("categorie_id requis.");
            if (!preg_match('/^[a-zA-Z0-9\s\'\-éèêëàâùûüîïôçœæÉÈÊËÀÂÙÛÜÎÏÔÇŒÆ]+$/', $nom)) throw new Exception("Caractères spéciaux non autorisés.");

            $chkCat = $bdBASI->prepare("SELECT COUNT(*) FROM categorie WHERE id=? AND statut=1");
            $chkCat->execute([$categorieId]);
            if ($chkCat->fetchColumn() == 0) throw new Exception("Catégorie parente introuvable ou inactive.");

            $chkDup = $bdBASI->prepare("SELECT COUNT(*) FROM souscategorie WHERE nom_sous_categorie=? AND categorie_id=? AND statut=1");
            $chkDup->execute([$nom, $categorieId]);
            if ($chkDup->fetchColumn() > 0) throw new Exception("Cette sous-catégorie existe déjà dans cette catégorie.");

            $bdBASI->beginTransaction();
            $stmt = $bdBASI->prepare("INSERT INTO souscategorie (categorie_id, nom_sous_categorie, statut, date_creation, date_derniere_modification, idUtilisateur) VALUES (?,?,1,NOW(),NOW(),?)");
            $stmt->execute([$categorieId, $nom, $sessionUserId]);
            $newId = (int)$bdBASI->lastInsertId();

            insertHistoriqueSousCategorie($bdBASI, $newId, $categorieId, $nom, 'insertion', $sessionUserId);
            $bdBASI->commit();

            echo json_encode(['status'=>'success','new_id'=>$newId,'message'=>'Sous-catégorie créée avec succès.']);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 6 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 6: ".$e->getMessage()); http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
        }
        exit;

// ─── CASE 7 : POST — modifier une sous-catégorie ─────────────────────────────
// Body JSON : { "id": 20, "nom": "Stylos bille", "categorie_id": 2 }
    case 7:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data        = getJsonBody();
            $id          = isset($data['id']) ? (int)$data['id'] : null;
            $nom         = trim($data['nom'] ?? '');
            $categorieId = isset($data['categorie_id']) ? (int)$data['categorie_id'] : null;
            if (!$id)          throw new Exception("Champ requis manquant : id.");
            if (!$nom)         throw new Exception("Le nom de la sous-catégorie est requis.");
            if (!$categorieId) throw new Exception("categorie_id requis.");
            if (!preg_match('/^[a-zA-Z0-9\s\'\-éèêëàâùûüîïôçœæÉÈÊËÀÂÙÛÜÎÏÔÇŒÆ]+$/', $nom)) throw new Exception("Caractères spéciaux non autorisés.");

            $chk = $bdBASI->prepare("SELECT COUNT(*) FROM souscategorie WHERE id=? AND statut=1");
            $chk->execute([$id]);
            if ($chk->fetchColumn() == 0) throw new Exception("Sous-catégorie non trouvée.",404);

            $chkCat = $bdBASI->prepare("SELECT COUNT(*) FROM categorie WHERE id=? AND statut=1");
            $chkCat->execute([$categorieId]);
            if ($chkCat->fetchColumn() == 0) throw new Exception("Catégorie parente introuvable ou inactive.");

            $chkDup = $bdBASI->prepare("SELECT COUNT(*) FROM souscategorie WHERE nom_sous_categorie=? AND categorie_id=? AND id!=? AND statut=1");
            $chkDup->execute([$nom, $categorieId, $id]);
            if ($chkDup->fetchColumn() > 0) throw new Exception("Cette sous-catégorie existe déjà dans cette catégorie.");

            $bdBASI->beginTransaction();
            $bdBASI->prepare("UPDATE souscategorie SET nom_sous_categorie=?, categorie_id=?, date_derniere_modification=NOW() WHERE id=?")->execute([$nom, $categorieId, $id]);
            insertHistoriqueSousCategorie($bdBASI, $id, $categorieId, $nom, 'modification', $sessionUserId);
            $bdBASI->commit();

            echo json_encode(['status'=>'success','message'=>'Sous-catégorie modifiée avec succès.']);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 7 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 7: ".$e->getMessage()); http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
        }
        exit;

// ─── CASE 8 : POST — supprimer une sous-catégorie (soft delete, statut=0) ───
// Body JSON : { "id": 20 }
// Refuse si des produits actifs sont rattachés
    case 8:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data = getJsonBody();
            $id   = isset($data['id']) ? (int)$data['id'] : null;
            if (!$id) throw new Exception("Champ requis manquant : id.");

            $chk = $bdBASI->prepare("SELECT nom_sous_categorie, categorie_id FROM souscategorie WHERE id=? AND statut=1");
            $chk->execute([$id]);
            $sub = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$sub) throw new Exception("Sous-catégorie non trouvée.",404);

            $chkProd = $bdBASI->prepare("SELECT COUNT(*) FROM product WHERE id_Sous_categorie=? AND id_statut=1");
            $chkProd->execute([$id]);
            if ($chkProd->fetchColumn() > 0) throw new Exception("Impossible de supprimer : des produits actifs sont rattachés à cette sous-catégorie.");

            $bdBASI->beginTransaction();
            $bdBASI->prepare("UPDATE souscategorie SET statut=0, date_derniere_modification=NOW() WHERE id=?")->execute([$id]);
            insertHistoriqueSousCategorie($bdBASI, $id, (int)$sub['categorie_id'], $sub['nom_sous_categorie'], 'suppression', $sessionUserId);
            $bdBASI->commit();

            echo json_encode(['status'=>'success','message'=>'Sous-catégorie supprimée avec succès.']);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 8 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 8: ".$e->getMessage()); http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
        }
        exit;

// ─── CASE 9 : POST — liste des statuts produit ───────────────────────────────
// Reprend la logique de l'ancien case 55 (table statut, id_statut/nom_statut)
    case 9:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $stmt = $bdBASI->prepare("SELECT id_statut, nom_statut FROM statut ORDER BY nom_statut ASC");
            $stmt->execute();
            echo json_encode(['status'=>'success','data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { error_log("Case 9 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 9: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 10 : POST — liste des produits (stock) ─────────────────────────────
// Reprend la logique de l'ancien case 56
    case 10:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $sql = "SELECT p.*,
                           s.nom_statut,
                           sc.nom_sous_categorie AS nom_souscat,
                           c.nom_categorie,
                           COALESCE((SELECT COUNT(*) FROM ligneBudget lb WHERE lb.id_produit = p.idP  AND lb.idStatut=1), 0)  AS utilise
                    FROM product p
                    LEFT JOIN statut s        ON p.id_statut = s.id_statut
                    LEFT JOIN souscategorie sc ON p.id_Sous_categorie = sc.id
                    LEFT JOIN categorie c      ON c.id = sc.categorie_id
                    WHERE p.id_type_product = ?
                    ORDER BY p.idP DESC";
            $stmt = $bdBASI->prepare($sql);
            $stmt->execute([ID_TYPE_PRODUCT_STOCK]);
            echo json_encode(['status'=>'success','data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {echo $e; die; error_log("Case 10 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { echo $e; die;error_log("Case 10: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 11 : POST — créer un ou plusieurs produits ─────────────────────────
// Reprend la logique de l'ancien case 146 (bulk insert avec vérification doublons)
// Body JSON : { "produits": [ { "nom": "...", "seuil": N, "sous_categorie_id": N }, ... ] }
    case 11:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data     = getJsonBody();
            $produits = $data['produits'] ?? [];
            if (empty($produits) || !is_array($produits)) throw new Exception("Au moins un produit est requis.");

            $bdBASI->beginTransaction();

            $stmtCheck      = $bdBASI->prepare("SELECT COUNT(*) FROM product WHERE nomproduit=? AND id_Sous_categorie=?");
            $stmtInsert     = $bdBASI->prepare("
                INSERT INTO product (nomproduit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, id_type_product, date_creation)
                VALUES (?,0,?,0,?,0,1,?,?)
            ");
            $stmtUpdateCode = $bdBASI->prepare("UPDATE product SET code_produit=? WHERE idP=?");

            $crees     = [];
            $dupliques = [];

            foreach ($produits as $p) {
                $nom          = trim($p['nom'] ?? '');
                $seuil        = isset($p['seuil']) ? (int)$p['seuil'] : 0;
                $scId         = isset($p['sous_categorie_id']) ? (int)$p['sous_categorie_id'] : null;
                $dateCreation = date('Y-m-d H:i:s');

                if (!$nom || !$scId) continue; // ligne incomplète, ignorée silencieusement

                $stmtCheck->execute([$nom, $scId]);
                if ($stmtCheck->fetchColumn() > 0) { $dupliques[] = $nom; continue; }

                $stmtInsert->execute([$nom, $seuil, $scId, ID_TYPE_PRODUCT_STOCK, $dateCreation]);
                $newId = (int)$bdBASI->lastInsertId();

                $codeProduit = 'PRD-' . str_pad($newId, 6, '0', STR_PAD_LEFT);
                $stmtUpdateCode->execute([$codeProduit, $newId]);

                insertHistoriqueProduit(
                    $bdBASI, $newId, $nom, $codeProduit,
                    0, $seuil, 0, $scId, 0, 1,
                    $dateCreation, ID_TYPE_PRODUCT_STOCK, 'insertion'
                );

                $crees[] = ['id'=>$newId,'nom'=>$nom,'code_produit'=>$codeProduit];
            }

            $bdBASI->commit();

            if (empty($crees) && !empty($dupliques)) {
                echo json_encode(['status'=>'error','message'=>'Les produits suivants existent déjà : '.implode(', ', $dupliques)]);
            } elseif (!empty($dupliques)) {
                echo json_encode(['status'=>'success','data'=>$crees,'message'=>count($crees).' produit(s) créé(s). Doublons ignorés : '.implode(', ', $dupliques)]);
            } else {
                echo json_encode(['status'=>'success','data'=>$crees,'message'=>count($crees).' produit(s) créé(s) avec succès.']);
            }
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 11 PDO: ".$e->getMessage());
            http_response_code(500);
            echo json_encode(["status"=>"error","message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 11: ".$e->getMessage());
            http_response_code($e->getCode()?:400);
            echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
        }
        exit;

// ─── CASE 12 : POST — vérifier si un produit est utilisé dans une commande ───
// Reprend la logique de l'ancien case 149 (partie lecture, utilisée par btn-edit côté JS)
// Body JSON : { "id": 12 }
    case 12:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data = getJsonBody();
            $id   = isset($data['id']) ? (int)$data['id'] : null;
            if (!$id) throw new Exception("Champ requis manquant : id.");

            $stmt = $bdBASI->prepare("SELECT COUNT(*) FROM bon_commande_produit WHERE idP=?");
            $stmt->execute([$id]);
            $used = (int)$stmt->fetchColumn() > 0;

            echo json_encode(['status'=>'success','used'=>$used]);
        } catch (PDOException $e) { error_log("Case 12 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 12: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 13 : POST — modifier un produit ────────────────────────────────────
// Seuls nom, sous-catégorie et seuil d'alerte sont modifiables.
// Stock, Total, Retrait et Statut restent inchangés (gérés ailleurs : mouvements de stock, toggle statut).
// Body JSON : { "id": 12, "nom": "...", "sous_categorie_id": N, "seuil"?: N }
    case 13:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data = getJsonBody();
            $id   = isset($data['id']) ? (int)$data['id'] : null;
            $nom  = trim($data['nom'] ?? '');
            $scId = isset($data['sous_categorie_id']) ? (int)$data['sous_categorie_id'] : null;
            if (!$id)   throw new Exception("Champ requis manquant : id.");
            if (!$nom)  throw new Exception("Le nom du produit est requis.");
            if (!$scId) throw new Exception("sous_categorie_id requis.");

            $chk = $bdBASI->prepare("SELECT * FROM product WHERE idP=?");
            $chk->execute([$id]);
            $old = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$old) throw new Exception("Produit non trouvé.",404);

            $chkDup = $bdBASI->prepare("SELECT COUNT(*) FROM product WHERE nomproduit=? AND id_Sous_categorie=? AND idP!=?");
            $chkDup->execute([$nom, $scId, $id]);
            if ($chkDup->fetchColumn() > 0) throw new Exception("Un autre produit porte déjà ce nom dans cette sous-catégorie.");

            // Seuil : modifiable, garde l'ancienne valeur si absent du payload
            $seuil = isset($data['seuil']) ? (int)$data['seuil'] : (int)$old['Seuil_limite'];

            // Stock, Total, Retrait, Statut : jamais touchés ici, on garde les valeurs existantes
            $stock   = (int)$old['Stock_actuel'];
            $total   = (int)$old['Total'];
            $retrait = (int)$old['retrait'];
            $statut  = (int)$old['id_statut'];

            $bdBASI->beginTransaction();
            $bdBASI->prepare("
                UPDATE product SET
                    nomproduit=?, id_Sous_categorie=?, Seuil_limite=?
                WHERE idP=?
            ")->execute([$nom, $scId, $seuil, $id]);

            insertHistoriqueProduit(
                $bdBASI, $id, $nom, $old['code_produit'] ?? null,
                $stock, $seuil, $total, $scId, $retrait, $statut,
                $old['date_creation'] ?? date('Y-m-d H:i:s'), (int)$old['id_type_product'], 'modification'
            );
            $bdBASI->commit();

            echo json_encode(['status'=>'success','message'=>'Produit modifié avec succès.']);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 13 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 13: ".$e->getMessage()); http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
        }
        exit;

// ─── CASE 14 : POST — activer / désactiver un produit ────────────────────────
// Reprend la logique de l'ancien case 149 (partie écriture)
// Body JSON : { "id": 12, "statut": 1|2 }
    case 14:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data   = getJsonBody();
            $id     = isset($data['id'])     ? (int)$data['id']     : null;
            $statut = isset($data['statut']) ? (int)$data['statut'] : null;
            if (!$id)               throw new Exception("Champ requis manquant : id.");
            if ($statut===null)     throw new Exception("Champ requis manquant : statut.");

            $chk = $bdBASI->prepare("SELECT * FROM product WHERE idP=?");
            $chk->execute([$id]);
            $prod = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$prod) throw new Exception("Produit non trouvé.",404);

            $bdBASI->beginTransaction();
            $bdBASI->prepare("UPDATE product SET id_statut=? WHERE idP=?")->execute([$statut, $id]);

            insertHistoriqueProduit(
                $bdBASI, $id, $prod['nomproduit'], $prod['code_produit'] ?? null,
                (int)$prod['Stock_actuel'], (int)$prod['Seuil_limite'], (int)$prod['Total'],
                (int)$prod['id_Sous_categorie'], (int)$prod['retrait'], $statut,
                $prod['date_creation'] ?? date('Y-m-d H:i:s'), (int)$prod['id_type_product'], 'modification'
            );
            $bdBASI->commit();

            echo json_encode(['status'=>'success','message'=>'Statut mis à jour avec succès.']);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 14 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 14: ".$e->getMessage()); http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
        }
        exit;

// ─── CASE 15 : POST — alertes de réapprovisionnement ─────────────────────────
// Reprend la logique de l'ancien case 187
    case 15:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $sql = "SELECT p.nomproduit, c.nom_categorie, sc.nom_sous_categorie AS souscat,
                           p.Stock_actuel, p.Seuil_limite
                    FROM product p
                    LEFT JOIN souscategorie sc ON sc.id = p.id_Sous_categorie
                    LEFT JOIN categorie c      ON c.id = sc.categorie_id
                    WHERE p.Stock_actuel <= p.Seuil_limite AND p.id_type_product = ?
                    ORDER BY p.Stock_actuel ASC";
            $stmt = $bdBASI->prepare($sql);
            $stmt->execute([ID_TYPE_PRODUCT_STOCK]);
            echo json_encode(['status'=>'success','data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { echo $e;die; error_log("Case 15 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { echo $e; die;error_log("Case 15: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ═══════════════════════════════════════════════════════════════════════════════
// CASES 16-26 : MODULE BUDGET FONCTIONNEMENT — vue comptable, tous budgets
// type_budget_id fixé à TYPE_BUDGET_FONCTIONNEMENT ; direction_id est NULL pour
// ce type de budget, donc aucune jointure/filtre direction. Le comptable a
// accès à tous les budgets de fonctionnement, toutes directions confondues.
// ═══════════════════════════════════════════════════════════════════════════════

// ─── CASE 16 : POST — liste de tous les budgets fonctionnement ──────────────
// Body JSON : { "annee"?: N, "limit"?: N, "offset"?: N }
    case 16:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data   = getJsonBody();
            $annee  = isset($data['annee'])  && $data['annee']!==''  ? (int)$data['annee']  : null;
            $limit  = isset($data['limit'])  && $data['limit']!==''  ? max(1,(int)$data['limit'])  : 50;
            $offset = isset($data['offset']) && $data['offset']!=='' ? max(0,(int)$data['offset']) : 0;

            $sql = "SELECT b.*, tb.nom AS type_budget_nom, ".sqlIdStatut('b')."
                    FROM budget b
                    JOIN typeBudget tb ON b.type_budget_id=tb.id
                    WHERE b.statut!='Supprimer'
                      AND b.type_budget_id=:type_budget_id";
            if ($annee!==null) $sql .= " AND b.annee=:annee";
            $sql .= " ORDER BY b.annee DESC, b.id DESC LIMIT :limit OFFSET :offset";

            $stmt=$bdBASI->prepare($sql);
            $stmt->bindValue(':type_budget_id',TYPE_BUDGET_FONCTIONNEMENT,PDO::PARAM_INT);
            if ($annee!==null) $stmt->bindValue(':annee',(int)$annee,PDO::PARAM_INT);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset',(int)$offset,PDO::PARAM_INT);
            $stmt->execute();
            $rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
            foreach ($rows as &$row) $row['tmp']=$basiController->tokenencrypt($row['id']);
            unset($row);
            echo json_encode(["status"=>"success","data"=>$rows]);
        } catch (PDOException $e) { error_log("Case 16 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 16: ".$e->getMessage());     http_response_code(400);  echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 17 : POST — un budget fonctionnement par id ────────────────────────
// Body JSON : { "id": 42 }
    case 17:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            if (empty($data['id'])) throw new Exception("Champ requis manquant : id.");
            $id=(int)$data['id'];
            $stmt=$bdBASI->prepare("
                SELECT b.*, tb.nom AS type_budget_nom, ".sqlIdStatut('b')."
                FROM budget b
                JOIN typeBudget tb ON b.type_budget_id=tb.id
                WHERE b.id=:id AND b.statut!='Supprimer' AND b.type_budget_id=:type_budget_id
                LIMIT 1
            ");
            $stmt->bindValue(':id',            $id,                            PDO::PARAM_INT);
            $stmt->bindValue(':type_budget_id',TYPE_BUDGET_FONCTIONNEMENT,     PDO::PARAM_INT);
            $stmt->execute();
            $result=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) { http_response_code(404); echo json_encode(["status"=>"error","message"=>"Budget non trouvé."]); exit; }
            echo json_encode(["status"=>"success","data"=>$result]);
        } catch (PDOException $e) { error_log("Case 17 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 17: ".$e->getMessage());     http_response_code(400);  echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 18 : POST — créer un budget fonctionnement ─────────────────────────
// Body JSON : { "annee": 2026, "plafond": 5000000 }
// type_budget_id fixé côté serveur. direction_id toujours NULL (fonctionnement = transversal).
    case 18:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            if (empty($data['annee'])) throw new Exception("Champ requis manquant : annee.");

            $annee         = (int)$data['annee'];
            $plafond       = isset($data['plafond'])&&is_numeric($data['plafond'])?(float)$data['plafond']:null;
            $type_budget_id= TYPE_BUDGET_FONCTIONNEMENT;
            $matricule     = $sessionMatricule;
            $idUtilisateur = (int)$sessionUserId;
            $statut        = 'En cours';
            $idStatut      = 1;

            $curYear=(int)date('Y');
            if ($annee<$curYear)      throw new Exception("Impossible de créer un budget pour une année passée.");
            if ($annee>$curYear+10)   throw new Exception("L'année ne peut pas excéder 10 ans dans le futur.");
            if ($plafond!==null&&($plafond<=0||$plafond>1000000000)) throw new Exception("Plafond hors limites.");

            $chkT=$bdBASI->prepare("SELECT COUNT(*) FROM typeBudget WHERE id=?");
            $chkT->execute([$type_budget_id]);
            if ($chkT->fetchColumn()==0) throw new Exception("Type de budget invalide (ID=".TYPE_BUDGET_FONCTIONNEMENT.").");

            $chkU=$bdBASI->prepare("SELECT COUNT(*) FROM budget WHERE annee=? AND type_budget_id=? AND direction_id IS NULL AND statut!='Supprimer'");
            $chkU->execute([$annee,$type_budget_id]);
            if ($chkU->fetchColumn()>0) throw new Exception("Un budget de fonctionnement existe déjà pour l'année $annee.");

            $dateCreation = date('Y-m-d H:i:s');
            $stmt=$bdBASI->prepare("INSERT INTO budget (annee,type_budget_id,plafond,statut,idStatut,date_creation,matricule,idUtilisateur,direction_id) VALUES (?,?,?,?,?,?,?,?,NULL)");
            $stmt->execute([$annee,$type_budget_id,$plafond,$statut,$idStatut,$dateCreation,$matricule,$idUtilisateur]);
            $newId=$bdBASI->lastInsertId();

            insertHistoriqueBudget($bdBASI,(int)$newId,[
                'annee'=>$annee,'matricule'=>$matricule,'direction_id'=>null,
                'type_budget_id'=>$type_budget_id,'date_creation'=>$dateCreation,
                'statut'=>$statut,'idStatut'=>$idStatut,'plafond'=>$plafond,
            ],$idUtilisateur,'Insertion');

            echo json_encode(["status"=>"success","data"=>["id"=>(int)$newId],"message"=>"Budget de fonctionnement créé avec succès."]);
        } catch (PDOException $e) { error_log("Case 18 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 18: ".$e->getMessage());     http_response_code(400);  echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 19 : POST — mettre à jour un budget fonctionnement ────────────────
// Body JSON : { "id": 42, "plafond"?: ..., "statut"?: ..., "motif"?: ... }
    case 19:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            if (empty($data['id'])) throw new Exception("Champ requis manquant : id.");
            $id=(int)$data['id'];

            $get=$bdBASI->prepare("SELECT * FROM budget WHERE id=? AND type_budget_id=? AND statut!='Supprimer'");
            $get->execute([$id,TYPE_BUDGET_FONCTIONNEMENT]);
            $old=$get->fetch(PDO::FETCH_ASSOC);
            if (!$old) throw new Exception("Budget non trouvé.");
            if (in_array($old['statut'], ['Terminer', 'Valider', 'Accepter'], true))
                throw new Exception("Impossible de modifier un budget au statut '{$old['statut']}'.");

            $statut_incoming = isset($data['statut']) ? trim($data['statut']) : $old['statut'];
            if ($statut_incoming === 'Réajuster' || $old['statut'] === 'Réajuster') {
                $curYear = (int)date('Y');
                if ((int)$old['annee'] < $curYear) {
                    throw new Exception(
                        "Le statut 'Réajuster' n'est applicable que pour des budgets de l'année en cours ($curYear) ou des années suivantes. "
                        . "Ce budget concerne l'année {$old['annee']}."
                    );
                }
            }

            $plafond     = array_key_exists('plafond',$data)
                ? (($data['plafond']===null||$data['plafond']==='') ? null : (float)$data['plafond'])
                : (float)$old['plafond'];
            $statut      = isset($data['statut']) ? trim($data['statut']) : $old['statut'];
            $idStatut    = getIdStatut($statut);
            $motifUpdate = isset($data['motif']) ? trim($data['motif']) : null;

            $validStatuts=['En cours','Dépassé','Sauvegarder','Terminer','Rejeter','Valider','Accepter','Réajuster'];
            if (!in_array($statut,$validStatuts,true)) throw new Exception("Statut invalide : $statut.");
            if ($plafond!==null&&($plafond<=0||$plafond>1000000000)) throw new Exception("Plafond hors limites.");

            if ($plafond !== null && array_key_exists('plafond', $data)) {
                $sqlSomme = "
                    SELECT COALESCE(SUM(
                        CASE
                            WHEN lb.id_type_budget_investissement IN (
                                SELECT id FROM type_budget_investissement WHERE categorie='Produit'
                            ) THEN lb.quantite * lb.prix_unitaire
                            ELSE lb.prix_unitaire
                        END
                    ), 0)
                    FROM ligneBudget lb
                    WHERE lb.budget_id = ?
                      AND lb.statut    = 'Actif'
                      AND (lb.idStatut = 1 OR lb.verrouiller = 1)";
                $stmtSomme = $bdBASI->prepare($sqlSomme);
                $stmtSomme->execute([$id]);
                $sommeLignes = (float)$stmtSomme->fetchColumn();

                if ($sommeLignes > 0 && (float)$plafond < $sommeLignes) {
                    throw new Exception(
                        "Le plafond ne peut pas être inférieur au total des lignes actives/verrouillées ("
                        . number_format($sommeLignes, 0, ',', ' ') . " FCFA)."
                    );
                }
                $plafondActuel = (float)$old['plafond'];
                if ($sommeLignes > 0 && (float)$plafond < $plafondActuel) {
                    throw new Exception(
                        "Impossible de diminuer le plafond lorsque des lignes actives ou verrouillées sont rattachées à ce budget. "
                        . "Plafond actuel : " . number_format($plafondActuel, 0, ',', ' ') . " FCFA."
                    );
                }
            }

            $fields=[]; $params=[':id'=>$id,':tbid'=>TYPE_BUDGET_FONCTIONNEMENT];
            if (array_key_exists('plafond',$data)) { $fields[]="plafond=:plafond"; $params[':plafond']=$plafond; }
            if (isset($data['statut']))             { $fields[]="statut=:statut"; $fields[]="idStatut=:idStatut"; $params[':statut']=$statut; $params[':idStatut']=$idStatut; }
            if (empty($fields)) throw new Exception("Aucun champ modifiable fourni.");

            $bdBASI->beginTransaction();
            $stmt=$bdBASI->prepare("UPDATE budget SET ".implode(',',$fields)." WHERE id=:id AND type_budget_id=:tbid");
            foreach ($params as $k=>$v) {
                if ($k===':plafond'&&$v===null) $stmt->bindValue($k,null,PDO::PARAM_NULL);
                else $stmt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
            }
            $stmt->execute();

            if ($statut==='Rejeter')                                                                      { $motif=$motifUpdate?:"Budget rejeté"; }
            elseif (array_key_exists('plafond',$data)&&(float)$old['plafond']!==(float)$plafond) { $motif="Plafond modifié : ".number_format((float)$old['plafond'],0,',',' ')." → ".number_format((float)$plafond,0,',',' ')." FCFA"; }
            else                                                                                           { $motif=$motifUpdate?:"Mise à jour du budget"; }

            insertHistoriqueBudget($bdBASI,$id,[
                'annee'=>$old['annee'],'matricule'=>$old['matricule'],
                'direction_id'=>null,'type_budget_id'=>TYPE_BUDGET_FONCTIONNEMENT,
                'date_creation'=>$old['date_creation'],'statut'=>$statut,'idStatut'=>$idStatut,'plafond'=>$plafond,
            ],(int)$sessionUserId,$motif);
            $bdBASI->commit();

            $rel=$bdBASI->prepare("SELECT b.*,tb.nom AS type_budget_nom,".sqlIdStatut('b')." FROM budget b JOIN typeBudget tb ON b.type_budget_id=tb.id WHERE b.id=?");
            $rel->execute([$id]);
            echo json_encode(["status"=>"success","data"=>$rel->fetch(PDO::FETCH_ASSOC),"message"=>"Budget mis à jour avec succès."]);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 19 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 19: ".$e->getMessage()); http_response_code(400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
        }
        exit;

// ─── CASE 20 : POST — lignes (action=lines) | supprimer (action=delete) ──────
    case 20:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data  =getJsonBody();
            $action=trim($data['action']??'');

            if ($action==='lines') {
                $budgetId=$basiController->tokendecrypt($data['budgetId']??'');
                if (!$budgetId) throw new Exception("Champ requis manquant : budgetId.");
                $chk=$bdBASI->prepare("SELECT COUNT(*) FROM budget WHERE id=? AND type_budget_id=? AND statut!='Supprimer'");
                $chk->execute([$budgetId,TYPE_BUDGET_FONCTIONNEMENT]);
                if ($chk->fetchColumn()==0) throw new Exception("Budget non trouvé.");
                $stmt=$bdBASI->prepare("
                    SELECT lb.*, c.nom_categorie AS categorie_nom, sc.nom_sous_categorie AS sous_categorie_nom,
                           sc.id AS sous_categorie_id, c.id AS categorie_id,
                           s.nom_services AS service_nom
                    FROM ligneBudget lb
                    LEFT JOIN product      p  ON lb.id_produit        = p.idP
                    LEFT JOIN souscategorie sc ON p.id_Sous_categorie = sc.id
                    LEFT JOIN categorie    c  ON sc.categorie_id      = c.id
                    LEFT JOIN services     s  ON lb.service_id        = s.id
                    WHERE lb.budget_id=? AND lb.statut!='Inactif'
                ");
                $stmt->execute([$budgetId]);
                $lines=$stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["status"=>"success","lineCount"=>count($lines),"lastUpdate"=>date('Y-m-d H:i:s'),"lines"=>$lines]);
                exit;
            }

            if ($action==='delete') {
                $id=isset($data['id'])?(int)$data['id']:null;
                if (!$id) throw new Exception("Champ requis manquant : id.");
                $chk=$bdBASI->prepare("SELECT * FROM budget WHERE id=? AND type_budget_id=?");
                $chk->execute([$id,TYPE_BUDGET_FONCTIONNEMENT]);
                $budgetRow=$chk->fetch(PDO::FETCH_ASSOC);
                if (!$budgetRow) throw new Exception("Budget non trouvé.");
                if (in_array($budgetRow['statut'],['Valider','Accepter','Réajuster'])) throw new Exception("Impossible de supprimer un budget au statut '{$budgetRow['statut']}'.");

                $bdBASI->beginTransaction();
                try {
                    $lns=$bdBASI->prepare("SELECT * FROM ligneBudget WHERE budget_id=? AND statut!='Inactif' AND idStatut!=2");
                    $lns->execute([$id]); $lignes=$lns->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($lignes)) {
                        $bdBASI->prepare("UPDATE ligneBudget SET statut='Inactif', idStatut=2 WHERE budget_id=? AND statut!='Inactif' AND idStatut!=2")->execute([$id]);
                        foreach ($lignes as $ligneSnap) {
                            insertHistoriqueLigneBudgetFonct($bdBASI, $ligneSnap, 'Inactif', 2, 'Suppression (budget supprimé)');
                        }
                    }
                    $bdBASI->prepare("UPDATE budget SET statut='Supprimer', idStatut=8 WHERE id=?")->execute([$id]);
                    insertHistoriqueBudget($bdBASI,$id,[
                        'annee'=>$budgetRow['annee'],'matricule'=>$budgetRow['matricule'],
                        'direction_id'=>null,'type_budget_id'=>$budgetRow['type_budget_id'],
                        'date_creation'=>$budgetRow['date_creation'],'statut'=>'Supprimer',
                        'idStatut'=>8,'plafond'=>$budgetRow['plafond'],
                    ],(int)$sessionUserId,'Suppression');
                    $bdBASI->commit();
                    $nb=count($lignes);
                    echo json_encode(["status"=>"success","message"=>"Budget supprimé avec succès".($nb>0?" ($nb ligne(s) supprimée(s)).":".")]);
                } catch (Exception $ie) { $bdBASI->rollBack(); throw $ie; }
                exit;
            }
            throw new Exception("Action non supportée. Valeurs acceptées : 'lines', 'delete'.");
        } catch (PDOException $e) { error_log("Case 20 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 20: ".$e->getMessage());     http_response_code(400);  echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 21 : POST — créer une ligne (fonctionnement, produit existant sélectionné) ──
// Le budget fonctionnement n'a qu'un seul type de ligne : Produit.
// categorie_id / sous_categorie_id (tables categorie/souscategorie) remplacent
// rubrique_id / sous_rubrique_id, qui restent toujours NULL en base.
// Le produit est SÉLECTIONNÉ parmi ceux existants (id_produit), filtré par
// sous_categorie_id + id_type_product=Fonctionnement + id_statut=1 — aucune
// création automatique de produit ici. service_id n'existe plus sur ce formulaire
// (toujours NULL).
    case 21:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();

            foreach (['budgetId','categorie_id','sous_categorie_id','id_produit'] as $f) {
                if (empty($data[$f])) throw new Exception("Champ requis manquant : $f");
            }

            $budgetId        = $basiController->tokendecrypt($data['budgetId']);
            $description     = !empty($data['description']) ? trim($data['description']) : null;
            $periode         = trim($data['periode_d_utilisation'] ?? '');
            $categorieId     = (int)$data['categorie_id'];
            $sousCategorieId = (int)$data['sous_categorie_id'];
            $idProduit       = (int)$data['id_produit'];

            $chk=$bdBASI->prepare("SELECT statut FROM budget WHERE id=? AND type_budget_id=? AND statut!='Supprimer'");
            $chk->execute([$budgetId,TYPE_BUDGET_FONCTIONNEMENT]);
            $bRow=$chk->fetch(PDO::FETCH_ASSOC);
            if (!$bRow) throw new Exception("Budget non trouvé.",404);
            if (in_array($bRow['statut'],['Valider','Accepter','Terminer'],true))
                throw new Exception("Impossible d'ajouter une ligne à un budget au statut '{$bRow['statut']}'.");

            if (empty($periode)) throw new Exception("La période d'utilisation est requise.");

            $chkCat=$bdBASI->prepare("SELECT COUNT(*) FROM categorie WHERE id=? AND statut=1");
            $chkCat->execute([$categorieId]);
            if ($chkCat->fetchColumn()==0) throw new Exception("Catégorie invalide ou inactive.");

            $chkSousCat=$bdBASI->prepare("SELECT COUNT(*) FROM souscategorie WHERE id=? AND categorie_id=? AND statut=1");
            $chkSousCat->execute([$sousCategorieId,$categorieId]);
            if ($chkSousCat->fetchColumn()==0) throw new Exception("Sous-catégorie invalide, inactive ou n'appartenant pas à la catégorie sélectionnée.");

            // Vérifier que le produit sélectionné existe bien, appartient à la sous-catégorie,
            // est de type Fonctionnement et est actif
            $chkProd = $bdBASI->prepare("
                SELECT nomproduit FROM product
                WHERE idP=? AND id_Sous_categorie=? AND id_type_product=? AND id_statut=1
            ");
            $chkProd->execute([$idProduit, $sousCategorieId, TYPE_BUDGET_FONCTIONNEMENT]);
            $prod = $chkProd->fetch(PDO::FETCH_ASSOC);
            if (!$prod) throw new Exception("Produit invalide, inactif ou n'appartenant pas à la sous-catégorie sélectionnée.");
            $designation = $prod['nomproduit'];

            if (!isset($data['quantite'])      || $data['quantite']      === '') throw new Exception("La quantité est requise.");
            if (!isset($data['unite_id'])      || $data['unite_id']      === '') throw new Exception("L'unité est requise.");
            if (!isset($data['prix_unitaire'])  || $data['prix_unitaire'] === '') throw new Exception("Le prix unitaire est requis.");

            $quantite     = (int)$data['quantite'];
            $uniteId      = (int)$data['unite_id'];
            $prixUnitaire = (float)$data['prix_unitaire'];
            $montantTotal = round($prixUnitaire * $quantite, 2);

            if ($quantite <= 0)     throw new Exception("La quantité doit être supérieure à 0.");
            if ($prixUnitaire <= 0) throw new Exception("Le prix unitaire doit être supérieur à 0.");

            // rubrique_id / sous_rubrique_id / service_id toujours NULL pour le fonctionnement
            $rubriqueId = null;
            $sousRubId  = null;
            $serviceId  = null;

            $bdBASI->beginTransaction();

            // id_type_budget_investissement = 1 par défaut pour le fonctionnement
            $stmtL = $bdBASI->prepare("
                INSERT INTO ligneBudget
                    (budget_id, service_id, id_type_budget_investissement, rubrique_id, sous_rubrique_id,
                     designation, description, periode_d_utilisation, quantite, unite_id,
                     prix_unitaire, montant_total, id_produit, statut, idStatut, date_creation)
                VALUES (?,NULL,1,?,?,?,?,?,?,?,?,?,?,'Actif',1,NOW())
            ");
            $stmtL->execute([
                $budgetId, $rubriqueId, $sousRubId,
                $designation, $description, $periode, $quantite, $uniteId,
                $prixUnitaire, $montantTotal, $idProduit,
            ]);
            $newId = (int)$bdBASI->lastInsertId();

            insertHistoriqueLigneBudgetFonct($bdBASI, [
                'id'                            => $newId,
                'budget_id'                     => $budgetId,
                'rubrique_id'                   => $rubriqueId,
                'sous_rubrique_id'              => $sousRubId,
                'id_type_budget_investissement' => 1,
                'designation'                   => $designation,
                'description'                   => $description,
                'quantite'                      => $quantite,
                'unite_id'                      => $uniteId,
                'prix_unitaire'                 => $prixUnitaire,
                'montant_total'                 => $montantTotal,
                'service_id'                    => null,
                'id_produit'                    => $idProduit,
                'date_creation'                 => date('Y-m-d H:i:s'),
                'periode_d_utilisation'         => $periode,
            ], 'Actif', 1, 'Insertion');

//            $bdBASI->prepare("UPDATE budget SET statut='Sauvegarder', idStatut=3 WHERE id=?")->execute([$budgetId]);
            $bdBASI->commit();

            echo json_encode(['success'=>true,'new_id'=>$newId,'message'=>'Ligne créée avec succès.']);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 21 PDO: ".$e->getMessage());
            http_response_code(500);
            echo json_encode(["success"=>false,"message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 21: ".$e->getMessage());
            http_response_code($e->getCode()?:400);
            echo json_encode(["success"=>false,"message"=>$e->getMessage()]);
        }
        exit;

// ─── CASE 22 : POST — modifier une ligne (fonctionnement, type Produit implicite) ──
// ─── CASE 22 : POST — modifier une ligne (fonctionnement, produit existant sélectionné) ──
    case 22:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            if (empty($data['lineId'])) throw new Exception("Champ requis manquant : lineId.");
            foreach (['categorie_id','sous_categorie_id','id_produit'] as $f) {
                if (empty($data[$f])) throw new Exception("Champ requis manquant : $f");
            }

            $lineId          = (int)$data['lineId'];
            $description     = !empty($data['description']) ? trim($data['description']) : null;
            $periode         = trim($data['periode_d_utilisation'] ?? '');
            $categorieId     = (int)$data['categorie_id'];
            $sousCategorieId = (int)$data['sous_categorie_id'];
            $idProduit       = (int)$data['id_produit'];

            $chkLine=$bdBASI->prepare("SELECT * FROM ligneBudget WHERE id=? AND statut!='Inactif'");
            $chkLine->execute([$lineId]);
            $lineRow=$chkLine->fetch(PDO::FETCH_ASSOC);
            if (!$lineRow) throw new Exception("Ligne non trouvée.",404);

            if ((int)$lineRow['verrouiller'] === 1)
                throw new Exception("Impossible de modifier une ligne verrouillée.",403);

            $chkBudget=$bdBASI->prepare("SELECT statut FROM budget WHERE id=? AND type_budget_id=? AND statut!='Supprimer'");
            $chkBudget->execute([$lineRow['budget_id'],TYPE_BUDGET_FONCTIONNEMENT]);
            $bRow=$chkBudget->fetch(PDO::FETCH_ASSOC);
            if (!$bRow) throw new Exception("Accès non autorisé.",403);
            if (in_array($bRow['statut'],['Valider','Accepter','Terminer'],true))
                throw new Exception("Impossible de modifier une ligne d'un budget au statut '{$bRow['statut']}'.");

            if (empty($periode)) throw new Exception("La période d'utilisation est requise.");

            $chkCat=$bdBASI->prepare("SELECT COUNT(*) FROM categorie WHERE id=? AND statut=1");
            $chkCat->execute([$categorieId]);
            if ($chkCat->fetchColumn()==0) throw new Exception("Catégorie invalide ou inactive.");

            $chkSousCat=$bdBASI->prepare("SELECT COUNT(*) FROM souscategorie WHERE id=? AND categorie_id=? AND statut=1");
            $chkSousCat->execute([$sousCategorieId,$categorieId]);
            if ($chkSousCat->fetchColumn()==0) throw new Exception("Sous-catégorie invalide, inactive ou n'appartenant pas à la catégorie sélectionnée.");

            // Valider que le produit sélectionné est bien actif et dans la bonne sous-catégorie
            $chkProd = $bdBASI->prepare("
                SELECT nomproduit FROM product
                WHERE idP=? AND id_Sous_categorie=? AND id_type_product=? AND id_statut=1
            ");
            $chkProd->execute([$idProduit, $sousCategorieId, TYPE_BUDGET_FONCTIONNEMENT]);
            $prod = $chkProd->fetch(PDO::FETCH_ASSOC);
            if (!$prod) throw new Exception("Produit invalide, inactif ou n'appartenant pas à la sous-catégorie sélectionnée.");
            $designation = $prod['nomproduit'];

            if (!isset($data['quantite'])      || $data['quantite']      === '') throw new Exception("La quantité est requise.");
            if (!isset($data['unite_id'])      || $data['unite_id']      === '') throw new Exception("L'unité est requise.");
            if (!isset($data['prix_unitaire']) || $data['prix_unitaire'] === '') throw new Exception("Le prix unitaire est requis.");

            $quantite     = (int)$data['quantite'];
            $uniteId      = (int)$data['unite_id'];
            $prixUnitaire = (float)$data['prix_unitaire'];
            $montantTotal = round($prixUnitaire * $quantite, 2);

            if ($quantite <= 0)     throw new Exception("La quantité doit être supérieure à 0.");
            if ($prixUnitaire <= 0) throw new Exception("Le prix unitaire doit être supérieur à 0.");

            $bdBASI->beginTransaction();

            // service_id toujours NULL pour le fonctionnement
            $bdBASI->prepare("
                UPDATE ligneBudget SET
                    rubrique_id=NULL, sous_rubrique_id=NULL, service_id=NULL,
                    id_type_budget_investissement=1,
                    designation=?, description=?, quantite=?, unite_id=?,
                    prix_unitaire=?, montant_total=?,
                    periode_d_utilisation=?, id_produit=?
                WHERE id=?
            ")->execute([
                $designation, $description, $quantite, $uniteId,
                $prixUnitaire, $montantTotal,
                $periode, $idProduit, $lineId,
            ]);

            insertHistoriqueLigneBudgetFonct($bdBASI, [
                'id'                            => $lineId,
                'budget_id'                     => $lineRow['budget_id'],
                'rubrique_id'                   => $categorieId,
                'sous_rubrique_id'              => $sousCategorieId,
                'id_type_budget_investissement' => 1,
                'designation'                   => $designation,
                'description'                   => $description,
                'quantite'                      => $quantite,
                'unite_id'                      => $uniteId,
                'prix_unitaire'                 => $prixUnitaire,
                'montant_total'                 => $montantTotal,
                'service_id'                    => null,
                'id_produit'                    => $idProduit,
                'date_creation'                 => date('Y-m-d H:i:s'),
                'periode_d_utilisation'         => $periode,
            ], 'Actif', 1, 'Modification');

            $bdBASI->prepare("UPDATE budget SET statut='Sauvegarder', idStatut=3 WHERE id=?")->execute([$lineRow['budget_id']]);
            $bdBASI->commit();

            echo json_encode(['success'=>true,'message'=>'Ligne modifiée avec succès.']);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 22 PDO: ".$e->getMessage());
            http_response_code(500);
            echo json_encode(["success"=>false,"message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 22: ".$e->getMessage());
            http_response_code($e->getCode()?:400);
            echo json_encode(["success"=>false,"message"=>$e->getMessage()]);
        }
        exit;

// ─── CASE 23 : POST — supprimer une ligne (soft delete) ──────────────────────
// Body JSON : { "lineId": 5 }
    case 23:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data  =getJsonBody();
            $lineId=isset($data['lineId'])?(int)$data['lineId']:null;
            if (!$lineId) throw new Exception("Champ requis manquant : lineId.");

            $chkLine=$bdBASI->prepare("SELECT * FROM ligneBudget WHERE id=? AND statut!='Inactif'");
            $chkLine->execute([$lineId]);
            $lineRow=$chkLine->fetch(PDO::FETCH_ASSOC);
            if (!$lineRow) throw new Exception("Ligne non trouvée.",404);

            if (!empty($lineRow['verrouiller']) && (int)$lineRow['verrouiller'] === 1)
                throw new Exception("Impossible de supprimer une ligne verrouillée.",403);

            $chkBudget=$bdBASI->prepare("SELECT id FROM budget WHERE id=? AND type_budget_id=? AND statut!='Supprimer'");
            $chkBudget->execute([$lineRow['budget_id'],TYPE_BUDGET_FONCTIONNEMENT]);
            if (!$chkBudget->fetch()) throw new Exception("Accès non autorisé.",403);

            $bdBASI->beginTransaction();
            $bdBASI->prepare("UPDATE ligneBudget SET statut='Inactif',idStatut=2 WHERE id=?")->execute([$lineId]);
            insertHistoriqueLigneBudgetFonct($bdBASI, $lineRow, 'Inactif', 2, 'Suppression');
            $bdBASI->prepare("UPDATE budget SET statut='Sauvegarder', idStatut=3 WHERE id=?")->execute([$lineRow['budget_id']]);
            $bdBASI->commit();
            echo json_encode(['success'=>true,'message'=>'Ligne supprimée avec succès.']);
        } catch (PDOException $e) { if($bdBASI->inTransaction())$bdBASI->rollBack(); error_log("Case 23 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]); }
        catch (Exception    $e) { if($bdBASI->inTransaction())$bdBASI->rollBack(); error_log("Case 23: ".$e->getMessage()); http_response_code($e->getCode()?:400); echo json_encode(["success"=>false,"message"=>$e->getMessage()]); }
        exit;

// ─── CASE 24 : POST — services (toutes directions, fonctionnement = transversal) ──
// Body JSON : {}
    case 24:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $stmt=$bdBASI->prepare("SELECT s.id, s.nom_services, s.id_direction, d.nom_direction FROM services s LEFT JOIN direction d ON d.id=s.id_direction ORDER BY d.nom_direction ASC, s.nom_services ASC");
            $stmt->execute();
            echo json_encode(['success'=>true,'services'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { error_log("Case 24 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 24: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["success"=>false,"message"=>$e->getMessage()]); }
        exit;

// ─── CASE 25 : POST — valider un budget fonctionnement ──────────────────────
// Body JSON : { "budgetId": "<token>" }
    case 25:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data     = getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("Champ requis manquant : budgetId.");
            $budgetId = (int)$basiController->tokendecrypt($data['budgetId']);
            if ($budgetId <= 0) throw new Exception("Token budgetId invalide.",400);

            $get = $bdBASI->prepare("SELECT * FROM budget WHERE id=? AND type_budget_id=? AND statut!='Supprimer'");
            $get->execute([$budgetId, TYPE_BUDGET_FONCTIONNEMENT]);
            $budget = $get->fetch(PDO::FETCH_ASSOC);
            if (!$budget) throw new Exception("Budget non trouvé.",404);

            if (!in_array($budget['statut'], ['En cours','Sauvegarder','Réajuster','Rejeter'], true))
                throw new Exception("Impossible de valider un budget au statut '{$budget['statut']}'. Statut requis : En cours, Sauvegarder ou Réajuster.");

            $lns = $bdBASI->prepare("SELECT COUNT(*) FROM ligneBudget WHERE budget_id=? AND statut='Actif'");
            $lns->execute([$budgetId]);
            if ($lns->fetchColumn() == 0)
                throw new Exception("Impossible de valider un budget sans ligne budgétaire active.");

            $bdBASI->beginTransaction();
            $bdBASI->prepare("UPDATE budget SET statut='Valider', idStatut=6 WHERE id=?")->execute([$budgetId]);

            insertHistoriqueBudget($bdBASI, $budgetId, [
                'annee'          => $budget['annee'],
                'matricule'      => $budget['matricule'],
                'direction_id'   => null,
                'type_budget_id' => TYPE_BUDGET_FONCTIONNEMENT,
                'date_creation'  => $budget['date_creation'],
                'statut'         => 'Valider',
                'idStatut'       => 6,
                'plafond'        => $budget['plafond'],
            ], (int)$sessionUserId, 'Validation du budget');

            $bdBASI->commit();
            echo json_encode(['success'=>true,'message'=>"Budget validé avec succès. Aucune modification n'est plus possible."]);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 25 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 25: ".$e->getMessage()); http_response_code($e->getCode()?:400); echo json_encode(["success"=>false,"message"=>$e->getMessage()]);
        }
        exit;

// ─── CASE 26 : POST — détails d'une ligne par id (fonctionnement) ────────────
// Body JSON : { "lineId": 5 }
    case 26:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            if (empty($data['lineId'])) throw new Exception("Champ requis manquant : lineId.");
            $lineId=(int)$data['lineId'];
            $stmt=$bdBASI->prepare("
                SELECT lb.*,
                       lb.designation, lb.description, lb.periode_d_utilisation,
                       sc.id AS sous_categorie_id, c.id AS categorie_id,
                       c.nom_categorie, sc.nom_sous_categorie
                FROM ligneBudget lb
                LEFT JOIN product       p  ON lb.id_produit        = p.idP
                LEFT JOIN souscategorie sc ON p.id_Sous_categorie  = sc.id
                LEFT JOIN categorie     c  ON sc.categorie_id      = c.id
                WHERE lb.id=:line_id AND lb.statut!='Inactif'
            ");
            $stmt->bindValue(':line_id',$lineId,PDO::PARAM_INT);
            $stmt->execute();
            $line=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$line) { http_response_code(404); echo json_encode(['status'=>'error','message'=>'Ligne non trouvée.']); exit; }
            echo json_encode(['status'=>'success','data'=>$line]);
        } catch (PDOException $e) { error_log("Case 26 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 26: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 27 : POST — liste des unités ───────────────────────────────────────
// Body JSON : {}
    case 27:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $stmt=$bdBASI->prepare("SELECT id, unite AS nom FROM listeUnites ORDER BY unite ASC");
            $stmt->execute();
            echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { error_log("Case 27 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 27: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["success"=>false,"message"=>$e->getMessage()]); }
        exit;

// ─── CASE 28 : POST — détails d'un budget par token (fonctionnement) ─────────
// Utilisé par la page lignes (budgetId transmis en token chiffré dans l'URL,
// pas en id clair) — équivalent du case 18 de chef_service_basi_controller,
// sans filtre direction (direction_id est NULL pour le fonctionnement).
// Body JSON : { "budgetId": "<token>" }
    case 28:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("Champ requis manquant : budgetId.");
            $id=$basiController->tokendecrypt($data['budgetId']);
            $stmt=$bdBASI->prepare("
                SELECT b.*, tb.nom AS type_budget_nom, tb.id AS type_budget_id
                FROM budget b
                JOIN typeBudget tb ON b.type_budget_id=tb.id
                WHERE b.id=:id AND b.type_budget_id=:type_budget_id AND b.statut!='Supprimer'
                LIMIT 1
            ");
            $stmt->bindValue(':id',            $id,                        PDO::PARAM_INT);
            $stmt->bindValue(':type_budget_id',TYPE_BUDGET_FONCTIONNEMENT, PDO::PARAM_INT);
            $stmt->execute();
            $budget=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$budget) { http_response_code(404); echo json_encode(['status'=>'error','message'=>'Budget non trouvé.']); exit; }
            echo json_encode(['status'=>'success','donnees'=>$budget]);
        } catch (PDOException $e) { error_log("Case 28 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 28: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 29 : POST — liste des produits par sous-catégorie (pour formulaire ligne) ──
// Body JSON : { "sous_categorie_id": N }
// Filtre : id_Sous_categorie = N, id_type_product = TYPE_BUDGET_FONCTIONNEMENT, id_statut = 1
    case 29:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data = getJsonBody();
            $sousCategorieId = isset($data['sous_categorie_id']) ? (int)$data['sous_categorie_id'] : null;
            if (!$sousCategorieId) throw new Exception("Champ requis manquant : sous_categorie_id.");

            $stmt = $bdBASI->prepare("
                SELECT idP, nomproduit, code_produit, id_Sous_categorie
                FROM product
                WHERE id_Sous_categorie=? AND id_type_product=? AND id_statut=1
                ORDER BY nomproduit ASC
            ");
            $stmt->execute([$sousCategorieId, TYPE_BUDGET_FONCTIONNEMENT]);
            echo json_encode(['status'=>'success','data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { error_log("Case 29 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 29: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 33 : POST — liste des demandes de la direction ──────────────────────
    case 33:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);

            $stmt = $bdBASI->prepare("
                SELECT da.idD                               AS id,
                       da.idUtilisateur,
                       da.date_creation,
                       da.budget_id,
                       COALESCE(da.etat_demande,'En création') AS statut,
                       b.annee                               AS budget_annee,
                       tb.nom                                AS type_budget_nom,
                       da.type_demande,
                       da.idTypeDemande,
                       (SELECT COUNT(*) FROM demandes_ligne dal
                        WHERE dal.idD = da.idD AND dal.statut != 'supprimer') AS nb_lignes
                FROM demandes da
                LEFT JOIN budget     b  ON da.budget_id  = b.id
                LEFT JOIN typeBudget tb ON b.type_budget_id = tb.id
                WHERE b.type_budget_id = ? AND COALESCE(da.etat_demande,'En création') != 'Supprimée'
                ORDER BY da.date_creation DESC
            ");
            $stmt->execute([TYPE_BUDGET_FONCTIONNEMENT]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Ajouter token chiffré sur chaque demande
            foreach ($rows as &$r) {
                $r['tmp'] = $basiController->tokenencrypt($r['id']);
            }
            unset($r);
            echo json_encode(['success'=>true,'data'=>$rows]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ─── CASE 34 : POST — budgets investissement validés (Accepter) de la direction
    case 34:
        // Budgets investissement validés (Accepter) de la direction
        // Visibles : année en cours ET année suivante (si elle existe)
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $annee      = (int)date('Y');
            $annee_suiv = $annee + 1;

            $stmt = $bdBASI->prepare("
                SELECT b.id, b.annee, b.plafond, b.statut,
                       tb.nom AS type_budget_nom,
                       b.type_budget_id
                FROM budget b
                JOIN typeBudget tb ON b.type_budget_id = tb.id
                WHERE b.statut         = 'Accepter'
                  AND b.annee          IN (?, ?)
                  AND b.type_budget_id = ?
                ORDER BY b.annee ASC, tb.nom ASC
            ");
            $stmt->execute([ $annee, $annee_suiv, TYPE_BUDGET_FONCTIONNEMENT]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['tmp'] = $basiController->tokenencrypt($r['id']);
            }
            unset($r);
            echo json_encode(['success'=>true,'data'=>$rows]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;


    case 35:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("budgetId requis.");

            $budgetId  = (int)$basiController->tokendecrypt($data['budgetId']);
            if (!$budgetId) throw new Exception("Token invalide.");
            $categorieId = isset($data['id_Sous_categorie']) && $data['id_Sous_categorie']!=='' ? (int)$data['id_Sous_categorie'] : null;

            // Vérifier budget : appartient à la direction + statut Accepter
            $chk = $bdBASI->prepare("SELECT id FROM budget WHERE id=?  AND statut='Accepter'");
            $chk->execute([$budgetId]);
            if (!$chk->fetch()) throw new Exception("Budget non trouvé ou accès non autorisé.", 403);

            $sql = "
                SELECT lb.id,
                       lb.id_type_budget_investissement,
                       tbi.nom        AS nature_nom,
                       tbi.categorie  AS nature_categorie,
                       sc.nom_sous_categorie,
                       c.nom_categorie,
                       s.nom_services AS service_nom,
                       lb.designation,
                       lb.description,
                       lb.quantite,
                       lb.prix_unitaire,
                       lb.montant_total,
                       u.unite        AS unite_nom,
                       lb.periode_d_utilisation,
                       lb.verrouiller,
                       -- Quantité déjà demandée (toutes demandes actives)
                       COALESCE((
                           SELECT SUM(dal.quantite)
                           FROM demandes_ligne dal
                           WHERE dal.idLB = lb.id AND dal.statut = 'crée'
                       ), 0) AS quantite_demandee,
                       -- Montant déjà demandé (lignes Autre / paiement)
                       COALESCE((
                           SELECT SUM(dal.montant_total)
                           FROM demandes_ligne dal
                           WHERE dal.idLB = lb.id AND dal.statut = 'crée'
                       ), 0) AS montant_demande,
                       -- nature de la ligne : id_type=1 → achat | autre → paiement
                       CASE WHEN lb.id_type_budget_investissement = 1
                            THEN 'demande_achat'
                            ELSE 'demande_paiement'
                       END   AS type_demande,
                       lb.id_type_budget_investissement AS idTypeLigne
                FROM ligneBudget lb
                LEFT JOIN type_budget_investissement tbi ON lb.id_type_budget_investissement = tbi.id
                LEFT JOIN product     p   ON p.idP      = lb.id_produit
                LEFT JOIN souscategorie sc  ON sc.id  = p.id_Sous_categorie
                                LEFT JOIN categorie     c   ON c.id       = sc.categorie_id
                LEFT JOIN services     s   ON lb.service_id        = s.id
                LEFT JOIN listeUnites  u   ON lb.unite_id          = u.id
                WHERE lb.budget_id = ? AND lb.statut = 'Actif'
            ";
            $params = [$budgetId];
            if ($categorieId) { $sql .= " AND p.id_Sous_categorie = ?"; $params[] = $categorieId; }
            $sql .= " ORDER BY tbi.categorie DESC, c.nom_categorie, lb.designation ASC";

            $stmt = $bdBASI->prepare($sql);
            $stmt->execute($params);
            $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcul quantité restante (Produit) et montant restant (Autre)
            foreach ($lignes as &$l) {
                $l['quantite_restante'] = max(0, (float)$l['quantite'] - (float)$l['quantite_demandee']);
                $l['montant_restant']   = max(0, (float)$l['montant_total'] - (float)$l['montant_demande']);
            }
            unset($l);

            echo json_encode(['success'=>true,'data'=>$lignes,'lineCount'=>count($lignes)]);
        } catch (PDOException $e) { echo $e; die;http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { echo $e; die;http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;


    case 36:
        // Créer une demande — champs : budgetId (token) + type_demande
        // Budget autorisé : année en cours OU année suivante
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['budgetId']))    throw new Exception("budgetId requis.");
            if (empty($data['type_demande'])) throw new Exception("type_demande requis.");

            // Accepter soit la valeur texte soit l'entier (1=achat, 2=paiement)
            $rawType      = $data['type_demande'];


            $idTypeDemande = null;
            if ($rawType === 'demande_achat'    || (int)$rawType === 1) { $typeDemande = 'demande_achat';    $idTypeDemande = 1; }
            elseif ($rawType === 'demande_paiement' || (int)$rawType === 2) { $typeDemande = 'demande_paiement'; $idTypeDemande = 2; }
            else throw new Exception("type_demande invalide. Valeurs : demandes (1) ou demande_paiement (2).");

            $budgetId = (int)$basiController->tokendecrypt($data['budgetId']);
            if (!$budgetId) throw new Exception("Token invalide.");

            $annee      = (int)date('Y');
            $annee_suiv = $annee + 1;

            // Budget : direction OK + statut Accepter + année courante OU suivante
            $chk = $bdBASI->prepare("
                SELECT * FROM budget
                WHERE id          = ?
                  AND statut       = 'Accepter'
                  AND annee        IN (?, ?)
                  AND type_budget_id = ?
            ");
            $chk->execute([$budgetId, $annee, $annee_suiv, TYPE_BUDGET_FONCTIONNEMENT]);
            $budget = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$budget) throw new Exception("Budget non trouvé, non accepté ou hors années autorisées (en cours / suivante).");

            // Vérifier qu'il reste des lignes non épuisées
            $lns = $bdBASI->prepare("
                SELECT COUNT(*) FROM ligneBudget lb
                WHERE lb.budget_id = ? AND lb.statut = 'Actif'
                  AND (
                      lb.id_type_budget_investissement != 1
                      OR lb.quantite > COALESCE(
                          (SELECT SUM(dal.quantite) FROM demandes_ligne dal
                           WHERE dal.idLB = lb.id AND dal.statut = 'crée'), 0
                      )
                  )
            ");
            $lns->execute([$budgetId]);
            if ((int)$lns->fetchColumn() === 0)
                throw new Exception("Toutes les lignes de ce budget sont épuisées. Impossible de créer une nouvelle demande.");

            $bdBASI->beginTransaction();
            $stmt = $bdBASI->prepare("
                INSERT INTO demandes
                    (idUtilisateur, date_creation, budget_id,
                     etat_demande, type_demande, idTypeDemande)
                VALUES (?, ?, ?, 'En création', ?, ?)
            ");
            $stmt->execute([
                $sessionUserId,
                date('Y-m-d H:i:s'),
                $budgetId,
                $typeDemande,
                $idTypeDemande,
            ]);
            $newId = (int)$bdBASI->lastInsertId();

            $bdBASI->prepare("
                INSERT INTO historique_demandes (id_demande, action, date_action)
                VALUES (?, 'insertion', ?)
            ")->execute([$newId,date('Y-m-d H:i:s')]);

            $bdBASI->commit();
            echo json_encode([
                'success'      => true,
                'id'           => $newId,
                'tmp'          => $basiController->tokenencrypt($newId),
                'typeToken'    => $basiController->tokenencrypt((string)$idTypeDemande), // token chiffré du type
                'type_demande'  => $typeDemande,
                'idTypeDemande' => $idTypeDemande,
                'budget_annee' => $budget['annee'],
                'message'      => 'Demande créée avec succès.',
            ]);
        } catch (PDOException $e) {

            echo $e;
            die;

            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        } catch (Exception $e) {

            echo $e;
            die;

            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;
    case 37:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['demandeId'])) throw new Exception("demandeId requis.");
            if (empty($data['lignes']) || !is_array($data['lignes'])) throw new Exception("lignes[] requis.");
            $demandeId = (int)$data['demandeId'];

            // Vérifier la demande appartient à la direction + statut modifiable
            $chkD = $bdBASI->prepare("
                SELECT da.idD FROM demandes da
                JOIN budget b ON da.budget_id = b.id
                WHERE da.idD = ?
                  AND COALESCE(da.etat_demande,'En création') = 'En création'
            ");
            $chkD->execute([$demandeId]);
            if (!$chkD->fetch()) throw new Exception("Demande non trouvée ou non modifiable.", 403);

            $bdBASI->beginTransaction();

            // Date Dakar calculée une seule fois côté PHP
            $nowDakar = (new DateTime('now', new DateTimeZone('Africa/Dakar')))->format('Y-m-d H:i:s');

            $insertLigne = $bdBASI->prepare("
                INSERT INTO demandes_ligne
                    (idD, idLB, quantite, montant_total, unite_id, dateadd, idUtilisateur, date_creation, etat_demande, statut)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'En création', 'crée')
            ");
            $insertHisto = $bdBASI->prepare("
                INSERT INTO historique_demandes_ligne
                    (id_demande_ligne, action, date_action, ancienne_qte, nouvelle_qte,ancien_montant,nouveaux_montant)
                VALUES (?, 'insertion', ?, ?, ?,?,?)
            ");

            // Récupérer unite + budget de la ligne + son type
            $getUnite = $bdBASI->prepare("
                SELECT lb.unite_id, u.unite, lb.id_type_budget_investissement, lb.montant_total
                FROM ligneBudget lb
                LEFT JOIN listeUnites u ON lb.unite_id = u.id
                WHERE lb.id = ?
            ");

            // Montant déjà demandé sur une ligne (toutes demandes actives confondues)
            $getMontantDeja = $bdBASI->prepare("
                SELECT COALESCE(SUM(montant_total),0) FROM demandes_ligne
                WHERE idLB = ? AND statut = 'crée'
            ");

            // Requête pour vérifier si la ligne existe déjà dans cette demande
            $checkExist = $bdBASI->prepare("
                SELECT idDL, quantite, montant_total
                FROM demandes_ligne
                WHERE idD = ? AND idLB = ? AND statut = 'crée'
                LIMIT 1
            ");

            $nbInseres = 0; $nbMaj = 0;
            foreach ($data['lignes'] as $l) {
                $lineId = (int)($l['lineId'] ?? 0);
                if (!$lineId) continue;

                // closeCursor() obligatoire avant re-execute sur PDO MySQL
                $getUnite->execute([$lineId]);
                $lb = $getUnite->fetch(PDO::FETCH_ASSOC);
                $getUnite->closeCursor();
                if (!$lb) continue;

                $isAchat = (int)$lb['id_type_budget_investissement'] === 1;
                $uniteId = $lb['unite_id'] ? (int)$lb['unite_id'] : null;

                // ── Déterminer quantité (achat) OU montant (paiement) ─────────────
                // Une ligne Produit (achat) n'a pas de montant_total propre : NULL.
                // Une ligne Autre (paiement) n'a pas de quantité : NULL.
                $qte           = null;
                $montantCustom = null;

                if ($isAchat) {
                    $qte = max(1, (int)($l['quantite'] ?? 1));
                } else {
                    $montantCustom = isset($l['montant']) && is_numeric($l['montant'])
                        ? (float)$l['montant'] : null;

                    // ── Contrôle serveur du montant restant (lignes Autre / paiement) ──
                    if ($montantCustom !== null) {
                        $getMontantDeja->execute([$lineId]);
                        $dejaMontant = (float)$getMontantDeja->fetchColumn();
                        $getMontantDeja->closeCursor();

                        $montantBudget  = (float)$lb['montant_total'];
                        $montantRestant = $montantBudget - $dejaMontant;

                        if ($montantCustom > $montantRestant) {
                            throw new Exception(
                                "Montant demandé (".number_format($montantCustom,0,',',' ')." FCFA) supérieur au montant restant (".
                                number_format($montantRestant,0,',',' ')." FCFA) pour la ligne budgétaire #$lineId."
                            );
                        }
                    }
                }

                // UPSERT : UPDATE si la ligne existe déjà, INSERT sinon
                $checkExist->execute([$demandeId, $lineId]);
                $existing = $checkExist->fetch(PDO::FETCH_ASSOC);
                $checkExist->closeCursor();

                if ($existing) {
                    // ── Même méthode symétrique pour qté (achat) et montant (paiement) :
                    //    on part des VRAIES valeurs existantes en base, pas d'un proxy comme idDL.
                    $ancienne_qte   = $existing['quantite'];
                    $ancien_montant = $existing['montant_total'];

                    if ($isAchat) {
                        $nouvelle_qte    = (int)$existing['quantite'] + $qte;
                        $nouveau_montant = $ancien_montant; // inchangé pour une ligne achat

                        $bdBASI->prepare("
                            UPDATE demandes_ligne
                            SET quantite = ?
                            WHERE idDL = ?
                        ")->execute([$nouvelle_qte, (int)$existing['idDL']]);
                    } else {
                        $nouvelle_qte    = $ancienne_qte; // inchangé pour une ligne paiement
                        $nouveau_montant = (float)($existing['montant_total'] ?? 0) + ($montantCustom ?? 0);

                        $bdBASI->prepare("
                            UPDATE demandes_ligne
                            SET montant_total = ?
                            WHERE idDL = ?
                        ")->execute([$nouveau_montant, (int)$existing['idDL']]);
                    }

                    $insertHisto->execute([
                        (int)$existing['idDL'], $nowDakar,
                        $ancienne_qte, $nouvelle_qte,
                        $ancien_montant, $nouveau_montant,
                    ]);
                    $nbMaj++;
                } else {
                    // INSERT nouvelle ligne — quantite NULL si paiement, montant_total NULL si achat
                    $insertLigne->execute([
                        $demandeId, $lineId, $qte, $montantCustom, $uniteId, $nowDakar, $sessionUserId, $nowDakar,
                    ]);
                    $newLigneId = (int)$bdBASI->lastInsertId();

                    $insertHisto->execute([
                        $newLigneId, $nowDakar,
                        NULL, $qte,
                        NULL, $montantCustom,
                    ]);
                    $nbInseres++;
                }
            }

            // Mettre à jour histo demande
            $bdBASI->prepare("
                INSERT INTO historique_demandes (id_demande, action, date_action)
                VALUES (?, 'modification', ?)
            ")->execute([$demandeId, $nowDakar]);

            $bdBASI->commit();
            echo json_encode([
                'success'   => true,
                'nbInseres' => $nbInseres,
                'message'   => "{$nbInseres} ligne(s) ajoutée(s) avec succès et {$nbMaj} ligne(s) à jour.",
            ]);
        } catch (PDOException $e) {

            echo $e;
            die;
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        } catch (Exception $e) {

            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;

// ─── CASE 38 : POST — lignes d'une demande existante ──────────────────────────
// Body JSON : { "demandeId": N }
    case 38:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['demandeId'])) throw new Exception("demandeId requis.");
            $demandeId = (int)$data['demandeId'];

            $stmt = $bdBASI->prepare("
               SELECT dal.idDL                 AS id,
                       dal.idD                  AS demande_id,
                       dal.idLB                  AS line_id,
                       dal.quantite,
                       dal.montant_total         AS montant_demande,
                       lu.unite,
                       dal.etat_demande   AS statut_ligne,
                       lb.designation,
                       lb.description,
                       lb.prix_unitaire,
                       lb.quantite               AS budget_quantite,
                       lb.montant_total          AS montant_budget,
                       lb.periode_d_utilisation,
                       lb.id_type_budget_investissement,
                       tbi.nom                   AS nature_nom,
                       tbi.categorie             AS nature_categorie,
                      sc.nom_sous_categorie,
                       c.nom_categorie,
                       s.nom_services            AS service_nom,
                       CASE WHEN lb.id_type_budget_investissement = 1
                            THEN 'demande_achat'
                            ELSE 'demande_paiement'
                       END                       AS type_demande,
                       lb.id_type_budget_investissement AS idTypeLigne,
                       -- Quantité utilisée par les AUTRES lignes de demande sur cette même ligne budget
                       COALESCE((
                           SELECT SUM(dal2.quantite) FROM demandes_ligne dal2
                           WHERE dal2.idLB = dal.idLB AND dal2.statut = 'crée' AND dal2.idDL != dal.idDL
                       ), 0) AS quantite_utilisee_ailleurs,
                       -- Montant utilisé par les AUTRES lignes de demande sur cette même ligne budget
                       COALESCE((
                           SELECT SUM(dal2.montant_total) FROM demandes_ligne dal2
                           WHERE dal2.idLB = dal.idLB AND dal2.statut = 'crée' AND dal2.idDL != dal.idDL
                       ), 0) AS montant_utilise_ailleurs
                FROM demandes_ligne dal
                JOIN ligneBudget lb ON dal.idLB = lb.id
                LEFT JOIN type_budget_investissement tbi ON lb.id_type_budget_investissement = tbi.id
     LEFT JOIN product     p   ON p.idP      = lb.id_produit
                LEFT JOIN souscategorie sc  ON sc.id  = p.id_Sous_categorie
                                LEFT JOIN categorie     c   ON c.id       = sc.categorie_id
                LEFT JOIN services     s  ON lb.service_id       = s.id
                             LEFT JOIN listeUnites     lu  ON dal.unite_id       = lu.id

                WHERE dal.idD = ? AND dal.statut = 'crée'
                ORDER BY dal.idDL ASC
            ");
            $stmt->execute([$demandeId]);
            $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Restant disponible pour la modification de CETTE ligne (= budget - ce qui est pris par les autres)
            foreach ($lignes as &$l) {
                $l['quantite_restante_modif'] = max(0, (float)$l['budget_quantite'] - (float)$l['quantite_utilisee_ailleurs']);
                $l['montant_restant_modif']   = max(0, (float)$l['montant_budget']   - (float)$l['montant_utilise_ailleurs']);
            }
            unset($l);

            echo json_encode(['success'=>true,'data'=>$lignes,'count'=>count($lignes)]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;



    case 39:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['demandeId'])) throw new Exception("demandeId requis.");
            $demandeId = (int)$data['demandeId'];

            $chkD = $bdBASI->prepare("
                SELECT da.idD, COALESCE(da.etat_demande,'En création') AS statut
                FROM demandes da
                JOIN budget b ON da.budget_id = b.id
                WHERE da.idD = ? 
            ");
            $chkD->execute([$demandeId]);
            $row = $chkD->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception("Demande non trouvée.", 404);
            if (!in_array($row['statut'], ['En création'], true))
                throw new Exception("Impossible de supprimer une demande au statut '{$row['statut']}'.");

            // Vérifier aucune ligne active
            $lc = $bdBASI->prepare("SELECT COUNT(*) FROM demandes_ligne WHERE idD=? AND statut='crée'");
            $lc->execute([$demandeId]);
            if ((int)$lc->fetchColumn() > 0)
                throw new Exception("Impossible de supprimer une demande avec des lignes actives. Supprimez d'abord les lignes.");

            $bdBASI->beginTransaction();
            $bdBASI->prepare("UPDATE demande SET etat_demande='Supprimée' WHERE idD=?")
                ->execute([$demandeId]);
            $bdBASI->prepare("INSERT INTO historique_demandes (id_demande, action, date_action) VALUES (?, 'suppression', ?)")
                ->execute([$demandeId,date('Y-m-d H:i:s')]);
            $bdBASI->commit();
            echo json_encode(['success'=>true,'message'=>'Demande supprimée avec succès.']);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;

// ─── CASE 40 : POST — services de la direction (pour filtre) ──────────────────
    case 40:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $stmt = $bdBASI->prepare("SELECT categorie.nom_categorie,souscategorie.id,souscategorie.nom_sous_categorie FROM categorie,souscategorie WHERE souscategorie.categorie_id=categorie.id ORDER BY nom_categorie,nom_sous_categorie ASC");
            $stmt->execute();
            echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { echo $e; die;http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) {echo $e; die; http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

    case 41:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['token'])) throw new Exception("Token requis.");

            $id = (int)$basiController->tokendecrypt($data['token']);
            if (!$id) throw new Exception("Token invalide.");

            $stmt = $bdBASI->prepare("
                SELECT da.idD                                AS id,
                       da.idUtilisateur,
                       da.date_creation,
                       da.budget_id,
                       COALESCE(da.etat_demande,'En création') AS statut,
                       b.annee                                AS budget_annee,
                       tb.nom                                 AS type_budget_nom,
                       da.type_demande,
                       da.idTypeDemande,
                       (SELECT COUNT(*) FROM demandes_ligne dal
                        WHERE dal.idD = da.idD AND dal.statut != 'supprimer') AS nb_lignes
                FROM demandes da
                LEFT JOIN budget     b  ON da.budget_id     = b.id
                LEFT JOIN typeBudget tb ON b.type_budget_id = tb.id
                WHERE da.idD = ? 
                  AND COALESCE(da.etat_demande,'En création') NOT IN ('Supprimée')
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception("Demande non trouvée ou accès non autorisé.", 404);

            // Ajouter le token chiffré du budget pour case 35
            $row['budget_tmp'] = $basiController->tokenencrypt($row['budget_id']);
            $row['tmp']        = $basiController->tokenencrypt($row['id']);

            echo json_encode(['success'=>true,'data'=>$row]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;



    // ═══════════════════════════════════════════════════════════════════════════════
// CASE 49 : Modifier la quantité (ou montant) d'une ligne de demande
// Body JSON : { "ligneId": N, "quantite": N } ou { "ligneId": N, "montant": N }
// Condition : demande au statut 'En création'
// ═══════════════════════════════════════════════════════════════════════════════
    case 49:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['ligneId'])) throw new Exception("ligneId requis.");
            $ligneId = (int)$data['ligneId'];

            // Récupérer la ligne + statut de la demande + infos du budget (quantite/montant_total)
            $stmt = $bdBASI->prepare("
                SELECT dal.idDL, dal.idLB, dal.quantite, dal.montant_total AS montant_demande, dal.idD,
                       COALESCE(da.etat_demande,'En création') AS statut_demande,
                       lb.id_type_budget_investissement,
                       lb.quantite       AS budget_quantite,
                       lb.montant_total  AS budget_montant
                FROM demandes_ligne dal
                JOIN demandes da ON dal.idD = da.idD
                JOIN ligneBudget lb    ON dal.idLB = lb.id
                JOIN budget b          ON da.budget_id = b.id
                WHERE dal.idDL = ? AND dal.statut = 'crée' 
                LIMIT 1
            ");
            $stmt->execute([$ligneId]);
            $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ligne) throw new Exception("Ligne non trouvée ou accès non autorisé.", 404);
            if ($ligne['statut_demande'] !== 'En création')
                throw new Exception("Impossible de modifier une ligne d'une demande au statut '{$ligne['statut_demande']}'.");

            $isAchat = (int)$ligne['id_type_budget_investissement'] === 1;
            $nowDakar = (new DateTime('now', new DateTimeZone('Africa/Dakar')))->format('Y-m-d H:i:s');

            $bdBASI->beginTransaction();

            if ($isAchat) {
                // Modifier la quantité (Produit)
                if (!isset($data['quantite']) || $data['quantite'] === '')
                    throw new Exception("quantite requis pour une ligne Produit.");
                $newQte    = max(1, (int)$data['quantite']);
                $ancienQte = (int)$ligne['quantite'];

                // ── Contrôle serveur : quantité restante (hors ligne courante) ──
                $sumAutres = $bdBASI->prepare("
                    SELECT COALESCE(SUM(quantite),0) FROM demandes_ligne
                    WHERE idLB = ? AND statut = 'crée' AND idDL != ?
                ");
                $sumAutres->execute([$ligne['idLB'], $ligneId]);
                $dejaAutresQte = (int)$sumAutres->fetchColumn();

                $quantiteRestante = (int)$ligne['budget_quantite'] - $dejaAutresQte;
                if ($newQte > $quantiteRestante) {
                    throw new Exception(
                        "Quantité demandée ($newQte) supérieure à la quantité restante ($quantiteRestante) pour cette ligne budgétaire."
                    );
                }

                $bdBASI->prepare("UPDATE demandes_ligne SET quantite = ? WHERE idDL = ?")
                    ->execute([$newQte, $ligneId]);

                $bdBASI->prepare("
                    INSERT INTO historique_demandes_ligne
                        (id_demande_ligne, action, date_action, ancienne_qte, nouvelle_qte)
                    VALUES (?, 'modification', ?, ?, ?)
                ")->execute([$ligneId, $nowDakar, $ancienQte, $newQte]);
            } else {
                // Modifier le montant_total (Autre/Prestation)
                if (!isset($data['montant']) || !is_numeric($data['montant']))
                    throw new Exception("montant requis pour une ligne Autre.");
                $newMontant    = (float)$data['montant'];
                $ancienMontant = (float)($ligne['montant_demande'] ?? 0);
                if ($newMontant <= 0) throw new Exception("Le montant doit être > 0.");

                // ── Contrôle serveur : montant restant (hors ligne courante) ──
                $sumAutres = $bdBASI->prepare("
                    SELECT COALESCE(SUM(montant_total),0) FROM demandes_ligne
                    WHERE idLB = ? AND statut = 'crée' AND idDL != ?
                ");
                $sumAutres->execute([$ligne['idLB'], $ligneId]);
                $dejaAutresMontant = (float)$sumAutres->fetchColumn();

                $montantRestant = (float)$ligne['budget_montant'] - $dejaAutresMontant;
                if ($newMontant > $montantRestant) {
                    throw new Exception(
                        "Montant demandé (".number_format($newMontant,0,',',' ')." FCFA) supérieur au montant restant (".
                        number_format($montantRestant,0,',',' ')." FCFA) pour cette ligne budgétaire."
                    );
                }

                $bdBASI->prepare("UPDATE demandes_ligne SET montant_total = ? WHERE idDL = ?")
                    ->execute([$newMontant, $ligneId]);

                $bdBASI->prepare("
                    INSERT INTO historique_demandes_ligne
                        (id_demande_ligne, action, date_action, ancien_montant, nouveaux_montant)
                    VALUES (?, 'modification', ?, ?, ?)
                ")->execute([$ligneId, $nowDakar, $ancienMontant, $newMontant]);
            }

            $bdBASI->commit();
            echo json_encode(['success'=>true,'message'=>'Ligne mise à jour avec succès.']);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;

// ═══════════════════════════════════════════════════════════════════════════════
// CASE 50 : Supprimer (soft delete) une ligne de demande
// Body JSON : { "ligneId": N }
// Condition : demande au statut 'En création'
// ═══════════════════════════════════════════════════════════════════════════════
    case 50:


        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['ligneId'])) throw new Exception("ligneId requis.");
            $ligneId = (int)$data['ligneId'];

            $stmt = $bdBASI->prepare("
                SELECT dal.idDL,
                       COALESCE(da.etat_demande,'En création') AS statut_demande, dal.quantite
                FROM demandes_ligne dal
                JOIN demandes da ON dal.idD = da.idD
                JOIN budget b          ON da.budget_id = b.id
                WHERE dal.idDL = ? AND dal.statut = 'crée'
                LIMIT 1
            ");
            $stmt->execute([$ligneId]);
            $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ligne) throw new Exception("Ligne non trouvée ou accès non autorisé.", 404);
            if ($ligne['statut_demande'] !== 'En création')
                throw new Exception("Impossible de supprimer une ligne d'une demande au statut '{$ligne['statut_demande']}'.");

            $nowDakar = (new DateTime('now', new DateTimeZone('Africa/Dakar')))->format('Y-m-d H:i:s');
            $bdBASI->beginTransaction();



            // Soft delete : statut = 'supprimer'
            $bdBASI->prepare("UPDATE demandes_ligne SET statut = 'supprimer' WHERE idDL = ?")
                ->execute([$ligneId]);

            $bdBASI->prepare("
                INSERT INTO historique_demandes_ligne
                    (id_demande_ligne, action, date_action, ancienne_qte, nouvelle_qte)
                VALUES (?, 'suppression', ?, ?, ?)
            ")->execute([$ligneId, $nowDakar,$ligne['quantite'],NULL]);

            $bdBASI->commit();
            echo json_encode(['success'=>true,'message'=>'Ligne supprimée de la demande.']);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;

// ═══════════════════════════════════════════════════════════════════════════════
// CASE 51 : Soumettre une demande (En création → Soumise)
// Body JSON : { "demandeId": N }
// Condition : statut = 'En création' ET au moins une ligne active
// ═══════════════════════════════════════════════════════════════════════════════
    case 51:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['demandeId'])) throw new Exception("demandeId requis.");
            $demandeId = (int)$data['demandeId'];

            // Vérifier appartenance direction + statut
            $stmt = $bdBASI->prepare("
                SELECT da.idD,
                       COALESCE(da.etat_demande,'En création') AS statut
                FROM demandes da
                JOIN budget b ON da.budget_id = b.id
                WHERE da.idD = ? 
                LIMIT 1
            ");
            $stmt->execute([$demandeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception("Demande non trouvée ou accès non autorisé.", 404);
            if ($row['statut'] !== 'En création')
                throw new Exception("Seule une demande 'En création' peut être soumise. Statut actuel : '{$row['statut']}'.");

            // Vérifier au moins une ligne active
            $lc = $bdBASI->prepare("SELECT COUNT(*) FROM demandes_ligne WHERE idD = ? AND statut = 'crée'");
            $lc->execute([$demandeId]);
            if ((int)$lc->fetchColumn() === 0)
                throw new Exception("Impossible de soumettre une demande sans lignes actives.");

            $nowDakar = (new DateTime('now', new DateTimeZone('Africa/Dakar')))->format('Y-m-d H:i:s');
            $bdBASI->beginTransaction();

            // Passer la demande en "Soumise"
            $bdBASI->prepare("UPDATE demandes SET etat_demande = 'Soumise' WHERE idD = ?")
                ->execute([$demandeId]);

            // Passer toutes les lignes actives en "Soumise"
            $bdBASI->prepare("UPDATE demandes_ligne SET etat_demande = 'Soumise' WHERE idD = ? AND statut = 'crée'")
                ->execute([$demandeId]);

            // Historique
            $bdBASI->prepare("
                INSERT INTO historique_demandes (id_demande, action, date_action)
                VALUES (?, 'soumission', ?)
            ")->execute([$demandeId, $nowDakar]);

            $bdBASI->commit();
            echo json_encode(['success'=>true,'message'=>"Demande #{$demandeId} soumise avec succès."]);
        } catch (PDOException $e) {
            echo $e;
            die;
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        } catch (Exception $e) {
            echo $e;
            die;
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;


    /**
     * case52-voir-suivi-demande.php
     * Bloc à insérer dans compta_basi_controller.php (routage : ajouter
     * `case 52: voirSuiviDemande($bdBASI, $basiController); break;`).
     *
     * ⚠️ Hypothèses de schéma confirmées par l'utilisateur :
     *   - `demandes` (PK idD) : en-tête de la demande.
     *   - `demandes_ligne` (PK idDL) : demandes_ligne.idD → demandes.idD
     *     (lien confirmé). Jointure vers ligneBudget déjà en place ailleurs
     *     dans le projet (via idLB), réutilisée ici telle quelle.
     *   - Le pont vers les commandes (passer_achat_et_paiement) se fait via
     *     passer_achat_et_paiement_ligne.idDL = demandes_ligne.idDL (lien déjà
     *     établi et utilisé ailleurs dans le projet — cf. options 17-25 du
     *     module Liste des opérations).
     *   - `paiement_pap` / `livraison` / `livraison_produit` / `product` /
     *     `historique_passer_achat_et_paiement` : tables déjà établies et
     *     utilisées ailleurs dans le projet, réutilisées SANS modification.
     *   - ⚠️ "Bénéficiaire" (demandé pour la livraison) : aucune colonne dédiée
     *     n'existe dans `livraison_produit` à ma connaissance. Par défaut,
     *     j'utilise le demandeur de la `demandes` (idUtilisateur) comme
     *     bénéficiaire — À CONFIRMER / ajuster si une autre colonne existe
     *     réellement (ex. livraison_produit.beneficiaire ou équivalent).
     *   - Réponses au format `success`/`message`/`data`, cohérent avec le reste
     *     de ce module (ld_post() côté JS lit `d.success`).
     */

    /**
     * Vue complète d'une demande : en-tête, lignes, et pour chaque commande
     * (passer_achat_et_paiement) issue de cette demande — paiements en caisse,
     * livraisons (avec détail produit/quantité/bénéficiaire), et historique.
     *
     * Paramètre attendu : demandeId (entier, id de `demandes`)
     */



        /**
         * case52-voir-suivi-demande.php
         * Code du `case 52` à coller DIRECTEMENT dans le switch de routage de
         * compta_basi_controller.php — pas de fonction séparée, tout est inline.
         *
         * ⚠️ Hypothèses de schéma confirmées par l'utilisateur :
         *   - `demandes` (PK idD) : en-tête de la demande.
         *   - `demandes_ligne` (PK idDL) : demandes_ligne.idD → demandes.idD
         *     (lien confirmé). Jointure vers ligneBudget déjà en place ailleurs
         *     dans le projet (via idLB) — reconstituée ici par déduction du JS ;
         *     à remplacer par la requête réelle de votre case 38 si elle diffère.
         *   - Le pont vers les commandes (passer_achat_et_paiement) se fait via
         *     passer_achat_et_paiement_ligne.idDL = demandes_ligne.idDL (lien déjà
         *     établi et utilisé ailleurs dans le projet).
         *   - `paiement_pap` / `livraison` / `livraison_produit` / `product` /
         *     `historique_passer_achat_et_paiement` : tables déjà établies et
         *     utilisées ailleurs dans le projet, réutilisées SANS modification.
         *   - ⚠️ "Bénéficiaire" (livraison) : pas de colonne dédiée connue —
         *     repli sur le demandeur de `demandes` (idUtilisateur). À corriger si
         *     une colonne dédiée existe réellement.
         *   - $_POST['demandeId'] : à remplacer par votre propre helper de lecture
         *     d'input si le contrôleur en a un (inputValueCompta(), etc.).
         *
         * ⚠️ Ce fichier est un EXTRAIT (bloc `case 52: ... break;`) destiné à être
         * collé à l'intérieur d'un `switch` existant — il n'est pas exécutable seul.
         *
         * ⚠️ IMPORTANT — lecture du corps JSON : ld_post() (JS) envoie toujours son
         * corps en JSON brut (Content-Type: application/json), jamais en
         * application/x-www-form-urlencoded. Or vos options 34/36/38/39
         * fonctionnent déjà avec ld_post() : votre contrôleur possède donc
         * TRÈS PROBABLEMENT DÉJÀ un helper dédié (type inputValue()/inputValueCompta())
         * qui lit php://input. Si c'est le cas, UTILISEZ CE HELPER EXISTANT au lieu
         * du file_get_contents('php://input') ci-dessous, pour rester cohérent et
         * éviter toute double lecture du flux. Le code ci-dessous n'est qu'un repli
         * autonome, à adapter.
         */

/**
 * case52-voir-suivi-demande.php
 * Code du `case 52` à coller DIRECTEMENT dans le switch de routage de
 * compta_basi_controller.php — pas de fonction séparée, tout est inline.
 *
 * ⚠️ Hypothèses de schéma confirmées par l'utilisateur :
 *   - `demandes` (PK idD) : en-tête de la demande. Colonne de statut réelle
 *     confirmée : `etat_demande` (aliasée `AS statut` dans la requête, pour
 *     rester compatible avec le JS existant qui lit `demande.statut`).
 *   - `demandes_ligne` (PK idDL) : demandes_ligne.idD → demandes.idD
 *     (lien confirmé). Jointure vers ligneBudget déjà en place ailleurs
 *     dans le projet (via idLB) — reconstituée ici par déduction du JS ;
 *     à remplacer par la requête réelle de votre case 38 si elle diffère.
 *   - Le pont vers les commandes (passer_achat_et_paiement) se fait via
 *     passer_achat_et_paiement_ligne.idDL = demandes_ligne.idDL (lien déjà
 *     établi et utilisé ailleurs dans le projet).
 *   - `paiement_pap` / `livraison` / `livraison_produit` / `product` /
 *     `historique_passer_achat_et_paiement` : tables déjà établies et
 *     utilisées ailleurs dans le projet, réutilisées SANS modification.
 *   - ⚠️ "Bénéficiaire" (livraison) : pas de colonne dédiée connue —
 *     repli sur le demandeur de `demandes` (idUtilisateur). À corriger si
 *     une colonne dédiée existe réellement.
 *   - $_POST['demandeId'] : à remplacer par votre propre helper de lecture
 *     d'input si le contrôleur en a un (inputValueCompta(), etc.).
 *
 * ⚠️ Ce fichier est un EXTRAIT (bloc `case 52: ... break;`) destiné à être
 * collé à l'intérieur d'un `switch` existant — il n'est pas exécutable seul.
 *
 * ⚠️ IMPORTANT — lecture du corps JSON : ld_post() (JS) envoie toujours son
 * corps en JSON brut (Content-Type: application/json), jamais en
 * application/x-www-form-urlencoded. Or vos options 34/36/38/39
 * fonctionnent déjà avec ld_post() : votre contrôleur possède donc
 * TRÈS PROBABLEMENT DÉJÀ un helper dédié (type inputValue()/inputValueCompta())
 * qui lit php://input. Si c'est le cas, UTILISEZ CE HELPER EXISTANT au lieu
 * du file_get_contents('php://input') ci-dessous, pour rester cohérent et
 * éviter toute double lecture du flux. Le code ci-dessous n'est qu'un repli
 * autonome, à adapter.
 */


    case 52:
        try {
            $bodyJson52 = json_decode(file_get_contents('php://input'), true) ?: [];
            $demandeId = (int)($bodyJson52['demandeId'] ?? $_POST['demandeId'] ?? $_GET['demandeId'] ?? 0);
            if ($demandeId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Identifiant de demande manquant.']);
                break;
            }

            // ── En-tête de la demande ────────────────────────────────────
            $stmtD = $bdBASI->prepare("
                    SELECT d.idD, d.type_demande, d.etat_demande AS statut, d.date_creation, d.idUtilisateur,
                           CONCAT(u.prenom, ' ', u.nom) AS demandeur
                    FROM demandes d
                    LEFT JOIN utilisateurs u ON d.idUtilisateur = u.id
                    WHERE d.idD = ?
                    LIMIT 1
                ");
            $stmtD->execute([$demandeId]);
            $demande = $stmtD->fetch(PDO::FETCH_ASSOC);
            if (!$demande) {
                echo json_encode(['success' => false, 'message' => 'Demande introuvable.']);
                break;
            }

            // ── Lignes de la demande ─────────────────────────────────────
            $stmtLignes = $bdBASI->prepare("
            SELECT 
    dl.idDL,
    lb.designation,
    c.nom_categorie,
    dl.quantite,
    lt.unite
FROM demandes_ligne dl
JOIN ligneBudget lb 
    ON dl.idLB = lb.id
LEFT JOIN product p 
    ON lb.id_produit = p.idP
LEFT JOIN souscategorie sc 
    ON p.id_Sous_categorie = sc.id
LEFT JOIN categorie c 
    ON sc.categorie_id = c.id
LEFT JOIN listeUnites lt 
    ON lt.id = dl.unite_id
WHERE dl.idD = ?
ORDER BY dl.idDL ASC;
                ");
            $stmtLignes->execute([$demandeId]);
            $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

            // ── Requêtes préparées réutilisées pour chaque ligne ─────────
            // Toutes les occurrences (une par commande) de cette ligne côté
            // "passer_achat_et_paiement_ligne", avec la quantité livrée sur
            // CETTE occurrence précise (une même ligne budgétaire peut avoir
            // été commandée plusieurs fois, chaque fois dans un papl distinct).
            $stmtOccurrences = $bdBASI->prepare("
                    SELECT papl.id AS idPAPL, papl.idPAP, papl.quantite_reelle, papl.quantite_livree,
                           papl.montant_total_ligne, p.nom_commande, p.idStatut, p.idTypePAP
                    FROM passer_achat_et_paiement_ligne papl
                    JOIN passer_achat_et_paiement p ON papl.idPAP = p.id
                    WHERE papl.idDL = ?
                    ORDER BY p.dateCreation ASC
                ");
            $stmtPaiementEffectue = $bdBASI->prepare("
                    SELECT COUNT(*) AS n FROM paiement_pap WHERE idPAP = ? AND montant > 0
                ");
            $stmtHistoCmd = $bdBASI->prepare("
                    SELECT h.idStatut, h.motif, h.dateEnregistrement,
                           CONCAT(u2.prenom, ' ', u2.nom) AS utilisateur
                    FROM historique_passer_achat_et_paiement h
                    LEFT JOIN utilisateurs u2 ON h.idUtilisateur = u2.id
                    WHERE h.idPAP = ?
                    ORDER BY h.dateEnregistrement ASC
                ");

            foreach ($lignes as &$ligne) {
                $stmtOccurrences->execute([(int)$ligne['idDL']]);
                $occurrences = $stmtOccurrences->fetchAll(PDO::FETCH_ASSOC);

                $commandes = [];
                foreach ($occurrences as $occ) {
                    $idPAP = (int)$occ['idPAP'];
                    $estAchat = ((int)$occ['idTypePAP'] === 1);

                    $entree = [
                        'idPAP' => $idPAP,
                        'nom_commande' => $occ['nom_commande'],
                        'idStatut' => (int)$occ['idStatut'],
                        'idTypePAP' => (int)$occ['idTypePAP'],
                    ];

                    if ($estAchat) {
                        // Achat : livré si quantite_livree >= quantite_reelle
                        // SUR CETTE OCCURRENCE (ce papl précis).
                        $qteReelle = (float)$occ['quantite_reelle'];
                        $qteLivree = (float)$occ['quantite_livree'];
                        $entree['quantite_commandee'] = $qteReelle;
                        $entree['livre'] = ($qteReelle > 0) && ($qteLivree >= $qteReelle - 0.001);
                    } else {
                        // Paiement : montant à payer + juste "payé / non payé",
                        // aucun autre détail.
                        $entree['montant_a_payer'] = (float)$occ['montant_total_ligne'];
                        $stmtPaiementEffectue->execute([$idPAP]);
                        $entree['paye'] = ((int)($stmtPaiementEffectue->fetch(PDO::FETCH_ASSOC)['n'] ?? 0)) > 0;
                    }

                    $stmtHistoCmd->execute([$idPAP]);
                    $entree['historique'] = $stmtHistoCmd->fetchAll(PDO::FETCH_ASSOC);

                    $commandes[] = $entree;
                }

                $ligne['nombre_commandes'] = count($commandes);
                $ligne['commandes'] = $commandes;
            }
            unset($ligne);

            echo json_encode([
                'success' => true,
                'data' => [
                    'demande' => $demande,
                    'lignes' => $lignes,
                ],
            ]);
        } catch (\Throwable $e) {

            echo $ê;
            die;
            error_log('[Compta][case52] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Impossible de charger le suivi de la demande.']);
        }
        break;




    default:
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Option '$option' non reconnue."]);
        exit;
}