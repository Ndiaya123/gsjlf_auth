<?php
session_start();
// État de l'arrêt de caisse du jour, calculé directement côté serveur (pas
// besoin d'AJAX pour ça) — détermine la couleur du bouton (rouge/jaune).
$dgaArreteDejaEffectue = false;
try {
    include_once('../../bdBASI.php'); // ← ajuster selon la profondeur réelle du fichier
    $BDBASI = new BDBASI();
    $bdBASIVerif = $BDBASI->connect();
    if ($bdBASIVerif && !empty($_SESSION['tmpIdBASI'])) {
        $stmtVerifArrete = $bdBASIVerif->prepare("
            SELECT id FROM arrete_caisse WHERE idCaissier = ? AND date_alimentation = CURDATE() LIMIT 1
        ");
        $stmtVerifArrete->execute([(int)$_SESSION['tmpIdBASI']]);
        $dgaArreteDejaEffectue = (bool) $stmtVerifArrete->fetch();
    }
} catch (\Throwable $e) {
    error_log('[caissier-paiement][verifArrete] ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Paiement des commandes — ENT GSJLF</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"/>
    <link href="/personnel/ressources/dist_assets/css/style_basi_2.css" rel="stylesheet" type="text/css"/>


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
                <a href="/personnel/responsable-financier-accueil" style="margin-left:65px;" id="lien_logo1">
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
                        <a href="/personnel/responsable-financier-accueil" class="d-lg-none" id="lien_logo2">
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
                                        <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                                    </svg>
                                    Paiement des commandes
                                </h1>
                                <p>Réglez les commandes d'achat et de paiement en attente</p>
                            </div>
                            <a href="/personnel/arrete-caisse-pdf" target="_blank"
                               id="dga-btn-arrete-caisse"
                               class="dga-btn-arrete <?php echo $dgaArreteDejaEffectue ? 'dga-btn-arrete--effectue' : 'dga-btn-arrete--attente'; ?>"
                               onclick="return dga_confirmerArreteCaisseLien(event, this.href, <?php echo $dgaArreteDejaEffectue ? 'true' : 'false'; ?>);">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
                                <?php echo $dgaArreteDejaEffectue ? 'Arrêt de caisse (consulter)' : 'Arrêt de caisse'; ?>
                            </a>
                        </div>

                        <!-- ══ STATISTIQUES PAR MODE ══ -->
                        <div class="dga-stats-modes">
                            <div class="dga-mode-card dga-mc-liquide">
                                <div class="dga-mode-card-title">
                                    <div class="dga-mode-card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg></div>
                                    Liquide
                                </div>
                                <div class="dga-mode-card-rows">
                                    <div class="dga-mode-card-row"><span class="lbl">Alloué</span><span class="val" id="dga-liq-alloue">0 FCFA</span></div>
                                    <div class="dga-mode-card-row"><span class="lbl">Utilisé</span><span class="val" id="dga-liq-utilise">0 FCFA</span></div>
                                    <div class="dga-mode-card-row dga-mcr-solde"><span class="lbl">Solde</span><span class="val" id="dga-liq-solde">0 FCFA</span></div>
                                </div>
                            </div>
                            <div class="dga-mode-card dga-mc-wave">
                                <div class="dga-mode-card-title">
                                    <div class="dga-mode-card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 12c2-4 4-4 6 0s4 4 6 0 4-4 6 0"/></svg></div>
                                    Wave
                                </div>
                                <div class="dga-mode-card-rows">
                                    <div class="dga-mode-card-row"><span class="lbl">Alloué</span><span class="val" id="dga-wave-alloue">0 FCFA</span></div>
                                    <div class="dga-mode-card-row"><span class="lbl">Utilisé</span><span class="val" id="dga-wave-utilise">0 FCFA</span></div>
                                    <div class="dga-mode-card-row dga-mcr-solde"><span class="lbl">Solde</span><span class="val" id="dga-wave-solde">0 FCFA</span></div>
                                </div>
                            </div>
                            <div class="dga-mode-card dga-mc-om">
                                <div class="dga-mode-card-title">
                                    <div class="dga-mode-card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></div>
                                    Orange Money
                                </div>
                                <div class="dga-mode-card-rows">
                                    <div class="dga-mode-card-row"><span class="lbl">Alloué</span><span class="val" id="dga-om-alloue">0 FCFA</span></div>
                                    <div class="dga-mode-card-row"><span class="lbl">Utilisé</span><span class="val" id="dga-om-utilise">0 FCFA</span></div>
                                    <div class="dga-mode-card-row dga-mcr-solde"><span class="lbl">Solde</span><span class="val" id="dga-om-solde">0 FCFA</span></div>
                                </div>
                            </div>
                        </div>

                        <!-- ══ COMPTEURS ══ -->
                        <div class="dga-stats-counters">
                            <div class="dga-counter-card dga-cc-traitees">
                                <div class="dga-counter-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>
                                <div><div class="dga-counter-lbl">Traitées aujourd'hui</div><div class="dga-counter-val" id="dga-cnt-traitees">0</div></div>
                            </div>
                            <div class="dga-counter-card dga-cc-partielles">
                                <div class="dga-counter-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
                                <div><div class="dga-counter-lbl">Partiellement payées</div><div class="dga-counter-val" id="dga-cnt-partielles">0</div></div>
                            </div>
                            <div class="dga-counter-card dga-cc-attente">
                                <div class="dga-counter-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                                <div><div class="dga-counter-lbl">En attente de paiement</div><div class="dga-counter-val" id="dga-cnt-attente">0</div></div>
                            </div>
                        </div>

                        <!-- ══ TABLE COMMANDES À PAYER ══ -->
                        <div class="dga-card">
                            <div class="dga-card-head">
                                <span class="dga-card-title">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
                                        <line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/>
                                    </svg>
                                    Commandes à payer
                                </span>
                            </div>
                            <div id="dga-alerte-arrete"></div>
                            <table id="dga-table-commandes" class="display" style="width:100%">
                                <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Nom</th>
                                    <th>Type</th>
                                    <th>Montant total</th>
                                    <th>Payé</th>
                                    <th>Restant</th>
                                    <th>Mode</th>
                                    <th>Statut</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <!-- ══ MODALE DE PAIEMENT ══ -->
                        <div class="modal fade dga-modal" id="modalPaiement" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2 id="dgaModalTitre">Paiement</h2>
                                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                            <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="modal-body">
                                        <div id="dgaErreurGenerale" class="dga-erreur-generale" style="display:none;"></div>

                                        <div class="dga-paiement-info" id="dgaPaiementInfo"></div>

                                        <div class="dga-montant-a-payer">
                                            <span class="lbl" id="dgaMontantLabel">Montant à régler</span>
                                            <span class="val" id="dgaMontantValeur">0 FCFA</span>
                                        </div>

                                        <div id="dgaSoldeInfo"></div>

                                        <!-- Champs spécifiques Chèque/Virement -->
                                        <div id="dgaBlocBanque" style="display:none;">
                                            <div class="dga-field">
                                                <label for="dgaBanque">Banque</label>
                                                <select id="dgaBanque" class="dga-inp">
                                                    <option value="">Sélectionner…</option>
                                                </select>
                                            </div>
                                            <div class="dga-field">
                                                <label for="dgaNumeroChequeVirement">Numéro du chèque / virement</label>
                                                <input type="text" id="dgaNumeroChequeVirement" class="dga-inp"/>
                                            </div>
                                        </div>

                                        <div class="dga-field">
                                            <label for="dgaRecu">Preuve de paiement (optionnel)</label>
                                            <input type="file" id="dgaRecu" class="dga-inp" accept="application/pdf,image/jpeg,image/png"/>
                                        </div>
                                    </div>
                                    <div class="dga-actions">
                                        <button type="button" class="dga-cancel" data-bs-dismiss="modal">Annuler</button>
                                        <button type="button" class="dga-submit" id="dgaSubmitPaiement">
                                            <span>Valider le paiement</span>
                                            <svg class="dga-spinner hidden" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 019.76 7.8"/></svg>
                                        </button>
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
<script src="/personnel/basi-scripts.bundle.25.js"></script>

</body>
</html>


