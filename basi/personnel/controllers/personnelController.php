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
 *     restante + statut de ligne (En attente / Partiellement livré / Livré),
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
$sessionIdDirection = (int)($_SESSION['user_direction'] ?? 0);

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
function erreurSqlEB(string $message = "Erreur lors de l'accès à la base de données."): void {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$message]);
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
            SELECT id, nom_expression, idUtilisateur, idDirection, date_creation, idStatut, dateEnregistrement
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

        // ── Produits : demandée / validée / sortie / restante / statut ligne ─
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
            $qteReelle  = $p['quantite_reelle'] !== null ? (float) $p['quantite_reelle'] : null;
            $qteSortie  = (float) ($p['quantite_sortie'] ?? 0);

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


function detailExpressionBesoinDirection2(PDO $bdBASI, expressionBesoinController $basiController): void {
    try {
        $token = trim((string) inputValueEB('token', ''));
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

    echo $e;
    die;
        error_log('[ChefDirEB][detailExpressionBesoinDirection] ' . $e->getMessage());
        erreurSqlChefDirEB("Impossible de charger le détail de l'expression de besoin.");
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
            case 8 :

                  detailExpressionBesoinDirection2($bdBASI, $basiController);
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