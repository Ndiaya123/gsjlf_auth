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
//

//    header("Location: /personnel/signin");
//    exit();
            }else
            {


                $connectUser = $_SESSION['connectUserGSJLF_ENT'] ?? null;

                if($connectUser == 1)
                {

                    $lien_logo1 = "/personnel/admin-accueil";
                    $lien_logo2 = "/personnel/admin-accueil";

//                header("Location: /personnel/admin-accueil");
//                exit();





                }else if($connectUser == 2)
                {

                    $lien_logo1 = "/personnel/user-accueil";
                    $lien_logo2 = "/personnel/user-accueil";

//                header("Location: /personnel/user-accueil");
//                exit();

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



            // ── Après ──────────────────────────────────────
            $page_accueil = $userSimpleController->getPageAccueil(
                $listeTachesIncarnes,
                $listeTachesStructures,
                $listeTachesParDefaut,
                $page_par_defaut ?? 'http://localhost/personnel/signin'   // fallback ultime
            );

            echo "ac".$page_accueil;
            die;



            } catch (\Throwable $th) {
                error_log($th->getMessage());
               echo "erreur";
               die;
            }

            break;

        }else
        {
            echo "erreur";
            die;
        }
        break;


    case 2:



        $idDirection = NULL;

        if(!empty($_SESSION['user_direction']))
        {
            $idDirection = $_SESSION['user_direction'];



        }else
        {
            echo "sessionExpired";
            die;
        }

            try {



                $stmt = $bdP->prepare("SELECT * FROM direction_employe_chef WHERE idChefDirection = ?");
                $stmt->execute([$idDirection]);
                $listes = $stmt->fetchAll(PDO::FETCH_OBJ);

                if(count($listes) > 0)
                {

                    $listeAgents = array();


                    foreach ($listes as $tmp) {

                        if($tmp->niveau == 1)
                        {
                            $stmtN1 = $bdP->prepare("SELECT etatCivil.identifiant,etatCivil.prenom,etatCivil.nom,personnels.matricule FROM personnels,affectations,etatCivil WHERE personnels.idEtatCivil=etatCivil.id AND personnels.idAffectation=affectations.id AND affectations.idUniteAdministrativeNiv1=:idUniteAdministrativeNiv1 AND affectations.statutAffectation=:statutAffectation");
                            $stmtN1->execute([$idDirection]);
                            $listesN1 = $stmtN1->fetchAll(PDO::FETCH_OBJ);


                            foreach ($listesN1 as $tmp1) {

                                $listeAgents[] = array(
                                    'matricule' => $tmp1->matricule,
                                    'prenom' => ucwords(mb_strtolower($tmp1->prenom)),
                                    'nom' => $userSimpleController->fctRetirerAccents(mb_strtoupper($tmp1->nom)),
                                    'prenomNom' => ucfirst(mb_strtolower($tmp1["prenom"])) . " " . $userSimpleController->fctRetirerAccents(mb_strtoupper($tmp1["nom"]))

                                );

                            }



                        }


                        if($tmp->niveau == 2)
                        {

                            $stmtN2 = $bdP->prepare("SELECT etatCivil.identifiant,etatCivil.prenom,etatCivil.nom,personnels.matricule FROM personnels,affectations,etatCivil WHERE personnels.idEtatCivil=etatCivil.id AND personnels.idAffectation=affectations.id AND affectations.idUniteAdministrativeNiv2=:idUniteAdministrativeNiv2 AND affectations.statutAffectation=:statutAffectation");
                            $stmtN2->execute([$idDirection]);
                            $listesN2 = $stmtN2->fetchAll(PDO::FETCH_OBJ);


                            foreach ($listesN2 as $tmp2) {

                                $listeAgents[] = array(
                                    'matricule' => $tmp2->matricule,
                                    'prenom' => ucwords(mb_strtolower($tmp2->prenom)),
                                    'nom' => $userSimpleController->fctRetirerAccents(mb_strtoupper($tmp2->nom)),
                                    'prenomNom' => ucfirst(mb_strtolower($tmp2["prenom"])) . " " . $userSimpleController->fctRetirerAccents(mb_strtoupper($tmp2["nom"]))

                                );


                            }


                        }


                        if($tmp->niveau == 3)
                        {


                            $stmtN3 = $bdP->prepare("SELECT etatCivil.identifiant,etatCivil.prenom,etatCivil.nom,personnels.matricule FROM personnels,affectations,etatCivil WHERE personnels.idEtatCivil=etatCivil.id AND personnels.idAffectation=affectations.id AND affectations.idUniteAdministrativeNiv3=:idUniteAdministrativeNiv3 AND affectations.statutAffectation=:statutAffectation");
                            $stmtN3->execute([$idDirection]);
                            $listesN3 = $stmtN3->fetchAll(PDO::FETCH_OBJ);


                            foreach ($listesN3 as $tmp3) {


                                $listeAgents[] = array(
                                    'matricule' => $tmp3->matricule,
                                    'prenom' => ucwords(mb_strtolower($tmp3->prenom)),
                                    'nom' => $userSimpleController->fctRetirerAccents(mb_strtoupper($tmp3->nom)),
                                    'prenomNom' => ucfirst(mb_strtolower($tmp3["prenom"])) . " " . $userSimpleController->fctRetirerAccents(mb_strtoupper($tmp3["nom"]))

                                );

                            }


                        }


                    }


                    echo '<option></option><option value="" >Choisir...</option>';

                    $listeAgents = json_decode(json_encode($listeAgents), true);

                    foreach ($listeAgents as $tmp4) {
                        echo '<option value="' . $tmp4->identifiant . '">' . $tmp4->prenomNom . '</option>';
                    }

                    die;

                }else
                {
                    echo "erreur";
                    die;
                }

                die;


            } catch (\Throwable $th) {
                echo "erreur";
                die;
            }
            break;


    case 3 :


        $matricule = NULL;

        if(!empty($_SESSION['tmpMatricule']))
        {
            $matricule = $_SESSION['tmpMatricule'];



        }else
        {
            echo "sessionExpired";
            die;
        }

        try {




            $stmt = $bdP->prepare("SELECT  etatCivil.identifiant,etatCivil.prenom,etatCivil.nom,personnels.matricule FROM direction_employe_chef,direction,postesAResponsabilite,personnels,etatCivil WHERE direction_employe_chef.idChefDirection=direction.id AND direction.idFonction=postesAResponsabilite.idFonction AND postesAResponsabilite.idPersonnel=personnels.id AND personnels.idEtatCivil=etatCivil.id and postesAResponsabilite.statutPoste=:statutPoste");
            $stmt->execute([1]);
            $listes = $stmt->fetchAll(PDO::FETCH_OBJ);

            if(count($listes) > 0)
            {

                $listeAgents = array();


                foreach ($listes as $tmp) {


                    if($matricule != $tmp->matricule)
                    {
                        $listeAgents[] = array(
                            'matricule' => $tmp->matricule,
                            'prenom' => ucwords(mb_strtolower($tmp->prenom)),
                            'nom' => $userSimpleController->fctRetirerAccents(mb_strtoupper($tmp->nom)),
                            'prenomNom' => ucfirst(mb_strtolower($tmp["prenom"])) . " " . $userSimpleController->fctRetirerAccents(mb_strtoupper($tmp["nom"]))

                        );
                    }
                }


                echo '<option></option><option value="" >Choisir...</option>';

                $listeAgents = json_decode(json_encode($listeAgents), true);

                foreach ($listeAgents as $tmp4) {
                    echo '<option value="' . $tmp4->identifiant . '">' . $tmp4->prenomNom . '</option>';
                }

                die;

            }else
            {
                echo "erreur";
                die;
            }

            die;


        } catch (\Throwable $th) {
            echo "erreur";
            die;
        }
        break;



    break;
    default :
        echo "erreur";
        die;





}
