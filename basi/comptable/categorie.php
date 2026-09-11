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
    <link href="/personnel/ressources/dist_assets/css/style_basi_12.css" rel="stylesheet" type="text/css"/>

    <style>
        body{background:#f5f8fa;font-family:'Poppins',sans-serif;}

        .dga-hero{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem}
        .dga-hero-title h1{font-size:1.35rem;font-weight:800;color:#111827;margin:0;display:flex;align-items:center;gap:.55rem}
        .dga-hero-title h1 svg{background:#d1fae5;color:#065f46;border-radius:9px;padding:.4rem;width:20px !important;height:20px !important;box-sizing:content-box}
        .dga-hero-title p{font-size:.8rem;color:#9ca3af;margin:.2rem 0 0}

        .dga-btn-primary{display:inline-flex !important;align-items:center !important;gap:.4rem !important;padding:.65rem 1.25rem !important;border-radius:10px !important;font-size:.85rem !important;font-weight:700 !important;background:#1a7a5e !important;color:#fff !important;border:none !important;cursor:pointer !important;transition:all .18s;text-decoration:none !important}
        .dga-btn-primary:hover{background:#145f49 !important;transform:translateY(-1px);color:#fff !important}

        .dga-stat-card{background:#fff;border-radius:14px;border:1px solid #e9ecef;padding:1.15rem 1.3rem;display:flex;align-items:center;gap:1rem;position:relative;overflow:hidden;margin-bottom:1.25rem;max-width:320px}
        .dga-stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:#1a7a5e}
        .dga-stat-icon{width:42px;height:42px;border-radius:12px;background:#d1fae5;color:#065f46;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .dga-stat-lbl{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:.3rem}
        .dga-stat-val{font-size:1.4rem;font-weight:900;color:#111827;font-variant-numeric:tabular-nums}

        .dga-card{background:#fff;border-radius:14px;border:1px solid #e9ecef;box-shadow:0 1px 4px rgba(0,0,0,.05);overflow:hidden}
        .dga-card-title{font-size:.88rem;font-weight:700;color:#111827;display:flex;align-items:center;gap:.45rem}
        .dga-card-title svg{color:#1a7a5e}
        .dga-card-head{padding:1rem 1.35rem;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem}

        .dga-search{position:relative}
        .dga-search svg{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:#9ca3af}
        .dga-search input{border:1.5px solid #e5e7eb !important;border-radius:9px !important;padding:.55rem .9rem .55rem 2.4rem !important;font-size:.83rem !important;color:#374151 !important;min-width:230px;background:#fff !important;box-shadow:none !important}
        .dga-search input:focus{outline:none;border-color:#1a7a5e !important;box-shadow:0 0 0 3px rgba(26,122,94,.1) !important}

        .dga-table-wrap{padding:0 1.35rem 1.35rem}
        table.dga-table{width:100%;border-collapse:collapse}
        table.dga-table thead th{background:#f8f9fa;font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:#9ca3af;border-bottom:2px solid #e9ecef;padding:.85rem 1rem;text-align:left;white-space:nowrap}
        table.dga-table tbody td{padding:.85rem 1rem;font-size:.875rem;color:#374151;border-bottom:1px solid #f9fafb;vertical-align:middle}
        table.dga-table tbody tr:hover td{background:#f0fdf4}
        table.dataTable{border-collapse:collapse !important;width:100% !important}
        table.dataTable thead th{background:#f8f9fa !important;font-size:.7rem !important;font-weight:800 !important;text-transform:uppercase !important;letter-spacing:.07em !important;color:#9ca3af !important;border-bottom:2px solid #e9ecef !important;border-top:none !important;padding:.85rem 1rem !important;white-space:nowrap}
        table.dataTable tbody td{padding:.85rem 1rem !important;font-size:.875rem !important;color:#374151 !important;border-bottom:1px solid #f9fafb !important;vertical-align:middle !important}
        table.dataTable tbody tr:hover td{background:#f0fdf4 !important}
        .dataTables_empty{padding:2.5rem !important;font-style:italic !important;color:#9ca3af !important}
        .dataTables_paginate .paginate_button.current{background:#1a7a5e !important;color:#fff !important;border-color:#1a7a5e !important;border-radius:7px !important}

        .dga-btn-modifier,.dga-btn-supprimer{display:inline-flex !important;align-items:center !important;gap:.35rem !important;padding:.4rem .8rem !important;border-radius:7px !important;font-size:.75rem !important;font-weight:700 !important;border:1.5px solid transparent !important;cursor:pointer !important;transition:all .18s;margin-right:.35rem}
        .dga-btn-modifier:hover,.dga-btn-supprimer:hover{transform:translateY(-1px)}
        .dga-btn-modifier{background:#ecfdf5 !important;color:#059669 !important;border-color:#d1fae5 !important}
        .dga-btn-modifier:hover{background:#059669 !important;color:#fff !important}
        .dga-btn-supprimer{background:#fef2f2 !important;color:#dc2626 !important;border-color:#fee2e2 !important}
        .dga-btn-supprimer:hover{background:#dc2626 !important;color:#fff !important}

        /* ── Modales (même gabarit que le reste du projet) ──────────────── */
        .modal-content{border-radius:16px !important;border:none !important;box-shadow:0 24px 64px rgba(0,0,0,.18) !important;overflow:hidden}
        .modal-header{background:linear-gradient(135deg,#064e3b,#1a7a5e) !important;border:none !important;padding:1.25rem 1.5rem !important}
        .modal-header h2{color:#fff !important;font-size:1rem !important;font-weight:800 !important;margin:0 !important}
        .modal-header .btn-icon .svg-icon svg path,.modal-header .btn-icon .svg-icon svg rect{fill:#fff !important}
        .modal-body{padding:1.5rem !important}
        .modal-body .form-label{font-size:.72rem !important;font-weight:700 !important;color:#6b7280 !important;text-transform:uppercase;letter-spacing:.04em}
        .modal-body .form-control{border:1.5px solid #e5e7eb !important;border-radius:8px !important;padding:.6rem .9rem !important;font-size:.85rem !important}
        .modal-body .form-control:focus{outline:none !important;border-color:#1a7a5e !important;box-shadow:0 0 0 3px rgba(26,122,94,.1) !important}
        .modal-body .btn-primary{background:#1a7a5e !important;border-color:#1a7a5e !important;border-radius:9px !important;font-weight:700 !important;padding:.6rem 1.4rem !important}
        .modal-body .btn-primary:hover{background:#145f49 !important;border-color:#145f49 !important}
        .modal-body .btn-light{border-radius:9px !important;font-weight:600 !important;padding:.6rem 1.4rem !important}
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
                <a href="/personnel/responsable-financier-accueil" style="margin-left: 65px;" id="lien_logo1">
                    <img alt="Logo" src="/personnel/ressources/dist_assets/media/logos/1.png" id="logo1" class="h-50px logo"/>
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
                         id="kt_aside_menu" data-kt-menu="true" >
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
                    <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0" >
                        <a href="/personnel/responsable-financier-accueil" class="d-lg-none" id="lien_logo2">
                            <img alt="Logo" src="/personnel/ressources/dist_assets/media/logos/1.png" id="logo2"   class="h-30px"/>
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
                                        <a class="menu-link py-3" href="" id="lien_ent">
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
                                        <img src="" id="user_photo1"/>
                                    </div>
                                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-primary fw-bold py-4 fs-6 w-275px"
                                         data-kt-menu="true">
                                        <div class="menu-item px-3">
                                            <div class="menu-content d-flex align-items-center px-3">
                                                <div class="symbol symbol-50px me-5">
                                                    <img alt="admin" src="" id="user_photo2"/>
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <div class="fw-bolder d-flex align-items-center fs-5" id="user_pn">
                                                    </div>
                                                    <a href="javascript:void(0)"
                                                       class="fw-bold text-muted text-hover-primary fs-7" id="user_email"></a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="separator my-2"></div>
                                        <div class="menu-item px-5">
                                            <a href="javascript:void(0)" onclick="actionMonProfile()"
                                               class="menu-link px-5">Mon profil</a>
                                        </div>
                                        <div class="separator my-2" ></div>
                                        <div class="menu-item px-5">
                                            <div class="menu-content px-5">
                                                <label class="form-check form-switch form-check-custom form-check-solid pulse pulse-success" for="kt_user_menu_dark_mode_toggle">
                                                    <div class="menu-item px-5">
                                                        <div class="menu-content px-5">
                                                            <label class="form-check form-switch form-check-custom form-check-solid pulse pulse-success" for="kt_user_menu_dark_mode_toggle">
                                                                <a href="/personnel/signout">
                                                                    <input class="form-check-input w-30px h-20px" checked="checked" type="checkbox" value="1" name="mode" id="kt_user_menu_dark_mode_toggle" data-kt-url="/personnel/quitter" />
                                                                    <span class="pulse-ring ms-n1"></span>
                                                                    <span class="form-check-label text-gray-600 fs-7">se déconnecter</span>
                                                                </a>
                                                            </label>
                                                        </div>
                                                    </div>
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
                             class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0" id="infoAppli">

                        </div>
                    </div>
                </div>
                <div class="post d-flex flex-column-fluid" id="kt_post">
                    <div id="kt_content_container" class="container-xxl">

                        <!-- ══ BANDEAU TITRE ══ -->
                        <div class="dga-hero">
                            <div class="dga-hero-title">
                                <h1>
                                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/>
                                    </svg>
                                    Gestion des catégories
                                </h1>
                                <p>Créez et gérez les catégories de produits</p>
                            </div>
                            <a href="#" class="dga-btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_categorie">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Nouvelle catégorie
                            </a>
                        </div>

                        <!-- ══ STATISTIQUE ══ -->
                        <div class="dga-stat-card">
                            <div class="dga-stat-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/></svg>
                            </div>
                            <div>
                                <div class="dga-stat-lbl">Nombre total</div>
                                <div class="dga-stat-val" id="dga-stat-nombre-categories">0</div>
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
                                    Catégories
                                </span>
                                <div class="dga-search">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                    </svg>
                                    <input type="text" data-kt-docs-table-filter="search" placeholder="Rechercher"/>
                                </div>
                                <div class="d-flex justify-content-end align-items-center d-none" data-kt-docs-table-toolbar="selected">
                                    <div class="fw-bolder me-5">
                                        <span class="me-2" data-kt-docs-table-select="selected_count"></span>Selected
                                    </div>
                                    <button type="button" class="btn btn-danger" data-kt-docs-table-select="delete_selected">Selection Action</button>
                                </div>
                            </div>

                            <div class="dga-table-wrap">
                                <div class="table-responsive">
                                    <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4 dga-table" id="table_categorie">
                                        <thead>
                                        <tr class="fw-bolder text-muted">
                                            <th class="min-w-120px">N°</th>
                                            <th class="min-w-200px">Nom</th>
                                            <th class="min-w-150px">Date de création</th>
                                            <th class="min-w-150px">Créateur</th>
                                            <th class="min-w-100px">Action</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
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

<!--begin::Modal - New Card-->
<div class="modal fade" id="kt_modal_new_categorie" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajouter une categorie</h2>
                <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal"
                     onclick="closecategorie()">
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
                <form id="formcategorie" class="form" action="#">
                    <input type="hidden" name="option" value="2"/>
                    <div class="d-flex flex-column mb-7 fv-row">
                        <label class="d-flex align-items-center fs-6 fw-bold form-label mb-2">
                            <span class="required">Nom</span>
                            <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip"
                               title="Le nom de la categorie doit être unique."></i>
                        </label>
                        <input type="text" class="form-control form-control-solid" placeholder="" name="nom_categorie"
                               id="nom_categorie" value=""/>
                    </div>

                    <div class="text-center pt-15">
                        <button type="reset" class="btn btn-light me-3" onclick="closecategorie()">Annuler</button>
                        <button type="submit" id="formcategorie_submit" class="btn btn-primary">
                            <span class="indicator-label">Ajouter</span>
                            <span class="indicator-progress">Veuillez patienter...
															<span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!--editer-->

<div class="modal fade" id="kt_modal_update_categorie" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Modifier une categorie</h2>
                <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal"
                     onclick="closecategorieUpdate()">
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
                <form id="formcategorieUpdate" class="form" action="#">
                    <input type="hidden" name="option" value="3"/>
                    <input type="hidden" name="tmp" id="tmp" value=""/>
                    <input type="hidden" name="original_nom" id="original_nom" value=""/>

                    <div class="d-flex flex-column mb-7 fv-row">
                        <label class="d-flex align-items-center fs-6 fw-bold form-label mb-2">
                            <span class="required">Nom</span>
                            <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip"
                               title="Le nom de la categorie doit être unique."></i>
                        </label>
                        <input type="text" class="form-control form-control-solid" placeholder="" name="nom_categorie_up"
                               id="nom_categorie_up" value=""/>
                    </div>

                    <div class="text-center pt-15">
                        <button type="reset" class="btn btn-light me-3" onclick="closecategorieUpdate()">Annuler</button>
                        <button type="submit" id="formcategorieUpdate_submit" class="btn btn-primary">
                            <span class="indicator-label">Modifier</span>
                            <span class="indicator-progress">Veuillez patienter...
															<span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>




<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script src="/personnel/basi-scripts.bundle.3.js"></script>
<script src="/personnel/scripts.bundle.gs.js"></script>

<script>
    // Mise à jour automatique du compteur "Nombre total" — n'interfère pas
    // avec le JS existant (basi-scripts.bundle.3.js), se contente d'observer
    // le tableau et de compter les lignes affichées.
    (function () {
        var tbody = document.querySelector('#table_categorie tbody');
        var compteur = document.getElementById('dga-stat-nombre-categories');
        if (!tbody || !compteur) return;

        function majCompteur() {
            var lignes = tbody.querySelectorAll('tr');
            compteur.textContent = lignes.length;
        }

        majCompteur();
        new MutationObserver(majCompteur).observe(tbody, { childList: true, subtree: false });
    })();
</script>


</body>

</html>