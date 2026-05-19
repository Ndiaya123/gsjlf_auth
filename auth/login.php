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
    <!--    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>-->
    <link rel="stylesheet" type="text/css" href="/personnel/ressources/dist_assets/css/login.css">
</head>
<body>
<div class="page-shell">
    <aside class="sidebar">
        <div class="slide active"
             style="background-image:url('/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg')"></div>
        <div class="slide"
             style="background-image:url('/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg')"></div>
        <div class="slide"
             style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>
        <div class="sidebar-overlay"></div>
        <div class="sidebar-orb"></div>
        <div class="sb-inner">
            <div><a href="/personnel/accueil" class="sb-logo">
                    <img src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" alt="GSJLF"
                         class="sb-logo-img">
                    <div>
                        <div class="sb-name">Groupe Scolaire Jean de la Fontaine</div>
                        <div class="sb-sub">Environnement Numérique de Travail</div>
                    </div>
                </a></div>
            <div class="sb-mid">
                <div class="sb-kicker"><span class="material-symbols-outlined" style="font-size:13px">login</span>Accès
                    sécurisé ENT
                </div>
                <h2 class="sb-headline">Bienvenue sur<br>votre <span>espace.</span></h2>
                <p class="sb-desc">Connectez-vous à votre environnement numérique pour accéder à vos cours, notes et
                    outils pédagogiques.</p>
                <div class="sb-stats">
                    <div class="sb-stat">
                        <div class="sb-stat-num">3</div>
                        <div class="sb-stat-lbl">Entités</div>
                    </div>
                    <div class="sb-stat">
                        <div class="sb-stat-num">500+</div>
                        <div class="sb-stat-lbl">Membres</div>
                    </div>
                    <div class="sb-stat">
                        <div class="sb-stat-num">100%</div>
                        <div class="sb-stat-lbl">Sécurisé</div>
                    </div>
                </div>
                <div class="slide-ent">
                    <img id="ent-logo" src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png" alt="">
                    <div class="slide-ent-info"><span id="ent-name">UAHB</span><small id="ent-desc">Université Amadou
                            Hampâté Bâ</small></div>
                </div>
                <div class="slide-dots" id="slide-dots"></div>
            </div>
            <div class="sb-bottom">
                <div class="sb-secure"><span class="material-symbols-outlined"
                                             style="font-size:13px">verified_user</span>Connexion chiffrée SSL
                </div>
                <span>© <span id="annee_en_cours1"></span> GSJLF</span>
            </div>
        </div>
    </aside>

    <main class="main-wrap">
        <div class="auth-card">
            <a href="/personnel/accueil" class="back-link"><span class="material-symbols-outlined"
                                                                 style="font-size:15px">arrow_back</span>Retour à
                l'accueil</a>

            <div id="main-form">
                <div class="form-icon"><span class="material-symbols-outlined">login</span></div>
                <h1 class="form-title">Connexion</h1>
                <p class="form-subtitle">Accédez à votre espace ENT avec vos identifiants institutionnels.</p>

                <!-- Sélecteur entité -->
                <div class="entity-row">
                    <div class="entity-opt uahb">
                        <input type="radio" id="e-uahb" name="entity" value="uahb">
                        <label for="e-uahb"><img src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png"
                                                 alt="UAHB">UAHB<small>Université</small></label>
                    </div>
                    <div class="entity-opt cmjlf">
                        <input type="radio" id="e-cmjlf" name="entity" value="cmjlf">
                        <label for="e-cmjlf"><img src="/personnel/ressources/dist_assets/media/logos/logo_cmjlf.png"
                                                  alt="CMJLF">CMJLF<small>Collège</small></label>
                    </div>
                    <div class="entity-opt ctd">
                        <input type="radio" id="e-ctd" name="entity" value="ctd">
                        <label for="e-ctd"><img src="/personnel/ressources/dist_assets/media/logos/logo_ctd.png"
                                                alt="CTD">CTD<small>Technique</small></label>
                    </div>
                </div>
                <!---->
                <!--                <div class="field">-->
                <!--                    <label>Matricule</label>-->
                <!--                    <div class="input-wrap">-->
                <!--                        <span class="material-symbols-outlined ico">pin</span>-->
                <!--                        <input type="text" placeholder="Votre numéro matricule" required>-->
                <!--                    </div>-->
                <!--                </div>-->
                <form novalidate="novalidate" id="formSignIn" autocomplete="off">
                    <input type="hidden" name="option" value="2">

                    <div class="field">
                        <label>Email institutionnel</label>
                        <div class="input-wrap">
                            <span class="material-symbols-outlined ico">alternate_email</span>
                            <input type="email" placeholder="prenom.nom@uahb.sn" name="email" id="email" required>
                        </div>
                    </div>
                    <div class="field">
                        <label>Mot de passe</label>
                        <div class="input-wrap">
                            <span class="material-symbols-outlined ico">lock</span>
                            <input type="password" id="pw-field" name="password" placeholder="Votre mot de passe"
                                   required>
                            <button type="button" class="eye-btn" onclick="togglePw()">
                                <span class="material-symbols-outlined" style="font-size:18px"
                                      id="eye-icon">visibility</span>
                            </button>
                        </div>
                    </div>

                    <div class="form-row-meta">
                        <label class="remember">
                        </label>
                        <a href="/personnel/reset-password" class="link-btn">Mot de passe oublié ?</a>
                    </div>

                    <button class="submit-btn" type="button" id="formSignIn_submit">
                        <span class="material-symbols-outlined" style="font-size:18px">login</span>Se connecter

                    </button>


                    <div class="alt-line">ou</div>
                    <p class="switch-note">Pas encore de compte ? <a href="/personnel/signup" class="link-btn">Créer un
                            compte</a></p>

                </form>

            </div>

            <div id="success-state" class="success-banner" style="display:none">
                <div class="success-icon"><span class="material-symbols-outlined" style="font-size:34px">check</span>
                </div>
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
