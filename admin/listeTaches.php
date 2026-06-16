<?php

include_once('../sessions/admin.php');


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


    <style>
        #iconPreview {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            min-height: 48px;
        }

        #iconPreview svg {
            width: 32px;
            height: 32px;
        }
    </style>
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
                <a href="/personnel/admin-accueil" style="margin-left: 65px;">
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
                            <a class="menu-link" href="/personnel/admin-dashboard">
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
                            <a class="menu-link" href="/personnel/admin-liste-utilisateurs">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                              <path d="M16.0173 9H15.3945C14.2833 9 13.263 9.61425 12.7431 10.5963L12.154 11.7091C12.0645 11.8781 12.1072 12.0868 12.2559 12.2071L12.6402 12.5183C13.2631 13.0225 13.7556 13.6691 14.0764 14.4035L14.2321 14.7601C14.2957 14.9058 14.4396 15 14.5987 15H18.6747C19.7297 15 20.4057 13.8774 19.912 12.945L18.6686 10.5963C18.1487 9.61425 17.1285 9 16.0173 9Z"
                                                    fill="black"/>
<rect opacity="0.3" x="14" y="4" width="4" height="4" rx="2" fill="black"/>
<path d="M4.65486 14.8559C5.40389 13.1224 7.11161 12 9 12C10.8884 12 12.5961 13.1224 13.3451 14.8559L14.793 18.2067C15.3636 19.5271 14.3955 21 12.9571 21H5.04292C3.60453 21 2.63644 19.5271 3.20698 18.2067L4.65486 14.8559Z"
      fill="black"/>
<rect opacity="0.3" x="6" y="5" width="6" height="6" rx="3" fill="black"/>

                                            </svg>
                                        </span>
                                    </span>Liste des utilisateurs
                                <span class="menu-title"></span>
                            </a>
                        </div>

                        <div class="menu-item">
                            <a class="menu-link" href="/personnel/admin-liste-sous-menus">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                               <path d="M7 21H3C2.4 21 2 20.6 2 20V4C2 3.4 2.4 3 3 3H7C7.6 3 8 3.4 8 4V20C8 20.6 7.6 21 7 21Z"
                                                     fill="black"/>
<path opacity="0.3"
      d="M21 11H11C10.4 11 10 10.6 10 10V4C10 3.4 10.4 3 11 3H21C21.6 3 22 3.4 22 4V10C22 10.6 21.6 11 21 11ZM22 20V14C22 13.4 21.6 13 21 13H11C10.4 13 10 13.4 10 14V20C10 20.6 10.4 21 11 21H21C21.6 21 22 20.6 22 20Z"
      fill="black"/>

                                            </svg>
                                        </span>
                                    </span>Liste des sous-menus
                                <span class="menu-title"></span>
                            </a>
                        </div>

                        <div class="menu-item">
                            <a class="menu-link active" href="/personnel/admin-liste-taches">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                              <path opacity="0.5"
                                                    d="M18 2H9C7.34315 2 6 3.34315 6 5H8C8 4.44772 8.44772 4 9 4H18C18.5523 4 19 4.44772 19 5V16C19 16.5523 18.5523 17 18 17V19C19.6569 19 21 17.6569 21 16V5C21 3.34315 19.6569 2 18 2Z"
                                                    fill="black"/>
<path fill-rule="evenodd" clip-rule="evenodd"
      d="M14.7857 7.125H6.21429C5.62255 7.125 5.14286 7.6007 5.14286 8.1875V18.8125C5.14286 19.3993 5.62255 19.875 6.21429 19.875H14.7857C15.3774 19.875 15.8571 19.3993 15.8571 18.8125V8.1875C15.8571 7.6007 15.3774 7.125 14.7857 7.125ZM6.21429 5C4.43908 5 3 6.42709 3 8.1875V18.8125C3 20.5729 4.43909 22 6.21429 22H14.7857C16.5609 22 18 20.5729 18 18.8125V8.1875C18 6.42709 16.5609 5 14.7857 5H6.21429Z"
      fill="black"/>

                                            </svg>
                                        </span>
                                    </span>Liste des taches
                                <span class="menu-title"></span>
                            </a>
                        </div>

                        <div class="menu-item">
                            <a class="menu-link" href="/personnel/admin-liste-tache_qualification">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <path opacity="0.3"
                                                      d="M8.9 21L7.19999 22.6999C6.79999 23.0999 6.2 23.0999 5.8 22.6999L4.1 21H8.9ZM4 16.0999L2.3 17.8C1.9 18.2 1.9 18.7999 2.3 19.1999L4 20.9V16.0999ZM19.3 9.1999L15.8 5.6999C15.4 5.2999 14.8 5.2999 14.4 5.6999L9 11.0999V21L19.3 10.6999C19.7 10.2999 19.7 9.5999 19.3 9.1999Z"
                                                      fill="black"/>
<path d="M21 15V20C21 20.6 20.6 21 20 21H11.8L18.8 14H20C20.6 14 21 14.4 21 15ZM10 21V4C10 3.4 9.6 3 9 3H4C3.4 3 3 3.4 3 4V21C3 21.6 3.4 22 4 22H9C9.6 22 10 21.6 10 21ZM7.5 18.5C7.5 19.1 7.1 19.5 6.5 19.5C5.9 19.5 5.5 19.1 5.5 18.5C5.5 17.9 5.9 17.5 6.5 17.5C7.1 17.5 7.5 17.9 7.5 18.5Z"
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
                        <a href="/personnel/admin-accueil" class="d-lg-none">
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
                                        <a class="menu-link py-3" href="/personnel/admin-accueil">
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
                                        <img src="<?= !empty($tmpPhoto) ? $tmpPhoto : '/personnel/ressources/dist_assets/media/avatars/150-26.jpg' ?>"/>
                                    </div>
                                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-primary fw-bold py-4 fs-6 w-275px"
                                         data-kt-menu="true">
                                        <div class="menu-item px-3">
                                            <div class="menu-content d-flex align-items-center px-3">
                                                <div class="symbol symbol-50px me-5">
                                                    <img alt="admin"
                                                         src="<?= !empty($tmpPhoto) ? $tmpPhoto : '/personnel/ressources/dist_assets/media/avatars/150-26.jpg' ?>"/>
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <div class="fw-bolder d-flex align-items-center fs-5"> <?= $tmpPrenom . ' ' . $tmpNom ?>
                                                    </div>
                                                    <a href="#"
                                                       class="fw-bold text-muted text-hover-primary fs-7"><?= $tmpEmail ?></a>
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
                                                <label class="form-check form-switch form-check-custom form-check-solid pulse pulse-success"
                                                       for="kt_user_menu_dark_mode_toggle">
                                                    <a href="/personnel/signout">
                                                        <input class="form-check-input w-30px h-20px" checked="checked"
                                                               type="checkbox" value="1" name="mode"
                                                               id="kt_user_menu_dark_mode_toggle"
                                                               data-kt-url="/quitter"/>
                                                        <span class="pulse-ring ms-n1"></span>
                                                        <span class="form-check-label text-gray-600 fs-7">se déconnecter</span>
                                                    </a>
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

                                    <a href="/personnel/admin-accueil"
                                       class="text-muted text-hover-primary">Accueil</a>
                                </li>
                                <span class="h-20px border-gray-200 border-start mx-4"></span>

                                <li class="breadcrumb-item text-muted">
                                    <span class="text-muted text-hover-primary">Liste des taches</span>
                                </li>

                            </ul>
                        </div>
                    </div>
                </div>
                <div class="post d-flex flex-column-fluid" id="kt_post">
                    <div class="container-xxl">


                        <div class="col-sm-12 mb-5">
                            <div class="ps1-card">

                                <!-- Bloc gauche — label + total -->
                                <div class="ps1-card__left">
                                    <div class="ps1-card__eyebrow">
                                        <span class="ps1-card__dot"></span>
                                        Nombre de taches
                                    </div>
                                    <div class="ps1-card__main">
                                        <span class="ps1-card__number" id="ps1-total">...</span>
                                        <span class="ps1-card__unit">taches</span>
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
                                                   onclick="ajouterTaches()">
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

                                                    Ajouter une tâche</a>
                                            </div>
                                        </div>
                                        <table id="kt_table_taches"
                                               class="table align-middle table-row-dashed fs-6 gy-5">
                                            <thead>
                                            <tr
                                                class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
<!--                                                <th>Icon</th>-->
<!--                                                <th>sous_menu</th>-->
<!--                                                <th class="max-w-150px text-center">Action</th>-->

                                                <th>Nom</th>
                                                <th>Type</th>
                                                <th>Nombre d'utilisateur</th>
                                                <th>code</th>
                                                <th>Commentaire</th>
                                                <th>actions</th>
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

<div class="modal fade" id="kt_modal_add_tache" tabindex="-1" aria-labelledby="tache" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tâche</h5>
                <button type="button" class="btn-close" onclick="closeAddTache()"></button>
            </div>
            <div class="modal-body">
                <form id="formAddTache" action="#" class="form m-3">
                    <input type="hidden" name="option" value="19">
                    <input type="hidden" name="id" id="id_tache">

                    <!-- Nom -->
                    <div class="mb-3">
                        <label class="form-label required">Nom</label>
                        <input type="text" name="nom" class="form-control" placeholder="Nom de la tâche" id="nom">
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Type -->
                    <div class="mb-3">
                        <label class="form-label required">Type</label>
                        <select name="idTypeTache"
                                class="form-control"
                                data-control="select2"
                                data-placeholder="Choisir..."
                                id="idTypeTache"
                                onchange="type_tache(this.value)">
                            <option value="">Sélectionner un type</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Niveaux UA (visible si type == 2) -->
                    <div class="mb-3 d-none" id="box_niv">
                        <label class="form-label required">Niveaux</label>
                        <select name="nivUA"
                                class="form-control"
                                data-control="select2"
                                data-placeholder="Choisir..."
                                id="nivUA"
                                onchange="actionUniteAdministrative(this.value)">
                            <option value="">Sélectionner le niveau</option>
                            <option value="1">Niveau 1</option>
                            <option value="2">Niveau 2</option>
                            <option value="3">Niveau 3</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Unité administrative (visible si type == 2) -->
                    <div class="mb-3 d-none" id="box_ua">
                        <label class="form-label required">Unité administrative</label>
                        <select name="idUA"
                                class="form-control"
                                data-control="select2"
                                data-placeholder="Choisir..."
                                id="idUA">
                            <option value="">Sélectionner une unité administrative</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Fonction (visible si type == 1) -->
                    <div class="mb-3 d-none" id="box_fonction">
                        <label class="form-label required">Fonction</label>
                        <select name="id_fonction"
                                class="form-control"
                                data-control="select2"
                                data-placeholder="Choisir..."
                                id="id_fonction">
                            <option value="">Sélectionner une fonction</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Sous menu -->
                    <div class="mb-3">
                        <label class="form-label required">Sous menu</label>
                        <select name="idSousMenu"
                                class="form-control"
                                data-control="select2"
                                data-placeholder="Choisir..."
                                id="idSousMenu">
                            <option value="">Sélectionner un sous menu</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Icône -->
                    <!-- Icône -->
                    <div class="mb-3">
                        <label class="form-label required">Icône</label>
                        <div class="d-flex align-items-center justify-content-start gap-3">
                            <div id="iconPreview" class="border bg-light btn d-flex align-items-center justify-content-center" style="min-width:48px;min-height:48px;"></div>
                            <div class="flex-grow-1" id="iconSelectWrapper"> <!-- ✅ id ajouté -->
                                <select class="form-control" name="idIcon" id="id_icon_tache">
                                    <option value="">Sélectionner une icône</option>
                                </select>
                            </div>
                        </div>
                        <div class="invalid-feedback d-block" id="idIcon-feedback"></div>
                    </div>
                    <!-- URL -->
                    <div class="mb-3">
                        <label class="form-label required">URL</label>
                        <input type="text" name="url" id="urlInput" class="form-control" placeholder="https://...">
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Base de données -->
                    <div class="mb-3">
                        <label class="form-label required">Base de données</label>
                        <select name="idBD"
                                class="form-control"
                                data-control="select2"
                                data-placeholder="Choisir..."
                                id="idBD">
                            <option value="">Sélectionner une base de données</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Applications -->
                    <div class="mb-3">
                        <label class="form-label required">Applications</label>
                        <select name="idAppli"
                                id="idAppli"
                                class="form-control"
                                data-control="select2"
                                data-placeholder="Choisir...">
                            <option value="">Sélectionner l'application</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Autre ressource -->
                    <div class="mb-3">
                        <label class="form-label">Autre ressource</label>
                        <textarea name="autre_ressource" class="form-control" rows="3" placeholder="Autre ressource..."></textarea>
                    </div>

                    <!-- Commentaire -->
                    <div class="mb-3">
                        <label class="form-label">Commentaire</label>
                        <textarea name="commentaire" class="form-control" rows="3" placeholder="Commentaire..."></textarea>
                    </div>

                    <!-- Boutons -->
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light" onclick="closeAddTache()">
                            Annuler
                        </button>
                        <button type="submit" class="btn btn-success" id="formAddTache_submit">
                            <span class="indicator-label">Valider</span>
                            <span class="indicator-progress d-none">
                                Veuillez patienter...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<!--<div class="modal fade" id="kt_modal_add_tache" tabindex="-1" aria-labelledby="tache" aria-hidden="true">-->
<!--    <div class="modal-dialog">-->
<!--        <div class="modal-content">-->
<!--            <div class="modal-header">-->
<!--                <h5 class="modal-title" id="exampleModalLabel">Tache</h5>-->
<!--                <button type="button" class="btn-close" onclick="closeAddTache()" ></button>-->
<!--            </div>-->
<!--            <div class="modal-body">-->
<!--                <form id="formAddTache" action="#" class="form row g-2 d-flex align-items-center justify-content-center m-3">-->
<!--                    <input type="hidden" name="option" value="19">-->
<!--                    <input type="hidden" name="id" id="id_tache">-->
<!---->
<!--                    <div class="mb-3">-->
<!--                        <label class="form-label required">Nom </label>-->
<!--                        <input type="text" name="nom" class="form-control" placeholder="nom" id="nom">-->
<!---->
<!---->
<!--                    </div>-->
<!--                    <div class="mb-3">-->
<!--                        <label class="form-label required">Type</label>-->
<!--                        <select name="idTypeTache" class="form-control" data-control="select2" data-placeholder="Choisir..." id="idTypeTache" onchange="type_tache(this.value)">-->
<!--                            <option value="">Sélectionner un type</option>-->
<!--                        </select>-->
<!---->
<!--                    </div>-->
<!--                    <div class="mb-3 d-none" id="box_niv">-->
<!--                        <label class="form-label required">Niveaux</label>-->
<!--                        <select name="nivUA" class="form-control" data-control="select2" data-placeholder="Choisir..." id="nivUA" onchange="actionUniteAdministrative(this.value)">-->
<!--                            <option value="">Sélectionner le niveau</option>-->
<!--                            <option value="1">Niveau 1</option>-->
<!--                            <option value="2">Niveau 2</option>-->
<!--                            <option value="3">Niveau 3</option>-->
<!--                        </select>-->
<!---->
<!--                    </div>-->
<!--                    <div class="mb-3 d-none" id="box_ua">-->
<!--                        <label class="form-label required">Unité administrative</label>-->
<!--                        <select name="idUA" class="form-control" data-control="select2" data-placeholder="Choisir..." id="idUA">-->
<!--                            <option value="">Sélectionner une fonction</option>-->
<!--                        </select>-->
<!---->
<!--                    </div>-->
<!--                    <div class="mb-3 d-none" id="box_fonction">-->
<!--                        <label class="form-label required">Fonction</label>-->
<!--                        <select name="id_fonction" class="form-control" data-control="select2" data-placeholder="Choisir..." id="id_fonction">-->
<!--                            <option value="">Sélectionner une fonction</option>-->
<!--                        </select>-->
<!---->
<!--                    </div>-->
<!--                    <div class="mb-3">-->
<!--                        <label class="form-label required">Sous menu</label>-->
<!--                        <select name="idSousMenu" class="form-control" data-control="select2" data-placeholder="Choisir..." id="idSousMenu">-->
<!--                            <option value=''>Sélectionner un sous menu</option>-->
<!--                        </select>-->
<!---->
<!--                    </div>-->
<!---->
<!--                    <label class="form-label required">Icône</label>-->
<!--                    <div class="mb-1 d-flex align-items-center justify-content-start">-->
<!--                        <div id="iconPreview" class="border bg-light b-1px btn "></div>-->
<!--                        <div class="w-50 m-2">-->
<!--                            <select class="form-control select2-icon"  name="idIcon" id="id_icon_tache">-->
<!--                                <option value="">Sélectionner une icône</option>-->
<!--                                <option value="1"><span class="menu-icon">-->
<!--<span class="svg-icon svg-icon-2">-->
<!--												<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">-->
<!--													<rect x="2" y="2" width="9" height="9" rx="2" fill="black" />-->
<!--													<rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="black" />-->
<!--													<rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="black" />-->
<!--													<rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="black" />-->
<!--												</svg>-->
<!--											</span>-->
<!--</span></option>-->
<!---->
<!--                            </select>-->
<!---->
<!--                        </div>-->
<!---->
<!--                    </div>-->
<!---->
<!--                    <div class="mb-3">-->
<!--                        <label class="form-label required">URL</label>-->
<!--                        <input type="text" name="url" id="urlInput" class="form-control" placeholder="url">-->
<!---->
<!--                    </div>-->
<!--                    <div class="mb-3">-->
<!--                        <label class="form-label required">Base de données</label>-->
<!--                        <select name="idBD" class="form-control" data-control="select2" data-placeholder="Choisir..." id="idBD">-->
<!--                            <option value="">Sélectionner une base de données</option>-->
<!--                        </select>-->
<!--                    </div>-->
<!--                    <div class="mb-3">-->
<!--                        <label class="form-label required">Applications</label>-->
<!--                        <select name="idDB" id="idAppli" class="form-select" data-control="select2" data-placeholder="Choisir...">-->
<!--                            <option value="">Sélectionner l'application</option>-->
<!--                        </select>-->
<!--                    </div>-->
<!--                    <div class="mb-3">-->
<!--                        <label class="form-label">Autre ressource </label>-->
<!--                        <textarea name="autre_ressource" class="form-control"></textarea>-->
<!---->
<!--                    </div>-->
<!--                    <div class="mb-3">-->
<!--                        <label class="form-label">Commentaire</label>-->
<!--                        <textarea name="commentaire" class="form-control"></textarea>-->
<!--                    </div>-->
<!---->
<!--<!--                    <div class="text-center pt-15">-->-->
<!--<!--                        <button type="reset" class="btn btn-light me-3" onclick="closeAddUser()">Annuler</button>-->-->
<!--<!--                        <button type="submit" id="formAddUser2_submit" class="btn btn-primary">-->-->
<!--<!--                            <span class="indicator-label">Valider</span>-->-->
<!--<!--                            <span class="indicator-progress">Veuillez patienter...-->-->
<!--<!--															<span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>-->-->
<!--<!--                        </button>-->-->
<!--<!--                    </div>-->-->
<!--                    <button type="submit" class="btn btn-success w-500px m-2" ype="submit" id="formAddTache_submit">valider-->
<!--                        <span class="indicator-progress">Veuillez patienter...-->
<!--															<span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>-->
<!--                    </button>-->
<!--                </form>-->
<!--            </div>-->
<!---->
<!--        </div>-->
<!--    </div>-->
<!--</div>-->


<div class="modal fade" id="kt_modal_edit_tache" tabindex="-1" aria-labelledby="editTache" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier la Tâche</h5>
                <button type="button" class="btn-close" onclick="closeEditTache()"></button>
            </div>
            <div class="modal-body">
                <form id="formEditTache" action="#" class="form m-3">
                    <input type="hidden" name="option" value="22">
                    <input type="hidden" name="id" id="edit_id_tache">

                    <!-- Nom -->
                    <div class="mb-3">
                        <label class="form-label required">Nom</label>
                        <input type="text" name="nom" class="form-control" placeholder="Nom de la tâche" id="edit_nom">
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Type -->
                    <div class="mb-3">
                        <label class="form-label required">Type</label>
                        <select name="idTypeTache"
                                class="form-control"
                                data-control="select2"
                                data-placeholder="Choisir..."
                                id="edit_idTypeTache"
                                onchange="edit_type_tache(this.value)">
                            <option value="">Sélectionner un type</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Niveaux UA (visible si type == 2) -->
                    <div class="mb-3 d-none" id="edit_box_niv">
                        <label class="form-label required">Niveaux</label>
                        <select name="nivUA"
                                class="form-control"
                                data-control="select2"
                                data-placeholder="Choisir..."
                                id="edit_nivUA"
                                onchange="editActionUniteAdministrative(this.value)">
                            <option value="">Sélectionner le niveau</option>
                            <option value="1">Niveau 1</option>
                            <option value="2">Niveau 2</option>
                            <option value="3">Niveau 3</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Unité administrative (visible si type == 2) -->
                    <div class="mb-3 d-none" id="edit_box_ua">
                        <label class="form-label required">Unité administrative</label>
                        <select name="idUA"
                                class="form-control"
                                data-control="select2"
                                data-placeholder="Choisir..."
                                id="edit_idUA">
                            <option value="">Sélectionner une unité administrative</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Fonction (visible si type == 1) -->
                    <div class="mb-3 d-none" id="edit_box_fonction">
                        <label class="form-label required">Fonction</label>
                        <select name="id_fonction"
                                class="form-control"
                                data-control="select2"
                                data-placeholder="Choisir..."
                                id="edit_id_fonction">
                            <option value="">Sélectionner une fonction</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Sous menu -->
                    <div class="mb-3">
                        <label class="form-label required">Sous menu</label>
                        <select name="idSousMenu"
                                class="form-control"
                                data-control="select2"
                                data-placeholder="Choisir..."
                                id="edit_idSousMenu">
                            <option value="">Sélectionner un sous menu</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Icône -->
                    <div class="mb-3">
                        <label class="form-label required">Icône</label>
                        <div class="d-flex align-items-center justify-content-start gap-3">
                            <div id="edit_iconPreview"
                                 class="border bg-light btn d-flex align-items-center justify-content-center"
                                 style="min-width:48px;min-height:48px;"></div>
                            <div class="flex-grow-1" id="edit_iconSelectWrapper">
                                <select class="form-control" name="idIcon" id="edit_id_icon_tache">
                                    <option value="">Sélectionner une icône</option>
                                </select>
                            </div>
                        </div>
                        <div class="invalid-feedback d-block" id="edit_idIcon-feedback"></div>
                    </div>

                    <!-- URL -->
                    <div class="mb-3">
                        <label class="form-label required">URL</label>
                        <input type="text" name="url" id="edit_urlInput" class="form-control" placeholder="https://...">
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Base de données -->
                    <div class="mb-3">
                        <label class="form-label required">Base de données</label>
                        <select name="idBD"
                                class="form-control"
                                data-control="select2"
                                data-placeholder="Choisir..."
                                id="edit_idBD">
                            <option value="">Sélectionner une base de données</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Applications -->
                    <div class="mb-3">
                        <label class="form-label required">Applications</label>
                        <select name="idDB"
                                id="edit_idAppli"
                                class="form-control"
                                data-control="select2"
                                data-placeholder="Choisir...">
                            <option value="">Sélectionner l'application</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Autre ressource -->
                    <div class="mb-3">
                        <label class="form-label">Autre ressource</label>
                        <textarea name="autre_ressource" id="edit_autre_ressource" class="form-control" rows="3"></textarea>
                    </div>

                    <!-- Commentaire -->
                    <div class="mb-3">
                        <label class="form-label">Commentaire</label>
                        <textarea name="commentaire" id="edit_commentaire" class="form-control" rows="3"></textarea>
                    </div>

                    <!-- Boutons -->
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light" onclick="closeEditTache()">Annuler</button>
                        <button type="submit" class="btn btn-success" id="formEditTache_submit">
                            <span class="indicator-label">Modifier</span>
                            <span class="indicator-progress d-none">
                                Veuillez patienter...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
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



<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script src="/personnel/scripts.bundle.9.js"></script>
</body>

</html>