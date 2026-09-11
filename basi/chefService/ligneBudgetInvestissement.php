<?php
$budgetToken = $_GET['budgetId'] ?? '';
if (empty($budgetToken)) { header('Location: /personnel/responsable-financier-accueil'); exit; }
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Lignes de Budget — ENT GSJLF</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/fullcalendar/fullcalendar.bundle.css" rel="stylesheet"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet"/>
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"/>

    <link href="/personnel/ressources/dist_assets/css/style_basi_5.css" rel="stylesheet" type="text/css"/>

</head>
<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled toolbar-fixed aside-enabled aside-fixed" style="--kt-toolbar-height:55px;--kt-toolbar-height-tablet-and-mobile:55px">
<div class="d-flex flex-column flex-root"><div class="page d-flex flex-row flex-column-fluid">

        <!-- ASIDE -->
        <div id="kt_aside" class="aside aside-light aside-hoverable" data-kt-drawer="true" data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_mobile_toggle">
            <div class="aside-logo flex-column-auto text-center" id="kt_aside_logo">
                <a href="/personnel/responsable-financier-accueil" style="margin-left:65px;" id="lien_logo1"><img alt="Logo" src="/personnel/ressources/dist_assets/media/logos/1.png" id="logo1" class="h-50px logo"/></a>
                <div id="kt_aside_toggle" class="btn btn-icon w-auto px-0 btn-active-color-primary aside-toggle" data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body" data-kt-toggle-name="aside-minimize">
                    <span class="svg-icon svg-icon-1 rotate-180"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path opacity="0.5" d="M14.2657 11.4343L18.45 7.25C18.8642 6.83579 18.8642 6.16421 18.45 5.75C18.0358 5.33579 17.3642 5.33579 16.95 5.75L11.4071 11.2929C11.0166 11.6834 11.0166 12.3166 11.4071 12.7071L16.95 18.25C17.3642 18.6642 18.0358 18.6642 18.45 18.25C18.8642 17.8358 18.8642 17.1642 18.45 16.75L14.2657 12.5657C13.9533 12.2533 13.9533 11.7467 14.2657 11.4343Z" fill="black"/><path d="M8.2657 11.4343L12.45 7.25C12.8642 6.83579 12.8642 6.16421 12.45 5.75C12.0358 5.33579 11.3642 5.33579 10.95 5.75L5.40712 11.2929C5.01659 11.6834 5.01659 12.3166 5.40712 12.7071L10.95 18.25C11.3642 18.6642 12.0358 18.6642 12.45 18.25C12.8642 17.8358 12.8642 17.1642 12.45 16.75L8.2657 12.5657C7.95328 12.2533 7.95328 11.7467 8.2657 11.4343Z" fill="black"/></svg></span>
                </div>
            </div>
            <div class="aside-menu flex-column-fluid">
                <div class="hover-scroll-overlay-y my-5 my-lg-5" id="kt_aside_menu_wrapper" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_aside_logo, #kt_aside_footer" data-kt-scroll-wrappers="#kt_aside_menu" data-kt-scroll-offset="0">
                    <div class="menu menu-column menu-title-gray-800 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-500" id="kt_aside_menu" data-kt-menu="true"></div>
                </div>
            </div>
        </div>

        <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
            <!-- HEADER -->
            <div id="kt_header" class="header align-items-stretch">
                <div class="container-fluid d-flex align-items-stretch justify-content-between">
                    <div class="d-flex align-items-center d-lg-none ms-n3 me-1">
                        <div class="btn btn-icon btn-active-light-primary w-30px h-30px w-md-40px h-md-40px" id="kt_aside_mobile_toggle">
                            <span class="svg-icon svg-icon-2x mt-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z" fill="black"/><path opacity="0.3" d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z" fill="black"/></svg></span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0"><a href="/personnel/responsable-financier-accueil" class="d-lg-none" id="lien_logo2"><img alt="Logo" src="/personnel/ressources/dist_assets/media/logos/1.png" id="logo2" class="h-30px"/></a></div>
                    <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1">
                        <div class="d-flex align-items-stretch" id="kt_header_nav">
                            <div class="header-menu align-items-stretch" data-kt-drawer="true" data-kt-drawer-name="header-menu" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="end" data-kt-drawer-toggle="#kt_header_menu_mobile_toggle" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_body', lg: '#kt_header_nav'}">
                                <div class="menu menu-lg-rounded menu-column menu-lg-row menu-state-bg menu-title-gray-700 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-400 fw-bold my-5 my-lg-0 align-items-stretch" id="kt_header_menu" data-kt-menu="true">
                                    <div class="menu-item me-lg-1"><a class="menu-link py-3" href="" id="lien_ent"><h3><span class="menu-title">Environnement Numérique de Travail</span></h3></a></div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-stretch flex-shrink-0">
                            <div class="d-flex align-items-center ms-1 ms-lg-3" id="kt_header_user_menu_toggle">
                                <div class="cursor-pointer symbol symbol-30px symbol-md-40px" data-kt-menu-trigger="click" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end"><img src="" id="user_photo1"/></div>
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-primary fw-bold py-4 fs-6 w-275px" data-kt-menu="true">
                                    <div class="menu-item px-3"><div class="menu-content d-flex align-items-center px-3"><div class="symbol symbol-50px me-5"><img alt="admin" src="" id="user_photo2"/></div><div class="d-flex flex-column"><div class="fw-bolder d-flex align-items-center fs-5" id="user_pn"></div><a href="javascript:void(0)" class="fw-bold text-muted text-hover-primary fs-7" id="user_email"></a></div></div></div>
                                    <div class="separator my-2"></div>
                                    <div class="menu-item px-5"><a href="javascript:void(0)" onclick="actionMonProfile()" class="menu-link px-5">Mon profil</a></div>
                                    <div class="separator my-2"></div>
                                    <div class="menu-item px-5"><div class="menu-content px-5"><label class="form-check form-switch form-check-custom form-check-solid pulse pulse-success"><a href="/personnel/signout"><input class="form-check-input w-30px h-20px" checked="checked" type="checkbox" value="1" name="mode" id="kt_user_menu_dark_mode_toggle" data-kt-url="/personnel/quitter"/><span class="pulse-ring ms-n1"></span><span class="form-check-label text-gray-600 fs-7">se déconnecter</span></a></label></div></div>
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
                        <div data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', 'lg': '#kt_toolbar_container'}" class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0" id="infoAppli"></div>
                    </div>
                </div>
                <div class="post d-flex flex-column-fluid" id="kt_post">
                    <div id="kt_content_container" class="container-xxl">

                        <!-- En-tête page -->
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem;">
                            <div style="display:flex;align-items:center;gap:.85rem;">
                                <button id="lb-back-btn" class="lb-btn lb-btn--back" title="Retour">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
                                </button>
                                <div>
                                    <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#1a7a5e;margin-bottom:.15rem;">Module Budget — Investissement</div>
                                    <h1 style="margin:0;font-size:1.35rem;font-weight:800;color:#111827;letter-spacing:-.02em;">Lignes Budgétaires</h1>
                                    <span id="lb-budget-info-text" style="font-size:.78rem;color:#6b7280;"></span>
                                </div>
                            </div>
                            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                                <button id="lb-validate-btn" class="lb-btn lb-btn--success" style="display:none;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    Valider le budget
                                </button>
                                <button id="lb-add-btn" class="lb-btn lb-btn--primary">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Ajouter une ligne
                                </button>
                            </div>
                        </div>

                        <!-- Alerte plafond dépassé -->
                        <div class="lb-alert" id="lb-alert">
                            <div class="lb-alert__icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                            <div><strong style="color:#991b1b;">Plafond dépassé</strong><p style="margin:.15rem 0 0;font-size:.82rem;color:#b91c1c;">Total : <span id="lb-alert-total" style="font-weight:700;">0</span> FCFA — Plafond : <span id="lb-alert-ceiling" style="font-weight:700;">0</span> FCFA</p></div>
                        </div>

                        <!-- ══ STATS — fond blanc, accents colorés ══ -->
                        <div class="lb-stats">

                            <!-- Total prévisionnel — Vert -->
                            <div class="lb-stat-card lb-stat-card--total">
                                <div class="lb-stat-card__header">
                                    <div class="lb-stat-card__icon">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                                    </div>
                                    <span class="lb-stat-card__trend">Total</span>
                                </div>
                                <div class="lb-stat-card__label">Total prévisionnel</div>
                                <div class="lb-stat-card__value" id="lb-stat-total">0</div>
                                <div class="lb-stat-card__footer">
                                    <div class="lb-stat-card__sub-label">Budget investissement</div>
                                    <div class="lb-stat-card__sub-value">FCFA</div>
                                </div>
                            </div>

                            <!-- Lignes actives — Bleu -->
                            <div class="lb-stat-card lb-stat-card--lines">
                                <div class="lb-stat-card__header">
                                    <div class="lb-stat-card__icon">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
                                    </div>
                                    <span class="lb-stat-card__trend">Actives</span>
                                </div>
                                <div class="lb-stat-card__label">Lignes actives</div>
                                <div class="lb-stat-card__value" id="lb-stat-count">0</div>
                                <div class="lb-stat-card__footer">
                                    <div class="lb-stat-card__sub-label">Moyenne par ligne</div>
                                    <div class="lb-stat-card__sub-value" id="lb-stat-avg">— FCFA</div>
                                </div>
                            </div>

                            <!-- Lignes verrouillées — Violet -->
                            <div class="lb-stat-card lb-stat-card--locked">
                                <div class="lb-stat-card__header">
                                    <div class="lb-stat-card__icon">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                                    </div>
                                    <span class="lb-stat-card__trend">Verrouillées</span>
                                </div>
                                <div class="lb-stat-card__label">Lignes verrouillées</div>
                                <div class="lb-stat-card__value" id="lb-stat-locked">0</div>
                                <div class="lb-stat-card__footer">
                                    <div class="lb-stat-card__sub-label">Modifiables</div>
                                    <div class="lb-stat-card__sub-value" id="lb-stat-editable">0 ligne(s)</div>
                                </div>
                            </div>

                            <!-- Plafond — Ambre -->
                            <div class="lb-stat-card lb-stat-card--ceil">
                                <div class="lb-stat-card__header">
                                    <div class="lb-stat-card__icon">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
                                    </div>
                                    <span class="lb-stat-card__trend">Plafond</span>
                                </div>
                                <div class="lb-stat-card__label">Plafond autorisé</div>
                                <div class="lb-stat-card__value" id="lb-stat-ceiling" style="font-size:1.6rem;">0</div>
                                <div class="lb-ceil-bar">
                                    <div class="lb-ceil-fill lb-ceil-fill--ok" id="lb-progress" style="width:0%"></div>
                                </div>
                                <div class="lb-ceil-meta">
                                    <span>Consommé : <strong id="lb-consumed">0</strong> FCFA</span>
                                    <strong id="lb-percent">0%</strong>
                                </div>
                            </div>

                        </div><!-- /.lb-stats -->

                        <!-- ══ TABLEAU ══ -->
                        <div class="lb-card">
                            <div class="lb-card-body" style="padding-bottom:.5rem;">
                                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1rem;">
                                    <div style="display:flex;align-items:center;gap:.75rem;">
                                        <div style="font-size:.95rem;font-weight:700;color:#374151;">Lignes budgétaires</div>
                                        <span id="lb-badge-locked" style="display:none;align-items:center;gap:.3rem;background:#ede9fe;color:#6d28d9;font-size:.68rem;font-weight:700;padding:.2rem .6rem;border-radius:20px;border:1px solid #ddd6fe;">
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="#6d28d9"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                                            <span id="lb-badge-locked-count">0</span> verrouillée(s)
                                        </span>
                                    </div>
                                    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                                        <select id="lb-filter-nature" class="lb-inp" style="min-width:200px;padding:.45rem .85rem;font-size:.82rem;">
                                            <option value="">Tous les types d'investissement</option>
                                        </select>

                                    </div>
                                </div>
                            </div>
                            <!-- DataTable — dom géré par le JS : lb-dt-top (recherche) + lb-dt-bottom (info + pagination) -->
                            <div class="lb-table-wrap" style="padding:0 1.25rem 1.25rem;">
                                <table id="lb-table" class="display" style="width:100%;"></table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="footer py-4 d-flex flex-lg-column" id="kt_footer">
                <div class="container-fluid d-flex flex-column flex-md-row align-items-center justify-content-between">
                    <div class="text-dark order-2 order-md-1"><span class="text-muted fw-bold me-1"><?php echo date('Y'); ?>©</span><a href="https://univ.uahb.sn/" target="_blank" class="text-gray-800 text-hover-primary">CRIAT</a></div>
                </div>
            </div>
        </div>
    </div></div>

<!-- ═══ MODALE ═══ -->
<div class="lb-modal-overlay" id="lb-modal">
    <div class="lb-modal">
        <div class="lb-modal-header">
            <h2 id="lb-modal-title">Nouvelle ligne budgétaire</h2>
            <button id="lb-modal-close"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="lb-modal-body">
            <form id="lb-form">
                <input type="hidden" id="lb-budget-id"/>
                <input type="hidden" id="lb-line-id"/>

                <div class="lb-field">
                    <label class="lb-lbl" for="lb-service">Service</label>
                    <select id="lb-service" class="lb-inp"><option value="">Sélectionner un service (optionnel)</option></select>
                </div>

                <div class="lb-field">
                    <label class="lb-lbl" for="lb-nature">Type d'investissement <span style="color:#ef4444;">*</span></label>
                    <select id="lb-nature" class="lb-inp"><option value="">Sélectionner le type d'investissement</option></select>
                    <p class="lb-err-msg" id="err-nature"></p>
                </div>

                <div class="lb-field lb-hidden" id="lb-designation-block">
                    <label class="lb-lbl" for="lb-designation">Désignation <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="lb-designation" class="lb-inp" placeholder="Désignation de la ligne..."/>
                    <p class="lb-err-msg" id="err-designation"></p>
                </div>

                <div class="lb-grid-2 lb-hidden" id="lb-rub-block">
                    <div>
                        <label class="lb-lbl" for="lb-categorie">Rubrique <span style="color:#ef4444;">*</span></label>
                        <div style="display:flex;gap:.4rem;">
                            <select id="lb-categorie" class="lb-inp" style="flex:1;"><option value="">Choisir une rubrique</option></select>
                            <button type="button" id="lb-create-rub" class="lb-btn lb-btn--ghost" style="padding:.5rem .65rem;flex-shrink:0;min-width:36px;" title="Nouvelle rubrique">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            </button>
                        </div>
                        <p class="lb-err-msg" id="err-categorie"></p>
                    </div>
                    <div>
                        <label class="lb-lbl" for="lb-sous-rubrique">Sous-rubrique <span style="color:#ef4444;">*</span></label>
                        <div style="display:flex;gap:.4rem;">
                            <select id="lb-sous-rubrique" class="lb-inp" style="flex:1;"><option value="">Choisir une sous-rubrique</option></select>
                            <button type="button" id="lb-create-subrub" class="lb-btn lb-btn--ghost" style="padding:.5rem .65rem;flex-shrink:0;min-width:36px;" title="Nouvelle sous-rubrique">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            </button>
                        </div>
                        <p class="lb-err-msg" id="err-sous-rubrique"></p>
                    </div>
                </div>

                <div class="lb-hidden" id="lb-qte-pu-row">
                    <div class="lb-grid-2" style="margin-bottom:.85rem;">
                        <div id="lb-qte-block">
                            <label class="lb-lbl" for="lb-qte">Quantité <span style="color:#ef4444;">*</span></label>
                            <input type="number" id="lb-qte" class="lb-inp" min="1" placeholder="Ex : 10"/>
                            <p class="lb-err-msg" id="err-qte"></p>
                        </div>
                        <div id="lb-unite-block">
                            <label class="lb-lbl" for="lb-unite">Unité <span style="color:#ef4444;">*</span></label>
                            <select id="lb-unite" class="lb-inp"><option value="">Sélectionner une unité *</option></select>
                            <p class="lb-err-msg" id="err-unite"></p>
                        </div>
                    </div>
                    <div id="lb-pu-block">
                        <label class="lb-lbl" for="lb-prix">Prix unitaire (FCFA) <span style="color:#ef4444;">*</span></label>
                        <input type="number" id="lb-prix" class="lb-inp" min="0" step="0.01" placeholder="0"/>
                        <p class="lb-err-msg" id="err-prix"></p>
                    </div>
                </div>

                <div class="lb-grid-2 lb-hidden" id="lb-autre-row">
                    <div id="lb-nombre-block">
                        <label class="lb-lbl" for="lb-nombre">Nombre <span style="color:#ef4444;">*</span></label>
                        <input type="number" id="lb-nombre" class="lb-inp" min="1" placeholder="Ex : 12"/>
                        <p class="lb-err-msg" id="err-nombre"></p>
                    </div>
                    <div id="lb-montant-block">
                        <label class="lb-lbl" for="lb-montant">Montant total (FCFA) <span style="color:#ef4444;">*</span></label>
                        <input type="number" id="lb-montant" class="lb-inp" min="0" step="0.01" placeholder="0"/>
                        <p class="lb-err-msg" id="err-montant"></p>
                    </div>
                </div>

                <div class="lb-field lb-hidden" id="lb-description-block">
                    <label class="lb-lbl" for="lb-description">Description <span style="font-weight:400;text-transform:none;font-size:.68rem;">(optionnel)</span></label>
                    <textarea id="lb-description" class="lb-inp" rows="2" placeholder="Description supplémentaire..."></textarea>
                </div>

                <div class="lb-field lb-hidden" id="lb-periode-block">
                    <label class="lb-lbl">Période d'utilisation <span style="color:#ef4444;">*</span></label>
                    <div class="lb-grid-2">
                        <div>
                            <label class="lb-lbl" style="font-size:.65rem;">Début <span style="color:#ef4444;">*</span></label>
                            <select id="lb-periode-debut" class="lb-inp">
                                <option value="">Mois de début</option>
                                <option>Janvier</option><option>Février</option><option>Mars</option><option>Avril</option>
                                <option>Mai</option><option>Juin</option><option>Juillet</option><option>Août</option>
                                <option>Septembre</option><option>Octobre</option><option>Novembre</option><option>Décembre</option>
                            </select>
                            <p class="lb-err-msg" id="err-periode"></p>
                        </div>
                        <div>
                            <label class="lb-lbl" style="font-size:.65rem;">Fin <span style="color:#ef4444;">*</span></label>
                            <select id="lb-periode-fin" class="lb-inp">
                                <option value="">Mois de fin</option>
                                <option>Janvier</option><option>Février</option><option>Mars</option><option>Avril</option>
                                <option>Mai</option><option>Juin</option><option>Juillet</option><option>Août</option>
                                <option>Septembre</option><option>Octobre</option><option>Novembre</option><option>Décembre</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:.6rem;padding-top:.75rem;border-top:1px solid #f3f4f6;margin-top:.25rem;">
                    <button type="button" id="lb-cancel" class="lb-btn lb-btn--ghost">Annuler</button>
                    <button type="submit" class="lb-btn lb-btn--primary">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v14a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts Metronic -->
<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script src="/personnel/scripts.bundle.gs.js"></script>

<script>window.LB_BUDGET_TOKEN = <?php echo json_encode($budgetToken); ?>;</script>
<script src="/personnel/basi-scripts.bundle.6.js"></script>
</body>
</html>