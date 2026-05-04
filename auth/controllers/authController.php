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


    function genererCode6Chiffres()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
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


}


$bd = new BD();
$bd = $bd->connect();
$authController = new papa();
date_default_timezone_set('Africa/Dakar');


function valid_donnees($donnees)
{
    $donnees = trim($donnees);
    $donnees = stripslashes($donnees);
    $donnees = htmlspecialchars($donnees);
    return $donnees;
}

$option = (!empty($_POST['option'])) ? $_POST['option'] : '';


switch ($option) {
    case 1:


        if (!empty($_POST['matricule']) && !empty($_POST['email']) && !empty($_POST['password']) && !empty($_POST['confirm-password']) && !empty($_POST['cgu'])) {

            try {
                $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bd->beginTransaction();


                date_default_timezone_set('Africa/Dakar');
                $date_jour = date('Y-m-d');

                $matricule = valid_donnees($_POST['matricule']);
                $email = valid_donnees($_POST['email']);
                $password = valid_donnees($_POST['password']);
                $confirmPassword = valid_donnees($_POST['confirm-password']);
                $cgu = 1;

                if ($password != $confirmPassword) {

                    echo "pasCorrespondantPWD";
                    die;

                }


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

                        if ($email != $result_perso->email) {
                            echo "pasCorrespondantEmail";
                            die;
                        }

                        $identifiant = $result_perso->identifiant;
                        $idEtatCivil = $result_perso->idEtatCivil;
                        $prenom = ucwords($result_perso->prenom);
                        $nom = $authController->fctRetirerAccents(mb_strtoupper($result_perso->nom));


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


                            if ($authController->comparerDate($finContrat)) {


                                $dateCreation = new DateTime();
                                $dateCreation = $dateCreation->format('Y-m-d H:i:s');
                                $password = password_hash(valid_donnees($_POST['password']), PASSWORD_DEFAULT, ['cost' => 5]);


                                $codeActivation = $authController->genererCode6Chiffres();
                                $codeActivation_encrypt = $authController->tokenencrypt($codeActivation);

                                $dataCandidat = [
                                    'identifiant' => $identifiant,
                                    'matricule' => valid_donnees($_POST['matricule']),
                                    'email' => strtolower(valid_donnees($_POST['email'])),
                                    'password' => $password,
                                    'cgu' => $cgu,
                                    'dateCreation' => $dateCreation,
                                    'statutUtilisateur' => 0,
                                    'statutActivation' => 0,
                                    'codeActivation' => $codeActivation_encrypt,
                                    'dateEnvoiCodeValidation' => $dateCreation,
                                    'idEtatCivil' => $idEtatCivil
                                ];
                                $sql = "INSERT INTO utilisateurs(identifiant,matricule,email,password,cgu,dateCreation,statutUtilisateur,statutActivation,codeActivation,dateEnvoiCodeValidation,idEtatCivil) VALUES(:identifiant,:matricule,:email,:password,:cgu,:dateCreation,:statutUtilisateur,:statutActivation,:codeActivation,:dateEnvoiCodeValidation,:idEtatCivil)";
                                $stmt = $bd->prepare($sql);
                                $tmpStmt = $stmt->execute($dataCandidat);

                                if ($tmpStmt == 1) {


                                    $table = "utilisateurs";
                                    $motif = "Création de compte";
                                    $dateEnregistrement = new DateTime();
                                    $dateEnregistrement = $dateEnregistrement->format('Y-m-d H:i:s');
                                    $dataHistorique = [
                                        'identifiant' => $identifiant,
                                        'matricule' => $matricule,
                                        'tableHistorique' => $table,
                                        'motif' => $motif,
                                        'idEtatCivil' => $idEtatCivil,
                                        'dateEnregistremenent' => $dateEnregistrement,
                                    ];
                                    $sqlHistorique = "INSERT INTO auth_personnel_historiques(identifiant,matricule,tableHistorique,motif,idEtatCivil,dateEnregistremenent) VALUES (:identifiant,:matricule,:tableHistorique,:motif,:idEtatCivil,:dateEnregistremenent)";
                                    $stmtHistorique = $bd->prepare($sqlHistorique);
                                    $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);


                                    if ($tmpStmtHistorique == 1) {

                                        $link = "/personnel/activate-account/" . $authController->tokenencrypt($matricule);
                                        $message = "<html>
                                        <head>
                                          <title>Code d'activation – Demande d'admission en ligne</title>
                                          <style>
                                        p{
                                        font-size: 16px;
                                        font-family: Roboto, Arial, sans-serif;
                                        color: #202124;
                                        background-color: #ffffff;
                                        }
                                        ul, li{
                                        font-size: 16px;
                                        font-family: Roboto, Arial, sans-serif;
                                        color: #202124;
                                        background-color: #ffffff;
                                        }
                                        .code {
                                        text-align: center;
                                        font-size: 32px;
                                        font-weight: bold;
                                        letter-spacing: 6px;
                                        color: #28A745;
                                        margin: 20px 0;
                                        }
                                          </style>
                                        </head>
                                        <body>
                                        
                                          <p>Bonjour " . $prenom . " " . $nom . ",</p>
                                        
                                          <p>Vous venez de créer votre compte sur l’ENT du <strong>Groupe Scolaire Jean de la Fontaine (GSJLF)</strong>.</p>
                                        
                                          <p>Pour activer votre compte, veuillez utiliser le code d’activation ci-dessous :</p>
                                        
                                          <div class='code'>$codeActivation</div>
                                        
                                          <p>Ce code vous sera demandé afin de confirmer votre identité et finaliser l’activation de votre compte.</p>
                                        <p>Veuillez utiliser le lien suivant pour accéder à la page d’activation : <a href='$link'>Activer mon compte</a></p>
                                          <p><strong>Important :</strong> ce code est valable pour une durée limitée. Pour des raisons de sécurité, ne le partagez avec personne.</p>
                                        
                                          <p>Une fois votre compte activé, vous pourrez :</p>
                                          <ul>
                                            <li>Accéder à votre espace de travail</li>
                                            <li>Consulter vos informations professionnelles</li>
                                            <li>Utiliser les services internes de l’établissement</li>
                                          </ul>
                                        <p>
                                        Si vous n’êtes pas à l’origine de cette demande, veuillez ignorer cet email ou contacter immédiatement le service informatique à :
                                        <a href=\"mailto:criat@uahb.sn\">criat@uahb.sn</a>.
                                        </p>
                                          <p>Cordialement,<br>
                                          Le service d'informatique<br>
                                          Groupe Scolaire Jean de la Fontaine</p>
                                        
                                        </body>
                                        </html>";
                                        // Envoyer l'e-mail
                                        $emailSent = $authController->sendEmail($email, $prenom, "Activez votre compte maintenant !", $message);

                                        if (!$emailSent) {
                                            $bd->rollBack();

                                            echo "erreurMail";
                                            die;
                                        } else {

                                            $bd->commit();
                                            echo "succès" . $authController->tokenencrypt($matricule);
                                            die;

                                        }

                                    } else {
                                        $bd->rollBack();
                                        echo "erreur";
                                        die;
                                    }


                                } else {
                                    echo "erreur";
                                    die;
                                }


                            } else {

                                echo "finContrat";
                                die;

                            }


                        } else {
                            echo "pasContrat";
                            die;
                        }


                    } else {
                        echo "matriculeExistsPas";
                        die;

                    }

                } else {
                    echo "dejaCompte";
                    die;

                }

            } catch (Exception $e) {
                $bd->rollBack();
                echo "erreur";
                die;
            }


        } else {
            echo "champsObligatoire";
            die;
        }
        break;

    case 2:


        if (!empty($_POST['email']) && !empty($_POST['password'])) {

            try {
                $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bd->beginTransaction();


                date_default_timezone_set('Africa/Dakar');

                $email = valid_donnees($_POST['email']);
                $password = valid_donnees($_POST['password']);


                $data = [
                    'email' => $email
                ];

                $sql = "SELECT * FROM utilisateurs WHERE email=:email";
                $stmt = $bd->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if ($result) {


                    if ($result->statutActivation == 1) {

                        if (password_verify(valid_donnees($password), $result->password) == 1) {


                            echo "succès";
                            die;

                        } else {
                            echo "pasCompte";
                            die;

                        }


                    } else {
                        echo "compteInactive";
                        die;
                    }

                } else {
                    echo "pasCompte";
                    die;

                }

            } catch (Exception $e) {
                $bd->rollBack();
                echo "erreur" . $e;
                die;
            }


        } else {
            echo "champsObligatoire";
            die;
        }
        break;
    case 3:
        if (!empty($_POST['matricule'])) {



            try {
                $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bd->beginTransaction();



                date_default_timezone_set('Africa/Dakar');

                $matricule = valid_donnees($_POST['matricule']);
                $date_jour = date('d/m/Y H:i:s');
                $data = [
                    'matricule' => $matricule
                ];

                $sql = "SELECT * FROM utilisateurs WHERE matricule=:matricule";
                $stmt = $bd->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if ($result) {

                    $email = $result->email;
                    $jourCreation = dateFranc($result->dateCreation);
                    $tempsExpire = "24 h";

                    if ($result->statutActivation == 1) {

                        $statut = 1;

                    } else {

                        if (comparerDate($result->dateEnvoiCodeValidation)) {
                            echo "erreur";
                            die;
                        } else {
//                       renvoyer code

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


                                $identifiant = $result_perso->identifiant;
                                $idEtatCivil = $result_perso->idEtatCivil;
                                $prenom = ucwords($result_perso->prenom);
                                $nom = $authController->fctRetirerAccents(mb_strtoupper($result_perso->nom));


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

                                    if ($authController->comparerDate($finContrat)) {


                                        $dateCreation = new DateTime();
                                        $dateCreation = $dateCreation->format('Y-m-d H:i:s');
                                        $password = password_hash(valid_donnees($_POST['password']), PASSWORD_DEFAULT, ['cost' => 5]);

                                        $codeActivation = $authController->genererCode6Chiffres();
                                        $codeActivation_encrypt = $authController->tokenencrypt($codeActivation);

                                        $dataCandidat = [
                                            'matricule' => valid_donnees($_POST['matricule']),
                                            'statutActivation' => 0,
                                            'codeActivation' => $codeActivation_encrypt,
                                            'dateEnvoiCodeValidation' => $dateCreation,
                                        ];

                                        $sql = "UPDATE utilisateurs 
                                                    SET
                                                        statutActivation = :statutActivation,
                                                        codeActivation = :codeActivation,
                                                        dateEnvoiCodeValidation = :dateEnvoiCodeValidation
                                                    WHERE matricule = :matricule";

                                        $stmt = $bd->prepare($sql);
                                        $tmpStmt = $stmt->execute($dataCandidat);

                                        if ($tmpStmt == 1) {


                                            $table = "utilisateurs";
                                            $motif = "Renvoyer un nouveau code d'activation";
                                            $dateEnregistrement = new DateTime();
                                            $dateEnregistrement = $dateEnregistrement->format('Y-m-d H:i:s');
                                            $dataHistorique = [
                                                'identifiant' => $identifiant,
                                                'matricule' => $matricule,
                                                'tableHistorique' => $table,
                                                'motif' => $motif,
                                                'idEtatCivil' => $idEtatCivil,
                                                'dateEnregistremenent' => $dateEnregistrement,
                                            ];
                                            $sqlHistorique = "INSERT INTO auth_personnel_historiques(identifiant,matricule,tableHistorique,motif,idEtatCivil,dateEnregistremenent) VALUES (:identifiant,:matricule,:tableHistorique,:motif,:idEtatCivil,:dateEnregistremenent)";
                                            $stmtHistorique = $bd->prepare($sqlHistorique);
                                            $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);

                                            if ($tmpStmtHistorique == 1) {

                                                $link = "/personnel/activate-account/" . $authController->tokenencrypt($matricule);

                                                $message = "<html>
<head>
  <title>Code d'activation – Demande d'admission en ligne</title>
  <style>
p{
font-size: 16px;
font-family: Roboto, Arial, sans-serif;
color: #202124;
background-color: #ffffff;
}
ul, li{
font-size: 16px;
font-family: Roboto, Arial, sans-serif;
color: #202124;
background-color: #ffffff;
}
.code {
text-align: center;
font-size: 32px;
font-weight: bold;
letter-spacing: 6px;
color: #28A745;
margin: 20px 0;
}
  </style>
</head>
<body>

  <p>Bonjour " . $prenom . " " . $nom . ",</p>

  <p>Vous venez de créer votre compte sur l’ENT du <strong>Groupe Scolaire Jean de la Fontaine (GSJLF)</strong>.</p>

  <p>Pour activer votre compte, veuillez utiliser le code d’activation ci-dessous :</p>

  <div class='code'>$codeActivation</div>

  <p>Ce code vous sera demandé afin de confirmer votre identité et finaliser l’activation de votre compte.</p>
                                        <p>Veuillez utiliser le lien suivant pour accéder à la page d’activation : <a href='$link'>Activer mon compte</a></p>

  <p><strong>Important :</strong> ce code est valable pour une durée limitée. Pour des raisons de sécurité, ne le partagez avec personne.</p>

  <p>Une fois votre compte activé, vous pourrez :</p>
  <ul>
    <li>Accéder à votre espace de travail</li>
    <li>Consulter vos informations professionnelles</li>
    <li>Utiliser les services internes de l’établissement</li>
  </ul>
<p>
Si vous n’êtes pas à l’origine de cette demande, veuillez ignorer cet email ou contacter immédiatement le service informatique à :
<a href=\"mailto:criat@uahb.sn\">criat@uahb.sn</a>.
</p>
  <p>Cordialement,<br>
  Le service d'informatique<br>
  Groupe Scolaire Jean de la Fontaine</p>

</body>
</html>";
                                                // Envoyer l'e-mail
                                                $emailSent = $authController->sendEmail($email, $prenom, "Activez votre compte maintenant !", $message);

                                                if (!$emailSent) {
                                                    $bd->rollBack();

                                                    echo "erreurMail";
                                                    die;
                                                } else {

                                                    $bd->commit();
                                                    echo "succès" . $authController->tokenencrypt($matricule);
                                                    die;

                                                }

                                            } else {
                                                $bd->rollBack();
                                                echo "erreur";
                                                die;
                                            }


                                        } else {
                                            echo "erreur";
                                            die;
                                        }


                                    } else {

                                        echo "erreur";
                                        die;

                                    }


                                } else {
                                    echo "pasContrat";
                                    die;
                                }


                            } else {
                                echo "erreur";
                                die;

                            }
//                       fin renvoyer code

                        }

                    }

//            $statut = 0;
                } else {
                    echo "erreur";
                    die;
                }

            }catch (Exception $e) {
                $bd->rollBack();
                echo "erreur" . $e;
                die;
            }


        } else {
            echo "erreur";
            die;
        }
        break;
    case 4 :


        if (!empty($_POST['matricule']) && !empty($_POST['code'])) {



            try {
                $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bd->beginTransaction();



                date_default_timezone_set('Africa/Dakar');

                $matricule = valid_donnees($_POST['matricule']);
                $code = valid_donnees($_POST['code']);

                $date_jour = date('d/m/Y H:i:s');
                $data = [
                    'matricule' => $matricule
                ];

                $sql = "SELECT * FROM utilisateurs WHERE matricule=:matricule";
                $stmt = $bd->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if ($result) {


                    if ($result->statutActivation != 1) {

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


                            $identifiant = $result_perso->identifiant;
                            $idEtatCivil = $result_perso->idEtatCivil;
                            $prenom = ucwords($result_perso->prenom);
                            $nom = $authController->fctRetirerAccents(mb_strtoupper($result_perso->nom));


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


                                if ($authController->comparerDate($finContrat)) {


                                    if ($result->codeActivation == $authController->tokenencrypt($code)) {

                                        $dateCreation = new DateTime();
                                        $dateCreation = $dateCreation->format('Y-m-d H:i:s');

                                        $codeActivation = $authController->genererCode6Chiffres();
                                        $codeActivation_encrypt = $authController->tokenencrypt($codeActivation);

                                        $dataCandidat = [
                                            'matricule' => valid_donnees($_POST['matricule']),
                                            'statutActivation' => 1,
                                            'dateActivation' => $dateCreation,
                                        ];

                                        $sql = "UPDATE utilisateurs 
        SET
            statutActivation = :statutActivation,
            dateActivation = :dateActivation
        WHERE matricule = :matricule";

                                        $stmt = $bd->prepare($sql);
                                        $tmpStmt = $stmt->execute($dataCandidat);

                                        if ($tmpStmt == 1) {


                                            $table = "utilisateurs";
                                            $motif = "Activation du compte";
                                            $dateEnregistrement = new DateTime();
                                            $dateEnregistrement = $dateEnregistrement->format('Y-m-d H:i:s');
                                            $dataHistorique = [
                                                'identifiant' => $identifiant,
                                                'matricule' => $matricule,
                                                'tableHistorique' => $table,
                                                'motif' => $motif,
                                                'idEtatCivil' => $idEtatCivil,
                                                'dateEnregistremenent' => $dateEnregistrement,
                                            ];
                                            $sqlHistorique = "INSERT INTO auth_personnel_historiques(identifiant,matricule,tableHistorique,motif,idEtatCivil,dateEnregistremenent) VALUES (:identifiant,:matricule,:tableHistorique,:motif,:idEtatCivil,:dateEnregistremenent)";
                                            $stmtHistorique = $bd->prepare($sqlHistorique);
                                            $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);

                                            if ($tmpStmtHistorique == 1) {

                                                $bd->commit();
                                                echo "succès";
                                                die;

                                            } else {
                                                $bd->rollBack();
                                                echo "erreur";
                                                die;
                                            }


                                        } else {
                                            echo "erreur";
                                            die;
                                        }


                                    } else {
                                        echo "codeIncorrect";
                                        die;
                                    }


                                } else {

                                    echo "finContrat";
                                    die;

                                }


                            } else {
                                echo "pasContrat";
                                die;
                            }


                        } else {
                            echo "matriculeExistsPas";
                            die;

                        }

                    } else {
                        echo "dejaActive";
                        die;
                    }


                } else {
                    echo "erreur";
                    die;
                }



            }catch (Exception $e) {
                $bd->rollBack();
                echo "erreur";
                die;
            }

        } else {
            echo "erreur";
            die;
        }
        break;
    case 5 :


        if (!empty($_POST['matricule']) && !empty($_POST['email'])) {

            try {
                $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bd->beginTransaction();


                date_default_timezone_set('Africa/Dakar');

                $email = valid_donnees($_POST['email']);
                $matricule = valid_donnees($_POST['matricule']);


                $data = [
                    'email' => $email
                ];

                $sql = "SELECT * FROM utilisateurs WHERE email=:email";
                $stmt = $bd->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if ($result) {


                    if ($result->statutActivation == 1) {

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


                            $identifiant = $result_perso->identifiant;
                            $idEtatCivil = $result_perso->idEtatCivil;
                            $prenom = ucwords($result_perso->prenom);
                            $nom = $authController->fctRetirerAccents(mb_strtoupper($result_perso->nom));


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

                                if ($authController->comparerDate($finContrat)) {


                                    $dateCreation = new DateTime();
                                    $dateCreation = $dateCreation->format('Y-m-d H:i:s');
                                    $password = password_hash(valid_donnees($_POST['password']), PASSWORD_DEFAULT, ['cost' => 5]);

                                    $codeActivation = $authController->genererCode6Chiffres();
                                    $codeActivation_encrypt = $authController->tokenencrypt($codeActivation);

                                    $dataCandidat = [
                                        'matricule' => valid_donnees($_POST['matricule']),
                                        'statutActivation' => 0,
                                        'codeActivation' => $codeActivation_encrypt,
                                        'dateEnvoiCodeValidation' => $dateCreation,
                                    ];

                                    $sql = "UPDATE utilisateurs 
                                                    SET
                                                        statutActivation = :statutActivation,
                                                        codeActivation = :codeActivation,
                                                        dateEnvoiCodeValidation = :dateEnvoiCodeValidation
                                                    WHERE matricule = :matricule";

                                    $stmt = $bd->prepare($sql);
                                    $tmpStmt = $stmt->execute($dataCandidat);

                                    if ($tmpStmt == 1) {


                                        $table = "utilisateurs";
                                        $motif = "Renvoyer un nouveau code d'activation";
                                        $dateEnregistrement = new DateTime();
                                        $dateEnregistrement = $dateEnregistrement->format('Y-m-d H:i:s');
                                        $dataHistorique = [
                                            'identifiant' => $identifiant,
                                            'matricule' => $matricule,
                                            'tableHistorique' => $table,
                                            'motif' => $motif,
                                            'idEtatCivil' => $idEtatCivil,
                                            'dateEnregistremenent' => $dateEnregistrement,
                                        ];
                                        $sqlHistorique = "INSERT INTO auth_personnel_historiques(identifiant,matricule,tableHistorique,motif,idEtatCivil,dateEnregistremenent) VALUES (:identifiant,:matricule,:tableHistorique,:motif,:idEtatCivil,:dateEnregistremenent)";
                                        $stmtHistorique = $bd->prepare($sqlHistorique);
                                        $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);

                                        if ($tmpStmtHistorique == 1) {

                                            $link = "/personnel/activate-account/" . $authController->tokenencrypt($matricule);

                                            $message = "<html>
<head>
  <title>Code d'activation – Demande d'admission en ligne</title>
  <style>
p{
font-size: 16px;
font-family: Roboto, Arial, sans-serif;
color: #202124;
background-color: #ffffff;
}
ul, li{
font-size: 16px;
font-family: Roboto, Arial, sans-serif;
color: #202124;
background-color: #ffffff;
}
.code {
text-align: center;
font-size: 32px;
font-weight: bold;
letter-spacing: 6px;
color: #28A745;
margin: 20px 0;
}
  </style>
</head>
<body>

  <p>Bonjour " . $prenom . " " . $nom . ",</p>

  <p>Vous venez de créer votre compte sur l’ENT du <strong>Groupe Scolaire Jean de la Fontaine (GSJLF)</strong>.</p>

  <p>Pour activer votre compte, veuillez utiliser le code d’activation ci-dessous :</p>

  <div class='code'>$codeActivation</div>

  <p>Ce code vous sera demandé afin de confirmer votre identité et finaliser l’activation de votre compte.</p>
                                        <p>Veuillez utiliser le lien suivant pour accéder à la page d’activation : <a href='$link'>Activer mon compte</a></p>

  <p><strong>Important :</strong> ce code est valable pour une durée limitée. Pour des raisons de sécurité, ne le partagez avec personne.</p>

  <p>Une fois votre compte activé, vous pourrez :</p>
  <ul>
    <li>Accéder à votre espace de travail</li>
    <li>Consulter vos informations professionnelles</li>
    <li>Utiliser les services internes de l’établissement</li>
  </ul>
<p>
Si vous n’êtes pas à l’origine de cette demande, veuillez ignorer cet email ou contacter immédiatement le service informatique à :
<a href=\"mailto:criat@uahb.sn\">criat@uahb.sn</a>.
</p>
  <p>Cordialement,<br>
  Le service d'informatique<br>
  Groupe Scolaire Jean de la Fontaine</p>

</body>
</html>";
                                            // Envoyer l'e-mail
                                            $emailSent = $authController->sendEmail($email, $prenom, "Activez votre compte maintenant !", $message);

                                            if (!$emailSent) {
                                                $bd->rollBack();

                                                echo "erreurMail";
                                                die;
                                            } else {

                                                $bd->commit();
                                                echo "succès" . $authController->tokenencrypt($matricule);
                                                die;

                                            }

                                        } else {
                                            $bd->rollBack();
                                            echo "erreur";
                                            die;
                                        }


                                    } else {
                                        echo "erreur";
                                        die;
                                    }


                                } else {

                                    echo "erreur";
                                    die;

                                }


                            } else {
                                echo "pasContrat";
                                die;
                            }


                        } else {
                            echo "erreur";
                            die;

                        }

                    } else {
                        echo "compteInactive";
                        die;
                    }

                } else {
                    echo "pasCompte";
                    die;

                }

            } catch (Exception $e) {
                $bd->rollBack();
                echo "erreur" . $e;
                die;
            }


        } else {
            echo "champsObligatoire";
            die;
        }
        break;


    default :
        echo "erreur";
        die;


}
