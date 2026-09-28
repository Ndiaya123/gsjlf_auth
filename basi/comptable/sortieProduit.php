<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Sorties de produits — ENT GSJLF</title>
    <link rel="shortcut icon" href="http://localhost/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"/>
    <link href="/personnel/ressources/dist_assets/css/style_basi_35.css" rel="stylesheet" type="text/css"/>

    <style>
        /* Absente de style_basi_35.css — même règle que style_basi_36.css
           (page listeProduitInvestissement.php). */
        .dga-switch-type{display:inline-flex;background:#f3f4f6;border-radius:99px;padding:.25rem;gap:.15rem;margin-bottom:1.25rem}
        .dga-switch-btn{background:none;border:none;padding:.45rem 1.1rem;border-radius:99px;font-size:.8rem;font-weight:600;color:#6b7280;cursor:pointer;transition:all .15s}
        .dga-switch-btn:hover{color:#111827}
        .dga-switch-active{background:#fff;color:#1a7a5e;box-shadow:0 1px 3px rgba(0,0,0,.08)}
        /* Badges des statuts 7 (Livrée) et 8 (Clôturée) du circuit livraison/réception. */
        .dga-statut-7{background:#dbeafe;color:#1e40af}
        .dga-statut-8{background:#d1fae5;color:#065f46}
        /* Circuit livraison/réception : statuts 7-10, clôture du solde, écarts */
        .dga-statut-7{background:#dbeafe;color:#1e40af}
        .dga-statut-8{background:#d1fae5;color:#065f46}
        .dga-statut-9{background:#e0e7ff;color:#3730a3}
        .dga-statut-10{background:#f3f4f6;color:#6b7280}
        .dga-btn-cloturer{background:#fff7ed;color:#9a3412;border:1.5px solid #fed7aa;border-radius:7px;padding:.35rem .7rem;font-size:.72rem;font-weight:700;cursor:pointer;margin-left:.35rem}
        .dga-btn-cloturer:hover{background:#9a3412;color:#fff}
        .dga-btn-ecarts{display:inline-flex;align-items:center;gap:.4rem;background:#fef2f2;color:#991b1b;border:1.5px solid #fecaca;border-radius:9px;padding:.5rem .9rem;font-size:.78rem;font-weight:700;cursor:pointer}
        .dga-btn-ecarts:hover{background:#991b1b;color:#fff}
        .dga-btn-ecarts .dga-ecarts-count{background:#991b1b;color:#fff;border-radius:99px;padding:.05rem .45rem;font-size:.7rem}
        .dga-btn-ecarts:hover .dga-ecarts-count{background:#fff;color:#991b1b}
        .dga-ligne-ecart{background:#fee2e2;color:#991b1b}
        table.dga-table-ecarts{width:100%;border-collapse:collapse;font-size:.82rem}
        table.dga-table-ecarts thead th{background:#f8f9fa;font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;padding:.55rem .6rem;text-align:left;border-bottom:2px solid #e9ecef}
        table.dga-table-ecarts tbody td{padding:.6rem;border-bottom:1px solid #f3f4f6;color:#374151;vertical-align:top}
        .dga-ecart-motif{font-size:.75rem;color:#6b7280;margin-top:.15rem}
        .dga-ecart-actions{display:flex;flex-direction:column;gap:.35rem;min-width:230px}
        .dga-ecart-actions select,.dga-ecart-actions input{border:1.5px solid #e5e7eb;border-radius:7px;padding:.35rem .5rem;font-size:.78rem;width:100%}
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
                                        <path d="M20 12V8H6a2 2 0 010-4h12v4"/><path d="M4 6v14a2 2 0 002 2h14v-4"/><path d="M18 12a2 2 0 000 4h4v-4Z"/>
                                    </svg>
                                    Sorties de produits
                                </h1>
                                <p>Demandes en attente de sortie de stock — Fonctionnement et Investissement</p>
                            </div>
                        </div>

                        <!-- ══ ALERTE INVENTAIRE EN COURS (partagée) ══ -->
                        <div class="dga-alerte-inventaire" id="dga-alerte-inventaire">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span>Un inventaire est en cours : aucune sortie de stock ne peut être effectuée pour le moment. Les boutons "Sortie" sont désactivés jusqu'à la fin de l'inventaire.</span>
                        </div>

                        <!-- ══ COMMUTATEUR ══ -->
                        <div class="dga-switch-type" id="dga-switch-type-sortie">
                            <button type="button" class="dga-switch-btn dga-switch-active" data-cible="fonctionnement">Fonctionnement</button>
                            <button type="button" class="dga-switch-btn" data-cible="investissement">Investissement</button>
                        </div>

                        <!-- ══ PANNEAU FONCTIONNEMENT ══ -->
                        <div id="dga-panel-sortie-fonctionnement">

                            <!-- ══ STATISTIQUES ══ -->
                            <div class="dga-stats-grid">
                                <div class="dga-stat-card">
                                    <div class="dga-stat-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/></svg>
                                    </div>
                                    <div>
                                        <div class="dga-stat-lbl">Résultats affichés</div>
                                        <div class="dga-stat-val" id="dga-stat-nombre">0</div>
                                    </div>
                                </div>
                                <div class="dga-stat-card dga-sc-a-sortir">
                                    <div class="dga-stat-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 12V8H6a2 2 0 010-4h12v4"/><path d="M4 6v14a2 2 0 002 2h14v-4"/><path d="M18 12a2 2 0 000 4h4v-4Z"/></svg>
                                    </div>
                                    <div>
                                        <div class="dga-stat-lbl">À sortir</div>
                                        <div class="dga-stat-val" id="dga-stat-a-sortir">0</div>
                                    </div>
                                </div>
                                <div class="dga-stat-card dga-sc-partiel">
                                    <div class="dga-stat-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                    </div>
                                    <div>
                                        <div class="dga-stat-lbl">Sortie partielle</div>
                                        <div class="dga-stat-val" id="dga-stat-partiel">0</div>
                                    </div>
                                </div>
                                <div class="dga-stat-card dga-sc-terminee">
                                    <div class="dga-stat-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                    <div>
                                        <div class="dga-stat-lbl">Sortie totale</div>
                                        <div class="dga-stat-val" id="dga-stat-termine">0</div>
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
                                    Expressions de besoin
                                </span>
                                </div>

                                <div class="dga-filters">
                                    <div class="dga-filter-group">
                                        <label class="dga-filter-label" for="dga-filtre-statut-sortie">Statut</label>
                                        <select id="dga-filtre-statut-sortie" class="dga-inp-annee">
                                            <option value="3,5" selected>À traiter (À sortir + Sortie partielle)</option>
                                            <option value="3">À sortir</option>
                                            <option value="5">Sortie partielle</option>
                                            <option value="6">Sortie totale</option>
                                            <option value="3,5,6">Tous</option>
                                        </select>
                                    </div>
                                    <button type="button" class="dga-btn-ecarts" id="dga-btn-ecarts">
                                        Écarts de réception à régulariser <span class="dga-ecarts-count" id="dga-ecarts-count">0</span>
                                    </button>
                                </div>

                                <table id="dga-table-sortie" class="display" style="width:100%">
                                    <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Demandeur</th>
                                        <th>Date de création</th>
                                        <th>Nb. produits</th>
                                        <th>Statut</th>
                                        <th>Action</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                        </div><!-- /#dga-panel-sortie-fonctionnement -->

                        <!-- ══ PANNEAU INVESTISSEMENT ══ -->
                        <div id="dga-panel-sortie-investissement" style="display:none;">

                            <!-- ══ STATISTIQUES ══ -->
                            <div class="dga-stats-grid">
                                <div class="dga-stat-card">
                                    <div class="dga-stat-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/></svg>
                                    </div>
                                    <div>
                                        <div class="dga-stat-lbl">Résultats affichés</div>
                                        <div class="dga-stat-val" id="dga-stat-nombre-invest">0</div>
                                    </div>
                                </div>
                                <div class="dga-stat-card dga-sc-a-sortir">
                                    <div class="dga-stat-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 12V8H6a2 2 0 010-4h12v4"/><path d="M4 6v14a2 2 0 002 2h14v-4"/><path d="M18 12a2 2 0 000 4h4v-4Z"/></svg>
                                    </div>
                                    <div>
                                        <div class="dga-stat-lbl">Soumises</div>
                                        <div class="dga-stat-val" id="dga-stat-a-sortir-invest">0</div>
                                    </div>
                                </div>
                                <div class="dga-stat-card dga-sc-partiel">
                                    <div class="dga-stat-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                    </div>
                                    <div>
                                        <div class="dga-stat-lbl">Partiellement sorti</div>
                                        <div class="dga-stat-val" id="dga-stat-partiel-invest">0</div>
                                    </div>
                                </div>
                                <div class="dga-stat-card dga-sc-terminee">
                                    <div class="dga-stat-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                    <div>
                                        <div class="dga-stat-lbl">Terminées</div>
                                        <div class="dga-stat-val" id="dga-stat-termine-invest">0</div>
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
                                        Expressions de besoin Investissement
                                    </span>
                                </div>

                                <div class="dga-filters">
                                    <div class="dga-filter-group">
                                        <label class="dga-filter-label" for="dga-filtre-statut-sortie-invest">Statut</label>
                                        <select id="dga-filtre-statut-sortie-invest" class="dga-inp-annee">
                                            <option value="2,3" selected>À traiter (Soumises + Partiel)</option>
                                            <option value="2">Soumises</option>
                                            <option value="3">Partiellement sorti</option>
                                            <option value="4">Terminé</option>
                                            <option value="2,3,4">Tous</option>
                                        </select>
                                    </div>
                                </div>

                                <table id="dga-table-sortie-invest" class="display" style="width:100%">
                                    <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Direction</th>
                                        <th>Date de création</th>
                                        <th>Nb. produits</th>
                                        <th>Statut</th>
                                        <th>Action</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                        </div><!-- /#dga-panel-sortie-investissement -->

                        <!-- ══ MODALE : Sortie ══ -->
                        <!-- ══ MODALE : Écarts de réception à régulariser ══ -->
                        <div class="modal fade dga-modal" id="modalEcarts" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:980px;">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2>Écarts de réception à régulariser</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">
                                        <div id="dgaErreurEcarts" class="dga-erreur-generale" style="display:none;"></div>
                                        <div class="dga-alerte-info" style="margin-bottom:1rem;">
                                            Le demandeur déclare n'avoir pas reçu ces produits, pourtant marqués « livrés » par le magasinier. Choisissez la régularisation :
                                            <strong>correction de la livraison</strong> (le magasinier doit les remettre), <strong>retour en stock</strong>
                                            (jamais sortis du magasin : ils redeviennent « à sortir ») ou <strong>perte</strong> (stock inchangé, écart clos).
                                        </div>
                                        <table class="dga-table-ecarts">
                                            <thead><tr><th>Bon / Demande</th><th>Produit</th><th>Non reçu</th><th>Déclaré par</th><th>Régularisation</th></tr></thead>
                                            <tbody id="dgaCorpsEcarts"></tbody>
                                        </table>
                                    </div>
                                    <div class="dga-actions">
                                        <button type="button" class="dga-cancel" data-bs-dismiss="modal">Fermer</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade dga-modal" id="modalSortie" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2 id="sortieModalTitre">Sortie de produits</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">
                                        <div id="dgaErreurSortie" class="dga-erreur-generale" style="display:none;"></div>
                                        <div id="dgaInfoDejaSortie" class="dga-alerte-info" style="display:none;background:#fffbeb;border-color:#fde68a;color:#92400e;"></div>
                                        <div class="dga-alerte-info">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                            La quantité à sortir est préremplie au maximum autorisé (minimum entre le restant à sortir et le stock disponible), mais vous pouvez la diminuer. Elle ne peut jamais dépasser ce maximum. Si le stock est nul, aucune sortie n'est possible pour cette ligne.
                                        </div>
                                        <table class="dga-table-produits">
                                            <thead><tr><th>Produit</th><th>Déjà sorti</th><th>Restant à sortir</th><th>Stock dispo.</th><th>À sortir</th><th></th></tr></thead>
                                            <tbody id="dgaCorpsSortie"></tbody>
                                        </table>
                                    </div>
                                    <div class="dga-actions">
                                        <button type="button" class="dga-cancel" data-bs-dismiss="modal">Annuler</button>
                                        <button type="button" class="dga-submit" id="dgaBtnConfirmerSortie">
                                            <span>Confirmer la sortie</span>
                                            <svg class="dga-spinner hidden" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 019.76 7.8"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ══ MODALE : Voir (suivi de l'évolution) ══ -->
                        <div class="modal fade dga-modal" id="modalVoirEB" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2 id="voirEbModalTitre">Suivi</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">
                                        <div id="voirEbContenu"></div>
                                    </div>
                                    <div class="dga-actions">
                                        <button type="button" class="dga-cancel" data-bs-dismiss="modal">Fermer</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ══ MODALE : Consulter sortie (Informations sur les sorties) ══ -->
                        <div class="modal fade dga-modal" id="modalConsulterSortie" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:700px;">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2 id="consulterSortieModalTitre">Informations sur les sorties</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">
                                        <div id="consulterSortieContenu"></div>
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
<script src="/personnel/basi-scripts.bundle.31.js"></script>

</body>
</html>


x²