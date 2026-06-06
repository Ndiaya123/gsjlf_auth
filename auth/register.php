<?php

include_once('../sessions/commun.php');


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
    <link rel="stylesheet" type="text/css" href="/personnel/ressources/dist_assets/css/register.css">
</head>
<body>
<div class="ps_page-shell">

    <aside class="ps_sidebar">
        <div class="ps_slide ps_active" id="slide-0" style="background-image:url('/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg')"></div>
        <div class="ps_slide"           id="slide-1" style="background-image:url('/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg')"></div>
        <div class="ps_slide"           id="slide-2" style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>

        <div class="ps_sidebar-overlay"></div>
        <div class="ps_sidebar-orb"></div>

        <div class="ps_sb-inner">
            <div class="ps_sb-top">
                <a href="/personnel/accueil" class="ps_sb-logo">
                    <img src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" alt="GSJLF" class="ps_sb-logo-img">
                    <div>
                        <div class="ps_sb-name">Groupe Scolaire Jean de la Fontaine</div>
                        <div class="ps_sb-sub">Environnement Numérique de Travail</div>
                    </div>
                </a>
            </div>

            <div class="ps_sb-mid">
                <div class="ps_sb-kicker">
                    <span class="material-symbols-outlined" style="font-size:13px">person_add</span>
                    Rejoindre l'ENT GSJLF
                </div>

                <h2 class="ps_sb-headline"> Rejoignez la <br>
                    communauté <span>GSJLF.</span>
                </h2>

                <p class="ps_sb-desc">
                    Créez votre compte en quelques étapes et accédez à tous vos services numériques.
                </p>

                <div class="ps_sb-stats">
                    <div class="ps_sb-stat">
                        <div class="ps_sb-stat-num">3</div>
                        <div class="ps_sb-stat-lbl">Entités</div>
                    </div>
                    <div class="ps_sb-stat">
                        <div class="ps_sb-stat-num">20+</div>
                        <div class="ps_sb-stat-lbl">Membres</div>
                    </div>
                    <div class="ps_sb-stat">
                        <div class="ps_sb-stat-num">100%</div>
                        <div class="ps_sb-stat-lbl">Sécurisé</div>
                    </div>
                </div>

                <div class="ps_slide-ent">
                    <img id="ent-logo" src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png" alt="">
                    <div class="ps_slide-ent-info">
                        <span id="ent-name">UAHB</span>
                        <small id="ent-desc">Université Amadou Hampâté Bâ</small>
                    </div>
                </div>


                <div class="ps_slide-dots" id="slide-dots"></div>
            </div>

            <div class="ps_sb-bottom">
                <div class="ps_sb-secure">
                    <span class="material-symbols-outlined" style="font-size:13px">verified_user</span>
                    Données protégées
                </div>
                <span>© <span id="annee_en_cours1"></span> GSJLF</span>
            </div>
        </div>
    </aside>

    <main class="ps_main-wrap">
        <div class="ps_auth-card">

            <a href="/personnel/accueil" class="ps_back-link">
                <span class="material-symbols-outlined" style="font-size:15px">arrow_back</span>
                Retour à l'accueil
            </a>

            <form novalidate="novalidate" id="formSignUp" autocomplete="off">
                <input type="hidden" name="option" value="1" />

                <div id="signup-form">
                    <div class="ps_form-icon">
                        <span class="material-symbols-outlined">person_add</span>
                    </div>
                    <h1 class="ps_form-title">Créer un compte</h1>
                    <p class="ps_form-subtitle">Renseignez vos identifiants institutionnels pour rejoindre l'ENT GSJLF.</p>

                    <div class="ps_field">
                        <label>Matricule</label>
                        <div class="ps_input-wrap">
                            <span class="material-symbols-outlined ps_ico">pin</span>
                            <input type="text" id="f-mat" placeholder="Votre numéro matricule" name="matricule" required autocomplete="off">
                        </div>
                    </div>

                    <div class="ps_field">
                        <label>Email institutionnel</label>
                        <div class="ps_input-wrap">
                            <span class="material-symbols-outlined ps_ico">alternate_email</span>
                            <input type="email" id="f-email" placeholder="prenom.nom@uahb.sn" name="email" oninput="this.value = this.value.toLowerCase()" required autocomplete="email">
                        </div>
                    </div>

                    <div class="ps_field">
                        <label>Mot de passe</label>
                        <div class="ps_input-wrap">
                            <span class="material-symbols-outlined ps_ico">lock</span>
                            <input type="password" id="f-pw"
                                   placeholder="Créer un mot de passe"
                                   oninput="checkStrength(this.value)" required autocomplete="new-password" name="password">
                            <button type="button" class="ps_eye-btn" onclick="togglePw('f-pw','eye1')">
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
                            <input type="password" id="f-pw2"
                                   placeholder="Répéter le mot de passe" name="confirm-password"
                                   oninput="checkMatch()" required autocomplete="new-password">
                            <button type="button" class="ps_eye-btn" onclick="togglePw('f-pw2','eye2')">
                                <span class="material-symbols-outlined" style="font-size:18px" id="eye2">visibility</span>
                            </button>
                        </div>
                    </div>

                    <p class="ps_match-error" id="match-err">
                        <span class="material-symbols-outlined" style="font-size:13px">error</span>
                        Les mots de passe ne correspondent pas.
                    </p>

                    <div class="ps_rules">
                        <span><span class="material-symbols-outlined">check_circle</span> Minimum 8 caractères</span>
                        <span><span class="material-symbols-outlined">check_circle</span> Au moins une majuscule et un chiffre</span>
                        <span><span class="material-symbols-outlined">check_circle</span> Caractère spécial recommandé</span>
                    </div>

                    <button class="ps_submit-btn" type="button" id="formSignUp_submit">
                        <span class="material-symbols-outlined" style="font-size:18px">person_add</span>
                        Créer mon compte
                    </button>

                    <div class="ps_alt-line">déjà inscrit</div>
                    <p class="ps_switch-note">Vous avez déjà un accès ? <a href="/personnel/signin" class="ps_link-btn">Se connecter</a></p>
                </div>

            </form>

            <div id="success-state" class="ps_success-banner" style="display:none">
                <div class="ps_success-icon">
                    <span class="material-symbols-outlined" style="font-size:34px">mark_email_read</span>
                </div>
                <h3>Compte créé !</h3>
                <p>Un e-mail d’activation vous a été envoyé sur votre compte mail institutionnel. Si vous ne le trouvez pas dans votre boîte de réception, veuillez vérifier votre dossier des courriers indésirables (spams), puis cliquer sur le lien afin d’activer votre compte.</p>
            </div>

        </div>
    </main>
</div>

<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/personnel/scripts.bundle.1.js"></script>
</body>
</html>