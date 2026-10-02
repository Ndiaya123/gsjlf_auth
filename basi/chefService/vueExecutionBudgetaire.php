<?php
// Même convention que ligneBudgetInvestissement.php : l'ID du budget arrive
// chiffré (token), jamais en clair — déchiffré côté serveur à chaque appel.
$budgetToken = $_GET['budgetId'] ?? '';
if (empty($budgetToken)) { header('Location: /responsable-financier-accueil'); exit; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Exécution budgétaire — ENT GSJLF</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

    <style>
        /* Page entièrement autonome — pas de dépendance à une feuille style_basi_N.css externe. */
        #veb-loader{position:fixed;inset:0;z-index:9999}
        @keyframes veb-spin{to{transform:rotate(360deg)}}
        @keyframes veb-dash{0%{stroke-dashoffset:80}50%{stroke-dashoffset:20}100%{stroke-dashoffset:80}}
        #veb-loader .veb-loader-bg{position:absolute;inset:0;background:rgba(10,40,25,.45);backdrop-filter:blur(4px)}
        #veb-loader .veb-loader-box{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:16px;padding:2rem 2.5rem;display:flex;flex-direction:column;align-items:center;gap:.9rem;box-shadow:0 20px 60px rgba(0,0,0,.18);min-width:190px}
        #veb-loader .veb-loader-box p{margin:0;font-size:.82rem;font-weight:700;color:#1a7a5e}
        #veb-loader .veb-loader-spin{width:40px;height:40px;animation:veb-spin .85s linear infinite}
        #veb-loader .veb-loader-spin circle{stroke:#1a7a5e;stroke-dasharray:80;stroke-dashoffset:55;stroke-linecap:round;fill:none;animation:veb-dash 1.4s ease-in-out infinite}

        .veb-hero{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.85rem}
        .veb-hero-title h1{font-size:1.4rem;font-weight:800;color:#111827;margin:0;display:flex;align-items:center;gap:.6rem}
        .veb-hero-title h1 svg{background:#d1fae5;color:#065f46;border-radius:10px;padding:.45rem;width:22px !important;height:22px !important;box-sizing:content-box}
        .veb-hero-title p{font-size:.82rem;color:#9ca3af;margin:.25rem 0 0}

        .veb-filtre{display:flex;align-items:center;gap:.6rem;background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;padding:.5rem .7rem}
        .veb-filtre label{font-size:.72rem;font-weight:700;color:#6b7280;white-space:nowrap}
        .veb-filtre select{border:none;background:transparent;font-size:.85rem;font-weight:700;color:#111827;min-width:200px;cursor:pointer}
        .veb-filtre select:focus{outline:none}

        .veb-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:.85rem;margin-bottom:1.5rem}
        @media(max-width:1100px){.veb-stats{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:560px){.veb-stats{grid-template-columns:1fr}}
        .veb-stat{background:#fff;border-radius:14px;border:1px solid #e9ecef;padding:1.1rem 1.25rem;position:relative;overflow:hidden}
        .veb-stat::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
        .veb-stat-total::before{background:#1a7a5e}
        .veb-stat-cours::before{background:#d97706}
        .veb-stat-execute::before{background:#059669}
        .veb-stat-restant::before{background:#6b7280}
        .veb-stat-taux::before{background:#2563eb}
        .veb-stat-lbl{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:.4rem}
        .veb-stat-val{font-size:1.3rem;font-weight:900;color:#111827;font-variant-numeric:tabular-nums}
        .veb-stat-val small{font-size:.7rem;font-weight:700;color:#9ca3af;margin-left:.15rem}

        .veb-card{background:#fff;border-radius:14px;border:1px solid #e9ecef;box-shadow:0 1px 4px rgba(0,0,0,.05);margin-bottom:1.5rem;overflow:hidden}
        .veb-card-head{padding:1rem 1.35rem;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem}
        .veb-card-title{font-size:.9rem;font-weight:700;color:#111827;display:flex;align-items:center;gap:.5rem}
        .veb-card-title svg{color:#1a7a5e}
        .veb-card-body{padding:1.35rem}

        .veb-legende{display:flex;gap:1rem;flex-wrap:wrap;font-size:.72rem;color:#6b7280}
        .veb-legende span{display:inline-flex;align-items:center;gap:.35rem}
        .veb-legende i{width:10px;height:10px;border-radius:3px;display:inline-block}

        #veb-chart-wrap{position:relative;max-height:620px;overflow-y:auto;overflow-x:hidden}
        #veb-chart-vide{text-align:center;color:#9ca3af;font-style:italic;font-size:.85rem;padding:2rem 0;}
        .veb-qte-cell{font-variant-numeric:tabular-nums;color:#374151}
        .veb-qte-livree-ok{color:#059669;font-weight:700}
        .veb-qte-livree-partiel{color:#d97706;font-weight:700}

        table.veb-table{width:100%;border-collapse:collapse;font-size:.82rem}
        table.veb-table thead th{background:#f8f9fa;font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#9ca3af;padding:.6rem .75rem;text-align:left;white-space:nowrap}
        table.veb-table tbody td{padding:.55rem .75rem;border-bottom:1px solid #f3f4f6;color:#374151}

        .veb-badge-etat{display:inline-flex;padding:.22rem .6rem;border-radius:99px;font-size:.68rem;font-weight:700;white-space:nowrap}
        .veb-etat-non_execute{background:#fef2f2;color:#991b1b}
        .veb-etat-partiellement_execute{background:#fef3c7;color:#92400e}
        .veb-etat-entierement_execute{background:#ecfdf5;color:#059669}

        .veb-barre-taux{width:70px;height:6px;background:#f3f4f6;border-radius:99px;overflow:hidden;display:inline-block;vertical-align:middle;margin-right:.4rem}
        .veb-barre-taux-fill{display:block;height:100%;background:#1a7a5e;border-radius:99px;transition:width .3s}
        .veb-montant-execute{color:#059669;font-weight:700}
        .veb-montant-restant{color:#6b7280}
    </style>

    <script>document.documentElement.classList.add('ld-booting');</script>
</head>

<body id="kt_body"
      class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled toolbar-fixed aside-enabled aside-fixed"
      style="--kt-toolbar-height:55px;--kt-toolbar-height-tablet-and-mobile:55px">
<div class="d-flex flex-column flex-root">
    <div class="page d-flex flex-row flex-column-fluid">

        <div id="kt_aside" class="aside aside-light aside-hoverable" data-kt-drawer="true"
             data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}"
             data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}"
             data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_mobile_toggle">
            <div class="aside-logo flex-column-auto text-center" id="kt_aside_logo">
                <a href="/responsable-financier-accueil" style="margin-left:65px;" id="lien_logo1">
                    <img alt="Logo" src="/personnel/ressources/dist_assets/media/logos/1.png" id="logo1" class="h-50px logo"/>
                </a>
            </div>
            <div class="aside-menu flex-column-fluid">
                <div class="hover-scroll-overlay-y my-5 my-lg-5" id="kt_aside_menu_wrapper"
                     data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}"
                     data-kt-scroll-height="auto" data-kt-scroll-wrappers="#kt_aside_menu" data-kt-scroll-offset="0">
                    <div class="menu menu-column menu-title-gray-800 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-500"
                         id="kt_aside_menu" data-kt-menu="true"></div>
                </div>
            </div>
        </div>

        <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
            <div id="kt_header" class="header align-items-stretch">
                <div class="container-fluid d-flex align-items-stretch justify-content-between">
                    <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
                        <a href="/responsable-financier-accueil" class="d-lg-none" id="lien_logo2">
                            <img alt="Logo" src="/personnel/ressources/dist_assets/media/logos/1.png" id="logo2" class="h-30px"/>
                        </a>
                    </div>
                    <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1">
                        <div class="d-flex align-items-stretch" id="kt_header_nav">
                            <div class="menu menu-lg-rounded menu-column menu-lg-row menu-state-bg menu-title-gray-700 fw-bold my-5 my-lg-0 align-items-stretch" id="kt_header_menu" data-kt-menu="true">
                                <div class="menu-item me-lg-1">
                                    <a class="menu-link py-3" href="" id="lien_ent"><h3><span class="menu-title">Environnement Numérique de Travail</span></h3></a>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-stretch flex-shrink-0">
                            <div class="d-flex align-items-center ms-1 ms-lg-3" id="kt_header_user_menu_toggle">
                                <div class="cursor-pointer symbol symbol-30px symbol-md-40px" data-kt-menu-trigger="click" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
                                    <img src="" id="user_photo1"/>
                                </div>
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-primary fw-bold py-4 fs-6 w-275px" data-kt-menu="true">
                                    <div class="menu-item px-3">
                                        <div class="menu-content d-flex align-items-center px-3">
                                            <div class="symbol symbol-50px me-5"><img alt="admin" src="" id="user_photo2"/></div>
                                            <div class="d-flex flex-column">
                                                <div class="fw-bolder d-flex align-items-center fs-5" id="user_pn"></div>
                                                <a href="javascript:void(0)" class="fw-bold text-muted text-hover-primary fs-7" id="user_email"></a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="separator my-2"></div>
                                    <div class="menu-item px-5">
                                        <div class="menu-content px-5">
                                            <label class="form-check form-switch form-check-custom form-check-solid pulse pulse-success" for="kt_user_menu_dark_mode_toggle">
                                                <a href="/signout">
                                                    <input class="form-check-input w-30px h-20px" checked="checked" type="checkbox" value="1" name="mode" id="kt_user_menu_dark_mode_toggle" data-kt-url="/quitter"/>
                                                    <span class="pulse-ring ms-n1"></span>
                                                    <span class="form-check-label text-gray-600 fs-7">se déconnecter</span>
                                                </a>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
                <div class="toolbar" id="kt_toolbar">
                    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack">
                        <div class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0" id="infoAppli"></div>
                    </div>
                </div>
                <div class="post d-flex flex-column-fluid" id="kt_post">
                    <div id="kt_content_container" class="container-xxl">

                        <!-- ══ EN-TÊTE + FILTRE DIRECTION ══ -->
                        <div class="veb-hero">
                            <div class="veb-hero-title">
                                <h1>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                        <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                                    </svg>
                                    Exécution budgétaire — <span id="veb-nom-budget">…</span>
                                </h1>
                                <p id="veb-sous-titre-budget">Chargement…</p>
                            </div>
                        </div>

                        <!-- ══ BUDGET NON VALIDÉ (masqué par défaut) ══ -->
                        <div class="veb-card" id="veb-non-valide" style="display:none;">
                            <div class="veb-card-body" style="text-align:center;padding:2.5rem 1.5rem;color:#6b7280;">
                                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="margin-bottom:.75rem;opacity:.5;">
                                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                                </svg>
                                <p style="font-size:.9rem;font-weight:600;margin:0;">Ce budget n'est pas encore validé par le DFC.</p>
                                <p style="font-size:.8rem;margin:.35rem 0 0;">Le suivi d'exécution ne sera disponible qu'une fois le budget accepté.</p>
                            </div>
                        </div>

                        <!-- ══ CONTENU (masqué tant que la validité n'est pas confirmée) ══ -->
                        <div id="veb-contenu-valide" style="display:none;">

                            <!-- ══ STATISTIQUES ══ -->
                            <div class="veb-stats">
                                <div class="veb-stat veb-stat-total">
                                    <div class="veb-stat-lbl">Budget total</div>
                                    <div class="veb-stat-val" id="veb-stat-total">—</div>
                                </div>
                                <div class="veb-stat veb-stat-cours">
                                    <div class="veb-stat-lbl">En cours d'exécution</div>
                                    <div class="veb-stat-val" id="veb-stat-cours">—</div>
                                </div>
                                <div class="veb-stat veb-stat-execute">
                                    <div class="veb-stat-lbl">Exécuté</div>
                                    <div class="veb-stat-val" id="veb-stat-execute">—</div>
                                </div>
                                <div class="veb-stat veb-stat-restant">
                                    <div class="veb-stat-lbl">Restant à exécuter</div>
                                    <div class="veb-stat-val" id="veb-stat-restant">—</div>
                                </div>
                                <div class="veb-stat veb-stat-taux">
                                    <div class="veb-stat-lbl">Taux global d'exécution</div>
                                    <div class="veb-stat-val" id="veb-stat-taux">—</div>
                                </div>
                            </div>

                            <!-- ══ GRAPHE MENSUEL ══ -->
                            <div class="veb-card">
                                <div class="veb-card-head">
                                <span class="veb-card-title">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                                    Exécution par ligne budgétaire (période prévue)
                                </span>
                                    <div class="veb-legende">
                                        <span><i style="background:#fecaca"></i> Non exécuté</span>
                                        <span><i style="background:#fde68a"></i> Partiellement exécuté</span>
                                        <span><i style="background:#6ee7b7"></i> Entièrement exécuté</span>
                                        <span><i style="background:#059669"></i> Progression</span>
                                    </div>
                                </div>
                                <div class="veb-card-body">
                                    <div id="veb-chart-wrap"><canvas id="veb-chart"></canvas></div>
                                </div>
                            </div>

                            <!-- ══ TABLEAU DE SUIVI ══ -->
                            <div class="veb-card">
                                <div class="veb-card-head">
                                <span class="veb-card-title">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                                    Tableau de suivi des lignes budgétaires
                                </span>
                                </div>
                                <div class="veb-card-body" style="padding:0 1.35rem 1.35rem;">
                                    <table id="veb-table" class="display veb-table" style="width:100%">
                                        <thead>
                                        <tr>
                                            <th>Désignation</th>
                                            <th>Type</th>
                                            <th>Délai prévu</th>
                                            <th>Montant prévu</th>
                                            <th>Exécuté</th>
                                            <th>En cours</th>
                                            <th>Restant</th>
                                            <th>Taux</th>
                                            <th>Qté prévue</th>
                                            <th>Qté commandée</th>
                                            <th>Qté livrée</th>
                                            <th>État</th>
                                        </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                        </div><!-- /#veb-contenu-valide -->

                    </div>
                </div>
            </div>

            <div class="footer py-4 d-flex flex-lg-column" id="kt_footer">
                <div class="container-fluid d-flex flex-column flex-md-row align-items-center justify-content-between">
                    <div class="text-dark order-2 order-md-1">
                        <span class="text-muted fw-bold me-1"><?php echo date('Y'); ?>©</span>
                        <a href="https://univ.uahb.sn/" target="_blank" class="text-gray-800 text-hover-primary">CRIAT</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="kt_scrolltop" class="scrolltop" data-kt-scrolltop="true">
    <span class="svg-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
            <rect opacity="0.5" x="13" y="6" width="13" height="2" rx="1" transform="rotate(90 13 6)" fill="black"/>
            <path d="M12.5657 8.56569L16.75 12.75C17.1642 13.1642 17.8358 13.1642 18.25 12.75C18.6642 12.3358 18.6642 11.6642 18.25 11.25L12.7071 5.70711C12.3166 5.31658 11.6834 5.31658 11.2929 5.70711L5.75 11.25C5.33579 11.6642 5.33579 12.3358 5.75 12.75C6.16421 13.1642 6.83579 13.1642 7.25 12.75L11.4343 8.56569C11.7467 8.25327 12.2533 8.25327 12.5657 8.56569Z" fill="black"/>
        </svg>
    </span>
</div>

<noscript>
    <style>html.ld-booting body > .d-flex.flex-column.flex-root { visibility: visible !important; }</style>
</noscript>

<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script src="/personnel/scripts.bundle.gs.js"></script>
<script>window.CS_BUDGET_TOKEN = <?php echo json_encode($budgetToken); ?>;</script>
<script src="/personnel/basi-scripts.bundle.42.js"></script>

</body>
</html>