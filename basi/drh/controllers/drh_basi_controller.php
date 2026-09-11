<?php
/**
 * controllerLigneBudget.php
 * Contrôleur AJAX pour l'affichage des lignes budgétaires validées
 * (demandes d'achat et demandes de paiement) et leurs actions associées.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * HYPOTHÈSES DE SCHÉMA (à ajuster selon votre base réelle) :
 *
 *   budget(idBudget, designation, prix_unitaire, ...)
 *
 *   demande_achat(idDemande, idBudget, quantite_demandee, statut,
 *                 categorie, sous_categorie, date_demande, idDirection, ...)
 *     → statut = 'Validée' pour les lignes à afficher ici.
 *
 *   demande_paiement(idDemande, idBudget, montant_total, statut,
 *                     rubrique, sous_rubrique, date_demande, idDirection, ...)
 *     → statut = 'Validée' pour les lignes à afficher ici.
 *
 *   commande(idCommande, idDemandeAchat, quantite, statut_commande, date_commande, idUtilisateur)
 *     → statut_commande = 'Livrée'  pour la "quantité commandée" (reçue)
 *     → statut_commande = 'En cours' pour la "quantité en cours" (commandée, non reçue)
 *
 *   paiement(idPaiement, idDemandePaiement, montant, statut_paiement, date_paiement, idUtilisateur)
 *     → statut_paiement = 'Validée' pour le "montant payé"
 *
 *   facture_proforma(idFacture, idDemandeAchat, reference, montant, date_creation, idUtilisateur)
 *
 * Adaptez les noms de table/colonne dans les requêtes ci-dessous si votre
 * base diffère — ils sont rassemblés dans les fonctions SQL pour être
 * faciles à repérer et modifier.
 * ═══════════════════════════════════════════════════════════════════════════
 */

// ─── Capture de tout output parasite ─────────────────────────────────────────
ob_start();
session_start();
include_once('../../../bdBASI.php'); // ← à ajuster selon la profondeur réelle du fichier
ob_end_clean();

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// ─── Vérification sessions obligatoires ──────────────────────────────────────
function checkSession(): void {
    foreach (['tmpIdBASI', 'tmpIdDirection', 'tmpMatricule'] as $key) {
        if (empty($_SESSION[$key])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['status'=>'error','code'=>'sessionExpired','message'=>'Session expirée. Veuillez vous reconnecter.']);
            exit;
        }
    }
}
checkSession();

$sessionUserId    = (int)$_SESSION['tmpIdBASI'];
$sessionDirection = (int)$_SESSION['tmpIdDirection'];
$sessionMatricule = trim($_SESSION['tmpMatricule']);

// ─── Classe contrôleur ────────────────────────────────────────────────────────
class ligneBudgetController extends BDBASI
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

// ─── Connexion DB (PDO) ────────────────────────────────────────────────────────
try {
    $BDBASI  = new BDBASI();
    $bdBASI  = $BDBASI->connect(); // Instance PDO
    $ctrl    = new ligneBudgetController();
} catch (\Throwable $e) {
    error_log('[LigneBudget][Connexion] ' . $e->getMessage());
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

function valid_donnees($d) { return htmlspecialchars(stripslashes(trim($d))); }

// ─── Lecture de l'option ──────────────────────────────────────────────────────
$option = isset($_GET['option']) ? trim($_GET['option']) : '';
if ($option==='' && isset($_POST['option'])) $option = trim($_POST['option']);
if ($option==='') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Paramètre option manquant.']);
    exit;
}
$option = (int)$option;

header('Content-Type: application/json; charset=utf-8');

/* ═══════════════════════════════════════════════════════════════════════════
   HELPERS
═══════════════════════════════════════════════════════════════════════════ */

function erreurSql(string $message = "Erreur lors de l'accès à la base de données."): void {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$message]);
}


try {
    switch ($option) {

        case 1:
            listerDemandesAchat($bdBASI, $sessionDirection);
            break;

        case 2:
            listerDemandesPaiement($bdBASI, $sessionDirection);
            break;

        case 3:
            enregistrerFactureProforma($bdBASI, $sessionUserId);
            break;

        case 4:
            passerCommande($bdBASI, $sessionUserId);
            break;

        case 5:
            passerPaiement($bdBASI, $sessionUserId);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Option '$option' non reconnue."]);
            exit;
    }
} catch (\Throwable $e) {
    error_log('[LigneBudget][Routage] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Une erreur inattendue est survenue.']);
    exit;
}