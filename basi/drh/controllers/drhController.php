<?php
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
class drhController extends BDBASI
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
    function fctRetirerAccents($s) {
        $search  = ['À','Á','Â','Ã','Ä','Å','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ò','Ó','Ô','Õ','Ö','Ù','Ú','Û','Ü','Ý','à','á','â','ã','ä','å','ç','è','é','ê','ë','ì','í','î','ï','ð','ò','ó','ô','õ','ö','ù','ú','û','ü','ý','ÿ'];
        $replace = ['A','A','A','A','A','A','C','E','E','E','E','I','I','I','I','O','O','O','O','O','U','U','U','U','Y','a','a','a','a','a','a','c','e','e','e','e','i','i','i','i','o','o','o','o','o','o','u','u','u','u','y','y'];
        return str_replace($search,$replace,$s);
    }
    public function numberFormat($n,$t1,$t2,$t3) {
        return ($n!=null&&$n!='') ? number_format($n,0,',',' ') : $n;
    }
}

// ─── Connexion DB (PDO) ────────────────────────────────────────────────────────
try {
    $BDBASI         = new BDBASI();
    $bdBASI         = $BDBASI->connect(); // Instance PDO
    $basiController = new drhController();
} catch (\Throwable $e) {
    error_log('[Fournisseur][Connexion] ' . $e->getMessage());
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

/**
 * Lit le corps de la requête envoyé en JSON brut (cas des appels fetch()
 * avec Content-Type: application/json, comme depuis liste-demandes-scripts).
 * $_POST ne contient RIEN dans ce cas — PHP ne le remplit que pour les
 * bodies form-urlencoded/multipart. Retourne [] si le body est vide ou
 * n'est pas du JSON valide.
 */
function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Récupère un paramètre de requête en cherchant, dans l'ordre : le body JSON
 * brut (fetch), $_POST (formulaire classique), $_GET. Permet à une même
 * fonction de fonctionner quel que soit le mode d'envoi utilisé côté client.
 */
function inputValue(string $key, $default = null) {
    static $jsonBody = null;
    if ($jsonBody === null) $jsonBody = getJsonBody();

    if (array_key_exists($key, $jsonBody)) return $jsonBody[$key];
    if (isset($_POST[$key]))               return $_POST[$key];
    if (isset($_GET[$key]))                return $_GET[$key];
    return $default;
}

// ─── Lecture de l'option ──────────────────────────────────────────────────────
// Cherche dans l'ordre : $_GET, $_POST, puis le corps JSON brut (cas des
// appels fetch()/$.ajax avec contentType: 'application/json'), comme la
// modale "Demande de facture" — sans ce dernier cas, $_POST reste vide et
// l'option est perdue même si elle est bien présente dans le body envoyé.
$option = isset($_GET['option']) ? trim($_GET['option']) : '';
if ($option==='' && isset($_POST['option'])) $option = trim($_POST['option']);
if ($option==='') {
    $jsonBodyOption = getJsonBody();
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
   HELPERS — Fournisseur
═══════════════════════════════════════════════════════════════════════════ */

function erreurSql(string $message = "Erreur lors de l'accès à la base de données."): void {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$message]);
}

/**
 * Filet de sécurité : convertit une violation de contrainte UNIQUE (SQLSTATE
 * 23000, ex. 'uq_fournisseur_email', 'uq_fournisseur_tel') en une erreur
 * JSON compréhensible, plutôt que de laisser l'exception PDO brute remonter.
 * Sert de dernier rempart si un doublon passe malgré tout la vérification
 * applicative (ex. contrainte non prévue, insertion concurrente).
 * Retourne ['field' => 'telF'|'emailF'|null, 'message' => string]
 */
function messageDoublonDepuisException(\PDOException $e): array {
    $msg = $e->getMessage();

    if (preg_match("/for key '([^']+)'/i", $msg, $m)) {
        $cle = strtolower($m[1]);
        if (strpos($cle, 'email') !== false) {
            return ['field' => 'emailF', 'message' => "Cette adresse email existe déjà pour un autre fournisseur (ou un fournisseur supprimé)."];
        }
        if (strpos($cle, 'tel') !== false) {
            return ['field' => 'telF', 'message' => "Ce numéro de téléphone existe déjà pour un autre fournisseur (ou un fournisseur supprimé)."];
        }
    }

    return ['field' => null, 'message' => "Cette information est déjà utilisée par un autre fournisseur (ou un fournisseur supprimé)."];
}

/**
 * Valide et nettoie les données du formulaire fournisseur (format uniquement).
 * Retourne ['errors' => [...], 'clean' => [...]]
 */
function validerDonneesFournisseur(array $input): array {
    $errors = [];

    $nomF       = valid_donnees($input['nomF'] ?? '');
    $prenomF    = valid_donnees($input['prenomF'] ?? '');
    $telF       = valid_donnees($input['telF'] ?? '');
    $emailF     = valid_donnees($input['emailF'] ?? '');
    $adresseF   = valid_donnees($input['adresseF'] ?? '');
    $entreprise = valid_donnees($input['entreprise'] ?? '');
    $ville      = valid_donnees($input['ville'] ?? '');

    if ($nomF === '') {
        $errors['nomF'] = 'Le nom est requis.';
    } elseif (mb_strlen($nomF) > 100) {
        $errors['nomF'] = 'Le nom est trop long (100 caractères max).';
    }

    if ($prenomF === '') {
        $errors['prenomF'] = 'Le prénom est requis.';
    } elseif (mb_strlen($prenomF) > 100) {
        $errors['prenomF'] = 'Le prénom est trop long (100 caractères max).';
    }

    if ($telF === '') {
        $errors['telF'] = 'Le téléphone est requis.';
    } elseif (!preg_match('/^[0-9+\s\-().]{6,20}$/', $telF)) {
        $errors['telF'] = 'Le téléphone est invalide.';
    }

    if ($emailF !== '' && !filter_var($emailF, FILTER_VALIDATE_EMAIL)) {
        $errors['emailF'] = 'Adresse email invalide.';
    }

    return [
        'errors' => $errors,
        'clean'  => compact('nomF', 'prenomF', 'telF', 'emailF', 'adresseF', 'entreprise', 'ville'),
    ];
}

/**
 * Recherche des doublons (téléphone / email / nom+prénom+entreprise) parmi
 * TOUS les fournisseurs, y compris ceux au statut 'supprimer'. C'est
 * indispensable car les colonnes telF/emailF portent une contrainte UNIQUE
 * en base qui s'applique à toutes les lignes, supprimées ou non — vérifier
 * uniquement les fournisseurs visibles laisserait passer un INSERT qui
 * échouerait ensuite au niveau de la base (violation de contrainte).
 * $excludeIdF permet d'exclure le fournisseur en cours d'édition (modification).
 * Retourne un tableau d'erreurs indexées par champ, vide si aucun doublon.
 * @throws \RuntimeException en cas d'échec de préparation/exécution SQL
 */
function verifierDoublonsFournisseur(PDO $bdBASI, string $telF, string $emailF, string $nomF, string $prenomF, string $entreprise, ?int $excludeIdF = null): array {
    $errors = [];

    // ── Téléphone déjà utilisé ────────────────────────────────────────────
    if ($telF !== '') {
        $sql = "SELECT idF, statut FROM fournisseur WHERE telF = ?";
        $params = [$telF];
        if ($excludeIdF !== null) {
            $sql .= " AND idF != ?";
            $params[] = $excludeIdF;
        }
        $stmt = $bdBASI->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Préparation de la vérification de doublon (téléphone) impossible.');
        }
        $stmt->execute($params);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($match) {
            $errors['telF'] = $match['statut'] === 'supprimer'
                ? "Ce numéro de téléphone appartient à un fournisseur précédemment supprimé. Contactez un administrateur pour le restaurer plutôt que d'en recréer un."
                : "Ce numéro de téléphone existe déjà pour un autre fournisseur.";
        }
    }

    // ── Email déjà utilisé ─────────────────────────────────────────────────
    if ($emailF !== '') {
        $sql = "SELECT idF, statut FROM fournisseur WHERE emailF = ?";
        $params = [$emailF];
        if ($excludeIdF !== null) {
            $sql .= " AND idF != ?";
            $params[] = $excludeIdF;
        }
        $stmt = $bdBASI->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Préparation de la vérification de doublon (email) impossible.');
        }
        $stmt->execute($params);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($match) {
            $errors['emailF'] = $match['statut'] === 'supprimer'
                ? "Cette adresse email appartient à un fournisseur précédemment supprimé. Contactez un administrateur pour le restaurer plutôt que d'en recréer un."
                : "Cette adresse email existe déjà pour un autre fournisseur.";
        }
    }

    // ── Fiche identique (nom + prénom + entreprise) ────────────────────────
    // Utile même quand téléphone et email diffèrent (ex. deux contacts saisis
    // par erreur pour le même fournisseur).
    if ($nomF !== '' && $prenomF !== '' && $entreprise !== '') {
        $sql = "
            SELECT idF, statut FROM fournisseur
            WHERE LOWER(nomF) = LOWER(?) AND LOWER(prenomF) = LOWER(?) AND LOWER(entreprise) = LOWER(?)
        ";
        $params = [$nomF, $prenomF, $entreprise];
        if ($excludeIdF !== null) {
            $sql .= " AND idF != ?";
            $params[] = $excludeIdF;
        }
        $stmt = $bdBASI->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Préparation de la vérification de doublon (fiche complète) impossible.');
        }
        $stmt->execute($params);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($match) {
            // Pas de champ unique concerné : on rattache l'erreur à 'nomF' pour
            // qu'elle s'affiche aussi sous le champ, en plus du toast récapitulatif.
            $errors['nomF'] = $match['statut'] === 'supprimer'
                ? "Un fournisseur avec ce nom, prénom et cette entreprise a été précédemment supprimé. Contactez un administrateur pour le restaurer plutôt que d'en recréer un."
                : "Un fournisseur avec ce nom, prénom et cette entreprise existe déjà.";
        }
    }

    return $errors;
}

/**
 * Valide le format des champs PUIS vérifie les doublons (téléphone / email /
 * fiche identique). Retourne ['errors' => [...], 'clean' => [...]] — combine
 * les deux étapes pour que create/update aient un point d'entrée unique.
 */
function validerEtVerifierFournisseur(PDO $bdBASI, array $input, ?int $excludeIdF = null): array {
    $resultat = validerDonneesFournisseur($input);
    $errors   = $resultat['errors'];
    $d        = $resultat['clean'];

    // On ne vérifie les doublons que sur des champs déjà bien formés,
    // pour éviter de mélanger erreur de format et erreur de doublon.
    $telFPourDoublon   = isset($errors['telF'])    ? '' : $d['telF'];
    $emailFPourDoublon = isset($errors['emailF'])  ? '' : $d['emailF'];
    $nomFPourDoublon   = isset($errors['nomF'])    ? '' : $d['nomF'];
    $prenomFPourDoublon= isset($errors['prenomF']) ? '' : $d['prenomF'];

    $doublons = verifierDoublonsFournisseur(
        $bdBASI, $telFPourDoublon, $emailFPourDoublon, $nomFPourDoublon, $prenomFPourDoublon, $d['entreprise'], $excludeIdF
    );
    // Une erreur de doublon sur 'nomF' ne doit pas écraser une éventuelle
    // erreur de format déjà présente sur ce même champ.
    foreach ($doublons as $champ => $message) {
        if (!isset($errors[$champ])) {
            $errors[$champ] = $message;
        }
    }

    return ['errors' => $errors, 'clean' => $d];
}

/**
 * Récupère un fournisseur par idF (PDO, requête préparée).
 * @throws \RuntimeException en cas d'échec de préparation
 */
function recupererFournisseur(PDO $bdBASI, int $idF): ?array {
    $stmt = $bdBASI->prepare("
        SELECT idF, nomF, prenomF, adresseF, telF, emailF, entreprise, ville,
               statut, date_creation, idUtilisateur
        FROM fournisseur WHERE idF = ?
    ");
    if (!$stmt) {
        throw new \RuntimeException('Préparation de la requête impossible (recupererFournisseur).');
    }
    $stmt->execute([$idF]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Enregistre un instantané du fournisseur dans historiqueFournisseur.
 * $row doit contenir : idF, nomF, prenomF, adresseF, telF, emailF,
 *                       entreprise, ville, statut, date_creation
 * $idUtilisateur : identifiant de l'utilisateur qui a déclenché l'action.
 * @throws \RuntimeException en cas d'échec de préparation/exécution
 */
function enregistrerHistorique(PDO $bdBASI, array $row, string $motif, int $idUtilisateur): void {
    date_default_timezone_set('Africa/Dakar');
    $dateEnregistrement = date('Y-m-d H:i:s');

    $stmt = $bdBASI->prepare("
        INSERT INTO historiqueFournisseur
            (idF, nomF, prenomF, adresseF, telF, emailF, entreprise, ville,
             statut, date_creation, motif, dateEnregistrement, idUtilisateur)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new \RuntimeException("Préparation de la requête d'historisation impossible.");
    }
    $ok = $stmt->execute([
        $row['idF'], $row['nomF'], $row['prenomF'], $row['adresseF'], $row['telF'],
        $row['emailF'], $row['entreprise'], $row['ville'], $row['statut'], $row['date_creation'],
        $motif, $dateEnregistrement, $idUtilisateur,
    ]);
    if (!$ok) {
        throw new \RuntimeException("Échec de l'enregistrement dans l'historique.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   HELPERS — Liste des demandes (Achat / Paiement)

   Hypothèses de schéma (à ajuster si elles ne correspondent pas à la base) :
   - Les colonnes qte_commandee, qte_restant, mnt_paye, mnt_restant sont
     portées par `demandes_ligne` (PAR LIGNE de demande), pas par `demandes`.
     Elles restent NULL tant qu'aucune commande / aucun paiement n'a été
     enregistré pour cette ligne précise (alimentées par un processus
     ultérieur, hors du périmètre de ce contrôleur).
   - idTypeDemande : 1 = Achat | 2 = Paiement.
   - Une DEMANDE pouvant compter plusieurs LIGNES, son épuisement est dérivé
     de l'ensemble de ses lignes actives (demandes_ligne.statut = 'crée') :
     une demande est épuisée quand TOUTES ses lignes actives sont épuisées
     (et qu'elle compte au moins une ligne active) :
       • Achat    : pour chaque ligne, qte_commandee == qte_restant
       • Paiement : pour chaque ligne, montant_total == mnt_paye
     (les deux valeurs comparées doivent être renseignées ; une ligne dont
     rien n'a encore été commandé/payé n'est jamais considérée épuisée).
   - Le "montant estimé" n'est PAS lu depuis une colonne stockée : il est
     recalculé à chaque affichage en sommant les lignes réellement demandées
     (table demandes_ligne, statut = 'crée') :
       • Achat    : Σ (quantité de la ligne × prix_unitaire de la ligne budget)
       • Paiement : Σ (montant_total de chaque ligne de demande)
   - Le DRH voit les demandes de toutes les directions (pas de filtre sur
     tmpIdDirection ici, contrairement à la vue "chef de service").
   - Seules les demandes déjà soumises sont listées (etat_demande != 'En
     création' et != 'Supprimée') — les brouillons ne concernent pas le DRH.
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Fragment SQL réutilisable (sous-requête corrélée sur l'alias $d = demandes) :
 * vrai quand TOUTES les lignes actives de la demande sont épuisées, et qu'il
 * en existe au moins une (une demande sans ligne n'est jamais épuisée).
 *
 * NB : qte_commandee, qte_restant, mnt_paye sont des colonnes VARCHAR(255) en
 * base (et non numériques) — on les CAST explicitement en DECIMAL avant
 * comparaison pour éviter tout souci de comparaison de chaînes (ex. "10.0"
 * vs "10" traités comme différents en comparaison texte, mais égaux en
 * comparaison numérique).
 */
function sqlDemandeEpuisee(string $d = 'd'): string {
    return "(
        EXISTS (
            SELECT 1 FROM demandes_ligne dl0
            WHERE dl0.idD = $d.idD AND dl0.statut = 'crée'
        )
        AND NOT EXISTS (
            SELECT 1 FROM demandes_ligne dl1
            WHERE dl1.idD = $d.idD AND dl1.statut = 'crée'
              AND (
                  ($d.idTypeDemande = 1 AND NOT (
                      dl1.qte_commandee IS NOT NULL
                      AND CAST(dl1.quantite AS DECIMAL(15,2)) = CAST(dl1.qte_commandee AS DECIMAL(15,2))
                  ))
                  OR
                  ($d.idTypeDemande = 2 AND NOT (
                      dl1.montant_total IS NOT NULL AND dl1.mnt_paye IS NOT NULL
                      AND CAST(dl1.montant_total AS DECIMAL(15,2)) = CAST(dl1.mnt_paye AS DECIMAL(15,2))
                  ))
              )
        )
    )";
}

/* ═══════════════════════════════════════════════════════════════════════════
   ACTIONS — Fournisseur
   Chaque action est protégée par un try/catch : toute exception (SQL,
   connexion, logique) est journalisée côté serveur (error_log) et renvoyée
   au client sous forme de message générique, sans exposer de détails
   techniques sensibles. Les erreurs de validation / doublon sont renvoyées
   de façon détaillée (par champ) pour un affichage précis côté JS.
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Liste des demandes Achat / Paiement pour le DRH.
 *
 * Paramètres POST/GET :
 *   - type            : 'toutes' (défaut) | 'demande_achat' | 'demande_paiement'
 *   - afficherEpuisees : '0' (défaut) | '1' — quand absent/faux, seules les
 *                        demandes "en cours" (non épuisées) sont renvoyées.
 */
function listerDemandes(PDO $bdBASI, drhController $basiController): void {
    try {
        $type = strtolower(trim((string)inputValue('type', 'toutes')));
        if (!in_array($type, ['toutes', 'demande_achat', 'demande_paiement'], true)) $type = 'toutes';

        $afficherEpuisees = filter_var(inputValue('afficherEpuisees', false), FILTER_VALIDATE_BOOLEAN);

        $idTypeFiltre = null;
        if ($type === 'demande_achat')    $idTypeFiltre = 1;
        if ($type === 'demande_paiement') $idTypeFiltre = 2;

        // Montant estimé : calculé selon le type, en sommant les lignes réellement
        // demandées (statut = 'crée') plutôt que lu depuis une colonne stockée.
        $sqlMontantEstime = "
            CASE WHEN d.idTypeDemande = 1
                THEN (
                    SELECT COALESCE(SUM(dal.quantite * lb.prix_unitaire), 0)
                    FROM demandes_ligne dal
                    JOIN ligneBudget lb ON dal.idLB = lb.id
                    WHERE dal.idD = d.idD AND dal.statut = 'crée'
                )
                ELSE (
                    SELECT COALESCE(SUM(dal.montant_total), 0)
                    FROM demandes_ligne dal
                    WHERE dal.idD = d.idD AND dal.statut = 'crée'
                )
            END
        ";

        $sqlEpuisee = sqlDemandeEpuisee('d');

        $sql = "
            SELECT
                d.idD                                    AS idD,
                d.idTypeDemande,
                d.type_demande,
                CONCAT(u.prenom, ' ', u.nom)              AS demandeur,
                d.date_creation,
                COALESCE(d.etat_demande, 'En création')   AS statut,
                $sqlMontantEstime                         AS montant_estime,
                $sqlEpuisee                                AS epuisee_flag
            FROM demandes d
            JOIN utilisateurs u ON d.idUtilisateur = u.id
            WHERE d.idTypeDemande IN (1, 2)
              AND COALESCE(d.etat_demande, 'En création') NOT IN ('En création', 'Supprimée')
        ";
        $params = [];

        if ($idTypeFiltre !== null) {
            $sql .= " AND d.idTypeDemande = ?";
            $params[] = $idTypeFiltre;
        }

        if (!$afficherEpuisees) {
            $sql .= " AND NOT " . $sqlEpuisee;
        }

        $sql .= " ORDER BY d.date_creation DESC";

        $stmt = $bdBASI->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Préparation de la requête liste des demandes impossible.');
        }
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['tmp']     = $basiController->tokenencrypt($r['idD']);
            $r['epuisee'] = (bool)((int)$r['epuisee_flag']);
            unset($r['epuisee_flag']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows]);
    } catch (\Throwable $e) {
        error_log('[Demandes][listerDemandes] ' . $e->getMessage());
        erreurSql('Impossible de charger la liste des demandes.');
    }
}

/**
 * Détail d'UNE demande (Achat ou Paiement) : infos d'en-tête + ses lignes,
 * avec des colonnes différentes selon le type. Alimente la page "Voir la
 * demande" ouverte depuis le bouton "Voir" de la liste des demandes.
 *
 * Paramètre POST/GET :
 *   - token : token chiffré de idD (généré par listerDemandes, champ 'tmp')
 *
 * Chaque ligne renvoyée porte la clé `idDemande` = demandes_ligne.idDL,
 * pour rester compatible avec les actions groupées déjà en place
 * (Facture pro forma / Passer commande / Passer au paiement sur
 * /personnel/drh_basi_controller1, options 3/4/5) qui attendent cette forme.
 */
function voirDemande(PDO $bdBASI, drhController $basiController): void {
    try {
        $token = trim((string) inputValue('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }

        $idD = (int) $basiController->tokendecrypt($token);
        if ($idD <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmtD = $bdBASI->prepare("
            SELECT d.idD, d.idTypeDemande, d.type_demande, d.date_creation,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM demandes d
            JOIN utilisateurs u ON d.idUtilisateur = u.id
            WHERE d.idD = ?
            LIMIT 1
        ");
        if (!$stmtD) {
            throw new \RuntimeException("Préparation de la requête d'en-tête de demande impossible.");
        }
        $stmtD->execute([$idD]);
        $demande = $stmtD->fetch(PDO::FETCH_ASSOC);
        if (!$demande) {
            echo json_encode(['status' => 'error', 'message' => 'Demande introuvable.']);
            return;
        }
        $demande['tmp'] = $basiController->tokenencrypt($idD);

        $estAchat = ((int)$demande['idTypeDemande'] === 1);

        if ($estAchat) {
            // ── Achat : Désignation, Qté demandée, Qté commandée, Qté restante,
            //           Prix unitaire, Montant total, Rubrique, Sous-rubrique.
            // Qté restante = colonne dédiée si renseignée, sinon la totalité de
            // la quantité demandée (rien n'a encore été commandé sur la ligne).
            // id_unite / unite : nécessaires au modal "Passer commande" (colonne
            // "Pièces par unité" affichée uniquement si id_unite != 1).
            // Les lignes épuisées (rien à commander en plus) ne sont pas affichées
            // — cohérent avec les pages "toutes les lignes" (achat/paiement).
            $sql = "
                SELECT
                    dal.idDL                                                          AS idDemande,
                    lb.designation,
                    dal.quantite                                                      AS quantite_demandee,
                    CAST(dal.qte_commandee AS DECIMAL(15,2))                          AS quantite_commandee,
                    COALESCE(CAST(dal.qte_restant AS DECIMAL(15,2)), dal.quantite)    AS quantite_restante,
                    lb.prix_unitaire,
                    (dal.quantite * lb.prix_unitaire)                                 AS montant_total,
                    r.nom_rubrique                                                    AS rubrique,
                    sr.nom_sous_rubrique                                              AS sous_rubrique,
                    dal.unite_id                                                      AS id_unite,
                    lu.unite                                                          AS unite
                FROM demandes_ligne dal
                JOIN ligneBudget lb ON dal.idLB = lb.id
                LEFT JOIN rubrique     r  ON lb.rubrique_id      = r.id
                LEFT JOIN sousRubrique sr ON lb.sous_rubrique_id = sr.id
                LEFT JOIN listeUnites  lu ON dal.unite_id         = lu.id
                WHERE dal.idD = ? AND dal.statut = 'crée'
                  AND COALESCE(CAST(dal.qte_restant AS DECIMAL(15,2)), dal.quantite) > 0
                ORDER BY dal.idDL ASC
            ";
        } else {
            // ── Paiement : Désignation, Montant à payer, Montant payé, Montant restant.
            // Montant restant = colonne dédiée si renseignée, sinon la totalité du
            // montant demandé (rien n'a encore été payé sur la ligne).
            // Les lignes épuisées (plus rien à payer) ne sont pas affichées.
            $sql = "
                SELECT
                    dal.idDL                                                              AS idDemande,
                    lb.designation,
                    dal.montant_total                                                     AS montant_a_payer,
                    CAST(dal.mnt_paye AS DECIMAL(15,2))                                   AS montant_paye,
                    COALESCE(CAST(dal.mnt_restant AS DECIMAL(15,2)), dal.montant_total)   AS montant_restant
                FROM demandes_ligne dal
                JOIN ligneBudget lb ON dal.idLB = lb.id
                WHERE dal.idD = ? AND dal.statut = 'crée'
                  AND COALESCE(CAST(dal.mnt_restant AS DECIMAL(15,2)), dal.montant_total) > 0
                ORDER BY dal.idDL ASC
            ";
        }

        $stmtL = $bdBASI->prepare($sql);
        if (!$stmtL) {
            throw new \RuntimeException('Préparation de la requête des lignes de la demande impossible.');
        }
        $stmtL->execute([$idD]);
        $lignes = $stmtL->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'demande' => $demande, 'data' => $lignes]);
    } catch (\Throwable $e) {
        error_log('[Demandes][voirDemande] ' . $e->getMessage());
        erreurSql('Impossible de charger le détail de la demande.');
    }
}

/**
 * Liste TOUTES les lignes de TOUTES les demandes d'achat soumises (toutes
 * demandes confondues, pas une seule), pour alimenter la page
 * "toutesLignesDemandesAchat". Seules les lignes non épuisées sont
 * renvoyées (quantité restante > 0) — une ligne déjà entièrement commandée
 * n'a plus rien à faire ici.
 *
 * Chaque ligne porte son propre `idD` (numéro de la demande d'origine) :
 * indispensable côté front pour vérifier qu'une sélection multi-lignes
 * appartient bien à une seule et même demande avant "Passer commande"
 * (passer_achat_et_paiement n'a qu'une seule colonne idD par enregistrement).
 * "Demande de facture" n'a pas cette contrainte (demande_proforma n'a pas
 * de colonne idD), donc aucune restriction n'est nécessaire pour cette action.
 */
function listerToutesLignesAchat(PDO $bdBASI, drhController $basiController): void {
    try {
        $stmt = $bdBASI->query("
            SELECT
                dal.idDL                                                          AS idDemande,
                dal.idD                                                           AS idD,
                lb.designation,
                dal.quantite                                                      AS quantite_demandee,
                CAST(dal.qte_commandee AS DECIMAL(15,2))                          AS quantite_commandee,
                COALESCE(CAST(dal.qte_restant AS DECIMAL(15,2)), dal.quantite)    AS quantite_restante,
                lb.prix_unitaire,
                (dal.quantite * lb.prix_unitaire)                                 AS montant_total,
                r.nom_rubrique                                                    AS rubrique,
                sr.nom_sous_rubrique                                              AS sous_rubrique,
                dal.unite_id                                                      AS id_unite,
                lu.unite                                                          AS unite
            FROM demandes_ligne dal
            JOIN demandes    d  ON dal.idD  = d.idD
            JOIN ligneBudget lb ON dal.idLB = lb.id
            LEFT JOIN rubrique     r  ON lb.rubrique_id      = r.id
            LEFT JOIN sousRubrique sr ON lb.sous_rubrique_id = sr.id
            LEFT JOIN listeUnites  lu ON dal.unite_id         = lu.id
            WHERE dal.statut = 'crée'
              AND d.idTypeDemande = 1
              AND COALESCE(d.etat_demande, 'En création') NOT IN ('En création', 'Supprimée')
              AND COALESCE(CAST(dal.qte_restant AS DECIMAL(15,2)), dal.quantite) > 0
            ORDER BY dal.idD DESC, dal.idDL ASC
        ");
        if (!$stmt) throw new \RuntimeException('Requête des lignes de demandes d\'achat échouée.');

        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($lignes as &$l) {
            $l['tmp'] = $basiController->tokenencrypt($l['idD']); // lien vers la page "Voir la demande"
        }
        unset($l);

        echo json_encode(['status' => 'success', 'data' => $lignes]);
    } catch (\Throwable $e) {
        error_log('[LignesAchat][listerToutesLignesAchat] ' . $e->getMessage());
        erreurSql("Impossible de charger les lignes de demandes d'achat.");
    }
}

/**
 * Liste TOUTES les lignes de TOUTES les demandes de paiement soumises
 * (toutes demandes confondues), pour alimenter la page
 * "toutesLignesDemandesPaiement". Seules les lignes non épuisées sont
 * renvoyées (montant restant > 0).
 *
 * Même principe que listerToutesLignesAchat() : chaque ligne porte son
 * propre idD, et passerPaiement() n'exige plus qu'une demande unique
 * (un paiement peut désormais regrouper des lignes de plusieurs demandes
 * de paiement différentes).
 */
function listerToutesLignesPaiement(PDO $bdBASI, drhController $basiController): void {
    try {
        $stmt = $bdBASI->query("
            SELECT
                dal.idDL                                                              AS idDemande,
                dal.idD                                                               AS idD,
                lb.designation,
                dal.montant_total                                                     AS montant_a_payer,
                CAST(dal.mnt_paye AS DECIMAL(15,2))                                   AS montant_paye,
                COALESCE(CAST(dal.mnt_restant AS DECIMAL(15,2)), dal.montant_total)   AS montant_restant
            FROM demandes_ligne dal
            JOIN demandes    d  ON dal.idD  = d.idD
            JOIN ligneBudget lb ON dal.idLB = lb.id
            WHERE dal.statut = 'crée'
              AND d.idTypeDemande = 2
              AND COALESCE(d.etat_demande, 'En création') NOT IN ('En création', 'Supprimée')
              AND COALESCE(CAST(dal.mnt_restant AS DECIMAL(15,2)), dal.montant_total) > 0
            ORDER BY dal.idD DESC, dal.idDL ASC
        ");
        if (!$stmt) throw new \RuntimeException('Requête des lignes de demandes de paiement échouée.');

        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($lignes as &$l) {
            $l['tmp'] = $basiController->tokenencrypt($l['idD']); // lien vers la page "Voir la demande"
        }
        unset($l);

        echo json_encode(['status' => 'success', 'data' => $lignes]);
    } catch (\Throwable $e) {
        error_log('[LignesPaiement][listerToutesLignesPaiement] ' . $e->getMessage());
        erreurSql("Impossible de charger les lignes de demandes de paiement.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Passer commande (achat)

   Hypothèses de schéma (à ajuster si elles ne correspondent pas à la base) :
   - Tables `mode_reglement` et `mode_paiement` : colonnes `id`, `nom`.
   - idModePaiement = 3 → "Paiement par tranche".
   - `passer_achat_et_paiement` / `historique_passer_achat_et_paiement` :
     colonnes bon_commande_file et id_fournisseur supprimées, colonne
     idUtilisateur ajoutée (utilisateur ayant créé l'enregistrement).
   - Le fournisseur n'est plus associé à la commande globale mais à CHAQUE
     pro forma individuellement (jusqu'à 3), via une nouvelle colonne
     `id_fournisseur` sur `documents_pap` — colonne ajoutée par rapport à la
     structure fournie (idPAP, doc, statut, choix, dateEnregistrement), car
     sans elle l'association fournisseur↔pro forma demandée comme obligatoire
     serait impossible à stocker. ⚠️ À confirmer / ajuster si un autre
     emplacement est prévu pour cette information.
   - Prix unitaire non saisi à cette étape : prix_reel, montant_total_ligne
     (ligne) et montant_total (commande) sont NULL à la création ; ils seront
     renseignés plus tard par une autre procédure.
   - Une commande peut regrouper des lignes de PLUSIEURS demandes d'achat
     différentes (page "toutesLignesDemandesAchat"). `passer_achat_et_paiement.idD`
     est donc NULLABLE : renseigné seulement si toutes les lignes de la
     commande appartiennent à la même demande, sinon NULL — chaque ligne
     garde de toute façon sa demande d'origine via
     passer_achat_et_paiement_ligne → idDL → demandes_ligne.idD.
     ⚠️ Assurez-vous que la colonne `idD` de `passer_achat_et_paiement` (et
     de `historique_passer_achat_et_paiement`) accepte bien NULL en base.
   - Le champ UI "Pièces par unité" saisi par l'utilisateur correspond à la
     colonne `nb_unites` ; la colonne `pieces_par_unite` est calculée par le
     serveur : quantite_reelle si id_unite == 1, sinon nb_unites × quantite_reelle.
   - `idTypePAP` (1 = Passer commande, 2 = Passer au paiement) distingue le
     TYPE de dossier. `idStatut` est un pur statut de cycle de vie, commun aux
     deux types : 1 = En attente | 2 = Validé (DGA) | 3 = Avis favorable (DFC)
     | 4 = Accepter | 5 = Rejetée | 6 = En paiement | 7 = Terminée. Les deux
     colonnes sont donc indépendantes : passerCommande() et passerPaiement()
     initialisent toutes deux idStatut = 1, seul idTypePAP diffère (1 / 2).
   - Les PDF pro forma sont déplacés vers UPLOAD_DIR_COMMANDES (chemin relatif
     stocké dans `documents_pap.doc`).
═══════════════════════════════════════════════════════════════════════════ */

define('UPLOAD_DIR_COMMANDES', __DIR__ . '/../../documents/commandes'); // ← ajuster selon l'arborescence réelle
define('UPLOAD_URL_COMMANDES', 'http://localhost/personnel/basi/documents/commandes');          // ← chemin public correspondant

function listerModesReglement(PDO $bdBASI): void {
    try {
        $stmt = $bdBASI->query("SELECT id, mode_reglement AS nom FROM mode_reglement ORDER BY mode_reglement ASC");
        if (!$stmt) throw new \RuntimeException('Requête des modes de règlement échouée.');
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Throwable $e) {
        error_log('[Commande][listerModesReglement] ' . $e->getMessage());
        erreurSql('Impossible de charger les modes de règlement.');
    }
}

function listerModesPaiement(PDO $bdBASI): void {
    try {
        $stmt = $bdBASI->query("SELECT id, mode_paiement AS nom FROM mode_paiement ORDER BY id ASC");
        if (!$stmt) throw new \RuntimeException('Requête des modalités de paiement échouée.');
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Throwable $e) {
        error_log('[Commande][listerModesPaiement] ' . $e->getMessage());
        erreurSql('Impossible de charger les modalités de paiement.');
    }
}

/**
 * Enregistre une commande (bon de commande) pour une ou plusieurs lignes
 * d'une même demande d'achat. Transactionnel : soit tout est enregistré,
 * soit rien ne l'est.
 *
 * Requête multipart/form-data (upload de fichiers) — $_POST/$_FILES sont
 * donc directement exploitables (pas besoin de inputValue()/getJsonBody()).
 *
 * Champs attendus :
 *   - idD, id_mode_reglement, id_mode_paiement
 *   - nb_tranche, tranches (JSON [{ordre,pourcentage}, ...])  — si modalité = 3
 *   - lignes (JSON [{idDemande, quantite_reelle, id_unite, nb_unites}, ...])
 *     (prix_reel n'est plus attendu ici : NULL à cette étape)
 *   - Jusqu'à 3 pro forma, chacun avec fichier + fournisseur obligatoires :
 *       proforma_file_1 / proforma_fournisseur_1
 *       proforma_file_2 / proforma_fournisseur_2
 *       proforma_file_3 / proforma_fournisseur_3
 *     (au moins un couple fichier+fournisseur complet est requis)
 */
function passerCommande(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        // idD n'est plus obligatoire : une commande peut désormais regrouper
        // des lignes provenant de plusieurs demandes d'achat différentes.
        // Chaque ligne porte de toute façon sa propre demande d'origine
        // (demandes_ligne.idD) — c'est elle qui fait foi, pas un idD global.
        $idModeReglement = (int)($_POST['id_mode_reglement'] ?? 0);
        $idModePaiement  = (int)($_POST['id_mode_paiement'] ?? 0);

        if ($idModeReglement <= 0) { echo json_encode(['status'=>'error','message'=>'Mode de règlement requis.']); return; }
        if ($idModePaiement  <= 0) { echo json_encode(['status'=>'error','message'=>'Modalité de paiement requise.']); return; }

        $lignesInput = json_decode($_POST['lignes'] ?? '[]', true);
        if (!is_array($lignesInput) || empty($lignesInput)) {
            echo json_encode(['status' => 'error', 'message' => 'Aucune ligne à commander.']);
            return;
        }

        // ── Tranches (uniquement si modalité de paiement = 3) ─────────────────
        $estParTranche = ($idModePaiement === 3);
        $nbTranche     = null;
        $tranches      = [];

        if ($estParTranche) {
            $nbTranche = (int)($_POST['nb_tranche'] ?? 0);
            $tranches  = json_decode($_POST['tranches'] ?? '[]', true);

            if ($nbTranche < 2 || !is_array($tranches) || count($tranches) !== $nbTranche) {
                echo json_encode(['status' => 'error', 'message' => 'Répartition des tranches invalide.']);
                return;
            }

            $somme = 0.0;
            foreach ($tranches as $t) {
                $somme += (float)($t['pourcentage'] ?? 0);
            }
            foreach ($tranches as $idx => $t) {
                $pourcentage = (float)($t['pourcentage'] ?? 0);
                if ($pourcentage <= 0) {
                    echo json_encode(['status' => 'error', 'message' => 'La tranche ' . ($idx + 1) . ' doit être strictement supérieure à 0 %.']);
                    return;
                }
            }
            if (abs($somme - 100.0) > 0.01) {
                echo json_encode(['status' => 'error', 'message' => 'La somme des tranches doit être exactement égale à 100 % (actuellement ' . number_format($somme, 2) . ' %).']);
                return;
            }
        }

        // ── Validation + calcul des lignes ─────────────────────────────────────
        // Chaque ligne est vérifiée par rapport au qte_restant ACTUEL de la
        // demandes_ligne correspondante, pour ne jamais commander au-delà de
        // ce qui reste réellement disponible sur la demande. Les lignes
        // peuvent provenir de demandes d'achat différentes — seul le type
        // de la demande (achat) et son statut (soumise) sont vérifiés, pas
        // un idD particulier.
        // Le prix n'est plus saisi ici : prix_reel et montant_total_ligne
        // sont NULL, ils seront renseignés lors d'une procédure ultérieure.
        $lignesValidees = [];

        $stmtLigne = $bdBASI->prepare("
            SELECT dal.idDL, dal.idD, dal.quantite, dal.qte_commandee, dal.qte_restant, dal.unite_id
            FROM demandes_ligne dal
            JOIN demandes d ON dal.idD = d.idD
            WHERE dal.idDL = ?
              AND dal.statut = 'crée'
              AND d.idTypeDemande = 1
              AND COALESCE(d.etat_demande, 'En création') NOT IN ('En création', 'Supprimée')
            LIMIT 1
        ");
        if (!$stmtLigne) throw new \RuntimeException('Préparation de la vérification des lignes impossible.');

        foreach ($lignesInput as $l) {
            $idDL           = (int)($l['idDemande'] ?? 0); // idDemande = demandes_ligne.idDL (contrat de la page)
            $quantiteReelle = (float)($l['quantite_reelle'] ?? 0);
            $idUnite        = isset($l['id_unite']) && $l['id_unite'] !== '' ? (int)$l['id_unite'] : null;
            $nbUnites       = isset($l['nb_unites']) && $l['nb_unites'] !== '' ? (float)$l['nb_unites'] : null;

            if ($idDL <= 0)           { echo json_encode(['status'=>'error','message'=>'Ligne invalide.']); return; }
            if ($quantiteReelle <= 0) { echo json_encode(['status'=>'error','message'=>"Le nombre de pièces doit être supérieur à 0 pour chaque ligne."]); return; }

            $stmtLigne->execute([$idDL]);
            $ligneDb = $stmtLigne->fetch(PDO::FETCH_ASSOC);
            $stmtLigne->closeCursor();
            if (!$ligneDb) {
                echo json_encode(['status' => 'error', 'message' => "Une des lignes sélectionnées est introuvable ou n'est plus disponible (ligne #$idDL)."]);
                return;
            }

            $quantiteRestanteActuelle = $ligneDb['qte_restant'] !== null
                ? (float)$ligneDb['qte_restant']
                : (float)$ligneDb['quantite'];

            if ($quantiteReelle > $quantiteRestanteActuelle) {
                echo json_encode([
                    'status'  => 'error',
                    'message' => "Quantité demandée ($quantiteReelle) supérieure à la quantité restante ($quantiteRestanteActuelle) pour la ligne #$idDL.",
                ]);
                return;
            }

            // pieces_par_unite :
            //   - id_unite == 1        → quantite_reelle (pas de conversion d'unité)
            //   - id_unite != 1 (connu) → nb_unites × quantite_reelle
            //   - id_unite inconnu      → NULL
            if ($idUnite === 1) {
                $piecesParUnite = $quantiteReelle;
            } elseif ($idUnite !== null && $nbUnites !== null) {
                $piecesParUnite = $nbUnites * $quantiteReelle;
            } else {
                $piecesParUnite = null;
            }

            $lignesValidees[] = [
                'idDL'                   => $idDL,
                'idD'                    => (int)$ligneDb['idD'],
                'quantite_reelle'        => $quantiteReelle,
                'id_unite'               => $idUnite,
                'nb_unites'              => ($idUnite !== null && $idUnite !== 1) ? $nbUnites : null,
                'pieces_par_unite'       => $piecesParUnite,
                'quantite_demande_totale'=> (float)$ligneDb['quantite'],
                'qte_commandee_actuelle' => $ligneDb['qte_commandee'] !== null ? (float)$ligneDb['qte_commandee'] : 0.0,
            ];
        }

        // ── Pro forma (jusqu'à 3), chacun avec fournisseur obligatoire ──────────
        $prosforma = [];
        for ($i = 1; $i <= 3; $i++) {
            $cleFichier     = "proforma_file_$i";
            $cleFournisseur = "proforma_fournisseur_$i";

            $aUnFichier      = !empty($_FILES[$cleFichier]) && $_FILES[$cleFichier]['error'] === UPLOAD_ERR_OK;
            $idFournisseurPF = (int)($_POST[$cleFournisseur] ?? 0);

            if (!$aUnFichier && $idFournisseurPF <= 0) {
                continue; // slot non utilisé
            }
            if (!$aUnFichier || $idFournisseurPF <= 0) {
                echo json_encode([
                    'status'  => 'error',
                    'message' => "Pro forma $i : le fichier PDF et le fournisseur sont tous les deux obligatoires si l'un des deux est renseigné.",
                ]);
                return;
            }

            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES[$cleFichier]['tmp_name']);
            if ($mimeType !== 'application/pdf') {
                echo json_encode(['status' => 'error', 'message' => "Pro forma $i : la pièce jointe doit être un fichier PDF."]);
                return;
            }

            $prosforma[] = ['fichier' => $_FILES[$cleFichier], 'id_fournisseur' => $idFournisseurPF];
        }

        if (empty($prosforma)) {
            echo json_encode(['status' => 'error', 'message' => 'Au moins une facture pro forma (avec son fournisseur) est requise.']);
            return;
        }

        if (!is_dir(UPLOAD_DIR_COMMANDES)) {
            mkdir(UPLOAD_DIR_COMMANDES, 0755, true);
        }

        // ── Transaction ──────────────────────────────────────────────────────────
        $bdBASI->beginTransaction();

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $nomCommande  = 'commande_' . date('Ymd_His');
        $dateCreation = $dateEnregistrement;

        // montant_total NULL à cette étape : le prix n'est pas encore saisi.
        // idD retiré : une commande peut regrouper des lignes de plusieurs
        // demandes différentes — la trace de la demande d'origine reste
        // disponible par ligne via passer_achat_et_paiement_ligne → idDL →
        // demandes_ligne.idD.
        // idTypePAP = 1 (Passer commande) — distingue désormais le TYPE de
        // dossier, indépendamment d'idStatut qui redevient un pur statut de
        // cycle de vie (1 = en attente, 2 = validé DGA, 3 = avis favorable DFC).
        $stmtInsertPAP = $bdBASI->prepare("
            INSERT INTO passer_achat_et_paiement
                (nom_commande, montant_total, idStatut, idTypePAP,
                 dateCreation, id_mode_reglement, id_mode_paiement, nb_tranche, idUtilisateur)
            VALUES (?, NULL, 1, 1, ?, ?, ?, ?, ?)
        ");
        $stmtInsertPAP->execute([
            $nomCommande,
            $dateCreation, $idModeReglement, $idModePaiement,
            $estParTranche ? $nbTranche : null, $sessionUserId,
        ]);
        $idPAP = (int)$bdBASI->lastInsertId();

        // Historique de l'enregistrement principal
        $bdBASI->prepare("
            INSERT INTO historique_passer_achat_et_paiement
                (idPAP, nom_commande, montant_total, idStatut, idTypePAP,
                 dateCreation, id_mode_reglement, id_mode_paiement, nb_tranche, idUtilisateur,
                 motif, dateEnregistrement)
            VALUES (?, ?, NULL, 1, 1, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $idPAP, $nomCommande,
            $dateCreation, $idModeReglement, $idModePaiement,
            $estParTranche ? $nbTranche : null, $sessionUserId,
            "Création de la commande (par $sessionMatricule)", $dateEnregistrement,
        ]);

        // Tranches
        if ($estParTranche) {
            $stmtInsertTranche = $bdBASI->prepare("
                INSERT INTO tranches (idPAP, ordre, pourcentage, created_at, updated_at)
                VALUES (?, ?, ?, ?, NULL)
            ");
            $stmtInsertTrancheHisto = $bdBASI->prepare("
                INSERT INTO tranches_histo
                    (id_tranche, idPAP, ordre, pourcentage, created_at, updated_at, action, dateEnregistrement)
                VALUES (?, ?, ?, ?, ?, NULL, 'Création', ?)
            ");
            foreach ($tranches as $t) {
                $ordre       = (int)($t['ordre'] ?? 0);
                $pourcentage = (float)($t['pourcentage'] ?? 0);
                $stmtInsertTranche->execute([$idPAP, $ordre, $pourcentage, $dateEnregistrement]);
                $idTranche = (int)$bdBASI->lastInsertId();
                $stmtInsertTrancheHisto->execute([$idTranche, $idPAP, $ordre, $pourcentage, $dateEnregistrement, $dateEnregistrement]);
            }
        }

        // Pro forma → upload physique + insertion dans documents_pap
        // statut = 1 (par défaut), choix = 0 (par défaut, non retenu pour l'instant)
        $stmtInsertDoc = $bdBASI->prepare("
            INSERT INTO documents_pap (idPAP, doc, id_fournisseur, statut, choix, dateEnregistrement)
            VALUES (?, ?, ?, 1, 0, ?)
        ");
        foreach ($prosforma as $pf) {
            $nomFichier   = 'proforma_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
            $cheminAbsolu = UPLOAD_DIR_COMMANDES . '/' . $nomFichier;

            if (!move_uploaded_file($pf['fichier']['tmp_name'], $cheminAbsolu)) {
                throw new \RuntimeException("Échec de l'enregistrement d'un fichier pro forma.");
            }
            $cheminPublic = UPLOAD_URL_COMMANDES . '/' . $nomFichier;

            $stmtInsertDoc->execute([$idPAP, $cheminPublic, $pf['id_fournisseur'], $dateEnregistrement]);
        }

        // Lignes de commande + mise à jour de demandes_ligne (qte_commandee / qte_restant)
        // id_statut_PAPL = 1 par défaut à la création ("Créer").
        // prix_reel / montant_total_ligne : NULL à cette étape.
        $stmtInsertPAPL = $bdBASI->prepare("
            INSERT INTO passer_achat_et_paiement_ligne
                (idPAP, idDL, prix_reel, quantite_reelle, montant_total_ligne, date_ajout,
                 id_unite, nb_unites, pieces_par_unite, id_statut_PAPL)
            VALUES (?, ?, NULL, ?, NULL, ?, ?, ?, ?, 1)
        ");
        $stmtInsertPAPLHisto = $bdBASI->prepare("
            INSERT INTO historique_passer_achat_et_paiement_ligne
                (idPAPL, idPAP, idDL, prix_reel, quantite_reelle, montant_total_ligne, date_ajout,
                 id_unite, nb_unites, pieces_par_unite, id_statut_PAPL, motif, dateEnregistrement)
            VALUES (?, ?, ?, NULL, ?, NULL, ?, ?, ?, ?, 1, 'Création', ?)
        ");
        $stmtUpdateLigne = $bdBASI->prepare("
            UPDATE demandes_ligne SET qte_commandee = ?, qte_restant = ? WHERE idDL = ?
        ");

        foreach ($lignesValidees as $l) {
            $stmtInsertPAPL->execute([
                $idPAP, $l['idDL'], $l['quantite_reelle'],
                $dateEnregistrement, $l['id_unite'], $l['nb_unites'], $l['pieces_par_unite'],
            ]);
            $idPAPL = (int)$bdBASI->lastInsertId();

            $stmtInsertPAPLHisto->execute([
                $idPAPL, $idPAP, $l['idDL'], $l['quantite_reelle'],
                $dateEnregistrement, $l['id_unite'], $l['nb_unites'], $l['pieces_par_unite'], $dateEnregistrement,
            ]);

            // NB : mise à jour cumulative de demandes_ligne (une ligne peut faire
            // l'objet de plusieurs commandes successives tant qu'il reste du restant).
            $nouvelleQteCommandee = $l['qte_commandee_actuelle'] + $l['quantite_reelle'];
            $nouvelleQteRestante  = max(0, $l['quantite_demande_totale'] - $nouvelleQteCommandee);
            $stmtUpdateLigne->execute([$nouvelleQteCommandee, $nouvelleQteRestante, $l['idDL']]);
        }

        $bdBASI->commit();

        echo json_encode([
            'status'  => 'success',
            'message' => "Commande $nomCommande enregistrée avec succès.",
            'idPAP'   => $idPAP,
        ]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Commande][passerCommande] ' . $e->getMessage());
        erreurSql("Impossible d'enregistrer la commande.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Passer au paiement

   Hypothèses de schéma (à ajuster si elles ne correspondent pas à la base) :
   - passer_achat_et_paiement.idStatut = 2 par défaut pour un enregistrement
     de paiement (contre 1 pour une commande d'achat).
   - passer_achat_et_paiement_ligne.id_statut_PAPL reste 1 par défaut ("créé"),
     inchangé qu'il s'agisse d'une commande ou d'un paiement.
   - Table `document_justificatif_paiement` : idPAP, doc, statut (défaut 1),
     dateEnregistrement — un enregistrement par justificatif téléversé.
   - Les justificatifs sont acceptés en PDF ou image (jpg/jpeg/png) — un
     reçu de paiement est souvent une photo/scan, pas nécessairement un PDF.
═══════════════════════════════════════════════════════════════════════════ */

define('UPLOAD_DIR_JUSTIFICATIFS', __DIR__ . '/../../documents/justificatifs'); // ← ajuster si besoin
define('UPLOAD_URL_JUSTIFICATIFS', 'http://localhost//personnel/basi/documents/justificatifs');

/**
 * Enregistre un paiement (passer_achat_et_paiement + ses lignes) pour une ou
 * plusieurs lignes d'une même demande de paiement. Transactionnel.
 *
 * Requête multipart/form-data (upload de fichiers) — $_POST/$_FILES sont
 * directement exploitables.
 *
 * Champs attendus :
 *   - id_mode_reglement, id_mode_paiement
 *   - nb_tranche, tranches (JSON [{ordre,pourcentage}, ...]) — si modalité = 3
 *   - lignes (JSON [{idDemande, montant}, ...])  (idDemande = demandes_ligne.idDL)
 *   - justificatifs[] : un ou plusieurs fichiers (PDF/JPG/PNG)
 */
function passerPaiement(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        // idD n'est plus obligatoire : un paiement peut regrouper des lignes
        // provenant de plusieurs demandes de paiement différentes. Chaque
        // ligne porte de toute façon sa propre demande d'origine
        // (demandes_ligne.idD) — c'est elle qui fait foi.
        $idModeReglement = (int)($_POST['id_mode_reglement'] ?? 0);
        $idModePaiement  = (int)($_POST['id_mode_paiement'] ?? 0);

        if ($idModeReglement <= 0) { echo json_encode(['status'=>'error','message'=>'Mode de règlement requis.']); return; }
        if ($idModePaiement  <= 0) { echo json_encode(['status'=>'error','message'=>'Modalité de paiement requise.']); return; }

        $lignesInput = json_decode($_POST['lignes'] ?? '[]', true);
        if (!is_array($lignesInput) || empty($lignesInput)) {
            echo json_encode(['status' => 'error', 'message' => 'Aucune ligne à payer.']);
            return;
        }

        // ── Tranches (uniquement si modalité de paiement = 3) ─────────────────
        $estParTranche = ($idModePaiement === 3);
        $nbTranche     = null;
        $tranches      = [];

        if ($estParTranche) {
            $nbTranche = (int)($_POST['nb_tranche'] ?? 0);
            $tranches  = json_decode($_POST['tranches'] ?? '[]', true);

            if ($nbTranche < 2 || !is_array($tranches) || count($tranches) !== $nbTranche) {
                echo json_encode(['status' => 'error', 'message' => 'Répartition des tranches invalide.']);
                return;
            }

            $somme = 0.0;
            foreach ($tranches as $t) {
                $somme += (float)($t['pourcentage'] ?? 0);
            }
            foreach ($tranches as $idx => $t) {
                $pourcentage = (float)($t['pourcentage'] ?? 0);
                if ($pourcentage <= 0) {
                    echo json_encode(['status' => 'error', 'message' => 'La tranche ' . ($idx + 1) . ' doit être strictement supérieure à 0 %.']);
                    return;
                }
            }
            if (abs($somme - 100.0) > 0.01) {
                echo json_encode(['status' => 'error', 'message' => 'La somme des tranches doit être exactement égale à 100 % (actuellement ' . number_format($somme, 2) . ' %).']);
                return;
            }
        }

        // ── Validation + calcul des lignes ─────────────────────────────────────
        // Chaque montant est vérifié par rapport au mnt_restant ACTUEL de la
        // demandes_ligne correspondante, pour ne jamais payer au-delà de ce
        // qui reste réellement dû sur la ligne. Les lignes peuvent provenir
        // de demandes de paiement différentes — seul le type de la demande
        // (paiement) et son statut (soumise) sont vérifiés.
        $lignesValidees      = [];
        $montantTotalPaiement = 0.0;

        $stmtLigne = $bdBASI->prepare("
            SELECT dal.idDL, dal.idD, dal.montant_total, dal.mnt_paye, dal.mnt_restant
            FROM demandes_ligne dal
            JOIN demandes d ON dal.idD = d.idD
            WHERE dal.idDL = ?
              AND dal.statut = 'crée'
              AND d.idTypeDemande = 2
              AND COALESCE(d.etat_demande, 'En création') NOT IN ('En création', 'Supprimée')
            LIMIT 1
        ");
        if (!$stmtLigne) throw new \RuntimeException('Préparation de la vérification des lignes impossible.');

        foreach ($lignesInput as $l) {
            $idDL    = (int)($l['idDemande'] ?? 0); // idDemande = demandes_ligne.idDL (contrat de la page)
            $montant = (float)($l['montant'] ?? -1);

            if ($idDL <= 0)   { echo json_encode(['status'=>'error','message'=>'Ligne invalide.']); return; }
            if ($montant <= 0) { echo json_encode(['status'=>'error','message'=>'Le montant doit être supérieur à 0 pour chaque ligne.']); return; }

            $stmtLigne->execute([$idDL]);
            $ligneDb = $stmtLigne->fetch(PDO::FETCH_ASSOC);
            $stmtLigne->closeCursor();
            if (!$ligneDb) {
                echo json_encode(['status' => 'error', 'message' => "Une des lignes sélectionnées est introuvable ou n'est plus disponible (ligne #$idDL)."]);
                return;
            }

            $montantDemandeTotal = (float)$ligneDb['montant_total'];
            $montantRestantActuel = $ligneDb['mnt_restant'] !== null
                ? (float)$ligneDb['mnt_restant']
                : $montantDemandeTotal;

            if ($montant > $montantRestantActuel) {
                echo json_encode([
                    'status'  => 'error',
                    'message' => "Montant saisi (" . number_format($montant, 0, ',', ' ') . " FCFA) supérieur au montant restant (" .
                        number_format($montantRestantActuel, 0, ',', ' ') . " FCFA) pour la ligne #$idDL.",
                ]);
                return;
            }

            $montantTotalPaiement += $montant;

            $lignesValidees[] = [
                'idDL'                  => $idDL,
                'montant'               => $montant,
                'mnt_paye_actuel'       => $ligneDb['mnt_paye'] !== null ? (float)$ligneDb['mnt_paye'] : 0.0,
                'montant_demande_total' => $montantDemandeTotal,
            ];
        }

        // ── Justificatifs de paiement (un ou plusieurs, obligatoires) ───────────
        $justificatifs = [];
        if (!empty($_FILES['justificatifs']) && is_array($_FILES['justificatifs']['name'])) {
            $nb = count($_FILES['justificatifs']['name']);
            for ($i = 0; $i < $nb; $i++) {
                if ($_FILES['justificatifs']['error'][$i] !== UPLOAD_ERR_OK) continue;

                $tmpName = $_FILES['justificatifs']['tmp_name'][$i];
                $finfo    = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($tmpName);
                $extensionsAutorisees = ['application/pdf', 'image/jpeg', 'image/png'];
                if (!in_array($mimeType, $extensionsAutorisees, true)) {
                    echo json_encode(['status' => 'error', 'message' => "Le justificatif #" . ($i + 1) . " doit être un PDF, JPG ou PNG."]);
                    return;
                }

                $justificatifs[] = ['tmp_name' => $tmpName, 'mime' => $mimeType];
            }
        }

        if (empty($justificatifs)) {
            echo json_encode(['status' => 'error', 'message' => 'Au moins un justificatif de paiement est requis.']);
            return;
        }

        if (!is_dir(UPLOAD_DIR_JUSTIFICATIFS)) {
            mkdir(UPLOAD_DIR_JUSTIFICATIFS, 0755, true);
        }

        // ── Transaction ──────────────────────────────────────────────────────────
        $bdBASI->beginTransaction();

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $nomPaiement  = 'paiement_' . date('Ymd_His');
        $dateCreation = $dateEnregistrement;

        // idTypePAP = 2 (Passer au paiement) — distingue le TYPE de dossier ;
        // idStatut redevient un pur statut de cycle de vie, uniforme avec
        // passerCommande (1 = en attente, 2 = validé DGA, 3 = avis favorable DFC).
        // idD retiré : un paiement peut regrouper des lignes de plusieurs
        // demandes différentes — la trace de la demande d'origine reste
        // disponible par ligne via passer_achat_et_paiement_ligne → idDL →
        // demandes_ligne.idD.
        $stmtInsertPAP = $bdBASI->prepare("
            INSERT INTO passer_achat_et_paiement
                (nom_commande, montant_total, idStatut, idTypePAP,
                 dateCreation, id_mode_reglement, id_mode_paiement, nb_tranche, idUtilisateur)
            VALUES (?, ?, 2, 2, ?, ?, ?, ?, ?)
        ");
        $stmtInsertPAP->execute([
            $nomPaiement, $montantTotalPaiement,
            $dateCreation, $idModeReglement, $idModePaiement,
            $estParTranche ? $nbTranche : null, $sessionUserId,
        ]);
        $idPAP = (int)$bdBASI->lastInsertId();

        // Historique de l'enregistrement principal
        $bdBASI->prepare("
            INSERT INTO historique_passer_achat_et_paiement
                (idPAP, nom_commande, montant_total, idStatut, idTypePAP,
                 dateCreation, id_mode_reglement, id_mode_paiement, nb_tranche, idUtilisateur,
                 motif, dateEnregistrement)
            VALUES (?, ?, ?, 2, 2, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $idPAP, $nomPaiement, $montantTotalPaiement,
            $dateCreation, $idModeReglement, $idModePaiement,
            $estParTranche ? $nbTranche : null, $sessionUserId,
            "Création du paiement (par $sessionMatricule)", $dateEnregistrement,
        ]);

        // Tranches
        if ($estParTranche) {
            $stmtInsertTranche = $bdBASI->prepare("
                INSERT INTO tranches (idPAP, ordre, pourcentage, created_at, updated_at)
                VALUES (?, ?, ?, ?, NULL)
            ");
            $stmtInsertTrancheHisto = $bdBASI->prepare("
                INSERT INTO tranches_histo
                    (id_tranche, idPAP, ordre, pourcentage, created_at, updated_at, action, dateEnregistrement)
                VALUES (?, ?, ?, ?, ?, NULL, 'Création', ?)
            ");
            foreach ($tranches as $t) {
                $ordre       = (int)($t['ordre'] ?? 0);
                $pourcentage = (float)($t['pourcentage'] ?? 0);
                $stmtInsertTranche->execute([$idPAP, $ordre, $pourcentage, $dateEnregistrement]);
                $idTranche = (int)$bdBASI->lastInsertId();
                $stmtInsertTrancheHisto->execute([$idTranche, $idPAP, $ordre, $pourcentage, $dateEnregistrement, $dateEnregistrement]);
            }
        }

        // Lignes de paiement + mise à jour de demandes_ligne (mnt_paye / mnt_restant)
        // prix_reel, quantite_reelle, id_unite, nb_unites, pieces_par_unite : NULL.
        // id_statut_PAPL = 1 par défaut (inchangé, "créé").
        $stmtInsertPAPL = $bdBASI->prepare("
            INSERT INTO passer_achat_et_paiement_ligne
                (idPAP, idDL, prix_reel, quantite_reelle, montant_total_ligne, date_ajout,
                 id_unite, nb_unites, pieces_par_unite, id_statut_PAPL)
            VALUES (?, ?, NULL, NULL, ?, ?, NULL, NULL, NULL, 1)
        ");
        $stmtInsertPAPLHisto = $bdBASI->prepare("
            INSERT INTO historique_passer_achat_et_paiement_ligne
                (idPAPL, idPAP, idDL, prix_reel, quantite_reelle, montant_total_ligne, date_ajout,
                 id_unite, nb_unites, pieces_par_unite, id_statut_PAPL, motif, dateEnregistrement)
            VALUES (?, ?, ?, NULL, NULL, ?, ?, NULL, NULL, NULL, 1, 'Création', ?)
        ");
        $stmtUpdateLigne = $bdBASI->prepare("
            UPDATE demandes_ligne SET mnt_paye = ?, mnt_restant = ? WHERE idDL = ?
        ");

        foreach ($lignesValidees as $l) {
            $stmtInsertPAPL->execute([
                $idPAP, $l['idDL'], $l['montant'], $dateEnregistrement,
            ]);
            $idPAPL = (int)$bdBASI->lastInsertId();

            $stmtInsertPAPLHisto->execute([
                $idPAPL, $idPAP, $l['idDL'], $l['montant'], $dateEnregistrement, $dateEnregistrement,
            ]);

            // NB : mise à jour cumulative de demandes_ligne (une ligne peut faire
            // l'objet de plusieurs paiements successifs tant qu'il reste du restant).
            $nouveauMntPaye    = $l['mnt_paye_actuel'] + $l['montant'];
            $nouveauMntRestant = max(0, $l['montant_demande_total'] - $nouveauMntPaye);
            $stmtUpdateLigne->execute([$nouveauMntPaye, $nouveauMntRestant, $l['idDL']]);
        }

        // Justificatifs de paiement : upload physique + insertion dans
        // document_justificatif_paiement (statut = 1 par défaut).
        $stmtInsertJustif = $bdBASI->prepare("
            INSERT INTO document_justificatif_paiement (idPAP, doc, statut, dateEnregistrement)
            VALUES (?, ?, 1, ?)
        ");
        $extensionParMime = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        foreach ($justificatifs as $j) {
            $ext        = $extensionParMime[$j['mime']] ?? 'bin';
            $nomFichier = 'justificatif_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $cheminAbsolu = UPLOAD_DIR_JUSTIFICATIFS . '/' . $nomFichier;

            if (!move_uploaded_file($j['tmp_name'], $cheminAbsolu)) {
                throw new \RuntimeException("Échec de l'enregistrement d'un justificatif de paiement.");
            }
            $cheminPublic = UPLOAD_URL_JUSTIFICATIFS . '/' . $nomFichier;
            $stmtInsertJustif->execute([$idPAP, $cheminPublic, $dateEnregistrement]);
        }

        $bdBASI->commit();

        echo json_encode([
            'status'  => 'success',
            'message' => "Paiement $nomPaiement enregistré avec succès.",
            'idPAP'   => $idPAP,
        ]);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Paiement][passerPaiement] ' . $e->getMessage());
        erreurSql("Impossible d'enregistrer le paiement.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Modification des opérations rejetées (idStatut = 5)

   Hypothèses de schéma (à ajuster si elles ne correspondent pas à la base) :
   - `passer_achat_et_paiement_ligne.id_statut_PAPL` : 1 = créée (par défaut),
     2 = supprimée (mise à jour lors d'une modification, cf. ce module).
   - Après modification réussie, `passer_achat_et_paiement.idStatut` repasse
     de 5 (Rejetée) à 1 (En attente) — le dossier réintègre le circuit complet
     de validation (DGA → DFC → DG).
   - `tranches` a désormais une colonne `statut` (1 = active, 0 = remplacée
     par une modification) — absente de la structure d'origine, à ajouter :
       ALTER TABLE tranches ADD COLUMN statut TINYINT DEFAULT 1;
   - Au moins une ligne (id_statut_PAPL = 1) doit toujours subsister par
     dossier — la suppression de la dernière ligne active est rejetée.
   - Lorsqu'une ligne est modifiée ou supprimée, sa contribution précédente
     est d'abord "restituée" à demandes_ligne (qte_commandee/qte_restant pour
     l'achat, mnt_paye/mnt_restant pour le paiement) avant de revalider et
     d'appliquer la nouvelle valeur — symétrique à la logique de
     passerCommande()/passerPaiement().
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Liste des dossiers modifiables : passer_achat_et_paiement.idStatut = 5
 * (Rejetée), toutes provenances confondues (idTypePAP distingue achat/paiement).
 */
function listerDossiersAModifier(PDO $bdBASI, drhController $basiController): void {
    try {
        $stmt = $bdBASI->query("
            SELECT
                p.id AS idPAP,
                p.nom_commande,
                p.dateCreation,
                p.idTypePAP,
                p.motifRejet,
                CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM passer_achat_et_paiement p
            LEFT JOIN utilisateurs u ON p.idUtilisateur = u.id
            WHERE p.idStatut = 5
            ORDER BY p.dateCreation DESC
        ");
        if (!$stmt) throw new \RuntimeException('Requête de liste des dossiers à modifier échouée.');

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['tmp'] = $basiController->tokenencrypt($r['idPAP']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'data' => $rows]);
    } catch (\Throwable $e) {
        error_log('[Modif][listerDossiersAModifier] ' . $e->getMessage());
        erreurSql('Impossible de charger la liste des dossiers à modifier.');
    }
}

/**
 * Détail d'un dossier rejeté pour pré-remplir le formulaire de modification :
 *   - idTypePAP = 1 (achat) : lignes (idDL, désignation, quantite_reelle,
 *     unité, quantité disponible en tenant compte de la contribution actuelle
 *     de la ligne) + pro forma actifs (documents_pap, statut = 1).
 *   - idTypePAP = 2 (paiement) : lignes (montant, montant disponible) +
 *     justificatif(s) actif(s) (document_justificatif_paiement, statut = 1).
 *
 * Paramètre : token (chiffré de idPAP)
 */
function detailDossierAModifier(PDO $bdBASI, drhController $basiController): void {
    try {
        $token = trim((string) inputValue('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmtC = $bdBASI->prepare("
            SELECT id AS idPAP, nom_commande, idStatut, idTypePAP, id_mode_reglement, id_mode_paiement, nb_tranche, motifRejet
            FROM passer_achat_et_paiement
            WHERE id = ? AND idStatut = 5
            LIMIT 1
        ");
        $stmtC->execute([$idPAP]);
        $dossier = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$dossier) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable ou non modifiable.']);
            return;
        }

        $estAchat = ((int)$dossier['idTypePAP'] === 1);

        if ($estAchat) {
            $stmtL = $bdBASI->prepare("
                SELECT
                    papl.id AS idPAPL, papl.idDL, papl.quantite_reelle, papl.id_unite, papl.nb_unites,
                    lb.designation, lu.unite,
                    dal.quantite AS quantite_demandee,
                    COALESCE(CAST(dal.qte_restant AS DECIMAL(15,2)), dal.quantite) AS qte_restant_actuelle
                FROM passer_achat_et_paiement_ligne papl
                JOIN demandes_ligne dal ON papl.idDL = dal.idDL
                JOIN ligneBudget    lb  ON dal.idLB  = lb.id
                LEFT JOIN listeUnites lu ON papl.id_unite = lu.id
                WHERE papl.idPAP = ? AND papl.id_statut_PAPL = 1
                ORDER BY papl.id ASC
            ");
            $stmtL->execute([$idPAP]);
            $lignes = $stmtL->fetchAll(PDO::FETCH_ASSOC);
            foreach ($lignes as &$l) {
                // Disponible = restant actuel + ce que CETTE ligne a déjà pris
                // (puisqu'on va d'abord "rendre" sa contribution avant de revalider).
                $l['quantite_disponible_max'] = (float)$l['qte_restant_actuelle'] + (float)($l['quantite_reelle'] ?? 0);
            }
            unset($l);

            $stmtDoc = $bdBASI->prepare("
                SELECT dp.id, dp.doc, dp.id_fournisseur, f.nomF, f.prenomF, f.entreprise
                FROM documents_pap dp
                JOIN fournisseur f ON dp.id_fournisseur = f.idF
                WHERE dp.idPAP = ? AND dp.statut = 1
                ORDER BY dp.id ASC
            ");
            $stmtDoc->execute([$idPAP]);
            $documents = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmtL = $bdBASI->prepare("
                SELECT
                    papl.id AS idPAPL, papl.idDL, papl.montant_total_ligne,
                    lb.designation,
                    dal.montant_total AS montant_demande,
                    COALESCE(CAST(dal.mnt_restant AS DECIMAL(15,2)), dal.montant_total) AS mnt_restant_actuelle
                FROM passer_achat_et_paiement_ligne papl
                JOIN demandes_ligne dal ON papl.idDL = dal.idDL
                JOIN ligneBudget    lb  ON dal.idLB  = lb.id
                WHERE papl.idPAP = ? AND papl.id_statut_PAPL = 1
                ORDER BY papl.id ASC
            ");
            $stmtL->execute([$idPAP]);
            $lignes = $stmtL->fetchAll(PDO::FETCH_ASSOC);
            foreach ($lignes as &$l) {
                $l['montant_disponible_max'] = (float)$l['mnt_restant_actuelle'] + (float)($l['montant_total_ligne'] ?? 0);
            }
            unset($l);

            $stmtDoc = $bdBASI->prepare("
                SELECT id, doc, dateEnregistrement
                FROM document_justificatif_paiement
                WHERE idPAP = ? AND statut = 1
                ORDER BY id ASC
            ");
            $stmtDoc->execute([$idPAP]);
            $documents = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);
        }

        // Tranches actives (statut = 1), pour pré-remplir les pourcentages
        // déjà saisis lors de la création — sans ça, la modale de modification
        // rouvre le nombre de tranches mais avec des champs vides.
        $stmtTranches = $bdBASI->prepare("
            SELECT ordre, pourcentage FROM tranches WHERE idPAP = ? AND statut = 1 ORDER BY ordre ASC
        ");
        $stmtTranches->execute([$idPAP]);
        $tranches = $stmtTranches->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'dossier' => $dossier, 'lignes' => $lignes, 'documents' => $documents, 'tranches' => $tranches]);
    } catch (\Throwable $e) {
        error_log('[Modif][detailDossierAModifier] ' . $e->getMessage());
        erreurSql('Impossible de charger le détail du dossier.');
    }
}

/**
 * Modifie une opération "Passer commande" rejetée (idStatut = 5, idTypePAP = 1) :
 *   - Suppression de lignes possible (id_statut_PAPL 1 → 2), au moins une
 *     ligne active doit subsister.
 *   - Modification de la quantité des lignes conservées, revalidée contre
 *     demandes_ligne (en restituant d'abord la contribution actuelle de la ligne).
 *   - Nouveau jeu de pro forma obligatoire (au moins 1, jusqu'à 3, chacun
 *     avec fournisseur) — les documents_pap existants passent statut 1 → 0,
 *     les nouveaux sont insérés.
 *   - Tranches existantes passent statut 1 → 0 ; nouvelles tranches insérées
 *     si modalité = 3.
 *   - idStatut repasse de 5 à 1 (En attente).
 */
function modifierCommande(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        $idPAP           = (int)($_POST['idPAP'] ?? 0);
        $idModeReglement = (int)($_POST['id_mode_reglement'] ?? 0);
        $idModePaiement  = (int)($_POST['id_mode_paiement'] ?? 0);

        if ($idPAP <= 0)           { echo json_encode(['status'=>'error','message'=>'Dossier manquant.']); return; }
        if ($idModeReglement <= 0) { echo json_encode(['status'=>'error','message'=>'Mode de règlement requis.']); return; }
        if ($idModePaiement <= 0)  { echo json_encode(['status'=>'error','message'=>'Modalité de paiement requise.']); return; }

        $stmtC = $bdBASI->prepare("SELECT id FROM passer_achat_et_paiement WHERE id = ? AND idStatut = 5 AND idTypePAP = 1 LIMIT 1");
        $stmtC->execute([$idPAP]);
        if (!$stmtC->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable ou non modifiable.']);
            return;
        }

        $lignesInput = json_decode($_POST['lignes'] ?? '[]', true);
        if (!is_array($lignesInput) || empty($lignesInput)) {
            echo json_encode(['status' => 'error', 'message' => 'Aucune ligne fournie.']);
            return;
        }

        // ── Séparer lignes conservées / supprimées ──────────────────────────────
        $lignesConservees = [];
        $idsASupprimer    = [];
        foreach ($lignesInput as $l) {
            $idPAPL = (int)($l['idPAPL'] ?? 0);
            if ($idPAPL <= 0) { echo json_encode(['status'=>'error','message'=>'Ligne invalide.']); return; }

            if (($l['action'] ?? 'garder') === 'supprimer') {
                $idsASupprimer[] = $idPAPL;
            } else {
                $quantiteReelle = (float)($l['quantite_reelle'] ?? 0);
                if ($quantiteReelle <= 0) {
                    echo json_encode(['status' => 'error', 'message' => 'La quantité doit être supérieure à 0 pour chaque ligne conservée.']);
                    return;
                }
                $lignesConservees[] = [
                    'idPAPL'          => $idPAPL,
                    'quantite_reelle' => $quantiteReelle,
                    'id_unite'        => isset($l['id_unite']) && $l['id_unite'] !== '' ? (int)$l['id_unite'] : null,
                    'nb_unites'       => isset($l['nb_unites']) && $l['nb_unites'] !== '' ? (float)$l['nb_unites'] : null,
                ];
            }
        }

        if (empty($lignesConservees)) {
            echo json_encode(['status' => 'error', 'message' => 'Vous devez conserver au moins une ligne.']);
            return;
        }

        // ── Valider + calculer les lignes conservées ────────────────────────────
        $stmtLigneInfo = $bdBASI->prepare("
            SELECT papl.id, papl.idDL, papl.quantite_reelle AS ancienne_quantite,
                   dal.quantite AS quantite_demande_totale,
                   CAST(dal.qte_commandee AS DECIMAL(15,2)) AS qte_commandee_actuelle,
                   COALESCE(CAST(dal.qte_restant AS DECIMAL(15,2)), dal.quantite) AS qte_restant_actuelle
            FROM passer_achat_et_paiement_ligne papl
            JOIN demandes_ligne dal ON papl.idDL = dal.idDL
            WHERE papl.id = ? AND papl.idPAP = ? AND papl.id_statut_PAPL = 1
            LIMIT 1
        ");

        $lignesAMettreAJour = [];
        foreach ($lignesConservees as $l) {
            $stmtLigneInfo->execute([$l['idPAPL'], $idPAP]);
            $ligneDb = $stmtLigneInfo->fetch(PDO::FETCH_ASSOC);
            $stmtLigneInfo->closeCursor();
            if (!$ligneDb) {
                echo json_encode(['status' => 'error', 'message' => "Une des lignes n'appartient pas à ce dossier (ligne #{$l['idPAPL']})."]);
                return;
            }

            $ancienneQuantite     = (float)$ligneDb['ancienne_quantite'];
            $qteCommandeeActuelle = (float)$ligneDb['qte_commandee_actuelle'];
            $qteRestantActuelle   = (float)$ligneDb['qte_restant_actuelle'];
            $quantiteDemandeTotal = (float)$ligneDb['quantite_demande_totale'];

            // On "restitue" la contribution précédente de cette ligne avant de revalider.
            $qteDisponiblePourCetteLigne = $qteRestantActuelle + $ancienneQuantite;

            if ($l['quantite_reelle'] > $qteDisponiblePourCetteLigne) {
                echo json_encode([
                    'status'  => 'error',
                    'message' => "Quantité ({$l['quantite_reelle']}) supérieure à la quantité disponible ($qteDisponiblePourCetteLigne) pour la ligne #{$l['idPAPL']}.",
                ]);
                return;
            }

            $idUnite  = $l['id_unite'];
            $nbUnites = $l['nb_unites'];
            if ($idUnite === 1) {
                $piecesParUnite = $l['quantite_reelle'];
            } elseif ($idUnite !== null && $nbUnites !== null) {
                $piecesParUnite = $nbUnites * $l['quantite_reelle'];
            } else {
                $piecesParUnite = null;
            }

            $nouveauQteCommandee = $qteCommandeeActuelle - $ancienneQuantite + $l['quantite_reelle'];
            $nouveauQteRestant   = max(0, $quantiteDemandeTotal - $nouveauQteCommandee);

            $lignesAMettreAJour[] = [
                'idPAPL'             => $l['idPAPL'],
                'idDL'               => $ligneDb['idDL'],
                'quantite_reelle'    => $l['quantite_reelle'],
                'id_unite'           => $idUnite,
                'nb_unites'          => ($idUnite !== null && $idUnite !== 1) ? $nbUnites : null,
                'pieces_par_unite'   => $piecesParUnite,
                'nouveau_qte_commandee' => $nouveauQteCommandee,
                'nouveau_qte_restant'   => $nouveauQteRestant,
            ];
        }

        // ── Valider les lignes à supprimer + calculer la restitution ────────────
        $lignesASupprimerInfo = [];
        foreach ($idsASupprimer as $idPAPL) {
            $stmtLigneInfo->execute([$idPAPL, $idPAP]);
            $ligneDb = $stmtLigneInfo->fetch(PDO::FETCH_ASSOC);
            $stmtLigneInfo->closeCursor();
            if (!$ligneDb) {
                echo json_encode(['status' => 'error', 'message' => "Une des lignes à supprimer n'appartient pas à ce dossier (ligne #$idPAPL)."]);
                return;
            }

            $ancienneQuantite     = (float)$ligneDb['ancienne_quantite'];
            $qteCommandeeActuelle = (float)$ligneDb['qte_commandee_actuelle'];
            $quantiteDemandeTotal = (float)$ligneDb['quantite_demande_totale'];

            $nouveauQteCommandee = max(0, $qteCommandeeActuelle - $ancienneQuantite);
            $nouveauQteRestant   = max(0, $quantiteDemandeTotal - $nouveauQteCommandee);

            $lignesASupprimerInfo[] = [
                'idPAPL'                => $idPAPL,
                'idDL'                  => $ligneDb['idDL'],
                'nouveau_qte_commandee' => $nouveauQteCommandee,
                'nouveau_qte_restant'   => $nouveauQteRestant,
            ];
        }

        // ── Pro forma (au moins 1, jusqu'à 3, fournisseur obligatoire) ──────────
        $prosforma = [];
        for ($i = 1; $i <= 3; $i++) {
            $cleFichier      = "proforma_file_$i";
            $cleFournisseur  = "proforma_fournisseur_$i";
            $aUnFichier      = !empty($_FILES[$cleFichier]) && $_FILES[$cleFichier]['error'] === UPLOAD_ERR_OK;
            $idFournisseurPF = (int)($_POST[$cleFournisseur] ?? 0);

            if (!$aUnFichier && $idFournisseurPF <= 0) continue;
            if (!$aUnFichier || $idFournisseurPF <= 0) {
                echo json_encode(['status' => 'error', 'message' => "Pro forma $i : le fichier PDF et le fournisseur sont tous les deux obligatoires si l'un des deux est renseigné."]);
                return;
            }

            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES[$cleFichier]['tmp_name']);
            if ($mimeType !== 'application/pdf') {
                echo json_encode(['status' => 'error', 'message' => "Pro forma $i : la pièce jointe doit être un fichier PDF."]);
                return;
            }

            $prosforma[] = ['fichier' => $_FILES[$cleFichier], 'id_fournisseur' => $idFournisseurPF];
        }

        if (empty($prosforma)) {
            echo json_encode(['status' => 'error', 'message' => 'Vous devez téléverser à nouveau au moins une facture pro forma (avec son fournisseur).']);
            return;
        }

        // ── Tranches ─────────────────────────────────────────────────────────────
        $estParTranche = ($idModePaiement === 3);
        $nbTranche     = null;
        $tranches      = [];
        if ($estParTranche) {
            $nbTranche = (int)($_POST['nb_tranche'] ?? 0);
            $tranches  = json_decode($_POST['tranches'] ?? '[]', true);
            if ($nbTranche < 2 || !is_array($tranches) || count($tranches) !== $nbTranche) {
                echo json_encode(['status' => 'error', 'message' => 'Répartition des tranches invalide.']);
                return;
            }
            $somme = 0.0;
            foreach ($tranches as $t) $somme += (float)($t['pourcentage'] ?? 0);
            foreach ($tranches as $idx => $t) {
                if ((float)($t['pourcentage'] ?? 0) <= 0) {
                    echo json_encode(['status' => 'error', 'message' => 'La tranche ' . ($idx + 1) . ' doit être strictement supérieure à 0 %.']);
                    return;
                }
            }
            if (abs($somme - 100.0) > 0.01) {
                echo json_encode(['status' => 'error', 'message' => 'La somme des tranches doit être exactement égale à 100 % (actuellement ' . number_format($somme, 2) . ' %).']);
                return;
            }
        }

        if (!is_dir(UPLOAD_DIR_COMMANDES)) mkdir(UPLOAD_DIR_COMMANDES, 0755, true);

        // ── Transaction ──────────────────────────────────────────────────────────
        $bdBASI->beginTransaction();

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        // 1) Dossier : idStatut 5 → 1, modes mis à jour
        $bdBASI->prepare("
            UPDATE passer_achat_et_paiement
            SET idStatut = 1, id_mode_reglement = ?, id_mode_paiement = ?, nb_tranche = ?
            WHERE id = ?
        ")->execute([$idModeReglement, $idModePaiement, $estParTranche ? $nbTranche : null, $idPAP]);

        $bdBASI->prepare("
            INSERT INTO historique_passer_achat_et_paiement
                (idPAP, nom_commande, montant_total, idStatut, idTypePAP, dateCreation, id_mode_reglement,
                 id_mode_paiement, nb_tranche, idUtilisateur, motif, dateEnregistrement)
            SELECT id, nom_commande, montant_total, idStatut, idTypePAP, dateCreation, id_mode_reglement,
                   id_mode_paiement, nb_tranche, ?, ?, ?
            FROM passer_achat_et_paiement
            WHERE id = ?
        ")->execute([
            $sessionUserId, "Modification de la commande rejetée (par $sessionMatricule)",
            $dateEnregistrement, $idPAP,
        ]);

        // 2) Tranches : anciennes désactivées, nouvelles insérées
        $bdBASI->prepare("UPDATE tranches SET statut = 0 WHERE idPAP = ?")->execute([$idPAP]);
        if ($estParTranche) {
            $stmtInsertTranche = $bdBASI->prepare("
                INSERT INTO tranches (idPAP, ordre, pourcentage, statut, created_at, updated_at)
                VALUES (?, ?, ?, 1, ?, NULL)
            ");
            $stmtInsertTrancheHisto = $bdBASI->prepare("
                INSERT INTO tranches_histo (id_tranche, idPAP, ordre, pourcentage, created_at, updated_at, action, dateEnregistrement)
                VALUES (?, ?, ?, ?, ?, NULL, 'Modification', ?)
            ");
            foreach ($tranches as $t) {
                $ordre       = (int)($t['ordre'] ?? 0);
                $pourcentage = (float)($t['pourcentage'] ?? 0);
                $stmtInsertTranche->execute([$idPAP, $ordre, $pourcentage, $dateEnregistrement]);
                $idTranche = (int)$bdBASI->lastInsertId();
                $stmtInsertTrancheHisto->execute([$idTranche, $idPAP, $ordre, $pourcentage, $dateEnregistrement, $dateEnregistrement]);
            }
        }

        // 3) Pro forma : anciens désactivés, nouveaux insérés
        $bdBASI->prepare("UPDATE documents_pap SET statut = 0 WHERE idPAP = ? AND statut = 1")->execute([$idPAP]);
        $stmtInsertDoc = $bdBASI->prepare("
            INSERT INTO documents_pap (idPAP, doc, id_fournisseur, statut, choix, dateEnregistrement)
            VALUES (?, ?, ?, 1, 0, ?)
        ");
        foreach ($prosforma as $pf) {
            $nomFichier   = 'proforma_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
            $cheminAbsolu = UPLOAD_DIR_COMMANDES . '/' . $nomFichier;
            if (!move_uploaded_file($pf['fichier']['tmp_name'], $cheminAbsolu)) {
                throw new \RuntimeException("Échec de l'enregistrement d'un fichier pro forma.");
            }
            $cheminPublic = UPLOAD_URL_COMMANDES . '/' . $nomFichier;
            $stmtInsertDoc->execute([$idPAP, $cheminPublic, $pf['id_fournisseur'], $dateEnregistrement]);
        }

        // 4) Lignes conservées : quantité + demandes_ligne mises à jour
        $stmtUpdatePAPL = $bdBASI->prepare("
            UPDATE passer_achat_et_paiement_ligne
            SET quantite_reelle = ?, id_unite = ?, nb_unites = ?, pieces_par_unite = ?
            WHERE id = ?
        ");
        $stmtUpdateDL = $bdBASI->prepare("UPDATE demandes_ligne SET qte_commandee = ?, qte_restant = ? WHERE idDL = ?");
        $stmtHistoPAPL = $bdBASI->prepare("
            INSERT INTO historique_passer_achat_et_paiement_ligne
                (idPAPL, idPAP, idDL, prix_reel, quantite_reelle, montant_total_ligne, date_ajout,
                 id_unite, nb_unites, pieces_par_unite, id_statut_PAPL, motif, dateEnregistrement)
            SELECT id, idPAP, idDL, prix_reel, quantite_reelle, montant_total_ligne, date_ajout,
                   id_unite, nb_unites, pieces_par_unite, id_statut_PAPL, ?, ?
            FROM passer_achat_et_paiement_ligne
            WHERE id = ?
        ");
        foreach ($lignesAMettreAJour as $l) {
            $stmtUpdatePAPL->execute([$l['quantite_reelle'], $l['id_unite'], $l['nb_unites'], $l['pieces_par_unite'], $l['idPAPL']]);
            $stmtUpdateDL->execute([$l['nouveau_qte_commandee'], $l['nouveau_qte_restant'], $l['idDL']]);
            $stmtHistoPAPL->execute(["Modification de la ligne (par $sessionMatricule)", $dateEnregistrement, $l['idPAPL']]);
        }

        // 5) Lignes supprimées : id_statut_PAPL 1 → 2 + restitution demandes_ligne
        $stmtSupprimerPAPL = $bdBASI->prepare("UPDATE passer_achat_et_paiement_ligne SET id_statut_PAPL = 2 WHERE id = ?");
        foreach ($lignesASupprimerInfo as $l) {
            $stmtSupprimerPAPL->execute([$l['idPAPL']]);
            $stmtUpdateDL->execute([$l['nouveau_qte_commandee'], $l['nouveau_qte_restant'], $l['idDL']]);
            $stmtHistoPAPL->execute(["Suppression de la ligne lors de la modification (par $sessionMatricule)", $dateEnregistrement, $l['idPAPL']]);
        }

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Commande modifiée avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Modif][modifierCommande] ' . $e->getMessage());
        erreurSql('Impossible de modifier la commande.');
    }
}

/**
 * Modifie une opération "Passer au paiement" rejetée (idStatut = 5, idTypePAP = 2) :
 *   - Suppression de lignes possible (id_statut_PAPL 1 → 2), au moins une
 *     ligne active doit subsister.
 *   - Modification du montant des lignes conservées, revalidée contre
 *     demandes_ligne (en restituant d'abord la contribution actuelle de la ligne).
 *   - Nouveau justificatif de paiement obligatoire — l'existant passe
 *     statut 1 → 0, le(s) nouveau(x) est/sont inséré(s).
 *   - Tranches existantes passent statut 1 → 0 ; nouvelles tranches insérées
 *     si modalité = 3.
 *   - idStatut repasse de 5 à 2 (Validée) directement — un paiement ne
 *     passe pas par l'étape DGA (choix du fournisseur), contrairement à
 *     une commande.
 */
function modifierPaiement(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        $idPAP           = (int)($_POST['idPAP'] ?? 0);
        $idModeReglement = (int)($_POST['id_mode_reglement'] ?? 0);
        $idModePaiement  = (int)($_POST['id_mode_paiement'] ?? 0);

        if ($idPAP <= 0)           { echo json_encode(['status'=>'error','message'=>'Dossier manquant.']); return; }
        if ($idModeReglement <= 0) { echo json_encode(['status'=>'error','message'=>'Mode de règlement requis.']); return; }
        if ($idModePaiement <= 0)  { echo json_encode(['status'=>'error','message'=>'Modalité de paiement requise.']); return; }

        $stmtC = $bdBASI->prepare("SELECT id FROM passer_achat_et_paiement WHERE id = ? AND idStatut = 5 AND idTypePAP = 2 LIMIT 1");
        $stmtC->execute([$idPAP]);
        if (!$stmtC->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable ou non modifiable.']);
            return;
        }

        $lignesInput = json_decode($_POST['lignes'] ?? '[]', true);
        if (!is_array($lignesInput) || empty($lignesInput)) {
            echo json_encode(['status' => 'error', 'message' => 'Aucune ligne fournie.']);
            return;
        }

        $lignesConservees = [];
        $idsASupprimer    = [];
        foreach ($lignesInput as $l) {
            $idPAPL = (int)($l['idPAPL'] ?? 0);
            if ($idPAPL <= 0) { echo json_encode(['status'=>'error','message'=>'Ligne invalide.']); return; }

            if (($l['action'] ?? 'garder') === 'supprimer') {
                $idsASupprimer[] = $idPAPL;
            } else {
                $montant = (float)($l['montant'] ?? 0);
                if ($montant <= 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Le montant doit être supérieur à 0 pour chaque ligne conservée.']);
                    return;
                }
                $lignesConservees[] = ['idPAPL' => $idPAPL, 'montant' => $montant];
            }
        }

        if (empty($lignesConservees)) {
            echo json_encode(['status' => 'error', 'message' => 'Vous devez conserver au moins une ligne.']);
            return;
        }

        $stmtLigneInfo = $bdBASI->prepare("
            SELECT papl.id, papl.idDL, papl.montant_total_ligne AS ancien_montant,
                   dal.montant_total AS montant_demande_total,
                   CAST(dal.mnt_paye AS DECIMAL(15,2)) AS mnt_paye_actuelle,
                   COALESCE(CAST(dal.mnt_restant AS DECIMAL(15,2)), dal.montant_total) AS mnt_restant_actuelle
            FROM passer_achat_et_paiement_ligne papl
            JOIN demandes_ligne dal ON papl.idDL = dal.idDL
            WHERE papl.id = ? AND papl.idPAP = ? AND papl.id_statut_PAPL = 1
            LIMIT 1
        ");

        $lignesAMettreAJour   = [];
        $montantTotalPaiement = 0.0;
        foreach ($lignesConservees as $l) {
            $stmtLigneInfo->execute([$l['idPAPL'], $idPAP]);
            $ligneDb = $stmtLigneInfo->fetch(PDO::FETCH_ASSOC);
            $stmtLigneInfo->closeCursor();
            if (!$ligneDb) {
                echo json_encode(['status' => 'error', 'message' => "Une des lignes n'appartient pas à ce dossier (ligne #{$l['idPAPL']})."]);
                return;
            }

            $ancienMontant        = (float)$ligneDb['ancien_montant'];
            $mntPayeActuelle      = (float)$ligneDb['mnt_paye_actuelle'];
            $mntRestantActuelle   = (float)$ligneDb['mnt_restant_actuelle'];
            $montantDemandeTotal  = (float)$ligneDb['montant_demande_total'];

            $montantDisponiblePourCetteLigne = $mntRestantActuelle + $ancienMontant;
            if ($l['montant'] > $montantDisponiblePourCetteLigne) {
                echo json_encode([
                    'status'  => 'error',
                    'message' => "Montant ({$l['montant']}) supérieur au montant disponible ($montantDisponiblePourCetteLigne) pour la ligne #{$l['idPAPL']}.",
                ]);
                return;
            }

            $nouveauMntPaye    = $mntPayeActuelle - $ancienMontant + $l['montant'];
            $nouveauMntRestant = max(0, $montantDemandeTotal - $nouveauMntPaye);
            $montantTotalPaiement += $l['montant'];

            $lignesAMettreAJour[] = [
                'idPAPL'             => $l['idPAPL'],
                'idDL'               => $ligneDb['idDL'],
                'montant'            => $l['montant'],
                'nouveau_mnt_paye'   => $nouveauMntPaye,
                'nouveau_mnt_restant'=> $nouveauMntRestant,
            ];
        }

        $lignesASupprimerInfo = [];
        foreach ($idsASupprimer as $idPAPL) {
            $stmtLigneInfo->execute([$idPAPL, $idPAP]);
            $ligneDb = $stmtLigneInfo->fetch(PDO::FETCH_ASSOC);
            $stmtLigneInfo->closeCursor();
            if (!$ligneDb) {
                echo json_encode(['status' => 'error', 'message' => "Une des lignes à supprimer n'appartient pas à ce dossier (ligne #$idPAPL)."]);
                return;
            }

            $ancienMontant       = (float)$ligneDb['ancien_montant'];
            $mntPayeActuelle     = (float)$ligneDb['mnt_paye_actuelle'];
            $montantDemandeTotal = (float)$ligneDb['montant_demande_total'];

            $nouveauMntPaye    = max(0, $mntPayeActuelle - $ancienMontant);
            $nouveauMntRestant = max(0, $montantDemandeTotal - $nouveauMntPaye);

            $lignesASupprimerInfo[] = [
                'idPAPL'              => $idPAPL,
                'idDL'                => $ligneDb['idDL'],
                'nouveau_mnt_paye'    => $nouveauMntPaye,
                'nouveau_mnt_restant' => $nouveauMntRestant,
            ];
        }

        // ── Justificatif de paiement (un ou plusieurs, obligatoire) ─────────────
        if (empty($_FILES['justificatifs']) || empty($_FILES['justificatifs']['name'][0])) {
            echo json_encode(['status' => 'error', 'message' => 'Le justificatif de paiement est obligatoire.']);
            return;
        }
        $fichiersValides = [];
        $nbFichiers = count($_FILES['justificatifs']['name']);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        for ($i = 0; $i < $nbFichiers; $i++) {
            if ($_FILES['justificatifs']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $tmpName = $_FILES['justificatifs']['tmp_name'][$i];
            $mime = $finfo->file($tmpName);
            if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
                echo json_encode(['status' => 'error', 'message' => "Le fichier " . ($i + 1) . " doit être un PDF ou une image (JPEG/PNG)."]);
                return;
            }
            $fichiersValides[] = ['tmpName' => $tmpName, 'originalName' => $_FILES['justificatifs']['name'][$i]];
        }
        if (empty($fichiersValides)) {
            echo json_encode(['status' => 'error', 'message' => 'Au moins un justificatif de paiement valide est requis.']);
            return;
        }

        // ── Tranches ─────────────────────────────────────────────────────────────
        $estParTranche = ($idModePaiement === 3);
        $nbTranche     = null;
        $tranches      = [];
        if ($estParTranche) {
            $nbTranche = (int)($_POST['nb_tranche'] ?? 0);
            $tranches  = json_decode($_POST['tranches'] ?? '[]', true);
            if ($nbTranche < 2 || !is_array($tranches) || count($tranches) !== $nbTranche) {
                echo json_encode(['status' => 'error', 'message' => 'Répartition des tranches invalide.']);
                return;
            }
            $somme = 0.0;
            foreach ($tranches as $t) $somme += (float)($t['pourcentage'] ?? 0);
            foreach ($tranches as $idx => $t) {
                if ((float)($t['pourcentage'] ?? 0) <= 0) {
                    echo json_encode(['status' => 'error', 'message' => 'La tranche ' . ($idx + 1) . ' doit être strictement supérieure à 0 %.']);
                    return;
                }
            }
            if (abs($somme - 100.0) > 0.01) {
                echo json_encode(['status' => 'error', 'message' => 'La somme des tranches doit être exactement égale à 100 % (actuellement ' . number_format($somme, 2) . ' %).']);
                return;
            }
        }

        if (!is_dir(UPLOAD_DIR_JUSTIFICATIFS)) mkdir(UPLOAD_DIR_JUSTIFICATIFS, 0755, true);

        // ── Transaction ──────────────────────────────────────────────────────────
        $bdBASI->beginTransaction();

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        // 1) Dossier : idStatut 5 → 2 (le paiement ne passe pas par la DGA,
        // il repart directement en "Validée"), montant_total recalculé, modes mis à jour
        $bdBASI->prepare("
            UPDATE passer_achat_et_paiement
            SET idStatut = 2, montant_total = ?, id_mode_reglement = ?, id_mode_paiement = ?, nb_tranche = ?
            WHERE id = ?
        ")->execute([$montantTotalPaiement, $idModeReglement, $idModePaiement, $estParTranche ? $nbTranche : null, $idPAP]);

        $bdBASI->prepare("
            INSERT INTO historique_passer_achat_et_paiement
                (idPAP, nom_commande, montant_total, idStatut, idTypePAP, dateCreation, id_mode_reglement,
                 id_mode_paiement, nb_tranche, idUtilisateur, motif, dateEnregistrement)
            SELECT id, nom_commande, montant_total, idStatut, idTypePAP, dateCreation, id_mode_reglement,
                   id_mode_paiement, nb_tranche, ?, ?, ?
            FROM passer_achat_et_paiement
            WHERE id = ?
        ")->execute([
            $sessionUserId, "Modification du paiement rejeté (par $sessionMatricule)",
            $dateEnregistrement, $idPAP,
        ]);

        // 2) Tranches : anciennes désactivées, nouvelles insérées
        $bdBASI->prepare("UPDATE tranches SET statut = 0 WHERE idPAP = ?")->execute([$idPAP]);
        if ($estParTranche) {
            $stmtInsertTranche = $bdBASI->prepare("
                INSERT INTO tranches (idPAP, ordre, pourcentage, statut, created_at, updated_at)
                VALUES (?, ?, ?, 1, ?, NULL)
            ");
            $stmtInsertTrancheHisto = $bdBASI->prepare("
                INSERT INTO tranches_histo (id_tranche, idPAP, ordre, pourcentage, created_at, updated_at, action, dateEnregistrement)
                VALUES (?, ?, ?, ?, ?, NULL, 'Modification', ?)
            ");
            foreach ($tranches as $t) {
                $ordre       = (int)($t['ordre'] ?? 0);
                $pourcentage = (float)($t['pourcentage'] ?? 0);
                $stmtInsertTranche->execute([$idPAP, $ordre, $pourcentage, $dateEnregistrement]);
                $idTranche = (int)$bdBASI->lastInsertId();
                $stmtInsertTrancheHisto->execute([$idTranche, $idPAP, $ordre, $pourcentage, $dateEnregistrement, $dateEnregistrement]);
            }
        }

        // 3) Justificatif : ancien désactivé, nouveau(x) inséré(s)
        $bdBASI->prepare("UPDATE document_justificatif_paiement SET statut = 0 WHERE idPAP = ? AND statut = 1")->execute([$idPAP]);
        $stmtInsertDoc = $bdBASI->prepare("
            INSERT INTO document_justificatif_paiement (idPAP, doc, statut, dateEnregistrement)
            VALUES (?, ?, 1, ?)
        ");
        foreach ($fichiersValides as $f) {
            $ext = strtolower(pathinfo($f['originalName'], PATHINFO_EXTENSION)) ?: 'pdf';
            $nomFichier   = 'justificatif_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $cheminAbsolu = UPLOAD_DIR_JUSTIFICATIFS . '/' . $nomFichier;
            if (!move_uploaded_file($f['tmpName'], $cheminAbsolu)) {
                throw new \RuntimeException("Échec de l'enregistrement d'un justificatif.");
            }
            $cheminPublic = UPLOAD_URL_JUSTIFICATIFS . '/' . $nomFichier;
            $stmtInsertDoc->execute([$idPAP, $cheminPublic, $dateEnregistrement]);
        }

        // 4) Lignes conservées
        $stmtUpdatePAPL = $bdBASI->prepare("UPDATE passer_achat_et_paiement_ligne SET montant_total_ligne = ? WHERE id = ?");
        $stmtUpdateDL   = $bdBASI->prepare("UPDATE demandes_ligne SET mnt_paye = ?, mnt_restant = ? WHERE idDL = ?");
        $stmtHistoPAPL  = $bdBASI->prepare("
            INSERT INTO historique_passer_achat_et_paiement_ligne
                (idPAPL, idPAP, idDL, prix_reel, quantite_reelle, montant_total_ligne, date_ajout,
                 id_unite, nb_unites, pieces_par_unite, id_statut_PAPL, motif, dateEnregistrement)
            SELECT id, idPAP, idDL, prix_reel, quantite_reelle, montant_total_ligne, date_ajout,
                   id_unite, nb_unites, pieces_par_unite, id_statut_PAPL, ?, ?
            FROM passer_achat_et_paiement_ligne
            WHERE id = ?
        ");
        foreach ($lignesAMettreAJour as $l) {
            $stmtUpdatePAPL->execute([$l['montant'], $l['idPAPL']]);
            $stmtUpdateDL->execute([$l['nouveau_mnt_paye'], $l['nouveau_mnt_restant'], $l['idDL']]);
            $stmtHistoPAPL->execute(["Modification de la ligne (par $sessionMatricule)", $dateEnregistrement, $l['idPAPL']]);
        }

        // 5) Lignes supprimées
        $stmtSupprimerPAPL = $bdBASI->prepare("UPDATE passer_achat_et_paiement_ligne SET id_statut_PAPL = 2 WHERE id = ?");
        foreach ($lignesASupprimerInfo as $l) {
            $stmtSupprimerPAPL->execute([$l['idPAPL']]);
            $stmtUpdateDL->execute([$l['nouveau_mnt_paye'], $l['nouveau_mnt_restant'], $l['idDL']]);
            $stmtHistoPAPL->execute(["Suppression de la ligne lors de la modification (par $sessionMatricule)", $dateEnregistrement, $l['idPAPL']]);
        }

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Paiement modifié avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Modif][modifierPaiement] ' . $e->getMessage());
        erreurSql('Impossible de modifier le paiement.');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Liste des demandes (tableau de bord global des opérations)

   Remplace la page "modification" dédiée aux seuls dossiers rejetés : cette
   page liste TOUTES les opérations (passer_achat_et_paiement), tous statuts
   confondus, avec un tableau de bord de comptage par statut et un filtrage
   au clic. Cycle de vie complet :
     1 = En attente | 2 = Validée | 3 = Avis favorable | 4 = Acceptée
     5 = Rejetée | 6 = En paiement / Livrée | 7 = Terminée
   (aucune action de transition n'est demandée pour 6/7 dans cette page —
   seuls l'affichage et le filtrage sont couverts ici.)
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Liste toutes les opérations, avec :
 *   - stats : comptage par statut (1 à 8) + 'tous', INDÉPENDANT du filtre
 *     appliqué à la liste (toujours calculé sur l'ensemble des dossiers).
 *   - data  : la liste elle-même, filtrée sur idStatut si un filtre != 0
 *     (ou absent) est fourni.
 *
 * Paramètre : statut (0 ou absent = toutes les demandes)
 */
function listerToutesOperations(PDO $bdBASI, drhController $basiController): void {
    try {
        $statutFiltre = (int) inputValue('statut', 0);

        // ── Statistiques (jamais affectées par le filtre) ───────────────────────
        $stmtStats = $bdBASI->query("SELECT idStatut, COUNT(*) AS n FROM passer_achat_et_paiement GROUP BY idStatut");
        if (!$stmtStats) throw new \RuntimeException('Requête des statistiques échouée.');

        $stats = ['tous' => 0, '1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, '6' => 0, '7' => 0];
        while ($row = $stmtStats->fetch(PDO::FETCH_ASSOC)) {
            $cle = (string)(int)$row['idStatut'];
            if (isset($stats[$cle])) $stats[$cle] = (int)$row['n'];
            $stats['tous'] += (int)$row['n'];
        }

        // ── Liste (filtrée si statut != 0) ──────────────────────────────────────
        // bc_uploade : vrai si un bon de commande (documents_pap.facture_definitive)
        // a été téléversé pour le fournisseur retenu (statut = 1, choix = 1) —
        // conditionne l'affichage/l'activation du bouton "Passer en caisse".
        $sql = "
            SELECT
                p.id AS idPAP,
                p.nom_commande,
                p.montant_total,
                p.dateCreation,
                p.idTypePAP,
                p.idStatut,
                p.motifRejet,
                mr.mode_reglement AS mode_reglement_nom,
                mp.mode_paiement  AS mode_paiement_nom,
                CONCAT(u.prenom, ' ', u.nom) AS demandeur,
                EXISTS (
                    SELECT 1 FROM documents_pap dp
                    WHERE dp.idPAP = p.id AND dp.statut = 1 AND dp.choix = 1
                      AND dp.facture_definitive IS NOT NULL AND dp.facture_definitive != ''
                ) AS bc_uploade
            FROM passer_achat_et_paiement p
            LEFT JOIN mode_reglement mr ON p.id_mode_reglement = mr.id
            LEFT JOIN mode_paiement  mp ON p.id_mode_paiement  = mp.id
            LEFT JOIN utilisateurs   u  ON p.idUtilisateur     = u.id
        ";
        $params = [];
        if ($statutFiltre > 0) {
            $sql .= " WHERE p.idStatut = ?";
            $params[] = $statutFiltre;
        }
        $sql .= " ORDER BY p.dateCreation DESC";

        $stmt = $bdBASI->prepare($sql);
        if (!$stmt) throw new \RuntimeException('Requête de liste des opérations échouée.');
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['tmp']        = $basiController->tokenencrypt($r['idPAP']);
            $r['bc_uploade'] = (bool)((int)$r['bc_uploade']);
        }
        unset($r);

        echo json_encode(['status' => 'success', 'stats' => $stats, 'data' => $rows]);
    } catch (\Throwable $e) {
        error_log('[Operations][listerToutesOperations] ' . $e->getMessage());
        erreurSql('Impossible de charger la liste des demandes.');
    }
}

/**
 * Détail complet d'une opération (action "Détail"), quel que soit son statut
 * (contrairement à detailDossierAModifier(), réservé aux dossiers rejetés).
 *   - idTypePAP = 1 (achat) : lignes (prix_reel) + facture définitive si
 *     déjà retenue (documents_pap.choix = 1), sinon pro forma actifs.
 *   - idTypePAP = 2 (paiement) : lignes (montant) + justificatif(s) actif(s).
 *
 * Paramètre : token (chiffré de idPAP)
 */
function detailOperation(PDO $bdBASI, drhController $basiController): void {
    try {
        $token = trim((string) inputValue('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmtC = $bdBASI->prepare("
            SELECT p.id AS idPAP, p.nom_commande, p.montant_total, p.dateCreation, p.idStatut, p.idTypePAP,
                   p.id_mode_reglement, p.id_mode_paiement, p.motifRejet,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM passer_achat_et_paiement p
            LEFT JOIN utilisateurs u ON p.idUtilisateur = u.id
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmtC->execute([$idPAP]);
        $dossier = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$dossier) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable.']);
            return;
        }

        $estAchat = ((int)$dossier['idTypePAP'] === 1);
        $reponse  = ['status' => 'success', 'dossier' => $dossier];

        // Lignes (actives uniquement, id_statut_PAPL = 1)
        $stmtLignes = $bdBASI->prepare("
            SELECT
                papl.id AS idPAPL, lb.designation, papl.quantite_reelle, papl.prix_reel, papl.montant_total_ligne
            FROM passer_achat_et_paiement_ligne papl
            JOIN demandes_ligne dal ON papl.idDL = dal.idDL
            JOIN ligneBudget    lb  ON dal.idLB  = lb.id
            WHERE papl.idPAP = ? AND papl.id_statut_PAPL = 1
            ORDER BY papl.id ASC
        ");
        $stmtLignes->execute([$idPAP]);
        $reponse['lignes'] = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

        if ($estAchat) {
            // Fournisseur retenu si déjà validé par la DGA (choix = 1), sinon
            // liste des pro forma actifs proposés. La facture définitive n'est
            // plus téléversée par la DGA — c'est le pro forma déjà associé
            // (dp.doc) qui sert de pièce justificative, exposé ici sous la
            // même clé 'facture_definitive' pour ne pas casser le front-end.
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

            if (!$reponse['facture_definitive']) {
                $stmtProforma = $bdBASI->prepare("
                    SELECT dp.id, dp.doc, dp.id_fournisseur, f.nomF, f.prenomF, f.entreprise
                    FROM documents_pap dp
                    JOIN fournisseur f ON dp.id_fournisseur = f.idF
                    WHERE dp.idPAP = ? AND dp.statut = 1
                    ORDER BY dp.id ASC
                ");
                $stmtProforma->execute([$idPAP]);
                $reponse['documents'] = $stmtProforma->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $reponse['documents'] = [];
            }
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

        echo json_encode($reponse);
    } catch (\Throwable $e) {
        error_log('[Operations][detailOperation] ' . $e->getMessage());
        erreurSql('Impossible de charger le détail de la demande.');
    }
}

/**
 * Historique / suivi d'une opération (action "Suivi") : renvoie chaque
 * instantané enregistré dans historique_passer_achat_et_paiement, dans
 * l'ordre chronologique — permet de reconstituer les différentes étapes de
 * traitement (création, modification, validation, rejet, etc.).
 *
 * Paramètre : token (chiffré de idPAP)
 */
function suiviOperation(PDO $bdBASI, drhController $basiController): void {
    try {
        $token = trim((string) inputValue('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmt = $bdBASI->prepare("
            SELECT h.idStatut, h.montant_total, h.motif, h.motifRejet, h.dateEnregistrement,
                   CONCAT(u.prenom, ' ', u.nom) AS utilisateur
            FROM historique_passer_achat_et_paiement h
            LEFT JOIN utilisateurs u ON h.idUtilisateur = u.id
            WHERE h.idPAP = ?
            ORDER BY h.dateEnregistrement ASC, h.id ASC
        ");
        $stmt->execute([$idPAP]);
        $historique = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'historique' => $historique]);
    } catch (\Throwable $e) {
        error_log('[Operations][suiviOperation] ' . $e->getMessage());
        erreurSql("Impossible de charger le suivi de la demande.");
    }
}

/**
 * Dossier complet d'une opération TERMINÉE (idStatut = 7) : commande,
 * paiement(s), livraison(s) (achat uniquement) et tous les documents liés à
 * chaque étape. Sert à la fois pour le bouton "Dossier" (vue complète) et le
 * bouton "Pièces" (le front-end n'affiche alors que la section `documents`
 * de cette même réponse).
 *
 * Paramètre : token (chiffré de idPAP)
 */
function detailDossierComplet(PDO $bdBASI, drhController $basiController): void {
    try {
        $token = trim((string) inputValue('token', ''));
        if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Token manquant.']); return; }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) { echo json_encode(['status' => 'error', 'message' => 'Token invalide.']); return; }

        $stmtC = $bdBASI->prepare("
            SELECT p.id, p.id AS numero, p.nom_commande, p.montant_total, p.montant_paye, p.dateCreation,
                   p.idStatut, p.idTypePAP, p.livraison,
                   mr.mode_reglement AS mode_reglement_nom, mp.mode_paiement AS mode_paiement_nom,
                   CONCAT(u.prenom, ' ', u.nom) AS demandeur
            FROM passer_achat_et_paiement p
            LEFT JOIN mode_reglement mr ON p.id_mode_reglement = mr.id
            LEFT JOIN mode_paiement  mp ON p.id_mode_paiement  = mp.id
            LEFT JOIN utilisateurs   u  ON p.idUtilisateur     = u.id
            WHERE p.id = ? AND p.idStatut = 7
            LIMIT 1
        ");
        $stmtC->execute([$idPAP]);
        $commande = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$commande) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable ou non terminé.']);
            return;
        }

        $estAchat = ((int) $commande['idTypePAP'] === 1);
        $reponse  = ['status' => 'success', 'commande' => $commande, 'estAchat' => $estAchat];

        // ── Lignes ────────────────────────────────────────────────────────────
        if ($estAchat) {
            $stmtLignes = $bdBASI->prepare("
                SELECT papl.id AS idPAPL, lb.designation, papl.quantite_reelle, papl.quantite_livree,
                       papl.prix_reel, papl.montant_total_ligne
                FROM passer_achat_et_paiement_ligne papl
                JOIN demandes_ligne dal ON papl.idDL = dal.idDL
                JOIN ligneBudget    lb  ON dal.idLB  = lb.id
                WHERE papl.idPAP = ? AND papl.id_statut_PAPL = 1
                ORDER BY papl.id ASC
            ");
        } else {
            $stmtLignes = $bdBASI->prepare("
                SELECT papl.id AS idPAPL, lb.designation, papl.montant_total_ligne
                FROM passer_achat_et_paiement_ligne papl
                JOIN demandes_ligne dal ON papl.idDL = dal.idDL
                JOIN ligneBudget    lb  ON dal.idLB  = lb.id
                WHERE papl.idPAP = ? AND papl.id_statut_PAPL = 1
                ORDER BY papl.id ASC
            ");
        }
        $stmtLignes->execute([$idPAP]);
        $reponse['lignes'] = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

        // ── Paiements (avec preuve de paiement, si ajoutée) ──────────────────────
        $stmtPaiements = $bdBASI->prepare("
            SELECT pp.id, pp.montant, pp.mode_reglement, pp.banque, pp.numero_cheque_virement,
                   pp.recu, pp.date_paiement, CONCAT(uc.prenom, ' ', uc.nom) AS caissier,
                   mr.mode_reglement AS mode_reglement_nom
            FROM paiement_pap pp
            JOIN utilisateurs uc ON pp.idCaissier = uc.id
            LEFT JOIN mode_reglement mr ON pp.mode_reglement = mr.id
            WHERE pp.idPAP = ?
            ORDER BY pp.date_paiement ASC
        ");
        $stmtPaiements->execute([$idPAP]);
        $reponse['paiements'] = $stmtPaiements->fetchAll(PDO::FETCH_ASSOC);

        // ── Documents ─────────────────────────────────────────────────────────
        $documents = [];

        if ($estAchat) {
            // Facture choisie + facture définitive (BC uploadé par le DRH) :
            // toutes deux portées par la même ligne documents_pap (choix = 1).
            $stmtFacture = $bdBASI->prepare("
                SELECT dp.doc AS facture_choisie, dp.facture_definitive,
                       f.nomF, f.prenomF, f.entreprise
                FROM documents_pap dp
                JOIN fournisseur f ON dp.id_fournisseur = f.idF
                WHERE dp.idPAP = ? AND dp.choix = 1
                LIMIT 1
            ");
            $stmtFacture->execute([$idPAP]);
            $facture = $stmtFacture->fetch(PDO::FETCH_ASSOC) ?: null;

            $documents['facture_choisie']     = $facture['facture_choisie']     ?? null;
            $documents['facture_definitive']  = $facture['facture_definitive']  ?? null;
            $documents['fournisseur']         = $facture ? trim(($facture['prenomF'] ?? '') . ' ' . ($facture['nomF'] ?? '')) . (!empty($facture['entreprise']) ? ' — ' . $facture['entreprise'] : '') : null;

            // Bon de commande généré par le système (PDF autonome existant).
            $documents['bon_commande_url'] = '/personnel/bon_pap_pdf?token=' . urlencode($token);

            // Bon(s) de livraison — potentiellement plusieurs (livraisons partielles).
            $stmtLivraisons = $bdBASI->prepare("
                SELECT id, nom_livraison, numero_livraison, fichier_bon_livraison, date_livraison
                FROM livraison
                WHERE idPAP = ?
                ORDER BY date_livraison ASC, id ASC
            ");
            $stmtLivraisons->execute([$idPAP]);
            $documents['livraisons'] = $stmtLivraisons->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmtJustif = $bdBASI->prepare("
                SELECT id, doc, dateEnregistrement
                FROM document_justificatif_paiement
                WHERE idPAP = ? AND statut = 1
                ORDER BY id ASC
            ");
            $stmtJustif->execute([$idPAP]);
            $documents['justificatifs'] = $stmtJustif->fetchAll(PDO::FETCH_ASSOC);
        }

        $reponse['documents'] = $documents;

        echo json_encode($reponse);
    } catch (\Throwable $e) {
        error_log('[Operations][detailDossierComplet] ' . $e->getMessage());
        erreurSql('Impossible de charger le dossier complet.');
    }
}

/**
 * Envoie le dossier à la caisse : idStatut 4 (Acceptée) → 6 (En paiement).
 * Pour une commande d'achat (idTypePAP = 1), exige qu'un bon de commande ait
 * été téléversé au préalable (documents_pap.facture_definitive renseigné
 * pour la ligne du fournisseur retenu, statut = 1 et choix = 1). Cette
 * contrainte ne s'applique pas à un paiement (idTypePAP = 2), qui n'a pas
 * de notion de bon de commande.
 *
 * Paramètre : token (chiffré de idPAP)
 */
function envoyerCaisse(PDO $bdBASI, drhController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string) inputValue('token', ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmtC = $bdBASI->prepare("SELECT id, idTypePAP FROM passer_achat_et_paiement WHERE id = ? AND idStatut = 4 LIMIT 1");
        $stmtC->execute([$idPAP]);
        $dossier = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$dossier) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable ou non éligible (doit être Accepté).']);
            return;
        }

        if ((int)$dossier['idTypePAP'] === 1) {
            $stmtBC = $bdBASI->prepare("
                SELECT id FROM documents_pap
                WHERE idPAP = ? AND statut = 1 AND choix = 1
                  AND facture_definitive IS NOT NULL AND facture_definitive != ''
                LIMIT 1
            ");
            $stmtBC->execute([$idPAP]);
            if (!$stmtBC->fetch()) {
                echo json_encode(['status' => 'error', 'message' => "Le bon de commande (BC) doit être téléversé avant l'envoi à la caisse."]);
                return;
            }
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        $bdBASI->prepare("UPDATE passer_achat_et_paiement SET idStatut = 6 WHERE id = ?")->execute([$idPAP]);

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
            "Envoyé à la caisse (par $sessionMatricule)",
            $dateEnregistrement, $idPAP,
        ]);

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Dossier envoyé à la caisse avec succès.']);
    } catch (\Throwable $e) {

        echo $e;
        die;
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[Operations][envoyerCaisse] ' . $e->getMessage());
        erreurSql("Impossible d'envoyer le dossier à la caisse.");
    }
}

/**
 * Téléverse le bon de commande (BC) d'une commande d'achat Acceptée
 * (idStatut = 4, idTypePAP = 1). Le fichier est enregistré dans la colonne
 * `facture_definitive` de la ligne documents_pap du fournisseur retenu
 * (statut = 1, choix = 1) — condition requise pour pouvoir ensuite
 * "Passer en caisse" (cf. envoyerCaisse()).
 *
 * Requête multipart/form-data (upload de fichier).
 * Champs attendus : token (chiffré de idPAP), bc (fichier PDF)
 */
function uploaderBC(PDO $bdBASI, drhController $basiController, int $sessionUserId, string $sessionMatricule): void {
    try {
        $token = trim((string)($_POST['token'] ?? ''));
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token manquant.']);
            return;
        }
        $idPAP = (int) $basiController->tokendecrypt($token);
        if ($idPAP <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Token invalide.']);
            return;
        }

        $stmtC = $bdBASI->prepare("SELECT id FROM passer_achat_et_paiement WHERE id = ? AND idStatut = 4 AND idTypePAP = 1 LIMIT 1");
        $stmtC->execute([$idPAP]);
        if (!$stmtC->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Dossier introuvable ou non éligible (doit être une commande Acceptée).']);
            return;
        }

        $stmtDoc = $bdBASI->prepare("SELECT id FROM documents_pap WHERE idPAP = ? AND statut = 1 AND choix = 1 LIMIT 1");
        $stmtDoc->execute([$idPAP]);
        $document = $stmtDoc->fetch(PDO::FETCH_ASSOC);
        if (!$document) {
            echo json_encode(['status' => 'error', 'message' => "Aucun fournisseur retenu n'a été trouvé pour cette commande."]);
            return;
        }

        if (empty($_FILES['bc']) || $_FILES['bc']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'Le bon de commande (PDF) est obligatoire.']);
            return;
        }
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['bc']['tmp_name']);
        if ($mimeType !== 'application/pdf') {
            echo json_encode(['status' => 'error', 'message' => 'Le bon de commande doit être un fichier PDF.']);
            return;
        }

        if (!is_dir(UPLOAD_DIR_COMMANDES)) {
            mkdir(UPLOAD_DIR_COMMANDES, 0755, true);
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $nomFichier   = 'bon_commande_' . $idPAP . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $cheminAbsolu = UPLOAD_DIR_COMMANDES . '/' . $nomFichier;
        if (!move_uploaded_file($_FILES['bc']['tmp_name'], $cheminAbsolu)) {
            throw new \RuntimeException("Échec de l'enregistrement du bon de commande.");
        }
        $cheminPublic = UPLOAD_URL_COMMANDES . '/' . $nomFichier;

        $bdBASI->prepare("
            UPDATE documents_pap SET facture_definitive = ? WHERE id = ?
        ")->execute([$cheminPublic, $document['id']]);

        echo json_encode(['status' => 'success', 'message' => 'Bon de commande téléversé avec succès.']);
    } catch (\Throwable $e) {

        echo $e;
        die;
        error_log('[Operations][uploaderBC] ' . $e->getMessage());
        erreurSql('Impossible de téléverser le bon de commande.');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODULE — Demande de facture pro forma

   Hypothèses de schéma (à ajuster si elles ne correspondent pas à la base) :
   - Table `demande_proforma` : idFournisseur, idDL, idUtilisateur, statut
     (1 = active, 0 = ancienne/désactivée), dateEnregistrement, reference
     (une référence par fournisseur, partagée par toutes ses lignes du même
     lot de demande).
   - La génération des PDF (et leur archivage en ZIP) se fait désormais dans
     un script autonome dédié (dfc-demande-facture-proforma.php), sans passer
     par ce contrôleur — voir ce fichier pour la logique FPDF/ZipArchive. Ce
     script lit désormais `reference` directement en base (il ne la génère
     plus lui-même), pour rester cohérent entre l'enregistrement et le PDF.
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Génère une référence lisible pour un lot de demande de facture pro forma
 * (ex. PF-2026-0007-A3F9), stockée en base et réutilisée telle quelle lors
 * de la génération du PDF correspondant.
 */
function genererReference(int $idFournisseur): string {
    return 'PF-' . date('Y') . '-' . str_pad((string)$idFournisseur, 4, '0', STR_PAD_LEFT)
        . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
}

/**
 * Enregistre une demande de facture pro forma pour les lignes sélectionnées,
 * envoyée à EXACTEMENT 3 fournisseurs. Désactive d'abord (statut → 0) les
 * demandes actives déjà existantes pour ces mêmes lignes, avant d'insérer
 * les nouvelles (statut = 1). Transactionnel.
 *
 * Paramètres (JSON ou POST) :
 *   - idDL_liste  : tableau des demandes_ligne.idDL sélectionnées
 *   - fournisseurs: tableau d'EXACTEMENT 3 identifiants fournisseur (idF), distincts
 */
function demanderFactureProforma(PDO $bdBASI, int $sessionUserId): void {
    try {
        $idDLs        = inputValue('idDL_liste', []);
        $fournisseurs = inputValue('fournisseurs', []);

        if (is_string($idDLs))        $idDLs        = json_decode($idDLs, true) ?: [];
        if (is_string($fournisseurs)) $fournisseurs = json_decode($fournisseurs, true) ?: [];

        if (!is_array($idDLs) || empty($idDLs)) {
            echo json_encode(['status' => 'error', 'message' => 'Aucune ligne sélectionnée.']);
            return;
        }

        $idDLs        = array_values(array_unique(array_map('intval', $idDLs)));
        $fournisseurs = array_values(array_unique(array_map('intval', $fournisseurs)));

        foreach ($idDLs as $id) {
            if ($id <= 0) { echo json_encode(['status' => 'error', 'message' => 'Ligne invalide.']); return; }
        }

        if (count($fournisseurs) !== 3) {
            echo json_encode(['status' => 'error', 'message' => 'Vous devez sélectionner exactement 3 fournisseurs distincts.']);
            return;
        }
        foreach ($fournisseurs as $idF) {
            if ($idF <= 0) { echo json_encode(['status' => 'error', 'message' => 'Fournisseur invalide.']); return; }
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $bdBASI->beginTransaction();

        // Avant d'insérer les nouvelles demandes : désactiver (statut → 0) les
        // anciennes demandes actives pour ces mêmes lignes.
        $stmtDesactiver = $bdBASI->prepare("
            UPDATE demande_proforma SET statut = 0 WHERE idDL = ? AND statut = 1
        ");
        foreach ($idDLs as $idDL) {
            $stmtDesactiver->execute([$idDL]);
        }

        // Insertion des nouvelles demandes (une ligne par couple ligne × fournisseur)
        // — une référence unique par fournisseur, partagée par toutes ses lignes.
        $stmtInsert = $bdBASI->prepare("
            INSERT INTO demande_proforma (idFournisseur, idDL, idUtilisateur, statut, dateEnregistrement, reference)
            VALUES (?, ?, ?, 1, ?, ?)
        ");
        foreach ($fournisseurs as $idF) {
            $reference = genererReference($idF);
            foreach ($idDLs as $idDL) {
                $stmtInsert->execute([$idF, $idDL, $sessionUserId, $dateEnregistrement, $reference]);
            }
        }

        $bdBASI->commit();

        echo json_encode(['status' => 'success', 'message' => 'Demande de facture pro forma enregistrée avec succès.']);
    } catch (\Throwable $e) {
        if ($bdBASI->inTransaction()) $bdBASI->rollBack();
        error_log('[ProForma][demanderFactureProforma] ' . $e->getMessage());
        erreurSql("Impossible d'enregistrer la demande de facture pro forma.");
    }
}

function listerFournisseurs(PDO $bdBASI): void {
    try {
        // Les fournisseurs au statut 'supprimer' ne sont jamais renvoyés :


        // ils n'apparaissent plus nulle part sur la plateforme.
        $stmt = $bdBASI->query("
            SELECT idF, nomF, prenomF, adresseF, telF, emailF, entreprise, ville,
                   statut, date_creation, date_derniere_modification
            FROM fournisseur
            WHERE statut != 'supprimer'
            ORDER BY date_creation DESC
        ");
        if (!$stmt) {
            throw new \RuntimeException('Requête de liste des fournisseurs échouée.');
        }

        $rows  = [];
        $stats = ['tous' => 0, 'actif' => 0, 'inactif' => 0];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
            $stats['tous']++;
            if ($row['statut'] === 'actif')   $stats['actif']++;
            if ($row['statut'] === 'inactif') $stats['inactif']++;
        }

        echo json_encode(['status' => 'success', 'data' => $rows, 'stats' => $stats]);
    } catch (\Throwable $e) {
        error_log('[Fournisseur][listerFournisseurs] ' . $e->getMessage());
        erreurSql('Impossible de charger la liste des fournisseurs.');
    }
}

function ajouterFournisseur(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        $resultat = validerEtVerifierFournisseur($bdBASI, $_POST);
        if (!empty($resultat['errors'])) {
            echo json_encode(['status' => 'error', 'errors' => $resultat['errors']]);
            return;
        }
        $d = $resultat['clean'];

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $stmt = $bdBASI->prepare("
            INSERT INTO fournisseur
                (nomF, prenomF, adresseF, telF, emailF, entreprise, ville, statut,
                 date_creation, date_derniere_modification, idUtilisateur)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'actif', ?, ?, ?)
        ");
        if (!$stmt) {
            throw new \RuntimeException("Préparation de la requête d'insertion impossible.");
        }

        try {
            $ok = $stmt->execute([
                $d['nomF'], $d['prenomF'], $d['adresseF'], $d['telF'], $d['emailF'], $d['entreprise'], $d['ville'],
                $dateEnregistrement, $dateEnregistrement, $sessionUserId,
            ]);
        } catch (\PDOException $ePdo) {
            if ($ePdo->getCode() === '23000') {
                error_log('[Fournisseur][ajouterFournisseur][doublon-db] ' . $ePdo->getMessage());
                $info = messageDoublonDepuisException($ePdo);
                if ($info['field']) {
                    echo json_encode(['status' => 'error', 'errors' => [$info['field'] => $info['message']]]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => $info['message']]);
                }
                return;
            }
            throw $ePdo;
        }
        if (!$ok) {
            $info = $stmt->errorInfo();
            throw new \RuntimeException($info[2] ?? 'Échec de la création du fournisseur.');
        }
        $idF = (int)$bdBASI->lastInsertId();

        try {
            $nouveau = recupererFournisseur($bdBASI, $idF);
            if ($nouveau) {
                enregistrerHistorique($bdBASI, $nouveau, "Création du fournisseur (par $sessionMatricule)", $sessionUserId);
            }
        } catch (\Throwable $eHisto) {
            // Le fournisseur est bien créé : on journalise l'échec d'historisation
            // sans faire échouer la réponse principale.
            error_log('[Fournisseur][ajouterFournisseur][historique] ' . $eHisto->getMessage());
        }

        echo json_encode(['status' => 'success', 'message' => 'Fournisseur créé avec succès.', 'idF' => $idF]);
    } catch (\Throwable $e) {
        error_log('[Fournisseur][ajouterFournisseur] ' . $e->getMessage());
        erreurSql('Impossible de créer le fournisseur.');
    }
}

function modifierFournisseur(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        $idF = (int)($_POST['idF'] ?? 0);
        if ($idF <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Identifiant fournisseur manquant.']);
            return;
        }

        $existant = recupererFournisseur($bdBASI, $idF);
        if (!$existant || $existant['statut'] === 'supprimer') {
            echo json_encode(['status' => 'error', 'message' => 'Fournisseur introuvable.']);
            return;
        }

        $resultat = validerEtVerifierFournisseur($bdBASI, $_POST, $idF);
        if (!empty($resultat['errors'])) {
            echo json_encode(['status' => 'error', 'errors' => $resultat['errors']]);
            return;
        }
        $d = $resultat['clean'];

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $stmt = $bdBASI->prepare("
            UPDATE fournisseur SET
                nomF = ?, prenomF = ?, adresseF = ?, telF = ?, emailF = ?,
                entreprise = ?, ville = ?, date_derniere_modification = ?, idUtilisateur = ?
            WHERE idF = ?
        ");
        if (!$stmt) {
            throw new \RuntimeException('Préparation de la requête de modification impossible.');
        }

        try {
            $ok = $stmt->execute([
                $d['nomF'], $d['prenomF'], $d['adresseF'], $d['telF'], $d['emailF'], $d['entreprise'], $d['ville'],
                $dateEnregistrement, $sessionUserId, $idF,
            ]);
        } catch (\PDOException $ePdo) {
            if ($ePdo->getCode() === '23000') {
                error_log('[Fournisseur][modifierFournisseur][doublon-db] ' . $ePdo->getMessage());
                $info = messageDoublonDepuisException($ePdo);
                if ($info['field']) {
                    echo json_encode(['status' => 'error', 'errors' => [$info['field'] => $info['message']]]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => $info['message']]);
                }
                return;
            }
            throw $ePdo;
        }
        if (!$ok) {
            $info = $stmt->errorInfo();
            throw new \RuntimeException($info[2] ?? 'Échec de la modification du fournisseur.');
        }

        try {
            $modifie = recupererFournisseur($bdBASI, $idF);
            if ($modifie) {
                enregistrerHistorique($bdBASI, $modifie, "Modification des informations du fournisseur (par $sessionMatricule)", $sessionUserId);
            }
        } catch (\Throwable $eHisto) {
            error_log('[Fournisseur][modifierFournisseur][historique] ' . $eHisto->getMessage());
        }

        echo json_encode(['status' => 'success', 'message' => 'Fournisseur modifié avec succès.']);
    } catch (\Throwable $e) {
        error_log('[Fournisseur][modifierFournisseur] ' . $e->getMessage());
        erreurSql('Impossible de modifier le fournisseur.');
    }
}

function supprimerFournisseur(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        $idF = (int)($_POST['idF'] ?? 0);
        if ($idF <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Identifiant fournisseur manquant.']);
            return;
        }

        $existant = recupererFournisseur($bdBASI, $idF);
        if (!$existant || $existant['statut'] === 'supprimer') {
            echo json_encode(['status' => 'error', 'message' => 'Fournisseur introuvable.']);
            return;
        }

        // Suppression douce : on ne retire jamais la ligne de la base,
        // on bascule simplement son statut sur 'supprimer'. Le fournisseur
        // n'apparaîtra alors plus nulle part sur la plateforme.
        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $stmt = $bdBASI->prepare("UPDATE fournisseur SET statut = 'supprimer', date_derniere_modification = ?, idUtilisateur = ? WHERE idF = ?");
        if (!$stmt) {
            throw new \RuntimeException('Préparation de la requête de suppression impossible.');
        }
        $ok = $stmt->execute([$dateEnregistrement, $sessionUserId, $idF]);
        if (!$ok) {
            $info = $stmt->errorInfo();
            throw new \RuntimeException($info[2] ?? 'Échec de la suppression du fournisseur.');
        }

        try {
            $supprime = recupererFournisseur($bdBASI, $idF);
            if ($supprime) {
                enregistrerHistorique($bdBASI, $supprime, "Suppression du fournisseur (par $sessionMatricule)", $sessionUserId);
            }
        } catch (\Throwable $eHisto) {
            error_log('[Fournisseur][supprimerFournisseur][historique] ' . $eHisto->getMessage());
        }

        echo json_encode(['status' => 'success', 'message' => 'Fournisseur supprimé avec succès.']);
    } catch (\Throwable $e) {
        error_log('[Fournisseur][supprimerFournisseur] ' . $e->getMessage());
        erreurSql('Impossible de supprimer le fournisseur.');
    }
}

/**
 * Restaure un fournisseur précédemment supprimé (statut 'supprimer' → 'actif').
 * Non exposé dans l'interface actuelle (les fournisseurs supprimés ne sont
 * plus listés nulle part), mais conservé côté API pour un usage administratif
 * futur (ex. accès direct par idF).
 */
function restaurerFournisseur(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        $idF = (int)($_POST['idF'] ?? 0);
        if ($idF <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Identifiant fournisseur manquant.']);
            return;
        }

        $existant = recupererFournisseur($bdBASI, $idF);
        if (!$existant) {
            echo json_encode(['status' => 'error', 'message' => 'Fournisseur introuvable.']);
            return;
        }

        if ($existant['statut'] !== 'supprimer') {
            echo json_encode(['status' => 'error', 'message' => "Ce fournisseur n'est pas supprimé."]);
            return;
        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $stmt = $bdBASI->prepare("UPDATE fournisseur SET statut = 'actif', date_derniere_modification = ?, idUtilisateur = ? WHERE idF = ?");
        if (!$stmt) {
            throw new \RuntimeException('Préparation de la requête de restauration impossible.');
        }
        $ok = $stmt->execute([$dateEnregistrement, $sessionUserId, $idF]);
        if (!$ok) {
            $info = $stmt->errorInfo();
            throw new \RuntimeException($info[2] ?? 'Échec de la restauration du fournisseur.');
        }

        try {
            $restaure = recupererFournisseur($bdBASI, $idF);
            if ($restaure) {
                enregistrerHistorique($bdBASI, $restaure, "Restauration du fournisseur (par $sessionMatricule)", $sessionUserId);
            }
        } catch (\Throwable $eHisto) {
            error_log('[Fournisseur][restaurerFournisseur][historique] ' . $eHisto->getMessage());
        }

        echo json_encode(['status' => 'success', 'message' => 'Fournisseur restauré avec succès.']);
    } catch (\Throwable $e) {
        error_log('[Fournisseur][restaurerFournisseur] ' . $e->getMessage());
        erreurSql('Impossible de restaurer le fournisseur.');
    }
}

function activerDesactiverFournisseur(PDO $bdBASI, int $sessionUserId, string $sessionMatricule): void {
    try {
        $idF = (int)($_POST['idF'] ?? 0);
        if ($idF <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Identifiant fournisseur manquant.']);
            return;
        }

        $existant = recupererFournisseur($bdBASI, $idF);
        if (!$existant || $existant['statut'] === 'supprimer') {
            echo json_encode(['status' => 'error', 'message' => 'Fournisseur introuvable.']);
            return;
        }

        $nouveauStatut = ($existant['statut'] === 'actif') ? 'inactif' : 'actif';

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $stmt = $bdBASI->prepare("UPDATE fournisseur SET statut = ?, date_derniere_modification = ?, idUtilisateur = ? WHERE idF = ?");
        if (!$stmt) {
            throw new \RuntimeException('Préparation de la requête de changement de statut impossible.');
        }
        $ok = $stmt->execute([$nouveauStatut, $dateEnregistrement, $sessionUserId, $idF]);
        if (!$ok) {
            $info = $stmt->errorInfo();
            throw new \RuntimeException($info[2] ?? 'Échec du changement de statut.');
        }

        try {
            $modifie = recupererFournisseur($bdBASI, $idF);
            $motif = $nouveauStatut === 'actif'
                ? "Activation du fournisseur (par $sessionMatricule)"
                : "Désactivation du fournisseur (par $sessionMatricule)";
            if ($modifie) {
                enregistrerHistorique($bdBASI, $modifie, $motif, $sessionUserId);
            }
        } catch (\Throwable $eHisto) {
            error_log('[Fournisseur][activerDesactiverFournisseur][historique] ' . $eHisto->getMessage());
        }

        echo json_encode([
            'status'        => 'success',
            'message'       => $nouveauStatut === 'actif'
                ? 'Fournisseur activé avec succès.'
                : 'Fournisseur désactivé avec succès.',
            'nouveauStatut' => $nouveauStatut,
        ]);
    } catch (\Throwable $e) {
        error_log('[Fournisseur][activerDesactiverFournisseur] ' . $e->getMessage());
        erreurSql('Impossible de modifier le statut du fournisseur.');
    }
}

function historiqueFournisseur(PDO $bdBASI): void {
    try {
        $idF = (int)($_POST['idF'] ?? $_GET['idF'] ?? 0);
        if ($idF <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Identifiant fournisseur manquant.']);
            return;
        }

        $stmt = $bdBASI->prepare("
            SELECT id, idF, nomF, prenomF, adresseF, telF, emailF, entreprise, ville,
                   statut, date_creation, motif, dateEnregistrement, idUtilisateur
            FROM historiqueFournisseur
            WHERE idF = ?
            ORDER BY dateEnregistrement DESC
        ");
        if (!$stmt) {
            throw new \RuntimeException("Préparation de la requête d'historique impossible.");
        }
        $ok = $stmt->execute([$idF]);
        if (!$ok) {
            $info = $stmt->errorInfo();
            throw new \RuntimeException($info[2] ?? "Échec de la récupération de l'historique.");
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $rows]);
    } catch (\Throwable $e) {
        error_log('[Fournisseur][historiqueFournisseur] ' . $e->getMessage());
        erreurSql("Impossible de charger l'historique du fournisseur.");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   ROUTAGE
   1 = listerFournisseurs        (exclut automatiquement statut='supprimer')
   2 = ajouterFournisseur
   3 = modifierFournisseur
   4 = supprimerFournisseur      (suppression douce : statut → 'supprimer')
   5 = activerDesactiverFournisseur
   6 = historiqueFournisseur
   7 = restaurerFournisseur      (statut 'supprimer' → 'actif', usage admin)
   8 = listerDemandes            (liste Achat/Paiement, filtre type + épuisement)
   9 = voirDemande                (détail d'une demande : en-tête + ses lignes)
   10 = listerModesReglement      (table mode_reglement)
   11 = listerModesPaiement       (table mode_paiement)
   12 = passerCommande            (bon de commande + tranches + lignes + MAJ demandes_ligne)
   13 = demanderFactureProforma   (enregistre la demande envoyée à 3 fournisseurs)
   14 = passerPaiement            (paiement + tranches + lignes + justificatifs + MAJ demandes_ligne)
   17 = listerToutesLignesAchat   (toutes les lignes d'achat non épuisées, toutes demandes confondues)
   18 = listerToutesLignesPaiement (toutes les lignes de paiement non épuisées, toutes demandes confondues)
   19 = listerDossiersAModifier    (passer_achat_et_paiement.idStatut = 5)
   20 = detailDossierAModifier     (détail + lignes + documents actifs, pour pré-remplir le formulaire)
   21 = modifierCommande           (modification d'une commande rejetée, idStatut 5 → 1)
   22 = modifierPaiement           (modification d'un paiement rejeté, idStatut 5 → 1)
   23 = listerToutesOperations     (tableau de bord + liste filtrable, tous statuts)
   24 = detailOperation            (détail complet, quel que soit le statut)
   25 = suiviOperation             (historique / suivi des étapes)
   26 = envoyerCaisse              (idStatut 4 → 6, Acceptée → En paiement — exige un BC pour l'achat)
   27 = uploaderBC                 (téléverse le bon de commande, achat / idStatut = 4 uniquement)
   28 = detailDossierComplet       (Dossier + Pièces — idStatut = 7 uniquement)
   (La génération des PDF/ZIP se fait dans un script autonome, hors de ce contrôleur —
    voir dfc-demande-facture-proforma.php)

   L'ensemble du routage est protégé par un try/catch de dernier recours :
   toute exception non gérée par les fonctions ci-dessus (erreur inattendue,
   type d'option invalide, etc.) est journalisée et renvoyée proprement en JSON
   au lieu de faire planter le script ou renvoyer du HTML d'erreur PHP.
═══════════════════════════════════════════════════════════════════════════ */
try {
    switch ($option) {

        case 1:
            listerFournisseurs($bdBASI);
            break;

        case 2:
            ajouterFournisseur($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 3:
            modifierFournisseur($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 4:
            supprimerFournisseur($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 5:
            activerDesactiverFournisseur($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 6:
            historiqueFournisseur($bdBASI);
            break;

        case 7:
            restaurerFournisseur($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 8:
            listerDemandes($bdBASI, $basiController);
            break;

        case 9:
            voirDemande($bdBASI, $basiController);
            break;

        case 10:
            listerModesReglement($bdBASI);
            break;

        case 11:
            listerModesPaiement($bdBASI);
            break;

        case 12:
            passerCommande($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 13:
            demanderFactureProforma($bdBASI, $sessionUserId);
            break;

        case 14:
            passerPaiement($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 17:
            listerToutesLignesAchat($bdBASI, $basiController);
            break;

        case 18:
            listerToutesLignesPaiement($bdBASI, $basiController);
            break;

        case 19:
            listerDossiersAModifier($bdBASI, $basiController);
            break;

        case 20:
            detailDossierAModifier($bdBASI, $basiController);
            break;

        case 21:
            modifierCommande($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 22:
            modifierPaiement($bdBASI, $sessionUserId, $sessionMatricule);
            break;

        case 23:
            listerToutesOperations($bdBASI, $basiController);
            break;

        case 24:
            detailOperation($bdBASI, $basiController);
            break;

        case 25:
            suiviOperation($bdBASI, $basiController);
            break;

        case 26:
            envoyerCaisse($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 27:
            uploaderBC($bdBASI, $basiController, $sessionUserId, $sessionMatricule);
            break;

        case 28:
            detailDossierComplet($bdBASI, $basiController);
            break;


        // Créer une commande d'achat avec ses lignes + pro-forma PDF





        // Créer une commande d'achat avec ses lignes + pro-forma PDF

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Option '$option' non reconnue."]);
            exit;
    }
} catch (\Throwable $e) {
    error_log('[Fournisseur][Routage] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => "Une erreur inattendue est survenue."]);
    exit;
}