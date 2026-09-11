<?php
/**
 * caisseCaissierController.php
 * Module Comptabilité — profil Caissier : consultation de ses propres
 * alimentations de caisse, et décision (Accepter / Rejeter) sur celles en
 * attente.
 *
 * ⚠️ Hypothèses de schéma (mêmes que caisseController.php — à confirmer) :
 *   - `caisse_alimentation` : id, numero, montant_total, idCaissier,
 *     date_alimentation, idTypeAC, idStatut, commentaire, idUtilisateur,
 *     dateEnregistrement.
 *   - `historique_caisse_alimentation` : id_caisse_alimentation, numero,
 *     montant_total, commentaire, date_alimentation, idTypeAC, idStatut,
 *     idCaissier, idUtilisateur, motif, dateEnregistrement.
 *   - Le caissier connecté est identifié par idCaissier = $_SESSION['tmpIdBASI']
 *     (utilisateurs.id). ⚠️ À confirmer : si les caissiers se connectent avec
 *     un identifiant différent de leur utilisateurs.id, adapter en conséquence.
 *   - idTypeAC : 1 = Alimentation initiale, 2 = Approvisionnement.
 *   - idStatut : 1 = En attente, 2 = Confirmée, 3 = Rejetée.
 *   - Accepter / Rejeter ne sont autorisés que si idStatut = 1 ET
 *     date_alimentation = date du jour (vérifié côté serveur, jamais côté
 *     client seul).
 */

// ─── Capture de tout output parasite ─────────────────────────────────────────
ob_start();
session_start();
include_once('../../../bdBASI.php'); // ← ajuster selon la profondeur réelle du fichier
ob_end_clean();

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// ─── Vérification sessions obligatoires ──────────────────────────────────────
function checkSessionCaissier(): void {
    foreach (['tmpIdBASI', 'tmpMatricule'] as $key) {
        if (empty($_SESSION[$key])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['status'=>'error','code'=>'sessionExpired','message'=>'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }
    }
}
checkSessionCaissier();

$sessionUserId    = (int)$_SESSION['tmpIdBASI'];
$sessionMatricule = trim($_SESSION['tmpMatricule']);

// ─── Classe contrôleur ────────────────────────────────────────────────────────
class caisseCaissierController extends BDBASI
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
    $basiController = new caisseCaissierController();
} catch (\Throwable $e) {
    error_log('[CaisseCaissier][Connexion] ' . $e->getMessage());
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
function getJsonBodyCaissier(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Récupère un paramètre en cherchant dans l'ordre : le body JSON brut,
 * $_POST, $_GET — fonctionne quel que soit le mode d'envoi utilisé.
 */
function inputValueCaissier(string $key, $default = null) {
    static $jsonBody = null;
    if ($jsonBody === null) $jsonBody = getJsonBodyCaissier();

    if (array_key_exists($key, $jsonBody)) return $jsonBody[$key];
    if (isset($_POST[$key]))               return $_POST[$key];
    if (isset($_GET[$key]))                return $_GET[$key];
    return $default;
}

function erreurSqlCaissier(string $message = "Erreur lors de l'accès à la base de données."): void {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$message]);
}

// ─── Lecture de l'option ──────────────────────────────────────────────────────
$option = isset($_GET['option']) ? trim($_GET['option']) : '';
if ($option==='' && isset($_POST['option'])) $option = trim($_POST['option']);
if ($option==='') {
    $jsonBodyOption = getJsonBodyCaissier();
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

// Modes de règlement soumis à vérification de solde journalier (alimentés via
// caisse_alimentation). Tout id_mode_reglement absent de cette liste est
// considéré comme "Cas 2" (Chèque/Virement/etc. — Banque + Numéro requis).
define('MODES_SOLDE_JOURNALIER', [1, 4, 5]); // Liquide, Wave, Orange Money

define('UPLOAD_DIR_RECUS_PAIEMENT', __DIR__ . '/../../documents/commandes'); // ← ajuster
define('UPLOAD_URL_RECUS_PAIEMENT', '/personnel/basi/documents');

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Profil Caissier

   Le caissier ne voit que les alimentations qui le concernent (idCaissier =
   utilisateur connecté) et peut Accepter / Rejeter une alimentation en
   attente, uniquement le jour même de l'alimentation.
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Liste des alimentations du caissier connecté (mêmes filtres que la vue
 * Comptable : intervalle d'années + statut).
 */
function listerAlimentationsCaissier(PDO $bdBASI, caisseCaissierController $basiController, int $sessionUserId): void {
    try {
        $anneeCourante = (int) date('Y');
        $anneeFin = (int) inputValueCaissier('anneeFin', $anneeCourante);
        if ($anneeFin <= 0) $anneeFin = $anneeCourante;

        $anneeDebutBrute = trim((string) inputValueCaissier('anneeDebut', ''));
        $anneeDebut = ($anneeDebutBrute === '') ? $anneeFin : (int) $anneeDebutBrute;
        if ($anneeDebut <= 0) $anneeDebut = $anneeFin;
        if ($anneeDebut > $anneeFin) { [$anneeDebut, $anneeFin] = [$anneeFin, $anneeDebut]; }

        $statutFiltre = trim((string) inputValueCaissier('statut', ''));

        $sql = "
            SELECT
                ca.id,
                ca.numero,
                ca.montant_total,
                ca.commentaire,
                ca.date_alimentation,
                ca.idTypeAC,
                ca.idStatut,
                ca.idUtilisateur,
                CONCAT(uu.prenom, ' ', uu.nom) AS utilisateur,
                ca.dateEnregistrement
            FROM caisse_alimentation ca
            JOIN utilisateurs uu ON ca.idUtilisateur = uu.id
            WHERE ca.idCaissier = ?
              AND YEAR(ca.date_alimentation) BETWEEN ? AND ?
        ";
        $params = [$sessionUserId, $anneeDebut, $anneeFin];

        if ($statutFiltre !== '' && in_array($statutFiltre, ['1', '2', '3'], true)) {
            $sql .= " AND ca.idStatut = ?";
            $params[] = (int) $statutFiltre;
        }

        $sql .= " ORDER BY ca.date_alimentation DESC, ca.id DESC";

        $stmt = $bdBASI->prepare($sql);
        if (!$stmt) throw new \RuntimeException('Requête de liste des alimentations (caissier) échouée.');
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['id']);
            // Accepter / Rejeter disponibles uniquement si En attente ET date = aujourd'hui.
            $r['peutDecider'] = (
                    (int)$r['idStatut'] === 1
                    && $r['date_alimentation'] === date('Y-m-d')
            );
        }
        unset($r);

        // ── Statistiques du jour (idStatut = 2, Confirmée uniquement — les
        // alimentations en attente/rejetées ne représentent pas des fonds
        // effectivement disponibles en caisse) ─────────────────────────────
        $stmtWaveOm = $bdBASI->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN cal.id_mode_reglement = 1 THEN cal.montant ELSE 0 END), 0) AS montant_liquide_jour,
                COALESCE(SUM(CASE WHEN cal.id_mode_reglement = 4 THEN cal.montant ELSE 0 END), 0) AS montant_wave_jour,
                COALESCE(SUM(CASE WHEN cal.id_mode_reglement = 5 THEN cal.montant ELSE 0 END), 0) AS montant_om_jour
            FROM caisse_alimentation_ligne cal
            JOIN caisse_alimentation ca ON cal.id_caisse_alimentation = ca.id
            WHERE ca.idCaissier = ? AND ca.date_alimentation = ? AND ca.idStatut = 2
        ");
        $stmtWaveOm->execute([$sessionUserId, date('Y-m-d')]);
        $statsWaveOm = $stmtWaveOm->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmtTotal = $bdBASI->prepare("
            SELECT COALESCE(SUM(montant_total), 0) AS total
            FROM caisse_alimentation
            WHERE idCaissier = ? AND date_alimentation = ? AND idStatut = 2
        ");
        $stmtTotal->execute([$sessionUserId, date('Y-m-d')]);
        $montantTotalJour = (float) ($stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $stats = [
                'montant_total_jour'   => $montantTotalJour,
                'montant_liquide_jour' => (float) ($statsWaveOm['montant_liquide_jour'] ?? 0),
                'montant_wave_jour'    => (float) ($statsWaveOm['montant_wave_jour'] ?? 0),
                'montant_om_jour'      => (float) ($statsWaveOm['montant_om_jour'] ?? 0),
        ];

        echo json_encode(['status' => 'success', 'data' => $rows, 'stats' => $stats]);
    } catch (\Throwable $e) {
        error_log('[CaisseCaissier][listerAlimentationsCaissier] ' . $e->getMessage());
        erreurSqlCaissier('Impossible de charger la liste des alimentations.');
    }
}

/**
 * Vérifie et retourne l'alimentation ciblée si elle est décidable par ce
 * caissier (idCaissier = session, idStatut = 1, date = aujourd'hui), sinon
 * renvoie null après avoir déjà émis la réponse d'erreur JSON.
 */
function chargerAlimentationDecidable(PDO $bdBASI, caisseCaissierController $basiController, int $sessionUserId): ?array {
    $token = trim((string) inputValueCaissier('token', ''));
    if ($token === '') {
        echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
        return null;
    }
    $id = (int) $basiController->tokendecrypt($token);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
        return null;
    }

    $stmt = $bdBASI->prepare("
        SELECT id, numero, montant_total, commentaire, date_alimentation, idTypeAC, idStatut, idCaissier, idUtilisateur
        FROM caisse_alimentation
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $alimentation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alimentation || (int)$alimentation['idCaissier'] !== $sessionUserId) {
        echo json_encode(['status' => 'error', 'message' => 'Alimentation introuvable.']);
        return null;
    }
    if ((int)$alimentation['idStatut'] !== 1) {
        echo json_encode(['status' => 'error', 'message' => 'Seule une alimentation En attente peut être acceptée ou rejetée.']);
        return null;
    }
    if ($alimentation['date_alimentation'] !== date('Y-m-d')) {
        echo json_encode(['status' => 'error', 'message' => "Cette action n'est possible que le jour même de l'alimentation."]);
        return null;
    }

    return $alimentation;
}

/**
 * Le caissier accepte une alimentation en attente (idStatut 1 → 2).
 * Paramètre : token (chiffré de caisse_alimentation.id)
 */
function accepterAlimentationCaisse(PDO $bdBASI, caisseCaissierController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $alimentation = chargerAlimentationDecidable($bdBASI, $basiController, $sessionUserId);
        if (!$alimentation) return;
        $id = (int) $alimentation['id'];

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        $bdBASI->prepare("UPDATE caisse_alimentation SET idStatut = 2 WHERE id = ?")->execute([$id]);

        $bdBASI->prepare("
            INSERT INTO historique_caisse_alimentation
                (id_caisse_alimentation, numero, montant_total, commentaire, date_alimentation, idTypeAC, idStatut, idCaissier, idUtilisateur, motif, dateEnregistrement)
            SELECT id, numero, montant_total, commentaire, date_alimentation, idTypeAC, idStatut, idCaissier, idUtilisateur, ?, ?
            FROM caisse_alimentation
            WHERE id = ?
        ")->execute(["Acceptée par le caissier (par $sessionMatricule)", $dateEnregistrement, $id]);

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Alimentation acceptée avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[CaisseCaissier][accepterAlimentationCaisse] ' . $e->getMessage());
        erreurSqlCaissier("Impossible d'accepter cette alimentation.");
    }
}

/**
 * Le caissier rejette une alimentation en attente (idStatut 1 → 3).
 * Paramètres : token (chiffré de caisse_alimentation.id), motif (optionnel)
 */
function rejeterAlimentationCaisse(PDO $bdBASI, caisseCaissierController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $alimentation = chargerAlimentationDecidable($bdBASI, $basiController, $sessionUserId);
        if (!$alimentation) return;
        $id = (int) $alimentation['id'];

        $motifRej = trim((string) inputValueCaissier('motif', ''));
        if ($motifRej === '') {
            echo json_encode(['status' => 'error', 'message' => 'Le motif du rejet est obligatoire.']);
            return;
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        $bdBASI->prepare("UPDATE caisse_alimentation SET idStatut = 3, motifRej = ? WHERE id = ?")->execute([$motifRej, $id]);

        $motif = "Rejetée par le caissier (par $sessionMatricule)";
        $bdBASI->prepare("
            INSERT INTO historique_caisse_alimentation
                (id_caisse_alimentation, numero, montant_total, commentaire, date_alimentation, idTypeAC, idStatut, idCaissier, idUtilisateur, motif, motifRej, dateEnregistrement)
            SELECT id, numero, montant_total, commentaire, date_alimentation, idTypeAC, idStatut, idCaissier, idUtilisateur, ?, motifRej, ?
            FROM caisse_alimentation
            WHERE id = ?
        ")->execute([$motif, $dateEnregistrement, $id]);

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Alimentation rejetée avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[CaisseCaissier][rejeterAlimentationCaisse] ' . $e->getMessage());
        erreurSqlCaissier("Impossible de rejeter cette alimentation.");
    }
}

/**
 * Détail d'une alimentation du caissier connecté : en-tête + répartition des
 * montants par mode de règlement (Liquide / Orange Money / Wave).
 * Paramètre : token (chiffré de caisse_alimentation.id)
 */
function detailAlimentationCaissier(PDO $bdBASI, caisseCaissierController $basiController, int $sessionUserId): void {
    try {
        $token = trim((string) inputValueCaissier('token', ''));
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
            SELECT id, numero, montant_total, commentaire, date_alimentation, idTypeAC, idStatut, idCaissier, motifRej
            FROM caisse_alimentation
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $alimentation = $stmt->fetch(PDO::FETCH_ASSOC);

        // Un caissier ne peut consulter que ses propres alimentations.
        if (!$alimentation || (int)$alimentation['idCaissier'] !== $sessionUserId) {
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
        error_log('[CaisseCaissier][detailAlimentationCaissier] ' . $e->getMessage());
        erreurSqlCaissier("Impossible de charger le détail de l'alimentation.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Paiement des commandes (achat / paiement)

   ⚠️ Hypothèses de schéma (à confirmer / ajuster) :
   - `passer_achat_et_paiement` : pas de colonne `numero` distincte — le
     numéro affiché est `nom_commande`. Colonne `montant_paye` À AJOUTER
     (NOT NULL DEFAULT 0) :
       ALTER TABLE passer_achat_et_paiement ADD COLUMN montant_paye DECIMAL(15,2) NOT NULL DEFAULT 0;
       ALTER TABLE historique_passer_achat_et_paiement ADD COLUMN montant_paye DECIMAL(15,2) NOT NULL DEFAULT 0;
   - `tranches` : colonnes `paiement` (0/1, NOUVELLE) et `date_paiement`
     (NOUVELLE) à ajouter :
       ALTER TABLE tranches ADD COLUMN paiement TINYINT NOT NULL DEFAULT 0;
       ALTER TABLE tranches ADD COLUMN date_paiement DATETIME NULL;
   - `paiement_pap` (NOUVELLE TABLE) : idPaiement (PK auto), idPAP, montant,
     mode_reglement, banque, numero_cheque_virement (NOUVELLE colonne,
     nécessaire pour stocker le numéro de chèque/virement demandé), recu,
     id_tranche (NOUVELLE colonne, NULL si paiement complet hors tranche),
     date_paiement, idCaissier.
   - `historique_paiement_pap` : mêmes colonnes que paiement_pap + motif,
     dateEnregistrement.
   - `typeBanque` (et non `banque`) : id, type (nom de la banque).
   - idStatut de passer_achat_et_paiement éligible au paiement : 6
     (En paiement) uniquement — une commande Acceptée (4) doit d'abord être
     envoyée à la caisse (4 → 6) avant de pouvoir être payée. Passage à 7
     (Terminée) selon idTypePAP :
       • idTypePAP = 2 (Passer au paiement) : dès que montant_paye =
         montant_total, passage automatique à 7.
       • idTypePAP = 1 (Passer commande / achat) : passage à 7 uniquement si
         montant_paye = montant_total ET `livraison` = 0 (colonne À AJOUTER
         si absente : ALTER TABLE passer_achat_et_paiement ADD COLUMN
         livraison TINYINT NOT NULL DEFAULT 0). ⚠️ Numérotation 1=Achat /
         2=Paiement confirmée cohérente avec le reste du projet.
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Montant alloué (caisse_alimentation confirmées, idStatut = 2) pour un mode
 * de règlement donné, à la date du jour — toutes caisses confondues.
 */
function montantAlloueJour(PDO $bdBASI, int $idModeReglement): float {
    $stmt = $bdBASI->prepare("
        SELECT COALESCE(SUM(cal.montant), 0) AS total
        FROM caisse_alimentation_ligne cal
        JOIN caisse_alimentation ca ON cal.id_caisse_alimentation = ca.id
        WHERE ca.date_alimentation = CURDATE() AND ca.idStatut = 2 AND cal.id_mode_reglement = ?
    ");
    $stmt->execute([$idModeReglement]);
    return (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

/**
 * Montant déjà utilisé (paiement_pap) pour un mode de règlement donné, à la
 * date du jour.
 */
function montantUtiliseJour(PDO $bdBASI, int $idModeReglement): float {
    $stmt = $bdBASI->prepare("
        SELECT COALESCE(SUM(montant), 0) AS total
        FROM paiement_pap
        WHERE DATE(date_paiement) = CURDATE() AND mode_reglement = ?
    ");
    $stmt->execute([$idModeReglement]);
    return (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

/**
 * Première tranche non encore payée (paiement = 0) d'un dossier, triée par
 * ordre — ou null s'il n'y en a aucune.
 */
function trancheAPayer(PDO $bdBASI, int $idPAP, float $montantTotal): ?array {
    $stmt = $bdBASI->prepare("
        SELECT id, ordre, pourcentage, paiement
        FROM tranches
        WHERE idPAP = ? AND statut = 1
        ORDER BY ordre ASC
    ");
    $stmt->execute([$idPAP]);
    $tranches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tranches as $t) {
        if ((int)$t['paiement'] === 0) {
            $t['montant'] = round($montantTotal * ((float)$t['pourcentage'] / 100), 2);
            return $t;
        }
    }
    return null;
}

/**
 * Vrai si un arrêt de caisse existe déjà pour ce caissier à la date du jour
 * (date_alimentation = aujourd'hui) — ferme définitivement la caisse pour
 * la journée : plus aucun paiement ne peut être enregistré.
 */
function arreteCaisseExisteJour(PDO $bdBASI, int $idCaissier): bool {
    $stmt = $bdBASI->prepare("
        SELECT id FROM alimentation_arrete_caisse WHERE idCaissier = ? AND date_alimentation = CURDATE() LIMIT 1
    ");
    $stmt->execute([$idCaissier]);
    return (bool) $stmt->fetch();
}

/**
 * Liste des commandes (achat + paiement) restant à régler : idStatut = 6
 * ET montant_paye < montant_total.
 */
function listerCommandesAPayer(PDO $bdBASI, caisseCaissierController $basiController, int $sessionUserId): void {
    try {
        $stmt = $bdBASI->query("
            SELECT
                p.id, p.nom_commande AS numero, p.nom_commande, p.montant_total, p.montant_paye,
                p.idTypePAP, p.idStatut, p.id_mode_reglement, p.id_mode_paiement, p.nb_tranche,
                mr.mode_reglement AS mode_reglement_nom,
                mp.mode_paiement  AS mode_paiement_nom,
                CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM passer_achat_et_paiement p
            LEFT JOIN mode_reglement mr ON p.id_mode_reglement = mr.id
            LEFT JOIN mode_paiement  mp ON p.id_mode_paiement  = mp.id
            LEFT JOIN utilisateurs   u  ON p.idUtilisateur     = u.id
            WHERE p.idStatut = 6
              AND p.montant_paye < p.montant_total
            ORDER BY p.dateCreation ASC
        ");
        if (!$stmt) throw new \RuntimeException('Requête de liste des commandes à payer échouée.');

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['tmp']             = $basiController->tokenencrypt($r['id']);
            $r['montant_restant'] = (float)$r['montant_total'] - (float)$r['montant_paye'];
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows, 'arreteEffectue' => arreteCaisseExisteJour($bdBASI, $sessionUserId)]);
    } catch (\Throwable $e) {

        echo $e;
        die;
        error_log('[CaisseCaissier][listerCommandesAPayer] ' . $e->getMessage());
        erreurSqlCaissier('Impossible de charger la liste des commandes à payer.');
    }
}

/**
 * Détail d'une commande pour l'écran de paiement : infos générales, mode de
 * règlement/modalité préchargés, montant à régler à cette étape.
 */
function detailCommandePaiement(PDO $bdBASI, caisseCaissierController $basiController, int $sessionUserId): void {
    try {
        $token = trim((string) inputValueCaissier('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        // Bloque dès l'ouverture de l'écran de paiement (pas seulement à la
        // soumission) si l'arrêt de caisse du jour est déjà effectué.
        if (arreteCaisseExisteJour($bdBASI, $sessionUserId)) {
            echo json_encode(['status' => 'error', 'message' => "L'arrêt de caisse a déjà été effectué aujourd'hui : aucun paiement n'est possible."]);
            return;
        }

        $stmt = $bdBASI->prepare("
            SELECT
                p.id, p.nom_commande AS numero, p.nom_commande, p.montant_total, p.montant_paye,
                p.idTypePAP, p.idStatut, p.id_mode_reglement, p.id_mode_paiement, p.nb_tranche,
                mr.mode_reglement AS mode_reglement_nom,
                mp.mode_paiement  AS mode_paiement_nom
            FROM passer_achat_et_paiement p
            LEFT JOIN mode_reglement mr ON p.id_mode_reglement = mr.id
            LEFT JOIN mode_paiement  mp ON p.id_mode_paiement  = mp.id
            WHERE p.id = ? AND p.idStatut = 6
            LIMIT 1
        ");
        $stmt->execute([$idPAP]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$commande) {
            echo json_encode(['status' => 'error', 'message' => 'Commande introuvable ou déjà soldée.']);
            return;
        }

        $montantTotal   = (float)$commande['montant_total'];
        $montantPaye    = (float)$commande['montant_paye'];
        $montantRestant = $montantTotal - $montantPaye;

        $estParTranche = ((int)$commande['id_mode_paiement'] === 3);
        $tranche = null;
        if ($estParTranche) {
            $tranche = trancheAPayer($bdBASI, $idPAP, $montantTotal);
            if (!$tranche) {
                echo json_encode(['status' => 'error', 'message' => 'Toutes les tranches de ce dossier sont déjà réglées.']);
                return;
            }
            $montantAPayer = $tranche['montant'];
        } else {
            $montantAPayer = $montantRestant;
        }

        $idModeReglement = (int)$commande['id_mode_reglement'];
        $soldeRequis     = in_array($idModeReglement, MODES_SOLDE_JOURNALIER, true);
        $reponse = [
                'status'          => 'success',
                'commande'        => $commande,
                'montant_restant' => $montantRestant,
                'montant_a_payer' => $montantAPayer,
                'tranche'         => $tranche,
                'solde_requis'    => $soldeRequis,
        ];

        if ($soldeRequis) {
            $alloue  = montantAlloueJour($bdBASI, $idModeReglement);
            $utilise = montantUtiliseJour($bdBASI, $idModeReglement);
            $reponse['solde_disponible'] = $alloue - $utilise;
        } else {
            $stmtBanques = $bdBASI->query("SELECT id, type AS nom FROM typeBanque ORDER BY nom ASC");
            $reponse['banques'] = $stmtBanques ? $stmtBanques->fetchAll(PDO::FETCH_ASSOC) : [];
        }

        echo json_encode($reponse);
    } catch (\Throwable $e) {
        error_log('[CaisseCaissier][detailCommandePaiement] ' . $e->getMessage());
        erreurSqlCaissier('Impossible de charger le détail de la commande.');
    }
}

/**
 * Enregistre un paiement (complet ou par tranche) pour une commande.
 * Requête multipart/form-data (upload du reçu, optionnel).
 */
function effectuerPaiement(PDO $bdBASI, caisseCaissierController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string)($_POST['token'] ?? ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        // L'arrêt de caisse du jour ferme définitivement la possibilité
        // d'enregistrer un nouveau paiement pour ce caissier aujourd'hui.
        if (arreteCaisseExisteJour($bdBASI, $sessionUserId)) {
            echo json_encode(['status' => 'error', 'message' => "L'arrêt de caisse a déjà été effectué aujourd'hui : aucun nouveau paiement ne peut être enregistré."]);
            return;
        }

        $stmt = $bdBASI->prepare("
            SELECT id, montant_total, montant_paye, id_mode_reglement, id_mode_paiement, idStatut, idTypePAP, livraison
            FROM passer_achat_et_paiement
            WHERE id = ? AND idStatut = 6
            LIMIT 1
        ");
        $stmt->execute([$idPAP]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$commande) {
            echo json_encode(['status' => 'error', 'message' => 'Commande introuvable ou déjà soldée.']);
            return;
        }

        $montantTotal   = (float)$commande['montant_total'];
        $montantPaye    = (float)$commande['montant_paye'];
        $montantRestant = $montantTotal - $montantPaye;
        if ($montantRestant <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Cette commande est déjà intégralement réglée.']);
            return;
        }

        $idModeReglement = (int)$commande['id_mode_reglement'];
        $estParTranche   = ((int)$commande['id_mode_paiement'] === 3);

        $trancheAReglee = null;
        if ($estParTranche) {
            $trancheAReglee = trancheAPayer($bdBASI, $idPAP, $montantTotal);
            if (!$trancheAReglee) {
                echo json_encode(['status' => 'error', 'message' => 'Toutes les tranches de ce dossier sont déjà réglées.']);
                return;
            }
            $montant = $trancheAReglee['montant'];
        } else {
            $montant = $montantRestant;
        }

        if ($montant > $montantRestant + 0.01) {
            echo json_encode(['status' => 'error', 'message' => 'Le montant à régler dépasse le montant restant dû.']);
            return;
        }

        $banque = null;
        $numeroChequeVirement = null;
        $soldeRequis = in_array($idModeReglement, MODES_SOLDE_JOURNALIER, true);

        if ($soldeRequis) {
            $solde = montantAlloueJour($bdBASI, $idModeReglement) - montantUtiliseJour($bdBASI, $idModeReglement);
            if ($solde < $montant - 0.01) {
                echo json_encode(['status' => 'error', 'message' => "Solde disponible insuffisant pour ce mode de règlement aujourd'hui (" . number_format($solde, 0, ',', ' ') . " FCFA disponible)."]);
                return;
            }
        } else {
            $banque = trim((string)($_POST['banque'] ?? ''));
            $numeroChequeVirement = trim((string)($_POST['numero_cheque_virement'] ?? ''));
            if ($banque === '' || $numeroChequeVirement === '') {
                echo json_encode(['status' => 'error', 'message' => 'La banque et le numéro du chèque/virement sont obligatoires pour ce mode de règlement.']);
                return;
            }
        }

        $cheminRecu = null;
        if (!empty($_FILES['recu']) && $_FILES['recu']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir(UPLOAD_DIR_RECUS_PAIEMENT)) mkdir(UPLOAD_DIR_RECUS_PAIEMENT, 0755, true);
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES['recu']['tmp_name']);
            $extensionParMime = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
            if (!isset($extensionParMime[$mimeType])) {
                echo json_encode(['status' => 'error', 'message' => 'Le reçu doit être un PDF, JPG ou PNG.']);
                return;
            }
            $nomFichier   = 'recu_' . $idPAP . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extensionParMime[$mimeType];
            $cheminAbsolu = UPLOAD_DIR_RECUS_PAIEMENT . '/' . $nomFichier;
            if (!move_uploaded_file($_FILES['recu']['tmp_name'], $cheminAbsolu)) {
                throw new \RuntimeException("Échec de l'enregistrement du reçu.");
            }
            $cheminRecu = UPLOAD_URL_RECUS_PAIEMENT . '/' . $nomFichier;
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');
        $nouveauMontantPaye = $montantPaye + $montant;
        $desormaisSolde     = ($nouveauMontantPaye >= $montantTotal - 0.01);

        $bdBASI->beginTransaction();

        $stmtInsertPaiement = $bdBASI->prepare("
            INSERT INTO paiement_pap
                (idPAP, montant, mode_reglement, banque, numero_cheque_virement, recu, id_tranche, date_paiement, idCaissier)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtInsertPaiement->execute([
                $idPAP, $montant, $idModeReglement, $banque, $numeroChequeVirement, $cheminRecu,
                $trancheAReglee['id'] ?? null, $dateEnregistrement, $sessionUserId,
        ]);
        $idPaiement = (int) $bdBASI->lastInsertId();

        $motif = $estParTranche
                ? "Paiement de la tranche n°{$trancheAReglee['ordre']} (par $sessionMatricule)"
                : "Paiement intégral de la commande (par $sessionMatricule)";

        // id_tranche inclus dans l'historique (point 2).
        $bdBASI->prepare("
            INSERT INTO historique_paiement_pap
                (idPaiement, idPAP, montant, mode_reglement, banque, numero_cheque_virement, recu, id_tranche, date_paiement, idCaissier, motif, dateEnregistrement)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
                $idPaiement, $idPAP, $montant, $idModeReglement, $banque, $numeroChequeVirement, $cheminRecu,
                $trancheAReglee['id'] ?? null, $dateEnregistrement, $sessionUserId, $motif, $dateEnregistrement,
        ]);

        if ($trancheAReglee) {
            $bdBASI->prepare("
                UPDATE tranches SET paiement = 1, date_paiement = ? WHERE id = ?
            ")->execute([$dateEnregistrement, $trancheAReglee['id']]);

            // Historisation de la tranche (point 3) — même table/motif que lors
            // de la création des tranches (passerCommande/passerPaiement).
            $bdBASI->prepare("
                INSERT INTO tranches_histo
                    (id_tranche, idPAP, ordre, pourcentage, created_at, updated_at, action, dateEnregistrement)
                VALUES (?, ?, ?, ?, ?, ?, 'Paiement', ?)
            ")->execute([
                    $trancheAReglee['id'], $idPAP, $trancheAReglee['ordre'], $trancheAReglee['pourcentage'],
                    $dateEnregistrement, $dateEnregistrement, $dateEnregistrement,
            ]);
        }

        // Règle de transition 6 → 7 :
        //   - idTypePAP = 2 (Passer au paiement) : dès que le paiement est
        //     complet, passage automatique à 7 (pas de notion de livraison
        //     pour un dossier de paiement).
        //   - idTypePAP = 1 (Passer commande / achat) : ne passe à 7 que si
        //     le paiement est complet ET que livraison = 0 (garde-fou —
        //     évite de clore un dossier déjà marqué comme livré par un autre
        //     processus). ⚠️ Numérotation confirmée avec l'utilisateur :
        //     1 = Achat, 2 = Paiement (cohérent avec le reste du projet).
        $estTypePaiement = ((int)$commande['idTypePAP'] === 2);
        if ($estTypePaiement) {
            $nouveauStatut = $desormaisSolde ? 7 : (int)$commande['idStatut'];
        } else {
            $livraisonNulle = ((int)($commande['livraison'] ?? 0) === 0);
            $nouveauStatut = ($desormaisSolde && $livraisonNulle) ? 7 : (int)$commande['idStatut'];
        }
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
        ")->execute([$sessionUserId, $motif, $dateEnregistrement, $idPAP]);

        $bdBASI->commit();

        echo json_encode([
                'status'         => 'success',
                'message'        => $desormaisSolde ? 'Paiement enregistré : commande intégralement soldée.' : 'Paiement enregistré avec succès.',
                'commandeSoldee' => $desormaisSolde,
        ]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[CaisseCaissier][effectuerPaiement] ' . $e->getMessage());
        erreurSqlCaissier("Impossible d'enregistrer le paiement.");
    }
}

/**
 * Statistiques de la journée : montants alloué/utilisé/solde par mode
 * (Liquide/Wave/OM), et compteurs de commandes/paiements.
 */
function statsJourPaiement(PDO $bdBASI): void {
    try {
        $stats = ['liquide' => [], 'wave' => [], 'om' => []];
        $modes = ['liquide' => 1, 'wave' => 4, 'om' => 5];
        foreach ($modes as $cle => $idMode) {
            $alloue  = montantAlloueJour($bdBASI, $idMode);
            $utilise = montantUtiliseJour($bdBASI, $idMode);
            $stats[$cle] = ['alloue' => $alloue, 'utilise' => $utilise, 'solde' => $alloue - $utilise];
        }

        // Commandes intégralement soldées grâce à un paiement effectué
        // aujourd'hui (et non plus la date de création de la commande, qui
        // pouvait être très antérieure au règlement effectif).
        $stmtTraitees = $bdBASI->query("
            SELECT COUNT(DISTINCT pp.idPAP) AS total
            FROM paiement_pap pp
            JOIN passer_achat_et_paiement p ON pp.idPAP = p.id
            WHERE DATE(pp.date_paiement) = CURDATE() AND p.idStatut = 7
        ");
        $traiteesJour = (int) ($stmtTraitees ? ($stmtTraitees->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0);

        // Nombre total de paiements effectués aujourd'hui, TOUS modes
        // confondus (Liquide/Wave/Orange Money/Chèque/Virement) — les
        // chèques et virements ne rentrent pas dans les montants
        // alloué/utilisé (ils ne débitent pas la caisse), mais doivent
        // néanmoins être comptabilisés ici.
        $stmtNbPaiements = $bdBASI->query("
            SELECT COUNT(*) AS total FROM paiement_pap WHERE DATE(date_paiement) = CURDATE()
        ");
        $nombrePaiementsJour = (int) ($stmtNbPaiements ? ($stmtNbPaiements->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0);

        $stmtCompteurs = $bdBASI->query("
            SELECT
                SUM(CASE WHEN idStatut IN (6) AND montant_paye > 0 AND montant_paye < montant_total THEN 1 ELSE 0 END) AS partielles,
                SUM(CASE WHEN idStatut IN (6) AND montant_paye = 0 THEN 1 ELSE 0 END) AS en_attente
            FROM passer_achat_et_paiement
        ");
        $compteurs = $stmtCompteurs ? ($stmtCompteurs->fetch(PDO::FETCH_ASSOC) ?: []) : [];

        echo json_encode([
                'status' => 'success',
                'stats'  => $stats,
                'compteurs' => [
                        'traitees_jour'         => $traiteesJour,
                        'partielles'            => (int)($compteurs['partielles'] ?? 0),
                        'en_attente'            => (int)($compteurs['en_attente'] ?? 0),
                        'nombre_paiements_jour' => $nombrePaiementsJour,
                ],
        ]);
    } catch (\Throwable $e) {
        error_log('[CaisseCaissier][statsJourPaiement] ' . $e->getMessage());
        erreurSqlCaissier('Impossible de charger les statistiques du jour.');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   ROUTAGE
   1 = listerAlimentationsCaissier (les alimentations du caissier connecté)
   2 = accepterAlimentationCaisse  (idStatut 1 → 2, En attente + date du jour)
   3 = rejeterAlimentationCaisse   (idStatut 1 → 3, En attente + date du jour)
   4 = detailAlimentationCaissier  (en-tête + répartition des montants)
   5 = listerCommandesAPayer       (idStatut = 6, montant_paye < montant_total)
   6 = detailCommandePaiement      (mode/modalité préchargés, montant à régler)
   7 = effectuerPaiement           (paiement complet ou par tranche)
   8 = statsJourPaiement           (montants alloué/utilisé/solde + compteurs)
   (L'arrêt de caisse est géré par un script autonome sans AJAX :
    arrete-caisse-pdf.php — cf. ce fichier pour la logique complète.)
═══════════════════════════════════════════════════════════════════════════ */
try {
    switch ($option) {
        case 1:
            listerAlimentationsCaissier($bdBASI, $basiController, $sessionUserId);
            break;

        case 2:
            accepterAlimentationCaisse($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 3:
            rejeterAlimentationCaisse($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 4:
            detailAlimentationCaissier($bdBASI, $basiController, $sessionUserId);
            break;

        case 5:
            listerCommandesAPayer($bdBASI, $basiController, $sessionUserId);
            break;

        case 6:
            detailCommandePaiement($bdBASI, $basiController, $sessionUserId);
            break;

        case 7:
            effectuerPaiement($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 8:
            statsJourPaiement($bdBASI);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Option '$option' non reconnue."]);
            exit;
    }
} catch (\Throwable $e) {
    error_log('[CaisseCaissier][Routage] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Une erreur inattendue est survenue.']);
    exit;
}