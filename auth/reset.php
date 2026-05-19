<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>GSJLF — Réinitialisation mot de passe</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" type="text/css" href="/personnel/ressources/dist_assets/css/reset.css">
</head>
<body>
<div class="page-shell">
    <aside class="sidebar">
        <div class="slide active" style="background-image:url('/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg')"></div>
        <div class="slide"        style="background-image:url('/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg')"></div>
        <div class="slide"        style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>
        <div class="sidebar-overlay"></div>
        <div class="sidebar-orb"></div>
        <div class="sb-inner">
            <div><a href="/personnel/accueil" class="sb-logo">
                    <img src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" alt="GSJLF" class="sb-logo-img">
                    <div><div class="sb-name">Groupe Scolaire Jean de la Fontaine</div><div class="sb-sub">Environnement Numérique de Travail</div></div>
                </a></div>
            <div class="sb-mid">
                <div class="sb-kicker"><span class="material-symbols-outlined" style="font-size:13px">lock_reset</span>Récupération d'accès</div>
                <h2 class="sb-headline">Réinitialisez<br>votre <span>mot de passe.</span></h2>
                <p class="sb-desc">Recevez un lien sécurisé pour créer un nouveau mot de passe et retrouver l'accès à votre espace ENT.</p>
                <div class="sb-stats">
                    <div class="sb-stat"><div class="sb-stat-num">10min</div><div class="sb-stat-lbl">Validité</div></div>
                    <div class="sb-stat"><div class="sb-stat-num">1x</div><div class="sb-stat-lbl">Usage</div></div>
                    <div class="sb-stat"><div class="sb-stat-num">SSL</div><div class="sb-stat-lbl">Chiffré</div></div>
                </div>
                <div class="slide-ent">
                    <img id="ent-logo" src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png" alt="">
                    <div class="slide-ent-info"><span id="ent-name">UAHB</span><small id="ent-desc">Université Amadou Hampâté Bâ</small></div>
                </div>
                <div class="slide-dots" id="slide-dots"></div>
            </div>
            <div class="sb-bottom">
                <div class="sb-secure"><span class="material-symbols-outlined" style="font-size:13px">verified_user</span>Réinitialisation sécurisée</div>
                <span>© 2026 GSJLF</span>
            </div>
        </div>
    </aside>

    <main class="main-wrap">
        <div class="auth-card">
            <a href="/personnel/signin" class="back-link"><span class="material-symbols-outlined" style="font-size:15px">arrow_back</span>Retour à la connexion</a>

            <form novalidate="novalidate" id="formReset" autocomplete="off">

                <input type="hidden" name="option" value="6">

                <!-- PHASE 1 : demande -->
                <div id="phase1" class="phase">
                    <div class="form-icon"><span class="material-symbols-outlined">lock_reset</span></div>
                    <h1 class="form-title">Mot de passe oublié</h1>
                    <p class="form-subtitle">Renseignez votre matricule et email pour recevoir un lien de réinitialisation sécurisé.</p>
                    <div class="info-box">
                        <span class="material-symbols-outlined ico2">link</span>
                        <p>Le lien est personnel, usage unique et expire automatiquement après 24 heures.</p>
                    </div>
                    <div class="field">
                        <label>Matricule</label>
                        <div class="input-wrap">
                            <span class="material-symbols-outlined ico">pin</span>
                            <input type="text" id="mat1" placeholder="Votre numéro matricule" name="matricule" required>
                        </div>
                    </div>
                    <div class="field">
                        <label>Email institutionnel</label>
                        <div class="input-wrap">
                            <span class="material-symbols-outlined ico">alternate_email</span>
                            <input type="email" id="email1" name="email" placeholder="prenom.nom@uahb.sn" required>
                        </div>
                    </div>
                    <div class="link-card">
                        <div class="link-card-icon"><span class="material-symbols-outlined">mark_email_read</span></div>
                        <div><strong>Lien sécurisé par email</strong><p>Le lien permettra de définir un nouveau mot de passe sur une page dédiée.</p></div>
                    </div>
                    <div class="rules">
                        <span><span class="material-symbols-outlined">check_circle</span>Lien personnel et non partageable.</span>
                        <span><span class="material-symbols-outlined">check_circle</span>Expire automatiquement après 24 heures.</span>
                        <span><span class="material-symbols-outlined">check_circle</span>Vérifiez vos spams si vous ne recevez rien.</span>
                    </div>
                    <button class="submit-btn" type="button"  id="formReset_submit">
                        <span class="material-symbols-outlined" style="font-size:18px">send</span>Envoyer le lien
                    </button>
                    <div class="alt-line">retour</div>
                    <p class="switch-note">Vous vous souvenez du mot de passe ? <a href="/personnel/signin" class="link-btn">Se connecter</a></p>
                </div>

            </form>

            <!-- PHASE 2 : email envoyé -->
            <div id="phase2" style="display:none">
                <div class="form-icon" style="background:linear-gradient(135deg,#0f7c40,#1aad60)">
                    <span class="material-symbols-outlined">mark_email_unread</span>
                </div>
                <h1 class="form-title">Email envoyé !</h1>
                <p class="form-subtitle">Un lien de réinitialisation a été envoyé à <strong id="email-display"></strong>.</p>
                <div class="info-box-success">
                    <span class="material-symbols-outlined" style="font-size:18px;color:#2e7d32;margin-top:1px;flex-shrink:0">check_circle</span>
                    <p>Email envoyé avec succès. Lien valable 24 heures. Pensez à vérifier votre dossier spam.</p>
                </div>
                <button class="submit-btn" type="button" onclick="goPhase(3)">
                    <span class="material-symbols-outlined" style="font-size:18px">lock_reset</span>Définir mon nouveau mot de passe
                </button>
                <button class="btn-ghost" type="button" onclick="goPhase(1)">
                    <span class="material-symbols-outlined" style="font-size:18px">refresh</span>Renvoyer le lien
                </button>
            </div>

            <!-- PHASE 3 : nouveau mot de passe -->
            <div id="phase3" style="display:none">
                <div class="form-icon"><span class="material-symbols-outlined">key</span></div>
                <h1 class="form-title">Nouveau mot de passe</h1>
                <p class="form-subtitle">Choisissez un nouveau mot de passe sécurisé pour votre compte ENT.</p>
                <div class="field">
                    <label>Nouveau mot de passe</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined ico">lock</span>
                        <input type="password" id="pw1" placeholder="Créer un mot de passe" oninput="checkStrength(this.value)" required>
                        <button type="button" class="eye-btn" onclick="togglePw('pw1','eye1')">
                            <span class="material-symbols-outlined" style="font-size:18px" id="eye1">visibility</span>
                        </button>
                    </div>
                </div>
                <div class="strength-bar">
                    <div class="strength-track"><div class="strength-fill" id="sfill"></div></div>
                    <div class="strength-label" id="slabel">—</div>
                </div>
                <div class="field">
                    <label>Confirmation du mot de passe</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined ico">lock_clock</span>
                        <input type="password" id="pw2" placeholder="Répéter le mot de passe" oninput="checkMatch()" required>
                        <button type="button" class="eye-btn" onclick="togglePw('pw2','eye2')">
                            <span class="material-symbols-outlined" style="font-size:18px" id="eye2">visibility</span>
                        </button>
                    </div>
                </div>
                <p class="match-error" id="match-err">
                    <span class="material-symbols-outlined" style="font-size:13px">error</span>Les mots de passe ne correspondent pas.
                </p>
                <div class="rules">
                    <span><span class="material-symbols-outlined">check_circle</span>Minimum 8 caractères</span>
                    <span><span class="material-symbols-outlined">check_circle</span>Au moins une majuscule et un chiffre</span>
                    <span><span class="material-symbols-outlined">check_circle</span>Différent de votre ancien mot de passe</span>
                </div>
                <button class="submit-btn" type="button" id="btn-reset" onclick="handleReset()">
                    <span class="material-symbols-outlined" style="font-size:18px">check_circle</span>Enregistrer le nouveau mot de passe
                </button>
            </div>

            <!-- SUCCESS -->
            <div id="success-state" class="success-banner" style="display:none">
                <div class="success-icon"><span class="material-symbols-outlined" style="font-size:34px">lock_open</span></div>
                <h3>Mot de passe mis à jour !</h3>
                <p>Votre mot de passe a été réinitialisé avec succès. Connectez-vous avec vos nouveaux identifiants.</p>
                <a href="/personnel/signin" class="submit-btn" style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px">Se connecter</a>
            </div>
        </div>
    </main>
</div>


<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>

<script src="/personnel/scripts.bundle.4.js"></script>
</body>
</html>
