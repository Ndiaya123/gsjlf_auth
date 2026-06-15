<?php
session_start();

include_once('../../bdP.php');


class menuController extends BDP
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


    /**
     * Génère le HTML du menu latéral à partir des 3 listes de tâches
     *
     * @param array|null $listeTachesStructures Tâches structurées (avec sous-menus)
     * @param array|null $listeTachesIncarnes Tâches incarnées
     * @param array|null $listeTachesParDefaut Tâches par défaut
     * @param string $page_par_defaut URL de la page par défaut
     * @param string $url_page URL de la page active
     * @return string    HTML du menu
     */
    function genererMenu(
        $listeTachesStructures,
        $listeTachesIncarnes,
        $listeTachesParDefaut,
        ?string $page_par_defaut,   // ✅ nullable
        string $url_page
    ): string
    {

        // Normaliser les listes (null => tableau vide)
        $structures = $listeTachesStructures ?? [];
        $incarnes = $listeTachesIncarnes ?? [];
        $parDefaut = $listeTachesParDefaut ?? [];

        // Si tout est vide → page par défaut uniquement
        $toutVide = empty($structures) && empty($incarnes) && empty($parDefaut);

        $html = '';

        // ─────────────────────────────────────────────
        // Lien Dashboard/Accueil par défaut tout en haut
        // ─────────────────────────────────────────────
        $isDefaultActive = ($url_page === $page_par_defaut) ? 'active' : '';
        $html .= <<<HTML
    <div class="menu-item">
        <div class="menu-content pb-2">
            <span class="menu-section text-muted text-uppercase fs-8 ls-1">Dashboard</span>
        </div>
    </div>
    <div class="menu-item">
        <a class="menu-link {$isDefaultActive}" href="{$page_par_defaut}">
            <span class="menu-icon">
                <span class="svg-icon svg-icon-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect x="2" y="2" width="9" height="9" rx="2" fill="black"/>
                        <rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="black"/>
                        <rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="black"/>
                        <rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="black"/>
                    </svg>
                </span>
            </span>
            <span class="menu-title">Dashboard</span>
        </a>
    </div>
    HTML;

        if ($toutVide) {
            return $html;
        }

        // ─────────────────────────────────────────────
        // Ordre d'affichage : Structures → Incarnés → Par défaut
        // ─────────────────────────────────────────────
        $sections = [
            ['label' => null, 'taches' => $structures],
            ['label' => null, 'taches' => $incarnes],
            ['label' => 'Autres', 'taches' => $parDefaut],
        ];

        foreach ($sections as $section) {
            if (empty($section['taches'])) {
                continue;
            }

            $html .= renderSection($section['taches'], $section['label'], $url_page);
        }

        return $html;
    }


    /**
     * Rend une section du menu (groupe de tâches potentiellement regroupées par application + sous-menu)
     */
    function renderSection(array $taches, ?string $labelSection, string $url_page): string
    {
        if (empty($taches)) return '';

        $html = '';

        // ── Regrouper par application (nomApplication / idAppli) ──────────────
        $parApplication = [];
        foreach ($taches as $t) {
            $key = $t->idAppli ?? '__sans_appli__';
            $parApplication[$key][] = $t;
        }

        foreach ($parApplication as $idAppli => $tachesAppli) {

            // Nom de l'application pour le menu-section
            $nomAppli = $tachesAppli[0]->nomApplication ?? null;

            // ── Entête de section (nomApplication ou label fourni) ────────────
            $titreSection = $nomAppli ?? $labelSection;
            if ($titreSection) {
                $html .= <<<HTML
            <div class="menu-item">
                <div class="menu-content pb-2">
                    <span class="menu-section text-muted text-uppercase fs-8 ls-1">{$titreSection}</span>
                </div>
            </div>
            HTML;
            }

            // ── Séparer : tâches Accueil/Dashboard vs reste ───────────────────
            $accueilItems = array_filter($tachesAppli, fn($t) => $t->statut == 1);
            $autresItems = array_filter($tachesAppli, fn($t) => $t->statut == 0);

            // ── Tâches Accueil/Dashboard EN HAUT (liens directs, pas d'accordion)
            foreach ($accueilItems as $t) {
                $isActive = isUrlActive($t->url, $url_page) ? 'active' : '';
                $iconHtml = buildIcon($t->icon ?? null);
                $html .= <<<HTML
            <div class="menu-item">
                <a class="menu-link {$isActive}" href="{$t->url}">
                    <span class="menu-icon">
                        <span class="svg-icon svg-icon-2">{$iconHtml}</span>
                    </span>
                    <span class="menu-title">{$t->nom}</span>
                </a>
            </div>
            HTML;
            }

            // ── Regrouper le reste par sousMenu ───────────────────────────────
            $parSousMenu = [];
            foreach ($autresItems as $t) {
                $key = $t->sousMenu ?? '__sans_sous_menu__';
                $parSousMenu[$key][] = $t;
            }

            foreach ($parSousMenu as $nomSousMenu => $tachesDuSousMenu) {

                if ($nomSousMenu === '__sans_sous_menu__') {
                    // ── Liens directs (pas de sous-menu) ──────────────────────
                    foreach ($tachesDuSousMenu as $t) {
                        $isActive = isUrlActive($t->url, $url_page) ? 'active' : '';
                        $iconHtml = buildIcon($t->icon ?? null);
                        $html .= <<<HTML
                    <div class="menu-item">
                        <a class="menu-link {$isActive}" href="{$t->url}">
                            <span class="menu-icon">
                                <span class="svg-icon svg-icon-2">{$iconHtml}</span>
                            </span>
                            <span class="menu-title">{$t->nom}</span>
                        </a>
                    </div>
                    HTML;
                    }
                } else {
                    // ── Accordion sous-menu ────────────────────────────────────
                    // Détecter si un enfant est actif → ouvrir l'accordion
                    $sousMenuActif = array_reduce(
                        $tachesDuSousMenu,
                        fn($carry, $t) => $carry || isUrlActive($t->url, $url_page),
                        false
                    );
                    $showClass = $sousMenuActif ? 'show' : '';
                    $iconSousMenu = buildIcon($tachesDuSousMenu[0]->icon ?? null);

                    $html .= <<<HTML
                <div data-kt-menu-trigger="click" class="menu-item menu-accordion {$showClass}">
                    <span class="menu-link">
                        <span class="menu-icon">
                            <span class="svg-icon svg-icon-2">{$iconSousMenu}</span>
                        </span>
                        <span class="menu-title">{$nomSousMenu}</span>
                        <span class="menu-arrow"></span>
                    </span>
                    <div class="menu-sub menu-sub-accordion menu-active-bg">
                HTML;

                    foreach ($tachesDuSousMenu as $t) {
                        $isActive = isUrlActive($t->url, $url_page) ? 'active' : '';
                        $html .= <<<HTML
                        <div class="menu-item">
                            <a class="menu-link {$isActive}" href="{$t->url}">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">{$t->nom}</span>
                            </a>
                        </div>
                    HTML;
                    }

                    $html .= <<<HTML
                    </div>
                </div>
                HTML;
                }
            }
        }

        return $html;
    }


    /**
     * Vérifie si une URL de tâche correspond à la page active
     */
    function isUrlActive(string $taskUrl, string $currentUrl): bool
    {
        // Normaliser : supprimer slash final
        $taskUrl = rtrim($taskUrl, '/');
        $currentUrl = rtrim($currentUrl, '/');

        return $taskUrl === $currentUrl;
    }


    /**
     * Construit le HTML d'une icône SVG stockée en base
     * (on suppose que $icon contient directement le SVG ou un nom de classe)
     */
    function buildIcon(?string $icon): string
    {
        if (empty($icon)) {
            // Icône générique par défaut
            return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
            <rect opacity="0.3" x="2" y="2" width="20" height="20" rx="4" fill="black"/>
        </svg>
        SVG;
        }

        // Si le champ icon contient directement du SVG
        if (str_starts_with(trim($icon), '<svg')) {
            return $icon;
        }

        // Sinon on suppose que c'est un nom de classe FontAwesome / autre
        return "<i class=\"{$icon}\"></i>";
    }
}


$bdP = new BDP();
$bdP = $bdP->connect();
$menuController = new menuController();
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
        $url_page = null;
        if(!empty($_POST["url_page"]))
        {
            $url_page = $_POST["url_page"];

        }else
        {
            echo "erreur";
            die;
        }

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
            !isset($_SESSION['connectUser']) ||
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


            $connectUser = $_SESSION['connectUser'] ?? null;

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
            $tmpEmail = $_SESSION['tmpEmail'] ?? null;
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



        $tmp_tache_url_page = "non";
        $nomApplication = null;
        $nomTache = null;
        $idTache = null;
        $idAppli = null;

        foreach ($listeTachesStructures as $tache) {
            if ($tache->url === $url_page) {
                $tmp_tache_url_page = "oui";
                $nomApplication = $tache->nomApplication;
                $nomTache = $tache->nom;
                $idAppli = $tache->idAppli;
            }
        }

        foreach ($listeTachesIncarnes as $tache) {
            if ($tache->url === $url_page) {
                $tmp_tache_url_page = "oui";
                $nomApplication = $tache->nomApplication;
                $nomTache = $tache->nom;
                $idAppli = $tache->idAppli;

            }
        }

        foreach ($listeTachesParDefaut as $tache) {
            if ($tache->url === $url_page) {
                $tmp_tache_url_page = "oui";
                $nomApplication = $tache->nomApplication;
                $nomTache = $tache->nom;
                $idAppli = $tache->idAppli;

            }
        }



        $page_par_defaut = null;

        foreach ($tmpListeApplication as $appli) {
            if ($appli->numero === $idAppli) {
                $page_par_defaut = $appli->page_defaut;
            }
        }



        if($page_par_defaut == null || $page_par_defaut == "")
        {

            $chemin = parse_url($url_page, PHP_URL_PATH);
            $chemin = preg_replace('#^/personnel/#', '', $chemin);
            foreach ($tmpListeApplication as $appli) {
                if ($appli->page_defaut === $chemin) {
                    $page_par_defaut = $BASE_URL.$appli->page_defaut;
                    $nomApplication = $appli->nomApplication;
                    $nomTache = "Dashboard";
                    $tmp_tache_url_page = "oui";

                }
            }

        }


        if($tmp_tache_url_page != "oui")
        {
            echo "sesionExpired";
            die;

        }





        $infoAppi = '
    <h1 class="d-flex align-items-center text-dark fw-bolder fs-3 my-1">'.$nomApplication.'
                            </h1>
                            <span class="h-20px border-gray-200 border-start mx-4"></span>
                            <ul class="breadcrumb breadcrumb-separatorless fw-bold fs-7 my-1" >
     <li class="breadcrumb-item text-muted">
    '.$nomTache.'
                                </li>

                            </ul>                  
    ';




        $menuHtml = $menuController->genererMenu(
            $listeTachesStructures,
            $listeTachesIncarnes,
            $listeTachesParDefaut,
            $page_par_defaut,
            $url_page
        );

        $listeInfo[] = array(
            'user_photo' => $tmpPhoto,
            'matricule' => $tmpMatricule,
            'user_pn' => ucwords(mb_strtolower($tmpPrenom))." ".$menuController->fctRetirerAccents(mb_strtoupper($tmpNom)),
            'user_email' => $tmpEmail,
            'infoAppli' => $infoAppi,
            'lien_logo1' => $lien_logo1,
            'lien_logo2' => $lien_logo2,
            'menus' => $menuHtml
        );

        echo json_encode($listeInfo);
        die;

        } catch (\Throwable $th) {

            $listeInfo[] = array(
                'user_photo' => NULL,
                'matricule' => NULL,
                'user_pn' => NULL,
                'user_email' => NULL,
                'infoAppli' => NULL,
                'lien_logo1' => NULL,
                'lien_logo2' => NULL,
                'menus' => NULL
            );

            echo json_encode($listeInfo);
            die;
        }

        break;

    default :
        echo "erreur";
        die;





}
