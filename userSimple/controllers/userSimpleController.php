<?php

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

$option = (!empty($_POST['option'])) ? $_POST['option'] : '';
$BASE_URL = "http://localhost/personnel/";

switch ($option) {
    case 1:

        date_default_timezone_set('Africa/Dakar');
        $dateNow = new DateTime();
        $dateNow = $dateNow->format('Y-m-d');

        if (!empty($_POST['matricule'])) {

            try {

                if (count($userSimpleController->verificationMatricule(valid_donnees($_POST['matricule']))) == 1) {

                    $identifiant = $userSimpleController->verificationMatricule(valid_donnees($_POST['matricule']))['0']->identifiant;

                    if (count($userSimpleController->verificationInfosPersonnel(valid_donnees($_POST['matricule']))) == 1) {

                        $infosPersonnel = $userSimpleController->verificationInfosPersonnel(valid_donnees($_POST['matricule']));
                        if (count($userSimpleController->returnContratEnCours($identifiant)) == 1) {

                            $returnContratEnCours = $userSimpleController->returnContratEnCours($identifiant);

                            if ($returnContratEnCours['0']->idTypeStatutContrat == 1) {
                                if ($returnContratEnCours['0']->dateFinContrat > $dateNow) {

                                    if (count($userSimpleController->infoCompteGmail($identifiant)) == 1) {



                                        $infosPosteAResponsabilite = NULL;


                                        if (count($userSimpleController->infoCompteGmail($identifiant)) == 1) {



                                            if (count($userSimpleController->infoAffectations($identifiant)) == 1) {

                                                $infoAffectations = $userSimpleController->infoAffectations($identifiant);

                                                if ($infoAffectations['0']->idUniteAdministrativeNiv1 != NULL) {

                                                    echo "erreur";
                                                    die;

                                                } else if ($infoAffectations['0']->idUniteAdministrativeNiv2 != NULL) {


                                                    $data =
                                                        [
                                                            'id' => $infoAffectations['0']->idUniteAdministrativeNiv2

                                                        ];
                                                    $stmt = $bdP->prepare("SELECT unite_administrative_niv2.idUniteAdministrativeNiv1  FROM unite_administrative_niv2 WHERE unite_administrative_niv2.id=:id");
                                                    $stmt->execute($data);
                                                    $listes = $stmt->fetchAll(PDO::FETCH_OBJ);


                                                    if (count($listes) == 1) {


                                                        $data =
                                                            [
                                                                'id' => $listes['0']->idUniteAdministrativeNiv1

                                                            ];
                                                        $stmt = $bdP->prepare("SELECT unite_administrative_niv2.id FROM unite_administrative_niv2 WHERE unite_administrative_niv2.idUniteAdministrativeNiv1=:id");
                                                        $stmt->execute($data);
                                                        $listes = $stmt->fetchAll(PDO::FETCH_OBJ);



                                                        $infosApi[] = array();
                                                        foreach ($listes as $tmp) {
                                                            // actuel

                                                            $infoPersonnelsNiveau2 = $userSimpleController->infoPersonnelsNiveau2($tmp->id);

                                                            foreach ($infoPersonnelsNiveau2 as $tmp2) {

                                                                $email = NULL;
                                                                if (count($userSimpleController->infoCompteGmail($tmp2->identifiant)) == 1) {
                                                                    $email = $userSimpleController->infoCompteGmail($tmp2->identifiant)['0']->email;
                                                                }

                                                                $grade = NULL;
                                                                $statutPoste = NULL;
                                                                if (count($userSimpleController->infoPosteAReponsabilite($tmp2->identifiant)) == 1) {
                                                                    $infosPosteAResponsabilite = "OUI";
                                                                    $grade = $userSimpleController->infoPosteAReponsabilite($tmp2->identifiant)['0']->statutGradePoste;
                                                                    $statutPoste = $userSimpleController->infoPosteAReponsabilite($tmp2->identifiant)['0']->idStatutFonction;
                                                                } else {
                                                                    $infosPosteAResponsabilite = "NON";
                                                                    $statutPoste = 3;
                                                                    $grade = NULL;
                                                                }

                                                                $sans = NULL;
                                                                //
                                                                if ($tmp2->idUniteAdministrativeNiv1 != NULL || $tmp2->idUniteAdministrativeNiv1 != "") {


                                                                    $infoUniteAdministrativeNiv1 = $userSimpleController->infoUniteAdministrativeNiv1($tmp2->idUniteAdministrativeNiv1);

                                                                    if ($infoUniteAdministrativeNiv1 != "erreur") {
                                                                        $sans = $infoUniteAdministrativeNiv1;

                                                                    } else {
                                                                        $infosApi[] = array(
                                                                            'statut' => "Error",
                                                                            'Message' => "Une erreur est survenue. Veuillez vous rapprocher de la CRIAT.",
                                                                            'option' => valid_donnees($_POST['option'])

                                                                        );
                                                                        echo json_encode($infosApi);
                                                                        die;
                                                                    }


                                                                } else if ($tmp2->idUniteAdministrativeNiv2 != NULL || $tmp2->idUniteAdministrativeNiv2 != "") {
                                                                    $infoUniteAdministrativeNiv2 = $userSimpleController->infoUniteAdministrativeNiv2($tmp2->idUniteAdministrativeNiv2);

                                                                    if ($infoUniteAdministrativeNiv2 != "erreur") {
                                                                        $sans = $infoUniteAdministrativeNiv2;

                                                                    } else {
                                                                        $infosApi[] = array(
                                                                            'statut' => "Error",
                                                                            'Message' => "Une erreur est survenue. Veuillez vous rapprocher de la CRIAT.",
                                                                            'option' => valid_donnees($_POST['option'])

                                                                        );
                                                                        echo json_encode($infosApi);
                                                                        die;
                                                                    }

                                                                } else if ($tmp2->idUniteAdministrativeNiv3 != NULL || $tmp2->idUniteAdministrativeNiv3 != "") {


                                                                    $infoUniteAdministrativeNiv3 = $userSimpleController->infoUniteAdministrativeNiv3($tmp2->idUniteAdministrativeNiv3);

                                                                    if ($infoUniteAdministrativeNiv3 != "erreur") {
                                                                        $sans = $infoUniteAdministrativeNiv3;

                                                                    } else {
                                                                        $infosApi[] = array(
                                                                            'statut' => "Error",
                                                                            'Message' => "Une erreur est survenue. Veuillez vous rapprocher de la CRIAT.",
                                                                            'option' => valid_donnees($_POST['option'])

                                                                        );
                                                                        echo json_encode($infosApi);
                                                                        die;
                                                                    }
                                                                } else {

                                                                    $infosApi[] = array(
                                                                        'statut' => "Error",
                                                                        'Message' => "Une erreur est survenue. Veuillez vous rapprocher de la CRIAT.",
                                                                        'option' => valid_donnees($_POST['option'])

                                                                    );
                                                                    echo json_encode($infosApi);
                                                                    die;

                                                                }
                                                                //

                                                                if ($tmp2->identifiant != $identifiant) {
                                                                    $infosApi[] = array(
                                                                        'statut' => "succès",
                                                                        'Message' => "Successful",
                                                                        'option' => valid_donnees($_POST['option']),
                                                                        'matricule' => $tmp2->matricule,
                                                                        'prenom' => $tmp2->prenom,
                                                                        'nom' => $tmp2->nom,
                                                                        'email' => $email,
                                                                        'qualification' => $tmp2->qualification,
                                                                        'idQualification' => $tmp2->idQualification,
                                                                        'statutPoste' => $statutPoste,
                                                                        'sans' => $sans,
                                                                        'grade' => $grade,
                                                                        'idUniteAdministrativeNiv1' => $tmp2->idUniteAdministrativeNiv1,
                                                                        'idUniteAdministrativeNiv2' => $tmp2->idUniteAdministrativeNiv2,
                                                                        'idUniteAdministrativeNiv3' => $tmp2->idUniteAdministrativeNiv3,

                                                                    );
                                                                }


                                                                $data =
                                                                    [
                                                                        'id' => $tmp->id

                                                                    ];
                                                                $stmt = $bdP->prepare("SELECT unite_administrative_niv3.id FROM unite_administrative_niv3 WHERE unite_administrative_niv3.idUniteAdministrativeNiv2=:id");
                                                                $stmt->execute($data);
                                                                $listes = $stmt->fetchAll(PDO::FETCH_OBJ);


                                                                foreach ($listes as $tmp4) {

                                                                    $infoPersonnelsNiveau3 = $userSimpleController->infoPersonnelsNiveau3($tmp4->id);
                                                                    foreach ($infoPersonnelsNiveau3 as $tmp3) {


                                                                        $email = NULL;
                                                                        if (count($userSimpleController->infoCompteGmail($tmp3->identifiant)) == 1) {
                                                                            $email = $userSimpleController->infoCompteGmail($tmp3->identifiant)['0']->email;
                                                                        }

                                                                        $grade = NULL;
                                                                        $statutPoste = NULL;
                                                                        if (count($userSimpleController->infoPosteAReponsabilite($tmp3->identifiant)) == 1) {
                                                                            $infosPosteAResponsabilite = "OUI";
                                                                            $grade = $userSimpleController->infoPosteAReponsabilite($tmp3->identifiant)['0']->statutGradePoste;
                                                                            $statutPoste = $userSimpleController->infoPosteAReponsabilite($tmp3->identifiant)['0']->idStatutFonction;
                                                                        } else {
                                                                            $infosPosteAResponsabilite = "NON";
                                                                            $statutPoste = 3;
                                                                            $grade = NULL;
                                                                        }

                                                                        $sans = NULL;
                                                                        //
                                                                        if ($tmp3->idUniteAdministrativeNiv1 != NULL || $tmp3->idUniteAdministrativeNiv1 != "") {


                                                                            $infoUniteAdministrativeNiv1 = $userSimpleController->infoUniteAdministrativeNiv1($tmp3->idUniteAdministrativeNiv1);

                                                                            if ($infoUniteAdministrativeNiv1 != "erreur") {
                                                                                $sans = $infoUniteAdministrativeNiv1;

                                                                            } else {
                                                                                $infosApi[] = array(
                                                                                    'statut' => "Error",
                                                                                    'Message' => "Une erreur est survenue. Veuillez vous rapprocher de la CRIAT.",
                                                                                    'option' => valid_donnees($_POST['option'])

                                                                                );
                                                                                echo json_encode($infosApi);
                                                                                die;
                                                                            }


                                                                        } else if ($tmp3->idUniteAdministrativeNiv2 != NULL || $tmp3->idUniteAdministrativeNiv2 != "") {
                                                                            $infoUniteAdministrativeNiv2 = $userSimpleController->infoUniteAdministrativeNiv2($tmp3->idUniteAdministrativeNiv2);

                                                                            if ($infoUniteAdministrativeNiv2 != "erreur") {
                                                                                $sans = $infoUniteAdministrativeNiv2;

                                                                            } else {
                                                                                $infosApi[] = array(
                                                                                    'statut' => "Error",
                                                                                    'Message' => "Une erreur est survenue. Veuillez vous rapprocher de la CRIAT.",
                                                                                    'option' => valid_donnees($_POST['option'])

                                                                                );
                                                                                echo json_encode($infosApi);
                                                                                die;
                                                                            }

                                                                        } else if ($tmp3->idUniteAdministrativeNiv3 != NULL || $tmp3->idUniteAdministrativeNiv3 != "") {


                                                                            $infoUniteAdministrativeNiv3 = $userSimpleController->infoUniteAdministrativeNiv3($tmp3->idUniteAdministrativeNiv3);

                                                                            if ($infoUniteAdministrativeNiv3 != "erreur") {
                                                                                $sans = $infoUniteAdministrativeNiv3;

                                                                            } else {
                                                                                $infosApi[] = array(
                                                                                    'statut' => "Error",
                                                                                    'Message' => "Une erreur est survenue. Veuillez vous rapprocher de la CRIAT.",
                                                                                    'option' => valid_donnees($_POST['option'])

                                                                                );
                                                                                echo json_encode($infosApi);
                                                                                die;
                                                                            }
                                                                        } else {

                                                                            $infosApi[] = array(
                                                                                'statut' => "Error",
                                                                                'Message' => "Une erreur est survenue. Veuillez vous rapprocher de la CRIAT.",
                                                                                'option' => valid_donnees($_POST['option'])

                                                                            );
                                                                            echo json_encode($infosApi);
                                                                            die;

                                                                        }
                                                                        //

                                                                        if ($tmp3->identifiant != $identifiant) {
                                                                            $infosApi[] = array(
                                                                                'statut' => "succès",
                                                                                'Message' => "Successful",
                                                                                'option' => valid_donnees($_POST['option']),
                                                                                'matricule' => $tmp3->matricule,
                                                                                'prenom' => $tmp3->prenom,
                                                                                'nom' => $tmp3->nom,
                                                                                'email' => $email,
                                                                                'qualification' => $tmp3->qualification,
                                                                                'idQualification' => $tmp3->idQualification,
                                                                                'statutPoste' => $statutPoste,
                                                                                'sans' => $sans,
                                                                                'grade' => $grade,
                                                                                'idUniteAdministrativeNiv1' => $tmp3->idUniteAdministrativeNiv1,
                                                                                'idUniteAdministrativeNiv2' => $tmp3->idUniteAdministrativeNiv2,
                                                                                'idUniteAdministrativeNiv3' => $tmp3->idUniteAdministrativeNiv3,

                                                                            );
                                                                        }
                                                                    }
                                                                }

                                                            }

                                                        }


//                                                        echo json_encode($infosApi);
//                                                        die;


                                                        echo '<option></option><option value="">Choisir...</option>';


                                                        $prenom = null;
                                                        $nom = null;

                                                        foreach ($infosApi as $info) {
                                                            $prenom = ucwords(mb_strtolower($info['prenom']));
                                                            $nom = $userSimpleController->fctRetirerAccents(mb_strtoupper($info['nom']));
                                                            echo '<option value="' . $info['matricule'] . '">' . $prenom .' '.$nom. '</option>';

                                                            $prenom = null;
                                                            $nom = null;

                                                        }

//            echo '<option value="vide" hidden="">VIDE</option>';

                                                        if (count($listes) > 0) {
                                                            foreach ($listes as $tmp) {
                                                            }
                                                        } else {
                                                            echo '<option value="">-- Qualification --</option>';
                                                        }

                                                        die;


                                                    } else {
                                                        echo "erreur";
                                                        die;
                                                    }



                                                } else if ($infoAffectations['0']->idUniteAdministrativeNiv3 != NULL) {


                                                    echo "erreur";
                                                    die;
//                                                    $infosApi[] = array(
//                                                        'statut' => "Error",
//                                                        'Message' => "Une erreur est survenue.",
//                                                        'option' => valid_donnees($_POST['option']),
//                                                    );
//                                                    echo json_encode($infosApi);
//                                                    die;
                                                } else {

                                                    echo "erreur";
                                                    die;
//                                                    $infosApi[] = array(
//                                                        'statut' => "Error",
//                                                        'Message' => "Une erreur est survenue. ",
//                                                        'option' => valid_donnees($_POST['option']),
//                                                    );
//                                                    echo json_encode($infosApi);
//                                                    die;
                                                }



                                            } else {


                                                echo "erreur";
                                                die;
//
//                                                $infosApi[] = array(
//                                                    'statut' => "Error",
//                                                    'Message' => "Une erreur est survenue. ",
//                                                    'option' => valid_donnees($_POST['option']),
//                                                );
//                                                echo json_encode($infosApi);
//                                                die;

                                            }

                                        } else {

                                            echo "erreur";
                                            die;
//                                            $infosApi[] = array(
//                                                'statut' => "Error",
//                                                'Message' => "Une erreur est survenue.",
//                                                'option' => valid_donnees($_POST['option']),
//                                            );
//                                            echo json_encode($infosApi);
//                                            die;

                                        }

                                    } else {

                                        echo "erreur";
                                        die;
//                                        $infosApi[] = array(
//                                            'statut' => "Error",
//                                            'Message' => "Le courriel institutionnel est introuvable. Veuillez vérifier le matricule saisi ou vous rapprocher de la CRIAT.",
//                                            'option' => valid_donnees($_POST['option'])
//
//                                        );
//                                        echo json_encode($infosApi);
//                                        die;

                                    }



                                } else {
                                    echo "erreur";
                                    die;
//                                    $infosApi[] = array(
//                                        'statut' => "Error",
//                                        'Message' => "En fin de contrat, l'employé doit se rapprocher du DRH.",
//                                        'option' => valid_donnees($_POST['option'])
//
//                                    );
//                                    echo json_encode($infosApi);
//                                    die;
                                }
                            } else {
                                echo "erreur";
                                die;
//                                $infosApi[] = array(
//                                    'statut' => "Error",
//                                    'Message' => "En fin de contrat, l'employé doit se rapprocher du DRH",
//                                    'option' => valid_donnees($_POST['option'])
//
//                                );
//                                echo json_encode($infosApi);
//                                die;
                            }
                        } else {

                            echo "erreur";
                            die;

//                            $infosApi[] = array(
//                                'statut' => "Error",
//                                'Message' => "Pas de contrat en cours, l'employé doit se rapprocher du DRH.",
//                                'option' => valid_donnees($_POST['option'])
//
//                            );
//                            echo json_encode($infosApi);
//                            die;

                        }
                    } else {

                        echo "erreur";
                        die;
//                        $infosApi[] = array(
//                            'statut' => "Error",
//                            'Message' => "Une erreur est survenue. Veuillez vous rapprocher de la CRIAT.",
//                            'option' => valid_donnees($_POST['option'])
//
//                        );
//                        echo json_encode($infosApi);
//                        die;
                    }


                } else {

                    echo "erreur";
                    die;
//                    $infosApi[] = array(
//                        'statut' => "Error",
//                        'Message' => "Ce matricule est introuvable.",
//                        'option' => valid_donnees($_POST['option']),
//
//                    );
//                    echo json_encode($infosApi);
//                    die;

                }
            } catch (\Throwable $th) {

                echo "erreur";
                die;
//                $infosApi[] = array(
//                    'statut' => "Error",
//                    'Message' => "Une erreur est survenue.",
//                    'option' => valid_donnees($_POST['option']),
//                );
//                echo json_encode($infosApi);
//                die;

            }

        } else {
            // echo $_POST['matricule'];
            // die;
            echo "erreur";
            die;
//            $infosApi[] = array(
//                'statut' => "Error",
//                'Message' => "Une erreur est survenue. Veuillez revoir les paramètres envoyés.",
//                'option' => valid_donnees($_POST['option']),
//            );
//            echo json_encode($infosApi);
//            die;
        }




    default :
        echo "erreur";
        die;





}
