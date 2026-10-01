<?php
/**
 * expressionBesoinController.php
 * Module Expression de besoin — accessible à TOUS les utilisateurs (pas de
 * restriction de rôle). Chaque utilisateur ne gère que ses propres
 * expressions de besoin.
 *
 * ⚠️ Hypothèses de schéma (à confirmer / ajuster) :
 *   - `expression_besoin` : id (PK auto), nom_expression, idUtilisateur,
 *     idDirection, date_creation, idStatut (défaut 1), dateEnregistrement.
 *   - `historique_expression_besoin` : idEB (référence expression_besoin.id),
 *     nom_expression, idUtilisateur, idDirection, date_creation, idStatut,
 *     motif, dateEnregistrement.
 *   - `expression_besoin_produit` : id (PK auto), idEB, idP, quantite,
 *     quantite_reelle (renseignée à la validation), quantite_sortie
 *     (incrémentée à chaque sortie de stock), statut (défaut 1),
 *     dateEnregistrement.
 *   - `historique_expression_besoin_produit` : idEBP (référence
 *     expression_besoin_produit.id), idEB, idP, quantite, statut, motif,
 *     dateEnregistrement, idUtilisateur.
 *   - `categorie` : id, nom_categorie.
 *   - `souscategorie` : id, nom_sous_categorie, categorie_id.
 *   - `product` (déjà établie ailleurs, PK = idP) : idP, nomproduit,
 *     id_Sous_categorie, id_statut, id_type_product. Seuls les produits
 *     id_type_product = 1 sont proposables dans une expression de besoin
 *     (filtre confirmé, appliqué aux 3 niveaux catégorie/sous-catégorie/produit).
 *   - idDirection récupérée depuis $_SESSION['user_direction'] (nom de
 *     variable de session explicitement fourni dans la demande, distinct
 *     de tmpIdDirection utilisé ailleurs dans le projet).
 *   - idStatut de expression_besoin : 1 = En attente, 2 = Soumise,
 *     3 = Validée, 4 = Rejetée, 5 = Sortie produit (toutes les lignes
 *     entièrement sorties du stock).
 *   - Modification autorisée uniquement si idStatut ∈ {1, 4}. Pour idStatut
 *     = 1 : deux actions possibles ("Soumettre et poursuivre" → reste à 1 ;
 *     "Soumettre" → passe à 2). Pour idStatut = 4 : une seule action
 *     ("Modifier" → enregistrement fait automatiquement passer 4 → 2, pas
 *     d'option pour "rester Rejetée").
 *   - Reconciliation des produits à l'enregistrement : le client envoie la
 *     liste COMPLÈTE des produits actuellement voulus (idP + quantite) ;
 *     le serveur ajoute les nouveaux, met à jour les quantités des existants,
 *     et désactive (statut 1 → 0) ceux absents de la liste envoyée —
 *     historisant chaque changement individuellement.
 *   - "Voir" (option 7) : vue de suivi de l'évolution, quel que soit le
 *     statut — pour chaque produit : quantité demandée / validée / sortie /
 *     restante + statut de ligne (En attente / Sorti du stock / Livré / Reçu partiellement / Reçu),
 *     motif de rejet le cas échéant (dernière ligne d'historique idStatut=4),
 *     et historique complet des changements de statut de l'en-tête.
 */

// ─── Capture de tout output parasite ─────────────────────────────────────────
ob_start();
session_start();
include_once('../../../bdBASI.php'); // ← ajuster selon la profondeur réelle du fichier
ob_end_clean();

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// ─── Vérification sessions obligatoires ──────────────────────────────────────
function checkSessionEB(): void {
    foreach (['tmpIdBASI', 'tmpMatricule'] as $key) {
        if (empty($_SESSION[$key])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['status'=>'error','code'=>'sessionExpired','message'=>'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }
    }
}
checkSessionEB();

$sessionUserId      = (int)$_SESSION['tmpIdBASI'];
$sessionMatricule   = trim($_SESSION['tmpMatricule']);
$sessionIdDirection = (int)($_SESSION['user_direction'] ?? 0); // direction d'appartenance — TOUT employé (Fonctionnement)
// idDirection dont l'utilisateur est le DIRECTEUR — seule cette variable
// autorise l'Investissement (même convention que chef_service_basi_controller.php).
$sessionIdDirectionDirecteur = (int)($_SESSION['tmpIdDirection'] ?? 0);

// ─── Classe contrôleur ────────────────────────────────────────────────────────
class expressionBesoinController extends BDBASI
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
    $basiController = new expressionBesoinController();
} catch (\Throwable $e) {
    error_log('[EB][Connexion] ' . $e->getMessage());
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

function getJsonBodyEB(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
function inputValueEB(string $key, $default = null) {
    static $jsonBody = null;
    if ($jsonBody === null) $jsonBody = getJsonBodyEB();
    if (array_key_exists($key, $jsonBody)) return $jsonBody[$key];
    if (isset($_POST[$key]))               return $_POST[$key];
    if (isset($_GET[$key]))                return $_GET[$key];
    return $default;
}
/** Les produits sont des unités entières — jamais de quantité à virgule. */
function estEntierPositif($valeur): bool {
    return is_numeric($valeur) && (float) $valeur == (int) $valeur && (int) $valeur > 0;
}

function erreurSqlEB(string $message = "Erreur lors de l'accès à la base de données."): void {
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
    $jsonBodyOption = getJsonBodyEB();
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

/* ═══════════════════════════════════════════════════════════════════════════
   ACTIONS
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Liste des expressions de besoin de l'utilisateur connecté (ses propres
 * demandes uniquement), filtrable par intervalle d'années (Début vide par
 * défaut, Fin = année en cours par défaut).
 */
function listerExpressionsBesoin(PDO $bdBASI, expressionBesoinController $basiController, int $sessionUserId): void {
    try {
        $anneeCourante = (int) date('Y');
        $anneeFin = (int) inputValueEB('anneeFin', $anneeCourante);
        if ($anneeFin <= 0) $anneeFin = $anneeCourante;

        $anneeDebutBrute = trim((string) inputValueEB('anneeDebut', ''));
        $anneeDebut = ($anneeDebutBrute === '') ? $anneeFin : (int) $anneeDebutBrute;
        if ($anneeDebut <= 0) $anneeDebut = $anneeFin;
        if ($anneeDebut > $anneeFin) { [$anneeDebut, $anneeFin] = [$anneeFin, $anneeDebut]; }

        $stmt = $bdBASI->prepare("
            SELECT eb.id, eb.nom_expression, eb.date_creation, eb.idStatut, eb.dateEnregistrement,
                   (SELECT COUNT(*) FROM expression_besoin_produit ebp WHERE ebp.idEB = eb.id AND ebp.statut = 1) AS nombre_produits
            FROM expression_besoin eb
            WHERE eb.idUtilisateur = ? AND YEAR(eb.date_creation) BETWEEN ? AND ?
            ORDER BY eb.date_creation DESC, eb.id DESC
        ");
        $stmt->execute([$sessionUserId, $anneeDebut, $anneeFin]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['id']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows, 'nombre_total' => count($rows)]);
    } catch (\Throwable $e) {
        error_log('[EB][listerExpressionsBesoin] ' . $e->getMessage());
        erreurSqlEB('Impossible de charger la liste des expressions de besoin.');
    }
}

/**
 * Catégories contenant au moins un produit actif (idStatut = 1).
 */
function listerCategoriesEligibles(PDO $bdBASI): void {
    try {
        $stmt = $bdBASI->query("
            SELECT DISTINCT c.id, c.nom_categorie as nom
            FROM categorie c
            JOIN souscategorie sc ON sc.categorie_id = c.id
            JOIN product p ON p.id_Sous_categorie = sc.id
            WHERE p.id_statut = 1 AND p.id_type_product = 1
            ORDER BY c.nom_categorie ASC
        ");
        echo json_encode(['status' => 'success', 'data' => $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []]);
    } catch (\Throwable $e) {
        error_log('[EB][listerCategoriesEligibles] ' . $e->getMessage());
        erreurSqlEB('Impossible de charger la liste des catégories.');
    }
}

/**
 * Sous-catégories (d'une catégorie donnée) contenant au moins un produit
 * actif (idStatut = 1).
 */
function listerSousCategoriesEligibles(PDO $bdBASI): void {
    try {
        $idCategorie = (int) inputValueEB('idCategorie', 0);
        if ($idCategorie <= 0) { echo json_encode(['status' => 'error', 'message' => 'Catégorie manquante.']); return; }

        $stmt = $bdBASI->prepare("
            SELECT DISTINCT sc.id, sc.nom_sous_categorie as nom
            FROM souscategorie sc
            JOIN product p ON p.id_Sous_categorie = sc.id
            WHERE sc.categorie_id = ? AND p.id_statut = 1 AND p.id_type_product = 1
            ORDER BY sc.nom_sous_categorie ASC
        ");
        $stmt->execute([$idCategorie]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Throwable $e) {
        error_log('[EB][listerSousCategoriesEligibles] ' . $e->getMessage());
        erreurSqlEB('Impossible de charger la liste des sous-catégories.');
    }
}

/**
 * Produits actifs (idStatut = 1) d'une sous-catégorie donnée.
 */
function listerProduitsEligibles(PDO $bdBASI): void {
    try {
        $idSousCategorie = (int) inputValueEB('idSousCategorie', 0);
        if ($idSousCategorie <= 0) { echo json_encode(['status' => 'error', 'message' => 'Sous-catégorie manquante.']); return; }

        $stmt = $bdBASI->prepare("
            SELECT idP, nomproduit as designation
            FROM product
            WHERE id_Sous_categorie = ? AND id_statut = 1 AND id_type_product = 1
            ORDER BY nomproduit ASC
        ");
        $stmt->execute([$idSousCategorie]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Throwable $e) {
        error_log('[EB][listerProduitsEligibles] ' . $e->getMessage());
        erreurSqlEB('Impossible de charger la liste des produits.');
    }
}

/**
 * Détail d'une expression de besoin (en-tête + produits actifs), pour
 * pré-remplir le formulaire de modification ou afficher le Détail en lecture
 * seule. Un utilisateur ne peut consulter que ses propres expressions.
 */
function detailExpressionBesoin(PDO $bdBASI, expressionBesoinController $basiController, int $sessionUserId): void {
    try {
        $token = trim((string) inputValueEB('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEB = (int) $basiController->tokendecrypt($token);
        if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmt = $bdBASI->prepare("
            SELECT eb.id, eb.nom_expression, eb.idUtilisateur, eb.idDirection, eb.date_creation, eb.idStatut, eb.dateEnregistrement,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM expression_besoin eb
            JOIN utilisateurs u ON eb.idUtilisateur = u.id
            WHERE eb.id = ? AND eb.idUtilisateur = ?
            LIMIT 1
        ");
        $stmt->execute([$idEB, $sessionUserId]);
        $expression = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$expression) {
            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable.']);
            return;
        }

        $stmtProduits = $bdBASI->prepare("
            SELECT ebp.id AS idEBP, ebp.idP, ebp.quantite, ebp.quantite_reelle, p.nomproduit as designation
            FROM expression_besoin_produit ebp
            JOIN product p ON ebp.idP = p.idP
            WHERE ebp.idEB = ? AND ebp.statut = 1
            ORDER BY ebp.id ASC
        ");
        $stmtProduits->execute([$idEB]);
        $expression['produits'] = $stmtProduits->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'expression' => $expression]);
    } catch (\Throwable $e) {
        error_log('[EB][detailExpressionBesoin] ' . $e->getMessage());
        erreurSqlEB("Impossible de charger le détail de l'expression de besoin.");
    }
}

/**
 * "Voir" — vue de suivi de l'évolution d'une expression de besoin, quel que
 * soit son statut. Pour chaque produit : quantité demandée / validée /
 * sortie / restante + statut de ligne (En attente / Partiellement livré /
 * Livré). Motif de rejet si idStatut = 4. Historique complet des
 * changements de statut de l'en-tête.
 */
function voirSuiviExpressionBesoin(PDO $bdBASI, expressionBesoinController $basiController, int $sessionUserId): void {
    try {
        $token = trim((string) inputValueEB('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEB = (int) $basiController->tokendecrypt($token);
        if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmt = $bdBASI->prepare("
            SELECT id, nom_expression, date_creation, idStatut, dateEnregistrement
            FROM expression_besoin
            WHERE id = ? AND idUtilisateur = ?
            LIMIT 1
        ");
        $stmt->execute([$idEB, $sessionUserId]);
        $expression = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$expression) {
            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable.']);
            return;
        }

        // ── Produits : demandée / validée / sortie / livrée / reçue / restante ─
        $stmtProduits = $bdBASI->prepare("
            SELECT ebp.id AS idEBP, ebp.idP, ebp.quantite, ebp.quantite_reelle, ebp.quantite_sortie,
                   ebp.quantite_livree, ebp.quantite_recue, ebp.quantite_annulee,
                   (SELECT COALESCE(SUM(bl.quantite_ecart), 0)  FROM bon_sortie_eb_ligne bl WHERE bl.idEBP = ebp.id) AS quantite_ecart,
                   (SELECT COALESCE(SUM(bl.quantite_perdue), 0) FROM bon_sortie_eb_ligne bl WHERE bl.idEBP = ebp.id) AS quantite_perdue,
                   p.nomproduit as designation
            FROM expression_besoin_produit ebp
            JOIN product p ON ebp.idP = p.idP
            WHERE ebp.idEB = ? AND ebp.statut = 1
            ORDER BY ebp.id ASC
        ");
        $stmtProduits->execute([$idEB]);
        $produits = $stmtProduits->fetchAll(PDO::FETCH_ASSOC);

        // Statut de ligne à 4 étapes désormais : la sortie comptable
        // (décrémentation administrative du stock) est DISTINCTE de la
        // livraison physique (remise par le magasinier) et de la réception
        // (confirmée par le demandeur lui-même, via "Reçu").
        foreach ($produits as &$p) {
            $qteReelle  = $p['quantite_reelle'] !== null ? (float) $p['quantite_reelle'] : null;
            $qteSortie  = (float) ($p['quantite_sortie'] ?? 0);
            $qteLivree  = (float) ($p['quantite_livree'] ?? 0);
            $qteRecue   = (float) ($p['quantite_recue'] ?? 0);
            $qteAnnulee = (float) ($p['quantite_annulee'] ?? 0);
            $qteEcart   = (float) ($p['quantite_ecart'] ?? 0);   // signalé, en attente du comptable
            $qtePerdue  = (float) ($p['quantite_perdue'] ?? 0);  // régularisé en perte

            $resteARecevoir = max(0, $qteLivree - $qteRecue - $qteEcart - $qtePerdue); // livré, pas encore confirmé
            $resteALivrer   = max(0, $qteSortie - $qteLivree);                         // sorti, pas encore remis
            $p['quantite_restante_a_recevoir'] = $resteARecevoir;
            $p['quantite_restante_a_livrer']   = $resteALivrer;
            $p['peut_confirmer_reception'] = ($resteARecevoir > 0.001);

            if ($qteReelle === null) {
                $p['quantite_restante'] = null;
                $p['statut_ligne'] = 'En attente';
            } else {
                $cible = max(0, $qteReelle - $qteAnnulee);                 // validée, nette du solde annulé
                $p['quantite_restante'] = max(0, $cible - $qteSortie);     // reste à sortir
                // Le statut suit la prochaine action attendue, dans l'ordre :
                // 1) un écart déclaré attend la régularisation du comptable ;
                // 2) le demandeur doit confirmer ce qui lui a été remis ;
                // 3) le magasinier doit remettre ce qui a été sorti ;
                // 4) tout ce qui a été remis est réglé : "Reçu" si la quantité
                //    validée (nette du solde annulé) est entièrement traitée,
                //    "Reçu partiellement" sinon (le reste est à sortir).
                if ($qteEcart > 0.001) {
                    $p['statut_ligne'] = 'Écart signalé — en attente du comptable';
                } elseif ($resteARecevoir > 0.001) {
                    $p['statut_ligne'] = 'Livré — en attente de votre confirmation';
                } elseif ($resteALivrer > 0.001) {
                    $p['statut_ligne'] = 'Sorti du stock — en attente du magasinier';
                } elseif ($qteRecue + $qtePerdue > 0.001) {
                    $p['statut_ligne'] = ($qteRecue + $qtePerdue >= $cible - 0.001)
                        ? ($qteAnnulee > 0.001 ? 'Reçu — solde annulé' : 'Reçu')
                        : 'Reçu partiellement';
                } elseif ($qteAnnulee > 0.001 && $cible <= 0.001) {
                    $p['statut_ligne'] = 'Annulée';
                } else {
                    $p['statut_ligne'] = 'En attente';
                }
            }
        }
        unset($p);

        // ── Motif de rejet (dernière transition vers idStatut = 4) ──────────
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

        // ── Historique des changements de statut de l'en-tête ───────────────
        $stmtHisto = $bdBASI->prepare("
            SELECT idStatut, motif, dateEnregistrement
            FROM historique_expression_besoin
            WHERE idEB = ?
            ORDER BY dateEnregistrement ASC
        ");
        $stmtHisto->execute([$idEB]);
        $historique = $stmtHisto->fetchAll(PDO::FETCH_ASSOC);

        $expression['produits']    = $produits;

        // ── Bons de sortie : chaque sortie du comptable = un bon, livré puis
        // confirmé bon par bon (suivi des livraisons partielles et successives).
        $stmtBons = $bdBASI->prepare("SELECT id, numero_bon, dateSortie, idStatut FROM bon_sortie_eb WHERE idEB = ? ORDER BY dateSortie ASC, id ASC");
        $stmtBons->execute([$idEB]);
        $bons = $stmtBons->fetchAll(PDO::FETCH_ASSOC);
        $stmtBonLignes = $bdBASI->prepare("
            SELECT bl.id AS idBSL, bl.quantite_sortie, bl.quantite_livree, bl.quantite_recue, bl.quantite_ecart, bl.quantite_perdue, p.nomproduit AS designation
            FROM bon_sortie_eb_ligne bl
            JOIN product p ON bl.idP = p.idP
            WHERE bl.idBS = ?
            ORDER BY bl.id ASC
        ");
        foreach ($bons as &$b) {
            $stmtBonLignes->execute([$b['id']]);
            $b['lignes'] = $stmtBonLignes->fetchAll(PDO::FETCH_ASSOC);
            $b['peut_confirmer'] = false;
            foreach ($b['lignes'] as &$bl) {
                $bl['quantite_restante_a_recevoir'] = max(0, (float) $bl['quantite_livree'] - (float) $bl['quantite_recue'] - (float) $bl['quantite_ecart'] - (float) $bl['quantite_perdue']);
                $bl['peut_confirmer_reception'] = ($bl['quantite_restante_a_recevoir'] > 0.001);
                if ($bl['peut_confirmer_reception']) $b['peut_confirmer'] = true;
            }
            unset($bl);
        }
        unset($b);
        $expression['bons'] = $bons;
        $expression['motif_rejet'] = $motifRejet;
        $expression['historique']  = $historique;

        echo json_encode(['status' => 'success', 'expression' => $expression]);
    } catch (\Throwable $e) {
        error_log('[EB][voirSuiviExpressionBesoin] ' . $e->getMessage());
        erreurSqlEB("Impossible de charger le suivi de l'expression de besoin.");
    }
}

/**
 * Crée (si pas de token) ou met à jour (si token fourni) une expression de
 * besoin, avec réconciliation complète de la liste de produits envoyée.
 *
 * Champs attendus :
 *   - token (optionnel, présent uniquement en modification)
 *   - action : 'poursuivre' | 'soumettre'
 *   - produits : [{ idP, quantite }, ...] — liste COMPLÈTE voulue
 */
function enregistrerExpressionBesoin(PDO $bdBASI, expressionBesoinController $basiController, int $sessionUserId, string $sessionMatricule, int $sessionIdDirection): void {
    try {
        $token  = trim((string) inputValueEB('token', ''));
        $action = trim((string) inputValueEB('action', 'poursuivre'));
        $produitsEnvoyes = inputValueEB('produits', []);
        if (!is_array($produitsEnvoyes)) $produitsEnvoyes = [];

        // Normalisation + validation des produits envoyés.
        $produitsValides = [];
        $vus = [];
        foreach ($produitsEnvoyes as $p) {
            $idP      = (int) ($p['idP'] ?? 0);
            $quantite = (float) ($p['quantite'] ?? 0);
            if ($idP <= 0) continue;
            if ($quantite <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'La quantité doit être strictement supérieure à zéro pour chaque produit.']);
                return;
            }
            if (!estEntierPositif($quantite)) {
                echo json_encode(['status' => 'error', 'message' => 'Les quantités demandées doivent être des nombres entiers.']);
                return;
            }
            if (isset($vus[$idP])) {
                echo json_encode(['status' => 'error', 'message' => 'Un même produit ne peut être ajouté qu\'une seule fois.']);
                return;
            }
            $vus[$idP] = true;
            $produitsValides[$idP] = $quantite;
        }
        if (empty($produitsValides)) {
            echo json_encode(['status' => 'error', 'message' => 'Veuillez ajouter au moins un produit.']);
            return;
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        if ($token === '') {
            // ── CRÉATION ─────────────────────────────────────────────────────
            $nomExpression = 'expression_' . date('Ymd_His');
            $idStatut = 1; // toujours 1 à la création, quelle que soit l'action
            // ('soumettre' dès la création passera à 2 juste après).

            $bdBASI->prepare("
                INSERT INTO expression_besoin (nom_expression, idUtilisateur, idDirection, date_creation, idStatut, dateEnregistrement)
                VALUES (?, ?, ?, CURDATE(), ?, ?)
            ")->execute([$nomExpression, $sessionUserId, $sessionIdDirection, $idStatut, $dateEnregistrement]);
            $idEB = (int) $bdBASI->lastInsertId();

            $motif = "Création de l'expression de besoin (par $sessionMatricule)";
            insererHistoriqueEB($bdBASI, $idEB, $nomExpression, $sessionUserId, $sessionIdDirection, $idStatut, $motif, $dateEnregistrement);
        } else {
            // ── MODIFICATION ─────────────────────────────────────────────────
            $idEB = (int) $basiController->tokendecrypt($token);
            if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); $bdBASI->rollBack(); return; }

            $stmtC = $bdBASI->prepare("
                SELECT id, nom_expression, idUtilisateur, idDirection, idStatut
                FROM expression_besoin
                WHERE id = ? AND idUtilisateur = ?
                LIMIT 1
            ");
            $stmtC->execute([$idEB, $sessionUserId]);
            $expression = $stmtC->fetch(PDO::FETCH_ASSOC);
            if (!$expression) {
                echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable.']);
                $bdBASI->rollBack();
                return;
            }
            if (!in_array((int)$expression['idStatut'], [1, 4], true)) {
                echo json_encode(['status' => 'error', 'message' => 'Seule une expression En attente ou Rejetée peut être modifiée.']);
                $bdBASI->rollBack();
                return;
            }
            $nomExpression = $expression['nom_expression'];
        }

        // ── Réconciliation des produits ────────────────────────────────────────
        $stmtActifs = $bdBASI->prepare("SELECT id, idP, quantite FROM expression_besoin_produit WHERE idEB = ? AND statut = 1");
        $stmtActifs->execute([$idEB]);
        $actifsExistants = [];
        foreach ($stmtActifs->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $actifsExistants[(int)$row['idP']] = $row;
        }

        $motifProduits = ($token === '')
            ? "Ajout à la création (par $sessionMatricule)"
            : "Modification de l'expression de besoin (par $sessionMatricule)";

        // Ajouts / mises à jour.
        foreach ($produitsValides as $idP => $quantite) {
            if (isset($actifsExistants[$idP])) {
                $idEBP = (int) $actifsExistants[$idP]['id'];
                if ((float)$actifsExistants[$idP]['quantite'] !== $quantite) {
                    $bdBASI->prepare("UPDATE expression_besoin_produit SET quantite = ?, dateEnregistrement = ? WHERE id = ?")
                        ->execute([$quantite, $dateEnregistrement, $idEBP]);
                    insererHistoriqueEBP($bdBASI, $idEBP, $idEB, $idP, $quantite, 1, $motifProduits, $dateEnregistrement, $sessionUserId);
                }
            } else {
                $bdBASI->prepare("
                    INSERT INTO expression_besoin_produit (idEB, idP, quantite, statut, dateEnregistrement)
                    VALUES (?, ?, ?, 1, ?)
                ")->execute([$idEB, $idP, $quantite, $dateEnregistrement]);
                $idEBP = (int) $bdBASI->lastInsertId();
                insererHistoriqueEBP($bdBASI, $idEBP, $idEB, $idP, $quantite, 1, $motifProduits, $dateEnregistrement, $sessionUserId);
            }
        }

        // Suppressions logiques (produits actifs non présents dans l'envoi).
        foreach ($actifsExistants as $idP => $row) {
            if (!isset($produitsValides[$idP])) {
                $idEBP = (int) $row['id'];
                $bdBASI->prepare("UPDATE expression_besoin_produit SET statut = 0, dateEnregistrement = ? WHERE id = ?")
                    ->execute([$dateEnregistrement, $idEBP]);
                insererHistoriqueEBP($bdBASI, $idEBP, $idEB, $idP, (float)$row['quantite'], 0, "Suppression du produit (par $sessionMatricule)", $dateEnregistrement, $sessionUserId);
            }
        }

        // ── Statut de l'expression selon l'action ───────────────────────────────
        $nouveauStatut = null;
        if ($token === '') {
            // Création : reste à 1, sauf si l'utilisateur soumet directement.
            if ($action === 'soumettre') $nouveauStatut = 2;
        } else {
            $ancienStatut = (int) $expression['idStatut'];
            if ($ancienStatut === 4) {
                // Rejetée : toute modification relance automatiquement le
                // processus (4 → 2), aucune option pour "rester Rejetée".
                $nouveauStatut = 2;
            } elseif ($action === 'soumettre') {
                $nouveauStatut = 2;
            }
            // Sinon (En attente + "poursuivre") : le statut reste inchangé (1).
        }

        if ($nouveauStatut !== null) {
            $bdBASI->prepare("UPDATE expression_besoin SET idStatut = ? WHERE id = ?")->execute([$nouveauStatut, $idEB]);
            $motifStatut = ($nouveauStatut === 2)
                ? "Soumission de l'expression de besoin (par $sessionMatricule)"
                : "Mise à jour du statut (par $sessionMatricule)";
            insererHistoriqueEB($bdBASI, $idEB, $nomExpression, $sessionUserId, $sessionIdDirection, $nouveauStatut, $motifStatut, $dateEnregistrement);
        }

        $bdBASI->commit();

        echo json_encode([
            'status'  => 'success',
            'message' => ($nouveauStatut === 2)
                ? 'Expression de besoin soumise avec succès.'
                : 'Expression de besoin enregistrée avec succès.',
        ]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[EB][enregistrerExpressionBesoin] ' . $e->getMessage());
        erreurSqlEB("Impossible d'enregistrer l'expression de besoin.");
    }
}

/** Insère un instantané de l'en-tête dans historique_expression_besoin. */
function insererHistoriqueEB(PDO $bdBASI, int $idEB, string $nomExpression, int $idUtilisateur, int $idDirection, int $idStatut, string $motif, string $dateEnregistrement): void {
    $bdBASI->prepare("
        INSERT INTO historique_expression_besoin
            (idEB, nom_expression, idUtilisateur, idDirection, date_creation, idStatut, motif, dateEnregistrement)
        SELECT id, nom_expression, idUtilisateur, idDirection, date_creation, ?, ?, ?
        FROM expression_besoin
        WHERE id = ?
    ")->execute([$idStatut, $motif, $dateEnregistrement, $idEB]);
}

/** Insère une ligne dans historique_expression_besoin_produit. */
function insererHistoriqueEBP(PDO $bdBASI, int $idEBP, int $idEB, int $idP, float $quantite, int $statut, string $motif, string $dateEnregistrement, int $idUtilisateur): void {
    $bdBASI->prepare("
        INSERT INTO historique_expression_besoin_produit (idEBP, idEB, idP, quantite, statut, motif, dateEnregistrement, idUtilisateur)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([$idEBP, $idEB, $idP, $quantite, $statut, $motif, $dateEnregistrement, $idUtilisateur]);
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Expression de besoin INVESTISSEMENT
   Réservé aux chefs de service (idDirection présent en session, lue depuis
   $_SESSION['user_direction'], même variable que le module Fonctionnement).
   Cycle : 1 = Brouillon, 2 = Terminé (la finalisation déclenche IMMÉDIATEMENT
   la sortie de stock — pas de validation hiérarchique, c'est la direction qui
   consomme son propre quota).
   Catalogue : produits partagés `product` avec id_type_product = 2.
   Quota : calculé depuis livraison_produit_repartition (ce que le comptable a
   explicitement attribué en stock à cette direction), moins ce qui a déjà été
   sorti via ce module — jamais lu directement sur product.Stock_actuel, qui
   reste un total physique partagé entre toutes les directions.
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Indique si l'utilisateur connecté a accès à l'onglet Investissement
 * (idDirection renseigné en session) et le nom de sa direction.
 */
function chargerContexteDirection(PDO $bdBASI, int $sessionIdDirectionDirecteur): void {
    try {
        if ($sessionIdDirectionDirecteur <= 0) {
            echo json_encode(['status' => 'success', 'aDirection' => false]);
            return;
        }
        $stmt = $bdBASI->prepare("SELECT id, nom_direction FROM direction WHERE id = ? LIMIT 1");
        $stmt->execute([$sessionIdDirectionDirecteur]);
        $direction = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'status'      => 'success',
            'aDirection'  => (bool) $direction,
            'idDirection' => $sessionIdDirectionDirecteur,
            'nomDirection'=> $direction['nom_direction'] ?? null,
        ]);
    } catch (\Throwable $e) {
        error_log('[EBI][chargerContexteDirection] ' . $e->getMessage());
        erreurSqlEB("Impossible de charger le contexte de direction.");
    }
}

/**
 * Calcule le quota disponible pour une direction sur un produit donné :
 * tout ce qui lui a été explicitement attribué en stock à la réception,
 * moins tout ce qu'elle a déjà retiré via ce module.
 */
function calculerQuotaDirection(PDO $bdBASI, int $idDirection, int $idP): float {
    $stmtRecu = $bdBASI->prepare("
        SELECT COALESCE(SUM(lpr.quantite), 0) AS total
        FROM livraison_produit_repartition lpr
        JOIN livraison_produit lp ON lpr.idLP = lp.id
        WHERE lpr.mode = 'stock' AND lpr.idDirection = ? AND lp.idP = ?
    ");
    $stmtRecu->execute([$idDirection, $idP]);
    $recu = (float) $stmtRecu->fetch(PDO::FETCH_ASSOC)['total'];

    $stmtSorti = $bdBASI->prepare("
        SELECT COALESCE(SUM(ebip.quantite_sortie), 0) AS total
        FROM expression_besoin_investissement_produit ebip
        JOIN expression_besoin_investissement ebi ON ebip.idEBI = ebi.id
        WHERE ebip.id_produit = ? AND ebi.idDirection = ?
    ");
    $stmtSorti->execute([$idP, $idDirection]);
    $sorti = (float) $stmtSorti->fetch(PDO::FETCH_ASSOC)['total'];

    return max(0.0, $recu - $sorti);
}

/**
 * Liste directe des produits Investissement (id_type_product=2) pour
 * lesquels la direction de l'utilisateur connecté a un quota disponible
 * strictement positif — pas de cascade catégorie/sous-catégorie, ces champs
 * ne s'appliquent pas aux produits Investissement (product.id_Sous_categorie
 * y est NULL). La rubrique/sous-rubrique, à titre indicatif seulement, est
 * retrouvée via ligneBudget (id_produit) → sousRubrique → rubrique.
 *
 * On ne parcourt que les produits ayant déjà transité par
 * livraison_produit_repartition pour cette direction (mode='stock') — pas
 * tout le catalogue Investissement — pour rester performant.
 */
function listerProduitsEligiblesInvestissement(PDO $bdBASI, int $sessionIdDirectionDirecteur): void {
    try {
        if ($sessionIdDirectionDirecteur <= 0) {
            echo json_encode(['status' => 'error', 'message' => "Aucune direction associée à votre compte."]);
            return;
        }

        $stmtCandidats = $bdBASI->prepare("
            SELECT DISTINCT lp.idP
            FROM livraison_produit_repartition lpr
            JOIN livraison_produit lp ON lpr.idLP = lp.id
            WHERE lpr.mode = 'stock' AND lpr.idDirection = ?
        ");
        $stmtCandidats->execute([$sessionIdDirectionDirecteur]);
        $idsCandidats = array_column($stmtCandidats->fetchAll(PDO::FETCH_ASSOC), 'idP');

        if (empty($idsCandidats)) {
            echo json_encode(['status' => 'success', 'data' => []]);
            return;
        }

        $placeholders = implode(',', array_fill(0, count($idsCandidats), '?'));
        $stmtProduits = $bdBASI->prepare("
            SELECT idP, nomproduit as designation
            FROM product
            WHERE idP IN ($placeholders) AND id_statut = 1 AND id_type_product = 2
            ORDER BY nomproduit ASC
        ");
        $stmtProduits->execute($idsCandidats);
        $produits = $stmtProduits->fetchAll(PDO::FETCH_ASSOC);

        // Rubrique / sous-rubrique — indicatif seulement, via ligneBudget.
        $stmtRubrique = $bdBASI->prepare("
            SELECT r.nom_rubrique, sr.nom_sous_rubrique
            FROM ligneBudget lb
            JOIN sousRubrique sr ON lb.sous_rubrique_id = sr.id
            JOIN rubrique r ON sr.rubrique_id = r.id
            WHERE lb.id_produit = ?
            LIMIT 1
        ");

        $resultat = [];
        foreach ($produits as $p) {
            $quota = calculerQuotaDirection($bdBASI, $sessionIdDirectionDirecteur, (int) $p['idP']);
            if ($quota <= 0.001) continue;

            $stmtRubrique->execute([$p['idP']]);
            $rubriqueInfo = $stmtRubrique->fetch(PDO::FETCH_ASSOC);

            $p['quota_disponible'] = $quota;
            $p['nom_rubrique']     = $rubriqueInfo['nom_rubrique'] ?? null;
            $p['nom_sous_rubrique'] = $rubriqueInfo['nom_sous_rubrique'] ?? null;
            $resultat[] = $p;
        }

        echo json_encode(['status' => 'success', 'data' => $resultat]);
    } catch (\Throwable $e) {
        error_log('[EBI][listerProduitsEligiblesInvestissement] ' . $e->getMessage());
        erreurSqlEB('Impossible de charger la liste des produits.');
    }
}

/**
 * Liste des expressions de besoin investissement de la direction de
 * l'utilisateur connecté (partagée entre tous les chefs de service qui se
 * succèdent à la tête de cette direction — jamais filtrée par idUtilisateur).
 */
function listerExpressionsBesoinInvestissement(PDO $bdBASI, expressionBesoinController $basiController, int $sessionIdDirectionDirecteur): void {
    try {
        if ($sessionIdDirectionDirecteur <= 0) {
            echo json_encode(['status' => 'error', 'message' => "Aucune direction associée à votre compte."]);
            return;
        }

        $stmt = $bdBASI->prepare("
            SELECT ebi.id, ebi.nom_expression, ebi.date_creation, ebi.idStatut,
                   (SELECT COUNT(*) FROM expression_besoin_investissement_produit ebip WHERE ebip.idEBI = ebi.id AND ebip.statut = 1) AS nombre_produits
            FROM expression_besoin_investissement ebi
            WHERE ebi.idDirection = ?
            ORDER BY ebi.date_creation DESC, ebi.id DESC
        ");
        $stmt->execute([$sessionIdDirectionDirecteur]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['id']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows, 'nombre_total' => count($rows)]);
    } catch (\Throwable $e) {
        error_log('[EBI][listerExpressionsBesoinInvestissement] ' . $e->getMessage());
        erreurSqlEB('Impossible de charger la liste des expressions de besoin investissement.');
    }
}

/**
 * Détail d'une expression de besoin investissement (en-tête + produits),
 * pour modification ou consultation. Filtrée par idDirection, pas par
 * idUtilisateur : n'importe quel chef de service de cette direction (passé
 * ou présent) peut la consulter/modifier tant qu'elle est en Brouillon.
 */
function detailExpressionBesoinInvestissement(PDO $bdBASI, expressionBesoinController $basiController, int $sessionIdDirectionDirecteur): void {
    try {
        if ($sessionIdDirectionDirecteur <= 0) {
            echo json_encode(['status' => 'error', 'message' => "Aucune direction associée à votre compte."]);
            return;
        }
        $token = trim((string) inputValueEB('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEBI = (int) $basiController->tokendecrypt($token);
        if ($idEBI <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmt = $bdBASI->prepare("
            SELECT id, nom_expression, idDirection, date_creation, idStatut, dateEnregistrement
            FROM expression_besoin_investissement
            WHERE id = ? AND idDirection = ?
            LIMIT 1
        ");
        $stmt->execute([$idEBI, $sessionIdDirectionDirecteur]);
        $expression = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$expression) {
            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable.']);
            return;
        }

        $stmtProduits = $bdBASI->prepare("
            SELECT ebip.id AS idEBIP, ebip.id_produit, ebip.quantite_demandee, ebip.quantite_sortie,
                   p.nomproduit AS designation
            FROM expression_besoin_investissement_produit ebip
            JOIN product p ON ebip.id_produit = p.idP
            WHERE ebip.idEBI = ? AND ebip.statut = 1
            ORDER BY ebip.id ASC
        ");
        $stmtProduits->execute([$idEBI]);
        $expression['produits'] = $stmtProduits->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'expression' => $expression]);
    } catch (\Throwable $e) {
        error_log('[EBI][detailExpressionBesoinInvestissement] ' . $e->getMessage());
        erreurSqlEB("Impossible de charger le détail de l'expression de besoin.");
    }
}

/**
 * Crée (si pas de token) ou met à jour (si token fourni) une expression de
 * besoin investissement. Action 'poursuivre' → reste en Brouillon. Action
 * 'terminer' → vérifie le quota une dernière fois (sécurité), puis déclenche
 * IMMÉDIATEMENT la sortie de stock (product.Stock_actuel décrémenté,
 * quantite_sortie = quantite_demandee pour chaque ligne) — pas d'étape
 * comptable intermédiaire, c'est la direction qui consomme son propre quota.
 *
 * Champs attendus :
 *   - token (optionnel, présent uniquement en modification)
 *   - action : 'poursuivre' | 'terminer'
 *   - produits : [{ idP, quantite }, ...] — liste COMPLÈTE voulue
 */
function enregistrerExpressionBesoinInvestissement(PDO $bdBASI, expressionBesoinController $basiController, int $sessionUserId, string $sessionMatricule, int $sessionIdDirectionDirecteur): void {
    try {
        if ($sessionIdDirectionDirecteur <= 0) {
            echo json_encode(['status' => 'error', 'message' => "Aucune direction associée à votre compte."]);
            return;
        }

        $token  = trim((string) inputValueEB('token', ''));
        $action = trim((string) inputValueEB('action', 'poursuivre'));
        $produitsEnvoyes = inputValueEB('produits', []);
        if (!is_array($produitsEnvoyes)) $produitsEnvoyes = [];

        $produitsValides = [];
        $vus = [];
        foreach ($produitsEnvoyes as $p) {
            $idP      = (int) ($p['idP'] ?? 0);
            $quantite = (float) ($p['quantite'] ?? 0);
            if ($idP <= 0) continue;
            if ($quantite <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'La quantité doit être strictement supérieure à zéro pour chaque produit.']);
                return;
            }
            if (!estEntierPositif($quantite)) {
                echo json_encode(['status' => 'error', 'message' => 'Les quantités demandées doivent être des nombres entiers.']);
                return;
            }
            if (isset($vus[$idP])) {
                echo json_encode(['status' => 'error', 'message' => 'Un même produit ne peut être ajouté qu\'une seule fois.']);
                return;
            }
            $vus[$idP] = true;
            $produitsValides[$idP] = $quantite;
        }
        if (empty($produitsValides)) {
            echo json_encode(['status' => 'error', 'message' => 'Veuillez ajouter au moins un produit.']);
            return;
        }

        // ── Vérification du quota (sécurité, en plus du filtrage déjà fait
        // côté catalogue) — jamais faire confiance uniquement au client. ──
        foreach ($produitsValides as $idP => $quantiteDemandee) {
            $quotaDisponible = calculerQuotaDirection($bdBASI, $sessionIdDirectionDirecteur, $idP);
            // En modification, il faut réintégrer la quantité déjà réservée
            // par CETTE MÊME expression avant de comparer (sinon on se
            // pénaliserait soi-même à chaque modification).
            if ($token !== '') {
                $idEBIExistant = (int) $basiController->tokendecrypt($token);
                $stmtDejaReserve = $bdBASI->prepare("
                    SELECT COALESCE(SUM(quantite_demandee), 0) AS total
                    FROM expression_besoin_investissement_produit
                    WHERE idEBI = ? AND id_produit = ? AND statut = 1
                ");
                $stmtDejaReserve->execute([$idEBIExistant, $idP]);
                $quotaDisponible += (float) $stmtDejaReserve->fetch(PDO::FETCH_ASSOC)['total'];
            }
            if ($quantiteDemandee > $quotaDisponible + 0.001) {
                echo json_encode(['status' => 'error', 'message' => "Quantité demandée supérieure au quota disponible pour un des produits ($quotaDisponible restant(s))."]);
                return;
            }
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        if ($token === '') {
            $nomExpression = 'expression_invest_' . date('Ymd_His');
            $idStatut = 1;

            $bdBASI->prepare("
                INSERT INTO expression_besoin_investissement (nom_expression, idDirection, idUtilisateur, date_creation, idStatut, dateEnregistrement)
                VALUES (?, ?, ?, CURDATE(), ?, ?)
            ")->execute([$nomExpression, $sessionIdDirectionDirecteur, $sessionUserId, $idStatut, $dateEnregistrement]);
            $idEBI = (int) $bdBASI->lastInsertId();

            insererHistoriqueEBI($bdBASI, $idEBI, $nomExpression, $sessionIdDirectionDirecteur, $sessionUserId, $idStatut,
                "Création de l'expression de besoin investissement (par $sessionMatricule)", $dateEnregistrement);
        } else {
            $idEBI = (int) $basiController->tokendecrypt($token);
            if ($idEBI <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); $bdBASI->rollBack(); return; }

            $stmtC = $bdBASI->prepare("
                SELECT id, nom_expression, idDirection, idStatut
                FROM expression_besoin_investissement
                WHERE id = ? AND idDirection = ?
                LIMIT 1
            ");
            $stmtC->execute([$idEBI, $sessionIdDirectionDirecteur]);
            $expression = $stmtC->fetch(PDO::FETCH_ASSOC);
            if (!$expression) {
                echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable.']);
                $bdBASI->rollBack();
                return;
            }
            if ((int) $expression['idStatut'] !== 1) {
                echo json_encode(['status' => 'error', 'message' => 'Seule une expression en Brouillon peut être modifiée.']);
                $bdBASI->rollBack();
                return;
            }
            $nomExpression = $expression['nom_expression'];
        }

        // ── Réconciliation des produits (identique au module Fonctionnement) ──
        $stmtActifs = $bdBASI->prepare("SELECT id, id_produit, quantite_demandee FROM expression_besoin_investissement_produit WHERE idEBI = ? AND statut = 1");
        $stmtActifs->execute([$idEBI]);
        $actifsExistants = [];
        foreach ($stmtActifs->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $actifsExistants[(int) $row['id_produit']] = $row;
        }

        $motifProduits = ($token === '')
            ? "Ajout à la création (par $sessionMatricule)"
            : "Modification de l'expression de besoin (par $sessionMatricule)";

        foreach ($produitsValides as $idP => $quantite) {
            if (isset($actifsExistants[$idP])) {
                $idEBIP = (int) $actifsExistants[$idP]['id'];
                if ((float) $actifsExistants[$idP]['quantite_demandee'] !== $quantite) {
                    $bdBASI->prepare("UPDATE expression_besoin_investissement_produit SET quantite_demandee = ?, dateEnregistrement = ? WHERE id = ?")
                        ->execute([$quantite, $dateEnregistrement, $idEBIP]);
                    insererHistoriqueEBIP($bdBASI, $idEBIP, $idEBI, $idP, $quantite, 0, 1, $motifProduits, $dateEnregistrement, $sessionUserId);
                }
            } else {
                $bdBASI->prepare("
                    INSERT INTO expression_besoin_investissement_produit (idEBI, id_produit, quantite_demandee, quantite_sortie, statut, dateEnregistrement)
                    VALUES (?, ?, ?, 0, 1, ?)
                ")->execute([$idEBI, $idP, $quantite, $dateEnregistrement]);
                $idEBIP = (int) $bdBASI->lastInsertId();
                insererHistoriqueEBIP($bdBASI, $idEBIP, $idEBI, $idP, $quantite, 0, 1, $motifProduits, $dateEnregistrement, $sessionUserId);
            }
        }

        foreach ($actifsExistants as $idP => $row) {
            if (!isset($produitsValides[$idP])) {
                $idEBIP = (int) $row['id'];
                $bdBASI->prepare("UPDATE expression_besoin_investissement_produit SET statut = 0, dateEnregistrement = ? WHERE id = ?")
                    ->execute([$dateEnregistrement, $idEBIP]);
                insererHistoriqueEBIP($bdBASI, $idEBIP, $idEBI, $idP, (float) $row['quantite_demandee'], 0, 0,
                    "Suppression du produit (par $sessionMatricule)", $dateEnregistrement, $sessionUserId);
            }
        }

        // ── Finalisation : passe simplement à Soumise (2). La sortie de
        // stock n'est plus automatique ici — c'est le comptable qui la
        // déclenche (effectuerSortieExpressionBesoinInvestissement, dans
        // caisseController.php), exactement comme pour le Fonctionnement.
        if ($action === 'terminer') {
            $bdBASI->prepare("UPDATE expression_besoin_investissement SET idStatut = 2 WHERE id = ?")->execute([$idEBI]);
            insererHistoriqueEBI($bdBASI, $idEBI, $nomExpression, $sessionIdDirectionDirecteur, $sessionUserId, 2,
                "Soumission au comptable pour sortie de stock (par $sessionMatricule)", $dateEnregistrement);
        }

        $bdBASI->commit();

        echo json_encode([
            'status'  => 'success',
            'message' => ($action === 'terminer')
                ? 'Expression de besoin soumise avec succès : en attente de sortie par le comptable.'
                : 'Brouillon enregistré avec succès.',
            'tmp' => $basiController->tokenencrypt($idEBI),
        ]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[EBI][enregistrerExpressionBesoinInvestissement] ' . $e->getMessage());
        erreurSqlEB("Impossible d'enregistrer l'expression de besoin.");
    }
}

/** Insère un instantané de l'en-tête dans historique_expression_besoin_investissement. */
function insererHistoriqueEBI(PDO $bdBASI, int $idEBI, string $nomExpression, int $idDirection, int $idUtilisateur, int $idStatut, string $motif, string $dateEnregistrement): void {
    $bdBASI->prepare("
        INSERT INTO historique_expression_besoin_investissement
            (idEBI, nom_expression, idDirection, idUtilisateur, idStatut, motif, dateEnregistrement)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([$idEBI, $nomExpression, $idDirection, $idUtilisateur, $idStatut, $motif, $dateEnregistrement]);
}

/** Insère une ligne dans historique_expression_besoin_investissement_produit. */
function insererHistoriqueEBIP(PDO $bdBASI, int $idEBIP, int $idEBI, int $idProduit, float $quantiteDemandee, float $quantiteSortie, int $statut, string $motif, string $dateEnregistrement, int $idUtilisateur): void {
    $bdBASI->prepare("
        INSERT INTO historique_expression_besoin_investissement_produit
            (idEBIP, idEBI, id_produit, quantite_demandee, quantite_sortie, statut, idUtilisateur, motif, dateEnregistrement)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([$idEBIP, $idEBI, $idProduit, $quantiteDemandee, $quantiteSortie, $statut, $idUtilisateur, $motif, $dateEnregistrement]);
}

/**
 * OPTION 17 — Le demandeur confirme la réception, bon par bon et ligne par
 * ligne. Pour chaque ligne il indique :
 *   - quantite : la quantité RÉELLEMENT reçue ;
 *   - ecart    : la quantité marquée "livrée" mais NON reçue (optionnel) —
 *                un commentaire est alors obligatoire ; l'écart est transmis
 *                au comptable pour régularisation (le bon passe à "Écart
 *                signalé").
 * Contrôle serveur : quantite + ecart ≤ reste à recevoir de la ligne de bon
 * (livrée − reçue − écart − perdue). Réservé au demandeur de la demande.
 *
 * Champs attendus : token, quantites: [{ idBSL, quantite, ecart?, commentaire? }, ...]
 */
function confirmerReceptionDemandeur(PDO $bdBASI, expressionBesoinController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueEB('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEB = (int) $basiController->tokendecrypt($token);
        if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $saisies = inputValueEB('quantites', []);
        if (!is_array($saisies)) $saisies = [];
        $parLigne = [];
        foreach ($saisies as $q) {
            $idBSL = (int) ($q['idBSL'] ?? 0);
            if ($idBSL <= 0) continue;
            $parLigne[$idBSL] = [
                'recue'       => (float) ($q['quantite'] ?? 0),
                'ecart'       => (float) ($q['ecart'] ?? 0),
                'commentaire' => trim((string) ($q['commentaire'] ?? '')),
            ];
        }

        $stmtC = $bdBASI->prepare("SELECT id FROM expression_besoin WHERE id = ? AND idUtilisateur = ? LIMIT 1");
        $stmtC->execute([$idEB, $sessionUserId]);
        if (!$stmtC->fetch()) { echo json_encode(['status' => 'error', 'message' => "Expression de besoin introuvable ou vous n'en êtes pas le demandeur."]); return; }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $motifRecu  = "Réception confirmée par le demandeur (par $sessionMatricule)";
        $motifEcart = "Écart de réception signalé par le demandeur (par $sessionMatricule)";

        $bdBASI->beginTransaction();

        // Uniquement les lignes de bons DE CETTE demande (le token ne peut donc
        // pas servir à confirmer la ligne d'un bon appartenant à une autre).
        $stmtLignes = $bdBASI->prepare("
            SELECT bl.id AS idBSL, bl.idBS, bl.idEBP, bl.quantite_livree, bl.quantite_recue, bl.quantite_ecart, bl.quantite_perdue
            FROM bon_sortie_eb_ligne bl
            JOIN bon_sortie_eb bs ON bs.id = bl.idBS
            WHERE bs.idEB = ?
            FOR UPDATE
        ");
        $stmtLignes->execute([$idEB]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
        if (empty($lignes)) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Aucun bon de sortie pour cette expression de besoin.']);
            return;
        }

        $stmtRecueBon = $bdBASI->prepare("UPDATE bon_sortie_eb_ligne SET quantite_recue = quantite_recue + ? WHERE id = ?");
        $stmtRecueEBP = $bdBASI->prepare("UPDATE expression_besoin_produit SET quantite_recue = quantite_recue + ?, dateEnregistrement = ? WHERE id = ?");

        $auMoinsUneAction = false;
        $ecartSignale = false;
        $bonsTouches = [];
        foreach ($lignes as $l) {
            $idBSL = (int) $l['idBSL'];
            if (!isset($parLigne[$idBSL])) continue;
            $recue = $parLigne[$idBSL]['recue'];
            $ecart = $parLigne[$idBSL]['ecart'];
            if ($recue < 0 || $ecart < 0) { $bdBASI->rollBack(); echo json_encode(['status' => 'error', 'message' => 'Les quantités ne peuvent pas être négatives.']); return; }
            if (($recue > 0 && !estEntierPositif($recue)) || ($ecart > 0 && !estEntierPositif($ecart))) {
                $bdBASI->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Les quantités doivent être des nombres entiers.']);
                return;
            }
            if ($recue <= 0.001 && $ecart <= 0.001) continue;

            $restant = max(0, (float) $l['quantite_livree'] - (float) $l['quantite_recue'] - (float) $l['quantite_ecart'] - (float) $l['quantite_perdue']);

            // Garde-fou serveur : reçu + écart jamais plus que ce qui restait à confirmer dans CE bon.
            if ($recue + $ecart > $restant + 0.001) {
                $bdBASI->rollBack();
                echo json_encode(['status' => 'error', 'message' => "La quantité reçue et l'écart déclaré dépassent ce qui vous a été remis dans ce bon pour au moins une ligne."]);
                return;
            }

            if ($recue > 0.001) {
                $stmtRecueBon->execute([$recue, $idBSL]);
                $stmtRecueEBP->execute([$recue, $dateEnregistrement, $l['idEBP']]);
                ebw_historiserLigneBon($bdBASI, $idBSL, $motifRecu, $sessionUserId, $dateEnregistrement);
                ebw_historiserEBP($bdBASI, (int) $l['idEBP'], $motifRecu, $sessionUserId, $dateEnregistrement);
            }
            if ($ecart > 0.001) {
                ebw_signalerEcart($bdBASI, $idBSL, $ecart, $parLigne[$idBSL]['commentaire'], $sessionUserId, $motifEcart, $dateEnregistrement);
                $ecartSignale = true;
            }
            $bonsTouches[(int) $l['idBS']] = true;
            $auMoinsUneAction = true;
        }

        if (!$auMoinsUneAction) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'message' => "Aucune réception confirmée : veuillez saisir au moins une quantité reçue ou un écart."]);
            return;
        }

        // Statuts DÉDUITS des quantités : d'abord les bons, puis la demande
        // (8 Clôturée / 9 Clôturée avec solde dès que tout est sorti, livré
        // ET reçu, sans écart en attente).
        foreach (array_keys($bonsTouches) as $idBS) {
            ebw_recalculerStatutBon($bdBASI, $idBS, $sessionUserId, $motifRecu, $dateEnregistrement);
        }
        ebw_recalculerStatutEB($bdBASI, $idEB, $motifRecu, $dateEnregistrement);

        $bdBASI->commit();
        echo json_encode(['status' => 'success', 'message' => $ecartSignale
            ? "Réception enregistrée. L'écart signalé a été transmis au comptable pour régularisation."
            : 'Réception confirmée avec succès.']);
    } catch (EbwException $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[EB][confirmerReceptionDemandeur] ' . $e->getMessage());
        erreurSqlEB('Impossible de confirmer la réception.');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Suivi et réception INVESTISSEMENT (miroir exact du module
   Fonctionnement ci-dessus, sur expression_besoin_investissement(_produit) /
   bon_sortie_ebi(_ligne)). Filtré par idDirection — pas idUtilisateur —
   comme le reste du module Investissement (toute la direction partage le
   suivi de ses propres expressions).
═══════════════════════════════════════════════════════════════════════════ */

/**
 * OPTION 18 — Suivi d'une expression de besoin Investissement pour le chef
 * de service : quantités par ligne (demandée/sortie/livrée/reçue), bons de
 * sortie, et bouton "Reçu" dès qu'une ligne de bon reste à confirmer.
 */
function voirSuiviExpressionBesoinInvestissement(PDO $bdBASI, expressionBesoinController $basiController, int $sessionIdDirectionDirecteur): void {
    try {
        if ($sessionIdDirectionDirecteur <= 0) {
            echo json_encode(['status' => 'error', 'message' => "Aucune direction associée à votre compte."]);
            return;
        }
        $token = trim((string) inputValueEB('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEBI = (int) $basiController->tokendecrypt($token);
        if ($idEBI <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmt = $bdBASI->prepare("
            SELECT id, nom_expression, date_creation, idStatut, dateEnregistrement
            FROM expression_besoin_investissement
            WHERE id = ? AND idDirection = ?
            LIMIT 1
        ");
        $stmt->execute([$idEBI, $sessionIdDirectionDirecteur]);
        $expression = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$expression) {
            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable.']);
            return;
        }

        $stmtProduits = $bdBASI->prepare("
            SELECT ebip.id AS idEBIP, ebip.id_produit, ebip.quantite_demandee, ebip.quantite_sortie,
                   ebip.quantite_livree, ebip.quantite_recue, ebip.quantite_annulee,
                   (SELECT COALESCE(SUM(bl.quantite_ecart), 0)  FROM bon_sortie_ebi_ligne bl WHERE bl.idEBIP = ebip.id) AS quantite_ecart,
                   (SELECT COALESCE(SUM(bl.quantite_perdue), 0) FROM bon_sortie_ebi_ligne bl WHERE bl.idEBIP = ebip.id) AS quantite_perdue,
                   p.nomproduit as designation
            FROM expression_besoin_investissement_produit ebip
            JOIN product p ON ebip.id_produit = p.idP
            WHERE ebip.idEBI = ? AND ebip.statut = 1
            ORDER BY ebip.id ASC
        ");
        $stmtProduits->execute([$idEBI]);
        $produits = $stmtProduits->fetchAll(PDO::FETCH_ASSOC);

        foreach ($produits as &$p) {
            $qteDemandee = (float) $p['quantite_demandee'];
            $qteSortie   = (float) ($p['quantite_sortie'] ?? 0);
            $qteLivree   = (float) ($p['quantite_livree'] ?? 0);
            $qteRecue    = (float) ($p['quantite_recue'] ?? 0);
            $qteAnnulee  = (float) ($p['quantite_annulee'] ?? 0);
            $qteEcart    = (float) ($p['quantite_ecart'] ?? 0);
            $qtePerdue   = (float) ($p['quantite_perdue'] ?? 0);

            $resteARecevoir = max(0, $qteLivree - $qteRecue - $qteEcart - $qtePerdue);
            $resteALivrer   = max(0, $qteSortie - $qteLivree);
            $p['quantite_restante_a_recevoir'] = $resteARecevoir;
            $p['quantite_restante_a_livrer']   = $resteALivrer;
            $p['peut_confirmer_reception'] = ($resteARecevoir > 0.001);

            $cible = max(0, $qteDemandee - $qteAnnulee); // demandée, nette du solde annulé
            $p['quantite_restante'] = max(0, $cible - $qteSortie);

            if ($qteEcart > 0.001) {
                $p['statut_ligne'] = 'Écart signalé — en attente du comptable';
            } elseif ($resteARecevoir > 0.001) {
                $p['statut_ligne'] = 'Livré — en attente de votre confirmation';
            } elseif ($resteALivrer > 0.001) {
                $p['statut_ligne'] = 'Sorti du stock — en attente du magasinier';
            } elseif ($qteRecue + $qtePerdue > 0.001) {
                $p['statut_ligne'] = ($qteRecue + $qtePerdue >= $cible - 0.001)
                    ? ($qteAnnulee > 0.001 ? 'Reçu — solde annulé' : 'Reçu')
                    : 'Reçu partiellement';
            } elseif ($qteAnnulee > 0.001 && $cible <= 0.001) {
                $p['statut_ligne'] = 'Annulée';
            } else {
                $p['statut_ligne'] = 'En attente';
            }
        }
        unset($p);

        $stmtHisto = $bdBASI->prepare("
            SELECT idStatut, motif, dateEnregistrement
            FROM historique_expression_besoin_investissement
            WHERE idEBI = ?
            ORDER BY dateEnregistrement ASC
        ");
        $stmtHisto->execute([$idEBI]);
        $historique = $stmtHisto->fetchAll(PDO::FETCH_ASSOC);

        $expression['produits'] = $produits;

        $stmtBons = $bdBASI->prepare("SELECT id, numero_bon, dateSortie, idStatut FROM bon_sortie_ebi WHERE idEBI = ? ORDER BY dateSortie ASC, id ASC");
        $stmtBons->execute([$idEBI]);
        $bons = $stmtBons->fetchAll(PDO::FETCH_ASSOC);
        $stmtBonLignes = $bdBASI->prepare("
            SELECT bl.id AS idBSL, bl.quantite_sortie, bl.quantite_livree, bl.quantite_recue, bl.quantite_ecart, bl.quantite_perdue, p.nomproduit AS designation
            FROM bon_sortie_ebi_ligne bl
            JOIN product p ON bl.id_produit = p.idP
            WHERE bl.idBS = ?
            ORDER BY bl.id ASC
        ");
        foreach ($bons as &$b) {
            $stmtBonLignes->execute([$b['id']]);
            $b['lignes'] = $stmtBonLignes->fetchAll(PDO::FETCH_ASSOC);
            $b['peut_confirmer'] = false;
            foreach ($b['lignes'] as &$bl) {
                $bl['quantite_restante_a_recevoir'] = max(0, (float) $bl['quantite_livree'] - (float) $bl['quantite_recue'] - (float) $bl['quantite_ecart'] - (float) $bl['quantite_perdue']);
                $bl['peut_confirmer_reception'] = ($bl['quantite_restante_a_recevoir'] > 0.001);
                if ($bl['peut_confirmer_reception']) $b['peut_confirmer'] = true;
            }
            unset($bl);
        }
        unset($b);
        $expression['bons'] = $bons;
        $expression['historique'] = $historique;

        echo json_encode(['status' => 'success', 'expression' => $expression]);
    } catch (\Throwable $e) {
        error_log('[EBI][voirSuiviExpressionBesoinInvestissement] ' . $e->getMessage());
        erreurSqlEB("Impossible de charger le suivi de l'expression de besoin.");
    }
}

/**
 * OPTION 19 — Confirme la réception Investissement, bon par bon et ligne par
 * ligne (avec écart optionnel). Réservée à un chef de service de la MÊME
 * direction que la demande (comme le reste du module Investissement).
 *
 * Champs attendus : token, quantites: [{ idBSL, quantite, ecart?, commentaire? }, ...]
 */
function confirmerReceptionDemandeurInvestissement(PDO $bdBASI, expressionBesoinController $basiController, int $sessionUserId, string $sessionMatricule, int $sessionIdDirectionDirecteur): void {
    try {
        if ($sessionIdDirectionDirecteur <= 0) {
            echo json_encode(['status' => 'error', 'message' => "Aucune direction associée à votre compte."]);
            return;
        }
        $token = trim((string) inputValueEB('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEBI = (int) $basiController->tokendecrypt($token);
        if ($idEBI <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $saisies = inputValueEB('quantites', []);
        if (!is_array($saisies)) $saisies = [];
        $parLigne = [];
        foreach ($saisies as $q) {
            $idBSL = (int) ($q['idBSL'] ?? 0);
            if ($idBSL <= 0) continue;
            $parLigne[$idBSL] = [
                'recue'       => (float) ($q['quantite'] ?? 0),
                'ecart'       => (float) ($q['ecart'] ?? 0),
                'commentaire' => trim((string) ($q['commentaire'] ?? '')),
            ];
        }

        $stmtC = $bdBASI->prepare("SELECT id FROM expression_besoin_investissement WHERE id = ? AND idDirection = ? LIMIT 1");
        $stmtC->execute([$idEBI, $sessionIdDirectionDirecteur]);
        if (!$stmtC->fetch()) { echo json_encode(['status' => 'error', 'message' => "Expression de besoin introuvable ou hors de votre direction."]); return; }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $motifRecu  = "Réception confirmée par le demandeur (par $sessionMatricule)";
        $motifEcart = "Écart de réception signalé par le demandeur (par $sessionMatricule)";

        $bdBASI->beginTransaction();

        $stmtLignes = $bdBASI->prepare("
            SELECT bl.id AS idBSL, bl.idBS, bl.idEBIP, bl.quantite_livree, bl.quantite_recue, bl.quantite_ecart, bl.quantite_perdue
            FROM bon_sortie_ebi_ligne bl
            JOIN bon_sortie_ebi bs ON bs.id = bl.idBS
            WHERE bs.idEBI = ?
            FOR UPDATE
        ");
        $stmtLignes->execute([$idEBI]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
        if (empty($lignes)) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Aucun bon de sortie pour cette expression de besoin.']);
            return;
        }

        $stmtRecueBon  = $bdBASI->prepare("UPDATE bon_sortie_ebi_ligne SET quantite_recue = quantite_recue + ? WHERE id = ?");
        $stmtRecueEBIP = $bdBASI->prepare("UPDATE expression_besoin_investissement_produit SET quantite_recue = quantite_recue + ?, dateEnregistrement = ? WHERE id = ?");

        $auMoinsUneAction = false;
        $ecartSignale = false;
        $bonsTouches = [];
        foreach ($lignes as $l) {
            $idBSL = (int) $l['idBSL'];
            if (!isset($parLigne[$idBSL])) continue;
            $recue = $parLigne[$idBSL]['recue'];
            $ecart = $parLigne[$idBSL]['ecart'];
            if ($recue < 0 || $ecart < 0) { $bdBASI->rollBack(); echo json_encode(['status' => 'error', 'message' => 'Les quantités ne peuvent pas être négatives.']); return; }
            if (($recue > 0 && !estEntierPositif($recue)) || ($ecart > 0 && !estEntierPositif($ecart))) {
                $bdBASI->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Les quantités doivent être des nombres entiers.']);
                return;
            }
            if ($recue <= 0.001 && $ecart <= 0.001) continue;

            $restant = max(0, (float) $l['quantite_livree'] - (float) $l['quantite_recue'] - (float) $l['quantite_ecart'] - (float) $l['quantite_perdue']);

            if ($recue + $ecart > $restant + 0.001) {
                $bdBASI->rollBack();
                echo json_encode(['status' => 'error', 'message' => "La quantité reçue et l'écart déclaré dépassent ce qui vous a été remis dans ce bon pour au moins une ligne."]);
                return;
            }

            if ($recue > 0.001) {
                $stmtRecueBon->execute([$recue, $idBSL]);
                $stmtRecueEBIP->execute([$recue, $dateEnregistrement, $l['idEBIP']]);
                ebwi_historiserLigneBon($bdBASI, $idBSL, $motifRecu, $sessionUserId, $dateEnregistrement);
                ebwi_historiserEBIP($bdBASI, (int) $l['idEBIP'], $motifRecu, $sessionUserId, $dateEnregistrement);
            }
            if ($ecart > 0.001) {
                ebwi_signalerEcart($bdBASI, $idBSL, $ecart, $parLigne[$idBSL]['commentaire'], $sessionUserId, $motifEcart, $dateEnregistrement);
                $ecartSignale = true;
            }
            $bonsTouches[(int) $l['idBS']] = true;
            $auMoinsUneAction = true;
        }

        if (!$auMoinsUneAction) {
            $bdBASI->rollBack();
            echo json_encode(['status' => 'error', 'message' => "Aucune réception confirmée : veuillez saisir au moins une quantité reçue ou un écart."]);
            return;
        }

        foreach (array_keys($bonsTouches) as $idBS) {
            ebwi_recalculerStatutBon($bdBASI, $idBS, $sessionUserId, $motifRecu, $dateEnregistrement);
        }
        ebwi_recalculerStatutEBI($bdBASI, $idEBI, $motifRecu, $dateEnregistrement);

        $bdBASI->commit();
        echo json_encode(['status' => 'success', 'message' => $ecartSignale
            ? "Réception enregistrée. L'écart signalé a été transmis au comptable pour régularisation."
            : 'Réception confirmée avec succès.']);
    } catch (EbwException $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[EBI][confirmerReceptionDemandeurInvestissement] ' . $e->getMessage());
        erreurSqlEB('Impossible de confirmer la réception.');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Page dédiée "Réception des produits" : liste en un seul endroit
   tous les bons (Fonctionnement + Investissement) ayant au moins une ligne
   remise par le magasinier mais pas encore confirmée par ce demandeur,
   quelle que soit l'expression de besoin d'origine.
═══════════════════════════════════════════════════════════════════════════ */

/**
 * OPTION 20 — Liste des bons en attente de confirmation, tous types
 * confondus. Fonctionnement : bons des expressions dont l'utilisateur est
 * le demandeur. Investissement : bons des expressions de sa direction
 * (même règle que le reste du module Investissement — la direction entière
 * partage la confirmation, pas un seul individu).
 */
function listerBonsAConfirmer(PDO $bdBASI, expressionBesoinController $basiController, int $sessionUserId, int $sessionIdDirectionDirecteur): void {
    try {
        $stmtF = $bdBASI->prepare("
            SELECT bs.id, bs.numero_bon, bs.dateSortie, eb.nom_expression,
                   'fonctionnement' AS type,
                   (SELECT COUNT(*) FROM bon_sortie_eb_ligne bl
                    WHERE bl.idBS = bs.id AND (bl.quantite_livree - bl.quantite_recue - bl.quantite_ecart - bl.quantite_perdue) > 0) AS nombre_lignes_a_confirmer
            FROM bon_sortie_eb bs
            JOIN expression_besoin eb ON eb.id = bs.idEB
            WHERE eb.idUtilisateur = ?
            HAVING nombre_lignes_a_confirmer > 0
        ");
        $stmtF->execute([$sessionUserId]);
        $bonsFonctionnement = $stmtF->fetchAll(PDO::FETCH_ASSOC);

        $bonsInvestissement = [];
        if ($sessionIdDirectionDirecteur > 0) {
            $stmtI = $bdBASI->prepare("
                SELECT bs.id, bs.numero_bon, bs.dateSortie, ebi.nom_expression,
                       'investissement' AS type,
                       (SELECT COUNT(*) FROM bon_sortie_ebi_ligne bl
                        WHERE bl.idBS = bs.id AND (bl.quantite_livree - bl.quantite_recue - bl.quantite_ecart - bl.quantite_perdue) > 0) AS nombre_lignes_a_confirmer
                FROM bon_sortie_ebi bs
                JOIN expression_besoin_investissement ebi ON ebi.id = bs.idEBI
                WHERE ebi.idDirection = ?
                HAVING nombre_lignes_a_confirmer > 0
            ");
            $stmtI->execute([$sessionIdDirectionDirecteur]);
            $bonsInvestissement = $stmtI->fetchAll(PDO::FETCH_ASSOC);
        }

        $tous = array_merge($bonsFonctionnement, $bonsInvestissement);
        usort($tous, fn($a, $b) => strcmp($a['dateSortie'], $b['dateSortie']));

        foreach ($tous as &$b) {
            $b['tmp'] = $basiController->tokenencrypt($b['type'][0] . $b['id']); // préfixe f/i + id, décodé côté détail
        }
        unset($b);

        echo json_encode(['status' => 'success', 'data' => $tous, 'nombre_total' => count($tous)]);
    } catch (\Throwable $e) {
        error_log('[EB][listerBonsAConfirmer] ' . $e->getMessage());
        erreurSqlEB('Impossible de charger la liste des réceptions en attente.');
    }
}

/**
 * OPTION 21 — Détail d'UN bon (Fonctionnement ou Investissement, déduit du
 * préfixe du token) pour la page de réception : lignes encore à confirmer.
 */
function detailBonReception(PDO $bdBASI, expressionBesoinController $basiController, int $sessionUserId, int $sessionIdDirectionDirecteur): void {
    try {
        $token = trim((string) inputValueEB('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $decode = (string) $basiController->tokendecrypt($token);
        $type = $decode[0] ?? '';
        $idBS = (int) substr($decode, 1);
        if ($idBS <= 0 || !in_array($type, ['f', 'i'], true)) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        if ($type === 'f') {
            $stmtC = $bdBASI->prepare("
                SELECT bs.id, bs.numero_bon, bs.dateSortie, eb.nom_expression
                FROM bon_sortie_eb bs JOIN expression_besoin eb ON eb.id = bs.idEB
                WHERE bs.id = ? AND eb.idUtilisateur = ?
                LIMIT 1
            ");
            $stmtC->execute([$idBS, $sessionUserId]);
        } else {
            $stmtC = $bdBASI->prepare("
                SELECT bs.id, bs.numero_bon, bs.dateSortie, ebi.nom_expression
                FROM bon_sortie_ebi bs JOIN expression_besoin_investissement ebi ON ebi.id = bs.idEBI
                WHERE bs.id = ? AND ebi.idDirection = ?
                LIMIT 1
            ");
            $stmtC->execute([$idBS, $sessionIdDirectionDirecteur]);
        }
        $bon = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$bon) { echo json_encode(['status' => 'error', 'message' => 'Bon introuvable.']); return; }

        $table = $type === 'f' ? 'bon_sortie_eb_ligne' : 'bon_sortie_ebi_ligne';
        $colProduit = $type === 'f' ? 'idP' : 'id_produit';
        $stmtLignes = $bdBASI->prepare("
            SELECT bl.id AS idBSL, bl.quantite_livree, bl.quantite_recue, bl.quantite_ecart, bl.quantite_perdue, p.nomproduit AS designation
            FROM $table bl
            JOIN product p ON bl.$colProduit = p.idP
            WHERE bl.idBS = ?
            ORDER BY bl.id ASC
        ");
        $stmtLignes->execute([$idBS]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

        $lignes = array_values(array_filter(array_map(function ($l) {
            $l['quantite_restante_a_recevoir'] = max(0, (int) $l['quantite_livree'] - (int) $l['quantite_recue'] - (int) $l['quantite_ecart'] - (int) $l['quantite_perdue']);
            return $l['quantite_restante_a_recevoir'] > 0 ? $l : null;
        }, $lignes)));

        $bon['type'] = $type === 'f' ? 'fonctionnement' : 'investissement';
        $bon['lignes'] = $lignes;
        echo json_encode(['status' => 'success', 'bon' => $bon]);
    } catch (\Throwable $e) {
        error_log('[EB][detailBonReception] ' . $e->getMessage());
        erreurSqlEB('Impossible de charger le détail du bon.');
    }
}

/**
 * OPTION 22 — Confirme la réception d'UN bon depuis la page dédiée.
 * La quantité reçue est TOUJOURS la totalité de ce qui restait à confirmer
 * sur chaque ligne du bon (pas de saisie partielle, pas d'écart pour
 * l'instant — cette page ne les propose pas). Réutilise directement
 * confirmerReceptionDemandeur / confirmerReceptionDemandeurInvestissement
 * en leur transmettant le token décodé de la bonne expression de besoin.
 */
function confirmerReceptionBon(PDO $bdBASI, expressionBesoinController $basiController, int $sessionUserId, string $sessionMatricule, int $sessionIdDirectionDirecteur): void {
    try {
        $token = trim((string) inputValueEB('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $decode = (string) $basiController->tokendecrypt($token);
        $type = $decode[0] ?? '';
        $idBS = (int) substr($decode, 1);
        if ($idBS <= 0 || !in_array($type, ['f', 'i'], true)) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $motif = "Réception confirmée par le demandeur, depuis la page Réception des produits (par $sessionMatricule)";

        if ($type === 'f') {
            $stmtEB = $bdBASI->prepare("SELECT eb.id FROM bon_sortie_eb bs JOIN expression_besoin eb ON eb.id = bs.idEB WHERE bs.id = ? AND eb.idUtilisateur = ? LIMIT 1");
            $stmtEB->execute([$idBS, $sessionUserId]);
            $idEB = (int) $stmtEB->fetchColumn();
            if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Bon introuvable.']); return; }

            $bdBASI->beginTransaction();
            $stmtLignes = $bdBASI->prepare("SELECT id AS idBSL, idEBP, quantite_livree, quantite_recue, quantite_ecart, quantite_perdue FROM bon_sortie_eb_ligne WHERE idBS = ? FOR UPDATE");
            $stmtLignes->execute([$idBS]);
            $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

            $stmtRecueBon = $bdBASI->prepare("UPDATE bon_sortie_eb_ligne SET quantite_recue = quantite_recue + ? WHERE id = ?");
            $stmtRecueEBP = $bdBASI->prepare("UPDATE expression_besoin_produit SET quantite_recue = quantite_recue + ?, dateEnregistrement = ? WHERE id = ?");

            $auMoinsUne = false;
            foreach ($lignes as $l) {
                $restant = max(0, (int) $l['quantite_livree'] - (int) $l['quantite_recue'] - (int) $l['quantite_ecart'] - (int) $l['quantite_perdue']);
                if ($restant <= 0) continue;
                $stmtRecueBon->execute([$restant, $l['idBSL']]);
                $stmtRecueEBP->execute([$restant, $dateEnregistrement, $l['idEBP']]);
                ebw_historiserLigneBon($bdBASI, (int) $l['idBSL'], $motif, $sessionUserId, $dateEnregistrement);
                ebw_historiserEBP($bdBASI, (int) $l['idEBP'], $motif, $sessionUserId, $dateEnregistrement);
                $auMoinsUne = true;
            }
            if (!$auMoinsUne) { $bdBASI->rollBack(); echo json_encode(['status' => 'error', 'message' => 'Ce bon est déjà entièrement confirmé.']); return; }

            ebw_recalculerStatutBon($bdBASI, $idBS, $sessionUserId, $motif, $dateEnregistrement);
            ebw_recalculerStatutEB($bdBASI, $idEB, $motif, $dateEnregistrement);
            $bdBASI->commit();
        } else {
            $stmtEB = $bdBASI->prepare("SELECT ebi.id FROM bon_sortie_ebi bs JOIN expression_besoin_investissement ebi ON ebi.id = bs.idEBI WHERE bs.id = ? AND ebi.idDirection = ? LIMIT 1");
            $stmtEB->execute([$idBS, $sessionIdDirectionDirecteur]);
            $idEBI = (int) $stmtEB->fetchColumn();
            if ($idEBI <= 0) { echo json_encode(['status' => 'error', 'message' => 'Bon introuvable.']); return; }

            $bdBASI->beginTransaction();
            $stmtLignes = $bdBASI->prepare("SELECT id AS idBSL, idEBIP, quantite_livree, quantite_recue, quantite_ecart, quantite_perdue FROM bon_sortie_ebi_ligne WHERE idBS = ? FOR UPDATE");
            $stmtLignes->execute([$idBS]);
            $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

            $stmtRecueBon  = $bdBASI->prepare("UPDATE bon_sortie_ebi_ligne SET quantite_recue = quantite_recue + ? WHERE id = ?");
            $stmtRecueEBIP = $bdBASI->prepare("UPDATE expression_besoin_investissement_produit SET quantite_recue = quantite_recue + ?, dateEnregistrement = ? WHERE id = ?");

            $auMoinsUne = false;
            foreach ($lignes as $l) {
                $restant = max(0, (int) $l['quantite_livree'] - (int) $l['quantite_recue'] - (int) $l['quantite_ecart'] - (int) $l['quantite_perdue']);
                if ($restant <= 0) continue;
                $stmtRecueBon->execute([$restant, $l['idBSL']]);
                $stmtRecueEBIP->execute([$restant, $dateEnregistrement, $l['idEBIP']]);
                ebwi_historiserLigneBon($bdBASI, (int) $l['idBSL'], $motif, $sessionUserId, $dateEnregistrement);
                ebwi_historiserEBIP($bdBASI, (int) $l['idEBIP'], $motif, $sessionUserId, $dateEnregistrement);
                $auMoinsUne = true;
            }
            if (!$auMoinsUne) { $bdBASI->rollBack(); echo json_encode(['status' => 'error', 'message' => 'Ce bon est déjà entièrement confirmé.']); return; }

            ebwi_recalculerStatutBon($bdBASI, $idBS, $sessionUserId, $motif, $dateEnregistrement);
            ebwi_recalculerStatutEBI($bdBASI, $idEBI, $motif, $dateEnregistrement);
            $bdBASI->commit();
        }

        echo json_encode(['status' => 'success', 'message' => 'Réception confirmée avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[EB][confirmerReceptionBon] ' . $e->getMessage());
        erreurSqlEB('Impossible de confirmer la réception.');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   ROUTAGE
   1 = listerExpressionsBesoin       (ses propres demandes, filtrable par années)
   2 = listerCategoriesEligibles     (catégories avec ≥1 produit actif)
   3 = listerSousCategoriesEligibles (sous-catégories avec ≥1 produit actif)
   4 = listerProduitsEligibles       (produits actifs d'une sous-catégorie)
   5 = detailExpressionBesoin        (pour modification ou Détail)
   6 = enregistrerExpressionBesoin   (création OU modification + réconciliation)
   7 = voirSuiviExpressionBesoin     (bouton "Voir" — suivi complet, tous statuts)

   ── Investissement (visible uniquement si idDirection défini en session) ──
   10 = chargerContexteDirection               (a-t-on accès à l'onglet Investissement ?)
   13 = listerProduitsEligiblesInvestissement  (liste directe, quota > 0, pas de cascade catégorie)
   14 = listerExpressionsBesoinInvestissement
   15 = detailExpressionBesoinInvestissement
   16 = enregistrerExpressionBesoinInvestissement (brouillon OU terminé → sortie immédiate)
═══════════════════════════════════════════════════════════════════════════ */
try {
    switch ($option) {
        case 1:
            listerExpressionsBesoin($bdBASI, $basiController, $sessionUserId);
            break;

        case 2:
            listerCategoriesEligibles($bdBASI);
            break;

        case 3:
            listerSousCategoriesEligibles($bdBASI);
            break;

        case 4:
            listerProduitsEligibles($bdBASI);
            break;

        case 5:
            detailExpressionBesoin($bdBASI, $basiController, $sessionUserId);
            break;

        case 6:
            enregistrerExpressionBesoin($bdBASI, $basiController, $sessionUserId, $sessionMatricule, $sessionIdDirection);
            break;

        case 7:
            voirSuiviExpressionBesoin($bdBASI, $basiController, $sessionUserId);
            break;

        case 10:
            chargerContexteDirection($bdBASI, $sessionIdDirectionDirecteur);
            break;

        case 13:
            listerProduitsEligiblesInvestissement($bdBASI, $sessionIdDirectionDirecteur);
            break;

        case 14:
            listerExpressionsBesoinInvestissement($bdBASI, $basiController, $sessionIdDirectionDirecteur);
            break;

        case 15:
            detailExpressionBesoinInvestissement($bdBASI, $basiController, $sessionIdDirectionDirecteur);
            break;

        case 16:
            enregistrerExpressionBesoinInvestissement($bdBASI, $basiController, $sessionUserId, $sessionMatricule, $sessionIdDirectionDirecteur);
            break;

        case 17:
            confirmerReceptionDemandeur($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 18:
            voirSuiviExpressionBesoinInvestissement($bdBASI, $basiController, $sessionIdDirectionDirecteur);
            break;

        case 19:
            confirmerReceptionDemandeurInvestissement($bdBASI, $basiController, $sessionUserId, $sessionMatricule, $sessionIdDirectionDirecteur);
            break;

        case 20:
            listerBonsAConfirmer($bdBASI, $basiController, $sessionUserId, $sessionIdDirectionDirecteur);
            break;

        case 21:
            detailBonReception($bdBASI, $basiController, $sessionUserId, $sessionIdDirectionDirecteur);
            break;

        case 22:
            confirmerReceptionBon($bdBASI, $basiController, $sessionUserId, $sessionMatricule, $sessionIdDirectionDirecteur);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Option '$option' non reconnue."]);
            exit;
    }
} catch (\Throwable $e) {
    error_log('[EB][Routage] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Une erreur inattendue est survenue.']);
    exit;
}