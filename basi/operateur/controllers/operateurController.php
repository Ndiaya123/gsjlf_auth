<?php
/**
 * inventaireOperateurController.php
 * Module Gestion de l'inventaire — profil Opérateur : saisie terrain des
 * quantités, brouillon, soumission.
 *
 * ⚠️ Mêmes hypothèses de schéma que inventaireController.php.
 *
 * ⚠️ Interprétation retenue pour la condition d'affichage : la demande
 * indique littéralement "etat = 1 ET idStatut = 1", mais "Soumettre le
 * traitement" doit rester accessible à idStatut = 2 (après un brouillon) —
 * ce qui suppose que la page reste fonctionnelle à idStatut = 2 également.
 * Retenu ici : etat = 1 ET idStatut ∈ {1, 2} (à confirmer).
 */

// ─── Capture de tout output parasite ─────────────────────────────────────────
ob_start();
session_start();
include_once('../../../bdBASI.php'); // ← ajuster selon la profondeur réelle du fichier
ob_end_clean();

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// ─── Vérification sessions obligatoires ──────────────────────────────────────
function checkSessionInvOp(): void {
    foreach (['tmpIdBASI', 'tmpMatricule'] as $key) {
        if (empty($_SESSION[$key])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['status'=>'error','code'=>'sessionExpired','message'=>'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }
    }
}
checkSessionInvOp();

$sessionUserId    = (int)$_SESSION['tmpIdBASI'];
$sessionMatricule = trim($_SESSION['tmpMatricule']);

// ─── Classe contrôleur ────────────────────────────────────────────────────────
class inventaireOperateurController extends BDBASI
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
    $basiController = new inventaireOperateurController();
} catch (\Throwable $e) {
    error_log('[InventaireOp][Connexion] ' . $e->getMessage());
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

function getJsonBodyInvOp(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
function inputValueInvOp(string $key, $default = null) {
    static $jsonBody = null;
    if ($jsonBody === null) $jsonBody = getJsonBodyInvOp();
    if (array_key_exists($key, $jsonBody)) return $jsonBody[$key];
    if (isset($_POST[$key]))               return $_POST[$key];
    if (isset($_GET[$key]))                return $_GET[$key];
    return $default;
}
function erreurSqlInvOp(string $message = "Erreur lors de l'accès à la base de données."): void {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$message]);
}

// ─── Lecture de l'option ──────────────────────────────────────────────────────
$option = isset($_GET['option']) ? trim($_GET['option']) : '';
if ($option==='' && isset($_POST['option'])) $option = trim($_POST['option']);
if ($option==='') {
    $jsonBodyOption = getJsonBodyInvOp();
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
   HELPERS (dupliqués volontairement — même logique que inventaireController.php,
   contrôleurs indépendants par profil, cf. convention établie dans le projet)
═══════════════════════════════════════════════════════════════════════════ */

function insererHistoriqueInventaireOp(PDO $bdBASI, int $idI, string $motif, string $dateEnregistrement): void {
    $bdBASI->prepare("
        INSERT INTO historique_inventaire
            (idI, reference, etat, idStatut, dateDebut, dateFin, dateSoumission, observation_operateur, observation_comptable, idUtilisateur, motif, dateEnregistrement)
        SELECT id, reference, etat, idStatut, dateDebut, dateFin, dateSoumission, observation_operateur, observation_comptable, idUtilisateur, ?, ?
        FROM inventaire
        WHERE id = ?
    ")->execute([$motif, $dateEnregistrement, $idI]);
}

function insererHistoriqueInventaireProduitOp(PDO $bdBASI, int $idIP, string $motif, string $dateEnregistrement): void {
    $bdBASI->prepare("
        INSERT INTO historique_inventaire_produit
            (idIP, idI, idP, quantite_systeme, quantite_operateur, quantite_valide, motif, dateEnregistrement)
        SELECT id, idI, idP, quantite_systeme, quantite_operateur, quantite_valide, ?, ?
        FROM inventaire_produit
        WHERE id = ?
    ")->execute([$motif, $dateEnregistrement, $idIP]);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ACTIONS
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Inventaire en cours pour l'opérateur — etat = 1 ET idStatut ∈ {1, 2}
 * (cf. note d'interprétation en tête de fichier). Aucun inventaire visible
 * en dehors de cette fenêtre.
 */
function detailInventaireOperateur(PDO $bdBASI, inventaireOperateurController $basiController): void {
    try {
        $stmtI = $bdBASI->query("
            SELECT id, reference, etat, idStatut, dateDebut
            FROM inventaire
            WHERE etat = 1 AND idStatut IN (1, 2)
            ORDER BY dateDebut DESC
            LIMIT 1
        ");
        $inventaire = $stmtI ? $stmtI->fetch(PDO::FETCH_ASSOC) : null;
        if (!$inventaire) {
            echo json_encode(['status' => 'success', 'inventaire' => null]);
            return;
        }

        $idI = (int) $inventaire['id'];
        $inventaire['tmp'] = $basiController->tokenencrypt($idI);

        $stmtLignes = $bdBASI->prepare("
            SELECT ip.id AS idIP, ip.idP, ip.quantite_systeme, ip.quantite_operateur,
                   p.nomproduit, p.code_produit, p.id_statut AS produit_id_statut,
                   sc.nom_sous_categorie, c.nom_categorie
            FROM inventaire_produit ip
            JOIN product p ON ip.idP = p.idP
            JOIN souscategorie sc ON p.id_Sous_categorie = sc.id
            JOIN categorie c ON sc.categorie_id = c.id
            WHERE ip.idI = ?
            ORDER BY c.nom_categorie ASC, sc.nom_sous_categorie ASC, p.nomproduit ASC
        ");
        $stmtLignes->execute([$idI]);
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
        foreach ($lignes as &$l) {
            $l['produit_actif'] = ((int) $l['produit_id_statut'] === 1);
        }
        unset($l);

        $inventaire['lignes'] = $lignes;
        echo json_encode(['status' => 'success', 'inventaire' => $inventaire]);
    } catch (\Throwable $e) {
        error_log('[InventaireOp][detailInventaireOperateur] ' . $e->getMessage());
        erreurSqlInvOp("Impossible de charger l'inventaire en cours.");
    }
}

/**
 * Enregistre les quantités saisies (quantite_operateur) sans changer le
 * statut au-delà de 2 (Brouillon). Accessible tant que idStatut ∈ {1, 2}.
 *
 * Champs attendus : token, lignes: [{ idIP, quantite_operateur }, ...]
 */
function sauvegarderBrouillon(PDO $bdBASI, inventaireOperateurController $basiController, string $sessionMatricule): void {
    try {
        [$idI, $erreur] = resoudreInventaireModifiableOp($bdBASI, $basiController);
        if ($erreur) { echo json_encode(['status' => 'error', 'message' => $erreur]); return; }

        $lignes = inputValueInvOp('lignes', []);
        if (!is_array($lignes)) $lignes = [];

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $motif = "Sauvegarde du brouillon (par $sessionMatricule)";

        $bdBASI->beginTransaction();
        enregistrerQuantitesOperateur($bdBASI, $idI, $lignes, $motif, $dateEnregistrement);

        $bdBASI->prepare("UPDATE inventaire SET idStatut = 2 WHERE id = ? AND idStatut = 1")->execute([$idI]);
        insererHistoriqueInventaireOp($bdBASI, $idI, $motif, $dateEnregistrement);

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Brouillon enregistré avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[InventaireOp][sauvegarderBrouillon] ' . $e->getMessage());
        erreurSqlInvOp("Impossible d'enregistrer le brouillon.");
    }
}

/**
 * Soumission finale (idStatut → 3), avec observation obligatoire.
 *
 * Champs attendus : token, lignes: [{ idIP, quantite_operateur }, ...],
 * observation
 */
function soumettreTraitement(PDO $bdBASI, inventaireOperateurController $basiController, string $sessionMatricule): void {
    try {
        [$idI, $erreur] = resoudreInventaireModifiableOp($bdBASI, $basiController);
        if ($erreur) { echo json_encode(['status' => 'error', 'message' => $erreur]); return; }

        $observation = trim((string) inputValueInvOp('observation', ''));
        if ($observation === '') {
            echo json_encode(['status' => 'error', 'message' => "L'observation est obligatoire pour soumettre le traitement."]);
            return;
        }

        $lignes = inputValueInvOp('lignes', []);
        if (!is_array($lignes)) $lignes = [];

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $motif = "Soumission du traitement (par $sessionMatricule)";

        $bdBASI->beginTransaction();
        enregistrerQuantitesOperateur($bdBASI, $idI, $lignes, $motif, $dateEnregistrement);

        // Aucune ligne ne doit rester à quantite_operateur = NULL (0 est en
        // revanche une valeur valide et acceptée).
        $stmtNonRemplies = $bdBASI->prepare("SELECT COUNT(*) AS n FROM inventaire_produit WHERE idI = ? AND quantite_operateur IS NULL");
        $stmtNonRemplies->execute([$idI]);
        $nombreNonRemplies = (int) ($stmtNonRemplies->fetch(PDO::FETCH_ASSOC)['n'] ?? 0);
        if ($nombreNonRemplies > 0) {
            $bdBASI->rollBack();
            echo json_encode([
                'status'  => 'error',
                'message' => "Impossible de soumettre : $nombreNonRemplies produit(s) n'ont pas de quantité renseignée (0 est accepté, mais le champ ne peut pas être vide).",
            ]);
            return;
        }

        $bdBASI->prepare("
            UPDATE inventaire
            SET idStatut = 3, dateSoumission = ?, observation_operateur = ?
            WHERE id = ?
        ")->execute([$dateEnregistrement, $observation, $idI]);
        insererHistoriqueInventaireOp($bdBASI, $idI, $motif, $dateEnregistrement);

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Traitement soumis avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[InventaireOp][soumettreTraitement] ' . $e->getMessage());
        erreurSqlInvOp("Impossible de soumettre le traitement.");
    }
}

/**
 * Vérifie que le token pointe vers un inventaire encore modifiable par
 * l'opérateur (etat = 1, idStatut ∈ {1, 2}). Retourne [idI, null] si OK,
 * [0, messageErreur] sinon.
 */
function resoudreInventaireModifiableOp(PDO $bdBASI, inventaireOperateurController $basiController): array {
    $token = trim((string) inputValueInvOp('token', ''));
    if ($token === '') return [0, 'Token manquant.'];
    $idI = (int) $basiController->tokendecrypt($token);
    if ($idI <= 0) return [0, 'Token invalide.'];

    $stmt = $bdBASI->prepare("SELECT id, etat, idStatut FROM inventaire WHERE id = ? LIMIT 1");
    $stmt->execute([$idI]);
    $inventaire = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$inventaire) return [0, 'Inventaire introuvable.'];
    if ((int)$inventaire['etat'] !== 1 || !in_array((int)$inventaire['idStatut'], [1, 2], true)) {
        return [0, "Cet inventaire n'est plus modifiable."];
    }
    return [$idI, null];
}

/** Enregistre les quantités saisies pour chaque ligne + historise. */
function enregistrerQuantitesOperateur(PDO $bdBASI, int $idI, array $lignes, string $motif, string $dateEnregistrement): void {
    $stmtActives = $bdBASI->prepare("SELECT id FROM inventaire_produit WHERE idI = ?");
    $stmtActives->execute([$idI]);
    $idsValides = array_column($stmtActives->fetchAll(PDO::FETCH_ASSOC), 'id');
    $idsValides = array_map('intval', $idsValides);

    $stmtUpdate = $bdBASI->prepare("UPDATE inventaire_produit SET quantite_operateur = ?, dateEnregistrement = ? WHERE id = ?");
    foreach ($lignes as $l) {
        $idIP = (int) ($l['idIP'] ?? 0);
        if (!in_array($idIP, $idsValides, true)) continue;
        $quantite = $l['quantite_operateur'] ?? null;
        if ($quantite === null || $quantite === '') continue;
        $stmtUpdate->execute([(float) $quantite, $dateEnregistrement, $idIP]);
        insererHistoriqueInventaireProduitOp($bdBASI, $idIP, $motif, $dateEnregistrement);
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   ROUTAGE
   1 = detailInventaireOperateur (inventaire en cours, ou null si aucun)
   2 = sauvegarderBrouillon      (idStatut 1 → 2)
   3 = soumettreTraitement       (idStatut 1|2 → 3, observation obligatoire)
═══════════════════════════════════════════════════════════════════════════ */
try {
    switch ($option) {
        case 1:
            detailInventaireOperateur($bdBASI, $basiController);
            break;

        case 2:
            sauvegarderBrouillon($bdBASI, $basiController, $sessionMatricule);
            break;

        case 3:
            soumettreTraitement($bdBASI, $basiController, $sessionMatricule);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Option '$option' non reconnue."]);
            exit;
    }
} catch (\Throwable $e) {
    error_log('[InventaireOp][Routage] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Une erreur inattendue est survenue.']);
    exit;
}