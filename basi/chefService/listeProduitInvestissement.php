<?php
include_once($_SERVER['DOCUMENT_ROOT'] . '/uahb/sessions/verification.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Expressions de besoin — ENT GSJLF (Chef de direction)</title>
    <link rel="shortcut icon" href="http://localhost/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700"/>
    <link href="/uahb/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/uahb/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/uahb/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"/>

    <style>
        body{background:#f5f8fa;font-family:'Poppins',sans-serif;}

        #dga-loader{position:fixed;inset:0;z-index:9999;pointer-events:all}
        #dga-loader .dga-loader-bg{position:absolute;inset:0;background:rgba(10,40,25,.45);backdrop-filter:blur(4px)}
        #dga-loader .dga-loader-box{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:16px;padding:2rem 2.5rem;display:flex;flex-direction:column;align-items:center;gap:.9rem;box-shadow:0 20px 60px rgba(0,0,0,.18);min-width:190px}
        #dga-loader .dga-loader-spin{width:40px;height:40px;animation:dga-spin .85s linear infinite}
        #dga-loader .dga-loader-spin circle{stroke:#1a7a5e;stroke-dasharray:80;stroke-dashoffset:55;stroke-linecap:round;fill:none;animation:dga-dash 1.4s ease-in-out infinite}
        #dga-loader .dga-loader-box p{margin:0;font-size:.82rem;font-weight:700;color:#1a7a5e}
        @keyframes dga-spin{to{transform:rotate(360deg)}}
        @keyframes dga-dash{0%{stroke-dashoffset:80}50%{stroke-dashoffset:20}100%{stroke-dashoffset:80}}

        .dga-hero{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem}
        .dga-hero-title h1{font-size:1.35rem;font-weight:800;color:#111827;margin:0;display:flex;align-items:center;gap:.55rem}
        .dga-hero-title h1 svg{background:#d1fae5;color:#065f46;border-radius:9px;padding:.4rem;width:20px !important;height:20px !important;box-sizing:content-box}
        .dga-hero-title p{font-size:.8rem;color:#9ca3af;margin:.2rem 0 0}

        .dga-stats-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:.85rem;margin-bottom:1.25rem}
        .dga-stat-card{background:#fff;border-radius:14px;border:1px solid #e9ecef;padding:1.15rem 1.3rem;display:flex;align-items:center;gap:1rem;position:relative;overflow:hidden}
        .dga-stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:#1a7a5e}
        .dga-stat-icon{width:42px;height:42px;border-radius:12px;background:#d1fae5;color:#065f46;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .dga-stat-lbl{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:.3rem}
        .dga-stat-val{font-size:1.4rem;font-weight:900;color:#111827;font-variant-numeric:tabular-nums}

        .dga-sc-soumise::before{background:#f59e0b}
        .dga-sc-soumise .dga-stat-icon{background:#fef3c7;color:#92400e}
        .dga-sc-validee::before{background:#10b981}
        .dga-sc-validee .dga-stat-icon{background:#d1fae5;color:#047857}
        .dga-sc-rejetee::before{background:#ef4444}
        .dga-sc-rejetee .dga-stat-icon{background:#fee2e2;color:#991b1b}
        .dga-sc-partiel::before{background:#f59e0b}
        .dga-sc-partiel .dga-stat-icon{background:#fef3c7;color:#92400e}
        .dga-sc-terminee::before{background:#7c3aed}
        .dga-sc-terminee .dga-stat-icon{background:#ede9fe;color:#5b21b6}

        @media(max-width:1100px){.dga-stats-grid{grid-template-columns:repeat(3,1fr)}}
        @media(max-width:700px){.dga-stats-grid{grid-template-columns:1fr 1fr}}
        @media(max-width:480px){.dga-stats-grid{grid-template-columns:1fr}}

        .dga-card{background:#fff;border-radius:14px;border:1px solid #e9ecef;box-shadow:0 1px 4px rgba(0,0,0,.05);overflow:hidden}
        .dga-card-head{padding:.9rem 1.35rem;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem}
        .dga-card-title{font-size:.88rem;font-weight:700;color:#111827;display:flex;align-items:center;gap:.45rem}
        .dga-card-title svg{color:#1a7a5e}

        .dga-filters{display:flex;flex-wrap:wrap;gap:.85rem;align-items:flex-end;padding:1rem 1.35rem;border-bottom:1px solid #f3f4f6;background:#fafafa}
        .dga-filter-group{display:flex;flex-direction:column;gap:.3rem}
        .dga-filter-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af}
        .dga-inp-annee{border:1.5px solid #e5e7eb;border-radius:8px;padding:.5rem .85rem;font-size:.82rem;color:#374151;min-width:120px}
        .dga-annee-sep{padding-bottom:.6rem;color:#9ca3af;font-weight:600}
        .dga-btn-reset,.dga-btn-filtrer{display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1.1rem;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;border:1.5px solid transparent}
        .dga-btn-reset{background:#fff;color:#6b7280;border-color:#e5e7eb}
        .dga-btn-reset:hover{border-color:#9ca3af;color:#374151}
        .dga-btn-filtrer{background:#1a7a5e;color:#fff}
        .dga-btn-filtrer:hover{background:#145f49}

        table.dataTable{border-collapse:collapse !important;width:100% !important}
        table.dataTable thead th{background:#f8f9fa !important;font-size:.7rem !important;font-weight:800 !important;text-transform:uppercase !important;letter-spacing:.07em !important;color:#9ca3af !important;border-bottom:2px solid #e9ecef !important;border-top:none !important;padding:.85rem 1rem !important;white-space:nowrap}
        table.dataTable tbody td{padding:.85rem 1rem !important;font-size:.875rem !important;color:#374151 !important;border-bottom:1px solid #f9fafb !important;vertical-align:middle !important}
        table.dataTable tbody tr:hover td{background:#f0fdf4 !important}
        .dataTables_empty{padding:2.5rem !important;font-style:italic !important;color:#9ca3af !important}
        .dataTables_paginate .paginate_button.current{background:#1a7a5e !important;color:#fff !important;border-color:#1a7a5e !important;border-radius:7px !important}

        .dga-badge-statut{display:inline-flex;align-items:center;padding:.25rem .65rem;border-radius:99px;font-size:.68rem;font-weight:800;text-transform:uppercase}
        .dga-statut-2{background:#dbeafe;color:#1d4ed8}
        .dga-statut-3{background:#d1fae5;color:#047857}
        .dga-statut-4{background:#fee2e2;color:#991b1b}
        .dga-statut-5{background:#fef3c7;color:#92400e}
        .dga-statut-6{background:#ede9fe;color:#5b21b6}
        .dga-statut-7{background:#dbeafe;color:#1e40af}
        .dga-statut-8{background:#d1fae5;color:#065f46}
        .dga-statut-9{background:#e0e7ff;color:#3730a3}
        .dga-statut-10{background:#f3f4f6;color:#6b7280}

        .dga-btn-valider,.dga-btn-rejeter,.dga-btn-consulter{
            display:inline-flex !important;align-items:center !important;gap:.35rem !important;padding:.4rem .8rem !important;border-radius:7px !important;
            font-size:.75rem !important;font-weight:700 !important;border:1.5px solid transparent !important;cursor:pointer !important;transition:all .18s;margin-right:.35rem;
        }
        .dga-btn-valider:hover,.dga-btn-rejeter:hover,.dga-btn-consulter:hover{transform:translateY(-1px)}
        .dga-btn-valider{background:#ecfdf5 !important;color:#059669 !important;border-color:#d1fae5 !important}
        .dga-btn-valider:hover{background:#059669 !important;color:#fff !important}
        .dga-btn-rejeter{background:#fef2f2 !important;color:#dc2626 !important;border-color:#fee2e2 !important}
        .dga-btn-rejeter:hover{background:#dc2626 !important;color:#fff !important}
        .dga-btn-consulter{background:#f3f4f6 !important;color:#374151 !important;border-color:#e5e7eb !important}
        .dga-btn-consulter:hover{background:#e5e7eb !important}

        /* ── Modale ───────────────────────────────────────────────────── */
        .dga-modal .modal-dialog{max-width:680px}
        .dga-modal .modal-content{border-radius:16px !important;border:none !important;box-shadow:0 24px 64px rgba(0,0,0,.18) !important;overflow:hidden}
        .dga-modal .modal-header{background:linear-gradient(135deg,#064e3b,#1a7a5e) !important;border:none !important;padding:1.25rem 1.5rem !important}
        .dga-modal .modal-header h2{color:#fff !important;font-size:1rem !important;font-weight:800 !important;margin:0 !important}
        .dga-modal .modal-body{padding:1.35rem 1.5rem !important;max-height:70vh;overflow-y:auto}

        .dga-erreur-generale{margin-bottom:1rem;padding:.7rem .9rem;border-radius:9px;background:#fef2f2;border:1.5px solid #fecaca;color:#991b1b;font-size:.82rem;font-weight:600}

        table.dga-table-produits{width:100%;border-collapse:collapse;font-size:.85rem;margin-bottom:.5rem}
        table.dga-table-produits thead th{background:#f8f9fa;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;padding:.6rem .75rem;text-align:left;border-bottom:2px solid #e9ecef}
        table.dga-table-produits tbody td{padding:.55rem .75rem;border-bottom:1px solid #f3f4f6;color:#374151}
        .dga-inp-qte-reelle{width:100px;border:1.5px solid #e5e7eb;border-radius:6px;padding:.35rem .55rem;font-size:.82rem}

        /* ── Modale Informations sur les sorties ─────────────────────── */
        .dga-btn-sorties-info{background:#ede9fe !important;color:#5b21b6 !important;border-color:#ddd6fe !important}
        .dga-btn-sorties-info:hover{background:#5b21b6 !important;color:#fff !important}
        .dga-section-produit{margin-bottom:1.2rem;padding-bottom:1rem;border-bottom:1px solid #f3f4f6}
        .dga-section-produit:last-child{border-bottom:none;margin-bottom:0}
        .dga-section-produit-titre{font-size:.85rem;font-weight:800;color:#111827;margin-bottom:.4rem}
        .dga-section-produit-qtes{font-size:.76rem;color:#6b7280;margin-bottom:.6rem}
        table.dga-table-sorties-detail{width:100%;border-collapse:collapse;font-size:.8rem}
        table.dga-table-sorties-detail thead th{background:#f8f9fa;font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;padding:.5rem .7rem;text-align:left;border-bottom:2px solid #e9ecef}
        table.dga-table-sorties-detail tbody td{padding:.45rem .7rem;border-bottom:1px solid #f3f4f6;color:#374151}
        .dga-inp-qte-reelle:focus{outline:none;border-color:#1a7a5e}

        .dga-actions{display:flex;justify-content:flex-end;gap:.6rem;padding:1rem 1.35rem;border-top:1px solid #f3f4f6;background:#fafafa}
        .dga-cancel{padding:.55rem 1.15rem;border-radius:8px;font-size:.82rem;font-weight:600;background:#fff;color:#6b7280;border:1.5px solid #e5e7eb;cursor:pointer}
        .dga-cancel:hover{border-color:#9ca3af;color:#374151}
        .dga-submit{display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1.35rem;border-radius:8px;font-size:.82rem;font-weight:700;background:#1a7a5e;color:#fff;border:none;cursor:pointer;min-width:150px;justify-content:center}
        .dga-submit:hover{background:#145f49}
        .dga-submit:disabled{opacity:.6;cursor:not-allowed}
        .dga-spinner.hidden{display:none !important}
        @keyframes dga-spin2{to{transform:rotate(360deg)}}
        .dga-spinner{animation:dga-spin2 1s linear infinite}
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
                <a href="/uahb/responsable-financier-accueil" style="margin-left:65px;" id="lien_logo1">
                    <img alt="Logo" src="/uahb/ressources/dist_assets/media/logos/1.png" id="logo1" class="h-50px logo"/>
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
                        <a href="/uahb/responsable-financier-accueil" class="d-lg-none" id="lien_logo2">
                            <img alt="Logo" src="/uahb/ressources/dist_assets/media/logos/1.png" id="logo2" class="h-30px"/>
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
                                                <a href="/personnel/signout">
                                                    <input class="form-check-input w-30px h-20px" checked="checked" type="checkbox" value="1" name="mode" id="kt_user_menu_dark_mode_toggle" data-kt-url="/uahb/quitter"/>
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
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
                                    </svg>
                                    Expressions de besoin — Ma direction
                                </h1>
                                <p>Validez ou rejetez les demandes soumises par votre direction</p>
                            </div>
                        </div>

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
                            <div class="dga-stat-card dga-sc-soumise">
                                <div class="dga-stat-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </div>
                                <div>
                                    <div class="dga-stat-lbl">Soumises</div>
                                    <div class="dga-stat-val" id="dga-stat-soumise">0</div>
                                </div>
                            </div>
                            <div class="dga-stat-card dga-sc-validee">
                                <div class="dga-stat-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <div>
                                    <div class="dga-stat-lbl">Validées</div>
                                    <div class="dga-stat-val" id="dga-stat-validee">0</div>
                                </div>
                            </div>
                            <div class="dga-stat-card dga-sc-rejetee">
                                <div class="dga-stat-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </div>
                                <div>
                                    <div class="dga-stat-lbl">Rejetées</div>
                                    <div class="dga-stat-val" id="dga-stat-rejetee">0</div>
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
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 12V8H6a2 2 0 010-4h12v4"/><path d="M4 6v14a2 2 0 002 2h14v-4"/><path d="M18 12a2 2 0 000 4h4v-4Z"/></svg>
                                </div>
                                <div>
                                    <div class="dga-stat-lbl">Sortie totale</div>
                                    <div class="dga-stat-val" id="dga-stat-terminee">0</div>
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
                                    Expressions de besoin de la direction
                                </span>
                            </div>

                            <div class="dga-filters">
                                <div class="dga-filter-group">
                                    <label class="dga-filter-label" for="dga-annee-debut">Année de début</label>
                                    <select id="dga-annee-debut" class="dga-inp-annee"></select>
                                </div>
                                <span class="dga-annee-sep">à</span>
                                <div class="dga-filter-group">
                                    <label class="dga-filter-label" for="dga-annee-fin">Année de fin</label>
                                    <select id="dga-annee-fin" class="dga-inp-annee"></select>
                                </div>
                                <div class="dga-filter-group">
                                    <label class="dga-filter-label" for="dga-filtre-statut">Statut</label>
                                    <select id="dga-filtre-statut" class="dga-inp-annee">
                                        <option value="0">Tous</option>
                                        <option value="2" selected>Soumise</option>
                                        <option value="3">Validée</option>
                                        <option value="4">Rejetée</option>
                                        <option value="5">Sortie partielle</option>
                                        <option value="6">Sortie totale</option>
                                    </select>
                                </div>
                                <div style="display:flex;gap:.5rem;">
                                    <button id="dga-btn-reset-filtres" class="dga-btn-reset" type="button">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 109-9M3 12V6m0 6H9"/></svg>
                                        Réinitialiser
                                    </button>
                                    <button id="dga-btn-appliquer-filtres" class="dga-btn-filtrer" type="button">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                                        Filtrer
                                    </button>
                                </div>
                            </div>

                            <table id="dga-table-eb" class="display" style="width:100%">
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

                        <!-- ══ MODALE : Valider ══ -->
                        <div class="modal fade dga-modal" id="modalValider" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2 id="validerModalTitre">Valider l'expression de besoin</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">
                                        <div id="dgaErreurValider" class="dga-erreur-generale" style="display:none;"></div>
                                        <table class="dga-table-produits">
                                            <thead><tr><th>Désignation</th><th>Qté demandée</th><th>Qté réelle</th></tr></thead>
                                            <tbody id="dgaCorpsValider"></tbody>
                                        </table>
                                    </div>
                                    <div class="dga-actions">
                                        <button type="button" class="dga-cancel" data-bs-dismiss="modal">Annuler</button>
                                        <button type="button" class="dga-submit" id="dgaBtnConfirmerValider">
                                            <span>Valider</span>
                                            <svg class="dga-spinner hidden" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 019.76 7.8"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ══ MODALE : Consulter (lecture seule) ══ -->
                        <div class="modal fade dga-modal" id="modalConsulterEB" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2 id="consulterModalTitre">Détail</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">
                                        <div id="dgaContenuConsulter"></div>
                                    </div>
                                    <div class="dga-actions">
                                        <button type="button" class="dga-cancel" data-bs-dismiss="modal">Fermer</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ══ MODALE : Informations sur les sorties (Terminée) ══ -->
                        <div class="modal fade dga-modal" id="modalInfoSorties" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:700px;">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2 id="infoSortiesModalTitre">Informations sur les sorties</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">
                                        <div id="dgaContenuInfoSorties"></div>
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

<script src="/uahb/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/uahb/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/uahb/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script src="http://localhost/personnel/scripts.bundle.gs.js"></script>
<script src="/uahb/basi-scripts.bundle.37.js"></script>

</body>
</html>