
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Budgets de Fonctionnement — ENT GSJLF</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/fullcalendar/fullcalendar.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"/>
    <link href="/personnel/ressources/dist_assets/css/style_basi_11.css" rel="stylesheet" type="text/css"/>
    <script>document.documentElement.classList.add('ld-booting');</script>

</head>

<body id="kt_body"
      class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled toolbar-fixed aside-enabled aside-fixed"
      style="--kt-toolbar-height:55px;--kt-toolbar-height-tablet-and-mobile:55px">
<div class="d-flex flex-column flex-root">
    <div class="page d-flex flex-row flex-column-fluid">

        <!-- ASIDE Metronic -->
        <div id="kt_aside" class="aside aside-light aside-hoverable" data-kt-drawer="true"
             data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}"
             data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}"
             data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_mobile_toggle">
            <div class="aside-logo flex-column-auto text-center" id="kt_aside_logo">
                <a href="/personnel/comptable-accueil" style="margin-left:65px;" id="lien_logo1">
                    <img alt="Logo" src="/personnel/ressources/dist_assets/media/logos/1.png" id="logo1" class="h-50px logo"/>
                </a>
                <div id="kt_aside_toggle" class="btn btn-icon w-auto px-0 btn-active-color-primary aside-toggle"
                     data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body" data-kt-toggle-name="aside-minimize">
                <span class="svg-icon svg-icon-1 rotate-180">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path opacity="0.5" d="M14.2657 11.4343L18.45 7.25C18.8642 6.83579 18.8642 6.16421 18.45 5.75C18.0358 5.33579 17.3642 5.33579 16.95 5.75L11.4071 11.2929C11.0166 11.6834 11.0166 12.3166 11.4071 12.7071L16.95 18.25C17.3642 18.6642 18.0358 18.6642 18.45 18.25C18.8642 17.8358 18.8642 17.1642 18.45 16.75L14.2657 12.5657C13.9533 12.2533 13.9533 11.7467 14.2657 11.4343Z" fill="black"/>
                        <path d="M8.2657 11.4343L12.45 7.25C12.8642 6.83579 12.8642 6.16421 12.45 5.75C12.0358 5.33579 11.3642 5.33579 10.95 5.75L5.40712 11.2929C5.01659 11.6834 5.01659 12.3166 5.40712 12.7071L10.95 18.25C11.3642 18.6642 12.0358 18.6642 12.45 18.25C12.8642 17.8358 12.8642 17.1642 12.45 16.75L8.2657 12.5657C7.95328 12.2533 7.95328 11.7467 8.2657 11.4343Z" fill="black"/>
                    </svg>
                </span>
                </div>
            </div>
            <div class="aside-menu flex-column-fluid">
                <div class="hover-scroll-overlay-y my-5 my-lg-5" id="kt_aside_menu_wrapper"
                     data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}"
                     data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_aside_logo, #kt_aside_footer"
                     data-kt-scroll-wrappers="#kt_aside_menu" data-kt-scroll-offset="0">
                    <div class="menu menu-column menu-title-gray-800 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-500"
                         id="kt_aside_menu" data-kt-menu="true"></div>
                </div>
            </div>
        </div>

        <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">

            <!-- HEADER Metronic -->
            <div id="kt_header" class="header align-items-stretch">
                <div class="container-fluid d-flex align-items-stretch justify-content-between">
                    <div class="d-flex align-items-center d-lg-none ms-n3 me-1">
                        <div class="btn btn-icon btn-active-light-primary w-30px h-30px w-md-40px h-md-40px" id="kt_aside_mobile_toggle">
                        <span class="svg-icon svg-icon-2x mt-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z" fill="black"/>
                                <path opacity="0.3" d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z" fill="black"/>
                            </svg>
                        </span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
                        <a href="/personnel/comptable-accueil" class="d-lg-none" id="lien_logo2">
                            <img alt="Logo" src="/personnel/ressources/dist_assets/media/logos/1.png" id="logo2" class="h-30px"/>
                        </a>
                    </div>
                    <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1">
                        <div class="d-flex align-items-stretch" id="kt_header_nav">
                            <div class="header-menu align-items-stretch" data-kt-drawer="true" data-kt-drawer-name="header-menu"
                                 data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true"
                                 data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="end"
                                 data-kt-drawer-toggle="#kt_header_menu_mobile_toggle"
                                 data-kt-swapper="true" data-kt-swapper-mode="prepend"
                                 data-kt-swapper-parent="{default: '#kt_body', lg: '#kt_header_nav'}">
                                <div class="menu menu-lg-rounded menu-column menu-lg-row menu-state-bg menu-title-gray-700 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-400 fw-bold my-5 my-lg-0 align-items-stretch"
                                     id="kt_header_menu" data-kt-menu="true">
                                    <div class="menu-item me-lg-1">
                                        <a class="menu-link py-3" href="" id="lien_ent">
                                            <h3><span class="menu-title">Environnement Numérique de Travail</span></h3>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-stretch flex-shrink-0">
                            <div class="d-flex align-items-center ms-1 ms-lg-3" id="kt_header_user_menu_toggle">
                                <div class="cursor-pointer symbol symbol-30px symbol-md-40px"
                                     data-kt-menu-trigger="click" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
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
                                    <div class="menu-item px-5"><a href="javascript:void(0)" onclick="actionMonProfile()" class="menu-link px-5">Mon profil</a></div>
                                    <div class="separator my-2"></div>
                                    <div class="menu-item px-5">
                                        <div class="menu-content px-5">
                                            <label class="form-check form-switch form-check-custom form-check-solid pulse pulse-success" for="kt_user_menu_dark_mode_toggle">
                                                <a href="/personnel/signout">
                                                    <input class="form-check-input w-30px h-20px" checked="checked" type="checkbox" value="1" name="mode" id="kt_user_menu_dark_mode_toggle" data-kt-url="/personnel/quitter"/>
                                                    <span class="pulse-ring ms-n1"></span>
                                                    <span class="form-check-label text-gray-600 fs-7">se déconnecter</span>
                                                </a>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center d-lg-none ms-2 me-n3">
                                <div class="btn btn-icon btn-active-light-primary w-30px h-30px w-md-40px h-md-40px" id="kt_header_menu_mobile_toggle">
                                <span class="svg-icon svg-icon-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path d="M13 11H3C2.4 11 2 10.6 2 10V9C2 8.4 2.4 8 3 8H13C13.6 8 14 8.4 14 9V10C14 10.6 13.6 11 13 11ZM22 5V4C22 3.4 21.6 3 21 3H3C2.4 3 2 3.4 2 4V5C2 5.6 2.4 6 3 6H21C21.6 6 22 5.6 22 5Z" fill="black"/>
                                        <path opacity="0.3" d="M21 16H3C2.4 16 2 15.6 2 15V14C2 13.4 2.4 13 3 13H21C21.6 13 22 13.4 22 14V15C22 15.6 21.6 16 21 16ZM14 20V19C14 18.4 13.6 18 13 18H3C2.4 18 2 18.4 2 19V20C2 20.6 2.4 21 3 21H13C13.6 21 14 20.6 14 20Z" fill="black"/>
                                    </svg>
                                </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONTENU -->
            <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
                <div class="toolbar" id="kt_toolbar">
                    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack">
                        <div data-kt-swapper="true" data-kt-swapper-mode="prepend"
                             data-kt-swapper-parent="{default: '#kt_content_container', 'lg': '#kt_toolbar_container'}"
                             class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0" id="infoAppli"></div>
                    </div>
                </div>
                <div class="post d-flex flex-column-fluid" id="kt_post">
                    <div id="kt_content_container" class="container-xxl">

                        <!-- Bouton nouveau budget -->
                        <div style="display:flex;justify-content:flex-end;margin-bottom:1.25rem;">
                            <button id="openModalBtn" type="button" class="bud-btn bud-btn--add">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                                Nouveau budget
                            </button>
                        </div>

                        <!-- ════════════════════════
                             CARTES STATISTIQUES
                             .tab-content + data-tab  ← attendu par updateAllStatsCards()
                             .bud-show-all sur .bud-stats quand onglet "tous" ← géré par le JS patch
                        ════════════════════════ -->
                        <div class="bud-stats bud-show-all" id="bud-stats-grid">

                            <div class="tab-content active" data-tab="tous">
                                <div class="bud-stat bud-stat--tous">
                                    <div class="bud-stat__header">
                                        <div class="bud-stat__icon">
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                        </div>
                                        <span class="bud-stat__trend">Total</span>
                                    </div>
                                    <div class="bud-stat__label">Tous les budgets</div>
                                    <div class="bud-stat__value" id="stats-tous-count">0</div>
                                    <div class="bud-stat__footer">
                                        <div class="bud-stat__sub-label">Plafond cumulé</div>
                                        <div class="bud-stat__sub-value" id="stats-tous-plafond">0 FCFA</div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-content" data-tab="encours">
                                <div class="bud-stat bud-stat--encours">
                                    <div class="bud-stat__header">
                                        <div class="bud-stat__icon">
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        </div>
                                        <span class="bud-stat__trend">En cours</span>
                                    </div>
                                    <div class="bud-stat__label">Budgets en cours</div>
                                    <div class="bud-stat__value" id="stats-encours-count">0</div>
                                    <div class="bud-stat__footer">
                                        <div class="bud-stat__sub-label">Plafond cumulé</div>
                                        <div class="bud-stat__sub-value" id="stats-encours-plafond">0 FCFA</div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-content" data-tab="valider">
                                <div class="bud-stat bud-stat--valider">
                                    <div class="bud-stat__header">
                                        <div class="bud-stat__icon">
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </div>
                                        <span class="bud-stat__trend">Validation</span>
                                    </div>
                                    <div class="bud-stat__label">À valider</div>
                                    <div class="bud-stat__value" id="stats-valider-count">0</div>
                                    <div class="bud-stat__footer">
                                        <div class="bud-stat__sub-label">Plafond cumulé</div>
                                        <div class="bud-stat__sub-value" id="stats-valider-plafond">0 FCFA</div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-content" data-tab="accepter">
                                <div class="bud-stat bud-stat--accepter">
                                    <div class="bud-stat__header">
                                        <div class="bud-stat__icon">
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                        </div>
                                        <span class="bud-stat__trend">Accepté</span>
                                    </div>
                                    <div class="bud-stat__label">Budgets acceptés</div>
                                    <div class="bud-stat__value" id="stats-accepter-count">0</div>
                                    <div class="bud-stat__footer">
                                        <div class="bud-stat__sub-label">Plafond cumulé</div>
                                        <div class="bud-stat__sub-value" id="stats-accepter-plafond">0 FCFA</div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-content" data-tab="rejeter">
                                <div class="bud-stat bud-stat--rejeter">
                                    <div class="bud-stat__header">
                                        <div class="bud-stat__icon">
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </div>
                                        <span class="bud-stat__trend">Rejeté</span>
                                    </div>
                                    <div class="bud-stat__label">Budgets rejetés</div>
                                    <div class="bud-stat__value" id="stats-rejeter-count">0</div>
                                    <div class="bud-stat__footer">
                                        <div class="bud-stat__sub-label">Plafond cumulé</div>
                                        <div class="bud-stat__sub-value" id="stats-rejeter-plafond">0 FCFA</div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-content" data-tab="reajuster">
                                <div class="bud-stat" style="border-top:3px solid #7c3aed;background:#fff;border-radius:14px;border:1px solid #e9ecef;padding:1.2rem 1.15rem 1rem;overflow:hidden;transition:transform .2s ease,box-shadow .2s ease;cursor:default;">
                                    <div class="bud-stat__header">
                                        <div class="bud-stat__icon" style="background:#ede9fe;color:#5b21b6;">
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                                        </div>
                                        <span class="bud-stat__trend" style="background:#ede9fe;color:#5b21b6;">Réajuster</span>
                                    </div>
                                    <div class="bud-stat__label">Budgets à réajuster</div>
                                    <div class="bud-stat__value" id="stats-reajuster-count">0</div>
                                    <div class="bud-stat__footer">
                                        <div class="bud-stat__sub-label">Plafond cumulé</div>
                                        <div class="bud-stat__sub-value" id="stats-reajuster-plafond" style="color:#5b21b6;">0 FCFA</div>
                                    </div>
                                </div>
                            </div>

                        </div><!-- /.bud-stats -->

                        <!-- ════════════════════════
                             CARTE TABLE
                        ════════════════════════ -->
                        <div class="bud-card">

                            <!-- Onglets — classes gérées par le JS (initTabs) -->
                            <div class="bud-tabs-bar">
                                <button data-tab="tous" class="active bg-white border border-b-0 border-gray-200 text-emerald-700">
                                    Tous <span class="bud-tab-badge" id="count-tous">0</span>
                                </button>
                                <button data-tab="encours" class="text-gray-600">
                                    En cours <span class="bud-tab-badge" id="count-encours">0</span>
                                </button>
                                <button data-tab="valider" class="text-gray-600">
                                    À valider <span class="bud-tab-badge" id="count-valider">0</span>
                                </button>
                                <button data-tab="accepter" class="text-gray-600">
                                    Acceptés <span class="bud-tab-badge" id="count-accepter">0</span>
                                </button>
                                <button data-tab="rejeter" class="text-gray-600">
                                    Rejetés <span class="bud-tab-badge" id="count-rejeter">0</span>
                                </button>
                                <button data-tab="reajuster" class="text-gray-600">
                                    Réajuster <span class="bud-tab-badge" id="count-reajuster">0</span>
                                </button>
                            </div>

                            <!-- Filtres -->
                            <div class="bud-filters">
                                <div class="bud-filter-group">
                                    <label class="bud-filter-label" for="filter_annee">Année budgétaire</label>
                                    <select id="filter_annee" class="bud-select">
                                        <option value="">Toutes les années</option>
                                    </select>
                                </div>
                                <div style="display:flex;gap:.5rem;align-items:flex-end;">
                                    <button id="resetFilters" class="bud-btn bud-btn--ghost" type="button">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 109-9M3 12V6m0 6H9"/></svg>
                                        Réinitialiser
                                    </button>
                                    <button id="applyFilters" class="bud-btn bud-btn--primary" type="button">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                                        Filtrer
                                    </button>
                                </div>
                            </div>

                            <!-- Table DataTables -->
                            <div class="bud-table-wrap">
                                <table id="budgetTable" class="display" style="width:100%;">
                                    <thead>
                                    <tr>
                                        <th>Année</th>
                                        <th>Type</th>
                                        <th>Plafond</th>
                                        <th>Création</th>
                                        <th style="min-width:90px;">Statut</th>
                                        <th style="text-align:right;min-width:110px;">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                        </div><!-- /.bud-card -->

                        <!-- MODALE -->
                        <div class="modal fade bud-modal" id="budgetModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered mw-580px">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2 id="modalLabel">Créer un Budget</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                        <span class="svg-icon svg-icon-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/>
                                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
                                            </svg>
                                        </span>
                                        </div>
                                    </div>
                                    <div class="modal-body">
                                        <form id="budgetForm">
                                            <!-- Année + Type (type verrouillé = investissement) -->
                                            <div class="bud-grid-2">
                                                <div class="bud-field">
                                                    <label class="bud-lbl" for="annee">Année <span>*</span></label>
                                                    <select id="annee" name="annee" class="bud-inp">
                                                        <option value="">Sélectionner</option>
                                                    </select>
                                                    <p id="annee-error" class="bud-err hidden"></p>
                                                </div>
                                                <div class="bud-field">
                                                    <label class="bud-lbl" for="type_budget_id">Type de budget <span>*</span></label>
                                                    <select id="type_budget_id" name="type_budget_id" class="bud-inp">
                                                        <option value="" disabled selected>Fonctionnement</option>
                                                    </select>
                                                    <p id="type-error" class="bud-err hidden"></p>
                                                </div>
                                            </div>
                                            <!-- Plafond -->
                                            <div class="bud-field">
                                                <label class="bud-lbl" for="plafond">Plafond (FCFA) <span>*</span></label>
                                                <div class="bud-plafond">
                                                    <span class="bud-plafond-pre">FCFA</span>
                                                    <input type="number" id="plafond" name="plafond" step="0.01" min="0" class="bud-inp" placeholder="0.00">
                                                    <span class="bud-plafond-suf">XOF</span>
                                                </div>
                                                <p id="plafond-error" class="bud-err hidden"></p>
                                                <p id="plafond-help" class="hidden" style="font-size:.72rem;color:#1a7a5e;margin-top:.3rem;font-weight:500;"></p>
                                            </div>
                                            <div class="bud-actions">
                                                <button type="button" id="cancelModalBtn" class="bud-cancel">Annuler</button>
                                                <button type="submit" id="submitBudgetBtn" class="bud-submit">
                                                    <span id="submitButton">Créer le Budget</span>
                                                    <svg id="submitSpinner" class="hidden" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                        <circle cx="12" cy="12" r="10" stroke-opacity=".25"/>
                                                        <path d="M12 2a10 10 0 019.76 7.8"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- FOOTER -->
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

<!-- Scripts Metronic (jQuery + DataTables + SweetAlert2 déjà inclus) -->
<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script src="/personnel/scripts.bundle.gs.js"></script>

<!-- Config module Budget Fonctionnement
     defaultType : ID du type "Fonctionnement" dans la table TypeBudget (=1)
     direction_id est toujours NULL pour ce type de budget : transversal,
     visible et gérable par le comptable quelle que soit la direction.
-->
<script>
    window.COMPTABLE_CONFIG = {
        defaultType: 1  // Fonctionnement
    };
</script>

<!-- Script métier budget fonctionnement -->
<script src="/personnel/basi-scripts.bundle.8.js"></script>

<!--
    Patch : synchronisation onglets ↔ cartes stats.
    Le JS métier (updateAllStatsCards) ajoute/retire .active sur .tab-content.
    On surcharge pour gérer aussi .bud-show-all sur #bud-stats-grid
    (affiche toutes les cartes quand l'onglet "tous" est sélectionné).
-->
<script>
    (function () {
        const _updateAllStatsCards = window.updateAllStatsCards;
        window.updateAllStatsCards = function () {
            if (typeof _updateAllStatsCards === 'function') _updateAllStatsCards();
            const grid = document.getElementById('bud-stats-grid');
            if (!grid) return;
            if (typeof currentTab !== 'undefined' && currentTab === 'tous') {
                grid.classList.add('bud-show-all');
            } else {
                grid.classList.remove('bud-show-all');
            }
        };

        // Réinitialiser l'état au chargement (onglet "tous" par défaut)
        document.addEventListener('DOMContentLoaded', function () {
            const grid = document.getElementById('bud-stats-grid');
            if (grid) grid.classList.add('bud-show-all');
        });
    })();
</script>

</body>
</html>