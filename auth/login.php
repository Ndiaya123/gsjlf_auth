<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>GSJLF — Connexion ENT</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
          rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
          rel="stylesheet"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" type="text/css" href="/personnel/ressources/dist_assets/css/login.css">
</head>
<body>
<div class="ps_page-shell">
    <aside class="ps_sidebar">
        <div class="ps_slide ps_active"
             style="background-image:url('/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg')"></div>
        <div class="ps_slide"
             style="background-image:url('/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg')"></div>
        <div class="ps_slide"
             style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>
        <div class="ps_sidebar-overlay"></div>
        <div class="ps_sidebar-orb"></div>
        <div class="ps_sb-inner">
            <div><a href="/personnel/accueil" class="ps_sb-logo">
                    <img src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" alt="GSJLF"
                         class="ps_sb-logo-img">
                    <div>
                        <div class="ps_sb-name">Groupe Scolaire Jean de la Fontaine</div>
                        <div class="ps_sb-sub">Environnement Numérique de Travail</div>
                    </div>
                </a></div>
            <div class="ps_sb-mid">
                <div class="ps_sb-kicker"><span class="material-symbols-outlined" style="font-size:13px">login</span>Accès sécurisé ENT</div>
                <h2 class="ps_sb-headline">Bienvenue sur<br>votre <span>espace.</span></h2>
                <p class="ps_sb-desc">Connectez-vous à votre environnement numérique pour accéder à vos cours, notes et outils pédagogiques.</p>
                <div class="ps_sb-stats">
                    <div class="ps_sb-stat">
                        <div class="ps_sb-stat-num">3</div>
                        <div class="ps_sb-stat-lbl">Entités</div>
                    </div>
                    <div class="ps_sb-stat">
                        <div class="ps_sb-stat-num">500+</div>
                        <div class="ps_sb-stat-lbl">Membres</div>
                    </div>
                    <div class="ps_sb-stat">
                        <div class="ps_sb-stat-num">100%</div>
                        <div class="ps_sb-stat-lbl">Sécurisé</div>
                    </div>
                </div>
                <div class="ps_slide-ent">
                    <img id="ent-logo" src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png" alt="">
                    <div class="ps_slide-ent-info"><span id="ent-name">UAHB</span><small id="ent-desc">Université Amadou Hampâté Bâ</small></div>
                </div>
                <div class="ps_slide-dots" id="slide-dots"></div>
            </div>
            <div class="ps_sb-bottom">
                <div class="ps_sb-secure"><span class="material-symbols-outlined" style="font-size:13px">verified_user</span>Connexion chiffrée SSL</div>
                <span>© <span id="annee_en_cours1"></span> GSJLF</span>
            </div>
        </div>
    </aside>

    <main class="ps_main-wrap">
        <div class="ps_auth-card">
            <a href="/personnel/accueil" class="ps_back-link"><span class="material-symbols-outlined" style="font-size:15px">arrow_back</span>Retour à l'accueil</a>

            <div id="main-form">
                <div class="ps_form-icon"><span class="material-symbols-outlined">login</span></div>
                <h1 class="ps_form-title">Connexion</h1>
                <p class="ps_form-subtitle">Accédez à votre espace ENT avec vos identifiants institutionnels.</p>

                <!-- Sélecteur entité -->
                <div class="ps_entity-row">
                    <div class="ps_entity-opt ps_uahb">
                        <input type="radio" id="e-uahb" name="entity" value="uahb">
                        <label for="e-uahb"><img src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png" alt="UAHB">UAHB<small>Université</small></label>
                    </div>
                    <div class="ps_entity-opt ps_cmjlf">
                        <input type="radio" id="e-cmjlf" name="entity" value="cmjlf">
                        <label for="e-cmjlf"><img src="/personnel/ressources/dist_assets/media/logos/logo_cmjlf.png" alt="CMJLF">CMJLF<small>Collège</small></label>
                    </div>
                    <div class="ps_entity-opt ps_ctd">
                        <input type="radio" id="e-ctd" name="entity" value="ctd">
                        <label for="e-ctd"><img src="/personnel/ressources/dist_assets/media/logos/logo_ctd.png" alt="CTD">CTD<small>Technique</small></label>
                    </div>
                </div>

                <form novalidate="novalidate" id="formSignIn" autocomplete="off">
                    <input type="hidden" name="option" value="2">

                    <div class="ps_field">
                        <label>Email institutionnel</label>
                        <div class="ps_input-wrap">
                            <span class="material-symbols-outlined ps_ico">alternate_email</span>
                            <input type="email" placeholder="prenom.nom@uahb.sn" name="email" id="email" required>
                        </div>
                    </div>
                    <div class="ps_field">
                        <label>Mot de passe</label>
                        <div class="ps_input-wrap">
                            <span class="material-symbols-outlined ps_ico">lock</span>
                            <input type="password" id="pw-field" name="password" placeholder="Votre mot de passe" required>
                            <button type="button" class="ps_eye-btn" onclick="togglePw()">
                                <span class="material-symbols-outlined" style="font-size:18px" id="eye-icon">visibility</span>
                            </button>
                        </div>
                    </div>

                    <div class="ps_form-row-meta">
                        <label class="ps_remember"></label>
                        <a href="/personnel/reset-password" class="ps_link-btn">Mot de passe oublié ?</a>
                    </div>

                    <button class="ps_submit-btn" type="button" id="formSignIn_submit">
                        <span class="material-symbols-outlined" style="font-size:18px">login</span>Se connecter
                    </button>

                    <div class="ps_alt-line">ou</div>
                    <p class="ps_switch-note">Pas encore de compte ? <a href="/personnel/signup" class="ps_link-btn">Créer un compte</a></p>

                </form>
            </div>

            <div id="success-state" class="ps_success-banner" style="display:none">
                <div class="ps_success-icon"><span class="material-symbols-outlined" style="font-size:34px">check</span></div>
                <h3>Connexion réussie !</h3>
                <p>Vous êtes connecté à votre espace ENT. Redirection en cours…</p>
            </div>

        </div>
    </main>
</div>

<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/personnel/scripts.bundle.2.js"></script>
</body>
</html>