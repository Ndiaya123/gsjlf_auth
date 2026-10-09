<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Produits à livrer — ENT GSJLF</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"/>
    <?php
    // Numéro de version = date de dernière modification du CSS : le navigateur recharge
    // le fichier dès qu'il change (sinon il garde l'ancienne copie en cache).
    $cssFichier = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/personnel/ressources/dist_assets/css/style_basi_38.css';
    $cssVersion = is_file($cssFichier) ? filemtime($cssFichier) : '38';
    ?>
    <link href="/personnel/ressources/dist_assets/css/style_basi_38.css?v=<?php echo $cssVersion; ?>" rel="stylesheet" type="text/css"/>

    <style>
        /* Styles propres à cette page — filet de sécurité : identiques à ceux de
           style_basi_38.css, répétés ici pour que la page reste correcte même si
           ce fichier est ancien, en cache ou incomplet sur le serveur. */
        #dga-stat-card-livraison{margin-bottom:1.25rem;max-width:320px}
        #dga-stat-card-livraison-invest{margin-bottom:1.25rem;max-width:320px}
        .dga-switch-type{display:inline-flex;background:#f3f4f6;border-radius:99px;padding:.25rem;gap:.15rem;margin-bottom:1.25rem}
        .dga-switch-btn{background:none;border:none;padding:.45rem 1.1rem;border-radius:99px;font-size:.8rem;font-weight:600;color:#6b7280;cursor:pointer;transition:all .15s}
        .dga-switch-btn:hover{color:#111827}
        .dga-switch-active{background:#fff;color:#1a7a5e;box-shadow:0 1px 3px rgba(0,0,0,.08)}
        .dga-btn-avis{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .95rem;border-radius:8px;font-size:.78rem;font-weight:700;background:#1a7a5e;color:#fff;border:none;cursor:pointer;transition:all .18s}
        .dga-btn-avis:hover{background:#145f49}
        .dga-inp-qte-livraison{width:100px;border:1.5px solid #e5e7eb;border-radius:6px;padding:.35rem .5rem;font-size:.82rem}
        .dga-inp-qte-livraison:focus{outline:none;border-color:#1a7a5e}
        .dga-inp-qte-livraison:disabled{background:#f3f4f6;color:#9ca3af;cursor:not-allowed}
        .dga-badge-livree{background:#ecfdf5;color:#059669;font-size:.72rem;font-weight:700;padding:.2rem .55rem;border-radius:99px}
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
                                        <path d="M21 8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4a2 2 0 001-1.73z"/>
                                        <path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/>
                                    </svg>
                                    Produits à livrer
                                </h1>
                                <p>Un bon = une sortie du comptable : préparez et remettez les produits de chaque bon au demandeur</p>
                            </div>
                        </div>

                        <!-- ══ COMMUTATEUR TYPE ══ -->
                        <div class="dga-switch-type" id="dga-switch-type-livraison">
                            <button type="button" class="dga-switch-btn dga-switch-active" data-cible="fonctionnement">Fonctionnement</button>
                            <button type="button" class="dga-switch-btn" data-cible="investissement">Investissement</button>
                        </div>

                        <div id="dga-panel-livraison-fonctionnement">

                            <!-- ══ STATISTIQUE ══ -->
                            <div class="dga-stat-card" id="dga-stat-card-livraison">
                                <div class="dga-stat-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/></svg>
                                </div>
                                <div>
                                    <div class="dga-stat-lbl">Bons à livrer</div>
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
                                    À préparer et remettre
                                </span>
                                </div>

                                <table id="dga-table-livraison" class="display" style="width:100%">
                                    <thead>
                                    <tr>
                                        <th>N° bon</th>
                                        <th>Expression de besoin</th>
                                        <th>Demandeur</th>
                                        <th>Date de sortie</th>
                                        <th>Lignes à livrer</th>
                                        <th>Action</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                        </div><!-- /#dga-panel-livraison-fonctionnement -->

                        <div id="dga-panel-livraison-investissement" style="display:none;">

                            <!-- ══ STATISTIQUE ══ -->
                            <div class="dga-stat-card" id="dga-stat-card-livraison-invest">
                                <div class="dga-stat-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/></svg>
                                </div>
                                <div>
                                    <div class="dga-stat-lbl">Bons Investissement à livrer</div>
                                    <div class="dga-stat-val" id="dga-stat-nombre-invest">0</div>
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
                                    À préparer et remettre — Investissement
                                </span>
                                </div>

                                <table id="dga-table-livraison-invest" class="display" style="width:100%">
                                    <thead>
                                    <tr>
                                        <th>N° bon</th>
                                        <th>Expression de besoin</th>
                                        <th>Direction</th>
                                        <th>Date de sortie</th>
                                        <th>Lignes à livrer</th>
                                        <th>Action</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                        </div><!-- /#dga-panel-livraison-investissement -->

                        <!-- ══ MODALE : Détail / confirmation de livraison ══ -->
                        <div class="modal fade dga-modal" id="modalLivraison" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2 id="livraisonModalTitre">Livraison</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">
                                        <div id="dgaErreurLivraison" class="dga-erreur-generale" style="display:none;"></div>
                                        <p style="margin-bottom:1rem;font-size:.85rem;color:#374151;" id="dgaInfoDemandeur"></p>

                                        <table class="dga-table-produits">
                                            <thead><tr><th>Produit</th><th>Déjà livré</th><th>Restant à livrer</th><th>Quantité à remettre (fixée par le comptable)</th></tr></thead>
                                            <tbody id="dgaCorpsLivraison"></tbody>
                                        </table>
                                    </div>
                                    <div class="dga-actions">
                                        <button type="button" class="dga-cancel" data-bs-dismiss="modal">Fermer</button>
                                        <button type="button" class="dga-submit" id="dgaBtnConfirmerLivraison">
                                            <span>Livré</span>
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
<script src="/personnel/basi-scripts.bundle.39.js"></script>

</body>
</html>