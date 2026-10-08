<?php
/**
 * caisseController.php
 * Module Comptabilité : gestion des alimentations de caisse.
 *
 * ⚠️ Hypothèses de schéma (à confirmer / ajuster) :
 *   - `caisse_alimentation` (colonnes non détaillées dans la demande, déduites) :
 *       id, numero, montant_total, idCaissier, date_alimentation, idTypeAC,
 *       idStatut, commentaire, idUtilisateur, dateEnregistrement
 *     `numero` : généré à la création selon le même principe que
 *     `nom_commande` pour les commandes ('alimentation_' . date('Ymd_His')),
 *     et ne change plus ensuite (y compris lors d'une modification).
 *     `montant_total` : stocké (NOT NULL), calculé comme la somme des 3
 *     montants (Liquide + Orange Money + Wave) à la création ET recalculé de
 *     la même façon à chaque modification, pour rester synchronisé avec le
 *     détail (caisse_alimentation_ligne).
 *   - `caisse_alimentation_ligne` : id, id_caisse_alimentation, id_mode_reglement,
 *     montant, statut, dateEnregistrement (id supposé auto-incrémenté).
 *   - `historique_caisse_alimentation` : id_caisse_alimentation, numero,
 *     montant_total, commentaire, date_alimentation, idTypeAC, idStatut,
 *     idCaissier, idUtilisateur, motif, dateEnregistrement — une ligne
 *     insérée à chaque création ET modification (instantané complet de
 *     l'en-tête au moment de l'action).
 *   - `historique_caisse_alimentation_ligne` : id_caisse_alimentation_ligne
 *     (FK vers caisse_alimentation_ligne.id), id_caisse_alimentation,
 *     id_mode_reglement, montant, statut, motif, dateEnregistrement — une
 *     ligne par mode de règlement, à chaque création ET modification.
 *   - `arrete_caisse` : id, montant, surplus, perte, date_alimentation,
 *     idCaissier, statut, idComptable, dateConsolidation, idBrouillardCaisse,
 *     dateEnregistrement (id supposé auto-incrémenté).
 *   - `utilisateurs.idRole = 9` identifie un caissier.
 *   - id_mode_reglement : 1 = Liquide, 5 = Orange Money, 4 = Wave (valeurs
 *     fixes fournies, non relues depuis la table mode_reglement ici).
 *   - idTypeAC : 1 = Alimentation initiale, 2 = Approvisionnement.
 *   - idStatut : 1 = En attente, 2 = Confirmée, 3 = Rejetée. Aucune action de
 *     confirmation/rejet n'est demandée dans ce module — seuls la liste, la
 *     création et la modification (d'un enregistrement Rejeté) sont couvertes.
 *     Après modification, idStatut repasse à 1 (En attente) pour re-soumission.
 */

// ─── Capture de tout output parasite ─────────────────────────────────────────
ob_start();
session_start();
include_once('../../../bdBASI.php'); // ← ajuster selon la profondeur réelle du fichier
ob_end_clean();

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);



// ─── Vérification sessions obligatoires ──────────────────────────────────────
function checkSessionCaisse(): void {
    foreach (['tmpIdBASI', 'tmpMatricule'] as $key) {
        if (empty($_SESSION[$key])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['status'=>'error','code'=>'sessionExpired','message'=>'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }
    }
}
checkSessionCaisse();

$sessionUserId    = (int)$_SESSION['tmpIdBASI'];
$sessionMatricule = trim($_SESSION['tmpMatricule']);

// ─── Classe contrôleur ────────────────────────────────────────────────────────
class caisseController extends BDBASI
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
    $BDBASI           = new BDBASI();
    $bdBASI           = $BDBASI->connect();
    $basiController   = new caisseController();
} catch (\Throwable $e) {
    error_log('[Caisse][Connexion] ' . $e->getMessage());
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



define('UPLOAD_DIR_COMMANDES', __DIR__ . '/../../documents'); // ← ajuster selon l'arborescence réelle
define('UPLOAD_URL_COMMANDES', 'http://localhost/personnel/basi/documents');          // ← chemin public correspondant


/**
 * Lit le corps de la requête envoyé en JSON brut (cas des appels fetch()
 * avec Content-Type: application/json). $_POST ne contient rien dans ce cas.
 */
function getJsonBodyCaisse(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Récupère un paramètre en cherchant dans l'ordre : le body JSON brut,
 * $_POST, $_GET — fonctionne quel que soit le mode d'envoi utilisé.
 */
function inputValueCaisse(string $key, $default = null) {
    static $jsonBody = null;
    if ($jsonBody === null) $jsonBody = getJsonBodyCaisse();

    if (array_key_exists($key, $jsonBody)) return $jsonBody[$key];
    if (isset($_POST[$key]))               return $_POST[$key];
    if (isset($_GET[$key]))                return $_GET[$key];
    return $default;
}

/** Les produits sont des unités entières — jamais de quantité à virgule. */
function estEntierPositif($valeur): bool {
    return is_numeric($valeur) && (float) $valeur == (int) $valeur && (int) $valeur > 0;
}

function erreurSqlCaisse(string $message = "Erreur lors de l'accès à la base de données."): void {
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
    $jsonBodyOption = getJsonBodyCaisse();
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

define('UPLOAD_DIR_BON_LIVRAISON', __DIR__ . '/../../documents/commandes'); // ← ajuster
define('UPLOAD_URL_BON_LIVRAISON', 'http://localhost/personnel/basi/documents/commandes');

/* ═══════════════════════════════════════════════════════════════════════════
   HELPERS
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Vrai si un arrêté de caisse existe déjà pour ce caissier à cette date
 * (quel que soit son statut) — condition bloquante pour un nouvel
 * approvisionnement et pour toute modification d'une alimentation.
 */
function arreteCaisseExiste(PDO $bdBASI, int $idCaissier, string $dateAlimentation): bool {
    $stmt = $bdBASI->prepare("
        SELECT id FROM alimentation_arrete_caisse WHERE idCaissier = ? AND date_alimentation = ? LIMIT 1
    ");
    $stmt->execute([$idCaissier, $dateAlimentation]);
    return (bool)$stmt->fetch();
}

/**
 * Vrai si une alimentation initiale (idTypeAC = 1) existe déjà pour ce
 * caissier à cette date — condition requise pour autoriser un
 * approvisionnement (idTypeAC = 2). $excludeId permet d'exclure
 * l'enregistrement en cours de modification (sans effet lors d'une création).
 */
/**
 * Vrai si une alimentation initiale (idTypeAC = 1) existe déjà pour ce
 * caissier à cette date ET qu'elle a été acceptée par le caissier
 * (idStatut = 2, Confirmée) — condition requise pour autoriser un
 * approvisionnement (idTypeAC = 2). Tant que l'alimentation initiale est en
 * attente (1) ou rejetée (3), aucun approvisionnement n'est possible.
 * $excludeId permet d'exclure l'enregistrement en cours de modification
 * (sans effet lors d'une création).
 */
function alimentationInitialeExiste(PDO $bdBASI, int $idCaissier, string $dateAlimentation, ?int $excludeId = null): bool {
    $sql = "SELECT id FROM caisse_alimentation WHERE idCaissier = ? AND date_alimentation = ? AND idTypeAC = 1 AND idStatut = 2";
    $params = [$idCaissier, $dateAlimentation];
    if ($excludeId !== null) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    $sql .= " LIMIT 1";
    $stmt = $bdBASI->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetch();
}

/* ═══════════════════════════════════════════════════════════════════════════
   ACTIONS
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Liste des alimentations de caisse.
 *
 * Paramètres POST/GET :
 *   - anneeDebut, anneeFin : intervalle d'années (défaut : année en cours pour les deux)
 *   - statut : '' (tous, défaut) | '1' | '2' | '3'
 */
function listerAlimentations(PDO $bdBASI, caisseController $basiController): void {
    try {
        $anneeCourante = (int) date('Y');
        $anneeFin   = (int) inputValueCaisse('anneeFin', $anneeCourante);
        if ($anneeFin <= 0) $anneeFin = $anneeCourante;

        // "Année de" est vide par défaut côté formulaire : quand elle n'est pas
        // renseignée, on filtre uniquement sur "Année à" (comportement par
        // défaut = alimentations de l'année en cours seule, comme demandé).
        $anneeDebutBrute = trim((string) inputValueCaisse('anneeDebut', ''));
        $anneeDebut = ($anneeDebutBrute === '') ? $anneeFin : (int) $anneeDebutBrute;
        if ($anneeDebut <= 0) $anneeDebut = $anneeFin;

        // Garde-fou serveur : "Année de" ne doit jamais dépasser "Année à"
        // (déjà validé côté client, mais on ne fait jamais confiance qu'au client).
        if ($anneeDebut > $anneeFin) { [$anneeDebut, $anneeFin] = [$anneeFin, $anneeDebut]; }

        $statutFiltre = trim((string) inputValueCaisse('statut', ''));

        $sql = "
            SELECT
                ca.id,
                ca.numero,
                ca.montant_total,
                ca.commentaire,
                ca.date_alimentation,
                ca.idTypeAC,
                ca.idStatut,
                ca.idCaissier,
                CONCAT(uc.prenom, ' ', uc.nom)            AS caissier,
                ca.idUtilisateur,
                CONCAT(uu.prenom, ' ', uu.nom)            AS utilisateur,
                ca.dateEnregistrement
            FROM caisse_alimentation ca
            JOIN utilisateurs uc ON ca.idCaissier    = uc.id
            JOIN utilisateurs uu ON ca.idUtilisateur = uu.id
            WHERE YEAR(ca.date_alimentation) BETWEEN ? AND ?
        ";
        $params = [$anneeDebut, $anneeFin];

        if ($statutFiltre !== '' && in_array($statutFiltre, ['1', '2', '3'], true)) {
            $sql .= " AND ca.idStatut = ?";
            $params[] = (int) $statutFiltre;
        }

        $sql .= " ORDER BY ca.date_alimentation DESC, ca.id DESC";

        $stmt = $bdBASI->prepare($sql);
        if (!$stmt) throw new \RuntimeException('Requête de liste des alimentations échouée.');
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['id']);
            // Modifiable uniquement si : Rejetée, date = aujourd'hui, et aucun
            // arrêté de caisse n'existe encore pour ce caissier à cette date.
            $r['modifiable'] = (
                (int)$r['idStatut'] === 3
                && $r['date_alimentation'] === date('Y-m-d')
                && !arreteCaisseExiste($bdBASI, (int)$r['idCaissier'], $r['date_alimentation'])
            );
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows]);
    } catch (\Throwable $e) {
        error_log('[Caisse][listerAlimentations] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger la liste des alimentations de caisse.');
    }
}

/**
 * Liste des caissiers (utilisateurs.idRole = 9).
 */
function listerCaissiers(PDO $bdBASI): void {
    try {
        $stmt = $bdBASI->query("
            SELECT id, CONCAT(prenom, ' ', nom) AS nom
            FROM utilisateurs
            WHERE idRole = 9
            ORDER BY nom ASC, prenom ASC
        ");
        if (!$stmt) throw new \RuntimeException('Requête de liste des caissiers échouée.');
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Throwable $e) {
        error_log('[Caisse][listerCaissiers] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger la liste des caissiers.');
    }
}

/**
 * Détail d'une alimentation, pour pré-remplir le formulaire de modification.
 * Paramètre : token (chiffré de caisse_alimentation.id)
 */
function detailAlimentation(PDO $bdBASI, caisseController $basiController): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $id = (int) $basiController->tokendecrypt($token);
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmt = $bdBASI->prepare("
            SELECT ca.id, ca.numero, ca.montant_total, ca.idCaissier, ca.date_alimentation,
                   ca.idTypeAC, ca.idStatut, ca.commentaire, ca.motifRej,
                   CONCAT(uc.prenom, ' ', uc.nom) AS caissier
            FROM caisse_alimentation ca
            JOIN utilisateurs uc ON ca.idCaissier = uc.id
            WHERE ca.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $alimentation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$alimentation) {
            echo json_encode(['status' => 'error', 'message' => 'Alimentation introuvable.']);
            return;
        }

        $stmtLignes = $bdBASI->prepare("
            SELECT id_mode_reglement, montant
            FROM caisse_alimentation_ligne
            WHERE id_caisse_alimentation = ?
        ");
        $stmtLignes->execute([$id]);
        $montants = ['1' => 0, '5' => 0, '4' => 0]; // Liquide / Orange Money / Wave
        foreach ($stmtLignes->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $montants[(string)$ligne['id_mode_reglement']] = (float)$ligne['montant'];
        }

        echo json_encode(['status' => 'success', 'alimentation' => $alimentation, 'montants' => $montants]);
    } catch (\Throwable $e) {
        error_log('[Caisse][detailAlimentation] ' . $e->getMessage());
        erreurSqlCaisse("Impossible de charger le détail de l'alimentation.");
    }
}

/**
 * Crée une alimentation de caisse (initiale ou approvisionnement).
 *
 * Champs attendus :
 *   - idCaissier, dateAlimentation (Y-m-d), idTypeAC (1|2), commentaire
 *   - montantLiquide, montantOM, montantWave
 */
function creerAlimentation(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        $idCaissier      = (int) inputValueCaisse('idCaissier', 0);
        $dateAlimentation = trim((string) inputValueCaisse('dateAlimentation', date('Y-m-d')));
        $idTypeAC        = (int) inputValueCaisse('idTypeAC', 0);
        $commentaire     = trim((string) inputValueCaisse('commentaire', ''));

        $montantLiquide = (float) inputValueCaisse('montantLiquide', 0);
        $montantOM      = (float) inputValueCaisse('montantOM', 0);
        $montantWave    = (float) inputValueCaisse('montantWave', 0);

        if ($idCaissier <= 0) { echo json_encode(['status'=>'error','message'=>'Caissier requis.']); return; }
        if (!in_array($idTypeAC, [1, 2], true)) { echo json_encode(['status'=>'error','message'=>"Type d'alimentation invalide."]); return; }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateAlimentation)) { echo json_encode(['status'=>'error','message'=>'Date invalide.']); return; }
        if ($montantLiquide < 0 || $montantOM < 0 || $montantWave < 0) { echo json_encode(['status'=>'error','message'=>'Les montants ne peuvent pas être négatifs.']); return; }
        if (($montantLiquide + $montantOM + $montantWave) <= 0) { echo json_encode(['status'=>'error','message'=>'Au moins un montant doit être supérieur à 0.']); return; }

        $stmtCaissier = $bdBASI->prepare("SELECT id FROM utilisateurs WHERE id = ? AND idRole = 9 LIMIT 1");
        $stmtCaissier->execute([$idCaissier]);
        if (!$stmtCaissier->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Caissier invalide.']);
            return;
        }

        if ($idTypeAC === 1) {
            // Alimentation initiale : une seule fois par jour pour ce caissier.
            $stmtExiste = $bdBASI->prepare("
                SELECT id FROM caisse_alimentation
                WHERE idCaissier = ? AND date_alimentation = ? AND idTypeAC = 1
                LIMIT 1
            ");
            $stmtExiste->execute([$idCaissier, $dateAlimentation]);
            if ($stmtExiste->fetch()) {
                echo json_encode(['status' => 'error', 'message' => "Une alimentation initiale existe déjà pour ce caissier à cette date."]);
                return;
            }
        } else {
            // Approvisionnement : nécessite qu'une alimentation initiale existe
            // déjà pour ce caissier à cette date...
            if (!alimentationInitialeExiste($bdBASI, $idCaissier, $dateAlimentation)) {
                echo json_encode(['status' => 'error', 'message' => "L'alimentation initiale de ce caissier pour cette date doit d'abord être acceptée par le caissier (statut Confirmée) avant tout approvisionnement."]);
                return;
            }
            // ...et bloqué si un arrêté de caisse existe déjà pour ce caissier
            // à cette date.
            if (arreteCaisseExiste($bdBASI, $idCaissier, $dateAlimentation)) {
                echo json_encode(['status' => 'error', 'message' => "L'arrêté de caisse a déjà été effectué pour ce caissier à cette date : aucun nouvel approvisionnement n'est possible."]);
                return;
            }
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        // Même principe de génération que nom_commande pour les commandes
        // (passerCommande() : 'commande_' . date('Ymd_His')).
        $numero = 'alimentation_' . date('Ymd_His');
        $montantTotal = $montantLiquide + $montantOM + $montantWave;

        $bdBASI->beginTransaction();

        $stmtInsert = $bdBASI->prepare("
            INSERT INTO caisse_alimentation
                (numero, montant_total, idCaissier, date_alimentation, idTypeAC, idStatut, commentaire, idUtilisateur, dateEnregistrement)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)
        ");
        $stmtInsert->execute([$numero, $montantTotal, $idCaissier, $dateAlimentation, $idTypeAC, $commentaire, $sessionUserId, $dateEnregistrement]);
        $idAlimentation = (int) $bdBASI->lastInsertId();

        $motifCreation = "Création de l'alimentation (par $sessionMatricule)";
        $bdBASI->prepare("
            INSERT INTO historique_caisse_alimentation
                (id_caisse_alimentation, numero, montant_total, commentaire, date_alimentation, idTypeAC, idStatut, idCaissier, idUtilisateur, motif, dateEnregistrement)
            SELECT id, numero, montant_total, commentaire, date_alimentation, idTypeAC, idStatut, idCaissier, idUtilisateur, ?, ?
            FROM caisse_alimentation
            WHERE id = ?
        ")->execute([$motifCreation, $dateEnregistrement, $idAlimentation]);

        $stmtLigne = $bdBASI->prepare("
            INSERT INTO caisse_alimentation_ligne (id_caisse_alimentation, id_mode_reglement, montant, statut, dateEnregistrement)
            VALUES (?, ?, ?, 1, ?)
        ");
        $stmtLigneHisto = $bdBASI->prepare("
            INSERT INTO historique_caisse_alimentation_ligne
                (id_caisse_alimentation_ligne, id_caisse_alimentation, id_mode_reglement, montant, statut, motif, dateEnregistrement)
            VALUES (?, ?, ?, ?, 1, ?, ?)
        ");
        // 1 = Liquide, 5 = Orange Money, 4 = Wave — les 3 lignes sont toujours
        // créées, même à 0, pour conserver une structure homogène.
        foreach ([[1, $montantLiquide], [5, $montantOM], [4, $montantWave]] as [$idModeReglement, $montantLigne]) {
            $stmtLigne->execute([$idAlimentation, $idModeReglement, $montantLigne, $dateEnregistrement]);
            $idLigne = (int) $bdBASI->lastInsertId();
            $stmtLigneHisto->execute([$idLigne, $idAlimentation, $idModeReglement, $montantLigne, $motifCreation, $dateEnregistrement]);
        }

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Alimentation de caisse enregistrée avec succès.', 'id' => $idAlimentation, 'numero' => $numero]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Caisse][creerAlimentation] ' . $e->getMessage());
        erreurSqlCaisse("Impossible d'enregistrer l'alimentation de caisse.");
    }
}

/**
 * Modifie une alimentation de caisse. Autorisé uniquement si :
 *   - idStatut = 3 (Rejetée)
 *   - date_alimentation = date du jour
 *   - aucun arrêté de caisse n'existe pour ce caissier à cette date
 *
 * Après modification, idStatut repasse à 1 (En attente).
 *
 * Champs attendus : token, idCaissier, dateAlimentation, idTypeAC,
 * commentaire, montantLiquide, montantOM, montantWave
 */
function modifierAlimentation(PDO $bdBASI, caisseController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') { echo json_encode(['status'=>'error','message'=>'Token manquant.']); return; }
        $id = (int) $basiController->tokendecrypt($token);
        if ($id <= 0) { echo json_encode(['status'=>'error','message'=>'Token invalide.']); return; }

        $idCaissier       = (int) inputValueCaisse('idCaissier', 0);
        $dateAlimentation = trim((string) inputValueCaisse('dateAlimentation', ''));
        $idTypeAC         = (int) inputValueCaisse('idTypeAC', 0);
        $commentaire      = trim((string) inputValueCaisse('commentaire', ''));

        $montantLiquide = (float) inputValueCaisse('montantLiquide', 0);
        $montantOM      = (float) inputValueCaisse('montantOM', 0);
        $montantWave    = (float) inputValueCaisse('montantWave', 0);

        if ($idCaissier <= 0) { echo json_encode(['status'=>'error','message'=>'Caissier requis.']); return; }
        if (!in_array($idTypeAC, [1, 2], true)) { echo json_encode(['status'=>'error','message'=>"Type d'alimentation invalide."]); return; }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateAlimentation)) { echo json_encode(['status'=>'error','message'=>'Date invalide.']); return; }
        if ($montantLiquide < 0 || $montantOM < 0 || $montantWave < 0) { echo json_encode(['status'=>'error','message'=>'Les montants ne peuvent pas être négatifs.']); return; }
        if (($montantLiquide + $montantOM + $montantWave) <= 0) { echo json_encode(['status'=>'error','message'=>'Au moins un montant doit être supérieur à 0.']); return; }

        $stmtC = $bdBASI->prepare("
            SELECT id, idCaissier, date_alimentation, idStatut
            FROM caisse_alimentation
            WHERE id = ?
            LIMIT 1
        ");
        $stmtC->execute([$id]);
        $alimentation = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$alimentation) {
            echo json_encode(['status' => 'error', 'message' => 'Alimentation introuvable.']);
            return;
        }
        if ((int)$alimentation['idStatut'] !== 3) {
            echo json_encode(['status' => 'error', 'message' => 'Seule une alimentation Rejetée peut être modifiée.']);
            return;
        }
        if ($alimentation['date_alimentation'] !== date('Y-m-d')) {
            echo json_encode(['status' => 'error', 'message' => "La modification n'est possible que le jour même de l'alimentation."]);
            return;
        }
        if (arreteCaisseExiste($bdBASI, (int)$alimentation['idCaissier'], $alimentation['date_alimentation'])) {
            echo json_encode(['status' => 'error', 'message' => "L'arrêté de caisse a déjà été effectué pour ce caissier : modification impossible."]);
            return;
        }

        $stmtCaissier = $bdBASI->prepare("SELECT id FROM utilisateurs WHERE id = ? AND idRole = 9 LIMIT 1");
        $stmtCaissier->execute([$idCaissier]);
        if (!$stmtCaissier->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Caissier invalide.']);
            return;
        }

        // La date d'alimentation modifiée doit elle aussi rester le jour même
        // (sinon la modification ne serait de toute façon plus autorisée au
        // prochain chargement de la page).
        if ($dateAlimentation !== date('Y-m-d')) {
            echo json_encode(['status' => 'error', 'message' => "La date d'alimentation doit rester le jour même."]);
            return;
        }

        // Ré-application des mêmes règles métier que la création, mais sur le
        // COUPLE (caissier, date) nouvellement soumis — l'utilisateur peut en
        // effet changer le caissier ou la date pendant la modification.
        if ($idTypeAC === 1) {
            $stmtExiste = $bdBASI->prepare("
                SELECT id FROM caisse_alimentation
                WHERE idCaissier = ? AND date_alimentation = ? AND idTypeAC = 1 AND id != ?
                LIMIT 1
            ");
            $stmtExiste->execute([$idCaissier, $dateAlimentation, $id]);
            if ($stmtExiste->fetch()) {
                echo json_encode(['status' => 'error', 'message' => "Une alimentation initiale existe déjà pour ce caissier à cette date."]);
                return;
            }
        } else {
            if (!alimentationInitialeExiste($bdBASI, $idCaissier, $dateAlimentation, $id)) {
                echo json_encode(['status' => 'error', 'message' => "L'alimentation initiale de ce caissier pour cette date doit d'abord être acceptée par le caissier (statut Confirmée) avant tout approvisionnement."]);
                return;
            }
            if (arreteCaisseExiste($bdBASI, $idCaissier, $dateAlimentation)) {
                echo json_encode(['status' => 'error', 'message' => "L'arrêté de caisse a déjà été effectué pour ce caissier à cette date : modification impossible."]);
                return;
            }
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $montantTotal = $montantLiquide + $montantOM + $montantWave;

        $bdBASI->beginTransaction();

        $bdBASI->prepare("
            UPDATE caisse_alimentation
            SET idCaissier = ?, date_alimentation = ?, idTypeAC = ?, idStatut = 1, commentaire = ?, montant_total = ?
            WHERE id = ?
        ")->execute([$idCaissier, $dateAlimentation, $idTypeAC, $commentaire, $montantTotal, $id]);

        $motifModification = "Modification de l'alimentation rejetée (par $sessionMatricule)";
        $bdBASI->prepare("
            INSERT INTO historique_caisse_alimentation
                (id_caisse_alimentation, numero, montant_total, commentaire, date_alimentation, idTypeAC, idStatut, idCaissier, idUtilisateur, motif, dateEnregistrement)
            SELECT id, numero, montant_total, commentaire, date_alimentation, idTypeAC, idStatut, idCaissier, idUtilisateur, ?, ?
            FROM caisse_alimentation
            WHERE id = ?
        ")->execute([$motifModification, $dateEnregistrement, $id]);

        $stmtUpdateLigne = $bdBASI->prepare("
            UPDATE caisse_alimentation_ligne
            SET montant = ?, statut = 1, dateEnregistrement = ?
            WHERE id_caisse_alimentation = ? AND id_mode_reglement = ?
        ");
        $stmtLigneHisto = $bdBASI->prepare("
            INSERT INTO historique_caisse_alimentation_ligne
                (id_caisse_alimentation_ligne, id_caisse_alimentation, id_mode_reglement, montant, statut, motif, dateEnregistrement)
            SELECT id, id_caisse_alimentation, id_mode_reglement, montant, statut, ?, ?
            FROM caisse_alimentation_ligne
            WHERE id_caisse_alimentation = ? AND id_mode_reglement = ?
        ");
        foreach ([[1, $montantLiquide], [5, $montantOM], [4, $montantWave]] as [$idModeReglement, $montantLigne]) {
            $stmtUpdateLigne->execute([$montantLigne, $dateEnregistrement, $id, $idModeReglement]);
            $stmtLigneHisto->execute([$motifModification, $dateEnregistrement, $id, $idModeReglement]);
        }

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Alimentation de caisse modifiée avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Caisse][modifierAlimentation] ' . $e->getMessage());
        erreurSqlCaisse("Impossible de modifier l'alimentation de caisse.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Liste des paiements (profil Comptable)

   ⚠️ Hypothèses de schéma (mêmes que caisseCaissierController.php) :
   - `paiement_pap` : idPaiement (PK, colonne réelle utilisée : id), idPAP,
     montant, mode_reglement, banque, numero_cheque_virement, recu, id_tranche,
     date_paiement, idCaissier.
   - `historique_paiement_pap` : mêmes colonnes + motif, dateEnregistrement.
   - "Annulation" d'un paiement = montant remis à 0 (pas de suppression),
     avec répercussion sur passer_achat_et_paiement.montant_paye et idStatut,
     et sur tranches.paiement/date_paiement si le paiement était lié à une
     tranche. Toujours historisé.
   - Tous les paiements sont TOUJOURS affichés dans la liste, quel que soit
     l'état de l'arrêt de caisse — confirmé : aucune ligne n'est masquée.
   - Un paiement n'est modifiable (bouton actif) que si l'arrêt de caisse du
     CAISSIER concerné n'a pas déjà été effectué pour la DATE de ce paiement
     précis (pas seulement "aujourd'hui" — un paiement plus ancien reste
     bloqué si l'arrêt de ce jour-là a eu lieu). Seule la modification est
     restreinte, jamais l'affichage.
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Liste des paiements (paiement_pap), filtrable par intervalle de dates.
 * Par défaut : paiements du jour (dateDebut vide → dateFin seule).
 */
function listerPaiements(PDO $bdBASI, caisseController $basiController): void {
    try {
        $dateFin = trim((string) inputValueCaisse('dateFin', ''));
        if ($dateFin === '') $dateFin = date('Y-m-d');

        $dateDebut = trim((string) inputValueCaisse('dateDebut', ''));

        // Garde-fou serveur : Date de début ne doit jamais dépasser Date de fin.
        if ($dateDebut !== '' && $dateDebut > $dateFin) {
            [$dateDebut, $dateFin] = [$dateFin, $dateDebut];
        }

        $sql = "
            SELECT
                pp.id, pp.idPAP, pp.montant, pp.mode_reglement, pp.banque, pp.numero_cheque_virement,
                pp.recu, pp.id_tranche, pp.date_paiement, pp.idCaissier,
                CONCAT(uc.prenom, ' ', uc.nom) AS caissier,
                p.id AS numeroPAP, p.nom_commande, p.idTypePAP,
                mr.mode_reglement AS mode_reglement_nom
            FROM paiement_pap pp
            JOIN passer_achat_et_paiement p ON pp.idPAP = p.id
            JOIN utilisateurs uc ON pp.idCaissier = uc.id
            LEFT JOIN mode_reglement mr ON pp.mode_reglement = mr.id
            WHERE 1 = 1
        ";
        $params = [];

        if ($dateDebut !== '') {
            $sql .= " AND DATE(pp.date_paiement) BETWEEN ? AND ?";
            $params[] = $dateDebut;
            $params[] = $dateFin;
        } else {
            $sql .= " AND DATE(pp.date_paiement) = ?";
            $params[] = $dateFin;
        }

        // Tous les paiements sont toujours affichés, quel que soit l'état de
        // l'arrêt de caisse — seule la MODIFICATION est bloquée (cf. le flag
        // `modifiable`, calculé ci-dessous à partir de arreteCaisseExiste()).
        $sql .= " ORDER BY pp.date_paiement DESC";

        $stmt = $bdBASI->prepare($sql);
        if (!$stmt) throw new \RuntimeException('Requête de liste des paiements échouée.');
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $montantTotal = 0.0;
        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['id']);
            $montantTotal += (float) $r['montant'];

            $dateDuPaiement = date('Y-m-d', strtotime($r['date_paiement']));
            $r['modifiable'] = (
                (float) $r['montant'] > 0
                && !arreteCaisseExiste($bdBASI, (int) $r['idCaissier'], $dateDuPaiement)
            );
        }
        unset($r);

        echo json_encode([
            'status'         => 'success',
            'data'           => $rows,
            'nombre_total'   => count($rows),
            'montant_total'  => $montantTotal,
        ]);
    } catch (\Throwable $e) {
        error_log('[Caisse][listerPaiements] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger la liste des paiements.');
    }
}

/**
 * "Modifie" un paiement — en réalité une annulation logique : le montant du
 * paiement est remis à 0 (jamais supprimé), avec répercussion sur
 * passer_achat_et_paiement (montant_paye décrémenté, idStatut 7 → 6 si
 * applicable) et sur la tranche concernée le cas échéant.
 *
 * Règle tranches : une tranche ne peut être annulée que si aucune tranche
 * suivante (ordre supérieur) n'a déjà été réglée.
 */
function modifierPaiement(PDO $bdBASI, caisseController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idPaiement = (int) $basiController->tokendecrypt($token);
        if ($idPaiement <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $motif = trim((string) inputValueCaisse('motif', ''));

        $stmt = $bdBASI->prepare("
            SELECT id, idPAP, montant, mode_reglement, banque, numero_cheque_virement, recu, id_tranche, date_paiement, idCaissier
            FROM paiement_pap
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$idPaiement]);
        $paiement = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$paiement) {
            echo json_encode(['status' => 'error', 'message' => 'Paiement introuvable.']);
            return;
        }

        if ((float) $paiement['montant'] <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Ce paiement est déjà annulé.']);
            return;
        }

        $dateDuPaiement = date('Y-m-d', strtotime($paiement['date_paiement']));
        if (arreteCaisseExiste($bdBASI, (int) $paiement['idCaissier'], $dateDuPaiement)) {
            echo json_encode(['status' => 'error', 'message' => "L'arrêt de caisse a déjà été effectué pour ce caissier à cette date : modification impossible."]);
            return;
        }

        $idPAP      = (int) $paiement['idPAP'];
        $idTranche  = $paiement['id_tranche'] !== null ? (int) $paiement['id_tranche'] : null;
        $trancheActuelle = null;

        if ($idTranche !== null) {
            $stmtTr = $bdBASI->prepare("SELECT id, ordre, pourcentage FROM tranches WHERE id = ? LIMIT 1");
            $stmtTr->execute([$idTranche]);
            $trancheActuelle = $stmtTr->fetch(PDO::FETCH_ASSOC);

            if ($trancheActuelle) {
                // Interdiction de modifier si une tranche suivante a déjà été réglée.
                $stmtSuivantes = $bdBASI->prepare("
                    SELECT id FROM tranches
                    WHERE idPAP = ? AND statut = 1 AND ordre > ? AND paiement = 1
                    LIMIT 1
                ");
                $stmtSuivantes->execute([$idPAP, $trancheActuelle['ordre']]);
                if ($stmtSuivantes->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => "Impossible de modifier cette tranche : une tranche suivante a déjà été réglée."]);
                    return;
                }
            }
        }

        $ancienMontant = (float) $paiement['montant'];

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $motifFinal = "Annulation du paiement (par $sessionMatricule)" . ($motif !== '' ? ' — ' . $motif : '');

        $bdBASI->beginTransaction();

        // 1) Paiement : montant remis à 0 (annulation logique).
        $bdBASI->prepare("UPDATE paiement_pap SET montant = 0 WHERE id = ?")->execute([$idPaiement]);

        $bdBASI->prepare("
            INSERT INTO historique_paiement_pap
                (idPaiement, idPAP, montant, mode_reglement, banque, numero_cheque_virement, recu, id_tranche, date_paiement, idCaissier, motif, dateEnregistrement)
            SELECT id, idPAP, montant, mode_reglement, banque, numero_cheque_virement, recu, id_tranche, date_paiement, idCaissier, ?, ?
            FROM paiement_pap
            WHERE id = ?
        ")->execute([$motifFinal, $dateEnregistrement, $idPaiement]);

        // 2) Commande : montant_paye décrémenté, idStatut 7 → 6 si applicable.
        $stmtPAP = $bdBASI->prepare("SELECT montant_paye, idStatut FROM passer_achat_et_paiement WHERE id = ? LIMIT 1");
        $stmtPAP->execute([$idPAP]);
        $pap = $stmtPAP->fetch(PDO::FETCH_ASSOC);
        $nouveauMontantPaye = max(0, (float) $pap['montant_paye'] - $ancienMontant);
        $nouveauStatut = ((int) $pap['idStatut'] === 7) ? 6 : (int) $pap['idStatut'];

        $bdBASI->prepare("
            UPDATE passer_achat_et_paiement SET montant_paye = ?, idStatut = ? WHERE id = ?
        ")->execute([$nouveauMontantPaye, $nouveauStatut, $idPAP]);

        $bdBASI->prepare("
            INSERT INTO historique_passer_achat_et_paiement
                (idPAP, nom_commande, montant_total, montant_paye, idStatut, idTypePAP, dateCreation,
                 id_mode_reglement, id_mode_paiement, nb_tranche, idUtilisateur, motif, dateEnregistrement)
            SELECT id, nom_commande, montant_total, montant_paye, idStatut, idTypePAP, dateCreation,
                   id_mode_reglement, id_mode_paiement, nb_tranche, ?, ?, ?
            FROM passer_achat_et_paiement
            WHERE id = ?
        ")->execute([$sessionUserId, $motifFinal, $dateEnregistrement, $idPAP]);

        // 3) Tranche concernée (le cas échéant) : paiement/date_paiement remis à zéro.
        if ($trancheActuelle) {
            $bdBASI->prepare("UPDATE tranches SET paiement = 0, date_paiement = NULL WHERE id = ?")->execute([$idTranche]);

            $bdBASI->prepare("
                INSERT INTO tranches_histo
                    (id_tranche, idPAP, ordre, pourcentage, created_at, updated_at, action, dateEnregistrement)
                VALUES (?, ?, ?, ?, ?, ?, 'Annulation du paiement', ?)
            ")->execute([
                $idTranche, $idPAP, $trancheActuelle['ordre'], $trancheActuelle['pourcentage'],
                $dateEnregistrement, $dateEnregistrement, $dateEnregistrement,
            ]);
        }

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Paiement annulé avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Caisse][modifierPaiement] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de modifier le paiement.');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Liste des opérations (profil Comptable)

   Identique à la page DRH (liste-operations-pap.php), adaptée ici pour le
   Comptable — mêmes 3 fonctions (adaptées depuis drh_controller.php :
   listerToutesOperations/detailOperation/suiviOperation), qui sont
   entièrement génériques (aucun filtre par idUtilisateur/session), donc
   sûres à réutiliser telles quelles.

   ⚠️ Ces fonctions ne sont PAS ajoutées ici via drh_controller.php car ce
   dernier exige `tmpIdDirection` en session — variable absente pour le
   profil Comptable (même bug identifié précédemment pour le Caissier).
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Liste de toutes les opérations (passer_achat_et_paiement), tous statuts,
 * avec statistiques par statut (non affectées par le filtre).
 */
function listerToutesOperationsComptable(PDO $bdBASI, caisseController $basiController): void {
    try {
        $statutFiltre = (int) inputValueCaisse('statut', 0);

        $stmtStats = $bdBASI->query("SELECT idStatut, COUNT(*) AS n FROM passer_achat_et_paiement GROUP BY idStatut");
        if (!$stmtStats) throw new \RuntimeException('Requête des statistiques échouée.');

        $stats = ['tous' => 0, '1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, '6' => 0, '7' => 0];
        while ($row = $stmtStats->fetch(PDO::FETCH_ASSOC)) {
            $cle = (string)(int)$row['idStatut'];
            if (isset($stats[$cle])) $stats[$cle] = (int)$row['n'];
            $stats['tous'] += (int)$row['n'];
        }

        $sql = "
            SELECT
                p.id AS idPAP,
                p.nom_commande,
                p.montant_total,
                p.dateCreation,
                p.idTypePAP,
                p.idStatut,
                p.motifRejet,
                mr.mode_reglement AS mode_reglement_nom,
                mp.mode_paiement  AS mode_paiement_nom,
                CONCAT(u.prenom, ' ', u.nom) AS demandeur,
                EXISTS (
                    SELECT 1 FROM documents_pap dp
                    WHERE dp.idPAP = p.id AND dp.statut = 1 AND dp.choix = 1
                      AND dp.facture_definitive IS NOT NULL AND dp.facture_definitive != ''
                ) AS bc_uploade
            FROM passer_achat_et_paiement p
            LEFT JOIN mode_reglement mr ON p.id_mode_reglement = mr.id
            LEFT JOIN mode_paiement  mp ON p.id_mode_paiement  = mp.id
            LEFT JOIN utilisateurs   u  ON p.idUtilisateur     = u.id
        ";
        $params = [];
        if ($statutFiltre > 0) {
            $sql .= " WHERE p.idStatut = ?";
            $params[] = $statutFiltre;
        }
        $sql .= " ORDER BY p.dateCreation DESC";

        $stmt = $bdBASI->prepare($sql);
        if (!$stmt) throw new \RuntimeException('Requête de liste des opérations échouée.');
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['tmp']        = $basiController->tokenencrypt($r['idPAP']);
            $r['bc_uploade'] = (bool)((int)$r['bc_uploade']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'stats' => $stats, 'data' => $rows]);
    } catch (\Throwable $e) {
        error_log('[Caisse][listerToutesOperationsComptable] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger la liste des demandes.');
    }
}

/**
 * Détail complet d'une opération, quel que soit son statut.
 * Paramètre : token (chiffré de idPAP)
 */
function detailOperationComptable(PDO $bdBASI, caisseController $basiController): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmtC = $bdBASI->prepare("
            SELECT p.id AS idPAP, p.nom_commande, p.montant_total, p.dateCreation, p.idStatut, p.idTypePAP,
                   p.id_mode_reglement, p.id_mode_paiement, p.motifRejet,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM passer_achat_et_paiement p
            LEFT JOIN utilisateurs u ON p.idUtilisateur = u.id
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmtC->execute([$idPAP]);
        $dossier = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$dossier) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable.']);
            return;
        }

        $estAchat = ((int)$dossier['idTypePAP'] === 1);
        $reponse  = ['status' => 'success', 'dossier' => $dossier];

        $stmtLignes = $bdBASI->prepare("
            SELECT
                papl.id AS idPAPL, lb.designation, papl.quantite_reelle, papl.prix_reel, papl.montant_total_ligne
            FROM passer_achat_et_paiement_ligne papl
            JOIN demandes_ligne dal ON papl.idDL = dal.idDL
            JOIN ligneBudget    lb  ON dal.idLB  = lb.id
            WHERE papl.idPAP = ? AND papl.id_statut_PAPL = 1
            ORDER BY papl.id ASC
        ");
        $stmtLignes->execute([$idPAP]);
        $reponse['lignes'] = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

        if ($estAchat) {
            $stmtFacture = $bdBASI->prepare("
                SELECT dp.id, dp.doc AS facture_definitive, dp.id_fournisseur,
                       f.nomF, f.prenomF, f.entreprise
                FROM documents_pap dp
                JOIN fournisseur f ON dp.id_fournisseur = f.idF
                WHERE dp.idPAP = ? AND dp.choix = 1
                LIMIT 1
            ");
            $stmtFacture->execute([$idPAP]);
            $reponse['facture_definitive'] = $stmtFacture->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$reponse['facture_definitive']) {
                $stmtProforma = $bdBASI->prepare("
                    SELECT dp.id, dp.doc, dp.id_fournisseur, f.nomF, f.prenomF, f.entreprise
                    FROM documents_pap dp
                    JOIN fournisseur f ON dp.id_fournisseur = f.idF
                    WHERE dp.idPAP = ? AND dp.statut = 1
                    ORDER BY dp.id ASC
                ");
                $stmtProforma->execute([$idPAP]);
                $reponse['documents'] = $stmtProforma->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $reponse['documents'] = [];
            }
        } else {
            $stmtJustif = $bdBASI->prepare("
                SELECT id, doc, dateEnregistrement
                FROM document_justificatif_paiement
                WHERE idPAP = ? AND statut = 1
                ORDER BY id ASC
            ");
            $stmtJustif->execute([$idPAP]);
            $reponse['justificatifs'] = $stmtJustif->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($reponse);
    } catch (\Throwable $e) {
        error_log('[Caisse][detailOperationComptable] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger le détail de la demande.');
    }
}

/**
 * Historique / suivi d'une opération.
 * Paramètre : token (chiffré de idPAP)
 */
function suiviOperationComptable(PDO $bdBASI, caisseController $basiController): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmt = $bdBASI->prepare("
            SELECT h.idStatut, h.montant_total, h.motif, h.motifRejet, h.dateEnregistrement,
                   CONCAT(u.prenom, ' ', u.nom) AS utilisateur
            FROM historique_passer_achat_et_paiement h
            LEFT JOIN utilisateurs u ON h.idUtilisateur = u.id
            WHERE h.idPAP = ?
            ORDER BY h.dateEnregistrement ASC, h.id ASC
        ");
        $stmt->execute([$idPAP]);
        $historique = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'historique' => $historique]);
    } catch (\Throwable $e) {
        error_log('[Caisse][suiviOperationComptable] ' . $e->getMessage());
        erreurSqlCaisse("Impossible de charger le suivi de la demande.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Gestion des livraisons (profil Comptable)
   Concerne uniquement les commandes d'achat (idTypePAP = 1).

   ⚠️ Hypothèses de schéma (NOUVELLES tables/colonnes — à créer/ajuster) :
   - `livraison` : id (PK auto), idPAP, nom_livraison, numero_livraison,
     fichier_bon_livraison, date_livraison, idStatut (défaut 1), dateEnregistrement
     (colonne ajoutée en plus de la liste fournie, nécessaire à l'horodatage) :
       CREATE TABLE livraison (
           id INT AUTO_INCREMENT PRIMARY KEY,
           idPAP INT NOT NULL,
           nom_livraison VARCHAR(255) NOT NULL,
           numero_livraison VARCHAR(100) NULL,
           fichier_bon_livraison VARCHAR(255) NULL,
           date_livraison DATE NOT NULL,
           idStatut TINYINT NOT NULL DEFAULT 1,
           dateEnregistrement DATETIME NOT NULL
       );
   - `historique_livraison` : mêmes colonnes que livraison (idL référence
     livraison.id) + motif, dateEnregistrement.
   - `livraison_produit` : id (PK auto), idL, idPAPL, idP (id du produit,
     référence product.idP — NULL si la ligne n'est reliée à aucun produit),
     quantite, id_unite, piece_par_unite, dateEnregistrement.
   - `passer_achat_et_paiement_ligne` : colonne `quantite_livree` À AJOUTER
     (NOT NULL DEFAULT 0), incrémentée à chaque livraison :
       ALTER TABLE passer_achat_et_paiement_ligne ADD COLUMN quantite_livree DECIMAL(15,2) NOT NULL DEFAULT 0;
   - `ligneBudget` : reliée à `product` via la colonne `id_produit` :
       ligneBudget.id_produit → product.idP
   - `product` (PK = idP, et non `id`) : idP, id_type_product, Stock_actuel
     (entre autres colonnes).
   - idStatut de `livraison` : 1 = valeur par défaut (aucune autre valeur
     n'étant précisée dans la demande, aucun autre statut n'est géré ici).
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Liste des livraisons, filtrable par intervalle d'années (Début vide par
 * défaut, Fin = année en cours par défaut), années de 2026 à l'année en cours.
 */
function listerLivraisons(PDO $bdBASI, caisseController $basiController): void {
    try {
        $anneeCourante = (int) date('Y');
        $anneeFin = (int) inputValueCaisse('anneeFin', $anneeCourante);
        if ($anneeFin <= 0) $anneeFin = $anneeCourante;

        $anneeDebutBrute = trim((string) inputValueCaisse('anneeDebut', ''));
        $anneeDebut = ($anneeDebutBrute === '') ? $anneeFin : (int) $anneeDebutBrute;
        if ($anneeDebut <= 0) $anneeDebut = $anneeFin;
        if ($anneeDebut > $anneeFin) { [$anneeDebut, $anneeFin] = [$anneeFin, $anneeDebut]; }

        $stmt = $bdBASI->prepare("
            SELECT
                l.id, l.idPAP, l.nom_livraison, l.numero_livraison, l.fichier_bon_livraison,
                l.date_livraison, l.idStatut, l.dateEnregistrement,
                p.nom_commande, p.id AS numeroPAP
            FROM livraison l
            JOIN passer_achat_et_paiement p ON l.idPAP = p.id
            WHERE YEAR(l.date_livraison) BETWEEN ? AND ?
            ORDER BY l.date_livraison DESC, l.id DESC
        ");
        $stmt->execute([$anneeDebut, $anneeFin]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['id']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows]);
    } catch (\Throwable $e) {
        error_log('[Caisse][listerLivraisons] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger la liste des livraisons.');
    }
}

/**
 * Liste des commandes d'achat livrables : idTypePAP = 1, idStatut = 6,
 * livraison = 0 — pour le menu déroulant du formulaire de création.
 */
function listerCommandesLivrables(PDO $bdBASI, caisseController $basiController): void {
    try {
        $stmt = $bdBASI->query("
            SELECT id, id as numero, nom_commande
            FROM passer_achat_et_paiement
            WHERE idTypePAP = 1 AND idStatut = 6 AND livraison = 0
            ORDER BY dateCreation ASC
        ");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['id']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows]);
    } catch (\Throwable $e) {
        error_log('[Caisse][listerCommandesLivrables] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger la liste des commandes livrables.');
    }
}

/**
 * Lignes d'une commande d'achat sélectionnée, avec quantité restant à
 * livrer (quantite_reelle - quantite_livree). Paramètre : token (idPAP).
 */
function detailCommandeLivraison(PDO $bdBASI, caisseController $basiController): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmtC = $bdBASI->prepare("
            SELECT id, id as numero, nom_commande, idTypePAP, idStatut, livraison
            FROM passer_achat_et_paiement
            WHERE id = ? AND idTypePAP = 1 AND idStatut = 6 AND livraison = 0
            LIMIT 1
        ");
        $stmtC->execute([$idPAP]);
        $commande = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$commande) {
            echo json_encode(['status' => 'error', 'message' => 'Commande introuvable ou non éligible à la livraison.']);
            return;
        }

        $stmtLignes = $bdBASI->prepare("
            SELECT
                papl.id AS idPAPL, papl.idDL, lb.designation, papl.quantite_reelle, papl.quantite_livree,
                papl.id_unite, papl.nb_unites,
                (papl.quantite_reelle - papl.quantite_livree) AS quantite_restante,
                p.id_type_product, b.direction_id AS idDirection, d.nom_direction, d.code_direction
            FROM passer_achat_et_paiement_ligne papl
            JOIN demandes_ligne dal ON papl.idDL = dal.idDL
            JOIN ligneBudget    lb  ON dal.idLB  = lb.id
            LEFT JOIN product   p   ON lb.id_produit = p.idP
            LEFT JOIN budget    b   ON lb.budget_id   = b.id
            LEFT JOIN direction d   ON b.direction_id = d.id
            WHERE papl.idPAP = ? AND papl.id_statut_PAPL = 1
                  AND papl.quantite_reelle > papl.quantite_livree
            ORDER BY papl.id ASC
        ");
        $stmtLignes->execute([$idPAP]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'commande' => $commande, 'lignes' => $lignes]);
    } catch (\Throwable $e) {
        error_log('[Caisse][detailCommandeLivraison] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger le détail de la commande.');
    }
}

/**
 * Détail d'une livraison déjà enregistrée (action "Consulter").
 * Paramètre : token (chiffré de livraison.id)
 */
function detailLivraison(PDO $bdBASI, caisseController $basiController): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idL = (int) $basiController->tokendecrypt($token);
        if ($idL <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmt = $bdBASI->prepare("
            SELECT l.id, l.idPAP, l.nom_livraison, l.numero_livraison, l.fichier_bon_livraison,
                   l.date_livraison, l.idStatut, l.dateEnregistrement,
                   p.nom_commande, p.id AS numeroPAP
            FROM livraison l
            JOIN passer_achat_et_paiement p ON l.idPAP = p.id
            WHERE l.id = ?
            LIMIT 1
        ");
        $stmt->execute([$idL]);
        $livraison = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$livraison) {
            echo json_encode(['status' => 'error', 'message' => 'Livraison introuvable.']);
            return;
        }

        $stmtProduits = $bdBASI->prepare("
            SELECT lp.id, lp.idP, lp.quantite, lp.id_unite, lp.piece_par_unite, lb.designation,
                   p.id_type_product
            FROM livraison_produit lp
            JOIN passer_achat_et_paiement_ligne papl ON lp.idPAPL = papl.id
            JOIN demandes_ligne dal ON papl.idDL = dal.idDL
            JOIN ligneBudget    lb  ON dal.idLB  = lb.id
            LEFT JOIN product   p   ON lb.id_produit = p.idP
            WHERE lp.idL = ?
            ORDER BY lp.id ASC
        ");
        $stmtProduits->execute([$idL]);
        $produits = $stmtProduits->fetchAll(PDO::FETCH_ASSOC);

        // Pour les lignes Investissement uniquement : comment la quantité
        // reçue a été répartie (direct au demandeur / mise en stock), et
        // pour quelle direction — jamais renseigné pour le Fonctionnement.
        $stmtRepartition = $bdBASI->prepare("
            SELECT lpr.mode, lpr.quantite, lpr.idDirection, d.nom_direction, d.code_direction
            FROM livraison_produit_repartition lpr
            LEFT JOIN direction d ON lpr.idDirection = d.id
            WHERE lpr.idLP = ?
            ORDER BY lpr.id ASC
        ");
        foreach ($produits as &$p) {
            if ((int) ($p['id_type_product'] ?? 0) === 2) {
                $stmtRepartition->execute([$p['id']]);
                $p['repartitions'] = $stmtRepartition->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $p['repartitions'] = [];
            }
        }
        unset($p);

        $livraison['produits'] = $produits;

        echo json_encode(['status' => 'success', 'livraison' => $livraison]);
    } catch (\Throwable $e) {
        error_log('[Caisse][detailLivraison] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger le détail de la livraison.');
    }
}

/**
 * Dossier complet d'une opération TERMINÉE (idStatut = 7) : commande,
 * paiement(s), livraison(s) (achat uniquement) et tous les documents liés à
 * chaque étape. Sert à la fois pour le bouton "Dossier" (vue complète) et le
 * bouton "Pièces" (le front-end n'affiche alors que la section `documents`
 * de cette même réponse).
 *
 * Paramètre : token (chiffré de idPAP)
 */
function detailDossierComplet(PDO $bdBASI, caisseController $basiController): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmtC = $bdBASI->prepare("
            SELECT p.id, p.id AS numero, p.nom_commande, p.montant_total, p.montant_paye, p.dateCreation,
                   p.idStatut, p.idTypePAP, p.livraison,
                   mr.mode_reglement AS mode_reglement_nom, mp.mode_paiement AS mode_paiement_nom,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM passer_achat_et_paiement p
            LEFT JOIN mode_reglement mr ON p.id_mode_reglement = mr.id
            LEFT JOIN mode_paiement  mp ON p.id_mode_paiement  = mp.id
            LEFT JOIN utilisateurs   u  ON p.idUtilisateur     = u.id
            WHERE p.id = ? AND p.idStatut = 7
            LIMIT 1
        ");
        $stmtC->execute([$idPAP]);
        $commande = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$commande) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable ou non terminé.']);
            return;
        }

        $estAchat = ((int) $commande['idTypePAP'] === 1);
        $reponse  = ['status' => 'success', 'commande' => $commande, 'estAchat' => $estAchat];

        // ── Lignes ────────────────────────────────────────────────────────────
        if ($estAchat) {
            $stmtLignes = $bdBASI->prepare("
                SELECT papl.id AS idPAPL, lb.designation, papl.quantite_reelle, papl.quantite_livree,
                       papl.prix_reel, papl.montant_total_ligne
                FROM passer_achat_et_paiement_ligne papl
                JOIN demandes_ligne dal ON papl.idDL = dal.idDL
                JOIN ligneBudget    lb  ON dal.idLB  = lb.id
                WHERE papl.idPAP = ? AND papl.id_statut_PAPL = 1
                ORDER BY papl.id ASC
            ");
        } else {
            $stmtLignes = $bdBASI->prepare("
                SELECT papl.id AS idPAPL, lb.designation, papl.montant_total_ligne
                FROM passer_achat_et_paiement_ligne papl
                JOIN demandes_ligne dal ON papl.idDL = dal.idDL
                JOIN ligneBudget    lb  ON dal.idLB  = lb.id
                WHERE papl.idPAP = ? AND papl.id_statut_PAPL = 1
                ORDER BY papl.id ASC
            ");
        }
        $stmtLignes->execute([$idPAP]);
        $reponse['lignes'] = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

        // ── Paiements ─────────────────────────────────────────────────────────
        $stmtPaiements = $bdBASI->prepare("
            SELECT pp.id, pp.montant, pp.mode_reglement, pp.banque, pp.numero_cheque_virement,
                   pp.recu, pp.date_paiement, CONCAT(uc.prenom, ' ', uc.nom) AS caissier,
                   mr.mode_reglement AS mode_reglement_nom
            FROM paiement_pap pp
            JOIN utilisateurs uc ON pp.idCaissier = uc.id
            LEFT JOIN mode_reglement mr ON pp.mode_reglement = mr.id
            WHERE pp.idPAP = ?
            ORDER BY pp.date_paiement ASC
        ");
        $stmtPaiements->execute([$idPAP]);
        $reponse['paiements'] = $stmtPaiements->fetchAll(PDO::FETCH_ASSOC);

        // ── Documents ─────────────────────────────────────────────────────────
        $documents = [];

        if ($estAchat) {
            // Facture choisie + facture définitive (BC uploadé par le DRH) :
            // toutes deux portées par la même ligne documents_pap (choix = 1).
            $stmtFacture = $bdBASI->prepare("
                SELECT dp.doc AS facture_choisie, dp.facture_definitive,
                       f.nomF, f.prenomF, f.entreprise
                FROM documents_pap dp
                JOIN fournisseur f ON dp.id_fournisseur = f.idF
                WHERE dp.idPAP = ? AND dp.choix = 1
                LIMIT 1
            ");
            $stmtFacture->execute([$idPAP]);
            $facture = $stmtFacture->fetch(PDO::FETCH_ASSOC) ?: null;

            $documents['facture_choisie']     = $facture['facture_choisie']     ?? null;
            $documents['facture_definitive']  = $facture['facture_definitive']  ?? null;
            $documents['fournisseur']         = $facture ? trim(($facture['prenomF'] ?? '') . ' ' . ($facture['nomF'] ?? '')) . (!empty($facture['entreprise']) ? ' — ' . $facture['entreprise'] : '') : null;

            // Bon de commande généré par le système (PDF autonome existant).
            $documents['bon_commande_url'] = '/compta_facture/' . urlencode($token);

            // Bon(s) de livraison — potentiellement plusieurs (livraisons partielles).
            $stmtLivraisons = $bdBASI->prepare("
                SELECT id, nom_livraison, numero_livraison, fichier_bon_livraison, date_livraison
                FROM livraison
                WHERE idPAP = ?
                ORDER BY date_livraison ASC, id ASC
            ");
            $stmtLivraisons->execute([$idPAP]);
            $documents['livraisons'] = $stmtLivraisons->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmtJustif = $bdBASI->prepare("
                SELECT id, doc, dateEnregistrement
                FROM document_justificatif_paiement
                WHERE idPAP = ? AND statut = 1
                ORDER BY id ASC
            ");
            $stmtJustif->execute([$idPAP]);
            $documents['justificatifs'] = $stmtJustif->fetchAll(PDO::FETCH_ASSOC);
        }

        $reponse['documents'] = $documents;

        echo json_encode($reponse);
    } catch (\Throwable $e) {
        error_log('[Caisse][detailDossierComplet] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger le dossier complet.');
    }
}

/**
 * Création d'une livraison — opération IRRÉVERSIBLE :
 *   1) Insère `livraison` + `historique_livraison`.
 *   2) Pour chaque ligne avec une quantité saisie > 0 :
 *        - insère `livraison_produit` (piece_par_unite calculé selon la
 *          règle : id_unite=1 → = quantité saisie, sinon → nb_unites × quantité),
 *        - incrémente passer_achat_et_paiement_ligne.quantite_livree,
 *        - si produit.id_type_product = 1, incrémente produit.Stock_actuel.
 *   3) Marque passer_achat_et_paiement.livraison = 1.
 *   4) Si montant_total = montant_paye ET idStatut = 6, passe idStatut à 7.
 * Requête multipart/form-data (bon de livraison, obligatoire ou non — non
 * précisé, traité ici comme optionnel comme les autres pièces jointes du
 * projet, à confirmer).
 */

function creerLivraison(PDO $bdBASI, caisseController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string)($_POST['token'] ?? ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $numeroLivraison = trim((string)($_POST['numero_livraison'] ?? ''));
        if ($numeroLivraison === '') {
            echo json_encode(['status' => 'error', 'message' => 'Le numéro de livraison est obligatoire.']);
            return;
        }
        $dateLivraison   = trim((string)($_POST['date_livraison'] ?? date('Y-m-d')));
        $quantitesSaisies = json_decode((string)($_POST['quantites'] ?? '[]'), true);
        if (!is_array($quantitesSaisies)) $quantitesSaisies = [];
        // Mode de remise des lignes Investissement UNIQUEMENT :
        // { idPAPL: 'directe'|'stock', ... } — absent ou ignoré pour les
        // lignes Fonctionnement, qui continuent d'incrémenter Stock_actuel
        // en totalité comme aujourd'hui. Chaque ligne appartient déjà à UNE
        // direction précise (via idDL) : pas de saisie de direction/quantité
        // libre ici, juste un choix de mode par ligne, déjà plafonnée par
        // sa propre quantite_restante.
        $modesSaisis = json_decode((string)($_POST['modes'] ?? '[]'), true);
        if (!is_array($modesSaisis)) $modesSaisis = [];

        $stmtC = $bdBASI->prepare("
            SELECT id, id as numero, nom_commande, montant_total, montant_paye, idStatut
            FROM passer_achat_et_paiement
            WHERE id = ? AND idTypePAP = 1 AND idStatut = 6 AND livraison = 0
            LIMIT 1
        ");
        $stmtC->execute([$idPAP]);
        $commande = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$commande) {
            echo json_encode(['status' => 'error', 'message' => 'Commande introuvable ou déjà livrée.']);
            return;
        }

        // ── Bon de livraison (upload OBLIGATOIRE) ────────────────────────────
        if (empty($_FILES['fichier_bon_livraison']) || $_FILES['fichier_bon_livraison']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'Le bon de livraison est obligatoire.']);
            return;
        }

        if (!is_dir(UPLOAD_DIR_BON_LIVRAISON)) mkdir(UPLOAD_DIR_BON_LIVRAISON, 0755, true);
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['fichier_bon_livraison']['tmp_name']);
        $extensionParMime = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!isset($extensionParMime[$mimeType])) {
            echo json_encode(['status' => 'error', 'message' => 'Le bon de livraison doit être un PDF, JPG ou PNG.']);
            return;
        }
        $nomFichier   = 'bon_livraison_' . $idPAP . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extensionParMime[$mimeType];
        $cheminAbsolu = UPLOAD_DIR_BON_LIVRAISON . '/' . $nomFichier;
        if (!move_uploaded_file($_FILES['fichier_bon_livraison']['tmp_name'], $cheminAbsolu)) {
            throw new \RuntimeException("Échec de l'enregistrement du bon de livraison.");
        }
        $cheminBonLivraison = UPLOAD_URL_BON_LIVRAISON . '/' . $nomFichier;

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $nomLivraison = 'livraison_' . date('Ymd_His');

        $bdBASI->beginTransaction();

        // 1) En-tête de la livraison
        $bdBASI->prepare("
            INSERT INTO livraison (idPAP, nom_livraison, numero_livraison, fichier_bon_livraison, date_livraison, idStatut, dateEnregistrement)
            VALUES (?, ?, ?, ?, ?, 1, ?)
        ")->execute([$idPAP, $nomLivraison, $numeroLivraison, $cheminBonLivraison, $dateLivraison, $dateEnregistrement]);
        $idL = (int) $bdBASI->lastInsertId();

        $motif = "Création de la livraison (par $sessionMatricule)";
        $bdBASI->prepare("
            INSERT INTO historique_livraison
                (idL, idPAP, nom_livraison, numero_livraison, fichier_bon_livraison, date_livraison, idStatut, motif, dateEnregistrement)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)
        ")->execute([$idL, $idPAP, $nomLivraison, $numeroLivraison, $cheminBonLivraison, $dateLivraison, $motif, $dateEnregistrement]);

        // 2) Lignes reçues
        $stmtLigneInfo = $bdBASI->prepare("
            SELECT papl.id, papl.idDL, papl.quantite_reelle, papl.quantite_livree, papl.id_unite, papl.nb_unites,
                   lb.id_produit as idProduit, b.direction_id AS idDirection
            FROM passer_achat_et_paiement_ligne papl
            JOIN demandes_ligne dal ON papl.idDL = dal.idDL
            JOIN ligneBudget    lb  ON dal.idLB  = lb.id
            LEFT JOIN budget    b   ON lb.budget_id = b.id
            WHERE papl.id = ? AND papl.idPAP = ? AND papl.id_statut_PAPL = 1
            LIMIT 1
        ");
        $stmtInsertProduit = $bdBASI->prepare("
            INSERT INTO livraison_produit (idL, idPAPL, idP, quantite, id_unite, piece_par_unite, dateEnregistrement)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtUpdateQteLivree = $bdBASI->prepare("
            UPDATE passer_achat_et_paiement_ligne SET quantite_livree = quantite_livree + ? WHERE id = ?
        ");
        $stmtProduitType = $bdBASI->prepare("SELECT id_type_product FROM product WHERE idP = ? LIMIT 1");
        $stmtIncrementStock = $bdBASI->prepare("UPDATE product SET Stock_actuel = Stock_actuel + ? WHERE idP = ?");
        $stmtInsertRepartition = $bdBASI->prepare("
            INSERT INTO livraison_produit_repartition (idLP, idDirection, mode, idDL, quantite, idUtilisateur, dateEnregistrement)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtHistoRepartition = $bdBASI->prepare("
            INSERT INTO historique_livraison_produit_repartition (idLPR, idLP, idDirection, mode, idDL, quantite, idUtilisateur, motif, dateEnregistrement)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($quantitesSaisies as $idPAPL => $quantiteSaisie) {
            $idPAPL = (int) $idPAPL;
            $quantiteSaisie = (float) $quantiteSaisie;
            if ($quantiteSaisie <= 0) continue;

            $stmtLigneInfo->execute([$idPAPL, $idPAP]);
            $ligne = $stmtLigneInfo->fetch(PDO::FETCH_ASSOC);
            if (!$ligne) continue;

            // Plafond STRICT : chaque ligne appartient déjà à une direction
            // précise, on ne peut jamais lui attribuer plus que ce qu'elle a
            // elle-même commandé et pas encore reçu.
            $restant = (float) $ligne['quantite_reelle'] - (float) $ligne['quantite_livree'];
            if ($quantiteSaisie > $restant + 0.01) {
                throw new \RuntimeException("La quantité reçue pour une ligne dépasse la quantité restant à livrer.");
            }

            // Règle : id_unite = 1 → piece_par_unite = quantité saisie ;
            // sinon → nb_unites × quantité saisie.
            $piecesParUnite = ((int) $ligne['id_unite'] === 1)
                ? $quantiteSaisie
                : ((float) $ligne['nb_unites'] * $quantiteSaisie);

            $idProduitLigne = !empty($ligne['idProduit']) ? (int) $ligne['idProduit'] : null;

            $stmtInsertProduit->execute([$idL, $idPAPL, $idProduitLigne, $quantiteSaisie, (int) $ligne['id_unite'], $piecesParUnite, $dateEnregistrement]);
            $idLP = (int) $bdBASI->lastInsertId();
            $stmtUpdateQteLivree->execute([$quantiteSaisie, $idPAPL]);

            if ($idProduitLigne === null) continue;

            $stmtProduitType->execute([$idProduitLigne]);
            $produit = $stmtProduitType->fetch(PDO::FETCH_ASSOC);
            $typeProduit = $produit ? (int) $produit['id_type_product'] : null;

            if ($typeProduit === 1) {
                // Fonctionnement : comportement inchangé, tout va au stock.
                $stmtIncrementStock->execute([$piecesParUnite, $idProduitLigne]);
                continue;
            }

            if ($typeProduit === 2) {
                // Investissement : la direction et le demandeur viennent de
                // LA LIGNE ELLE-MÊME (jamais d'une saisie libre côté client)
                // — seul le mode (directe/stock) est choisi par le comptable.
                $idDirectionLigne = (int) ($ligne['idDirection'] ?? 0);
                $idDLLigne        = (int) $ligne['idDL'];
                if ($idDirectionLigne <= 0) {
                    throw new \RuntimeException("Aucune direction associée au budget de cette ligne Investissement — vérifiez la configuration du budget.");
                }

                $mode = (($modesSaisis[$idPAPL] ?? $modesSaisis[(string) $idPAPL] ?? 'stock') === 'directe') ? 'directe' : 'stock';

                $stmtInsertRepartition->execute([
                    $idLP, $idDirectionLigne, $mode, ($mode === 'directe' ? $idDLLigne : null),
                    $quantiteSaisie, $sessionUserId, $dateEnregistrement,
                ]);
                $idLPR = (int) $bdBASI->lastInsertId();

                $motifRepartition = $mode === 'stock'
                    ? "Réception : mise en stock pour la direction (par $sessionMatricule)"
                    : "Réception : remise directe au demandeur (par $sessionMatricule)";
                $stmtHistoRepartition->execute([
                    $idLPR, $idLP, $idDirectionLigne, $mode, ($mode === 'directe' ? $idDLLigne : null),
                    $quantiteSaisie, $sessionUserId, $motifRepartition, $dateEnregistrement,
                ]);

                if ($mode === 'stock') {
                    $stmtIncrementStock->execute([$piecesParUnite, $idProduitLigne]);
                }
                // mode='directe' : remis en main propre, ne touche jamais
                // Stock_actuel — cohérent avec la logique déjà établie.
            }
        }

        // 3) "Dernière livraison" : toutes les lignes actives de la commande
        // sont-elles désormais entièrement livrées (quantite_livree >=
        // quantite_reelle) ? Ce n'est QUE dans ce cas que livraison passe à 1
        // (et, le cas échéant, que le statut peut passer à 7).
        $stmtVerifComplet = $bdBASI->prepare("
            SELECT COUNT(*) AS n
            FROM passer_achat_et_paiement_ligne
            WHERE idPAP = ? AND id_statut_PAPL = 1 AND quantite_livree < quantite_reelle
        ");
        $stmtVerifComplet->execute([$idPAP]);
        $ligneRestante = (int) ($stmtVerifComplet->fetch(PDO::FETCH_ASSOC)['n'] ?? 0);
        $derniereLivraison = ($ligneRestante === 0);

        if ($derniereLivraison) {
            $bdBASI->prepare("UPDATE passer_achat_et_paiement SET livraison = 1 WHERE id = ?")->execute([$idPAP]);

            // 4) Si en plus le paiement est intégral, passage automatique à
            // "Terminée" (7).
            $montantTotal = (float) $commande['montant_total'];
            $montantPaye  = (float) $commande['montant_paye'];
            if (abs($montantTotal - $montantPaye) < 0.01 && (int) $commande['idStatut'] === 6) {
                $bdBASI->prepare("UPDATE passer_achat_et_paiement SET idStatut = 7 WHERE id = ?")->execute([$idPAP]);
            }
        }

        $bdBASI->commit();

        echo json_encode([
            'status'  => 'success',
            'message' => $derniereLivraison
                ? 'Livraison enregistrée avec succès : commande intégralement livrée.'
                : 'Livraison partielle enregistrée avec succès : la commande reste ouverte pour une prochaine livraison.',
            'derniereLivraison' => $derniereLivraison,
        ]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Caisse][creerLivraison] ' . $e->getMessage());
        erreurSqlCaisse($e->getMessage() ?: "Impossible d'enregistrer la livraison.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Gestion des sorties de produits (profil Comptable)

   ⚠️ Hypothèses de schéma (cohérentes avec expressionBesoinController.php /
   chefDirectionEBController.php) :
   - `expression_besoin_produit` : colonne `quantite_sortie` À AJOUTER
     (NOT NULL DEFAULT 0), incrémentée à chaque sortie :
       ALTER TABLE expression_besoin_produit ADD COLUMN quantite_sortie DECIMAL(15,2) NOT NULL DEFAULT 0;
       ALTER TABLE historique_expression_besoin_produit ADD COLUMN quantite_sortie DECIMAL(15,2) NOT NULL DEFAULT 0;
   - `product` (PK = idP) : idP, nomproduit, Stock_actuel — décrémentée à
     chaque sortie effective.
   - idStatut de expression_besoin : la demande décrit ici la table
     `produit` (minuscule) — cohérent avec le reste du projet, la table
     réelle utilisée ailleurs est `product` (PK idP) ; je conserve `product`
     ici pour rester cohérent avec expressionBesoinController.php /
     chefDirectionEBController.php — à corriger si `produit` est en réalité
     une table distincte.
   - `product` (PK = idP) : en plus des colonnes déjà connues
     (nomproduit, code_produit, Stock_actuel, Seuil_limite, Total,
     id_Sous_categorie, id_statut, date_creation, id_type_product), possède
     une colonne `retrait` — compteur cumulatif, INCRÉMENTÉ à chaque sortie
     effective (en plus de la décrémentation de Stock_actuel).
   - `historique_product` (confirmée) : product_id (FK → product.idP),
     nomproduit, code_produit, Stock_actuel, Seuil_limite, Total,
     id_Sous_categorie, retrait, id_statut, date_creation, id_type_product,
     motif, dateEnregistrement — SNAPSHOT COMPLET de la ligne `product`,
     inséré APRÈS la mise à jour (Stock_actuel et retrait déjà à jour dans le
     snapshot), même principe que les autres tables historique_* du projet.
   - Quantité sortie par ligne : SAISIE par le comptable, plafonnée
     serveur-side à MIN(quantite_restante, Stock_actuel) — jamais plus. Si le
     stock d'une ligne est à 0, aucune sortie n'est possible pour cette ligne.
   - `inventaire` (NOUVELLE table) : id, etat (entre autres colonnes). Si un
     enregistrement avec statut = 1 existe, TOUTES les opérations de sortie
     sont bloquées (liste consultable, mais ouverture de l'écran de sortie ET
     confirmation de sortie toutes deux rejetées tant que l'inventaire est en
     cours).
   - Une expression de besoin est visible dans la liste des sorties tant
     qu'idStatut = 3 (Validée) ET qu'au moins une ligne active a
     quantite_reelle > quantite_sortie (pas encore entièrement servie).
     Passage automatique à 5 (Terminée) dès que toutes les lignes actives
     sont entièrement servies.
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Vrai si un inventaire est en cours (table `inventaire`, etat = 1) —
 * bloque alors TOUTES les opérations de sortie de stock, sans exception.
 */
function inventaireEnCours(PDO $bdBASI): bool {
    $stmt = $bdBASI->query("SELECT id FROM inventaire WHERE etat = 1 LIMIT 1");
    return $stmt ? (bool) $stmt->fetch() : false;
}

/**
 * Même contrôle, mais À APPELER DANS la transaction de l'opération qui modifie
 * le stock (sortie, retour en stock), avant toute écriture : la lecture
 * verrouillante (LOCK IN SHARE MODE) fait attendre la création d'un inventaire
 * concurrent jusqu'à la fin de cette transaction. Ainsi, soit l'inventaire
 * existe déjà et l'opération est refusée, soit il sera créé APRÈS elle et son
 * instantané du stock (quantite_systeme) la contient. Le contrôle fait avant
 * la transaction ne suffit pas : un inventaire pouvait être créé entre ce
 * contrôle et le commit.
 */
function inventaireEnCoursVerrou(PDO $bdBASI): bool {
    $stmt = $bdBASI->query("SELECT id FROM inventaire WHERE etat = 1 LIMIT 1 LOCK IN SHARE MODE");
    return $stmt ? (bool) $stmt->fetch() : false;
}

const MSG_INVENTAIRE_SORTIE = "Un inventaire est en cours : aucune sortie de stock n'est possible pour le moment.";
const MSG_INVENTAIRE_RETOUR = "Un inventaire est en cours : aucun retour en stock n'est possible pour le moment.";

/**
 * Liste des expressions de besoin Validées (idStatut = 3) et pas encore
 * entièrement servies (au moins une ligne active avec quantite_reelle >
 * quantite_sortie).
 */
//function listerExpressionsBesoinSortie(PDO $bdBASI, caisseController $basiController): void {
//    try {
//        $stmt = $bdBASI->query("
//            SELECT eb.id, eb.nom_expression, eb.date_creation, eb.idDirection,
//                   CONCAT(u.prenom, ' ', u.nom) AS demandeur,
//                   (SELECT COUNT(*) FROM expression_besoin_produit ebp WHERE ebp.idEB = eb.id AND ebp.statut = 1) AS nombre_produits
//            FROM expression_besoin eb
//            JOIN utilisateurs u ON eb.idUtilisateur = u.id
//            WHERE eb.idStatut = 3
//              AND EXISTS (
//                  SELECT 1 FROM expression_besoin_produit ebp
//                  WHERE ebp.idEB = eb.id AND ebp.statut = 1 AND ebp.quantite_reelle > ebp.quantite_sortie
//              )
//            ORDER BY eb.date_creation ASC, eb.id ASC
//        ");
//        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
//        foreach ($rows as &$r) {
//            $r['tmp'] = $basiController->tokenencrypt($r['id']);
//        }
//        unset($r);
//
//        echo json_encode(['status' => 'success', 'data' => $rows, 'nombre_total' => count($rows), 'inventaireEnCours' => inventaireEnCours($bdBASI)]);
//    } catch (\Throwable $e) {
//        error_log('[Caisse][listerExpressionsBesoinSortie] ' . $e->getMessage());
//        erreurSqlCaisse('Impossible de charger la liste des sorties de produits.');
//    }
//}

/**
 * Détail d'une expression de besoin pour l'écran de sortie : pour chaque
 * ligne active, quantité restant à sortir, stock disponible, et quantité
 * qui SERA effectivement sortie (MIN des deux — jamais modifiable).
 */
//function detailSortieExpressionBesoin(PDO $bdBASI, caisseController $basiController): void {
//    try {
//        $token = trim((string) inputValueCaisse('token', ''));
//        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
//        $idEB = (int) $basiController->tokendecrypt($token);
//        if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }
//
//        // Aucune sortie (même la simple consultation de l'écran de saisie)
//        // n'est autorisée tant qu'un inventaire est en cours.
//        if (inventaireEnCours($bdBASI)) {
//            echo json_encode(['status' => 'error', 'inventaireEnCours' => true, 'message' => MSG_INVENTAIRE_SORTIE]);
//            return;
//        }
//
//        $stmtC = $bdBASI->prepare("
//            SELECT eb.id, eb.nom_expression, eb.date_creation, eb.idStatut,
//                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
//            FROM expression_besoin eb
//            JOIN utilisateurs u ON eb.idUtilisateur = u.id
//            WHERE eb.id = ? AND eb.idStatut = 3
//            LIMIT 1
//        ");
//        $stmtC->execute([$idEB]);
//        $expression = $stmtC->fetch(PDO::FETCH_ASSOC);
//        if (!$expression) {
//            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable ou non éligible à la sortie.']);
//            return;
//        }
//
//        $stmtLignes = $bdBASI->prepare("
//            SELECT ebp.id AS idEBP, ebp.idP, ebp.quantite_reelle, ebp.quantite_sortie, p.nomproduit AS designation, p.Stock_actuel
//            FROM expression_besoin_produit ebp
//            JOIN product p ON ebp.idP = p.idP
//            WHERE ebp.idEB = ? AND ebp.statut = 1
//            ORDER BY ebp.id ASC
//        ");
//        $stmtLignes->execute([$idEB]);
//        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
//
//        foreach ($lignes as &$l) {
//            $quantiteReelle = (float) $l['quantite_reelle'];
//            $quantiteSortie = (float) $l['quantite_sortie'];
//            $stockActuel    = (float) $l['Stock_actuel'];
//            $restant        = max(0, $quantiteReelle - $quantiteSortie);
//            $maxSortable    = min($restant, max(0, $stockActuel));
//
//            $l['quantite_restante']  = $restant;
//            $l['stock_disponible']   = $stockActuel;
//            // Plafond de saisie — le comptable peut saisir n'importe quelle
//            // valeur ENTRE 0 et max_sortable, jamais plus. Si le stock est à
//            // 0, max_sortable = 0 : aucune sortie possible pour cette ligne.
//            $l['max_sortable']       = $maxSortable;
//            $l['entierement_servie'] = ($restant <= 0.001);
//        }
//        unset($l);
//
//        $expression['lignes'] = $lignes;
//        echo json_encode(['status' => 'success', 'expression' => $expression]);
//    } catch (\Throwable $e) {
//        error_log('[Caisse][detailSortieExpressionBesoin] ' . $e->getMessage());
//        erreurSqlCaisse("Impossible de charger le détail de la sortie.");
//    }
//}

/**
 * Effectue la sortie de stock pour une expression de besoin, à partir des
 * quantités SAISIES par le comptable (plafonnées serveur-side par ligne à
 * MIN(quantite_restante, Stock_actuel) — jamais plus). Si le stock d'une
 * ligne est à 0, aucune sortie n'est possible pour cette ligne (quantité
 * doit être 0 ou absente). Décrémente le stock, incrémente quantite_sortie,
 * historise, et passe l'expression à 5 (Terminée) si c'est la dernière
 * sortie permettant de tout satisfaire.
 *
 * Champs attendus : token, quantites: [{ idEBP, quantite }, ...]
 */
//function effectuerSortieExpressionBesoin(PDO $bdBASI, caisseController $basiController, int $sessionUserId, string $sessionMatricule): void {
//    try {
//        $token = trim((string) inputValueCaisse('token', ''));
//        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
//        $idEB = (int) $basiController->tokendecrypt($token);
//        if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }
//
//        // Aucune sortie n'est possible tant qu'un inventaire est en cours.
//        if (inventaireEnCours($bdBASI)) {
//            echo json_encode(['status' => 'error', 'inventaireEnCours' => true, 'message' => MSG_INVENTAIRE_SORTIE]);
//            return;
//        }
//
//        $quantitesSaisies = inputValueCaisse('quantites', []);
//        if (!is_array($quantitesSaisies)) $quantitesSaisies = [];
//        $quantitesParLigne = [];
//        foreach ($quantitesSaisies as $q) {
//            $idEBP = (int) ($q['idEBP'] ?? 0);
//            if ($idEBP <= 0) continue;
//            $quantitesParLigne[$idEBP] = (float) ($q['quantite'] ?? 0);
//        }
//
//        $stmtC = $bdBASI->prepare("
//            SELECT id, nom_expression, idUtilisateur, idDirection, date_creation, idStatut
//            FROM expression_besoin
//            WHERE id = ? AND idStatut IN (3, 5)
//            LIMIT 1
//        ");
//        $stmtC->execute([$idEB]);
//        $expression = $stmtC->fetch(PDO::FETCH_ASSOC);
//        if (!$expression) {
//            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable ou non éligible à la sortie.']);
//            return;
//        }
//
//        $stmtLignes = $bdBASI->prepare("
//            SELECT ebp.id AS idEBP, ebp.idP, ebp.quantite_reelle, ebp.quantite_sortie, p.Stock_actuel
//            FROM expression_besoin_produit ebp
//            JOIN product p ON ebp.idP = p.idP
//            WHERE ebp.idEB = ? AND ebp.statut = 1
//            FOR UPDATE
//        ");
//
//        date_default_timezone_set('Africa/Dakar');
//        $dateEnregistrement = date('Y-m-d H:i:s');
//        $motif = "Sortie de stock (par $sessionMatricule)";
//
//        $bdBASI->beginTransaction();
//
//        $stmtLignes->execute([$idEB]);
//        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
//        if (empty($lignes)) {
//            $bdBASI->rollBack();
//            echo json_encode(['status' => 'error', 'message' => 'Cette expression de besoin ne contient aucune ligne active.']);
//            return;
//        }
//
//        $stmtDecrementerStock = $bdBASI->prepare("UPDATE product SET Stock_actuel = Stock_actuel - ?, retrait = retrait + ? WHERE idP = ?");
//        $stmtIncrementerSortie = $bdBASI->prepare("UPDATE expression_besoin_produit SET quantite_sortie = quantite_sortie + ?, dateEnregistrement = ? WHERE id = ?");
//        $stmtHistoLigne = $bdBASI->prepare("
//            INSERT INTO historique_expression_besoin_produit (idEBP, idEB, idP, quantite, quantite_reelle, quantite_sortie, statut, motif, dateEnregistrement, idUtilisateur)
//            SELECT id, idEB, idP, quantite, quantite_reelle, quantite_sortie, statut, ?, ?, ?
//            FROM expression_besoin_produit
//            WHERE id = ?
//        ");
//        // Snapshot complet du produit APRÈS mise à jour (Stock_actuel et
//        // retrait déjà décrémenté/incrémenté à ce stade) — même principe que
//        // les autres tables historique_* du projet.
//        $stmtHistoProduct = $bdBASI->prepare("
//            INSERT INTO historique_product
//                (product_id, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, motif, dateEnregistrement)
//            SELECT idP, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, ?, ?
//            FROM product
//            WHERE idP = ?
//        ");
//
//        $auMoinsUneSortie = false;
//        foreach ($lignes as $l) {
//            $idEBP = (int) $l['idEBP'];
//            if (!isset($quantitesParLigne[$idEBP])) continue; // rien saisi pour cette ligne
//
//            $quantiteReelle = (float) $l['quantite_reelle'];
//            $quantiteSortie = (float) $l['quantite_sortie'];
//            $stockActuel    = (float) $l['Stock_actuel'];
//            $restant = max(0, $quantiteReelle - $quantiteSortie);
//            if ($restant <= 0.001) continue; // déjà entièrement servie
//
//            // Aucune sortie possible si le stock est à 0.
//            $maxSortable = min($restant, max(0, $stockActuel));
//            if ($maxSortable <= 0.001) continue;
//
//            $quantiteSaisie = $quantitesParLigne[$idEBP];
//            if ($quantiteSaisie <= 0) continue; // rien à sortir pour cette ligne
//
//            // Garde-fou serveur : jamais plus que MIN(restant, stock).
//            if ($quantiteSaisie > $maxSortable + 0.001) {
//                $bdBASI->rollBack();
//                echo json_encode(['status' => 'error', 'message' => "La quantité saisie dépasse le maximum autorisé (restant à sortir / stock disponible) pour au moins une ligne."]);
//                return;
//            }
//
//            $idProduit = (int) $l['idP'];
//
//            $stmtDecrementerStock->execute([$quantiteSaisie, $quantiteSaisie, $idProduit]);
//            $stmtIncrementerSortie->execute([$quantiteSaisie, $dateEnregistrement, $idEBP]);
//            $stmtHistoLigne->execute([$motif, $dateEnregistrement, $sessionUserId, $idEBP]);
//            $stmtHistoProduct->execute([$motif, $dateEnregistrement, $idProduit]);
//            $auMoinsUneSortie = true;
//        }
//
//        if (!$auMoinsUneSortie) {
//            $bdBASI->rollBack();
//            echo json_encode(['status' => 'error', 'message' => "Aucune sortie effectuée : veuillez saisir au moins une quantité valide."]);
//            return;
//        }
//
//        // Vérifie si TOUTES les lignes actives sont désormais entièrement
//        // servies (quantite_reelle = quantite_sortie) → dernière sortie.
//        $stmtVerif = $bdBASI->prepare("
//            SELECT COUNT(*) AS n
//            FROM expression_besoin_produit
//            WHERE idEB = ? AND statut = 1 AND quantite_reelle > quantite_sortie
//        ");
//        $stmtVerif->execute([$idEB]);
//        $ligneRestante = (int) ($stmtVerif->fetch(PDO::FETCH_ASSOC)['n'] ?? 0);
//        $entierementSatisfaite = ($ligneRestante === 0);
//
//        // 5 = Partiellement livré (au moins une sortie effectuée, mais des
//        //     lignes restent à servir) ; 6 = Terminé (toutes les lignes
//        //     entièrement servies).
//        $nouveauStatutEB = $entierementSatisfaite ? 6 : 5;
//        $motifStatutEB = $entierementSatisfaite
//            ? "Expression de besoin entièrement satisfaite (par $sessionMatricule)"
//            : "Sortie partielle enregistrée (par $sessionMatricule)";
//
//        $bdBASI->prepare("UPDATE expression_besoin SET idStatut = ? WHERE id = ?")->execute([$nouveauStatutEB, $idEB]);
//        $bdBASI->prepare("
//            INSERT INTO historique_expression_besoin
//                (idEB, nom_expression, idUtilisateur, idDirection, date_creation, idStatut, motif, dateEnregistrement)
//            SELECT id, nom_expression, idUtilisateur, idDirection, date_creation, idStatut, ?, ?
//            FROM expression_besoin
//            WHERE id = ?
//        ")->execute([$motifStatutEB, $dateEnregistrement, $idEB]);
//
//        $bdBASI->commit();
//
//        echo json_encode([
//            'status'  => 'success',
//            'message' => $entierementSatisfaite
//                ? 'Sortie enregistrée avec succès : expression de besoin entièrement satisfaite.'
//                : 'Sortie enregistrée avec succès : reliquat en attente de réapprovisionnement.',
//            'entierementSatisfaite' => $entierementSatisfaite,
//        ]);
//    } catch (\Throwable $e) {
//        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
//        error_log('[Caisse][effectuerSortieExpressionBesoin] ' . $e->getMessage());
//        erreurSqlCaisse("Impossible d'effectuer la sortie de stock.");
//    }
//}


/**
 * fonctions-modifiees-sortie.php
 * Extrait des 3 fonctions de caisseController.php modifiées pour le
 * module Sortie de produits (statuts 3=À sortir, 5=Partiellement livré,
 * 6=Terminé) — à coller telles quelles à la place des versions existantes
 * dans caisseController.php (mêmes noms de fonction, remplacement direct).
 *
 * Routage concerné (déjà en place, inchangé) :
 *   option 26 = listerExpressionsBesoinSortie
 *   option 27 = detailSortieExpressionBesoin
 *   option 28 = effectuerSortieExpressionBesoin
 */


/**
 * Détail d'une expression de besoin pour l'écran de sortie : pour chaque
 * ligne active, quantité restant à sortir, stock disponible, et quantité
 * qui SERA effectivement sortie (MIN des deux — jamais modifiable).
 */
function detailSortieExpressionBesoin(PDO $bdBASI, caisseController $basiController): void
{
    try {
        $token = trim((string)inputValueCaisse('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idEB = (int)$basiController->tokendecrypt($token);
        if ($idEB <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        // Aucune sortie (même la simple consultation de l'écran de saisie)
        // n'est autorisée tant qu'un inventaire est en cours.
        if (inventaireEnCours($bdBASI)) {
            echo json_encode(['status' => 'error', 'inventaireEnCours' => true, 'message' => MSG_INVENTAIRE_SORTIE]);
            return;
        }

        $stmtC = $bdBASI->prepare("
            SELECT eb.id, eb.nom_expression, eb.date_creation, eb.idStatut,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM expression_besoin eb
            JOIN utilisateurs u ON eb.idUtilisateur = u.id
            WHERE eb.id = ? AND eb.idStatut IN (3, 5)
            LIMIT 1
        ");
        $stmtC->execute([$idEB]);
        $expression = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$expression) {
            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable ou non éligible à la sortie.']);
            return;
        }

        $stmtLignes = $bdBASI->prepare("
            SELECT ebp.id AS idEBP, ebp.idP, ebp.quantite_reelle, ebp.quantite_sortie, ebp.quantite_annulee, p.nomproduit AS designation, p.Stock_actuel
            FROM expression_besoin_produit ebp
            JOIN product p ON ebp.idP = p.idP
            WHERE ebp.idEB = ? AND ebp.statut = 1
            ORDER BY ebp.id ASC
        ");
        $stmtLignes->execute([$idEB]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

        foreach ($lignes as &$l) {
            $quantiteReelle = (float)$l['quantite_reelle'];
            $quantiteSortie = (float)$l['quantite_sortie'];
            $stockActuel = (float)$l['Stock_actuel'];
            $restant = max(0, $quantiteReelle - (float)($l['quantite_annulee'] ?? 0) - $quantiteSortie); // net du solde annulé
            $maxSortable = min($restant, max(0, $stockActuel));

            $l['quantite_restante'] = $restant;
            $l['stock_disponible'] = $stockActuel;
            // Plafond de saisie — le comptable peut saisir n'importe quelle
            // valeur ENTRE 0 et max_sortable, jamais plus. Si le stock est à
            // 0, max_sortable = 0 : aucune sortie possible pour cette ligne.
            $l['max_sortable'] = $maxSortable;
            $l['entierement_servie'] = ($restant <= 0.001);
        }
        unset($l);

        $expression['lignes'] = $lignes;
        echo json_encode(['status' => 'success', 'expression' => $expression]);
    } catch (\Throwable $e) {
        error_log('[Caisse][detailSortieExpressionBesoin] ' . $e->getMessage());
        erreurSqlCaisse("Impossible de charger le détail de la sortie.");
    }
}

/**
 * Effectue la sortie de stock pour une expression de besoin, à partir des
 * quantités SAISIES par le comptable (plafonnées serveur-side par ligne à
 * MIN(quantite_restante, Stock_actuel) — jamais plus). Si le stock d'une
 * ligne est à 0, aucune sortie n'est possible pour cette ligne (quantité
 * doit être 0 ou absente). Décrémente le stock, incrémente quantite_sortie,
 * historise, et passe l'expression à 5 (Terminée) si c'est la dernière
 * sortie permettant de tout satisfaire.
 *
 * Champs attendus : token, quantites: [{ idEBP, quantite }, ...]
 */
function effectuerSortieExpressionBesoin(PDO $bdBASI, caisseController $basiController, int $sessionUserId, string $sessionMatricule): void
{
    try {
        $token = trim((string)inputValueCaisse('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idEB = (int)$basiController->tokendecrypt($token);
        if ($idEB <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        // Aucune sortie n'est possible tant qu'un inventaire est en cours.
        if (inventaireEnCours($bdBASI)) {
            echo json_encode(['status' => 'error', 'inventaireEnCours' => true, 'message' => MSG_INVENTAIRE_SORTIE]);
            return;
        }

        $quantitesSaisies = inputValueCaisse('quantites', []);
        if (!is_array($quantitesSaisies)) $quantitesSaisies = [];
        $quantitesParLigne = [];
        foreach ($quantitesSaisies as $q) {
            $idEBP = (int)($q['idEBP'] ?? 0);
            if ($idEBP <= 0) continue;
            $quantitesParLigne[$idEBP] = (float)($q['quantite'] ?? 0);
        }

        $stmtC = $bdBASI->prepare("
            SELECT id, nom_expression, idUtilisateur, idDirection, date_creation, idStatut
            FROM expression_besoin
            WHERE id = ? AND idStatut IN (3, 5)
            LIMIT 1
        ");
        $stmtC->execute([$idEB]);
        $expression = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$expression) {
            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable ou non éligible à la sortie.']);
            return;
        }

        $stmtLignes = $bdBASI->prepare("
            SELECT ebp.id AS idEBP, ebp.idP, ebp.quantite_reelle, ebp.quantite_sortie, ebp.quantite_annulee, p.Stock_actuel
            FROM expression_besoin_produit ebp
            JOIN product p ON ebp.idP = p.idP
            WHERE ebp.idEB = ? AND ebp.statut = 1
            FOR UPDATE
        ");

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $motif = "Sortie de stock (par $sessionMatricule)";

        $bdBASI->beginTransaction();

        // Contrôle FINAL, dans la transaction : un inventaire a pu démarrer depuis le premier contrôle.
        if (inventaireEnCoursVerrou($bdBASI)) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'inventaireEnCours' => true, 'message' => MSG_INVENTAIRE_SORTIE]);
            return;
        }

        $stmtLignes->execute([$idEB]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
        if (empty($lignes)) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Cette expression de besoin ne contient aucune ligne active.']);
            return;
        }

        $stmtDecrementerStock = $bdBASI->prepare("UPDATE product SET Stock_actuel = Stock_actuel - ?, retrait = retrait + ? WHERE idP = ?");
        $stmtIncrementerSortie = $bdBASI->prepare("UPDATE expression_besoin_produit SET quantite_sortie = quantite_sortie + ?, dateEnregistrement = ? WHERE id = ?");
        // Snapshot complet du produit APRÈS mise à jour (Stock_actuel et
        // retrait déjà décrémenté/incrémenté à ce stade) — même principe que
        // les autres tables historique_* du projet.
        $stmtHistoProduct = $bdBASI->prepare("
            INSERT INTO historique_product
                (product_id, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, motif, dateEnregistrement)
            SELECT idP, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, ?, ?
            FROM product
            WHERE idP = ?
        ");

        $auMoinsUneSortie = false;
        $idBS = null; // bon de sortie : créé à la première ligne effectivement sortie
        foreach ($lignes as $l) {
            $idEBP = (int)$l['idEBP'];
            if (!isset($quantitesParLigne[$idEBP])) continue; // rien saisi pour cette ligne

            $quantiteReelle = (float)$l['quantite_reelle'];
            $quantiteSortie = (float)$l['quantite_sortie'];
            $stockActuel = (float)$l['Stock_actuel'];
            $restant = max(0, $quantiteReelle - (float)($l['quantite_annulee'] ?? 0) - $quantiteSortie); // net du solde annulé
            if ($restant <= 0.001) continue; // déjà entièrement servie

            // Aucune sortie possible si le stock est à 0.
            $maxSortable = min($restant, max(0, $stockActuel));
            if ($maxSortable <= 0.001) continue;

            $quantiteSaisie = $quantitesParLigne[$idEBP];
            if ($quantiteSaisie <= 0) continue; // rien à sortir pour cette ligne

            if (!estEntierPositif($quantiteSaisie)) {
                $bdBASI->rollBack();
                echo json_encode(['status' => 'error', 'message' => "Les quantités sorties doivent être des nombres entiers."]);
                return;
            }

            // Garde-fou serveur : jamais plus que MIN(restant, stock).
            if ($quantiteSaisie > $maxSortable + 0.001) {
                $bdBASI->rollBack();
                echo json_encode(['status' => 'error', 'message' => "La quantité saisie dépasse le maximum autorisé (restant à sortir / stock disponible) pour au moins une ligne."]);
                return;
            }

            $idProduit = (int)$l['idP'];

            $stmtDecrementerStock->execute([$quantiteSaisie, $quantiteSaisie, $idProduit]);
            $stmtIncrementerSortie->execute([$quantiteSaisie, $dateEnregistrement, $idEBP]);
            ebw_historiserEBP($bdBASI, $idEBP, $motif, $sessionUserId, $dateEnregistrement);
            $stmtHistoProduct->execute([$motif, $dateEnregistrement, $idProduit]);

            // Bon de sortie : une action de sortie = un bon, livré puis
            // confirmé bon par bon (magasinier / demandeur).
            if ($idBS === null) {
                $idBS = ebw_creerBonSortie($bdBASI, $idEB, $sessionUserId, $dateEnregistrement, "Bon créé à la sortie de stock (par $sessionMatricule)");
            }
            ebw_ajouterLigneBon($bdBASI, $idBS, $idEBP, $idProduit, $quantiteSaisie, $sessionUserId, "Sortie de stock (par $sessionMatricule)", $dateEnregistrement);
            $auMoinsUneSortie = true;
        }

        if (!$auMoinsUneSortie) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'message' => "Aucune sortie effectuée : veuillez saisir au moins une quantité valide."]);
            return;
        }

        // Statut de la demande déduit des quantités (5 = Sortie partielle,
        // 6 = Sortie totale) par la fonction centrale — plus de transition
        // codée localement.
        $nouveauStatutEB = ebw_recalculerStatutEB($bdBASI, $idEB, "Sortie de stock enregistrée (par $sessionMatricule)", $dateEnregistrement);
        $entierementSatisfaite = ($nouveauStatutEB !== null && $nouveauStatutEB >= 6);

        $bdBASI->commit();

        echo json_encode([
            'status' => 'success',
            'message' => $entierementSatisfaite
                ? 'Sortie enregistrée avec succès : expression de besoin entièrement satisfaite.'
                : 'Sortie enregistrée avec succès : reliquat en attente de réapprovisionnement.',
            'entierementSatisfaite' => $entierementSatisfaite,
            'idBS' => $idBS,
        ]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Caisse][effectuerSortieExpressionBesoin] ' . $e->getMessage());
        erreurSqlCaisse("Impossible d'effectuer la sortie de stock.");
    }
}
/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Sortie de stock INVESTISSEMENT (page sortieProduit.php)
   Même mécanique que la sortie Fonctionnement ci-dessus, adaptée aux tables
   expression_besoin_investissement / expression_besoin_investissement_produit.
   La demande (chef de service) est déjà validée d'elle-même (pas de
   Validée/Rejetée) : le statut "Soumise" (2) est directement actionnable
   par le comptable ici. Statuts : 1=Brouillon (jamais visible ici),
   2=Soumise, 3=Partiellement sorti, 4=Terminé.
═══════════════════════════════════════════════════════════════════════════ */

function listerExpressionsBesoinSortieInvestissement(PDO $bdBASI, caisseController $basiController): void {
    try {
        // Filtre par défaut "2,3" (Soumise + Partiellement sorti). "4" (Terminé)
        // regroupe désormais 4 à 8 : tout ce qui est entièrement sorti, quel que
        // soit l'avancement de la livraison/réception (comme le Fonctionnement).
        $statutFiltreRaw = trim((string) inputValueCaisse('statut', '2,3'));
        $statutsDemandes = array_map('intval', array_filter(explode(',', $statutFiltreRaw), 'strlen'));
        if (in_array(4, $statutsDemandes, true)) $statutsDemandes = array_merge($statutsDemandes, [5, 6, 7, 8]);
        $statutsValides  = array_values(array_intersect($statutsDemandes, [2, 3, 4, 5, 6, 7, 8]));
        if (empty($statutsValides)) $statutsValides = [2, 3];

        $stmtStats = $bdBASI->query("
            SELECT idStatut, COUNT(*) AS n
            FROM expression_besoin_investissement
            WHERE idStatut IN (2, 3, 4, 5, 6, 7, 8)
            GROUP BY idStatut
        ");
        $stats = ['2' => 0, '3' => 0, '4' => 0];
        foreach ($stmtStats->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cle = (string) (int) $row['idStatut'];
            if (in_array($cle, ['5', '6', '7', '8'], true)) $cle = '4'; // regroupées avec Terminé
            if (isset($stats[$cle])) $stats[$cle] += (int) $row['n'];
        }

        $placeholders = implode(',', array_fill(0, count($statutsValides), '?'));
        $sql = "
            SELECT ebi.id, ebi.nom_expression, ebi.date_creation, ebi.idStatut,
                   d.nom_direction, d.code_direction,
                   (SELECT COUNT(*) FROM expression_besoin_investissement_produit ebip WHERE ebip.idEBI = ebi.id AND ebip.statut = 1) AS nombre_produits,
                   (SELECT COALESCE(SUM(GREATEST(ebip.quantite_demandee - ebip.quantite_annulee - ebip.quantite_sortie, 0)), 0)
                    FROM expression_besoin_investissement_produit ebip WHERE ebip.idEBI = ebi.id AND ebip.statut = 1) AS solde_a_sortir,
                   (SELECT COUNT(*) FROM ecart_bon_sortie_ebi e WHERE e.idEBI = ebi.id AND e.statut = 1) AS nombre_ecarts_ouverts
            FROM expression_besoin_investissement ebi
            LEFT JOIN direction d ON ebi.idDirection = d.id
            WHERE ebi.idStatut IN ($placeholders)
        ";
        $params = $statutsValides;
        $sql .= " ORDER BY (ebi.idStatut >= 4) ASC, ebi.date_creation ASC, ebi.id ASC";

        $stmt = $bdBASI->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['id']);
        }
        unset($r);

        echo json_encode([
            'status'            => 'success',
            'data'              => $rows,
            'nombre_total'      => count($rows),
            'stats'             => $stats,
            'ecartsOuverts'     => (int) $bdBASI->query("SELECT COUNT(*) FROM ecart_bon_sortie_ebi WHERE statut = 1")->fetchColumn(),
            'inventaireEnCours' => inventaireEnCours($bdBASI),
        ]);
    } catch (\Throwable $e) {
        error_log('[Caisse][listerExpressionsBesoinSortieInvestissement] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger la liste des sorties Investissement.');
    }
}

function detailSortieExpressionBesoinInvestissement(PDO $bdBASI, caisseController $basiController): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEBI = (int) $basiController->tokendecrypt($token);
        if ($idEBI <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        if (inventaireEnCours($bdBASI)) {
            echo json_encode(['status' => 'error', 'inventaireEnCours' => true, 'message' => MSG_INVENTAIRE_SORTIE]);
            return;
        }

        $stmtC = $bdBASI->prepare("
            SELECT ebi.id, ebi.nom_expression, ebi.date_creation, ebi.idStatut,
                   d.nom_direction, d.code_direction
            FROM expression_besoin_investissement ebi
            LEFT JOIN direction d ON ebi.idDirection = d.id
            WHERE ebi.id = ? AND ebi.idStatut IN (2, 3)
            LIMIT 1
        ");
        $stmtC->execute([$idEBI]);
        $expression = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$expression) {
            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable ou non éligible à la sortie.']);
            return;
        }

        $stmtLignes = $bdBASI->prepare("
            SELECT ebip.id AS idEBIP, ebip.id_produit, ebip.quantite_demandee, ebip.quantite_sortie, ebip.quantite_annulee,
                   p.nomproduit AS designation, p.Stock_actuel
            FROM expression_besoin_investissement_produit ebip
            JOIN product p ON ebip.id_produit = p.idP
            WHERE ebip.idEBI = ? AND ebip.statut = 1
            ORDER BY ebip.id ASC
        ");
        $stmtLignes->execute([$idEBI]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

        foreach ($lignes as &$l) {
            $quantiteDemandee = (float) $l['quantite_demandee'];
            $quantiteSortie   = (float) $l['quantite_sortie'];
            $stockActuel      = (float) $l['Stock_actuel'];
            $restant = max(0, $quantiteDemandee - (float) ($l['quantite_annulee'] ?? 0) - $quantiteSortie); // net du solde annulé
            $maxSortable = min($restant, max(0, $stockActuel));

            $l['quantite_restante']  = $restant;
            $l['stock_disponible']   = $stockActuel;
            $l['max_sortable']       = $maxSortable;
            $l['entierement_servie'] = ($restant <= 0.001);
        }
        unset($l);

        $expression['lignes'] = $lignes;
        echo json_encode(['status' => 'success', 'expression' => $expression]);
    } catch (\Throwable $e) {
        error_log('[Caisse][detailSortieExpressionBesoinInvestissement] ' . $e->getMessage());
        erreurSqlCaisse("Impossible de charger le détail de la sortie.");
    }
}

/**
 * Effectue la sortie de stock Investissement — même plafonnement strict
 * que le Fonctionnement : MIN(quantite_restante, Stock_actuel), jamais
 * plus. Décrémente Stock_actuel (et retrait, comme le Fonctionnement),
 * incrémente quantite_sortie, historise, et passe l'expression à 3
 * (Partiellement sorti) ou 4 (Terminé).
 *
 * Champs attendus : token, quantites: [{ idEBIP, quantite }, ...]
 */
function effectuerSortieExpressionBesoinInvestissement(PDO $bdBASI, caisseController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEBI = (int) $basiController->tokendecrypt($token);
        if ($idEBI <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        if (inventaireEnCours($bdBASI)) {
            echo json_encode(['status' => 'error', 'inventaireEnCours' => true, 'message' => MSG_INVENTAIRE_SORTIE]);
            return;
        }

        $quantitesSaisies = inputValueCaisse('quantites', []);
        if (!is_array($quantitesSaisies)) $quantitesSaisies = [];
        $quantitesParLigne = [];
        foreach ($quantitesSaisies as $q) {
            $idEBIP = (int) ($q['idEBIP'] ?? 0);
            if ($idEBIP <= 0) continue;
            $quantitesParLigne[$idEBIP] = (float) ($q['quantite'] ?? 0);
        }

        $stmtC = $bdBASI->prepare("
            SELECT id, nom_expression, idDirection, idUtilisateur, date_creation, idStatut
            FROM expression_besoin_investissement
            WHERE id = ? AND idStatut IN (2, 3)
            LIMIT 1
        ");
        $stmtC->execute([$idEBI]);
        $expression = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$expression) {
            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable ou non éligible à la sortie.']);
            return;
        }

        $stmtLignes = $bdBASI->prepare("
            SELECT ebip.id AS idEBIP, ebip.id_produit, ebip.quantite_demandee, ebip.quantite_sortie, ebip.quantite_annulee, p.Stock_actuel
            FROM expression_besoin_investissement_produit ebip
            JOIN product p ON ebip.id_produit = p.idP
            WHERE ebip.idEBI = ? AND ebip.statut = 1
            FOR UPDATE
        ");

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $motif = "Sortie de stock Investissement (par $sessionMatricule)";

        $bdBASI->beginTransaction();

        // Contrôle FINAL, dans la transaction : un inventaire a pu démarrer depuis le premier contrôle.
        if (inventaireEnCoursVerrou($bdBASI)) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'inventaireEnCours' => true, 'message' => MSG_INVENTAIRE_SORTIE]);
            return;
        }

        $stmtLignes->execute([$idEBI]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
        if (empty($lignes)) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Cette expression de besoin ne contient aucune ligne active.']);
            return;
        }

        $stmtDecrementerStock  = $bdBASI->prepare("UPDATE product SET Stock_actuel = Stock_actuel - ?, retrait = retrait + ? WHERE idP = ?");
        $stmtIncrementerSortie = $bdBASI->prepare("UPDATE expression_besoin_investissement_produit SET quantite_sortie = quantite_sortie + ?, dateEnregistrement = ? WHERE id = ?");
        $stmtHistoProduct = $bdBASI->prepare("
            INSERT INTO historique_product
                (product_id, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, motif, dateEnregistrement)
            SELECT idP, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, ?, ?
            FROM product
            WHERE idP = ?
        ");

        $auMoinsUneSortie = false;
        $idBS = null; // bon de sortie : créé à la première ligne effectivement sortie
        foreach ($lignes as $l) {
            $idEBIP = (int) $l['idEBIP'];
            if (!isset($quantitesParLigne[$idEBIP])) continue;

            $quantiteDemandee = (float) $l['quantite_demandee'];
            $quantiteAnnulee  = (float) ($l['quantite_annulee'] ?? 0);
            $quantiteSortie   = (float) $l['quantite_sortie'];
            $stockActuel      = (float) $l['Stock_actuel'];
            $restant = max(0, $quantiteDemandee - $quantiteAnnulee - $quantiteSortie);
            if ($restant <= 0.001) continue;

            $maxSortable = min($restant, max(0, $stockActuel));
            if ($maxSortable <= 0.001) continue;

            $quantiteSaisie = $quantitesParLigne[$idEBIP];
            if ($quantiteSaisie <= 0) continue;

            if (!estEntierPositif($quantiteSaisie)) {
                $bdBASI->rollBack();
                echo json_encode(['status' => 'error', 'message' => "Les quantités sorties doivent être des nombres entiers."]);
                return;
            }

            if ($quantiteSaisie > $maxSortable + 0.001) {
                $bdBASI->rollBack();
                echo json_encode(['status' => 'error', 'message' => "La quantité saisie dépasse le maximum autorisé (restant à sortir / stock disponible) pour au moins une ligne."]);
                return;
            }

            $idProduit = (int) $l['id_produit'];

            $stmtDecrementerStock->execute([$quantiteSaisie, $quantiteSaisie, $idProduit]);
            $stmtIncrementerSortie->execute([$quantiteSaisie, $dateEnregistrement, $idEBIP]);
            ebwi_historiserEBIP($bdBASI, $idEBIP, $motif, $sessionUserId, $dateEnregistrement);
            $stmtHistoProduct->execute([$motif, $dateEnregistrement, $idProduit]);

            // Bon de sortie : une action de sortie = un bon, livré puis
            // confirmé bon par bon (magasinier / demandeur).
            if ($idBS === null) {
                $idBS = ebwi_creerBonSortie($bdBASI, $idEBI, $sessionUserId, $dateEnregistrement, "Bon créé à la sortie de stock (par $sessionMatricule)");
            }
            ebwi_ajouterLigneBon($bdBASI, $idBS, $idEBIP, $idProduit, $quantiteSaisie, $sessionUserId, "Sortie de stock (par $sessionMatricule)", $dateEnregistrement);
            $auMoinsUneSortie = true;
        }

        if (!$auMoinsUneSortie) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'message' => "Aucune sortie effectuée : veuillez saisir au moins une quantité valide."]);
            return;
        }

        // Statut déduit des quantités (3 = Partiellement sorti, 4 = Terminé)
        // par la fonction centrale — plus de transition codée localement.
        $nouveauStatutEBI = ebwi_recalculerStatutEBI($bdBASI, $idEBI, "Sortie de stock enregistrée (par $sessionMatricule)", $dateEnregistrement);
        $entierementSatisfaite = ($nouveauStatutEBI !== null && $nouveauStatutEBI >= 4);

        $bdBASI->commit();

        echo json_encode([
            'status'  => 'success',
            'message' => $entierementSatisfaite
                ? 'Sortie enregistrée avec succès : expression de besoin entièrement satisfaite.'
                : 'Sortie enregistrée avec succès : reliquat en attente.',
            'entierementSatisfaite' => $entierementSatisfaite,
            'idBS' => $idBS,
        ]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Caisse][effectuerSortieExpressionBesoinInvestissement] ' . $e->getMessage());
        erreurSqlCaisse("Impossible d'effectuer la sortie de stock.");
    }
}


/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Consultation des produits Investissement (profil Comptable)
   Page listeProduitsInvestissementComptable.php : vue en lecture seule,
   croisant chaque produit Investissement avec chaque direction qui en a
   reçu en stock (livraison_produit_repartition, mode='stock'), avec ce qui
   a déjà été sorti et ce qui reste disponible. Purement consultatif — le
   comptable ne peut rien saisir ici, seulement observer la répartition.
═══════════════════════════════════════════════════════════════════════════ */

function listerCatalogueProduitsInvestissementComptable(PDO $bdBASI): void {
    try {
        // Tous les produits Investissement, même ceux qui n'ont jamais reçu
        // de stock — le comptable doit pouvoir les consulter aussi.
        $stmt = $bdBASI->query("
            SELECT p.idP, p.nomproduit, p.code_produit, p.Stock_actuel, p.Seuil_limite,
                   p.retrait, p.id_statut, p.date_creation,
                   r.nom_rubrique, sr.nom_sous_rubrique,
                   CONCAT(u.prenom, ' ', u.nom) AS nom_createur
            FROM product p
            LEFT JOIN sousRubrique sr ON p.id_sous_rubrique = sr.id
            LEFT JOIN rubrique r ON sr.rubrique_id = r.id
            LEFT JOIN utilisateurs u ON p.idUtilisateur = u.id
            WHERE p.id_type_product = 2
            ORDER BY p.nomproduit ASC
        ");
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $produits, 'nombre_total' => count($produits)]);
    } catch (\Throwable $e) {
        error_log('[Caisse][listerCatalogueProduitsInvestissementComptable] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger le catalogue des produits Investissement.');
    }
}

/**
 * Statistiques Investissement adaptées au comptable : celui-ci voit TOUT
 * (aucun masquage), y compris le stock global consolidé et le nombre de
 * directions concernées par au moins une réception en stock.
 */
function statistiquesProduitsInvestissementComptable(PDO $bdBASI): void {
    try {
        $stmtProduits = $bdBASI->query("
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN id_statut = 1 THEN 1 ELSE 0 END) AS en_service,
                   SUM(CASE WHEN id_statut = 0 THEN 1 ELSE 0 END) AS hors_service,
                   COALESCE(SUM(Stock_actuel), 0) AS stock_global_total
            FROM product
            WHERE id_type_product = 2
        ");
        $produitsStats = $stmtProduits->fetch(PDO::FETCH_ASSOC);

        $stmtDirections = $bdBASI->query("
            SELECT COUNT(DISTINCT idDirection) AS n
            FROM livraison_produit_repartition
            WHERE mode = 'stock'
        ");
        $nombreDirections = (int) ($stmtDirections->fetch(PDO::FETCH_ASSOC)['n'] ?? 0);

        echo json_encode([
            'status' => 'success',
            'stats'  => [
                'total_produits'      => (int) $produitsStats['total'],
                'en_service'          => (int) $produitsStats['en_service'],
                'hors_service'        => (int) $produitsStats['hors_service'],
                'stock_global_total'  => (float) $produitsStats['stock_global_total'],
                'nombre_directions'   => $nombreDirections,
            ],
        ]);
    } catch (\Throwable $e) {
        error_log('[Caisse][statistiquesProduitsInvestissementComptable] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger les statistiques.');
    }
}

/**
 * Détail de la répartition par direction d'UN produit Investissement (pour
 * le bouton "Détail" du catalogue comptable) : ce que chaque direction a
 * reçu en stock, déjà sorti, réservé, et ce qu'il lui reste disponible.
 */
function detailRepartitionProduitInvestissement(PDO $bdBASI): void {
    try {
        $idP = (int) inputValueCaisse('idP', 0);
        if ($idP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Produit manquant.']); return; }

        $stmtProduit = $bdBASI->prepare("SELECT nomproduit, Stock_actuel FROM product WHERE idP = ? AND id_type_product = 2 LIMIT 1");
        $stmtProduit->execute([$idP]);
        $produit = $stmtProduit->fetch(PDO::FETCH_ASSOC);
        if (!$produit) { echo json_encode(['status' => 'error', 'message' => 'Produit introuvable.']); return; }

        $stmtRecu = $bdBASI->prepare("
            SELECT lpr.idDirection, SUM(lpr.quantite) AS total_recu
            FROM livraison_produit_repartition lpr
            JOIN livraison_produit lp ON lpr.idLP = lp.id
            WHERE lpr.mode = 'stock' AND lp.idP = ?
            GROUP BY lpr.idDirection
        ");
        $stmtRecu->execute([$idP]);
        $recuParDirection = [];
        foreach ($stmtRecu->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $recuParDirection[(int) $row['idDirection']] = (float) $row['total_recu'];
        }

        $stmtSorti = $bdBASI->prepare("
            SELECT ebi.idDirection, SUM(ebip.quantite_sortie) AS total_sorti
            FROM expression_besoin_investissement_produit ebip
            JOIN expression_besoin_investissement ebi ON ebip.idEBI = ebi.id
            WHERE ebip.id_produit = ? AND ebip.statut = 1
            GROUP BY ebi.idDirection
        ");
        $stmtSorti->execute([$idP]);
        $sortiParDirection = [];
        foreach ($stmtSorti->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sortiParDirection[(int) $row['idDirection']] = (float) $row['total_sorti'];
        }

        $stmtReserve = $bdBASI->prepare("
            SELECT ebi.idDirection, SUM(ebip.quantite_demandee - ebip.quantite_sortie) AS total_reserve
            FROM expression_besoin_investissement_produit ebip
            JOIN expression_besoin_investissement ebi ON ebip.idEBI = ebi.id
            WHERE ebip.id_produit = ? AND ebip.statut = 1 AND ebi.idStatut IN (1, 2, 3)
            GROUP BY ebi.idDirection
        ");
        $stmtReserve->execute([$idP]);
        $reserveParDirection = [];
        foreach ($stmtReserve->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $reserveParDirection[(int) $row['idDirection']] = (float) $row['total_reserve'];
        }

        $stmtDirections = $bdBASI->query("SELECT id, nom_direction, code_direction FROM direction ORDER BY nom_direction ASC");
        $repartition = [];
        foreach ($stmtDirections->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $idDirection = (int) $d['id'];
            $recu = $recuParDirection[$idDirection] ?? 0.0;
            if ($recu <= 0.001) continue; // direction jamais concernée par ce produit

            $sorti = $sortiParDirection[$idDirection] ?? 0.0;
            $reserve = $reserveParDirection[$idDirection] ?? 0.0;

            $repartition[] = [
                'idDirection'         => $idDirection,
                'nom_direction'       => $d['nom_direction'],
                'code_direction'      => $d['code_direction'],
                'quantite_recue'      => $recu,
                'quantite_sortie'     => $sorti,
                'quantite_reservee'   => $reserve,
                'quantite_disponible' => max(0.0, $recu - $sorti - $reserve),
            ];
        }

        echo json_encode([
            'status'      => 'success',
            'produit'     => ['idP' => $idP, 'nomproduit' => $produit['nomproduit'], 'stock_actuel' => (float) $produit['Stock_actuel']],
            'repartition' => $repartition,
        ]);
    } catch (\Throwable $e) {
        error_log('[Caisse][detailRepartitionProduitInvestissement] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger la répartition de ce produit.');
    }
}



/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Écarts de réception et clôture du solde (Expression de besoin,
   Fonctionnement). Logique métier fusionnée ci-dessus dans ce fichier.
═══════════════════════════════════════════════════════════════════════════ */

/** OPTION 43 — Écarts de réception à régulariser (déclarés par les demandeurs). */
function listerEcartsBonSortie(PDO $bdBASI): void {
    try {
        $stmt = $bdBASI->query("
            SELECT e.id, e.quantite_ecart, e.commentaire, e.dateSignalement,
                   bs.numero_bon, eb.nom_expression, p.nomproduit AS designation,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM ecart_bon_sortie_eb e
            JOIN bon_sortie_eb_ligne bl ON bl.id = e.idBSL
            JOIN bon_sortie_eb bs ON bs.id = e.idBS
            JOIN expression_besoin eb ON eb.id = e.idEB
            JOIN product p ON p.idP = bl.idP
            LEFT JOIN utilisateurs u ON u.id = e.idUtilisateurSignale
            WHERE e.statut = 1
            ORDER BY e.dateSignalement ASC, e.id ASC
        ");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Throwable $e) {
        error_log('[Caisse][listerEcartsBonSortie] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger les écarts de réception.');
    }
}

/**
 * OPTION 44 — Régularise un écart : correction_livraison | retour_stock | perte.
 * Un retour en stock modifie le stock : interdit pendant un inventaire.
 * Champs attendus : idEcart, resolution, commentaire
 */
function regulariserEcartBonSortie(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        $idEcart     = (int) inputValueCaisse('idEcart', 0);
        $resolution  = trim((string) inputValueCaisse('resolution', ''));
        $commentaire = trim((string) inputValueCaisse('commentaire', ''));
        if ($idEcart <= 0) { echo json_encode(['status' => 'error', 'message' => 'Écart manquant.']); return; }
        if ($resolution === 'retour_stock' && inventaireEnCours($bdBASI)) {
            echo json_encode(['status' => 'error', 'inventaireEnCours' => true, 'message' => MSG_INVENTAIRE_RETOUR]);
            return;
        }

        date_default_timezone_set('Africa/Dakar');
        $bdBASI->beginTransaction();
        if ($resolution === 'retour_stock' && inventaireEnCoursVerrou($bdBASI)) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'inventaireEnCours' => true, 'message' => MSG_INVENTAIRE_RETOUR]);
            return;
        }
        ebw_regulariserEcart($bdBASI, $idEcart, $resolution, $commentaire, $sessionUserId, $sessionMatricule, date('Y-m-d H:i:s'));
        $bdBASI->commit();
        echo json_encode(['status' => 'success', 'message' => 'Écart régularisé avec succès.']);
    } catch (EbwException $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Caisse][regulariserEcartBonSortie] ' . $e->getMessage());
        erreurSqlCaisse("Impossible de régulariser cet écart.");
    }
}

/**
 * OPTION 45 — Clôture le solde d'une demande (renonce à sortir le reste).
 * Champs attendus : token, commentaire (motif obligatoire)
 */
function cloturerSoldeExpressionBesoin(PDO $bdBASI, caisseController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        $commentaire = trim((string) inputValueCaisse('commentaire', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEB = (int) $basiController->tokendecrypt($token);
        if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        date_default_timezone_set('Africa/Dakar');
        $bdBASI->beginTransaction();
        [$statut, $solde] = ebw_cloturerSolde($bdBASI, $idEB, $commentaire, $sessionUserId, $sessionMatricule, date('Y-m-d H:i:s'));
        $bdBASI->commit();

        $messages = [
            10 => "Demande annulée : aucun produit n'avait été sorti.",
            9  => 'Solde clôturé : la demande sera close une fois toutes les livraisons reçues.',
        ];
        echo json_encode([
            'status'  => 'success',
            'message' => $messages[$statut] ?? 'Solde clôturé : les livraisons en cours se poursuivent normalement.',
            'nouveauStatut' => $statut,
            'solde'   => $solde,
        ]);
    } catch (EbwException $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Caisse][cloturerSoldeExpressionBesoin] ' . $e->getMessage());
        erreurSqlCaisse("Impossible de clôturer le solde.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Écarts de réception et clôture du solde (Expression de besoin,
   INVESTISSEMENT). Miroir exact du module Fonctionnement ci-dessus. Logique
   métier dans le bloc ebwi_* fusionné en tête de ce fichier.
═══════════════════════════════════════════════════════════════════════════ */

/** OPTION 46 — Écarts de réception Investissement à régulariser. */
function listerEcartsBonSortieInvestissement(PDO $bdBASI): void {
    try {
        $stmt = $bdBASI->query("
            SELECT e.id, e.quantite_ecart, e.commentaire, e.dateSignalement,
                   bs.numero_bon, ebi.nom_expression, p.nomproduit AS designation,
                   d.nom_direction, d.code_direction
            FROM ecart_bon_sortie_ebi e
            JOIN bon_sortie_ebi_ligne bl ON bl.id = e.idBSL
            JOIN bon_sortie_ebi bs ON bs.id = e.idBS
            JOIN expression_besoin_investissement ebi ON ebi.id = e.idEBI
            LEFT JOIN direction d ON ebi.idDirection = d.id
            JOIN product p ON p.idP = bl.id_produit
            WHERE e.statut = 1
            ORDER BY e.dateSignalement ASC, e.id ASC
        ");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Throwable $e) {
        error_log('[Caisse][listerEcartsBonSortieInvestissement] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger les écarts de réception Investissement.');
    }
}

/**
 * OPTION 47 — Régularise un écart Investissement.
 * Champs attendus : idEcart, resolution, commentaire
 */
function regulariserEcartBonSortieInvestissement(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        $idEcart     = (int) inputValueCaisse('idEcart', 0);
        $resolution  = trim((string) inputValueCaisse('resolution', ''));
        $commentaire = trim((string) inputValueCaisse('commentaire', ''));
        if ($idEcart <= 0) { echo json_encode(['status' => 'error', 'message' => 'Écart manquant.']); return; }
        if ($resolution === 'retour_stock' && inventaireEnCours($bdBASI)) {
            echo json_encode(['status' => 'error', 'inventaireEnCours' => true, 'message' => MSG_INVENTAIRE_RETOUR]);
            return;
        }

        date_default_timezone_set('Africa/Dakar');
        $bdBASI->beginTransaction();
        if ($resolution === 'retour_stock' && inventaireEnCoursVerrou($bdBASI)) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'inventaireEnCours' => true, 'message' => MSG_INVENTAIRE_RETOUR]);
            return;
        }
        ebwi_regulariserEcart($bdBASI, $idEcart, $resolution, $commentaire, $sessionUserId, $sessionMatricule, date('Y-m-d H:i:s'));
        $bdBASI->commit();
        echo json_encode(['status' => 'success', 'message' => 'Écart régularisé avec succès.']);
    } catch (EbwException $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Caisse][regulariserEcartBonSortieInvestissement] ' . $e->getMessage());
        erreurSqlCaisse("Impossible de régulariser cet écart.");
    }
}

/**
 * OPTION 48 — Clôture le solde d'une demande Investissement.
 * Champs attendus : token, commentaire (motif obligatoire)
 */
function cloturerSoldeExpressionBesoinInvestissement(PDO $bdBASI, caisseController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        $commentaire = trim((string) inputValueCaisse('commentaire', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEBI = (int) $basiController->tokendecrypt($token);
        if ($idEBI <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        date_default_timezone_set('Africa/Dakar');
        $bdBASI->beginTransaction();
        [$statut, $solde] = ebwi_cloturerSolde($bdBASI, $idEBI, $commentaire, $sessionUserId, $sessionMatricule, date('Y-m-d H:i:s'));
        $bdBASI->commit();

        $messages = [
            8 => "Demande annulée : aucun produit n'avait été sorti.",
            7 => 'Solde clôturé : la demande sera close une fois toutes les livraisons reçues.',
        ];
        echo json_encode([
            'status'  => 'success',
            'message' => $messages[$statut] ?? 'Solde clôturé : les livraisons en cours se poursuivent normalement.',
            'nouveauStatut' => $statut,
            'solde'   => $solde,
        ]);
    } catch (EbwException $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Caisse][cloturerSoldeExpressionBesoinInvestissement] ' . $e->getMessage());
        erreurSqlCaisse("Impossible de clôturer le solde.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Gestion de l'inventaire (profil Comptable)
   Fusionné depuis inventaireController.php (contrôleur dédié initial),
   à la demande, dans ce contrôleur unique du Comptable.

   ⚠️ Hypothèses de schéma (NOUVELLES tables — cohérentes avec le reste du
   projet : product PK=idP, souscategorie/categorie déjà établies) :
   - `inventaire` : id, reference, etat, idStatut, dateDebut, dateFin,
     observation_operateur, observation_comptable, idUtilisateur,
     dateEnregistrement, + colonne AJOUTÉE `dateSoumission` DATETIME NULL
     ("Date de soumission par l'opérateur" — dateFin sert de "Date de
     validation par le comptable" ET "Date de fin", simultanées).
   - `historique_inventaire` : idI, reference, etat, idStatut, dateDebut,
     dateFin, dateSoumission, observation_operateur, observation_comptable,
     idUtilisateur, motif, dateEnregistrement.
   - `inventaire_produit` : id (PK auto), idI, idP, quantite_systeme,
     quantite_operateur, quantite_valide, dateEnregistrement.
   - `historique_inventaire_produit` : idIP, idI, idP, quantite_systeme,
     quantite_operateur, quantite_valide, motif, dateEnregistrement.
   - `product` : idP, nomproduit, code_produit, Stock_actuel, id_statut,
     id_type_product, id_Sous_categorie. Seuls les produits
     id_type_product = 1 sont intégrés à l'inventaire.
   - `souscategorie` : id, nom_sous_categorie, categorie_id.
   - `categorie` : id, nom_categorie.
   - idStatut de `inventaire` : 1 = Créé, 2 = Brouillon (opérateur),
     3 = Soumis (opérateur), 4 = Terminé (validé par le comptable).
   - etat de `inventaire` : 1 = En cours, 0 = Terminé.

   La saisie terrain (Opérateur) reste dans un contrôleur séparé :
   inventaireOperateurController.php (rôle distinct du Comptable).
═══════════════════════════════════════════════════════════════════════════ */

/* ═══════════════════════════════════════════════════════════════════════════
   HELPERS
═══════════════════════════════════════════════════════════════════════════ */

/** Insère un instantané complet de l'en-tête dans historique_inventaire. */
function insererHistoriqueInventaire(PDO $bdBASI, int $idI, string $motif, string $dateEnregistrement): void {
    $bdBASI->prepare("
        INSERT INTO historique_inventaire
            (idI, reference, etat, idStatut, dateDebut, dateFin, dateSoumission, observation_operateur, observation_comptable, idUtilisateur, motif, dateEnregistrement)
        SELECT id, reference, etat, idStatut, dateDebut, dateFin, dateSoumission, observation_operateur, observation_comptable, idUtilisateur, ?, ?
        FROM inventaire
        WHERE id = ?
    ")->execute([$motif, $dateEnregistrement, $idI]);
}

/** Insère un instantané d'une ligne dans historique_inventaire_produit. */
function insererHistoriqueInventaireProduit(PDO $bdBASI, int $idIP, string $motif, string $dateEnregistrement): void {
    $bdBASI->prepare("
        INSERT INTO historique_inventaire_produit
            (idIP, idI, idP, quantite_systeme, quantite_operateur, quantite_valide, motif, dateEnregistrement)
        SELECT id, idI, idP, quantite_systeme, quantite_operateur, quantite_valide, ?, ?
        FROM inventaire_produit
        WHERE id = ?
    ")->execute([$motif, $dateEnregistrement, $idIP]);
}

/**
 * Requête commune (catégorie/sous-catégorie/produit) réutilisée par
 * plusieurs écrans.
 */
function sqlLignesInventaireBase(): string {
    return "
        SELECT ip.id AS idIP, ip.idP, ip.quantite_systeme, ip.quantite_operateur, ip.quantite_valide,
               p.nomproduit, p.code_produit, p.id_statut AS produit_id_statut,
               sc.nom_sous_categorie, c.nom_categorie
        FROM inventaire_produit ip
        JOIN product p ON ip.idP = p.idP
        JOIN souscategorie sc ON p.id_Sous_categorie = sc.id
        JOIN categorie c ON sc.categorie_id = c.id
        WHERE ip.idI = ?
        ORDER BY c.nom_categorie ASC, sc.nom_sous_categorie ASC, p.nomproduit ASC
    ";
}





/* ═══════════════════════════════════════════════════════════════════════════
   ACTIONS
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Crée un nouvel inventaire — impossible si un inventaire etat = 1 existe
 * déjà. Génère automatiquement les lignes inventaire_produit à partir de
 * tous les produits id_type_product = 1 (quantite_systeme = Stock_actuel
 * au moment de la création).
 */
function creerInventaire(PDO $bdBASI, caisseController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $stmtActif = $bdBASI->query("SELECT id FROM inventaire WHERE etat = 1 LIMIT 1");
        if ($stmtActif && $stmtActif->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Un inventaire est déjà en cours : impossible d\'en créer un nouveau.']);
            return;
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $reference = 'inventaire_' . date('Ymd_His');

        $bdBASI->beginTransaction();

        $bdBASI->prepare("
            INSERT INTO inventaire
                (reference, etat, idStatut, dateDebut, dateFin, dateSoumission, observation_operateur, observation_comptable, idUtilisateur, dateEnregistrement)
            VALUES (?, 1, 1, ?, NULL, NULL, NULL, NULL, ?, ?)
        ")->execute([$reference, $dateEnregistrement, $sessionUserId, $dateEnregistrement]);
        $idI = (int) $bdBASI->lastInsertId();

        insererHistoriqueInventaire($bdBASI, $idI, "Création de l'inventaire (par $sessionMatricule)", $dateEnregistrement);

        $stmtProduits = $bdBASI->query("SELECT idP, Stock_actuel FROM product WHERE id_type_product = 1");
        $produits = $stmtProduits ? $stmtProduits->fetchAll(PDO::FETCH_ASSOC) : [];

        $stmtInsertLigne = $bdBASI->prepare("
            INSERT INTO inventaire_produit (idI, idP, quantite_systeme, quantite_operateur, quantite_valide, dateEnregistrement)
            VALUES (?, ?, ?, NULL, NULL, ?)
        ");
        foreach ($produits as $p) {
            $stmtInsertLigne->execute([$idI, (int) $p['idP'], (float) $p['Stock_actuel'], $dateEnregistrement]);
            $idIP = (int) $bdBASI->lastInsertId();
            insererHistoriqueInventaireProduit($bdBASI, $idIP, "Création de la ligne d'inventaire (par $sessionMatricule)", $dateEnregistrement);
        }

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Inventaire créé avec succès.', 'reference' => $reference, 'nombreProduits' => count($produits)]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Inventaire][creerInventaire] ' . $e->getMessage());
        erreurSqlCaisse("Impossible de créer l'inventaire.");
    }
}

/**
 * Liste de tous les inventaires (en cours et terminés).
 */
function listerInventaires(PDO $bdBASI, caisseController $basiController): void {
    try {
        $stmt = $bdBASI->query("
            SELECT i.id, i.reference, i.etat, i.idStatut, i.dateDebut, i.dateFin, i.dateSoumission,
                   CONCAT(u.prenom, ' ', u.nom) AS createur,
                   (SELECT COUNT(*) FROM inventaire_produit ip WHERE ip.idI = i.id) AS nombre_produits
            FROM inventaire i
            JOIN utilisateurs u ON i.idUtilisateur = u.id
            ORDER BY i.dateDebut DESC, i.id DESC
        ");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['id']);
        }
        unset($r);

        $inventaireActif = null;
        foreach ($rows as $r) {
            if ((int) $r['etat'] === 1) { $inventaireActif = $r; break; }
        }

        echo json_encode(['status' => 'success', 'data' => $rows, 'inventaireActif' => $inventaireActif]);
    } catch (\Throwable $e) {
        error_log('[Inventaire][listerInventaires] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger la liste des inventaires.');
    }
}

/**
 * Détail complet d'un inventaire (en-tête + lignes), pour l'écran de
 * validation (idStatut = 3) ou la consultation en mode Détail (idStatut = 4).
 */
function detailInventaire(PDO $bdBASI, caisseController $basiController): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idI = (int) $basiController->tokendecrypt($token);
        if ($idI <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmtI = $bdBASI->prepare("
            SELECT i.id, i.reference, i.etat, i.idStatut, i.dateDebut, i.dateFin, i.dateSoumission,
                   i.observation_operateur, i.observation_comptable, i.dateEnregistrement,
                   CONCAT(u.prenom, ' ', u.nom) AS createur
            FROM inventaire i
            JOIN utilisateurs u ON i.idUtilisateur = u.id
            WHERE i.id = ?
            LIMIT 1
        ");
        $stmtI->execute([$idI]);
        $inventaire = $stmtI->fetch(PDO::FETCH_ASSOC);
        if (!$inventaire) {
            echo json_encode(['status' => 'error', 'message' => 'Inventaire introuvable.']);
            return;
        }

        $stmtLignes = $bdBASI->prepare(sqlLignesInventaireBase());
        $stmtLignes->execute([$idI]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

        // Constat (Conforme / Excédent / Déficitaire), pertinent en mode Détail
        // (idStatut = 4, quantite_valide renseignée).
        foreach ($lignes as &$l) {
            if ($l['quantite_valide'] !== null) {
                $systeme = (float) $l['quantite_systeme'];
                $valide  = (float) $l['quantite_valide'];
                if (abs($systeme - $valide) < 0.001) {
                    $l['constat'] = 'Conforme';
                } elseif ($valide > $systeme) {
                    $l['constat'] = 'Excédent';
                } else {
                    $l['constat'] = 'Déficitaire';
                }
            } else {
                $l['constat'] = null;
            }
        }
        unset($l);

        $inventaire['lignes'] = $lignes;
        echo json_encode(['status' => 'success', 'inventaire' => $inventaire]);
    } catch (\Throwable $e) {
        error_log('[Inventaire][detailInventaire] ' . $e->getMessage());
        erreurSqlCaisse("Impossible de charger le détail de l'inventaire.");
    }
}

/**
 * Validation par le comptable (idStatut 3 → 4) : enregistre quantite_valide
 * par ligne, met à jour Stock_actuel de chaque produit, et clôture
 * l'inventaire (etat 1 → 0).
 *
 * Champs attendus : token, lignes: [{ idIP, quantite_valide }, ...],
 * observation_comptable (optionnelle)
 */
function validerInventaire(PDO $bdBASI, caisseController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idI = (int) $basiController->tokendecrypt($token);
        if ($idI <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $observationComptable = trim((string) inputValueCaisse('observation_comptable', ''));
        if ($observationComptable === '') {
            echo json_encode(['status' => 'error', 'message' => "L'observation est obligatoire pour valider l'inventaire."]);
            return;
        }
        $lignesEnvoyees = inputValueCaisse('lignes', []);
        if (!is_array($lignesEnvoyees)) $lignesEnvoyees = [];

        $stmtI = $bdBASI->prepare("SELECT id, idStatut FROM inventaire WHERE id = ? LIMIT 1");
        $stmtI->execute([$idI]);
        $inventaire = $stmtI->fetch(PDO::FETCH_ASSOC);
        if (!$inventaire) {
            echo json_encode(['status' => 'error', 'message' => 'Inventaire introuvable.']);
            return;
        }
        if ((int) $inventaire['idStatut'] !== 3) {
            echo json_encode(['status' => 'error', 'message' => 'Seul un inventaire Soumis peut être validé.']);
            return;
        }

        $stmtLignesActives = $bdBASI->prepare("SELECT id, idP, quantite_operateur, quantite_systeme FROM inventaire_produit WHERE idI = ?");
        $stmtLignesActives->execute([$idI]);
        $lignesActives = [];
        foreach ($stmtLignesActives->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lignesActives[(int)$row['id']] = $row;
        }

        // Quantité validée : OBLIGATOIREMENT renseignée par le comptable pour
        // chaque ligne — aucun repli silencieux, aucune valeur NULL tolérée.
        $quantitesValidees = [];
        foreach ($lignesEnvoyees as $l) {
            $idIP = (int) ($l['idIP'] ?? 0);
            if (!isset($lignesActives[$idIP])) continue;
            $qv = $l['quantite_valide'] ?? null;
            if ($qv === null || $qv === '') continue;
            $quantitesValidees[$idIP] = (float) $qv;
        }
        $idsManquants = array_diff(array_keys($lignesActives), array_keys($quantitesValidees));
        if (!empty($idsManquants)) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Impossible de valider : ' . count($idsManquants) . " produit(s) n'ont pas de quantité validée renseignée.",
            ]);
            return;
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $motif = "Validation par le comptable (par $sessionMatricule)";

        $bdBASI->beginTransaction();

        $stmtUpdateLigne = $bdBASI->prepare("UPDATE inventaire_produit SET quantite_valide = ?, dateEnregistrement = ? WHERE id = ?");
        $stmtUpdateStock = $bdBASI->prepare("UPDATE product SET Stock_actuel = ? WHERE idP = ?");
        foreach ($quantitesValidees as $idIP => $quantiteValide) {
            $stmtUpdateLigne->execute([$quantiteValide, $dateEnregistrement, $idIP]);
            insererHistoriqueInventaireProduit($bdBASI, $idIP, $motif, $dateEnregistrement);

            $idProduit = (int) $lignesActives[$idIP]['idP'];
            $stmtUpdateStock->execute([$quantiteValide, $idProduit]);
        }

        $bdBASI->prepare("
            UPDATE inventaire
            SET idStatut = 4, etat = 0, dateFin = ?, observation_comptable = ?
            WHERE id = ?
        ")->execute([$dateEnregistrement, $observationComptable, $idI]);
        insererHistoriqueInventaire($bdBASI, $idI, $motif, $dateEnregistrement);

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Inventaire validé avec succès : le stock a été mis à jour.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Inventaire][validerInventaire] ' . $e->getMessage());
        erreurSqlCaisse("Impossible de valider l'inventaire.");
    }
}

function uploaderFD(PDO $bdBASI, caisseController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string)($_POST['token'] ?? ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmtC = $bdBASI->prepare("SELECT id,nom_commande FROM passer_achat_et_paiement WHERE id = ? AND idStatut = 4 AND idTypePAP = 1 LIMIT 1");
        $stmtC->execute([$idPAP]);
        if (!$stmtC->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable ou non éligible (doit être une commande Acceptée).']);
            return;
        }

        $stmtDoc = $bdBASI->prepare("SELECT id FROM documents_pap WHERE idPAP = ? AND statut = 1 AND choix = 1 LIMIT 1");
        $stmtDoc->execute([$idPAP]);
        $document = $stmtDoc->fetch(PDO::FETCH_ASSOC);
        if (!$document) {
            echo json_encode(['status' => 'error', 'message' => "Aucun fournisseur retenu n'a été trouvé pour cette commande."]);
            return;
        }

        if (empty($_FILES['bc']) || $_FILES['bc']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'Le bon de commande (PDF) est obligatoire.']);
            return;
        }
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['bc']['tmp_name']);
        if ($mimeType !== 'application/pdf') {
            echo json_encode(['status' => 'error', 'message' => 'La facture définitive doit être un fichier PDF.']);
            return;
        }

        $tmp_dossier = UPLOAD_DIR_COMMANDES.'/'.$stmtC['nom_commande'];
        if (!is_dir($tmp_dossier)) {
            mkdir($tmp_dossier, 0755, true);
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $nomFichier   = 'facture_difitive_' . $idPAP . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $cheminAbsolu = $tmp_dossier . '/' . $nomFichier;
        if (!move_uploaded_file($_FILES['bc']['tmp_name'], $cheminAbsolu)) {
            throw new \RuntimeException("Échec de l'enregistrement du bon de commande.");
        }
        $cheminPublic = UPLOAD_URL_COMMANDES .'/'.$stmtC['nom_commande'].'/' . $nomFichier;

        $bdBASI->beginTransaction();

        $bdBASI->prepare("
            UPDATE documents_pap SET facture_definitive = ? WHERE id = ?
        ")->execute([$cheminPublic, $document['id']]);


        //  date_default_timezone_set('Africa/Dakar');
        // $dateEnregistrement = date('Y-m-d H:i:s');



        // $bdBASI->prepare("
        //     INSERT INTO historique_passer_achat_et_paiement
        //         (idPAP, nom_commande, montant_total, idStatut, idTypePAP, dateCreation, id_mode_reglement,
        //          id_mode_paiement, nb_tranche, idUtilisateur, motif, dateEnregistrement)
        //     SELECT id, nom_commande, montant_total, idStatut, idTypePAP, dateCreation, id_mode_reglement,
        //            id_mode_paiement, nb_tranche, ?, ?, ?
        //     FROM passer_achat_et_paiement
        //     WHERE id = ?
        // ")->execute([
        //     $sessionUserId,
        //     "La facture définitive a été téléversée (par $sessionMatricule)",
        //     $dateEnregistrement, $idPAP,
        // ]);

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Bon de commande téléversé avec succès.']);
    } catch (\Throwable $e) {
        error_log('[Operations][uploaderFD] ' . $e->getMessage());
        erreurSql('Impossible de téléverser la facture définitive.');
    }
}


function envoyerCaisse(PDO $bdBASI, caisseController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmtC = $bdBASI->prepare("SELECT id, idTypePAP FROM passer_achat_et_paiement WHERE id = ? AND idStatut = 4 LIMIT 1");
        $stmtC->execute([$idPAP]);
        $dossier = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$dossier) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable ou non éligible (doit être Accepté).']);
            return;
        }

        if ((int)$dossier['idTypePAP'] === 1) {
            $stmtBC = $bdBASI->prepare("
                SELECT id FROM documents_pap
                WHERE idPAP = ? AND statut = 1 AND choix = 1
                  AND facture_definitive IS NOT NULL AND facture_definitive != ''
                LIMIT 1
            ");
            $stmtBC->execute([$idPAP]);
            if (!$stmtBC->fetch()) {
                echo json_encode(['status' => 'error', 'message' => "Le bon de commande (BC) doit être téléversé avant l'envoi à la caisse."]);
                return;
            }
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        $bdBASI->prepare("UPDATE passer_achat_et_paiement SET idStatut = 6 WHERE id = ?")->execute([$idPAP]);

        $bdBASI->prepare("
            INSERT INTO historique_passer_achat_et_paiement
                (idPAP, nom_commande, montant_total, idStatut, idTypePAP, dateCreation, id_mode_reglement,
                 id_mode_paiement, nb_tranche, idUtilisateur, motif, dateEnregistrement)
            SELECT id, nom_commande, montant_total, idStatut, idTypePAP, dateCreation, id_mode_reglement,
                   id_mode_paiement, nb_tranche, ?, ?, ?
            FROM passer_achat_et_paiement
            WHERE id = ?
        ")->execute([
            $sessionUserId,
            "Envoyé à la caisse (par $sessionMatricule)",
            $dateEnregistrement, $idPAP,
        ]);

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Dossier envoyé à la caisse avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Operations][envoyerCaisse] ' . $e->getMessage());
        erreurSql("Impossible d'envoyer le dossier à la caisse.");
    }
}




/**
 * caisseController-diff-sortie.php
 * Parties de caisseController.php modifiées/ajoutées pour la dernière
 * demande (filtre combiné + boutons Voir/Consulter sortie).
 *
 * ── 1) listerExpressionsBesoinSortie (option 26) — MODIFIÉE ──
 * Remplace intégralement la fonction existante du même nom.
 */

function listerExpressionsBesoinSortie(PDO $bdBASI, caisseController $basiController): void {
    try {
        // Filtre : liste de statuts séparés par virgule. Par défaut "3,5"
        // (À sortir + Partiellement livré combinés) pour que le comptable ne
        // perde jamais de vue ce qui reste à traiter. Autres valeurs possibles
        // envoyées par le front-end : "3", "5", "6", ou "3,5,6" (Tous).
        $statutFiltreRaw = trim((string) inputValueCaisse('statut', '3,5'));
        $statutsDemandes = array_map('intval', array_filter(explode(',', $statutFiltreRaw), 'strlen'));
        // Le filtre "6" (Sortie totale) regroupe 6, 7 et 8 : tout ce qui est
        // entièrement sorti, quel que soit l'avancement de la livraison.
        if (in_array(6, $statutsDemandes, true)) $statutsDemandes = array_merge($statutsDemandes, [7, 8, 9, 10]);
        $statutsValides  = array_values(array_intersect($statutsDemandes, [3, 5, 6, 7, 8, 9, 10]));
        if (empty($statutsValides)) $statutsValides = [3, 5];

        // Statistiques par statut, INDÉPENDANTES du filtre appliqué à la liste.
        $stmtStats = $bdBASI->query("
            SELECT idStatut, COUNT(*) AS n
            FROM expression_besoin
            WHERE idStatut IN (3, 5, 6, 7, 8, 9, 10)
            GROUP BY idStatut
        ");
        $stats = ['3' => 0, '5' => 0, '6' => 0];
        foreach ($stmtStats->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cle = (string)(int)$row['idStatut'];
            if (in_array($cle, ['7', '8', '9', '10'], true)) $cle = '6'; // Sortie totale et suites
            if (isset($stats[$cle])) $stats[$cle] += (int)$row['n'];
        }

        $placeholders = implode(',', array_fill(0, count($statutsValides), '?'));
        $sql = "
            SELECT eb.id, eb.nom_expression, eb.date_creation, eb.idStatut, eb.idDirection,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur,
                   (SELECT COUNT(*) FROM expression_besoin_produit ebp WHERE ebp.idEB = eb.id AND ebp.statut = 1) AS nombre_produits,
                   (SELECT COALESCE(SUM(GREATEST(COALESCE(ebp.quantite_reelle, 0) - ebp.quantite_annulee - ebp.quantite_sortie, 0)), 0)
                    FROM expression_besoin_produit ebp WHERE ebp.idEB = eb.id AND ebp.statut = 1) AS solde_a_sortir,
                   (SELECT COUNT(*) FROM ecart_bon_sortie_eb e WHERE e.idEB = eb.id AND e.statut = 1) AS nombre_ecarts_ouverts
            FROM expression_besoin eb
            JOIN utilisateurs u ON eb.idUtilisateur = u.id
            WHERE eb.idStatut IN ($placeholders)
        ";
        $params = $statutsValides;
        // Tri : les dossiers encore actionnables (À sortir / Partiellement
        // livré) d'abord, les Terminés en dernier, puis par date de création.
        $sql .= " ORDER BY (eb.idStatut >= 6) ASC, eb.date_creation ASC, eb.id ASC";

        $stmt = $bdBASI->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['id']);
        }
        unset($r);

        echo json_encode([
            'status'           => 'success',
            'data'             => $rows,
            'nombre_total'     => count($rows),
            'stats'            => $stats,
            'ecartsOuverts'    => (int) $bdBASI->query("SELECT COUNT(*) FROM ecart_bon_sortie_eb WHERE statut = 1")->fetchColumn(),
            'inventaireEnCours'=> inventaireEnCours($bdBASI),
        ]);
    } catch (\Throwable $e) {
        error_log('[Caisse][listerExpressionsBesoinSortie] ' . $e->getMessage());
        erreurSqlCaisse('Impossible de charger la liste des sorties de produits.');
    }
}



function voirExpressionBesoinCaisse(PDO $bdBASI, caisseController $basiController): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEB = (int) $basiController->tokendecrypt($token);
        if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmt = $bdBASI->prepare("
            SELECT eb.id, eb.nom_expression, eb.date_creation, eb.idStatut, eb.idDirection,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM expression_besoin eb
            JOIN utilisateurs u ON eb.idUtilisateur = u.id
            WHERE eb.id = ?
            LIMIT 1
        ");
        $stmt->execute([$idEB]);
        $expression = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$expression) {
            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable.']);
            return;
        }

        $stmtProduits = $bdBASI->prepare("
            SELECT ebp.id AS idEBP, ebp.idP, ebp.quantite, ebp.quantite_reelle, ebp.quantite_sortie, ebp.quantite_annulee,
                   p.nomproduit as designation
            FROM expression_besoin_produit ebp
            JOIN product p ON ebp.idP = p.idP
            WHERE ebp.idEB = ? AND ebp.statut = 1
            ORDER BY ebp.id ASC
        ");
        $stmtProduits->execute([$idEB]);
        $produits = $stmtProduits->fetchAll(PDO::FETCH_ASSOC);

        foreach ($produits as &$p) {
            $qteReelle  = $p['quantite_reelle'] !== null ? (float) $p['quantite_reelle'] : null;
            $qteSortie  = (float) ($p['quantite_sortie'] ?? 0);
            $qteAnnulee = (float) ($p['quantite_annulee'] ?? 0);

            if ($qteReelle === null) {
                $p['quantite_restante'] = null;
                $p['statut_ligne'] = 'En attente';
            } else {
                $cible = max(0, $qteReelle - $qteAnnulee); // validée, nette du solde annulé
                $p['quantite_restante'] = max(0, $cible - $qteSortie);
                if ($qteAnnulee > 0.001 && $cible <= 0.001) {
                    $p['statut_ligne'] = 'Annulée';
                } elseif ($cible > 0 && $qteSortie >= $cible - 0.001) {
                    $p['statut_ligne'] = 'Sortie totale';
                } elseif ($qteSortie > 0) {
                    $p['statut_ligne'] = 'Sortie partielle';
                } else {
                    $p['statut_ligne'] = 'En attente';
                }
            }
        }
        unset($p);
        $expression['produits'] = $produits;

        // Motif de rejet (dernière transition vers idStatut = 4), si applicable.
        $motifRejet = null;
        if ((int) $expression['idStatut'] === 4) {
            $stmtMotif = $bdBASI->prepare("
                SELECT motif FROM historique_expression_besoin
                WHERE idEB = ? AND idStatut = 4
                ORDER BY dateEnregistrement DESC
                LIMIT 1
            ");
            $stmtMotif->execute([$idEB]);
            $motifRejet = $stmtMotif->fetch(PDO::FETCH_ASSOC)['motif'] ?? null;
        }
        $expression['motif_rejet'] = $motifRejet;

        $stmtHisto = $bdBASI->prepare("
            SELECT idStatut, motif, dateEnregistrement
            FROM historique_expression_besoin
            WHERE idEB = ?
            ORDER BY dateEnregistrement ASC
        ");
        $stmtHisto->execute([$idEB]);
        $expression['historique'] = $stmtHisto->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'expression' => $expression]);
    } catch (\Throwable $e) {
        error_log('[Caisse][voirExpressionBesoinCaisse] ' . $e->getMessage());
        erreurSqlCaisse("Impossible de charger le suivi de l'expression de besoin.");
    }
}

/**
 * "Consulter sortie" — informations détaillées sur les sorties déjà
 * effectuées (date, quantité, utilisateur), ligne par ligne. Même logique
 * que "Informations sur les sorties" côté chef de direction, sans
 * restriction de direction.
 */
function consulterSortiesExpressionBesoinCaisse(PDO $bdBASI, caisseController $basiController): void {
    try {
        $token = trim((string) inputValueCaisse('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEB = (int) $basiController->tokendecrypt($token);
        if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmtC = $bdBASI->prepare("
            SELECT eb.id, eb.nom_expression, eb.date_creation, eb.idStatut,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM expression_besoin eb
            JOIN utilisateurs u ON eb.idUtilisateur = u.id
            WHERE eb.id = ?
            LIMIT 1
        ");
        $stmtC->execute([$idEB]);
        $expression = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$expression) {
            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable.']);
            return;
        }

        $stmtLignes = $bdBASI->prepare("
            SELECT ebp.id AS idEBP, ebp.idP, ebp.quantite, ebp.quantite_reelle, ebp.quantite_sortie, p.nomproduit AS designation
            FROM expression_besoin_produit ebp
            JOIN product p ON ebp.idP = p.idP
            WHERE ebp.idEB = ? AND ebp.statut = 1
            ORDER BY ebp.id ASC
        ");
        $stmtLignes->execute([$idEB]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

        $stmtHisto = $bdBASI->prepare("
            SELECT h.idEBP, h.quantite_sortie, h.dateEnregistrement, h.motif,
                   CONCAT(u.prenom, ' ', u.nom) AS utilisateur
            FROM historique_expression_besoin_produit h
            LEFT JOIN utilisateurs u ON h.idUtilisateur = u.id
            WHERE h.idEB = ? AND h.motif LIKE 'Sortie de stock%'
            ORDER BY h.dateEnregistrement ASC, h.idEBP ASC
        ");
        $stmtHisto->execute([$idEB]);
        $historique = $stmtHisto->fetchAll(PDO::FETCH_ASSOC);

        $dernierParLigne = [];
        foreach ($lignes as &$l) { $l['sorties'] = []; }
        unset($l);
        $lignesParId = [];
        foreach ($lignes as &$l) { $lignesParId[(int)$l['idEBP']] = &$l; }
        unset($l);

        foreach ($historique as $h) {
            $idEBP = (int) $h['idEBP'];
            if (!isset($lignesParId[$idEBP])) continue;
            $cumulActuel = (float) $h['quantite_sortie'];
            $cumulPrecedent = $dernierParLigne[$idEBP] ?? 0.0;
            $delta = $cumulActuel - $cumulPrecedent;
            $dernierParLigne[$idEBP] = $cumulActuel;

            if ($delta > 0.001) {
                $lignesParId[$idEBP]['sorties'][] = [
                    'quantite_sortie' => $delta,
                    'date_sortie'     => $h['dateEnregistrement'],
                    'utilisateur'     => $h['utilisateur'],
                ];
            }
        }

        $expression['lignes'] = $lignes;
        echo json_encode(['status' => 'success', 'expression' => $expression]);
    } catch (\Throwable $e) {
        error_log('[Caisse][consulterSortiesExpressionBesoinCaisse] ' . $e->getMessage());
        erreurSqlCaisse("Impossible de charger les informations sur les sorties.");
    }
}




/* ═══════════════════════════════════════════════════════════════════════════
   ROUTAGE
   10 = listerAlimentations   (liste filtrable par intervalle d'années + statut)
   11 = listerCaissiers       (utilisateurs.idRole = 9)
   12 = creerAlimentation     (initiale ou approvisionnement)
   13 = detailAlimentation    (pour pré-remplir le formulaire de modification)
   14 = modifierAlimentation  (Rejetée + date du jour + pas d'arrêté)
   15 = listerPaiements       (liste filtrable par intervalle de dates)
   16 = modifierPaiement      (annulation logique : montant → 0)
   17 = listerToutesOperationsComptable (tous statuts + stats, comme la page DRH)
   18 = detailOperationComptable        (détail complet d'une opération)
   19 = suiviOperationComptable         (historique/timeline d'une opération)
   20 = listerLivraisons                (liste filtrable par intervalle d'années)
   21 = listerCommandesLivrables        (achat, statut=6, livraison=0)
   22 = detailCommandeLivraison         (lignes + quantité restant à livrer)
   23 = creerLivraison                  (opération irréversible)
   24 = detailLivraison                 (consultation d'une livraison existante)
   25 = detailDossierComplet            (Dossier + Pièces — idStatut = 7 uniquement)
   26 = listerExpressionsBesoinSortie   (idStatut = 3, pas encore entièrement servies)
   27 = detailSortieExpressionBesoin    (restant à sortir + stock disponible + quantité calculée)
   28 = effectuerSortieExpressionBesoin (sortie de stock, MIN(restant, stock), jamais éditable)
   29 = creerInventaire                 (bloqué si un inventaire etat=1 existe déjà)
   30 = listerInventaires               (tous, en cours et terminés)
   31 = detailInventaire                (en-tête + lignes, validation ou détail)
   32 = validerInventaire               (idStatut 3 → 4, etat 1 → 0, MAJ Stock_actuel)
   (Le profil Caissier — liste de ses propres alimentations, Accepter/Rejeter —
    est couvert par un contrôleur séparé : caisseCaissierController.php)
═══════════════════════════════════════════════════════════════════════════ */
try {
    switch ($option) {
        case 10:
            listerAlimentations($bdBASI, $basiController);
            break;

        case 11:
            listerCaissiers($bdBASI);
            break;

        case 12:
            creerAlimentation($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 13:
            detailAlimentation($bdBASI, $basiController);
            break;

        case 14:
            modifierAlimentation($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 15:
            listerPaiements($bdBASI, $basiController);
            break;

        case 16:
            modifierPaiement($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 17:
            listerToutesOperationsComptable($bdBASI, $basiController);
            break;

        case 18:
            detailOperationComptable($bdBASI, $basiController);
            break;

        case 19:
            suiviOperationComptable($bdBASI, $basiController);
            break;

        case 20:
            listerLivraisons($bdBASI, $basiController);
            break;

        case 21:
            listerCommandesLivrables($bdBASI, $basiController);
            break;

        case 22:
            detailCommandeLivraison($bdBASI, $basiController);
            break;

        case 23:
            creerLivraison($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 24:
            detailLivraison($bdBASI, $basiController);
            break;

        case 25:
            detailDossierComplet($bdBASI, $basiController);
            break;

        case 26:
            listerExpressionsBesoinSortie($bdBASI, $basiController);
            break;

        case 27:
            detailSortieExpressionBesoin($bdBASI, $basiController);
            break;

        case 28:
            effectuerSortieExpressionBesoin($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 29:
            creerInventaire($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 30:
            listerInventaires($bdBASI, $basiController);
            break;

        case 31:
            detailInventaire($bdBASI, $basiController);
            break;

        case 32:
            validerInventaire($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 33:
            uploaderFD($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 34:
            envoyerCaisse($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 35:
            voirExpressionBesoinCaisse($bdBASI, $basiController);
            break;

        case 36:
            consulterSortiesExpressionBesoinCaisse($bdBASI, $basiController);
            break;

        case 37:
            listerExpressionsBesoinSortieInvestissement($bdBASI, $basiController);
            break;

        case 38:
            detailSortieExpressionBesoinInvestissement($bdBASI, $basiController);
            break;

        case 39:
            effectuerSortieExpressionBesoinInvestissement($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 40:
            listerCatalogueProduitsInvestissementComptable($bdBASI);
            break;

        case 41:
            detailRepartitionProduitInvestissement($bdBASI);
            break;

        case 42:
            statistiquesProduitsInvestissementComptable($bdBASI);
            break;

        case 43:
            listerEcartsBonSortie($bdBASI);
            break;

        case 44:
            regulariserEcartBonSortie($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 45:
            cloturerSoldeExpressionBesoin($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 46:
            listerEcartsBonSortieInvestissement($bdBASI);
            break;

        case 47:
            regulariserEcartBonSortieInvestissement($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 48:
            cloturerSoldeExpressionBesoinInvestissement($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Option '$option' non reconnue."]);
            exit;
    }
} catch (\Throwable $e) {
    error_log('[Caisse][Routage] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Une erreur inattendue est survenue.']);
    exit;
}