<?php

//ini_set('session.gc_maxlifetime', 36000);
//session_set_cookie_params(36000);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

include_once('../../bdP.php');
include_once('../../bd.php');
include_once('../../bdBASI.php');
require_once('../../includes/phpMailer/PHPMailer.php');
require_once('../../includes/phpMailer/SMTP.php');
require_once('../../includes/phpMailer/Exception.php');

include_once('../../sessions/session.php');

class authController extends BDP
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


    function getInitiales($prenom, $nom)
    {
        $premiereLettrePrenom = mb_substr(trim($prenom), 0, 1, 'UTF-8');
        $deuxiemeLettreNom = mb_substr(trim($nom), 1, 1, 'UTF-8');

        return mb_strtoupper($premiereLettrePrenom . $deuxiemeLettreNom, 'UTF-8');
    }

    public function infoApplication()
    {
        try {

            $bdP = $this->connect();

            $stmt = $bdP->prepare("
            SELECT 
                COUNT(*) AS total_applications,
                SUM(CASE WHEN statut = 0 THEN 1 ELSE 0 END) AS en_attente
            FROM listeApplications
        ");

            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_OBJ);

        } catch (\Throwable $th) {

            return (object)[
                'total_applications' => 0,
                'en_attente' => 0
            ];

        }
    }

    public function infoEntite($identifiant)
    {
        try {

            $bdP = $this->connect();


            $data =
                [
                    'identifiant' => $identifiant,
                ];
            $stmt = $bdP->prepare("
           SELECT DISTINCT entites.entite
FROM affectations
INNER JOIN entites ON affectations.idEntite = entites.id
WHERE affectations.identifiant = :identifiant;
        ");

            $stmt->execute($data);

            return $stmt->fetch(PDO::FETCH_OBJ);

        } catch (\Throwable $th) {


            return (object)[
                'entite' => null
            ];

        }
    }


    public function verifierUserCRIAT($bd,$email,$matricule,$nom, $prenom)
    {
        try {


            // Vérifier si l'utilisateur existe
            $stmt = $bd->prepare("
            SELECT id, email
            FROM utilisateurs
            WHERE email = :email
            LIMIT 1
        ");

            $stmt->execute([
                'email' => $email
            ]);

            $user = $stmt->fetch(PDO::FETCH_OBJ);

            // Utilisateur trouvé
            if ($user) {
                return $user;
            }


            // Création de l'utilisateur
            $password = password_hash(valid_donnees($_POST['password']), PASSWORD_DEFAULT, ['cost' => 5]);

            $stmt = $bd->prepare("
            INSERT INTO utilisateurs (matricule,prenom,nom,email,password,photo,statut,idRole,idDepartement)
            VALUES (:nom, :prenom, :email)
        ");

            $stmt->execute([
                'matricule' => $matricule,
                'prenom' => $prenom,
                'nom' => $nom,
                'email' => $email,
                'password' => $password,
                'photo' => '1.png',
                'statut' => 1,
                'idRole' => 20,
                'idDepartement' => NULL
            ]);

            $id = $bd->lastInsertId();

            return (object)[
                'id' => $id,
                'email' => $email
            ];

        } catch (\Throwable $th) {

            return false;

        }
    }


    public function verifierUserBASI($bdBASI,$email,$matricule,$nom, $prenom)
    {
        try {


            // Vérifier si l'utilisateur existe
            $stmt = $bdBASI->prepare("
            SELECT id, email
            FROM utilisateurs
            WHERE email = :email
            LIMIT 1
        ");

            $stmt->execute([
                'email' => $email
            ]);

            $user = $stmt->fetch(PDO::FETCH_OBJ);

            // Utilisateur trouvé
            if ($user) {
                return $user;
            }


            // Création de l'utilisateur
            $password = password_hash(valid_donnees($_POST['password']), PASSWORD_DEFAULT, ['cost' => 5]);

            $stmt = $bdBASI->prepare("
            INSERT INTO utilisateurs (matricule,prenom,nom,email,password,photo,statut,idRole)
            VALUES (:matricule,:prenom,:nom,:email,:password,:photo,:statut,:idRole)
        ");

            $stmt->execute([
                'matricule' => $matricule,
                'prenom' => $prenom,
                'nom' => $nom,
                'email' => $email,
                'password' => $password,
                'photo' => '1.png',
                'statut' => 1,
                'idRole' => 20
            ]);

            $id = $bdBASI->lastInsertId();

            return (object)[
                'id' => $id,
                'email' => $email
            ];

        } catch (\Throwable $th) {

            return false;

        }
    }




    public function listeApplications()
    {
        try {

            $bdP = $this->connect();

            $stmt = $bdP->prepare("
         SELECT 
    la.id AS numero,
    la.nomApplication,
        la.icon,
    la.description AS descriptionApplication,
    la.statut AS statutApplication,
            la.page_defaut,
    CASE la.statut
        WHEN 0 THEN 'pending'
        WHEN 1 THEN 'authorized'
        WHEN 2 THEN 'denied'
        ELSE 'Inconnu'
    END AS statutLibelle,
 CASE la.idEntite
        WHEN 1 THEN 'cmjlf'
        WHEN 2 THEN 'ctd'
        WHEN 3 THEN 'uahb'
        WHEN 4 THEN 'gsjlf'
        WHEN 5 THEN 'gsjlf'
        ELSE 'gsjlf'
    END AS entite,
    GROUP_CONCAT(ha.nom_hashtag) AS hashtags

FROM listeApplications la
LEFT JOIN hashtag_appli ha 
    ON ha.idAppli = la.id

GROUP BY la.id, la.nomApplication, la.description, la.statut;
        ");

            $stmt->execute();

            $data = $stmt->fetchAll(PDO::FETCH_OBJ);

            // transformer en tableau propre
            foreach ($data as $app) {
                $app->hashtags = $app->hashtags
                    ? explode(',', $app->hashtags)
                    : [];
            }

            return $data;

        } catch (\Throwable $th) {

            return [];

        }
    }



    /**
     * ============================================================================
     *  Tri des menus/tâches selon le champ `ordre` (au lieu du tri alphabétique)
     * ============================================================================
     *  Seul le ORDER BY change dans les 5 fonctions ci-dessous — SELECT/JOIN/WHERE
     *  restent identiques. Nouvelle logique de tri :
     *
     *   1) Accueil/Dashboard toujours en premier (filet de sécurité conservé,
     *      utilisé aussi par getPageAccueil() ailleurs dans le code)
     *   2) COALESCE(sm.ordre, t.ordre, 0) → position de premier niveau :
     *        - si la tâche appartient à un sous-menu, c'est l'ordre DU SOUS-MENU
     *          qui détermine sa position au premier niveau (toutes les tâches
     *          d'un même sous-menu restent groupées à la place de leur sous-menu)
     *        - si la tâche est "libre" (idSousMenu NULL), c'est son propre ordre
     *   3) t.ordre → position de la tâche à l'intérieur de son sous-menu
     *   4) t.nom → repli alphabétique si l'ordre n'a pas encore été configuré
     *      (valeur par défaut 0 pour tout le monde)
     *
     *  ⚠️ Prérequis : la colonne sous_menu.ordre doit exister
     *  (voir alter_sous_menu_ordre.sql fourni précédemment).
     * ============================================================================
     */


    public function listeTachesParDefaut()
    {
        try {

            $bdP = $this->connect();

            $params = [
                'idTypeTache' => 3,
                'active' => 1
            ];

            $sql = "
          SELECT
    t.id,
    t.nom,
    t.url,
    sm.nom AS sousMenu,
        t.estVisible,

        t.estVisibleUrl,

    CASE
        WHEN sm.id IS NOT NULL THEN ic_sm.icon
        ELSE ic_t.icon
    END AS icon,

    CASE
        WHEN t.nom LIKE 'Accueil%' OR t.nom LIKE 'Dashboard%' THEN 1
        ELSE 0
    END AS statut,
    la.nomApplication,
                    la.id as idAppli



FROM tache t

LEFT JOIN sous_menu sm
    ON sm.id = t.idSousMenu

LEFT JOIN icons ic_t
    ON ic_t.id = t.idIcon

LEFT JOIN icons ic_sm
    ON ic_sm.id = sm.idIcon


LEFT JOIN listeApplications la
    ON la.id = t.idAppli

WHERE t.idTypeTache = :idTypeTache
  AND t.active = :active

ORDER BY
    CASE
        WHEN t.nom LIKE 'Accueil%' OR t.nom LIKE 'Dashboard%' THEN 0
        ELSE 1
    END,
    COALESCE(sm.ordre, t.ordre, 0),
    t.ordre,
    t.nom;
        ";

            $stmt = $bdP->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_OBJ);

        } catch (\Throwable $th) {
            return [];
        }
    }

//    public function listeTachesIncarnes($idFonction)
//    {
//        try {
//
//            $bdP = $this->connect();
//
//            $params = [
//                'idTypeTache' => 2,
//                'idFonction' => $idFonction,
//                'active' => 1
//            ];
//
//            $sql = "
//         SELECT
//    t.id,
//    t.nom,
//        t.url,
//    sm.nom AS sousMenu,
//        t.estVisible,
//                t.estVisibleUrl,
//
//
//
//    CASE
//        WHEN sm.id IS NOT NULL THEN ic_sm.icon
//        ELSE ic_t.icon
//    END AS icon,
//
//    CASE
//        WHEN t.nom LIKE 'Accueil%' OR t.nom LIKE 'Dashboard%' THEN 1
//        ELSE 0
//    END AS statut,
//                 la.nomApplication,
//                                 la.id as idAppli
//FROM tache t
//
//LEFT JOIN sous_menu sm
//    ON sm.id = t.idSousMenu
//
//LEFT JOIN icons ic_t
//    ON ic_t.id = t.idIcon
//
//LEFT JOIN icons ic_sm
//    ON ic_sm.id = sm.idIcon
//
//LEFT JOIN listeApplications la
//    ON la.id = t.idAppli
//WHERE t.idTypeTache = :idTypeTache
//  AND t.idFonction = :idFonction
//  AND t.active = :active
//
//ORDER BY
//    CASE
//        WHEN t.nom LIKE 'Accueil%' OR t.nom LIKE 'Dashboard%' THEN 0
//        ELSE 1
//    END,
//    COALESCE(sm.ordre, t.ordre, 0),
//    t.ordre,
//    t.nom;
//        ";
//
//            $stmt = $bdP->prepare($sql);
//            $stmt->execute($params);
//
//            return $stmt->fetchAll(PDO::FETCH_OBJ);
//
//        } catch (\Throwable $th) {
//            return [];
//        }
//    }
//
//
//    public function listeTachesStructures($matricule)
//    {
//        try {
//
//            $bdP = $this->connect();
//
//            $params = [
//                'idTypeTache' => 1,
//                'matricule' => $matricule,
//                'access' => 1,
//                'active' => 1
//
//            ];
//
//            $sql = "
//SELECT
//    t.id,
//    t.nom,
//        t.url,
//    sm.nom AS sousMenu,
//    t.estVisible,
//        t.estVisibleUrl,
//
//    CASE
//        WHEN sm.id IS NOT NULL THEN ic_sm.icon
//        ELSE ic_t.icon
//    END AS icon,
//
//    CASE
//        WHEN t.nom LIKE 'Accueil%' OR t.nom LIKE 'Dashboard%' THEN 1
//        ELSE 0
//    END AS statut,
//        la.nomApplication,
//                la.id as idAppli
//
//
//
//FROM tache t
//
//INNER JOIN tache_utilisateur tu
//    ON tu.idTache = t.id
//
//LEFT JOIN sous_menu sm
//    ON sm.id = t.idSousMenu
//
//LEFT JOIN icons ic_t
//    ON ic_t.id = t.idIcon
//
//LEFT JOIN icons ic_sm
//    ON ic_sm.id = sm.idIcon
//
//
//LEFT JOIN listeApplications la
//    ON la.id = t.idAppli
//
//WHERE t.idTypeTache = :idTypeTache
//  AND t.active = :active
//  AND tu.matricule = :matricule
//  AND tu.access = :access
//
//ORDER BY
//    CASE
//        WHEN t.nom LIKE 'Accueil%' OR t.nom LIKE 'Dashboard%' THEN 0
//        ELSE 1
//    END,
//    COALESCE(sm.ordre, t.ordre, 0),
//    t.ordre,
//    t.nom;        ";
//
//            $stmt = $bdP->prepare($sql);
//            $stmt->execute($params);
//
//            return $stmt->fetchAll(PDO::FETCH_OBJ);
//
//        } catch (\Throwable $th) {
//            return [];
//        }
//    }
//






    private function selectTachesSql($condition)
    {
        return "
SELECT
    t.id,
    t.nom,
    t.url,
    sm.nom AS sousMenu,
    t.estVisible,
    t.estVisibleUrl,

    CASE
        WHEN sm.id IS NOT NULL THEN ic_sm.icon
        ELSE ic_t.icon
    END AS icon,

    CASE
        WHEN t.nom LIKE 'Accueil%' OR t.nom LIKE 'Dashboard%' THEN 1
        ELSE 0
    END AS statut,

    la.nomApplication,
    la.id AS idAppli,

    -- colonnes de tri uniquement, retirées par fusionnerTaches()
    COALESCE(sm.ordre, t.ordre, 0) AS ordreTri,
    t.ordre                        AS ordreTache

FROM tache t

LEFT JOIN sous_menu sm
    ON sm.id = t.idSousMenu

LEFT JOIN icons ic_t
    ON ic_t.id = t.idIcon

LEFT JOIN icons ic_sm
    ON ic_sm.id = sm.idIcon

LEFT JOIN listeApplications la
    ON la.id = t.idAppli

WHERE t.idTypeTache = :idTypeTache
  AND t.active      = :active
  AND (" . $condition . ")
";
    }


    /**
     * Clause « affectée via tache_utilisateur ».
     * EXISTS plutôt qu'un INNER JOIN : une tâche octroyée, retirée puis
     * ré-octroyée a plusieurs lignes dans tache_utilisateur et le join la
     * renvoyait autant de fois, ce qui la dupliquait dans le menu.
     */
    private function conditionTacheAffectee()
    {
        return "EXISTS (
            SELECT 1
            FROM tache_utilisateur tu
            WHERE tu.idTache = t.id
              AND tu.matricule = :matricule
              AND tu.access = :access
              AND tu.idUtilisateurSupRetrait IS NULL
        )";
    }


    /**
     * Fusionne les ensembles reçus, supprime les doublons par id,
     * applique l'ordre d'affichage puis nettoie les colonnes de tri.
     */
    private function fusionnerTaches(array $ensembles)
    {
        $parId = [];

        foreach ($ensembles as $ensemble) {
            foreach ($ensemble as $tache) {
                if (!isset($parId[$tache->id])) {
                    $parId[$tache->id] = $tache;
                }
            }
        }

        $taches = array_values($parId);

        // Même tri que l'ancien ORDER BY :
        //   1. Accueil / Dashboard en tête
        //   2. COALESCE(sm.ordre, t.ordre, 0)
        //   3. t.ordre
        //   4. t.nom
        usort($taches, function ($a, $b) {

            $sa = ((int) $a->statut === 1) ? 0 : 1;
            $sb = ((int) $b->statut === 1) ? 0 : 1;
            if ($sa !== $sb) {
                return $sa - $sb;
            }

            $ta = (int) $a->ordreTri;
            $tb = (int) $b->ordreTri;
            if ($ta !== $tb) {
                return $ta - $tb;
            }

            $oa = (int) $a->ordreTache;
            $ob = (int) $b->ordreTache;
            if ($oa !== $ob) {
                return $oa - $ob;
            }

            return strcasecmp((string) $a->nom, (string) $b->nom);
        });

        foreach ($taches as $tache) {
            unset($tache->ordreTri, $tache->ordreTache);
        }

        return $taches;
    }


    public function listeTachesIncarnes($idFonction, $matricule = null)
    {
        try {

            $bdP = $this->connect();

            // ── Étape 1 : les tâches allouées à la fonction de la personne ──
            $allouees = [];

            if (!empty($idFonction)) {
                $stmt = $bdP->prepare($this->selectTachesSql("t.idFonction = :idFonction"));
                $stmt->execute([
                    'idTypeTache' => 2,
                    'active'      => 1,
                    'idFonction'  => $idFonction
                ]);
                $allouees = $stmt->fetchAll(PDO::FETCH_OBJ);
            }

            // ── Étape 2 : les tâches incarnées affectées à la personne ──────
            $affectees = [];

            if (!empty($matricule)) {
                $stmt = $bdP->prepare($this->selectTachesSql($this->conditionTacheAffectee()));
                $stmt->execute([
                    'idTypeTache' => 2,
                    'active'      => 1,
                    'matricule'   => $matricule,
                    'access'      => 1
                ]);
                $affectees = $stmt->fetchAll(PDO::FETCH_OBJ);
            }

            return $this->fusionnerTaches([$allouees, $affectees]);

        } catch (\Throwable $th) {
            return [];
        }
    }


    public function listeTachesStructures($matricule)
    {
        try {

            $bdP = $this->connect();

            // ── Étape 1 : les tâches de structure allouées ──────────────────
            //
            // Pour ce type il n'existe aujourd'hui aucune allocation
            // automatique : une tâche de structure n'arrive à une personne
            // que par affectation. L'étape 1 est donc vide, et c'est l'étape 2
            // qui porte tout.
            //
            // Si une règle d'allocation par unité administrative est ajoutée
            // plus tard, elle se branche ici, sur le modèle de l'étape 1 de
            // listeTachesIncarnes() :
            //
            //     $stmt = $bdP->prepare($this->selectTachesSql(
            //         "t.idUniteAdministrativeNiv2 = :idUA"));
            //     $stmt->execute([
            //         'idTypeTache' => 1, 'active' => 1, 'idUA' => $idUA
            //     ]);
            //     $allouees = $stmt->fetchAll(PDO::FETCH_OBJ);
            //
            $allouees = [];

            // ── Étape 2 : les tâches de structure affectées à la personne ───
            $affectees = [];

            if (!empty($matricule)) {
                $stmt = $bdP->prepare($this->selectTachesSql($this->conditionTacheAffectee()));
                $stmt->execute([
                    'idTypeTache' => 1,
                    'active'      => 1,
                    'matricule'   => $matricule,
                    'access'      => 1
                ]);
                $affectees = $stmt->fetchAll(PDO::FETCH_OBJ);
            }

            return $this->fusionnerTaches([$allouees, $affectees]);

        } catch (\Throwable $th) {
            return [];
        }
    }

    public function listeTachesIncarnesAdmin()
    {
        try {

            $bdP = $this->connect();

            $params = [
                'idTypeTache' => 2,
                'active' => 1

            ];

            $sql = "
SELECT
    t.id,
    t.nom,
        t.url,
    sm.nom AS sousMenu,
        t.estVisible,
        t.estVisibleUrl,


    CASE
        WHEN sm.id IS NOT NULL THEN ic_sm.icon
        ELSE ic_t.icon
    END AS icon,

    CASE
        WHEN t.nom LIKE 'Accueil%' OR t.nom LIKE 'Dashboard%' THEN 1
        ELSE 0
    END AS statut,
        la.nomApplication,
                        la.id as idAppli



FROM tache t

LEFT JOIN sous_menu sm
    ON sm.id = t.idSousMenu

LEFT JOIN icons ic_t
    ON ic_t.id = t.idIcon

LEFT JOIN icons ic_sm
    ON ic_sm.id = sm.idIcon


LEFT JOIN listeApplications la
    ON la.id = t.idAppli
WHERE t.idTypeTache = :idTypeTache
  AND t.active = :active

ORDER BY
    CASE
        WHEN t.nom LIKE 'Accueil%' OR t.nom LIKE 'Dashboard%' THEN 0
        ELSE 1
    END,
    COALESCE(sm.ordre, t.ordre, 0),
    t.ordre,
    t.nom;        ";

            $stmt = $bdP->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_OBJ);

        } catch (\Throwable $th) {
            return [];
        }
    }


    public function listeTachesStructuresAdmin()
    {
        try {

            $bdP = $this->connect();

            $params = [
                'idTypeTache' => 1,
                'active' => 1
            ];

            $sql = "
SELECT
    t.id,
    t.nom,
    t.url,
    sm.nom AS sousMenu,
        t.estVisible,
        t.estVisibleUrl,


    COALESCE(ic_sm.icon, ic_t.icon) AS icon,

    CASE
        WHEN t.nom LIKE 'Accueil%' OR t.nom LIKE 'Dashboard%' THEN 1
        ELSE 0
    END AS statut,
        la.nomApplication,
                        la.id as idAppli



FROM tache t

LEFT JOIN sous_menu sm
    ON sm.id = t.idSousMenu

LEFT JOIN icons ic_t
    ON ic_t.id = t.idIcon

LEFT JOIN icons ic_sm
    ON ic_sm.id = sm.idIcon

LEFT JOIN listeApplications la
    ON la.id = t.idAppli

WHERE t.idTypeTache = :idTypeTache
  AND t.active = :active

ORDER BY
    CASE
        WHEN t.nom LIKE 'Accueil%' OR t.nom LIKE 'Dashboard%' THEN 0
        ELSE 1
    END,
    COALESCE(sm.ordre, t.ordre, 0),
    t.ordre,
    t.nom;        ";

            $stmt = $bdP->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_OBJ);

        } catch (\Throwable $th) {
            return [];
        }
    }




    public function infoPosteResponsable($identifiant)
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
            WHERE postesAResponsabilite.idFonction=fonction.id 	AND postesAResponsabilite.identifiant = :identifiant
              AND postesAResponsabilite.statutPoste = :statutPoste
        ");

            $stmt->execute($data);

            $result =  $stmt->fetch(PDO::FETCH_OBJ);

            return $result;


        } catch (\Throwable $th) {
            return [];
        }
    }

//    public function listeApplicationUserSimple($matricule)
//    {
//
//
//        try {
//
//            $bdP = $this->connect();
//
//            $params = [
//                'idTypeTache1' => 1,
//                'idTypeTache2' => 2,
//                'idTypeTache3' => 3,
//                'matricule' => $matricule,
//                'access' => 1,
//                'active' => 1
//
//            ];
//
//            $sql = "
//          SELECT DISTINCT
//    numero,
//    nomApplication,
//    icon,
//    descriptionApplication,
//    statutApplication
//FROM (
//
//    SELECT
//        la.id AS numero,
//        la.nomApplication,
//        la.icon,
//        la.description AS descriptionApplication,
//        la.statut AS statutApplication
//    FROM tache t
//    LEFT JOIN listeApplications la ON la.id = t.idAppli
//    WHERE t.idTypeTache = :idTypeTache3
//      AND t.active = :active
//
//    UNION ALL
//
//    SELECT
//        la.id AS numero,
//        la.nomApplication,
//        la.icon,
//        la.description AS descriptionApplication,
//        la.statut AS statutApplication
//    FROM tache t
//    LEFT JOIN listeApplications la ON la.id = t.idAppli
//    WHERE t.idTypeTache = idTypeTache2
//      AND t.active = :active
//      AND t.idFonction = :idFonction
//
//    UNION ALL
//
//    SELECT
//        la.id AS numero,
//        la.nomApplication,
//        la.icon,
//        la.description AS descriptionApplication,
//        la.statut AS statutApplication
//    FROM tache t
//    LEFT JOIN listeApplications la ON la.id = t.idAppli
//    INNER JOIN tache_utilisateur tu
//        ON tu.idTache = t.id
//    WHERE t.idTypeTache = :idTypeTache1
//      AND t.active = :active
//      AND tu.matricule = :matricule
//      AND tu.access = :access
//
//) applications
//
//ORDER BY nomApplication;
//        ";
//
//            $stmt = $bdP->prepare($sql);
//            $stmt->execute($params);
//
//            return $stmt->fetchAll(PDO::FETCH_OBJ);
//
//
//        } catch (\Throwable $th) {
//            return [];
//        }
//
//    }





    public function listeApplicationUserSimple($matricule, $idFonction)
    {
        try {

            $bdP = $this->connect();

            $params = [
                'idTypeTache1' => 1, // tâches utilisateur
                'idTypeTache2' => 2, // tâches liées à une fonction
                'idTypeTache3' => 3, // tâches par défaut
                'idFonction'   => $idFonction,
                'matricule'    => $matricule,
                'access'       => 1,
                'active'       => 1
            ];

            $sql = "SELECT
    numero,
    nomApplication,
    icon,
    descriptionApplication,
    statutApplication,
    statutLibelle,
    entite,
    page_defaut,
    GROUP_CONCAT(DISTINCT hashtags SEPARATOR ',') AS hashtags
FROM (

    SELECT
        la.id AS numero,
        la.nomApplication,
        la.icon,
        la.description AS descriptionApplication,
        la.statut AS statutApplication,
        la.page_defaut,
        CASE la.statut
            WHEN 0 THEN 'pending'
            WHEN 1 THEN 'authorized'
            WHEN 2 THEN 'denied'
            ELSE 'Inconnu'
        END AS statutLibelle,
        CASE la.idEntite
            WHEN 1 THEN 'cmjlf'
            WHEN 2 THEN 'ctd'
            WHEN 3 THEN 'uahb'
            WHEN 4 THEN 'gsjlf'
            ELSE 'gsjlf'
        END AS entite,
        ha.nom_hashtag AS hashtags
    FROM tache t
    INNER JOIN listeApplications la ON la.id = t.idAppli
    LEFT JOIN hashtag_appli ha ON ha.idAppli = la.id
    WHERE t.idTypeTache = :idTypeTache3
            AND t.active = :active
            -- ❌ Pas de GROUP BY ici

    UNION ALL

    SELECT
        la.id,
        la.nomApplication,
        la.icon,
        la.description,
        la.statut,
        la.page_defaut,
        CASE la.statut
            WHEN 0 THEN 'pending'
            WHEN 1 THEN 'authorized'
            WHEN 2 THEN 'denied'
            ELSE 'Inconnu'
        END,
        CASE la.idEntite
            WHEN 1 THEN 'cmjlf'
            WHEN 2 THEN 'ctd'
            WHEN 3 THEN 'uahb'
            WHEN 4 THEN 'gsjlf'
            ELSE 'gsjlf'
        END,
        ha.nom_hashtag
    FROM tache t
    INNER JOIN listeApplications la ON la.id = t.idAppli
    LEFT JOIN hashtag_appli ha ON ha.idAppli = la.id
    WHERE t.idTypeTache = :idTypeTache2
            AND t.active = :active
            AND t.idFonction = :idFonction
            -- ❌ Pas de GROUP BY ici

    UNION ALL

    SELECT
        la.id,
        la.nomApplication,
        la.icon,
        la.description,
        la.statut,
                la.page_defaut,
        CASE la.statut
            WHEN 0 THEN 'pending'
            WHEN 1 THEN 'authorized'
            WHEN 2 THEN 'denied'
            ELSE 'Inconnu'
        END,
        CASE la.idEntite
            WHEN 1 THEN 'cmjlf'
            WHEN 2 THEN 'ctd'
            WHEN 3 THEN 'uahb'
            WHEN 4 THEN 'gsjlf'
            ELSE 'gsjlf'
        END,
        ha.nom_hashtag
    FROM tache t
    INNER JOIN listeApplications la ON la.id = t.idAppli
    LEFT JOIN hashtag_appli ha ON ha.idAppli = la.id
    INNER JOIN tache_utilisateur tu ON tu.idTache = t.id
    WHERE t.idTypeTache = :idTypeTache1
            AND t.active = :active
            AND tu.matricule = :matricule
            AND tu.access = :access
            -- ❌ Pas de GROUP BY ici
    


) applications

GROUP BY
    numero,
    nomApplication,
    icon,
    descriptionApplication,
    statutApplication,
    statutLibelle,
    entite

ORDER BY nomApplication;
        ";

            $stmt = $bdP->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_OBJ);

            // transformer en tableau propre
            foreach ($data as $app) {
                $app->hashtags = $app->hashtags
                    ? explode(',', $app->hashtags)
                    : [];
            }

            return $data;



        } catch (\Throwable $th) {

            return [];
        }
    }

//    public function listeBaseDoneeUserSimple($matricule, $idFonction)
//    {
//        try {
//
//            $bdP = $this->connect();
//
//            $params = [
//                'idTypeTache1' => 1,
//                'idTypeTache2' => 2,
//                'idTypeTache3' => 3,
//                'idFonction'   => $idFonction,
//                'matricule'    => $matricule,
//                'access'       => 1,
//                'active'       => 1
//            ];
//
//            $sql = "
//            SELECT DISTINCT base_donnees, code_base_donnees
//            FROM (
//
//                -- Type 3 : accès par défaut
//                SELECT
//                    bd.nom AS base_donnees,
//                    bd.code AS code_base_donnees
//                FROM tache t
//                INNER JOIN base_donnees bd ON bd.id = t.idDB
//                WHERE t.idTypeTache = :idTypeTache3
//                  AND t.active = :active
//
//                UNION ALL
//
//                -- Type 2 : accès par fonction
//                SELECT
//                    bd.nom AS base_donnees,
//                    bd.code AS code_base_donnees
//                FROM tache t
//                INNER JOIN base_donnees bd ON bd.id = t.idDB
//                WHERE t.idTypeTache = :idTypeTache2
//                  AND t.active = :active
//                  AND t.idFonction = :idFonction
//
//                UNION ALL
//
//                -- Type 1 : accès utilisateur
//                SELECT
//                    bd.nom AS base_donnees,
//                    bd.code AS code_base_donnees
//                FROM tache t
//                INNER JOIN base_donnees bd ON bd.id = t.idDB
//                INNER JOIN tache_utilisateur tu ON tu.idTache = t.id
//                WHERE t.idTypeTache = :idTypeTache1
//                  AND t.active = :active
//                  AND tu.matricule = :matricule
//                  AND tu.access = :access
//
//            ) data
//
//            ORDER BY base_donnees, code_base_donnees
//        ";
//
//            $stmt = $bdP->prepare($sql);
//            $stmt->execute($params);
//
//
//            return $stmt->fetchAll(PDO::FETCH_OBJ);
//
//        } catch (\Throwable $th) {
//            return [];
//        }
//    }


    public function listeBaseDoneeUserSimple($matricule, $idFonction)
    {
        try {

            $bdP = $this->connect();

            $params = [
                'idTypeTache1' => 1,
                'idTypeTache2' => 2,
                'idTypeTache3' => 3,
                'idFonction'   => $idFonction,
                'matricule'    => $matricule,
                'access'       => 1,
                'active'       => 1
            ];

            $sql = "
            SELECT DISTINCT
            bd.nom AS base_donnees,
            bd.code AS code_base_donnees
            FROM (
                SELECT la.id AS idAppli
                FROM tache t
                INNER JOIN listeApplications la ON la.id = t.idAppli
                WHERE t.idTypeTache = :idTypeTache3
                  AND t.active = :active

                UNION ALL

                SELECT la.id AS idAppli
                FROM tache t
                INNER JOIN listeApplications la ON la.id = t.idAppli
                WHERE t.idTypeTache = :idTypeTache2
                  AND t.active = :active
                  AND t.idFonction = :idFonction

                UNION ALL

                SELECT la.id AS idAppli
                FROM tache t
                INNER JOIN listeApplications la ON la.id = t.idAppli
                INNER JOIN tache_utilisateur tu ON tu.idTache = t.id
                WHERE t.idTypeTache = :idTypeTache1
                  AND t.active = :active
                  AND tu.matricule = :matricule
                  AND tu.access = :access
            ) apps
            INNER JOIN appli_bd ab
                ON ab.idAppli = apps.idAppli
            INNER JOIN base_donnees bd
                ON bd.id = ab.idBD
            ORDER BY bd.nom
        ";

            $stmt = $bdP->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_OBJ);

        } catch (\Throwable $th) {

            return [];
        }
    }


    /**
     * Fusionne listeApplications avec listeApplicationUserSimple
     * en appliquant les règles de statut :
     * - présente dans userSimple → statut = 1 (authorized)
     * - statut 1 mais absente de userSimple → statut = 2 (denied)
     * - autres → inchangé
     */
    public function listeApplicationsMerged($matricule, $idFonction)
    {
        // Récupérer les deux listes
        $listeApplications       = $this->listeApplications();
        $listeApplicationUserSimple = $this->listeApplicationUserSimple($matricule, $idFonction);


        if (empty($listeApplications) || !is_array($listeApplications)) {
            return [];
        }

        // Indexer les apps accessibles par leur numéro pour lookup O(1)
        $userSimpleIndex = [];
        if (!empty($listeApplicationUserSimple) && is_array($listeApplicationUserSimple)) {
            foreach ($listeApplicationUserSimple as $app) {
                $userSimpleIndex[$app->numero] = $app;
            }
        }

        // Appliquer les règles sur la liste complète
        foreach ($listeApplications as $app) {
            if (isset($userSimpleIndex[$app->numero])) {
                // Présente dans userSimple → autorisée
                $app->statutApplication = 1;
                $app->statutLibelle     = 'authorized';
            } elseif ($app->statutApplication === 1) {
                // Statut 1 mais pas dans userSimple → refusée
                $app->statutApplication = 2;
                $app->statutLibelle     = 'denied';
            }
            // sinon → inchangé (pending, denied déjà, etc.)
        }

        return $listeApplications;
    }


    public function infoDirection($bdBASI,$idFonction)
    {
        try {


            $params = [
                'idFonction' => $idFonction
            ];

            $sql = "SELECT * FROM direction WHERE direction.idFonction=:idFonction";

            $stmt = $bdBASI->prepare($sql);
            $stmt->execute($params);

            $tmp = $stmt->fetch(PDO::FETCH_OBJ);

            if($tmp)
            {

                return $tmp;

            }else
            {
                return [];
            }

        } catch (\Throwable $th) {
            return [];
        }
    }


    public function retourDirectionUser($matricule)
    {

        try {

            $bdP = $this->connect();

            $data = [
                'matricule' => $matricule,
                'statutAffectation' => 1
            ];

            $stmt = $bdP->prepare("SELECT affectations.idUniteAdministrativeNiv1,affectations.idUniteAdministrativeNiv2,affectations.idUniteAdministrativeNiv3 FROM personnels,affectations WHERE personnels.idAffectation=affectations.id AND affectations.statutAffectation=:statutAffectation AND personnels.matricule=:matricule");

            $stmt->execute($data);

            $result =  $stmt->fetch(PDO::FETCH_OBJ);

            if($result)
            {
                $niveau = NULL;
                $idUniteAdministrative = NULL;

                if($result->idUniteAdministrativeNiv1 != null && $result->idUniteAdministrativeNiv1 != "")
                {
                    $niveau = 1;

                    $idUniteAdministrative = $result->idUniteAdministrativeNiv1;

                }else if($result->idUniteAdministrativeNiv2 != null && $result->idUniteAdministrativeNiv2 != "")
                {
                    $niveau = 2;

                    $idUniteAdministrative = $result->idUniteAdministrativeNiv2;

                }else if($result->idUniteAdministrativeNiv3 != null && $result->idUniteAdministrativeNiv3 != "")
                {
                    $niveau = 3;
                    $idUniteAdministrative = $result->idUniteAdministrativeNiv3;

                }else
                {
                    return "erreur";

                }



                $dataD = [
                    'niveau' => $niveau,
                    'idUniteAdministrative' => $idUniteAdministrative
                ];

                $stmtD = $bdP->prepare("SELECT * FROM direction_employe_chef WHERE direction_employe_chef.niveau=:niveau AND direction_employe_chef.idUniteAdministrative=:idUniteAdministrative");
                $stmtD->execute($dataD);
                $resultD =  $stmtD->fetch(PDO::FETCH_OBJ);

                if($resultD)
                {
                    return $resultD->idChefDirection;

                }else
                {
                    return "erreur";
                }

            }else
            {
                return "erreur";
            }



        } catch (\Throwable $th) {
            return "erreur";
        }

}



    public function retourInfoDirecteur($idFonction)
    {

        try {

            $bdP = $this->connect();

            $data = [
                'idFonction' => $idFonction
            ];

            $stmt = $bdP->prepare("SELECT * FROM direction WHERE direction.idFonction=:idFonction");

            $stmt->execute($data);

            $result =  $stmt->fetch(PDO::FETCH_OBJ);

            if($result)
            {

                return "oui";

            }else
            {
                return "erreur";
            }



        } catch (\Throwable $th) {
            return "erreur";
        }

    }

}


$bdP = new BDP();
$bdP = $bdP->connect();

$bd = new BD();
$bd = $bd->connect();

$bdBASI = new BDBASI();
$bdBASI = $bdBASI->connect();

initialiserSession();


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
$BASE_URL = "http://localhost/personnel/";

switch ($option) {
    case 1:



        if (!empty($_POST['matricule']) && !empty($_POST['email']) && !empty($_POST['password']) && !empty($_POST['confirm-password'])) {

            try {
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bdP->beginTransaction();


                date_default_timezone_set('Africa/Dakar');
                $date_jour = date('Y-m-d');

                $matricule = valid_donnees($_POST['matricule']);
                $email = valid_donnees($_POST['email']);
                $password = valid_donnees($_POST['password']);
                $confirmPassword = valid_donnees($_POST['confirm-password']);
                $cgu = 1;

                if ($password !== $confirmPassword) {
                    echo "pasCorrespondantPWD";
                    die;
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo "emailInvalide";
                    die;
                }
                if (strlen($password) < 8) {
                    echo "passwordCourt";
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
                        $nom = $authController->fctRetirerAccents(mb_strtoupper($result_perso->nom));



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


                            if ($authController->comparerDateContrat($finContrat)) {


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
                                    'idEtatCivil' => $idEtatCivil,
                                    'idTypeUtilisateur' => 2
                                ];
                                $sql = "INSERT INTO utilisateurs(identifiant,matricule,email,password,cgu,dateCreation,statutUtilisateur,statutActivation,codeActivation,dateEnvoiCodeValidation,idEtatCivil,idTypeUtilisateur) VALUES(:identifiant,:matricule,:email,:password,:cgu,:dateCreation,:statutUtilisateur,:statutActivation,:codeActivation,:dateEnvoiCodeValidation,:idEtatCivil,:idTypeUtilisateur)";
                                $stmt = $bdP->prepare($sql);
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
                                    $stmtHistorique = $bdP->prepare($sqlHistorique);
                                    $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);


                                    if ($tmpStmtHistorique) {

                                        $link = $BASE_URL."activate-account/" . $authController->tokenencrypt($matricule).'/'.$codeActivation_encrypt;
                                        $message = "<html>
<head>
  <title>Activation de compte – ENT GSJLF</title>
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
    .cta-wrap {
      text-align: center;
      margin: 32px 0;
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
    .expiry-box {
      background-color: #fff8e1;
      border-left: 4px solid #f0cc6a;
      border-radius: 6px;
      padding: 14px 18px;
      margin: 24px 0;
    }
    .expiry-box p {
      margin: 0;
      font-size: 14px;
      color: #5c3e08;
      font-weight: 600;
    }
    .link-fallback {
      background-color: #f9f9f9;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      padding: 14px 18px;
      margin: 20px 0;
      word-break: break-all;
    }
    .link-fallback p {
      margin: 0 0 6px;
      font-size: 12px;
      color: #888;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .link-fallback a {
      font-size: 13px;
      color: #113B26;
      text-decoration: none;
    }
    .features {
      background-color: #f9fdf9;
      border: 1px solid rgba(17,59,38,.1);
      border-radius: 8px;
      padding: 18px 20px;
      margin: 20px 0;
    }
    .features p {
      margin: 0 0 10px;
      font-size: 13px;
      color: #113B26;
      font-weight: 700;
    }
    .features ul {
      margin: 0;
      padding-left: 20px;
    }
    .features li {
      font-size: 14px;
      color: #202124;
      line-height: 1.8;
    }
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
      <h1>✅ Activation de votre compte ENT</h1>
      <p>Groupe Scolaire Jean de la Fontaine</p>
    </div>

    <!-- CORPS -->
    <div class='body'>

      <p>Bonjour <strong>" . $prenom . " " . $nom . "</strong>,</p>

      <p>Votre compte sur l'ENT du <strong>Groupe Scolaire Jean de la Fontaine (GSJLF)</strong> a bien été créé. Pour finaliser votre inscription, veuillez activer votre compte en cliquant sur le bouton ci-dessous.</p>

      <div class='cta-wrap'>
        <a href='$link' class='cta-btn'>Activer mon compte</a>
      </div>

      <div class='expiry-box'>
        <p>⏱ Ce lien est valable <strong>24 heures</strong> à compter de la réception de cet email. Passé ce délai, vous devrez effectuer une nouvelle demande depuis la page d'activation.</p>
      </div>

      <p>Si le bouton ne fonctionne pas, copiez et collez le lien suivant dans votre navigateur :</p>

      <div class='link-fallback'>
        <p>Lien d'activation</p>
        <a href='$link'>$link</a>
      </div>

      <div class='features'>
        <p>Une fois votre compte activé, vous pourrez :</p>
        <ul>
          <li>Accéder à votre espace de travail personnel</li>
          <li>Consulter vos informations professionnelles</li>
          <li>Utiliser les services internes de l'établissement</li>
        </ul>
      </div>

      <div class='warning-box'>
        <p>⚠ Si vous n'êtes pas à l'origine de cette demande, ignorez cet email et contactez immédiatement le service informatique. Votre compte ne sera pas activé sans action de votre part.</p>
      </div>

      <p>Cordialement,<br>
      <strong>Le service informatique</strong><br>
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
                                        $emailSent = $authController->sendEmail($email, $prenom, "Activez votre compte maintenant !", $message);

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

    case 2:




        if (!empty($_POST['email']) && !empty($_POST['password'])) {

            try {
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bdP->beginTransaction();


                date_default_timezone_set('Africa/Dakar');

                $email = valid_donnees($_POST['email']);
                $password = valid_donnees($_POST['password']);


                $data = [
                    'email' => $email
                ];

                $sql = "SELECT * FROM utilisateurs WHERE email=:email";
                $stmt = $bdP->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if ($result) {


                    $matricule = $result->matricule;
                    $identifiant = $result->identifiant;
                    $idEtatCivil = $result->idEtatCivil;

                    if ($result->statutActivation == 1) {


                        if($result->statutUtilisateur == 0)
                        {
                            if (password_verify(valid_donnees($password), $result->password) == 1) {



                                if($result->statutCreerPar == 0)
                                {


                                    $dateCreation = new DateTime();
                                    $dateCreation = $dateCreation->format('Y-m-d H:i:s');

                                    $codeActivation = $authController->genererCode6Chiffres();
                                    $codeActivation_encrypt = $authController->tokenencrypt($codeActivation);

                                    $dataCandidat = [
                                        'matricule' => $matricule,
                                        'statut1' => 0,
                                        'statut2' => 1

                                    ];

                                    $sql = "UPDATE auth_create_password 
                                                    SET
                                                        statut = :statut1
                                                    WHERE matricule = :matricule AND statut=:statut2";

                                    $stmt = $bdP->prepare($sql);
                                    $tmpStmt = $stmt->execute($dataCandidat);

                                    if($tmpStmt)
                                    {

                                        $dateEnregistrement = new DateTime();
                                        $dateEnregistrement = $dateEnregistrement->format('Y-m-d H:i:s');

                                        $data_reset = [
                                            'matricule' => $matricule,
                                            'codeCreate' => $codeActivation_encrypt,
                                            'statut' => 1,
                                            'dateEnregistrement' => $dateEnregistrement,
                                        ];
                                        $sql_reset = "INSERT INTO auth_create_password(matricule,codeCreate,statut,dateEnregistrement) VALUES (:matricule,:codeCreate,:statut,:dateEnregistrement)";
                                        $stmt_reset  = $bdP->prepare($sql_reset);
                                        $tmpStmt_reset  = $stmt_reset ->execute($data_reset);

                                        if ($tmpStmt_reset == 1) {


                                            $table = "auth_create_password";
                                            $motif = "Connexion apres creation du compte par l'admin.";
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
                                            $stmtHistorique = $bdP->prepare($sqlHistorique);
                                            $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);

                                            if ($tmpStmtHistorique) {

                                                $bdP->commit();
                                                echo "mdp/personnel/user-modification-mdp/".$authController->tokenencrypt($result->matricule)."/".$codeActivation_encrypt;
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

                                    }else
                                    {
                                        if ($bdP->inTransaction()) {
                                            $bdP->rollBack();
                                        }
                                        echo "erreur";
                                        die;
                                    }






                                }else if($result->statutCreerPar == 1)
                                {

                                    $matricule = $result->matricule;
                                    $identifiant = $result->identifiant;
                                    $data_info_user = [
                                        'identifiant' => $identifiant
                                    ];

                                    $sql_info_user = "SELECT * FROM etatCivil WHERE identifiant=:identifiant";
                                    $stmt_info_user = $bdP->prepare($sql_info_user);
                                    $stmt_info_user->execute($data_info_user);
                                    $result_info_user = $stmt_info_user->fetch(PDO::FETCH_OBJ);

                                    if($result_info_user)
                                    {


                                        $prenom = ucwords(mb_strtolower($result_info_user->prenom));
                                        $nom= $authController->fctRetirerAccents(mb_strtoupper($result_info_user->nom));
                                        $infoEntite = $authController->infoEntite($identifiant);

                                        $photo = NULL;
                                        if ($authController->imageExiste($result_info_user->photo)) {
                                            $photo = $result_info_user->photo;
                                        } else {
                                            $photo = "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";

                                            if ($result_info_user->sexe == "Féminin") {
                                                $photo = "/personnel/includes/fpdf/template/avatar1.png";
                                            } else if ($result_info_user->sexe == "Masculin") {
                                                $photo = "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";
                                            } else {
                                                $photo = "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";
                                            }


                                        }


                                        // session id utilisateur personnel
                                        $_SESSION['tmpIdP'] = $result->id;
                                        $_SESSION['tmpMatricule'] = $matricule;
                                        $_SESSION['tmpPrenom'] = $prenom;
                                        $_SESSION['tmpNom'] =  $nom;
                                        $_SESSION['tmpPhoto'] =  $photo;
                                        $_SESSION['tmpEmail'] =  $email;
                                        $_SESSION['tmpInitiales'] = $authController->getInitiales($prenom, $nom);
                                        $_SESSION['tmpEntite'] = $infoEntite->entite;
                                        $infoApplication = $authController->infoApplication();


                                        // A faire apres pour avoir l'id Direction

                                        $retourDirectionUser = $authController->retourDirectionUser($matricule);



                                        if($retourDirectionUser == "erreur")
                                        {
                                            echo "erreur";
                                            die;
                                        }

                                        $_SESSION['user_direction'] = $retourDirectionUser;




                                        if($result->idTypeUtilisateur == 1)
                                        {
                                            //pour tous les utilisateurs admin = 1 et user simple = 2
                                            $_SESSION['connectUserGSJLF_ENT'] = 1;
                                            $_SESSION['tmpNbrAppli'] = $infoApplication->total_applications + 1;
                                            $_SESSION['tmpNbrAppliEnAttente'] = $infoApplication->en_attente;
                                            $_SESSION['tmpNbrAppliAutorisees'] = $infoApplication->total_applications - $infoApplication->en_attente + 1;
                                            $_SESSION['tmpNbrAppliRefusees'] = 0;

                                            //base de donnee criat
                                            $resultVerifierUserCRIAT = $authController->verifierUserCRIAT($bd,$email,$matricule,$prenom,$nom);

                                            if (is_object($resultVerifierUserCRIAT)) {
                                                $tmpId = $resultVerifierUserCRIAT->id;
                                                $_SESSION['tmpId'] = $tmpId;
                                            } else {
                                                session_destroy();
                                                echo "erreur1";
                                                die;
                                            }

                                            //base de donnee BASI
                                            $resultVerifierUserBASI = $authController->verifierUserBASI($bdBASI,$email,$matricule,$prenom,$nom);

                                            if (is_object($resultVerifierUserBASI)) {
                                                $tmpIdBASI = $resultVerifierUserBASI->id;
                                                $_SESSION['tmpIdBASI'] = $tmpIdBASI;
                                            } else {
                                                session_destroy();
                                                echo "erreur2";
                                                die;
                                            }


                                            // Liste des applications
                                            $listeApplications = $authController->listeApplications();





                                            if (!empty($listeApplications) && is_array($listeApplications)) {
                                                $_SESSION['tmpListeApplication'] = $listeApplications;
                                            } else {
                                                session_destroy();
                                                echo "erreur3";
                                                die;
                                            }


                                            // liste des taches
                                            $listeTachesParDefaut = $authController->listeTachesParDefaut();


                                            if (is_array($listeTachesParDefaut)) {
                                                $_SESSION['listeTachesParDefaut'] = $listeTachesParDefaut;
                                            } else {
                                                session_destroy();
                                                echo "erreur3";
                                                die;
                                            }


                                            $listeTachesIncarnes = $authController->listeTachesIncarnesAdmin();
                                            if (is_array($listeTachesIncarnes)) {
                                                $_SESSION['listeTachesIncarnes'] = $listeTachesIncarnes;
                                            } else {
                                                session_destroy();
                                                echo "erreur";
                                                die;
                                            }


                                            $listeTachesStructures = $authController->listeTachesStructuresAdmin();
                                            if (is_array($listeTachesStructures)) {
                                                $_SESSION['listeTachesStructures'] = $listeTachesStructures;
                                            } else {
                                                session_destroy();
                                                echo "erreur";
                                                die;
                                            }



                                            echo "succès/personnel/admin-accueil";
                                            die;

                                        }else
                                        {



                                            //pour tous les utilisateurs admin = 1 et user simple = 2
                                            $_SESSION['connectUserGSJLF_ENT'] = 2;

                                            $infoPosteResponsable = $authController->infoPosteResponsable($identifiant);

                                            $idFonction = null;
                                            $statutPoste = null;
                                            $nbrAppliGestTache = 0;
                                            if (!empty($infoPosteResponsable) && is_object($infoPosteResponsable)) {


                                                $idFonction = $infoPosteResponsable->idFonction;

                                                $retourInfoDirecteur = $authController->retourInfoDirecteur($idFonction);

                                                if($retourInfoDirecteur == "oui")
                                                {
                                                    $statutPoste = 1;
                                                    $nbrAppliGestTache = 1;

                                                }else
                                                {
                                                    $statutPoste = 0;

                                                }
                                            }


//                                        $listeApplicationUserSimple = $authController->listeApplicationUserSimple(
//                                            $matricule,
//                                            $idFonction
//                                        );

                                            $_SESSION['statutPoste'] = $statutPoste;

//
//                                        if (!empty($listeApplicationUserSimple) && is_array($listeApplicationUserSimple)) {
//
////                                            $_SESSION['tmpListeApplication'] = $listeApplicationUserSimple;
//
//                                            $tmpNbrAppli = $infoApplication->total_applications + $nbrAppliGestTache;
//                                            $_SESSION['tmpNbrAppli'] = $tmpNbrAppli;
//
//                                            $tmpNbrAppliEnAttente =  $infoApplication->en_attente;
//                                            $_SESSION['tmpNbrAppliEnAttente'] = $tmpNbrAppliEnAttente;
//
//                                            $tmpNbrAppliAutorisees = count($listeApplicationUserSimple) + $nbrAppliGestTache;
//                                            $_SESSION['tmpNbrAppliAutorisees'] = $tmpNbrAppliAutorisees;
//
//                                            $tmpNbrAppliRefusees = $tmpNbrAppli - $tmpNbrAppliEnAttente - $tmpNbrAppliAutorisees;
//                                            $_SESSION['tmpNbrAppliRefusees'] = max(
//                                                0,$tmpNbrAppliRefusees
//                                            );
//
//                                        } else {
//
//                                            session_destroy();
//                                            echo "erreur";
//                                            die;
//                                        }


                                            // Liste des applications avec règles de statut appliquées



                                            $tmpNbrAppliEnAttente = 0;
                                            $tmpNbrAppliAutorisees = 0;
                                            $tmpNbrAppliRefusees = 0;

                                            $listeApplicationsMerged = $authController->listeApplicationsMerged($matricule, $idFonction);



                                            if (!empty($listeApplicationsMerged) && is_array($listeApplicationsMerged)) {
                                                $_SESSION['tmpListeApplication'] = $listeApplicationsMerged;

                                                $tmpNbrAppli = count($listeApplicationsMerged) + $nbrAppliGestTache;
                                                $_SESSION['tmpNbrAppli'] = $tmpNbrAppli;

                                                foreach ($listeApplicationsMerged as $listeApplicationMerged) {

                                                    if($listeApplicationMerged->statutApplication == 0)
                                                    {
                                                        ++$tmpNbrAppliEnAttente;

                                                    }else if($listeApplicationMerged->statutApplication == 1)
                                                    {
                                                        ++$tmpNbrAppliAutorisees;

                                                    }else if($listeApplicationMerged->statutApplication == 2)
                                                    {
                                                        ++$tmpNbrAppliRefusees;
                                                    }

                                                }

                                                $_SESSION['tmpNbrAppliEnAttente'] = max(0,$tmpNbrAppliEnAttente);
                                                $_SESSION['tmpNbrAppliAutorisees'] = max(0,$tmpNbrAppliAutorisees + $nbrAppliGestTache);
                                                $_SESSION['tmpNbrAppliRefusees'] = max(
                                                    0,$tmpNbrAppliRefusees
                                                );


                                            } else {
                                                session_destroy();
                                                echo "erreur4";
                                                die;
                                            }



                                            // creer utilisateurs

                                            $listeBaseDoneeUserSimple = $authController->listeBaseDoneeUserSimple(
                                                $matricule,
                                                $idFonction
                                            );








                                            $tmp_nombre_bd = 0;
                                            if (is_array($listeBaseDoneeUserSimple)) {

                                                if(!empty($listeBaseDoneeUserSimple))
                                                {
                                                    foreach ($listeBaseDoneeUserSimple as $base) {


                                                        if(trim($base->code_base_donnees) == "criat_uahb"){
                                                            //base de donnee criat
                                                            $resultVerifierUserCRIAT = $authController->verifierUserCRIAT($bd,$email,$matricule,$prenom,$nom);

                                                            if (is_object($resultVerifierUserCRIAT)) {
                                                                $tmpId = $resultVerifierUserCRIAT->id;
                                                                $_SESSION['tmpId'] = $tmpId;
                                                                ++$tmp_nombre_bd;
                                                            } else {
                                                                session_destroy();
                                                                echo "erreur2";
                                                                die;
                                                            }

                                                        }

                                                        if(trim($base->code_base_donnees) == "basi") {


                                                            //base de donnee BASI
                                                            $resultVerifierUserBASI = $authController->verifierUserBASI($bdBASI,$email,$matricule,$prenom,$nom);

                                                            if (is_object($resultVerifierUserBASI)) {
                                                                $tmpIdBASI = $resultVerifierUserBASI->id;
                                                                $_SESSION['tmpIdBASI'] = $tmpIdBASI;
                                                                ++$tmp_nombre_bd;
                                                            } else {
                                                                session_destroy();
                                                                echo "erreur5";
                                                                die;
                                                            }
                                                        }



                                                    }








                                                    // importnat quand tout sera ok
//                                                        if(count($listeBaseDoneeUserSimple) != $tmp_nombre_bd)
//                                                        {
//                                                            session_destroy();
//                                                            echo "erreur";
//                                                            die;
//                                                        }
                                                }


                                            } else {

                                                session_destroy();
                                                echo "erreur6";
                                                die;
                                            }




                                            // liste des taches
                                            $listeTachesParDefaut = $authController->listeTachesParDefaut();
                                            if (is_array($listeTachesParDefaut)) {
                                                $_SESSION['listeTachesParDefaut'] = $listeTachesParDefaut;
                                            } else {
                                                session_destroy();
                                                echo "erreur7";
                                                die;
                                            }



                                            $listeTachesIncarnes = $authController->listeTachesIncarnes($idFonction,$matricule);

                                            if (is_array($listeTachesIncarnes)) {
                                                $_SESSION['listeTachesIncarnes'] = $listeTachesIncarnes;
                                            } else {
                                                session_destroy();
                                                echo "erreur8";
                                                die;
                                            }





                                            $listeTachesStructures = $authController->listeTachesStructures($idFonction);
                                            if (is_array($listeTachesStructures)) {
                                                $_SESSION['listeTachesStructures'] = $listeTachesStructures;
                                            } else {
                                                session_destroy();
                                                echo "erreur9";
                                                die;
                                            }

                                            // session direction si tmp


                                            $infoDirection = $authController->infoDirection($bdBASI,$idFonction);



                                            if (!empty($infoDirection) && is_object($infoDirection)) {
                                                $_SESSION['tmpIdDirection'] = $infoDirection->id;
                                            } else {
//                                                session_destroy();
//                                                echo "erreur22";
//                                                die;
                                            }


                                            echo "succès/personnel/user-accueil";
                                            die;
                                        }

                                    }else
                                    {
                                        echo "erreur";
                                        die;
                                    }
                                }else {
                                    echo "erreur";
                                    die;

                                }



                            } else {
                                echo "pasCompte";
                                die;

                            }
                        }else
                        {
                            echo "bloquer";
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
                if ($bdP->inTransaction()) {
                    $bdP->rollBack();
                }
                echo "erreur".$e;
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
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bdP->beginTransaction();



                date_default_timezone_set('Africa/Dakar');

                $matricule = valid_donnees($_POST['matricule']);
                $date_jour = date('d/m/Y H:i:s');
                $data = [
                    'matricule' => $matricule
                ];

                $sql = "SELECT * FROM utilisateurs WHERE matricule=:matricule";
                $stmt = $bdP->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if ($result) {

                    $email = $result->email;
                    $jourCreation = $authController->dateFranc($result->dateCreation);
                    $tempsExpire = "24 h";

                    if ($result->statutActivation == 1) {

                        echo "dejaActive";
                        die;

                    } else {

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


                            $identifiant = $result_perso->identifiant;
                            $idEtatCivil = $result_perso->idEtatCivil;
                            $prenom = ucwords($result_perso->prenom);
                            $nom = $authController->fctRetirerAccents(mb_strtoupper($result_perso->nom));


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

                                if ($authController->comparerDateContrat($finContrat)) {


                                    $dateCreation = new DateTime();
                                    $dateCreation = $dateCreation->format('Y-m-d H:i:s');

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

                                    $stmt = $bdP->prepare($sql);
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
                                        $stmtHistorique = $bdP->prepare($sqlHistorique);
                                        $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);

                                        if ($tmpStmtHistorique) {

                                            $link = $BASE_URL."activate-account/" . $authController->tokenencrypt($matricule)."/".$codeActivation_encrypt;

                                            $message = "<html>
<head>
  <title>Nouveau lien d'activation – ENT GSJLF</title>
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
    .cta-wrap {
      text-align: center;
      margin: 32px 0;
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
    .info-box {
      background-color: #e8f5e9;
      border-left: 4px solid #2e7d32;
      border-radius: 6px;
      padding: 14px 18px;
      margin: 24px 0;
    }
    .info-box p {
      margin: 0;
      font-size: 14px;
      color: #1b5e20;
      font-weight: 600;
    }
    .expiry-box {
      background-color: #fff8e1;
      border-left: 4px solid #f0cc6a;
      border-radius: 6px;
      padding: 14px 18px;
      margin: 24px 0;
    }
    .expiry-box p {
      margin: 0;
      font-size: 14px;
      color: #5c3e08;
      font-weight: 600;
    }
    .link-fallback {
      background-color: #f9f9f9;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      padding: 14px 18px;
      margin: 20px 0;
      word-break: break-all;
    }
    .link-fallback p {
      margin: 0 0 6px;
      font-size: 12px;
      color: #888;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .link-fallback a {
      font-size: 13px;
      color: #113B26;
      text-decoration: none;
    }
    .features {
      background-color: #f9fdf9;
      border: 1px solid rgba(17,59,38,.1);
      border-radius: 8px;
      padding: 18px 20px;
      margin: 20px 0;
    }
    .features p {
      margin: 0 0 10px;
      font-size: 13px;
      color: #113B26;
      font-weight: 700;
    }
    .features ul {
      margin: 0;
      padding-left: 20px;
    }
    .features li {
      font-size: 14px;
      color: #202124;
      line-height: 1.8;
    }
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
      <h1>🔁 Nouveau lien d'activation</h1>
      <p>Groupe Scolaire Jean de la Fontaine — ENT</p>
    </div>

    <!-- CORPS -->
    <div class='body'>

      <p>Bonjour <strong>" . $prenom . " " . $nom . "</strong>,</p>

      <p>Vous avez demandé un nouveau lien d'activation pour votre compte ENT du <strong>Groupe Scolaire Jean de la Fontaine (GSJLF)</strong>. Votre précédent lien avait expiré.</p>

      <div class='info-box'>
        <p>✅ Un nouveau lien d'activation a été généré. L'ancien lien n'est plus valide.</p>
      </div>

      <p>Cliquez sur le bouton ci-dessous pour activer votre compte :</p>

      <div class='cta-wrap'>
        <a href='$link' class='cta-btn'>Activer mon compte</a>
      </div>

      <div class='expiry-box'>
        <p>⏱ Ce nouveau lien est valable <strong>24 heures</strong> à compter de la réception de cet email. Passé ce délai, vous devrez effectuer une nouvelle demande depuis la page d'activation.</p>
      </div>

      <p>Si le bouton ne fonctionne pas, copiez et collez le lien suivant dans votre navigateur :</p>

      <div class='link-fallback'>
        <p>Lien d'activation</p>
        <a href='$link'>$link</a>
      </div>

      <div class='features'>
        <p>Une fois votre compte activé, vous pourrez :</p>
        <ul>
          <li>Accéder à votre espace de travail personnel</li>
          <li>Consulter vos informations professionnelles</li>
          <li>Utiliser les services internes de l'établissement</li>
        </ul>
      </div>

      <div class='warning-box'>
        <p>⚠ Si vous n'êtes pas à l'origine de cette demande, ignorez cet email et contactez immédiatement le service informatique. Votre compte ne sera pas activé sans action de votre part.</p>
      </div>

      <p>Cordialement,<br>
      <strong>Le service informatique</strong><br>
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
</html>";                                            // Envoyer l'e-mail
                                            $emailSent = $authController->sendEmail($email, $prenom, "Activez votre compte maintenant !", $message);

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
                            echo "erreur";
                            die;

                        }

                    }

                } else {
                    echo "erreur";
                    die;
                }

            }catch (Exception $e) {
                if ($bdP->inTransaction()) {
                    $bdP->rollBack();
                }
                echo "erreur";
                die;
            }


        } else {
            echo "erreur";
            die;
        }
        break;
//    case 4 :


//        if (!empty($_POST['matricule']) && !empty($_POST['code'])) {
//
//
//
//            try {
//                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//                $bdP->beginTransaction();
//
//
//
//                date_default_timezone_set('Africa/Dakar');
//
//                $matricule = valid_donnees($_POST['matricule']);
//                $code = valid_donnees($_POST['code']);
//
//                $date_jour = date('d/m/Y H:i:s');
//                $data = [
//                    'matricule' => $matricule
//                ];
//
//                $sql = "SELECT * FROM utilisateurs WHERE matricule=:matricule";
//                $stmt = $bdP->prepare($sql);
//                $stmt->execute($data);
//                $result = $stmt->fetch(PDO::FETCH_OBJ);
//
//                if ($result) {
//
//
//                    if ($result->statutActivation != 1) {
//
//                        $data_perso = [
//                            'matricule' => $matricule
//                        ];
//                        $sql_perso = "SELECT
//    p.identifiant,
//    p.idEtatCivil,
//    ec.prenom,
//    ec.nom,
//    cg.email
//FROM personnels p
//INNER JOIN etatCivil ec
//    ON p.idEtatCivil = ec.id
//LEFT JOIN compteGmail cg
//    ON p.idCompteGmail = cg.id
//WHERE p.matricule = :matricule;";
//                        $stmt_perso = $bdP->prepare($sql_perso);
//                        $stmt_perso->execute($data_perso);
//                        $result_perso = $stmt_perso->fetch(PDO::FETCH_OBJ);
//
//                        if ($result_perso) {
//
//
//                            $identifiant = $result_perso->identifiant;
//                            $idEtatCivil = $result_perso->idEtatCivil;
//                            $prenom = ucwords($result_perso->prenom);
//                            $nom = $authController->fctRetirerAccents(mb_strtoupper($result_perso->nom));
//
//
//                            $data_perso_contrat = [
//                                'matricule' => $matricule,
//                                'idTypeStatutContrat' => 1,
//
//                            ];
//                            $sql_perso_contrat = "SELECT * FROM contrat WHERE matricule=:matricule AND idTypeStatutContrat=:idTypeStatutContrat";
//                            $stmt_perso_contrat = $bdP->prepare($sql_perso_contrat);
//                            $stmt_perso_contrat->execute($data_perso_contrat);
//                            $result_perso_contrat = $stmt_perso_contrat->fetch(PDO::FETCH_OBJ);
//
//                            if ($result_perso_contrat) {
//
//                                $debutContrat = $result_perso_contrat->dateDebutContrat;
//                                $finContrat = $result_perso_contrat->dateFinContrat;
//
//
//                                if ($authController->comparerDate($finContrat)) {
//
//
//                                    if ($result->codeActivation == $authController->tokenencrypt($code)) {
//
//                                        $dateCreation = new DateTime();
//                                        $dateCreation = $dateCreation->format('Y-m-d H:i:s');
//
//                                        $codeActivation = $authController->genererCode6Chiffres();
//                                        $codeActivation_encrypt = $authController->tokenencrypt($codeActivation);
//
//                                        $dataCandidat = [
//                                            'matricule' => valid_donnees($_POST['matricule']),
//                                            'statutActivation' => 1,
//                                            'dateActivation' => $dateCreation,
//                                        ];
//
//                                        $sql = "UPDATE utilisateurs
//        SET
//            statutActivation = :statutActivation,
//            dateActivation = :dateActivation
//        WHERE matricule = :matricule";
//
//                                        $stmt = $bdP->prepare($sql);
//                                        $tmpStmt = $stmt->execute($dataCandidat);
//
//                                        if ($tmpStmt == 1) {
//
//
//                                            $table = "utilisateurs";
//                                            $motif = "Activation du compte";
//                                            $dateEnregistrement = new DateTime();
//                                            $dateEnregistrement = $dateEnregistrement->format('Y-m-d H:i:s');
//                                            $dataHistorique = [
//                                                'identifiant' => $identifiant,
//                                                'matricule' => $matricule,
//                                                'tableHistorique' => $table,
//                                                'motif' => $motif,
//                                                'idEtatCivil' => $idEtatCivil,
//                                                'dateEnregistremenent' => $dateEnregistrement,
//                                            ];
//                                            $sqlHistorique = "INSERT INTO auth_personnel_historiques(identifiant,matricule,tableHistorique,motif,idEtatCivil,dateEnregistremenent) VALUES (:identifiant,:matricule,:tableHistorique,:motif,:idEtatCivil,:dateEnregistremenent)";
//                                            $stmtHistorique = $bdP->prepare($sqlHistorique);
//                                            $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);
//
//                                            if ($tmpStmtHistorique) {
//
//                                                $bdP->commit();
//                                                echo "succès";
//                                                die;
//
//                                            } else {
//                                                if ($bdP->inTransaction()) {
//    $bdP->rollBack();
//}
//                                                echo "erreur";
//                                                die;
//                                            }
//
//
//                                        } else {
//                                            echo "erreur";
//                                            die;
//                                        }
//
//
//                                    } else {
//                                        echo "codeIncorrect";
//                                        die;
//                                    }
//
//
//                                } else {
//
//                                    echo "finContrat";
//                                    die;
//
//                                }
//
//
//                            } else {
//                                echo "pasContrat";
//                                die;
//                            }
//
//
//                        } else {
//                            echo "matriculeExistsPas";
//                            die;
//
//                        }
//
//                    } else {
//                        echo "dejaActive";
//                        die;
//                    }
//
//
//                } else {
//                    echo "erreur";
//                    die;
//                }
//
//
//
//            }catch (Exception $e) {
//                if ($bdP->inTransaction()) {
//    $bdP->rollBack();
//}
//                echo "erreur";
//                die;
//            }
//
//        } else {
//            echo "erreur";
//            die;
//        }
//        break;
//    case 5 :


//        if (!empty($_POST['matricule']) && !empty($_POST['email'])) {
//
//            try {
//                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//                $bdP->beginTransaction();
//
//
//                date_default_timezone_set('Africa/Dakar');
//
//                $email = valid_donnees($_POST['email']);
//                $matricule = valid_donnees($_POST['matricule']);
//                $password = valid_donnees($_POST['password']);
//
//
//                $data = [
//                    'email' => $email
//                ];
//
//                $sql = "SELECT * FROM utilisateurs WHERE email=:email";
//                $stmt = $bdP->prepare($sql);
//                $stmt->execute($data);
//                $result = $stmt->fetch(PDO::FETCH_OBJ);
//
//                if ($result) {
//
//
//                    if ($result->statutActivation == 1) {
//
//                        $data_perso = [
//                            'matricule' => $matricule
//                        ];
//                        $sql_perso = "SELECT
//                                    p.identifiant,
//                                    p.idEtatCivil,
//                                    ec.prenom,
//                                    ec.nom,
//                                    cg.email
//                                FROM personnels p
//                                INNER JOIN etatCivil ec
//                                    ON p.idEtatCivil = ec.id
//                                LEFT JOIN compteGmail cg
//                                    ON p.idCompteGmail = cg.id
//                                WHERE p.matricule = :matricule;";
//                        $stmt_perso = $bdP->prepare($sql_perso);
//                        $stmt_perso->execute($data_perso);
//                        $result_perso = $stmt_perso->fetch(PDO::FETCH_OBJ);
//
//                        if ($result_perso) {
//
//
//                            $identifiant = $result_perso->identifiant;
//                            $idEtatCivil = $result_perso->idEtatCivil;
//                            $prenom = ucwords($result_perso->prenom);
//                            $nom = $authController->fctRetirerAccents(mb_strtoupper($result_perso->nom));
//
//
//                            $data_perso_contrat = [
//                                'matricule' => $matricule,
//                                'idTypeStatutContrat' => 1,
//
//                            ];
//                            $sql_perso_contrat = "SELECT * FROM contrat WHERE matricule=:matricule AND idTypeStatutContrat=:idTypeStatutContrat";
//                            $stmt_perso_contrat = $bdP->prepare($sql_perso_contrat);
//                            $stmt_perso_contrat->execute($data_perso_contrat);
//                            $result_perso_contrat = $stmt_perso_contrat->fetch(PDO::FETCH_OBJ);
//
//                            if ($result_perso_contrat) {
//
//                                $debutContrat = $result_perso_contrat->dateDebutContrat;
//                                $finContrat = $result_perso_contrat->dateFinContrat;
//
//                                if ($authController->comparerDate($finContrat)) {
//
//
//                                    $dateCreation = new DateTime();
//                                    $dateCreation = $dateCreation->format('Y-m-d H:i:s');
//                                    $password = password_hash($password, PASSWORD_DEFAULT);
//                                    $codeActivation = $authController->genererCode6Chiffres();
//                                    $codeActivation_encrypt = $authController->tokenencrypt($codeActivation);
//
//                                    $dataCandidat = [
//                                        'matricule' => valid_donnees($_POST['matricule']),
//                                        'statutActivation' => 0,
//                                        'codeActivation' => $codeActivation_encrypt,
//                                        'dateEnvoiCodeValidation' => $dateCreation,
//                                    ];
//
//                                    $sql = "UPDATE utilisateurs
//                                                    SET
//                                                        statutActivation = :statutActivation,
//                                                        codeActivation = :codeActivation,
//                                                        dateEnvoiCodeValidation = :dateEnvoiCodeValidation
//                                                    WHERE matricule = :matricule";
//
//                                    $stmt = $bdP->prepare($sql);
//                                    $tmpStmt = $stmt->execute($dataCandidat);
//
//                                    if ($tmpStmt == 1) {
//
//
//                                        $table = "utilisateurs";
//                                        $motif = "Renvoyer un nouveau code d'activation";
//                                        $dateEnregistrement = new DateTime();
//                                        $dateEnregistrement = $dateEnregistrement->format('Y-m-d H:i:s');
//                                        $dataHistorique = [
//                                            'identifiant' => $identifiant,
//                                            'matricule' => $matricule,
//                                            'tableHistorique' => $table,
//                                            'motif' => $motif,
//                                            'idEtatCivil' => $idEtatCivil,
//                                            'dateEnregistremenent' => $dateEnregistrement,
//                                        ];
//                                        $sqlHistorique = "INSERT INTO auth_personnel_historiques(identifiant,matricule,tableHistorique,motif,idEtatCivil,dateEnregistremenent) VALUES (:identifiant,:matricule,:tableHistorique,:motif,:idEtatCivil,:dateEnregistremenent)";
//                                        $stmtHistorique = $bdP->prepare($sqlHistorique);
//                                        $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);
//
//                                        if ($tmpStmtHistorique) {
//
//                                            $link = $BASE_URL."activate-account/" . $authController->tokenencrypt($matricule);
//
//                                            $message = "<html>
//<head>
//  <title>Code d'activation – Demande d'admission en ligne</title>
//  <style>
//p{
//font-size: 16px;
//font-family: Roboto, Arial, sans-serif;
//color: #202124;
//background-color: #ffffff;
//}
//ul, li{
//font-size: 16px;
//font-family: Roboto, Arial, sans-serif;
//color: #202124;
//background-color: #ffffff;
//}
//.code {
//text-align: center;
//font-size: 32px;
//font-weight: bold;
//letter-spacing: 6px;
//color: #28A745;
//margin: 20px 0;
//}
//  </style>
//</head>
//<body>
//
//  <p>Bonjour " . $prenom . " " . $nom . ",</p>
//
//  <p>Vous venez de créer votre compte sur l’ENT du <strong>Groupe Scolaire Jean de la Fontaine (GSJLF)</strong>.</p>
//
//  <p>Pour activer votre compte, veuillez utiliser le code d’activation ci-dessous :</p>
//
//  <div class='code'>$codeActivation</div>
//
//  <p>Ce code vous sera demandé afin de confirmer votre identité et finaliser l’activation de votre compte.</p>
//                                        <p>Veuillez utiliser le lien suivant pour accéder à la page d’activation : <a href='$link'>Activer mon compte</a></p>
//
//  <p><strong>Important :</strong> ce code est valable pour une durée limitée. Pour des raisons de sécurité, ne le partagez avec personne.</p>
//
//  <p>Une fois votre compte activé, vous pourrez :</p>
//  <ul>
//    <li>Accéder à votre espace de travail</li>
//    <li>Consulter vos informations professionnelles</li>
//    <li>Utiliser les services internes de l’établissement</li>
//  </ul>
//<p>
//Si vous n’êtes pas à l’origine de cette demande, veuillez ignorer cet email ou contacter immédiatement le service informatique à :
//<a href=\"mailto:criat@uahb.sn\">criat@uahb.sn</a>.
//</p>
//  <p>Cordialement,<br>
//  Le service d'informatique<br>
//  Groupe Scolaire Jean de la Fontaine</p>
//
//</body>
//</html>";
//                                            // Envoyer l'e-mail
//                                            $emailSent = $authController->sendEmail($email, $prenom, "Activez votre compte maintenant !", $message);
//
//                                            if (!$emailSent) {
//                                                if ($bdP->inTransaction()) {
//    $bdP->rollBack();
//}
//
//                                                echo "erreurMail";
//                                                die;
//                                            } else {
//
//                                                $bdP->commit();
//                                                echo "succès" . $authController->tokenencrypt($matricule);
//                                                die;
//
//                                            }
//
//                                        } else {
//                                            if ($bdP->inTransaction()) {
//    $bdP->rollBack();
//}
//                                            echo "erreur";
//                                            die;
//                                        }
//
//
//                                    } else {
//                                        echo "erreur";
//                                        die;
//                                    }
//
//
//                                } else {
//
//                                    echo "erreur";
//                                    die;
//
//                                }
//
//
//                            } else {
//                                echo "pasContrat";
//                                die;
//                            }
//
//
//                        } else {
//                            echo "erreur";
//                            die;
//
//                        }
//
//                    } else {
//                        echo "compteInactive";
//                        die;
//                    }
//
//                } else {
//                    echo "pasCompte";
//                    die;
//
//                }
//
//            } catch (Exception $e) {
//                if ($bdP->inTransaction()) {
//    $bdP->rollBack();
//}
//                echo "erreur" . $e;
//                die;
//            }
//
//
//        } else {
//            echo "champsObligatoire";
//            die;
//        }
//        break;

    case 4:



        if (!empty($_POST['matricule']) && !empty($_POST['email'])) {

            try {
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bdP->beginTransaction();


                date_default_timezone_set('Africa/Dakar');

                $email = valid_donnees($_POST['email']);
                $matricule = valid_donnees($_POST['matricule']);


                $data = [
                    'email' => $email,
                    'matricule' => $matricule
                ];

                $sql = "SELECT * FROM utilisateurs WHERE email=:email AND matricule=:matricule";
                $stmt = $bdP->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if ($result) {


                    if ($result->statutActivation == 1) {

                        if ($result->statutUtilisateur == 0) {



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


                                $identifiant = $result_perso->identifiant;
                                $idEtatCivil = $result_perso->idEtatCivil;
                                $prenom = ucwords($result_perso->prenom);
                                $nom = $authController->fctRetirerAccents(mb_strtoupper($result_perso->nom));


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

                                    if ($authController->comparerDateContrat($finContrat)) {


                                        $dateCreation = new DateTime();
                                        $dateCreation = $dateCreation->format('Y-m-d H:i:s');

                                        $codeActivation = $authController->genererCode6Chiffres();
                                        $codeActivation_encrypt = $authController->tokenencrypt($codeActivation);

                                        $dataCandidat = [
                                            'matricule' => $matricule,
                                            'statut1' => 0,
                                            'statut2' => 1

                                        ];

                                        $sql = "UPDATE auth_reset_password 
                                                    SET
                                                        statut = :statut1
                                                    WHERE matricule = :matricule AND statut=:statut2";

                                        $stmt = $bdP->prepare($sql);
                                        $tmpStmt = $stmt->execute($dataCandidat);

                                        if($tmpStmt)
                                        {

                                            $dateEnregistrement = new DateTime();
                                            $dateEnregistrement = $dateEnregistrement->format('Y-m-d H:i:s');

                                            $data_reset = [
                                                'matricule' => $matricule,
                                                'codeReset' => $codeActivation_encrypt,
                                                'statut' => 1,
                                                'dateEnregistrement' => $dateEnregistrement,
                                            ];
                                            $sql_reset = "INSERT INTO auth_reset_password(matricule,codeReset,statut,dateEnregistrement) VALUES (:matricule,:codeReset,:statut,:dateEnregistrement)";
                                            $stmt_reset  = $bdP->prepare($sql_reset);
                                            $tmpStmt_reset  = $stmt_reset ->execute($data_reset);

                                            if ($tmpStmt_reset == 1) {


                                                $table = "auth_reset_password";
                                                $motif = "Demande de modification du mot de passe";
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
                                                $stmtHistorique = $bdP->prepare($sqlHistorique);
                                                $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);

                                                if ($tmpStmtHistorique) {

                                                    $resetLink = $BASE_URL."change-password/" . $authController->tokenencrypt($matricule)."/".$codeActivation_encrypt;

                                                    $message = "<html>
<head>
  <title>Réinitialisation de mot de passe – ENT GSJLF</title>
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
    .cta-wrap {
      text-align: center;
      margin: 32px 0;
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
    .expiry-box {
      background-color: #fff8e1;
      border-left: 4px solid #f0cc6a;
      border-radius: 6px;
      padding: 14px 18px;
      margin: 24px 0;
    }
    .expiry-box p {
      margin: 0;
      font-size: 14px;
      color: #5c3e08;
      font-weight: 600;
    }
    .link-fallback {
      background-color: #f9f9f9;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      padding: 14px 18px;
      margin: 20px 0;
      word-break: break-all;
    }
    .link-fallback p {
      margin: 0 0 6px;
      font-size: 12px;
      color: #888;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .link-fallback a {
      font-size: 13px;
      color: #113B26;
      text-decoration: none;
    }
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
      <h1>🔐 Réinitialisation de mot de passe</h1>
      <p>Groupe Scolaire Jean de la Fontaine — ENT</p>
    </div>

    <!-- CORPS -->
    <div class='body'>

      <p>Bonjour <strong>" . $prenom . " " . $nom . "</strong>,</p>

      <p>Nous avons reçu une demande de réinitialisation du mot de passe associé à votre compte ENT du <strong>Groupe Scolaire Jean de la Fontaine (GSJLF)</strong>.</p>

      <p>Cliquez sur le bouton ci-dessous pour définir un nouveau mot de passe :</p>

      <div class='cta-wrap'>
        <a href='".$resetLink."' class='cta-btn'>Réinitialiser mon mot de passe</a>
      </div>

      <div class='expiry-box'>
        <p>⏱ Ce lien est valable <strong>24 heures</strong> à compter de la réception de cet email. Passé ce délai, vous devrez effectuer une nouvelle demande.</p>
      </div>

      <p>Si le bouton ne fonctionne pas, copiez et collez le lien suivant dans votre navigateur :</p>

      <div class='link-fallback'>
        <p>Lien de réinitialisation</p>
        <a href='".$resetLink."'>$resetLink</a>
      </div>

      <div class='warning-box'>
        <p>⚠ Si vous n'êtes pas à l'origine de cette demande, ignorez cet email. Votre mot de passe restera inchangé. En cas de doute, contactez immédiatement le service informatique.</p>
      </div>

      <p>Cordialement,<br>
      <strong>Le service informatique</strong><br>
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
                                                    $emailSent = $authController->sendEmail($email, $prenom, $authController->decode("Réinitialisez votre mot de passe !"), $message);

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

                                        echo "finContrat";
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

                        }else
                        {

                            echo "bloquer";
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

    case 5 :



        if (!empty($_POST['matricule']) && !empty($_POST['code']) && !empty($_POST['password']) && !empty($_POST['confirm-password'])) {

            try {
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bdP->beginTransaction();


                date_default_timezone_set('Africa/Dakar');
                $date_jour = date('Y-m-d');

                $matricule = valid_donnees($_POST['matricule']);
                $code = valid_donnees($_POST['code']);
                $password = valid_donnees($_POST['password']);
                $confirmPassword = valid_donnees($_POST['confirm-password']);

                if ($password !== $confirmPassword) {
                    echo "pasCorrespondantPWD";
                    die;

                }

                $password = password_hash($password, PASSWORD_DEFAULT);


                if (strlen($password) < 8) {
                    echo "passwordCourt";
                    die;
                }

                $data = [
                    'matricule' => $matricule
                ];

                $sql = "SELECT * FROM utilisateurs WHERE matricule=:matricule";
                $stmt = $bdP->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if ($result) {



                    $data_reset_code = [
                        'matricule' => $matricule,
                        'statut' => 1
                    ];

                    $sql_reset_code = "SELECT * FROM auth_reset_password WHERE matricule=:matricule AND statut=:statut";
                    $stmt_reset_code = $bdP ->prepare($sql_reset_code);
                    $stmt_reset_code->execute($data_reset_code);
                    $result_reset_code = $stmt_reset_code->fetch(PDO::FETCH_OBJ);


                    if($result_reset_code)
                    {

                        if($result_reset_code->codeReset == $code)
                        {

                            if ($result->statutActivation == 1) {

                                if ($result->statutUtilisateur == 0) {

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


                                        $identifiant = $result_perso->identifiant;
                                        $idEtatCivil = $result_perso->idEtatCivil;


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

                                            if ($authController->comparerDateContrat($finContrat)) {



                                                $data_reset_up =
                                                    [
                                                        'matricule' => $matricule,
                                                        'statut1' => 0,
                                                        'statut2' => 1
                                                    ];
                                                $sql_reset_up = "UPDATE auth_reset_password 
                                                    SET
                                                        statut = :statut1
                                                    WHERE matricule = :matricule AND statut=:statut2";

                                                $stmt_reset_up = $bdP->prepare($sql_reset_up);
                                                $tmpStmt_reset_up = $stmt_reset_up->execute($data_reset_up);

                                                if($tmpStmt_reset_up)
                                                {

                                                    $data_utilisateur= [
                                                        'matricule' => $matricule,
                                                        'password' => $password

                                                    ];

                                                    $sql_utilisateur = "UPDATE utilisateurs 
                                                        SET
                                                            password = :password
                                                        WHERE matricule = :matricule";

                                                    $stmt_utilisateur = $bdP->prepare($sql_utilisateur);
                                                    $tmpStmt_utilisateur = $stmt_utilisateur->execute($data_utilisateur);

                                                    if($tmpStmt_utilisateur)
                                                    {

                                                        $table = "auth_reset_password,utilisateurs";
                                                        $motif = "Mot de passe modifié";
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
                                                        $stmtHistorique = $bdP->prepare($sqlHistorique);
                                                        $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);

                                                        if ($tmpStmtHistorique) {


                                                            $bdP->commit();
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
                                        echo "erreur";
                                        die;

                                    }

                                }else
                                {
                                    echo "bloquer";
                                    die;
                                }


                            } else {
                                if ($bdP->inTransaction()) {
                                    $bdP->rollBack();
                                }
                                echo "compteInactive";
                                die;
                            }


                        }else
                        {
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
                    echo "pasCompte";
                    die;

                }

            } catch (Exception $e) {
                if ($bdP->inTransaction()) {
                    $bdP->rollBack();
                }
                echo "erreur".$e;
                die;
            }


        } else {
            echo "champsObligatoire";
            die;
        }
        break;

    case 6 :

        if (!empty($_POST['matricule']) && !empty($_POST['code']) && !empty($_POST['password']) && !empty($_POST['confirm-password'])) {

            try {
                $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bdP->beginTransaction();


                date_default_timezone_set('Africa/Dakar');
                $date_jour = date('Y-m-d');

                $matricule = valid_donnees($_POST['matricule']);
                $code = valid_donnees($_POST['code']);
                $password = valid_donnees($_POST['password']);
                $confirmPassword = valid_donnees($_POST['confirm-password']);

                if ($password !== $confirmPassword) {
                    echo "pasCorrespondantPWD";
                    die;

                }

                $password = password_hash($password, PASSWORD_DEFAULT);


                if (strlen($password) < 8) {
                    echo "passwordCourt";
                    die;
                }

                $data = [
                    'matricule' => $matricule
                ];

                $sql = "SELECT * FROM utilisateurs WHERE matricule=:matricule";
                $stmt = $bdP->prepare($sql);
                $stmt->execute($data);
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                if ($result) {


                    $email = $result->email;

                    $data_reset_code = [
                        'matricule' => $matricule,
                        'statut' => 1
                    ];

                    $sql_reset_code = "SELECT * FROM auth_create_password WHERE matricule=:matricule AND statut=:statut";
                    $stmt_reset_code = $bdP ->prepare($sql_reset_code);
                    $stmt_reset_code->execute($data_reset_code);
                    $result_reset_code = $stmt_reset_code->fetch(PDO::FETCH_OBJ);


                    if($result_reset_code)
                    {

                        if($result_reset_code->codeCreate == $code)
                        {

                            if ($result->statutActivation == 1) {

                                if ($result->statutUtilisateur == 0) {

                                    if($result->statutCreerPar == 0 && $result->creerPar == "Admin" )
                                    {

                                        if (password_verify(valid_donnees($password), $result->password) == 1) {

                                            echo "memeMotDePasse";
                                            die;

                                        }else
                                        {

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


                                                $identifiant = $result_perso->identifiant;
                                                $idEtatCivil = $result_perso->idEtatCivil;


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

                                                    if ($authController->comparerDateContrat($finContrat)) {



                                                        $data_reset_up =
                                                            [
                                                                'matricule' => $matricule,
                                                                'statut1' => 0,
                                                                'statut2' => 1
                                                            ];
                                                        $sql_reset_up = "UPDATE auth_create_password 
                                                    SET
                                                        statut = :statut1
                                                    WHERE matricule = :matricule AND statut=:statut2";

                                                        $stmt_reset_up = $bdP->prepare($sql_reset_up);
                                                        $tmpStmt_reset_up = $stmt_reset_up->execute($data_reset_up);

                                                        if($tmpStmt_reset_up)
                                                        {

                                                            $data_utilisateur= [
                                                                'matricule' => $matricule,
                                                                'password' => $password,
                                                                'statutCreerPar1' => 1,
                                                                'statutCreerPar2' => 0,
                                                                'creerPar' => "Admin"

                                                            ];

                                                            $sql_utilisateur = "UPDATE utilisateurs 
                                                        SET
                                                            password = :password,
                                                            statutCreerPar = :statutCreerPar1                             
                                                        WHERE matricule = :matricule AND statutCreerPar = :statutCreerPar2 AND creerPar = :creerPar";

                                                            $stmt_utilisateur = $bdP->prepare($sql_utilisateur);
                                                            $tmpStmt_utilisateur = $stmt_utilisateur->execute($data_utilisateur);

                                                            if($tmpStmt_utilisateur)
                                                            {

                                                                $table = "auth_create_password,utilisateurs";
                                                                $motif = "Mot de passe modifié après la création du compte par l'administrateur.";
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
                                                                $stmtHistorique = $bdP->prepare($sqlHistorique);
                                                                $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);

                                                                if ($tmpStmtHistorique) {



                                                                    $data_info_user = [
                                                                        'identifiant' => $identifiant
                                                                    ];

                                                                    $sql_info_user = "SELECT * FROM etatCivil WHERE identifiant=:identifiant";
                                                                    $stmt_info_user = $bdP->prepare($sql_info_user);
                                                                    $stmt_info_user->execute($data_info_user);
                                                                    $result_info_user = $stmt_info_user->fetch(PDO::FETCH_OBJ);

                                                                    if($result_info_user)
                                                                    {


                                                                        $prenom = ucwords(mb_strtolower($result_info_user->prenom));
                                                                        $nom= $authController->fctRetirerAccents(mb_strtoupper($result_info_user->nom));
                                                                        $infoEntite = $authController->infoEntite($identifiant);


                                                                        $photo = NULL;
                                                                        if ($authController->imageExiste($result_info_user->photo)) {
                                                                            $photo = $result_info_user->photo;
                                                                        } else {
                                                                            $photo = "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";

                                                                            if ($result_info_user->sexe == "Féminin") {
                                                                                $photo = "/personnel/includes/fpdf/template/avatar1.png";
                                                                            } else if ($result_info_user->sexe == "Masculin") {
                                                                                $photo = "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";
                                                                            } else {
                                                                                $photo = "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";
                                                                            }


                                                                        }

                                                                        // session id utilisateur personnel
                                                                        $_SESSION['tmpIdP'] = $result->id;
                                                                        $_SESSION['tmpMatricule'] = $matricule;
                                                                        $_SESSION['tmpPrenom'] = $prenom;
                                                                        $_SESSION['tmpNom'] =  $nom;
                                                                        $_SESSION['tmpPhoto'] =  $photo;
                                                                        $_SESSION['tmpEmail'] =  $result_perso->email;
                                                                        $_SESSION['tmpInitiales'] = $authController->getInitiales($prenom, $nom);
                                                                        $_SESSION['tmpEntite'] = $infoEntite->entite;
                                                                        $infoApplication = $authController->infoApplication();
// A faire apres pour avoir l'id Direction

                                                                        $retourDirectionUser = $authController->retourDirectionUser($matricule);

                                                                        if($retourDirectionUser == "erreur")
                                                                        {
                                                                            echo "erreur";
                                                                            die;
                                                                        }

                                                                        $_SESSION['user_direction'] = $retourDirectionUser;




                                                                        if($result->idTypeUtilisateur == 1)
                                                                        {
                                                                            //pour tous les utilisateurs admin = 1 et user simple = 2
                                                                            $_SESSION['connectUserGSJLF_ENT'] = 1;
                                                                            $_SESSION['tmpNbrAppli'] = $infoApplication->total_applications;
                                                                            $_SESSION['tmpNbrAppliEnAttente'] = $infoApplication->en_attente;
                                                                            $_SESSION['tmpNbrAppliAutorisees'] = $infoApplication->total_applications - $infoApplication->en_attente;
                                                                            $_SESSION['tmpNbrAppliRefusees'] = 0;

                                                                            //base de donnee criat
                                                                            $resultVerifierUserCRIAT = $authController->verifierUserCRIAT($bd,$email,$matricule,$prenom,$nom);

                                                                            if (is_object($resultVerifierUserCRIAT)) {
                                                                                $tmpId = $resultVerifierUserCRIAT->id;
                                                                                $_SESSION['tmpId'] = $tmpId;
                                                                            } else {
                                                                                session_destroy();
                                                                                echo "erreur";
                                                                                die;
                                                                            }

                                                                            //base de donnee BASI
                                                                            $resultVerifierUserBASI = $authController->verifierUserBASI($bdBASI,$email,$matricule,$prenom,$nom);

                                                                            if (is_object($resultVerifierUserBASI)) {
                                                                                $tmpIdBASI = $result->id;
                                                                                $_SESSION['tmpIdBASI'] = $tmpIdBASI;
                                                                            } else {
                                                                                session_destroy();
                                                                                echo "erreur";
                                                                                die;
                                                                            }


                                                                            // Liste des applications
                                                                            $listeApplications = $authController->listeApplications();



                                                                            if (!empty($listeApplications) && is_array($listeApplications)) {
                                                                                $_SESSION['tmpListeApplication'] = $listeApplications;
                                                                            } else {
                                                                                session_destroy();
                                                                                echo "erreur";
                                                                                die;
                                                                            }


                                                                            // liste des taches
                                                                            $listeTachesParDefaut = $authController->listeTachesParDefaut();

                                                                            if (is_array($listeTachesParDefaut)) {
                                                                                $_SESSION['listeTachesParDefaut'] = $listeTachesParDefaut;
                                                                            } else {
                                                                                session_destroy();
                                                                                echo "erreur";
                                                                                die;
                                                                            }


                                                                            $listeTachesIncarnes = $authController->listeTachesIncarnesAdmin();
                                                                            if (is_array($listeTachesIncarnes)) {
                                                                                $_SESSION['listeTachesIncarnes'] = $listeTachesIncarnes;
                                                                            } else {
                                                                                session_destroy();
                                                                                echo "erreur";
                                                                                die;
                                                                            }

                                                                            $listeTachesStructures = $authController->listeTachesStructuresAdmin();
                                                                            if (is_array($listeTachesStructures)) {
                                                                                $_SESSION['listeTachesStructures'] = $listeTachesStructures;
                                                                            } else {
                                                                                session_destroy();
                                                                                echo "erreur";
                                                                                die;
                                                                            }



                                                                            $bdP->commit();
                                                                            echo "succès/personnel/admin-accueil";
                                                                            die;

                                                                        }else
                                                                        {



                                                                            //pour tous les utilisateurs admin = 1 et user simple = 2
                                                                            $_SESSION['connectUserGSJLF_ENT'] = 2;

                                                                            $infoPosteResponsable = $authController->infoPosteResponsable($identifiant);

                                                                            $idFonction = null;
                                                                            $statutPoste = null;
                                                                            $nbrAppliGestTache = 0;
                                                                            if (!empty($infoPosteResponsable) && is_array($infoPosteResponsable)) {

                                                                                $idFonction = $infoPosteResponsable->id;
                                                                                $retourInfoDirecteur = $authController->retourInfoDirecteur($idFonction);

                                                                                if($retourInfoDirecteur == "oui")
                                                                                {
                                                                                    $statutPoste = 1;
                                                                                    $nbrAppliGestTache = 1;

                                                                                }else
                                                                                {
                                                                                    $statutPoste = 0;

                                                                                }
                                                                            }
//                                        $listeApplicationUserSimple = $authController->listeApplicationUserSimple(
//                                            $matricule,
//                                            $idFonction
//                                        );

                                                                            $_SESSION['statutPoste'] = $statutPoste;

//
//                                        if (!empty($listeApplicationUserSimple) && is_array($listeApplicationUserSimple)) {
//
////                                            $_SESSION['tmpListeApplication'] = $listeApplicationUserSimple;
//
//                                            $tmpNbrAppli = $infoApplication->total_applications + $nbrAppliGestTache;
//                                            $_SESSION['tmpNbrAppli'] = $tmpNbrAppli;
//
//                                            $tmpNbrAppliEnAttente =  $infoApplication->en_attente;
//                                            $_SESSION['tmpNbrAppliEnAttente'] = $tmpNbrAppliEnAttente;
//
//                                            $tmpNbrAppliAutorisees = count($listeApplicationUserSimple) + $nbrAppliGestTache;
//                                            $_SESSION['tmpNbrAppliAutorisees'] = $tmpNbrAppliAutorisees;
//
//                                            $tmpNbrAppliRefusees = $tmpNbrAppli - $tmpNbrAppliEnAttente - $tmpNbrAppliAutorisees;
//                                            $_SESSION['tmpNbrAppliRefusees'] = max(
//                                                0,$tmpNbrAppliRefusees
//                                            );
//
//                                        } else {
//
//                                            session_destroy();
//                                            echo "erreur";
//                                            die;
//                                        }


                                                                            // Liste des applications avec règles de statut appliquées



                                                                            $tmpNbrAppliEnAttente = 0;
                                                                            $tmpNbrAppliAutorisees = 0;
                                                                            $tmpNbrAppliRefusees = 0;

                                                                            $listeApplicationsMerged = $authController->listeApplicationsMerged($matricule, $idFonction);

                                                                            if (!empty($listeApplicationsMerged) && is_array($listeApplicationsMerged)) {
                                                                                $_SESSION['tmpListeApplication'] = $listeApplicationsMerged;

                                                                                $tmpNbrAppli = count($listeApplicationsMerged) + $nbrAppliGestTache;
                                                                                $_SESSION['tmpNbrAppli'] = $tmpNbrAppli;

                                                                                foreach ($listeApplicationsMerged as $listeApplicationMerged) {

                                                                                    if($listeApplicationMerged->statutApplication == 0)
                                                                                    {
                                                                                        ++$tmpNbrAppliEnAttente;

                                                                                    }else if($listeApplicationMerged->statutApplication == 1)
                                                                                    {
                                                                                        ++$tmpNbrAppliAutorisees;

                                                                                    }else if($listeApplicationMerged->statutApplication == 2)
                                                                                    {
                                                                                        ++$tmpNbrAppliRefusees;
                                                                                    }

                                                                                }

                                                                                $_SESSION['tmpNbrAppliEnAttente'] = max(0,$tmpNbrAppliEnAttente);
                                                                                $_SESSION['tmpNbrAppliAutorisees'] = max(0,$tmpNbrAppliAutorisees);
                                                                                $_SESSION['tmpNbrAppliRefusees'] = max(
                                                                                    0,$tmpNbrAppliRefusees
                                                                                );


                                                                            } else {
                                                                                session_destroy();
                                                                                echo "erreur";
                                                                                die;
                                                                            }



                                                                            // creer utilisateurs

                                                                            $listeBaseDoneeUserSimple = $authController->listeBaseDoneeUserSimple(
                                                                                $matricule,
                                                                                $idFonction
                                                                            );




                                                                            $tmp_nombre_bd = 0;
                                                                            if (is_array($listeBaseDoneeUserSimple)) {

                                                                                if(!empty($listeBaseDoneeUserSimple))
                                                                                {
                                                                                    foreach ($listeBaseDoneeUserSimple as $base) {
                                                                                        if($base->code_base_donnees == "criat_uahb"){
                                                                                            //base de donnee criat
                                                                                            $resultVerifierUserCRIAT = $authController->verifierUserCRIAT($bd,$email,$matricule,$prenom,$nom);

                                                                                            if (is_object($resultVerifierUserCRIAT)) {
                                                                                                $tmpId = $resultVerifierUserCRIAT->id;
                                                                                                $_SESSION['tmpId'] = $tmpId;
                                                                                                ++$tmp_nombre_bd;
                                                                                            } else {
                                                                                                session_destroy();
                                                                                                echo "erreur";
                                                                                                die;
                                                                                            }

                                                                                        }

                                                                                        if($base->code_base_donnees == "basi") {

                                                                                            //base de donnee BASI
                                                                                            $resultVerifierUserBASI = $authController->verifierUserBASI($bdBASI,$email,$matricule,$prenom,$nom);

                                                                                            if (is_object($resultVerifierUserBASI)) {
                                                                                                $tmpIdBASI = $result->id;
                                                                                                $_SESSION['tmpIdBASI'] = $tmpIdBASI;
                                                                                                ++$tmp_nombre_bd;
                                                                                            } else {
                                                                                                session_destroy();
                                                                                                echo "erreur";
                                                                                                die;
                                                                                            }
                                                                                        }



                                                                                    }



                                                                                    // important quand tout sera ok
//                                                        if(count($listeBaseDoneeUserSimple) != $tmp_nombre_bd)
//                                                        {
//                                                            session_destroy();
//                                                            echo "erreur";
//                                                            die;
//                                                        }
                                                                                }


                                                                            } else {

                                                                                session_destroy();
                                                                                echo "erreur";
                                                                                die;
                                                                            }



                                                                            // liste des taches
                                                                            $listeTachesParDefaut = $authController->listeTachesParDefaut();
                                                                            if (is_array($listeTachesParDefaut)) {
                                                                                $_SESSION['listeTachesParDefaut'] = $listeTachesParDefaut;
                                                                            } else {
                                                                                session_destroy();
                                                                                echo "erreur";
                                                                                die;
                                                                            }


                                                                            $listeTachesIncarnes = $authController->listeTachesIncarnes($idFonction,$matricule);

                                                                            if (is_array($listeTachesIncarnes)) {
                                                                                $_SESSION['listeTachesIncarnes'] = $listeTachesIncarnes;
                                                                            } else {
                                                                                session_destroy();
                                                                                echo "erreur";
                                                                                die;
                                                                            }

                                                                            $listeTachesStructures = $authController->listeTachesStructures($idFonction);
                                                                            if (is_array($listeTachesStructures)) {
                                                                                $_SESSION['listeTachesStructures'] = $listeTachesStructures;
                                                                            } else {
                                                                                session_destroy();
                                                                                echo "erreur";
                                                                                die;
                                                                            }



                                                                            $infoDirection = $authController->infoDirection($bdBASI,$idFonction);
                                                                            if (!empty($infoDirection) && is_array($infoDirection)) {
                                                                                $_SESSION['tmpIdDirection'] = $infoDirection->id;
                                                                            } else {
//                                                                                session_destroy();
//                                                                                echo "erreur";
//                                                                                die;
                                                                            }



                                                                            $bdP->commit();

                                                                            echo "succès/personnel/user-accueil";
                                                                            die;
                                                                        }

                                                                    }else
                                                                    {
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

                                                        echo "finContrat";
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

                                        }


                                    }else
                                    {
                                        echo "erreur";
                                        die;

                                    }


                                }else
                                {
                                    echo "bloquer";
                                    die;
                                }


                            } else {
                                if ($bdP->inTransaction()) {
                                    $bdP->rollBack();
                                }
                                echo "compteInactive";
                                die;
                            }


                        }else
                        {
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
                    echo "pasCompte";
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


    default :
        echo "erreur";
        die;




}