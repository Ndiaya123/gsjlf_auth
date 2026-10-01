<?php
/**
 * chefDirectionEBController.php
 * Module Expression de besoin — profil Chef de direction : consultation des
 * expressions de besoin de sa propre direction, Valider / Rejeter une
 * demande Soumise (idStatut = 2).
 *
 * ⚠️ Hypothèses de schéma (à confirmer / ajuster — cohérentes avec
 * expressionBesoinController.php) :
 *   - `expression_besoin` : id, nom_expression, idUtilisateur, idDirection,
 *     date_creation, idStatut, dateEnregistrement.
 *   - `expression_besoin_produit` : id, idEB, idP, quantite, statut,
 *     dateEnregistrement, + colonne `quantite_reelle` À AJOUTER (NULL par
 *     défaut, renseignée uniquement à la validation) :
 *       ALTER TABLE expression_besoin_produit ADD COLUMN quantite_reelle DECIMAL(15,2) NULL;
 *       ALTER TABLE historique_expression_besoin_produit ADD COLUMN quantite_reelle DECIMAL(15,2) NULL;
 *   - `historique_expression_besoin` / `historique_expression_besoin_produit` :
 *     mêmes colonnes que leurs tables respectives + motif, dateEnregistrement.
 *   - `product` (PK = idP) : idP, nomproduit.
 *   - `utilisateurs` : id, prenom, nom, email.
 *   - Le chef de direction est identifié par $_SESSION['tmpIdDirection'] (nom
 *     de variable explicitement fourni dans la demande, distinct de
 *     $_SESSION['user_direction'] utilisé côté utilisateur standard).
 *   - Liste visible au chef de direction : idStatut ∈ {2, 3, 4} uniquement
 *     (les demandes encore En attente = 1, non soumises, ne sont pas
 *     visibles — hypothèse cohérente avec "de la même manière que
 *     l'utilisateur", à confirmer).
 *   - Envoi d'e-mail au rejet : via PHPMailer (SMTP Gmail, criat@uahb.sn) —
 *     chemins require_once vers includes/phpMailer/ à ajuster selon la
 *     profondeur réelle du fichier sur le serveur. ⚠️ Mot de passe SMTP
 *     (`$mail->Password`) laissé vide dans ce fichier — à renseigner.
 */

// ─── PHPMailer ────────────────────────────────────────────────────────────────
require_once('../../../includes/phpMailer/PHPMailer.php');
require_once('../../../includes/phpMailer/SMTP.php');
require_once('../../../includes/phpMailer/Exception.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// ─── Capture de tout output parasite ─────────────────────────────────────────
ob_start();
session_start();
include_once('../../../bdBASI.php'); // ← ajuster selon la profondeur réelle du fichier
ob_end_clean();

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// ─── Vérification sessions obligatoires ──────────────────────────────────────
function checkSessionChefDirEB(): void {
    foreach (['tmpIdBASI', 'tmpMatricule', 'tmpIdDirection'] as $key) {
        if (empty($_SESSION[$key])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['status'=>'error','code'=>'sessionExpired','message'=>'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }
    }
}
checkSessionChefDirEB();

$sessionUserId      = (int)$_SESSION['tmpIdBASI'];
$sessionMatricule   = trim($_SESSION['tmpMatricule']);
$sessionIdDirection = (int)$_SESSION['tmpIdDirection'];

// ─── Classe contrôleur ────────────────────────────────────────────────────────
class chefDirectionEBController extends BDBASI
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
    $basiController = new chefDirectionEBController();
} catch (\Throwable $e) {
    error_log('[ChefDirEB][Connexion] ' . $e->getMessage());
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

function getJsonBodyChefDirEB(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
function inputValueChefDirEB(string $key, $default = null) {
    static $jsonBody = null;
    if ($jsonBody === null) $jsonBody = getJsonBodyChefDirEB();
    if (array_key_exists($key, $jsonBody)) return $jsonBody[$key];
    if (isset($_POST[$key]))               return $_POST[$key];
    if (isset($_GET[$key]))                return $_GET[$key];
    return $default;
}
/** Les produits sont des unités entières — jamais de quantité à virgule. */
function estEntierPositif($valeur): bool {
    return is_numeric($valeur) && (float) $valeur == (int) $valeur && (int) $valeur > 0;
}

function erreurSqlChefDirEB(string $message = "Erreur lors de l'accès à la base de données."): void {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$message]);
}

// ─── Lecture de l'option ──────────────────────────────────────────────────────
$option = isset($_GET['option']) ? trim($_GET['option']) : '';
if ($option==='' && isset($_POST['option'])) $option = trim($_POST['option']);
if ($option==='') {
    $jsonBodyOption = getJsonBodyChefDirEB();
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
 * imprimé avant lui (avertissement PHP, notice, texte parasite...). Le
 * JSON recherché commence toujours par "{" ou "[".
 */
function dga_nettoyerSortieJson(string $buffer): string {
    $pos = strpos($buffer, '{');
    $posCrochet = strpos($buffer, '[');
    if ($posCrochet !== false && ($pos === false || $posCrochet < $pos)) $pos = $posCrochet;
    return ($pos !== false) ? substr($buffer, $pos) : $buffer;
}

// ─── Tampon de sortie défensif ────────────────────────────────────────────
// Le callback ci-dessus s'exécute automatiquement à la fin du script — y
// compris après un exit — donc aucune sortie parasite ne peut plus jamais
// corrompre la réponse JSON attendue par le JS.
ob_start('dga_nettoyerSortieJson');

/* ═══════════════════════════════════════════════════════════════════════════
   ACTIONS
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Liste des expressions de besoin de la direction du chef de direction
 * connecté (idDirection = $_SESSION['tmpIdDirection']), filtrable par
 * intervalle d'années — même présentation que la liste utilisateur.
 */
function listerExpressionsBesoinDirection(PDO $bdBASI, chefDirectionEBController $basiController, int $sessionIdDirection): void {
    try {
        $anneeCourante = (int) date('Y');
        $anneeFin = (int) inputValueChefDirEB('anneeFin', $anneeCourante);
        if ($anneeFin <= 0) $anneeFin = $anneeCourante;

        $anneeDebutBrute = trim((string) inputValueChefDirEB('anneeDebut', ''));
        $anneeDebut = ($anneeDebutBrute === '') ? $anneeFin : (int) $anneeDebutBrute;
        if ($anneeDebut <= 0) $anneeDebut = $anneeFin;
        if ($anneeDebut > $anneeFin) { [$anneeDebut, $anneeFin] = [$anneeFin, $anneeDebut]; }

        // Filtre par statut — par défaut "Soumise" (2), afin d'afficher en
        // priorité les demandes en attente de traitement.
        // 0 = Tous les statuts (2, 3, 4, 5, 6 confondus).
        $statutFiltre = (int) inputValueChefDirEB('statut', 2);
        if (!in_array($statutFiltre, [0, 2, 3, 4, 5, 6], true)) $statutFiltre = 2;
        // Circuit livraison/réception : 7 (Livrée), 8 (Clôturée), 9 (Clôturée avec
        // solde) et 10 (Annulée) restent visibles et sont regroupées avec 6
        // (Sortie totale) dans le filtre et les stats.

        // Statistiques par statut (Soumise/Validée/Rejetée/Terminée), sur le
        // même périmètre direction + intervalle d'années, INDÉPENDANTES du
        // filtre de statut appliqué à la liste elle-même.
        $stmtStats = $bdBASI->prepare("
            SELECT idStatut, COUNT(*) AS n
            FROM expression_besoin
            WHERE idDirection = ? AND idStatut IN (2, 3, 4, 5, 6, 7, 8, 9, 10) AND YEAR(date_creation) BETWEEN ? AND ?
            GROUP BY idStatut
        ");
        $stmtStats->execute([$sessionIdDirection, $anneeDebut, $anneeFin]);
        $stats = ['2' => 0, '3' => 0, '4' => 0, '5' => 0, '6' => 0];
        foreach ($stmtStats->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cle = (string)(int)$row['idStatut'];
            if (in_array($cle, ['7', '8', '9', '10'], true)) $cle = '6'; // regroupées avec Sortie totale
            if (isset($stats[$cle])) $stats[$cle] += (int)$row['n'];
        }

        $sql = "
            SELECT eb.id, eb.nom_expression, eb.date_creation, eb.idStatut, eb.dateEnregistrement,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur,
                   (SELECT COUNT(*) FROM expression_besoin_produit ebp WHERE ebp.idEB = eb.id AND ebp.statut = 1) AS nombre_produits
            FROM expression_besoin eb
            JOIN utilisateurs u ON eb.idUtilisateur = u.id
            WHERE eb.idDirection = ? AND YEAR(eb.date_creation) BETWEEN ? AND ?
        ";
        $params = [$sessionIdDirection, $anneeDebut, $anneeFin];

        if ($statutFiltre === 0) {
            // "Tous" : les 5 statuts visibles au chef de direction (2, 3, 4, 5, 6).
            $sql .= " AND eb.idStatut IN (2, 3, 4, 5, 6, 7, 8, 9, 10)";
        } elseif ($statutFiltre === 6) {
            $sql .= " AND eb.idStatut IN (6, 7, 8, 9, 10)";
        } else {
            $sql .= " AND eb.idStatut = ?";
            $params[] = $statutFiltre;
        }
        $sql .= " ORDER BY eb.date_creation DESC, eb.id DESC";

        $stmt = $bdBASI->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['id']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows, 'nombre_total' => count($rows), 'stats' => $stats]);
    } catch (\Throwable $e) {
        error_log('[ChefDirEB][listerExpressionsBesoinDirection] ' . $e->getMessage());
        erreurSqlChefDirEB('Impossible de charger la liste des expressions de besoin.');
    }
}

/**
 * Détail d'une expression de besoin de la direction (en-tête + produits),
 * pour l'écran de validation ou la simple consultation.
 */
function detailExpressionBesoinDirection(PDO $bdBASI, chefDirectionEBController $basiController, int $sessionIdDirection): void {
    try {
        $token = trim((string) inputValueChefDirEB('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEB = (int) $basiController->tokendecrypt($token);
        if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmt = $bdBASI->prepare("
            SELECT eb.id, eb.nom_expression, eb.date_creation, eb.idStatut, eb.idDirection,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM expression_besoin eb
            JOIN utilisateurs u ON eb.idUtilisateur = u.id
            WHERE eb.id = ? AND eb.idDirection = ?
            LIMIT 1
        ");
        $stmt->execute([$idEB, $sessionIdDirection]);
        $expression = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$expression) {
            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable.']);
            return;
        }

        // ── Produits : demandée / validée / sortie / livrée / reçue / annulée —
        // même logique que le "Voir" côté personnel (personnelController.php),
        // ENFIN alignée ici : jusqu'ici cette vue datait d'avant les bons de
        // sortie et ne montrait ni livraison, ni réception, ni écarts.
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

        foreach ($produits as &$p) {
            $qteReelle  = $p['quantite_reelle'] !== null ? (float) $p['quantite_reelle'] : null;
            $qteSortie  = (float) ($p['quantite_sortie'] ?? 0);
            $qteLivree  = (float) ($p['quantite_livree'] ?? 0);
            $qteRecue   = (float) ($p['quantite_recue'] ?? 0);
            $qteAnnulee = (float) ($p['quantite_annulee'] ?? 0);
            $qteEcart   = (float) ($p['quantite_ecart'] ?? 0);
            $qtePerdue  = (float) ($p['quantite_perdue'] ?? 0);

            if ($qteReelle === null) {
                $p['quantite_restante'] = null;
                $p['statut_ligne'] = 'En attente';
            } else {
                $cible = max(0, $qteReelle - $qteAnnulee);
                $p['quantite_restante'] = max(0, $cible - $qteSortie);
                $resteALivrer   = max(0, $qteSortie - $qteLivree);
                $resteARecevoir = max(0, $qteLivree - $qteRecue - $qteEcart - $qtePerdue);

                if ($qteEcart > 0.001) {
                    $p['statut_ligne'] = 'Écart signalé — en attente du comptable';
                } elseif ($resteARecevoir > 0.001) {
                    $p['statut_ligne'] = 'Livré — en attente de confirmation du demandeur';
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
        $expression['produits'] = $produits;

        // ── Bons de sortie (un par action du comptable), avec leurs lignes —
        // c'est ce qui manquait le plus : sans eux, impossible de distinguer
        // plusieurs sorties successives ni de voir où en est chacune.
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
        }
        unset($b);
        $expression['bons'] = $bons;

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
        $expression['motif_rejet'] = $motifRejet;

        // ── Historique des changements de statut de l'en-tête ───────────────
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
        error_log('[ChefDirEB][detailExpressionBesoinDirection] ' . $e->getMessage());
        erreurSqlChefDirEB("Impossible de charger le détail de l'expression de besoin.");
    }
}

/**
 * Valide une expression de besoin Soumise (idStatut 2 → 3), en enregistrant
 * la quantite_reelle de chaque ligne (≤ quantite demandée, jamais supérieure).
 *
 * Champs attendus : token, lignes: [{ idEBP, quantite_reelle }, ...]
 */
function validerExpressionBesoin(PDO $bdBASI, chefDirectionEBController $basiController, int $sessionUserId, string $sessionMatricule, int $sessionIdDirection): void {
    try {
        $token = trim((string) inputValueChefDirEB('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEB = (int) $basiController->tokendecrypt($token);
        if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $lignesEnvoyees = inputValueChefDirEB('lignes', []);
        if (!is_array($lignesEnvoyees)) $lignesEnvoyees = [];

        $stmtC = $bdBASI->prepare("
            SELECT id, nom_expression, idUtilisateur, idDirection, date_creation, idStatut
            FROM expression_besoin
            WHERE id = ? AND idDirection = ?
            LIMIT 1
        ");
        $stmtC->execute([$idEB, $sessionIdDirection]);
        $expression = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$expression) {
            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable.']);
            return;
        }
        if ((int)$expression['idStatut'] !== 2) {
            echo json_encode(['status' => 'error', 'message' => 'Seule une expression Soumise peut être validée.']);
            return;
        }

        // Récupère les lignes actives + leur quantité demandée, pour valider
        // que quantite_reelle ne dépasse jamais quantite.
        $stmtLignes = $bdBASI->prepare("SELECT id, quantite FROM expression_besoin_produit WHERE idEB = ? AND statut = 1");
        $stmtLignes->execute([$idEB]);
        $lignesActives = [];
        foreach ($stmtLignes->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $lignesActives[(int)$l['id']] = (float)$l['quantite'];
        }
        if (empty($lignesActives)) {
            echo json_encode(['status' => 'error', 'message' => 'Cette expression de besoin ne contient aucune ligne active.']);
            return;
        }

        $quantitesValidees = [];
        foreach ($lignesEnvoyees as $l) {
            $idEBP = (int) ($l['idEBP'] ?? 0);
            $quantiteReelle = (float) ($l['quantite_reelle'] ?? -1);
            if (!isset($lignesActives[$idEBP])) continue;
            if ($quantiteReelle < 0) {
                echo json_encode(['status' => 'error', 'message' => 'Quantité réelle invalide.']);
                return;
            }
            if ($quantiteReelle > $lignesActives[$idEBP] + 0.001) {
                echo json_encode(['status' => 'error', 'message' => 'La quantité réelle ne peut jamais être supérieure à la quantité demandée.']);
                return;
            }
            if ($quantiteReelle > 0 && !estEntierPositif($quantiteReelle)) {
                echo json_encode(['status' => 'error', 'message' => 'Les quantités doivent être des nombres entiers.']);
                return;
            }
            $quantitesValidees[$idEBP] = $quantiteReelle;
        }
        // Toute ligne active non couverte par l'envoi conserve sa quantité
        // demandée comme quantité réelle par défaut (garde-fou).
        foreach ($lignesActives as $idEBP => $quantiteDemandee) {
            if (!isset($quantitesValidees[$idEBP])) $quantitesValidees[$idEBP] = $quantiteDemandee;
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        $stmtUpdateLigne = $bdBASI->prepare("UPDATE expression_besoin_produit SET quantite_reelle = ?, dateEnregistrement = ? WHERE id = ?");
        $stmtHistoLigne  = $bdBASI->prepare("
            INSERT INTO historique_expression_besoin_produit (idEBP, idEB, idP, quantite, quantite_reelle, statut, motif, dateEnregistrement, idUtilisateur)
            SELECT id, idEB, idP, quantite, quantite_reelle, statut, ?, ?, ?
            FROM expression_besoin_produit
            WHERE id = ?
        ");
        $motifLigne = "Validation par le chef de direction (par $sessionMatricule)";
        foreach ($quantitesValidees as $idEBP => $quantiteReelle) {
            $stmtUpdateLigne->execute([$quantiteReelle, $dateEnregistrement, $idEBP]);
            $stmtHistoLigne->execute([$motifLigne, $dateEnregistrement, $sessionUserId, $idEBP]);
        }

        $bdBASI->prepare("UPDATE expression_besoin SET idStatut = 3 WHERE id = ?")->execute([$idEB]);
        insererHistoriqueEBChefDir($bdBASI, $idEB, $expression['nom_expression'], (int)$expression['idUtilisateur'], (int)$expression['idDirection'], 3, "Validée par le chef de direction (par $sessionMatricule)", $dateEnregistrement);

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Expression de besoin validée avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[ChefDirEB][validerExpressionBesoin] ' . $e->getMessage());
        erreurSqlChefDirEB("Impossible de valider l'expression de besoin.");
    }
}

/**
 * Rejette une expression de besoin Soumise (idStatut 2 → 4), motif
 * obligatoire, envoie un e-mail au créateur de la demande.
 *
 * Champs attendus : token, motif
 */
function rejeterExpressionBesoin(PDO $bdBASI, chefDirectionEBController $basiController, int $sessionMatricule, int $sessionIdDirection): void {
    try {
        $token = trim((string) inputValueChefDirEB('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEB = (int) $basiController->tokendecrypt($token);
        if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $motif = trim((string) inputValueChefDirEB('motif', ''));
        if ($motif === '') {
            echo json_encode(['status' => 'error', 'message' => 'Le motif du rejet est obligatoire.']);
            return;
        }

        $stmtC = $bdBASI->prepare("
            SELECT eb.id, eb.nom_expression, eb.idUtilisateur, eb.idDirection, eb.date_creation, eb.idStatut,
                   u.email, CONCAT(u.prenom, ' ', u.nom) AS demandeurNom
            FROM expression_besoin eb
            JOIN utilisateurs u ON eb.idUtilisateur = u.id
            WHERE eb.id = ? AND eb.idDirection = ?
            LIMIT 1
        ");
        $stmtC->execute([$idEB, $sessionIdDirection]);
        $expression = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$expression) {
            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable.']);
            return;
        }
        if ((int)$expression['idStatut'] !== 2) {
            echo json_encode(['status' => 'error', 'message' => 'Seule une expression Soumise peut être rejetée.']);
            return;
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        $bdBASI->prepare("UPDATE expression_besoin SET idStatut = 4 WHERE id = ?")->execute([$idEB]);

        $motifComplet = "Rejetée par le chef de direction (par $sessionMatricule) — $motif";
        insererHistoriqueEBChefDir($bdBASI, $idEB, $expression['nom_expression'], (int)$expression['idUtilisateur'], (int)$expression['idDirection'], 4, $motifComplet, $dateEnregistrement);

        $bdBASI->commit();

        // Envoi de l'e-mail au créateur — ne doit jamais faire échouer le
        // rejet lui-même si l'envoi échoue (déjà commité en base).
        if (!empty($expression['email'])) {
            envoyerEmailRejetEB($expression['email'], $expression['demandeurNom'], $expression['nom_expression'], $motif);
        }

        echo json_encode(['status' => 'success', 'message' => 'Expression de besoin rejetée avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[ChefDirEB][rejeterExpressionBesoin] ' . $e->getMessage());
        erreurSqlChefDirEB("Impossible de rejeter l'expression de besoin.");
    }
}

/** Insère un instantané de l'en-tête dans historique_expression_besoin. */
function insererHistoriqueEBChefDir(PDO $bdBASI, int $idEB, string $nomExpression, int $idUtilisateur, int $idDirection, int $idStatut, string $motif, string $dateEnregistrement): void {
    $bdBASI->prepare("
        INSERT INTO historique_expression_besoin
            (idEB, nom_expression, idUtilisateur, idDirection, date_creation, idStatut, motif, dateEnregistrement)
        SELECT id, nom_expression, idUtilisateur, idDirection, date_creation, ?, ?, ?
        FROM expression_besoin
        WHERE id = ?
    ")->execute([$idStatut, $motif, $dateEnregistrement, $idEB]);
}

/**
 * Envoie l'e-mail de notification de rejet au créateur de la demande.
 * ⚠️ Utilise mail() natif PHP — à remplacer par PHPMailer/SMTP si le projet
 * en utilise un en production (mail() est souvent peu fiable sans
 * configuration serveur dédiée).
 */
/**
 * Envoi générique d'e-mail via PHPMailer (SMTP Gmail).
 */
function envoyerMail(string $sujet, string $htmlBody, string $to = 'ndiaya.ndao@uahb.sn'): void
{
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'criat@uahb.sn';
        $mail->Password   = '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom('criat@uhb.sn', 'ENT UAHB');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
        $mail->send();
    } catch (PHPMailerException $e) {
        error_log("PHPMailer erreur : " . $e->getMessage());
    }
}

/**
 * Notifie par e-mail le créateur d'une expression de besoin rejetée.
 */
function envoyerEmailRejetEB(string $email, string $nomDestinataire, string $nomExpression, string $motif): void {
    try {
        $sujet = "Votre expression de besoin a été rejetée";
        $htmlBody = "
            <p>Bonjour " . htmlspecialchars($nomDestinataire) . ",</p>
            <p>Votre expression de besoin <strong>" . htmlspecialchars($nomExpression) . "</strong> a été rejetée par votre chef de direction.</p>
            <p><strong>Motif du rejet :</strong><br>" . nl2br(htmlspecialchars($motif)) . "</p>
            <p>Vous pouvez consulter et modifier votre demande depuis la plateforme.</p>
            <p>Cordialement,<br>ENT GSJLF — UAHB</p>
        ";

        envoyerMail($sujet, $htmlBody, $email);
    } catch (\Throwable $e) {
        // L'échec de l'envoi d'e-mail ne doit jamais faire échouer le rejet
        // lui-même (déjà enregistré en base à ce stade).
        error_log('[ChefDirEB][envoyerEmailRejetEB] ' . $e->getMessage());
    }
}

/**
 * Informations sur les sorties d'une expression de besoin Terminée
 * (idStatut = 5) : pour chaque ligne de produit, l'historique détaillé des
 * sorties (quantité sortie à chaque événement, date, motif). Un chef de
 * direction ne peut consulter que les expressions de sa propre direction.
 *
 * Paramètre : token (chiffré de expression_besoin.id)
 */
function detailSortiesExpressionBesoin(PDO $bdBASI, chefDirectionEBController $basiController, int $sessionIdDirection): void {
    try {
        $token = trim((string) inputValueChefDirEB('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEB = (int) $basiController->tokendecrypt($token);
        if ($idEB <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmtC = $bdBASI->prepare("
            SELECT eb.id, eb.nom_expression, eb.date_creation, eb.idStatut,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM expression_besoin eb
            JOIN utilisateurs u ON eb.idUtilisateur = u.id
            WHERE eb.id = ? AND eb.idDirection = ?
            LIMIT 1
        ");
        $stmtC->execute([$idEB, $sessionIdDirection]);
        $expression = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$expression) {
            echo json_encode(['status' => 'error', 'message' => 'Expression de besoin introuvable.']);
            return;
        }

        // Ligne par ligne : quantité demandée / réelle / sortie à ce jour.
        $stmtLignes = $bdBASI->prepare("
            SELECT ebp.id AS idEBP, ebp.idP, ebp.quantite, ebp.quantite_reelle, ebp.quantite_sortie, p.nomproduit AS designation
            FROM expression_besoin_produit ebp
            JOIN product p ON ebp.idP = p.idP
            WHERE ebp.idEB = ? AND ebp.statut = 1
            ORDER BY ebp.id ASC
        ");
        $stmtLignes->execute([$idEB]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

        // Bons de sortie réels (un par action du comptable) — remplace l'ancienne
        // reconstruction par différence d'historique, devenue inutile depuis que
        // ces événements sont directement représentés par une table dédiée.
        // Chaque bon porte aussi son état de livraison/réception.
        $stmtBons = $bdBASI->prepare("
            SELECT bs.id, bs.numero_bon, bs.dateSortie, bs.idStatut,
                   CONCAT(u.prenom, ' ', u.nom) AS utilisateurSortie
            FROM bon_sortie_eb bs
            LEFT JOIN utilisateurs u ON bs.idUtilisateurSortie = u.id
            WHERE bs.idEB = ?
            ORDER BY bs.dateSortie ASC, bs.id ASC
        ");
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
        }
        unset($b);

        $expression['lignes'] = $lignes;
        $expression['bons'] = $bons;
        echo json_encode(['status' => 'success', 'expression' => $expression]);
    } catch (\Throwable $e) {
        error_log('[ChefDirEB][detailSortiesExpressionBesoin] ' . $e->getMessage());
        erreurSqlChefDirEB("Impossible de charger les informations sur les sorties.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Produits d'investissement (page listeProduitInvestissement.php)
   Vue "stock + création directe" pour le chef de service : le tableau des
   produits disponibles sert lui-même de formulaire d'ajout au panier.
   "Soumettre" passe la demande en Soumise (2) — AUCUNE sortie de stock à ce
   stade. Une fois les produits en stock, c'est le COMPTABLE qui en a la
   garde et qui effectue la sortie réelle, sur la page "Sorties de
   produits" (même principe et mêmes tables que le Fonctionnement — voir
   caisseController.php). Pas de validation hiérarchique (chef de
   direction) sur cette demande : la direction consomme son propre quota,
   seule la sortie physique passe par le comptable.
   idStatut de expression_besoin_investissement :
     1 = Brouillon (modifiable)
     2 = Soumise (en attente de sortie par le comptable)
     3 = Partiellement sorti (au moins une sortie effectuée, pas toutes)
     4 = Terminé (toutes les lignes entièrement sorties)
   Même schéma que celui déjà mis en place sur expression-besoin.php côté
   Fonctionnement, adapté aux conventions de CE contrôleur
   ($sessionIdDirection = $_SESSION['tmpIdDirection'], classe
   chefDirectionEBController, inputValueChefDirEB, erreurSqlChefDirEB).
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Calcule le quota disponible pour une direction sur un produit donné :
 * tout ce qui lui a été explicitement attribué en stock à la réception
 * (livraison_produit_repartition, mode='stock'), moins tout ce qu'elle a
 * déjà retiré via ce module (quantite_sortie), moins tout ce qui est déjà
 * demandé mais pas encore sorti par le comptable (réservation — sinon une
 * même quantité pourrait être redemandée deux fois avant traitement).
 * $excludeIdEBI permet d'exclure l'expression en cours d'édition de cette
 * réservation (sans quoi elle se pénaliserait elle-même à chaque saisie).
 * Jamais lu directement sur product.Stock_actuel, qui reste un total
 * physique partagé entre toutes les directions.
 */
function calculerQuotaDirectionInvest(PDO $bdBASI, int $idDirection, int $idP, ?int $excludeIdEBI = null): float {
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

    // Réservation : demandé mais pas encore sorti, sur toute expression
    // ACTIVE (Brouillon/Soumise/Partiellement sorti — donc pas Terminée),
    // hors celle en cours d'édition le cas échéant.
    $sqlReserve = "
        SELECT COALESCE(SUM(ebip.quantite_demandee - ebip.quantite_sortie), 0) AS total
        FROM expression_besoin_investissement_produit ebip
        JOIN expression_besoin_investissement ebi ON ebip.idEBI = ebi.id
        WHERE ebip.id_produit = ? AND ebi.idDirection = ? AND ebip.statut = 1
              AND ebi.idStatut IN (1, 2, 3)
    ";
    $paramsReserve = [$idP, $idDirection];
    if ($excludeIdEBI !== null) {
        $sqlReserve .= " AND ebi.id != ?";
        $paramsReserve[] = $excludeIdEBI;
    }
    $stmtReserve = $bdBASI->prepare($sqlReserve);
    $stmtReserve->execute($paramsReserve);
    $reserve = (float) $stmtReserve->fetch(PDO::FETCH_ASSOC)['total'];

    return max(0.0, $recu - $sorti - $reserve);
}

/**
 * OPTION 6 — Liste directe des produits en stock pour la direction du chef
 * de service connecté (quota > 0 uniquement), avec rubrique/sous-rubrique
 * à titre indicatif via ligneBudget → sousRubrique → rubrique.
 */
function listerProduitsInvestissement(PDO $bdBASI, int $sessionIdDirection): void {
    try {
        $stmtCandidats = $bdBASI->prepare("
            SELECT DISTINCT lp.idP
            FROM livraison_produit_repartition lpr
            JOIN livraison_produit lp ON lpr.idLP = lp.id
            WHERE lpr.mode = 'stock' AND lpr.idDirection = ?
        ");
        $stmtCandidats->execute([$sessionIdDirection]);
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
            $quota = calculerQuotaDirectionInvest($bdBASI, $sessionIdDirection, (int) $p['idP']);
            if ($quota <= 0.001) continue;

            $stmtRubrique->execute([$p['idP']]);
            $rubriqueInfo = $stmtRubrique->fetch(PDO::FETCH_ASSOC);

            $p['quota_disponible']  = $quota;
            $p['nom_rubrique']      = $rubriqueInfo['nom_rubrique'] ?? null;
            $p['nom_sous_rubrique'] = $rubriqueInfo['nom_sous_rubrique'] ?? null;
            $resultat[] = $p;
        }

        echo json_encode(['status' => 'success', 'data' => $resultat]);
    } catch (\Throwable $e) {
        error_log('[ChefDirEB][listerProduitsInvestissement] ' . $e->getMessage());
        erreurSqlChefDirEB('Impossible de charger la liste des produits.');
    }
}

/**
 * OPTION 7 — Liste des expressions de besoin investissement de la direction
 * (partagée entre tous les chefs de service qui se succèdent, jamais
 * filtrée par idUtilisateur).
 */
function listerExpressionsBesoinInvestissement(PDO $bdBASI, chefDirectionEBController $basiController, int $sessionIdDirection): void {
    try {
        $stmt = $bdBASI->prepare("
            SELECT ebi.id, ebi.nom_expression, ebi.date_creation, ebi.idStatut,
                   (SELECT COUNT(*) FROM expression_besoin_investissement_produit ebip WHERE ebip.idEBI = ebi.id AND ebip.statut = 1) AS nombre_produits
            FROM expression_besoin_investissement ebi
            WHERE ebi.idDirection = ?
            ORDER BY ebi.date_creation DESC, ebi.id DESC
        ");
        $stmt->execute([$sessionIdDirection]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['id']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows, 'nombre_total' => count($rows)]);
    } catch (\Throwable $e) {
        error_log('[ChefDirEB][listerExpressionsBesoinInvestissement] ' . $e->getMessage());
        erreurSqlChefDirEB('Impossible de charger la liste des expressions de besoin investissement.');
    }
}

/**
 * OPTION 8 — Détail d'une expression de besoin investissement (en-tête +
 * produits), pour poursuivre un brouillon ou consulter. Filtrée par
 * idDirection, pas par idUtilisateur.
 */
function detailExpressionBesoinInvestissement(PDO $bdBASI, chefDirectionEBController $basiController, int $sessionIdDirection): void {
    try {
        $token = trim((string) inputValueChefDirEB('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idEBI = (int) $basiController->tokendecrypt($token);
        if ($idEBI <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmt = $bdBASI->prepare("
            SELECT id, nom_expression, idDirection, date_creation, idStatut, dateEnregistrement
            FROM expression_besoin_investissement
            WHERE id = ? AND idDirection = ?
            LIMIT 1
        ");
        $stmt->execute([$idEBI, $sessionIdDirection]);
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
        error_log('[ChefDirEB][detailExpressionBesoinInvestissement] ' . $e->getMessage());
        erreurSqlChefDirEB("Impossible de charger le détail de l'expression de besoin.");
    }
}

/**
 * OPTION 9 — Crée (si pas de token) ou met à jour (si token fourni) une
 * expression de besoin investissement. Action 'poursuivre' → reste en
 * Brouillon (1). Action 'soumettre' → passe en Soumise (2), en attente de
 * sortie par le comptable — AUCUNE sortie de stock ici : une fois les
 * produits en stock, c'est le comptable qui en a la garde et qui effectue
 * la sortie réelle (page "Sorties de produits"), même principe que le
 * Fonctionnement.
 *
 * Champs attendus : token (optionnel), action ('poursuivre'|'soumettre'),
 * produits : [{ idP, quantite }, ...] — liste COMPLÈTE voulue.
 */
function enregistrerExpressionBesoinInvestissement(PDO $bdBASI, chefDirectionEBController $basiController, int $sessionUserId, string $sessionMatricule, int $sessionIdDirection): void {
    try {
        $token  = trim((string) inputValueChefDirEB('token', ''));
        $action = trim((string) inputValueChefDirEB('action', 'poursuivre'));
        $produitsEnvoyes = inputValueChefDirEB('produits', []);
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

        // ── Vérification du quota (sécurité) ────────────────────────────
        $idEBIPourExclusion = ($token !== '') ? (int) $basiController->tokendecrypt($token) : null;
        foreach ($produitsValides as $idP => $quantiteDemandee) {
            $quotaDisponible = calculerQuotaDirectionInvest($bdBASI, $sessionIdDirection, $idP, $idEBIPourExclusion);
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
            ")->execute([$nomExpression, $sessionIdDirection, $sessionUserId, $idStatut, $dateEnregistrement]);
            $idEBI = (int) $bdBASI->lastInsertId();

            insererHistoriqueEBI($bdBASI, $idEBI, $nomExpression, $sessionIdDirection, $sessionUserId, $idStatut,
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
            $stmtC->execute([$idEBI, $sessionIdDirection]);
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

        // ── Réconciliation des produits ──────────────────────────────────
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

        // ── Soumission : ne fait QUE passer la demande en Soumise (2) —
        // aucune sortie de stock ici. C'est le comptable qui, une fois les
        // produits en stock sous sa garde, effectue la sortie réelle sur la
        // page "Sorties de produits" (même principe que le Fonctionnement).
        if ($action === 'soumettre') {
            $bdBASI->prepare("UPDATE expression_besoin_investissement SET idStatut = 2 WHERE id = ?")->execute([$idEBI]);
            insererHistoriqueEBI($bdBASI, $idEBI, $nomExpression, $sessionIdDirection, $sessionUserId, 2,
                "Soumission de l'expression de besoin (par $sessionMatricule)", $dateEnregistrement);
        }

        $bdBASI->commit();

        echo json_encode([
            'status'  => 'success',
            'message' => ($action === 'soumettre')
                ? 'Expression de besoin soumise avec succès : en attente de sortie par le comptable.'
                : 'Brouillon enregistré avec succès.',
            'tmp' => $basiController->tokenencrypt($idEBI),
        ]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[ChefDirEB][enregistrerExpressionBesoinInvestissement] ' . $e->getMessage());
        erreurSqlChefDirEB("Impossible d'enregistrer l'expression de besoin.");
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

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Catalogue des produits d'investissement (page
   listeProduitsInvestissement.php, chef de service)
   Équivalent Investissement de la gestion des produits Fonctionnement
   (comptableController.php, cases 10/11/13/14). Différences imposées :
     - id_Sous_categorie = NULL, id_sous_rubrique renseignée à la place ;
     - id_type_product = 2 ;
     - idUtilisateur trace le créateur — un chef de service ne modifie
       QUE ses propres produits ;
     - le Stock_actuel n'est visible, par produit, que pour son créateur
       (masqué pour les produits des autres) ;
     - le doublon est vérifié sur le NOM SEUL (globalement), pas par
       sous-rubrique — cohérent avec la recherche-suggestion à la saisie.
   Pas de désactivation/réactivation ici (non demandée pour ce module).
═══════════════════════════════════════════════════════════════════════════ */

/** OPTION 10 — Liste des rubriques actives, pour le select du formulaire. */
function listerRubriquesPourProduit(PDO $bdBASI): void {
    try {
        $stmt = $bdBASI->query("SELECT id, nom_rubrique FROM rubrique WHERE statut = 1 ORDER BY nom_rubrique ASC");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Throwable $e) {
        error_log('[ChefDirEB][listerRubriquesPourProduit] ' . $e->getMessage());
        erreurSqlChefDirEB('Impossible de charger la liste des rubriques.');
    }
}

/** OPTION 11 — Sous-rubriques actives d'une rubrique donnée. */
function listerSousRubriquesPourProduit(PDO $bdBASI): void {
    try {
        $idRubrique = (int) inputValueChefDirEB('idRubrique', 0);
        if ($idRubrique <= 0) { echo json_encode(['status' => 'error', 'message' => 'Rubrique manquante.']); return; }

        $stmt = $bdBASI->prepare("
            SELECT id, nom_sous_rubrique
            FROM sousRubrique
            WHERE rubrique_id = ? AND statut = 1
            ORDER BY nom_sous_rubrique ASC
        ");
        $stmt->execute([$idRubrique]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Throwable $e) {
        error_log('[ChefDirEB][listerSousRubriquesPourProduit] ' . $e->getMessage());
        erreurSqlChefDirEB('Impossible de charger la liste des sous-rubriques.');
    }
}

/**
 * OPTION 12 — Recherche-suggestion à la saisie : produits Investissement
 * dont le nom COMMENCE par le texte saisi (insensible à la casse/accents
 * n'est pas géré ici — LIKE simple, suffisant pour une saisie progressive).
 * Utilisée pour proposer les produits déjà existants avant d'en créer un
 * nouveau, et éviter les doublons.
 */
function rechercherProduitsInvestissement(PDO $bdBASI): void {
    try {
        $texte = trim((string) inputValueChefDirEB('texte', ''));
        if (mb_strlen($texte) < 2) { echo json_encode(['status' => 'success', 'data' => []]); return; }

        // Catalogue global : un produit ne peut exister qu'une seule fois,
        // tous créateurs confondus.
        $stmt = $bdBASI->prepare("
            SELECT p.idP, p.nomproduit, p.id_sous_rubrique AS idSousRubrique, sr.nom_sous_rubrique, r.nom_rubrique
            FROM product p
            LEFT JOIN sousRubrique sr ON p.id_sous_rubrique = sr.id
            LEFT JOIN rubrique r ON sr.rubrique_id = r.id
            WHERE p.id_type_product = 2 AND p.nomproduit LIKE CONCAT(?, '%')
            ORDER BY p.nomproduit ASC
            LIMIT 10
        ");
        $stmt->execute([$texte]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Throwable $e) {
        error_log('[ChefDirEB][rechercherProduitsInvestissement] ' . $e->getMessage());
        erreurSqlChefDirEB('Impossible de rechercher des produits.');
    }
}

/**
 * OPTION 13 — Catalogue COMPLET des produits Investissement (tous les chefs
 * de service voient tous les produits, comme à l'origine). Deux choses
 * varient selon le chef de service connecté :
 *   - Stock + bouton "Détail" : visibles uniquement si SA direction a déjà
 *     reçu du stock de ce produit (EXISTS dans livraison_produit_repartition,
 *     mode='stock') — sinon le stock est masqué (null) et le détail inutile.
 *   - Modifier / Activer / Désactiver : réservés au créateur du produit
 *     (idUtilisateur = utilisateur connecté), comme à l'origine.
 */
function listerCatalogueProduitsInvestissement(PDO $bdBASI, int $sessionUserId, int $sessionIdDirection): void {
    try {
        $stmt = $bdBASI->prepare("
            SELECT p.idP, p.nomproduit, p.code_produit, p.Seuil_limite,
                   p.id_statut, p.date_creation, p.idUtilisateur,
                   r.nom_rubrique, sr.nom_sous_rubrique,
                   CONCAT(u.prenom, ' ', u.nom) AS nom_createur
            FROM product p
            LEFT JOIN sousRubrique sr ON p.id_sous_rubrique = sr.id
            LEFT JOIN rubrique r ON sr.rubrique_id = r.id
            LEFT JOIN utilisateurs u ON p.idUtilisateur = u.id
            WHERE p.id_type_product = 2
            ORDER BY p.nomproduit ASC
        ");
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtRecu = $bdBASI->prepare("
            SELECT COALESCE(SUM(lpr.quantite), 0)
            FROM livraison_produit_repartition lpr
            JOIN livraison_produit lp ON lpr.idLP = lp.id
            WHERE lpr.mode = 'stock' AND lp.idP = ? AND lpr.idDirection = ?
        ");
        $stmtSorti = $bdBASI->prepare("
            SELECT COALESCE(SUM(ebip.quantite_sortie), 0)
            FROM expression_besoin_investissement_produit ebip
            JOIN expression_besoin_investissement ebi ON ebip.idEBI = ebi.id
            WHERE ebip.id_produit = ? AND ebip.statut = 1 AND ebi.idDirection = ?
        ");
        $stmtReserve = $bdBASI->prepare("
            SELECT COALESCE(SUM(ebip.quantite_demandee - ebip.quantite_sortie), 0)
            FROM expression_besoin_investissement_produit ebip
            JOIN expression_besoin_investissement ebi ON ebip.idEBI = ebi.id
            WHERE ebip.id_produit = ? AND ebip.statut = 1 AND ebi.idDirection = ? AND ebi.idStatut IN (1, 2, 3)
        ");

        foreach ($produits as &$p) {
            $p['modifiable'] = ((int) $p['idUtilisateur'] === $sessionUserId); // créateur uniquement
            $idP = (int) $p['idP'];

            $stmtRecu->execute([$idP, $sessionIdDirection]);
            $recu = (float) $stmtRecu->fetchColumn();

            if ($recu <= 0.001) {
                // Sa direction n'a jamais rien reçu de ce produit : stock,
                // retrait et détail n'ont pas de sens pour elle — p.retrait
                // est un compteur GLOBAL (toutes directions confondues), donc
                // masqué pour la même raison que Stock_actuel.
                $p['Stock_actuel'] = null;
                $p['retrait'] = null;
                $p['peut_voir_detail'] = false;
                continue;
            }

            $stmtSorti->execute([$idP, $sessionIdDirection]);
            $sorti = (float) $stmtSorti->fetchColumn();
            $stmtReserve->execute([$idP, $sessionIdDirection]);
            $reserve = (float) $stmtReserve->fetchColumn();

            $p['Stock_actuel'] = max(0.0, $recu - $sorti - $reserve); // disponible pour SA direction
            $p['retrait'] = $sorti; // "retrait" affiché = quantité sortie pour SA direction, pas le compteur global
            $p['peut_voir_detail'] = true;
        }
        unset($p);

        echo json_encode(['status' => 'success', 'data' => $produits, 'nombre_total' => count($produits)]);
    } catch (\Throwable $e) {
        error_log('[ChefDirEB][listerCatalogueProduitsInvestissement] ' . $e->getMessage());
        erreurSqlChefDirEB('Impossible de charger le catalogue des produits.');
    }
}

/**
 * OPTION 16 — Active/désactive un produit Investissement — réservé à son
 * créateur, même règle que la modification.
 */
function toggleStatutProduitInvestissement(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        $idP = (int) inputValueChefDirEB('idP', 0);
        if ($idP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Produit manquant.']); return; }

        $stmtC = $bdBASI->prepare("SELECT * FROM product WHERE idP = ? AND id_type_product = 2 LIMIT 1");
        $stmtC->execute([$idP]);
        $produit = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$produit) { echo json_encode(['status' => 'error', 'message' => 'Produit introuvable.']); return; }

        if ((int) $produit['idUtilisateur'] !== $sessionUserId) {
            echo json_encode(['status' => 'error', 'message' => "Vous ne pouvez modifier que les produits que vous avez vous-même créés."]);
            return;
        }

        $nouveauStatut = ((int) $produit['id_statut'] === 1) ? 0 : 1;

        $bdBASI->beginTransaction();
        $bdBASI->prepare("UPDATE product SET id_statut = ? WHERE idP = ?")->execute([$nouveauStatut, $idP]);
        $bdBASI->prepare("
            INSERT INTO historique_product
                (product_id, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, motif, dateEnregistrement)
            VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, 2, ?, NOW())
        ")->execute([
            $idP, $produit['nomproduit'], $produit['code_produit'], (int) $produit['Stock_actuel'], (int) $produit['Seuil_limite'],
            (int) $produit['Total'], (int) $produit['retrait'], $nouveauStatut, $produit['date_creation'],
            $nouveauStatut === 1 ? "Réactivation par $sessionMatricule" : "Désactivation par $sessionMatricule",
        ]);
        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => $nouveauStatut === 1 ? 'Produit réactivé avec succès.' : 'Produit désactivé avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[ChefDirEB][toggleStatutProduitInvestissement] ' . $e->getMessage());
        erreurSqlChefDirEB('Impossible de modifier le statut du produit.');
    }
}

/**
 * OPTION 17 — Statistiques Investissement : total du catalogue GLOBAL (tous
 * créateurs confondus, comme la liste elle-même), et "Produits avec stock"/
 * "Stock total" qui portent sur TOUS les produits que SA DIRECTION a déjà
 * reçus (via livraison_produit_repartition) — pas sur ce que l'utilisateur
 * connecté a personnellement créé. Ainsi, un chef de service qui en
 * remplace un autre à la tête de la même direction voit exactement les
 * mêmes chiffres que son prédécesseur, quel que soit le créateur d'origine
 * de chaque produit.
 */
function statistiquesProduitsInvestissementChefService(PDO $bdBASI, int $sessionUserId, int $sessionIdDirection): void {
    try {
        $stmtGlobal = $bdBASI->query("
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN id_statut = 1 THEN 1 ELSE 0 END) AS en_service,
                   SUM(CASE WHEN id_statut = 0 THEN 1 ELSE 0 END) AS hors_service
            FROM product
            WHERE id_type_product = 2
        ");
        $global = $stmtGlobal->fetch(PDO::FETCH_ASSOC);

        // Produits que SA DIRECTION a déjà reçus au moins une fois — même
        // critère que celui qui démasque le stock dans le tableau du catalogue.
        $stmtRecus = $bdBASI->prepare("
            SELECT DISTINCT lp.idP
            FROM livraison_produit_repartition lpr
            JOIN livraison_produit lp ON lpr.idLP = lp.id
            JOIN product p ON p.idP = lp.idP AND p.id_type_product = 2
            WHERE lpr.mode = 'stock' AND lpr.idDirection = ?
        ");
        $stmtRecus->execute([$sessionIdDirection]);
        $produitsRecus = $stmtRecus->fetchAll(PDO::FETCH_COLUMN);

        $stockTotalDirection = 0.0;
        if ($produitsRecus) {
            $stmtDispo = $bdBASI->prepare("
                SELECT
                    (SELECT COALESCE(SUM(lpr.quantite), 0) FROM livraison_produit_repartition lpr JOIN livraison_produit lp ON lpr.idLP = lp.id WHERE lpr.mode = 'stock' AND lp.idP = ? AND lpr.idDirection = ?)
                  - (SELECT COALESCE(SUM(ebip.quantite_sortie), 0) FROM expression_besoin_investissement_produit ebip JOIN expression_besoin_investissement ebi ON ebip.idEBI = ebi.id WHERE ebip.id_produit = ? AND ebip.statut = 1 AND ebi.idDirection = ?)
                  - (SELECT COALESCE(SUM(ebip.quantite_demandee - ebip.quantite_sortie), 0) FROM expression_besoin_investissement_produit ebip JOIN expression_besoin_investissement ebi ON ebip.idEBI = ebi.id WHERE ebip.id_produit = ? AND ebip.statut = 1 AND ebi.idDirection = ? AND ebi.idStatut IN (1, 2, 3))
            ");
            foreach ($produitsRecus as $idP) {
                $stmtDispo->execute([$idP, $sessionIdDirection, $idP, $sessionIdDirection, $idP, $sessionIdDirection]);
                $stockTotalDirection += max(0.0, (float) $stmtDispo->fetchColumn());
            }
        }

        echo json_encode([
            'status' => 'success',
            'stats'  => [
                'total_produits'  => (int) $global['total'],
                'en_service'      => (int) $global['en_service'],
                'hors_service'    => (int) $global['hors_service'],
                'mes_produits'    => count($produitsRecus),
                'mon_stock_total' => $stockTotalDirection,
            ],
        ]);
    } catch (\Throwable $e) {
        error_log('[ChefDirEB][statistiquesProduitsInvestissementChefService] ' . $e->getMessage());
        erreurSqlChefDirEB('Impossible de charger les statistiques.');
    }
}

/**
 * OPTION 18 — Détail de la répartition par direction d'UN produit
 * Investissement (bouton "Détail") — miroir exact de
 * detailRepartitionProduitInvestissement dans caisseController.php, pour
 * que le créateur du produit ait la même visibilité que le comptable sur
 * où en est son stock.
 */
function detailRepartitionProduitInvestissementChefService(PDO $bdBASI, int $sessionIdDirection): void {
    try {
        $idP = (int) inputValueChefDirEB('idP', 0);
        if ($idP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Produit manquant.']); return; }

        $stmtProduit = $bdBASI->prepare("SELECT nomproduit, Stock_actuel FROM product WHERE idP = ? AND id_type_product = 2 LIMIT 1");
        $stmtProduit->execute([$idP]);
        $produit = $stmtProduit->fetch(PDO::FETCH_ASSOC);
        if (!$produit) { echo json_encode(['status' => 'error', 'message' => 'Produit introuvable.']); return; }

        // Le détail n'a de sens que si SA direction a déjà reçu du stock de
        // ce produit — sinon rien à montrer qui la concerne.
        $stmtExiste = $bdBASI->prepare("
            SELECT 1 FROM livraison_produit_repartition lpr
            JOIN livraison_produit lp ON lpr.idLP = lp.id
            WHERE lpr.mode = 'stock' AND lp.idP = ? AND lpr.idDirection = ?
            LIMIT 1
        ");
        $stmtExiste->execute([$idP, $sessionIdDirection]);
        if (!$stmtExiste->fetch()) {
            echo json_encode(['status' => 'error', 'message' => "Votre direction n'a jamais reçu de stock de ce produit."]);
            return;
        }

        $stmtRecu = $bdBASI->prepare("
            SELECT COALESCE(SUM(lpr.quantite), 0)
            FROM livraison_produit_repartition lpr
            JOIN livraison_produit lp ON lpr.idLP = lp.id
            WHERE lpr.mode = 'stock' AND lp.idP = ? AND lpr.idDirection = ?
        ");
        $stmtRecu->execute([$idP, $sessionIdDirection]);
        $recu = (float) $stmtRecu->fetchColumn();

        $stmtSorti = $bdBASI->prepare("
            SELECT COALESCE(SUM(ebip.quantite_sortie), 0)
            FROM expression_besoin_investissement_produit ebip
            JOIN expression_besoin_investissement ebi ON ebip.idEBI = ebi.id
            WHERE ebip.id_produit = ? AND ebip.statut = 1 AND ebi.idDirection = ?
        ");
        $stmtSorti->execute([$idP, $sessionIdDirection]);
        $sorti = (float) $stmtSorti->fetchColumn();

        $stmtReserve = $bdBASI->prepare("
            SELECT COALESCE(SUM(ebip.quantite_demandee - ebip.quantite_sortie), 0)
            FROM expression_besoin_investissement_produit ebip
            JOIN expression_besoin_investissement ebi ON ebip.idEBI = ebi.id
            WHERE ebip.id_produit = ? AND ebip.statut = 1 AND ebi.idDirection = ? AND ebi.idStatut IN (1, 2, 3)
        ");
        $stmtReserve->execute([$idP, $sessionIdDirection]);
        $reserve = (float) $stmtReserve->fetchColumn();

        $stmtDirection = $bdBASI->prepare("SELECT nom_direction, code_direction FROM direction WHERE id = ?");
        $stmtDirection->execute([$sessionIdDirection]);
        $direction = $stmtDirection->fetch(PDO::FETCH_ASSOC);

        $repartition = [[
            'idDirection'         => $sessionIdDirection,
            'nom_direction'       => $direction['nom_direction'] ?? null,
            'code_direction'      => $direction['code_direction'] ?? null,
            'quantite_recue'      => $recu,
            'quantite_sortie'     => $sorti,
            'quantite_reservee'   => $reserve,
            'quantite_disponible' => max(0.0, $recu - $sorti - $reserve),
        ]];

        echo json_encode([
            'status'      => 'success',
            'produit'     => ['idP' => $idP, 'nomproduit' => $produit['nomproduit'], 'stock_actuel' => (float) $produit['Stock_actuel']],
            'repartition' => $repartition,
        ]);
    } catch (\Throwable $e) {
        error_log('[ChefDirEB][detailRepartitionProduitInvestissementChefService] ' . $e->getMessage());
        erreurSqlChefDirEB('Impossible de charger la répartition de ce produit.');
    }
}

/**
 * OPTION 14 — Création d'un produit Investissement. Doublon vérifié sur le
 * nom SEUL (globalement, tous créateurs confondus) — un produit ne peut
 * jamais être ajouté deux fois, quelle que soit la sous-rubrique.
 */
function creerProduitInvestissement(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        $nom = trim((string) inputValueChefDirEB('nom', ''));
        $idSousRubrique = (int) inputValueChefDirEB('idSousRubrique', 0);
        $seuil = (int) inputValueChefDirEB('seuil', 0);

        if ($nom === '') { echo json_encode(['status' => 'error', 'message' => 'Le nom du produit est requis.']); return; }
        if ($idSousRubrique <= 0) { echo json_encode(['status' => 'error', 'message' => 'La sous-rubrique est requise.']); return; }

        $stmtCheck = $bdBASI->prepare("SELECT COUNT(*) FROM product WHERE nomproduit = ? AND id_type_product = 2");
        $stmtCheck->execute([$nom]);
        if ((int) $stmtCheck->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => "Ce produit existe déjà — utilisez la recherche pour le retrouver plutôt que d'en créer un doublon."]);
            return;
        }

        $dateCreation = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();
        $bdBASI->prepare("
            INSERT INTO product (nomproduit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, id_sous_rubrique, retrait, id_statut, id_type_product, idUtilisateur, date_creation)
            VALUES (?, 0, ?, 0, NULL, ?, 0, 1, 2, ?, ?)
        ")->execute([$nom, $seuil, $idSousRubrique, $sessionUserId, $dateCreation]);
        $newId = (int) $bdBASI->lastInsertId();

        $codeProduit = 'INV-' . str_pad((string) $newId, 6, '0', STR_PAD_LEFT);
        $bdBASI->prepare("UPDATE product SET code_produit = ? WHERE idP = ?")->execute([$codeProduit, $newId]);

        $bdBASI->prepare("
            INSERT INTO historique_product
                (product_id, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, motif, dateEnregistrement)
            VALUES (?, ?, ?, 0, ?, 0, NULL, 0, 1, ?, 2, ?, NOW())
        ")->execute([$newId, $nom, $codeProduit, $seuil, $dateCreation, "Création par $sessionMatricule"]);

        $bdBASI->commit();
        echo json_encode(['status' => 'success', 'message' => 'Produit créé avec succès.', 'idP' => $newId]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[ChefDirEB][creerProduitInvestissement] ' . $e->getMessage());
        erreurSqlChefDirEB('Impossible de créer le produit.');
    }
}

/**
 * OPTION 15 — Modification d'un produit Investissement — réservée à son
 * créateur (idUtilisateur = utilisateur connecté). Seuls nom, sous-rubrique
 * et seuil d'alerte sont modifiables (stock/total/retrait intouchés ici,
 * mêmes règles que le module Fonctionnement).
 */
function modifierProduitInvestissement(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        $idP = (int) inputValueChefDirEB('idP', 0);
        $nom = trim((string) inputValueChefDirEB('nom', ''));
        $idSousRubrique = (int) inputValueChefDirEB('idSousRubrique', 0);
        $seuil = (int) inputValueChefDirEB('seuil', 0);

        if ($idP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Produit manquant.']); return; }
        if ($nom === '') { echo json_encode(['status' => 'error', 'message' => 'Le nom du produit est requis.']); return; }
        if ($idSousRubrique <= 0) { echo json_encode(['status' => 'error', 'message' => 'La sous-rubrique est requise.']); return; }

        $stmtC = $bdBASI->prepare("SELECT * FROM product WHERE idP = ? AND id_type_product = 2 LIMIT 1");
        $stmtC->execute([$idP]);
        $produit = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$produit) { echo json_encode(['status' => 'error', 'message' => 'Produit introuvable.']); return; }

        if ((int) $produit['idUtilisateur'] !== $sessionUserId) {
            echo json_encode(['status' => 'error', 'message' => "Vous ne pouvez modifier que les produits que vous avez vous-même créés."]);
            return;
        }

        $stmtDup = $bdBASI->prepare("SELECT COUNT(*) FROM product WHERE nomproduit = ? AND id_type_product = 2 AND idP != ?");
        $stmtDup->execute([$nom, $idP]);
        if ((int) $stmtDup->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Un autre produit porte déjà ce nom.']);
            return;
        }

        $bdBASI->beginTransaction();
        $bdBASI->prepare("UPDATE product SET nomproduit = ?, id_sous_rubrique = ?, Seuil_limite = ? WHERE idP = ?")
            ->execute([$nom, $idSousRubrique, $seuil, $idP]);

        $bdBASI->prepare("
            INSERT INTO historique_product
                (product_id, nomproduit, code_produit, Stock_actuel, Seuil_limite, Total, id_Sous_categorie, retrait, id_statut, date_creation, id_type_product, motif, dateEnregistrement)
            VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, 2, ?, NOW())
        ")->execute([
            $idP, $nom, $produit['code_produit'], (int) $produit['Stock_actuel'], $seuil, (int) $produit['Total'],
            (int) $produit['retrait'], (int) $produit['id_statut'], $produit['date_creation'], "Modification par $sessionMatricule",
        ]);

        $bdBASI->commit();
        echo json_encode(['status' => 'success', 'message' => 'Produit modifié avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[ChefDirEB][modifierProduitInvestissement] ' . $e->getMessage());
        erreurSqlChefDirEB('Impossible de modifier le produit.');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   ROUTAGE
   1 = listerExpressionsBesoinDirection (idDirection = tmpIdDirection, statuts 2/3/4/5)
   2 = detailExpressionBesoinDirection  (en-tête + produits, pour validation/consultation)
   3 = validerExpressionBesoin          (idStatut 2 → 3, quantite_reelle par ligne)
   4 = rejeterExpressionBesoin          (idStatut 2 → 4, motif obligatoire + e-mail)
   5 = detailSortiesExpressionBesoin    (idStatut 5, informations détaillées sur les sorties)

   ── Produits d'investissement (listeProduitInvestissement.php) ──
   6 = listerProduitsInvestissement            (stock direct, quota > 0)
   7 = listerExpressionsBesoinInvestissement
   8 = detailExpressionBesoinInvestissement
   9 = enregistrerExpressionBesoinInvestissement (brouillon OU terminé → sortie immédiate)
═══════════════════════════════════════════════════════════════════════════ */
try {
    switch ($option) {
        case 1:
            listerExpressionsBesoinDirection($bdBASI, $basiController, $sessionIdDirection);
            break;

        case 2:
            detailExpressionBesoinDirection($bdBASI, $basiController, $sessionIdDirection);
            break;

        case 3:
            validerExpressionBesoin($bdBASI, $basiController, $sessionUserId, $sessionMatricule, $sessionIdDirection);
            break;

        case 4:
            rejeterExpressionBesoin($bdBASI, $basiController, $sessionMatricule, $sessionIdDirection);
            break;

        case 5:
            detailSortiesExpressionBesoin($bdBASI, $basiController, $sessionIdDirection);
            break;

        case 6:
            listerProduitsInvestissement($bdBASI, $sessionIdDirection);
            break;

        case 7:
            listerExpressionsBesoinInvestissement($bdBASI, $basiController, $sessionIdDirection);
            break;

        case 8:
            detailExpressionBesoinInvestissement($bdBASI, $basiController, $sessionIdDirection);
            break;

        case 9:
            enregistrerExpressionBesoinInvestissement($bdBASI, $basiController, $sessionUserId, $sessionMatricule, $sessionIdDirection);
            break;

        case 10:
            listerRubriquesPourProduit($bdBASI);
            break;

        case 11:
            listerSousRubriquesPourProduit($bdBASI);
            break;

        case 12:
            rechercherProduitsInvestissement($bdBASI);
            break;

        case 13:
            listerCatalogueProduitsInvestissement($bdBASI, $sessionUserId, $sessionIdDirection);
            break;

        case 14:
            creerProduitInvestissement($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 15:
            modifierProduitInvestissement($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 16:
            toggleStatutProduitInvestissement($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 17:
            statistiquesProduitsInvestissementChefService($bdBASI, $sessionUserId, $sessionIdDirection);
            break;

        case 18:
            detailRepartitionProduitInvestissementChefService($bdBASI, $sessionIdDirection);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Option '$option' non reconnue."]);
            exit;
    }
} catch (\Throwable $e) {
    error_log('[ChefDirEB][Routage] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Une erreur inattendue est survenue.']);
    exit;
}