<?php
/**
 * dgaController.php
 * Contrôleur DGA (Direction Générale) : validation finale des commandes
 * d'achat déjà passées par le DRH — choix du fournisseur retenu parmi les
 * pro forma reçus, et saisie du prix réel de chaque ligne.
 *
 * ⚠️ Hypothèses de schéma (à confirmer / ajuster) :
 *   - `documents_pap` possède une clé primaire auto-incrémentée nommée `id`.
 *   - La facture définitive n'est plus téléversée à cette étape : le
 *     fournisseur retenu est simplement marqué (documents_pap.choix = 1),
 *     et c'est le pro forma déjà associé (documents_pap.doc) qui sert de
 *     pièce justificative affichée dans les détails de la commande.
 *   - `passer_achat_et_paiement.idStatut` est un pur statut de cycle de vie
 *     (jamais un indicateur de type — celui-ci est porté par idTypePAP) :
 *       1 = En attente | 2 = Validé (DGA) | 3 = Avis favorable (DFC)
 *       4 = Accepter | 5 = Rejetée | 6 = En paiement | 7 = Terminée
 *     validerCommande() ne gère que la transition 1 → 2.
 */

// ─── Capture de tout output parasite ─────────────────────────────────────────
ob_start();
session_start();
include_once('../../../bdBASI.php'); // ← ajuster selon la profondeur réelle de ce fichier
ob_end_clean();

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// ─── Vérification sessions obligatoires ──────────────────────────────────────
function checkSessionDga(): void {
    foreach (['tmpIdBASI',  'tmpMatricule'] as $key) {
        if (empty($_SESSION[$key])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['status'=>'error','code'=>'sessionExpired','message'=>'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }
    }
}
checkSessionDga();

$sessionUserId    = (int)$_SESSION['tmpIdBASI'];
$sessionMatricule = trim($_SESSION['tmpMatricule']);

// ─── Classe contrôleur ────────────────────────────────────────────────────────
class dgaController extends BDBASI
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
    $BDBASI        = new BDBASI();
    $bdBASI        = $BDBASI->connect();
    $dgaController = new dgaController();
} catch (\Throwable $e) {
    error_log('[DGA][Connexion] ' . $e->getMessage());
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
function getJsonBodyDga(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Récupère un paramètre en cherchant dans l'ordre : le body JSON brut,
 * $_POST, $_GET — fonctionne quel que soit le mode d'envoi utilisé.
 */
function inputValueDga(string $key, $default = null) {
    static $jsonBody = null;
    if ($jsonBody === null) $jsonBody = getJsonBodyDga();

    if (array_key_exists($key, $jsonBody)) return $jsonBody[$key];
    if (isset($_POST[$key]))               return $_POST[$key];
    if (isset($_GET[$key]))                return $_GET[$key];
    return $default;
}

function erreurSqlDga(string $message = "Erreur lors de l'accès à la base de données."): void {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$message]);
}

// ─── Lecture de l'option ──────────────────────────────────────────────────────
$option = isset($_GET['option']) ? trim($_GET['option']) : '';
if ($option==='' && isset($_POST['option'])) $option = trim($_POST['option']);
if ($option==='') {
    $jsonBodyOption = getJsonBodyDga();
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

define('UPLOAD_DIR_FACTURES_DEFINITIVES', __DIR__ . '/../../documents/commandes/factures_definitives'); // ← ajuster
define('UPLOAD_URL_FACTURES_DEFINITIVES', '/personnel/basi/documents/factures_definitives');                  // ← ajuster

/* ═══════════════════════════════════════════════════════════════════════════
   ACTIONS
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Liste des commandes à valider : passer_achat_et_paiement.idStatut = 1.
 */
function listerCommandes(PDO $bdBASI, dgaController $dgaController): void {
    try {
        $stmt = $bdBASI->query("
            SELECT
                p.id AS idPAP,
                p.nom_commande,
                p.montant_total,
                p.dateCreation,
                mr.mode_reglement AS mode_reglement_nom,
                mp.mode_paiement  AS mode_paiement_nom,
                p.nb_tranche,
                CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM passer_achat_et_paiement p
            LEFT JOIN mode_reglement mr ON p.id_mode_reglement = mr.id
            LEFT JOIN mode_paiement  mp ON p.id_mode_paiement  = mp.id
            LEFT JOIN utilisateurs   u  ON p.idUtilisateur     = u.id
            WHERE p.idStatut = 1 AND p.idTypePAP = 1
            ORDER BY p.dateCreation DESC
        ");
        if (!$stmt) throw new \RuntimeException('Requête de liste des commandes échouée.');

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['tmp'] = $dgaController->tokenencrypt($r['idPAP']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows]);
    } catch (\Throwable $e) {
        error_log('[DGA][listerCommandes] ' . $e->getMessage());
        erreurSqlDga('Impossible de charger la liste des commandes.');
    }
}

/**
 * Détail d'une commande pour la modale de validation :
 *   - En-tête (nom_commande, montant_total, dates, modes)
 *   - Fournisseurs proposés (documents_pap.statut = 1), avec infos fournisseur
 *   - Lignes de la commande (passer_achat_et_paiement_ligne), pour saisie du prix réel
 *
 * Paramètre : token (chiffré de idPAP)
 */
function detailCommande(PDO $bdBASI, dgaController $dgaController): void {
    try {
        $token = trim((string) inputValueDga('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $dgaController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmtC = $bdBASI->prepare("
            SELECT id AS idPAP, nom_commande, montant_total, dateCreation, idStatut
            FROM passer_achat_et_paiement
            WHERE id = ? AND idStatut = 1 AND idTypePAP = 1
            LIMIT 1
        ");
        $stmtC->execute([$idPAP]);
        $commande = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$commande) {
            echo json_encode(['status' => 'error', 'message' => 'Commande introuvable ou déjà traitée.']);
            return;
        }

        // Fournisseurs proposés (pro forma actifs)
        $stmtF = $bdBASI->prepare("
            SELECT dp.id, dp.doc, dp.id_fournisseur, dp.choix,
                   f.nomF, f.prenomF, f.entreprise, f.telF, f.emailF, f.adresseF, f.ville
            FROM documents_pap dp
            JOIN fournisseur f ON dp.id_fournisseur = f.idF
            WHERE dp.idPAP = ? AND dp.statut = 1
            ORDER BY dp.id ASC
        ");
        $stmtF->execute([$idPAP]);
        $fournisseurs = $stmtF->fetchAll(PDO::FETCH_ASSOC);

        // Lignes de la commande
        $stmtL = $bdBASI->prepare("
            SELECT
                papl.id AS idPAPL, papl.idDL, papl.prix_reel, papl.quantite_reelle,
                papl.montant_total_ligne, papl.id_unite, papl.nb_unites, papl.pieces_par_unite,
                lb.designation, lu.unite
            FROM passer_achat_et_paiement_ligne papl
            JOIN demandes_ligne dal ON papl.idDL = dal.idDL
            JOIN ligneBudget    lb  ON dal.idLB  = lb.id
            LEFT JOIN listeUnites lu ON papl.id_unite = lu.id
            WHERE papl.idPAP = ?
            ORDER BY papl.id ASC
        ");
        $stmtL->execute([$idPAP]);
        $lignes = $stmtL->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status'       => 'success',
            'commande'     => $commande,
            'fournisseurs' => $fournisseurs,
            'lignes'       => $lignes,
        ]);
    } catch (\Throwable $e) {
        error_log('[DGA][detailCommande] ' . $e->getMessage());
        erreurSqlDga('Impossible de charger le détail de la commande.');
    }
}

/**
 * Valide une commande :
 *   1. Marque le fournisseur choisi (documents_pap.choix = 1). Aucune
 *      facture définitive n'est téléversée ici — le pro forma déjà associé
 *      (documents_pap.doc) sert de pièce justificative.
 *   2. Enregistre le prix réel de chaque ligne, recalcule
 *      montant_total_ligne, puis le montant_total global de la commande.
 *
 * Requête JSON classique (aucun upload de fichier à cette étape).
 *
 * Champs attendus :
 *   - idPAP
 *   - id_document_choisi (documents_pap.id du fournisseur retenu)
 *   - lignes (JSON [{idPAPL, prix_reel}, ...])
 */
function validerCommande(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        $idPAP             = (int)($_POST['idPAP'] ?? 0);
        $idDocumentChoisi  = (int)($_POST['id_document_choisi'] ?? 0);

        if ($idPAP <= 0)            { echo json_encode(['status'=>'error','message'=>'Commande manquante.']); return; }
        if ($idDocumentChoisi <= 0) { echo json_encode(['status'=>'error','message'=>'Fournisseur à retenir manquant.']); return; }

        $lignesInput = json_decode($_POST['lignes'] ?? '[]', true);
        if (!is_array($lignesInput) || empty($lignesInput)) {
            echo json_encode(['status' => 'error', 'message' => 'Aucune ligne à mettre à jour.']);
            return;
        }

        // ── Vérifier que la commande existe et est encore "En attente" ────────
        $stmtC = $bdBASI->prepare("SELECT id FROM passer_achat_et_paiement WHERE id = ? AND idStatut = 1 AND idTypePAP = 1 LIMIT 1");
        $stmtC->execute([$idPAP]);
        if (!$stmtC->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Commande introuvable ou déjà traitée.']);
            return;
        }

        // ── Vérifier que le document choisi appartient bien à cette commande ──
        $stmtDoc = $bdBASI->prepare("SELECT id FROM documents_pap WHERE id = ? AND idPAP = ? AND statut = 1 LIMIT 1");
        $stmtDoc->execute([$idDocumentChoisi, $idPAP]);
        if (!$stmtDoc->fetch()) {
            echo json_encode(['status' => 'error', 'message' => "Le fournisseur sélectionné n'appartient pas à cette commande."]);
            return;
        }

        // ── Vérifier et calculer les lignes ────────────────────────────────────
        $stmtLigne = $bdBASI->prepare("
            SELECT id AS idPAPL, quantite_reelle FROM passer_achat_et_paiement_ligne
            WHERE id = ? AND idPAP = ?
            LIMIT 1
        ");
        $lignesValidees = [];
        $montantTotalCommande = 0.0;

        foreach ($lignesInput as $l) {
            $idPAPL   = (int)($l['idPAPL'] ?? 0);
            $prixReel = (float)($l['prix_reel'] ?? -1);

            if ($idPAPL <= 0) { echo json_encode(['status'=>'error','message'=>'Ligne invalide.']); return; }
            if ($prixReel <= 0) { echo json_encode(['status'=>'error','message'=>'Le prix réel doit être supérieur à 0 pour chaque ligne.']); return; }

            $stmtLigne->execute([$idPAPL, $idPAP]);
            $ligneDb = $stmtLigne->fetch(PDO::FETCH_ASSOC);
            $stmtLigne->closeCursor();
            if (!$ligneDb) {
                echo json_encode(['status' => 'error', 'message' => "Une des lignes n'appartient pas à cette commande (ligne #$idPAPL)."]);
                return;
            }

            $quantiteReelle     = (float)($ligneDb['quantite_reelle'] ?? 0);
            $montantTotalLigne  = $prixReel * $quantiteReelle;
            $montantTotalCommande += $montantTotalLigne;

            $lignesValidees[] = [
                'idPAPL'              => $idPAPL,
                'prix_reel'           => $prixReel,
                'montant_total_ligne' => $montantTotalLigne,
            ];
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        // ── Transaction ──────────────────────────────────────────────────────────
        $bdBASI->beginTransaction();

        // 1) Fournisseur retenu : la facture définitive n'est plus téléversée
        // à cette étape — le pro forma déjà associé (documents_pap.doc) fait
        // désormais office de pièce justificative affichée dans les détails.
        $bdBASI->prepare("
            UPDATE documents_pap SET choix = 1 WHERE id = ? AND idPAP = ?
        ")->execute([$idDocumentChoisi, $idPAP]);

        // 2) Lignes : prix réel + montant_total_ligne, avec historique
        // (id_statut_PAPL n'est pas modifié ici : 1 = créée par défaut,
        // 2 signifie que la ligne a été supprimée — rien à voir avec la validation)
        $stmtUpdateLigne = $bdBASI->prepare("
            UPDATE passer_achat_et_paiement_ligne SET prix_reel = ?, montant_total_ligne = ? WHERE id = ?
        ");
        $stmtHistoLigne = $bdBASI->prepare("
            INSERT INTO historique_passer_achat_et_paiement_ligne
                (idPAPL, idPAP, idDL, prix_reel, quantite_reelle, montant_total_ligne, date_ajout,
                 id_unite, nb_unites, pieces_par_unite, id_statut_PAPL, motif, dateEnregistrement)
            SELECT id, idPAP, idDL, prix_reel, quantite_reelle, montant_total_ligne, date_ajout,
                   id_unite, nb_unites, pieces_par_unite, id_statut_PAPL, ?, ?
            FROM passer_achat_et_paiement_ligne
            WHERE id = ?
        ");
        foreach ($lignesValidees as $l) {
            $stmtUpdateLigne->execute([$l['prix_reel'], $l['montant_total_ligne'], $l['idPAPL']]);
            $stmtHistoLigne->execute([
                "Validation DGA (par $sessionMatricule) — prix réel renseigné",
                $dateEnregistrement,
                $l['idPAPL'],
            ]);
        }

        // 3) Montant total de la commande + passage du statut 1 (en attente) → 2 (validée DGA)
        $bdBASI->prepare("
            UPDATE passer_achat_et_paiement SET montant_total = ?, idStatut = 2 WHERE id = ?
        ")->execute([$montantTotalCommande, $idPAP]);

        $bdBASI->prepare("
            INSERT INTO historique_passer_achat_et_paiement
                (idPAP, nom_commande, montant_total, idStatut, idTypePAP, dateCreation, id_mode_reglement,
                 id_mode_paiement, nb_tranche, idUtilisateur, motif, dateEnregistrement)
            SELECT id, nom_commande, ?, idStatut, idTypePAP, dateCreation, id_mode_reglement,
                   id_mode_paiement, nb_tranche, ?, ?, ?
            FROM passer_achat_et_paiement
            WHERE id = ?
        ")->execute([
            $montantTotalCommande, $sessionUserId,
            "Validation par la DGA (par $sessionMatricule) — fournisseur retenu et prix réels renseignés",
            $dateEnregistrement, $idPAP,
        ]);

        $bdBASI->commit();

        echo json_encode([
            'status'  => 'success',
            'message' => 'Commande validée avec succès.',
            'montant_total' => $montantTotalCommande,
        ]);
    } catch (\Throwable $e) {

        echo $e;
        die;
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[DGA][validerCommande] ' . $e->getMessage());
        erreurSqlDga('Impossible de valider la commande.');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   ROUTAGE
   1 = listerCommandes    (passer_achat_et_paiement.idStatut = 1)
   2 = detailCommande     (en-tête + fournisseurs proposés + lignes)
   3 = validerCommande    (choix fournisseur + facture définitive + prix réels)
═══════════════════════════════════════════════════════════════════════════ */
try {
    switch ($option) {
        case 1:
            listerCommandes($bdBASI, $dgaController);
            break;

        case 2:
            detailCommande($bdBASI, $dgaController);
            break;

        case 3:
            validerCommande($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Option '$option' non reconnue."]);
            exit;
    }
} catch (\Throwable $e) {
    error_log('[DGA][Routage] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Une erreur inattendue est survenue.']);
    exit;
}