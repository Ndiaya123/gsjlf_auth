<?php
/* =============================================================================
 *  admin-controller
 *
 *  ... cases 1 à 32 existants ...
 *  case 33 : liste des tâches invisibles associées à une tâche   (JSON)
 *  case 34 : retirer une association — estVisible passe à 3
 * ========================================================================== */

ini_set('session.gc_maxlifetime', 36000);
session_set_cookie_params(36000);
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

include_once('../../bdP.php');
require_once('../../includes/phpMailer/PHPMailer.php');
require_once('../../includes/phpMailer/SMTP.php');
require_once('../../includes/phpMailer/Exception.php');

class adminController extends BDP
{


    function tokenencrypt($data)
    {
        $secretKey = 'U@hbENTDRI@TCRI@T2022';
        $secretIv = 'www.ent.uahb.sn';
        $encryptMethod = "AES-256-CBC";
        $key = hash('sha256', $secretKey);
        $iv = substr(hash('sha256', $secretIv), 0, 16);
        $result = openssl_encrypt($data, $encryptMethod, $key, 0, $iv);
        return $result = base64_encode($result);
    }

    function tokendecrypt($data)
    {
        $secretKey = 'U@hbENTDRI@TCRI@T2022';
        $secretIv = 'www.ent.uahb.sn';
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


    public function sendEmail($to, $name, $subject, $body)
    {
        $mail = new PHPMailer(true);
        try {
            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'criat@uahb.sn';
            $mail->Password = 'tevklroalsmwpjsl';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;


            $mail->setFrom('criat@uahb.sn', 'UAHB');
            $mail->addAddress($to);


            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;


            $mail->send();
            return true;
        } catch (Exception $e) {
            if (strpos($mail->ErrorInfo, 'Invalid address') !== false) {
                return false;
            } else {
                return false;
            }
        }
    }


    function dateFranc($date)
    {
        try {
            $datetime = new DateTime($date);

            $formatter = new IntlDateFormatter(
                'fr_FR',
                IntlDateFormatter::FULL,
                IntlDateFormatter::NONE
            );

            return $formatter->format($datetime);

        } catch (Exception $e) {
            return null;
        }
    }


    function comparerDate($date)
    {
        try {
            $dateParam = new DateTime($date);
            $dateParam->modify('+24 hours');

            $nowPlus24h = new DateTime();

            if ($dateParam > $nowPlus24h) {
                return true;
            }

            return false;

        } catch (Exception $e) {
            return false; // ou false selon ton choix de sécurité
        }
    }


    function comparerDateContrat($date)
    {
        try {
            $dateParam = new DateTime($date);
            $nowPlus24h = new DateTime();

            if ($dateParam > $nowPlus24h) {
                return true;
            }

            return false;

        } catch (Exception $e) {
            return false; // ou false selon ton choix de sécurité
        }
    }


    function imageExiste($url)
    {
        // Vérifie si l'URL est vide ou invalide
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false; // Retourne false si l'URL est vide ou invalide
        }

        // Essayer de récupérer les en-têtes de l'URL
        $headers = @get_headers($url, 1);

        // Vérifie si l'en-tête a été récupéré et si le code de statut est 200
        if ($headers && strpos($headers[0], '200') !== false) {
            return true; // L'image existe
        } else {
            return false; // L'image n'existe pas ou il y a un autre problème
        }
    }


    public function returnPosteResponsabilite($identifiant)
    {
        try {

            $bdP = $this->connect();

            $data = [
                'identifiant' => $identifiant,
                'statutPoste' => 1
            ];

            $stmt = $bdP->prepare("
            SELECT *
            FROM postesAResponsabilite, fonction
            WHERE postesAResponsabilite.idFonction=fonction.id  AND postesAResponsabilite.identifiant = :identifiant
              AND postesAResponsabilite.statutPoste = :statutPoste
        ");

            $stmt->execute($data);

            $result =  $stmt->fetch(PDO::FETCH_OBJ);

            if($result)
            {
                return $result->fonction;

            }else
            {
                return null;
            }


        } catch (\Throwable $th) {
            return null;
        }
    }


    function get_info($matricule)
    {

        $bdP = $this->connect();

        $listes =  array();
        foreach ($matricule as $matricule)
        {


            $data_perso = [
                'matricule' => $matricule
            ];
            $sql_perso = "SELECT
    p.identifiant,
    p.matricule,
    p.idQualification
FROM personnels p
WHERE p.matricule = :matricule;";
            $stmt_perso = $bdP->prepare($sql_perso);
            $stmt_perso->execute($data_perso);
            $result_perso = $stmt_perso->fetch(PDO::FETCH_OBJ);

            if ($result_perso) {


                $data_perso_contrat = [
                    'matricule' => $matricule,
                    'idTypeStatutContrat' => 1,

                ];
                $sql_perso_contrat = "SELECT * FROM contrat WHERE matricule=:matricule AND idTypeStatutContrat=:idTypeStatutContrat";
                $stmt_perso_contrat = $bdP->prepare($sql_perso_contrat);
                $stmt_perso_contrat->execute($data_perso_contrat);
                $result_perso_contrat = $stmt_perso_contrat->fetch(PDO::FETCH_OBJ);

                if ($result_perso_contrat) {

                    $debutContrat = $result_perso_contrat->dateDebutContrat;
                    $finContrat = $result_perso_contrat->dateFinContrat;


                    if ($this->comparerDateContrat($finContrat)) {


                        $listes[] = array(
                            'matricule' => $result_perso->matricule,
                            'idQualification' => $result_perso->idQualification,

                        );

                    }


                }

            }
        }

        return $listes;

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

        $incarnes  = $listeTachesIncarnes  ?? [];
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


    function genererMotDePasseDefaut(): string
    {
        return 'GSJLF@' . random_int(100000, 999999);
    }

}


$bdP = new BDP();
$bdP = $bdP->connect();
$adminController = new adminController();
date_default_timezone_set('Africa/Dakar');


function valid_donnees($donnees)
{
    $donnees = trim($donnees);
    $donnees = stripslashes($donnees);
    $donnees = htmlspecialchars($donnees);
    return $donnees;
}

$option = (!empty($_POST['option'])) ? $_POST['option'] : '';
$BASE_URL = "http://localhost/personnel/";

switch ($option) {

    // =========================================================================
    //  case 1 — Liste des utilisateurs
    // =========================================================================
    case 1:

        try {

            $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


            $sql = "SELECT
    utilisateurs.id,
    utilisateurs.matricule,
    utilisateurs.identifiant,
      utilisateurs.dateCreation,
      utilisateurs.statutUtilisateur,
      utilisateurs.statutActivation,
      utilisateurs.idTypeUtilisateur,
  etatCivil.photo,
    etatCivil.prenom,
    etatCivil.nom,
    etatCivil.sexe,
    qualifications.qualification,
    compteGmail.email,

    COALESCE(
        unite_administrative_niv1.codeNiv1,
        unite_administrative_niv2.codeNiv2,
        unite_administrative_niv3.codeNiv3
    ) AS affectation

FROM utilisateurs

INNER JOIN personnels
    ON utilisateurs.matricule = personnels.matricule
    AND utilisateurs.identifiant = personnels.identifiant

INNER JOIN etatCivil
    ON personnels.idEtatCivil = etatCivil.id

INNER JOIN qualifications
    ON personnels.idQualification = qualifications.id

INNER JOIN compteGmail
    ON personnels.idCompteGmail = compteGmail.id

INNER JOIN affectations
    ON personnels.idAffectation = affectations.id

LEFT JOIN unite_administrative_niv1
    ON affectations.idUniteAdministrativeNiv1 = unite_administrative_niv1.id

LEFT JOIN unite_administrative_niv2
    ON affectations.idUniteAdministrativeNiv2 = unite_administrative_niv2.id

LEFT JOIN unite_administrative_niv3
    ON affectations.idUniteAdministrativeNiv3 = unite_administrative_niv3.id

ORDER BY utilisateurs.dateCreation ASC;";
            $stmt = $bdP->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_OBJ);

            $listeUsers =  array();

            $posteAResponsabilite = null;
            $photo = null;
            $numero = 1;
            $etat = NULL;
            $etat1 = null;
            $etat2 = null;

            foreach ($result as $user) {

                $posteAResponsabilit = $adminController->returnPosteResponsabilite($user->identifiant);

                if ($adminController->imageExiste($user->photo)) {
                    $photo = $user->photo;
                } else {
                    $photo = "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";

                    if ($user->sexe == "Féminin") {
                        $photo = "/personnel/includes/fpdf/template/avatar1.png";
                    } else if ($user->sexe == "Masculin") {
                        $photo = "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";
                    } else {
                        $photo = "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";
                    }


                }

                if ($user->statutActivation == 0) {

                    $etat = '<span class="badge badge-warning">En attente d\'activation</span>';
                    $etat1 = 0;

                } elseif ($user->statutUtilisateur == 0) {

                    $etat = '<span class="badge badge-success">Compte actif</span>';
                    $etat1 = 1;


                } elseif ($user->statutUtilisateur == 1) {

                    $etat = '<span class="badge badge-danger">Compte bloqué</span>';
                    $etat1 = 2;

                } else {

                    $etat = '<span class="badge badge-dark">Erreur</span>';
                    $etat1 = 0;

                }

                if($user->idTypeUtilisateur == 2)
                {
                    $etat2 = 1;
                }else
                {
                    $etat2 = 0;
                }

                $listeUsers[] = array(
                    'numero' => $numero,
                    'tmp' => $adminController->tokenencrypt($user->id),
                    'matricule' => $user->matricule,
                    'prenom' => ucwords(mb_strtolower($user->prenom)),
                    'nom' => $adminController->fctRetirerAccents(mb_strtoupper($user->nom)),
                    'dateCreation' => date("d/m/Y H:i:s", strtotime($user->dateCreation)),
                    'photo' => $photo,
                    'email' => $user->email,
                    'qualification' => $user->qualification,
                    'affectation' => $user->affectation,
                    'poste' => $posteAResponsabilite,
                    'etat' => $etat,
                    'etat1' => $etat1,
                    'etat2' => $etat2
                );
                ++$numero;

            }

            echo json_encode($listeUsers);
            die;


        } catch (Exception $e) {
            echo json_encode(array());
            die;
        }

        break;


    // =========================================================================
    //  case 2 — Statistiques utilisateurs
    // =========================================================================
    case 2:

        $actifs = 0;
        $inactifs = 0;
        $total_etudiant = 0;


        try {

            $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


            $sql = "SELECT
    utilisateurs.id,
    utilisateurs.matricule,
    utilisateurs.identifiant,
      utilisateurs.statutUtilisateur,
      utilisateurs.statutActivation,
      utilisateurs.idTypeUtilisateur
FROM utilisateurs";
            $stmt = $bdP->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_OBJ);


            $actifs = NULL;
            $inactifs = null;
            $bloquer = null;

            foreach ($result as $user) {


                if ($user->statutActivation == 0) {

                    ++$inactifs;

                } elseif ($user->statutUtilisateur == 0) {

                    ++$actifs;

                } elseif ($user->statutUtilisateur == 1) {

                    ++$actifs;
                    ++$bloquer;
                }


            }

            echo json_encode([
                'total' => (int)count($result),
                'actifs' => (int)$actifs,
                'inactifs' => (int)$inactifs,
                'bloques' => (int)$bloquer
            ]);


        } catch (Exception $e) {


            echo json_encode(['total' => 0, 'actifs' => 0, 'inactifs' => 0,'bloques' => 0]);
            die;

        }

        break;


    // =========================================================================
    //  case 3 — Bloquer un compte
    // =========================================================================
    case 3:

        if(!empty($_POST['tmp']))
        {
            $id = $adminController->tokendecrypt(valid_donnees($_POST['tmp']));


            $idUtilisateur = 1;
//            $idUtilisateur = null;
//            if(!empty($_SESSION['tmpIdP']))
//            {
//                $idUtilisateur = $_SESSION['tmpIdP'];
//            }else
//            {
//                echo "sessionExpired";
//                die;
//            }


            try {
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bdP->beginTransaction();


                date_default_timezone_set('Africa/Dakar');
                $date_jour = date('Y-m-d');


                $data = [
                    'id' => $id
                ];

                $sql = "SELECT * FROM utilisateurs WHERE id=:id";
                $stmt = $bdP->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if ($result) {


                    if($result->statutUtilisateur == 0) {


                        $data_up_user = [
                            'statutUtilisateur' => 1,
                            'id' => $id
                        ];

                        $sql_up_user = "UPDATE utilisateurs
                SET statutUtilisateur = :statutUtilisateur
                WHERE id = :id";

                        $stmt_up_user = $bdP->prepare($sql_up_user);

                        $tmpStmt_up_user = $stmt_up_user->execute($data_up_user);

                        if ($tmpStmt_up_user) {

                            if ($stmt_up_user->rowCount() > 0) {

                                $table = "utilisateurs";
                                $motif = "Blocage du compte de l'utilisateur";
                                $dateEnregistrement = date('Y-m-d H:i:s');
                                $dataHistorique = [
                                    'identifiant' => $result->identifiant,
                                    'tables' => $table,
                                    'motif' => $motif,
                                    'idUtilisateur' => $idUtilisateur,
                                    'dateEnregistrement' => $dateEnregistrement

                                ];
                                $sqlHistorique = "INSERT INTO historiques(identifiant,motif,tables,idUtilisateur,dateEnregistrement) VALUES (:identifiant,:motif,:tables,:idUtilisateur,:dateEnregistrement)";
                                $stmtHistorique = $bdP->prepare($sqlHistorique);
                                $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);


                                if ($tmpStmtHistorique) {

                                    $bdP->commit();
                                    echo "succès";
                                    die;

                                } else {
                                    if ($bdP->inTransaction()) {
                                        $bdP->rollBack();
                                    }
                                    echo "erreur";
                                    die;
                                }


                            } else {
                                if ($bdP->inTransaction()) {
                                    $bdP->rollBack();
                                }
                                echo "erreur";
                                die;
                            }

                        } else {
                            if ($bdP->inTransaction()) {
                                $bdP->rollBack();
                            }
                            echo "erreur";
                            die;
                        }

                    }else
                    {
                        if ($bdP->inTransaction()) {
                            $bdP->rollBack();
                        }
                        echo "erreur";
                        die;
                    }


                } else {
                    if ($bdP->inTransaction()) {
                        $bdP->rollBack();
                    }
                    echo "erreur";
                    die;

                }

            } catch (Exception $e) {
                if ($bdP->inTransaction()) {
                    $bdP->rollBack();
                }
                echo "erreur";
                die;
            }


        }else
        {
            echo "erreur";
            die;
        }

        break;


    // =========================================================================
    //  case 4 — Débloquer un compte
    // =========================================================================
    case 4 :


        if(!empty($_POST['tmp']))
        {
            $id = $adminController->tokendecrypt(valid_donnees($_POST['tmp']));


            $idUtilisateur = 1;
//            $idUtilisateur = null;
//            if(!empty($_SESSION['tmpIdP']))
//            {
//                $idUtilisateur = $_SESSION['tmpIdP'];
//            }else
//            {
//                echo "sessionExpired";
//                die;
//            }


            try {
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bdP->beginTransaction();


                date_default_timezone_set('Africa/Dakar');
                $date_jour = date('Y-m-d');


                $data = [
                    'id' => $id];

                $sql = "SELECT * FROM utilisateurs WHERE id=:id";
                $stmt = $bdP->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if ($result) {


                    if($result->statutUtilisateur == 1) {


                        $data_up_user = [
                            'statutUtilisateur' => 0,
                            'id' => $id
                        ];

                        $sql_up_user = "UPDATE utilisateurs
                SET statutUtilisateur = :statutUtilisateur
                WHERE id = :id";

                        $stmt_up_user = $bdP->prepare($sql_up_user);

                        $tmpStmt_up_user = $stmt_up_user->execute($data_up_user);

                        if ($tmpStmt_up_user) {

                            if ($stmt_up_user->rowCount() > 0) {

                                $table = "utilisateurs";
                                $motif = "Blocage du compte de l'utilisateur";
                                $dateEnregistrement = date('Y-m-d H:i:s');
                                $dataHistorique = [
                                    'identifiant' => $result->identifiant,
                                    'tables' => $table,
                                    'motif' => $motif,
                                    'idUtilisateur' => $idUtilisateur,
                                    'dateEnregistrement' => $dateEnregistrement

                                ];
                                $sqlHistorique = "INSERT INTO historiques(identifiant,motif,tables,idUtilisateur,dateEnregistrement) VALUES (:identifiant,:motif,:tables,:idUtilisateur,:dateEnregistrement)";
                                $stmtHistorique = $bdP->prepare($sqlHistorique);
                                $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);


                                if ($tmpStmtHistorique) {

                                    $bdP->commit();
                                    echo "succès";
                                    die;

                                } else {
                                    if ($bdP->inTransaction()) {
                                        $bdP->rollBack();
                                    }
                                    echo "erreur";
                                    die;
                                }


                            } else {
                                if ($bdP->inTransaction()) {
                                    $bdP->rollBack();
                                }
                                echo "erreur";
                                die;
                            }

                        } else {
                            if ($bdP->inTransaction()) {
                                $bdP->rollBack();
                            }
                            echo "erreur";
                            die;
                        }

                    }else
                    {
                        if ($bdP->inTransaction()) {
                            $bdP->rollBack();
                        }
                        echo "erreur";
                        die;
                    }


                } else {
                    if ($bdP->inTransaction()) {
                        $bdP->rollBack();
                    }
                    echo "erreur";
                    die;

                }

            } catch (Exception $e) {
                if ($bdP->inTransaction()) {
                    $bdP->rollBack();
                }
                echo "erreur";
                die;
            }


        }else
        {
            echo "erreur";
            die;
        }
        break;


    // =========================================================================
    //  case 5 — Pré-remplissage création de compte à partir d'un matricule
    // =========================================================================
    case 5 :

        if (!empty($_POST['matricule'])) {

            try {
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


                date_default_timezone_set('Africa/Dakar');
                $date_jour = date('Y-m-d');

                $matricule = valid_donnees($_POST['matricule']);

                $infoPerso = array();


                $data = [
                    'matricule' => $matricule
                ];

                $sql = "SELECT * FROM utilisateurs WHERE matricule=:matricule";
                $stmt = $bdP->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if (!$result) {

                    $data_perso = [
                        'matricule' => $matricule
                    ];
                    $sql_perso = "SELECT
    p.identifiant,
    p.matricule,
    p.idEtatCivil,
    ec.prenom,
    ec.nom,
    ec.photo,
    ec.sexe,
    cg.email
FROM personnels p
INNER JOIN etatCivil ec
    ON p.idEtatCivil = ec.id
INNER JOIN compteGmail cg
    ON p.idCompteGmail = cg.id
WHERE p.matricule = :matricule;";
                    $stmt_perso = $bdP->prepare($sql_perso);
                    $stmt_perso->execute($data_perso);
                    $result_perso = $stmt_perso->fetch(PDO::FETCH_OBJ);

                    if ($result_perso) {


                        $photo = NULL;
                        if ($adminController->imageExiste($result_perso->photo)) {
                            $photo = $result_perso->photo;
                        } else {
                            $photo = "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";

                            if ($result_perso->sexe == "Féminin") {
                                $photo = "/personnel/includes/fpdf/template/avatar1.png";
                            } else if ($result_perso->sexe == "Masculin") {
                                $photo = "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";
                            } else {
                                $photo = "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";
                            }


                        }


                        $pwd = $adminController->genererMotDePasseDefaut();

                        echo json_encode([
                            'matricule' => $result_perso->matricule,
                            'prenom' => ucwords(mb_strtolower($result_perso->prenom)),
                            'nom' => $adminController->fctRetirerAccents(mb_strtoupper($result_perso->nom)),
                            'email' => $result_perso->email,
                            'photo' => $photo,
                            'pwd' => $pwd
                        ]);
                        die;


                    } else {

                        echo "matriculeExistsPas";
                        die;

                    }

                } else {

                    echo "dejaCompte";
                    die;

                }

            } catch (Exception $e) {

                echo "erreur";
                die;
            }


        } else {
            echo "champsObligatoire";
            die;
        }
        break;


    // =========================================================================
    //  case 6 — Création du compte + envoi des identifiants par e-mail
    // =========================================================================
    case 6:


        if (!empty($_POST['matricule2']) && !empty($_POST['email']) && !empty($_POST['password']) && !empty($_POST['prenom']) && !empty($_POST['nom'])) {


            $idUtilisateur = 1;
//            $idUtilisateur = null;
//            if(!empty($_SESSION['tmpIdP']))
//            {
//                $idUtilisateur = $_SESSION['tmpIdP'];
//            }else
//            {
//                echo "sessionExpired";
//                die;
//            }


            try {
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bdP->beginTransaction();


                date_default_timezone_set('Africa/Dakar');
                $date_jour = date('Y-m-d');

                $matricule = valid_donnees($_POST['matricule2']);
                $email = valid_donnees($_POST['email']);
                $password = valid_donnees($_POST['password']);
                $motDePasse = valid_donnees($_POST['password']);
                $lienConnexion = $BASE_URL."signin";
                $cgu = 1;

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo "emailInvalide";
                    die;
                }


                $data = [
                    'matricule' => $matricule,
                    'email' => $email
                ];

                $sql = "SELECT * FROM utilisateurs WHERE matricule=:matricule AND email=:email";
                $stmt = $bdP->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if (!$result) {

                    $data_perso = [
                        'matricule' => $matricule
                    ];
                    $sql_perso = "SELECT
    p.identifiant,
    p.idEtatCivil,
    ec.prenom,
    ec.nom,
    cg.email
FROM personnels p
INNER JOIN etatCivil ec
    ON p.idEtatCivil = ec.id
LEFT JOIN compteGmail cg
    ON p.idCompteGmail = cg.id
WHERE p.matricule = :matricule;";
                    $stmt_perso = $bdP->prepare($sql_perso);
                    $stmt_perso->execute($data_perso);
                    $result_perso = $stmt_perso->fetch(PDO::FETCH_OBJ);

                    if ($result_perso) {

                        if (strtolower(trim($email)) !== strtolower(trim($result_perso->email))) {
                            echo "pasCorrespondantEmail";
                            die;
                        }

                        $identifiant = $result_perso->identifiant;
                        $idEtatCivil = $result_perso->idEtatCivil;
                        $prenom = ucwords($result_perso->prenom);
                        $nom = $adminController->fctRetirerAccents(mb_strtoupper($result_perso->nom));


                        $data_perso_contrat = [
                            'matricule' => $matricule,
                            'idTypeStatutContrat' => 1,

                        ];
                        $sql_perso_contrat = "SELECT * FROM contrat WHERE matricule=:matricule AND idTypeStatutContrat=:idTypeStatutContrat";
                        $stmt_perso_contrat = $bdP->prepare($sql_perso_contrat);
                        $stmt_perso_contrat->execute($data_perso_contrat);
                        $result_perso_contrat = $stmt_perso_contrat->fetch(PDO::FETCH_OBJ);

                        if ($result_perso_contrat) {

                            $debutContrat = $result_perso_contrat->dateDebutContrat;
                            $finContrat = $result_perso_contrat->dateFinContrat;


                            if ($adminController->comparerDateContrat($finContrat)) {


                                $dateCreation = new DateTime();
                                $dateCreation = $dateCreation->format('Y-m-d H:i:s');
                                $password = password_hash(valid_donnees($password), PASSWORD_DEFAULT, ['cost' => 5]);


                                $dataCandidat = [
                                    'identifiant' => $identifiant,
                                    'matricule' => $matricule,
                                    'email' => $email,
                                    'password' => $password,
                                    'cgu' => $cgu,
                                    'dateCreation' => $dateCreation,
                                    'statutUtilisateur' => 0,
                                    'creerPar' => "Admin",
                                    'statutCreerPar' => 0,
                                    'statutActivation' => 1,
                                    'codeActivation' => NULL,
                                    'dateEnvoiCodeValidation' => NULL,
                                    'idEtatCivil' => $idEtatCivil,
                                    'idTypeUtilisateur' => 2
                                ];
                                $sql = "INSERT INTO utilisateurs(identifiant,matricule,email,password,cgu,dateCreation,statutUtilisateur,creerPar,statutCreerPar,statutActivation,codeActivation,dateEnvoiCodeValidation,idEtatCivil,idTypeUtilisateur) VALUES(:identifiant,:matricule,:email,:password,:cgu,:dateCreation,:statutUtilisateur,:creerPar,:statutCreerPar,:statutActivation,:codeActivation,:dateEnvoiCodeValidation,:idEtatCivil,:idTypeUtilisateur)";
                                $stmt = $bdP->prepare($sql);
                                $tmpStmt = $stmt->execute($dataCandidat);

                                if ($tmpStmt == 1) {


                                    $table = "utilisateurs";
                                    $motif = "Création de compte par l'admin";
                                    $dateEnregistrement = date('Y-m-d H:i:s');
                                    $dataHistorique = [
                                        'identifiant' => $identifiant,
                                        'tables' => $table,
                                        'motif' => $motif,
                                        'idUtilisateur' => $idUtilisateur,
                                        'dateEnregistrement' => $dateEnregistrement

                                    ];
                                    $sqlHistorique = "INSERT INTO historiques(identifiant,motif,tables,idUtilisateur,dateEnregistrement) VALUES (:identifiant,:motif,:tables,:idUtilisateur,:dateEnregistrement)";
                                    $stmtHistorique = $bdP->prepare($sqlHistorique);
                                    $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);


                                    if ($tmpStmtHistorique) {


                                        // ⚠️ ATTENTION — cette ligne écrase le destinataire réel :
                                        // tous les identifiants partent vers cette adresse.
                                        // À supprimer avant la mise en production.
                                        $email = "ndiaya.ndao@uahb.sn";

                                        $message = "<html>
<head>
  <title>Bienvenue sur l'ENT GSJLF – Vos identifiants de connexion</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background-color: #f4f4f4;
      font-family: Roboto, Arial, sans-serif;
    }
    .wrapper {
      max-width: 600px;
      margin: 40px auto;
      background-color: #ffffff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    .header {
      background-color: #113B26;
      padding: 32px 40px;
      text-align: center;
    }
    .header h1 {
      color: #f0cc6a;
      font-size: 22px;
      margin: 0;
      font-weight: 700;
      letter-spacing: 0.5px;
    }
    .header p {
      color: rgba(255,255,255,0.7);
      font-size: 13px;
      margin: 6px 0 0;
    }
    .body {
      padding: 36px 40px;
    }
    .body p {
      font-size: 15px;
      color: #202124;
      line-height: 1.7;
      margin: 0 0 16px;
    }

    /* Bloc identifiants */
    .credentials-box {
      background: linear-gradient(135deg, #f0faf4, #e8f5ed);
      border: 1.5px solid rgba(17,59,38,.18);
      border-radius: 10px;
      padding: 20px 24px;
      margin: 24px 0;
    }
    .credentials-box .cred-title {
      font-size: 11px;
      font-weight: 800;
      color: #113B26;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      margin: 0 0 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .cred-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 0;
      border-bottom: 1px solid rgba(17,59,38,.08);
    }
    .cred-row:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }
    .cred-row:first-of-type {
      padding-top: 0;
    }
    .cred-label {
      font-size: 12px;
      font-weight: 700;
      color: #5e6b61;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      flex-shrink: 0;
    }
    .cred-value {
      font-size: 14px;
      font-weight: 700;
      color: #113B26;
      text-align: right;
      word-break: break-all;
    }
    .cred-value.password {
      font-family: 'Courier New', monospace;
      font-size: 16px;
      letter-spacing: 0.08em;
      background: rgba(17,59,38,.08);
      padding: 4px 10px;
      border-radius: 6px;
    }

    /* Bouton connexion */
    .cta-wrap {
      text-align: center;
      margin: 28px 0;
    }
    .cta-btn {
      display: inline-block;
      background-color: #113B26;
      color: #ffffff !important;
      text-decoration: none;
      font-size: 15px;
      font-weight: 700;
      padding: 14px 36px;
      border-radius: 10px;
      letter-spacing: 0.3px;
    }

    /* Alerte changement de mot de passe */
    .alert-box {
      background-color: #fff8e1;
      border-left: 4px solid #f0cc6a;
      border-radius: 6px;
      padding: 14px 18px;
      margin: 24px 0;
    }
    .alert-box p {
      margin: 0;
      font-size: 14px;
      color: #5c3e08;
      font-weight: 600;
    }

    /* Étapes première connexion */
    .steps-box {
      background-color: #f9fdf9;
      border: 1px solid rgba(17,59,38,.1);
      border-radius: 8px;
      padding: 18px 20px;
      margin: 20px 0;
    }
    .steps-title {
      font-size: 13px;
      font-weight: 700;
      color: #113B26;
      margin: 0 0 12px;
    }
    .step {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      margin-bottom: 10px;
    }
    .step:last-child { margin-bottom: 0; }
    .step-num {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background-color: #113B26;
      color: white;
      font-size: 11px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      margin-top: 1px;
    }
    .step p {
      margin: 0;
      font-size: 13px;
      color: #202124;
      line-height: 1.6;
    }

    /* Avertissement sécurité */
    .warning-box {
      background-color: #fff3f3;
      border-left: 4px solid #e53935;
      border-radius: 6px;
      padding: 14px 18px;
      margin: 24px 0;
    }
    .warning-box p {
      margin: 0;
      font-size: 14px;
      color: #b71c1c;
      font-weight: 600;
    }

    /* Pied de page */
    .footer {
      background-color: #f4f4f4;
      padding: 24px 40px;
      text-align: center;
      border-top: 1px solid #e0e0e0;
    }
    .footer p {
      font-size: 12px;
      color: #888;
      margin: 4px 0;
      line-height: 1.6;
    }
    .footer a {
      color: #113B26;
      text-decoration: none;
      font-weight: 600;
    }
  </style>
</head>
<body>

  <div class='wrapper'>

    <!-- EN-TÊTE -->
    <div class='header'>
      <h1>🎓 Bienvenue sur l'ENT GSJLF</h1>
      <p>Groupe Scolaire Jean de la Fontaine — Environnement Numérique de Travail</p>
    </div>

    <!-- CORPS -->
    <div class='body'>

      <p>Bonjour <strong>" . $prenom . " " . $nom . "</strong>,</p>

      <p>Le service informatique du <strong>Groupe Scolaire Jean de la Fontaine (GSJLF)</strong> a créé votre compte sur l'ENT. Vous trouverez ci-dessous vos identifiants de connexion.</p>

      <!-- BLOC IDENTIFIANTS -->
      <div class='credentials-box'>
        <div class='cred-title'>🔐 Vos identifiants de connexion</div>
        <div class='cred-row'>
          <span class='cred-label'>Adresse e-mail</span>
          <span class='cred-value'>$email</span>
        </div>
        <div class='cred-row'>
          <span class='cred-label'>Mot de passe provisoire</span>
          <span class='cred-value password'>$motDePasse</span>
        </div>
      </div>

      <!-- BOUTON CONNEXION -->
      <div class='cta-wrap'>
        <a href='$lienConnexion' class='cta-btn'>Se connecter à l'ENT</a>
      </div>

      <!-- ALERTE CHANGEMENT MDP -->
      <div class='alert-box'>
        <p>⚠️ <strong>Important :</strong> ce mot de passe est provisoire. Vous serez invité à le modifier dès votre première connexion. Choisissez un mot de passe personnel et sécurisé.</p>
      </div>

      <!-- ÉTAPES PREMIÈRE CONNEXION -->
      <div class='steps-box'>
        <p class='steps-title'>📋 Comment accéder à votre espace :</p>
        <div class='step'>
          <div class='step-num'>1</div>
          <p>Cliquez sur le bouton <strong>\"Se connecter à l'ENT\"</strong> ci-dessus ou copiez le lien dans votre navigateur.</p>
        </div>
        <div class='step'>
          <div class='step-num'>2</div>
          <p>Saisissez votre <strong>adresse e-mail institutionnelle</strong> et le <strong>mot de passe provisoire</strong> indiqués dans cet email.</p>
        </div>
        <div class='step'>
          <div class='step-num'>3</div>
          <p>Définissez un <strong>nouveau mot de passe personnel</strong> lors de votre première connexion. Il doit contenir au moins 8 caractères, une majuscule, un chiffre et un symbole.</p>
        </div>
        <div class='step'>
          <div class='step-num'>4</div>
          <p>Accédez à votre <strong>espace ENT</strong> et découvrez toutes vos applications disponibles.</p>
        </div>
      </div>

      <!-- AVERTISSEMENT SÉCURITÉ -->
      <div class='warning-box'>
        <p>🔒 Ne partagez jamais vos identifiants avec quiconque. Si vous n'êtes pas à l'origine de cette création de compte, contactez immédiatement le service informatique.</p>
      </div>

      <p>Cordialement,<br>
      <strong>Le service informatique — CRIAT</strong><br>
      Groupe Scolaire Jean de la Fontaine</p>

    </div>

    <!-- PIED DE PAGE -->
    <div class='footer'>
      <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
      <p>Pour toute assistance : <a href='mailto:criat@uahb.sn'>criat@uahb.sn</a></p>
      <p>© 2026 Groupe Scolaire Jean de la Fontaine</p>
    </div>

  </div>

</body>
</html>";
                                        // Envoyer l'e-mail
                                        $emailSent = $adminController->sendEmail($email, $prenom, "Activez votre compte maintenant !", $message);

                                        if (!$emailSent) {
                                            if ($bdP->inTransaction()) {
                                                $bdP->rollBack();
                                            }
                                            echo "erreurMail";
                                            die;
                                        } else {

                                            $bdP->commit();
                                            echo "succès";
                                            die;

                                        }

                                    } else {
                                        if ($bdP->inTransaction()) {
                                            $bdP->rollBack();
                                        }
                                        echo "erreur";
                                        die;
                                    }


                                } else {
                                    if ($bdP->inTransaction()) {
                                        $bdP->rollBack();
                                    }

                                    echo "erreur";
                                    die;
                                }


                            } else {
                                if ($bdP->inTransaction()) {
                                    $bdP->rollBack();
                                }

                                echo "finContrat";
                                die;

                            }


                        } else {
                            if ($bdP->inTransaction()) {
                                $bdP->rollBack();
                            }
                            echo "pasContrat";
                            die;
                        }


                    } else {
                        if ($bdP->inTransaction()) {
                            $bdP->rollBack();
                        }
                        echo "matriculeExistsPas";
                        die;

                    }

                } else {
                    if ($bdP->inTransaction()) {
                        $bdP->rollBack();
                    }
                    echo "dejaCompte";
                    die;

                }

            } catch (Exception $e) {
                if ($bdP->inTransaction()) {
                    $bdP->rollBack();
                }
                echo "erreur";
                die;
            }


        } else {
            echo "champsObligatoire";
            die;
        }
        break;


    // =========================================================================
    //  case 7 — Liste des sous-menus
    // =========================================================================
    case 7 :

        try {
            $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


            $data =
                [
                    'statut' => 0
                ];

            $sql = "SELECT
    sous_menu.id,
    sous_menu.nom as nom_s,
    sous_menu.dateEnregistrement,
    i.icon,
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM tache t
            WHERE t.idSousMenu = sous_menu.id
              AND t.active = 1
        )
        THEN 1
        ELSE 0
    END AS tmp
FROM sous_menu
JOIN icons i
    ON i.id = sous_menu.idIcon
WHERE sous_menu.statut = :statut;";
            $stmt = $bdP->prepare($sql);
            $stmt->execute($data);
            $results = $stmt->fetchAll(PDO::FETCH_OBJ);

            echo json_encode($results);
            die;

        }catch (Exception $e) {
            echo json_encode(array());
            die;
        }

        break;


    // =========================================================================
    //  case 8 — Liste des icônes (dropdown)
    // =========================================================================
    case 8:

        try {
            $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


            $sql = "SELECT * FROM icons ORDER BY icons.id ASC";
            $stmt = $bdP->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_OBJ);

            $boxIcons = NULL;

            if(count($results) > 0){
                $boxIcons = 'icon';

                foreach ($results as $result) {


                    $boxIcons .= '<li>
                                        <div class="dropdown-item btn btn-outline-primary" onclick="selectIcon('.$result->id.',this)">
                                            '.$result->icon.'
                                        </div>
                                    </li>';


                }

                echo $boxIcons;
                die;
            }else
            {
                echo $boxIcons;
                die;

            }

        }catch (Exception $e) {
            echo "erreur".$e;
            die;
        }

        break;


    // =========================================================================
    //  case 9 — Détail d'un sous-menu
    // =========================================================================
    case 9 :


        if(!empty($_POST['id']))
        {

            $id = $_POST['id'];
            try {
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $data =
                    [
                        'id' => $id,
                        'statut' => 0
                    ];

                $sql = "SELECT
    sous_menu.id,
    sous_menu.nom as nom,
    sous_menu.dateEnregistrement,
    i.id as idIcon,
    i.icon,
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM tache t
            WHERE t.idSousMenu = sous_menu.id
              AND t.active = 1
        )
        THEN 1
        ELSE 0
    END AS tmp
FROM sous_menu
JOIN icons i
    ON i.id = sous_menu.idIcon
WHERE sous_menu.id = :id AND sous_menu.statut = :statut;";
                $stmt = $bdP->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if($result)
                {
                    echo json_encode($result);
                    die;

                }else
                {
                    echo "erreur";
                    die;
                }


            }catch (Exception $e) {
                echo "erreur".$e;
                die;
            }

        }else
        {
            echo "erreur";
            die;
        }

        break;


    // =========================================================================
    //  case 10 — Ajout d'un sous-menu
    // =========================================================================
    case 10 :

        date_default_timezone_set('Africa/Dakar');

        $idUtilisateur = 1;
//            $idUtilisateur = null;
//            if(!empty($_SESSION['tmpIdP']))
//            {
//                $idUtilisateur = $_SESSION['tmpIdP'];
//            }else
//            {
//                echo "sessionExpired";
//                die;
//            }

        if(!empty($_POST['nom']) && !empty($_POST['idIcon']))
        {


            $nom = $_POST['nom'];
            $idIcon = $_POST['idIcon'];
            $dateEnregistrement = date('Y-m-d H:i:s');

            try {
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bdP->beginTransaction();

                $data =
                    [
                        'nom' => $nom,
                        'statut' => 0
                    ];

                $sql = "SELECT * FROM sous_menu WHERE nom= :nom AND statut = :statut;";
                $stmt = $bdP->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetchAll(PDO::FETCH_OBJ);

                if(count($result) == 0)
                {

                    $data_insert = [
                        'nom' => $nom,
                        'idIcon' => $idIcon,
                        'idUtilisateur' => $idUtilisateur,
                        'statut' => 0,
                        'dateEnregistrement' => $dateEnregistrement

                    ];
                    $sql_insert = "INSERT INTO sous_menu(nom, idIcon,idUtilisateur,statut,dateEnregistrement) VALUES (:nom, :idIcon,:idUtilisateur,:statut,:dateEnregistrement)";
                    $stmt_insert = $bdP->prepare($sql_insert);
                    $tmp_insert = $stmt_insert->execute($data_insert);

                    if($tmp_insert)
                    {
                        $idSousMenu = $bdP->lastInsertId();

                        $data_insert_his = [
                            'idSousMenu' => $idSousMenu,
                            'nom' => $nom,
                            'idIcon' => $idIcon,
                            'idUtilisateur' => $idUtilisateur,
                            'statut' => 0,
                            'dateEnregistrement' => $dateEnregistrement
                        ];
                        $sql_insert_his = "INSERT INTO sous_menu_historiques(idSousMenu,nom,idIcon,idUtilisateur,statut,dateEnregistrement) VALUES (:idSousMenu,:nom, :idIcon,:idUtilisateur,:statut,:dateEnregistrement)";
                        $stmt_insert_his = $bdP->prepare($sql_insert_his);
                        $tmp_insert_his = $stmt_insert_his->execute($data_insert_his);

                        if($tmp_insert_his)
                        {
                            if ($bdP->inTransaction()) {
                                $bdP->commit();
                            }
                            echo "succès";
                            die;

                        }else
                        {
                            if ($bdP->inTransaction()) {
                                $bdP->rollBack();
                            }
                            echo "erreur";
                            die;
                        }

                    }else
                    {

                        if ($bdP->inTransaction()) {
                            $bdP->rollBack();
                        }
                        echo "erreur";
                        die;
                    }


                }else
                {

                    if ($bdP->inTransaction()) {
                        $bdP->rollBack();
                    }
                    echo "nomExiste";
                    die;
                }


            }catch (Exception $e) {

                if ($bdP->inTransaction()) {
                    $bdP->rollBack();
                }
                echo "erreur";
                die;
            }

        }else
        {


            echo "erreur";
            die;
        }

        break;


    // =========================================================================
    //  case 11 — Suppression logique d'un sous-menu
    // =========================================================================
    case 11 :


        date_default_timezone_set('Africa/Dakar');

        $idUtilisateur = 1;
//            $idUtilisateur = null;
//            if(!empty($_SESSION['tmpIdP']))
//            {
//                $idUtilisateur = $_SESSION['tmpIdP'];
//            }else
//            {
//                echo "sessionExpired";
//                die;
//            }

        if(!empty($_POST['id']))
        {


            $id = $_POST['id'];
            $dateEnregistrement = date('Y-m-d H:i:s');

            try {
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bdP->beginTransaction();

                $data =
                    [
                        'id' => $id,
                        'statut' => 0
                    ];

                $sql = "SELECT * FROM sous_menu WHERE id= :id AND statut = :statut;";
                $stmt = $bdP->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if($result)
                {


                    $data_up_sm = [
                        'statut1' => 1,
                        'statut2' => 0,
                        'id' => $id
                    ];

                    $sql_up_sm = "UPDATE sous_menu
                SET statut = :statut1
                WHERE id = :id AND statut = :statut2;";

                    $stmt_up_sm = $bdP->prepare($sql_up_sm);

                    $tmpStmt_up_sm = $stmt_up_sm->execute($data_up_sm);

                    if($tmpStmt_up_sm)
                    {

                        if ($stmt_up_sm->rowCount() > 0)
                        {
                            $data_insert_his = [
                                'idSousMenu' => $result->id,
                                'nom' => $result->nom,
                                'idIcon' => $result->idIcon,
                                'idUtilisateur' => $idUtilisateur,
                                'statut' => 1,
                                'dateEnregistrement' => $dateEnregistrement
                            ];
                            $sql_insert_his = "INSERT INTO sous_menu_historiques(idSousMenu,nom,idIcon,idUtilisateur,statut,dateEnregistrement) VALUES (:idSousMenu,:nom, :idIcon,:idUtilisateur,:statut,:dateEnregistrement)";
                            $stmt_insert_his = $bdP->prepare($sql_insert_his);
                            $tmp_insert_his = $stmt_insert_his->execute($data_insert_his);

                            if($tmp_insert_his)
                            {
                                if ($bdP->inTransaction()) {
                                    $bdP->commit();
                                }
                                echo "succès";
                                die;

                            }else
                            {
                                if ($bdP->inTransaction()) {
                                    $bdP->rollBack();
                                }
                                echo "erreur4";
                                die;
                            }

                        }else
                        {
                            if ($bdP->inTransaction()) {
                                $bdP->rollBack();
                            }
                            echo "erreur3";
                            die;
                        }


                    }else
                    {

                        if ($bdP->inTransaction()) {
                            $bdP->rollBack();
                        }
                        echo "erreur2";
                        die;
                    }


                }else
                {

                    if ($bdP->inTransaction()) {
                        $bdP->rollBack();
                    }
                    echo "erreur1";
                    die;
                }


            }catch (Exception $e) {

                if ($bdP->inTransaction()) {
                    $bdP->rollBack();
                }
                echo "erreur".$e;
                die;
            }

        }else
        {


            echo "erreur1";
            die;
        }

        break;


    // =========================================================================
    //  case 12 — Modification d'un sous-menu
    // =========================================================================
    case 12 :


        date_default_timezone_set('Africa/Dakar');

        $idUtilisateur = 1;
//            $idUtilisateur = null;
//            if(!empty($_SESSION['tmpIdP']))
//            {
//                $idUtilisateur = $_SESSION['tmpIdP'];
//            }else
//            {
//                echo "sessionExpired";
//                die;
//            }

        if(!empty($_POST['id']) && !empty($_POST['nom']) && !empty($_POST['idIcon']))
        {


            $id = $_POST['id'];
            $nom = $_POST['nom'];
            $idIcon = $_POST['idIcon'];
            $dateEnregistrement = date('Y-m-d H:i:s');

            try {
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bdP->beginTransaction();

                $data =
                    [
                        'id' => $id,
                        'statut' => 0
                    ];

                $sql = "SELECT * FROM sous_menu WHERE id= :id AND statut = :statut;";
                $stmt = $bdP->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if($result)
                {

                    if($result->nom == $nom)
                    {
                        echo "nomExiste";
                        die;
                    }


                    $data_up_sm = [
                        'nom' => $nom,
                        'idIcon' => $idIcon,
                        'statut' => 0,
                        'id' => $id
                    ];

                    $sql_up_sm = "UPDATE sous_menu
                SET  nom=:nom, idIcon=:idIcon
                WHERE id = :id AND statut = :statut;";

                    $stmt_up_sm = $bdP->prepare($sql_up_sm);

                    $tmpStmt_up_sm = $stmt_up_sm->execute($data_up_sm);

                    if($tmpStmt_up_sm)
                    {

                        if ($stmt_up_sm->rowCount() > 0)
                        {
                            $data_insert_his = [
                                'idSousMenu' => $result->id,
                                'nom' => $nom,
                                'idIcon' => $idIcon,
                                'idUtilisateur' => $idUtilisateur,
                                'statut' => 0,
                                'dateEnregistrement' => $dateEnregistrement
                            ];
                            $sql_insert_his = "INSERT INTO sous_menu_historiques(idSousMenu,nom,idIcon,idUtilisateur,statut,dateEnregistrement) VALUES (:idSousMenu,:nom, :idIcon,:idUtilisateur,:statut,:dateEnregistrement)";
                            $stmt_insert_his = $bdP->prepare($sql_insert_his);
                            $tmp_insert_his = $stmt_insert_his->execute($data_insert_his);

                            if($tmp_insert_his)
                            {
                                if ($bdP->inTransaction()) {
                                    $bdP->commit();
                                }
                                echo "succès";
                                die;

                            }else
                            {
                                if ($bdP->inTransaction()) {
                                    $bdP->rollBack();
                                }
                                echo "erreur4";
                                die;
                            }

                        }else
                        {
                            if ($bdP->inTransaction()) {
                                $bdP->rollBack();
                            }
                            echo "erreur3";
                            die;
                        }


                    }else
                    {

                        if ($bdP->inTransaction()) {
                            $bdP->rollBack();
                        }
                        echo "erreur2";
                        die;
                    }


                }else
                {

                    if ($bdP->inTransaction()) {
                        $bdP->rollBack();
                    }
                    echo "erreur1";
                    die;
                }


            }catch (Exception $e) {

                if ($bdP->inTransaction()) {
                    $bdP->rollBack();
                }
                echo "erreur".$e;
                die;
            }

        }else
        {


            echo "erreur1";
            die;
        }

        break;


    // =========================================================================
    //  case 13 — Liste des tâches visibles (DataTable)
    // =========================================================================
    case 13 :


        try {


            $sql = "SELECT
    t.id AS id,
    t.nom AS nom,
    tt.typeTache AS type,
    t.url AS url,
    t.autre_ressource AS autre_ressource,
    t.commentaire AS commentaire,
COALESCE(
        ua1.codeNiv1,
        ua2.codeNiv2,
        ua3.codeNiv3
    ) AS code,
    -- Obtenir le code UA Niv1 peu importe le niveau de rattachement
    COALESCE(
        ua1.id,
        ua1_from_niv2.id,
        ua1_from_niv3.id
    ) AS id_structure,

    COUNT(tu.idUtilisateur) AS nombre_utilisateurs,
    active

FROM tache t

JOIN typetache tt ON tt.id = t.idTypeTache

-- Lien direct
LEFT JOIN unite_administrative_niv1 ua1 ON t.idUniteAdministrativeNiv1 = ua1.id

-- Lien indirect depuis UA Niv2
LEFT JOIN unite_administrative_niv2 ua2 ON t.idUniteAdministrativeNiv2 = ua2.id
LEFT JOIN unite_administrative_niv1 ua1_from_niv2 ON ua2.idUniteAdministrativeNiv1 = ua1_from_niv2.id

-- Lien indirect depuis UA Niv3
LEFT JOIN unite_administrative_niv3 ua3 ON t.idUniteAdministrativeNiv3 = ua3.id
LEFT JOIN unite_administrative_niv2 ua2_from_niv3 ON ua3.idUniteAdministrativeNiv2 = ua2_from_niv3.id
LEFT JOIN unite_administrative_niv1 ua1_from_niv3 ON ua2_from_niv3.idUniteAdministrativeNiv1 = ua1_from_niv3.id

-- Utilisateurs liés à la tâche
LEFT JOIN tache_utilisateur tu ON t.id = tu.idTache AND tu.access = 1


WHERE t.estVisible=1

GROUP BY
    t.id,
    t.nom,
    tt.typeTache,
    t.url,
    t.autre_ressource,
    id_structure

ORDER BY t.nom ASC;";
            $stmt = $bdP->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_OBJ);


            echo json_encode($result);
            die;


        }catch (Exception $e) {

            echo json_encode(array());
            die;
        }

        break;


    // =========================================================================
    //  case 14 — Types de tâche
    // =========================================================================
    case 14 :

        try {


            $stmt = $bdP->prepare("SELECT * FROM typetache");
            $stmt->execute();
            $listes = $stmt->fetchAll(PDO::FETCH_OBJ);

            echo '<option></option><option value="" >Choisir...</option>';

            if (count($listes) > 0) {
                foreach ($listes as $tmp) {
                    echo '<option value="' . $tmp->id . '">' . $tmp->typeTache . '</option>';
                }
            } else {
                echo '<option value="">--tâche--</option>';
            }

            die;
        } catch (\Throwable $th) {
            echo "erreur";
            die;
        }
        break;


    // =========================================================================
    //  case 15 — Fonctions
    // =========================================================================
    case 15 :
        try {


            $stmt = $bdP->prepare("SELECT * FROM fonction");
            $stmt->execute();
            $listes = $stmt->fetchAll(PDO::FETCH_OBJ);

            echo '<option></option><option value="" >Choisir...</option>';

            if (count($listes) > 0) {
                foreach ($listes as $tmp) {
                    echo '<option value="' . $tmp->id . '">' . $tmp->fonction . '</option>';
                }
            } else {
                echo '<option value="">--fonction--</option>';
            }

            die;
        } catch (\Throwable $th) {
            echo "erreur";
            die;
        }
        break;


    // =========================================================================
    //  case 16 — Sous-menus
    // =========================================================================
    case 16 :
        try {


            $stmt = $bdP->prepare("SELECT * FROM sous_menu");
            $stmt->execute();
            $listes = $stmt->fetchAll(PDO::FETCH_OBJ);

            echo '<option></option><option value="" >Choisir...</option>';

            if (count($listes) > 0) {
                foreach ($listes as $tmp) {
                    echo '<option value="' . $tmp->id . '">' . $tmp->nom . '</option>';
                }
            } else {
                echo '<option value="">--Sous-menu--</option>';
            }

            die;
        } catch (\Throwable $th) {
            echo "erreur";
            die;
        }
        break;


    // =========================================================================
    //  case 17 — Icônes (options avec data-icon pour select2)
    // =========================================================================
    case 17 :

        try {

            $sql = "SELECT id, icon FROM icons ORDER BY id ASC";
            $stmt = $bdP->prepare($sql);
            $stmt->execute();
            $icons = $stmt->fetchAll(PDO::FETCH_OBJ);

            $html = '<option value="">Sélectionner une icône</option>';
            foreach ($icons as $icon) {
                // Encoder le SVG en attribut data
                $iconEncoded = htmlspecialchars($icon->icon, ENT_QUOTES, 'UTF-8');
                $html .= '<option value="' . $icon->id . '" data-icon="' . $iconEncoded . '"></option>';
            }

            echo $html;
            die;
        } catch (\Throwable $th) {
            echo "erreur";
            die;
        }
        break;


    // =========================================================================
    //  case 18 — Bases de données
    // =========================================================================
    case 18 :

        try {


            $stmt = $bdP->prepare("SELECT * FROM base_donnees");
            $stmt->execute();
            $listes = $stmt->fetchAll(PDO::FETCH_OBJ);

            echo '<option></option><option value="">Choisir...</option>';

            if (count($listes) > 0) {
                foreach ($listes as $tmp) {
                    echo '<option value="' . $tmp->id . '">' . $tmp->nom . '</option>';
                }
            } else {
                echo '<option value="">-- Base de données --</option>';
            }

            die;
        } catch (\Throwable $th) {
            echo "erreur";
            die;
        }
        break;


    // =========================================================================
    //  case 19 — Ajout d'une tâche (visible OU associée / invisible)
    //
    //  Corrigé : INSERT tache 18 colonnes / 18 placeholders (15 auparavant)
    //            INSERT historiqueTache 21 / 21 (18 auparavant)
    //            NULLIF(?, NULL) retiré (sans effet : une comparaison avec NULL
    //            n'est jamais vraie, la fonction renvoyait toujours sa valeur)
    //
    //  Mode association : estVisible = 0, estVisibleId = id de la tâche visible
    //  parente. L'URL du parent est relue en base, jamais reprise du POST.
    // =========================================================================
    case 19 :

        date_default_timezone_set('Africa/Dakar');

        $idUtilisateur = 1;
//        $idUtilisateur = null;
//        if (!empty($_SESSION['tmpIdP'])) {
//            $idUtilisateur = $_SESSION['tmpIdP'];
//        } else {
//            echo "sessionExpired";
//            die;
//        }

        // ── Récupération des données POST ───────────────────────────
        $nom             = trim($_POST['nom']          ?? '');
        $idTypeTache     = $_POST['idTypeTache']       ?? null;
        $url             = trim($_POST['url']          ?? '');
        $idSousMenu      = $_POST['idSousMenu']        ?? null;
        $idIcon          = $_POST['idIcon']            ?? null;
        $autre_ressource = $_POST['autre_ressource']   ?? null;
        $commentaire     = $_POST['commentaire']       ?? null;
        $id_UA           = $_POST['idUA']              ?? null;
        $niveau_UA       = $_POST['nivUA']             ?? null;
        $id_fonction     = $_POST['id_fonction']       ?? null;
        $idAppli         = $_POST['idAppli']           ?? null;
        // Le select du formulaire d'ajout porte name="idBD" (et non "idDB") :
        // en ne lisant que $_POST['idDB'], idDB était systématiquement NULL.
        $idDB            = $_POST['idBD'] ?? ($_POST['idDB'] ?? null);
        $dateEnregistrement = date('Y-m-d H:i:s');

        // ── Mode association (tâche invisible) ──────────────────────
        $estVisible    = isset($_POST['estVisible']) && $_POST['estVisible'] !== ''
            ? (int) $_POST['estVisible'] : 1;
        $estVisibleId  = !empty($_POST['estVisibleId']) ? (int) $_POST['estVisibleId'] : null;
        $estVisibleUrl = null;

        // ── Nettoyage des valeurs vides ──────────────────────────────
        if (empty($idSousMenu)  || $idSousMenu  == '0') $idSousMenu  = null;
        if (empty($idIcon)      || $idIcon      == '0') $idIcon      = null;
        if (empty($id_fonction) || $id_fonction == '0') $id_fonction = null;
        if (empty($idDB)        || $idDB        == '0') $idDB        = null;
        if (empty($idAppli)     || $idAppli     == '0') $idAppli     = null;
        if (empty($id_UA)       || $id_UA       == '0') $id_UA       = null;

        // ── Initialisation des niveaux UA ───────────────────────────
        $idNiv1 = null;
        $idNiv2 = null;
        $idNiv3 = null;

        if ($niveau_UA == 1 && $id_UA) {
            $idNiv1 = $id_UA;
        } elseif ($niveau_UA == 2 && $id_UA) {
            $idNiv2 = $id_UA;
        } elseif ($niveau_UA == 3 && $id_UA) {
            $idNiv3 = $id_UA;
        }

        try {
            $bdP->beginTransaction();



            // recuperer dernier tache
            $stmtTD = $bdP->prepare("
    SELECT ordre
    FROM tache
    WHERE estVisible = 1
    ORDER BY id DESC
    LIMIT 1
");

            $stmtTD->execute();

            $td = $stmtTD->fetch(PDO::FETCH_ASSOC);

            if ($td) {
                $ordre = 10 + (int) $td['ordre'];
            } else {
                $ordre = 10;
            }




            // ── Validation du mode association ──────────────────────
            if ($estVisible === 0) {

                if (!$estVisibleId) {
                    $bdP->rollBack();
                    echo "parentManquant";
                    die;
                }

                $stmtParent = $bdP->prepare("SELECT url FROM tache WHERE id = ? AND estVisible = 1");
                $stmtParent->execute([$estVisibleId]);
                $urlParent = $stmtParent->fetchColumn();

                if ($urlParent === false) {
                    $bdP->rollBack();
                    echo "parentIntrouvable";
                    die;
                }

                $estVisibleUrl = $urlParent;

            } else {
                // Toute autre valeur retombe sur une tâche visible normale.
                $estVisible    = 1;
                $estVisibleId  = null;
                $estVisibleUrl = null;
            }



            // ── Vérifier doublon nom + type + UA ────────────────────
            $whereUA  = '';
            $paramsUA = [];

            if ($niveau_UA == 1 && $idNiv1) {
                $whereUA  = 'idUniteAdministrativeNiv1 = ?';
                $paramsUA[] = $idNiv1;
            } elseif ($niveau_UA == 2 && $idNiv2) {
                $whereUA  = 'idUniteAdministrativeNiv2 = ?';
                $paramsUA[] = $idNiv2;
            } elseif ($niveau_UA == 3 && $idNiv3) {
                $whereUA  = 'idUniteAdministrativeNiv3 = ?';
                $paramsUA[] = $idNiv3;
            }

            if ($whereUA) {
                $stmtCheckNom = $bdP->prepare("SELECT COUNT(*) FROM tache WHERE nom = ? AND idTypeTache = ? AND $whereUA");
                $stmtCheckNom->execute(array_merge([$nom, $idTypeTache], $paramsUA));
                if ($stmtCheckNom->fetchColumn() > 0) {
                    $bdP->rollBack();
                    echo "tacheExisteUnite";
                    die;
                }
            }

            // ── Si fonction → récupérer ses UA ──────────────────────
            if (!empty($id_fonction)) {
                $stmtUA = $bdP->prepare("
            SELECT idUniteAdministrativeNiv1, idUniteAdministrativeNiv2, idUniteAdministrativeNiv3
            FROM fonction
            WHERE id = ?
        ");
                $stmtUA->execute([$id_fonction]);
                $ua = $stmtUA->fetch(PDO::FETCH_ASSOC);

                if ($ua) {
                    $idNiv1 = null;
                    $idNiv2 = null;
                    $idNiv3 = null;

                    if (!empty($ua['idUniteAdministrativeNiv1'])) {
                        $idNiv1 = $ua['idUniteAdministrativeNiv1'];
                    } elseif (!empty($ua['idUniteAdministrativeNiv2'])) {
                        $idNiv2 = $ua['idUniteAdministrativeNiv2'];
                    } elseif (!empty($ua['idUniteAdministrativeNiv3'])) {
                        $idNiv3 = $ua['idUniteAdministrativeNiv3'];
                    }
                }
            }


            // ── Vérifier si l'URL existe déjà ───────────────────────
            $stmtCheck = $bdP->prepare("SELECT COUNT(*) FROM tache WHERE url = ? AND  idUniteAdministrativeNiv1 = ? AND idUniteAdministrativeNiv2  = ? AND idUniteAdministrativeNiv3  = ? AND idFonction = ?");
            $stmtCheck->execute([$url,$idNiv1,$idNiv2,$idNiv3,$id_fonction]);
            if ($stmtCheck->fetchColumn() > 0) {
                $bdP->rollBack();
                echo "existeTache";
                die;
            }

            // ── Insertion tache — 18 colonnes / 18 placeholders ─────
            $stmtInsert = $bdP->prepare("
        INSERT INTO tache (
            nom, idTypeTache, url, idSousMenu, idIcon,
            autre_ressource, commentaire,
            idUniteAdministrativeNiv1, idUniteAdministrativeNiv2, idUniteAdministrativeNiv3,
            dateEnregistrement, idFonction, createdBy, idDB, idAppli,
            estVisible, estVisibleId, estVisibleUrl,ordre
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?
        )
    ");

            $stmtInsert->execute([
                $nom, $idTypeTache, $url, $idSousMenu, $idIcon,
                $autre_ressource, $commentaire,
                $idNiv1, $idNiv2, $idNiv3,
                $dateEnregistrement, $id_fonction, $idUtilisateur, $idDB, $idAppli,
                $estVisible, $estVisibleId, $estVisibleUrl,$ordre
            ]);

            $idTache = $bdP->lastInsertId();

            // ── Insertion historique — 21 colonnes / 21 placeholders ─
            $stmtHist = $bdP->prepare("
        INSERT INTO historiqueTache (
            idUtilisateur, idTache, nom, autre_ressource, url,
            idTypeTache, commentaire, idSousMenu, idIcon,
            idUniteAdministrativeNiv1, idUniteAdministrativeNiv2, idUniteAdministrativeNiv3,
            dateEnregistrement, active, idFonction, createdBy, idDB, idAppli,
            estVisible, estVisibleId, estVisibleUrl,ordre
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?,?
        )
    ");

            $stmtHist->execute([
                $idUtilisateur, $idTache, $nom, $autre_ressource, $url,
                $idTypeTache, $commentaire, $idSousMenu, $idIcon,
                $idNiv1, $idNiv2, $idNiv3,
                $dateEnregistrement, 1, $id_fonction, $idUtilisateur, $idDB, $idAppli,
                $estVisible, $estVisibleId, $estVisibleUrl,$ordre
            ]);

            $bdP->commit();
            echo "succès";
            die;

        } catch (PDOException $e) {
            if ($bdP->inTransaction()) {
                $bdP->rollBack();
            }
            error_log("Erreur add_tache: " . $e->getMessage());
            echo "erreur".$e;
            die;
        }

        break;


    // =========================================================================
    //  case 20 — Unités administratives d'un niveau
    //  Le niveau est casté et borné à 1-3 : il est concaténé dans le nom de
    //  table, un paramètre PDO ne peut pas le protéger.
    // =========================================================================
    case 20 :


        try {
            $niveau = isset($_POST['nivUA']) ? (int) $_POST['nivUA'] : 0;

            if ($niveau < 1 || $niveau > 3) {
                echo '<option value="">--tâche--</option>';
                die;
            }

            $stmt = $bdP->prepare('SELECT id,niveau'.$niveau.' as niveau FROM unite_administrative_niv' . $niveau);
            $stmt->execute();
            $listes = $stmt->fetchAll(PDO::FETCH_OBJ);


            echo '<option></option><option value="" >Choisir...</option>';

            if (count($listes) > 0) {
                foreach ($listes as $tmp) {
                    echo '<option value="' . $tmp->id . '">' . $tmp->niveau . '</option>';
                }
            } else {
                echo '<option value="">--tâche--</option>';
            }

            die;

        }catch (PDOException $e) {
            echo json_encode(array());
            die;
        }

        break;


    // =========================================================================
    //  case 21 — Détail d'une tâche
    // =========================================================================
    case 21 :


        $id_tache = $_POST['id'];

        $stmt = $bdP->prepare('SELECT t.id as id, t.nom as nom,t.idFonction, tt.typeTache as type,tt.id as idTypeTache
    , t.url as url, t.autre_ressource as autre_ressource, t.idSousMenu as idSousMenu, t.idIcon as idIcon, sm.nom as nom_sous_menu, i.icon as icon
        , t.commentaire as commentaire, t.idUniteAdministrativeNiv1 as idUniteAdministrativeNiv1, t.idUniteAdministrativeNiv2 as idUniteAdministrativeNiv2, t.idUniteAdministrativeNiv3 as idUniteAdministrativeNiv3,
        q.qualification as qualification, q.id as idQualification, t.idDB as idDB
     FROM tache t
LEFT JOIN typetache tt ON tt.id = t.idTypeTache
LEFT JOIN sous_menu sm ON t.idSousMenu = sm.id
LEFT JOIN icons i ON t.idIcon = i.id
LEFT JOIN tache_qualification tq ON t.id = tq.idTache
LEFT JOIN qualifications q ON q.id = tq.idQualification
    WHERE t.id = ?');
        $stmt->execute([$id_tache]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($result);
        die;

        break;


    // =========================================================================
    //  case 22 — Modification d'une tâche
    // =========================================================================
    case 22 :

        date_default_timezone_set('Africa/Dakar');


        $idUtilisateur = 1;
//        $idUtilisateur = null;
//        if (!empty($_SESSION['tmpIdP'])) {
//            $idUtilisateur = $_SESSION['tmpIdP'];
//        } else {
//            echo "sessionExpired";
//            die;
//        }

        // ── Récupération POST ────────────────────────────────────
        $id              = $_POST['id']              ?? null;
        $nom             = trim($_POST['nom']         ?? '');
        $idTypeTache     = $_POST['idTypeTache']      ?? null;
        $url             = trim($_POST['url']         ?? '');
        $idSousMenu      = $_POST['idSousMenu']       ?? null;
        $idIcon          = $_POST['idIcon']           ?? null;
        $autre_ressource = $_POST['autre_ressource']  ?? null;
        $commentaire     = $_POST['commentaire']      ?? null;
        $id_UA           = $_POST['idUA']             ?? null;
        $niveau_UA       = $_POST['nivUA']            ?? null;
        $id_fonction     = $_POST['id_fonction']      ?? null;
        $idAppli         = $_POST['idDB']             ?? null;
        $idDB            = $_POST['idBD']             ?? null;
        $dateModification = date('Y-m-d H:i:s');

        // ── Nettoyage ────────────────────────────────────────────
        if (empty($idSousMenu)  || $idSousMenu  == '0') $idSousMenu  = null;
        if (empty($idIcon)      || $idIcon      == '0') $idIcon      = null;
        if (empty($id_fonction) || $id_fonction == '0') $id_fonction = null;
        if (empty($idDB)        || $idDB        == '0') $idDB        = null;
        if (empty($idAppli)     || $idAppli     == '0') $idAppli     = null;
        if (empty($id_UA)       || $id_UA       == '0') $id_UA       = null;

        // ── Niveaux UA ───────────────────────────────────────────
        $idNiv1 = null;
        $idNiv2 = null;
        $idNiv3 = null;

        if ($niveau_UA == 1 && $id_UA) {
            $idNiv1 = $id_UA;
        } elseif ($niveau_UA == 2 && $id_UA) {
            $idNiv2 = $id_UA;
        } elseif ($niveau_UA == 3 && $id_UA) {
            $idNiv3 = $id_UA;
        }

        try {
            $bdP->beginTransaction();

            // ── Vérifier URL existante (hors tâche courante) ─────
            $stmtCheckUrl = $bdP->prepare("SELECT COUNT(*) FROM tache WHERE url = ? AND id != ?");
            $stmtCheckUrl->execute([$url, $id]);
            if ($stmtCheckUrl->fetchColumn() > 0) {
                $bdP->rollBack();
                echo "existeTache";
                die;
            }

            // ── Vérifier doublon nom + type + UA (hors tâche courante) ──
            $whereUA  = '';
            $paramsUA = [];

            if ($niveau_UA == 1 && $idNiv1) {
                $whereUA    = 'idUniteAdministrativeNiv1 = ?';
                $paramsUA[] = $idNiv1;
            } elseif ($niveau_UA == 2 && $idNiv2) {
                $whereUA    = 'idUniteAdministrativeNiv2 = ?';
                $paramsUA[] = $idNiv2;
            } elseif ($niveau_UA == 3 && $idNiv3) {
                $whereUA    = 'idUniteAdministrativeNiv3 = ?';
                $paramsUA[] = $idNiv3;
            }

            if ($whereUA) {
                $stmtCheckNom = $bdP->prepare("SELECT COUNT(*) FROM tache WHERE nom = ? AND idTypeTache = ? AND $whereUA AND id != ?");
                $stmtCheckNom->execute(array_merge([$nom, $idTypeTache], $paramsUA, [$id]));
                if ($stmtCheckNom->fetchColumn() > 0) {
                    $bdP->rollBack();
                    echo "tacheExisteUnite";
                    die;
                }
            }

            // ── Si fonction → récupérer ses UA ──────────────────
            if (!empty($id_fonction)) {
                $stmtUA = $bdP->prepare("
                SELECT idUniteAdministrativeNiv1, idUniteAdministrativeNiv2, idUniteAdministrativeNiv3
                FROM fonction WHERE id = ?
            ");
                $stmtUA->execute([$id_fonction]);
                $ua = $stmtUA->fetch(PDO::FETCH_ASSOC);

                if ($ua) {
                    $idNiv1 = null;
                    $idNiv2 = null;
                    $idNiv3 = null;
                    if (!empty($ua['idUniteAdministrativeNiv1'])) {
                        $idNiv1 = $ua['idUniteAdministrativeNiv1'];
                    } elseif (!empty($ua['idUniteAdministrativeNiv2'])) {
                        $idNiv2 = $ua['idUniteAdministrativeNiv2'];
                    } elseif (!empty($ua['idUniteAdministrativeNiv3'])) {
                        $idNiv3 = $ua['idUniteAdministrativeNiv3'];
                    }
                }
            }

            // ── Update tache ─────────────────────────────────────
            $stmtUpdate = $bdP->prepare("
            UPDATE tache SET
                nom                        = ?,
                idTypeTache                = ?,
                url                        = ?,
                idSousMenu                 = ?,
                idIcon                     = ?,
                autre_ressource            = ?,
                commentaire                = ?,
                idUniteAdministrativeNiv1  = ?,
                idUniteAdministrativeNiv2  = ?,
                idUniteAdministrativeNiv3  = ?,
                idFonction                 = ?,
                idDB                       = ?,
                idAppli                    = ?,
                lastDateModification       = ?
            WHERE id = ?
        ");

            $stmtUpdate->execute([
                $nom, $idTypeTache, $url, $idSousMenu, $idIcon,
                $autre_ressource, $commentaire,
                $idNiv1, $idNiv2, $idNiv3,
                $id_fonction, $idDB, $idAppli,
                $dateModification,
                $id
            ]);

            // ── Insertion historique ─────────────────────────────
            $stmtHist = $bdP->prepare("
            INSERT INTO historiqueTache (
                idUtilisateur, idTache, nom, autre_ressource, url,
                idTypeTache, commentaire, idSousMenu, idIcon,
                idUniteAdministrativeNiv1, idUniteAdministrativeNiv2, idUniteAdministrativeNiv3,
                dateEnregistrement, active, idFonction, createdBy, idDB, idAppli
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?, ?
            )
        ");

            $stmtHist->execute([
                $idUtilisateur, $id, $nom, $autre_ressource, $url,
                $idTypeTache, $commentaire, $idSousMenu, $idIcon,
                $idNiv1, $idNiv2, $idNiv3,
                $dateModification, 1, $id_fonction, $idUtilisateur, $idDB, $idAppli
            ]);

            $bdP->commit();
            echo "succès";
            die;

        } catch (PDOException $e) {
            if ($bdP->inTransaction()) {
                $bdP->rollBack();
            }
            error_log("Erreur update_tache: " . $e->getMessage());
            echo "erreur".$e;
            die;
        }

        break;


    // =========================================================================
    //  case 23 — Applications
    // =========================================================================
    case 23 :

        try {


            $data = [
                'statut' => 1
            ];
            $stmt = $bdP->prepare("SELECT * FROM listeApplications WHERE statut = :statut");
            $stmt->execute($data);
            $listes = $stmt->fetchAll(PDO::FETCH_OBJ);

            echo '<option></option><option value="">Choisir...</option>';

            if (count($listes) > 0) {
                foreach ($listes as $tmp) {
                    echo '<option value="' . $tmp->id . '">' . $tmp->nomApplication . '</option>';
                }
            } else {
                echo '<option value="">-- Applications --</option>';
            }

            die;
        } catch (\Throwable $th) {
            echo "erreur";
            die;
        }
        break;


    // =========================================================================
    //  case 24 — Activer / désactiver une tâche
    // =========================================================================
    case 24:

        date_default_timezone_set('Africa/Dakar');


        $idUtilisateur = 1;
//        $idUtilisateur = null;
//        if (!empty($_SESSION['tmpIdP'])) {
//            $idUtilisateur = $_SESSION['tmpIdP'];
//        } else {
//            echo "sessionExpired";
//            die;
//        }

        $id_tache         = $_POST['id']   ?? null;
        $dateModification = date('Y-m-d H:i:s');

        if (!$id_tache) {
            echo "erreur";
            die;
        }

        try {
            $bdP->beginTransaction();

            // Vérifier l'état actuel
            $stmtEtat = $bdP->prepare("SELECT active FROM tache WHERE id = ?");
            $stmtEtat->execute([$id_tache]);
            $etat = $stmtEtat->fetchColumn();

            if ($etat === false) {
                $bdP->rollBack();
                echo "erreur";
                die;
            }

            // Inverser l'état
            $nouvelEtat = ($etat == 1) ? 0 : 1;

            // Mettre à jour
            $stmtUpdate = $bdP->prepare("
            UPDATE tache
            SET active = ?, lastDateModification = ?
            WHERE id = ?
        ");
            $stmtUpdate->execute([$nouvelEtat, $dateModification, $id_tache]);

            // Récupérer les infos pour l'historique
            $stmtTache = $bdP->prepare("SELECT * FROM tache WHERE id = ?");
            $stmtTache->execute([$id_tache]);
            $tache = $stmtTache->fetch(PDO::FETCH_ASSOC);

            if (!$tache) {
                $bdP->rollBack();
                echo "erreur";
                die;
            }

            // Insertion historique
            $stmtHist = $bdP->prepare("
            INSERT INTO historiqueTache (
                idUtilisateur, idTache, nom, autre_ressource, url,
                idTypeTache, commentaire, idSousMenu, idIcon,
                idUniteAdministrativeNiv1, idUniteAdministrativeNiv2, idUniteAdministrativeNiv3,
                dateEnregistrement, active, idFonction, createdBy
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?
            )
        ");

            $stmtHist->execute([
                $idUtilisateur,
                $id_tache,
                $tache['nom']                        ?? null,
                $tache['autre_ressource']             ?? null,
                $tache['url']                         ?? null,
                $tache['idTypeTache']                 ?? null,
                $tache['commentaire']                 ?? null,
                $tache['idSousMenu']                  ?? null,
                $tache['idIcon']                      ?? null,
                $tache['idUniteAdministrativeNiv1']   ?? null,
                $tache['idUniteAdministrativeNiv2']   ?? null,
                $tache['idUniteAdministrativeNiv3']   ?? null,
                $dateModification,
                $nouvelEtat,
                $tache['idFonction']                  ?? null,
                $tache['createdBy']                   ?? null
            ]);

            $bdP->commit();
            echo "succès";
            die;

        } catch (Exception $e) {
            if ($bdP->inTransaction()) {
                $bdP->rollBack();
            }
            error_log("Erreur changeEtatTache: " . $e->getMessage());
            echo "erreur";
            die;
        }

        break;


    // =========================================================================
    //  case 25 — Statistiques tâches
    // =========================================================================
    case 25 :
        $actifs = 0;
        $inactifs = 0;
        $total_taches = 0;


        try {

            $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


            $sql = "SELECT
    tache.id,
    tache.active
FROM tache";
            $stmt = $bdP->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_OBJ);


            $actifs = NULL;
            $inactifs = null;

            foreach ($result as $user) {


                if ($user->active == 0) {

                    ++$inactifs;

                } elseif ($user->active == 1) {

                    ++$actifs;

                }


            }

            echo json_encode([
                'total' => (int)count($result),
                'actifs' => (int)$actifs,
                'inactifs' => (int)$inactifs
            ]);


        } catch (Exception $e) {


            echo json_encode(['total' => 0, 'actifs' => 0, 'inactifs' => 0]);
            die;

        }

        break;


    // =========================================================================
    //  case 26 — Liste des associations tâche / qualification
    // =========================================================================
    case 26 :


        try {

            $stmt = $bdP->prepare('SELECT t.nom as tache,q.qualification as qualification, tq.id as id, t.id as idTache, q.id as idQualification,
    coalesce(ua3.codeNiv3, ua2.codeNiv2, ua1.codeNiv1) as codeUA
     FROM tache_qualification tq
    JOIN tache t ON t.id = tq.idTache
    JOIN qualifications q ON q.id = tq.idQualification
    LEFT JOIN unite_administrative_niv2 ua2 ON ua2.id = t.idUniteAdministrativeNiv2
    LEFT JOIN unite_administrative_niv1 ua1 ON ua1.id = t.idUniteAdministrativeNiv1
    LEFT JOIN unite_administrative_niv3 ua3 ON ua3.id = t.idUniteAdministrativeNiv3
    WHERE tq.valide = 1
        ORDER BY tq.id DESC');
            $stmt->execute();
            $listes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($listes);
            die;

        } catch (Exception $e) {
            echo json_encode(array());
            die;

        }


        break;


    // =========================================================================
    //  case 27 — Qualifications
    // =========================================================================
    case 27 :

        try {


            $stmt = $bdP->prepare("SELECT * FROM qualifications");
            $stmt->execute();
            $listes = $stmt->fetchAll(PDO::FETCH_OBJ);

            echo '<option></option><option value="">Choisir...</option>';

            if (count($listes) > 0) {
                foreach ($listes as $tmp) {
                    echo '<option value="' . $tmp->id . '">' . $tmp->qualification . '</option>';
                }
            } else {
                echo '<option value="">-- Qualification --</option>';
            }

            die;
        } catch (\Throwable $th) {
            echo "erreur";
            die;
        }
        break;


    // =========================================================================
    //  case 28 — Tâches de structure d'une unité administrative
    //  Corrigé : le paramètre nommé s'écrivait « : idTypeTache » (espace après
    //  les deux-points). PDO ne le reconnaissait pas, la requête partait en
    //  exception et le catch renvoyait silencieusement une liste vide.
    // =========================================================================
    case 28 :


        try {
            $idUniteAd = $_POST['idUniteAd'];
            $idNiv     = isset($_POST['idNiv']) ? (int) $_POST['idNiv'] : 0;

            $colonneUA = NULL;
            if($idNiv == 1)
            {
                $colonneUA = "idUniteAdministrativeNiv1";

            }else if($idNiv == 2)
            {
                $colonneUA = "idUniteAdministrativeNiv2";

            }else if($idNiv == 3)
            {
                $colonneUA = "idUniteAdministrativeNiv3";

            }else
            {
                echo "erreur";
                die;
            }

            $data =
                [
                    'idUniteAd' => $idUniteAd,
                    'idTypeTache' => 1
                ];

            $stmt = $bdP->prepare('SELECT
    tache.id,
    tache.nom,
    CASE
        WHEN tache.idSousMenu IS NULL THEN NULL
        ELSE sous_menu.nom
    END AS sous_menu
FROM tache
LEFT JOIN sous_menu
    ON sous_menu.id = tache.idSousMenu
WHERE tache.'.$colonneUA.' = :idUniteAd AND tache.idTypeTache = :idTypeTache');
            $stmt->execute($data);
            $listes = $stmt->fetchAll(PDO::FETCH_OBJ);


            echo '<option></option><option value="" >Choisir...</option>';

            if (count($listes) > 0) {
                foreach ($listes as $tmp) {
                    if($tmp->sous_menu != null)
                    {
                        echo '<option value="' . $tmp->id . '">' .''.$tmp->sous_menu.'\\'. $tmp->nom . '</option>';

                    }else
                    {
                        echo '<option value="' . $tmp->id . '">' .$tmp->nom . '</option>';

                    }
                }
            } else {
                echo '<option value="">--tâche--</option>';
            }

            die;

        }catch (PDOException $e) {
            echo '<option value="">--tâche--</option>';

        }

        break;


    // =========================================================================
    //  case 29 — Associer une qualification à une tâche
    // =========================================================================
    case 29 :

        $idUtilisateur = 1;
//        $idUtilisateur = null;
//        if (!empty($_SESSION['tmpIdP'])) {
//            $idUtilisateur = $_SESSION['tmpIdP'];
//        } else {
//            echo "sessionExpired";
//            die;
//        }

        date_default_timezone_set('Africa/Dakar');
        $dateEnregistrement = date('Y-m-d H:i:s');

        $idTache         = $_POST['idTache']         ?? null;
        $idQualification = $_POST['idQualification'] ?? null;

        if (empty($idTache) || empty($idQualification) || $idTache <= 0 || $idQualification <= 0) {
            echo "erreur";
            die;
        }

        try {
            $bdP->beginTransaction();

            // 1. Vérifier si l'association existe déjà
            $stmtCheck = $bdP->prepare("
            SELECT 1 FROM tache_qualification
            WHERE idTache = ? AND idQualification = ? AND valide = 1
            LIMIT 1
        ");
            $stmtCheck->execute([$idTache, $idQualification]);

            if ($stmtCheck->fetch()) {
                $bdP->rollBack();
                echo "existe";
                die;
            }

            // 2. Récupérer les qualifications déjà associées à la tâche
            $stmtQualifs = $bdP->prepare("
            SELECT idQualification FROM tache_qualification
            WHERE idTache = ? AND valide = 1
        ");
            $stmtQualifs->execute([$idTache]);
            $qualificationsExistantes   = $stmtQualifs->fetchAll(PDO::FETCH_COLUMN);
            $qualificationsExistantes[] = $idQualification; // ajouter la nouvelle

            // 3. Récupérer les utilisateurs associés à la tâche
            $stmtUsers = $bdP->prepare("
            SELECT u.id, u.matricule, u.email
            FROM tache_utilisateur tu
            JOIN utilisateurs u ON tu.idUtilisateur = u.id
            WHERE tu.idTache = ? AND tu.access = 1
        ");
            $stmtUsers->execute([$idTache]);
            $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

            // 4. Appel get_info — retourne tableau indexé par matricule
            $matricules = array_column($users, 'matricule');
            $usersInfo  = $adminController->get_info($matricules);

            // 5. Préparer les requêtes de retrait d'accès
            $stmtGetLast = $bdP->prepare("
            SELECT id FROM tache_utilisateur
            WHERE idTache = ? AND idUtilisateur = ?
            ORDER BY id DESC LIMIT 1
        ");

            $stmtUpdate = $bdP->prepare("
            UPDATE tache_utilisateur
            SET access = 0,
                idUtilisateurSupRetrait = ?,
                dateRetrait = ?
            WHERE id = ?
        ");

            foreach ($users as $user) {
                $userQualifications = [];

                // get_info retourne une entrée par matricule (pas un tableau de qualifs)
                if (isset($usersInfo[$user['matricule']])) {
                    $info = $usersInfo[$user['matricule']];
                    if (!empty($info['idQualification'])) {
                        $userQualifications[] = $info['idQualification'];
                    }
                }

                $hasRequiredQualification = false;
                foreach ($userQualifications as $qualif) {
                    if (in_array($qualif, $qualificationsExistantes)) {
                        $hasRequiredQualification = true;
                        break;
                    }
                }

                if (!$hasRequiredQualification) {
                    $stmtGetLast->execute([$idTache, $user['id']]);
                    $lastId = $stmtGetLast->fetchColumn();
                    if ($lastId) {
                        $stmtUpdate->execute([$idUtilisateur, $dateEnregistrement, $lastId]);
                    }
                }
            }

            // 6. Créer la nouvelle association
            $stmtInsert = $bdP->prepare("
            INSERT INTO tache_qualification (idTache, idQualification, dateEnregistrement, createdBy)
            VALUES (?, ?, ?, ?)
        ");
            $stmtInsert->execute([$idTache, $idQualification, $dateEnregistrement, $idUtilisateur]);

            $bdP->commit();
            echo "succes";
            die;

        } catch (Exception $e) {
            if ($bdP->inTransaction()) {
                $bdP->rollBack();
            }
            error_log("Erreur addTacheQualification: " . $e->getMessage());
            echo "erreur";
            die;
        }

        break;


    // =========================================================================
    //  case 30 — Retirer une association tâche / qualification
    //  Le fichier d'origine portait deux fois l'étiquette « case 30: » dans le
    //  même switch (la première suivie uniquement de code commenté). Fusionné
    //  en une seule étiquette.
    // =========================================================================
    case 30:

        date_default_timezone_set('Africa/Dakar');
        $dateModification = date('Y-m-d H:i:s');

        $idUtilisateur = null;
        if (!empty($_SESSION['tmpIdP'])) {
            $idUtilisateur = $_SESSION['tmpIdP'];
        } else {
            echo "sessionExpired";
            die;
        }

        $idTacheQualification = $_POST['id'] ?? null;

        if (!$idTacheQualification) {
            echo "erreur";
            die;
        }

        try {
            $bdP->beginTransaction();

            // 1. Récupérer la tâche et la qualification liées
            $stmtAssoc = $bdP->prepare("
            SELECT idTache, idQualification
            FROM tache_qualification
            WHERE id = ? AND valide = 1
        ");
            $stmtAssoc->execute([$idTacheQualification]);
            $association = $stmtAssoc->fetch(PDO::FETCH_ASSOC);

            if (!$association) {
                $bdP->rollBack();
                echo "erreur";
                die;
            }

            $idTache         = $association['idTache'];
            $idQualification = $association['idQualification'];

            // 2. Compter les qualifications valides restantes pour cette tâche
            $stmtCount = $bdP->prepare("
            SELECT COUNT(*) FROM tache_qualification
            WHERE idTache = ? AND valide = 1
        ");
            $stmtCount->execute([$idTache]);
            $remainingQualifications = (int) $stmtCount->fetchColumn();

            // 3. Désactiver la validité de l'association
            $stmtDisable = $bdP->prepare("
            UPDATE tache_qualification
            SET valide = 0, updatedBy = ?, dateUpdate = ?
            WHERE id = ?
        ");
            $stmtDisable->execute([$idUtilisateur, $dateModification, $idTacheQualification]);

            // Si c'était la dernière qualification → pas besoin de vérifier les accès
            if ($remainingQualifications <= 1) {
                $bdP->commit();
                echo "succes";
                die;
            }

            // 4. Récupérer les utilisateurs ayant accès à cette tâche
            $stmtUsers = $bdP->prepare("
            SELECT u.id AS idUtilisateur, u.matricule
            FROM tache_utilisateur tu
            JOIN utilisateurs u ON tu.idUtilisateur = u.id
            WHERE tu.idTache = ? AND tu.access = 1
        ");
            $stmtUsers->execute([$idTache]);
            $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

            // Pas d'utilisateurs → terminer proprement
            if (empty($users)) {
                $bdP->commit();
                echo "succes";
                die;
            }

            // 5. Récupérer les infos utilisateurs via get_info
            $matricules = array_column($users, 'matricule');
            $infos      = $adminController->get_info($matricules);

            // 6. Préparer les requêtes de retrait d'accès
            $stmtGetLast = $bdP->prepare("
            SELECT id FROM tache_utilisateur
            WHERE idUtilisateur = ? AND idTache = ?
            ORDER BY id DESC LIMIT 1
        ");

            $stmtUpdate = $bdP->prepare("
            UPDATE tache_utilisateur
            SET access = 0,
                idUtilisateurSupRetrait = ?,
                dateRetrait = ?
            WHERE id = ?
        ");

            // 7. Pour chaque utilisateur, vérifier s'il a UNIQUEMENT la qualification supprimée
            foreach ($users as $user) {
                $matricule            = $user['matricule'];
                $idUtilisateurCourant = $user['idUtilisateur'];

                // Si pas d'info pour cet utilisateur → ignorer
                if (!isset($infos[$matricule])) {
                    continue;
                }

                $info = $infos[$matricule];

                // get_info retourne une entrée par matricule avec idQualification
                $userIdQualification = $info['idQualification'] ?? null;

                // Si l'utilisateur a UNIQUEMENT la qualification supprimée → retirer l'accès
                if ($userIdQualification == $idQualification) {

                    // Vérifier si l'utilisateur a une autre qualification valide pour cette tâche
                    $stmtCheckOtherQualif = $bdP->prepare("
                    SELECT COUNT(*) FROM tache_qualification tq
                    WHERE tq.idTache = ?
                    AND tq.valide = 1
                    AND tq.idQualification = ?
                ");
                    $stmtCheckOtherQualif->execute([$idTache, $userIdQualification]);
                    $hasOtherValidQualif = (int) $stmtCheckOtherQualif->fetchColumn();

                    // Si la qualification de l'utilisateur n'est plus valide pour la tâche
                    if ($hasOtherValidQualif === 0) {
                        $stmtGetLast->execute([$idUtilisateurCourant, $idTache]);
                        $lastId = $stmtGetLast->fetchColumn();
                        if ($lastId) {
                            $stmtUpdate->execute([$idUtilisateur, $dateModification, $lastId]);
                        }
                    }
                }
            }

            $bdP->commit();
            echo "succes";
            die;

        } catch (PDOException $e) {
            if ($bdP->inTransaction()) $bdP->rollBack();
            error_log("Erreur PDO changeValiditeTacheQualification: " . $e->getMessage());
            echo "erreur";
            die;

        } catch (Exception $e) {
            if ($bdP->inTransaction()) $bdP->rollBack();
            error_log("Erreur changeValiditeTacheQualification: " . $e->getMessage());
            echo "erreur";
            die;
        }

        break;


    // =========================================================================
    //  case 31 — Page d'accueil de l'application
    // =========================================================================
    case 31 :

        if(!empty($_POST['tmp']))
        {


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
                    !isset($_SESSION['listeTachesParDefaut']) ) {

                    session_unset();
                    session_destroy();

                    echo "sesionExpired";
                    die;

                }else
                {


                    $connectUser = $_SESSION['connectUserGSJLF_ENT'] ?? null;

                    if($connectUser == 1)
                    {

                        $lien_logo1 = "/personnel/admin-accueil";
                        $lien_logo2 = "/personnel/admin-accueil";


                    }else if($connectUser == 2)
                    {

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


                $page_accueil = $adminController->getPageAccueil(
                    $listeTachesIncarnes,
                    $listeTachesStructures,
                    $listeTachesParDefaut,
                    $page_par_defaut ?? 'http://localhost/personnel/signin'   // fallback ultime
                );

                echo "ac".$page_accueil;
                die;


            } catch (\Throwable $th) {
                error_log($th->getMessage());
                echo "erreur".$th;
                die;
            }

        }else
        {
            echo "erreur";
            die;
        }
        break;


    // =========================================================================
    //  case 32 — Ordre d'affichage du menu
    // =========================================================================
    case 32 :

        date_default_timezone_set('Africa/Dakar');

        $idUtilisateur = 1;
//        $idUtilisateur = null;
//        if (!empty($_SESSION['tmpIdP'])) {
//            $idUtilisateur = $_SESSION['tmpIdP'];
//        } else {
//            echo "sessionExpired";
//            die;
//        }

        $sousAction = $_POST['sousAction'] ?? '';
        $idAppli    = isset($_POST['idAppli']) ? (int) $_POST['idAppli'] : 0;

        if ($idAppli <= 0) {
            echo json_encode(['success' => false, 'message' => 'idAppliManquant']);
            die;
        }

        // ────────────────────────────────────────────────────────────────
        // RÉCUPÉRATION DE L'ARBRE (sous-menus + tâches) POUR L'APPLICATION
        // ────────────────────────────────────────────────────────────────
        if ($sousAction === 'get') {

            try {
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Sous-menus utilisés par au moins une tâche active de cette application
                $stmtSM = $bdP->prepare("
                    SELECT sm.id, sm.nom, sm.idIcon, i.icon AS iconSvg, COALESCE(sm.ordre, 0) AS ordre
                    FROM sous_menu sm
                    JOIN icons i ON i.id = sm.idIcon
                    WHERE sm.statut = 0
                      AND EXISTS (
                          SELECT 1 FROM tache t
                          WHERE t.idSousMenu = sm.id AND t.idAppli = :idAppli AND t.active = 1 AND estVisible = 1
                      )
                    ORDER BY COALESCE(sm.ordre, 0) ASC, sm.nom ASC
                ");
                $stmtSM->execute(['idAppli' => $idAppli]);
                $sousMenus = $stmtSM->fetchAll(PDO::FETCH_ASSOC);

                $stmtTachesSM = $bdP->prepare("
                    SELECT id, nom, url, idIcon, COALESCE(ordre, 0) AS ordre
                    FROM tache
                    WHERE idSousMenu = :idSousMenu AND idAppli = :idAppli AND active = 1 AND estVisible = 1
                    ORDER BY COALESCE(ordre, 0) ASC, nom ASC
                ");

                $listeSousMenus = [];
                foreach ($sousMenus as $sm) {
                    $stmtTachesSM->execute(['idSousMenu' => $sm['id'], 'idAppli' => $idAppli]);
                    $listeSousMenus[] = [
                        'type'   => 'sousMenu',
                        'id'     => (int) $sm['id'],
                        'nom'    => $sm['nom'],
                        'icon'   => $sm['iconSvg'],
                        'ordre'  => (int) $sm['ordre'],
                        'taches' => $stmtTachesSM->fetchAll(PDO::FETCH_ASSOC),
                    ];
                }

                // Tâches affichées directement dans le menu (idSousMenu NULL)
                $stmtTachesLibres = $bdP->prepare("
                    SELECT id, nom, url, idIcon, COALESCE(ordre, 0) AS ordre
                    FROM tache
                    WHERE idSousMenu IS NULL AND idAppli = :idAppli AND active = 1 AND estVisible = 1
                    ORDER BY COALESCE(ordre, 0) ASC, nom ASC
                ");
                $stmtTachesLibres->execute(['idAppli' => $idAppli]);
                $tachesLibres = $stmtTachesLibres->fetchAll(PDO::FETCH_ASSOC);

                $items = $listeSousMenus;
                foreach ($tachesLibres as $t) {
                    $items[] = [
                        'type'  => 'tache',
                        'id'    => (int) $t['id'],
                        'nom'   => $t['nom'],
                        'url'   => $t['url'],
                        'ordre' => (int) $t['ordre'],
                    ];
                }

                // Tri final : tous les éléments de premier niveau mélangés par ordre
                usort($items, function ($a, $b) {
                    return $a['ordre'] <=> $b['ordre'];
                });

                echo json_encode(['success' => true, 'items' => $items]);
                die;

            } catch (Exception $e) {
                error_log("Erreur getOrdreMenu: " . $e->getMessage());
                echo json_encode(['success' => false, 'items' => []]);
                die;
            }

            // ────────────────────────────────────────────────────────────────
            // ENREGISTREMENT DU NOUVEL ORDRE
            // ────────────────────────────────────────────────────────────────
        } elseif ($sousAction === 'save') {

            $items = json_decode($_POST['items'] ?? '', true);

            if (!is_array($items) || count($items) === 0) {
                echo "erreur";
                die;
            }

            $dateModification = date('Y-m-d H:i:s');

            // Historise une tâche dans historiqueTache à partir de son état courant
            // (lu APRÈS mise à jour de son ordre, comme pour changeEtatTache au case 24)
            $insererHistoriqueTache = function ($tache) use ($bdP, $idUtilisateur, $dateModification) {
                $stmtHist = $bdP->prepare("
                    INSERT INTO historiqueTache (
                        idUtilisateur, idTache, nom, autre_ressource, url,
                        idTypeTache, commentaire, idSousMenu, idIcon,
                        idUniteAdministrativeNiv1, idUniteAdministrativeNiv2, idUniteAdministrativeNiv3,
                        dateEnregistrement, active, idFonction, createdBy, idDB, idAppli, ordre, lastDateModification
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?
                    )
                ");
                $stmtHist->execute([
                    $idUtilisateur,
                    $tache['id'],
                    $tache['nom']                       ?? null,
                    $tache['autre_ressource']            ?? null,
                    $tache['url']                        ?? null,
                    $tache['idTypeTache']                ?? null,
                    $tache['commentaire']                ?? null,
                    $tache['idSousMenu']                 ?? null,
                    $tache['idIcon']                     ?? null,
                    $tache['idUniteAdministrativeNiv1']  ?? null,
                    $tache['idUniteAdministrativeNiv2']  ?? null,
                    $tache['idUniteAdministrativeNiv3']  ?? null,
                    $dateModification,
                    $tache['active']                     ?? null,
                    $tache['idFonction']                 ?? null,
                    $tache['createdBy']                  ?? null,
                    $tache['idDB']                        ?? null,
                    $tache['idAppli']                     ?? null,
                    $tache['ordre']                        ?? null,
                    $dateModification,
                ]);
            };

            try {
                $bdP->beginTransaction();

                // Tâche "libre" (idSousMenu NULL) : on ne touche que celles qui le
                // sont déjà, pour ne jamais dérattacher une tâche par erreur.
                $stmtUpdateTacheLibre = $bdP->prepare("
                    UPDATE tache SET ordre = ?, lastDateModification = ?
                    WHERE id = ? AND idAppli = ? AND idSousMenu IS NULL
                ");
                // Tâche rattachée à un sous-menu précis : on vérifie le rattachement
                // pour ne jamais réordonner une tâche en dehors de son sous-menu.
                $stmtUpdateTacheGroupee = $bdP->prepare("
                    UPDATE tache SET ordre = ?, lastDateModification = ?
                    WHERE id = ? AND idAppli = ? AND idSousMenu = ?
                ");
                $stmtUpdateSousMenu = $bdP->prepare("UPDATE sous_menu SET ordre = ? WHERE id = ?");
                $stmtGetTache       = $bdP->prepare("SELECT * FROM tache WHERE id = ?");

                $ordreTopLevel = 10;

                foreach ($items as $item) {

                    $type = $item['type'] ?? '';

                    if ($type === 'sousMenu') {

                        $idSousMenu = (int) ($item['id'] ?? 0);
                        if ($idSousMenu <= 0) {
                            continue;
                        }

                        $stmtUpdateSousMenu->execute([$ordreTopLevel, $idSousMenu]);

                        $ordreTache = 10;
                        foreach (($item['taches'] ?? []) as $idTache) {
                            $idTache = (int) $idTache;
                            if ($idTache <= 0) {
                                continue;
                            }

                            $stmtUpdateTacheGroupee->execute([$ordreTache, $dateModification, $idTache, $idAppli, $idSousMenu]);

                            $stmtGetTache->execute([$idTache]);
                            $tacheActuelle = $stmtGetTache->fetch(PDO::FETCH_ASSOC);
                            if ($tacheActuelle) {
                                $insererHistoriqueTache($tacheActuelle);
                            }

                            $ordreTache += 10;
                        }

                    } elseif ($type === 'tache') {

                        $idTache = (int) ($item['id'] ?? 0);
                        if ($idTache <= 0) {
                            continue;
                        }

                        $stmtUpdateTacheLibre->execute([$ordreTopLevel, $dateModification, $idTache, $idAppli]);

                        $stmtGetTache->execute([$idTache]);
                        $tacheActuelle = $stmtGetTache->fetch(PDO::FETCH_ASSOC);
                        if ($tacheActuelle) {
                            $insererHistoriqueTache($tacheActuelle);
                        }
                    }

                    $ordreTopLevel += 10;
                }

                $bdP->commit();
                echo "succès";
                die;

            } catch (Exception $e) {
                if ($bdP->inTransaction()) {
                    $bdP->rollBack();
                }
                error_log("Erreur saveOrdreMenu: " . $e->getMessage());
                echo "erreur";
                die;
            }

        } else {
            echo "erreur";
            die;
        }

        break;


    // =========================================================================
    //  case 33 — Liste des tâches invisibles associées à une tâche visible
    //            POST { option:33, id:<id de la tâche sélectionnée> }
    //            -> JSON { success, parent:{id,nom,url}, taches:[...] }
    // =========================================================================
    case 33 :

        $idParent = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($idParent <= 0) {
            echo json_encode(['success' => false, 'message' => 'idManquant', 'taches' => []]);
            die;
        }

        try {
            $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // ── La tâche sélectionnée doit exister et être visible ──
            $stmtParent = $bdP->prepare("SELECT id, nom, url FROM tache WHERE id = ? AND estVisible = 1");
            $stmtParent->execute([$idParent]);
            $parent = $stmtParent->fetch(PDO::FETCH_ASSOC);

            if (!$parent) {
                echo json_encode(['success' => false, 'message' => 'parentIntrouvable', 'taches' => []]);
                die;
            }

            // ── Tâches invisibles rattachées ────────────────────────
            $stmt = $bdP->prepare("
                SELECT
                    t.id            AS id,
                    t.nom           AS nom,
                    t.url           AS url,
                    t.commentaire   AS commentaire,
                    t.active        AS active,
                    t.estVisibleUrl AS estVisibleUrl,
                    tt.typeTache    AS type,
                    sm.nom          AS sousMenu
                FROM tache t
                JOIN typetache tt      ON tt.id = t.idTypeTache
                LEFT JOIN sous_menu sm ON sm.id = t.idSousMenu
                WHERE t.estVisible   = 0
                  AND t.estVisibleId = ?
                ORDER BY t.nom ASC
            ");
            $stmt->execute([$idParent]);
            $taches = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'parent'  => [
                    'id'  => (int) $parent['id'],
                    'nom' => $parent['nom'],
                    'url' => $parent['url'],
                ],
                'taches'  => $taches,
            ]);
            die;

        } catch (Exception $e) {
            error_log("Erreur listeTachesInvisibles: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'erreur', 'taches' => []]);
            die;
        }

        break;


    // =========================================================================
    //  case 34 — Retirer une association : estVisible passe à 3
    //            POST { option:34, id:<id de la tâche associée> }
    // =========================================================================
    case 34 :

        date_default_timezone_set('Africa/Dakar');

        $idUtilisateur = 1;
//        $idUtilisateur = null;
//        if (!empty($_SESSION['tmpIdP'])) {
//            $idUtilisateur = $_SESSION['tmpIdP'];
//        } else {
//            echo "sessionExpired";
//            die;
//        }

        $idTache          = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $dateModification = date('Y-m-d H:i:s');

        if ($idTache <= 0) {
            echo "erreur";
            die;
        }

        try {
            $bdP->beginTransaction();

            // ── La tâche doit bien être une tâche associée ──────────
            $stmtTache = $bdP->prepare("SELECT * FROM tache WHERE id = ? AND estVisible = 0");
            $stmtTache->execute([$idTache]);
            $tache = $stmtTache->fetch(PDO::FETCH_ASSOC);

            if (!$tache) {
                $bdP->rollBack();
                echo "nonAssociee";
                die;
            }

            // ── Aucun agent ne doit détenir la tâche ────────────────
            $stmtUsers = $bdP->prepare("
                SELECT COUNT(*) FROM tache_utilisateur
                WHERE idTache = ? AND access = 1
            ");
            $stmtUsers->execute([$idTache]);
            if ((int) $stmtUsers->fetchColumn() > 0) {
                $bdP->rollBack();
                echo "tacheAffectee";
                die;
            }

            // ── Suppression logique ─────────────────────────────────
            $stmtUpdate = $bdP->prepare("
                UPDATE tache
                SET estVisible = 3, active = 0, lastDateModification = ?
                WHERE id = ? AND estVisible = 0
            ");
            $stmtUpdate->execute([$dateModification, $idTache]);

            if ($stmtUpdate->rowCount() === 0) {
                $bdP->rollBack();
                echo "erreur";
                die;
            }

            // ── Historique ──────────────────────────────────────────
            $stmtHist = $bdP->prepare("
                INSERT INTO historiqueTache (
                    idUtilisateur, idTache, nom, autre_ressource, url,
                    idTypeTache, commentaire, idSousMenu, idIcon,
                    idUniteAdministrativeNiv1, idUniteAdministrativeNiv2, idUniteAdministrativeNiv3,
                    dateEnregistrement, active, idFonction, createdBy, idDB, idAppli,
                    estVisible, estVisibleId, estVisibleUrl
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?
                )
            ");

            $stmtHist->execute([
                $idUtilisateur,
                $idTache,
                $tache['nom']                       ?? null,
                $tache['autre_ressource']           ?? null,
                $tache['url']                       ?? null,
                $tache['idTypeTache']               ?? null,
                $tache['commentaire']               ?? null,
                $tache['idSousMenu']                ?? null,
                $tache['idIcon']                    ?? null,
                $tache['idUniteAdministrativeNiv1'] ?? null,
                $tache['idUniteAdministrativeNiv2'] ?? null,
                $tache['idUniteAdministrativeNiv3'] ?? null,
                $dateModification,
                0,
                $tache['idFonction']                ?? null,
                $tache['createdBy']                 ?? null,
                $tache['idDB']                      ?? null,
                $tache['idAppli']                   ?? null,
                3,
                $tache['estVisibleId']              ?? null,
                $tache['estVisibleUrl']             ?? null
            ]);

            $bdP->commit();
            echo "succès";
            die;

        } catch (Exception $e) {
            if ($bdP->inTransaction()) {
                $bdP->rollBack();
            }
            error_log("Erreur retirerTacheInvisible: " . $e->getMessage());
            echo "erreur";
            die;
        }

        break;


    // =========================================================================
    //  case 35 — Utilisateurs détenant une tâche
    //            POST { option:35, id:<id de la tâche> }
    //            -> JSON [ { idUtilisateur, matricule, email, prenom, nom,
    //                        prenomNom, photo, etat, etatClasse, access } ]
    //
    //  Remplace l'ancienne route api/voirUtilisateur/{id}.
    //  La table s'appelle « utilisateurs » (pluriel) dans cette base ; le nom,
    //  le prénom et la photo viennent de etatCivil via utilisateurs.idEtatCivil.
    //
    //  NB : imageExiste() n'est pas appelée ici — elle fait une requête HTTP
    //  par utilisateur, ce qui rendrait le modal très lent. Le repli sur
    //  l'avatar par défaut est fait côté navigateur (attribut onerror).
    // =========================================================================
    case 35 :

        $idTache = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($idTache <= 0) {
            echo json_encode(array());
            die;
        }

        try {
            $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $sql = "SELECT
                        u.id                AS idUtilisateur,
                        u.matricule         AS matricule,
                        u.email             AS email,
                        u.statutUtilisateur AS statutUtilisateur,
                        u.statutActivation  AS statutActivation,
                        ec.prenom           AS prenom,
                        ec.nom              AS nom,
                        ec.photo            AS photo,
                        ec.sexe             AS sexe
                    FROM tache_utilisateur tu
                    JOIN utilisateurs u   ON u.id = tu.idUtilisateur
                    LEFT JOIN etatCivil ec ON ec.id = u.idEtatCivil
                    WHERE tu.idTache = :idTache
                      AND tu.access  = 1
                      AND tu.idUtilisateurSupRetrait IS NULL
                    GROUP BY u.id
                    ORDER BY ec.nom ASC, ec.prenom ASC";

            $stmt = $bdP->prepare($sql);
            $stmt->execute([':idTache' => $idTache]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $liste = array();

            foreach ($result as $u) {

                // ── Photo : repli par sexe si la colonne est vide ───────
                $photo = trim((string) ($u['photo'] ?? ''));
                if ($photo === '') {
                    if (($u['sexe'] ?? '') === "Féminin") {
                        $photo = "/personnel/includes/fpdf/template/avatar1.png";
                    } else {
                        $photo = "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";
                    }
                }

                // ── État du compte — même logique qu'au case 1 ──────────
                if ($u['statutActivation'] == 0) {
                    $etat       = "En attente d'activation";
                    $etatClasse = "badge badge-light-warning";
                } elseif ($u['statutUtilisateur'] == 0) {
                    $etat       = "Compte actif";
                    $etatClasse = "badge badge-light-success";
                } elseif ($u['statutUtilisateur'] == 1) {
                    $etat       = "Compte bloqué";
                    $etatClasse = "badge badge-light-danger";
                } else {
                    $etat       = "Erreur";
                    $etatClasse = "badge badge-light-dark";
                }

                $prenom = ucwords(mb_strtolower($u['prenom'] ?? ''));
                $nom    = $adminController->fctRetirerAccents(mb_strtoupper($u['nom'] ?? ''));

                $liste[] = array(
                    'idUtilisateur' => (int) $u['idUtilisateur'],
                    'matricule'     => $u['matricule'],
                    'email'         => $u['email'],
                    'prenom'        => $prenom,
                    'nom'           => $nom,
                    'prenomNom'     => trim($prenom . ' ' . $nom),
                    'photo'         => $photo,
                    'etat'          => $etat,
                    'etatClasse'    => $etatClasse,
                    'access'        => 1
                );
            }

            echo json_encode($liste);
            die;

        } catch (Exception $e) {
            error_log("Erreur voirUtilisateur: " . $e->getMessage());
            echo json_encode(array());
            die;
        }

        break;


    default :
        echo "erreur";
        die;


}