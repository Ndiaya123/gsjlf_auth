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
                    <div>
                        <div class="ps_sb-name">Groupe Scolaire Jean de la Fontaine</div>
                        <div class="ps_sb-sub">Environnement Numérique de Travail</div>
                    </div>
                </a></div>
            <div class="ps_sb-mid">
                <div class="ps_sb-kicker"><span class="material-symbols-outlined" style="font-size:13px">lock_reset</span>Récupération d'accès</div>
                <h2 class="ps_sb-headline">Réinitialisez<br>votre <span>mot de passe.</span></h2>
                <p class="ps_sb-desc">Recevez un lien sécurisé pour créer un nouveau mot de passe et retrouver l'accès à votre espace ENT.</p>
                <div class="ps_sb-stats">
                    <div class="ps_sb-stat"><div class="ps_sb-stat-num">24 h</div><div class="ps_sb-stat-lbl">Validité</div></div>
                    <div class="ps_sb-stat"><div class="ps_sb-stat-num">1x</div><div class="ps_sb-stat-lbl">Usage</div></div>
                    <div class="ps_sb-stat"><div class="ps_sb-stat-num">100%</div><div class="ps_sb-stat-lbl">Sécurisé</div></div>
                </div>
                <div class="ps_slide-ent">
                    <img id="ent-logo" src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png" alt="">
                    <div class="ps_slide-ent-info"><span id="ent-name">UAHB</span><small id="ent-desc">Université Amadou Hampâté Bâ</small></div>
                </div>
                <div class="ps_slide-dots" id="slide-dots"></div>
            </div>
            <div class="ps_sb-bottom">
                <div class="ps_sb-secure"><span class="material-symbols-outlined" style="font-size:13px">verified_user</span>Réinitialisation sécurisée</div>
                <span>© <span id="annee_en_cours1"></span> GSJLF</span>
            </div>
        </div>
    </aside>

    <main class="ps_main-wrap">
        <div class="ps_auth-card">
            <a href="/personnel/signin" class="ps_back-link"><span class="material-symbols-outlined" style="font-size:15px">arrow_back</span>Retour à la connexion</a>

            <form novalidate="novalidate" id="formReset" autocomplete="off">

                <input type="hidden" name="option" value="4">

                <!-- PHASE 1 : demande -->
                <div id="phase1" class="ps_phase">
                    <div class="ps_form-icon"><span class="material-symbols-outlined">lock_reset</span></div>
                    <h1 class="ps_form-title">Mot de passe oublié</h1>
                    <p class="ps_form-subtitle">Renseignez votre matricule et email pour recevoir un lien de réinitialisation sécurisé.</p>
                    <div class="ps_info-box">
                        <span class="material-symbols-outlined ps_ico2">link</span>
                        <p>Le lien est personnel, usage unique et expire automatiquement après 24 heures.</p>
                    </div>
                    <div class="ps_field">
                        <label>Matricule</label>
                        <div class="ps_input-wrap">
                            <span class="material-symbols-outlined ps_ico">pin</span>
                            <input type="text" id="mat1" placeholder="Votre numéro matricule" name="matricule" required>
                        </div>
                    </div>
                    <div class="ps_field">
                        <label>Email institutionnel</label>
                        <div class="ps_input-wrap">
                            <span class="material-symbols-outlined ps_ico">alternate_email</span>
                            <input type="email" id="email1" name="email" placeholder="prenom.nom@uahb.sn" oninput="this.value = this.value.toLowerCase()" required>
                        </div>
                    </div>
                    <div class="ps_link-card">
                        <div class="ps_link-card-icon"><span class="material-symbols-outlined">mark_email_read</span></div>
                        <div><strong>Lien sécurisé par email</strong><p>Le lien permettra de définir un nouveau mot de passe sur une page dédiée.</p></div>
                    </div>
                    <div class="ps_rules">
                        <span><span class="material-symbols-outlined">check_circle</span>Lien personnel et non partageable.</span>
                        <span><span class="material-symbols-outlined">check_circle</span>Expire automatiquement après 24 heures.</span>
                        <span><span class="material-symbols-outlined">check_circle</span>Vérifiez vos spams si vous ne recevez rien.</span>
                    </div>
                    <button class="ps_submit-btn" type="button" id="formReset_submit">
                        <span class="material-symbols-outlined" style="font-size:18px">send</span>Envoyer le lien
                    </button>
                    <div class="ps_alt-line">retour</div>
                    <p class="ps_switch-note">Vous vous souvenez du mot de passe ? <a href="/personnel/signin" class="ps_link-btn">Se connecter</a></p>
                </div>

            </form>

            <!-- PHASE 2 : email envoyé -->
            <div id="phase2" style="display:none">
                <div class="ps_form-icon" style="background:linear-gradient(135deg,#0f7c40,#1aad60)">
                    <span class="material-symbols-outlined">mark_email_unread</span>
                </div>
                <h1 class="ps_form-title">Email envoyé !</h1>
                <p class="ps_form-subtitle">Un lien de réinitialisation a été envoyé à <strong id="email-display"></strong>.</p>
                <div class="ps_info-box-success">
                    <span class="material-symbols-outlined" style="font-size:18px;color:#2e7d32;margin-top:1px;flex-shrink:0">check_circle</span>
                    <p>Email envoyé avec succès. Lien valable 24 heures. Pensez à vérifier votre dossier spam.</p>
                </div>
                <button class="ps_btn-ghost" type="button" onclick="refreshPage()">
                    <span class="material-symbols-outlined" style="font-size:18px">refresh</span>Renvoyer le lien
                </button>
            </div>


            <div id="success-state" class="ps_success-banner" style="display:none">
                <div class="ps_success-icon"><span class="material-symbols-outlined" style="font-size:34px">lock_open</span></div>
                <h3>Mot de passe mis à jour !</h3>
                <p>Votre mot de passe a été réinitialisé avec succès. Connectez-vous avec vos nouveaux identifiants.</p>
                <a href="/personnel/signin" class="ps_submit-btn" style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px">Se connecter</a>
            </div>
        </div>
    </main>
</div>


<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>

<script src="/personnel/scripts.bundle.4.js"></script>
</body>
</html>