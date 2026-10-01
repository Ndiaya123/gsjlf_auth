<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Réception des produits — ENT GSJLF</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"/>
    <link href="/personnel/ressources/dist_assets/css/style_basi_36.css" rel="stylesheet" type="text/css"/>

    <style>
        /* Page entièrement autonome — classes absentes ou incomplètes dans
           style_basi_36.css, vérifiées une par une plutôt que supposées. */
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

        .dga-stat-card{background:#fff;border-radius:14px;border:1px solid #e9ecef;padding:1.15rem 1.3rem;display:flex;align-items:center;gap:1rem;position:relative;overflow:hidden;margin-bottom:1.25rem;max-width:320px}
        .dga-stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:#1a7a5e}
        .dga-stat-icon{width:42px;height:42px;border-radius:12px;background:#d1fae5;color:#065f46;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .dga-stat-lbl{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:.3rem}
        .dga-stat-val{font-size:1.4rem;font-weight:900;color:#111827;font-variant-numeric:tabular-nums}

        .dga-badge-type{display:inline-flex;padding:.2rem .55rem;border-radius:99px;font-size:.68rem;font-weight:700}
        .dga-badge-type-f{background:#dbeafe;color:#1e40af}
        .dga-badge-type-i{background:#ede9fe;color:#5b21b6}

        .dga-btn-avis{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .95rem;border-radius:8px;font-size:.78rem;font-weight:700;background:#1a7a5e;color:#fff;border:none;cursor:pointer;transition:all .18s}
        .dga-btn-avis:hover{background:#145f49}

        .dga-modal .modal-dialog{max-width:620px}
        .dga-modal .modal-content{border-radius:16px !important;border:none !important;box-shadow:0 24px 64px rgba(0,0,0,.18) !important;overflow:hidden}
        .dga-modal .modal-header{background:linear-gradient(135deg,#064e3b,#1a7a5e) !important;border:none !important;padding:1.25rem 1.5rem !important}
        .dga-modal .modal-header h2{color:#fff !important;font-size:1rem !important;font-weight:800 !important;margin:0 !important}
        .dga-modal .modal-body{padding:1.35rem 1.5rem !important;max-height:70vh;overflow-y:auto}
        .dga-actions{display:flex;justify-content:flex-end;gap:.6rem;padding:1rem 1.35rem;border-top:1px solid #f3f4f6;background:#fafafa}
        .dga-cancel{padding:.55rem 1.15rem;border-radius:8px;font-size:.82rem;font-weight:600;background:#fff;color:#6b7280;border:1.5px solid #e5e7eb;cursor:pointer}
        .dga-cancel:hover{border-color:#9ca3af;color:#374151}
        .dga-submit{display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1.35rem;border-radius:8px;font-size:.82rem;font-weight:700;background:#1a7a5e;color:#fff;border:none;cursor:pointer;min-width:170px;justify-content:center}
        .dga-submit:disabled{opacity:.6;cursor:not-allowed}
        .dga-spinner{animation:dga-spin 1s linear infinite}
        .hidden{display:none}
        .dga-erreur-generale{background:#fef2f2;color:#991b1b;border:1.5px solid #fecaca;border-radius:9px;padding:.65rem .9rem;font-size:.82rem;margin-bottom:1rem}

        table.dga-table-produits{width:100%;border-collapse:collapse;font-size:.83rem;margin-bottom:.5rem}
        table.dga-table-produits thead th{background:#f8f9fa;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;padding:.6rem .75rem;text-align:left;border-bottom:2px solid #e9ecef}
        table.dga-table-produits tbody td{padding:.55rem .75rem;border-bottom:1px solid #f3f4f6;color:#374151}
        .dga-qte-fixe{font-weight:800;color:#1a7a5e;font-variant-numeric:tabular-nums}
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
                                        <path d="M20 6L9 17l-5-5"/>
                                    </svg>
                                    Réception des produits
                                </h1>
                                <p>Toutes vos réceptions en attente, Fonctionnement et Investissement, au même endroit</p>
                            </div>
                        </div>

                        <!-- ══ STATISTIQUE ══ -->
                        <div class="dga-stat-card">
                            <div class="dga-stat-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/></svg>
                            </div>
                            <div>
                                <div class="dga-stat-lbl">Bons en attente de confirmation</div>
                                <div class="dga-stat-val" id="dga-stat-nombre">0</div>
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
                                    À confirmer
                                </span>
                            </div>

                            <table id="dga-table-reception" class="display" style="width:100%">
                                <thead>
                                <tr>
                                    <th>N° bon</th>
                                    <th>Type</th>
                                    <th>Expression de besoin</th>
                                    <th>Date de sortie</th>
                                    <th>Lignes à confirmer</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <!-- ══ MODALE : Confirmation de réception ══ -->
                        <div class="modal fade dga-modal" id="modalReception" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2 id="receptionModalTitre">Confirmer la réception</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">
                                        <div id="dgaErreurReception" class="dga-erreur-generale" style="display:none;"></div>
                                        <p style="margin-bottom:1rem;font-size:.85rem;color:#374151;">
                                            Vérifiez que vous avez bien reçu l'ensemble de ces produits avant de confirmer — la quantité correspond exactement à ce qui a été remis par le magasinier.
                                        </p>
                                        <table class="dga-table-produits">
                                            <thead><tr><th>Produit</th><th>Quantité reçue</th></tr></thead>
                                            <tbody id="dgaCorpsReception"></tbody>
                                        </table>
                                    </div>
                                    <div class="dga-actions">
                                        <button type="button" class="dga-cancel" data-bs-dismiss="modal">Annuler</button>
                                        <button type="button" class="dga-submit" id="dgaBtnConfirmerReceptionBon">
                                            <span>Reçu — confirmer</span>
                                            <svg class="dga-spinner hidden" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 019.76 7.8"/></svg>
                                        </button>
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
<script src="/personnel/basi-scripts.bundle.40.js"></script>

</body>
</html>