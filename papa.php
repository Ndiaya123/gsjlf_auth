
<?php
/* ══════════════════════════════════════════════════════
   GUARD : vérifier que l'utilisateur est connecté
   et que son compte nécessite un changement de MDP
   (premiereMdp = 1 = compte créé par un admin)
══════════════════════════════════════════════════════ */
session_start();

///* Rediriger vers signin si pas de session */
//if (empty($_SESSION['matricule'])) {
//    header("Location: /personnel/signin");
//    exit;
//}
//
///* Si le MDP n'est plus à modifier, rediriger vers l'accueil */
//if (empty($_SESSION['premiereMdp']) || $_SESSION['premiereMdp'] != 1) {
//    header("Location: /personnel/accueil");
//    exit;
//}

$prenom = $_SESSION['prenom'] ?? '';
$nom    = $_SESSION['nom']    ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>GSJLF — Définir mon mot de passe</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>
    <style>
        /* ═══════════════════════════════════════
           VARIABLES — identité GSJLF
        ═══════════════════════════════════════ */
        :root{
            --green:      #113B26;
            --green-mid:  #1a5c38;
            --green-light:#2d8a57;
            --beige:      #f0cc6a;
            --brown:      #864c20;
            --dark:       #0c1a10;
            --muted:      #5e6b61;
            --border:     rgba(17,59,38,.12);
            --border-soft:rgba(17,59,38,.07);
            --shadow:     0 32px 90px rgba(15,51,32,.18);

            --ok-bg:   rgba(17,59,38,.08);
            --ok-txt:  #113B26;
            --err-bg:  rgba(229,57,53,.08);
            --err-txt: #c62828;
        }

        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        html{height:100%}

        body{
            font-family:'Plus Jakarta Sans',sans-serif;
            min-height:100vh;
            color:var(--dark);
            overflow-x:hidden;
            background:
                    radial-gradient(ellipse 70% 60% at 0% 0%,   rgba(17,59,38,.10),transparent),
                    radial-gradient(ellipse 50% 50% at 100% 10%, rgba(134,76,32,.08),transparent),
                    #fdf8ef;
        }

        /* ═══════════════════════════════════════
           LAYOUT
        ═══════════════════════════════════════ */
        .ps_page-shell{
            min-height:100vh;
            display:grid;
            grid-template-columns:1.1fr .9fr;
        }

        /* ═══════════════════════════════════════
           SIDEBAR (fond image fixe)
        ═══════════════════════════════════════ */
        .ps_sidebar{
            position:sticky;top:0;
            height:100vh;
            overflow:hidden;
            display:flex;flex-direction:column;
        }

        .ps_slide{
            position:absolute;inset:0;z-index:0;
            background-size:cover;background-position:center;
            opacity:0;transition:opacity 1.2s ease;
        }
        .ps_slide.ps_active{opacity:1}

        .ps_sidebar-overlay{
            position:absolute;inset:0;z-index:2;
            background:linear-gradient(160deg,rgba(6,16,10,.86) 0%,rgba(6,16,10,.62) 45%,rgba(6,16,10,.84) 100%);
        }
        .ps_sidebar-overlay::before{
            content:"";position:absolute;inset:16px;
            border-radius:22px;border:1px solid rgba(255,255,255,.08);pointer-events:none;
        }

        .ps_sidebar-orb{
            position:absolute;z-index:3;
            width:380px;height:380px;border-radius:50%;
            background:radial-gradient(circle,rgba(240,204,106,.22),transparent 65%);
            right:-160px;top:-130px;pointer-events:none;
        }

        .ps_sb-inner{
            position:relative;z-index:10;height:100%;
            padding:32px;
            display:flex;flex-direction:column;justify-content:space-between;
        }

        .ps_sb-logo{display:flex;align-items:center;gap:13px;text-decoration:none}
        .ps_sb-logo-img{
            width:52px;height:52px;border-radius:50%;
            background:white;padding:3px;object-fit:contain;
            box-shadow:0 12px 28px rgba(0,0,0,.3);flex-shrink:0;
        }
        .ps_sb-name{color:white;font-weight:800;font-size:14px;line-height:1.3}
        .ps_sb-sub{color:rgba(255,255,255,.45);font-size:11px;font-weight:600;margin-top:2px}

        .ps_sb-mid{padding:0}

        .ps_sb-kicker{
            display:inline-flex;align-items:center;gap:7px;
            padding:7px 14px;border-radius:999px;
            background:rgba(240,204,106,.18);border:1px solid rgba(240,204,106,.3);
            color:var(--beige);font-size:11px;font-weight:800;letter-spacing:.08em;
            margin-bottom:20px;animation:ps_sbUp .6s .1s ease both;
        }

        .ps_sb-headline{
            font-family:'Playfair Display',serif;
            font-size:clamp(28px,3.2vw,46px);font-weight:900;
            color:white;line-height:1.0;letter-spacing:-.025em;
            margin-bottom:18px;animation:ps_sbUp .6s .18s ease both;
        }
        .ps_sb-headline span{color:var(--beige);font-style:italic}

        .ps_sb-desc{
            font-size:14px;line-height:1.9;
            color:rgba(255,255,255,.62);font-weight:500;
            max-width:360px;margin-bottom:28px;
            animation:ps_sbUp .6s .26s ease both;
        }

        /* Règles MDP dans la sidebar */
        .ps_sb-rules{
            display:grid;gap:10px;
            animation:ps_sbUp .6s .34s ease both;
        }

        .ps_sb-rule{
            display:flex;align-items:center;gap:10px;
            padding:11px 14px;border-radius:14px;
            background:rgba(255,255,255,.07);
            border:1px solid rgba(255,255,255,.08);
            transition:.3s;
        }

        .ps_sb-rule.ps_rule-ok{
            background:rgba(45,138,87,.2);
            border-color:rgba(45,138,87,.3);
        }

        .ps_sb-rule-ico{
            width:28px;height:28px;border-radius:8px;
            display:grid;place-items:center;
            background:rgba(255,255,255,.1);
            flex-shrink:0;transition:.3s;
        }

        .ps_sb-rule.ps_rule-ok .ps_sb-rule-ico{
            background:rgba(45,138,87,.3);
        }

        .ps_sb-rule-ico .material-symbols-outlined{font-size:15px;color:rgba(255,255,255,.6)}
        .ps_sb-rule.ps_rule-ok .ps_sb-rule-ico .material-symbols-outlined{color:#7dffb0}

        .ps_sb-rule-txt{font-size:12px;font-weight:700;color:rgba(255,255,255,.55)}
        .ps_sb-rule.ps_rule-ok .ps_sb-rule-txt{color:rgba(255,255,255,.85)}

        /* Entité diaporama */
        .ps_slide-ent{
            display:flex;align-items:center;gap:10px;
            animation:ps_sbUp .6s .42s ease both;
        }
        .ps_slide-ent img{
            width:32px;height:32px;border-radius:50%;
            object-fit:contain;background:white;padding:2px;
            box-shadow:0 6px 14px rgba(0,0,0,.2);
        }
        .ps_slide-ent-info span{display:block;font-size:11px;font-weight:800;color:var(--beige);letter-spacing:.06em}
        .ps_slide-ent-info small{display:block;font-size:10px;font-weight:600;color:rgba(255,255,255,.45);margin-top:1px}

        .ps_slide-dots{display:flex;align-items:center;gap:8px;margin-top:12px}
        .ps_slide-dot{height:4px;border-radius:999px;background:rgba(255,255,255,.28);cursor:pointer;transition:all .4s ease}
        .ps_slide-dot.ps_active{background:var(--beige)}

        .ps_sb-bottom{
            display:flex;align-items:center;justify-content:space-between;
            font-size:11px;font-weight:700;color:rgba(255,255,255,.32);
        }
        .ps_sb-secure{
            display:flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;
            background:rgba(255,255,255,.07);color:rgba(255,255,255,.5);
        }

        @keyframes ps_sbUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}

        /* ═══════════════════════════════════════
           MAIN — FORMULAIRE
        ═══════════════════════════════════════ */
        .ps_main-wrap{
            height:100vh;
            overflow-y:auto;
            overflow-x:hidden;
            display:flex;
            align-items:flex-start;
            justify-content:center;
            padding:44px 36px;
        }

        .ps_auth-card{
            width:100%;max-width:480px;
            background:rgba(255,255,255,.93);
            backdrop-filter:blur(32px);
            border:1px solid rgba(255,255,255,.95);
            border-radius:32px;padding:38px;
            box-shadow:var(--shadow);
            animation:ps_cardIn .5s cubic-bezier(.22,1,.36,1) both;
            margin:auto 0;
        }

        @keyframes ps_cardIn{
            from{opacity:0;transform:translateY(28px) scale(.98)}
            to  {opacity:1;transform:translateY(0) scale(1)}
        }

        /* ─── ALERTE OBLIGATOIRE ─── */
        .ps_alert-banner{
            display:flex;align-items:flex-start;gap:12px;
            padding:14px 16px;
            border-radius:16px;
            background:linear-gradient(135deg,rgba(240,204,106,.15),rgba(134,76,32,.08));
            border:1.5px solid rgba(240,204,106,.35);
            margin-bottom:24px;
        }
        .ps_alert-icon{
            width:36px;height:36px;border-radius:11px;
            background:rgba(240,204,106,.2);
            display:grid;place-items:center;
            color:var(--brown);flex-shrink:0;
        }
        .ps_alert-txt h4{
            font-size:13px;font-weight:800;color:var(--brown);margin-bottom:3px;
        }
        .ps_alert-txt p{
            font-size:12px;font-weight:600;color:#6b3c10;line-height:1.5;
        }

        /* ─── HEADER ─── */
        .ps_form-icon{
            width:56px;height:56px;border-radius:20px;
            display:grid;place-items:center;
            background:linear-gradient(135deg,var(--green),var(--green-mid));
            color:white;margin-bottom:18px;
            box-shadow:0 14px 30px rgba(17,59,38,.22);
        }

        .ps_form-title{
            font-family:'Playfair Display',serif;
            font-size:28px;font-weight:900;letter-spacing:-.03em;margin-bottom:6px;
        }

        .ps_form-subtitle{
            font-size:13px;line-height:1.7;color:var(--muted);font-weight:600;margin-bottom:24px;
        }

        .ps_user-badge{
            display:inline-flex;align-items:center;gap:8px;
            padding:8px 14px;border-radius:12px;
            background:rgba(17,59,38,.06);border:1px solid var(--border);
            margin-bottom:22px;
        }
        .ps_user-badge-avatar{
            width:28px;height:28px;border-radius:8px;
            background:linear-gradient(135deg,var(--green),var(--green-mid));
            display:grid;place-items:center;
            color:white;font-weight:900;font-size:11px;
        }
        .ps_user-badge-name{font-size:12px;font-weight:800;color:var(--dark)}
        .ps_user-badge-label{font-size:10px;font-weight:600;color:var(--muted)}

        /* ─── FIELDS ─── */
        .ps_field{margin-bottom:14px}
        .ps_field label{
            display:block;font-size:10px;font-weight:800;
            color:#2a3a2f;margin-bottom:7px;letter-spacing:.06em;text-transform:uppercase;
        }
        .ps_input-wrap{position:relative}

        .ps_input-wrap .ps_ico{
            position:absolute;left:13px;top:50%;transform:translateY(-50%);
            color:#96aca0;font-size:18px;pointer-events:none;transition:color .2s;
        }
        .ps_input-wrap:focus-within .ps_ico{color:var(--green)}

        .ps_input-wrap input{
            width:100%;height:50px;border-radius:14px;
            border:1.5px solid var(--border);background:white;outline:none;
            padding:0 48px 0 42px;
            font-family:'Plus Jakarta Sans',sans-serif;
            font-weight:700;font-size:13px;color:var(--dark);
            transition:border-color .22s,box-shadow .22s,transform .15s;
        }
        .ps_input-wrap input:focus{
            border-color:var(--green);
            box-shadow:0 0 0 4px rgba(17,59,38,.08);
            transform:translateY(-1px);
        }
        .ps_input-wrap input::placeholder{color:#b8c4bc;font-weight:600}
        .ps_input-wrap input:focus{outline:none}

        /* override style.bundle */
        .ps_page-shell .ps_input-wrap input{
            width:100% !important;height:50px !important;
            padding:0 48px 0 42px !important;
            border-radius:14px !important;
            border:1.5px solid var(--border) !important;
            background:white !important;box-shadow:none !important;
        }
        .ps_page-shell .ps_input-wrap input:focus{
            border-color:var(--green) !important;
            box-shadow:0 0 0 4px rgba(17,59,38,.08) !important;
            outline:none !important;
        }

        .ps_eye-btn{
            position:absolute;right:12px;top:50%;transform:translateY(-50%);
            background:none;border:none;cursor:pointer;
            color:#96aca0;display:grid;place-items:center;padding:4px;transition:color .2s;
        }
        .ps_eye-btn:hover{color:var(--green)}

        /* ─── BARRE DE FORCE ─── */
        .ps_strength-bar{margin:4px 0 14px}
        .ps_strength-track{
            height:5px;background:rgba(17,59,38,.08);
            border-radius:999px;overflow:hidden;
        }
        .ps_strength-fill{
            height:100%;border-radius:999px;
            transition:width .4s ease,background .4s ease;width:0%;
        }
        .ps_strength-row{
            display:flex;align-items:center;justify-content:space-between;
            margin-top:6px;
        }
        .ps_strength-label{font-size:11px;font-weight:700;color:var(--muted)}
        .ps_strength-pct  {font-size:10px;font-weight:800;color:var(--muted)}

        /* ─── ERREUR CORRESPONDANCE ─── */
        .ps_match-error{
            display:none;align-items:center;gap:5px;
            font-size:11px;font-weight:700;color:#e53935;
            margin-top:-8px;margin-bottom:12px;
        }
        .ps_match-error.ps_show{display:flex}

        /* ─── BOUTON SUBMIT ─── */
        .ps_submit-btn{
            width:100%;height:52px;border:0;border-radius:16px;
            cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;
            font-weight:800;font-size:14px;color:white;
            background:linear-gradient(135deg,var(--green-mid),var(--green));
            box-shadow:0 14px 32px rgba(17,59,38,.22);
            transition:transform .25s,box-shadow .25s;
            display:flex;align-items:center;justify-content:center;gap:9px;
            position:relative;overflow:hidden;margin-top:4px;
        }
        .ps_submit-btn::before{
            content:"";position:absolute;inset:0;
            background:linear-gradient(135deg,rgba(255,255,255,.12),transparent);
            opacity:0;transition:.25s;
        }
        .ps_submit-btn:hover{transform:translateY(-2px);box-shadow:0 20px 42px rgba(17,59,38,.28)}
        .ps_submit-btn:hover::before{opacity:1}
        .ps_submit-btn:disabled{opacity:.7;cursor:not-allowed;transform:none}

        /* override style.bundle */
        .ps_page-shell .ps_submit-btn{
            width:100% !important;height:52px !important;
            display:flex !important;align-items:center !important;justify-content:center !important;
            padding:0 !important;border:0 !important;border-radius:16px !important;
            background:linear-gradient(135deg,var(--green-mid),var(--green)) !important;
            color:white !important;font-size:14px !important;font-weight:800 !important;
            box-shadow:0 14px 32px rgba(17,59,38,.22) !important;
        }

        /* ─── SUCCESS STATE ─── */
        .ps_success-state{
            display:none;
            flex-direction:column;align-items:center;
            padding:16px 0 8px;text-align:center;
        }

        .ps_success-icon{
            width:80px;height:80px;border-radius:50%;
            background:linear-gradient(135deg,var(--green-mid),var(--green-light));
            display:grid;place-items:center;
            color:white;margin-bottom:20px;
            box-shadow:0 20px 40px rgba(17,59,38,.22);
            animation:ps_popIn .5s cubic-bezier(.34,1.56,.64,1) both;
        }
        @keyframes ps_popIn{from{transform:scale(0);opacity:0}to{transform:scale(1);opacity:1}}

        .ps_success-state h2{
            font-family:'Playfair Display',serif;
            font-size:26px;font-weight:900;letter-spacing:-.03em;margin-bottom:10px;
        }
        .ps_success-state p{
            font-size:13px;line-height:1.75;color:var(--muted);font-weight:600;max-width:300px;
            margin-bottom:24px;
        }

        .ps_redirect-bar{
            width:100%;height:4px;border-radius:999px;
            background:rgba(17,59,38,.1);overflow:hidden;
            margin-bottom:8px;
        }
        .ps_redirect-fill{
            height:100%;border-radius:999px;
            background:linear-gradient(90deg,var(--green-mid),var(--green-light));
            width:0%;transition:width linear;
        }
        .ps_redirect-label{font-size:11px;font-weight:700;color:var(--muted)}

        /* ─── VALIDATION MESSAGES ─── */
        .fv-plugins-message-container.invalid-feedback,
        .fv-plugins-message-container.valid-feedback{
            display:flex;align-items:center;gap:5px;
            font-size:11px;font-weight:700;color:#e53935;
        }

        /* btn SweetAlert */
        .ps_page-shell .btn{
            padding:12px 24px;border:none;border-radius:12px;
            font-size:14px;font-weight:700;cursor:pointer;color:white;
        }

        @keyframes ps_spin{to{transform:rotate(360deg)}}

        /* ─── RESPONSIVE ─── */
        @media(max-width:980px){
            .ps_page-shell{grid-template-columns:1fr}
            .ps_sidebar{display:none}
            .ps_main-wrap{height:auto;min-height:100vh;padding:22px 16px}
            .ps_auth-card{padding:26px 22px;border-radius:24px;margin:0}
        }

        /* isolation style.bundle */
        .ps_page-shell *,.ps_page-shell *::before,.ps_page-shell *::after{box-sizing:border-box !important}
        .ps_page-shell{display:grid !important;grid-template-columns:1.1fr .9fr !important;min-height:100vh !important;width:100% !important;overflow:visible !important}
        .ps_sidebar{position:sticky !important;top:0 !important;height:100vh !important;overflow:hidden !important;padding:0 !important;margin:0 !important}
        .ps_sb-inner{padding:32px !important;margin:0 !important;width:100% !important}
        .ps_main-wrap{height:100vh !important;overflow-y:auto !important;overflow-x:hidden !important;display:flex !important;align-items:flex-start !important;justify-content:center !important;padding:44px 36px !important;min-width:0 !important}
        .ps_auth-card{width:100% !important;max-width:480px !important;margin:auto 0 !important;padding:38px !important}
        .ps_slide{position:absolute !important;inset:0 !important;margin:0 !important;padding:0 !important;border:none !important}

        @media(max-width:980px){
            .ps_page-shell{grid-template-columns:1fr !important}
            .ps_sidebar{display:none !important}
            .ps_main-wrap{height:auto !important;min-height:100vh !important;overflow-y:visible !important;padding:22px 16px !important}
            .ps_auth-card{padding:26px 22px !important;border-radius:24px !important;margin:0 !important}
        }
    </style>
</head>
<body>
<div class="ps_page-shell">

    <!-- ══════════ SIDEBAR ══════════ -->
    <aside class="ps_sidebar">
        <div class="ps_slide ps_active" style="background-image:url('/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg')"></div>
        <div class="ps_slide"           style="background-image:url('/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg')"></div>
        <div class="ps_slide"           style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>
        <div class="ps_sidebar-overlay"></div>
        <div class="ps_sidebar-orb"></div>

        <div class="ps_sb-inner">
            <div>
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
                    <span class="material-symbols-outlined" style="font-size:12px">lock_reset</span>
                    Sécurisation du compte
                </div>
                <h2 class="ps_sb-headline">Créez votre<br><span>mot de passe.</span></h2>
                <p class="ps_sb-desc">Votre compte a été créé par l'administration. Définissez maintenant votre mot de passe personnel pour sécuriser votre espace ENT.</p>

                <!-- Règles dynamiques -->
                <div class="ps_sb-rules" id="sidebarRules">
                    <div class="ps_sb-rule" id="rule-length">
                        <div class="ps_sb-rule-ico">
                            <span class="material-symbols-outlined">straighten</span>
                        </div>
                        <span class="ps_sb-rule-txt">Au moins 8 caractères</span>
                    </div>
                    <div class="ps_sb-rule" id="rule-upper">
                        <div class="ps_sb-rule-ico">
                            <span class="material-symbols-outlined">text_fields</span>
                        </div>
                        <span class="ps_sb-rule-txt">Une lettre majuscule</span>
                    </div>
                    <div class="ps_sb-rule" id="rule-digit">
                        <div class="ps_sb-rule-ico">
                            <span class="material-symbols-outlined">pin</span>
                        </div>
                        <span class="ps_sb-rule-txt">Un chiffre</span>
                    </div>
                    <div class="ps_sb-rule" id="rule-special">
                        <div class="ps_sb-rule-ico">
                            <span class="material-symbols-outlined">tag</span>
                        </div>
                        <span class="ps_sb-rule-txt">Un caractère spécial</span>
                    </div>
                </div>

                <div style="margin-top:20px">
                    <div class="ps_slide-ent">
                        <img id="ent-logo" src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png" alt="">
                        <div class="ps_slide-ent-info">
                            <span id="ent-name">UAHB</span>
                            <small id="ent-desc">Université Amadou Hampâté Bâ</small>
                        </div>
                    </div>
                    <div class="ps_slide-dots" id="slide-dots"></div>
                </div>
            </div>

            <div class="ps_sb-bottom">
                <div class="ps_sb-secure">
                    <span class="material-symbols-outlined" style="font-size:13px">verified_user</span>
                    Connexion chiffrée SSL
                </div>
                <span>© 2026 GSJLF</span>
            </div>
        </div>
    </aside>

    <!-- ══════════ MAIN ══════════ -->
    <main class="ps_main-wrap">
        <div class="ps_auth-card">

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

            <!-- FORMULAIRE -->
            <div id="changePasswordForm">
                <div class="ps_form-icon">
                    <span class="material-symbols-outlined" style="font-size:28px">lock_reset</span>
                </div>
                <h1 class="ps_form-title">Nouveau mot de passe</h1>
                <p class="ps_form-subtitle">Bonjour <strong><?= htmlspecialchars($prenom . ' ' . $nom) ?></strong>, définissez un mot de passe sécurisé pour votre compte ENT.</p>

                <!-- Badge utilisateur -->
                <div class="ps_user-badge">
                    <div class="ps_user-badge-avatar">
                        <?= strtoupper(substr($prenom, 0, 1) . substr($nom, 0, 1)) ?>
                    </div>
                    <div>
                        <div class="ps_user-badge-name"><?= htmlspecialchars($prenom . ' ' . $nom) ?></div>
                        <div class="ps_user-badge-label">Première connexion · Modification requise</div>
                    </div>
                </div>

                <form novalidate="novalidate" id="formChangePwd" autocomplete="off">
                    <input type="hidden" name="option" value="7">

                    <!-- Nouveau mot de passe -->
                    <div class="ps_field">
                        <label>Nouveau mot de passe <span style="color:#e53935">*</span></label>
                        <div class="ps_input-wrap">
                            <span class="material-symbols-outlined ps_ico">lock</span>
                            <input type="password" id="pw-new" name="newPassword"
                                   placeholder="Créer un mot de passe sécurisé"
                                   oninput="onPasswordInput(this.value)" required autocomplete="new-password">
                            <button type="button" class="ps_eye-btn" onclick="togglePw('pw-new','eye-new')" tabindex="-1">
                                <span class="material-symbols-outlined" style="font-size:18px" id="eye-new">visibility</span>
                            </button>
                        </div>
                    </div>

                    <!-- Barre de force -->
                    <div class="ps_strength-bar">
                        <div class="ps_strength-track">
                            <div class="ps_strength-fill" id="strength-fill"></div>
                        </div>
                        <div class="ps_strength-row">
                            <span class="ps_strength-label" id="strength-label">—</span>
                            <span class="ps_strength-pct"   id="strength-pct"></span>
                        </div>
                    </div>

                    <!-- Confirmation -->
                    <div class="ps_field">
                        <label>Confirmer le mot de passe <span style="color:#e53935">*</span></label>
                        <div class="ps_input-wrap">
                            <span class="material-symbols-outlined ps_ico">lock_clock</span>
                            <input type="password" id="pw-confirm" name="confirmPassword"
                                   placeholder="Répéter le mot de passe"
                                   oninput="checkMatch()" required autocomplete="new-password">
                            <button type="button" class="ps_eye-btn" onclick="togglePw('pw-confirm','eye-confirm')" tabindex="-1">
                                <span class="material-symbols-outlined" style="font-size:18px" id="eye-confirm">visibility</span>
                            </button>
                        </div>
                    </div>

                    <p class="ps_match-error" id="match-error">
                        <span class="material-symbols-outlined" style="font-size:13px">error</span>
                        Les mots de passe ne correspondent pas.
                    </p>

                    <button class="ps_submit-btn" type="button" id="submitChangePwd">
                        <span class="material-symbols-outlined" style="font-size:18px">check_circle</span>
                        Enregistrer et accéder à mon espace
                    </button>

                </form>
            </div>

            <!-- ÉTAT SUCCÈS -->
            <div class="ps_success-state" id="successState">
                <div class="ps_success-icon">
                    <span class="material-symbols-outlined" style="font-size:36px">lock_open</span>
                </div>
                <h2>Mot de passe défini !</h2>
                <p>Votre mot de passe a été enregistré avec succès. Vous allez être redirigé vers votre espace ENT.</p>
                <div class="ps_redirect-bar">
                    <div class="ps_redirect-fill" id="redirectFill"></div>
                </div>
                <span class="ps_redirect-label" id="redirectLabel">Redirection dans 3 secondes…</span>
            </div>

        </div>
    </main>
</div>

<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script>
    /* ══════════════════════════════════════
       DIAPORAMA
    ══════════════════════════════════════ */
    const SLIDES = [
        { logo:'/personnel/ressources/dist_assets/media/logos/logo_uahb.png',  name:'UAHB',  desc:'Université Amadou Hampâté Bâ' },
        { logo:'/personnel/ressources/dist_assets/media/logos/logo_cmjlf.png', name:'CMJLF', desc:'Collège Moderne Jean de la Fontaine' },
        { logo:'/personnel/ressources/dist_assets/media/logos/logo_ctd.png',   name:'CTD',   desc:'Collège Technique de Dakar' },
    ];

    let cur = 0, slideTimer = null;
    const slides = document.querySelectorAll('.ps_slide');
    const dotsEl  = document.getElementById('slide-dots');

    SLIDES.forEach((_, i) => {
        const d = document.createElement('div');
        d.className = 'ps_slide-dot' + (i === 0 ? ' ps_active' : '');
        d.style.width = i === 0 ? '28px' : '8px';
        d.addEventListener('click', () => goSlide(i));
        dotsEl.appendChild(d);
    });

    function getDots() { return dotsEl.querySelectorAll('.ps_slide-dot'); }

    function goSlide(n, restart = true) {
        slides[cur].classList.remove('ps_active');
        getDots()[cur].classList.remove('ps_active');
        getDots()[cur].style.width = '8px';
        cur = (n + SLIDES.length) % SLIDES.length;
        slides[cur].classList.add('ps_active');
        getDots()[cur].classList.add('ps_active');
        getDots()[cur].style.width = '28px';
        const el = document.getElementById('ent-logo');
        const en = document.getElementById('ent-name');
        const ed = document.getElementById('ent-desc');
        [el,en,ed].forEach(x => { x.style.transition='opacity .3s'; x.style.opacity='0'; });
        setTimeout(() => {
            el.src = SLIDES[cur].logo;
            en.textContent = SLIDES[cur].name;
            ed.textContent = SLIDES[cur].desc;
            [el,en,ed].forEach(x => x.style.opacity='1');
        }, 300);
        if (restart) { clearInterval(slideTimer); slideTimer = setInterval(() => goSlide(cur+1,false), 5000); }
    }
    slideTimer = setInterval(() => goSlide(cur+1,false), 5000);

    /* ══════════════════════════════════════
       TOGGLE VISIBILITÉ MDP
    ══════════════════════════════════════ */
    function togglePw(fieldId, iconId) {
        const f = document.getElementById(fieldId);
        const i = document.getElementById(iconId);
        f.type = f.type === 'password' ? 'text' : 'password';
        i.textContent = f.type === 'password' ? 'visibility' : 'visibility_off';
    }

    /* ══════════════════════════════════════
       FORCE DU MOT DE PASSE
    ══════════════════════════════════════ */
    const RULES = [
        { id: 'rule-length',  test: v => v.length >= 8              },
        { id: 'rule-upper',   test: v => /[A-Z]/.test(v)            },
        { id: 'rule-digit',   test: v => /[0-9]/.test(v)            },
        { id: 'rule-special', test: v => /[^A-Za-z0-9]/.test(v)     },
    ];

    const STRENGTH_LEVELS = [
        { label:'—',          color:'',         pct:0  },
        { label:'Trop court', color:'#e53935',  pct:25 },
        { label:'Faible',     color:'#ff9800',  pct:50 },
        { label:'Moyen',      color:'#fbc02d',  pct:75 },
        { label:'Fort',       color:'#2e7d32',  pct:100},
    ];

    function onPasswordInput(v) {
        let score = 0;
        RULES.forEach(r => {
            const ok = r.test(v);
            if (ok) score++;
            const el = document.getElementById(r.id);
            if (el) {
                el.classList.toggle('ps_rule-ok', ok);
                el.querySelector('.material-symbols-outlined').textContent = ok ? 'check_circle' : el.querySelector('.material-symbols-outlined').getAttribute('data-icon') || el.querySelector('.material-symbols-outlined').textContent;
            }
        });

        const lvl = STRENGTH_LEVELS[score];
        const fill  = document.getElementById('strength-fill');
        const label = document.getElementById('strength-label');
        const pct   = document.getElementById('strength-pct');
        fill.style.width      = lvl.pct + '%';
        fill.style.background = lvl.color;
        label.textContent     = lvl.label;
        pct.textContent       = lvl.pct > 0 ? lvl.pct + '%' : '';
        pct.style.color       = lvl.color;
        label.style.color     = lvl.color || 'var(--muted)';

        checkMatch();
        return score;
    }

    /* ══════════════════════════════════════
       CORRESPONDANCE MDP
    ══════════════════════════════════════ */
    function checkMatch() {
        const p1  = document.getElementById('pw-new').value;
        const p2  = document.getElementById('pw-confirm').value;
        const err = document.getElementById('match-error');
        const inp = document.getElementById('pw-confirm');
        if (!p2) { err.classList.remove('ps_show'); return; }
        const bad = p1 !== p2;
        err.classList.toggle('ps_show', bad);
        inp.style.borderColor = bad ? '#e53935' : '';
        inp.style.boxShadow   = bad ? '0 0 0 4px rgba(229,57,53,.1)' : '';
    }

    /* ══════════════════════════════════════
       SWEETALERT CENTRALISÉ
    ══════════════════════════════════════ */
    function showSwal(icon, title, text, onClose) {
        const colors = { success:'#113B26', error:'#dc3545', warning:'#d97706' };
        Swal.fire({
            icon, title, text,
            confirmButtonText: 'OK',
            confirmButtonColor: colors[icon] || '#113B26',
            buttonsStyling: false,
            customClass: { confirmButton: 'btn' },
            timer: 4000, timerProgressBar: true,
            didOpen: () => {
                const btn = Swal.getConfirmButton();
                btn.style.backgroundColor = colors[icon] || '#113B26';
                btn.style.color = '#fff';
            }
        }).then(() => { if (typeof onClose === 'function') onClose(); });
    }

    /* ══════════════════════════════════════
       SOUMISSION
    ══════════════════════════════════════ */
    document.getElementById('submitChangePwd').addEventListener('click', function () {
        const pw1  = document.getElementById('pw-new').value;
        const pw2  = document.getElementById('pw-confirm').value;
        const btn  = this;

        /* Validation côté client */
        if (!pw1) {
            document.getElementById('pw-new').focus();
            showSwal('warning', 'Champ requis', 'Veuillez saisir votre nouveau mot de passe.');
            return;
        }

        const score = onPasswordInput(pw1);
        if (score < 3) {
            showSwal('warning', 'Mot de passe trop faible', 'Votre mot de passe doit contenir au moins 8 caractères, une majuscule, un chiffre et un caractère spécial.');
            return;
        }

        if (!pw2) {
            document.getElementById('pw-confirm').focus();
            showSwal('warning', 'Confirmation requise', 'Veuillez confirmer votre nouveau mot de passe.');
            return;
        }

        if (pw1 !== pw2) {
            checkMatch();
            document.getElementById('pw-confirm').focus();
            return;
        }

        /* Envoi AJAX */
        btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;animation:ps_spin 1s linear infinite">progress_activity</span> Enregistrement…';
        btn.disabled  = true;

        $.ajax({
            type: 'post',
            url:  '/personnel/auth-controller',
            data: {
                option:          7,
                newPassword:     pw1,
                confirmPassword: pw2
            },
            success: function (resp) {

                btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px">check_circle</span> Enregistrer et accéder à mon espace';
                btn.disabled  = false;

                if (resp === 'sessionExpired') {
                    window.location.href = '/personnel/signin';

                } else if (resp === 'champsObligatoire') {
                    showSwal('warning', 'Champs obligatoires', 'Veuillez remplir tous les champs.');

                } else if (resp === 'pasCorrespondant') {
                    showSwal('error', 'Mots de passe différents', 'Les deux mots de passe saisis ne correspondent pas.');

                } else if (resp === 'mdpFaible') {
                    showSwal('warning', 'Mot de passe trop faible', 'Choisissez un mot de passe plus sécurisé.');

                } else if (resp === 'memeMotDePasse') {
                    showSwal('warning', 'Mot de passe identique', 'Votre nouveau mot de passe doit être différent du mot de passe provisoire.');

                } else if (resp === 'succès') {
                    /* Afficher le succès et rediriger */
                    document.getElementById('changePasswordForm').style.display = 'none';
                    const ss = document.getElementById('successState');
                    ss.style.display = 'flex';

                    let remaining = 3;
                    const fill    = document.getElementById('redirectFill');
                    const label   = document.getElementById('redirectLabel');

                    fill.style.transition = 'width ' + remaining + 's linear';
                    fill.style.width      = '100%';

                    const countdown = setInterval(() => {
                        remaining--;
                        label.textContent = remaining > 0
                            ? 'Redirection dans ' + remaining + ' seconde' + (remaining > 1 ? 's' : '') + '…'
                            : 'Redirection en cours…';
                        if (remaining <= 0) {
                            clearInterval(countdown);
                            window.location.href = '/personnel/accueil';
                        }
                    }, 1000);

                } else {
                    showSwal('error', 'Erreur inattendue', 'Une erreur est survenue. Veuillez réessayer.');
                }
            },
            error: function () {
                btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px">check_circle</span> Enregistrer et accéder à mon espace';
                btn.disabled  = false;
                showSwal('error', 'Erreur réseau', 'Impossible de contacter le serveur. Vérifiez votre connexion.');
            }
        });
    });

    /* Icônes des règles — stocker l'icône par défaut */
    document.querySelectorAll('.ps_sb-rule-ico .material-symbols-outlined').forEach(el => {
        el.setAttribute('data-icon', el.textContent);
    });
</script>
</body>
</html>