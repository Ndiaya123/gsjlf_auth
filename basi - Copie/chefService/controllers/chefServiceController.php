<?php
// ─── Capture de tout output parasite ─────────────────────────────────────────
ob_start();
session_start();
include_once('../../../bdBASI.php');
ob_end_clean();

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// ─── Vérification sessions obligatoires ──────────────────────────────────────
function checkSession(): void {
    foreach (['tmpIdBASI', 'tmpIdDirection', 'tmpMatricule'] as $key) {
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
$sessionDirection = (int)$_SESSION['tmpIdDirection'];
$sessionMatricule = trim($_SESSION['tmpMatricule']);

// ─── Classe contrôleur ────────────────────────────────────────────────────────
class chefServiceController extends BDBASI
{
    function tokenencrypt($data) {
        $key = hash('sha256','U@hbENTDRI@TCRI@T2022');
        $iv  = substr(hash('sha256','www.ent.uahb.sn'),0,16);
        return strtr(base64_encode(openssl_encrypt($data,"AES-256-CBC",$key,0,$iv)), '+/=', '-_.');
    }
    function tokendecrypt($data) {
        $key = hash('sha256','U@hbENTDRI@TCRI@T2022');
        $iv  = substr(hash('sha256','www.ent.uahb.sn'),0,16);
        return openssl_decrypt(base64_decode(strtr($data, '-_.', '+/=')),"AES-256-CBC",$key,0,$iv);
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
$basiController = new chefServiceController();

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

// ─── Helpers globaux ─────────────────────────────────────────────────────────

// Type investissement fixé côté serveur (jamais lu depuis le client)
define('TYPE_BUDGET_INVESTISSEMENT', 2); // ← ajuster selon BDD
define('TYPE_BUDGET_FONCTIONNEMENT',  1); // budget fonctionnement

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

function insertHistoriqueBudget(PDO $pdo, int $budgetId, array $row, int $userId,$dateEnregistrement ,string $motif): void {
    $pdo->prepare("
        INSERT INTO historique_budget
            (idBudget, annee, matricule, direction_id, type_budget_id,
             date_creation, statut, idStatut, plafond, idUtilisateur, dateEnregistrement, motif)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([
        $budgetId, $row['annee'], $row['matricule'], $row['direction_id'],
        $row['type_budget_id'], $row['date_creation'], $row['statut'],
        $row['idStatut'], $row['plafond'], $userId, $dateEnregistrement,$motif
    ]);
}

function getJsonBody(): array {
    $raw = file_get_contents("php://input");
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}


// INSERT historique_ligneBudget (snapshot complet de la ligne)
function insertHistoriqueLigneBudget(PDO $pdo, array $ligne, $statut, $idStatut, string $motif): void {
    $pdo->prepare("
        INSERT INTO historique_ligneBudget
            (ligne_budget_id, budget_id, rubrique_id, sous_rubrique_id,
             id_type_budget_investissement, designation, description,
             quantite, unite_id, prix_unitaire, montant_total, service_id, id_produit,
             date_creation, statut, idStatut, periode_d_utilisation,
             motif, dateEnregistrement)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([
        $ligne['id']                            ?? null,
        $ligne['budget_id']                     ?? null,
        $ligne['rubrique_id']                   ?? null,
        $ligne['sous_rubrique_id']              ?? null,
        $ligne['id_type_budget_investissement'] ?? null,
        $ligne['designation']                   ?? null,
        $ligne['description']                   ?? null,
        $ligne['quantite']                      ?? null,
        $ligne['unite_id']                      ?? null, // corrigé : unite_id (pas unite)
        $ligne['prix_unitaire']                 ?? null,
        $ligne['montant_total']                 ?? null, // ajouté
        $ligne['service_id']                    ?? null,
        $ligne['id_produit']                    ?? null,
        $ligne['date_creation']                 ?? date('Y-m-d H:i:s'),
        $statut                       ?? 'Inactif',
        $idStatut                      ?? null,
        $ligne['periode_d_utilisation']         ?? null,
        $motif,
        date('Y-m-d H:i:s')
    ]);
}

// INSERT historique_product (snapshot complet du produit)
function insertHistoriqueProduit(PDO $pdo, array $produit, string $motif): void {
    // Colonnes produit + product_id + motif + dateEnregistrement
    $pdo->prepare("
        INSERT INTO historique_product
            (product_id, nomproduit, id_Sous_categorie, Stock_actuel, Seuil_limite,
             Total, retrait, id_statut, id_type_product, code_produit,
             id_unite, date_creation, motif, dateEnregistrement)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([
        $produit['idP']               ?? null,
        $produit['nomproduit']        ?? null,
        $produit['id_Sous_categorie'] ?? null,
        $produit['Stock_actuel']      ?? 0,
        $produit['Seuil_limite']      ?? 0,
        $produit['Total']             ?? 0,
        $produit['retrait']           ?? 0,
        $produit['id_statut']         ?? 1,
        $produit['id_type_product']   ?? null,
        $produit['code_produit']      ?? null,
        $produit['id_unite']          ?? null,
        $produit['date_creation']     ?? date('Y-m-d H:i:s'),
        $motif,
        date('Y-m-d H:i:s')
    ]);
}
switch ($option) {

// ─── CASE 1 : Lister les rubriques ───────────────────────────────────────────
    case 1:
        try {
            date_default_timezone_set('Africa/Dakar');
            $stmt = $bdBASI->prepare("
                SELECT rubrique.id, rubrique.nom_rubrique, rubrique.date_creation,
                       utilisateurs.prenom, utilisateurs.nom,
                       (SELECT COUNT(*) FROM sousRubrique sr
                        WHERE sr.rubrique_id=rubrique.id AND sr.statut!=4) AS subrub_count
                FROM rubrique
                INNER JOIN utilisateurs ON rubrique.idUtilisateur=utilisateurs.id
                WHERE rubrique.statut!=4 ORDER BY rubrique.id ASC
            ");
            $stmt->execute();
            $out=[]; $n=0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[] = [
                    'tmp'          => $basiController->tokenencrypt($r['id']),
                    'numero'       => ++$n,
                    'nom_rubrique' => $r['nom_rubrique'],
                    'date_creation'=> date_format(date_create($r['date_creation']),'d/m/Y H:i:s'),
                    'createur'     => ucfirst(mb_strtolower($r['prenom'])).' '.$basiController->fctRetirerAccents(mb_strtoupper($r['nom'])),
                    'subrub_count' => (int)$r['subrub_count'],
                ];
            }
            echo json_encode($out);
        } catch (\Throwable $th) { error_log("Case 1: ".$th->getMessage()); echo json_encode([]); }
        die;

// ─── CASE 2 : Ajouter une rubrique ───────────────────────────────────────────
    case 2:
        date_default_timezone_set('Africa/Dakar');
        if (empty(trim($_POST['nom_rubrique']??''))) { echo "obligatoire"; die; }
        try {
            $bdBASI->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
            $bdBASI->beginTransaction();
            $nomr = trim($_POST['nom_rubrique']);
            if (!preg_match('/^[\p{L}\p{N}\s\'&-]+$/u', $nomr)) { $bdBASI->rollBack(); echo "caratereSpeciaux"; die; }
            $chk=$bdBASI->prepare("SELECT COUNT(*) FROM rubrique WHERE nom_rubrique=? AND statut!=4");
            $chk->execute([$nomr]); if ($chk->fetchColumn()>0) { $bdBASI->rollBack(); echo "rubriqueExiste"; die; }
            $date=(new DateTime())->format('Y-m-d H:i:s');
            $s=$bdBASI->prepare("INSERT INTO rubrique (nom_rubrique,statut,date_creation,idUtilisateur) VALUES (?,1,?,?)");
            if ($s->execute([$nomr,$date,$sessionUserId])) {
                $rid=$bdBASI->lastInsertId();
                $h=$bdBASI->prepare("INSERT INTO historique_rubrique (id_rubrique,nom_rubrique,action,dateEnregistrement,idUtilisateur) VALUES (?,?,'Insertion',?,?)");
                if ($h->execute([$rid,$nomr,$date,$sessionUserId])) { $bdBASI->commit(); echo "succès"; }
                else { $bdBASI->rollBack(); echo "erreur"; }
            } else { $bdBASI->rollBack(); echo "erreur"; }
        } catch (\Throwable $th) { $bdBASI->rollBack(); error_log("Case 2: ".$th->getMessage()); echo "erreur"; }
        die;

// ─── CASE 3 : Modifier une rubrique ──────────────────────────────────────────
    case 3:
        date_default_timezone_set('Africa/Dakar');
        $id=$basiController->tokendecrypt($_POST['tmp']??null);
        $nom=trim($_POST['nom_rubrique_up']??''); $original=trim($_POST['original_nom']??'');
        $dmod=(new DateTime())->format('Y-m-d H:i:s');
        try {
            $bdBASI->beginTransaction();
            if (empty($nom)) { $bdBASI->rollBack(); echo "obligatoire"; die; }
            if (!preg_match('/^[\p{L}\p{N}\s\'&-]+$/u',$nom)) { $bdBASI->rollBack(); echo "caratereSpeciaux"; die; }
            $chk=$bdBASI->prepare("SELECT COUNT(*) FROM rubrique WHERE nom_rubrique=? AND id!=? AND statut!=4");
            $chk->execute([$nom,$id??0]); if ($chk->fetchColumn()>0) { $bdBASI->rollBack(); echo "rubriqueExiste"; die; }
            if (empty($id)) { $bdBASI->rollBack(); echo "erreur"; die; }
            $rub=$bdBASI->prepare("SELECT COUNT(*) FROM rubrique WHERE id=? AND statut!=4");
            $rub->execute([$id]); if ($rub->fetchColumn()!=1) { $bdBASI->rollBack(); echo "erreur"; die; }
            $sub=$bdBASI->prepare("SELECT COUNT(*) FROM sousRubrique WHERE rubrique_id=? AND statut!=4");
            $sub->execute([$id]); if ($sub->fetchColumn()>0 && $nom!==$original) { $bdBASI->rollBack(); echo "sousRubriqueExiste"; die; }
            $s=$bdBASI->prepare("UPDATE rubrique SET nom_rubrique=?,date_derniere_modification=? WHERE id=?");
            if (!$s->execute([$nom,$dmod,$id])) { $bdBASI->rollBack(); echo "erreur"; die; }
            $bdBASI->prepare("INSERT INTO historique_rubrique (id_rubrique,nom_rubrique,action,dateEnregistrement,idUtilisateur) VALUES (?,?,'Modification',?,?)")->execute([$id,$nom,$dmod,$sessionUserId]);
            $bdBASI->commit(); echo "succès";
        } catch (Exception $e) { $bdBASI->rollBack(); error_log("Case 3: ".$e->getMessage()); echo "erreur"; }
        die;

// ─── CASE 4 : Supprimer une rubrique ─────────────────────────────────────────
    case 4:
        date_default_timezone_set('Africa/Dakar');
        $id=$basiController->tokendecrypt($_POST['e1']??null); $nom=trim($_POST['e2']??'');
        $date=(new DateTime())->format('Y-m-d H:i:s');
        try {
            $bdBASI->beginTransaction();
            if (empty($id)) { $bdBASI->rollBack(); echo "erreur"; die; }
            $rub=$bdBASI->prepare("SELECT COUNT(*) FROM rubrique WHERE id=? AND statut!=4");
            $rub->execute([$id]); if ($rub->fetchColumn()!=1) { $bdBASI->rollBack(); echo "erreur"; die; }
            $sub=$bdBASI->prepare("SELECT COUNT(*) FROM sousRubrique WHERE rubrique_id=? AND statut!=4");
            $sub->execute([$id]); if ($sub->fetchColumn()>0) { $bdBASI->rollBack(); echo "sousRubriqueExiste"; die; }
            $s=$bdBASI->prepare("UPDATE rubrique SET statut=4,date_derniere_modification=? WHERE id=?");
            if (!$s->execute([$date,$id])) { $bdBASI->rollBack(); echo "erreur"; die; }
            $bdBASI->prepare("INSERT INTO historique_rubrique (id_rubrique,nom_rubrique,action,dateEnregistrement,idUtilisateur) VALUES (?,?,'Suppression',?,?)")->execute([$id,$nom,$date,$sessionUserId]);
            $bdBASI->commit(); echo "succès";
        } catch (Exception $e) { $bdBASI->rollBack(); error_log("Case 4: ".$e->getMessage()); echo "erreur"; }
        die;

// ─── CASE 5 : Lister les sous-rubriques ──────────────────────────────────────
    case 5:
        try {
            date_default_timezone_set('Africa/Dakar');
            $stmt=$bdBASI->prepare("
                SELECT sr.id, sr.nom_sous_rubrique, sr.date_creation, sr.rubrique_id AS rubrique,
                       u.prenom, u.nom, ru.nom_rubrique, COUNT(lb.id) AS subrub_count
                FROM sousRubrique sr
                INNER JOIN utilisateurs u ON sr.idUtilisateur=u.id
                INNER JOIN rubrique ru    ON sr.rubrique_id=ru.id
                LEFT JOIN  ligneBudget lb ON lb.sous_rubrique_id=sr.id
                WHERE sr.statut<>4
                GROUP BY sr.id,sr.nom_sous_rubrique,sr.date_creation,u.prenom,u.nom,ru.nom_rubrique
                ORDER BY sr.id ASC
            ");
            $stmt->execute();
            $out=[]; $n=0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[]=['tmp'=>$basiController->tokenencrypt($r['id']),'numero'=>++$n,
                    'nom_sous_rubrique'=>$r['nom_sous_rubrique'],'nom_rubrique'=>$r['nom_rubrique'],
                    'rubrique'=>$r['rubrique'],
                    'date_creation'=>date_format(date_create($r['date_creation']),'d/m/Y H:i:s'),
                    'createur'=>ucfirst(mb_strtolower($r['prenom'])).' '.$basiController->fctRetirerAccents(mb_strtoupper($r['nom'])),
                    'subrub_count'=>(int)$r['subrub_count']];
            }
            echo json_encode($out);
        } catch (\Throwable $th) { error_log("Case 5: ".$th->getMessage()); echo json_encode([]); }
        die;

// ─── CASE 6 : Options <select> rubriques ─────────────────────────────────────
    case 6:
        try {
            $stmt=$bdBASI->prepare("SELECT * FROM rubrique WHERE statut<>4");
            $stmt->execute(); $listes=$stmt->fetchAll(PDO::FETCH_OBJ);
            echo '<option></option><option value="">Choisir...</option>';
            foreach ($listes as $r) echo '<option value="'.$r->id.'">'.$r->nom_rubrique.'</option>';
        } catch (\Throwable $th) { echo "erreur"; }
        die;

// ─── CASE 7 : Ajouter une sous-rubrique ──────────────────────────────────────
    case 7:
        date_default_timezone_set('Africa/Dakar');
        if (empty(trim($_POST['nom_sous_rubrique']??''))&&empty(trim($_POST['rubrique']??''))) { echo "obligatoire"; die; }
        try {
            $bdBASI->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
            $bdBASI->beginTransaction();
            $nomr=trim($_POST['nom_sous_rubrique']); $rubrique_id=trim($_POST['rubrique']);
            if (!preg_match('/^[a-zA-Z0-9\s\'\-éèêëàâùûüîïôçœæÉÈÊËÀÂÙÛÜÎÏÔÇŒÆ]+$/',$nomr)) { $bdBASI->rollBack(); echo "caratereSpeciaux"; die; }
            $chk=$bdBASI->prepare("SELECT COUNT(*) FROM sousRubrique WHERE nom_sous_rubrique=? AND rubrique_id=? AND statut!=4");
            $chk->execute([$nomr,$rubrique_id]); if ($chk->fetchColumn()>0) { $bdBASI->rollBack(); echo "rubriqueExiste"; die; }
            $rub=$bdBASI->prepare("SELECT COUNT(*) FROM rubrique WHERE id=? AND statut!=4");
            $rub->execute([$rubrique_id]); if ($rub->fetchColumn()==0) { $bdBASI->rollBack(); echo "rubriqueParentNo"; die; }
            $date=(new DateTime())->format('Y-m-d H:i:s');
            $s=$bdBASI->prepare("INSERT INTO sousRubrique (rubrique_id,nom_sous_rubrique,statut,date_creation,idUtilisateur) VALUES (?,?,1,?,?)");
            if ($s->execute([$rubrique_id,$nomr,$date,$sessionUserId])) {
                $sid=$bdBASI->lastInsertId();
                $h=$bdBASI->prepare("INSERT INTO historique_sous_rubrique (id_sous_rubrique,rubrique_id,nom_sous_rubrique,action,dateEnregistrement,idUtilisateur) VALUES (?,?,?,'Insertion',?,?)");
                if ($h->execute([$sid,$rubrique_id,$nomr,$date,$sessionUserId])) { $bdBASI->commit(); echo "succès"; }
                else { $bdBASI->rollBack(); echo "erreur"; }
            } else { $bdBASI->rollBack(); echo "erreur"; }
        } catch (\Throwable $th) { $bdBASI->rollBack(); error_log("Case 7: ".$th->getMessage()); echo "erreur"; }
        die;

// ─── CASE 8 : Modifier une sous-rubrique ─────────────────────────────────────
    case 8:
        date_default_timezone_set('Africa/Dakar');
        $id=$basiController->tokendecrypt($_POST['tmp']??null);
        $rubrique_id=trim($_POST['rubrique_up']??''); $nom=trim($_POST['nom_sous_rubrique_up']??''); $original=trim($_POST['original_nom']??'');
        $dmod=(new DateTime())->format('Y-m-d H:i:s');
        try {
            $bdBASI->beginTransaction();
            if (empty($nom)) { $bdBASI->rollBack(); echo "obligatoire"; die; }
            if (!preg_match('/^[a-zA-Z0-9\s\'\-éèêëàâùûüîïôçœæÉÈÊËÀÂÙÛÜÎÏÔÇŒÆ]+$/',$nom)) { $bdBASI->rollBack(); echo "caratereSpeciaux"; die; }
            $chk=$bdBASI->prepare("SELECT COUNT(*) FROM sousRubrique WHERE nom_sous_rubrique=? AND rubrique_id=? AND id!=? AND statut!=4");
            $chk->execute([$nom,$rubrique_id??0,$id??0]); if ($chk->fetchColumn()>0) { $bdBASI->rollBack(); echo "rubriqueExiste"; die; }
            if (empty($id)) { $bdBASI->rollBack(); echo "erreur"; die; }
            $rub=$bdBASI->prepare("SELECT COUNT(*) FROM sousRubrique WHERE id=? AND statut!=4");
            $rub->execute([$id]); if ($rub->fetchColumn()!=1) { $bdBASI->rollBack(); echo "erreur"; die; }
            $sub=$bdBASI->prepare("SELECT COUNT(*) FROM sousRubrique WHERE rubrique_id=? AND statut!=4");
            $sub->execute([$id]); if ($sub->fetchColumn()>0 && $nom!==$original) { $bdBASI->rollBack(); echo "sousRubriqueExiste"; die; }
            $prnt=$bdBASI->prepare("SELECT COUNT(*) FROM rubrique WHERE id=? AND statut!=4");
            $prnt->execute([$rubrique_id]); if ($prnt->fetchColumn()==0) { $bdBASI->rollBack(); echo "rubriqueParentNo"; die; }
            $lb=$bdBASI->prepare("SELECT 1 FROM ligneBudget WHERE sous_rubrique_id=? LIMIT 1");
            $lb->execute([$id]); if ($lb->fetch()) { $bdBASI->rollBack(); echo "ligneBudgetExiste"; die; }
            $s=$bdBASI->prepare("UPDATE sousRubrique SET nom_sous_rubrique=?,date_derniere_modification=? WHERE id=?");
            if (!$s->execute([$nom,$dmod,$id])) { $bdBASI->rollBack(); echo "erreur"; die; }
            $bdBASI->prepare("INSERT INTO historique_sous_rubrique (id_sous_rubrique,rubrique_id,nom_sous_rubrique,action,dateEnregistrement,idUtilisateur) VALUES (?,?,?,'Modification',?,?)")->execute([$id,$rubrique_id,$nom,$dmod,$sessionUserId]);
            $bdBASI->commit(); echo "succès";
        } catch (Exception $e) { $bdBASI->rollBack(); error_log("Case 8: ".$e->getMessage()); echo "erreur"; }
        die;

// ─── CASE 9 : Supprimer une sous-rubrique ────────────────────────────────────
    case 9:
        date_default_timezone_set('Africa/Dakar');
        $id=$basiController->tokendecrypt($_POST['e1']??null); $nom=trim($_POST['e2']??'');
        $date=(new DateTime())->format('Y-m-d H:i:s');
        try {
            $bdBASI->beginTransaction();
            if (empty($id)) { $bdBASI->rollBack(); echo "erreur3"; die; }
            $rub=$bdBASI->prepare("SELECT * FROM sousRubrique WHERE id=? AND statut!=4");
            $rub->execute([$id]); $result=$rub->fetch(PDO::FETCH_OBJ);
            if (!$result) { $bdBASI->rollBack(); echo "erreur2"; die; }
            $lb=$bdBASI->prepare("SELECT 1 FROM ligneBudget WHERE sous_rubrique_id=? LIMIT 1");
            $lb->execute([$id]); if ($lb->fetch()) { $bdBASI->rollBack(); echo "ligneBudgetExiste"; die; }
            $s=$bdBASI->prepare("UPDATE sousRubrique SET statut=4,date_derniere_modification=? WHERE id=?");
            $s->execute([$date,$id]); if ($s->rowCount()!=1) { $bdBASI->rollBack(); echo "erreur"; die; }
            $bdBASI->prepare("INSERT INTO historique_sous_rubrique (id_sous_rubrique,rubrique_id,nom_sous_rubrique,action,dateEnregistrement,idUtilisateur) VALUES (?,?,?,'Suppression',?,?)")->execute([$id,$result->rubrique_id,$nom,$date,$sessionUserId]);
            $bdBASI->commit(); echo "succès";
        } catch (Exception $e) { $bdBASI->rollBack(); error_log("Case 9: ".$e->getMessage()); echo "erreur"; }
        die;

// ═══════════════════════════════════════════════════════════════════════════════
// CASES 10-17 : MODULE BUDGET (INVESTISSEMENT UNIQUEMENT)
// Direction : session | Type : TYPE_BUDGET_INVESTISSEMENT | Méthode : POST JSON
// ═══════════════════════════════════════════════════════════════════════════════

// ─── CASE 10 : POST — liste des budgets de la direction connectée ─────────────
    case 10:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data   = getJsonBody();
            $annee  = isset($data['annee'])  && $data['annee']!==''  ? (int)$data['annee']  : null;
            $limit  = isset($data['limit'])  && $data['limit']!==''  ? max(1,(int)$data['limit'])  : 50;
            $offset = isset($data['offset']) && $data['offset']!=='' ? max(0,(int)$data['offset']) : 0;

            $type_budget_id = TYPE_BUDGET_INVESTISSEMENT;
            $direction_id   = $sessionDirection;

            $sql = "SELECT b.*, tb.nom AS type_budget_nom, d.nom_direction, ".sqlIdStatut('b')."
                    FROM budget b
                    JOIN typeBudget tb ON b.type_budget_id=tb.id
                    LEFT JOIN direction d ON b.direction_id=d.id
                    WHERE b.statut!='Supprimer'
                      AND b.direction_id=:direction_id
                      AND b.type_budget_id=:type_budget_id";
            if ($annee!==null) $sql .= " AND b.annee=:annee";
            $sql .= " ORDER BY b.annee DESC, b.id DESC LIMIT :limit OFFSET :offset";

            $stmt=$bdBASI->prepare($sql);
            $stmt->bindValue(':direction_id',  (int)$direction_id,  PDO::PARAM_INT);
            $stmt->bindValue(':type_budget_id',(int)$type_budget_id,PDO::PARAM_INT);
            if ($annee!==null) $stmt->bindValue(':annee',(int)$annee,PDO::PARAM_INT);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset',(int)$offset,PDO::PARAM_INT);
            $stmt->execute();
            $rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
            foreach ($rows as &$row) $row['tmp']=$basiController->tokenencrypt($row['id']);
            unset($row);
            echo json_encode(["status"=>"success","data"=>$rows]);
        } catch (PDOException $e) { error_log("Case 10 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 10: ".$e->getMessage());     http_response_code(400);  echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 11 : POST — un budget par id (token) ───────────────────────────────
    case 11:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            if (empty($data['id'])) throw new Exception("Champ requis manquant : id.");
            $id=(int)$data['id'];
            $stmt=$bdBASI->prepare("
                SELECT b.*, tb.nom AS type_budget_nom, d.nom_direction, ".sqlIdStatut('b')."
                FROM budget b
                JOIN typeBudget tb ON b.type_budget_id=tb.id
                LEFT JOIN direction d ON b.direction_id=d.id
                WHERE b.id=:id AND b.statut!='Supprimer'
                  AND b.direction_id=:direction_id AND b.type_budget_id=:type_budget_id
                LIMIT 1
            ");
            $stmt->bindValue(':id',            $id,                         PDO::PARAM_INT);
            $stmt->bindValue(':direction_id',  (int)$sessionDirection,      PDO::PARAM_INT);
            $stmt->bindValue(':type_budget_id',TYPE_BUDGET_INVESTISSEMENT,  PDO::PARAM_INT);
            $stmt->execute();
            $result=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) { http_response_code(404); echo json_encode(["status"=>"error","message"=>"Budget non trouvé ou accès non autorisé."]); exit; }
            echo json_encode(["status"=>"success","data"=>$result]);
        } catch (PDOException $e) { error_log("Case 11 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 11: ".$e->getMessage());     http_response_code(400);  echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 12 : POST — créer un budget investissement ─────────────────────────
// Body JSON : { "annee": 2026, "plafond": 5000000 }
// type_budget_id, direction_id, matricule, idUtilisateur → session uniquement
    case 12:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            if (empty($data['annee'])) throw new Exception("Champ requis manquant : annee.");

            $annee         = (int)$data['annee'];
            $plafond       = isset($data['plafond'])&&is_numeric($data['plafond'])?(float)$data['plafond']:null;
            $type_budget_id= TYPE_BUDGET_INVESTISSEMENT;
            $direction_id  = (int)$sessionDirection;
            $matricule     = $sessionMatricule;
            $idUtilisateur = (int)$sessionUserId;
            $statut        = 'En cours';
            $idStatut      = 1;

            $curYear=(int)date('Y');
            if ($annee<$curYear)      throw new Exception("Impossible de créer un budget pour une année passée.");
            if ($annee>$curYear+10)   throw new Exception("L'année ne peut pas excéder 10 ans dans le futur.");
            if ($plafond!==null&&($plafond<=0||$plafond>1000000000)) throw new Exception("Plafond hors limites.");

            $chkD=$bdBASI->prepare("SELECT COUNT(*) FROM direction WHERE id=?");
            $chkD->execute([$direction_id]);
            if ($chkD->fetchColumn()==0) throw new Exception("Direction de session invalide (ID=$direction_id).");

            $chkT=$bdBASI->prepare("SELECT COUNT(*) FROM typeBudget WHERE id=?");
            $chkT->execute([$type_budget_id]);
            if ($chkT->fetchColumn()==0) throw new Exception("Type de budget invalide (ID=".TYPE_BUDGET_INVESTISSEMENT.").");

            $chkU=$bdBASI->prepare("SELECT COUNT(*) FROM budget WHERE annee=? AND type_budget_id=? AND direction_id=? AND statut!='Supprimer'");
            $chkU->execute([$annee,$type_budget_id,$direction_id]);
            if ($chkU->fetchColumn()>0) throw new Exception("Un budget investissement existe déjà pour l'année $annee et votre direction.");

            $dateCreation = date('Y-m-d H:i:s');
            $stmt=$bdBASI->prepare("INSERT INTO budget (annee,type_budget_id,plafond,statut,idStatut,date_creation,matricule,idUtilisateur,direction_id) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$annee,$type_budget_id,$plafond,$statut,$idStatut,$dateCreation,$matricule,$idUtilisateur,$direction_id]);
            $newId=$bdBASI->lastInsertId();

            insertHistoriqueBudget($bdBASI,(int)$newId,[
                'annee'=>$annee,'matricule'=>$matricule,'direction_id'=>$direction_id,
                'type_budget_id'=>$type_budget_id,'date_creation'=>date('Y-m-d H:i:s'),
                'statut'=>$statut,'idStatut'=>$idStatut,'plafond'=>$plafond,
            ],$idUtilisateur,date('Y-m-d H:i:s'),'Insertion');

            echo json_encode(["status"=>"success","data"=>["id"=>(int)$newId],"message"=>"Budget d'investissement créé avec succès."]);
        } catch (PDOException $e) { echo $e; die;error_log("Case 12 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 12: ".$e->getMessage());     http_response_code(400);  echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 13 : POST — mettre à jour un budget ────────────────────────────────
// Body JSON : { "id": 42, "plafond"?: ..., "statut"?: ..., "motif"?: ... }
    case 13:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            if (empty($data['id'])) throw new Exception("Champ requis manquant : id.");
            $id=(int)$data['id'];

            $get=$bdBASI->prepare("SELECT * FROM budget WHERE id=? AND direction_id=? AND type_budget_id=? AND statut!='Supprimer'");
            $get->execute([$id,(int)$sessionDirection,TYPE_BUDGET_INVESTISSEMENT]);
            $old=$get->fetch(PDO::FETCH_ASSOC);
            if (!$old) throw new Exception("Budget non trouvé ou accès non autorisé.");
            // Statuts bloquant toute modification
            if (in_array($old['statut'], ['Terminer', 'Valider', 'Accepter'], true))
                throw new Exception("Impossible de modifier un budget au statut '{$old['statut']}'.");

            // Réajuster : uniquement si l'année du budget est >= année en cours
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

            // ── Validation plafond ──────────────────────────────────────────────────────
            // Règle 1 : le nouveau plafond ne peut pas être inférieur à la somme des
            //           lignes actives ayant idStatut=1 OU verrouiller=1.
            // Règle 2 : s'il existe des lignes actives/verrouillées (somme > 0),
            //           le plafond ne peut pas diminuer par rapport à l'ancien.
            if ($plafond !== null && array_key_exists('plafond', $data)) {
                // Somme des lignes actives ET (idStatut=1 OU verrouiller=1)
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

                // Règle 1 : plafond ≥ somme des lignes protégées
                if ($sommeLignes > 0 && (float)$plafond < $sommeLignes) {
                    throw new Exception(
                        "Le plafond ne peut pas être inférieur au total des lignes actives/verrouillées ("
                        . number_format($sommeLignes, 0, ',', ' ') . " FCFA). "
                        . "Valeur minimale acceptée : " . number_format($sommeLignes, 0, ',', ' ') . " FCFA."
                    );
                }

                // Règle 2 : si des lignes protégées existent, le plafond ne peut que augmenter
                $plafondActuel = (float)$old['plafond'];
                if ($sommeLignes > 0 && (float)$plafond < $plafondActuel) {
                    throw new Exception(
                        "Impossible de diminuer le plafond lorsque des lignes actives ou verrouillées sont rattachées à ce budget. "
                        . "Plafond actuel : " . number_format($plafondActuel, 0, ',', ' ') . " FCFA. "
                        . "Le nouveau plafond doit être supérieur ou égal à " . number_format($plafondActuel, 0, ',', ' ') . " FCFA."
                    );
                }
            }

            $fields=[]; $params=[':id'=>$id,':dir'=>(int)$sessionDirection,':tbid'=>TYPE_BUDGET_INVESTISSEMENT];
            if (array_key_exists('plafond',$data)) { $fields[]="plafond=:plafond"; $params[':plafond']=$plafond; }
            if (isset($data['statut']))             { $fields[]="statut=:statut"; $fields[]="idStatut=:idStatut"; $params[':statut']=$statut; $params[':idStatut']=$idStatut; }
            if (empty($fields)) throw new Exception("Aucun champ modifiable fourni.");

            $bdBASI->beginTransaction();
            $stmt=$bdBASI->prepare("UPDATE budget SET ".implode(',',$fields)." WHERE id=:id AND direction_id=:dir AND type_budget_id=:tbid");
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
                'direction_id'=>(int)$sessionDirection,'type_budget_id'=>TYPE_BUDGET_INVESTISSEMENT,
                'date_creation'=>$old['date_creation'],'statut'=>$statut,'idStatut'=>$idStatut,'plafond'=>$plafond,
            ],(int)$sessionUserId,date('Y-m-d H:i:s'),$motif);
            $bdBASI->commit();

            $rel=$bdBASI->prepare("SELECT b.*,tb.nom AS type_budget_nom,d.nom_direction,".sqlIdStatut('b')." FROM budget b JOIN typeBudget tb ON b.type_budget_id=tb.id LEFT JOIN direction d ON b.direction_id=d.id WHERE b.id=?");
            $rel->execute([$id]);
            echo json_encode(["status"=>"success","data"=>$rel->fetch(PDO::FETCH_ASSOC),"message"=>"Budget mis à jour avec succès."]);
        } catch (PDOException $e) { error_log("Case 13 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 13: ".$e->getMessage());     http_response_code(400);  echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 14 : POST — lignes (action=lines) | supprimer (action=delete) ───────
    case 14:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data  =getJsonBody();
            $action=trim($data['action']??'');

            if ($action==='lines') {
                $budgetId=$basiController->tokendecrypt($data['budgetId']??'');
                if (!$budgetId) throw new Exception("Champ requis manquant : budgetId.");
                $chk=$bdBASI->prepare("SELECT COUNT(*) FROM budget WHERE id=? AND direction_id=? AND type_budget_id=? AND statut!='Supprimer'");
                $chk->execute([$budgetId,(int)$sessionDirection,TYPE_BUDGET_INVESTISSEMENT]);
                if ($chk->fetchColumn()==0) throw new Exception("Budget non trouvé ou accès non autorisé.");
                $stmt=$bdBASI->prepare("
                    SELECT lb.*, r.nom_rubrique AS rubrique_nom, sr.nom_sous_rubrique AS sous_rubrique_nom,
                           d.nom_direction AS direction_nom, s.nom_services AS service_nom
                    FROM ligneBudget lb
                    LEFT JOIN rubrique    r  ON lb.rubrique_id      = r.id
                    LEFT JOIN sousRubrique sr ON lb.sous_rubrique_id = sr.id
                    LEFT JOIN services    s  ON lb.service_id       = s.id
                    LEFT JOIN direction   d  ON s.id_direction      = d.id
                    WHERE lb.budget_id=? AND lb.statut!='Supprimer'
                ");
                $stmt->execute([$budgetId]);
                $lines=$stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["status"=>"success","lineCount"=>count($lines),"lastUpdate"=>date('Y-m-d H:i:s'),"lines"=>$lines]);
                exit;
            }

            if ($action==='delete') {
                $id=isset($data['id'])?(int)$data['id']:null;
                if (!$id) throw new Exception("Champ requis manquant : id.");
                $chk=$bdBASI->prepare("SELECT * FROM budget WHERE id=? AND direction_id=? AND type_budget_id=?");
                $chk->execute([$id,(int)$sessionDirection,TYPE_BUDGET_INVESTISSEMENT]);
                $budgetRow=$chk->fetch(PDO::FETCH_ASSOC);
                if (!$budgetRow) throw new Exception("Budget non trouvé ou accès non autorisé.");
                if (in_array($budgetRow['statut'],['Valider','Accepter','Réajuster'])) throw new Exception("Impossible de supprimer un budget au statut '{$budgetRow['statut']}'.");
                $bdBASI->beginTransaction();
                try {
                    // Récupérer les lignes complètes (snapshot) avant de les désactiver
                    $lns=$bdBASI->prepare("SELECT * FROM ligneBudget WHERE budget_id=? AND statut!='Inactif' AND idStatut!=2");
                    $lns->execute([$id]); $lignes=$lns->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($lignes)) {
                        $bdBASI->prepare("UPDATE ligneBudget SET statut='Inactif', idStatut=2 WHERE budget_id=? AND statut!='Inactif' AND idStatut!=2")->execute([$id]);
                        foreach ($lignes as $ligneSnap) {
                            insertHistoriqueLigneBudget($bdBASI, $ligneSnap, 'Inactif', 2, 'Suppression (budget supprimé)');
                        }
                    }
                    $bdBASI->prepare("UPDATE budget SET statut='Supprimer', idStatut=8 WHERE id=?")->execute([$id]);
                    insertHistoriqueBudget($bdBASI,$id,[
                        'annee'=>$budgetRow['annee'],'matricule'=>$budgetRow['matricule'],
                        'direction_id'=>$budgetRow['direction_id'],'type_budget_id'=>$budgetRow['type_budget_id'],
                        'date_creation'=>$budgetRow['date_creation'],'statut'=>'Supprimer',
                        'idStatut'=>8,'plafond'=>$budgetRow['plafond'],
                    ],(int)$sessionUserId,date('Y-m-d H:i:s'),'Suppression');
                    $bdBASI->commit();
                    $nb=count($lignes);
                    echo json_encode(["status"=>"success","message"=>"Budget supprimé avec succès".($nb>0?" ($nb ligne(s) supprimée(s)).":".")]);
                } catch (Exception $ie) { $bdBASI->rollBack(); throw $ie; }
                exit;
            }
            throw new Exception("Action non supportée. Valeurs acceptées : 'lines', 'delete'.");
        } catch (PDOException $e) { error_log("Case 14 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 14: ".$e->getMessage());     http_response_code(400);  echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 15 : POST — directions | services ──────────────────────────────────
    case 15:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data  =getJsonBody();
            $action=trim($data['action']??'');
            if ($action==='directions') {
                $stmt=$bdBASI->prepare("SELECT id, nom_direction FROM direction ORDER BY nom_direction ASC");
                $stmt->execute();
                echo json_encode(["success"=>true,"directions"=>$stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
            }
            if ($action==='byDirection') {
                $id_dir=isset($data['id_direction'])?(int)$data['id_direction']:null;
                if (!$id_dir) throw new Exception("Champ requis manquant : id_direction.");
                $stmt=$bdBASI->prepare("SELECT s.id,s.nom_services,s.id_direction,d.nom_direction FROM services s LEFT JOIN direction d ON d.id=s.id_direction WHERE s.id_direction=? ORDER BY s.nom_services ASC");
                $stmt->execute([$id_dir]);
                echo json_encode(["success"=>true,"services"=>$stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
            }
            throw new Exception("Action non supportée : '$action'. Valeurs acceptées : 'directions', 'byDirection'.");
        } catch (PDOException $e) { error_log("Case 15 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 15: ".$e->getMessage());     http_response_code(400);  echo json_encode(["success"=>false,"message"=>$e->getMessage()]); }
        exit;

// ─── CASE 16 : POST — types de budget ────────────────────────────────────────
    case 16:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $stmt=$bdBASI->prepare("SELECT id, nom FROM typeBudget ORDER BY nom ASC");
            $stmt->execute();
            echo json_encode(["status"=>"success","data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { error_log("Case 16 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 16: ".$e->getMessage());     http_response_code(400);  echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 17 : POST — un type de budget par id ───────────────────────────────
    case 17:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            if (empty($data['id'])) throw new Exception("Champ requis manquant : id.");
            $id=(int)$data['id'];
            $stmt=$bdBASI->prepare("SELECT id, nom FROM typeBudget WHERE id=?");
            $stmt->execute([$id]);
            $result=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) { http_response_code(404); echo json_encode(["status"=>"error","message"=>"Type de budget non trouvé."]); exit; }
            echo json_encode(["status"=>"success","data"=>$result]);
        } catch (PDOException $e) { error_log("Case 17 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 17: ".$e->getMessage());     http_response_code(400);  echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ═══════════════════════════════════════════════════════════════════════════════
// CASES 18-31 : MODULE LIGNES DE BUDGET — INVESTISSEMENT UNIQUEMENT
// budgetId transmis en token chiffré → décrypté par tokendecrypt()
// ═══════════════════════════════════════════════════════════════════════════════

// ─── CASE 18 : POST — détails d'un budget ────────────────────────────────────
// Body JSON : { "budgetId": "<token>" }
    case 18:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("Champ requis manquant : budgetId.");
            $id=$basiController->tokendecrypt($data['budgetId']);
            $stmt=$bdBASI->prepare("
                SELECT b.*, tb.nom AS type_budget_nom, tb.id AS type_budget_id, d.nom_direction
                FROM budget b
                JOIN typeBudget tb ON b.type_budget_id=tb.id
                LEFT JOIN direction d ON b.direction_id=d.id
                WHERE b.id=:id AND b.direction_id=:direction_id AND b.statut!='Supprimer'
                LIMIT 1
            ");
            $stmt->bindValue(':id',          $id,               PDO::PARAM_INT);
            $stmt->bindValue(':direction_id',$sessionDirection, PDO::PARAM_INT);
            $stmt->execute();
            $budget=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$budget) { http_response_code(404); echo json_encode(['status'=>'error','message'=>'Budget non trouvé ou accès non autorisé.']); exit; }
            echo json_encode(['status'=>'success','donnees'=>$budget]);
        } catch (PDOException $e) { error_log("Case 18 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 18: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 19 : POST — liste des lignes d'un budget (investissement) ──────────
// Body JSON : { "budgetId": "<token>", "id_type_budget_investissement"?: N }
    case 19:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("Champ requis manquant : budgetId.");
            $budgetId=$basiController->tokendecrypt($data['budgetId']);
            $filtreNature=isset($data['id_type_budget_investissement'])&&$data['id_type_budget_investissement']!==''
                ?(int)$data['id_type_budget_investissement']:null;

            // Vérifier appartenance direction + type investissement
            $chk=$bdBASI->prepare("SELECT COUNT(*) FROM budget WHERE id=? AND direction_id=? AND type_budget_id=? AND statut!='Supprimer'");
            $chk->execute([$budgetId,(int)$sessionDirection,TYPE_BUDGET_INVESTISSEMENT]);
            if ($chk->fetchColumn()==0) throw new Exception("Budget non trouvé ou accès non autorisé.",403);

            $sql="SELECT lb.id, lb.budget_id, lb.service_id, lb.id_type_budget_investissement,
                         tn.nom AS nature_nom, tn.categorie AS nature_categorie,
                         r.nom_rubrique AS categorie_nom, sr.nom_sous_rubrique AS sous_categorie_nom,
                         s.nom_services AS service_nom,
                         lb.designation, lb.description, lb.periode_d_utilisation,
                         lb.quantite, lb.prix_unitaire, lb.montant_total, lu.unite, lb.statut,lb.verrouiller
                  FROM ligneBudget lb
                  LEFT JOIN type_budget_investissement tn ON lb.id_type_budget_investissement=tn.id
                  LEFT JOIN rubrique     r  ON lb.rubrique_id     =r.id
                  LEFT JOIN sousRubrique sr ON lb.sous_rubrique_id=sr.id
                  LEFT JOIN services     s  ON lb.service_id      =s.id
                  LEFT JOIN listeUnites  lu ON lb.unite_id        =lu.id
                  WHERE lb.budget_id=:budgetId AND lb.statut!='Inactif'";
            $params=[':budgetId'=>$budgetId];
            if ($filtreNature) { $sql.=" AND lb.id_type_budget_investissement=:nature"; $params[':nature']=$filtreNature; }

            $stmt=$bdBASI->prepare($sql);
            foreach ($params as $k=>$v) $stmt->bindValue($k,(int)$v,PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(['status'=>'success','donnees'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { error_log("Case 19 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 19: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 20 : POST — détails d'une ligne par id ─────────────────────────────
// Body JSON : { "lineId": 5 }
    case 20:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            if (empty($data['lineId'])) throw new Exception("Champ requis manquant : lineId.");
            $lineId=(int)$data['lineId'];
            $stmt=$bdBASI->prepare("
                SELECT lb.*, lb.id_type_budget_investissement,
                       tn.nom AS nature_nom, tn.categorie AS nature_categorie,
                       lb.designation, lb.description, lb.periode_d_utilisation,
                       lb.rubrique_id, lb.sous_rubrique_id
                FROM ligneBudget lb
                LEFT JOIN type_budget_investissement tn ON lb.id_type_budget_investissement=tn.id
                WHERE lb.id=:line_id AND lb.statut!='Inactif'
            ");
            $stmt->bindValue(':line_id',$lineId,PDO::PARAM_INT);
            $stmt->execute();
            $line=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$line) { http_response_code(404); echo json_encode(['status'=>'error','message'=>'Ligne non trouvée.']); exit; }
            echo json_encode(['status'=>'success','data'=>$line]);
        } catch (PDOException $e) { error_log("Case 20 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["status"=>"error","message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 20: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["status"=>"error","message"=>$e->getMessage()]); }
        exit;

// ─── CASE 21 : POST — créer une ligne (investissement) ───────────────────────
    case 21:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();

            // Champs toujours requis
            foreach (['budgetId','id_type_budget_investissement','designation'] as $f) {
                if (empty($data[$f])) throw new Exception("Champ requis manquant : $f");
            }

            $budgetId    = $basiController->tokendecrypt($data['budgetId']);
            $natureId    = (int)$data['id_type_budget_investissement'];
            $designation = trim($data['designation']);
            $description = !empty($data['description']) ? trim($data['description']) : null;
            $periode     = trim($data['periode_d_utilisation'] ?? '');
            $serviceId   = isset($data['service_id']) && $data['service_id'] !== '' ? (int)$data['service_id'] : null;

            // Vérifier appartenance budget + statut modifiable
            $chk=$bdBASI->prepare("SELECT statut FROM budget WHERE id=? AND direction_id=? AND type_budget_id=? AND statut!='Supprimer'");
            $chk->execute([$budgetId,(int)$sessionDirection,TYPE_BUDGET_INVESTISSEMENT]);
            $bRow=$chk->fetch(PDO::FETCH_ASSOC);
            if (!$bRow) throw new Exception("Budget non trouvé ou accès non autorisé.",403);
            if (in_array($bRow['statut'],['Valider','Accepter','Terminer'],true))
                throw new Exception("Impossible d'ajouter une ligne à un budget au statut '{$bRow['statut']}'.");

            // Récupérer la catégorie de la nature
            $natChk=$bdBASI->prepare("SELECT categorie FROM type_budget_investissement WHERE id=? AND statut='Actif'");
            $natChk->execute([$natureId]);
            $nat=$natChk->fetch(PDO::FETCH_ASSOC);
            if (!$nat) throw new Exception("Type d'investissement invalide ou inactif.");
            $natureCategorie = $nat['categorie']; // 'Produit' ou 'Autre'

            // Période obligatoire
            if (empty($periode)) throw new Exception("La période d'utilisation est requise.");

            // ── Initialisation selon la catégorie ─────────────────────────────
            $rubriqueId   = null;
            $sousRubId    = null;
            $quantite     = null;
            $uniteId      = null;
            $prixUnitaire = null;
            $montantTotal = null;
            $newIdP       = null;

            if ($natureCategorie === 'Produit') {
                // ─ Cas 1 : Produit — tous ces champs sont obligatoires ─
                if (empty($data['categorie_id']))     throw new Exception("La rubrique est requise pour un type Produit.");
                if (empty($data['sous_rubrique_id'])) throw new Exception("La sous-rubrique est requise pour un type Produit.");
                if (!isset($data['quantite'])      || $data['quantite']      === '') throw new Exception("La quantité est requise.");
                if (!isset($data['unite_id'])       || $data['unite_id']      === '') throw new Exception("L'unité est requise.");
                if (!isset($data['prix_unitaire'])  || $data['prix_unitaire'] === '') throw new Exception("Le prix unitaire est requis.");

                $rubriqueId   = (int)$data['categorie_id'];
                $sousRubId    = (int)$data['sous_rubrique_id'];
                $quantite     = (int)$data['quantite'];     // int(11) en base
                $uniteId      = (int)$data['unite_id'];
                $prixUnitaire = (float)$data['prix_unitaire'];
                $montantTotal = round($prixUnitaire * $quantite, 2); // calculé côté serveur

                if ($quantite <= 0)     throw new Exception("La quantité doit être supérieure à 0.");
                if ($prixUnitaire <= 0) throw new Exception("Le prix unitaire doit être supérieur à 0.");

            } else {
                // ─ Cas 2 : Autre (Salaire, Vacation, Prestation…) ─
                // rubrique_id, sous_rubrique_id, unite_id, prix_unitaire, id_produit → NULL
                if (!isset($data['nombre'])        || $data['nombre']        === '') throw new Exception("Le nombre est requis.");
                if (!isset($data['montant_total']) || $data['montant_total'] === '') throw new Exception("Le montant total est requis.");

                $quantite     = (int)$data['nombre'];          // int(11) en base
                $montantTotal = (float)$data['montant_total'];

                if ($quantite <= 0)     throw new Exception("Le nombre doit être supérieur à 0.");
                if ($montantTotal <= 0) throw new Exception("Le montant total doit être supérieur à 0.");
                // rubriqueId, sousRubId, uniteId, prixUnitaire restent NULL
            }

            // ── Transaction ───────────────────────────────────────────────────
            $bdBASI->beginTransaction();

            // 1) Si Produit → créer le produit dans product + historique_product
            if ($natureCategorie === 'Produit') {
                $lastP = $bdBASI->query("SELECT MAX(idP) FROM product")->fetchColumn();
                $codeProduit = 'PRD-' . str_pad((int)$lastP + 1, 6, '0', STR_PAD_LEFT);
                $dateProduit = date('Y-m-d H:i:s');

                $stmtP = $bdBASI->prepare("
                    INSERT INTO product
                        (nomproduit, code_produit, id_Sous_categorie, Stock_actuel, Seuil_limite,
                         Total, retrait, id_statut, id_type_product, date_creation)
                    VALUES (?,?,?,0,5,0,0,1,?,?)
                ");
                $stmtP->execute([$designation, $codeProduit, NULL, TYPE_BUDGET_INVESTISSEMENT, $dateProduit]);
                $newIdP = (int)$bdBASI->lastInsertId();

                // Code produit final avec id réel
                $realCode = 'PRD-' . str_pad($newIdP, 6, '0', STR_PAD_LEFT);
                $bdBASI->prepare("UPDATE product SET code_produit=? WHERE idP=?")->execute([$realCode, $newIdP]);

                // Historique produit (structure réelle : idP PK auto, product_id, pas de id_unite)
                $bdBASI->prepare("
                    INSERT INTO historique_product
                        (product_id, nomproduit, code_produit, id_Sous_categorie,
                         Stock_actuel, Seuil_limite, Total, retrait,
                         id_statut, id_type_product, date_creation, motif, dateEnregistrement)
                    VALUES (?,?,?,NULL,0,5,0,0,1,?,?,?,?)
                ")->execute([$newIdP, $designation, $realCode,
                    TYPE_BUDGET_INVESTISSEMENT, $dateProduit,
                    "Insertion depuis budget investissement",date('Y-m-d H:i:s')]);
            }



            // 2) Insérer la ligne budget

            $dateCreation = date('Y-m-d H:i:s');
            $bdBASI->prepare("
                INSERT INTO ligneBudget
                    (budget_id, rubrique_id, sous_rubrique_id, id_type_budget_investissement,
                     designation, description, quantite, unite_id, prix_unitaire, montant_total,
                     service_id, id_produit, date_creation, statut, idStatut,
                     periode_d_utilisation, verrouiller)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'Actif',1,?,0)
            ")->execute([
                $budgetId, $rubriqueId, $sousRubId, $natureId,
                $designation, $description, $quantite, $uniteId, $prixUnitaire, $montantTotal,
                $serviceId, $newIdP,$dateCreation, $periode,
            ]);
            $newId = (int)$bdBASI->lastInsertId();

            // 3) Historique ligne budget (colonnes exactes de historique_ligneBudget)
            $bdBASI->prepare("
                INSERT INTO historique_ligneBudget
                    (ligne_budget_id, budget_id, rubrique_id, sous_rubrique_id,
                     id_type_budget_investissement, designation, description,
                     quantite, unite_id, prix_unitaire, montant_total,
                     service_id, id_produit, date_creation, statut, idStatut,
                     periode_d_utilisation, verrouiller, motif, dateEnregistrement)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Actif',1,?,0,'Insertion',?)
            ")->execute([
                $newId, $budgetId, $rubriqueId, $sousRubId,
                $natureId, $designation, $description,
                $quantite, $uniteId, $prixUnitaire, $montantTotal,
                $serviceId, $newIdP, $dateCreation,$periode,date('Y-m-d H:i:s')
            ]);

            // 4) Passer le budget en "Sauvegarder"
            //  $bdBASI->prepare("UPDATE budget SET statut='Sauvegarder', idStatut=3 WHERE id=?")->execute([$budgetId]);

            $bdBASI->commit();

            echo json_encode(['success'=>true,'new_id'=>$newId,'new_id_produit'=>$newIdP,'message'=>'Ligne créée avec succès.']);
        } catch (PDOException $e) {

            echo $e;
            die;

            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 21 PDO: ".$e->getMessage());
            http_response_code(500);
            echo json_encode(["success"=>false,"message"=>"Erreur base de données."]);
        } catch (Exception $e) {

            echo $e;
            die;
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 21: ".$e->getMessage());
            http_response_code($e->getCode()?:400);
            echo json_encode(["success"=>false,"message"=>$e->getMessage()]);
        }
        exit;
// ─── CASE 22 : POST — modifier une ligne (investissement) ────────────────────
    case 22:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            if (empty($data['lineId'])) throw new Exception("Champ requis manquant : lineId.");
            foreach (['id_type_budget_investissement','designation'] as $f) {
                if (empty($data[$f])) throw new Exception("Champ requis manquant : $f");
            }

            $lineId      = (int)$data['lineId'];
            $natureId    = (int)$data['id_type_budget_investissement'];
            $designation = trim($data['designation']);
            $description = !empty($data['description']) ? trim($data['description']) : null;
            $periode     = trim($data['periode_d_utilisation'] ?? '');
            $serviceId   = isset($data['service_id']) && $data['service_id'] !== '' ? (int)$data['service_id'] : null;

            // Vérifier existence + verrouillage de la ligne
            $chkLine=$bdBASI->prepare("SELECT * FROM ligneBudget WHERE id=? AND statut!='Supprimer'");
            $chkLine->execute([$lineId]);
            $lineRow=$chkLine->fetch(PDO::FETCH_ASSOC);
            if (!$lineRow) throw new Exception("Ligne non trouvée.",404);

            // verrouiller = 1 → interdit
            if ((int)$lineRow['verrouiller'] === 1)
                throw new Exception("Impossible de modifier une ligne verrouillée.",403);

            // Vérifier que le budget est modifiable
            $chkBudget=$bdBASI->prepare("SELECT statut FROM budget WHERE id=? AND direction_id=? AND type_budget_id=? AND statut!='Supprimer'");
            $chkBudget->execute([$lineRow['budget_id'],(int)$sessionDirection,TYPE_BUDGET_INVESTISSEMENT]);
            $bRow=$chkBudget->fetch(PDO::FETCH_ASSOC);
            if (!$bRow) throw new Exception("Accès non autorisé.",403);
            if (in_array($bRow['statut'],['Valider','Accepter','Terminer'],true))
                throw new Exception("Impossible de modifier une ligne d'un budget au statut '{$bRow['statut']}'.");
            // 'Réajuster' (idStatut=9) autorise la modification de lignes

            // Récupérer la catégorie de la nature
            $natChk=$bdBASI->prepare("SELECT categorie FROM type_budget_investissement WHERE id=? AND statut='Actif'");
            $natChk->execute([$natureId]);
            $nat=$natChk->fetch(PDO::FETCH_ASSOC);
            if (!$nat) throw new Exception("Type d'investissement invalide ou inactif.");
            $natureCategorie = $nat['categorie'];

            // Période obligatoire
            if (empty($periode)) throw new Exception("La période d'utilisation est requise.");

            // ── Initialisation des variables ──────────────────────────────────
            $rubriqueId   = null;
            $sousRubId    = null;
            $quantite     = null;
            $uniteId      = null;
            $prixUnitaire = null;
            $montantTotal = null;
            $idProduit    = $lineRow['id_produit'] ?? null; // conserver le produit existant par défaut

            if ($natureCategorie === 'Produit') {
                // ─ Cas 1 : Produit ─
                if (empty($data['categorie_id']))      throw new Exception("La rubrique est requise pour un type Produit.");
                if (empty($data['sous_rubrique_id']))  throw new Exception("La sous-rubrique est requise pour un type Produit.");
                if (!isset($data['quantite'])      || $data['quantite']      === '') throw new Exception("La quantité est requise.");
                if (!isset($data['unite_id'])      || $data['unite_id']      === '') throw new Exception("L'unité est requise.");
                if (!isset($data['prix_unitaire']) || $data['prix_unitaire'] === '') throw new Exception("Le prix unitaire est requis.");

                $rubriqueId   = (int)$data['categorie_id'];
                $sousRubId    = (int)$data['sous_rubrique_id'];
                $quantite     = (int)$data['quantite'];
                $uniteId      = (int)$data['unite_id'];
                $prixUnitaire = (float)$data['prix_unitaire'];
                $montantTotal = round($prixUnitaire * $quantite, 2); // calculé côté serveur

                if ($quantite <= 0)     throw new Exception("La quantité doit être supérieure à 0.");
                if ($prixUnitaire <= 0) throw new Exception("Le prix unitaire doit être supérieur à 0.");

            } else {
                // ─ Cas 2 : Autre — rubrique_id, sous_rubrique_id, unite_id, prix_unitaire → NULL ─
                if (!isset($data['nombre'])        || $data['nombre']        === '') throw new Exception("Le nombre est requis.");
                if (!isset($data['montant_total']) || $data['montant_total'] === '') throw new Exception("Le montant total est requis.");

                $quantite     = (int)$data['nombre'];
                $montantTotal = (float)$data['montant_total'];

                if ($quantite <= 0)     throw new Exception("Le nombre doit être supérieur à 0.");
                if ($montantTotal <= 0) throw new Exception("Le montant total doit être supérieur à 0.");
                // rubriqueId, sousRubId, uniteId, prixUnitaire, idProduit restent NULL
                $idProduit = null;
            }

            $bdBASI->beginTransaction();

            // ── Si type devient Produit et qu'aucun produit n'existait encore → créer ──
            if ($natureCategorie === 'Produit' && empty($idProduit)) {
                $lastP = $bdBASI->query("SELECT MAX(idP) FROM product")->fetchColumn();
                $codeProduit = 'PRD-' . str_pad((int)$lastP + 1, 6, '0', STR_PAD_LEFT);
                $dateProduit = date('Y-m-d H:i:s');

                $bdBASI->prepare("
                    INSERT INTO product
                        (nomproduit, code_produit, id_Sous_categorie, Stock_actuel, Seuil_limite,
                         Total, retrait, id_statut, id_type_product, date_creation)
                    VALUES (?,?,?,0,0,0,0,1,?,?)
                ")->execute([$designation, $codeProduit, $sousRubId, TYPE_BUDGET_INVESTISSEMENT, $dateProduit]);

                $idProduit = (int)$bdBASI->lastInsertId();

                // code_produit définitif avec l'id réel
                $realCode = 'PRD-' . str_pad($idProduit, 6, '0', STR_PAD_LEFT);
                $bdBASI->prepare("UPDATE product SET code_produit=? WHERE idP=?")->execute([$realCode, $idProduit]);

                // Historique produit
                insertHistoriqueProduit($bdBASI, [
                    'idP'               => $idProduit,
                    'nomproduit'        => $designation,
                    'id_Sous_categorie' => $sousRubId,
                    'Stock_actuel'      => 0,
                    'Seuil_limite'      => 0,
                    'Total'             => 0,
                    'retrait'           => 0,
                    'id_statut'         => 1,
                    'id_type_product'   => TYPE_BUDGET_INVESTISSEMENT,
                    'code_produit'      => $realCode,
                    'date_creation'     => $dateProduit,
                ], "Création produit lors de la modification de la ligne $lineId");
            }
            $dateCreation = date('Y-m-d H:i:s');

            // UPDATE ligneBudget — inclut id_produit mis à jour
            $bdBASI->prepare("
                UPDATE ligneBudget SET
                    rubrique_id=?, sous_rubrique_id=?, id_type_budget_investissement=?,
                    designation=?, description=?, quantite=?, unite_id=?,
                    prix_unitaire=?, montant_total=?, service_id=?,
                    periode_d_utilisation=?, id_produit=?
                WHERE id=?
            ")->execute([
                $rubriqueId, $sousRubId, $natureId,
                $designation, $description, $quantite, $uniteId,
                $prixUnitaire, $montantTotal, $serviceId,
                $periode, $idProduit, $lineId,
            ]);

            // Historique snapshot
            $bdBASI->prepare("
                INSERT INTO historique_ligneBudget
                    (ligne_budget_id, budget_id, rubrique_id, sous_rubrique_id,
                     id_type_budget_investissement, designation, description,
                     quantite, unite_id, prix_unitaire, montant_total,
                     service_id, id_produit, date_creation, statut, idStatut,
                     periode_d_utilisation, verrouiller, motif, dateEnregistrement)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Actif',1,?,0,'Modification',?)
            ")->execute([
                $lineId, $lineRow['budget_id'], $rubriqueId, $sousRubId,
                $natureId, $designation, $description,
                $quantite, $uniteId, $prixUnitaire, $montantTotal,
                $serviceId, $idProduit,$dateCreation,
                $periode,date('Y-m-d H:i:s')
            ]);

            $bdBASI->prepare("UPDATE budget SET statut='Sauvegarder', idStatut=3 WHERE id=?")->execute([$lineRow['budget_id']]);
            $bdBASI->commit();

            echo json_encode(['success'=>true,'message'=>'Ligne modifiée avec succès.','id_produit'=>$idProduit]);
        } catch (PDOException $e) {

            echo $e;
            die;
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 22 PDO: ".$e->getMessage());
            http_response_code(500);
            echo json_encode(["success"=>false,"message"=>"Erreur base de données."]);
        } catch (Exception $e) {

            echo $e;
            die;
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

            $chkLine=$bdBASI->prepare("SELECT * FROM ligneBudget WHERE id=? AND statut!='Supprimer'");
            $chkLine->execute([$lineId]);
            $lineRow=$chkLine->fetch(PDO::FETCH_ASSOC);
            if (!$lineRow) throw new Exception("Ligne non trouvée.",404);

            // Vérifier que la ligne n'est pas verrouillée
            if (!empty($lineRow['verrouiller']) && (int)$lineRow['verrouiller'] === 1)
                throw new Exception("Impossible de supprimer une ligne verrouillée.",403);

            $chkBudget=$bdBASI->prepare("SELECT id FROM budget WHERE id=? AND direction_id=? AND type_budget_id=? AND statut!='Supprimer'");
            $chkBudget->execute([$lineRow['budget_id'],(int)$sessionDirection,TYPE_BUDGET_INVESTISSEMENT]);
            if (!$chkBudget->fetch()) throw new Exception("Accès non autorisé.",403);

            // Snapshot de la ligne avant suppression (déjà récupéré dans $lineRow)
            $ligneSnap = $lineRow;

            $bdBASI->beginTransaction();
            $bdBASI->prepare("UPDATE ligneBudget SET statut='Inactif',idStatut=2 WHERE id=?")->execute([$lineId]);

            // Historique snapshot complet avec motif Suppression
            if ($ligneSnap) {
                insertHistoriqueLigneBudget($bdBASI, $ligneSnap,  'Inactif',2, 'Suppression');
            }

            $bdBASI->prepare("UPDATE budget SET statut='Sauvegarder', idStatut=3 WHERE id=?")->execute([$lineRow['budget_id']]);
            $bdBASI->commit();
            echo json_encode(['success'=>true,'message'=>'Ligne supprimée avec succès.']);
        } catch (PDOException $e) { echo $e; die;$bdBASI->rollBack(); error_log("Case 23 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]); }
        catch (Exception    $e) { echo $e; die; if($bdBASI->inTransaction())$bdBASI->rollBack(); error_log("Case 23: ".$e->getMessage()); http_response_code($e->getCode()?:400); echo json_encode(["success"=>false,"message"=>$e->getMessage()]); }
        exit;

// ─── CASE 24 : POST — rubriques (investissement) ─────────────────────────────
// Body JSON : {} — retourne uniquement les rubriques du module investissement
    case 24:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $stmt=$bdBASI->prepare("SELECT id, nom_rubrique AS nom FROM rubrique WHERE statut!=4 ORDER BY nom_rubrique ASC");
            $stmt->execute();
            echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { error_log("Case 24 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 24: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["success"=>false,"message"=>$e->getMessage()]); }
        exit;

// ─── CASE 25 : POST — sous-rubriques selon rubrique ──────────────────────────
// Body JSON : { "categorie_id": 3 }
    case 25:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data       =getJsonBody();
            $categorieId=isset($data['categorie_id'])?(int)$data['categorie_id']:null;
            if (!$categorieId) throw new Exception("Champ requis manquant : categorie_id.");
            $stmt=$bdBASI->prepare("SELECT id, nom_sous_rubrique AS nom FROM sousRubrique WHERE rubrique_id=? AND statut!=4 ORDER BY nom_sous_rubrique ASC");
            $stmt->execute([$categorieId]);
            echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { error_log("Case 25 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 25: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["success"=>false,"message"=>$e->getMessage()]); }
        exit;

// ─── CASE 26 : POST — natures/types d'investissement ─────────────────────────
// (anciennement case 27, regroupé ici car c'est le seul type géré)
// Body JSON : {}
    case 26:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $stmt=$bdBASI->prepare("SELECT id, nom, categorie FROM type_budget_investissement WHERE statut='Actif' ORDER BY categorie DESC, nom ASC");
            $stmt->execute();
            echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { error_log("Case 26 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 26: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["success"=>false,"message"=>$e->getMessage()]); }
        exit;

// ─── CASE 27 : POST — services de la direction connectée ─────────────────────
// Body JSON : { "action": "byDirection" } — utilise la direction de la session
    case 27:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            // La direction est toujours celle de la session — aucun paramètre client accepté
            $stmt=$bdBASI->prepare("SELECT s.id, s.nom_services, s.id_direction, d.nom_direction FROM services s LEFT JOIN direction d ON d.id=s.id_direction WHERE s.id_direction=? ORDER BY s.nom_services ASC");
            $stmt->execute([(int)$sessionDirection]);
            echo json_encode(['success'=>true,'services'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { error_log("Case 27 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 27: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["success"=>false,"message"=>$e->getMessage()]); }
        exit;

// ─── CASE 28 : POST — créer une rubrique (investissement) ────────────────────
// Body JSON : { "nom": "Matériel" }
    case 28:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data=getJsonBody();
            $nom=trim($data['nom']??'');
            if (!$nom) throw new Exception("Le nom de la rubrique est requis.");
            $chk=$bdBASI->prepare("SELECT COUNT(*) FROM rubrique WHERE nom_rubrique=? AND statut!=4");
            $chk->execute([$nom]); if ($chk->fetchColumn()>0) throw new Exception("Cette rubrique existe déjà.");
            $date=(new DateTime())->format('Y-m-d H:i:s');
            $bdBASI->beginTransaction();
            $s=$bdBASI->prepare("INSERT INTO rubrique (nom_rubrique,statut,date_creation,date_derniere_modification,idUtilisateur) VALUES (?,1,?,?,?)");
            $s->execute([$nom,$date,$date,$sessionUserId]);
            $newId=(int)$bdBASI->lastInsertId();
            $bdBASI->commit();
            echo json_encode(['success'=>true,'new_id'=>$newId]);
        } catch (PDOException $e) { error_log("Case 28 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 28: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["success"=>false,"message"=>$e->getMessage()]); }
        exit;

// ─── CASE 29 : POST — créer une sous-rubrique (investissement) ───────────────
// Body JSON : { "nom": "Chaises", "rubrique_id": 3 }

// ─── CASE 29 : POST — créer une sous-rubrique (investissement) ───────────────
// Body JSON : { "nom": "Chaises", "rubrique_id": 3 }
    case 29:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data      = getJsonBody();
            $nom       = trim($data['nom'] ?? '');
            $rubriqueId = isset($data['rubrique_id']) ? (int)$data['rubrique_id'] : null;
            if (!$nom)        throw new Exception("Le nom de la sous-rubrique est requis.");
            if (!$rubriqueId) throw new Exception("rubrique_id requis.");
            $chk = $bdBASI->prepare("SELECT COUNT(*) FROM sousRubrique WHERE nom_sous_rubrique=? AND rubrique_id=? AND statut!=4");
            $chk->execute([$nom, $rubriqueId]);
            if ($chk->fetchColumn() > 0) throw new Exception("Cette sous-rubrique existe déjà.");
            $date = (new DateTime())->format('Y-m-d H:i:s');
            $bdBASI->beginTransaction();
            $s = $bdBASI->prepare("INSERT INTO sousRubrique (rubrique_id, nom_sous_rubrique, statut, date_creation, date_derniere_modification, idUtilisateur) VALUES (?,?,1,?,?,?)");
            $s->execute([$rubriqueId, $nom, $date, $date, $sessionUserId]);
            $newId29 = (int)$bdBASI->lastInsertId();
            $bdBASI->commit();
            echo json_encode(['success' => true, 'new_id' => $newId29]);
        } catch (PDOException $e) { error_log("Case 29 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 29: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["success"=>false,"message"=>$e->getMessage()]); }
        exit;


// ─── CASE 30 : POST — liste des unités (listeUnite) ─────────────────────────
// Body JSON : {}
    case 30:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $stmt=$bdBASI->prepare("SELECT id, unite AS nom FROM listeUnites ORDER BY nom ASC");
            $stmt->execute();
            echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { error_log("Case 30 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]); }
        catch (Exception    $e) { error_log("Case 30: ".$e->getMessage());     http_response_code($e->getCode()?:400); echo json_encode(["success"=>false,"message"=>$e->getMessage()]); }
        exit;


// ─── CASE 31 : POST — créer un produit (investissement type Produit) ──────────
// Body JSON : { "nomproduit": "Chaise Bureau", "sous_rubrique_id": 5 }
    case 31:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data         = getJsonBody();
            $nomproduit   = trim($data['nomproduit']       ?? '');
            $sousRubId    = isset($data['sous_rubrique_id']) ? (int)$data['sous_rubrique_id'] : null;
            $typeBudgetId = TYPE_BUDGET_INVESTISSEMENT; // toujours fixé côté serveur

            if (!$nomproduit) throw new Exception("Le nom du produit est requis.");
            if (!$sousRubId)  throw new Exception("sous_rubrique_id requis.");

            // Vérifier unicité
            $chk = $bdBASI->prepare("SELECT COUNT(*) FROM product WHERE nomproduit=? AND id_Sous_categorie=?");
            $chk->execute([$nomproduit, $sousRubId]);
            if ($chk->fetchColumn() > 0) throw new Exception("Ce produit existe déjà dans cette sous-rubrique.");

            $date = (new DateTime())->format('Y-m-d H:i:s');
            $bdBASI->beginTransaction();
            $s = $bdBASI->prepare("
                INSERT INTO product (nomproduit, id_Sous_categorie, Stock_actuel, Seuil_limite,
                                     Total, retrait, id_statut, id_type_product, date_creation)
                VALUES (?,?,0,0,0,0,1,?,?)
            ");
            $s->execute([$nomproduit, $sousRubId, $typeBudgetId, $date]);
            $newId = (int)$bdBASI->lastInsertId();

            // code_produit
            $codeProduit = 'PRD-'.str_pad($newId, 6, '0', STR_PAD_LEFT);
            $bdBASI->prepare("UPDATE product SET code_produit=? WHERE idP=?")->execute([$codeProduit, $newId]);

            // Historique produit (snapshot complet)
            insertHistoriqueProduit($bdBASI, [
                'idP'               => $newId,
                'nomproduit'        => $nomproduit,
                'id_Sous_categorie' => $sousRubId,
                'Stock_actuel'      => 0,
                'Seuil_limite'      => 0,
                'Total'             => 0,
                'retrait'           => 0,
                'id_statut'         => 1,
                'id_type_product'   => $typeBudgetId,
                'code_produit'      => $codeProduit,
                'id_unite'          => null,
                'date_creation'     => $date,
            ], 'Insertion');

            $bdBASI->commit();
            echo json_encode(['success' => true, 'new_id' => $newId]);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 31 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 31: ".$e->getMessage()); http_response_code($e->getCode()?:400); echo json_encode(["success"=>false,"message"=>$e->getMessage()]);
        }
        exit;

// ─── CASE 32 : POST — valider un budget (En cours/Sauvegarder → Valider) ──────
// Body JSON : { "budgetId": "<token>" }
// Conditions : budget de la direction, statut En cours ou Sauvegarder, ≥1 ligne active.
// Après validation : plus possible d'ajouter ou modifier des lignes.
    case 32:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée. Utilisez POST.",405);
            $data     = getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("Champ requis manquant : budgetId.");
            $budgetId = (int)$basiController->tokendecrypt($data['budgetId']);
            if ($budgetId <= 0) throw new Exception("Token budgetId invalide.",400);

            // Récupérer + vérifier appartenance direction + type investissement
            $get = $bdBASI->prepare("SELECT * FROM budget WHERE id=? AND direction_id=? AND type_budget_id=? AND statut!='Supprimer'");
            $get->execute([$budgetId, (int)$sessionDirection, TYPE_BUDGET_INVESTISSEMENT]);
            $budget = $get->fetch(PDO::FETCH_ASSOC);
            if (!$budget) throw new Exception("Budget non trouvé ou accès non autorisé.",404);

            // Statut doit être En cours ou Sauvegarder
            if (!in_array($budget['statut'], ['En cours','Sauvegarder','Réajuster','Rejeter'], true))
                throw new Exception("Impossible de valider un budget au statut '{$budget['statut']}'. Statut requis : En cours, Sauvegarder ou Réajuster.");

            // Au moins 1 ligne active
            $lns = $bdBASI->prepare("SELECT COUNT(*) FROM ligneBudget WHERE budget_id=? AND statut='Actif'");
            $lns->execute([$budgetId]);
            if ($lns->fetchColumn() == 0)
                throw new Exception("Impossible de valider un budget sans ligne budgétaire active.");

            // Passer à Valider (idStatut=6)
            $bdBASI->beginTransaction();
            $bdBASI->prepare("UPDATE budget SET statut='Valider', idStatut=6 WHERE id=?")->execute([$budgetId]);

            // Historique snapshot
            insertHistoriqueBudget($bdBASI, $budgetId, [
                'annee'          => $budget['annee'],
                'matricule'      => $budget['matricule'],
                'direction_id'   => (int)$sessionDirection,
                'type_budget_id' => TYPE_BUDGET_INVESTISSEMENT,
                'date_creation'  => $budget['date_creation'],
                'statut'         => 'Valider',
                'idStatut'       => 6,
                'plafond'        => $budget['plafond'],
            ], (int)$sessionUserId,date('Y-m-d H:i:s'), 'Validation du budget');

            $bdBASI->commit();
            echo json_encode(['success'=>true,'message'=>"Budget validé avec succès. Aucune modification n'est plus possible."]);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 32 PDO: ".$e->getMessage()); http_response_code(500); echo json_encode(["success"=>false,"message"=>"Erreur base de données."]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            error_log("Case 32: ".$e->getMessage()); http_response_code($e->getCode()?:400); echo json_encode(["success"=>false,"message"=>$e->getMessage()]);
        }
        exit;

// ═══════════════════════════════════════════════════════════════════════════════
// CASES 33-40 : MODULE DEMANDES D'ACHAT / PAIEMENT — chef de service
// Budget      : investissement validé (Accepter) de la direction en session
// Type demande: calculé depuis ligneBudget.id_type_budget_investissement
//               → 1 = Produit = demandes | autre = demande_paiement
// Colonnes type_demande + idTypeDemande stockées en BDD dans demande
//   idTypeDemande : 1 = demandes | 2 = demande_paiement
// ═══════════════════════════════════════════════════════════════════════════════

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
                WHERE b.direction_id = ?
                  AND COALESCE(da.etat_demande,'En création') != 'Supprimée'
                ORDER BY da.date_creation DESC
            ");
            $stmt->execute([$sessionDirection]);
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
                  AND b.direction_id   = ?
                  AND b.annee          IN (?, ?)
                  AND b.type_budget_id = ?
                ORDER BY b.annee ASC, tb.nom ASC
            ");
            $stmt->execute([$sessionDirection, $annee, $annee_suiv, TYPE_BUDGET_INVESTISSEMENT]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['tmp'] = $basiController->tokenencrypt($r['id']);
            }
            unset($r);
            echo json_encode(['success'=>true,'data'=>$rows]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ─── CASE 35 : POST — lignes d'un budget pour sélection (token chiffré) ───────
// Body JSON : { "budgetId": "<token>", "service_id"?: N }
// ── MODIFIÉ : ajout du calcul du montant_demande / montant_restant pour les
//              lignes "Autre" (paiement), symétrique à quantite_restante (achat)
    case 35:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("budgetId requis.");

            $budgetId  = (int)$basiController->tokendecrypt($data['budgetId']);
            if (!$budgetId) throw new Exception("Token invalide.");
            $serviceId = isset($data['service_id']) && $data['service_id']!=='' ? (int)$data['service_id'] : null;

            // Vérifier budget : appartient à la direction + statut Accepter
            $chk = $bdBASI->prepare("SELECT id FROM budget WHERE id=? AND direction_id=? AND statut='Accepter' AND type_budget_id=?");
            $chk->execute([$budgetId, $sessionDirection, TYPE_BUDGET_INVESTISSEMENT]);
            if (!$chk->fetch()) throw new Exception("Budget non trouvé ou accès non autorisé.", 403);

            $sql = "
                SELECT lb.id,
                       lb.id_type_budget_investissement,
                       tbi.nom        AS nature_nom,
                       tbi.categorie  AS nature_categorie,
                       r.nom_rubrique,
                       sr.nom_sous_rubrique,
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
                LEFT JOIN rubrique     r   ON lb.rubrique_id       = r.id
                LEFT JOIN sousRubrique sr  ON lb.sous_rubrique_id  = sr.id
                LEFT JOIN services     s   ON lb.service_id        = s.id
                LEFT JOIN listeUnites  u   ON lb.unite_id          = u.id
                WHERE lb.budget_id = ? AND lb.statut = 'Actif'
            ";
            $params = [$budgetId];
            if ($serviceId) { $sql .= " AND lb.service_id = ?"; $params[] = $serviceId; }
            $sql .= " ORDER BY tbi.categorie DESC, r.nom_rubrique, lb.designation ASC";

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
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ─── CASE 36 : POST — créer une demande ───────────────────────────────────────
// Body JSON : { "budgetId": "<token>", "type_demande": "demandes|demande_paiement" ou idTypeDemande: 1|2 }
// Colonnes type_demande + idTypeDemande (1=achat, 2=paiement) stockées en BDD
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
                  AND direction_id = ?
                  AND statut       = 'Accepter'
                  AND annee        IN (?, ?)
                  AND type_budget_id = ?
            ");
            $chk->execute([$budgetId, $sessionDirection, $annee, $annee_suiv, TYPE_BUDGET_INVESTISSEMENT]);
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

// ─── CASE 37 : POST — ajouter des lignes à une demande ────────────────────────
// Body JSON : { "demandeId": N, "lignes": [ { "lineId": N, "quantite": N }, ... ] }
// ── MODIFIÉ : contrôle serveur du montant_restant pour les lignes "Autre"
//              (paiement), symétrique au contrôle de quantité restante prévu
//              pour les lignes "Produit" (achat)
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
                  AND b.direction_id = ?
                  AND COALESCE(da.etat_demande,'En création') = 'En création'
            ");
            $chkD->execute([$demandeId, $sessionDirection]);
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
                       r.nom_rubrique,
                       sr.nom_sous_rubrique,
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
                LEFT JOIN rubrique     r  ON lb.rubrique_id      = r.id
                LEFT JOIN sousRubrique sr ON lb.sous_rubrique_id = sr.id
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

// ─── CASE 39 : POST — supprimer une demande (soft delete) ─────────────────────
// Body JSON : { "demandeId": N }
// Condition : statut = 'En création' ET aucune ligne active
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
                WHERE da.idD = ? AND b.direction_id = ?
            ");
            $chkD->execute([$demandeId, $sessionDirection]);
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
            $stmt = $bdBASI->prepare("SELECT id, nom_services FROM services WHERE id_direction=? ORDER BY nom_services ASC");
            $stmt->execute([$sessionDirection]);
            echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;


// ─── CASE 41 : POST — détails d'une demande par token chiffré ─────────────────
// Body JSON : { "token": "<token_chiffré_idD>" }
// Retourne infos demande + token chiffré du budget lié (budget_tmp)
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
                WHERE da.idD = ? AND b.direction_id = ?
                  AND COALESCE(da.etat_demande,'En création') NOT IN ('Supprimée')
                LIMIT 1
            ");
            $stmt->execute([$id, $sessionDirection]);
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
// CASES 42-48 : MODULE DEMANDES FONCTIONNEMENT — chef de service
// Même logique que les demandes investissement (cases 33-41)
// mais sur budget type_budget_id = TYPE_BUDGET_FONCTIONNEMENT (1)
// Lignes : catégorie/sous-catégorie/produit (pas rubrique/sous-rubrique)
// type_demande = toujours 'demandes' (id_type=1) pour fonctionnement
// ═══════════════════════════════════════════════════════════════════════════════

// ─── CASE 42 : POST — liste des demandes fonctionnement de la direction ────────
    case 42:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $stmt = $bdBASI->prepare("
                SELECT da.idD                                AS id,
                       da.idUtilisateur,
                       da.date_creation,
                       da.budget_id,
                       COALESCE(da.etat_demande,'En création') AS statut,
                       da.type_demande,
                       da.idTypeDemande,
                       b.annee                                AS budget_annee,
                       tb.nom                                 AS type_budget_nom,
                       (SELECT COUNT(*) FROM demandes_ligne dal
                        WHERE dal.idD = da.idD AND dal.statut != 'supprimer') AS nb_lignes
                FROM demandes da
                LEFT JOIN budget     b  ON da.budget_id     = b.id
                LEFT JOIN typeBudget tb ON b.type_budget_id = tb.id
                WHERE b.direction_id  = ?
                  AND b.type_budget_id = ?
                  AND COALESCE(da.etat_demande,'En création') != 'Supprimée'
                ORDER BY da.date_creation DESC
            ");
            $stmt->execute([$sessionDirection, TYPE_BUDGET_FONCTIONNEMENT]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['tmp'] = $basiController->tokenencrypt($r['id']);
            }
            unset($r);
            echo json_encode(['success'=>true,'data'=>$rows]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ─── CASE 43 : POST — budgets fonctionnement validés (Accepter) ───────────────
// Années : en cours + suivante
    case 43:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $annee      = (int)date('Y');
            $annee_suiv = $annee + 1;
            $stmt = $bdBASI->prepare("
                SELECT b.id, b.annee, b.plafond, b.statut,
                       tb.nom AS type_budget_nom, b.type_budget_id
                FROM budget b
                JOIN typeBudget tb ON b.type_budget_id = tb.id
                WHERE b.statut         = 'Accepter'
                  AND b.direction_id   = ?
                  AND b.annee          IN (?, ?)
                  AND b.type_budget_id = ?
                ORDER BY b.annee ASC
            ");
            $stmt->execute([$sessionDirection, $annee, $annee_suiv, TYPE_BUDGET_FONCTIONNEMENT]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['tmp'] = $basiController->tokenencrypt($r['id']);
            }
            unset($r);
            echo json_encode(['success'=>true,'data'=>$rows]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ─── CASE 44 : POST — lignes fonctionnement d'un budget (token) ───────────────
// Body JSON : { "budgetId": "<token>", "service_id"?: N }
// Lignes fonctionnement : catégorie/sous-catégorie/produit
    case 44:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("budgetId requis.");
            $budgetId  = (int)$basiController->tokendecrypt($data['budgetId']);
            if (!$budgetId) throw new Exception("Token invalide.");
            $serviceId = isset($data['service_id']) && $data['service_id']!=='' ? (int)$data['service_id'] : null;

            $chk = $bdBASI->prepare("SELECT id FROM budget WHERE id=? AND direction_id=? AND statut='Accepter' AND type_budget_id=?");
            $chk->execute([$budgetId, $sessionDirection, TYPE_BUDGET_FONCTIONNEMENT]);
            if (!$chk->fetch()) throw new Exception("Budget fonctionnement non trouvé ou accès non autorisé.", 403);

            $sql = "
                SELECT lb.id,
                       c.nom_categorie,
                       sc.nom_sous_categorie,
                       p.nomproduit,
                       p.code_produit,
                       s.nom_services AS service_nom,
                       lb.designation,
                       lb.description,
                       lb.quantite,
                       lb.prix_unitaire,
                       lb.montant_total,
                       u.unite        AS unite_nom,
                       lb.unite_id,
                       lb.periode_d_utilisation,
                       lb.verrouiller,
                       lb.id_produit,
                       COALESCE((
                           SELECT SUM(dal.quantite)
                           FROM demandes_ligne dal
                           WHERE dal.idLB = lb.id AND dal.statut = 'crée'
                       ), 0) AS quantite_demandee,
                       'demandes' AS type_demande
                FROM ligneBudget lb
                LEFT JOIN product       p  ON lb.id_produit        = p.idP
                LEFT JOIN souscategorie sc ON p.id_Sous_categorie   = sc.id
                LEFT JOIN categorie     c  ON sc.categorie_id       = c.id
                LEFT JOIN services      s  ON lb.service_id         = s.id
                LEFT JOIN listeUnites   u  ON lb.unite_id           = u.id
                WHERE lb.budget_id = ? AND lb.statut = 'Actif'
            ";
            $params = [$budgetId];
            if ($serviceId) { $sql .= " AND lb.service_id = ?"; $params[] = $serviceId; }
            $sql .= " ORDER BY c.nom_categorie, sc.nom_sous_categorie, lb.designation ASC";

            $stmt = $bdBASI->prepare($sql);
            $stmt->execute($params);
            $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($lignes as &$l) {
                $l['quantite_restante'] = max(0, (float)$l['quantite'] - (float)$l['quantite_demandee']);
            }
            unset($l);

            echo json_encode(['success'=>true,'data'=>$lignes,'lineCount'=>count($lignes)]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ─── CASE 45 : POST — créer une demande fonctionnement ────────────────────────
// Body JSON : { "budgetId": "<token>" }
// type_demande = 'demandes', idTypeDemande = 1 (toujours pour fonctionnement)
    case 45:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("budgetId requis.");

            $budgetId = (int)$basiController->tokendecrypt($data['budgetId']);
            if (!$budgetId) throw new Exception("Token invalide.");

            $annee      = (int)date('Y');
            $annee_suiv = $annee + 1;
            $chk = $bdBASI->prepare("
                SELECT * FROM budget
                WHERE id=? AND direction_id=? AND statut='Accepter'
                  AND annee IN (?,?) AND type_budget_id=?
            ");
            $chk->execute([$budgetId, $sessionDirection, $annee, $annee_suiv, TYPE_BUDGET_FONCTIONNEMENT]);
            $budget = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$budget) throw new Exception("Budget fonctionnement non trouvé, non accepté ou hors années autorisées.");

            // Vérifier lignes non épuisées
            $lns = $bdBASI->prepare("
                SELECT COUNT(*) FROM ligneBudget lb
                WHERE lb.budget_id = ? AND lb.statut = 'Actif'
                  AND lb.quantite > COALESCE(
                      (SELECT SUM(dal.quantite) FROM demandes_ligne dal
                       WHERE dal.idLB = lb.id AND dal.statut = 'crée'), 0
                  )
            ");
            $lns->execute([$budgetId]);
            if ((int)$lns->fetchColumn() === 0)
                throw new Exception("Toutes les lignes de ce budget fonctionnement sont épuisées.");

            $bdBASI->beginTransaction();
            $stmt = $bdBASI->prepare("
                INSERT INTO demande
                    (idUtilisateur, date_creation, budget_id,
                     etat_demande, type_demande, idTypeDemande)
                VALUES (?, ?, ?, 'En création', 'demandes', 1)
            ");
            $stmt->execute([$sessionUserId,date('Y-m-d H:i:s'), $budgetId]);
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
                'typeToken'    => $basiController->tokenencrypt('1'),
                'type_demande' => 'demandes',
                'idTypeDemande'=> 1,
                'budget_annee' => $budget['annee'],
                'message'      => 'Demande fonctionnement créée avec succès.',
            ]);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;

// ─── CASE 46 : POST — ajouter lignes fonctionnement à une demande ─────────────
// Identique au case 37 mais pour les lignes fonctionnement (quantité gérée)
    case 46:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['demandeId'])) throw new Exception("demandeId requis.");
            if (empty($data['lignes']) || !is_array($data['lignes'])) throw new Exception("lignes[] requis.");
            $demandeId = (int)$data['demandeId'];

            $chkD = $bdBASI->prepare("
                SELECT da.idD FROM demandes da
                JOIN budget b ON da.budget_id = b.id
                WHERE da.idD=? AND b.direction_id=?
                  AND b.type_budget_id=?
                  AND COALESCE(da.etat_demande,'En création')='En création'
            ");
            $chkD->execute([$demandeId, $sessionDirection, TYPE_BUDGET_FONCTIONNEMENT]);
            if (!$chkD->fetch()) throw new Exception("Demande fonctionnement non trouvée ou non modifiable.", 403);

            $bdBASI->beginTransaction();

            // Date Dakar côté PHP
            $nowDakar = (new DateTime('now', new DateTimeZone('Africa/Dakar')))->format('Y-m-d H:i:s');

            $insertLigne = $bdBASI->prepare("
                INSERT INTO demandes_ligne
                    (idD, idLB, quantite, unite, dateadd, idUtilisateur, date_creation, etat_demande, statut)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'En création', 'crée')
            ");
            $insertHisto = $bdBASI->prepare("
                INSERT INTO historique_demandes_ligne
                    (id_demande_ligne, action, date_action, ancienne_qte, nouvelle_qte)
                VALUES (?, 'insertion', ?, ?, ?)
            ");
            $getUnite = $bdBASI->prepare("SELECT lb.unite_id, u.unite FROM ligneBudget lb LEFT JOIN listeUnites u ON lb.unite_id=u.id WHERE lb.id=?");

            // Vérifier si la ligne existe déjà dans cette demande
            $checkExist46 = $bdBASI->prepare("
                SELECT idDL, quantite
                FROM demandes_ligne
                WHERE idD = ? AND idLB = ? AND statut = 'crée'
                LIMIT 1
            ");

            $nbInseres = 0; $nbMaj = 0;
            foreach ($data['lignes'] as $l) {
                $lineId = (int)($l['lineId']  ?? 0);
                $qte    = max(1, (int)($l['quantite'] ?? 1));
                if (!$lineId) continue;

                $getUnite->execute([$lineId]);
                $lb = $getUnite->fetch(PDO::FETCH_ASSOC);
                $getUnite->closeCursor();
                if (!$lb) continue;

                $uniteId = $lb['unite_id'] ? (int)$lb['unite_id'] : null;

                $checkExist46->execute([$demandeId, $lineId]);
                $existing = $checkExist46->fetch(PDO::FETCH_ASSOC);
                $checkExist46->closeCursor();

                if ($existing) {
                    $bdBASI->prepare("
                        UPDATE demandes_ligne SET quantite = quantite + ? WHERE idDL = ?
                    ")->execute([$qte, (int)$existing['idDL']]);

                    $insertHisto->execute([(int)$existing['idDL'], $nowDakar,(int)$existing['idDL'], (int)$existing['idDL'] + $qte]);
                    $nbMaj++;
                } else {
                    $insertLigne->execute([$demandeId, $lineId, $qte, $uniteId, $nowDakar, $sessionUserId, $nowDakar]);
                    $newLigneId = (int)$bdBASI->lastInsertId();
                    $insertHisto->execute([$newLigneId, $nowDakar,NULL, $qte]);
                    $nbInseres++;
                }
            }
            $bdBASI->prepare("
                INSERT INTO historique_demandes (id_demande, action, date_action)
                VALUES (?, 'modification', ?)
            ")->execute([$demandeId, $nowDakar]);

            $bdBASI->commit();
            echo json_encode(['success'=>true,'nbInseres'=>$nbInseres,'message'=>"{$nbInseres} ligne(s) fonctionnement ajoutée(s) et ."]);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;

// ─── CASE 47 : POST — lignes d'une demande fonctionnement ─────────────────────
    case 47:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['demandeId'])) throw new Exception("demandeId requis.");
            $demandeId = (int)$data['demandeId'];

            $stmt = $bdBASI->prepare("
                SELECT dal.idDL          AS id,
                       dal.idD           AS demande_id,
                       dal.idLB           AS line_id,
                       dal.quantite,
                       dal.unite,
                       dal.etat_demande AS statut_ligne,
                       lb.designation,
                       lb.description,
                       lb.prix_unitaire,
                       lb.montant_total,
                       lb.periode_d_utilisation,
                       c.nom_categorie,
                       sc.nom_sous_categorie,
                       p.nomproduit,
                       p.code_produit,
                       s.nom_services    AS service_nom,
                       'demandes'   AS type_demande
                FROM demandes_ligne dal
                JOIN ligneBudget lb ON dal.idLB = lb.id
                LEFT JOIN product       p  ON lb.id_produit        = p.idP
                LEFT JOIN souscategorie sc ON p.id_Sous_categorie   = sc.id
                LEFT JOIN categorie     c  ON sc.categorie_id       = c.id
                LEFT JOIN services      s  ON lb.service_id         = s.id
                WHERE dal.idD = ? AND dal.statut = 'crée'
                ORDER BY dal.idDL ASC
            ");
            $stmt->execute([$demandeId]);
            $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,'data'=>$lignes,'count'=>count($lignes)]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ─── CASE 48 : POST — détails demande fonctionnement par token ────────────────
    case 48:
        header('Content-Type: application/json; charset=utf-8');
        try {
            if ($_SERVER['REQUEST_METHOD']!=='POST') throw new Exception("Méthode non supportée.",405);
            $data = getJsonBody();
            if (empty($data['token'])) throw new Exception("Token requis.");
            $id = (int)$basiController->tokendecrypt($data['token']);
            if (!$id) throw new Exception("Token invalide.");

            $stmt = $bdBASI->prepare("
                SELECT da.idD           AS id,
                       da.idUtilisateur,
                       da.date_creation,
                       da.budget_id,
                       COALESCE(da.etat_demande,'En création') AS statut,
                       da.type_demande,
                       da.idTypeDemande,
                       b.annee           AS budget_annee,
                       tb.nom            AS type_budget_nom,
                       (SELECT COUNT(*) FROM demandes_ligne dal
                        WHERE dal.idD = da.idD AND dal.statut != 'supprimer') AS nb_lignes
                FROM demandes da
                LEFT JOIN budget     b  ON da.budget_id     = b.id
                LEFT JOIN typeBudget tb ON b.type_budget_id = tb.id
                WHERE da.idD = ? AND b.direction_id = ? AND b.type_budget_id = ?
                  AND COALESCE(da.etat_demande,'En création') NOT IN ('Supprimée')
                LIMIT 1
            ");
            $stmt->execute([$id, $sessionDirection, TYPE_BUDGET_FONCTIONNEMENT]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception("Demande fonctionnement non trouvée ou accès non autorisé.", 404);

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
                WHERE dal.idDL = ? AND dal.statut = 'crée' AND b.direction_id = ?
                LIMIT 1
            ");
            $stmt->execute([$ligneId, $sessionDirection]);
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
                WHERE dal.idDL = ? AND dal.statut = 'crée' AND b.direction_id = ?
                LIMIT 1
            ");
            $stmt->execute([$ligneId, $sessionDirection]);
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
                WHERE da.idD = ? AND b.direction_id = ?
                LIMIT 1
            ");
            $stmt->execute([$demandeId, $sessionDirection]);
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
     * Code du `case 52` à coller DIRECTEMENT dans le switch de routage du
     * contrôleur (compta_basi_controller.php OU chef_service_basi_controller.php
     * selon la page) — pas de fonction séparée, tout est inline.
     *
     * Version PAR LIGNE : pour chaque ligne de la demande, on affiche le nombre
     * de fois où le DRH a effectué une commande d'achat ou de paiement liée à
     * cette ligne, l'évolution (historique de statut) de chacune de ces
     * commandes, et — uniquement pour les achats — si le produit a bien été
     * livré ou non. Pour les paiements, on indique juste "Payé" / "Non payé",
     * sans détail ni tableau.
     *
     * ⚠️ Hypothèses de schéma confirmées par l'utilisateur :
     *   - `demandes` (PK idD) : en-tête de la demande. Colonne de statut réelle
     *     confirmée : `etat_demande` (aliasée `AS statut`).
     *   - `demandes_ligne` (PK idDL) : demandes_ligne.idD → demandes.idD.
     *     Jointure vers ligneBudget/categorie — reconstituée par déduction du
     *     JS (case 38 existant) ; à adapter si votre requête réelle diffère.
     *     ⚠️ Pour la page "Investissement" (chef_service_basi_controller), le
     *     JS utilise `nom_rubrique` au lieu de `nom_categorie` — adapter la
     *     jointure ci-dessous en conséquence si c'est cette page qui est visée.
     *   - Le pont vers les commandes se fait via
     *     passer_achat_et_paiement_ligne.idDL = demandes_ligne.idDL (déjà établi
     *     ailleurs dans le projet).
     *   - `paiement_pap` / `livraison_produit` / `historique_passer_achat_et_paiement`
     *     : tables déjà établies ailleurs dans le projet, réutilisées SANS
     *     modification.
     *   - "Livré" (achat) : déterminé via
     *     passer_achat_et_paiement_ligne.quantite_livree >= quantite_reelle
     *     (déjà la logique utilisée dans le module Livraisons).
     *   - "Payé" (paiement) : EXISTS un paiement_pap actif (montant > 0) pour
     *     la commande.
     *   - $_POST['demandeId'] / lecture JSON : à adapter à votre helper existant
     *     si le contrôleur en a un (inputValue()/inputValueCompta(), etc.) —
     *     voir la note IMPORTANT ci-dessous.
     *
     * ⚠️ IMPORTANT — lecture du corps JSON : ld_post() (JS) envoie toujours son
     * corps en JSON brut (Content-Type: application/json), jamais en
     * application/x-www-form-urlencoded. Vos options 34/36/38/39 fonctionnent
     * déjà avec ld_post() : votre contrôleur possède donc TRÈS PROBABLEMENT DÉJÀ
     * un helper dédié qui lit php://input. Si c'est le cas, UTILISEZ CE HELPER
     * EXISTANT au lieu du file_get_contents('php://input') ci-dessous, pour
     * rester cohérent et éviter toute double lecture du flux. Le code ci-dessous
     * n'est qu'un repli autonome, à adapter.
     *
     * ⚠️ Ce fichier est un EXTRAIT (bloc `case 52: ... break;`) destiné à être
     * collé à l'intérieur d'un `switch` existant — il n'est pas exécutable seul.
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