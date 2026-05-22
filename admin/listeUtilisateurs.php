<?php

session_start();

?>
<!DOCTYPE html>

<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>ENT — GSJLF</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <link href="/personnel/ressources/dist_assets/plugins/custom/fullcalendar/fullcalendar.bundle.css" rel="stylesheet"
          type="text/css" />
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <link href="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet"
          type="text/css" />
    <link href="/personnel/ressources/dist_assets/css/ps-style.css" rel="stylesheet" type="text/css" />

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
                    <img alt="Logo" src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" class="h-50px logo" />
                </a>
                <div id="kt_aside_toggle" class="btn btn-icon w-auto px-0 btn-active-color-primary aside-toggle"
                     data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body"
                     data-kt-toggle-name="aside-minimize">
                        <span class="svg-icon svg-icon-1 rotate-180">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                 fill="none">
                                <path opacity="0.5"
                                      d="M14.2657 11.4343L18.45 7.25C18.8642 6.83579 18.8642 6.16421 18.45 5.75C18.0358 5.33579 17.3642 5.33579 16.95 5.75L11.4071 11.2929C11.0166 11.6834 11.0166 12.3166 11.4071 12.7071L16.95 18.25C17.3642 18.6642 18.0358 18.6642 18.45 18.25C18.8642 17.8358 18.8642 17.1642 18.45 16.75L14.2657 12.5657C13.9533 12.2533 13.9533 11.7467 14.2657 11.4343Z"
                                      fill="black" />
                                <path
                                    d="M8.2657 11.4343L12.45 7.25C12.8642 6.83579 12.8642 6.16421 12.45 5.75C12.0358 5.33579 11.3642 5.33579 10.95 5.75L5.40712 11.2929C5.01659 11.6834 5.01659 12.3166 5.40712 12.7071L10.95 18.25C11.3642 18.6642 12.0358 18.6642 12.45 18.25C12.8642 17.8358 12.8642 17.1642 12.45 16.75L8.2657 12.5657C7.95328 12.2533 7.95328 11.7467 8.2657 11.4343Z"
                                    fill="black" />
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
                                    <span class="menu-section text-muted text-uppercase fs-8 ls-1">Scolarité
                                    </span>
                            </div>
                        </div>

                        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <span class="menu-link">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <path opacity="0.3"
                                                      d="M20.335 15.537C21.725 14.425 21.57 12.812 21.553 11.224C21.4407 9.50899 20.742 7.88483 19.574 6.624C18.5503 5.40102 17.2668 4.4216 15.817 3.757C14.4297 3.26981 12.9703 3.01966 11.5 3.01701C8.79576 2.83108 6.11997 3.66483 4 5.35398C2.289 6.72498 1.23101 9.12497 2.68601 11.089C3.22897 11.6881 3.93029 12.1214 4.709 12.339C5.44803 12.6142 6.24681 12.6888 7.024 12.555C6.88513 12.9965 6.85078 13.4644 6.92367 13.9215C6.99656 14.3786 7.17469 14.8125 7.444 15.189C7.73891 15.5299 8.10631 15.8006 8.51931 15.9812C8.93232 16.1619 9.38047 16.2478 9.831 16.233C10.0739 16.2296 10.3141 16.1807 10.539 16.089C10.7371 15.9871 10.9288 15.8732 11.113 15.748C12.1594 15.2831 13.3275 15.1668 14.445 15.416C15.7795 15.7213 17.1299 15.952 18.49 16.107C18.7927 16.1438 19.0993 16.1313 19.398 16.07C19.7445 15.9606 20.0639 15.7789 20.335 15.537Z"
                                                      fill="black"/>
                                                <path d="M19.008 16.114C18.9486 16.6061 18.7934 17.0817 18.551 17.514C18.229 18.114 17.581 18.314 17.103 18.752C16.457 19.343 16.595 20.38 16.632 21.164C16.6522 21.3437 16.621 21.5254 16.542 21.688C16.4335 21.835 16.2751 21.9373 16.0965 21.9758C15.9179 22.0143 15.7314 21.9863 15.572 21.897C15.2577 21.7083 15.0072 21.4296 14.853 21.097C14.581 20.607 14.362 20.085 14.053 19.612C13.3182 18.7548 12.4201 18.0525 11.411 17.546C10.9334 17.1942 10.5857 16.6942 10.422 16.124C10.459 16.111 10.499 16.106 10.536 16.09C10.7336 15.9879 10.925 15.8741 11.109 15.749C12.1554 15.2842 13.3234 15.1678 14.441 15.417C15.7754 15.7223 17.1259 15.953 18.486 16.108C18.6598 16.1191 18.834 16.1211 19.008 16.114ZM18.8 10.278V3C18.8 2.73478 18.6946 2.48044 18.5071 2.29291C18.3196 2.10537 18.0652 2 17.8 2C17.5348 2 17.2804 2.10537 17.0929 2.29291C16.9053 2.48044 16.8 2.73478 16.8 3V10.278C16.4187 10.4981 16.1207 10.8379 15.9522 11.2447C15.7838 11.6514 15.7542 12.1024 15.8681 12.5277C15.9821 12.953 16.2332 13.3287 16.5825 13.5967C16.9318 13.8648 17.3597 14.0101 17.8 14.0101C18.2403 14.0101 18.6682 13.8648 19.0175 13.5967C19.3668 13.3287 19.6179 12.953 19.7318 12.5277C19.8458 12.1024 19.8162 11.6514 19.6477 11.2447C19.4793 10.8379 19.1813 10.4981 18.8 10.278ZM13.8 2C13.5348 2 13.2804 2.10537 13.0929 2.29291C12.9053 2.48044 12.8 2.73478 12.8 3V8.586L12.312 9.07397C11.8792 8.95363 11.4188 8.98003 11.0026 9.14899C10.5864 9.31794 10.2379 9.61994 10.0115 10.0079C9.78508 10.3958 9.69351 10.8478 9.75109 11.2933C9.80867 11.7387 10.0122 12.1526 10.3298 12.4702C10.6474 12.7878 11.0612 12.9913 11.5067 13.0489C11.9522 13.1065 12.4042 13.0149 12.7921 12.7885C13.18 12.5621 13.4821 12.2136 13.651 11.7974C13.82 11.3812 13.8463 10.9207 13.726 10.488L14.507 9.70697C14.6945 9.51948 14.7999 9.26519 14.8 9V3C14.8 2.73478 14.6946 2.48044 14.5071 2.29291C14.3196 2.10537 14.0652 2 13.8 2ZM9.79999 2C9.53478 2 9.28042 2.10537 9.09289 2.29291C8.90535 2.48044 8.79999 2.73478 8.79999 3V4.586L7.31199 6.07397C6.87924 5.95363 6.41882 5.98004 6.00263 6.14899C5.58644 6.31794 5.23792 6.61994 5.0115 7.00787C4.78508 7.39581 4.69351 7.84781 4.75109 8.29327C4.80867 8.73874 5.01216 9.1526 5.32977 9.47021C5.64739 9.78783 6.06124 9.99131 6.50671 10.0489C6.95218 10.1065 7.40417 10.0149 7.7921 9.78851C8.18004 9.56209 8.48207 9.21355 8.65102 8.79736C8.81997 8.38117 8.84634 7.92073 8.726 7.48798L10.507 5.70697C10.6945 5.51948 10.7999 5.26519 10.8 5V3C10.8 2.73478 10.6946 2.48044 10.5071 2.29291C10.3196 2.10537 10.0652 2 9.79999 2Z"
                                                      fill="black"/>
                                            </svg>
                                        </span>
                                    </span>
                                    <span class="menu-title">Scolarité UAHB</span>
                                    <span class="menu-arrow"></span>
                                </span>
                            <div class="menu-sub menu-sub-accordion menu-active-bg" kt-hidden-height="507">
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/reponsable-financier-scolarite-liste-paiement">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/reponsable-financier-scolarite-liste-des-paiement-modifies">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements modifiés</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/reponsable-financier-scolarite-liste-des-paiements-en-attentes">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements en attentes</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/reponsable-financier-liste-des-etudiants">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des étudiants</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/responsable-financier-liste-des-insciptions-en-attente">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des inscriptions en attentes</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/reponsable-financier-liste-des-organismes">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">Régime d'études</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <span class="menu-link">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <path opacity="0.3"
                                                      d="M20.335 15.537C21.725 14.425 21.57 12.812 21.553 11.224C21.4407 9.50899 20.742 7.88483 19.574 6.624C18.5503 5.40102 17.2668 4.4216 15.817 3.757C14.4297 3.26981 12.9703 3.01966 11.5 3.01701C8.79576 2.83108 6.11997 3.66483 4 5.35398C2.289 6.72498 1.23101 9.12497 2.68601 11.089C3.22897 11.6881 3.93029 12.1214 4.709 12.339C5.44803 12.6142 6.24681 12.6888 7.024 12.555C6.88513 12.9965 6.85078 13.4644 6.92367 13.9215C6.99656 14.3786 7.17469 14.8125 7.444 15.189C7.73891 15.5299 8.10631 15.8006 8.51931 15.9812C8.93232 16.1619 9.38047 16.2478 9.831 16.233C10.0739 16.2296 10.3141 16.1807 10.539 16.089C10.7371 15.9871 10.9288 15.8732 11.113 15.748C12.1594 15.2831 13.3275 15.1668 14.445 15.416C15.7795 15.7213 17.1299 15.952 18.49 16.107C18.7927 16.1438 19.0993 16.1313 19.398 16.07C19.7445 15.9606 20.0639 15.7789 20.335 15.537Z"
                                                      fill="black"/>
                                                <path
                                                    d="M19.008 16.114C18.9486 16.6061 18.7934 17.0817 18.551 17.514C18.229 18.114 17.581 18.314 17.103 18.752C16.457 19.343 16.595 20.38 16.632 21.164C16.6522 21.3437 16.621 21.5254 16.542 21.688C16.4335 21.835 16.2751 21.9373 16.0965 21.9758C15.9179 22.0143 15.7314 21.9863 15.572 21.897C15.2577 21.7083 15.0072 21.4296 14.853 21.097C14.581 20.607 14.362 20.085 14.053 19.612C13.3182 18.7548 12.4201 18.0525 11.411 17.546C10.9334 17.1942 10.5857 16.6942 10.422 16.124C10.459 16.111 10.499 16.106 10.536 16.09C10.7336 15.9879 10.925 15.8741 11.109 15.749C12.1554 15.2842 13.3234 15.1678 14.441 15.417C15.7754 15.7223 17.1259 15.953 18.486 16.108C18.6598 16.1191 18.834 16.1211 19.008 16.114ZM18.8 10.278V3C18.8 2.73478 18.6946 2.48044 18.5071 2.29291C18.3196 2.10537 18.0652 2 17.8 2C17.5348 2 17.2804 2.10537 17.0929 2.29291C16.9053 2.48044 16.8 2.73478 16.8 3V10.278C16.4187 10.4981 16.1207 10.8379 15.9522 11.2447C15.7838 11.6514 15.7542 12.1024 15.8681 12.5277C15.9821 12.953 16.2332 13.3287 16.5825 13.5967C16.9318 13.8648 17.3597 14.0101 17.8 14.0101C18.2403 14.0101 18.6682 13.8648 19.0175 13.5967C19.3668 13.3287 19.6179 12.953 19.7318 12.5277C19.8458 12.1024 19.8162 11.6514 19.6477 11.2447C19.4793 10.8379 19.1813 10.4981 18.8 10.278ZM13.8 2C13.5348 2 13.2804 2.10537 13.0929 2.29291C12.9053 2.48044 12.8 2.73478 12.8 3V8.586L12.312 9.07397C11.8792 8.95363 11.4188 8.98003 11.0026 9.14899C10.5864 9.31794 10.2379 9.61994 10.0115 10.0079C9.78508 10.3958 9.69351 10.8478 9.75109 11.2933C9.80867 11.7387 10.0122 12.1526 10.3298 12.4702C10.6474 12.7878 11.0612 12.9913 11.5067 13.0489C11.9522 13.1065 12.4042 13.0149 12.7921 12.7885C13.18 12.5621 13.4821 12.2136 13.651 11.7974C13.82 11.3812 13.8463 10.9207 13.726 10.488L14.507 9.70697C14.6945 9.51948 14.7999 9.26519 14.8 9V3C14.8 2.73478 14.6946 2.48044 14.5071 2.29291C14.3196 2.10537 14.0652 2 13.8 2ZM9.79999 2C9.53478 2 9.28042 2.10537 9.09289 2.29291C8.90535 2.48044 8.79999 2.73478 8.79999 3V4.586L7.31199 6.07397C6.87924 5.95363 6.41882 5.98004 6.00263 6.14899C5.58644 6.31794 5.23792 6.61994 5.0115 7.00787C4.78508 7.39581 4.69351 7.84781 4.75109 8.29327C4.80867 8.73874 5.01216 9.1526 5.32977 9.47021C5.64739 9.78783 6.06124 9.99131 6.50671 10.0489C6.95218 10.1065 7.40417 10.0149 7.7921 9.78851C8.18004 9.56209 8.48207 9.21355 8.65102 8.79736C8.81997 8.38117 8.84634 7.92073 8.726 7.48798L10.507 5.70697C10.6945 5.51948 10.7999 5.26519 10.8 5V3C10.8 2.73478 10.6946 2.48044 10.5071 2.29291C10.3196 2.10537 10.0652 2 9.79999 2Z"
                                                    fill="black"/>
                                            </svg>
                                        </span>
                                    </span>
                                    <span class="menu-title">Scolarité CMJLF</span>
                                    <span class="menu-arrow"></span>
                                </span>
                            <div class="menu-sub menu-sub-accordion menu-active-bg" kt-hidden-height="507">
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/cmjlf-responsable-financier-scolarite-liste-paiement">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/cmjlf-responsable-financier-scolarite-liste-des-paiement-modifies">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements modifiés</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/cmjlf-responsable-financier-scolarite-liste-des-paiements-en-attentes">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements en attentes</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/cmjlf-responsable-financier-scolarite-paiement-en-emprunt">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des emprunts</span>
                                    </a>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/cmjlf-responsable-financier-scolarite-paiement-en-empruntAttente">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des emprunts en attente</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/cmjlf-responsable-financier-pageFacturation">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">Facturation</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/cmjlf-responsable-financier-liste-des-eleves">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des élèves</span>
                                    </a>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/cmjlf-responsable-financier-liste-des-insciptions-en-attente">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des inscriptions en attentes</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/cmjlf-responsable-financier-liste-des-organismes">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">Régime d'études</span>
                                    </a>
                                </div>

                            </div>
                        </div>

                        <div data-kt-menu-trigger="click" class="menu-item menu-accordion show">
                                <span class="menu-link">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <path opacity="0.3"
                                                      d="M20.335 15.537C21.725 14.425 21.57 12.812 21.553 11.224C21.4407 9.50899 20.742 7.88483 19.574 6.624C18.5503 5.40102 17.2668 4.4216 15.817 3.757C14.4297 3.26981 12.9703 3.01966 11.5 3.01701C8.79576 2.83108 6.11997 3.66483 4 5.35398C2.289 6.72498 1.23101 9.12497 2.68601 11.089C3.22897 11.6881 3.93029 12.1214 4.709 12.339C5.44803 12.6142 6.24681 12.6888 7.024 12.555C6.88513 12.9965 6.85078 13.4644 6.92367 13.9215C6.99656 14.3786 7.17469 14.8125 7.444 15.189C7.73891 15.5299 8.10631 15.8006 8.51931 15.9812C8.93232 16.1619 9.38047 16.2478 9.831 16.233C10.0739 16.2296 10.3141 16.1807 10.539 16.089C10.7371 15.9871 10.9288 15.8732 11.113 15.748C12.1594 15.2831 13.3275 15.1668 14.445 15.416C15.7795 15.7213 17.1299 15.952 18.49 16.107C18.7927 16.1438 19.0993 16.1313 19.398 16.07C19.7445 15.9606 20.0639 15.7789 20.335 15.537Z"
                                                      fill="black"/>
                                                <path
                                                    d="M19.008 16.114C18.9486 16.6061 18.7934 17.0817 18.551 17.514C18.229 18.114 17.581 18.314 17.103 18.752C16.457 19.343 16.595 20.38 16.632 21.164C16.6522 21.3437 16.621 21.5254 16.542 21.688C16.4335 21.835 16.2751 21.9373 16.0965 21.9758C15.9179 22.0143 15.7314 21.9863 15.572 21.897C15.2577 21.7083 15.0072 21.4296 14.853 21.097C14.581 20.607 14.362 20.085 14.053 19.612C13.3182 18.7548 12.4201 18.0525 11.411 17.546C10.9334 17.1942 10.5857 16.6942 10.422 16.124C10.459 16.111 10.499 16.106 10.536 16.09C10.7336 15.9879 10.925 15.8741 11.109 15.749C12.1554 15.2842 13.3234 15.1678 14.441 15.417C15.7754 15.7223 17.1259 15.953 18.486 16.108C18.6598 16.1191 18.834 16.1211 19.008 16.114ZM18.8 10.278V3C18.8 2.73478 18.6946 2.48044 18.5071 2.29291C18.3196 2.10537 18.0652 2 17.8 2C17.5348 2 17.2804 2.10537 17.0929 2.29291C16.9053 2.48044 16.8 2.73478 16.8 3V10.278C16.4187 10.4981 16.1207 10.8379 15.9522 11.2447C15.7838 11.6514 15.7542 12.1024 15.8681 12.5277C15.9821 12.953 16.2332 13.3287 16.5825 13.5967C16.9318 13.8648 17.3597 14.0101 17.8 14.0101C18.2403 14.0101 18.6682 13.8648 19.0175 13.5967C19.3668 13.3287 19.6179 12.953 19.7318 12.5277C19.8458 12.1024 19.8162 11.6514 19.6477 11.2447C19.4793 10.8379 19.1813 10.4981 18.8 10.278ZM13.8 2C13.5348 2 13.2804 2.10537 13.0929 2.29291C12.9053 2.48044 12.8 2.73478 12.8 3V8.586L12.312 9.07397C11.8792 8.95363 11.4188 8.98003 11.0026 9.14899C10.5864 9.31794 10.2379 9.61994 10.0115 10.0079C9.78508 10.3958 9.69351 10.8478 9.75109 11.2933C9.80867 11.7387 10.0122 12.1526 10.3298 12.4702C10.6474 12.7878 11.0612 12.9913 11.5067 13.0489C11.9522 13.1065 12.4042 13.0149 12.7921 12.7885C13.18 12.5621 13.4821 12.2136 13.651 11.7974C13.82 11.3812 13.8463 10.9207 13.726 10.488L14.507 9.70697C14.6945 9.51948 14.7999 9.26519 14.8 9V3C14.8 2.73478 14.6946 2.48044 14.5071 2.29291C14.3196 2.10537 14.0652 2 13.8 2ZM9.79999 2C9.53478 2 9.28042 2.10537 9.09289 2.29291C8.90535 2.48044 8.79999 2.73478 8.79999 3V4.586L7.31199 6.07397C6.87924 5.95363 6.41882 5.98004 6.00263 6.14899C5.58644 6.31794 5.23792 6.61994 5.0115 7.00787C4.78508 7.39581 4.69351 7.84781 4.75109 8.29327C4.80867 8.73874 5.01216 9.1526 5.32977 9.47021C5.64739 9.78783 6.06124 9.99131 6.50671 10.0489C6.95218 10.1065 7.40417 10.0149 7.7921 9.78851C8.18004 9.56209 8.48207 9.21355 8.65102 8.79736C8.81997 8.38117 8.84634 7.92073 8.726 7.48798L10.507 5.70697C10.6945 5.51948 10.7999 5.26519 10.8 5V3C10.8 2.73478 10.6946 2.48044 10.5071 2.29291C10.3196 2.10537 10.0652 2 9.79999 2Z"
                                                    fill="black"/>
                                            </svg>
                                        </span>
                                    </span>
                                    <span class="menu-title">Scolarité CTD</span>
                                    <span class="menu-arrow"></span>
                                </span>
                            <div class="menu-sub menu-sub-accordion menu-active-bg" kt-hidden-height="507">
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/ps1-responsable-financier-scolarite-liste-paiement">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/ps1-responsable-financier-scolarite-liste-des-paiement-modifies">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements modifiés</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/ps1-responsable-financier-scolarite-liste-des-paiements-en-attentes">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements en attentes</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/ps1-responsable-financier-scolarite-paiement-en-emprunt">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des emprunts</span>
                                    </a>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/ps1-responsable-financier-scolarite-paiement-en-empruntAttente">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des emprunts en attente</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/ps1-responsable-financier-pageFacturation">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">Facturation</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link active" href="/personnel/ps1-responsable-financier-liste-des-eleves">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des élèves</span>
                                    </a>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/ps1-responsable-financier-liste-des-insciptions-en-attente">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des inscriptions en attentes</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/ps1-responsable-financier-liste-des-organismes">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">Régime d'études</span>
                                    </a>
                                </div>
                            </div>
                        </div>


                        <div class="menu-item">
                            <div class="menu-content pb-2">
                                    <span class="menu-section text-muted text-uppercase fs-8 ls-1">Admission
                                    </span>
                            </div>
                        </div>


                        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <span class="menu-link">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <path opacity="0.3"
                                                      d="M3 13V11C3 10.4 3.4 10 4 10H20C20.6 10 21 10.4 21 11V13C21 13.6 20.6 14 20 14H4C3.4 14 3 13.6 3 13Z"
                                                      fill="black"/>
                                                <path d="M13 21H11C10.4 21 10 20.6 10 20V4C10 3.4 10.4 3 11 3H13C13.6 3 14 3.4 14 4V20C14 20.6 13.6 21 13 21Z"
                                                      fill="black"/>
                                            </svg>
                                        </span>
                                    </span>
                                    <span class="menu-title">Admission UAHB</span>
                                    <span class="menu-arrow"></span>
                                </span>
                            <div class="menu-sub menu-sub-accordion menu-active-bg" kt-hidden-height="507">
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/reponsable-financier-admission-liste-paiement">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/reponsable-financier-admission-liste-des-paiement-modifies">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements modifiés</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/reponsable-financier-admission-liste-des-paiements-en-attentes">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements en attentes</span>
                                    </a>
                                </div>
                            </div>
                        </div>


                        <div class="menu-item">
                            <div class="menu-content pb-2">
                                    <span class="menu-section text-muted text-uppercase fs-8 ls-1">Arriéré UAHB
                                    </span>
                            </div>
                        </div>


                        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <span class="menu-link">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <path d="M9.60001 11H21C21.6 11 22 11.4 22 12C22 12.6 21.6 13 21 13H9.60001V11Z"
                                                      fill="black"/>
                                                <path opacity="0.3"
                                                      d="M9.6 20V4L2.3 11.3C1.9 11.7 1.9 12.3 2.3 12.7L9.6 20Z"
                                                      fill="black"/>

                                            </svg>
                                        </span>
                                    </span>
                                    <span class="menu-title">Arriéré UAHB</span>
                                    <span class="menu-arrow"></span>
                                </span>
                            <div class="menu-sub menu-sub-accordion menu-active-bg" kt-hidden-height="507">
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/reponsable-financier-arriere-liste-paiement">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/reponsable-financier-arriere-liste-des-paiement-modifies">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements modifiés</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/reponsable-financier-arriere-liste-des-paiements-en-attentes">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements en attentes</span>
                                    </a>
                                </div>
                            </div>
                        </div>


                        <div class="menu-item">
                            <div class="menu-content pb-2">
                                    <span class="menu-section text-muted text-uppercase fs-8 ls-1">Autres UAHB
                                    </span>
                            </div>
                        </div>

                        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <span class="menu-link">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <path opacity="0.3"
                                                      d="M21.25 18.525L13.05 21.825C12.35 22.125 11.65 22.125 10.95 21.825L2.75 18.525C1.75 18.125 1.75 16.725 2.75 16.325L4.04999 15.825L10.25 18.325C10.85 18.525 11.45 18.625 12.05 18.625C12.65 18.625 13.25 18.525 13.85 18.325L20.05 15.825L21.35 16.325C22.35 16.725 22.35 18.125 21.25 18.525ZM13.05 16.425L21.25 13.125C22.25 12.725 22.25 11.325 21.25 10.925L13.05 7.62502C12.35 7.32502 11.65 7.32502 10.95 7.62502L2.75 10.925C1.75 11.325 1.75 12.725 2.75 13.125L10.95 16.425C11.65 16.725 12.45 16.725 13.05 16.425Z"
                                                      fill="black"/>
                                                <path d="M11.05 11.025L2.84998 7.725C1.84998 7.325 1.84998 5.925 2.84998 5.525L11.05 2.225C11.75 1.925 12.45 1.925 13.15 2.225L21.35 5.525C22.35 5.925 22.35 7.325 21.35 7.725L13.05 11.025C12.45 11.325 11.65 11.325 11.05 11.025Z"
                                                      fill="black"/>
                                            </svg>
                                        </span>
                                    </span>
                                    <span class="menu-title">Autres UAHB</span>
                                    <span class="menu-arrow"></span>
                                </span>
                            <div class="menu-sub menu-sub-accordion menu-active-bg" kt-hidden-height="507">
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/reponsable-financier-autres-liste-paiement">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/reponsable-financier-autres-liste-des-paiement-modifies">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements modifiés</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/reponsable-financier-autres-liste-des-paiements-en-attentes">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">La liste des paiements en attentes</span>
                                    </a>
                                </div>
                            </div>
                        </div>


                        <div class="menu-item">
                            <div class="menu-content pb-2">
                                    <span class="menu-section text-muted text-uppercase fs-8 ls-1">Brouillards de caisse
                                    </span>
                            </div>
                        </div>


                        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <span class="menu-link">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                 <path opacity="0.3" d="M3 3V17H7V21H15V9H20V3H3Z" fill="black"/>
                                                <path d="M20 22H3C2.4 22 2 21.6 2 21V3C2 2.4 2.4 2 3 2H20C20.6 2 21 2.4 21 3V21C21 21.6 20.6 22 20 22ZM19 4H4V8H19V4ZM6 18H4V20H6V18ZM6 14H4V16H6V14ZM6 10H4V12H6V10ZM10 18H8V20H10V18ZM10 14H8V16H10V14ZM10 10H8V12H10V10ZM14 18H12V20H14V18ZM14 14H12V16H14V14ZM14 10H12V12H14V10ZM19 14H17V20H19V14ZM19 10H17V12H19V10Z"
                                                      fill="black"/>
                                          </svg>
                                        </span>
                                    </span>
                                    <span class="menu-title">Brouillards de caisse</span>
                                    <span class="menu-arrow"></span>
                                </span>
                            <div class="menu-sub menu-sub-accordion menu-active-bg" kt-hidden-height="507">
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/reponsable-financier-scolarite-liste-des-brouillard-de-caisse">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">UAHB</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/cmjlf-responsable-financier-scolarite-liste-des-brouillard-de-caisse">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">CMJLF</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link"
                                       href="/personnel/ps1-responsable-financier-scolarite-liste-des-brouillard-de-caisse">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">CTD</span>
                                    </a>
                                </div>
                            </div>
                        </div>


                        <div class="menu-item">
                            <div class="menu-content pb-2">
                                <span class="menu-section text-muted text-uppercase fs-8 ls-1">BANQUE </span>
                            </div>
                        </div>

                        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
                                <span class="menu-link">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <path opacity="0.3"
                                                      d="M19 22H5C4.4 22 4 21.6 4 21V3C4 2.4 4.4 2 5 2H14V4H6V20H18V8H20V21C20 21.6 19.6 22 19 22Z"
                                                      fill="black"/>
                                                <path d="M15 8H20L14 2V7C14 7.6 14.4 8 15 8Z" fill="black"/>
                                        </svg>
                                        </span>
                                    </span>
                                    <span class="menu-title">Reçu banque UAHB</span>
                                    <span class="menu-arrow"></span>
                                </span>
                            <div class="menu-sub menu-sub-accordion menu-active-bg" kt-hidden-height="507">
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/reponsable-financier-recu-banque">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">UAHB</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/cmjlf-responsable-financier-recu-banque">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">CMJLF</span>
                                    </a>
                                </div>
                                <div class="menu-item">
                                    <a class="menu-link" href="/personnel/ps1-responsable-financier-recu-banque">
                                            <span class="menu-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                        <span class="menu-title">CTD</span>
                                    </a>
                                </div>
                            </div>
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
                                            fill="black" />
                                        <path opacity="0.3"
                                              d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z"
                                              fill="black" />
                                    </svg>
                                </span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
                        <a href="/personnel/admin-controller" class="d-lg-none">
                            <img alt="Logo" src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" class="h-30px" />
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
                                            <h3> <span class="menu-title">Environnement Numérique de Travail</span>
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
                                        } ?>" />
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
                                                    } ?>" />
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <div class="fw-bolder d-flex align-items-center fs-5"><?php if (!empty($_SESSION['tmpPrenom'])) {
                                                            echo ucwords(strtolower($_SESSION['tmpPrenom'])) . ' ' . strtoupper($_SESSION['tmpNom']);
                                                        } else {
                                                            echo "test";
                                                        } ?>
                                                    </div>
                                                    <a href="#" class="fw-bold text-muted text-hover-primary fs-7"><?php if (!empty($_SESSION['tmpEmail'])) {
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
                                                           data-kt-url="/personnel/quitter" />
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
                                                        fill="black" />
                                                    <path opacity="0.3"
                                                          d="M21 16H3C2.4 16 2 15.6 2 15V14C2 13.4 2.4 13 3 13H21C21.6 13 22 13.4 22 14V15C22 15.6 21.6 16 21 16ZM14 20V19C14 18.4 13.6 18 13 18H3C2.4 18 2 18.4 2 19V20C2 20.6 2.4 21 3 21H13C13.6 21 14 20.6 14 20Z"
                                                          fill="black" />
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
                            <h1 class="d-flex align-items-center text-dark fw-bolder fs-3 my-1">Services financiers
                            </h1>
                            <span class="h-20px border-gray-200 border-start mx-4"></span>
                            <ul class="breadcrumb breadcrumb-separatorless fw-bold fs-7 my-1">
                                <li class="breadcrumb-item text-muted">
                                    <a href="javascript:void(0)" class="text-dark text-hover-primary">Responsable des finances</a>
                                    <span class="h-20px border-gray-200 border-start mx-4"></span>

                                    <a href="/personnel/comptable-accueil"
                                       class="text-muted text-hover-primary">accueil</a>
                                </li>
                                <span class="h-20px border-gray-200 border-start mx-4"></span>

                                <li class="breadcrumb-item text-muted">
                                    <span class="text-muted text-hover-primary">scolarité - </span>
                                </li>
                                <li class="breadcrumb-item text-muted">
                                    <span class="text-muted text-hover-primary">liste des étudiants</span>
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
                                    <span class="ps1-card__stat-label" >Actifs</span>
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
                                            <div class="d-flex justify-content-end align-items-center d-none"
                                                 data-kt-docs-table-toolbar="selected">
                                                <div class="fw-bolder me-5">
                                                        <span class="me-2"
                                                              data-kt-docs-table-select="selected_count"></span>Selected
                                                </div>
                                                <button type="button" class="btn btn-danger"
                                                        data-kt-docs-table-select="delete_selected">Selection
                                                    Action
                                                </button>
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
                      fill="black" />
                <path
                    d="M12.5657 8.56569L16.75 12.75C17.1642 13.1642 17.8358 13.1642 18.25 12.75C18.6642 12.3358 18.6642 11.6642 18.25 11.25L12.7071 5.70711C12.3166 5.31658 11.6834 5.31658 11.2929 5.70711L5.75 11.25C5.33579 11.6642 5.33579 12.3358 5.75 12.75C6.16421 13.1642 6.83579 13.1642 7.25 12.75L11.4343 8.56569C11.7467 8.25327 12.2533 8.25327 12.5657 8.56569Z"
                    fill="black" />
            </svg>
        </span>
</div>

<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script src="/personnel/scripts.bundle.7.js"></script>
</body>

</html>