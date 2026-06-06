<?php



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
                <a href="/personnel/user-accueil" style="margin-left: 65px;">
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
                            <a class="menu-link active" href="/personnel/user-gestion-taches">
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
                                <span class="menu-title">Gestion des taches</span>
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
                        <a href="/personnel/user-accueil" class="d-lg-none">
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
                                        <a class="menu-link py-3" href="/personnel/user-accueil">
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
                            <h1 class="d-flex align-items-center text-dark fw-bolder fs-3 my-1">Utilisateur
                            </h1>
                            <span class="h-20px border-gray-200 border-start mx-4"></span>
                            <ul class="breadcrumb breadcrumb-separatorless fw-bold fs-7 my-1">
                                <li class="breadcrumb-item text-muted">

                                    <a href="/personnel/user-accueil"
                                       class="text-muted text-hover-primary">Gestion des tache</a>
                                </li>

                            </ul>
                        </div>
                    </div>
                </div>
                <div class="post d-flex flex-column-fluid" id="kt_post">
                    <div class="container-xxl">


                        <div class="tab-content bg-white" id="myTabContent">
                            <div class="tab-pane fade show active" id="home-tab-pane" role="tabpanel" aria-labelledby="home-tab" tabindex="0">
                                <div class="card card-xl-stretch mb-5 mb-xl-8 d-flex align-items-center justify-content-center">
                                    <div class="card-header border-0 pt-5" id="responsabilite">
                                        <div class="d-flex flex-stack mb-5">
                                            <div class="badge badge-white" id="attributionDeTache">
                                                <form action="controller.php" method="post">
                                                    <input type="hidden" name="action" value="add">
                                                    <!-- <div id="refreshAgent">
                                                        <i class="bi bi-arrow-clockwise btn text-primary w-50px" style="font-size: 1.5rem" onclick="refreshAgents()"></i>
                                                    </div> -->

                                                    <div class="col-auto d-flex align-items-start justify-content-center">
                                                        <div>
                                                            <label for="agentSelectForTask">Agent</label>
                                                            <select class="form-control form-control-solid w-250px m-2 bg-primary text-center text-light" id="agentSelectForTask" name="id_utilisateur">
                                                                <option value="">Selectionner un Agent</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label for="listDirecteur">Directeur</label>
                                                            <select class="form-control form-control-solid w-250px m-2 bg-primary text-center text-light" id="listDirecteur" name="id_utilisateur">
                                                                <option value="">Selectionner un Directeur</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <!-- <div class="col-auto d-flex align-items-start justify-content-center">
                                                        <select class="form-control form-control-solid w-250px m-2 bg-primary text-center text-light" id="service" name="id_utilisateur">
                                                            <option value="">Veuillez selectionner un service </option>
                                                        </select>
                                                    </div> -->
                                                    <div class="d-flex align-items-start justify-content-around p-5">
                                                        <div class="shadow-sm card p-5 border border-0 border-info-subtle m-2" style="max-height: 500px; overflow-y: auto;">
                                                            <h6 class="rounded p-5 mb-3 w-400px">Taches de l'agent</h6>
                                                            <div id="tache3" class="badge badge-light-bg-secondary">
                                                                <div class="tache-${tache.id} d-flex align-items-center bg-light-warning rounded p-5 mb-3 w-400px ">
                                                                    <div type="button" class="flex-grow-1 me-2">
                                                                        <span class="fw-bolder text-gray-800 text-hover-primary fs-6">Aucune tache </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- <button id="kt_aside_toggle" class="btn btn-icon w-auto px-0 btn-active-color-primary aside-toggle" >
                                                        </button> -->
                                                        <div id="btn" class="card d-flex align-items-center">
                                                            <div>
                                                                <i class="bi bi-arrow-left-circle btn btn-secondary m-2 shadow-lg attribution disabled" style="font-size: 2rem;" onclick="octroiementTache()"></i><br>
                                                                <i class="bi bi-arrow-right-circle btn btn-secondary m-2 shadow-lg restriction disabled" style="font-size: 2rem;" onclick="restrictionTache()"></i>
                                                            </div>

                                                        </div>
                                                        <div class="shadow-sm  card p-5 border border-0 border-info-subtle m-2" style="max-height: 500px; overflow-y: auto;">
                                                            <h6 class=" rounded p-5 mb-3 w-400px ">Autres taches</h6>
                                                            <div id="services" class="d-none">
                                                                <!-- <i class="bi bi-arrow-clockwise btn text-primary w-50px" style="font-size: 1.5rem" onclick="refreshTache2()"></i> -->
                                                            </div>
                                                            <div id="tache2" class="badge badge-light-bg-secondary">
                                                                <div class="tache-${tache.id} d-flex align-items-center bg-light-warning rounded p-5 mb-3 w-400px ">
                                                                    <div type="button" class="flex-grow-1 me-2">
                                                                        <span class="fw-bolder text-gray-800 text-hover-primary fs-6">Aucune tache </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- <div id="allTache">
                                                        <h1>All tache</h1>
                                                    </div> -->
                                                </form>
                                            </div>
                                        </div>
                                    </div>


                                </div>
                            </div>

                            <div class="tab-pane show active" id="home" role="tabpanel" aria-labelledby="home-tab">





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



<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script src="/personnel/scripts.bundle.13.js"></script>

</body>

</html>