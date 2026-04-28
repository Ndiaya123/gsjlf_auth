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

class authController extends BD
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





    function genererCode6Chiffres() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }



}


$bd  = new BD();
$bd  = $bd ->connect();
$authController = new authController();
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
                $bd ->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bd ->beginTransaction();


            date_default_timezone_set('Africa/Dakar');
            $date_jour =  date('d/m/Y H:i:s');

            $matricule = valid_donnees($_POST['matricule']);
            $email = valid_donnees($_POST['email']);
            $password = valid_donnees($_POST['password']);
            $confirmPassword = valid_donnees($_POST['confirm-password']);
            $cgu = 1;

            if($password != $confirmPassword){

                echo "pasCorrespondantPWD";
                die;

            }


            $data = [
                'matricule' => $matricule
            ];

            $sql = "SELECT * FROM utilisateurs WHERE matricule=:matricule";
            $stmt = $bd ->prepare($sql);
            $stmt->execute($data);
            $result = $stmt->fetch(PDO::FETCH_OBJ);

            if(!$result)
            {

                $data_perso = [
                    'matricule' =>  $matricule
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
                $stmt_perso = $bd ->prepare($sql_perso);
                $stmt_perso->execute($data_perso);
                $result_perso = $stmt_perso->fetch(PDO::FETCH_OBJ);

                if($result_perso) {

                    if($email != $result_perso->email)
                    {
echo "pasCorrespondantEmail";
die;
                    }

                    $identifiant = $result_perso->identifiant;
                    $idEtatCivil = $result_perso->idEtatCivil;
                    $prenom = ucwords($result_perso->prenom);
                    $nom = $authController->fctRetirerAccents(mb_strtoupper($result_perso->nom));


                    $data_perso_contrat = [
                        'matricule' =>  $matricule,
                        'idTypeStatutContrat' => 1,

                    ];
                    $sql_perso_contrat = "SELECT * FROM contrat WHERE matricule=:matricule AND idTypeStatutContrat=:idTypeStatutContrat";
                    $stmt_perso_contrat = $bd ->prepare($sql_perso_contrat);
                    $stmt_perso_contrat->execute($data_perso_contrat);
                    $result_perso_contrat = $stmt_perso_contrat->fetch(PDO::FETCH_OBJ);

                    if($result_perso_contrat) {

                        $debutContrat = $result_perso_contrat->dateDebutContrat;
                        $finContrat = $result_perso_contrat->dateFinContrat;

                        if($finContrat < $date_jour) {


                            $dateCreation = new DateTime();
                            $dateCreation = $dateCreation->format('Y-m-d H:i:s');
                            $password = password_hash(valid_donnees($_POST['password']), PASSWORD_DEFAULT, ['cost' => 5]);


                            $codeActivation = $authController->genererCode6Chiffres();
                            $codeActivation_encrypt= $authController->tokenencrypt($codeActivation);

                            $dataCandidat = [
                                'identifiant' => $identifiant,
                                'matricule' =>  valid_donnees($_POST['matricule']),
                                'email' => strtolower(valid_donnees($_POST['email'])),
                                'password' =>  $password,
                                'cgu' =>$cgu,
                                'dateCreation' => $dateCreation,
                                'statutUtilisateur' => 0,
                                'statutActivation' => 0,
                                'codeActivation' => $codeActivation_encrypt,
                                'idEtatCivil' => $idEtatCivil
                            ];
                            $sql = "INSERT INTO utilisateurs(identifiant,matricule,email,password,cgu,dateCreation,statutUtilisateur,statutActivation,codeActivation,idEtatCivil) VALUES(:identifiant,:matricule,:email,:password,:cgu,:dateCreation,:statutUtilisateur,:statutActivation,:codeActivation,:idEtatCivil)";
                            $stmt = $bd ->prepare($sql);
                            $tmpStmt = $stmt->execute($dataCandidat);

                            if ($tmpStmt == 1) {

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
echo"erreurMail";
                                die;
                                } else {
                                    $table = "utilisateurs";
                                    $motif = "Création de compte";
                                    $dateEnregistrement = new DateTime();
                                    $dateEnregistrement = $dateEnregistrement->format('Y-m-d H:i:s');
                                    $dataHistorique = [
                                        'identifiant' =>$identifiant,
                                        'matricule' => $matricule,
                                        'tableHistorique' => $table,
                                        'motif' => $motif,
                                        'idEtatCivil' => $idEtatCivil,
                                        'dateEnregistremenent' => $dateEnregistrement,
                                    ];
                                    $sqlHistorique = "INSERT INTO auth_personnel_historiques(identifiant,matricule,tableHistorique,motif,idEtatCivil,dateEnregistremenent) VALUES (:identifiant,:matricule,:tableHistorique,:motif,:idEtatCivil,:dateEnregistremenent)";
                                    $stmtHistorique = $bd ->prepare($sqlHistorique);
                                    $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);

                                    if ($tmpStmtHistorique == 1) {
                                        $bd ->commit();
                                        echo "succès";
                                        die;
                                    } else {
                                        $bd ->rollBack();
                                        echo "erreur";
                                        die;
                                    }
                                }

                            } else {
                                echo "erreur";
                                die;
                            }


                        }else
                        {

                            echo "finContrat";
                            die;

                        }



                    }else
                    {
                        echo "pasContrat";
                        die;
                    }


                }else
                {
                    echo "matriculeExistsPas";
                    die;

                }

                }else
            {
                echo "dejaCompte";
                die;

            }

            } catch (Exception $e) {
                $bd ->rollBack();
                echo "erreur".$e;
                die;
            }




        } else {
            echo "champsObligatoire";
            die;
        }
        break;

    case 2:
        if (!empty($_POST['email'])) {
            if (!empty($_POST['password'])) {
                if (count($authController->verifierEmail(valid_donnees($_POST['email']))) == 1) {

                    $listes = $authController->verifierEmail(valid_donnees($_POST['email']));

                    $tmpCon = 0;
                    $tmpStatut = '';
                    $tmpPrenom = '';
                    $tmpNom = '';
                    $tmpPhoto = '';
                    $tmpEmail = '';
                    $tmpMatricule = '';
                    $tmpbloquer = '';
                    $tmpStatutEtudiant = '';

                    foreach ($listes as $tmp) {
                        if (password_verify(valid_donnees($_POST['password']), $tmp->password) == 1) {
                            $tmpCon = 1;
                            $tmpStatut = $tmp->statut;
                            $tmpPrenom = $tmp->prenom;
                            $tmpNom = $tmp->nom;
                            $tmpId = $tmp->id;
                            $tmpPhoto = $tmp->photo;
                            $tmpEmail = $tmp->email;
                            $tmpMatricule = $tmp->matricule;
                            $tmpBloquer = $tmp->bloquer;
                            $tmpPaysNaissance = $tmp->paysNaissance;
                        }
                    }

                    $dernnierAnneeInscription = $authController->verifierDerniereInscription($tmpMatricule);
                    $anneeEnCours = $authController->anneeEnCours();

                    if ($dernnierAnneeInscription == $anneeEnCours) {
                        $tmpStatutEtudiant = 1;
                    } else {
                        $tmpStatutEtudiant = 2;
                    }
                    if ($tmpCon === 1) {
                        $_SESSION['tmpId'] = $tmpId;
                        $_SESSION['tmpPrenom'] = $tmpPrenom;
                        $_SESSION['tmpNom'] = $tmpNom;
                        $_SESSION['tmpPhoto'] = $tmpPhoto;
                        $_SESSION['tmpMatricule'] = $tmpMatricule;
                        $_SESSION['tmpPaysNaissance'] = $tmpPaysNaissance;
                        $_SESSION['tmpStatutEtudiant'] = $tmpStatutEtudiant;

                        if ($tmpBloquer == 0) {

                            // $_SESSION['isValidEtudiant'] = true;
                            $_SESSION['tmpEmail'] = $tmpEmail;

                            if ($tmpStatut == 0) {
                                echo "compteDesactive";
                                die;
                            } else {
                                $_SESSION['isValidEtudiant'] = true;
                                try {
                                    $bd ->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    $bd ->beginTransaction();

                                    $last_activity = date("Y-m-d H:i:s", STRTOTIME(date('h:i:sa')));
                                    $data = [
                                        'matricule' => $tmpMatricule,
                                        'last_activity' => $last_activity,
                                    ];
                                    $sql = "UPDATE scolarite_utilisateurs_etudiants SET last_activity=:last_activity WHERE matricule=:matricule";
                                    $stmt = $bd ->prepare($sql);
                                    $tmpStmt = $stmt->execute($data);

                                    if ($tmpStmt) {

                                        $table = "scolarite_utilisateurs_etudiants";
                                        $motif = "Tentative de connexion";
                                        date_default_timezone_set('Africa/Dakar');
                                        $dateEnregistrement = new DateTime();
                                        $dateEnregistrement = $dateEnregistrement->format('Y-m-d H:i:s');
                                        $dataHistorique = [
                                            'matriculeEtudiant' => $tmpMatricule,
                                            'tableHistorique' => $table,
                                            'motif' => $motif,
                                            'idEtudiant' =>  $tmpId,
                                            'dateEnregistremenent' => $dateEnregistrement,
                                        ];
                                        $sqlHistorique = "INSERT INTO samahamapte_etudiants_historiques(matriculeEtudiant,tableHistorique,motif,idEtudiant,dateEnregistremenent) VALUES (:matriculeEtudiant,:tableHistorique,:motif,:idEtudiant,:dateEnregistremenent)";
                                        $stmtHistorique = $bd ->prepare($sqlHistorique);
                                        $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);

                                        if ($tmpStmtHistorique) {
                                            $bd ->commit();
                                            echo "succès";
                                            die;
                                        } else {
                                            $bd ->rollBack();
                                            echo "erreur";
                                            die;
                                        }
                                    } else {
                                        $bd ->rollBack();
                                        echo "erreur";
                                        die;
                                    }
                                } catch (Exception $e) {
                                    $bd ->rollBack();
                                    echo "Failed: " . $e->getMessage();
                                }
                            }
                        } else {
                            echo "compteBloquer";
                            die;
                        }
                    } else {
                        echo "emailMotPasseIncorrect";
                        die;
                    }
                } else {
                    echo "emailMotPasseIncorrect";
                    die;
                }
            } else {
                echo "passwordObligatoire";
                die;
            }
        } else {
            echo "emailObligatoire";
            die;
        }
        break;

    case 3:
        if (!empty($_POST['matricule']) && !empty($_POST['email'])) {
            if (count($authController->verifierCompte(valid_donnees($_POST['matricule']))) == 1) {
                if (count($authController->verifierMatricule(valid_donnees($_POST['matricule']))) != 0) {
                    if (count($authController->verifierEmail2(strtolower(valid_donnees($_POST['email'])))) != 0) {

                        if (count($authController->verifierEmailMatricule(valid_donnees($_POST['matricule']), valid_donnees($_POST['email']))) == 1) {
                            if (count($authController->verifierCompteBloquer(valid_donnees($_POST['matricule']))) == 0) {
                                if (count($authController->verifierCompteActive(valid_donnees($_POST['matricule']))) == 1) {

                                    date_default_timezone_set('Africa/Dakar');
                                    $dateCreation = new DateTime();
                                    $dateCreation = $dateCreation->format('Y-m-d H:i:s');
                                    $tmpEncryptDateCreation = $authController->tokenencrypt($dateCreation);
                                    $tmpEncryptMatricule = $authController->tokenencrypt(valid_donnees($_POST['matricule']));
                                    $tmpEncryptEmai = $authController->tokenencrypt(valid_donnees($_POST['email']));
                                    $tmpEncryptLien = 'https://samahampate.uahb.sn/valider-compter/' . $tmpEncryptDateCreation . '/' . $tmpEncryptMatricule . '/' . $tmpEncryptEmai;


                                    $infosEtudiant = $authController->verifierMatricule(valid_donnees($_POST['matricule']));
                                    $mail = new PHPMailer(true);
                                    $mail->IsSMTP();
                                    $mail->Mailer = "smtp";

                                    try {

                                        $mail->isSMTP();
                                        $mail->Host = 'smtp.googlemail.com';
                                        $mail->SMTPAuth = true;
                                        $mail->Username = 'criat@uahb.sn';
                                        $mail->Password = 'tevklroalsmwpjsl';
                                        $mail->SMTPSecure = 'ssl';
                                        $mail->Port = 465;

                                        $mail->addAddress(valid_donnees($_POST['email']), '');
                                        $mail->isHTML(true);
                                        $mail->Subject = utf8_decode('Activation de ton compte Sama hampaté');
                                        $mail->Body = '<p style="font-size:1.5em;color:black;">Bonjour <strong>' . $infosEtudiant['0']->prenom . ' ' . $infosEtudiant['0']->nom . '</strong></p>' . "\n" . '<p style="font-size:1.5em;;color:black;">Vous avez demande l\'accès à la plateforme Sama hampaté.</p>' . "\n" . '<p style="font-size:1.5em;color:black;">Merci de cliquer sur le lien pour activer votre compte de connexion á l\'espace étudiant </span>: <a href="' . $tmpEncryptLien . '" target="_blank">lien d\'activation du compte</a> <p>' . "\n" . '<p style="font-size:1.5em; color:red;">' . "\n" . '<p style="font-size:1.5em;color:black;">Pour toute assistance, contactez-nous à samahampate@uahb.sn. </p>' . "\n" . '<p style="font-size:1.5em;color:black;">Cordialement.</p>' . "\n" . '<p style="font-size:1.5em;color:black;">L\'équipe de Sama hampaté.<p>';
                                        if ($mail->send()) {

                                            $table = "VIDE";
                                            $motif = "Renvoyer l'e-mail d'Activation.";
                                            date_default_timezone_set('Africa/Dakar');
                                            $dateEnregistrement = new DateTime();
                                            $dateEnregistrement = $dateEnregistrement->format('Y-m-d H:i:s');
                                            $dataHistorique = [
                                                'matriculeEtudiant' => $infosEtudiant['0']->matricule,
                                                'tableHistorique' => $table,
                                                'motif' => $motif,
                                                'idEtudiant' =>  $infosEtudiant['0']->id,
                                                'dateEnregistremenent' => $dateEnregistrement,
                                            ];
                                            $sqlHistorique = "INSERT INTO samahamapte_etudiants_historiques(matriculeEtudiant,tableHistorique,motif,idEtudiant,dateEnregistremenent) VALUES (:matriculeEtudiant,:tableHistorique,:motif,:idEtudiant,:dateEnregistremenent)";
                                            $stmtHistorique = $bd ->prepare($sqlHistorique);
                                            $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);

                                            echo "succès";
                                            die;
                                        } else {
                                            echo 'erreurMail';
                                            die;
                                        }
                                    } catch (Exception $e) {
                                        echo 'erreurMail';
                                        die;
                                    }
                                } else {
                                    echo "compteActive";
                                    die;
                                }
                            } else {
                                echo "compteBloquer";
                                die;
                            }
                        } else {
                            echo "matriculeEmailCorrespondPas";
                            die;
                        }
                    } else {
                        echo "pasDeMail";
                        die;
                    }
                } else {
                    echo "pasDeMatricule";
                    die;
                }
            } else {
                echo "pasDeCompte";
                die;
            }
        } else {
            echo "champsObligatoire";
            die;
        }
        break;

    case 4:
        if (!empty(valid_donnees($_POST['matricule'])) && !empty(valid_donnees($_POST['email']))) {

            if (count($authController->verifierCompte(valid_donnees($_POST['matricule']))) != 0) {
                if (count($authController->verifierMatricule(valid_donnees($_POST['matricule']))) != 0) {
                    if (count($authController->verifierEmail2(strtolower(valid_donnees($_POST['email'])))) != 0) {
                        if (count($authController->verifierEmailMatricule(valid_donnees($_POST['matricule']), valid_donnees($_POST['email']))) == 1) {
                            if (count($authController->verifierCompteActive(strtolower(valid_donnees($_POST['matricule'])))) != 1) {
                                if (count($authController->verifierCompteBloquer(strtolower(valid_donnees($_POST['matricule'])))) != 1) {

                                    date_default_timezone_set('Africa/Dakar');
                                    $dateCreation = new DateTime();
                                    $dateCreation = $dateCreation->format('Y-m-d H:i:s');

                                    $tmpEncryptDateCreation = $authController->tokenencrypt($dateCreation);
                                    $tmpEncryptMatricule = $authController->tokenencrypt(valid_donnees($_POST['matricule']));
                                    $tmpEncryptEmai = $authController->tokenencrypt(valid_donnees($_POST['email']));

                                    $tmpEncryptLien = 'https://samahampate.uahb.sn/changer-mot-de-passe/' . $tmpEncryptDateCreation . '/' . $tmpEncryptMatricule . '/' . $tmpEncryptEmai;

                                    $infosEtudiant = $authController->verifierMatricule(valid_donnees($_POST['matricule']));
                                    $mail = new PHPMailer(true);
                                    $mail->IsSMTP();
                                    $mail->Mailer = "smtp";

                                    try {
                                        $mail->isSMTP();
                                        $mail->Host = 'smtp.googlemail.com';
                                        $mail->SMTPAuth = true;
                                        $mail->Username = 'criat@uahb.sn';
                                        $mail->Password = 'tevklroalsmwpjsl';
                                        $mail->SMTPSecure = 'ssl';
                                        $mail->Port = 465;


                                        $mail->addAddress(valid_donnees($_POST['email']), '');
                                        $mail->isHTML(true);
                                        $mail->Subject = utf8_decode('Réinitialisez votre mot de passe');
                                        $mail->Body = '<p style="font-size:1.5em;color:black;">Bonjour <strong>' . $infosEtudiant['0']->prenom . ' ' . $infosEtudiant['0']->nom . '</strong></p>' . "\n" . '<p style="font-size:1.5em;color:black;"><span style="color:red;">Merci de cliquer sur le lien pour réinitialiser votre mot de passe  </span>: <a href="' . $tmpEncryptLien . '" target="_blank">lien</a> </p>' . "\n" . '<p style="font-size:1.5em; color:red;">' . "\n" . '<p style="font-size:1.5em;color:black;">Pour toute assistance, contactez-nous à samahampate@uahb.sn. </p>' . "\n" . '<p style="font-size:1.5em;color:black;">Cordialement.</p>' . "\n" . '<p style="font-size:1.5em;color:black;">L\'équipe de Sama hampaté</p>';

                                        if ($mail->send()) {
                                            $table = "VIDE";
                                            $motif = "Réinitialisez votre mot de passe.";
                                            date_default_timezone_set('Africa/Dakar');
                                            $dateEnregistrement = new DateTime();
                                            $dateEnregistrement = $dateEnregistrement->format('Y-m-d H:i:s');
                                            $dataHistorique = [
                                                'matriculeEtudiant' => $infosEtudiant['0']->matricule,
                                                'tableHistorique' => $table,
                                                'motif' => $motif,
                                                'idEtudiant' =>  $infosEtudiant['0']->id,
                                                'dateEnregistremenent' => $dateEnregistrement,
                                            ];
                                            $sqlHistorique = "INSERT INTO samahamapte_etudiants_historiques(matriculeEtudiant,tableHistorique,motif,idEtudiant,dateEnregistremenent) VALUES (:matriculeEtudiant,:tableHistorique,:motif,:idEtudiant,:dateEnregistremenent)";
                                            $stmtHistorique = $bd ->prepare($sqlHistorique);
                                            $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);

                                            echo "succès";
                                            die;
                                        } else {
                                            echo "erreur";
                                            die;
                                        }
                                    } catch (Exception $e) {
                                        echo 'erreurMail';
                                        die;
                                    }
                                } else {
                                    echo "compteBloquer";
                                }
                            } else {
                                echo "compteDesactive";
                            }
                        } else {
                            echo "matriculeEmailCorrespondPas";
                            die;
                        }
                    } else {
                        echo "pasDeMail";
                        die;
                    }
                } else {
                    echo "pasDeMatricule";
                    die;
                }
            } else {
                echo "pasCompte";
                die;
            }
        } else {
            echo "champsObligatoire";
            die;
        }
        break;
    case 5:
        if (!empty($_POST['matricule']) && !empty($_POST['password']) && !empty($_POST['confirm-password'])) {

            if (valid_donnees($_POST['password']) == valid_donnees($_POST['confirm-password'])) {
                $infosEtudiant = $authController->verifierMatricule(valid_donnees($_POST['matricule']));

                if ($infosEtudiant >= 1) {
                    $password = password_hash(valid_donnees($_POST['password']), PASSWORD_DEFAULT, ['cost' => 5]);
                    $data = [
                        'matricule' => valid_donnees($_POST['matricule']),
                        'password' => $password
                    ];
                    $sql = "UPDATE scolarite_utilisateurs_etudiants SET password=:password WHERE matricule=:matricule";
                    $stmt = $bd ->prepare($sql);
                    $tmp = $stmt->execute($data);
                    if ($tmp) {
                        $table = "scolarite_utilisateurs_etudiants";
                        $motif = "Réinitialiser le mot de passe.";
                        date_default_timezone_set('Africa/Dakar');
                        $dateEnregistrement = new DateTime();
                        $dateEnregistrement = $dateEnregistrement->format('Y-m-d H:i:s');
                        $dataHistorique = [
                            'matriculeEtudiant' => $infosEtudiant['0']->matricule,
                            'tableHistorique' => $table,
                            'motif' => $motif,
                            'idEtudiant' =>  $infosEtudiant['0']->id,
                            'dateEnregistremenent' => $dateEnregistrement,
                        ];
                        $sqlHistorique = "INSERT INTO samahamapte_etudiants_historiques(matriculeEtudiant,tableHistorique,motif,idEtudiant,dateEnregistremenent) VALUES (:matriculeEtudiant,:tableHistorique,:motif,:idEtudiant,:dateEnregistremenent)";
                        $stmtHistorique = $bd ->prepare($sqlHistorique);
                        $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);

                        echo "succès";
                        die;
                    } else {
                        echo "erreur";
                        die;
                    }
                } else {
                    echo "erreur";
                    die;
                }
            } else {
                echo "pasCorrespondant";
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
