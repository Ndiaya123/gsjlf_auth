<?php

ini_set('session.gc_maxlifetime', 36000);
session_set_cookie_params(36000);
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

include_once('../../bd.php');
require_once('../../includes/phpMailer/PHPMailer.php');
require_once('../../includes/phpMailer/SMTP.php');
require_once('../../includes/phpMailer/Exception.php');

class papa extends BD
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



    public function returnPosteResponsabilite($identifiant)
    {


        try {
            $data = [
                'identifiant' => $identifiant,
                'statutPoste' => 1
            ];
            $db = $this->connect();
            $stmt = $db->prepare("SELECT fonction.fonction
FROM postesAResponsabilite
INNER JOIN fonction 
    ON postesAResponsabilite.idFonction = fonction.id
WHERE postesAResponsabilite.identifiant=:identifiant AND postesAResponsabilite.statutPoste = :statutPoste
AND postesAResponsabilite.dateFin IS NOT NULL;");
            $stmt->execute($data);
            $result = $stmt->fetch(PDO::FETCH_OBJ);

            if($result)
            {
                return $result->fonction;

            }else
            {
                return "Agent simple";

            }


        } catch (\Throwable $th) {
            return "";
        }


    }

    function imageExiste($url)
    {

//        if($url != NULL  && $url != "")
//        {
//            return true;
//
//        }else{
//            return false;
//
//        }
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

}


$bd = new BD();
$bd = $bd->connect();
$adminController = new papa();
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
    case 1:




            try {

                $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


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
                $stmt = $bd->prepare($sql);
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

                    $posteAResponsabilite = $adminController->returnPosteResponsabilite($user->identifiant);

                    if ($adminController->imageExiste($user->photo)) {
                        $photo = $user->photo;
                    } else {
                        $photo = "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";

                        if ($user->sexe == "féminin") {
                            $photo = "/personnel/includes/fpdf/template/avatar1.png";
                        } else if ($user->sexe == "masculin") {
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


    case 2:

        $actifs = 0;
        $inactifs = 0;
        $total_etudiant = 0;


        try {

            $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


            $sql = "SELECT 
    utilisateurs.id,
    utilisateurs.matricule,
    utilisateurs.identifiant,
      utilisateurs.statutUtilisateur,
      utilisateurs.statutActivation,
      utilisateurs.idTypeUtilisateur
FROM utilisateurs";
            $stmt = $bd->prepare($sql);
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

            echo $e;
            die;
            echo json_encode(['total' => 0, 'actifs' => 0, 'inactifs' => 0,'bloques' => 0]);
            die;

        }

        break;

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
                $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bd->beginTransaction();


                date_default_timezone_set('Africa/Dakar');
                $date_jour = date('Y-m-d');


                $data = [
                    'id' => $id
                ];

                $sql = "SELECT * FROM utilisateurs WHERE id=:id";
                $stmt = $bd->prepare($sql);
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

                        $stmt_up_user = $bd->prepare($sql_up_user);

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
                                $stmtHistorique = $bd->prepare($sqlHistorique);
                                $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);


                                if ($tmpStmtHistorique) {

                                    $bd->commit();
                                    echo "succès";
                                    die;

                                } else {
                                    if ($bd->inTransaction()) {
                                        $bd->rollBack();
                                    }
                                    echo "erreur";
                                    die;
                                }




                            } else {
                                if ($bd->inTransaction()) {
                                    $bd->rollBack();
                                }
                                echo "erreur";
                                die;                            }

                        } else {
                            if ($bd->inTransaction()) {
                                $bd->rollBack();
                            }
                            echo "erreur";
                            die;                        }

                    }else
                    {
                        if ($bd->inTransaction()) {
                            $bd->rollBack();
                        }
                        echo "erreur";
                        die;
                    }


                } else {
                    if ($bd->inTransaction()) {
                        $bd->rollBack();
                    }
                    echo "erreur";
                    die;

                }

            } catch (Exception $e) {
                if ($bd->inTransaction()) {
                    $bd->rollBack();
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
                $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bd->beginTransaction();


                date_default_timezone_set('Africa/Dakar');
                $date_jour = date('Y-m-d');


                $data = [
                    'id' => $id];

                $sql = "SELECT * FROM utilisateurs WHERE id=:id";
                $stmt = $bd->prepare($sql);
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

                        $stmt_up_user = $bd->prepare($sql_up_user);

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
                                $stmtHistorique = $bd->prepare($sqlHistorique);
                                $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);


                                if ($tmpStmtHistorique) {

                                    $bd->commit();
                                    echo "succès";
                                    die;

                                } else {
                                    if ($bd->inTransaction()) {
                                        $bd->rollBack();
                                    }
                                    echo "erreur";
                                    die;
                                }


                            } else {
                                if ($bd->inTransaction()) {
                                    $bd->rollBack();
                                }
                                echo "erreur";
                                die;                            }

                        } else {
                            if ($bd->inTransaction()) {
                                $bd->rollBack();
                            }
                            echo "erreur";
                            die;                        }

                    }else
                    {
                        if ($bd->inTransaction()) {
                            $bd->rollBack();
                        }
                        echo "erreur";
                        die;
                    }


                } else {
                    if ($bd->inTransaction()) {
                        $bd->rollBack();
                    }
                    echo "erreur";
                    die;

                }

            } catch (Exception $e) {
                if ($bd->inTransaction()) {
                    $bd->rollBack();
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
    default :
        echo "erreur1";
        die;




}
