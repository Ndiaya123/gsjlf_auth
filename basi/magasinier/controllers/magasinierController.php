<?php
/**
 * magasinierController.php
 * Profil "Magasinier" — travaille BON PAR BON : chaque sortie du comptable
 * crée un bon de sortie (bon_sortie_eb) ; le magasinier prépare les produits
 * de ce bon et confirme leur remise physique au demandeur en cliquant "Livré". Cette étape est DISTINCTE de la sortie de
 * stock comptable : "sortie" = décrémentation administrative du stock,
 * "livraison" (ce module) = remise physique effective au demandeur.
 *
 * ⚠️ Hypothèses de schéma (à confirmer / ajuster) :
 *   - Pas de profil Magasinier préexistant — conventions de session et de
 *     structure reprises à l'identique de expressionBesoinController.php
 *     ($_SESSION['tmpIdBASI'] / $_SESSION['tmpMatricule']).
 *   - expression_besoin_produit : quantite_livree et quantite_recue
 *     ajoutées (voir ebp-livraison-reception.sql), en miroir sur
 *     historique_expression_besoin_produit.
 *   - "Restant à livrer" = quantite_sortie - quantite_livree (jamais plus,
 *     le magasinier ne peut remettre que ce qui a déjà été sorti du stock).
 */

// ─── Capture de tout output parasite ─────────────────────────────────────────
ob_start();
session_start();
include_once('../../../bdBASI.php'); // ← ajuster selon la profondeur réelle du fichier
ob_end_clean();

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// ─── Vérification sessions obligatoires ──────────────────────────────────────
function checkSessionMagasinier(): void {
    foreach (['tmpIdBASI', 'tmpMatricule'] as $key) {
        if (empty($_SESSION[$key])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['status'=>'error','code'=>'sessionExpired','message'=>'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }
    }
}
checkSessionMagasinier();

$sessionUserId    = (int)$_SESSION['tmpIdBASI'];
$sessionMatricule = trim($_SESSION['tmpMatricule']);

// ─── Classe contrôleur ────────────────────────────────────────────────────────
class magasinierController extends BDBASI
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
}

// ─── Connexion DB (PDO) ───────────────────────────────────────────────────────
try {
    $BDBASI         = new BDBASI();
    $bdBASI         = $BDBASI->connect();
    $basiController = new magasinierController();
} catch (\Throwable $e) {
    error_log('[Magasinier][Connexion] ' . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Connexion base de données impossible.']);
    exit;
}
if (!$bdBASI) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Connexion base de données impossible.']);
    exit;
}

function getJsonBodyMagasinier(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
function inputValueMagasinier(string $key, $default = null) {
    static $jsonBody = null;
    if ($jsonBody === null) $jsonBody = getJsonBodyMagasinier();
    if (array_key_exists($key, $jsonBody)) return $jsonBody[$key];
    if (isset($_POST[$key]))               return $_POST[$key];
    if (isset($_GET[$key]))                return $_GET[$key];
    return $default;
}
/** Les produits sont des unités entières — jamais de quantité à virgule. */
function estEntierPositif($valeur): bool {
    return is_numeric($valeur) && (float) $valeur == (int) $valeur && (int) $valeur > 0;
}

function erreurSqlMagasinier(string $message = "Erreur lors de l'accès à la base de données."): void {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$message]);
}

/* ═══════════════════════════════════════════════════════════════════════════
   LOGIQUE PARTAGÉE DU CIRCUIT "Expression de besoin" (Fonctionnement) :
   bons de sortie, écarts, clôture de solde et calcul central des statuts.
   Fusionnée directement ici (plus d'include_once vers un fichier séparé) —
   ce bloc est IDENTIQUE dans caisseController.php, magasinierController.php
   et personnelController.php : toute correction doit être reportée dans les
   TROIS fichiers pour rester cohérente.
   cronReceptionPresumee.php contient sa propre copie de ce même bloc (script
   autonome, exécuté en ligne de commande) : quatre copies à tenir à jour.
 *
 * ── Principe ───────────────────────────────────────────────────────────────
 * Les QUANTITÉS sont la source de vérité ; les statuts en sont DÉDUITS par
 * ebw_recalculerStatutBon() / ebw_recalculerStatutEB(), au lieu de
 * transitions codées séparément dans chaque contrôleur. Les compteurs
 * cumulés de expression_besoin_produit (quantite_sortie / quantite_livree /
 * quantite_recue) restent maintenus en parallèle des lignes de bon (mêmes
 * transactions) : ils alimentent les écrans existants.
 *
 * ── Quantités restantes ────────────────────────────────────────────────────
 *   reste à sortir   = validée − annulée − Σ sorties            (par ligne de demande)
 *   reste à livrer   = sortie − livrée                          (par ligne de bon)
 *   reste à recevoir = livrée − reçue − écart − perdue          (par ligne de bon)
 *
 * ── Statuts de la demande (expression_besoin.idStatut) ─────────────────────
 *   1 Brouillon | 2 Soumise | 3 Validée | 4 Rejetée
 *   5 Sortie partielle : au moins une sortie, il reste des quantités à sortir
 *   6 Sortie totale    : plus rien à sortir, il reste à livrer
 *   7 Livrée           : tout ce qui est sorti est remis, reste à confirmer
 *                        (ou un écart est en attente de régularisation)
 *   8 Clôturée         : tout est reçu
 *   9 Clôturée avec solde : comme 8, mais une partie n'a jamais été sortie
 *                        (solde annulé par le comptable, avec motif)
 *  10 Annulée          : solde annulé avant toute sortie
 *
 * ── Statuts d'un bon (bon_sortie_eb.idStatut) ──────────────────────────────
 *   1 Sortie enregistrée | 2 Livraison partielle | 3 Livré
 *   4 Réception partielle | 5 Reçu
 *   6 Écart signalé (le demandeur déclare avoir reçu moins que "livré")
 *   7 Clos avec écart (écart régularisé : correction, retour en stock ou perte)
 */

const EBW_EPS = 0.001;

/** Statuts d'en-tête qui relèvent du circuit calculé (jamais 1, 2, 4). */
const EBW_STATUTS_CALCULES = [3, 5, 6, 7, 8, 9, 10];

/** Résolutions possibles d'un écart de réception. */
const EBW_RESOLUTIONS_ECART = ['correction_livraison', 'retour_stock', 'perte'];

/** Erreur métier : son message est destiné à l'utilisateur. */
class EbwException extends RuntimeException {}

/**
 * Crée l'en-tête d'un bon de sortie (une action de sortie du comptable) et
 * lui attribue son numéro BS-000123. Retourne l'id du bon.
 */
function ebw_creerBonSortie(PDO $bd, int $idEB, int $idUtilisateur, string $dateSortie, string $motif): int {
    $bd->prepare("
        INSERT INTO bon_sortie_eb (idEB, numero_bon, idStatut, idUtilisateurSortie, dateSortie)
        VALUES (?, '', 1, ?, ?)
    ")->execute([$idEB, $idUtilisateur, $dateSortie]);
    $idBS = (int) $bd->lastInsertId();

    $numero = 'BS-' . str_pad((string) $idBS, 6, '0', STR_PAD_LEFT);
    $bd->prepare("UPDATE bon_sortie_eb SET numero_bon = ? WHERE id = ?")->execute([$numero, $idBS]);

    ebw_historiserBon($bd, $idBS, $motif, $idUtilisateur, $dateSortie);
    return $idBS;
}

/** Ajoute une ligne au bon (quantité sortie du stock pour une ligne de demande). */
function ebw_ajouterLigneBon(PDO $bd, int $idBS, int $idEBP, int $idP, float $quantite, int $idUtilisateur, string $motif, string $date): int {
    $bd->prepare("
        INSERT INTO bon_sortie_eb_ligne (idBS, idEBP, idP, quantite_sortie, quantite_livree, quantite_recue)
        VALUES (?, ?, ?, ?, 0, 0)
    ")->execute([$idBS, $idEBP, $idP, $quantite]);
    $idBSL = (int) $bd->lastInsertId();
    ebw_historiserLigneBon($bd, $idBSL, $motif, $idUtilisateur, $date);
    return $idBSL;
}

/** Instantané de l'en-tête du bon (à chaque création / changement de statut). */
function ebw_historiserBon(PDO $bd, int $idBS, string $motif, int $idUtilisateur, string $date): void {
    $bd->prepare("
        INSERT INTO historique_bon_sortie_eb (idBS, idEB, numero_bon, idStatut, motif, idUtilisateur, dateEnregistrement)
        SELECT id, idEB, numero_bon, idStatut, ?, ?, ?
        FROM bon_sortie_eb
        WHERE id = ?
    ")->execute([$motif, $idUtilisateur, $date, $idBS]);
}

/** Instantané d'une ligne de bon (à chaque création / livraison / réception / écart). */
function ebw_historiserLigneBon(PDO $bd, int $idBSL, string $motif, int $idUtilisateur, string $date): void {
    $bd->prepare("
        INSERT INTO historique_bon_sortie_eb_ligne
            (idBSL, idBS, idEBP, idP, quantite_sortie, quantite_livree, quantite_recue, quantite_ecart, quantite_perdue, motif, idUtilisateur, dateEnregistrement)
        SELECT id, idBS, idEBP, idP, quantite_sortie, quantite_livree, quantite_recue, quantite_ecart, quantite_perdue, ?, ?, ?
        FROM bon_sortie_eb_ligne
        WHERE id = ?
    ")->execute([$motif, $idUtilisateur, $date, $idBSL]);
}

/** Instantané d'une ligne de demande (compteurs cumulés + quantité annulée). */
function ebw_historiserEBP(PDO $bd, int $idEBP, string $motif, int $idUtilisateur, string $date): void {
    $bd->prepare("
        INSERT INTO historique_expression_besoin_produit
            (idEBP, idEB, idP, quantite, quantite_reelle, quantite_sortie, quantite_livree, quantite_recue, quantite_annulee, statut, motif, dateEnregistrement, idUtilisateur)
        SELECT id, idEB, idP, quantite, quantite_reelle, quantite_sortie, quantite_livree, quantite_recue, quantite_annulee, statut, ?, ?, ?
        FROM expression_besoin_produit
        WHERE id = ?
    ")->execute([$motif, $date, $idUtilisateur, $idEBP]);
}

/**
 * Déduit le statut d'un bon de ses lignes et le met à jour s'il change.
 * Retourne le statut résultant.
 */
function ebw_recalculerStatutBon(PDO $bd, int $idBS, int $idUtilisateur, string $motif, string $date): int {
    $stmt = $bd->prepare("
        SELECT COALESCE(SUM(quantite_sortie), 0)  AS s,
               COALESCE(SUM(quantite_livree), 0)  AS l,
               COALESCE(SUM(quantite_recue), 0)   AS r,
               COALESCE(SUM(quantite_ecart), 0)   AS e,
               COALESCE(SUM(quantite_perdue), 0)  AS p
        FROM bon_sortie_eb_ligne WHERE idBS = ?
    ");
    $stmt->execute([$idBS]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    $s = (float) $t['s']; $l = (float) $t['l']; $r = (float) $t['r']; $e = (float) $t['e']; $p = (float) $t['p'];

    // Un écart a-t-il déjà été régularisé sur ce bon ? (détermine 5 Reçu / 7 Clos avec écart)
    $stmtR = $bd->prepare("SELECT COUNT(*) FROM ecart_bon_sortie_eb WHERE idBS = ? AND statut = 2");
    $stmtR->execute([$idBS]);
    $aRegularisation = ((int) $stmtR->fetchColumn() > 0) || $p > EBW_EPS;

    if     ($e > EBW_EPS)                                     $nouveau = 6; // Écart signalé
    elseif ($s <= EBW_EPS)                                    $nouveau = $aRegularisation ? 7 : 1; // bon vidé par un retour en stock
    elseif ($r + $p >= $s - EBW_EPS)                          $nouveau = $aRegularisation ? 7 : 5; // Reçu / Clos avec écart
    elseif ($r + $p > EBW_EPS)                                $nouveau = 4; // Réception partielle
    elseif ($l >= $s - EBW_EPS)                               $nouveau = 3; // Livré
    elseif ($l > EBW_EPS)                                     $nouveau = 2; // Livraison partielle
    else                                                      $nouveau = 1; // Sortie enregistrée

    $stmtC = $bd->prepare("SELECT idStatut FROM bon_sortie_eb WHERE id = ?");
    $stmtC->execute([$idBS]);
    $actuel = (int) $stmtC->fetchColumn();

    if ($nouveau !== $actuel) {
        $bd->prepare("UPDATE bon_sortie_eb SET idStatut = ? WHERE id = ?")->execute([$nouveau, $idBS]);
        ebw_historiserBon($bd, $idBS, $motif, $idUtilisateur, $date);
    }
    return $nouveau;
}

/**
 * Déduit le statut de la DEMANDE des quantités de ses lignes actives et le
 * met à jour (avec historique) s'il change. Ne touche jamais aux statuts
 * hors circuit (1 Brouillon, 2 Soumise, 4 Rejetée). Retourne le statut
 * résultant, ou null si la demande n'est pas dans le circuit calculé.
 */
function ebw_recalculerStatutEB(PDO $bd, int $idEB, string $motif, string $date): ?int {
    $stmtE = $bd->prepare("SELECT idStatut FROM expression_besoin WHERE id = ? LIMIT 1");
    $stmtE->execute([$idEB]);
    $actuel = $stmtE->fetchColumn();
    if ($actuel === false || !in_array((int) $actuel, EBW_STATUTS_CALCULES, true)) return null;
    $actuel = (int) $actuel;

    // Niveau demande : reste à sortir net du solde annulé.
    $stmt = $bd->prepare("
        SELECT COALESCE(SUM(quantite_sortie), 0)  AS total_sortie,
               COALESCE(SUM(quantite_annulee), 0) AS total_annulee,
               COALESCE(SUM(GREATEST(COALESCE(quantite_reelle, 0) - quantite_annulee, 0)), 0) AS net_valide,
               COALESCE(SUM(GREATEST(COALESCE(quantite_reelle, 0) - quantite_annulee - quantite_sortie, 0)), 0) AS reste_a_sortir
        FROM expression_besoin_produit
        WHERE idEB = ? AND statut = 1
    ");
    $stmt->execute([$idEB]);
    $q = $stmt->fetch(PDO::FETCH_ASSOC);

    // Niveau bons : reste à livrer / à recevoir / écarts ouverts.
    $stmtB = $bd->prepare("
        SELECT COALESCE(SUM(GREATEST(bl.quantite_sortie - bl.quantite_livree, 0)), 0) AS reste_a_livrer,
               COALESCE(SUM(GREATEST(bl.quantite_livree - bl.quantite_recue - bl.quantite_ecart - bl.quantite_perdue, 0)), 0) AS reste_a_recevoir,
               COALESCE(SUM(bl.quantite_ecart), 0) AS ecart_ouvert
        FROM bon_sortie_eb_ligne bl
        JOIN bon_sortie_eb bs ON bs.id = bl.idBS
        JOIN expression_besoin_produit ebp ON ebp.id = bl.idEBP AND ebp.statut = 1
        WHERE bs.idEB = ?
    ");
    $stmtB->execute([$idEB]);
    $b = $stmtB->fetch(PDO::FETCH_ASSOC);

    if ((float) $q['total_sortie'] <= EBW_EPS) {
        // Rien de sorti : Validée, ou Annulée si tout le solde a été renoncé.
        $nouveau = ((float) $q['net_valide'] <= EBW_EPS && (float) $q['total_annulee'] > EBW_EPS) ? 10 : 3;
    }
    elseif ((float) $q['reste_a_sortir']   > EBW_EPS) $nouveau = 5; // Sortie partielle
    elseif ((float) $b['reste_a_livrer']   > EBW_EPS) $nouveau = 6; // Sortie totale
    elseif ((float) $b['reste_a_recevoir'] > EBW_EPS || (float) $b['ecart_ouvert'] > EBW_EPS) $nouveau = 7; // Livrée
    else $nouveau = ((float) $q['total_annulee'] > EBW_EPS) ? 9 : 8; // Clôturée (avec solde ?)

    if ($nouveau !== $actuel) {
        $bd->prepare("UPDATE expression_besoin SET idStatut = ? WHERE id = ?")->execute([$nouveau, $idEB]);
        $bd->prepare("
            INSERT INTO historique_expression_besoin
                (idEB, nom_expression, idUtilisateur, idDirection, date_creation, idStatut, motif, dateEnregistrement)
            SELECT id, nom_expression, idUtilisateur, idDirection, date_creation, idStatut, ?, ?
            FROM expression_besoin
            WHERE id = ?
        ")->execute([$motif, $date, $idEB]);
    }
    return $nouveau;
}

/**
 * Le demandeur déclare n'avoir PAS reçu $quantite alors que le bon la marque
 * "livrée". Le plafond est le reste à recevoir de la ligne de bon. La
 * quantité passe en écart ouvert (le bon passe à 6 Écart signalé) jusqu'à
 * régularisation par le comptable. Retourne l'id de l'écart.
 */
function ebw_signalerEcart(PDO $bd, int $idBSL, float $quantite, string $commentaire, int $idUtilisateur, string $motif, string $date): int {
    $commentaire = trim($commentaire);
    if ($quantite <= EBW_EPS) throw new EbwException("La quantité d'écart doit être supérieure à zéro.");
    if ($commentaire === '')  throw new EbwException("Un commentaire est obligatoire pour signaler un écart de réception.");

    $stmt = $bd->prepare("
        SELECT bl.id, bl.idBS, bl.idEBP, bl.quantite_livree, bl.quantite_recue, bl.quantite_ecart, bl.quantite_perdue, bs.idEB
        FROM bon_sortie_eb_ligne bl JOIN bon_sortie_eb bs ON bs.id = bl.idBS
        WHERE bl.id = ? FOR UPDATE
    ");
    $stmt->execute([$idBSL]);
    $l = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$l) throw new EbwException("Ligne de bon introuvable.");

    $reste = (float) $l['quantite_livree'] - (float) $l['quantite_recue'] - (float) $l['quantite_ecart'] - (float) $l['quantite_perdue'];
    if ($quantite > $reste + EBW_EPS) throw new EbwException("L'écart déclaré dépasse ce qui restait à confirmer pour cette ligne.");

    $bd->prepare("UPDATE bon_sortie_eb_ligne SET quantite_ecart = quantite_ecart + ? WHERE id = ?")->execute([$quantite, $idBSL]);
    $bd->prepare("
        INSERT INTO ecart_bon_sortie_eb (idBSL, idBS, idEB, idEBP, quantite_ecart, commentaire, idUtilisateurSignale, dateSignalement, statut)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
    ")->execute([$idBSL, $l['idBS'], $l['idEB'], $l['idEBP'], $quantite, $commentaire, $idUtilisateur, $date]);
    $idEcart = (int) $bd->lastInsertId();

    ebw_historiserLigneBon($bd, $idBSL, $motif, $idUtilisateur, $date);
    return $idEcart;
}

/**
 * Régularise un écart ouvert (comptable, garant du stock).
 *  - correction_livraison : la livraison avait été enregistrée en trop ; la
 *    quantité redevient "à livrer" (le magasinier doit la remettre).
 *  - retour_stock : les produits n'ont jamais quitté le magasin ; ils sont
 *    remis en stock et redeviennent "à sortir" (le comptable peut ressortir
 *    ou annuler le solde).
 *  - perte : les produits sont perdus ; l'écart est clos, le stock reste
 *    décrémenté.
 */
function ebw_regulariserEcart(PDO $bd, int $idEcart, string $resolution, string $commentaire, int $idUtilisateur, string $matricule, string $date): void {
    $commentaire = trim($commentaire);
    if (!in_array($resolution, EBW_RESOLUTIONS_ECART, true)) throw new EbwException("Résolution invalide.");
    if ($commentaire === '') throw new EbwException("Un commentaire est obligatoire pour régulariser un écart.");

    $stmt = $bd->prepare("SELECT * FROM ecart_bon_sortie_eb WHERE id = ? FOR UPDATE");
    $stmt->execute([$idEcart]);
    $ecart = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ecart || (int) $ecart['statut'] !== 1) throw new EbwException("Écart introuvable ou déjà régularisé.");

    $q = (float) $ecart['quantite_ecart'];
    $stmtL = $bd->prepare("SELECT * FROM bon_sortie_eb_ligne WHERE id = ? FOR UPDATE");
    $stmtL->execute([$ecart['idBSL']]);
    $bl = $stmtL->fetch(PDO::FETCH_ASSOC);
    if (!$bl || (float) $bl['quantite_ecart'] < $q - EBW_EPS) throw new EbwException("Incohérence : la ligne de bon ne porte plus cet écart.");

    $motif = "Écart de réception régularisé — {$resolution} (par $matricule)";

    if ($resolution === 'correction_livraison') {
        $bd->prepare("UPDATE bon_sortie_eb_ligne SET quantite_livree = quantite_livree - ?, quantite_ecart = quantite_ecart - ? WHERE id = ?")
            ->execute([$q, $q, $bl['id']]);
        $bd->prepare("UPDATE expression_besoin_produit SET quantite_livree = quantite_livree - ?, dateEnregistrement = ? WHERE id = ?")
            ->execute([$q, $date, $bl['idEBP']]);
    }
    elseif ($resolution === 'retour_stock') {
        $bd->prepare("UPDATE bon_sortie_eb_ligne SET quantite_sortie = quantite_sortie - ?, quantite_livree = quantite_livree - ?, quantite_ecart = quantite_ecart - ? WHERE id = ?")
            ->execute([$q, $q, $q, $bl['id']]);
        $bd->prepare("UPDATE expression_besoin_produit SET quantite_sortie = quantite_sortie - ?, quantite_livree = quantite_livree - ?, dateEnregistrement = ? WHERE id = ?")
            ->execute([$q, $q, $date, $bl['idEBP']]);
        $bd->prepare("UPDATE product SET Stock_actuel = Stock_actuel + ?, retrait = GREATEST(retrait - ?, 0) WHERE idP = ?")
            ->execute([$q, $q, $bl['idP']]);
        $bd->prepare("
            INSERT INTO historique_product
                (product_id, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, motif, dateEnregistrement)
            SELECT idP, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, ?, ?
            FROM product WHERE idP = ?
        ")->execute([$motif, $date, $bl['idP']]);
    }
    else { // perte
        $bd->prepare("UPDATE bon_sortie_eb_ligne SET quantite_ecart = quantite_ecart - ?, quantite_perdue = quantite_perdue + ? WHERE id = ?")
            ->execute([$q, $q, $bl['id']]);
    }

    $bd->prepare("
        UPDATE ecart_bon_sortie_eb
        SET statut = 2, resolution = ?, commentaireResolution = ?, idUtilisateurResolution = ?, dateResolution = ?
        WHERE id = ?
    ")->execute([$resolution, $commentaire, $idUtilisateur, $date, $idEcart]);

    ebw_historiserLigneBon($bd, (int) $bl['id'], $motif, $idUtilisateur, $date);
    ebw_historiserEBP($bd, (int) $bl['idEBP'], $motif, $idUtilisateur, $date);
    ebw_recalculerStatutBon($bd, (int) $bl['idBS'], $idUtilisateur, $motif, $date);
    ebw_recalculerStatutEB($bd, (int) $ecart['idEB'], $motif, $date);
}

/**
 * Clôture avec solde : le comptable renonce à sortir le reste (rupture
 * durable, besoin disparu). Le reste à sortir de chaque ligne passe en
 * quantite_annulee ; la demande évolue ensuite selon ce qui a déjà été
 * sorti (9 Clôturée avec solde une fois tout reçu, 10 Annulée si rien
 * n'avait été sorti). Motif obligatoire. Retourne [nouveauStatut, soldeTotal].
 */
function ebw_cloturerSolde(PDO $bd, int $idEB, string $commentaire, int $idUtilisateur, string $matricule, string $date): array {
    $commentaire = trim($commentaire);
    if ($commentaire === '') throw new EbwException("Un motif est obligatoire pour clôturer le solde d'une demande.");

    $stmtE = $bd->prepare("SELECT idStatut FROM expression_besoin WHERE id = ? FOR UPDATE");
    $stmtE->execute([$idEB]);
    $statut = $stmtE->fetchColumn();
    if ($statut === false || !in_array((int) $statut, [3, 5], true)) {
        throw new EbwException("Seule une demande Validée ou en Sortie partielle peut voir son solde clôturé.");
    }

    $stmtL = $bd->prepare("
        SELECT id, COALESCE(quantite_reelle, 0) AS reelle, quantite_sortie, quantite_annulee
        FROM expression_besoin_produit WHERE idEB = ? AND statut = 1 FOR UPDATE
    ");
    $stmtL->execute([$idEB]);

    $motif = "Solde annulé par le comptable : {$commentaire} (par $matricule)";
    $total = 0.0;
    foreach ($stmtL->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $solde = max(0.0, (float) $l['reelle'] - (float) $l['quantite_annulee'] - (float) $l['quantite_sortie']);
        if ($solde <= EBW_EPS) continue;
        $bd->prepare("UPDATE expression_besoin_produit SET quantite_annulee = quantite_annulee + ?, dateEnregistrement = ? WHERE id = ?")
            ->execute([$solde, $date, $l['id']]);
        ebw_historiserEBP($bd, (int) $l['id'], $motif, $idUtilisateur, $date);
        $total += $solde;
    }
    if ($total <= EBW_EPS) throw new EbwException("Il n'y a aucun solde à clôturer sur cette demande.");

    $nouveau = ebw_recalculerStatutEB($bd, $idEB, $motif, $date);
    return [$nouveau, $total];
}

/**
 * Réception présumée : toute quantité livrée depuis plus de $delaiJours
 * jours et jamais confirmée ni contestée est réputée reçue. Traitée par
 * demande, chacune dans sa propre transaction (une erreur n'en bloque pas
 * les autres). Le demandeur est l'acteur tracé, le motif précise
 * l'automatisme. Retourne le nombre de lignes de bon régularisées.
 */
function ebw_receptionPresumee(PDO $bd, int $delaiJours, string $date): int {
    $stmt = $bd->prepare("
        SELECT bl.id AS idBSL, bl.idBS, bl.idEBP, bs.idEB, eb.idUtilisateur AS idDemandeur,
               (bl.quantite_livree - bl.quantite_recue - bl.quantite_ecart - bl.quantite_perdue) AS reste
        FROM bon_sortie_eb_ligne bl
        JOIN bon_sortie_eb bs ON bs.id = bl.idBS
        JOIN expression_besoin eb ON eb.id = bs.idEB
        WHERE bl.date_derniere_livraison IS NOT NULL
          AND bl.date_derniere_livraison <= DATE_SUB(?, INTERVAL ? DAY)
          AND (bl.quantite_livree - bl.quantite_recue - bl.quantite_ecart - bl.quantite_perdue) > ?
        ORDER BY bs.idEB, bl.id
    ");
    $stmt->execute([$date, $delaiJours, EBW_EPS]);
    $parDemande = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $parDemande[(int) $r['idEB']][] = $r;

    $motif = "Réception présumée automatiquement (aucune confirmation ni contestation sous $delaiJours jours)";
    $nb = 0;
    foreach ($parDemande as $idEB => $lignes) {
        try {
            $bd->beginTransaction();
            $bons = [];
            foreach ($lignes as $r) {
                $reste = (float) $r['reste'];
                $acteur = (int) $r['idDemandeur'];
                $bd->prepare("UPDATE bon_sortie_eb_ligne SET quantite_recue = quantite_recue + ? WHERE id = ?")->execute([$reste, $r['idBSL']]);
                $bd->prepare("UPDATE expression_besoin_produit SET quantite_recue = quantite_recue + ?, dateEnregistrement = ? WHERE id = ?")->execute([$reste, $date, $r['idEBP']]);
                ebw_historiserLigneBon($bd, (int) $r['idBSL'], $motif, $acteur, $date);
                ebw_historiserEBP($bd, (int) $r['idEBP'], $motif, $acteur, $date);
                $bons[(int) $r['idBS']] = $acteur;
                $nb++;
            }
            foreach ($bons as $idBS => $acteur) ebw_recalculerStatutBon($bd, $idBS, $acteur, $motif, $date);
            ebw_recalculerStatutEB($bd, $idEB, $motif, $date);
            $bd->commit();
        } catch (\Throwable $e) {
            if ($bd->inTransaction()) $bd->rollBack();
            error_log("[ebWorkflow][receptionPresumee] demande $idEB : " . $e->getMessage());
        }
    }
    return $nb;
}

/* ═══════════════════════════════════════════════════════════════════════════
   LOGIQUE PARTAGÉE DU CIRCUIT INVESTISSEMENT (bons de sortie, écarts,
   clôture de solde) — miroir exact du bloc ebw_* (Fonctionnement) ci-dessus,
   adapté au schéma Investissement : pas de quantite_reelle (quantite_demandee
   en tient lieu, il n'y a pas d'étape Validée/Rejetée), pas de idDL.
   Identique dans caisseController.php, magasinierController.php et
   personnelController.php : toute correction doit être reportée dans les
   TROIS fichiers pour rester cohérente.

   ── Statuts de la demande (expression_besoin_investissement.idStatut) ──────
     1 Brouillon | 2 Soumise
     3 Partiellement sorti : au moins une sortie, il reste à sortir
     4 Terminé              : plus rien à sortir, il reste à livrer
     5 Livrée                : tout ce qui est sorti est remis, reste à confirmer
     6 Clôturée               : tout est reçu
     7 Clôturée avec solde    : comme 6, mais une partie n'a jamais été sortie
     8 Annulée                : solde annulé avant toute sortie

   ── Statuts d'un bon (bon_sortie_ebi.idStatut) ──────────────────────────────
     1 Sortie enregistrée | 2 Livraison partielle | 3 Livré
     4 Réception partielle | 5 Reçu | 6 Écart signalé | 7 Clos avec écart
═══════════════════════════════════════════════════════════════════════════ */

const EBWI_EPS = 0.001;
const EBWI_STATUTS_CALCULES = [2, 3, 4, 5, 6, 7, 8]; // 2 (Soumise) inclus, pour permettre un retour en arrière si un retour en stock ramène tout à 0 sorti
const EBWI_RESOLUTIONS_ECART = ['correction_livraison', 'retour_stock', 'perte'];

// EbwException est déjà déclarée dans le bloc Fonctionnement ci-dessus.

function ebwi_creerBonSortie(PDO $bd, int $idEBI, int $idUtilisateur, string $dateSortie, string $motif): int {
    $bd->prepare("
        INSERT INTO bon_sortie_ebi (idEBI, numero_bon, idStatut, idUtilisateurSortie, dateSortie)
        VALUES (?, '', 1, ?, ?)
    ")->execute([$idEBI, $idUtilisateur, $dateSortie]);
    $idBS = (int) $bd->lastInsertId();

    $numero = 'BSI-' . str_pad((string) $idBS, 6, '0', STR_PAD_LEFT);
    $bd->prepare("UPDATE bon_sortie_ebi SET numero_bon = ? WHERE id = ?")->execute([$numero, $idBS]);

    ebwi_historiserBon($bd, $idBS, $motif, $idUtilisateur, $dateSortie);
    return $idBS;
}

function ebwi_ajouterLigneBon(PDO $bd, int $idBS, int $idEBIP, int $idProduit, float $quantite, int $idUtilisateur, string $motif, string $date): int {
    $bd->prepare("
        INSERT INTO bon_sortie_ebi_ligne (idBS, idEBIP, id_produit, quantite_sortie, quantite_livree, quantite_recue)
        VALUES (?, ?, ?, ?, 0, 0)
    ")->execute([$idBS, $idEBIP, $idProduit, $quantite]);
    $idBSL = (int) $bd->lastInsertId();
    ebwi_historiserLigneBon($bd, $idBSL, $motif, $idUtilisateur, $date);
    return $idBSL;
}

function ebwi_historiserBon(PDO $bd, int $idBS, string $motif, int $idUtilisateur, string $date): void {
    $bd->prepare("
        INSERT INTO historique_bon_sortie_ebi (idBS, idEBI, numero_bon, idStatut, motif, idUtilisateur, dateEnregistrement)
        SELECT id, idEBI, numero_bon, idStatut, ?, ?, ?
        FROM bon_sortie_ebi
        WHERE id = ?
    ")->execute([$motif, $idUtilisateur, $date, $idBS]);
}

function ebwi_historiserLigneBon(PDO $bd, int $idBSL, string $motif, int $idUtilisateur, string $date): void {
    $bd->prepare("
        INSERT INTO historique_bon_sortie_ebi_ligne
            (idBSL, idBS, idEBIP, id_produit, quantite_sortie, quantite_livree, quantite_recue, quantite_ecart, quantite_perdue, motif, idUtilisateur, dateEnregistrement)
        SELECT id, idBS, idEBIP, id_produit, quantite_sortie, quantite_livree, quantite_recue, quantite_ecart, quantite_perdue, ?, ?, ?
        FROM bon_sortie_ebi_ligne
        WHERE id = ?
    ")->execute([$motif, $idUtilisateur, $date, $idBSL]);
}

function ebwi_historiserEBIP(PDO $bd, int $idEBIP, string $motif, int $idUtilisateur, string $date): void {
    $bd->prepare("
        INSERT INTO historique_expression_besoin_investissement_produit
            (idEBIP, idEBI, id_produit, quantite_demandee, quantite_sortie, quantite_livree, quantite_recue, quantite_annulee, statut, idUtilisateur, motif, dateEnregistrement)
        SELECT id, idEBI, id_produit, quantite_demandee, quantite_sortie, quantite_livree, quantite_recue, quantite_annulee, statut, ?, ?, ?
        FROM expression_besoin_investissement_produit
        WHERE id = ?
    ")->execute([$idUtilisateur, $motif, $date, $idEBIP]);
}

function ebwi_recalculerStatutBon(PDO $bd, int $idBS, int $idUtilisateur, string $motif, string $date): int {
    $stmt = $bd->prepare("
        SELECT COALESCE(SUM(quantite_sortie), 0) AS s, COALESCE(SUM(quantite_livree), 0) AS l,
               COALESCE(SUM(quantite_recue), 0)  AS r, COALESCE(SUM(quantite_ecart), 0)  AS e,
               COALESCE(SUM(quantite_perdue), 0) AS p
        FROM bon_sortie_ebi_ligne WHERE idBS = ?
    ");
    $stmt->execute([$idBS]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    $s = (float) $t['s']; $l = (float) $t['l']; $r = (float) $t['r']; $e = (float) $t['e']; $p = (float) $t['p'];

    $stmtR = $bd->prepare("SELECT COUNT(*) FROM ecart_bon_sortie_ebi WHERE idBS = ? AND statut = 2");
    $stmtR->execute([$idBS]);
    $aRegularisation = ((int) $stmtR->fetchColumn() > 0) || $p > EBWI_EPS;

    if     ($e > EBWI_EPS)            $nouveau = 6;
    elseif ($s <= EBWI_EPS)            $nouveau = $aRegularisation ? 7 : 1;
    elseif ($r + $p >= $s - EBWI_EPS)  $nouveau = $aRegularisation ? 7 : 5;
    elseif ($r + $p > EBWI_EPS)        $nouveau = 4;
    elseif ($l >= $s - EBWI_EPS)       $nouveau = 3;
    elseif ($l > EBWI_EPS)             $nouveau = 2;
    else                               $nouveau = 1;

    $stmtC = $bd->prepare("SELECT idStatut FROM bon_sortie_ebi WHERE id = ?");
    $stmtC->execute([$idBS]);
    $actuel = (int) $stmtC->fetchColumn();

    if ($nouveau !== $actuel) {
        $bd->prepare("UPDATE bon_sortie_ebi SET idStatut = ? WHERE id = ?")->execute([$nouveau, $idBS]);
        ebwi_historiserBon($bd, $idBS, $motif, $idUtilisateur, $date);
    }
    return $nouveau;
}

function ebwi_recalculerStatutEBI(PDO $bd, int $idEBI, string $motif, string $date): ?int {
    $stmtE = $bd->prepare("SELECT idStatut FROM expression_besoin_investissement WHERE id = ? LIMIT 1");
    $stmtE->execute([$idEBI]);
    $actuel = $stmtE->fetchColumn();
    if ($actuel === false || !in_array((int) $actuel, EBWI_STATUTS_CALCULES, true)) return null;
    $actuel = (int) $actuel;

    $stmt = $bd->prepare("
        SELECT COALESCE(SUM(quantite_sortie), 0)  AS total_sortie,
               COALESCE(SUM(quantite_annulee), 0) AS total_annulee,
               COALESCE(SUM(GREATEST(quantite_demandee - quantite_annulee, 0)), 0) AS net_demande,
               COALESCE(SUM(GREATEST(quantite_demandee - quantite_annulee - quantite_sortie, 0)), 0) AS reste_a_sortir
        FROM expression_besoin_investissement_produit
        WHERE idEBI = ? AND statut = 1
    ");
    $stmt->execute([$idEBI]);
    $q = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmtB = $bd->prepare("
        SELECT COALESCE(SUM(GREATEST(bl.quantite_sortie - bl.quantite_livree, 0)), 0) AS reste_a_livrer,
               COALESCE(SUM(GREATEST(bl.quantite_livree - bl.quantite_recue - bl.quantite_ecart - bl.quantite_perdue, 0)), 0) AS reste_a_recevoir,
               COALESCE(SUM(bl.quantite_ecart), 0) AS ecart_ouvert
        FROM bon_sortie_ebi_ligne bl
        JOIN bon_sortie_ebi bs ON bs.id = bl.idBS
        JOIN expression_besoin_investissement_produit ebip ON ebip.id = bl.idEBIP AND ebip.statut = 1
        WHERE bs.idEBI = ?
    ");
    $stmtB->execute([$idEBI]);
    $b = $stmtB->fetch(PDO::FETCH_ASSOC);

    if ((float) $q['total_sortie'] <= EBWI_EPS) {
        $nouveau = ((float) $q['net_demande'] <= EBWI_EPS && (float) $q['total_annulee'] > EBWI_EPS) ? 8 : 2;
    }
    elseif ((float) $q['reste_a_sortir']   > EBWI_EPS) $nouveau = 3;
    elseif ((float) $b['reste_a_livrer']   > EBWI_EPS) $nouveau = 4;
    elseif ((float) $b['reste_a_recevoir'] > EBWI_EPS || (float) $b['ecart_ouvert'] > EBWI_EPS) $nouveau = 5;
    else $nouveau = ((float) $q['total_annulee'] > EBWI_EPS) ? 7 : 6;

    if ($nouveau !== $actuel) {
        $bd->prepare("UPDATE expression_besoin_investissement SET idStatut = ? WHERE id = ?")->execute([$nouveau, $idEBI]);
        $bd->prepare("
            INSERT INTO historique_expression_besoin_investissement
                (idEBI, nom_expression, idDirection, idUtilisateur, idStatut, motif, dateEnregistrement)
            SELECT id, nom_expression, idDirection, idUtilisateur, idStatut, ?, ?
            FROM expression_besoin_investissement
            WHERE id = ?
        ")->execute([$motif, $date, $idEBI]);
    }
    return $nouveau;
}

function ebwi_signalerEcart(PDO $bd, int $idBSL, float $quantite, string $commentaire, int $idUtilisateur, string $motif, string $date): int {
    $commentaire = trim($commentaire);
    if ($quantite <= EBWI_EPS) throw new EbwException("La quantité d'écart doit être supérieure à zéro.");
    if ($commentaire === '')   throw new EbwException("Un commentaire est obligatoire pour signaler un écart de réception.");

    $stmt = $bd->prepare("
        SELECT bl.id, bl.idBS, bl.idEBIP, bl.quantite_livree, bl.quantite_recue, bl.quantite_ecart, bl.quantite_perdue, bs.idEBI
        FROM bon_sortie_ebi_ligne bl JOIN bon_sortie_ebi bs ON bs.id = bl.idBS
        WHERE bl.id = ? FOR UPDATE
    ");
    $stmt->execute([$idBSL]);
    $l = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$l) throw new EbwException("Ligne de bon introuvable.");

    $reste = (float) $l['quantite_livree'] - (float) $l['quantite_recue'] - (float) $l['quantite_ecart'] - (float) $l['quantite_perdue'];
    if ($quantite > $reste + EBWI_EPS) throw new EbwException("L'écart déclaré dépasse ce qui restait à confirmer pour cette ligne.");

    $bd->prepare("UPDATE bon_sortie_ebi_ligne SET quantite_ecart = quantite_ecart + ? WHERE id = ?")->execute([$quantite, $idBSL]);
    $bd->prepare("
        INSERT INTO ecart_bon_sortie_ebi (idBSL, idBS, idEBI, idEBIP, quantite_ecart, commentaire, idUtilisateurSignale, dateSignalement, statut)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
    ")->execute([$idBSL, $l['idBS'], $l['idEBI'], $l['idEBIP'], $quantite, $commentaire, $idUtilisateur, $date]);
    $idEcart = (int) $bd->lastInsertId();

    ebwi_historiserLigneBon($bd, $idBSL, $motif, $idUtilisateur, $date);
    return $idEcart;
}

function ebwi_regulariserEcart(PDO $bd, int $idEcart, string $resolution, string $commentaire, int $idUtilisateur, string $matricule, string $date): void {
    $commentaire = trim($commentaire);
    if (!in_array($resolution, EBWI_RESOLUTIONS_ECART, true)) throw new EbwException("Résolution invalide.");
    if ($commentaire === '') throw new EbwException("Un commentaire est obligatoire pour régulariser un écart.");

    $stmt = $bd->prepare("SELECT * FROM ecart_bon_sortie_ebi WHERE id = ? FOR UPDATE");
    $stmt->execute([$idEcart]);
    $ecart = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ecart || (int) $ecart['statut'] !== 1) throw new EbwException("Écart introuvable ou déjà régularisé.");

    $q = (float) $ecart['quantite_ecart'];
    $stmtL = $bd->prepare("SELECT * FROM bon_sortie_ebi_ligne WHERE id = ? FOR UPDATE");
    $stmtL->execute([$ecart['idBSL']]);
    $bl = $stmtL->fetch(PDO::FETCH_ASSOC);
    if (!$bl || (float) $bl['quantite_ecart'] < $q - EBWI_EPS) throw new EbwException("Incohérence : la ligne de bon ne porte plus cet écart.");

    $motif = "Écart de réception régularisé — {$resolution} (par $matricule)";

    if ($resolution === 'correction_livraison') {
        $bd->prepare("UPDATE bon_sortie_ebi_ligne SET quantite_livree = quantite_livree - ?, quantite_ecart = quantite_ecart - ? WHERE id = ?")
            ->execute([$q, $q, $bl['id']]);
        $bd->prepare("UPDATE expression_besoin_investissement_produit SET quantite_livree = quantite_livree - ?, dateEnregistrement = ? WHERE id = ?")
            ->execute([$q, $date, $bl['idEBIP']]);
    }
    elseif ($resolution === 'retour_stock') {
        $bd->prepare("UPDATE bon_sortie_ebi_ligne SET quantite_sortie = quantite_sortie - ?, quantite_livree = quantite_livree - ?, quantite_ecart = quantite_ecart - ? WHERE id = ?")
            ->execute([$q, $q, $q, $bl['id']]);
        $bd->prepare("UPDATE expression_besoin_investissement_produit SET quantite_sortie = quantite_sortie - ?, quantite_livree = quantite_livree - ?, dateEnregistrement = ? WHERE id = ?")
            ->execute([$q, $q, $date, $bl['idEBIP']]);
        $bd->prepare("UPDATE product SET Stock_actuel = Stock_actuel + ?, retrait = GREATEST(retrait - ?, 0) WHERE idP = ?")
            ->execute([$q, $q, $bl['id_produit']]);
        $bd->prepare("
            INSERT INTO historique_product
                (product_id, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, motif, dateEnregistrement)
            SELECT idP, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, ?, ?
            FROM product WHERE idP = ?
        ")->execute([$motif, $date, $bl['id_produit']]);
    }
    else { // perte
        $bd->prepare("UPDATE bon_sortie_ebi_ligne SET quantite_ecart = quantite_ecart - ?, quantite_perdue = quantite_perdue + ? WHERE id = ?")
            ->execute([$q, $q, $bl['id']]);
    }

    $bd->prepare("
        UPDATE ecart_bon_sortie_ebi
        SET statut = 2, resolution = ?, commentaireResolution = ?, idUtilisateurResolution = ?, dateResolution = ?
        WHERE id = ?
    ")->execute([$resolution, $commentaire, $idUtilisateur, $date, $idEcart]);

    ebwi_historiserLigneBon($bd, (int) $bl['id'], $motif, $idUtilisateur, $date);
    ebwi_historiserEBIP($bd, (int) $bl['idEBIP'], $motif, $idUtilisateur, $date);
    ebwi_recalculerStatutBon($bd, (int) $bl['idBS'], $idUtilisateur, $motif, $date);
    ebwi_recalculerStatutEBI($bd, (int) $ecart['idEBI'], $motif, $date);
}

function ebwi_cloturerSolde(PDO $bd, int $idEBI, string $commentaire, int $idUtilisateur, string $matricule, string $date): array {
    $commentaire = trim($commentaire);
    if ($commentaire === '') throw new EbwException("Un motif est obligatoire pour clôturer le solde d'une demande.");

    $stmtE = $bd->prepare("SELECT idStatut FROM expression_besoin_investissement WHERE id = ? FOR UPDATE");
    $stmtE->execute([$idEBI]);
    $statut = $stmtE->fetchColumn();
    if ($statut === false || !in_array((int) $statut, [2, 3], true)) {
        throw new EbwException("Seule une demande Soumise ou en Sortie partielle peut voir son solde clôturé.");
    }

    $stmtL = $bd->prepare("
        SELECT id, quantite_demandee, quantite_sortie, quantite_annulee
        FROM expression_besoin_investissement_produit WHERE idEBI = ? AND statut = 1 FOR UPDATE
    ");
    $stmtL->execute([$idEBI]);

    $motif = "Solde annulé par le comptable : {$commentaire} (par $matricule)";
    $total = 0.0;
    foreach ($stmtL->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $solde = max(0.0, (float) $l['quantite_demandee'] - (float) $l['quantite_annulee'] - (float) $l['quantite_sortie']);
        if ($solde <= EBWI_EPS) continue;
        $bd->prepare("UPDATE expression_besoin_investissement_produit SET quantite_annulee = quantite_annulee + ?, dateEnregistrement = ? WHERE id = ?")
            ->execute([$solde, $date, $l['id']]);
        ebwi_historiserEBIP($bd, (int) $l['id'], $motif, $idUtilisateur, $date);
        $total += $solde;
    }
    if ($total <= EBWI_EPS) throw new EbwException("Il n'y a aucun solde à clôturer sur cette demande.");

    $nouveau = ebwi_recalculerStatutEBI($bd, $idEBI, $motif, $date);
    return [$nouveau, $total];
}

function ebwi_receptionPresumee(PDO $bd, int $delaiJours, string $date): int {
    $stmt = $bd->prepare("
        SELECT bl.id AS idBSL, bl.idBS, bl.idEBIP, bs.idEBI, ebi.idUtilisateur AS idDemandeur,
               (bl.quantite_livree - bl.quantite_recue - bl.quantite_ecart - bl.quantite_perdue) AS reste
        FROM bon_sortie_ebi_ligne bl
        JOIN bon_sortie_ebi bs ON bs.id = bl.idBS
        JOIN expression_besoin_investissement ebi ON ebi.id = bs.idEBI
        WHERE bl.date_derniere_livraison IS NOT NULL
          AND bl.date_derniere_livraison <= DATE_SUB(?, INTERVAL ? DAY)
          AND (bl.quantite_livree - bl.quantite_recue - bl.quantite_ecart - bl.quantite_perdue) > ?
        ORDER BY bs.idEBI, bl.id
    ");
    $stmt->execute([$date, $delaiJours, EBWI_EPS]);
    $parDemande = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $parDemande[(int) $r['idEBI']][] = $r;

    $motif = "Réception présumée automatiquement (aucune confirmation ni contestation sous $delaiJours jours)";
    $nb = 0;
    foreach ($parDemande as $idEBI => $lignes) {
        try {
            $bd->beginTransaction();
            $bons = [];
            foreach ($lignes as $r) {
                $reste = (float) $r['reste'];
                $acteur = (int) $r['idDemandeur'];
                $bd->prepare("UPDATE bon_sortie_ebi_ligne SET quantite_recue = quantite_recue + ? WHERE id = ?")->execute([$reste, $r['idBSL']]);
                $bd->prepare("UPDATE expression_besoin_investissement_produit SET quantite_recue = quantite_recue + ?, dateEnregistrement = ? WHERE id = ?")->execute([$reste, $date, $r['idEBIP']]);
                ebwi_historiserLigneBon($bd, (int) $r['idBSL'], $motif, $acteur, $date);
                ebwi_historiserEBIP($bd, (int) $r['idEBIP'], $motif, $acteur, $date);
                $bons[(int) $r['idBS']] = $acteur;
                $nb++;
            }
            foreach ($bons as $idBS => $acteur) ebwi_recalculerStatutBon($bd, $idBS, $acteur, $motif, $date);
            ebwi_recalculerStatutEBI($bd, $idEBI, $motif, $date);
            $bd->commit();
        } catch (\Throwable $e) {
            if ($bd->inTransaction()) $bd->rollBack();
            error_log("[ebwi][receptionPresumee] demande $idEBI : " . $e->getMessage());
        }
    }
    return $nb;
}

// ─── Lecture de l'option ──────────────────────────────────────────────────────
$option = isset($_GET['option']) ? trim($_GET['option']) : '';
if ($option==='' && isset($_POST['option'])) $option = trim($_POST['option']);
if ($option==='') {
    $jsonBodyOption = getJsonBodyMagasinier();
    if (isset($jsonBodyOption['option'])) $option = trim((string)$jsonBodyOption['option']);
}
if ($option==='') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Paramètre option manquant.']);
    exit;
}
$option = (int)$option;

header('Content-Type: application/json; charset=utf-8');

/**
 * Ne conserve que le JSON attendu, en ignorant tout ce qui aurait pu être
 * imprimé avant lui (avertissement PHP, notice, texte parasite...).
 */
function magasinier_nettoyerSortieJson(string $buffer): string {
    $pos = strpos($buffer, '{');
    $posCrochet = strpos($buffer, '[');
    if ($posCrochet !== false && ($pos === false || $posCrochet < $pos)) $pos = $posCrochet;
    return ($pos !== false) ? substr($buffer, $pos) : $buffer;
}
ob_start('magasinier_nettoyerSortieJson');

/* ═══════════════════════════════════════════════════════════════════════════
   ACTIONS
═══════════════════════════════════════════════════════════════════════════ */

/**
 * OPTION 1 — Liste des BONS DE SORTIE à livrer : au moins une ligne dont
 * quantite_sortie > quantite_livree. Visible pour TOUT magasinier, tous
 * demandeurs confondus — pas de filtre par idUtilisateur.
 */
function listerBonsALivrer(PDO $bdBASI, magasinierController $basiController): void {
    try {
        $stmt = $bdBASI->prepare("
            SELECT bs.id, bs.numero_bon, bs.dateSortie, bs.idStatut, eb.nom_expression,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur,
                   (SELECT COUNT(*) FROM bon_sortie_eb_ligne bl
                    WHERE bl.idBS = bs.id AND bl.quantite_sortie > bl.quantite_livree) AS nombre_lignes_a_livrer
            FROM bon_sortie_eb bs
            JOIN expression_besoin eb ON eb.id = bs.idEB
            LEFT JOIN utilisateurs u ON eb.idUtilisateur = u.id
            HAVING nombre_lignes_a_livrer > 0
            ORDER BY bs.dateSortie ASC, bs.id ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['id']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows, 'nombre_total' => count($rows)]);
    } catch (\Throwable $e) {
        error_log('[Magasinier][listerBonsALivrer] ' . $e->getMessage());
        erreurSqlMagasinier('Impossible de charger la liste des bons à livrer.');
    }
}

/**
 * OPTION 2 — Détail d'un bon pour le magasinier : pour chaque ligne, ce qui a
 * été sorti, déjà livré, et ce qu'il reste à livrer (plafond strict =
 * quantite_sortie - quantite_livree DE CE BON).
 */
function detailBonMagasinier(PDO $bdBASI, magasinierController $basiController): void {
    try {
        $token = trim((string) inputValueMagasinier('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idBS = (int) $basiController->tokendecrypt($token);
        if ($idBS <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmtC = $bdBASI->prepare("
            SELECT bs.id, bs.numero_bon, bs.dateSortie, bs.idStatut, eb.nom_expression,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM bon_sortie_eb bs
            JOIN expression_besoin eb ON eb.id = bs.idEB
            LEFT JOIN utilisateurs u ON eb.idUtilisateur = u.id
            WHERE bs.id = ?
            LIMIT 1
        ");
        $stmtC->execute([$idBS]);
        $bon = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$bon) { echo json_encode(['status' => 'error', 'message' => 'Bon de sortie introuvable.']); return; }

        $stmtLignes = $bdBASI->prepare("
            SELECT bl.id AS idBSL, bl.quantite_sortie, bl.quantite_livree, p.nomproduit AS designation
            FROM bon_sortie_eb_ligne bl
            JOIN product p ON bl.idP = p.idP
            WHERE bl.idBS = ?
            ORDER BY bl.id ASC
        ");
        $stmtLignes->execute([$idBS]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

        foreach ($lignes as &$l) {
            $restant = max(0, (float) $l['quantite_sortie'] - (float) $l['quantite_livree']);
            $l['quantite_restante_a_livrer'] = $restant;
            $l['entierement_livree'] = ($restant <= 0.001);
        }
        unset($l);

        $bon['lignes'] = $lignes;
        echo json_encode(['status' => 'success', 'bon' => $bon]);
    } catch (\Throwable $e) {
        error_log('[Magasinier][detailBonMagasinier] ' . $e->getMessage());
        erreurSqlMagasinier('Impossible de charger le détail du bon.');
    }
}

/**
 * OPTION 3 — Confirme la remise physique au demandeur ("Livré") pour un bon.
 * Incrémente quantite_livree de la ligne de bon (et le compteur cumulé de la
 * ligne de demande), plafonné strictement à quantite_sortie de CETTE ligne de
 * bon. Recalcule ensuite le statut du bon puis celui de la demande.
 *
 * Champs attendus : token (du bon), quantites: [{ idBSL, quantite }, ...]
 */
function confirmerLivraisonBon(PDO $bdBASI, magasinierController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueMagasinier('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idBS = (int) $basiController->tokendecrypt($token);
        if ($idBS <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $quantitesSaisies = inputValueMagasinier('quantites', []);
        if (!is_array($quantitesSaisies)) $quantitesSaisies = [];
        $quantitesParLigne = [];
        foreach ($quantitesSaisies as $q) {
            $idBSL = (int) ($q['idBSL'] ?? 0);
            if ($idBSL <= 0) continue;
            $quantitesParLigne[$idBSL] = (float) ($q['quantite'] ?? 0);
        }

        $stmtC = $bdBASI->prepare("SELECT idEB FROM bon_sortie_eb WHERE id = ? LIMIT 1");
        $stmtC->execute([$idBS]);
        $idEB = (int) $stmtC->fetchColumn();
        if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Bon de sortie introuvable.']); return; }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $motif = "Remise physique confirmée par le magasinier (par $sessionMatricule)";

        $bdBASI->beginTransaction();

        $stmtLignes = $bdBASI->prepare("
            SELECT id AS idBSL, idEBP, quantite_sortie, quantite_livree
            FROM bon_sortie_eb_ligne
            WHERE idBS = ?
            FOR UPDATE
        ");
        $stmtLignes->execute([$idBS]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
        if (empty($lignes)) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Aucune ligne pour ce bon de sortie.']);
            return;
        }

        // date_derniere_livraison : point de départ du délai de réception présumée.
        $stmtLivreeBon = $bdBASI->prepare("UPDATE bon_sortie_eb_ligne SET quantite_livree = quantite_livree + ?, date_derniere_livraison = ? WHERE id = ?");
        $stmtLivreeEBP = $bdBASI->prepare("UPDATE expression_besoin_produit SET quantite_livree = quantite_livree + ?, dateEnregistrement = ? WHERE id = ?");

        $auMoinsUneLivraison = false;
        foreach ($lignes as $l) {
            $idBSL = (int) $l['idBSL'];
            if (!isset($quantitesParLigne[$idBSL])) continue;

            $restant = max(0, (float) $l['quantite_sortie'] - (float) $l['quantite_livree']);
            if ($restant <= 0.001) continue;

            $quantiteSaisie = $quantitesParLigne[$idBSL];
            if ($quantiteSaisie <= 0) continue;

            if (!estEntierPositif($quantiteSaisie)) {
                $bdBASI->rollBack();
                echo json_encode(['status' => 'error', 'message' => "Les quantités livrées doivent être des nombres entiers."]);
                return;
            }

            // Garde-fou serveur : jamais plus que ce qui a été sorti dans CE bon.
            if ($quantiteSaisie > $restant + 0.001) {
                $bdBASI->rollBack();
                echo json_encode(['status' => 'error', 'message' => "La quantité saisie dépasse ce qui a été sorti dans ce bon pour au moins une ligne."]);
                return;
            }

            $stmtLivreeBon->execute([$quantiteSaisie, $dateEnregistrement, $idBSL]);
            $stmtLivreeEBP->execute([$quantiteSaisie, $dateEnregistrement, $l['idEBP']]);
            ebw_historiserEBP($bdBASI, (int) $l['idEBP'], $motif, $sessionUserId, $dateEnregistrement);
            ebw_historiserLigneBon($bdBASI, $idBSL, $motif, $sessionUserId, $dateEnregistrement);
            $auMoinsUneLivraison = true;
        }

        if (!$auMoinsUneLivraison) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'message' => "Aucune livraison enregistrée : veuillez saisir au moins une quantité valide."]);
            return;
        }

        // Statuts DÉDUITS des quantités : d'abord le bon, puis la demande.
        ebw_recalculerStatutBon($bdBASI, $idBS, $sessionUserId, $motif, $dateEnregistrement);
        ebw_recalculerStatutEB($bdBASI, $idEB, $motif, $dateEnregistrement);

        $bdBASI->commit();
        echo json_encode(['status' => 'success', 'message' => 'Remise au demandeur enregistrée avec succès.']);
    } catch (EbwException $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Magasinier][confirmerLivraisonBon] ' . $e->getMessage());
        erreurSqlMagasinier("Impossible d'enregistrer la livraison.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Livraison INVESTISSEMENT (miroir exact du module Fonctionnement
   ci-dessus, sur bon_sortie_ebi / bon_sortie_ebi_ligne).
═══════════════════════════════════════════════════════════════════════════ */

/** OPTION 4 — Liste des bons Investissement ayant des lignes à livrer. */
function listerBonsALivrerInvestissement(PDO $bdBASI, magasinierController $basiController): void {
    try {
        $stmt = $bdBASI->prepare("
            SELECT bs.id, bs.numero_bon, bs.dateSortie, bs.idStatut, ebi.nom_expression,
                   d.nom_direction, d.code_direction,
                   (SELECT COUNT(*) FROM bon_sortie_ebi_ligne bl
                    WHERE bl.idBS = bs.id AND bl.quantite_sortie > bl.quantite_livree) AS nombre_lignes_a_livrer
            FROM bon_sortie_ebi bs
            JOIN expression_besoin_investissement ebi ON ebi.id = bs.idEBI
            LEFT JOIN direction d ON ebi.idDirection = d.id
            HAVING nombre_lignes_a_livrer > 0
            ORDER BY bs.dateSortie ASC, bs.id ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['id']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows, 'nombre_total' => count($rows)]);
    } catch (\Throwable $e) {
        error_log('[Magasinier][listerBonsALivrerInvestissement] ' . $e->getMessage());
        erreurSqlMagasinier('Impossible de charger la liste des bons Investissement à livrer.');
    }
}

/** OPTION 5 — Détail d'un bon Investissement pour le magasinier. */
function detailBonMagasinierInvestissement(PDO $bdBASI, magasinierController $basiController): void {
    try {
        $token = trim((string) inputValueMagasinier('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idBS = (int) $basiController->tokendecrypt($token);
        if ($idBS <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmtC = $bdBASI->prepare("
            SELECT bs.id, bs.numero_bon, bs.dateSortie, bs.idStatut, ebi.nom_expression,
                   d.nom_direction, d.code_direction
            FROM bon_sortie_ebi bs
            JOIN expression_besoin_investissement ebi ON ebi.id = bs.idEBI
            LEFT JOIN direction d ON ebi.idDirection = d.id
            WHERE bs.id = ?
            LIMIT 1
        ");
        $stmtC->execute([$idBS]);
        $bon = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$bon) { echo json_encode(['status' => 'error', 'message' => 'Bon de sortie introuvable.']); return; }

        $stmtLignes = $bdBASI->prepare("
            SELECT bl.id AS idBSL, bl.quantite_sortie, bl.quantite_livree, p.nomproduit AS designation
            FROM bon_sortie_ebi_ligne bl
            JOIN product p ON bl.id_produit = p.idP
            WHERE bl.idBS = ?
            ORDER BY bl.id ASC
        ");
        $stmtLignes->execute([$idBS]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

        foreach ($lignes as &$l) {
            $restant = max(0, (float) $l['quantite_sortie'] - (float) $l['quantite_livree']);
            $l['quantite_restante_a_livrer'] = $restant;
            $l['entierement_livree'] = ($restant <= 0.001);
        }
        unset($l);

        $bon['lignes'] = $lignes;
        echo json_encode(['status' => 'success', 'bon' => $bon]);
    } catch (\Throwable $e) {
        error_log('[Magasinier][detailBonMagasinierInvestissement] ' . $e->getMessage());
        erreurSqlMagasinier('Impossible de charger le détail du bon.');
    }
}

/**
 * OPTION 6 — Confirme la remise physique Investissement ("Livré") pour un bon.
 * Champs attendus : token (du bon), quantites: [{ idBSL, quantite }, ...]
 */
function confirmerLivraisonBonInvestissement(PDO $bdBASI, magasinierController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueMagasinier('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idBS = (int) $basiController->tokendecrypt($token);
        if ($idBS <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $quantitesSaisies = inputValueMagasinier('quantites', []);
        if (!is_array($quantitesSaisies)) $quantitesSaisies = [];
        $quantitesParLigne = [];
        foreach ($quantitesSaisies as $q) {
            $idBSL = (int) ($q['idBSL'] ?? 0);
            if ($idBSL <= 0) continue;
            $quantitesParLigne[$idBSL] = (float) ($q['quantite'] ?? 0);
        }

        $stmtC = $bdBASI->prepare("SELECT idEBI FROM bon_sortie_ebi WHERE id = ? LIMIT 1");
        $stmtC->execute([$idBS]);
        $idEBI = (int) $stmtC->fetchColumn();
        if ($idEBI <= 0) { echo json_encode(['status' => 'error', 'message' => 'Bon de sortie introuvable.']); return; }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $motif = "Remise physique confirmée par le magasinier (par $sessionMatricule)";

        $bdBASI->beginTransaction();

        $stmtLignes = $bdBASI->prepare("
            SELECT id AS idBSL, idEBIP, quantite_sortie, quantite_livree
            FROM bon_sortie_ebi_ligne
            WHERE idBS = ?
            FOR UPDATE
        ");
        $stmtLignes->execute([$idBS]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
        if (empty($lignes)) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Aucune ligne pour ce bon de sortie.']);
            return;
        }

        $stmtLivreeBon  = $bdBASI->prepare("UPDATE bon_sortie_ebi_ligne SET quantite_livree = quantite_livree + ?, date_derniere_livraison = ? WHERE id = ?");
        $stmtLivreeEBIP = $bdBASI->prepare("UPDATE expression_besoin_investissement_produit SET quantite_livree = quantite_livree + ?, dateEnregistrement = ? WHERE id = ?");

        $auMoinsUneLivraison = false;
        foreach ($lignes as $l) {
            $idBSL = (int) $l['idBSL'];
            if (!isset($quantitesParLigne[$idBSL])) continue;

            $restant = max(0, (float) $l['quantite_sortie'] - (float) $l['quantite_livree']);
            if ($restant <= 0.001) continue;

            $quantiteSaisie = $quantitesParLigne[$idBSL];
            if ($quantiteSaisie <= 0) continue;

            if (!estEntierPositif($quantiteSaisie)) {
                $bdBASI->rollBack();
                echo json_encode(['status' => 'error', 'message' => "Les quantités livrées doivent être des nombres entiers."]);
                return;
            }

            if ($quantiteSaisie > $restant + 0.001) {
                $bdBASI->rollBack();
                echo json_encode(['status' => 'error', 'message' => "La quantité saisie dépasse ce qui a été sorti dans ce bon pour au moins une ligne."]);
                return;
            }

            $stmtLivreeBon->execute([$quantiteSaisie, $dateEnregistrement, $idBSL]);
            $stmtLivreeEBIP->execute([$quantiteSaisie, $dateEnregistrement, $l['idEBIP']]);
            ebwi_historiserEBIP($bdBASI, (int) $l['idEBIP'], $motif, $sessionUserId, $dateEnregistrement);
            ebwi_historiserLigneBon($bdBASI, $idBSL, $motif, $sessionUserId, $dateEnregistrement);
            $auMoinsUneLivraison = true;
        }

        if (!$auMoinsUneLivraison) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'message' => "Aucune livraison enregistrée : veuillez saisir au moins une quantité valide."]);
            return;
        }

        ebwi_recalculerStatutBon($bdBASI, $idBS, $sessionUserId, $motif, $dateEnregistrement);
        ebwi_recalculerStatutEBI($bdBASI, $idEBI, $motif, $dateEnregistrement);

        $bdBASI->commit();
        echo json_encode(['status' => 'success', 'message' => 'Remise au demandeur enregistrée avec succès.']);
    } catch (EbwException $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Magasinier][confirmerLivraisonBonInvestissement] ' . $e->getMessage());
        erreurSqlMagasinier("Impossible d'enregistrer la livraison.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   ROUTAGE
   1 = listerBonsALivrer
   2 = detailBonMagasinier
   3 = confirmerLivraisonBon
═══════════════════════════════════════════════════════════════════════════ */
try {
    switch ($option) {
        case 1:
            listerBonsALivrer($bdBASI, $basiController);
            break;

        case 2:
            detailBonMagasinier($bdBASI, $basiController);
            break;

        case 3:
            confirmerLivraisonBon($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 4:
            listerBonsALivrerInvestissement($bdBASI, $basiController);
            break;

        case 5:
            detailBonMagasinierInvestissement($bdBASI, $basiController);
            break;

        case 6:
            confirmerLivraisonBonInvestissement($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Option '$option' non reconnue."]);
            exit;
    }
} catch (\Throwable $e) {
    error_log('[Magasinier][Routage] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Une erreur inattendue est survenue.']);
    exit;
}