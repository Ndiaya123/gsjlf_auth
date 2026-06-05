<?php

include_once('../sessions/userSimple.php');


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>ENT — GSJLF</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <style>
        /* ═══════════════════════════════════════
           VARIABLES — identité GSJLF fond clair
        ═══════════════════════════════════════ */
        :root{
            --green:      #113B26;
            --green-mid:  #1a5c38;
            --green-light:#2d8a57;
            --beige:      #f0cc6a;
            --beige-bg:   #fdf8ef;
            --brown:      #864c20;
            --dark:       #0c1a10;
            --muted:      #5e6b61;
            --border:     rgba(17,59,38,.11);
            --border-soft:rgba(17,59,38,.07);
            --shadow:     0 8px 32px rgba(17,59,38,.10);
            --shadow-lg:  0 24px 64px rgba(17,59,38,.14);
            --card-bg:    rgba(255,255,255,.92);
            --page-bg:    #f4f6f3;

            /* statuts */
            --ok-bg:   rgba(17,59,38,.08); --ok-txt:  #113B26; --ok-dot:#2d8a57;
            --pend-bg: rgba(134,76,32,.09);--pend-txt:#6b3c10; --pend-dot:#a05a1a;
            --deny-bg: rgba(185,28,28,.07);--deny-txt:#7f1d1d; --deny-dot:#dc2626;
        }

        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

        html{scroll-behavior:smooth}

        body{
            font-family:'Plus Jakarta Sans',sans-serif;
            min-height:100vh;
            overflow-x:hidden;
            color:var(--dark);
            background:
                    radial-gradient(ellipse 55% 35% at 0%   0%,  rgba(240,204,106,.13),transparent),
                    radial-gradient(ellipse 40% 40% at 100% 0%,  rgba(17,59,38,.07),transparent),
                    radial-gradient(ellipse 60% 50% at 50% 100%, rgba(17,59,38,.05),transparent),
                    var(--page-bg);
        }

        /* ═══════════════════════════════════════
           LAYOUT SHELL
        ═══════════════════════════════════════ */
        .ps_shell{
            display:grid;
            grid-template-columns:72px 1fr;
            min-height:100vh;
        }

        /* ═══════════════════════════════════════
           SIDEBAR
        ═══════════════════════════════════════ */
        .ps_sidebar{
            position:sticky;top:0;
            height:100vh;
            background:white;
            border-right:1px solid var(--border);
            box-shadow:2px 0 12px rgba(17,59,38,.06);
            display:flex;flex-direction:column;
            align-items:center;
            justify-content:space-between;
            padding:20px 0;
            z-index:100;
            transition:width .3s ease;
        }

        .ps_logo{
            width:48px;height:48px;
            border-radius:16px;
            overflow:hidden;
            background:linear-gradient(135deg,var(--green),var(--green-mid));
            display:grid;place-items:center;
            box-shadow:0 8px 20px rgba(17,59,38,.22);
            flex-shrink:0;
        }

        .ps_logo img{width:32px;height:32px;object-fit:contain}
        .ps_logo-fallback{color:white;font-weight:900;font-size:16px;letter-spacing:-.04em}

        .ps_dock{
            display:flex;flex-direction:column;
            gap:6px;width:100%;
            align-items:center;
            padding:0 10px;
        }

        .ps_dock-btn{
            width:52px;height:52px;
            border-radius:16px;
            background:none;
            border:none;
            color:var(--muted);
            display:grid;place-items:center;
            cursor:pointer;
            transition:.22s ease;
            position:relative;
        }

        .ps_dock-btn span{font-size:22px}

        .ps_dock-btn:hover{
            background:rgba(17,59,38,.07);
            color:var(--green);
        }

        .ps_dock-btn.ps_active{
            background:linear-gradient(135deg,var(--green-mid),var(--green));
            color:white;
            box-shadow:0 10px 24px rgba(17,59,38,.22);
        }

        /* tooltip sidebar */
        .ps_dock-btn::after{
            content:attr(data-tip);
            position:absolute;
            left:calc(100% + 12px);
            top:50%;transform:translateY(-50%);
            background:var(--dark);
            color:white;
            font-size:11px;font-weight:700;
            padding:5px 10px;border-radius:8px;
            white-space:nowrap;
            opacity:0;pointer-events:none;
            transition:.18s;z-index:999;
        }

        .ps_dock-btn:hover::after{opacity:1}

        .ps_profile-mini{
            width:42px;height:42px;
            border-radius:13px;
            background:linear-gradient(135deg,var(--beige),#fff0aa);
            border:2px solid rgba(240,204,106,.4);
            display:grid;place-items:center;
            font-weight:900;font-size:14px;
            color:var(--green);
            cursor:pointer;
            transition:.22s;
        }

        .ps_profile-mini:hover{
            box-shadow:0 8px 20px rgba(240,204,106,.3);
            transform:scale(1.05);
        }

        /* ═══════════════════════════════════════
           MAIN
        ═══════════════════════════════════════ */
        .ps_main{
            padding:24px 28px;
            overflow-y:auto;
            min-width:0;
        }

        /* ─── TOPBAR ─── */
        .ps_topbar{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            margin-bottom:24px;
            flex-wrap:wrap;
        }

        .ps_title-block small{
            display:inline-flex;align-items:center;gap:6px;
            color:var(--green-light);
            text-transform:uppercase;letter-spacing:.12em;
            font-size:10px;font-weight:900;
            margin-bottom:6px;
        }

        .ps_title-block h1{
            font-family:'Playfair Display',serif;
            font-size:clamp(24px,3vw,40px);
            line-height:1;
            letter-spacing:-.04em;
            color:var(--dark);
        }

        .ps_title-block h1 em{
            font-style:italic;
            color:var(--green);
        }

        .ps_top-right{
            display:flex;align-items:center;gap:10px;
            flex-wrap:wrap;
        }

        .ps_search{
            position:relative;
            width:clamp(180px,25vw,280px);
        }

        .ps_search .ps_sico{
            position:absolute;left:13px;top:50%;
            transform:translateY(-50%);
            color:var(--muted);font-size:18px;
            pointer-events:none;
        }

        .ps_search input{
            width:100%;height:44px;
            border-radius:13px;
            border:1.5px solid var(--border);
            background:white;
            padding:0 14px 0 42px;
            outline:none;color:var(--dark);
            font-family:inherit;font-weight:600;font-size:13px;
            transition:.2s;
        }

        .ps_search input:focus{
            border-color:var(--green);
            box-shadow:0 0 0 3px rgba(17,59,38,.08);
        }

        .ps_search input::placeholder{color:#aab5ac}

        .ps_icon-btn{
            width:44px;height:44px;
            border-radius:13px;
            background:white;
            border:1.5px solid var(--border);
            color:var(--muted);
            display:grid;place-items:center;
            cursor:pointer;transition:.2s;
            position:relative;flex-shrink:0;
        }

        .ps_icon-btn:hover{
            border-color:var(--green);
            color:var(--green);
            box-shadow:var(--shadow);
        }

        .ps_notif-dot{
            position:absolute;top:9px;right:9px;
            width:7px;height:7px;border-radius:50%;
            background:var(--beige);
            border:1.5px solid white;
        }

        .ps_user-chip{
            display:flex;align-items:center;gap:9px;
            padding:6px 12px 6px 6px;
            border-radius:13px;
            background:white;
            border:1.5px solid var(--border);
            cursor:pointer;transition:.2s;
            flex-shrink:0;
        }

        .ps_user-chip:hover{
            border-color:var(--green);
            box-shadow:var(--shadow);
        }

        .ps_user-avatar{
            width:32px;height:32px;border-radius:10px;
            background:linear-gradient(135deg,var(--green),var(--green-mid));
            display:grid;place-items:center;
            color:white;font-weight:900;font-size:12px;
        }

        .ps_user-name{font-size:12px;font-weight:800;color:var(--dark)}
        .ps_user-role{font-size:10px;font-weight:600;color:var(--muted)}

        .ps_logout-btn{
            height:44px;padding:0 14px;
            border-radius:13px;
            border:1.5px solid rgba(185,28,28,.2);
            background:rgba(185,28,28,.05);
            color:#b91c1c;
            font-family:inherit;font-weight:800;font-size:12px;
            cursor:pointer;transition:.2s;
            display:inline-flex;align-items:center;gap:6px;
            flex-shrink:0;
            white-space:nowrap;
        }

        .ps_logout-btn:hover{
            background:rgba(185,28,28,.1);
            border-color:rgba(185,28,28,.35);
        }

        /* ─── HERO ─── */
        .ps_hero{
            display:grid;
            grid-template-columns:1.2fr .8fr;
            gap:16px;
            margin-bottom:20px;
        }

        /* ─── HERO CARD AVEC DIAPORAMA ─── */
        .ps_hero-card{
            position:relative;overflow:hidden;
            border-radius:28px;
            padding:32px 36px 60px;
            min-height:250px;
            box-shadow:0 24px 64px rgba(17,59,38,.22);
            display:flex;flex-direction:column;justify-content:flex-end;
        }

        /* slides fond */
        .ps_hero-slide{
            position:absolute;inset:0;
            background-size:cover;background-position:center;
            opacity:0;
            transition:opacity 1.2s ease;
            z-index:0;
        }
        .ps_hero-slide.ps_hs-active{opacity:1}

        /* overlay dégradé fort lisibilité */
        .ps_hero-overlay{
            position:absolute;inset:0;z-index:1;
            background:linear-gradient(160deg,
            rgba(6,16,10,.82) 0%,
            rgba(6,16,10,.52) 50%,
            rgba(6,16,10,.80) 100%);
        }

        /* bordure intérieure premium */
        .ps_hero-overlay::before{
            content:"";position:absolute;inset:14px;
            border-radius:18px;
            border:1px solid rgba(255,255,255,.08);
            pointer-events:none;
        }

        /* orbe lumineuse */
        .ps_hero-orb{
            position:absolute;z-index:2;
            width:300px;height:300px;border-radius:50%;
            background:radial-gradient(circle,rgba(240,204,106,.24),transparent 68%);
            right:-110px;top:-110px;pointer-events:none;
        }

        /* contenu texte */
        .ps_hero-content{position:relative;z-index:3}

        /* indicateurs dot */
        .ps_hs-dots{
            position:absolute;
            bottom:22px;right:24px;z-index:4;
            display:flex;gap:6px;align-items:center;
        }

        .ps_hs-dot{
            width:7px;height:7px;border-radius:50%;
            background:rgba(255,255,255,.3);
            cursor:pointer;transition:all .35s ease;
            border:none;padding:0;
        }

        .ps_hs-dot.ps_hs-active{
            background:var(--beige);
            width:22px;border-radius:4px;
            box-shadow:0 0 8px rgba(240,204,106,.55);
        }

        /* badge entité courant (bas gauche) */
        .ps_hs-ent{
            position:absolute;
            bottom:22px;left:24px;z-index:4;
            display:flex;align-items:center;gap:8px;
        }

        .ps_hs-ent img{
            width:28px;height:28px;border-radius:50%;
            background:white;padding:2px;object-fit:contain;
            box-shadow:0 4px 10px rgba(0,0,0,.28);
        }

        .ps_hs-ent-info{line-height:1.25}
        .ps_hs-ent-name{font-size:11px;font-weight:800;color:var(--beige);letter-spacing:.06em;display:block}
        .ps_hs-ent-desc{font-size:9px;font-weight:600;color:rgba(255,255,255,.5);display:block}

        /* kicker */
        .ps_hero-kicker{
            display:inline-flex;align-items:center;gap:6px;
            padding:5px 13px;border-radius:999px;
            background:rgba(240,204,106,.16);
            border:1px solid rgba(240,204,106,.28);
            color:var(--beige);
            font-size:10px;font-weight:900;letter-spacing:.1em;
            text-transform:uppercase;
            margin-bottom:14px;
        }

        .ps_hero-card h2{
            font-family:'Playfair Display',serif;
            font-size:clamp(18px,2.4vw,30px);
            line-height:1.1;letter-spacing:-.04em;
            color:white;
            margin-bottom:10px;max-width:460px;
        }

        .ps_hero-card h2 em{font-style:italic;color:var(--beige)}

        .ps_hero-card p{
            color:rgba(255,255,255,.68);
            font-size:13px;line-height:1.75;
            max-width:420px;margin-bottom:22px;
        }

        .ps_hero-actions{
            display:flex;align-items:center;gap:10px;
            flex-wrap:wrap;
        }

        .ps_hero-btn{
            height:40px;padding:0 18px;
            border-radius:12px;border:0;
            display:inline-flex;align-items:center;gap:8px;
            font-family:inherit;font-weight:800;font-size:12px;
            cursor:pointer;transition:.22s;
        }

        .ps_hero-btn.ps_primary{
            background:linear-gradient(135deg,var(--beige),#fff0aa);
            color:var(--green);
            box-shadow:0 10px 24px rgba(240,204,106,.28);
        }

        .ps_hero-btn.ps_primary:hover{
            transform:translateY(-2px);
            box-shadow:0 16px 32px rgba(240,204,106,.40);
        }

        .ps_hero-btn.ps_ghost{
            background:rgba(255,255,255,.12);
            border:1px solid rgba(255,255,255,.2);
            color:white;
        }

        .ps_hero-btn.ps_ghost:hover{background:rgba(255,255,255,.2)}

        /* ─── STATS ─── */
        .ps_stats-grid{
            display:grid;
            grid-template-columns:repeat(2,1fr);
            gap:12px;
        }

        .ps_stat{
            position:relative;overflow:hidden;
            border-radius:20px;
            padding:18px 20px;
            background:white;
            border:1.5px solid var(--border);
            box-shadow:var(--shadow);
            transition:.22s;
        }

        .ps_stat:hover{
            border-color:rgba(17,59,38,.2);
            box-shadow:0 12px 32px rgba(17,59,38,.10);
            transform:translateY(-2px);
        }

        .ps_stat::before{
            content:"";
            position:absolute;
            width:80px;height:80px;border-radius:50%;
            right:-20px;top:-20px;opacity:.12;
        }

        .ps_stat.ps_s1::before{background:var(--green)}
        .ps_stat.ps_s2::before{background:#2d8a57}
        .ps_stat.ps_s3::before{background:#a05a1a}
        .ps_stat.ps_s4::before{background:#dc2626}

        .ps_stat strong{
            display:block;
            font-family:'Playfair Display',serif;
            font-size:36px;font-weight:900;
            line-height:1;
            color:var(--green);
            margin-bottom:5px;
            letter-spacing:-.05em;
        }

        .ps_stat small{color:var(--muted);font-size:12px;font-weight:700}

        /* ─── INFO BANNER ─── */
        .ps_info-banner{
            display:flex;align-items:center;gap:12px;
            padding:14px 18px;
            border-radius:16px;
            background:rgba(240,204,106,.12);
            border:1.5px solid rgba(240,204,106,.28);
            margin-bottom:20px;
        }

        .ps_info-banner .ps_ib-icon{
            width:36px;height:36px;border-radius:10px;
            background:rgba(240,204,106,.2);
            display:grid;place-items:center;
            color:var(--brown);flex-shrink:0;
        }

        .ps_info-banner p{
            font-size:12px;font-weight:600;
            color:#5c3e08;line-height:1.5;
        }

        .ps_info-banner p strong{color:var(--brown)}

        /* ─── FILTRES ─── */
        .ps_filters{
            display:flex;align-items:center;
            justify-content:space-between;
            gap:12px;
            margin-bottom:18px;
            flex-wrap:wrap;
        }

        .ps_tabs{
            display:flex;gap:6px;
            flex-wrap:wrap;
        }

        .ps_tab{
            height:38px;padding:0 14px;
            border-radius:999px;
            border:1.5px solid var(--border);
            background:white;
            color:var(--muted);
            font-family:inherit;font-weight:800;font-size:12px;
            cursor:pointer;transition:.2s ease;
            display:inline-flex;align-items:center;gap:6px;
            box-shadow:0 2px 6px rgba(17,59,38,.04);
        }

        .ps_tab:hover{
            border-color:rgba(17,59,38,.22);
            color:var(--green);
            background:rgba(17,59,38,.04);
        }

        .ps_tab.ps_active{
            background:linear-gradient(135deg,var(--green-mid),var(--green));
            color:white;border-color:transparent;
            box-shadow:0 8px 20px rgba(17,59,38,.18);
        }

        .ps_tab .ps_count{
            background:rgba(17,59,38,.1);
            color:var(--green);
            border-radius:999px;
            padding:1px 7px;font-size:10px;font-weight:900;
        }

        .ps_tab.ps_active .ps_count{
            background:rgba(255,255,255,.2);
            color:white;
        }

        .ps_filter-right{
            display:flex;align-items:center;gap:8px;
            flex-wrap:wrap;
        }

        .ps_select{
            height:38px;padding:0 12px;
            border-radius:11px;
            border:1.5px solid var(--border);
            background:white;
            color:var(--dark);
            font-family:inherit;font-weight:700;font-size:12px;
            outline:none;cursor:pointer;
            box-shadow:0 2px 6px rgba(17,59,38,.04);
            transition:.2s;
        }

        .ps_select:focus{border-color:var(--green)}

        .ps_view-toggle{
            display:flex;gap:3px;
            background:white;
            border:1.5px solid var(--border);
            border-radius:11px;
            padding:3px;
            box-shadow:0 2px 6px rgba(17,59,38,.04);
        }

        .ps_vt-btn{
            width:30px;height:30px;
            border-radius:8px;
            background:none;border:none;
            color:var(--muted);
            display:grid;place-items:center;
            cursor:pointer;transition:.18s;
        }

        .ps_vt-btn.ps_active,
        .ps_vt-btn:hover{
            background:rgba(17,59,38,.08);
            color:var(--green);
        }

        /* ─── SECTION LABEL ─── */
        .ps_section-label{
            display:flex;align-items:center;gap:10px;
            margin-bottom:14px;
        }

        .ps_section-label h2{
            font-family:'Playfair Display',serif;
            font-size:20px;letter-spacing:-.04em;
            color:var(--dark);
        }

        .ps_section-label span{
            font-size:11px;font-weight:700;
            color:var(--muted);
            padding:3px 10px;border-radius:999px;
            background:rgba(17,59,38,.07);
            border:1px solid var(--border);
        }

        /* ═══════════════════════════════════════
           GRILLE APPLICATIONS — redesign
        ═══════════════════════════════════════ */
        .ps_apps-grid{
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(290px,1fr));
            gap:16px;
        }

        /* ─── CARTE ─── */
        .ps_app{
            position:relative;
            border-radius:24px;
            background:white;
            border:1.5px solid var(--border);
            box-shadow:0 2px 12px rgba(17,59,38,.06);
            transition:.28s cubic-bezier(.22,1,.36,1);
            overflow:hidden;
            display:flex;
            flex-direction:column;
        }

        .ps_app:hover{
            transform:translateY(-5px);
            box-shadow:0 16px 48px rgba(17,59,38,.12);
            border-color:rgba(17,59,38,.16);
        }

        /* ─── BANDE COULEUR HAUT ─── */
        .ps_app-stripe{
            height:4px;
            width:100%;
            flex-shrink:0;
        }
        .ps_app.ps_authorized .ps_app-stripe{background:linear-gradient(90deg,var(--green),var(--green-light))}
        .ps_app.ps_pending    .ps_app-stripe{background:linear-gradient(90deg,#c47a2e,#e8a24a)}
        .ps_app.ps_denied     .ps_app-stripe{background:linear-gradient(90deg,#c0392b,#e74c3c)}

        /* ─── CORPS CARTE ─── */
        .ps_app-body{
            padding:20px 22px 16px;
            flex:1;
            display:flex;
            flex-direction:column;
            gap:14px;
        }

        /* ligne icône + statut */
        .ps_app-top{
            display:flex;align-items:center;
            justify-content:space-between;
            gap:10px;
        }

        /* ICÔNE */
        .ps_app-icon{
            width:52px;height:52px;
            border-radius:16px;
            display:grid;place-items:center;
            flex-shrink:0;
            position:relative;
        }

        .ps_app-icon span{font-size:26px}

        /* halo subtil */
        .ps_app-icon::after{
            content:"";
            position:absolute;inset:-4px;
            border-radius:20px;
            opacity:.15;
            transition:.25s;
        }
        .ps_app:hover .ps_app-icon::after{opacity:.28}

        .ps_app-icon.ps_uahb {background:linear-gradient(135deg,#1a5c38,var(--green));color:white;box-shadow:0 8px 18px rgba(17,59,38,.22)}
        .ps_app-icon.ps_uahb::after{background:var(--green)}
        .ps_app-icon.ps_cmjlf{background:linear-gradient(135deg,#a85f28,#6b3714);color:white;box-shadow:0 8px 18px rgba(134,76,32,.22)}
        .ps_app-icon.ps_cmjlf::after{background:#a85f28}
        .ps_app-icon.ps_ctd  {background:linear-gradient(135deg,#2d8a57,#1a5c38);color:white;box-shadow:0 8px 18px rgba(45,138,87,.2)}
        .ps_app-icon.ps_ctd::after{background:#2d8a57}
        .ps_app-icon.ps_gsjlf  {background:linear-gradient(135deg,#2c5364,#203a43);color:white;box-shadow:0 8px 18px rgba(44,83,100,.2)}
        .ps_app-icon.ps_gsjlf::after{background:#2c5364}

        /* BADGE STATUT */
        .ps_status{
            padding:5px 12px;
            border-radius:999px;
            font-size:10px;font-weight:800;
            text-transform:uppercase;letter-spacing:.06em;
            display:inline-flex;align-items:center;gap:5px;
            flex-shrink:0;
        }
        .ps_status::before{
            content:"";width:5px;height:5px;border-radius:50%;flex-shrink:0;
        }
        .ps_status.ps_authorized{
            background:rgba(17,59,38,.08);color:#113B26;
            border:1px solid rgba(17,59,38,.12);
        }
        .ps_status.ps_authorized::before{background:#2d8a57}
        .ps_status.ps_pending{
            background:rgba(196,122,46,.1);color:#7a4210;
            border:1px solid rgba(196,122,46,.18);
        }
        .ps_status.ps_pending::before{background:#c47a2e}
        .ps_status.ps_denied{
            background:rgba(192,57,43,.08);color:#7a1c1c;
            border:1px solid rgba(192,57,43,.14);
        }
        .ps_status.ps_denied::before{background:#c0392b}

        /* ─── TEXTES ─── */
        .ps_app-meta{flex:1;min-width:0}

        .ps_app h3{
            font-family:'Playfair Display',serif;
            font-size:18px;
            font-weight:900;
            letter-spacing:-.035em;
            color:var(--dark);
            margin-bottom:5px;
            line-height:1.15;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }

        .ps_app-desc{
            color:var(--muted);
            font-size:12px;line-height:1.65;
            font-weight:500;
            display:-webkit-box;
            -webkit-line-clamp:2;
            -webkit-box-orient:vertical;
            overflow:hidden;
        }

        /* ─── TAGS ─── */
        .ps_tags{display:flex;flex-wrap:wrap;gap:5px}

        .ps_tag{
            padding:3px 10px;
            border-radius:6px;
            background:rgba(17,59,38,.06);
            border:1px solid rgba(17,59,38,.09);
            color:var(--green);
            font-size:10px;font-weight:800;
            letter-spacing:.02em;
            transition:.2s;
        }
        .ps_app:hover .ps_tag{background:rgba(17,59,38,.1)}

        /* ─── SÉPARATEUR ─── */
        .ps_app-sep{
            height:1px;
            background:var(--border-soft);
            margin:0 22px;
            flex-shrink:0;
        }

        /* ─── PIED DE CARTE ─── */
        .ps_app-footer{
            padding:14px 22px 18px;
            display:flex;
            align-items:center;
            gap:8px;
            flex-shrink:0;
        }

        /* bouton principal */
        .ps_open-btn,
        .ps_pending-btn,
        .ps_deny-btn{
            flex:1;
            height:38px;
            border-radius:11px;border:0;
            font-family:inherit;font-weight:800;font-size:12px;
            cursor:pointer;transition:.22s;
            display:inline-flex;align-items:center;
            justify-content:center;gap:7px;
        }

        .ps_open-btn{
            background:linear-gradient(135deg,var(--green-mid),var(--green));
            color:white;
            box-shadow:0 6px 16px rgba(17,59,38,.18);
        }
        .ps_open-btn:hover{
            box-shadow:0 10px 24px rgba(17,59,38,.28);
            transform:translateY(-1px);
        }

        .ps_pending-btn{
            background:rgba(196,122,46,.08);
            border:1.5px solid rgba(196,122,46,.2);
            color:#7a4210;
        }
        .ps_pending-btn:hover{background:rgba(196,122,46,.15)}

        .ps_deny-btn{
            background:rgba(192,57,43,.06);
            border:1.5px solid rgba(192,57,43,.16);
            color:#7a1c1c;
        }
        .ps_deny-btn:hover{background:rgba(192,57,43,.12)}

        /* bouton icône secondaire */
        .ps_action-icon-btn{
            width:38px;height:38px;
            border-radius:11px;border:0;
            background:rgba(17,59,38,.05);
            border:1.5px solid var(--border);
            color:var(--muted);
            display:grid;place-items:center;
            cursor:pointer;transition:.2s;flex-shrink:0;
        }
        .ps_action-icon-btn:hover{
            background:rgba(17,59,38,.09);
            color:var(--green);
            border-color:rgba(17,59,38,.18);
        }

        /* ─── EMPTY STATE ─── */
        /* ─── EMPTY STATE ─── */
        .ps_empty{
            grid-column:1/-1;
            text-align:center;padding:60px 20px;
            color:var(--muted);
        }

        .ps_empty span.material-symbols-outlined{
            font-size:44px;display:block;
            margin-bottom:12px;opacity:.3;
            color:var(--green);
        }

        .ps_empty p{font-size:14px;font-weight:600}

        /* ═══════════════════════════════════════
           MOBILE NAV (barre bas sur mobile)
        ═══════════════════════════════════════ */
        .ps_mobile-nav{
            display:none;
            position:fixed;bottom:0;left:0;right:0;
            height:64px;
            background:white;
            border-top:1px solid var(--border);
            box-shadow:0 -4px 20px rgba(17,59,38,.08);
            z-index:200;
            padding:0 8px;
            align-items:center;
            justify-content:space-around;
        }

        .ps_mn-btn{
            display:flex;flex-direction:column;
            align-items:center;gap:2px;
            width:52px;
            background:none;border:none;
            color:var(--muted);
            cursor:pointer;transition:.2s;
            font-family:inherit;font-size:9px;font-weight:700;
        }

        .ps_mn-btn span.material-symbols-outlined{font-size:22px}

        .ps_mn-btn.ps_active{color:var(--green)}

        /* ═══════════════════════════════════════
           ANIMATIONS
        ═══════════════════════════════════════ */
        @keyframes ps_fadeUp{
            from{opacity:0;transform:translateY(16px)}
            to  {opacity:1;transform:translateY(0)}
        }

        .ps_topbar      {animation:ps_fadeUp .4s ease both}
        .ps_hero        {animation:ps_fadeUp .4s .06s ease both}
        .ps_info-banner {animation:ps_fadeUp .4s .10s ease both}
        .ps_filters     {animation:ps_fadeUp .4s .14s ease both}
        .ps_apps-grid   {animation:ps_fadeUp .4s .18s ease both}

        /* ═══════════════════════════════════════
           RESPONSIVE
        ═══════════════════════════════════════ */

        /* Tablette large 1200px */
        @media(max-width:1200px){
            .ps_hero{grid-template-columns:1fr .8fr}
        }

        /* Tablette 900px */
        @media(max-width:900px){
            .ps_shell{grid-template-columns:1fr}

            .ps_sidebar{
                display:none; /* remplacée par mobile nav */
            }

            .ps_mobile-nav{display:flex}

            .ps_main{
                padding:16px 16px 80px; /* espace pour la nav bas */
            }

            .ps_hero{grid-template-columns:1fr}

            .ps_stats-grid{grid-template-columns:repeat(4,1fr)}
        }

        /* Tablette portrait 700px */
        @media(max-width:700px){
            .ps_stats-grid{grid-template-columns:repeat(2,1fr)}

            .ps_topbar{gap:10px}

            .ps_top-right{
                width:100%;
                justify-content:space-between;
            }

            .ps_search{width:100%;flex:1}

            .ps_user-chip .ps_user-info{display:none} /* cache le texte sur petit écran */

            .ps_logout-btn span:last-child{display:none} /* cache le texte "Déconnexion" */
            .ps_logout-btn{padding:0 12px}

            .ps_title-block h1{font-size:26px}
        }

        /* Mobile 480px */
        @media(max-width:480px){
            .ps_main{padding:14px 12px 80px}

            .ps_hero-card{padding:22px 20px}

            .ps_apps-grid{
                grid-template-columns:1fr;
            }

            .ps_stats-grid{grid-template-columns:repeat(2,1fr)}

            .ps_tabs{gap:4px}
            .ps_tab{font-size:11px;height:34px;padding:0 10px}

            .ps_filters{flex-direction:column;align-items:stretch}
            .ps_filter-right{justify-content:space-between}
        }

        /* Très petit mobile 360px */
        @media(max-width:360px){
            .ps_stat strong{font-size:28px}
            .ps_hero-card h2{font-size:17px}
        }

        /* ═══════════════════════════════════════
           AMÉLIORATIONS CSS GLOBALES
        ═══════════════════════════════════════ */

        /* Sidebar — logo avec halo */
        .ps_logo{
            position:relative;
        }
        .ps_logo::after{
            content:"";
            position:absolute;inset:-4px;
            border-radius:20px;
            background:linear-gradient(135deg,rgba(17,59,38,.12),rgba(240,204,106,.08));
            z-index:-1;
            opacity:0;transition:.3s;
        }
        .ps_logo:hover::after{opacity:1}

        /* Dock btn — indicateur gauche actif */
        .ps_dock-btn.ps_active::before{
            content:"";
            position:absolute;left:-10px;top:50%;transform:translateY(-50%);
            width:4px;height:22px;border-radius:0 4px 4px 0;
            background:var(--green);
        }

        /* Topbar — séparateur visuel */
        .ps_topbar{
            padding-bottom:20px;
            border-bottom:1px solid var(--border-soft);
        }

        /* Stats — amélioration gradient */
        .ps_stat{
            background:
                    radial-gradient(ellipse 80% 60% at 110% 110%, rgba(17,59,38,.04), transparent),
                    white;
        }

        .ps_stat strong{
            background:linear-gradient(135deg,var(--green),var(--green-light));
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
            background-clip:text;
        }

        .ps_stat.ps_s3 strong{
            background:linear-gradient(135deg,#a05a1a,#c47a2e);
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
            background-clip:text;
        }

        .ps_stat.ps_s4 strong{
            background:linear-gradient(135deg,#b91c1c,#dc2626);
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
            background-clip:text;
        }

        /* Stats — icône de contexte */
        .ps_stat::after{
            content:attr(data-icon);
            font-family:'Material Symbols Outlined';
            position:absolute;bottom:16px;right:16px;
            font-size:28px;
            opacity:.07;
            color:var(--green);
            font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24;
        }

        /* Cartes apps — ombre portée subtile au survol */
        .ps_app{
            background:
                    radial-gradient(ellipse 70% 50% at 105% 105%, rgba(17,59,38,.03), transparent),
                    white;
        }

        /* Barre supérieure de la carte plus visible */
        .ps_app.ps_authorized::after{height:4px;left:0;right:0;border-radius:28px 28px 0 0}
        .ps_app.ps_pending::after  {height:4px;left:0;right:0;border-radius:28px 28px 0 0}
        .ps_app.ps_denied::after   {height:4px;left:0;right:0;border-radius:28px 28px 0 0}

        /* Tags — légèrement améliorés */
        .ps_tag{
            transition:.18s;
        }
        .ps_app:hover .ps_tag{
            background:rgba(17,59,38,.11);
        }

        /* Search — focus ring plus visible */
        .ps_search input:focus{
            border-color:var(--green);
            box-shadow:0 0 0 4px rgba(17,59,38,.08),0 4px 12px rgba(17,59,38,.08);
        }

        /* Bouton ouvrir — pulse subtle */
        @keyframes ps_pulse{
            0%,100%{box-shadow:0 8px 20px rgba(17,59,38,.18)}
            50%    {box-shadow:0 12px 28px rgba(17,59,38,.28)}
        }

        .ps_open-btn:hover{
            animation:ps_pulse 1.5s ease-in-out infinite;
        }

        /* Info banner — icône animée */
        .ps_ib-icon .material-symbols-outlined{
            animation:ps_infoSpin 6s linear infinite;
        }
        @keyframes ps_infoSpin{
            0%,90%,100%{transform:rotate(0deg)}
            95%        {transform:rotate(20deg)}
        }

        /* Scroll bar custom */
        .ps_main::-webkit-scrollbar{width:6px}
        .ps_main::-webkit-scrollbar-track{background:transparent}
        .ps_main::-webkit-scrollbar-thumb{
            background:rgba(17,59,38,.15);
            border-radius:999px;
        }
        .ps_main::-webkit-scrollbar-thumb:hover{
            background:rgba(17,59,38,.28);
        }

        /* Section label — ligne décorative */
        .ps_section-label{
            padding-bottom:12px;
            border-bottom:1px solid var(--border-soft);
            margin-bottom:18px;
        }

        /* Apps grid — entrée décalée par carte */
        .ps_app:nth-child(1){animation-delay:.00s}
        .ps_app:nth-child(2){animation-delay:.04s}
        .ps_app:nth-child(3){animation-delay:.08s}
        .ps_app:nth-child(4){animation-delay:.12s}
        .ps_app:nth-child(5){animation-delay:.16s}
        .ps_app:nth-child(6){animation-delay:.20s}
        .ps_app:nth-child(7){animation-delay:.24s}
        .ps_app:nth-child(8){animation-delay:.28s}
        .ps_app:nth-child(9){animation-delay:.32s}
        .ps_app:nth-child(10){animation-delay:.36s}
        .ps_app:nth-child(11){animation-delay:.40s}
        .ps_app:nth-child(12){animation-delay:.44s}

        .ps_app{animation:ps_fadeUp .5s ease both}

        /* Mobile nav — active dot */
        .ps_mn-btn.ps_active span.material-symbols-outlined{
            font-variation-settings:'FILL' 1,'wght' 600,'GRAD' 0,'opsz' 24;
        }

        /* Filtre select — arrow custom */
        .ps_select{
            appearance:none;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%235e6b61'/%3E%3C/svg%3E");
            background-repeat:no-repeat;
            background-position:calc(100% - 12px) center;
            padding-right:32px;
        }


        /* ── Ajustement bannière responsive ── */
        .ps_app-banner{border-radius:22px 22px 0 0}

        /* ── Icône qui déborde légèrement de la bannière ── */
        .ps_app-icon-wrap{display:inline-block}

        /* ── Vue liste — override ── */
        @media(max-width:480px){
            .ps_app-banner{height:72px}
            .ps_app-body{padding:26px 16px 12px}
            .ps_app-footer{padding:0 16px 16px}
            .ps_app h3{font-size:16px}
        }

    </style>
</head>
<body>

<div class="ps_shell">

    <!-- ═══ SIDEBAR (desktop) ═══ -->
    <aside class="ps_sidebar">
        <div class="ps_logo">
            <img src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" alt="GSJLF"
                 onerror="this.outerHTML='<span class=\'ps_logo-fallback\'>GS</span>'">
        </div>

        <div class="ps_dock">
            <button class="ps_dock-btn ps_active" data-tip="Applications">
                <span class="material-symbols-outlined">apps</span>
            </button>
<!--            <button class="ps_dock-btn ps_active" data-tip="Applications">-->
<!--                <span class="material-symbols-outlined">dashboard</span>-->
<!--            </button>-->
            <button class="ps_dock-btn" data-tip="Messagerie">
                <span class="material-symbols-outlined">mail</span>
            </button>
            <button class="ps_dock-btn" data-tip="Documents">
                <span class="material-symbols-outlined">folder_open</span>
            </button>
            <button class="ps_dock-btn" data-tip="Paramètres">
                <span class="material-symbols-outlined">settings</span>
            </button>
        </div>

        <div class="ps_profile-mini" title="Mon profil"><?= $tmpInitiales  ?></div>
    </aside>

    <!-- ═══ MAIN ═══ -->
    <main class="ps_main">

        <!-- TOPBAR -->
        <header class="ps_topbar">
            <div class="ps_title-block">
                <small>
                    <span class="material-symbols-outlined" style="font-size:12px">verified_user</span>
                    ENT GSJLF — Lanceur d'applications
                </small>
                <h1>Vos applications <em>ENT.</em></h1>
            </div>

            <div class="ps_top-right">
                <div class="ps_search">
                    <span class="material-symbols-outlined ps_sico">search</span>
                    <input type="text" id="searchInput" placeholder="Rechercher…" oninput="filterApps()">
                </div>

                <button class="ps_icon-btn" title="Notifications" aria-label="Notifications">
                    <span class="material-symbols-outlined" style="font-size:20px">notifications</span>
                    <div class="ps_notif-dot"></div>
                </button>

                <div class="ps_user-chip" title="Mon profil">
                    <div class="ps_user-avatar">AD</div>
                    <div>
                        <div class="ps_user-name"><?= $tmpPrenom.' '.$tmpNom ?></div>
<!--                        <div class="ps_user-role">Enseignant · UAHB</div>-->
                        <div class="ps_user-role">Entité : <?= $tmpEntite ?></div>

                    </div>
                </div>

                <button class="ps_logout-btn" onclick="window.location.href='/personnel/signout'" aria-label="Déconnexion">
                    <span class="material-symbols-outlined" style="font-size:16px">logout</span>
                    <span>Déconnexion</span>
                </button>
            </div>
        </header>

        <!-- HERO -->
        <section class="ps_hero">
            <div class="ps_hero-card" id="heroCard">
                <!-- Slides fond diaporama -->
                <div class="ps_hero-slide ps_hs-active" style="background-image:url('/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg')"></div>
                <div class="ps_hero-slide" style="background-image:url('/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg')"></div>
                <div class="ps_hero-slide" style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>
                <!-- Overlay sombre -->
                <div class="ps_hero-overlay"></div>
                <!-- Orbe décorative -->
                <div class="ps_hero-orb"></div>
                <!-- Contenu -->
                <div class="ps_hero-content">
                    <div class="ps_hero-kicker">
                        <span class="material-symbols-outlined" style="font-size:11px">rocket_launch</span>
                        Plateforme unifiée GSJLF
                    </div>
                    <h2>Accédez à <em>toutes vos applications</em> depuis un seul espace.</h2>
                    <p>Consultez vos accès autorisés, suivez les demandes en attente et découvrez les outils disponibles au sein du Groupe Scolaire Jean de la Fontaine.</p>
                    <div class="ps_hero-actions">
                        <button class="ps_hero-btn ps_primary" onclick="document.getElementById('appsGrid').scrollIntoView({behavior:'smooth'})">
                            <span class="material-symbols-outlined" style="font-size:16px">open_in_new</span>
                            Voir mes infos
                        </button>
                        <button class="ps_hero-btn ps_ghost">
                            <span class="material-symbols-outlined" style="font-size:16px">help_outline</span>
                            Centre d'aide
                        </button>
                    </div>
                </div>
                <!-- Badge entité courante -->
                <div class="ps_hs-ent" id="heroEntBadge">
                    <img id="heroEntLogo" src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png" alt="">
                    <div class="ps_hs-ent-info">
                        <span class="ps_hs-ent-name" id="heroEntName">UAHB</span>
                        <span class="ps_hs-ent-desc" id="heroEntDesc">Université Amadou Hampâté Bâ</span>
                    </div>
                </div>
                <!-- Dots navigation -->
                <div class="ps_hs-dots" id="herosDots"></div>
            </div>

            <div class="ps_stats-grid">
                <div class="ps_stat ps_s1">
                    <strong id="statTotal"><?= $tmpNbrAppli   ?></strong>
                    <small>Applications</small>
                </div>
                <div class="ps_stat ps_s2">
                    <strong id="statAuth"><?= $tmpNbrAppliAutorisees ?></strong>
                    <small>Autorisées</small>
                </div>
                <div class="ps_stat ps_s3">
                    <strong id="statPend"><?= $tmpNbrAppliEnAttente ?></strong>
                    <small>En attente</small>
                </div>
                <div class="ps_stat ps_s4">
                    <strong id="statDeny"><?= $tmpNbrAppliRefusees ?></strong>
                    <small>Refusées</small>
                </div>
            </div>
        </section>

        <!-- BANNIÈRE INFO -->
        <div class="ps_info-banner">
            <div class="ps_ib-icon">
                <span class="material-symbols-outlined" style="font-size:18px">info</span>
            </div>
            <p>Pour demander l'accès à une application, contactez votre responsable ou le <strong>service CRIAT</strong> à <strong>criat@uahb.sn</strong>.</p>
        </div>

        <!-- FILTRES -->
        <div class="ps_filters">
            <div class="ps_tabs">
                <button class="ps_tab ps_active" onclick="setFilter('all',this)">
                    <span class="material-symbols-outlined" style="font-size:14px">apps</span>
                    Toutes <span class="ps_count" id="cntAll"><?= $tmpNbrAppli   ?></span>
                </button>
                <button class="ps_tab" onclick="setFilter('authorized',this)">
                    <span class="material-symbols-outlined" style="font-size:14px">check_circle</span>
                    Autorisées <span class="ps_count" id="cntAuth"><?= $tmpNbrAppliAutorisees ?></span>
                </button>
                <button class="ps_tab" onclick="setFilter('pending',this)">
                    <span class="material-symbols-outlined" style="font-size:14px">schedule</span>
                    Attente <span class="ps_count" id="cntPend"><?= $tmpNbrAppliEnAttente ?></span>
                </button>
                <button class="ps_tab" onclick="setFilter('denied',this)">
                    <span class="material-symbols-outlined" style="font-size:14px">block</span>
                    Refusées <span class="ps_count" id="cntDeny"><?= $tmpNbrAppliRefusees ?></span>
                </button>
            </div>

            <div class="ps_filter-right">
                <select class="ps_select" onchange="filterApps()" id="entityFilter" aria-label="Filtrer par entité">
                    <option value="">Toutes les entités</option>
                    <option value="uahb">UAHB</option>
                    <option value="cmjlf">CMJLF</option>
                    <option value="ctd">CTD</option>
                    <option value="gsjlf">Groupe</option>
                </select>
                <div class="ps_view-toggle" role="group" aria-label="Mode d'affichage">
                    <button class="ps_vt-btn ps_active" onclick="setView(0,this)" title="Grille" aria-label="Vue grille">
                        <span class="material-symbols-outlined" style="font-size:17px">grid_view</span>
                    </button>
                    <button class="ps_vt-btn" onclick="setView(1,this)" title="Liste" aria-label="Vue liste">
                        <span class="material-symbols-outlined" style="font-size:17px">view_list</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- SECTION LABEL -->
        <div class="ps_section-label">
            <h2>Applications</h2>
            <span id="resultCount"> <?= count($tmpListeApplication) ?>
    <?php if(count($tmpListeApplication) > 1): ?>
        résultats
    <?php else: ?>
        résultat
    <?php endif; ?>
</span>        </div>

        <!-- GRILLE APPLICATIONS -->
        <section class="ps_apps-grid" id="appsGrid">

            <?php

            foreach ($tmpListeApplication as $tmpAppli)
            {

                if($tmpAppli->statutApplication == 0)
                {
                    ?>

                    <article class="ps_app ps_pending" data-status="pending" data-entity="<?= $tmpAppli->entite  ?>" data-name="<?= $tmpAppli->descriptionApplication  ?>">
                        <div class="ps_app-stripe"></div>
                        <div class="ps_app-body">
                            <div class="ps_app-top">
                                <div class="ps_app-icon ps_gsjlf"><?= $tmpAppli->icon  ?></div>
                                <div class="ps_status ps_pending">En attente</div>
                            </div>
                            <div class="ps_app-meta">
                                <h3><?= $tmpAppli->nomApplication  ?></h3>
                                <p class="ps_app-desc"><?= $tmpAppli->descriptionApplication  ?></p>
                            </div>
                            <div class="ps_tags">
                                <?php   foreach ($tmpAppli->hashtags as $tag) { ?>
                                    <div class="ps_tag"><?= $tag ?></div>
                               <?php } ?>
                            </div>
                        </div>
                        <div class="ps_app-sep"></div>
                        <div class="ps_app-footer">
                            <button class="ps_pending-btn" onclick="actionOuvrirApplication(<?= $tmpAppli->numero  ?>)"><span class="material-symbols-outlined" style="font-size:15px">schedule</span>En construction</button>
                            <button class="ps_action-icon-btn" onclick="actionOuvrirApplication(<?= $tmpAppli->numero  ?>)"><span class="material-symbols-outlined" style="font-size:15px">schedule</span></button>
                        </div>
                    </article>


               <?php }else if($tmpAppli->statutApplication == 1)
                {?>

                    <article class="ps_app ps_authorized" data-status="authorized" data-entity="<?= $tmpAppli->entite  ?>" data-name="<?= $tmpAppli->descriptionApplication  ?>">
                        <div class="ps_app-stripe"></div>
                        <div class="ps_app-body">
                            <div class="ps_app-top">
                                <div class="ps_app-icon ps_gsjlf"><?= $tmpAppli->icon  ?></div>
                                <div class="ps_status ps_authorized">Autorisée</div>
                            </div>
                            <div class="ps_app-meta">
                                <h3><?= $tmpAppli->nomApplication  ?></h3>
                                <p class="ps_app-desc"><?= $tmpAppli->descriptionApplication  ?></p>
                            </div>
                            <div class="ps_tags">
                                <?php   foreach ($tmpAppli->hashtags as $tag) { ?>
                                    <div class="ps_tag"><?= $tag ?></div>
                                <?php } ?>
                        </div>
                        </div>
                        <div class="ps_app-sep"></div>
                        <div class="ps_app-footer">
                            <button class="ps_open-btn" onclick="actionOuvrirApplication(<?= $tmpAppli->numero  ?>)"><span class="material-symbols-outlined" style="font-size:15px">open_in_new</span>Ouvrir</button>
                            <button class="ps_action-icon-btn" title="Détails" onclick="actionOuvrirApplication(<?= $tmpAppli->numero  ?>)"><span class="material-symbols-outlined" style="font-size:16px">arrow_forward</span></button>
                        </div>
                    </article>





                <?php }else if($tmpAppli->statutApplication == 2)
                { ?>


                    <article class="ps_app ps_denied" data-status="denied" data-entity="<?= $tmpAppli->entite  ?>" data-name="<?= $tmpAppli->descriptionApplication  ?>">
                        <div class="ps_app-stripe"></div>
                        <div class="ps_app-body">
                            <div class="ps_app-top">
                                <div class="ps_app-icon ps_gsjlf"><span class="material-symbols-outlined">policy</span></div>
                                <div class="ps_status ps_denied">Non autorisée</div>
                            </div>
                            <div class="ps_app-meta">
                                <h3><?= $tmpAppli->nomApplication  ?></h3>
                                <p class="ps_app-desc"><?= $tmpAppli->descriptionApplication  ?></p>
                            </div>
                            <div class="ps_tags">
                                <?php   foreach ($tmpAppli->hashtags as $tag) { ?>
                                    <div class="ps_tag"><?= $tag ?></div>
                                <?php } ?>                            </div>
                        </div>
                        <div class="ps_app-sep"></div>
                        <div class="ps_app-footer">
                            <button class="ps_deny-btn" onclick="actionOuvrirApplication(<?= $tmpAppli->numero  ?>)"><span class="material-symbols-outlined" style="font-size:15px">lock</span>Demander l'accès</button>
                            <button class="ps_action-icon-btn" onclick="actionOuvrirApplication(<?= $tmpAppli->numero  ?>)"><span class="material-symbols-outlined" style="font-size:15px">lock</span></button>
                        </div>
                    </article>



               <?php }

            } ?>

        </section>

    </main>

    <!-- ═══ NAV MOBILE (bas d'écran) ═══ -->
    <nav class="ps_mobile-nav" role="navigation" aria-label="Navigation mobile">
        <button class="ps_mn-btn ps_active">
            <span class="material-symbols-outlined">apps</span>Apps
        </button>
<!--        <button class="ps_mn-btn">-->
<!--            <span class="material-symbols-outlined">dashboard</span>Board-->
<!--        </button>-->
        <button class="ps_mn-btn">
            <span class="material-symbols-outlined">mail</span>Mail
        </button>
        <button class="ps_mn-btn">
            <span class="material-symbols-outlined">folder_open</span>Docs
        </button>
        <button class="ps_mn-btn">
            <span class="material-symbols-outlined">settings</span>Config
        </button>
    </nav>

</div><!-- /ps_shell -->

<script>
    /* ══════════════════════════════════════
       FILTRAGE
    ══════════════════════════════════════ */
    let currentFilter = 'all';

    function setFilter(filter, btn) {
        currentFilter = filter;
        document.querySelectorAll('.ps_tab').forEach(t => t.classList.remove('ps_active'));
        btn.classList.add('ps_active');
        filterApps();
    }

    function filterApps() {
        const query  = document.getElementById('searchInput').value.toLowerCase().trim();
        const entity = document.getElementById('entityFilter').value;
        const cards  = document.querySelectorAll('.ps_app');
        let   count  = 0;

        cards.forEach(card => {
            const ok = (currentFilter === 'all' || card.dataset.status === currentFilter)
                && (!entity || card.dataset.entity === entity)
                && (!query  || card.dataset.name.includes(query) || card.querySelector('h3').textContent.toLowerCase().includes(query));
            card.style.display = ok ? '' : 'none';
            if (ok) count++;
        });

        /* empty state */
        let empty = document.getElementById('ps_emptyState');
        if (count === 0) {
            if (!empty) {
                empty = document.createElement('div');
                empty.id = 'ps_emptyState';
                empty.className = 'ps_empty';
                empty.innerHTML = `<span class="material-symbols-outlined">search_off</span><p>Aucune application ne correspond à votre recherche.</p>`;
                document.getElementById('appsGrid').appendChild(empty);
            }
        } else if (empty) {
            empty.remove();
        }

        document.getElementById('resultCount').textContent = count + ' résultat' + (count > 1 ? 's' : '');
    }

    /* ══════════════════════════════════════
       DOCK
    ══════════════════════════════════════ */
  //  document.querySelectorAll('.ps_dock-btn').forEach(btn => {
      //  btn.addEventListener('click', () => {
         //   document.querySelectorAll('.ps_dock-btn').forEach(b => b.classList.remove('ps_active'));
         //   btn.classList.add('ps_active');
       // });
    //});

   // document.querySelectorAll('.ps_mn-btn').forEach(btn => {
     //   btn.addEventListener('click', () => {
        //    document.querySelectorAll('.ps_mn-btn').forEach(b => b.classList.remove('ps_active'));
           // btn.classList.add('ps_active');
        //});
   // });

    /* ══════════════════════════════════════
       VUE GRILLE / LISTE
    ══════════════════════════════════════ */
    function setView(mode, btn) {
        document.querySelectorAll('.ps_vt-btn').forEach(b => b.classList.remove('ps_active'));
        btn.classList.add('ps_active');
        const grid = document.getElementById('appsGrid');

        if (mode === 1) {
            /* ── VUE LISTE ── */
            grid.style.gridTemplateColumns = '1fr';
            grid.style.gap = '10px';

            document.querySelectorAll('.ps_app').forEach(a => {
                a.style.flexDirection = 'row';
                a.style.alignItems    = 'stretch';
                a.style.borderRadius  = '16px';
                a.style.minHeight     = '90px';
            });

            /* Stripe verticale gauche */
            document.querySelectorAll('.ps_app-stripe').forEach(s => {
                s.style.width      = '4px';
                s.style.height     = 'auto';
                s.style.alignSelf  = 'stretch';
                s.style.flexShrink = '0';
            });

            /* Corps en row */
            document.querySelectorAll('.ps_app-body').forEach(b => {
                b.style.flexDirection = 'row';
                b.style.alignItems    = 'center';
                b.style.gap           = '16px';
                b.style.padding       = '12px 16px';
                b.style.flex          = '1';
            });

            /* Icône fixe à gauche */
            document.querySelectorAll('.ps_app-icon').forEach(ic => {
                ic.style.width        = '44px';
                ic.style.height       = '44px';
                ic.style.flexShrink   = '0';
            });

            /* app-top : icône + badge */
            document.querySelectorAll('.ps_app-top').forEach(t => {
                t.style.flex        = '0 0 auto';
                t.style.flexDirection = 'row';
                t.style.alignItems  = 'center';
                t.style.gap         = '10px';
            });

            /* Bloc texte : titre + desc + tags en colonne */
            document.querySelectorAll('.ps_app-meta').forEach(m => {
                m.style.flex        = '1';
                m.style.minWidth    = '0';
                m.style.display     = 'flex';
                m.style.flexDirection = 'column';
                m.style.gap         = '4px';
            });

            /* Titre */
            document.querySelectorAll('.ps_app h3').forEach(h => {
                h.style.fontSize     = '15px';
                h.style.marginBottom = '0';
                h.style.whiteSpace   = 'nowrap';
                h.style.overflow     = 'hidden';
                h.style.textOverflow = 'ellipsis';
            });

            /* Description visible, 1 ligne */
            document.querySelectorAll('.ps_app-desc').forEach(d => {
                d.style.display            = 'block';
                d.style.fontSize           = '11px';
                d.style.webkitLineClamp    = '1';
                d.style.webkitBoxOrient    = 'vertical';
                d.style.overflow           = 'hidden';
            });

            /* Tags visibles */
            document.querySelectorAll('.ps_tags').forEach(t => {
                t.style.display   = 'flex';
                t.style.flexWrap  = 'nowrap';
                t.style.gap       = '4px';
                t.style.overflow  = 'hidden';
            });

            document.querySelectorAll('.ps_tag').forEach(t => {
                t.style.fontSize   = '9px';
                t.style.padding    = '2px 7px';
                t.style.flexShrink = '0';
            });

            /* Séparateur vertical */
            document.querySelectorAll('.ps_app-sep').forEach(s => {
                s.style.width      = '1px';
                s.style.height     = 'auto';
                s.style.margin     = '12px 0';
                s.style.alignSelf  = 'stretch';
                s.style.flexShrink = '0';
            });

            /* Footer à droite, vertical centré */
            document.querySelectorAll('.ps_app-footer').forEach(f => {
                f.style.padding       = '0 16px';
                f.style.flex          = '0 0 200px';
                f.style.flexDirection = 'row';
                f.style.alignItems    = 'center';
                f.style.gap           = '6px';
            });

            document.querySelectorAll('.ps_open-btn, .ps_pending-btn, .ps_deny-btn').forEach(b => {
                b.style.height     = '34px';
                b.style.fontSize   = '11px';
                b.style.flex       = '1';
                b.style.whiteSpace = 'nowrap';
            });

            document.querySelectorAll('.ps_action-icon-btn').forEach(b => {
                b.style.width  = '34px';
                b.style.height = '34px';
            });

        } else {
            /* ── VUE GRILLE : reset complet ── */
            grid.style.gridTemplateColumns = '';
            grid.style.gap = '';

            document.querySelectorAll('.ps_app').forEach(a => {
                a.style.flexDirection = '';
                a.style.alignItems    = '';
                a.style.borderRadius  = '';
                a.style.minHeight     = '';
            });

            document.querySelectorAll('.ps_app-stripe').forEach(s => {
                s.style.width      = '';
                s.style.height     = '';
                s.style.alignSelf  = '';
                s.style.flexShrink = '';
            });

            document.querySelectorAll('.ps_app-body').forEach(b => {
                b.style.flexDirection = '';
                b.style.alignItems    = '';
                b.style.gap           = '';
                b.style.padding       = '';
                b.style.flex          = '';
            });

            document.querySelectorAll('.ps_app-icon').forEach(ic => {
                ic.style.width      = '';
                ic.style.height     = '';
                ic.style.flexShrink = '';
            });

            document.querySelectorAll('.ps_app-top').forEach(t => {
                t.style.flex          = '';
                t.style.flexDirection = '';
                t.style.alignItems    = '';
                t.style.gap           = '';
            });

            document.querySelectorAll('.ps_app-meta').forEach(m => {
                m.style.flex          = '';
                m.style.minWidth      = '';
                m.style.display       = '';
                m.style.flexDirection = '';
                m.style.gap           = '';
            });

            document.querySelectorAll('.ps_app h3').forEach(h => {
                h.style.fontSize     = '';
                h.style.marginBottom = '';
                h.style.whiteSpace   = '';
                h.style.overflow     = '';
                h.style.textOverflow = '';
            });

            document.querySelectorAll('.ps_app-desc').forEach(d => {
                d.style.display         = '';
                d.style.fontSize        = '';
                d.style.webkitLineClamp = '';
                d.style.webkitBoxOrient = '';
                d.style.overflow        = '';
            });

            document.querySelectorAll('.ps_tags').forEach(t => {
                t.style.display  = '';
                t.style.flexWrap = '';
                t.style.gap      = '';
                t.style.overflow = '';
            });

            document.querySelectorAll('.ps_tag').forEach(t => {
                t.style.fontSize   = '';
                t.style.padding    = '';
                t.style.flexShrink = '';
            });

            document.querySelectorAll('.ps_app-sep').forEach(s => {
                s.style.width     = '';
                s.style.height    = '';
                s.style.margin    = '';
                s.style.alignSelf = '';
                s.style.flexShrink = '';
            });

            document.querySelectorAll('.ps_app-footer').forEach(f => {
                f.style.padding       = '';
                f.style.flex          = '';
                f.style.flexDirection = '';
                f.style.alignItems    = '';
                f.style.gap           = '';
            });

            document.querySelectorAll('.ps_open-btn, .ps_pending-btn, .ps_deny-btn').forEach(b => {
                b.style.height     = '';
                b.style.fontSize   = '';
                b.style.flex       = '';
                b.style.whiteSpace = '';
            });

            document.querySelectorAll('.ps_action-icon-btn').forEach(b => {
                b.style.width  = '';
                b.style.height = '';
            });
        }
    }    /* ══════════════════════════════════════
       DIAPORAMA HERO CARD
    ══════════════════════════════════════ */
    const HERO_SLIDES = [
        {
            logo: '/personnel/ressources/dist_assets/media/logos/logo_uahb.png',
            name: 'UAHB',
            desc: 'Université Amadou Hampâté Bâ'
        },
        {
            logo: '/personnel/ressources/dist_assets/media/logos/logo_cmjlf.png',
            name: 'CMJLF',
            desc: 'Collège Moderne Jean de la Fontaine'
        },
        {
            logo: '/personnel/ressources/dist_assets/media/logos/logo_ctd.png',
            name: 'CTD',
            desc: 'Collège Technique de Dakar'
        }
    ];

    let heroCur = 0, heroTimer = null;
    const heroSlides = document.querySelectorAll('.ps_hero-slide');
    const heroDotsEl = document.getElementById('herosDots');

    // Créer les dots
    HERO_SLIDES.forEach((_, i) => {
        const d = document.createElement('button');
        d.className = 'ps_hs-dot' + (i === 0 ? ' ps_hs-active' : '');
        d.setAttribute('aria-label', 'Slide ' + (i + 1));
        d.addEventListener('click', () => heroGoSlide(i));
        heroDotsEl.appendChild(d);
    });

    function heroGetDots() { return heroDotsEl.querySelectorAll('.ps_hs-dot'); }

    function heroGoSlide(n, restart = true) {
        heroSlides[heroCur].classList.remove('ps_hs-active');
        heroGetDots()[heroCur].classList.remove('ps_hs-active');

        heroCur = (n + HERO_SLIDES.length) % HERO_SLIDES.length;

        heroSlides[heroCur].classList.add('ps_hs-active');
        heroGetDots()[heroCur].classList.add('ps_hs-active');

        // Fade badge entité
        const logo = document.getElementById('heroEntLogo');
        const name = document.getElementById('heroEntName');
        const desc = document.getElementById('heroEntDesc');
        const badge = document.getElementById('heroEntBadge');

        badge.style.opacity = '0';
        badge.style.transition = 'opacity .3s';

        setTimeout(() => {
            logo.src             = HERO_SLIDES[heroCur].logo;
            name.textContent     = HERO_SLIDES[heroCur].name;
            desc.textContent     = HERO_SLIDES[heroCur].desc;
            badge.style.opacity  = '1';
        }, 300);

        if (restart) {
            clearInterval(heroTimer);
            heroTimer = setInterval(() => heroGoSlide(heroCur + 1, false), 5000);
        }
    }

    heroTimer = setInterval(() => heroGoSlide(heroCur + 1, false), 5000);
</script>

<script src="/personnel/scripts.bundle.12.js"></script>

</body>
</html>
