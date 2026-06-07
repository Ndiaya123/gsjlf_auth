<?php

include_once('../sessions/commun.php');

include_once('../bdP.php');

?>


<?php
try {

    $bdP  = new BDP();
    $bdP  = $bdP ->connect();

    date_default_timezone_set('Africa/Dakar');



    $bdP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $bdP->beginTransaction();

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

    function valid_donnees($donnees)
    {
        $donnees = trim($donnees);
        $donnees = stripslashes($donnees);
        $donnees = htmlspecialchars($donnees);
        return $donnees;
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
            return false;
        }
    }




    if(!empty($_GET['mat']) && !empty($_GET['code']))
    {


        $now = new DateTime();
        $date_jour = $now->format('Y-m-d H:i:s');

        $matricule = tokendecrypt($_GET['mat']);
        $codeCreate_encrypt = $_GET['code'];


        $data = [
                'matricule' => $matricule
        ];

        $sql = "SELECT * FROM utilisateurs WHERE matricule=:matricule";
        $stmt = $bdP ->prepare($sql);
        $stmt->execute($data);
        $result = $stmt->fetch(PDO::FETCH_OBJ);




        if($result)
        {


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
                if($result_reset_code->codeCreate == $codeCreate_encrypt)
                {

                    if($result->statutUtilisateur == 0)
                    {

                        if(comparerDate($result_reset_code->dateEnregistrement))
                        {
                            if($result->statutCreerPar == 0 && $result->creerPar == "Admin")
                            {

                                $statut = 4;
                                $codeCreate_encrypt = $result_reset_code->codeCreate;
                            }else
                            {

                                $statut = 3;
                                $codeCreate_encrypt = null;
                            }
                        }else
                        {

                            $statut = 2;
                            $codeCreate_encrypt = null;
                        }
                    }else
                    {
                        $statut = 1;
                        $codeCreate_encrypt = null;
                    }
                }else
                {

                    $matricule = null;
                    $statut = 0;
                    $codeCreate_encrypt = null;
                }
            }else
            {

                $matricule = null;
                $statut = 0;
                $codeCreate_encrypt = null;
            }

        }else
        {

            $matricule = null;
            $statut = 0;
            $codeCreate_encrypt = null;
        }

    }else
    {

        $matricule = null;
        $statut = 0;
        $codeCreate_encrypt = null;
    }




}catch (Exception $e) {
    header("Location: /personnel/erreur");
    exit;
}



?>



<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>ENT — GSJLF</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" type="text/css" href="/personnel/ressources/dist_assets/css/reset.css">
</head>
<body>
<div class="ps_page-shell">


    <aside class="ps_sidebar">
        <div class="ps_slide ps_active" style="background-image:url('/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg')"></div>
        <div class="ps_slide"           style="background-image:url('/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg')"></div>
        <div class="ps_slide"           style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>
        <div class="ps_sidebar-overlay"></div>
        <div class="ps_sidebar-orb"></div>
        <div class="ps_sb-inner">
            <div><a href="/personnel/accueil" class="ps_sb-logo">
                    <img src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" alt="GSJLF" class="ps_sb-logo-img">
                    <div><div class="ps_sb-name">Groupe Scolaire Jean de la Fontaine</div><div class="ps_sb-sub">Environnement Numérique de Travail</div></div>
                </a></div>
            <div class="ps_sb-mid">
                <div class="ps_sb-kicker"><span class="material-symbols-outlined" style="font-size:13px">lock_reset</span>Sécurisation du compte</div>
                <h2 class="ps_sb-headline">Créez votre<br><span>mot de passe.</span></h2>
                <p class="ps_sb-desc">Votre compte a été créé par l'administration. Définissez maintenant votre mot de passe personnel pour sécuriser votre espace ENT.</p>
                <div class="ps_sb-stats">
                    <div class="ps_sb-stat"><div class="ps_sb-stat-num">24h</div><div class="ps_sb-stat-lbl">Validité</div></div>
                    <div class="ps_sb-stat"><div class="ps_sb-stat-num">1x</div><div class="ps_sb-stat-lbl">Usage</div></div>
                    <div class="ps_sb-stat"><div class="ps_sb-stat-num">SSL</div><div class="ps_sb-stat-lbl">Chiffré</div></div>
                </div>
                <div class="ps_slide-ent">
                    <img id="ent-logo" src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png" alt="">
                    <div class="ps_slide-ent-info"><span id="ent-name">UAHB</span><small id="ent-desc">Université Amadou Hampâté Bâ</small></div>
                </div>
                <div class="ps_slide-dots" id="slide-dots"></div>
            </div>
            <div class="ps_sb-bottom">
                <div class="ps_sb-secure"><span class="material-symbols-outlined" style="font-size:13px">verified_user</span>Réinitialisation sécurisée</div>
                <span>© 2026 GSJLF</span>
            </div>
        </div>
    </aside>

    <main class="ps_main-wrap">
        <div class="ps_auth-card">





            <?php

            if($statut == 0)
            {
                ?>

                <a href="/personnel/signin" class="ps_back-link"><span class="material-symbols-outlined" style="font-size:15px">arrow_back</span>Retour à la connexion</a>

                <div id="success-state" class="ps_success-banner">
                    <div class="ps_error-icon">
    <span class="material-symbols-outlined" style="font-size:34px">
        error
    </span>
                    </div>                    <h3 style="color: red;">Erreur !</h3>
                    <p> Une erreur s’est produite. Veuillez réessayer ou contacter le service informatique à l’adresse <strong><em style="color: red;">criat@uahb.sn</em></strong>.</p>
                    <!--                    <a href="/personnel/signin" class="submit-btn" style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px">Se connecter maintenant</a>-->
                </div>


            <?php }else if($statut == 1)
            {
                ?>


                <div id="success-state" class="ps_success-banner">
                    <div class="ps_error-icon">
   <span class="material-symbols-outlined" style="font-size:34px">
  lock
</span>
                    </div>                    <h3 style="color: red;">Compte bloqué !</h3>
                    <p> Votre compte est bloqué. Veuillez vous rapprocher du service informatique pour plus d’informations.</p>
                </div>

            <?php }else if($statut == 2)
            {?>


                <div id="success-state" class="ps_success-banner">
                    <div class="ps_warning-icon">
    <span class="material-symbols-outlined" style="font-size:34px">
        warning
    </span>
                    </div>                    <h3 style="color: #e0a800;">Le lien a expiré !</h3>
                    <p>                             Le lien a expiré. Demandez un nouveau lien et réessayez.
                    </p>
                    <button class="ps_warning-btn" style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px"  onclick="resetPassword()"><span class="material-symbols-outlined" style="font-size:13px;vertical-align:middle">refresh</span> Renvoyer le lien</button>
                </div>


            <?php }else if($statut == 3)
            {?>


                <div id="warning-state" class="ps_success-banner">
                    <div class="ps_warning-icon">
    <span class="material-symbols-outlined" style="font-size:34px">
        warning
    </span>
                    </div>                    <h3 style="color: #e0a800;">Mot de passe déjà modifié !</h3>
                    <p>
                        Votre mot de passe a déjà été modifié lors de votre première connexion.

                        Veuillez vous connecter directement à votre espace personnel avec votre mot de passe actuel.

                        Si vous l'avez oublié, utilisez l'option « Mot de passe oublié ? » pour le réinitialiser.

                    </p>
                    <a class="ps_warning-btn"  href="/personnel/" style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px"  ><span class="material-symbols-outlined" style="font-size:18px">login</span> Se connecter</a>

                </div>



            <?php }else if($statut == 4) { ?>

                <!-- ALERTE OBLIGATOIRE -->
                <div class="ps_alert-banner">
                    <div class="ps_alert-icon">
                        <span class="material-symbols-outlined" style="font-size:18px">warning</span>
                    </div>
                    <div class="ps_alert-txt">
                        <h4>Action requise avant de continuer</h4>
                        <p>Votre compte a été créé par l'administration avec un mot de passe provisoire. Vous devez définir un mot de passe personnel pour accéder à votre espace ENT.</p>
                    </div>
                </div>

                <form novalidate="novalidate" id="formReset" autocomplete="off">
                    <input type="hidden" name="option" value="6" />
                    <input type="hidden" name="code" id="code" value="<?=  $codeCreate_encrypt ?>"/>
                    <input type="hidden" name="matricule" id="matricule" value="<?=  $matricule ?>"/>


                    <div id="phase3" >
                        <div class="ps_form-icon">
                            <span class="material-symbols-outlined" style="font-size:28px">lock_reset</span>
                        </div>
                        <h1 class="ps_form-title">Nouveau mot de passe</h1>
                        <p class="ps_form-subtitle">Choisissez un nouveau mot de passe sécurisé pour votre compte ENT.</p>
                        <div class="ps_field">
                            <label>Nouveau mot de passe</label>
                            <div class="ps_input-wrap">
                                <span class="material-symbols-outlined ps_ico">lock</span>
                                <input type="password" id="pw1" placeholder="Créer un mot de passe" oninput="checkStrength(this.value)" name="password" required>
                                <button type="button" class="ps_eye-btn" onclick="togglePw('pw1','eye1')">
                                    <span class="material-symbols-outlined" style="font-size:18px" id="eye1">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div class="ps_strength-bar">
                            <div class="ps_strength-track"><div class="ps_strength-fill" id="sfill"></div></div>
                            <div class="ps_strength-label" id="slabel">—</div>
                        </div>
                        <div class="ps_field">
                            <label>Confirmation du mot de passe</label>
                            <div class="ps_input-wrap">
                                <span class="material-symbols-outlined ps_ico">lock_clock</span>
                                <input type="password" id="pw2" placeholder="Répéter le mot de passe" oninput="checkMatch()" name="confirm-password" required>
                                <button type="button" class="ps_eye-btn" onclick="togglePw('pw2','eye2')">
                                    <span class="material-symbols-outlined" style="font-size:18px" id="eye2">visibility</span>
                                </button>
                            </div>
                        </div>
                        <p class="ps_match-error" id="match-err">
                            <span class="material-symbols-outlined" style="font-size:13px">error</span>Les mots de passe ne correspondent pas.
                        </p>
                        <div class="ps_rules">
                            <span><span class="material-symbols-outlined">check_circle</span>Minimum 8 caractères</span>
                            <span><span class="material-symbols-outlined">check_circle</span>Au moins une majuscule et un chiffre</span>
                            <span><span class="material-symbols-outlined">check_circle</span>Différent de votre ancien mot de passe</span>
                        </div>
                        <button class="ps_submit-btn" type="button" id="formReset_submit">
                            <span class="material-symbols-outlined" style="font-size:18px">check_circle</span>Enregistrer et accéder à mon espace
                        </button>
                    </div>


                </form>

                <div id="success-state-reset" class="ps_success-banner" style="display:none">
                    <div class="ps_success-icon"><span class="material-symbols-outlined" style="font-size:34px">lock_open</span></div>
                    <h3>Mot de passe défini !</h3>
                    <p>Votre mot de passe a été enregistré avec succès. Vous allez être redirigé vers votre espace ENT.</p>
                </div>

            <?php }else{ ?>

                <div id="success-state" class="ps_success-banner">
                    <div class="ps_error-icon">
                                    <span class="material-symbols-outlined" style="font-size:34px">
                                        error
                                    </span>
                    </div>                    <h3 style="color: red;">Erreur !</h3>
                    <p> Une erreur s’est produite. Veuillez réessayer ou contacter le service informatique à l’adresse <strong><em style="color: red;">criat@uahb.sn</em></strong>.</p>
                </div>
            <?php }
            ?>


        </div>
    </main>
</div>

<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/personnel/scripts.bundle.14.js"></script>
<script>
</script>
</body>
</html>