<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>GSJLF — Inscription ENT</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
<!--    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css" />-->

    <link rel="stylesheet" type="text/css" href="/personnel/ressources/dist_assets/css/register.css">

</head>
<body>
<div class="page-shell">

    <!-- ═══════════════════════════════════════
         SIDEBAR — DIAPORAMA
    ═══════════════════════════════════════ -->
    <aside class="sidebar">
        <!-- Slides images -->
        <div class="slide active" id="slide-0" style="background-image:url('/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg')"></div>
        <div class="slide"        id="slide-1" style="background-image:url('/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg')"></div>
        <div class="slide"        id="slide-2" style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>

        <div class="sidebar-overlay"></div>
        <div class="sidebar-orb"></div>

        <div class="sb-inner">
            <!-- Logo uniquement -->
            <div class="sb-top">
                <a href="/personnel/accueil" class="sb-logo">
                    <img src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" alt="GSJLF" class="sb-logo-img">
                    <div>
                        <div class="sb-name">Groupe Scolaire Jean de la Fontaine</div>
                        <div class="sb-sub">Environnement Numérique de Travail</div>
                    </div>
                </a>
            </div>

            <!-- Texte central -->
            <div class="sb-mid">
                <div class="sb-kicker">
                    <span class="material-symbols-outlined" style="font-size:13px">person_add</span>
                    Rejoindre l'ENT GSJLF
                </div>

                <h2 class="sb-headline">
                    Créez votre<br>espace <span>numérique.</span>
                </h2>

                <p class="sb-desc">
                    Accédez à vos cours, notes, emplois du temps et outils collaboratifs.
                    Un seul compte pour tout le groupe scolaire.
                </p>

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

                <!-- Entité du slide courant -->
                <div class="slide-ent">
                    <img id="ent-logo" src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png" alt="">
                    <div class="slide-ent-info">
                        <span id="ent-name">UAHB</span>
                        <small id="ent-desc">Université Amadou Hampâté Bâ</small>
                    </div>
                </div>

                <!-- Points de navigation -->
                <div class="slide-dots" id="slide-dots"></div>
            </div>

            <!-- Bas -->
            <div class="sb-bottom">
                <div class="sb-secure">
                    <span class="material-symbols-outlined" style="font-size:13px">verified_user</span>
                    Données protégées · SSL
                </div>
                <span>© 2026 GSJLF</span>
            </div>
        </div>
    </aside>

    <!-- ═══════════════════════════════════════
         MAIN — FORMULAIRE
    ═══════════════════════════════════════ -->
    <main class="main-wrap">
        <div class="auth-card">

            <a href="/personnel/accueil" class="back-link">
                <span class="material-symbols-outlined" style="font-size:15px">arrow_back</span>
                Retour à l'accueil
            </a>

            <form novalidate="novalidate" id="formSignUp" autocomplete="off">

                <input type="hidden" name="option" value="1"  />
            <!-- Formulaire principal -->
            <div id="signup-form">
                <div class="form-icon">
                    <span class="material-symbols-outlined">person_add</span>
                </div>
                <h1 class="form-title">Créer un compte</h1>
                <p class="form-subtitle">Renseignez vos identifiants institutionnels pour rejoindre l'ENT GSJLF.</p>

                <!-- Matricule -->
                <div class="field">
                    <label>Matricule</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined ico">pin</span>
                        <input type="text" id="f-mat" placeholder="Votre numéro matricule" name="matricule" required autocomplete="off">
                    </div>
                </div>

                <!-- Email -->
                <div class="field">
                    <label>Email institutionnel</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined ico">alternate_email</span>
                        <input type="email" id="f-email" placeholder="prenom.nom@gsjlf.sn" name="email" required autocomplete="email">
                    </div>
                </div>

                <!-- Mot de passe -->
                <div class="field">
                    <label>Mot de passe</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined ico">lock</span>
                        <input type="password" id="f-pw"
                               placeholder="Créer un mot de passe"
                               oninput="checkStrength(this.value)" required autocomplete="new-password" name="password">
                        <button type="button" class="eye-btn" onclick="togglePw('f-pw','eye1')">
                            <span class="material-symbols-outlined" style="font-size:18px" id="eye1">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Barre de force -->
                <div class="strength-bar">
                    <div class="strength-track"><div class="strength-fill" id="sfill"></div></div>
                    <div class="strength-label" id="slabel">—</div>
                </div>

                <!-- Confirmation -->
                <div class="field">
                    <label>Confirmation du mot de passe</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined ico">lock_clock</span>
                        <input type="password" id="f-pw2"
                               placeholder="Répéter le mot de passe" name="confirm-password"
                               oninput="checkMatch()" required autocomplete="new-password">
                        <button type="button" class="eye-btn" onclick="togglePw('f-pw2','eye2')">
                            <span class="material-symbols-outlined" style="font-size:18px" id="eye2">visibility</span>
                        </button>
                    </div>
                </div>

                <p class="match-error" id="match-err">
                    <span class="material-symbols-outlined" style="font-size:13px">error</span>
                    Les mots de passe ne correspondent pas.
                </p>

                <!-- Règles -->
                <div class="rules">
                    <span><span class="material-symbols-outlined">check_circle</span> Minimum 8 caractères</span>
                    <span><span class="material-symbols-outlined">check_circle</span> Au moins une majuscule et un chiffre</span>
                    <span><span class="material-symbols-outlined">check_circle</span> Caractère spécial recommandé</span>
                </div>

                <!-- Bouton -->
                <button class="submit-btn" type="button"  id="formSignUp_submit">
                    <span class="material-symbols-outlined" style="font-size:18px">person_add</span>
                    Créer mon compte
                </button>

                <div class="alt-line">déjà inscrit</div>
                <p class="switch-note">Vous avez déjà un accès ? <a href="/personnel/signin" class="link-btn">Se connecter</a></p>
            </div>

            </form>
            <!-- Succès -->
            <div id="success-state" class="success-banner" style="display:none">
                <div class="success-icon">
                    <span class="material-symbols-outlined" style="font-size:34px">mark_email_read</span>
                </div>
                <h3>Compte créé !</h3>
                <p>Veuillez vérifier votre email institutionnel et cliquez sur le lien pour activer votre compte.</p>
<!--                <a href="activate.html" class="submit-btn"-->
<!--                   style="margin-top:22px;text-decoration:none;width:auto;padding:0 28px">-->
<!--                    Activer mon compte-->
<!--                </a>-->
            </div>

        </div>
    </main>
</div>


<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>

<script src="/personnel/scripts.bundle.1.js"></script>
<script>

</script>
</body>
</html>
