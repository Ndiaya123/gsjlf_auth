<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Produits d'investissement — répartition — ENT GSJLF</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"/>
    <link href="/personnel/ressources/dist_assets/css/style_basi_37.css" rel="stylesheet" type="text/css"/>

    <style>
        /* Absentes de style_basi_35.css — page rendue autonome (ne dépend
           plus de la feuille externe pour ces classes de base, communes à
           tout le module Investissement). */
        #dga-loader{position:fixed;inset:0;z-index:9999;pointer-events:all}
        @keyframes dga-spin{to{transform:rotate(360deg)}}
        @keyframes dga-dash{0%{stroke-dashoffset:80}50%{stroke-dashoffset:20}100%{stroke-dashoffset:80}}
        #dga-loader .dga-loader-bg{position:absolute;inset:0;background:rgba(10,40,25,.45);backdrop-filter:blur(4px)}
        #dga-loader .dga-loader-box{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:16px;padding:2rem 2.5rem;display:flex;flex-direction:column;align-items:center;gap:.9rem;box-shadow:0 20px 60px rgba(0,0,0,.18);min-width:190px}
        #dga-loader .dga-loader-box p{margin:0;font-size:.82rem;font-weight:700;color:#1a7a5e}
        #dga-loader .dga-loader-spin{width:40px;height:40px;animation:dga-spin .85s linear infinite}
        #dga-loader .dga-loader-spin circle{stroke:#1a7a5e;stroke-dasharray:80;stroke-dashoffset:55;stroke-linecap:round;fill:none;animation:dga-dash 1.4s ease-in-out infinite}

        .dga-hero{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem}
        .dga-hero-title h1{font-size:1.35rem;font-weight:800;color:#111827;margin:0;display:flex;align-items:center;gap:.55rem}
        .dga-hero-title h1 svg{background:#d1fae5;color:#065f46;border-radius:9px;padding:.4rem;width:20px !important;height:20px !important;box-sizing:content-box}
        .dga-hero-title p{font-size:.8rem;color:#9ca3af;margin:.2rem 0 0}

        .dga-card{background:#fff;border-radius:14px;border:1px solid #e9ecef;box-shadow:0 1px 4px rgba(0,0,0,.05);overflow:hidden}
        .dga-card-head{padding:.9rem 1.35rem;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem}
        .dga-card-title{font-size:.88rem;font-weight:700;color:#111827;display:flex;align-items:center;gap:.45rem}
        .dga-card-title svg{color:#1a7a5e}

        .dga-stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.85rem;margin-bottom:1.25rem}
        @media (max-width:992px){.dga-stats-grid{grid-template-columns:1fr 1fr}}
        @media (max-width:576px){.dga-stats-grid{grid-template-columns:1fr}}
        .dga-stat-card{background:#fff;border-radius:14px;border:1px solid #e9ecef;padding:1.15rem 1.3rem;display:flex;align-items:center;gap:1rem;position:relative;overflow:hidden}
        .dga-stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:#1a7a5e}
        .dga-stat-icon{width:42px;height:42px;border-radius:12px;background:#d1fae5;color:#065f46;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .dga-stat-lbl{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:.3rem}
        .dga-stat-val{font-size:1.4rem;font-weight:900;color:#111827;font-variant-numeric:tabular-nums}
        .dga-sc-a-sortir::before{background:#dc2626}
        .dga-sc-a-sortir .dga-stat-icon{background:#fee2e2;color:#991b1b}
        .dga-sc-terminee::before{background:#10b981}
        .dga-sc-terminee .dga-stat-icon{background:#d1fae5;color:#047857}

        .dga-badge-statut{display:inline-flex;align-items:center;padding:.25rem .65rem;border-radius:99px;font-size:.68rem;font-weight:800;text-transform:uppercase}

        .dga-modal .modal-dialog{max-width:720px}
        .dga-modal .modal-content{border-radius:16px !important;border:none !important;box-shadow:0 24px 64px rgba(0,0,0,.18) !important;overflow:hidden}
        .dga-modal .modal-header{background:linear-gradient(135deg,#064e3b,#1a7a5e) !important;border:none !important;padding:1.25rem 1.5rem !important}
        .dga-modal .modal-header h2{color:#fff !important;font-size:1rem !important;font-weight:800 !important;margin:0 !important}
        .dga-modal .modal-body{padding:1.35rem 1.5rem !important;max-height:70vh;overflow-y:auto}
        .dga-actions{display:flex;justify-content:flex-end;gap:.6rem;padding:1rem 1.35rem;border-top:1px solid #f3f4f6;background:#fafafa}
        .dga-cancel{padding:.55rem 1.15rem;border-radius:8px;font-size:.82rem;font-weight:600;background:#fff;color:#6b7280;border:1.5px solid #e5e7eb;cursor:pointer}
        .dga-cancel:hover{border-color:#9ca3af;color:#374151}

        .dga-pill-rubrique{display:inline-flex;padding:.2rem .55rem;border-radius:99px;font-size:.7rem;font-weight:600;background:#f3f4f6;color:#6b7280}
        .dga-cell-muted{color:#9ca3af}
        .dga-qte-recue{font-weight:700;color:#374151}
        .dga-qte-sortie{color:#6b7280}
        .dga-qte-reservee{color:#b45309}
        .dga-qte-dispo{font-weight:800;color:#1a7a5e;font-variant-numeric:tabular-nums}
        .dga-btn-detail-produit{background:#eff6ff;color:#1d4ed8;border:1.5px solid #dbeafe;border-radius:7px;padding:.35rem .7rem;font-size:.72rem;font-weight:700;cursor:pointer}
        .dga-btn-detail-produit:hover{background:#1d4ed8;color:#fff}
        table.dga-table-produits{width:100%;border-collapse:collapse;font-size:.83rem;margin-bottom:.5rem}
        table.dga-table-produits thead th{background:#f8f9fa;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;padding:.6rem .75rem;text-align:left;border-bottom:2px solid #e9ecef}
        table.dga-table-produits tbody td{padding:.55rem .75rem;border-bottom:1px solid #f3f4f6;color:#374151}
        .dga-search{position:relative}
        .dga-search svg{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none}
        .dga-search input{border:1.5px solid #e5e7eb !important;border-radius:9px !important;padding:.55rem .9rem .55rem 2.4rem !important;font-size:.83rem !important;color:#374151 !important;min-width:230px;background:#fff !important;box-shadow:none !important}
        .dga-search input:focus{outline:none;border-color:#1a7a5e !important;box-shadow:0 0 0 3px rgba(26,122,94,.1) !important}
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
                            <div class="header-menu align-items-stretch" data-kt-drawer="true" data-kt-drawer-name="header-menu">
                                <div class="menu menu-lg-rounded menu-column menu-lg-row menu-state-bg menu-title-gray-700 fw-bold my-5 my-lg-0 align-items-stretch" id="kt_header_menu" data-kt-menu="true">
                                    <div class="menu-item me-lg-1">
                                        <a class="menu-link py-3" href="" id="lien_ent"><h3><span class="menu-title">Environnement Numérique de Travail</span></h3></a>
                                    </div>
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

                        <!-- ══ BANDEAU TITRE ══ -->
                        <div class="dga-hero">
                            <div class="dga-hero-title">
                                <h1>
                                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 7h-9m0 10H4a2 2 0 01-2-2V7a2 2 0 012-2h5m0 10v6m0-6l4-4m-4 4l-4-4"/>
                                    </svg>
                                    Produits d'investissement — répartition
                                </h1>
                                <p>Consultation : ce que chaque direction a reçu, sorti et conserve en stock</p>
                            </div>
                        </div>

                        <!-- ══ STATISTIQUES ══ -->
                        <div class="dga-stats-grid">
                            <div class="dga-stat-card">
                                <div class="dga-stat-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/></svg>
                                </div>
                                <div>
                                    <div class="dga-stat-lbl">Produits au catalogue</div>
                                    <div class="dga-stat-val" id="dga-stat-nombre">0</div>
                                </div>
                            </div>
                            <div class="dga-stat-card dga-sc-terminee">
                                <div class="dga-stat-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <div>
                                    <div class="dga-stat-lbl">En service</div>
                                    <div class="dga-stat-val" id="dga-stat-en-service">0</div>
                                </div>
                            </div>
                            <div class="dga-stat-card dga-sc-a-sortir">
                                <div class="dga-stat-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                </div>
                                <div>
                                    <div class="dga-stat-lbl">Hors service</div>
                                    <div class="dga-stat-val" id="dga-stat-hors-service">0</div>
                                </div>
                            </div>
                            <div class="dga-stat-card">
                                <div class="dga-stat-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 7h-9m0 10H4a2 2 0 01-2-2V7a2 2 0 012-2h5m0 10v6m0-6l4-4m-4 4l-4-4"/></svg>
                                </div>
                                <div>
                                    <div class="dga-stat-lbl">Stock global total</div>
                                    <div class="dga-stat-val" id="dga-stat-stock-global">0</div>
                                </div>
                            </div>
                            <div class="dga-stat-card">
                                <div class="dga-stat-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 21h18M5 21V7l8-4v18M13 21V11l6 4v6"/></svg>
                                </div>
                                <div>
                                    <div class="dga-stat-lbl">Directions concernées</div>
                                    <div class="dga-stat-val" id="dga-stat-directions">0</div>
                                </div>
                            </div>
                        </div>

                        <!-- ══ CARTE LISTE ══ -->
                        <div class="dga-card">
                            <div class="dga-card-head">
                                <span class="dga-card-title">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
                                        <line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/>
                                    </svg>
                                    Catalogue des produits
                                </span>
                                <div class="dga-search">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                    </svg>
                                    <input type="text" data-kt-docs-table-filter="search" placeholder="Rechercher un produit"/>
                                </div>
                            </div>

                            <table id="dga-table-repartition" class="display" style="width:100%">
                                <thead>
                                <tr>
                                    <th>Rubrique</th>
                                    <th>Sous-rubrique</th>
                                    <th>Nom du produit</th>
                                    <th>Stock total</th>
                                    <th>Retrait</th>
                                    <th>Date de création</th>
                                    <th>État</th>
                                    <th>Créateur</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <!-- ══ MODALE : Détail — répartition par direction ══ -->
                        <div class="modal fade dga-modal" id="modalDetailRepartition" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2 id="detailRepartitionModalTitre">Répartition par direction</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">
                                        <div id="dgaContenuDetailRepartition"></div>
                                    </div>
                                    <div class="dga-actions">
                                        <button type="button" class="dga-cancel" data-bs-dismiss="modal">Fermer</button>
                                    </div>
                                </div>
                            </div>
                        </div>

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
<script src="/personnel/basi-scripts.bundle.38.js"></script>

</body>
</html>