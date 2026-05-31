<?php

session_start();

?>
<!DOCTYPE html>

<html lang="fr">

<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>ENT — GSJLF</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/fullcalendar/fullcalendar.bundle.css" rel="stylesheet"
          type="text/css"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet"
          type="text/css"/>
    <link href="/personnel/ressources/dist_assets/css/ps-style.css" rel="stylesheet" type="text/css"/>

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
                <a href="/personnel/admin-controller" style="margin-left: 65px;">
                    <img alt="Logo" src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"
                         class="h-50px logo"/>
                </a>
                <div id="kt_aside_toggle" class="btn btn-icon w-auto px-0 btn-active-color-primary aside-toggle"
                     data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body"
                     data-kt-toggle-name="aside-minimize">
                        <span class="svg-icon svg-icon-1 rotate-180">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                 fill="none">
                                <path opacity="0.5"
                                      d="M14.2657 11.4343L18.45 7.25C18.8642 6.83579 18.8642 6.16421 18.45 5.75C18.0358 5.33579 17.3642 5.33579 16.95 5.75L11.4071 11.2929C11.0166 11.6834 11.0166 12.3166 11.4071 12.7071L16.95 18.25C17.3642 18.6642 18.0358 18.6642 18.45 18.25C18.8642 17.8358 18.8642 17.1642 18.45 16.75L14.2657 12.5657C13.9533 12.2533 13.9533 11.7467 14.2657 11.4343Z"
                                      fill="black"/>
                                <path
                                        d="M8.2657 11.4343L12.45 7.25C12.8642 6.83579 12.8642 6.16421 12.45 5.75C12.0358 5.33579 11.3642 5.33579 10.95 5.75L5.40712 11.2929C5.01659 11.6834 5.01659 12.3166 5.40712 12.7071L10.95 18.25C11.3642 18.6642 12.0358 18.6642 12.45 18.25C12.8642 17.8358 12.8642 17.1642 12.45 16.75L8.2657 12.5657C7.95328 12.2533 7.95328 11.7467 8.2657 11.4343Z"
                                        fill="black"/>
                            </svg>
                        </span>
                </div>
            </div>
            <div class="aside-menu flex-column-fluid">
                <div class="hover-scroll-overlay-y my-5 my-lg-5" id="kt_aside_menu_wrapper" data-kt-scroll="true"
                     data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-height="auto"
                     data-kt-scroll-dependencies="#kt_aside_logo, #kt_aside_footer"
                     data-kt-scroll-wrappers="#kt_aside_menu" data-kt-scroll-offset="0">
                    <div class="menu menu-column menu-title-gray-800 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-500"
                         id="#kt_aside_menu" data-kt-menu="true">
                        <div class="menu-item">
                            <div class="menu-content pb-2">
                                <span class="menu-section text-muted text-uppercase fs-8 ls-1">Dashboard</span>
                            </div>
                        </div>
                        <div class="menu-item">
                            <a class="menu-link" href="/personnel/reponsable-financier-paiement">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <rect x="2" y="2" width="9" height="9" rx="2" fill="black"/>
                                                <rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2"
                                                      fill="black"/>
                                                <rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2"
                                                      fill="black"/>
                                                <rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2"
                                                      fill="black"/>
                                            </svg>
                                        </span>
                                    </span>
                                <span class="menu-title">Dashboard</span>
                            </a>
                        </div>

                        <div class="menu-item">
                            <div class="menu-content pb-2">
                                <span class="menu-section text-muted text-uppercase fs-8 ls-1">Dashboard</span>
                            </div>
                        </div>
                        <div class="menu-item">
                            <a class="menu-link" href="/personnel/reponsable-financier-paiement">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <rect x="2" y="2" width="9" height="9" rx="2" fill="black"/>
                                                <rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2"
                                                      fill="black"/>
                                                <rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2"
                                                      fill="black"/>
                                                <rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2"
                                                      fill="black"/>
                                            </svg>
                                        </span>
                                    </span>Liste des utilisateurs
                                <span class="menu-title"></span>
                            </a>
                        </div>

                        <div class="menu-item">
                            <a class="menu-link" href="/personnel/reponsable-financier-paiement">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <rect x="2" y="2" width="9" height="9" rx="2" fill="black"/>
                                                <rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2"
                                                      fill="black"/>
                                                <rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2"
                                                      fill="black"/>
                                                <rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2"
                                                      fill="black"/>
                                            </svg>
                                        </span>
                                    </span>Liste des sous-menus
                                <span class="menu-title"></span>
                            </a>
                        </div>

                        <div class="menu-item">
                            <a class="menu-link" href="/personnel/reponsable-financier-paiement">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <rect x="2" y="2" width="9" height="9" rx="2" fill="black"/>
                                                <rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2"
                                                      fill="black"/>
                                                <rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2"
                                                      fill="black"/>
                                                <rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2"
                                                      fill="black"/>
                                            </svg>
                                        </span>
                                    </span>Liste des taches
                                <span class="menu-title"></span>
                            </a>
                        </div>

                        <div class="menu-item">
                            <a class="menu-link" href="/personnel/reponsable-financier-paiement">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <rect x="2" y="2" width="9" height="9" rx="2" fill="black"/>
                                                <rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2"
                                                      fill="black"/>
                                                <rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2"
                                                      fill="black"/>
                                                <rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2"
                                                      fill="black"/>
                                            </svg>
                                        </span>
                                    </span>Liste des taches post
                                <span class="menu-title"></span>
                            </a>
                        </div>



                    </div>
                </div>
            </div>
        </div>
        <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
            <div id="kt_header" class="header align-items-stretch">
                <div class="container-fluid d-flex align-items-stretch justify-content-between">
                    <div class="d-flex align-items-center d-lg-none ms-n3 me-1" title="Show aside menu">
                        <div class="btn btn-icon btn-active-light-primary w-30px h-30px w-md-40px h-md-40px"
                             id="kt_aside_mobile_toggle">
                                <span class="svg-icon svg-icon-2x mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                         fill="none">
                                        <path
                                                d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z"
                                                fill="black"/>
                                        <path opacity="0.3"
                                              d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z"
                                              fill="black"/>
                                    </svg>
                                </span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
                        <a href="/personnel/admin-controller" class="d-lg-none">
                            <img alt="Logo" src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"
                                 class="h-30px"/>
                        </a>
                    </div>
                    <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1">
                        <div class="d-flex align-items-stretch" id="kt_header_nav">
                            <div class="header-menu align-items-stretch" data-kt-drawer="true"
                                 data-kt-drawer-name="header-menu"
                                 data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true"
                                 data-kt-drawer-width="{default:'200px', '300px': '250px'}"
                                 data-kt-drawer-direction="end" data-kt-drawer-toggle="#kt_header_menu_mobile_toggle"
                                 data-kt-swapper="true" data-kt-swapper-mode="prepend"
                                 data-kt-swapper-parent="{default: '#kt_body', lg: '#kt_header_nav'}">
                                <div class="menu menu-lg-rounded menu-column menu-lg-row menu-state-bg menu-title-gray-700 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-400 fw-bold my-5 my-lg-0 align-items-stretch"
                                     id="#kt_header_menu" data-kt-menu="true">
                                    <div class="menu-item me-lg-1">
                                        <a class="menu-link py-3" href="/personnel/admin-controller">
                                            <h3><span class="menu-title">Environnement Numérique de Travail</span>
                                            </h3>

                                        </a>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-stretch flex-shrink-0">
                            <div class="d-flex align-items-stretch flex-shrink-0">
                                <div class="d-flex align-items-center ms-1 ms-lg-3" id="kt_header_user_menu_toggle">
                                    <div class="cursor-pointer symbol symbol-30px symbol-md-40px"
                                         data-kt-menu-trigger="click" data-kt-menu-attach="parent"
                                         data-kt-menu-placement="bottom-end">
                                        <img src="<?php if (!empty($_SESSION['tmpPhoto'])) {
                                            echo "/personnel/assets/images/ent/" . strtolower($_SESSION['tmpPhoto']);
                                        } else {
                                            echo "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";
                                        } ?>"/>
                                    </div>
                                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-primary fw-bold py-4 fs-6 w-275px"
                                         data-kt-menu="true">
                                        <div class="menu-item px-3">
                                            <div class="menu-content d-flex align-items-center px-3">
                                                <div class="symbol symbol-50px me-5">
                                                    <img alt="admin" src="<?php if (!empty($_SESSION['tmpPhoto'])) {
                                                        echo "/personnel/assets/images/ent/" . strtolower($_SESSION['tmpPhoto']);
                                                    } else {
                                                        echo "/personnel/ressources/dist_assets/media/avatars/150-26.jpg";
                                                    } ?>"/>
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <div class="fw-bolder d-flex align-items-center fs-5"><?php if (!empty($_SESSION['tmpPrenom'])) {
                                                            echo ucwords(strtolower($_SESSION['tmpPrenom'])) . ' ' . strtoupper($_SESSION['tmpNom']);
                                                        } else {
                                                            echo "test";
                                                        } ?>
                                                    </div>
                                                    <a href="#"
                                                       class="fw-bold text-muted text-hover-primary fs-7"><?php if (!empty($_SESSION['tmpEmail'])) {
                                                            echo strtolower($_SESSION['tmpEmail']);
                                                        } else {
                                                            echo "test";
                                                        } ?></a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="separator my-2"></div>
                                        <div class="menu-item px-5">
                                            <a href="/personnel-espace-etudiant/infos-academiques"
                                               class="menu-link px-5">Mon profil</a>
                                        </div>
                                        <div class="separator my-2"></div>
                                        <div class="menu-item px-5">
                                            <div class="menu-content px-5">
                                                <label
                                                        class="form-check form-switch form-check-custom form-check-solid pulse pulse-success"
                                                        for="kt_user_menu_dark_mode_toggle">
                                                    <input class="form-check-input w-30px h-20px" type="checkbox"
                                                           value="1" name="mode" id="kt_user_menu_dark_mode_toggle"
                                                           data-kt-url="/personnel/quitter"/>
                                                    <span class="pulse-ring ms-n1"></span>
                                                    <span class="form-check-label text-gray-600 fs-7">Se
                                                            déconnecter</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center d-lg-none ms-2 me-n3"
                                     title="Show header menu">
                                    <div class="btn btn-icon btn-active-light-primary w-30px h-30px w-md-40px h-md-40px"
                                         id="kt_header_menu_mobile_toggle">
                                            <span class="svg-icon svg-icon-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                     viewBox="0 0 24 24" fill="none">
                                                    <path
                                                            d="M13 11H3C2.4 11 2 10.6 2 10V9C2 8.4 2.4 8 3 8H13C13.6 8 14 8.4 14 9V10C14 10.6 13.6 11 13 11ZM22 5V4C22 3.4 21.6 3 21 3H3C2.4 3 2 3.4 2 4V5C2 5.6 2.4 6 3 6H21C21.6 6 22 5.6 22 5Z"
                                                            fill="black"/>
                                                    <path opacity="0.3"
                                                          d="M21 16H3C2.4 16 2 15.6 2 15V14C2 13.4 2.4 13 3 13H21C21.6 13 22 13.4 22 14V15C22 15.6 21.6 16 21 16ZM14 20V19C14 18.4 13.6 18 13 18H3C2.4 18 2 18.4 2 19V20C2 20.6 2.4 21 3 21H13C13.6 21 14 20.6 14 20Z"
                                                          fill="black"/>
                                                </svg>
                                            </span>
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
                        <div data-kt-swapper="true" data-kt-swapper-mode="prepend"
                             data-kt-swapper-parent="{default: '#kt_content_container', 'lg': '#kt_toolbar_container'}"
                             class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
                            <h1 class="d-flex align-items-center text-dark fw-bolder fs-3 my-1">Admin
                            </h1>
                            <span class="h-20px border-gray-200 border-start mx-4"></span>
                            <ul class="breadcrumb breadcrumb-separatorless fw-bold fs-7 my-1">
                                <li class="breadcrumb-item text-muted">

                                    <a href="/personnel/comptable-accueil"
                                       class="text-muted text-hover-primary">accueil</a>
                                </li>
                                <span class="h-20px border-gray-200 border-start mx-4"></span>

                                <li class="breadcrumb-item text-muted">
                                    <span class="text-muted text-hover-primary">liste des utilisateurs</span>
                                </li>

                            </ul>
                        </div>
                    </div>
                </div>
                <div class="post d-flex flex-column-fluid" id="kt_post">
                    <div id="formEleves" class="container-xxl">


                        <div class="col-sm-12 mb-5">
                            <div class="ps1-card">

                                <!-- Bloc gauche — label + total -->
                                <div class="ps1-card__left">
                                    <div class="ps1-card__eyebrow">
                                        <span class="ps1-card__dot"></span>
                                        Nombre d’utilisateurs
                                    </div>
                                    <div class="ps1-card__main">
                                        <span class="ps1-card__number" id="ps1-total">...</span>
                                        <span class="ps1-card__unit">utilisateurs</span>
                                    </div>
                                </div>

                                <!-- Séparateur vertical -->
                                <div class="ps1-card__vsep"></div>

                                <!-- Bloc milieu — Actifs -->
                                <div class="ps1-card__stat ps1-card__stat--actifs">
                                    <span class="ps1-card__stat-num" id="ps1-actifs">...</span>
                                    <span class="ps1-card__stat-label">Actifs</span>
                                    <span class="ps1-card__stat-pct" id="ps1-pct-actifs">0%</span>
                                </div>

                                <!-- Séparateur vertical -->
                                <div class="ps1-card__vsep"></div>

                                <!-- Bloc droite — Inactifs -->
                                <div class="ps1-card__stat ps1-card__stat--inactifs">
                                    <span class="ps1-card__stat-num" id="ps1-inactifs">...</span>
                                    <span class="ps1-card__stat-label">Inactif</span>
                                    <span class="ps1-card__stat-pct" id="ps1-pct-inactifs">0%</span>
                                </div>


                                <!-- Séparateur vertical -->
                                <div class="ps1-card__vsep"></div>

                                <!-- Bloc droite — Inactifs -->
                                <div class="ps1-card__stat ps1-card__stat--bloquer">
                                    <span class="ps1-card__stat-num" id="ps1-bloquer">...</span>
                                    <span class="ps1-card__stat-label">Bloqués</span>
                                    <span class="ps1-card__stat-pct" id="ps1-pct-bloquer">0%</span>
                                </div>


                                <!-- Barre verticale de progression -->
                                <div class="ps1-card__bar">
                                    <div id="ps1-bar-fill" class="ps1-card__bar-fill"></div>
                                </div>

                            </div>

                        </div>


                        <div class="col sm-12" id="boxTable">
                            <div class="card card-xl-stretch mb-5 mb-xl-8">


                                <div class="card-body py-3">
                                    <div class="py-5">
                                        <div class="d-flex flex-stack mb-5">
                                            <div class="d-flex align-items-center position-relative my-1">
                                                    <span class="svg-icon svg-icon-1 position-absolute ms-6">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                             viewBox="0 0 24 24" fill="none">
                                                            <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546"
                                                                  height="2" rx="1"
                                                                  transform="rotate(45 17.0365 15.1223)"
                                                                  fill="black"/>
                                                            <path
                                                                    d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z"
                                                                    fill="black"/>
                                                        </svg>
                                                    </span>
                                                <input type="text" data-kt-docs-table-filter="search"
                                                       class="form-control form-control-solid w-250px ps-15"
                                                       placeholder="Rechercher" id="rechercher"/>
                                            </div>

                                            <div class="d-flex justify-content-end"
                                                 data-kt-docs-table-toolbar="base">
                                            </div>
                                            <div class="d-flex justify-content-end align-items-center"
                                                 data-kt-docs-table-toolbar="selected">
                                                <a href="javascript:void(0)"
                                                   class="btn btn-sm btn-light btn-active-primary"
                                                   onclick="ajouterUtilisateur()">
                                                <span class="svg-icon svg-icon-3">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                         viewBox="0 0 24 24" fill="none">
                                                        <rect opacity="0.5" x="11.364" y="20.364" width="16"
                                                              height="2" rx="1" transform="rotate(-90 11.364 20.364)"
                                                              fill="black"/>
                                                        <rect x="4.36396" y="11.364" width="16" height="2" rx="1"
                                                              fill="black"/>
                                                    </svg>
                                                </span>

                                                    Ajouter un utilisateur</a>
                                            </div>
                                        </div>
                                        <table id="kt_datatable_utilisateurs"
                                               class="table align-middle table-row-dashed fs-6 gy-5">
                                            <thead>
                                            <tr
                                                    class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">

                                                <th>Photo</th>
                                                <th>Matricule</th>
                                                <th>Prénoms</th>
                                                <th>Noms</th>
                                                <th>Qualification</th>
                                                <th>Affectation</th>
                                                <th>Poste</th>
                                                <th>Email</th>
                                                <th>Date création</th>
                                                <th>État</th>
                                                <th>Actions</th>
                                            </tr>
                                            </thead>
                                            <tbody class="text-gray-600 fw-bold"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="footer py-4 d-flex flex-lg-column" id="kt_footer">
                <div
                        class="container-fluid d-flex flex-column flex-md-row align-items-center justify-content-between">
                    <div class="text-dark order-2 order-md-1">
                            <span class="text-muted fw-bold me-1">
                                <script type="text/javascript">
                                    document.write(new Date().getFullYear())
                                </script>©
                            </span>
                        <a href="https://univ.uahb.sn/" target="_blank"
                           class="text-gray-800 text-hover-primary">CRIAT</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="kt_scrolltop" class="scrolltop" data-kt-scrolltop="true">
        <span class="svg-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                <rect opacity="0.5" x="13" y="6" width="13" height="2" rx="1" transform="rotate(90 13 6)"
                      fill="black"/>
                <path
                        d="M12.5657 8.56569L16.75 12.75C17.1642 13.1642 17.8358 13.1642 18.25 12.75C18.6642 12.3358 18.6642 11.6642 18.25 11.25L12.7071 5.70711C12.3166 5.31658 11.6834 5.31658 11.2929 5.70711L5.75 11.25C5.33579 11.6642 5.33579 12.3358 5.75 12.75C6.16421 13.1642 6.83579 13.1642 7.25 12.75L11.4343 8.56569C11.7467 8.25327 12.2533 8.25327 12.5657 8.56569Z"
                        fill="black"/>
            </svg>
        </span>
</div>


<div class="modal fade" id="kt_modal_add_user" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
     data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajouter un utilisateur</h2>
                <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal"
                     onclick="closeAddUser()">
                    <span class="svg-icon svg-icon-1">
														<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                             viewBox="0 0 24 24" fill="none">
															<rect opacity="0.5" x="6" y="17.3137" width="16" height="2"
                                                                  rx="1" transform="rotate(-45 6 17.3137)"
                                                                  fill="black"/>
															<rect x="7.41422" y="6" width="16" height="2" rx="1"
                                                                  transform="rotate(45 7.41422 6)" fill="black"/>
														</svg>
													</span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">

                <div class="d-block" id="box1">
                    <form id="formAddUser" class="form" action="#">
                        <input type="hidden" name="option" value="5"/>
                        <div class="d-flex flex-column mb-7 fv-row">
                            <label class="d-flex align-items-center fs-6 fw-bold form-label mb-2">
                                <span class="required">Matricule</span>
                            </label>
                            <input type="text" class="form-control color-input" id="matricule" name="matricule"/>
                        </div>


                        <div class="text-center pt-15">
                            <button type="reset" class="btn btn-light me-3" onclick="closeAddUser()">Annuler</button>
                            <button type="submit" id="formAddUser_submit" class="btn btn-primary">
                                <span class="indicator-label">Ajouter</span>
                                <span class="indicator-progress">Veuillez patienter...
															<span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                            </button>
                        </div>
                    </form>

                </div>

                <div class="d-none" id="box2">
                    <form id="formAddUser2" class="form" action="#">
                        <input type="hidden" name="option" value="6"/>
                        <div class="d-flex flex-column mb-7 fv-row">
                            <label class="d-flex align-items-center fs-6 fw-bold form-label mb-2">
                                <span class="required">Matricule</span>
                            </label>
                            <input type="text" class="form-control color-input" id="matricule2" name="matricule2" />
                        </div>

                        <div class="row">
                            <div class="col-sm-8">
                                <div class="row">
                                    <div class="d-flex flex-column mb-6 fv-row">
                                        <label class="d-flex align-items-center fs-6 fw-bold form-label mb-2">
                                            <span class="required">Prénom (s)</span>
                                        </label>
                                        <input type="text" class="form-control color-input" id="prenom" name="prenom"/>
                                    </div>
                                    <div class="d-flex flex-column mb-6 fv-row">
                                        <label class="d-flex align-items-center fs-6 fw-bold form-label mb-2">
                                            <span class="required">Nom (s)</span>
                                        </label>
                                        <input type="text" class="form-control color-input" id="nom" name="nom"/>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4 mt-5 text-center">
                                <div class="symbol symbol-100px symbol-lg-150px symbol-fixed position-relative">
                                    <img src="/uahb/ressources/dist_assets/media/avatars/150-26.jpg" alt="image"
                                         id="photoPersonnel">
                                </div>
                            </div>

                        </div>


                        <div class="d-flex flex-column mb-7 fv-row">
                            <label class="d-flex align-items-center fs-6 fw-bold form-label mb-2">
                                <span class="required">Email</span>
                            </label>
                            <input type="text" class="form-control color-input" id="email" name="email"/>
                        </div>

                        <div class="d-flex flex-column mb-7 fv-row">
                            <label class="d-flex align-items-center fs-6 fw-bold form-label mb-2">
                                <span class="required">Mot de passe par défaut</span>
                            </label>
                            <input type="text" class="form-control color-input" id="password" name="password"/>
                        </div>

                        <div class="text-center pt-15">
                            <button type="reset" class="btn btn-light me-3" onclick="closeAddUser()">Annuler</button>
                            <button type="submit" id="formAddUser2_submit" class="btn btn-primary">
                                <span class="indicator-label">Valider</span>
                                <span class="indicator-progress">Veuillez patienter...
															<span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                            </button>
                        </div>



                    </form>

                </div>

            </div>
        </div>
    </div>
</div>


<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script src="/personnel/scripts.bundle.7.js"></script>
</body>

</html>