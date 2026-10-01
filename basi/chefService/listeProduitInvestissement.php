<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Catalogue produits d'investissement — ENT GSJLF</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"/>
    <link href="/personnel/ressources/dist_assets/css/style_basi_36.css" rel="stylesheet" type="text/css"/>

    <style>
        /* Absentes de style_basi_36.css — vérifiées manquantes. */
        .dga-btn-primary{display:inline-flex;align-items:center;gap:.4rem;background:#1a7a5e;color:#fff;border:none;border-radius:9px;padding:.6rem 1.1rem;font-size:.85rem;font-weight:700;cursor:pointer;white-space:nowrap}
        /* .dga-inp n'était en fait JAMAIS définie — seules des variantes
           composées (.dga-inp-annee, .dga-inp-qte-sortie) existent. Sans
           cette règle, un <select class="dga-inp"> retombe sur l'apparence
           native du navigateur (fond grisé, flèche système), d'où le
           décalage visuel avec le champ texte juste au-dessus. */
        .dga-inp{width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:.55rem .85rem;font-size:.85rem;color:#374151;background:#fff;box-sizing:border-box;font-family:inherit}
        .dga-inp:focus{outline:none;border-color:#1a7a5e;box-shadow:0 0 0 3px rgba(26,122,94,.1)}
        .dga-inp:disabled{background:#f3f4f6;color:#9ca3af;cursor:not-allowed}
        select.dga-inp{appearance:none;-webkit-appearance:none;-moz-appearance:none;background-image:url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;background-size:16px;padding-right:2.4rem;cursor:pointer}
        select.dga-inp:disabled{cursor:not-allowed}
        .dga-btn-primary:hover{background:#145f49}
        .dga-cell-muted{color:#9ca3af}
        .dga-field{margin-bottom:1rem}
        .dga-lbl{display:block;font-size:.78rem;font-weight:700;color:#374151;margin-bottom:.35rem}

        .dga-badge-createur{display:inline-flex;padding:.2rem .55rem;border-radius:99px;font-size:.7rem;font-weight:600}
        .dga-badge-createur-moi{background:#ecfdf5;color:#059669}
        .dga-badge-createur-autre{background:#f3f4f6;color:#6b7280}
        .dga-btn-detail-produit{background:#ede9fe;color:#5b21b6;border:1.5px solid #ddd6fe;border-radius:7px;padding:.35rem .7rem;font-size:.72rem;font-weight:700;cursor:pointer;margin-right:.35rem}
        .dga-btn-detail-produit:hover{background:#5b21b6;color:#fff}
        .dga-btn-modifier-produit{background:#eff6ff;color:#1d4ed8;border:1.5px solid #dbeafe;border-radius:7px;padding:.35rem .7rem;font-size:.72rem;font-weight:700;cursor:pointer}
        .dga-btn-modifier-produit:hover{background:#1d4ed8;color:#fff}
        .dga-btn-toggle-produit{border-radius:7px;padding:.35rem .7rem;font-size:.72rem;font-weight:700;cursor:pointer;margin-left:.4rem;border:1.5px solid}
        .dga-btn-activer{background:#ecfdf5;color:#059669;border-color:#d1fae5}
        .dga-btn-activer:hover{background:#059669;color:#fff}
        .dga-btn-desactiver{background:#fef2f2;color:#991b1b;border-color:#fee2e2}
        .dga-btn-desactiver:hover{background:#991b1b;color:#fff}

        /* ── Autocomplétion (recherche-suggestion à la saisie) ────────────── */
        .dga-autocomplete-wrap{position:relative}
        .dga-autocomplete-liste{position:absolute;top:100%;left:0;right:0;z-index:60;background:#fff;border:1.5px solid #e5e7eb;border-top:none;border-radius:0 0 10px 10px;box-shadow:0 12px 24px rgba(0,0,0,.1);max-height:220px;overflow-y:auto;display:none}
        .dga-autocomplete-liste.dga-visible{display:block}
        .dga-autocomplete-item{padding:.6rem .85rem;font-size:.82rem;cursor:pointer;border-bottom:1px solid #f3f4f6}
        .dga-autocomplete-item:last-child{border-bottom:none}
        .dga-autocomplete-item:hover{background:#f9fafb}
        .dga-autocomplete-item-nom{font-weight:700;color:#111827}
        .dga-autocomplete-item-meta{font-size:.72rem;color:#9ca3af;margin-top:.1rem}
        .dga-autocomplete-vide{padding:.6rem .85rem;font-size:.78rem;color:#9ca3af;font-style:italic}
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
                                    Catalogue des produits d'investissement
                                </h1>
                                <p>Le catalogue complet — stock visible seulement si votre direction en a déjà reçu, modification réservée à vos propres créations</p>
                            </div>
                            <button id="dga-btn-nouveau-produit" class="dga-btn-primary" type="button">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Nouveau produit
                            </button>
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
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="8" r="4"/><path d="M6 21v-2a4 4 0 014-4h4a4 4 0 014 4v2"/></svg>
                                </div>
                                <div>
                                    <div class="dga-stat-lbl">Produits avec stock</div>
                                    <div class="dga-stat-val" id="dga-stat-mes-produits">0</div>
                                </div>
                            </div>
                            <div class="dga-stat-card">
                                <div class="dga-stat-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 7h-9m0 10H4a2 2 0 01-2-2V7a2 2 0 012-2h5m0 10v6m0-6l4-4m-4 4l-4-4"/></svg>
                                </div>
                                <div>
                                    <div class="dga-stat-lbl">Stock total (ma direction)</div>
                                    <div class="dga-stat-val" id="dga-stat-mon-stock">0</div>
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
                                    Tous les produits
                                </span>
                                <div class="dga-search">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                    </svg>
                                    <input type="text" data-kt-docs-table-filter="search" placeholder="Rechercher un produit"/>
                                </div>
                            </div>

                            <table id="dga-table-catalogue" class="display" style="width:100%">
                                <thead>
                                <tr>
                                    <th>Rubrique</th>
                                    <th>Sous-rubrique</th>
                                    <th>Nom du produit</th>
                                    <th>Stock total</th>
                                    <th>Sorti (ma direction)</th>
                                    <th>Date de création</th>
                                    <th>État</th>
                                    <th>Créateur</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <!-- ══ MODALE : Créer / Modifier un produit ══ -->
                        <div class="modal fade dga-modal" id="modalProduitInvest" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2 id="produitModalTitre">Nouveau produit</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">
                                        <div id="dgaErreurGeneraleProduit" class="dga-erreur-generale" style="display:none;"></div>

                                        <div class="dga-field">
                                            <label class="dga-lbl" for="dgaNomProduit">Nom du produit <span style="color:#ef4444;">*</span></label>
                                            <div class="dga-autocomplete-wrap">
                                                <input type="text" id="dgaNomProduit" class="dga-inp" autocomplete="off" placeholder="Commencez à taper le nom…"/>
                                                <div class="dga-autocomplete-liste" id="dgaAutocompleteListe"></div>
                                            </div>
                                        </div>

                                        <div class="dga-field">
                                            <label class="dga-lbl" for="dgaRubriqueProduit">Rubrique <span style="color:#ef4444;">*</span></label>
                                            <select id="dgaRubriqueProduit" class="dga-inp"><option value="">Sélectionner…</option></select>
                                        </div>

                                        <div class="dga-field">
                                            <label class="dga-lbl" for="dgaSousRubriqueProduit">Sous-rubrique <span style="color:#ef4444;">*</span></label>
                                            <select id="dgaSousRubriqueProduit" class="dga-inp" disabled><option value="">—</option></select>
                                        </div>

                                        <div class="dga-field" style="margin-bottom:0;">
                                            <label class="dga-lbl" for="dgaSeuilProduit">Seuil d'alerte</label>
                                            <input type="number" id="dgaSeuilProduit" class="dga-inp" min="0" value="0"/>
                                        </div>
                                    </div>
                                    <div class="dga-actions">
                                        <button type="button" class="dga-cancel" data-bs-dismiss="modal">Annuler</button>
                                        <button type="button" class="dga-submit" id="dgaBtnEnregistrerProduit">
                                            <span>Enregistrer</span>
                                            <svg class="dga-spinner hidden" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 019.76 7.8"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
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
<script src="/personnel/basi-scripts.bundle.37.js"></script>

</body>
</html>