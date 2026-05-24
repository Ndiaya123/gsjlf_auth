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

    case 5 :

        if (!empty($_POST['matricule'])) {

            try {
                $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


                date_default_timezone_set('Africa/Dakar');
                $date_jour = date('Y-m-d');

                $matricule = valid_donnees($_POST['matricule']);

                $infoPerso = array();



                $data = [
                    'matricule' => $matricule
                ];

                $sql = "SELECT * FROM utilisateurs WHERE matricule=:matricule";
                $stmt = $bd->prepare($sql);
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
LEFT JOIN compteGmail cg 
    ON p.idCompteGmail = cg.id
WHERE p.matricule = :matricule;";
                    $stmt_perso = $bd->prepare($sql_perso);
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


                        echo json_encode([
                            'matricule' => $result_perso->matricule,
                            'prenom' => ucwords(mb_strtolower($result_perso->prenom)),
                            'nom' => $adminController->fctRetirerAccents(mb_strtoupper($result_perso->nom)),
                            'email' => $result_perso->email,
                            'photo' => $photo,
                            'pwd' => "GSJLF@2006"
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
                $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bd->beginTransaction();


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
                $stmt = $bd->prepare($sql);
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
                    $stmt_perso = $bd->prepare($sql_perso);
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
                        $stmt_perso_contrat = $bd->prepare($sql_perso_contrat);
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
                                $stmt = $bd->prepare($sql);
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
                                    $stmtHistorique = $bd->prepare($sqlHistorique);
                                    $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);



                                    if ($tmpStmtHistorique) {

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
</html>";                                        // Envoyer l'e-mail
                                        $emailSent = $adminController->sendEmail($email, $prenom, "Activez votre compte maintenant !", $message);

                                        if (!$emailSent) {
                                            if ($bd->inTransaction()) {
                                                $bd->rollBack();
                                            }
                                            echo "erreurMail";
                                            die;
                                        } else {

                                            $bd->commit();
                                            echo "succès";
                                            die;

                                        }

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
                                    die;
                                }


                            } else {
                                if ($bd->inTransaction()) {
                                    $bd->rollBack();
                                }

                                echo "finContrat";
                                die;

                            }


                        } else {
                            if ($bd->inTransaction()) {
                                $bd->rollBack();
                            }
                            echo "pasContrat";
                            die;
                        }


                    } else {
                        if ($bd->inTransaction()) {
                            $bd->rollBack();
                        }
                        echo "matriculeExistsPas";
                        die;

                    }

                } else {
                    if ($bd->inTransaction()) {
                        $bd->rollBack();
                    }
                    echo "dejaCompte";
                    die;

                }

            } catch (Exception $e) {
                if ($bd->inTransaction()) {
                    $bd->rollBack();
                }
                echo "erreur";
                die;
            }


        } else {
            echo "champsObligatoire";
            die;
        }
        break;
    case 7 :

        try {
            $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


            $sql = "SELECT s.id AS id,s.nom AS nom_s, i.icon AS icon FROM sous_menu s
                        JOIN icons i ON i.id = s.idIcon ORDER BY s.id DESC";
            $stmt = $bd->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_OBJ);

            echo json_encode($results);
            die;

        }catch (Exception $e) {
               echo json_encode(array());
               die;
            }

            break;
    case 8:

        try {
            $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


            $sql = "SELECT * FROM icons ORDER BY icons.id ASC";
            $stmt = $bd->prepare($sql);
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

    case 9 :


        if(!empty($_POST['id']))
        {

            $id = $_POST['id'];
            try {
                $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $data =
                    [
                        'id' => $id
                    ];

            $sql = "SELECT * FROM sous_menu 
	    join icons i on i.id = sous_menu.idIcon
	WHERE sous_menu.id = :id";
            $stmt = $bd->prepare($sql);
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
                $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bd->beginTransaction();

                $data =
                    [
                        'nom' => $nom
                    ];

                $sql = "SELECT * FROM sous_menu WHERE nom= :nom";
                $stmt = $bd->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if(!$result)
                {

                    $data_insert = [
                        'nom' => $nom,
                        'idIcon' => $idIcon,
                        'idUtilisateur' => $idUtilisateur,
                        'dateEnregistrement' => $dateEnregistrement

                    ];
                $sql_insert = "INSERT INTO sous_menu(nom, idIcon,dateEnregistrement) VALUES (:nom, :idIcon,:idUtilisateur,:dateEnregistrement)";
                $stmt_insert = $bd->prepare($sql_insert);
                $tmp_insert = $stmt_insert->execute($data_insert);

                if($tmp_insert)
                {
                    $idSousMenu = $bd->lastInsertId();

                    $data_insert_his = [
                        'idSousMenu' => $idSousMenu,
                        'nom' => $nom,
                        'idIcon' => $idIcon,
                        'idUtilisateur' => $idUtilisateur,
                        'dateEnregistrement' => $dateEnregistrement

                    ];
                    $sql_insert_his = "INSERT INTO sous_menu_historiques(idSousMenu,nom, idIcon,idUtilisateur,dateEnregistrement) VALUES (:idSousMenu,:nom, :idIcon,:idUtilisateur,:dateEnregistrement)";
                    $stmt_insert_his = $bd->prepare($sql_insert_his);
                    $tmp_insert_his = $stmt_insert_his->execute($data_insert_his);

                    if($tmp_insert_his)
                    {
                        if ($bd->inTransaction()) {
                            $bd->commit();
                        }

                    }else
                    {
                        if ($bd->inTransaction()) {
                            $bd->rollBack();
                        }
                        echo "erreur";
                        die;
                    }

                }else
                {

                    if ($bd->inTransaction()) {
                        $bd->rollBack();
                    }
                    echo "erreur";
                    die;
                }



            }else
                {

                    if ($bd->inTransaction()) {
                        $bd->rollBack();
                    }
                    echo "erreur";
                    die;
                }





            }catch (Exception $e) {

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
        echo "erreur";
        die;




}
