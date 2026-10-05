<?php

// Token chiffré de la demande, transmis par le bouton "Voir" de la liste des demandes.
$demandeToken = isset($_GET['token']) ? trim($_GET['token']) : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Voir la demande — ENT GSJLF</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/fullcalendar/fullcalendar.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"/>
    <link href="/personnel/ressources/dist_assets/css/style_basi_29.css" rel="stylesheet" type="text/css"/>

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
                <a href="/responsable-financier-accueil" style="margin-left:65px;" id="lien_logo1">
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
                        <a href="/responsable-financier-accueil" class="d-lg-none" id="lien_logo2">
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

                        <!-- ══ BANDEAU TITRE ══ -->
                        <div class="vd-hero">
                            <div class="vd-hero-left">
                                <button id="vd-btn-retour" class="vd-back">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                                    </svg>
                                    Retour
                                </button>
                                <div class="vd-hero-title">
                                    <h1><span id="vd-titre">Chargement…</span></h1>
                                    <p id="vd-sous-titre"></p>
                                </div>
                            </div>
                            <div class="vd-hero-right">
                                <span id="vd-badge-type"></span>
                            </div>
                        </div>

                        <div class="vd-card">

                            <!-- Barre d'outils : sélection + actions groupées -->
                            <div class="vd-toolbar">
                                <div class="vd-toolbar__selection" id="vd-selection-label">Aucune ligne sélectionnée</div>

                                <!-- Actions Achat -->
                                <div class="vd-toolbar__actions" id="vd-toolbar-achat" style="display:none;">
                                    <button type="button" class="vd-btn vd-btn--commande" id="btnPasserCommande" disabled>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
                                        Passer commande
                                    </button>
                                    <button type="button" class="vd-btn vd-btn--proforma" id="btnDemandeFacture" disabled>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        Demande de facture
                                    </button>
                                </div>

                                <!-- Actions Paiement -->
                                <div class="vd-toolbar__actions" id="vd-toolbar-paiement" style="display:none;">
                                    <button type="button" class="vd-btn vd-btn--paiement" id="btnPasserPaiement" disabled>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                        Passer au paiement
                                    </button>
                                </div>
                            </div>

                            <!-- Table Achat -->
                            <div class="vd-table-wrap" id="vd-panel-achat" style="display:none;">
                                <table id="vd-table-achat" class="display" style="width:100%;">
                                    <thead>
                                    <tr>
                                        <th><input type="checkbox" class="vd-checkbox" id="checkAllAchat"/></th>
                                        <th>Désignation</th>
                                        <th>Qté demandée</th>
                                        <th>Qté commandée</th>
                                        <th>Qté restante</th>
                                        <th>Prix unitaire</th>
                                        <th>Montant total</th>
                                        <th>Rubrique</th>
                                        <th>Sous-rubrique</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <!-- Table Paiement -->
                            <div class="vd-table-wrap" id="vd-panel-paiement" style="display:none;">
                                <table id="vd-table-paiement" class="display" style="width:100%;">
                                    <thead>
                                    <tr>
                                        <th><input type="checkbox" class="vd-checkbox" id="checkAllPaiement"/></th>
                                        <th>Désignation</th>
                                        <th>Montant à payer</th>
                                        <th>Montant payé</th>
                                        <th>Montant restant</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                        </div><!-- /.vd-card -->

                        <!-- MODALE : Passer commande -->
                        <div class="modal fade vd-modal vd-modal--large" id="modalPasserCommande" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2>Passer commande</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">

                                        <div id="pcErreurGenerale" class="vd-pc-erreur-generale" style="display:none;"></div>

                                        <!-- ══ BLOC 1 : Informations générales ══ -->
                                        <div class="vd-pc-bloc">
                                            <h3 class="vd-pc-bloc-title">1. Informations générales</h3>
                                            <div class="vd-pc-grid">
                                                <div class="vd-pc-field">
                                                    <label>Mode de règlement</label>
                                                    <select class="vd-modal-inp" id="pcModeReglement">
                                                        <option value="">Chargement…</option>
                                                    </select>
                                                </div>
                                                <div class="vd-pc-field">
                                                    <label>Modalité de paiement</label>
                                                    <select class="vd-modal-inp" id="pcModePaiement">
                                                        <option value="">Chargement…</option>
                                                    </select>
                                                </div>
                                                <div class="vd-pc-field" id="pcNbTranchesWrap" style="display:none;">
                                                    <label>Nombre de tranches</label>
                                                    <input type="number" class="vd-modal-inp" id="pcNbTranches" min="2" step="1" placeholder="ex : 2"/>
                                                </div>
                                            </div>

                                            <!-- Champs de tranches générés dynamiquement -->
                                            <div id="pcTranchesWrap" class="vd-pc-tranches" style="display:none;">
                                                <div id="pcTranchesFields" class="vd-pc-tranches-fields"></div>
                                                <div id="pcTranchesErreur" class="vd-pc-tranches-erreur" style="display:none;"></div>
                                            </div>
                                        </div>

                                        <!-- ══ BLOC 2 : Lignes de commande ══ -->
                                        <!-- Le prix n'est pas saisi à cette étape : il sera renseigné plus tard. -->
                                        <div class="vd-pc-bloc">
                                            <h3 class="vd-pc-bloc-title">2. Lignes de commande</h3>
                                            <table class="vd-modal-table">
                                                <thead>
                                                <tr>
                                                    <th>Désignation</th>
                                                    <th>Unité</th>
                                                    <th>Nombre de pièces</th>
                                                    <th>Pièces par unité</th>
                                                </tr>
                                                </thead>
                                                <tbody id="bodyPasserCommande"></tbody>
                                            </table>
                                        </div>

                                        <!-- ══ BLOC 3 : Pro forma des fournisseurs ══ -->
                                        <!-- Jusqu'à 3 pro forma, chacun associé à un fournisseur obligatoire. -->
                                        <div class="vd-pc-bloc">
                                            <h3 class="vd-pc-bloc-title">3. Pro forma des fournisseurs</h3>
                                            <p class="vd-modal-hint" style="margin-bottom:.85rem;">
                                                Jusqu'à 3 factures pro forma peuvent être jointes. Chaque pro forma doit être associé à un fournisseur.
                                            </p>

                                            <div class="vd-pc-proforma-item" data-index="1">
                                                <div class="vd-pc-grid">
                                                    <div class="vd-pc-field">
                                                        <label>Pro forma 1 — Pièce jointe (PDF)</label>
                                                        <input type="file" class="vd-modal-inp pc-proforma-file" data-ordre="1" accept="application/pdf"/>
                                                    </div>
                                                    <div class="vd-pc-field">
                                                        <label>Pro forma 1 — Fournisseur</label>
                                                        <select class="vd-modal-inp pc-proforma-fournisseur" data-ordre="1">
                                                            <option value="">Chargement…</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="vd-pc-proforma-item" data-index="2">
                                                <div class="vd-pc-grid">
                                                    <div class="vd-pc-field">
                                                        <label>Pro forma 2 — Pièce jointe (PDF)</label>
                                                        <input type="file" class="vd-modal-inp pc-proforma-file" data-ordre="2" accept="application/pdf"/>
                                                    </div>
                                                    <div class="vd-pc-field">
                                                        <label>Pro forma 2 — Fournisseur</label>
                                                        <select class="vd-modal-inp pc-proforma-fournisseur" data-ordre="2">
                                                            <option value="">Chargement…</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="vd-pc-proforma-item" data-index="3">
                                                <div class="vd-pc-grid">
                                                    <div class="vd-pc-field">
                                                        <label>Pro forma 3 — Pièce jointe (PDF)</label>
                                                        <input type="file" class="vd-modal-inp pc-proforma-file" data-ordre="3" accept="application/pdf"/>
                                                    </div>
                                                    <div class="vd-pc-field">
                                                        <label>Pro forma 3 — Fournisseur</label>
                                                        <select class="vd-modal-inp pc-proforma-fournisseur" data-ordre="3">
                                                            <option value="">Chargement…</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                    <div class="vd-actions">
                                        <button type="button" class="vd-cancel" data-bs-dismiss="modal">Annuler</button>
                                        <button type="button" class="vd-submit" id="submitPasserCommande">
                                            <span>Confirmer</span>
                                            <svg class="vd-spinner hidden" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 019.76 7.8"/></svg>
                                        </button>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- MODALE : Demande de facture (sélection de 3 fournisseurs) -->
                        <div class="modal fade vd-modal" id="modalDemandeFacture" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2>Demande de facture pro forma</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">
                                        <div id="dfErreurGenerale" class="vd-pc-erreur-generale" style="display:none;"></div>
                                        <p class="vd-modal-hint" style="margin-bottom:.85rem;">
                                            Sélectionnez exactement <strong>3 fournisseurs</strong> à qui envoyer cette demande de facture pro forma pour les lignes sélectionnées.
                                        </p>
                                        <div id="dfListeFournisseurs" class="vd-df-liste">
                                            <p class="vd-cell-muted">Chargement des fournisseurs…</p>
                                        </div>
                                        <div class="vd-df-compteur">
                                            <span id="dfCompteur">0</span> / 3 fournisseur(s) sélectionné(s)
                                        </div>
                                    </div>
                                    <div class="vd-actions">
                                        <button type="button" class="vd-cancel" data-bs-dismiss="modal">Annuler</button>
                                        <button type="button" class="vd-submit" id="submitDemandeFacture">
                                            <span>Valider</span>
                                            <svg class="vd-spinner hidden" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 019.76 7.8"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- MODALE : Passer au paiement -->
                        <div class="modal fade vd-modal vd-modal--large" id="modalPasserPaiement" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2>Passer au paiement</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">

                                        <div id="ppErreurGenerale" class="vd-pc-erreur-generale" style="display:none;"></div>

                                        <!-- ══ BLOC 1 : Informations générales ══ -->
                                        <div class="vd-pc-bloc">
                                            <h3 class="vd-pc-bloc-title">1. Informations générales</h3>
                                            <div class="vd-pc-grid">
                                                <div class="vd-pc-field">
                                                    <label>Mode de règlement</label>
                                                    <select class="vd-modal-inp" id="ppModeReglement">
                                                        <option value="">Chargement…</option>
                                                    </select>
                                                </div>
                                                <div class="vd-pc-field">
                                                    <label>Modalité de paiement</label>
                                                    <select class="vd-modal-inp" id="ppModePaiement">
                                                        <option value="">Chargement…</option>
                                                    </select>
                                                </div>
                                                <div class="vd-pc-field" id="ppNbTranchesWrap" style="display:none;">
                                                    <label>Nombre de tranches</label>
                                                    <input type="number" class="vd-modal-inp" id="ppNbTranches" min="2" step="1" placeholder="ex : 2"/>
                                                </div>
                                            </div>

                                            <!-- Champs de tranches générés dynamiquement -->
                                            <div id="ppTranchesWrap" class="vd-pc-tranches" style="display:none;">
                                                <div id="ppTranchesFields" class="vd-pc-tranches-fields"></div>
                                                <div id="ppTranchesErreur" class="vd-pc-tranches-erreur" style="display:none;"></div>
                                            </div>
                                        </div>

                                        <!-- ══ BLOC 2 : Lignes de paiement ══ -->
                                        <div class="vd-pc-bloc">
                                            <h3 class="vd-pc-bloc-title">2. Lignes de paiement</h3>
                                            <table class="vd-modal-table">
                                                <thead>
                                                <tr>
                                                    <th>Désignation</th>
                                                    <th>Montant</th>
                                                </tr>
                                                </thead>
                                                <tbody id="bodyPasserPaiement"></tbody>
                                            </table>
                                            <div class="vd-pc-total">
                                                <span class="lbl">Montant total :</span>
                                                <span class="val" id="ppMontantTotal">0&nbsp;FCFA</span>
                                            </div>
                                        </div>

                                        <!-- ══ BLOC 3 : Justificatifs de paiement ══ -->
                                        <div class="vd-pc-bloc">
                                            <h3 class="vd-pc-bloc-title">3. Justificatifs de paiement</h3>
                                            <div class="vd-pc-field">
                                                <label>Pièces jointes (PDF, JPG ou PNG — un ou plusieurs fichiers)</label>
                                                <input type="file" class="vd-modal-inp" id="ppJustificatifs" accept="application/pdf,image/jpeg,image/png" multiple/>
                                                <p class="vd-modal-hint">Vous pouvez sélectionner plusieurs fichiers à la fois.</p>
                                                <ul id="ppListeJustificatifs" class="vd-pc-fichiers-liste"></ul>
                                            </div>
                                        </div>

                                    </div>
                                    <div class="vd-actions">
                                        <button type="button" class="vd-cancel" data-bs-dismiss="modal">Annuler</button>
                                        <button type="button" class="vd-submit" id="submitPasserPaiement">
                                            <span>Confirmer</span>
                                            <svg class="vd-spinner hidden" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 019.76 7.8"/></svg>
                                        </button>
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


<noscript>
    <style>html.ld-booting body > .d-flex.flex-column.flex-root { visibility: visible !important; }</style>
</noscript>

<!-- Scripts Metronic (jQuery + DataTables + SweetAlert2 déjà inclus) -->
<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script src="/personnel/scripts.bundle.gs.js"></script>

<!-- Token de la demande (chiffré) — géré par le JS -->
<script>window.VD_DEMANDE_TOKEN = <?php echo json_encode($demandeToken); ?>;</script>

<script src="/personnel/basi-scripts.bundle.16.js"></script>

</body>
</html>