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

        // Statistiques par statut (Soumise/Validée/Rejetée/Terminée), sur le
        // même périmètre direction + intervalle d'années, INDÉPENDANTES du
        // filtre de statut appliqué à la liste elle-même.
        $stmtStats = $bdBASI->prepare("
            SELECT idStatut, COUNT(*) AS n
            FROM expression_besoin
            WHERE idDirection = ? AND idStatut IN (2, 3, 4, 5, 6) AND YEAR(date_creation) BETWEEN ? AND ?
            GROUP BY idStatut
        ");
        $stmtStats->execute([$sessionIdDirection, $anneeDebut, $anneeFin]);
        $stats = ['2' => 0, '3' => 0, '4' => 0, '5' => 0, '6' => 0];
        foreach ($stmtStats->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cle = (string)(int)$row['idStatut'];
            if (isset($stats[$cle])) $stats[$cle] = (int)$row['n'];
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
            $sql .= " AND eb.idStatut IN (2, 3, 4, 5, 6)";
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

        // ── Produits : demandée / validée / sortie / restante / statut ligne
        // (même logique que le "Voir" côté personnel — expressionBesoinController.php).
        $stmtProduits = $bdBASI->prepare("
            SELECT ebp.id AS idEBP, ebp.idP, ebp.quantite, ebp.quantite_reelle, ebp.quantite_sortie,
                   p.nomproduit as designation
            FROM expression_besoin_produit ebp
            JOIN product p ON ebp.idP = p.idP
            WHERE ebp.idEB = ? AND ebp.statut = 1
            ORDER BY ebp.id ASC
        ");
        $stmtProduits->execute([$idEB]);
        $produits = $stmtProduits->fetchAll(PDO::FETCH_ASSOC);

        foreach ($produits as &$p) {
            $qteReelle = $p['quantite_reelle'] !== null ? (float) $p['quantite_reelle'] : null;
            $qteSortie = (float) ($p['quantite_sortie'] ?? 0);

            if ($qteReelle === null) {
                $p['quantite_restante'] = null;
                $p['statut_ligne'] = 'En attente';
            } else {
                $p['quantite_restante'] = max(0, $qteReelle - $qteSortie);
                if ($qteReelle > 0 && $qteSortie >= $qteReelle - 0.001) {
                    $p['statut_ligne'] = 'Livré';
                } elseif ($qteSortie > 0) {
                    $p['statut_ligne'] = 'Partiellement livré';
                } else {
                    $p['statut_ligne'] = 'En attente';
                }
            }
        }
        unset($p);
        $expression['produits'] = $produits;

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

        // Détail événement par événement (chaque sortie effectuée), à partir
        // de l'historique — motif préfixé "Sortie de stock" lors de chaque
        // sortie effective (cf. caisseController.php::effectuerSortieExpressionBesoin).
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

        // Regroupe l'historique par ligne, et calcule la quantité sortie à
        // CHAQUE événement (delta entre deux instantanés successifs — chaque
        // ligne d'historique stockant la valeur cumulative quantite_sortie
        // au moment de l'action).
        $dernierParLigne = [];
        foreach ($lignes as &$l) {
            $l['sorties'] = [];
        }
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
        error_log('[ChefDirEB][detailSortiesExpressionBesoin] ' . $e->getMessage());
        erreurSqlChefDirEB("Impossible de charger les informations sur les sorties.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   ROUTAGE
   1 = listerExpressionsBesoinDirection (idDirection = tmpIdDirection, statuts 2/3/4/5)
   2 = detailExpressionBesoinDirection  (en-tête + produits, pour validation/consultation)
   3 = validerExpressionBesoin          (idStatut 2 → 3, quantite_reelle par ligne)
   4 = rejeterExpressionBesoin          (idStatut 2 → 4, motif obligatoire + e-mail)
   5 = detailSortiesExpressionBesoin    (idStatut 5, informations détaillées sur les sorties)
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