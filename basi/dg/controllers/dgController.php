<?php
/**
 * dgController.php
 * Contrôleur DG (Directeur Général) : décision finale (Accepter / Rejeter)
 * sur les dossiers ayant reçu un avis favorable de la DFC
 * (passer_achat_et_paiement.idStatut = 3).
 *
 * ⚠️ Hypothèses de schéma :
 *   - `passer_achat_et_paiement.idTypePAP` (1 = Passer commande,
 *     2 = Passer au paiement) distingue le TYPE de dossier ; `idStatut` est
 *     un pur statut de cycle de vie :
 *       1 = En attente | 2 = Validé (DGA) | 3 = Avis favorable (DFC)
 *       4 = Accepter | 5 = Rejetée | 6 = En paiement | 7 = Terminée
 *     Ce contrôleur gère les transitions 3 → 4 et 3 → 5.
 *   - `document_justificatif_paiement` : id, idPAP, doc, statut, dateEnregistrement.
 *   - `passer_achat_et_paiement` et `historique_passer_achat_et_paiement` ont
 *     désormais une colonne `motifRejet` (texte), obligatoire lors d'un rejet
 *     (transition 3 → 5), affichée au comptable (DRH) lors de la modification
 *     du dossier rejeté. À créer si besoin :
 *       ALTER TABLE passer_achat_et_paiement ADD COLUMN motifRejet TEXT NULL;
 *       ALTER TABLE historique_passer_achat_et_paiement ADD COLUMN motifRejet TEXT NULL;
 */

// ─── Capture de tout output parasite ─────────────────────────────────────────
ob_start();
session_start();
include_once('../../../bdBASI.php'); // ← ajuster selon la profondeur réelle de ce fichier
ob_end_clean();

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// ─── Vérification sessions obligatoires ──────────────────────────────────────
function checkSessionDg(): void {
    foreach (['tmpIdBASI', 'tmpIdDirection', 'tmpMatricule'] as $key) {
        if (empty($_SESSION[$key])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['status'=>'error','code'=>'sessionExpired','message'=>'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }
    }
}
checkSessionDg();

$sessionUserId    = (int)$_SESSION['tmpIdBASI'];
$sessionMatricule = trim($_SESSION['tmpMatricule']);

// ─── Classe contrôleur ────────────────────────────────────────────────────────
class dgController extends BDBASI
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
    $BDBASI       = new BDBASI();
    $bdBASI       = $BDBASI->connect();
    $dgController = new dgController();
} catch (\Throwable $e) {
    error_log('[DG][Connexion] ' . $e->getMessage());
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

/**
 * Lit le corps de la requête envoyé en JSON brut (cas des appels fetch()
 * avec Content-Type: application/json). $_POST ne contient rien dans ce cas.
 */
function getJsonBodyDg(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Récupère un paramètre en cherchant dans l'ordre : le body JSON brut,
 * $_POST, $_GET — fonctionne quel que soit le mode d'envoi utilisé.
 */
function inputValueDg(string $key, $default = null) {
    static $jsonBody = null;
    if ($jsonBody === null) $jsonBody = getJsonBodyDg();

    if (array_key_exists($key, $jsonBody)) return $jsonBody[$key];
    if (isset($_POST[$key]))               return $_POST[$key];
    if (isset($_GET[$key]))                return $_GET[$key];
    return $default;
}

function erreurSqlDg(string $message = "Erreur lors de l'accès à la base de données."): void {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$message]);
}

// ─── Lecture de l'option ──────────────────────────────────────────────────────
$option = isset($_GET['option']) ? trim($_GET['option']) : '';
if ($option==='' && isset($_POST['option'])) $option = trim($_POST['option']);
if ($option==='') {
    $jsonBodyOption = getJsonBodyDg();
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
 * Liste des dossiers en attente de décision du DG : idStatut = 3 (avis
 * favorable DFC), toutes provenances confondues (commande ET paiement,
 * distinguées via idTypePAP pour l'affichage).
 */
function listerDossiers(PDO $bdBASI, dgController $dgController): void {
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
            WHERE p.idStatut = 3
            ORDER BY p.dateCreation DESC
        ");
        if (!$stmt) throw new \RuntimeException('Requête de liste des dossiers échouée.');

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['tmp'] = $dgController->tokenencrypt($r['idPAP']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows]);
    } catch (\Throwable $e) {
        error_log('[DG][listerDossiers] ' . $e->getMessage());
        erreurSqlDg('Impossible de charger la liste des dossiers.');
    }
}

/**
 * Détail d'un dossier pour la modale "Détail" — identique au principe DFC :
 *   - idTypePAP = 1 (Passer commande) → facture définitive (documents_pap,
 *     ligne dont choix = 1).
 *   - idTypePAP = 2 (Passer au paiement) → justificatif(s) de paiement
 *     (document_justificatif_paiement, statut = 1).
 *
 * Paramètre : token (chiffré de idPAP)
 */
function detailDossier(PDO $bdBASI, dgController $dgController): void {
    try {
        $token = trim((string) inputValueDg('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $dgController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmtC = $bdBASI->prepare("
            SELECT id AS idPAP, nom_commande, montant_total, dateCreation, idStatut, idTypePAP
            FROM passer_achat_et_paiement
            WHERE id = ? AND idStatut = 3
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
        error_log('[DG][detailDossier] ' . $e->getMessage());
        erreurSqlDg('Impossible de charger le détail du dossier.');
    }
}

/**
 * Accepter : passer_achat_et_paiement.idStatut de 3 → 4.
 *
 * Paramètre : token (chiffré de idPAP)
 */
function accepterDossier(PDO $bdBASI, dgController $dgController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueDg('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $dgController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmtC = $bdBASI->prepare("SELECT id FROM passer_achat_et_paiement WHERE id = ? AND idStatut = 3 LIMIT 1");
        $stmtC->execute([$idPAP]);
        if (!$stmtC->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable ou déjà traité.']);
            return;
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        $bdBASI->prepare("UPDATE passer_achat_et_paiement SET idStatut = 4 WHERE id = ?")->execute([$idPAP]);

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
            "Accepté par le DG (par $sessionMatricule)",
            $dateEnregistrement, $idPAP,
        ]);

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Dossier accepté avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[DG][accepterDossier] ' . $e->getMessage());
        erreurSqlDg("Impossible d'accepter le dossier.");
    }
}

/**
 * Rejeter : passer_achat_et_paiement.idStatut de 3 → 5.
 * Réinitialise également les montants déjà saisis, puisqu'ils ne sont plus
 * valables pour un dossier rejeté :
 *   - passer_achat_et_paiement.montant_total → NULL
 *   - passer_achat_et_paiement_ligne.prix_reel → NULL
 *   - passer_achat_et_paiement_ligne.montant_total_ligne → NULL
 *
 * Paramètre : token (chiffré de idPAP)
 */
function rejeterDossier(PDO $bdBASI, dgController $dgController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValueDg('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $dgController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        // Motif de rejet obligatoire — conservé pour trace et affiché au
        // comptable (DRH) lors de la modification du dossier rejeté.
        $motifRejet = trim((string) inputValueDg('motifRejet', ''));
        if ($motifRejet === '') {
            echo json_encode(['status' => 'error', 'message' => 'Le motif de rejet est obligatoire.']);
            return;
        }

        $stmtC = $bdBASI->prepare("SELECT id, idTypePAP FROM passer_achat_et_paiement WHERE id = ? AND idStatut = 3 LIMIT 1");
        $stmtC->execute([$idPAP]);
        $dossier = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$dossier) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable ou déjà traité.']);
            return;
        }
        $estAchat = ((int)$dossier['idTypePAP'] === 1);

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        // 1) Dossier : idStatut 3 → 5, montant_total remis à NULL, motifRejet enregistré
        $bdBASI->prepare("
            UPDATE passer_achat_et_paiement SET idStatut = 5, montant_total = NULL, motifRejet = ? WHERE id = ?
        ")->execute([$motifRejet, $idPAP]);

        $bdBASI->prepare("
            INSERT INTO historique_passer_achat_et_paiement
                (idPAP, nom_commande, montant_total, idStatut, idTypePAP, dateCreation, id_mode_reglement,
                 id_mode_paiement, nb_tranche, idUtilisateur, motifRejet, motif, dateEnregistrement)
            SELECT id, nom_commande, montant_total, idStatut, idTypePAP, dateCreation, id_mode_reglement,
                   id_mode_paiement, nb_tranche, ?, ?, ?, ?
            FROM passer_achat_et_paiement
            WHERE id = ?
        ")->execute([
            $sessionUserId, $motifRejet,
            "Rejeté par le DG (par $sessionMatricule)",
            $dateEnregistrement, $idPAP,
        ]);

        // 2) Lignes : prix_reel et montant_total_ligne remis à NULL — UNIQUEMENT
        // pour l'achat (idTypePAP=1). Pour le paiement (idTypePAP=2),
        // montant_total_ligne contient le montant réellement saisi par
        // l'utilisateur (pas une valeur calculée par la DGA) : il ne doit
        // surtout pas être écrasé, sinon le formulaire de modification du
        // paiement se rouvre avec un montant vide.
        // (quantite_reelle n'est PAS touchée — elle a été saisie dès l'étape
        // "Passer commande", pas par la DGA/DFC, et reste valable).
        if ($estAchat) {
            $bdBASI->prepare("
                UPDATE passer_achat_et_paiement_ligne SET prix_reel = NULL, montant_total_ligne = NULL
                WHERE idPAP = ?
            ")->execute([$idPAP]);
        }

        $bdBASI->prepare("
            INSERT INTO historique_passer_achat_et_paiement_ligne
                (idPAPL, idPAP, idDL, prix_reel, quantite_reelle, montant_total_ligne, date_ajout,
                 id_unite, nb_unites, pieces_par_unite, id_statut_PAPL, motif, dateEnregistrement)
            SELECT id, idPAP, idDL, prix_reel, quantite_reelle, montant_total_ligne, date_ajout,
                   id_unite, nb_unites, pieces_par_unite, id_statut_PAPL, ?, ?
            FROM passer_achat_et_paiement_ligne
            WHERE idPAP = ?
        ")->execute([
            $estAchat
                ? "Rejet DG — prix réel et montant remis à NULL (par $sessionMatricule)"
                : "Rejet DG (par $sessionMatricule)",
            $dateEnregistrement, $idPAP,
        ]);

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Dossier rejeté avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[DG][rejeterDossier] ' . $e->getMessage());
        erreurSqlDg('Impossible de rejeter le dossier.');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   ROUTAGE
   1 = listerDossiers    (passer_achat_et_paiement.idStatut = 3)
   2 = detailDossier     (facture définitive OU justificatif(s) de paiement)
   3 = accepterDossier   (idStatut 3 → 4)
   4 = rejeterDossier    (idStatut 3 → 5, + réinitialisation des montants)
═══════════════════════════════════════════════════════════════════════════ */
try {
    switch ($option) {
        case 1:
            listerDossiers($bdBASI, $dgController);
            break;

        case 2:
            detailDossier($bdBASI, $dgController);
            break;

        case 3:
            accepterDossier($bdBASI, $dgController, $sessionUserId, $sessionMatricule);
            break;

        case 4:
            rejeterDossier($bdBASI, $dgController, $sessionUserId, $sessionMatricule);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Option '$option' non reconnue."]);
            exit;
    }
} catch (\Throwable $e) {
    error_log('[DG][Routage] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Une erreur inattendue est survenue.']);
    exit;
}