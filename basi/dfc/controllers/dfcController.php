<?php
ob_start();
session_start();
include_once('../../../bdBASI.php');
ob_end_clean();

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
date_default_timezone_set('Africa/Dakar');

// ─── PHPMailer ────────────────────────────────────────────────────────────────
require_once('../../../includes/phpMailer/PHPMailer.php');
require_once('../../../includes/phpMailer/SMTP.php');
require_once('../../../includes/phpMailer/Exception.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// ─── Session ──────────────────────────────────────────────────────────────────
if (empty($_SESSION['tmpIdBASI'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'sessionExpired', 'message' => 'Session expirée.']);
    exit;
}
$sessionUserId    = (int)$_SESSION['tmpIdBASI'];
$sessionMatricule = trim($_SESSION['tmpMatricule'] ?? '');

// ─── Classe contrôleur (tokenencrypt/decrypt définis UNE SEULE FOIS ici) ──────
class dfcController extends BDBASI
{
    public function tokenencrypt($data): string {
        $key = hash('sha256', 'U@hbENTDRI@TCRI@T2022');
        $iv  = substr(hash('sha256', 'www.ent.uahb.sn'), 0, 16);
        return base64_encode(openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv));
    }

    public function tokendecrypt($data) {
        $key = hash('sha256', 'U@hbENTDRI@TCRI@T2022');
        $iv  = substr(hash('sha256', 'www.ent.uahb.sn'), 0, 16);
        return openssl_decrypt(base64_decode($data), 'AES-256-CBC', $key, 0, $iv);
    }

    public function fctRetirerAccents($s) {
        $search  = ['À','Á','Â','Ã','Ä','Å','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ò','Ó','Ô','Õ','Ö','Ù','Ú','Û','Ü','Ý','à','á','â','ã','ä','å','ç','è','é','ê','ë','ì','í','î','ï','ð','ò','ó','ô','õ','ö','ù','ú','û','ü','ý','ÿ'];
        $replace = ['A','A','A','A','A','A','C','E','E','E','E','I','I','I','I','O','O','O','O','O','U','U','U','U','Y','a','a','a','a','a','a','c','e','e','e','e','i','i','i','i','o','o','o','o','o','o','u','u','u','u','y','y'];
        return str_replace($search, $replace, $s);
    }

    public function numberFormat($n, $t1, $t2, $t3) {
        return ($n != null && $n != '') ? number_format($n, 0, ',', ' ') : $n;
    }
}

// ─── Connexion DB (PDO) ───────────────────────────────────────────────────────
try {
    $BDBASI        = new BDBASI();
    $bdBASI        = $BDBASI->connect();
    $dfcController = new dfcController();
} catch (\Throwable $e) {
    error_log('[DFC][Connexion] ' . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Connexion base de données impossible.']);
    exit;
}

if (!$bdBASI) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Connexion base de données impossible.']);
    exit;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

// ─── Insertion historique_Budget ──────────────────────────────────────────────
function insertHistoriqueBudget(PDO $pdo, int $budgetId, string $nouveauStatut, int $nouvelIdStatut, ?string $motif, int $userId): void
{
    $stmt = $pdo->prepare("SELECT * FROM budget WHERE id = ?");
    $stmt->execute([$budgetId]);
    $b = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$b) return;

    $pdo->prepare("
        INSERT INTO historique_budget
            (idBudget, annee, matricule, direction_id, type_budget_id,
             date_creation, statut, idStatut, plafond, motif, idUtilisateur, dateEnregistrement)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
    ")->execute([
        $budgetId,
        $b['annee'],
        $b['matricule'],
        $b['direction_id'],
        $b['type_budget_id'],
        $b['date_creation'],
        $nouveauStatut,
        $nouvelIdStatut,
        $b['plafond'],
        $motif,
        $userId,
    ]);
}


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

// ─── PHPMailer Gmail ──────────────────────────────────────────────────────────
// ⚠️ Adresse à confirmer — utilisée pour les relances DFC → responsable des
// achats (option 22, envoyerRelanceResponsableAchats).
if (!defined('RESPONSABLE_ACHATS_EMAIL_DFC')) {
    define('RESPONSABLE_ACHATS_EMAIL_DFC', 'ndiaya.ndao@uahb.sn');
}

function envoyerMail(string $sujet, string $htmlBody, string $to = 'ndiaya.ndao@uahb.sn'): void
{
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'criat@uahb.sn';
        $mail->Password   = 'bvszyikotvnxemot';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom('criat@uhb.sn', 'ENT UAHB');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>','<br/>','<br />','</p>'], "\n", $htmlBody));
        $mail->send();
    } catch (PHPMailerException $e) {

        error_log("PHPMailer erreur : " . $e->getMessage());
    }
}

// ─── Template email HTML ──────────────────────────────────────────────────────
function buildEmail(string $titre, string $corps, int $budgetId, string $statut, string $couleur): string
{
    return "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><style>
        body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px}
        .wrap{background:#fff;border-radius:8px;padding:24px;max-width:560px;margin:0 auto;box-shadow:0 2px 8px rgba(0,0,0,.1)}
        .title{color:#1a7a5e;font-size:1.1rem;font-weight:700;margin:0 0 1rem}
        .bdg{display:inline-block;padding:4px 14px;border-radius:20px;color:#fff;background:{$couleur};font-weight:700;font-size:.8rem}
        .motif{border-left:4px solid {$couleur};padding:.65rem 1rem;background:#f9fafb;border-radius:0 6px 6px 0;margin:.75rem 0;font-size:.875rem;line-height:1.6}
        .ft{font-size:.72rem;color:#9ca3af;margin-top:20px;border-top:1px solid #e5e7eb;padding-top:12px}
    </style></head><body>
    <div class='wrap'>
        <p class='title'>{$titre}</p>
        <p style='color:#374151;font-size:.875rem'>Bonjour,</p>
        {$corps}
        <p style='margin-top:16px;font-size:.875rem'>Référence : <strong>Budget #{$budgetId}</strong> &nbsp;<span class='bdg'>{$statut}</span></p>
        <p style='color:#374151;font-size:.875rem'>Connectez-vous à l'ENT UAHB pour consulter les détails.</p>
        <div class='ft'>Message automatique — ENT UAHB · CRIAT — Ne pas répondre.</div>
    </div></body></html>";
}

// ─── Filtre années ────────────────────────────────────────────────────────────
function buildAnneeWhere(array $data, string $alias = 'b'): array
{
    $where = []; $params = [];
    if (!empty($data['anneeDebut']) && is_numeric($data['anneeDebut'])) {
        $where[] = "{$alias}.annee >= ?"; $params[] = (int)$data['anneeDebut'];
    }
    if (!empty($data['anneeFin']) && is_numeric($data['anneeFin'])) {
        $where[] = "{$alias}.annee <= ?"; $params[] = (int)$data['anneeFin'];
    }
    return [$where, $params];
}




/**
 * Lit le corps de la requête envoyé en JSON brut (cas des appels fetch()
 * avec Content-Type: application/json). $_POST ne contient rien dans ce cas.
 */
function getJsonBodyDfc(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Récupère un paramètre en cherchant dans l'ordre : le body JSON brut,
 * $_POST, $_GET — fonctionne quel que soit le mode d'envoi utilisé.
 */
function inputValueDfc(string $key, $default = null) {
    static $jsonBody = null;
    if ($jsonBody === null) $jsonBody = getJsonBodyDfc();

    if (array_key_exists($key, $jsonBody)) return $jsonBody[$key];
    if (isset($_POST[$key]))               return $_POST[$key];
    if (isset($_GET[$key]))                return $_GET[$key];
    return $default;
}

function erreurSqlDfc(string $message = "Erreur lors de l'accès à la base de données."): void {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$message]);
}



// ─── Lecture de l'option ──────────────────────────────────────────────────────
$option = isset($_GET['option']) ? trim($_GET['option']) : '';
if ($option==='' && isset($_POST['option'])) $option = trim($_POST['option']);
if ($option==='') {
    $jsonBodyOption = getJsonBodyDfc();
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
 * Liste des dossiers en attente d'avis favorable : idStatut = 2 (validés
 * par la DGA), toutes provenances confondues (commande ET paiement,
 * distinguées via idTypePAP pour l'affichage).
 */
function listerDossiers(PDO $bdBASI, dfcController $dfcController): void {
    try {
        $stmt = $bdBASI->query("
            SELECT
                p.id AS idPAP,
                p.nom_commande,
                p.montant_total,
                p.dateCreation,
                p.idTypePAP,
                mr.mode_reglement AS mode_reglement_nom,
                mp.mode_paiement  AS mode_paiement_nom,
                CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM passer_achat_et_paiement p
            LEFT JOIN mode_reglement mr ON p.id_mode_reglement = mr.id
            LEFT JOIN mode_paiement  mp ON p.id_mode_paiement  = mp.id
            LEFT JOIN utilisateurs   u  ON p.idUtilisateur     = u.id
            WHERE p.idStatut = 2
            ORDER BY p.dateCreation DESC
        ");
        if (!$stmt) throw new \RuntimeException('Requête de liste des dossiers échouée.');

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['tmp'] = $dfcController->tokenencrypt($r['idPAP']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows]);
    } catch (\Throwable $e) {
        error_log('[DFC][listerDossiers] ' . $e->getMessage());
        erreurSqlDfc('Impossible de charger la liste des dossiers.');
    }
}

/**
 * Détail d'un dossier pour la modale "Détail" :
 *   - idTypePAP = 1 (Passer commande) → facture définitive (documents_pap,
 *     ligne dont choix = 1 = fournisseur retenu par la DGA).
 *   - idTypePAP = 2 (Passer au paiement) → justificatif(s) de paiement
 *     (document_justificatif_paiement, statut = 1).
 *
 * Paramètre : token (chiffré de idPAP)
 */
function detailDossier(PDO $bdBASI, dfcController $dfcController): void {
    try {
        $token = trim((string) inputValueDfc('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $dfcController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmtC = $bdBASI->prepare("
            SELECT id AS idPAP, nom_commande, montant_total, dateCreation, idStatut, idTypePAP
            FROM passer_achat_et_paiement
            WHERE id = ? AND idStatut = 2
            LIMIT 1
        ");
        $stmtC->execute([$idPAP]);
        $dossier = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$dossier) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable ou déjà traité.']);
            return;
        }

        $reponse = ['status' => 'success', 'dossier' => $dossier];

        if ((int)$dossier['idTypePAP'] === 1) {
            // ── Passer commande : pro forma du fournisseur retenu (choix = 1).
            // La facture définitive n'est plus téléversée par la DGA — c'est le
            // pro forma déjà associé (dp.doc) qui sert de pièce justificative,
            // exposé ici sous la même clé 'facture_definitive' pour ne pas
            // casser le front-end existant.
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
        } else {
            // ── Passer au paiement : justificatif(s) de paiement ────────────────
            $stmtJustif = $bdBASI->prepare("
                SELECT id, doc, dateEnregistrement
                FROM document_justificatif_paiement
                WHERE idPAP = ? AND statut = 1
                ORDER BY id ASC
            ");
            $stmtJustif->execute([$idPAP]);
            $reponse['justificatifs'] = $stmtJustif->fetchAll(PDO::FETCH_ASSOC);
        }

        // Lignes de la commande (communes aux deux types)
        $stmtLignes = $bdBASI->prepare("
            SELECT
                papl.id AS idPAPL,
                lb.designation,
                papl.quantite_reelle,
                papl.prix_reel,
                papl.montant_total_ligne,
                papl.id_statut_PAPL
            FROM passer_achat_et_paiement_ligne papl
            JOIN demandes_ligne dal ON papl.idDL = dal.idDL
            JOIN ligneBudget    lb  ON dal.idLB  = lb.id
            WHERE papl.idPAP = ?
            ORDER BY papl.id ASC
        ");
        $stmtLignes->execute([$idPAP]);
        $reponse['lignes'] = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($reponse);
    } catch (\Throwable $e) {
        error_log('[DFC][detailDossier] ' . $e->getMessage());
        erreurSqlDfc('Impossible de charger le détail du dossier.');
    }
}

/**
 * Avis favorable : passer_achat_et_paiement.idStatut de 2 → 3.
 *
 * Paramètre : idPAP
 */
/**
 * Avis favorable : passer_achat_et_paiement.idStatut de 2 → 3.
 *
 * Paramètre : token (chiffré de idPAP, cohérent avec detailDossier)
 */
function avisFavorable(PDO $bdBASI, dfcController $dfcController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueDfc('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $dfcController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmtC = $bdBASI->prepare("SELECT id FROM passer_achat_et_paiement WHERE id = ? AND idStatut = 2 LIMIT 1");
        $stmtC->execute([$idPAP]);
        if (!$stmtC->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable ou déjà traité.']);
            return;
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        $bdBASI->prepare("
            UPDATE passer_achat_et_paiement SET idStatut = 3 WHERE id = ?
        ")->execute([$idPAP]);

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
            "Avis favorable DFC (par $sessionMatricule)",
            $dateEnregistrement, $idPAP,
        ]);

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Avis favorable enregistré avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[DFC][avisFavorable] ' . $e->getMessage());
        erreurSqlDfc("Impossible d'enregistrer l'avis favorable.");
    }
}
/**
 * OPTION 19 — Modifie la quantité d'une ligne ACHAT (idTypePAP = 1) avant
 * l'avis favorable. Recalcule montant_total_ligne, synchronise
 * demandes_ligne (quantite, qte_commandee, qte_restant), puis recalcule le
 * montant_total du dossier (somme des lignes actives).
 *
 * Champs attendus : token (du dossier), idPAPL, quantite (nouvelle valeur).
 */
function modifierQuantiteLigneAchat(PDO $bdBASI, dfcController $dfcController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueDfc('token', ''));
        $idPAPL = (int) inputValueDfc('idPAPL', 0);
        $nouvelleQuantite = (float) inputValueDfc('quantite', -1);

        if ($token === '' || $idPAPL <= 0) { echo json_encode(['status' => 'error', 'message' => 'Paramètres manquants.']); return; }
        if ($nouvelleQuantite <= 0) { echo json_encode(['status' => 'error', 'message' => 'La quantité doit être strictement supérieure à zéro. Pour retirer entièrement cette ligne, utilisez plutôt "Supprimer".']); return; }

        $idPAP = (int) $dfcController->tokendecrypt($token);
        if ($idPAP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmtC = $bdBASI->prepare("SELECT id FROM passer_achat_et_paiement WHERE id = ? AND idStatut = 2 AND idTypePAP = 1 LIMIT 1");
        $stmtC->execute([$idPAP]);
        if (!$stmtC->fetch()) { echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable, déjà traité, ou pas de type Achat.']); return; }

        $stmtL = $bdBASI->prepare("SELECT id, idDL, quantite_reelle, prix_reel, montant_total_ligne, date_ajout, id_unite, nb_unites, pieces_par_unite, modifie_par_dfc FROM passer_achat_et_paiement_ligne WHERE id = ? AND idPAP = ? AND id_statut_PAPL = 1 LIMIT 1");
        $stmtL->execute([$idPAPL, $idPAP]);
        $ligne = $stmtL->fetch(PDO::FETCH_ASSOC);
        if (!$ligne) { echo json_encode(['status' => 'error', 'message' => 'Ligne introuvable ou déjà annulée.']); return; }

        // La DFC ne peut que DIMINUER une ligne, jamais l'augmenter — pour
        // retirer entièrement la ligne, elle doit l'annuler ("Supprimer").
        if ($nouvelleQuantite >= (float) $ligne['quantite_reelle']) {
            echo json_encode(['status' => 'error', 'message' => 'La quantité ne peut être que diminuée par rapport à la valeur actuelle (' . $ligne['quantite_reelle'] . ') — pour l\'augmenter, contactez la responsable des achats.']);
            return;
        }

        $nouveauMontantLigne = $nouvelleQuantite * (float) $ligne['prix_reel'];
        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        // Instantané AVANT modification (valeurs encore en vigueur ici).
        dfc_historiserLignePAP($bdBASI, $ligne, $idPAP, "Quantité modifiée par la DFC (par $sessionMatricule)", $dateEnregistrement);

        $bdBASI->prepare("UPDATE passer_achat_et_paiement_ligne SET quantite_reelle = ?, montant_total_ligne = ?, modifie_par_dfc = 1 WHERE id = ?")
            ->execute([$nouvelleQuantite, $nouveauMontantLigne, $idPAPL]);

        $bdBASI->prepare("UPDATE demandes_ligne SET quantite = ?, qte_commandee = ?, qte_restant = 0 WHERE idDL = ?")
            ->execute([$nouvelleQuantite, $nouvelleQuantite, $ligne['idDL']]);

        dfc_recalculerMontantTotalDossier($bdBASI, $idPAP);

        $bdBASI->commit();
        echo json_encode(['status' => 'success', 'message' => 'Quantité modifiée avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[DFC][modifierQuantiteLigneAchat] ' . $e->getMessage());
        erreurSqlDfc('Impossible de modifier la quantité de cette ligne.');
    }
}

/**
 * OPTION 20 — Modifie le montant d'une ligne PAIEMENT (idTypePAP = 2) avant
 * l'avis favorable. Synchronise demandes_ligne.montant_total, puis
 * recalcule le montant_total du dossier.
 *
 * Champs attendus : token (du dossier), idPAPL, montant (nouvelle valeur).
 */
function modifierMontantLignePaiement(PDO $bdBASI, dfcController $dfcController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueDfc('token', ''));
        $idPAPL = (int) inputValueDfc('idPAPL', 0);
        $nouveauMontant = (float) inputValueDfc('montant', -1);

        if ($token === '' || $idPAPL <= 0) { echo json_encode(['status' => 'error', 'message' => 'Paramètres manquants.']); return; }
        if ($nouveauMontant <= 0) { echo json_encode(['status' => 'error', 'message' => 'Le montant doit être strictement supérieur à zéro. Pour retirer entièrement cette ligne, utilisez plutôt "Supprimer".']); return; }

        $idPAP = (int) $dfcController->tokendecrypt($token);
        if ($idPAP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmtC = $bdBASI->prepare("SELECT id FROM passer_achat_et_paiement WHERE id = ? AND idStatut = 2 AND idTypePAP = 2 LIMIT 1");
        $stmtC->execute([$idPAP]);
        if (!$stmtC->fetch()) { echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable, déjà traité, ou pas de type Paiement.']); return; }

        $stmtL = $bdBASI->prepare("SELECT id, idDL, quantite_reelle, prix_reel, montant_total_ligne, date_ajout, id_unite, nb_unites, pieces_par_unite, modifie_par_dfc FROM passer_achat_et_paiement_ligne WHERE id = ? AND idPAP = ? AND id_statut_PAPL = 1 LIMIT 1");
        $stmtL->execute([$idPAPL, $idPAP]);
        $ligne = $stmtL->fetch(PDO::FETCH_ASSOC);
        if (!$ligne) { echo json_encode(['status' => 'error', 'message' => 'Ligne introuvable ou déjà annulée.']); return; }

        // La DFC ne peut que DIMINUER une ligne, jamais l'augmenter — pour
        // retirer entièrement la ligne, elle doit l'annuler ("Supprimer").
        if ($nouveauMontant >= (float) $ligne['montant_total_ligne']) {
            echo json_encode(['status' => 'error', 'message' => 'Le montant ne peut être que diminué par rapport à la valeur actuelle (' . $ligne['montant_total_ligne'] . ') — pour l\'augmenter, contactez la responsable des achats.']);
            return;
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        // Instantané AVANT modification (valeurs encore en vigueur ici).
        dfc_historiserLignePAP($bdBASI, $ligne, $idPAP, "Montant modifié par la DFC (par $sessionMatricule)", $dateEnregistrement);

        $bdBASI->prepare("UPDATE passer_achat_et_paiement_ligne SET montant_total_ligne = ?, modifie_par_dfc = 1 WHERE id = ?")
            ->execute([$nouveauMontant, $idPAPL]);

        $bdBASI->prepare("UPDATE demandes_ligne SET montant_total = ? WHERE idDL = ?")
            ->execute([$nouveauMontant, $ligne['idDL']]);

        dfc_recalculerMontantTotalDossier($bdBASI, $idPAP);

        $bdBASI->commit();
        echo json_encode(['status' => 'success', 'message' => 'Montant modifié avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[DFC][modifierMontantLignePaiement] ' . $e->getMessage());
        erreurSqlDfc('Impossible de modifier le montant de cette ligne.');
    }
}

/**
 * OPTION 21 — Annule une ligne (Achat ou Paiement) avant l'avis favorable.
 * Passe id_statut_PAPL à 0, remet à zéro la demande correspondante, et
 * recalcule le montant_total du dossier.
 *
 * Champs attendus : token (du dossier), idPAPL.
 */
function annulerLignePAP(PDO $bdBASI, dfcController $dfcController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueDfc('token', ''));
        $idPAPL = (int) inputValueDfc('idPAPL', 0);
        if ($token === '' || $idPAPL <= 0) { echo json_encode(['status' => 'error', 'message' => 'Paramètres manquants.']); return; }

        $idPAP = (int) $dfcController->tokendecrypt($token);
        if ($idPAP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmtC = $bdBASI->prepare("SELECT idTypePAP FROM passer_achat_et_paiement WHERE id = ? AND idStatut = 2 LIMIT 1");
        $stmtC->execute([$idPAP]);
        $dossier = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$dossier) { echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable ou déjà traité.']); return; }

        $stmtL = $bdBASI->prepare("SELECT id, idDL, quantite_reelle, prix_reel, montant_total_ligne, date_ajout, id_unite, nb_unites, pieces_par_unite, modifie_par_dfc FROM passer_achat_et_paiement_ligne WHERE id = ? AND idPAP = ? AND id_statut_PAPL = 1 LIMIT 1");
        $stmtL->execute([$idPAPL, $idPAP]);
        $ligne = $stmtL->fetch(PDO::FETCH_ASSOC);
        if (!$ligne) { echo json_encode(['status' => 'error', 'message' => 'Ligne introuvable ou déjà annulée.']); return; }

        // Sécurité : impossible de supprimer la dernière ligne active du dossier.
        $stmtCount = $bdBASI->prepare("SELECT COUNT(*) FROM passer_achat_et_paiement_ligne WHERE idPAP = ? AND id_statut_PAPL = 1");
        $stmtCount->execute([$idPAP]);
        if ((int) $stmtCount->fetchColumn() <= 1) {
            echo json_encode(['status' => 'error', 'message' => 'Impossible de supprimer la dernière ligne active du dossier — il doit en rester au moins une.']);
            return;
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        // Instantané AVANT annulation (valeurs encore en vigueur ici,
        // id_statut_PAPL = 1 puisque la ligne était encore active).
        dfc_historiserLignePAP($bdBASI, $ligne, $idPAP, "Ligne annulée par la DFC (par $sessionMatricule)", $dateEnregistrement);

        $bdBASI->prepare("UPDATE passer_achat_et_paiement_ligne SET id_statut_PAPL = 0 WHERE id = ?")->execute([$idPAPL]);

        if ((int) $dossier['idTypePAP'] === 1) {
            $bdBASI->prepare("UPDATE demandes_ligne SET quantite = 0, qte_commandee = 0, qte_restant = 0 WHERE idDL = ?")
                ->execute([$ligne['idDL']]);
        } else {
            $bdBASI->prepare("UPDATE demandes_ligne SET montant_total = 0 WHERE idDL = ?")
                ->execute([$ligne['idDL']]);
        }

        dfc_recalculerMontantTotalDossier($bdBASI, $idPAP);

        $bdBASI->commit();
        echo json_encode(['status' => 'success', 'message' => 'Ligne supprimée avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[DFC][annulerLignePAP] ' . $e->getMessage());
        erreurSqlDfc("Impossible de supprimer cette ligne.");
    }
}

/** Recalcule passer_achat_et_paiement.montant_total = somme des lignes actives. */
function dfc_recalculerMontantTotalDossier(PDO $bdBASI, int $idPAP): void {
    $stmt = $bdBASI->prepare("
        SELECT COALESCE(SUM(montant_total_ligne), 0) AS total
        FROM passer_achat_et_paiement_ligne
        WHERE idPAP = ? AND id_statut_PAPL = 1
    ");
    $stmt->execute([$idPAP]);
    $total = (float) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $bdBASI->prepare("UPDATE passer_achat_et_paiement SET montant_total = ? WHERE id = ?")->execute([$total, $idPAP]);
}

/**
 * Insère un instantané de l'ÉTAT DE LA LIGNE juste AVANT sa modification ou
 * son annulation (donc les valeurs passées ici sont les valeurs encore en
 * vigueur au moment de l'appel, PAS les nouvelles). La ligne historique la
 * plus ancienne pour un idPAPL donné représente donc l'état initial, avant
 * la toute première modification par la DFC.
 * Reprend l'intégralité des colonnes de passer_achat_et_paiement_ligne
 * (idPAP, idDL, prix_reel, quantite_reelle, montant_total_ligne, date_ajout,
 * id_unite, nb_unites, pieces_par_unite, id_statut_PAPL, modifie_par_dfc),
 * plus idPAPL/motif/dateEnregistrement propres à cette table d'historique —
 * schéma confirmé via les INSERT déjà existants ailleurs dans ce projet.
 */
function dfc_historiserLignePAP(PDO $bdBASI, array $ligne, int $idPAP, string $motif, string $dateEnregistrement): void {
    $bdBASI->prepare("
        INSERT INTO historique_passer_achat_et_paiement_ligne
            (idPAPL, idPAP, idDL, prix_reel, quantite_reelle, montant_total_ligne, date_ajout,
             id_unite, nb_unites, pieces_par_unite, id_statut_PAPL, modifie_par_dfc, motif, dateEnregistrement)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $ligne['id'], $idPAP, $ligne['idDL'], $ligne['prix_reel'], $ligne['quantite_reelle'],
        $ligne['montant_total_ligne'], $ligne['date_ajout'], $ligne['id_unite'], $ligne['nb_unites'],
        $ligne['pieces_par_unite'], 1, (int) $ligne['modifie_par_dfc'], $motif, $dateEnregistrement,
    ]);
}

/**
 * OPTION 22 — Envoie un e-mail de relance à la responsable des achats pour
 * un dossier toujours en attente. Réutilise envoyerMail() déjà présente
 * dans ce fichier.
 * ⚠️ L'adresse de la responsable des achats est à confirmer — voir la
 * constante RESPONSABLE_ACHATS_EMAIL_DFC ci-dessous.
 */
function envoyerRelanceResponsableAchats(PDO $bdBASI, dfcController $dfcController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueDfc('token', ''));
        $commentaire = trim((string) inputValueDfc('commentaire', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        if ($commentaire === '') { echo json_encode(['status' => 'error', 'message' => 'Le commentaire est requis pour la relance.']); return; }

        $idPAP = (int) $dfcController->tokendecrypt($token);
        if ($idPAP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmtC = $bdBASI->prepare("SELECT id, nom_commande FROM passer_achat_et_paiement WHERE id = ? LIMIT 1");
        $stmtC->execute([$idPAP]);
        $dossier = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$dossier) { echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable.']); return; }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        $bdBASI->prepare("
            INSERT INTO commentaire_dossier_pap (idPAP, idUtilisateur, commentaire, dateEnregistrement)
            VALUES (?, ?, ?, ?)
        ")->execute([$idPAP, $sessionUserId, $commentaire, $dateEnregistrement]);
        $idCommentaire = (int) $bdBASI->lastInsertId();

        $bdBASI->prepare("
            INSERT INTO historique_commentaire_dossier_pap (idCommentaire, idPAP, idUtilisateur, commentaire, motif, dateEnregistrement)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$idCommentaire, $idPAP, $sessionUserId, $commentaire, "Relance envoyée par $sessionMatricule", $dateEnregistrement]);

        $bdBASI->commit();

        $sujet = "Relance — Dossier #{$idPAP} en attente";
        $corpsHtml = "<p>Le dossier <strong>" . htmlspecialchars($dossier['nom_commande']) . "</strong> (#{$idPAP}) est toujours en attente de votre part.</p>"
            . "<p><strong>Commentaire de la DFC :</strong><br>" . nl2br(htmlspecialchars($commentaire)) . "</p>";
        envoyerMail($sujet, $corpsHtml, RESPONSABLE_ACHATS_EMAIL_DFC);

        echo json_encode(['status' => 'success', 'message' => 'Relance envoyée avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[DFC][envoyerRelanceResponsableAchats] ' . $e->getMessage());
        erreurSqlDfc("Impossible d'envoyer la relance.");
    }
}

/** OPTION 23 — Liste des commentaires d'un dossier (affichage responsable achats + DFC). */
function listerCommentairesDossier(PDO $bdBASI, dfcController $dfcController): void {
    try {
        $token = trim((string) inputValueDfc('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idPAP = (int) $dfcController->tokendecrypt($token);
        if ($idPAP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmt = $bdBASI->prepare("
            SELECT c.id, c.commentaire, c.dateEnregistrement, CONCAT(u.prenom, ' ', u.nom) AS auteur
            FROM commentaire_dossier_pap c
            LEFT JOIN utilisateurs u ON c.idUtilisateur = u.id
            WHERE c.idPAP = ?
            ORDER BY c.dateEnregistrement DESC
        ");
        $stmt->execute([$idPAP]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Throwable $e) {
        error_log('[DFC][listerCommentairesDossier] ' . $e->getMessage());
        erreurSqlDfc('Impossible de charger les commentaires.');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Vue d'exécution budgétaire (DFC)

   Définitions (confirmées avec l'utilisateur) :
   - Une ligne budgétaire est "exécutée" UNIQUEMENT quand elle est livrée
     (achat : quantite_livree >= quantite_reelle) ou payée (paiement : un
     paiement_pap au montant > 0 existe) — jamais simplement "commandée".
   - "En cours d'exécution" = une commande (PAP) existe sur la ligne mais
     n'est pas encore totalement livrée/payée.
   - "Restant à exécuter" = montant_total de la ligne moins tout ce qui a
     été engagé (exécuté + en cours) — ce qui n'a encore donné lieu à
     aucune commande.
   - Regroupement du graphe mensuel : par le MOIS DE FIN du délai prévu
     (periode_d_utilisation, stocké "MoisDébut-MoisFin" ou juste "Mois").
   - Périmètre : budgets Fonctionnement + Investissement confondus, au
     statut 'Accepter' ou 'Réajuster' (budgets validés par le DFC et donc
     réellement en exécution — 'Valider' seul n'est que l'étape direction,
     pas encore actionnable).

   Une même fonction de calcul par ligne (dfc_calculerExecutionLignes) est
   réutilisée par les 3 options pour que stats, graphe et tableau restent
   TOUJOURS cohérents entre eux.
═══════════════════════════════════════════════════════════════════════════ */

const DFC_MOIS_ORDRE = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

/** Extrait le mois de FIN du délai prévu ("Mars-Juin" → "Juin", "Mars" → "Mars", vide → null). */
function dfc_moisFinPeriode(?string $periode): ?string {
    $periode = trim((string) $periode);
    if ($periode === '') return null;
    $parts = array_map('trim', explode('-', $periode));
    $fin = end($parts);
    return in_array($fin, DFC_MOIS_ORDRE, true) ? $fin : null;
}

/** Extrait le mois de DÉBUT du délai prévu ("Avril-Septembre" → "Avril", "Mars" → "Mars", vide → null). */
function dfc_moisDebutPeriode(?string $periode): ?string {
    $periode = trim((string) $periode);
    if ($periode === '') return null;
    $parts = array_map('trim', explode('-', $periode));
    $debut = $parts[0];
    return in_array($debut, DFC_MOIS_ORDRE, true) ? $debut : null;
}

/**
 * Calcule, pour chaque ligne budgétaire active d'un budget accepté/réajusté
 * (Fonctionnement + Investissement confondus), les montants prévu / exécuté /
 * en cours / restant, le taux, l'état et le mois de fin du délai prévu.
 *
 * $directionId : null = toutes directions, sinon filtre sur budget.direction_id.
 * $annee : null = toutes années, sinon filtre sur budget.annee.
 * Retourne un tableau de lignes brutes, PAS encore agrégées.
 */
function dfc_calculerExecutionLignes(PDO $bdBASI, ?int $directionId, ?int $annee): array {
    $sql = "
        SELECT
            lb.id AS idLigne, lb.designation, lb.montant_total, lb.periode_d_utilisation,
            lb.id_type_budget_investissement, lb.service_id, lb.quantite AS quantite_prevue,
            b.id AS idBudget, b.annee, b.direction_id, b.type_budget_id,
            tb.nom AS type_budget_nom, tbi.categorie AS nature_categorie,
            d.nom_direction, d.code_direction,
            COALESCE(SUM(
                CASE
                    WHEN p.idTypePAP = 1 AND papl.quantite_reelle > 0 AND papl.quantite_livree >= papl.quantite_reelle
                        THEN papl.montant_total_ligne
                    WHEN p.idTypePAP = 2 AND COALESCE(pp.paye_total, 0) > 0
                        THEN papl.montant_total_ligne
                    ELSE 0
                END
            ), 0) AS montant_execute,
            COALESCE(SUM(papl.montant_total_ligne), 0) AS montant_engage,
            COUNT(DISTINCT papl.idPAP) AS nombre_commandes,
            COALESCE(SUM(papl.quantite_reelle), 0) AS quantite_commandee_totale,
            COALESCE(SUM(papl.quantite_livree), 0) AS quantite_livree_totale
        FROM ligneBudget lb
        JOIN budget b ON lb.budget_id = b.id
        JOIN typeBudget tb ON b.type_budget_id = tb.id
        LEFT JOIN direction d ON b.direction_id = d.id
        LEFT JOIN type_budget_investissement tbi ON lb.id_type_budget_investissement = tbi.id
        LEFT JOIN demandes_ligne dl ON dl.idLB = lb.id
        LEFT JOIN passer_achat_et_paiement_ligne papl ON papl.idDL = dl.idDL
        LEFT JOIN passer_achat_et_paiement p ON papl.idPAP = p.id
        LEFT JOIN (
            SELECT idPAP, SUM(montant) AS paye_total FROM paiement_pap GROUP BY idPAP
        ) pp ON pp.idPAP = p.id
        WHERE lb.statut = 'Actif' AND b.statut IN ('Accepter', 'Réajuster')
    ";
    $params = [];
    if ($directionId !== null) { $sql .= " AND b.direction_id = ?"; $params[] = $directionId; }
    if ($annee !== null)       { $sql .= " AND b.annee = ?";        $params[] = $annee; }
    $sql .= " GROUP BY lb.id ORDER BY b.annee DESC, lb.id ASC";

    $stmt = $bdBASI->prepare($sql);
    $stmt->execute($params);
    $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lignes as &$l) {
        $total   = (float) $l['montant_total'];
        $execute = (float) $l['montant_execute'];
        $engage  = (float) $l['montant_engage'];
        if ($execute > $total) $execute = $total; // sécurité : jamais plus exécuté que prévu dans les agrégats
        $enCours = max(0.0, $engage - $execute);
        $restant = max(0.0, $total - $engage);

        $l['montant_execute']  = $execute;  // toujours monétaire : suivi budgétaire réel
        $l['montant_en_cours'] = $enCours;
        $l['montant_restant']  = $restant;
        $l['mois_fin_delai']   = dfc_moisFinPeriode($l['periode_d_utilisation']);
        $l['mois_debut_delai'] = dfc_moisDebutPeriode($l['periode_d_utilisation']);

        // Quantités produit : uniquement pertinentes pour une ligne de type
        // "Produit" — null pour "Autre" (prestation, salaire…) où ces champs
        // n'ont pas de sens.
        $estProduit      = ($l['nature_categorie'] === 'Produit');
        $quantitePrevue  = (float) $l['quantite_prevue'];
        $quantiteLivree  = (float) $l['quantite_livree_totale'];
        $l['quantite_prevue']           = $estProduit ? $quantitePrevue : null;
        $l['quantite_commandee_totale'] = $estProduit ? (float) $l['quantite_commandee_totale'] : null;
        $l['quantite_livree_totale']    = $estProduit ? $quantiteLivree : null;

        // Taux / état : pour une ligne "Produit", basés sur les QUANTITÉS
        // (prévu vs livré), pas sur les montants — le prix réellement
        // commandé peut différer du prix budgété, ce qui fausserait un taux
        // calculé en argent (ex. 10 unités commandées moins cher que prévu,
        // toutes livrées, donnerait un taux < 100 % alors que tout est
        // physiquement reçu). Pour "Autre" (prestation, salaire…), sans
        // notion de quantité, le taux reste monétaire comme avant.
        if ($estProduit && $quantitePrevue > 0.001) {
            $tauxQte = min(100.0, round(($quantiteLivree / $quantitePrevue) * 100, 1));
            $l['taux_execution'] = $tauxQte;
            $l['etat_execution'] = $quantiteLivree <= 0.001
                ? 'non_execute'
                : ($quantiteLivree >= $quantitePrevue - 0.001 ? 'entierement_execute' : 'partiellement_execute');
        } else {
            $l['taux_execution'] = $total > 0 ? round(($execute / $total) * 100, 1) : 0.0;
            $l['etat_execution'] = $execute <= 0.001
                ? 'non_execute'
                : ($execute >= $total - 0.001 ? 'entierement_execute' : 'partiellement_execute');
        }
    }
    unset($l);

    return $lignes;
}

/**
 * OPTION 24 — Statistiques globales uniquement. Le graphe est désormais un
 * Gantt par ligne budgétaire (une barre = une période prévue), construit
 * côté client directement à partir des données de l'option 25 — pour que
 * graphe et tableau soient rigoureusement les mêmes lignes, sans écart
 * possible entre les deux.
 * Body JSON : { "direction_id"?: N, "annee"?: N }
 */
function dfc_statistiquesExecution(PDO $bdBASI, dfcController $dfcController): void {
    try {
        $data = getJsonBody();
        $directionId = isset($data['direction_id']) && $data['direction_id'] !== '' ? (int) $data['direction_id'] : null;
        $annee       = isset($data['annee']) && $data['annee'] !== '' ? (int) $data['annee'] : null;

        $lignes = dfc_calculerExecutionLignes($bdBASI, $directionId, $annee);

        $budgetTotal = 0.0; $execute = 0.0; $enCours = 0.0; $restant = 0.0;
        foreach ($lignes as $l) {
            $budgetTotal += (float) $l['montant_total'];
            $execute     += $l['montant_execute'];
            $enCours     += $l['montant_en_cours'];
            $restant     += $l['montant_restant'];
        }

        echo json_encode([
            'status' => 'success',
            'stats' => [
                'budget_total'    => $budgetTotal,
                'montant_en_cours'=> $enCours,
                'montant_execute' => $execute,
                'montant_restant' => $restant,
                'taux_global'     => $budgetTotal > 0 ? round(($execute / $budgetTotal) * 100, 1) : 0.0,
            ],
        ]);
    } catch (\Throwable $e) {
        error_log('[DFC][dfc_statistiquesExecution] ' . $e->getMessage());
        erreurSqlDfc("Impossible de charger les statistiques d'exécution.");
    }
}

/**
 * OPTION 25 — Tableau complet des lignes budgétaires (toutes directions ou filtré).
 * Body JSON : { "direction_id"?: N }
 */
function dfc_listerLignesExecution(PDO $bdBASI, dfcController $dfcController): void {
    try {
        $data = getJsonBody();
        $directionId = isset($data['direction_id']) && $data['direction_id'] !== '' ? (int) $data['direction_id'] : null;
        $annee       = isset($data['annee']) && $data['annee'] !== '' ? (int) $data['annee'] : null;

        $lignes = dfc_calculerExecutionLignes($bdBASI, $directionId, $annee);

        echo json_encode(['status' => 'success', 'data' => $lignes, 'nombre_total' => count($lignes)]);
    } catch (\Throwable $e) {
        error_log('[DFC][dfc_listerLignesExecution] ' . $e->getMessage());
        erreurSqlDfc('Impossible de charger le tableau des lignes.');
    }
}

/** OPTION 26 — Liste des directions, pour le filtre. */
function dfc_listerDirections(PDO $bdBASI): void {
    try {
        $stmt = $bdBASI->query("SELECT id, nom_direction, code_direction FROM direction ORDER BY nom_direction ASC");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Throwable $e) {
        error_log('[DFC][dfc_listerDirections] ' . $e->getMessage());
        erreurSqlDfc('Impossible de charger la liste des directions.');
    }
}

/**
 * OPTION 27 — Années disponibles pour le filtre : toutes celles portées par
 * un budget accepté/réajusté (même périmètre que le reste de la page),
 * plus l'année en cours même si elle n'a encore aucun budget dans cet état.
 */
function dfc_listerAnneesExecution(PDO $bdBASI): void {
    try {
        $stmt = $bdBASI->query("
            SELECT DISTINCT annee FROM budget
            WHERE statut IN ('Accepter', 'Réajuster')
            ORDER BY annee DESC
        ");
        $annees = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $anneeCourante = (int) date('Y');
        if (!in_array($anneeCourante, $annees, true)) {
            $annees[] = $anneeCourante;
            rsort($annees);
        }
        echo json_encode(['status' => 'success', 'data' => $annees, 'annee_courante' => $anneeCourante]);
    } catch (\Throwable $e) {
        error_log('[DFC][dfc_listerAnneesExecution] ' . $e->getMessage());
        erreurSqlDfc('Impossible de charger la liste des années.');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   ROUTAGE
   16 = listerDossiers    (passer_achat_et_paiement.idStatut = 2)
   17 = detailDossier     (facture définitive OU justificatif(s) de paiement)
   18 = avisFavorable     (idStatut 2 → 3)
   19 = modifierQuantiteLigneAchat    (Achat uniquement)
   20 = modifierMontantLignePaiement  (Paiement uniquement)
   21 = annulerLignePAP               (les deux types)
   22 = envoyerRelanceResponsableAchats (+ enregistre un commentaire)
   23 = listerCommentairesDossier
   24 = dfc_statistiquesExecution  (Vue d'exécution budgétaire : stats + graphe)
   25 = dfc_listerLignesExecution  (Vue d'exécution budgétaire : tableau)
   26 = dfc_listerDirections       (Vue d'exécution budgétaire : filtre direction)
   27 = dfc_listerAnneesExecution  (Vue d'exécution budgétaire : filtre année)
═══════════════════════════════════════════════════════════════════════════ */
switch ($option) {

// ═══ 1 : TOUS les budgets (en attente + validés + rejetés + réajustés) ══════
    case 1:
        try {
            $data = getJsonBody();
            [$aw, $ap] = buildAnneeWhere($data);
            $sql = "SELECT b.id, b.annee, b.date_creation, b.plafond, b.statut, b.idStatut, b.matricule,
                           tb.nom AS type_budget_nom
                    FROM budget b
                    JOIN typeBudget tb ON b.type_budget_id = tb.id
                    WHERE b.statut IN ('Valider','Accepter','Rejeter','Réajuster')";
            if ($aw) $sql .= ' AND ' . implode(' AND ', $aw);
            $sql .= ' ORDER BY b.annee DESC, b.id DESC';
            $st = $bdBASI->prepare($sql); $st->execute($ap);
            $resultats = $st->fetchAll(PDO::FETCH_ASSOC);
            // Ajouter le token chiffré sur chaque ligne (utilisé par dfc_voirBudget)
            foreach ($resultats as &$ligne) {
                $ligne['tmp'] = $dfcController->tokenencrypt($ligne['id']);
            }
            unset($ligne);
            echo json_encode(['success' => true, 'data' => $resultats]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ═══ 2 : budgets EN ATTENTE (statut = Valider) ════════════════════════════════
    case 2:
        try {
            $data = getJsonBody();
            [$aw, $ap] = buildAnneeWhere($data);
            $sql = "SELECT b.id, b.annee, b.date_creation, b.plafond, b.statut, b.idStatut, b.matricule,
                           tb.nom AS type_budget_nom
                    FROM budget b
                    JOIN typeBudget tb ON b.type_budget_id = tb.id
                    WHERE b.statut = 'Valider'";
            if ($aw) $sql .= ' AND ' . implode(' AND ', $aw);
            $sql .= ' ORDER BY b.annee DESC, b.id DESC';
            $st = $bdBASI->prepare($sql); $st->execute($ap);
            $resultats = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($resultats as &$ligne) {
                $ligne['tmp'] = $dfcController->tokenencrypt($ligne['id']);
            }
            unset($ligne);
            echo json_encode(['success' => true, 'data' => $resultats]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ═══ 3 : budgets VALIDÉS par DFC (statut = Accepter) ══════════════════════════
    case 3:
        try {
            $data = getJsonBody();
            [$aw, $ap] = buildAnneeWhere($data);
            $sql = "SELECT b.id, b.annee, b.date_creation, b.plafond, b.statut, b.idStatut, b.matricule,
                           tb.nom AS type_budget_nom
                    FROM budget b
                    JOIN typeBudget tb ON b.type_budget_id = tb.id
                    WHERE b.statut = 'Accepter'";
            if ($aw) $sql .= ' AND ' . implode(' AND ', $aw);
            $sql .= ' ORDER BY b.annee DESC, b.id DESC';
            $st = $bdBASI->prepare($sql); $st->execute($ap);
            $resultats = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($resultats as &$ligne) {
                $ligne['tmp'] = $dfcController->tokenencrypt($ligne['id']);
            }
            unset($ligne);
            echo json_encode(['success' => true, 'data' => $resultats]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ═══ 4 : budgets REJETÉS (statut = Rejeter) ══════════════════════════════════
    case 4:
        try {
            $data = getJsonBody();
            [$aw, $ap] = buildAnneeWhere($data);
            $sql = "SELECT b.id, b.annee, b.date_creation, b.plafond, b.statut, b.idStatut, b.matricule,
                           tb.nom AS type_budget_nom
                    FROM budget b
                    JOIN typeBudget tb ON b.type_budget_id = tb.id
                    WHERE b.statut = 'Rejeter'";
            if ($aw) $sql .= ' AND ' . implode(' AND ', $aw);
            $sql .= ' ORDER BY b.annee DESC, b.id DESC';
            $st = $bdBASI->prepare($sql); $st->execute($ap);
            $resultats = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($resultats as &$ligne) {
                $ligne['tmp'] = $dfcController->tokenencrypt($ligne['id']);
            }
            unset($ligne);
            echo json_encode(['success' => true, 'data' => $resultats]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ═══ 5 : budgets RÉAJUSTER (statut = Réajuster) ══════════════════════════════
    case 5:
        try {
            $data = getJsonBody();
            [$aw, $ap] = buildAnneeWhere($data);
            $sql = "SELECT b.id, b.annee, b.date_creation, b.plafond, b.statut, b.idStatut, b.matricule,
                           tb.nom AS type_budget_nom
                    FROM budget b
                    JOIN typeBudget tb ON b.type_budget_id = tb.id
                    WHERE b.statut = 'Réajuster'";
            if ($aw) $sql .= ' AND ' . implode(' AND ', $aw);
            $sql .= ' ORDER BY b.annee DESC, b.id DESC';
            $st = $bdBASI->prepare($sql); $st->execute($ap);
            $resultats = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($resultats as &$ligne) {
                $ligne['tmp'] = $dfcController->tokenencrypt($ligne['id']);
            }
            unset($ligne);
            echo json_encode(['success' => true, 'data' => $resultats]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ═══ 6 : ACTION VALIDER → Accepter (idStatut=7) ════════════════════════════
    case 6:


        try {
            $data = getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("budgetId requis.");
            $id = (int)$data['budgetId'];

            $chk = $bdBASI->prepare("SELECT * FROM budget WHERE id = ? AND statut NOT IN ('Supprimer')");
            $chk->execute([$id]);
            $b = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$b) throw new Exception("Budget non trouvé.", 404);
            if ($b['statut'] !== 'Valider') throw new Exception("Seul un budget en attente peut être accepté. Statut actuel : {$b['statut']}.");



            $chk_u = $bdBASI->prepare("SELECT * FROM utilisateurs WHERE id = ?");
            $chk_u->execute([$b['idUtilisateur']]);
            $b_u = $chk_u->fetch(PDO::FETCH_ASSOC);
            if (!$b_u) throw new Exception("Utilisateur non trouvé.", 404);




            $bdBASI->beginTransaction();
            $bdBASI->prepare("UPDATE budget SET statut='Accepter', idStatut=7 WHERE id=?")->execute([$id]);
            insertHistoriqueBudget($bdBASI, $id, 'Accepter', 7, "Budget accepté", $sessionUserId);
            $bdBASI->commit();


            $email = "ndiaya.ndao@uahb.sn";
//            $email = $b_u['email'];
            $prenom = ucfirst(mb_strtoupper($b_u['prenom']));
            $nom = mb_strtoupper($dfcController->fctRetirerAccents($b_u['nom']));

            $corps = "<p style='color:#374151;font-size:.875rem'>Le budget <strong>#{$id}</strong> (Année <strong>{$b['annee']}</strong>) a été <strong>validé</strong> par la Direction des Finances.</p>";
            envoyerMail("✅ Budget #{$id} validé — ENT UAHB", buildEmail("Budget #{$id} validé par la DFC", $corps, $id, 'Validé', '#059669'),$email);

            echo json_encode(['success' => true, 'message' => "Budget #{$id} validé avec succès."]);
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

// ═══ 7 : ACTION REJETER → Rejeter (idStatut=5) ═════════════════════════════
    case 7:
        try {
            $data = getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("budgetId requis.");
            if (empty(trim($data['motif'] ?? ''))) throw new Exception("Un motif de rejet est obligatoire.");
            $id    = (int)$data['budgetId'];
            $motif = trim($data['motif']);

            $chk = $bdBASI->prepare("SELECT * FROM budget WHERE id = ? AND statut NOT IN ('Supprimer')");
            $chk->execute([$id]);
            $b = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$b) throw new Exception("Budget non trouvé.", 404);
            if ($b['statut'] === 'Rejeter') throw new Exception("Ce budget est déjà rejeté.");
            if (!in_array($b['statut'], ['Valider', 'Accepter', 'Réajuster']))
                throw new Exception("Ce budget ne peut pas être rejeté depuis le statut '{$b['statut']}'.");


            $chk_u = $bdBASI->prepare("SELECT * FROM utilisateurs WHERE id = ?");
            $chk_u->execute([$b['idUtilisateur']]);
            $b_u = $chk_u->fetch(PDO::FETCH_ASSOC);
            if (!$b_u) throw new Exception("Utilisateur non trouvé.", 404);

            $bdBASI->beginTransaction();
            $bdBASI->prepare("UPDATE budget SET statut='Rejeter', idStatut=5 WHERE id=?")->execute([$id]);
            insertHistoriqueBudget($bdBASI, $id, 'Rejeter', 5, $motif, $sessionUserId);
            $bdBASI->commit();


            //  $email = $b_u['email'];
            $email = "ndiaya.ndao@uahb.sn";
            $prenom = ucfirst(mb_strtoupper($b_u['prenom']));
            $nom = mb_strtoupper($dfcController->fctRetirerAccents($b_u['nom']));

            $corps = "<p style='color:#374151;font-size:.875rem'>Le budget <strong>#{$id}</strong> (Année <strong>{$b['annee']}</strong>) a été <strong>rejeté</strong>.</p>
                      <p><strong>Motif :</strong></p>
                      <div class='motif'>".nl2br(htmlspecialchars($motif))."</div>";
            envoyerMail("❌ Budget #{$id} rejeté — ENT UAHB", buildEmail("Budget #{$id} rejeté par la DFC", $corps, $id, 'Rejeté', '#dc2626'),$email);

            echo json_encode(['success' => true, 'message' => "Budget #{$id} rejeté."]);
        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;

//// ═══ 8 : ACTION RÉAJUSTER → Réajuster (idStatut=9) ═════════════════════════
//    case 8:
//        try {
//            $data  = getJsonBody();
//            if (empty($data['budgetId'])) throw new Exception("budgetId requis.");
//            $id    = (int)$data['budgetId'];
//            $motif = trim($data['motif'] ?? 'Réajustement demandé par la DFC');
//
//            $chk = $bdBASI->prepare("SELECT * FROM budget WHERE id = ? AND statut NOT IN ('Supprimer')");
//            $chk->execute([$id]);
//            $b = $chk->fetch(PDO::FETCH_ASSOC);
//            if (!$b) throw new Exception("Budget non trouvé.", 404);
//            if ($b['statut'] !== 'Accepter') throw new Exception("Seul un budget validé peut être réajusté. Statut actuel : {$b['statut']}.");
//
//
//            $bdBASI->beginTransaction();
//            $upd = $bdBASI->prepare("UPDATE ligneBudget SET verrouiller=1 WHERE budget_id=? AND statut='Actif' AND (verrouiller=0 OR verrouiller IS NULL)");
//            $upd->execute([$id]);
//            $nbVerr = $upd->rowCount();
//
//
//            $bdBASI->prepare("UPDATE budget SET statut='Réajuster', idStatut=9 WHERE id=?")->execute([$id]);
//            insertHistoriqueBudget($bdBASI, $id, 'Réajuster', 9, $motif, $sessionUserId);
//            $bdBASI->commit();
//
//            $corps = "<p>Réajustement du budget <strong>#{$id}</strong> (Année <strong>{$b['annee']}</strong>).</p>
//                      <div class='motif'>".nl2br(htmlspecialchars($motif))."</div>
//                      <p><strong>{$nbVerr} ligne(s)</strong> verrouillée(s).</p>";
//            envoyerMail("🔄 Budget #{$id} — Réajustement — ENT UAHB", buildEmail("Budget #{$id} à réajuster", $corps, $id, 'Réajuster', '#d97706'));
//
//            echo json_encode(['success'=>true,'message'=>"Budget #{$id} passé en réajustement. {$nbVerr} ligne(s) verrouillée(s).",'lignes_verrouilees'=>$nbVerr]);
//        } catch (PDOException $e) {
//            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
//            http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
//        } catch (Exception $e) {
//            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
//            http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
//        }
//        exit;

// ═══ 8 : ACTION RÉAJUSTER → Réajuster (idStatut=9) ═══════════════════════
    case 8:
        try {
            $data  = getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("budgetId requis.");
            $id    = (int)$data['budgetId'];
            $motif = trim($data['motif'] ?? 'Réajustement demandé par la DFC');

            $chk = $bdBASI->prepare("SELECT * FROM budget WHERE id = ? AND statut NOT IN ('Supprimer')");
            $chk->execute([$id]);
            $b = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$b) throw new Exception("Budget non trouvé.", 404);
            if ($b['statut'] !== 'Accepter')
                throw new Exception("Seul un budget validé peut être réajusté. Statut actuel : {$b['statut']}.");


            $chk_u = $bdBASI->prepare("SELECT * FROM utilisateurs WHERE id = ?");
            $chk_u->execute([$b['idUtilisateur']]);
            $b_u = $chk_u->fetch(PDO::FETCH_ASSOC);
            if (!$b_u) throw new Exception("Utilisateur non trouvé.", 404);


            // Récupérer les lignes actives NON encore verrouillées
            // (ce sont celles qui vont être verrouillées par le réajustement)
            $stmtLignes = $bdBASI->prepare("
                SELECT id,
                       budget_id,
                       rubrique_id,
                       sous_rubrique_id,
                       id_type_budget_investissement,
                       designation,
                       description,
                       quantite,
                       unite_id,
                       prix_unitaire,
                       montant_total,
                       service_id,
                       id_produit,
                       date_creation,
                       statut,
                       idStatut,
                       periode_d_utilisation,
                       verrouiller
                FROM ligneBudget
                WHERE budget_id = ?
                  AND statut = 'Actif'
                  AND (verrouiller = 0 OR verrouiller IS NULL)
            ");
            $stmtLignes->execute([$id]);
            $lignesAVerrouiller = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
            $stmtLignes->closeCursor();

            $bdBASI->beginTransaction();

            // 1. Verrouiller les lignes actives
            $upd = $bdBASI->prepare("
                UPDATE ligneBudget
                SET verrouiller = 1
                WHERE budget_id = ? AND statut = 'Actif' AND (verrouiller = 0 OR verrouiller IS NULL)
            ");
            $upd->execute([$id]);
            $nbVerr = $upd->rowCount();

            // 2. Insérer un historique pour chaque ligne verrouillée
            $nowDakar  = (new DateTime('now', new DateTimeZone('Africa/Dakar')))->format('Y-m-d H:i:s');
            $stmtHisto = $bdBASI->prepare("
                INSERT INTO historique_ligneBudget (
                    ligne_budget_id,
                    budget_id,
                    rubrique_id,
                    sous_rubrique_id,
                    id_type_budget_investissement,
                    designation,
                    description,
                    quantite,
                    unite_id,
                    prix_unitaire,
                    montant_total,
                    service_id,
                    id_produit,
                    date_creation,
                    statut,
                    idStatut,
                    periode_d_utilisation,
                    verrouiller,
                    motif,
                    dateEnregistrement
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?
                )
            ");

            foreach ($lignesAVerrouiller as $ligne) {
                $stmtHisto->execute([
                    $ligne['id'],                                    // ligne_budget_id
                    $ligne['budget_id'],                             // budget_id
                    $ligne['rubrique_id'],                           // rubrique_id
                    $ligne['sous_rubrique_id'],                      // sous_rubrique_id
                    $ligne['id_type_budget_investissement'],          // id_type_budget_investissement
                    $ligne['designation'],                           // designation
                    $ligne['description'],                           // description
                    $ligne['quantite'],                              // quantite
                    $ligne['unite_id'],                              // unite_id
                    $ligne['prix_unitaire'],                         // prix_unitaire
                    $ligne['montant_total'],                         // montant_total
                    $ligne['service_id'],                            // service_id
                    $ligne['id_produit'],                            // id_produit
                    $ligne['date_creation'],                         // date_creation
                    $ligne['statut'],                                // statut (actif)
                    $ligne['idStatut'],                              // idStatut
                    $ligne['periode_d_utilisation'],                 // periode_d_utilisation
                    1,                                               // verrouiller (état APRÈS l'action)
                    $motif,                                          // motif du réajustement
                    $nowDakar,                                       // dateEnregistrement
                ]);
                $stmtHisto->closeCursor();
            }

            // 3. Passer le budget au statut Réajuster
            $bdBASI->prepare("UPDATE budget SET statut='Réajuster', idStatut=9 WHERE id=?")
                ->execute([$id]);

            insertHistoriqueBudget($bdBASI, $id, 'Réajuster', 9, $motif, $sessionUserId);

            $bdBASI->commit();


            //  $email = $b_u['email'];
            $email = "ndiaya.ndao@uahb.sn";
            $prenom = ucfirst(mb_strtoupper($b_u['prenom']));
            $nom = mb_strtoupper($dfcController->fctRetirerAccents($b_u['nom']));

            // 4. Email de notification
            $corps = "
                <p>Réajustement du budget <strong>#{$id}</strong> (Année <strong>{$b['annee']}</strong>).</p>
                <div class='motif'>".nl2br(htmlspecialchars($motif))."</div>
                <p><strong>{$nbVerr} ligne(s)</strong> verrouillée(s) et archivée(s) dans l'historique.</p>
            ";
            envoyerMail(
                "🔄 Budget #{$id} — Réajustement — ENT UAHB",
                buildEmail("Budget #{$id} à réajuster", $corps, $id, 'Réajuster', '#d97706'),$email
            );

            echo json_encode([
                'success'           => true,
                'message'           => "Budget #{$id} passé en réajustement. {$nbVerr} ligne(s) verrouillée(s) et archivée(s).",
                'lignes_verrouilees'=> $nbVerr,
            ]);

        } catch (PDOException $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (Exception $e) {
            if ($bdBASI->inTransaction()) $bdBASI->rollBack();
            http_response_code($e->getCode() ?: 400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;

// ═══ 9 : motif du dernier rejet ══════════════════════════════════════════════
    case 9:
        try {
            $data = getJsonBody();
            if (empty($data['budgetId'])) throw new Exception("budgetId requis.");
            $id = (int)$data['budgetId'];
            $st = $bdBASI->prepare("SELECT motif, statut, dateEnregistrement AS date FROM historique_budget WHERE idBudget=? AND statut='Rejeter' ORDER BY dateEnregistrement DESC, id DESC LIMIT 1");
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            echo $row
                ? json_encode(['success'=>true,'data'=>['motif'=>$row['motif']?:'(Motif non renseigné)','date'=>$row['date']]])
                : json_encode(['success'=>false,'message'=>'Aucun motif trouvé pour ce budget.']);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception $e) { http_response_code(400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ═══ 10 : années disponibles ══════════════════════════════════════════════════
    case 10:
        try {
            $st = $bdBASI->prepare("SELECT DISTINCT annee FROM budget WHERE statut IN ('Valider','Accepter','Rejeter','Réajuster') ORDER BY annee ASC");
            $st->execute();
            echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_COLUMN)]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ═══ 11 : stats globales ══════════════════════════════════════════════════════
    case 11:
        try {
            $data = getJsonBody();
            [$aw, $ap] = buildAnneeWhere($data);
            $sql = "SELECT b.statut, COUNT(*) AS nb, COALESCE(SUM(b.plafond),0) AS total_plafond FROM budget b WHERE b.statut IN ('Valider','Accepter','Rejeter','Réajuster')";
            if ($aw) $sql .= ' AND ' . implode(' AND ', $aw);
            $sql .= ' GROUP BY b.statut';
            $st = $bdBASI->prepare($sql); $st->execute($ap);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            $stats = ['tous'=>['count'=>0,'plafond'=>0],'encours'=>['count'=>0,'plafond'=>0],'valider'=>['count'=>0,'plafond'=>0],'accepter'=>['count'=>0,'plafond'=>0],'rejeter'=>['count'=>0,'plafond'=>0],'reajuster'=>['count'=>0,'plafond'=>0]];
            foreach ($rows as $r) {
                $c = (int)$r['nb']; $p = (float)$r['total_plafond'];
                $stats['tous']['count'] += $c; $stats['tous']['plafond'] += $p;
                if     ($r['statut']==='Valider')   { $stats['encours']['count']+=$c;   $stats['encours']['plafond']+=$p;   $stats['valider']['count']+=$c;   $stats['valider']['plafond']+=$p; }
                elseif ($r['statut']==='Accepter')  { $stats['accepter']['count']+=$c;  $stats['accepter']['plafond']+=$p;  }
                elseif ($r['statut']==='Rejeter')   { $stats['rejeter']['count']+=$c;   $stats['rejeter']['plafond']+=$p;   }
                elseif ($r['statut']==='Réajuster') { $stats['reajuster']['count']+=$c; $stats['reajuster']['plafond']+=$p; }
            }
            echo json_encode(['success'=>true,'data'=>$stats]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ═══════════════════════════════════════════════════════════════════════════════
// CONSULTATION BUDGET DFC — cases 12-15
// Token chiffré via $dfcController->tokenencrypt/tokendecrypt (méthodes de classe)
// Pas de redéclaration de fonctions globales — tout passe par la classe
// ═══════════════════════════════════════════════════════════════════════════════

// ═══ 12 : Détails budget par token ════════════════════════════════════════════
// Body JSON : { "token": "..." }
// Retourne success + data (compatible JS : dBudget.success && dBudget.data)
    case 12:
        try {
            $data = getJsonBody();
            if (empty($data['token'])) throw new Exception("Token requis.", 400);
            $id = (int)$dfcController->tokendecrypt($data['token']);
            if (!$id) throw new Exception("Token invalide ou budget introuvable.", 400);

            $stmt = $bdBASI->prepare("
                SELECT b.*, tb.nom AS type_budget_nom, tb.id AS type_budget_id,d.code_direction
                FROM budget b
                JOIN typeBudget tb ON b.type_budget_id = tb.id
               LEFT JOIN direction d ON b.direction_id = d.id
                WHERE b.id = ? AND b.statut NOT IN ('Supprimer','supprimer')
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $budget = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$budget) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Budget non trouvé.']); exit; }

            echo json_encode(['success'=>true,'data'=>$budget]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ═══ 13 : Lignes INVESTISSEMENT par token ═════════════════════════════════════
    case 13:
        try {
            $data = getJsonBody();
            if (empty($data['token'])) throw new Exception("Token requis.", 400);
            $id = (int)$dfcController->tokendecrypt($data['token']);
            if (!$id) throw new Exception("Token invalide.", 400);

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
                LEFT JOIN rubrique                    r   ON lb.rubrique_id                   = r.id
                LEFT JOIN sousRubrique                sr  ON lb.sous_rubrique_id              = sr.id
                LEFT JOIN type_budget_investissement  tbi ON lb.id_type_budget_investissement = tbi.id
                LEFT JOIN services                    s   ON lb.service_id                    = s.id
                LEFT JOIN direction                   d   ON s.id_direction                   = d.id
                LEFT JOIN product                     p   ON lb.id_produit                    = p.idP
                LEFT JOIN listeUnites                 u   ON lb.unite_id                      = u.id
                WHERE lb.budget_id = ? AND lb.statut NOT IN ('Inactif','inactif')
                ORDER BY lb.id ASC
            ");
            $stmt->execute([$id]);
            $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $byNature = []; $byDirection = []; $total = 0;
            foreach ($lines as $l) {
                $mt = (float)($l['montant_total'] ?? 0); $total += $mt;
                $n = $l['nature_nom']    ?? 'N/A'; $byNature[$n]    = ($byNature[$n]    ?? 0) + $mt;
                $d = $l['nom_direction'] ?? 'N/A'; $byDirection[$d] = ($byDirection[$d] ?? 0) + $mt;
            }
            echo json_encode(['success'=>true,'data'=>$lines,'lineCount'=>count($lines),'totalMontant'=>$total,'byNature'=>$byNature,'byDirection'=>$byDirection,'lastUpdate'=>date('Y-m-d H:i:s')]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ═══ 14 : Lignes FONCTIONNEMENT par token ══════════════════════════════════════
    case 14:
        try {
            $data = getJsonBody();
            if (empty($data['token'])) throw new Exception("Token requis.", 400);
            $id = (int)$dfcController->tokendecrypt($data['token']);
            if (!$id) throw new Exception("Token invalide.", 400);

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
                LEFT JOIN product       p  ON lb.id_produit        = p.idP
                LEFT JOIN souscategorie sc ON p.id_Sous_categorie  = sc.id
                LEFT JOIN categorie     c  ON sc.categorie_id      = c.id
                LEFT JOIN listeUnites   u  ON lb.unite_id          = u.id
                WHERE lb.budget_id = ? AND lb.statut NOT IN ('Inactif','inactif')
                ORDER BY lb.id ASC
            ");
            $stmt->execute([$id]);
            $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $byCategorie = []; $total = 0;
            foreach ($lines as $l) {
                $mt = (float)($l['montant_total'] ?? 0); $total += $mt;
                $cat = $l['nom_categorie'] ?? 'N/A'; $byCategorie[$cat] = ($byCategorie[$cat] ?? 0) + $mt;
            }
            echo json_encode(['success'=>true,'data'=>$lines,'lineCount'=>count($lines),'totalMontant'=>$total,'byCategorie'=>$byCategorie,'lastUpdate'=>date('Y-m-d H:i:s')]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

// ═══ 15 : Historique par token ════════════════════════════════════════════════
    case 15:
        try {
            $data = getJsonBody();
            if (empty($data['token'])) throw new Exception("Token requis.", 400);
            $id = (int)$dfcController->tokendecrypt($data['token']);
            if (!$id) throw new Exception("Token invalide.", 400);

            $stmt = $bdBASI->prepare("
                SELECT h.*, u.tmpPrenom AS prenom, u.tmpNom AS nom
                FROM historique_budget h
                LEFT JOIN utilisateur u ON h.idUtilisateur = u.id
                WHERE h.idBudget = ?
                ORDER BY h.dateEnregistrement DESC, h.id DESC
                LIMIT 50
            ");
            $stmt->execute([$id]);
            echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Exception    $e) { http_response_code(400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;

    case 16:
        listerDossiers($bdBASI, $dfcController);
        break;

    case 17:
        detailDossier($bdBASI, $dfcController);
        break;

    case 18:
        avisFavorable($bdBASI, $dfcController, $sessionUserId, $sessionMatricule);
        break;

    case 19:
        modifierQuantiteLigneAchat($bdBASI, $dfcController, $sessionUserId, $sessionMatricule);
        break;

    case 20:
        modifierMontantLignePaiement($bdBASI, $dfcController, $sessionUserId, $sessionMatricule);
        break;

    case 21:
        annulerLignePAP($bdBASI, $dfcController, $sessionUserId, $sessionMatricule);
        break;

    case 22:
        envoyerRelanceResponsableAchats($bdBASI, $dfcController, $sessionUserId, $sessionMatricule);
        break;

    case 23:
        listerCommentairesDossier($bdBASI, $dfcController);
        break;

    case 24:
        dfc_statistiquesExecution($bdBASI, $dfcController);
        break;

    case 25:
        dfc_listerLignesExecution($bdBASI, $dfcController);
        break;

    case 26:
        dfc_listerDirections($bdBASI);
        break;

    case 27:
        dfc_listerAnneesExecution($bdBASI);
        break;







    default:
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>"Option '{$option}' non reconnue."]);
        exit;
}