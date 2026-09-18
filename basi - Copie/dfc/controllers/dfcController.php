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
                papl.montant_total_ligne
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
/* ═══════════════════════════════════════════════════════════════════════════
   ROUTAGE
   16 = listerDossiers    (passer_achat_et_paiement.idStatut = 2)
   17 = detailDossier     (facture définitive OU justificatif(s) de paiement)
   18 = avisFavorable     (idStatut 2 → 3)
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






    default:
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>"Option '{$option}' non reconnue."]);
        exit;
}