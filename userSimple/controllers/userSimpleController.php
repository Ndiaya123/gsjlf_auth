<?php
/* =============================================================================
 *  user-controller
 *
 *  option 1 : page d'accueil de l'application
 *  option 2 : liste des agents du périmètre du chef connecté   (HTML <option>)
 *  option 3 : liste des directeurs (hors DG, hors soi-même)    (HTML <option>)
 *  option 4 : les deux colonnes de tâches pour une cible       (JSON)
 *  option 5 : affecter une ou plusieurs tâches                 (JSON)
 *  option 6 : retirer  une ou plusieurs tâches                 (JSON)
 * ========================================================================== */

ini_set('session.gc_maxlifetime', 36000);
session_set_cookie_params(36000);
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

include_once('../../bdP.php');


class userSimpleController extends BDP
{

    function tokenencrypt($data)
    {
        $secretKey = 'U@hbENTDRI@TCRI@T2022';
        $secretIv = '[www.ent.uahb.sn](https://www.ent.uahb.sn)';
        $encryptMethod = "AES-256-CBC";
        $key = hash('sha256', $secretKey);
        $iv = substr(hash('sha256', $secretIv), 0, 16);
        $result = openssl_encrypt($data, $encryptMethod, $key, 0, $iv);
        return $result = base64_encode($result);
    }

    function tokendecrypt($data)
    {
        $secretKey = 'U@hbENTDRI@TCRI@T2022';
        $secretIv = '[www.ent.uahb.sn](https://www.ent.uahb.sn)';
        $encryptMethod = "AES-256-CBC";
        $key = hash('sha256', $secretKey);
        $iv = substr(hash('sha256', $secretIv), 0, 16);
        $result = openssl_decrypt(base64_decode($data), $encryptMethod, $key, 0, $iv);
        return $result;
    }

    function decode($s)
    {
        if (version_compare(PHP_VERSION, '8.1.999', 'le')) {
            return utf8_decode($s);
        }

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
        }

        if (class_exists('UConverter')) {
            return UConverter::transcode($s, 'ISO-8859-1', 'UTF8');
        }

        if (function_exists('iconv')) {
            return iconv('UTF-8', 'ISO-8859-1', $s);
        }

        $s = (string)$s;
        $len = \strlen($s);

        for ($i = 0, $j = 0; $i < $len; ++$i, ++$j) {
            switch ($s[$i] & "\xF0") {
                case "\xC0":
                case "\xD0":
                    $c = (\ord($s[$i] & "\x1F") << 6) | \ord($s[++$i] & "\x3F");
                    $s[$j] = $c < 256 ? \chr($c) : '?';
                    break;

                case "\xF0":
                    ++$i;

                case "\xE0":
                    $s[$j] = '?';
                    $i += 2;
                    break;

                default:
                    $s[$j] = $s[$i];
            }
        }

        return substr($s, 0, $j);
    }


    function fctRetirerAccents($varMaChaine)
    {
        $search = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'à', 'á', 'â', 'ã', 'ä', 'å', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ð', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ');
        $replace = array('A', 'A', 'A', 'A', 'A', 'A', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 'a', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y');

        $varMaChaine = str_replace($search, $replace, $varMaChaine);
        return $varMaChaine;
    }


    public function verificationMatricule($matricule)
    {
        $bdp = $this->connect();
        $data = [
            ':matricule' => $matricule
        ];
        $stmt = $bdp->prepare("SELECT * FROM personnels WHERE matricule=:matricule");
        $stmt->execute($data);
        $listes = $stmt->fetchAll(PDO::FETCH_OBJ);

        return $listes;
    }

    public function verificationContrat($matricule)
    {
        $bdp = $this->connect();
        $data = [
            ':matricule' => $matricule
        ];
        $stmt = $bdp->prepare("SELECT * FROM contrat WHERE matricule=:matricule");
        $stmt->execute($data);
        $listes = $stmt->fetchAll(PDO::FETCH_OBJ);

        return count($listes);
    }

    public function verificationInfosPersonnel($matricule)
    {
        $bdp = $this->connect();
        $data = [
            ':matricule' => $matricule
        ];
        $stmt = $bdp->prepare("SELECT etatCivil.id as idEtatCivil,etatCivil.identifiant,etatCivil.prenom,etatCivil.nom,etatCivil.photo,personnels.matricule,affectations.idUniteAdministrativeNiv1,affectations.idUniteAdministrativeNiv2,affectations.idUniteAdministrativeNiv3 FROM personnels,etatCivil,affectations WHERE personnels.idEtatCivil=etatCivil.id AND personnels.idAffectation=affectations.id AND personnels.matricule=:matricule");
        $stmt->execute($data);
        $listes = $stmt->fetchAll(PDO::FETCH_OBJ);

        return $listes;
    }

    public function listeContrat($identifiant)
    {
        $bdp = $this->connect();

        $requete = "SELECT typeContrat.type as typeContrat,typeContrat.codeTypeContrat,contrat.dateDebutContrat,contrat.dateFinContrat,contrat.nbrDheure,contrat.idTypeStatutContrat,typeStatutContrat.statut FROM contrat,typeContrat,typeStatutContrat WHERE contrat.idTypeContrat=typeContrat.id AND contrat.idTypeStatutContrat=typeStatutContrat.id AND contrat.identifiant=:identifiant ORDER BY contrat.dateDebutContrat DESC";
        $data = [
            'identifiant' => $identifiant
        ];
        $smt = $bdp->prepare($requete);
        $smt->execute($data);
        $listes = $smt->fetchAll(PDO::FETCH_OBJ);
        return $listes;
    }


    public function returnContratEnCours($identifiant)
    {
        $bdp = $this->connect();

        $requeteHP = "SELECT * FROM historiquesPersonnel WHERE historiquesPersonnel.identifiant=:identifiant AND historiquesPersonnel.statut=:statut";
        $dataHP = [
            'identifiant' => $identifiant,
            'statut' => 1
        ];
        $smtHP = $bdp->prepare($requeteHP);
        $smtHP->execute($dataHP);
        $listeHP = $smtHP->fetchAll(PDO::FETCH_OBJ);

        if (count($listeHP) == 1) {
            $requete = "SELECT contrat.identifiant,contrat.idTypeContrat,typeContrat.type as typeContrat,contrat.dateDebutContrat,contrat.dateFinContrat,contrat.idTypeStatutContrat,contrat.nbrDheure,typeStatutContrat.statut,qualifications.qualification FROM contrat,typeContrat,typeStatutContrat,personnels,qualifications WHERE contrat.idPersonnel=personnels.id AND personnels.idQualification=qualifications.id AND contrat.idTypeContrat=typeContrat.id AND contrat.idTypeStatutContrat=typeStatutContrat.id AND contrat.id=:id ORDER BY contrat.dateDebutContrat DESC LIMIT 1";
            $data = [
                'id' => $listeHP['0']->idContrat,
            ];
            $smt = $bdp->prepare($requete);
            $smt->execute($data);
            $liste = $smt->fetchAll(PDO::FETCH_OBJ);

            return $liste;
        } else {
            return array();
        }
    }

    public function infoCompteGmail($identifiant)
    {
        $bdp = $this->connect();

        $requete = "SELECT * FROM compteGmail WHERE compteGmail.identifiant=:identifiant";
        $data = [
            'identifiant' => $identifiant
        ];
        $smt = $bdp->prepare($requete);
        $smt->execute($data);
        $listes = $smt->fetchAll(PDO::FETCH_OBJ);
        return $listes;
    }

    // public function infoPosteAReponsabilite($identifiant)
    // {
    //     $bdp = $this->connect();

    //     $requete = "SELECT * FROM postesAResponsabilite WHERE postesAResponsabilite.identifiant=:identifiant AND postesAResponsabilite.statutPoste=:statusPoste";
    //     $data = [
    //         'identifiant' => $identifiant,
    //         'statusPoste' => 1
    //     ];
    //     $smt = $bdp->prepare($requete);
    //     $smt->execute($data);
    //     $listes = $smt->fetchAll(PDO::FETCH_OBJ);
    //     return $listes;
    // }

    public function infoPosteAReponsabilite($identifiant)
    {
        $bdp = $this->connect();

        $requete = "SELECT postesAResponsabilite.identifiant,postesAResponsabilite.dateDebut,postesAResponsabilite.dateFin,fonction.idStatutFonction,fonction.statutGradePoste FROM postesAResponsabilite,fonction WHERE postesAResponsabilite.idFonction=fonction.id AND postesAResponsabilite.identifiant=:identifiant AND postesAResponsabilite.statutPoste=:statusPoste";
        $data = [
            'identifiant' => $identifiant,
            'statusPoste' => 1
        ];
        $smt = $bdp->prepare($requete);
        $smt->execute($data);
        $listes = $smt->fetchAll(PDO::FETCH_OBJ);
        return $listes;
    }


    public function infoCompteUtilisateur($identifiant)
    {
        $bdp = $this->connect();

        $requete = "SELECT * FROM utilisateurs WHERE utilisateurs.identifiant=:identifiant";
        $data = [
            'identifiant' => $identifiant,
            'statusPoste' => 1
        ];
        $smt = $bdp->prepare($requete);
        $smt->execute($data);
        $listes = $smt->fetchAll(PDO::FETCH_OBJ);
        return $listes;
    }

    public function infoAffectations($identifiant)
    {
        $bdp = $this->connect();

        $requete = "SELECT affectations.idUniteAdministrativeNiv1,affectations.idUniteAdministrativeNiv2,affectations.idUniteAdministrativeNiv3 FROM personnels, affectations WHERE personnels.idAffectation=affectations.id AND personnels.identifiant=:identifiant AND affectations.statutAffectation=:statutAffectation";
        $data = [
            'identifiant' => $identifiant,
            'statutAffectation' => 1
        ];
        $smt = $bdp->prepare($requete);
        $smt->execute($data);
        $listes = $smt->fetchAll(PDO::FETCH_OBJ);
        return $listes;
    }

    public function infoPersonnelsNiveau1($idNiveau)
    {
        $bdp = $this->connect();

        $requete = "SELECT etatCivil.matricule,etatCivil.identifiant,etatCivil.prenom,etatCivil.nom,etatCivil.photo,qualifications.qualification,personnels.idQualification,affectations.idUniteAdministrativeNiv1,affectations.idUniteAdministrativeNiv2,affectations.idUniteAdministrativeNiv3 FROM personnels,etatCivil,qualifications,affectations WHERE personnels.idEtatCivil=etatCivil.id  AND personnels.idQualification=qualifications.id AND personnels.idAffectation=affectations.id AND affectations.idUniteAdministrativeNiv1=:idNiveau AND affectations.statutAffectation=:statutAffectation";
        $data = [
            'idNiveau' => $idNiveau,
            'statutAffectation' => 1
        ];
        $smt = $bdp->prepare($requete);
        $smt->execute($data);
        $listes = $smt->fetchAll(PDO::FETCH_OBJ);
        return $listes;
    }

    public function infoPersonnelsNiveau2($idNiveau)
    {
        $bdp = $this->connect();

        $requete = "SELECT etatCivil.matricule,etatCivil.identifiant,etatCivil.prenom,etatCivil.nom,etatCivil.photo,qualifications.qualification,personnels.idQualification,affectations.idUniteAdministrativeNiv1,affectations.idUniteAdministrativeNiv2,affectations.idUniteAdministrativeNiv3 FROM personnels,etatCivil,qualifications,affectations WHERE personnels.idEtatCivil=etatCivil.id  AND personnels.idQualification=qualifications.id AND personnels.idAffectation=affectations.id AND affectations.idUniteAdministrativeNiv2=:idNiveau AND affectations.statutAffectation=:statutAffectation";
        $data = [
            'idNiveau' => $idNiveau,
            'statutAffectation' => 1
        ];
        $smt = $bdp->prepare($requete);
        $smt->execute($data);
        $listes = $smt->fetchAll(PDO::FETCH_OBJ);
        return $listes;
    }

    public function infoPersonnelsNiveau3($idNiveau)
    {
        $bdp = $this->connect();

        $requete = "SELECT etatCivil.matricule,etatCivil.identifiant,etatCivil.prenom,etatCivil.nom,etatCivil.photo,qualifications.qualification,personnels.idQualification,affectations.idUniteAdministrativeNiv1,affectations.idUniteAdministrativeNiv2,affectations.idUniteAdministrativeNiv3 FROM personnels,etatCivil,qualifications,affectations WHERE personnels.idEtatCivil=etatCivil.id  AND personnels.idQualification=qualifications.id AND personnels.idAffectation=affectations.id AND affectations.idUniteAdministrativeNiv3=:idNiveau AND affectations.statutAffectation=:statutAffectation";
        $data = [
            'idNiveau' => $idNiveau,
            'statutAffectation' => 1
        ];
        $smt = $bdp->prepare($requete);
        $smt->execute($data);
        $listes = $smt->fetchAll(PDO::FETCH_OBJ);
        return $listes;
    }

    public function infoUniteAdministrativeNiv1($idUniteAdministrativeNiv1)
    {
        try {
            $bdp = $this->connect();

            $requete = "SELECT * FROM unite_administrative_niv1 WHERE id=:idUniteAdministrativeNiv1";
            $data = [
                'idUniteAdministrativeNiv1' => $idUniteAdministrativeNiv1
            ];
            $smt = $bdp->prepare($requete);
            $smt->execute($data);
            $listes = $smt->fetchAll(PDO::FETCH_OBJ);

            if (count($listes) == 1) {
                return $listes['0']->sans;
            } else {
                return "erreur";
            }

        } catch (\Throwable $th) {
            return "erreur";
        }
    }

    public function infoUniteAdministrativeNiv2($idUniteAdministrativeNiv2)
    {
        try {
            $bdp = $this->connect();

            $requete = "SELECT * FROM unite_administrative_niv2 WHERE id=:idUniteAdministrativeNiv2";
            $data = [
                'idUniteAdministrativeNiv2' => $idUniteAdministrativeNiv2
            ];
            $smt = $bdp->prepare($requete);
            $smt->execute($data);
            $listes = $smt->fetchAll(PDO::FETCH_OBJ);

            if (count($listes) == 1) {
                return $listes['0']->sans;
            } else {
                return "erreur";
            }

        } catch (\Throwable $th) {
            return "erreur";
        }
    }

    public function infoUniteAdministrativeNiv3($idUniteAdministrativeNiv3)
    {
        try {
            $bdp = $this->connect();

            $requete = "SELECT * FROM unite_administrative_niv3 WHERE id=:idUniteAdministrativeNiv3";
            $data = [
                'idUniteAdministrativeNiv3' => $idUniteAdministrativeNiv3
            ];
            $smt = $bdp->prepare($requete);
            $smt->execute($data);
            $listes = $smt->fetchAll(PDO::FETCH_OBJ);

            if (count($listes) == 1) {
                return $listes['0']->sans;
            } else {
                return "erreur";
            }

        } catch (\Throwable $th) {
            return "erreur";
        }
    }


    /**
     * Détermine la page d'accueil/dashboard à afficher en haut du menu
     * Priorité : Incarnés → Structures → Par défaut → $page_par_defaut
     */
    function getPageAccueil(
        $listeTachesIncarnes,
        $listeTachesStructures,
        $listeTachesParDefaut,
        string $page_par_defaut
    ): string {

        $incarnes   = $listeTachesIncarnes   ?? [];
        $structures = $listeTachesStructures ?? [];
        $parDefaut  = $listeTachesParDefaut  ?? [];

        // ── 1. Chercher dans Incarnés ──────────────────────────────────────────
        foreach ($incarnes as $tache) {
            if (
                stripos($tache->nom, 'Accueil') === 0 ||
                stripos($tache->nom, 'Dashboard') === 0
            ) {
                return $tache->url;
            }
        }

        // ── 2. Chercher dans Structures ────────────────────────────────────────
        foreach ($structures as $tache) {
            if (
                stripos($tache->nom, 'Accueil') === 0 ||
                stripos($tache->nom, 'Dashboard') === 0
            ) {
                return $tache->url;
            }
        }

        // ── 3. Chercher dans Par défaut ────────────────────────────────────────
        foreach ($parDefaut as $tache) {
            if (
                stripos($tache->nom, 'Accueil') === 0 ||
                stripos($tache->nom, 'Dashboard') === 0
            ) {
                return $tache->url;
            }
        }

        // ── 4. Fallback → page par défaut globale ──────────────────────────────
        return $page_par_defaut;
    }

}


$bdP = new BDP();
$bdP = $bdP->connect();
$userSimpleController = new userSimpleController();
date_default_timezone_set('Africa/Dakar');


function valid_donnees($donnees)
{
    $donnees = trim($donnees);
    $donnees = stripslashes($donnees);
    $donnees = htmlspecialchars($donnees);
    return $donnees;
}


/* =============================================================================
/* =============================================================================
 * =============================================================================
 *
 *   GESTION DES TÂCHES — affectation / retrait pour un AGENT ou un DIRECTEUR
 *
 *   Le périmètre dépend UNIQUEMENT du type de la cible :
 *
 *   - Cible DIRECTEUR : tâches INCARNÉES
 *       tache.idTypeTache = 2
 *       AND tache.idFonction = (SELECT idFonction FROM direction
 *                               WHERE id = $_SESSION['user_direction'])
 *       AND tache.active = 1
 *
 *   - Cible AGENT : tâches de STRUCTURE
 *       tache.idTypeTache = 1
 *       AND tache.active  = 1
 *       AND tache.idUniteAdministrativeNiv{niveau} = idUniteAdministrative
 *       pour chaque ligne de direction_employe_chef dont
 *       idChefDirection = $_SESSION['user_direction']
 *
 *   Dans les deux cas :
 *   - si la tâche est liée à des qualifications valides (tache_qualification,
 *     valide = 1), la cible doit posséder l'une d'elles ;
 *   - le même périmètre borne l'affectation ET le retrait : on ne peut retirer
 *     que ce que l'on aurait pu octroyer ;
 *   - le retrait ne supprime rien : access -> 0 + idUtilisateurSupRetrait
 *     + dateRetrait, l'historique est conservé.
 *
 * =============================================================================
 * ========================================================================== */


/* -----------------------------------------------------------------------------
 *  CONFIG
 * -------------------------------------------------------------------------- */

// Table des comptes ENT référencée par tache_utilisateur.idUtilisateur.
// L'ancien module écrivait « utilisateur » (singulier), infoCompteUtilisateur()
// lit « utilisateurs » (pluriel). Mettre ici le nom réellement utilisé.
if (!defined('TU_TABLE_UTILISATEUR')) define('TU_TABLE_UTILISATEUR', 'utilisateurs');

// Types de tâches
if (!defined('TU_TYPE_STRUCTURE')) define('TU_TYPE_STRUCTURE', 1);   // -> agents
if (!defined('TU_TYPE_INCARNE'))   define('TU_TYPE_INCARNE',   2);   // -> directeurs


/* -----------------------------------------------------------------------------
 *  Utilitaires de réponse
 * -------------------------------------------------------------------------- */

/** Réponse JSON puis fin de script. */
function tuJson(array $payload, $http = 200)
{
    if (!headers_sent()) {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    die;
}

/** Liste d'entiers positifs, dédoublonnée, à partir d'un $_POST quelconque. */
function tuIds($brut)
{
    if (!is_array($brut)) {
        $brut = ($brut === null || $brut === '') ? [] : [$brut];
    }
    $ids = [];
    foreach ($brut as $v) {
        $v = (int) $v;
        if ($v > 0 && !in_array($v, $ids, true)) {
            $ids[] = $v;
        }
    }
    return $ids;
}

/** Compte les opérations réussies dans un lot de résultats. */
function tuNbOk(array $resultats)
{
    $n = 0;
    foreach ($resultats as $r) {
        if ($r['ok']) {
            $n++;
        }
    }
    return $n;
}


/* -----------------------------------------------------------------------------
 *  Identité
 * -------------------------------------------------------------------------- */

/** Identifiant etatCivil correspondant à un matricule. */
function tuIdentifiantParMatricule(PDO $bdP, $matricule)
{
    $st = $bdP->prepare("SELECT ec.identifiant
                         FROM personnels p
                         JOIN etatCivil ec ON p.idEtatCivil = ec.id
                         WHERE p.matricule = ? LIMIT 1");
    $st->execute([$matricule]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null) ? null : $v;
}

/** Compte ENT correspondant à un identifiant etatCivil. */
function tuIdUtilisateur(PDO $bdP, $identifiant)
{
    $st = $bdP->prepare("SELECT id FROM " . TU_TABLE_UTILISATEUR . "
                         WHERE identifiant = ? LIMIT 1");
    $st->execute([$identifiant]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null) ? null : (int) $v;
}

/** Fonction portée par la direction du connecté (sert au périmètre « incarné »). */
function tuIdFonctionDirection(PDO $bdP)
{
    if (empty($_SESSION['user_direction'])) {
        return null;
    }
    $st = $bdP->prepare("SELECT idFonction FROM direction WHERE id = ?");
    $st->execute([$_SESSION['user_direction']]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null) ? null : (int) $v;
}


/* -----------------------------------------------------------------------------
 *  Périmètre des personnes
 *  Utilisé par les option 2 / 3 ET par le contrôle d'autorisation des écritures.
 * -------------------------------------------------------------------------- */

/** Agents rattachés aux unités dont le connecté est chef. Indexé par identifiant. */
function tuListeAgents(PDO $bdP, $ctrl)
{
    if (empty($_SESSION['user_direction'])) {
        return [];
    }

    $st = $bdP->prepare("SELECT * FROM direction_employe_chef WHERE idChefDirection = ?");
    $st->execute([$_SESSION['user_direction']]);
    $unites = $st->fetchAll(PDO::FETCH_OBJ);

    $agents = [];
    foreach ($unites as $u) {
        $niveau = (int) $u->niveau;
        if ($niveau < 1 || $niveau > 3) {
            continue;
        }

        $sql = "SELECT ec.identifiant, ec.prenom, ec.nom, p.matricule
                FROM personnels p
                JOIN affectations a  ON p.idAffectation = a.id
                JOIN etatCivil    ec ON p.idEtatCivil   = ec.id
                WHERE a.idUniteAdministrativeNiv{$niveau} = :idUA
                  AND a.statutAffectation = 1";
        $q = $bdP->prepare($sql);
        $q->execute([':idUA' => $u->idUniteAdministrative]);

        foreach ($q->fetchAll(PDO::FETCH_OBJ) as $p) {
            $agents[$p->identifiant] = [
                'identifiant' => $p->identifiant,
                'matricule'   => $p->matricule,
                'prenom'      => ucwords(mb_strtolower($p->prenom)),
                'nom'         => $ctrl->fctRetirerAccents(mb_strtoupper($p->nom)),
                'prenomNom'   => ucfirst(mb_strtolower($p->prenom)) . ' '
                    . $ctrl->fctRetirerAccents(mb_strtoupper($p->nom)),
            ];
        }
    }

    uasort($agents, function ($a, $b) {
        return strcmp($a['prenomNom'], $b['prenomNom']);
    });

    return $agents;
}

/** Directeurs (hors DG et hors soi-même). Indexé par identifiant. */
function tuListeDirecteurs(PDO $bdP, $ctrl)
{
    $matriculeConnecte = isset($_SESSION['tmpMatricule']) ? $_SESSION['tmpMatricule'] : null;

    $sql = "SELECT DISTINCT ec.identifiant, ec.prenom, ec.nom, p.matricule
            FROM direction d
            JOIN postesAResponsabilite par ON d.idFonction   = par.idFonction
            JOIN personnels p              ON par.idPersonnel = p.id
            JOIN etatCivil  ec             ON p.idEtatCivil   = ec.id
            WHERE par.statutPoste = :statutPoste
              AND d.estDG = :estDG";
    $st = $bdP->prepare($sql);
    $st->execute([':statutPoste' => 1, ':estDG' => 0]);

    $directeurs = [];
    foreach ($st->fetchAll(PDO::FETCH_OBJ) as $p) {
        if ($matriculeConnecte !== null && $p->matricule == $matriculeConnecte) {
            continue;
        }
        $directeurs[$p->identifiant] = [
            'identifiant' => $p->identifiant,
            'matricule'   => $p->matricule,
            'prenom'      => ucwords(mb_strtolower($p->prenom)),
            'nom'         => $ctrl->fctRetirerAccents(mb_strtoupper($p->nom)),
            'prenomNom'   => ucfirst(mb_strtolower($p->prenom)) . ' '
                . $ctrl->fctRetirerAccents(mb_strtoupper($p->nom)),
        ];
    }

    uasort($directeurs, function ($a, $b) {
        return strcmp($a['prenomNom'], $b['prenomNom']);
    });

    return $directeurs;
}

/**
 * Contrôle d'autorisation : la cible demandée est-elle dans le périmètre du
 * connecté, et de quel type ? C'est ce qui détermine ensuite quelles tâches
 * sont manipulables, et ce qui empêche d'agir sur une personne hors périmètre.
 */
function tuCibleAutorisee(PDO $bdP, $ctrl, $identifiant)
{
    $directeurs = tuListeDirecteurs($bdP, $ctrl);
    if (isset($directeurs[$identifiant])) {
        return array_merge($directeurs[$identifiant], ['type' => 'directeur']);
    }

    $agents = tuListeAgents($bdP, $ctrl);
    if (isset($agents[$identifiant])) {
        return array_merge($agents[$identifiant], ['type' => 'agent']);
    }

    return null;
}

/** Cible complète : type + compte ENT + qualification. Interrompt si refus. */
function tuCible(PDO $bdP, $ctrl, $identifiant)
{
    $cible = tuCibleAutorisee($bdP, $ctrl, $identifiant);
    if (!$cible) {
        tuJson([
            'status'  => 'erreur',
            'message' => "Cette personne ne fait pas partie de votre périmètre."
        ], 403);
    }

    $st = $bdP->prepare("SELECT p.idQualification, q.qualification
                         FROM personnels p
                         JOIN etatCivil ec ON p.idEtatCivil = ec.id
                         LEFT JOIN qualifications q ON p.idQualification = q.id
                         WHERE ec.identifiant = ? LIMIT 1");
    $st->execute([$identifiant]);
    $p = $st->fetch(PDO::FETCH_ASSOC);

    $idUtilisateur = tuIdUtilisateur($bdP, $identifiant);
    if (!$idUtilisateur) {
        tuJson([
            'status'  => 'erreur',
            'message' => $cible['prenomNom'] . " n'a pas de compte ENT actif."
        ], 409);
    }

    $cible['idUtilisateur']   = $idUtilisateur;
    $cible['idQualification'] = $p ? (int) $p['idQualification'] : 0;
    $cible['qualification']   = ($p && isset($p['qualification'])) ? $p['qualification'] : null;

    return $cible;
}


/* -----------------------------------------------------------------------------
 *  Périmètre des tâches — dépend du TYPE de la cible
 * -------------------------------------------------------------------------- */

/** Colonnes sélectionnées pour une tâche, factorisées. */
function tuSelectTache()
{
    return "t.id, t.nom AS tache, t.url, t.idTypeTache, t.idFonction,
            t.idSousMenu, sm.nom AS sousMenu";
}

/**
 * Tâches INCARNÉES manipulables : type 2, portées par la fonction de la
 * direction du connecté.
 */
function tuTachesIncarnees(PDO $bdP)
{
    $idFonction = tuIdFonctionDirection($bdP);
    if (!$idFonction) {
        return [];
    }

    $sql = "SELECT " . tuSelectTache() . "
            FROM tache t
            LEFT JOIN sous_menu sm ON t.idSousMenu = sm.id
            WHERE t.idTypeTache = :type
              AND t.idFonction  = :idFonction
              AND t.active      = 1
            GROUP BY t.id";
    $st = $bdP->prepare($sql);
    $st->execute([':type' => TU_TYPE_INCARNE, ':idFonction' => $idFonction]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Tâches de STRUCTURE manipulables : type 1, portées par les unités
 * administratives dont le connecté est chef (direction_employe_chef).
 */
function tuTachesStructure(PDO $bdP)
{
    if (empty($_SESSION['user_direction'])) {
        return [];
    }

    $st = $bdP->prepare("SELECT * FROM direction_employe_chef WHERE idChefDirection = ?");
    $st->execute([$_SESSION['user_direction']]);
    $unites = $st->fetchAll(PDO::FETCH_OBJ);

    $taches = [];
    foreach ($unites as $u) {
        $niveau = (int) $u->niveau;
        if ($niveau < 1 || $niveau > 3) {
            continue;                                   // liste blanche stricte
        }

        // NB : la colonne est bien idUniteAdministrativeNiv1/2/3 sur `tache`.
        $sql = "SELECT " . tuSelectTache() . "
                FROM tache t
                LEFT JOIN sous_menu sm ON t.idSousMenu = sm.id
                WHERE t.idUniteAdministrativeNiv{$niveau} = :idUA
                  AND t.idTypeTache = :type
                  AND t.active      = 1
                GROUP BY t.id";
        $q = $bdP->prepare($sql);
        $q->execute([':idUA' => $u->idUniteAdministrative, ':type' => TU_TYPE_STRUCTURE]);

        // Dédoublonnage : une même tâche peut être atteinte par deux unités.
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $taches[(int) $t['id']] = $t;
        }
    }

    return array_values($taches);
}

/** Périmètre complet des tâches manipulables pour une cible donnée. */
function tuPerimetreTaches(PDO $bdP, array $cible)
{
    $taches = ($cible['type'] === 'directeur')
        ? tuTachesIncarnees($bdP)
        : tuTachesStructure($bdP);

    return tuNormaliserTaches($taches);
}

/** PDO renvoie les entiers en chaînes : on type proprement avant le JSON. */
function tuNormaliserTaches(array $lignes)
{
    $out = [];
    foreach ($lignes as $l) {
        $out[] = [
            'id'          => (int) $l['id'],
            'tache'       => $l['tache'],
            'url'         => isset($l['url']) ? $l['url'] : null,
            'idTypeTache' => (int) $l['idTypeTache'],
            'idFonction'  => (!isset($l['idFonction']) || $l['idFonction'] === null)
                ? null : (int) $l['idFonction'],
            'idSousMenu'  => (!isset($l['idSousMenu']) || $l['idSousMenu'] === null)
                ? null : (int) $l['idSousMenu'],
            'sousMenu'    => isset($l['sousMenu']) ? $l['sousMenu'] : null,
        ];
    }
    return $out;
}

/** Ids des tâches actuellement détenues par la cible (access = 1). */
function tuIdsDetenues(PDO $bdP, $idUtilisateurCible)
{
    $st = $bdP->prepare("SELECT DISTINCT idTache
                         FROM tache_utilisateur
                         WHERE idUtilisateur = ?
                           AND access = 1
                           AND idUtilisateurSupRetrait IS NULL");
    $st->execute([$idUtilisateurCible]);

    $ids = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $v) {
        $ids[(int) $v] = true;
    }
    return $ids;
}

/**
 * Répartit le périmètre en deux colonnes :
 *   detenues    -> colonne de gauche  (retrait possible)
 *   disponibles -> colonne de droite  (affectation possible)
 */
function tuColonnes(array $perimetre, array $idsDetenues)
{
    $detenues = [];
    $dispo    = [];

    foreach ($perimetre as $t) {
        if (isset($idsDetenues[$t['id']])) {
            $detenues[] = $t;
        } else {
            $dispo[] = $t;
        }
    }

    $tri = function ($a, $b) {
        $sa = ($a['sousMenu'] === null) ? "\xFF" : $a['sousMenu'];
        $sb = ($b['sousMenu'] === null) ? "\xFF" : $b['sousMenu'];
        $c  = strcmp($sa, $sb);
        return ($c !== 0) ? $c : strcmp($a['tache'], $b['tache']);
    };
    usort($detenues, $tri);
    usort($dispo, $tri);

    return ['detenues' => $detenues, 'disponibles' => $dispo];
}

/** Index id => tâche, pour valider une écriture contre le périmètre. */
function tuIndexer(array $perimetre)
{
    $index = [];
    foreach ($perimetre as $t) {
        $index[$t['id']] = $t;
    }
    return $index;
}


/* -----------------------------------------------------------------------------
 *  Écriture
 * -------------------------------------------------------------------------- */

/** La qualification de la cible autorise-t-elle cette tâche ? */
function tuQualificationOk(PDO $bdP, $idTache, $idQualificationCible)
{
    $st = $bdP->prepare("SELECT idQualification FROM tache_qualification
                         WHERE idTache = ? AND valide = 1");
    $st->execute([$idTache]);
    $requises = $st->fetchAll(PDO::FETCH_COLUMN);

    if (empty($requises)) {
        return true;                                    // aucune contrainte
    }
    return in_array((int) $idQualificationCible, array_map('intval', $requises), true);
}

/** Affectation d'une tâche. Idempotente ; réactive une ligne retirée si elle existe. */
function tuAffecterUne(PDO $bdP, array $index, array $cible, $idTache, $idSup)
{
    $idTache = (int) $idTache;

    // 1. La tâche appartient-elle au périmètre autorisé pour cette cible ?
    if (!isset($index[$idTache])) {
        return ['ok' => false, 'idTache' => $idTache, 'tache' => null,
            'message' => "Tâche hors de votre périmètre."];
    }
    $nom = $index[$idTache]['tache'];

    // 2. Qualification — lue en base pour la cible, jamais reçue du client.
    if (!tuQualificationOk($bdP, $idTache, $cible['idQualification'])) {
        return ['ok' => false, 'idTache' => $idTache, 'tache' => $nom,
            'message' => "Qualification requise non détenue."];
    }

    // 3. Déjà active ? on ne recrée pas de doublon.
    $st = $bdP->prepare("SELECT id FROM tache_utilisateur
                         WHERE idTache = ? AND idUtilisateur = ?
                           AND access = 1 AND idUtilisateurSupRetrait IS NULL
                         ORDER BY id DESC LIMIT 1");
    $st->execute([$idTache, $cible['idUtilisateur']]);
    if ($st->fetchColumn()) {
        return ['ok' => true, 'idTache' => $idTache, 'tache' => $nom,
            'message' => "Déjà affectée."];
    }

    $now = date('Y-m-d H:i:s');

    // 4. Une ligne retirée existe -> réactivation, sinon insertion.
    $st = $bdP->prepare("SELECT id FROM tache_utilisateur
                         WHERE idTache = ? AND idUtilisateur = ?
                         ORDER BY id DESC LIMIT 1");
    $st->execute([$idTache, $cible['idUtilisateur']]);
    $ancienne = $st->fetchColumn();

    if ($ancienne) {
        $q  = $bdP->prepare("UPDATE tache_utilisateur
                             SET access = 1,
                                 idUtilisateurSupOctroiement = ?,
                                 dateOctroiement = ?,
                                 idUtilisateurSupRetrait = NULL,
                                 dateRetrait = NULL
                             WHERE id = ?");
        $ok = $q->execute([$idSup, $now, $ancienne]);
    } else {
        $q  = $bdP->prepare("INSERT INTO tache_utilisateur
              (idUtilisateur, idTache, idUtilisateurSupOctroiement, dateOctroiement,
               dateEnregistrement, access, matricule, identifiant)
              VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
        $ok = $q->execute([$cible['idUtilisateur'], $idTache, $idSup, $now, $now,
            $cible['matricule'], $cible['identifiant']]);
    }

    return ['ok' => (bool) $ok, 'idTache' => $idTache, 'tache' => $nom,
        'message' => $ok ? "Affectée." : "Échec de l'enregistrement."];
}

/** Retrait d'une tâche : la dernière ligne active passe à access = 0. */
function tuRetirerUne(PDO $bdP, array $index, array $cible, $idTache, $idSup)
{
    $idTache = (int) $idTache;

    // Même borne que l'affectation : on ne retire que ce qu'on pourrait octroyer.
    if (!isset($index[$idTache])) {
        return ['ok' => false, 'idTache' => $idTache, 'tache' => null,
            'message' => "Tâche hors de votre périmètre."];
    }
    $nom = $index[$idTache]['tache'];

    $st = $bdP->prepare("SELECT id FROM tache_utilisateur
                         WHERE idTache = ? AND idUtilisateur = ?
                           AND access = 1 AND idUtilisateurSupRetrait IS NULL
                         ORDER BY id DESC LIMIT 1");
    $st->execute([$idTache, $cible['idUtilisateur']]);
    $ligneId = $st->fetchColumn();

    if (!$ligneId) {
        return ['ok' => false, 'idTache' => $idTache, 'tache' => $nom,
            'message' => "Tâche non détenue."];
    }

    $q  = $bdP->prepare("UPDATE tache_utilisateur
                         SET access = 0, idUtilisateurSupRetrait = ?, dateRetrait = ?
                         WHERE id = ?");
    $ok = $q->execute([$idSup, date('Y-m-d H:i:s'), $ligneId]);

    return ['ok' => (bool) $ok, 'idTache' => $idTache, 'tache' => $nom,
        'message' => $ok ? "Retirée." : "Échec du retrait."];
}

/** Garde commune aux options 4 / 5 / 6. Renvoie l'id ENT du connecté. */
function tuGarde(PDO $bdP)
{
    if (empty($_SESSION['tmpMatricule']) || empty($_SESSION['user_direction'])) {
        tuJson(['status' => 'sessionExpired'], 401);
    }

    $identifiant = tuIdentifiantParMatricule($bdP, $_SESSION['tmpMatricule']);
    if (!$identifiant) {
        tuJson(['status'  => 'erreur',
            'message' => "Votre fiche personnel est introuvable."], 403);
    }

    $idUtilisateur = tuIdUtilisateur($bdP, $identifiant);
    if (!$idUtilisateur) {
        tuJson(['status'  => 'erreur',
            'message' => "Votre compte ENT est introuvable."], 403);
    }

    return ['identifiant' => $identifiant, 'idUtilisateur' => $idUtilisateur];
}



/* =============================================================================
 *  ROUTAGE
 * ========================================================================== */

$option   = (!empty($_POST['option'])) ? $_POST['option'] : '';
$BASE_URL = "http://localhost/personnel/";

switch ($option) {


    /* ---------------------------------------------------------------------
     *  case 1 : page d'accueil de l'application
     * ------------------------------------------------------------------ */
    case 1:

        if (!empty($_POST['tmp'])) {

            try {
                $idAppli = valid_donnees($_POST['tmp']);
                $idAppli = (int) $idAppli;

                $tmpIdP = null;
                $tmpMatricule = null;
                $tmpPrenom = null;
                $tmpNom = null;
                $tmpPhoto = null;
                $tmpEmail = null;
                $tmpInitiales = null;
                $tmpNbrAppli = null;
                $tmpNbrAppliEnAttente = null;
                $tmpNbrAppliAutorisees = null;
                $tmpNbrAppliRefusees = null;
                $connectUser = null;
                $tmpId = null;
                $tmpIdBASI = null;
                $tmpListeApplication = null;
                $tmpEntite = null;

                $listeTachesStructures = null;
                $listeTachesIncarnes = null;
                $listeTachesParDefaut = null;
                $statutPoste = null;

                $lien_logo1 = null;
                $lien_logo2 = null;

                if (
                    !isset($_SESSION['tmpIdP']) ||
                    !isset($_SESSION['tmpMatricule']) ||
                    !isset($_SESSION['tmpPrenom']) ||
                    !isset($_SESSION['tmpNom']) ||
                    !isset($_SESSION['tmpPhoto']) ||
                    !isset($_SESSION['tmpEmail']) ||
                    !isset($_SESSION['tmpInitiales']) ||
                    !isset($_SESSION['tmpNbrAppli']) ||
                    !isset($_SESSION['tmpNbrAppliEnAttente']) ||
                    !isset($_SESSION['tmpNbrAppliAutorisees']) ||
                    !isset($_SESSION['tmpNbrAppliRefusees']) ||
                    !isset($_SESSION['connectUserGSJLF_ENT']) ||
                    !isset($_SESSION['tmpListeApplication']) ||
                    !isset($_SESSION['tmpEntite']) ||
                    !isset($_SESSION['listeTachesStructures']) ||
                    !isset($_SESSION['listeTachesIncarnes']) ||
                    !isset($_SESSION['listeTachesParDefaut'])
                ) {

                    session_unset();
                    session_destroy();

                    echo "sesionExpired";
                    die;

                } else {

                    $connectUser = $_SESSION['connectUserGSJLF_ENT'] ?? null;

                    if ($connectUser == 1) {
                        $lien_logo1 = "/personnel/admin-accueil";
                        $lien_logo2 = "/personnel/admin-accueil";
                    } else if ($connectUser == 2) {
                        $lien_logo1 = "/personnel/user-accueil";
                        $lien_logo2 = "/personnel/user-accueil";
                    }

                    $tmpIdP = $_SESSION['tmpIdP'] ?? null;
                    $tmpMatricule = $_SESSION['tmpMatricule'] ?? null;
                    $tmpPrenom = $_SESSION['tmpPrenom'] ?? null;
                    $tmpNom = $_SESSION['tmpNom'] ?? null;
                    $tmpPhoto = $_SESSION['tmpPhoto'] ?? null;
                    $tmpEmail = $_SESSION['tmpPhoto'] ?? null;
                    $tmpInitiales = $_SESSION['tmpInitiales'] ?? null;
                    $tmpNbrAppli = $_SESSION['tmpNbrAppli'] ?? null;
                    $tmpNbrAppliEnAttente = $_SESSION['tmpNbrAppliEnAttente'] ?? null;
                    $tmpNbrAppliAutorisees = $_SESSION['tmpNbrAppliAutorisees'] ?? null;
                    $tmpNbrAppliRefusees = $_SESSION['tmpNbrAppliRefusees'] ?? null;
                    $tmpId = $_SESSION['tmpId'] ?? null;
                    $tmpIdBASI = $_SESSION['tmpIdBASI'] ?? null;
                    $tmpListeApplication = $_SESSION['tmpListeApplication'] ?? null;
                    $tmpEntite = $_SESSION['tmpEntite'];

                    $listeTachesStructures = $_SESSION['listeTachesStructures'];
                    $listeTachesIncarnes = $_SESSION['listeTachesIncarnes'];
                    $listeTachesParDefaut = $_SESSION['listeTachesParDefaut'];
                    $statutPoste = $_SESSION['statutPoste'] ?? null;
                }

                $page_par_defaut = null;

                foreach ($tmpListeApplication as $appli) {
                    if ($appli->numero === $idAppli) {
                        $page_par_defaut = $appli->page_defaut;
                    }
                }

                $page_accueil = $userSimpleController->getPageAccueil(
                    $listeTachesIncarnes,
                    $listeTachesStructures,
                    $listeTachesParDefaut,
                    $page_par_defaut ?? 'http://localhost/personnel/signin'   // fallback ultime
                );

                echo "ac" . $page_accueil;
                die;

            } catch (\Throwable $th) {
                error_log($th->getMessage());
                echo "erreur";
                die;
            }

        } else {
            echo "erreur";
            die;
        }
        break;


    /* ---------------------------------------------------------------------
     *  case 2 : liste des agents du périmètre — <option> HTML
     * ------------------------------------------------------------------ */
    case 2:

        if (empty($_SESSION['user_direction'])) {
            echo "sessionExpired";
            die;
        }

        try {
            $agents = tuListeAgents($bdP, $userSimpleController);

            if (empty($agents)) {
                echo "erreur";
                die;
            }

            echo '<option value="">Sélectionner un agent</option>';
            foreach ($agents as $tmp) {
                echo '<option value="' . htmlspecialchars($tmp['identifiant'], ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars($tmp['prenomNom'], ENT_QUOTES, 'UTF-8') . '</option>';
            }
            die;

        } catch (\Throwable $th) {
            error_log('[taches:2] ' . $th->getMessage());
            echo "erreur";
            die;
        }
        break;


    /* ---------------------------------------------------------------------
     *  case 3 : liste des directeurs — <option> HTML
     * ------------------------------------------------------------------ */
    case 3:

        if (empty($_SESSION['tmpMatricule'])) {
            echo "sessionExpired";
            die;
        }

        try {
            $directeurs = tuListeDirecteurs($bdP, $userSimpleController);

            if (empty($directeurs)) {
                echo "erreur";
                die;
            }

            echo '<option value="">Sélectionner un directeur</option>';
            foreach ($directeurs as $tmp) {
                echo '<option value="' . htmlspecialchars($tmp['identifiant'], ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars($tmp['prenomNom'], ENT_QUOTES, 'UTF-8') . '</option>';
            }
            die;

        } catch (\Throwable $th) {
            error_log('[taches:3] ' . $th->getMessage());
            echo "erreur";
            die;
        }
        break;

    /* ---------------------------------------------------------------------
     *  case 4 : les deux colonnes de tâches pour une cible
     *           POST { option:4, identifiant:"..." }
     *
     *  Le type de la cible (agent / directeur) est déterminé côté serveur,
     *  et c'est lui qui décide du périmètre : structure ou incarné.
     * ------------------------------------------------------------------ */
    case 4:

        try {
            tuGarde($bdP);

            $identifiant = isset($_POST['identifiant']) ? trim((string) $_POST['identifiant']) : '';
            if ($identifiant === '') {
                tuJson(['status' => 'erreur', 'message' => "Aucune personne sélectionnée."], 400);
            }

            $cible     = tuCible($bdP, $userSimpleController, $identifiant);
            $perimetre = tuPerimetreTaches($bdP, $cible);
            $colonnes  = tuColonnes($perimetre, tuIdsDetenues($bdP, $cible['idUtilisateur']));

            tuJson([
                'status' => 'ok',
                'cible'  => [
                    'identifiant'   => $cible['identifiant'],
                    'prenomNom'     => $cible['prenomNom'],
                    'type'          => $cible['type'],
                    'qualification' => $cible['qualification'],
                    'typeTache'     => ($cible['type'] === 'directeur') ? 'incarné' : 'structure',
                ],
                'detenues'    => $colonnes['detenues'],
                'disponibles' => $colonnes['disponibles'],
            ]);

        } catch (\Throwable $th) {
            error_log('[taches:4] ' . $th->getMessage());
            tuJson(['status' => 'erreur', 'message' => "Erreur lors du chargement des tâches."], 500);
        }
        break;


    /* ---------------------------------------------------------------------
     *  case 5 : affecter une ou plusieurs tâches
     *           POST { option:5, identifiant:"...", taches:[1,2,3] }
     * ------------------------------------------------------------------ */
    case 5:

        try {
            $chef = tuGarde($bdP);

            $identifiant = isset($_POST['identifiant']) ? trim((string) $_POST['identifiant']) : '';
            $ids         = tuIds(isset($_POST['taches']) ? $_POST['taches'] : []);

            if ($identifiant === '' || empty($ids)) {
                tuJson(['status' => 'erreur', 'message' => "Sélection incomplète."], 400);
            }

            $cible = tuCible($bdP, $userSimpleController, $identifiant);
            $index = tuIndexer(tuPerimetreTaches($bdP, $cible));

            $resultats = [];
            $bdP->beginTransaction();
            foreach ($ids as $idTache) {
                $resultats[] = tuAffecterUne($bdP, $index, $cible, $idTache, $chef['idUtilisateur']);
            }
            $bdP->commit();

            $ok = tuNbOk($resultats);
            tuJson([
                'status'    => ($ok === count($resultats)) ? 'ok' : 'partiel',
                'message'   => $ok . '/' . count($resultats) . ' tâche(s) affectée(s) à '
                    . $cible['prenomNom'] . '.',
                'resultats' => $resultats,
            ]);

        } catch (\Throwable $th) {
            if ($bdP->inTransaction()) {
                $bdP->rollBack();
            }
            error_log('[taches:5] ' . $th->getMessage());
            tuJson(['status' => 'erreur', 'message' => "Erreur lors de l'affectation."], 500);
        }
        break;


    /* ---------------------------------------------------------------------
     *  case 6 : retirer une ou plusieurs tâches
     *           POST { option:6, identifiant:"...", taches:[1,2,3] }
     * ------------------------------------------------------------------ */
    case 6:

        try {
            $chef = tuGarde($bdP);

            $identifiant = isset($_POST['identifiant']) ? trim((string) $_POST['identifiant']) : '';
            $ids         = tuIds(isset($_POST['taches']) ? $_POST['taches'] : []);

            if ($identifiant === '' || empty($ids)) {
                tuJson(['status' => 'erreur', 'message' => "Sélection incomplète."], 400);
            }

            $cible = tuCible($bdP, $userSimpleController, $identifiant);
            $index = tuIndexer(tuPerimetreTaches($bdP, $cible));

            $resultats = [];
            $bdP->beginTransaction();
            foreach ($ids as $idTache) {
                $resultats[] = tuRetirerUne($bdP, $index, $cible, $idTache, $chef['idUtilisateur']);
            }
            $bdP->commit();

            $ok = tuNbOk($resultats);
            tuJson([
                'status'    => ($ok === count($resultats)) ? 'ok' : 'partiel',
                'message'   => $ok . '/' . count($resultats) . ' tâche(s) retirée(s) à '
                    . $cible['prenomNom'] . '.',
                'resultats' => $resultats,
            ]);

        } catch (\Throwable $th) {
            if ($bdP->inTransaction()) {
                $bdP->rollBack();
            }
            error_log('[taches:6] ' . $th->getMessage());
            tuJson(['status' => 'erreur', 'message' => "Erreur lors du retrait."], 500);
        }
        break;


    default:
        echo "erreur";
        die;
}