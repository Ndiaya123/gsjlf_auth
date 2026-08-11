// ==================================================
// LOADER
// ==================================================
function showLoader(message) {
    message = message || 'Chargement en cours…';
    $('#global-loader').remove();
    $('body').append(
        '<div id="global-loader">' +
        '<div class="loader-backdrop"></div>' +
        '<div class="loader-box">' +
        '<div class="loader-spinner">' +
        '<svg viewBox="0 0 50 50">' +
        '<circle cx="25" cy="25" r="20" fill="none" stroke-width="4"/>' +
        '</svg>' +
        '</div>' +
        '<p class="loader-msg">' + message + '</p>' +
        '</div>' +
        '</div>'
    );

    if (!$('#loader-style').length) {
        $('head').append(
            '<style id="loader-style">' +
            '#global-loader{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;}' +
            '.loader-backdrop{position:absolute;inset:0;background:rgba(4,20,11,.55);backdrop-filter:blur(4px);animation:loaderFadeIn .2s ease;}' +
            '.loader-box{position:relative;z-index:1;background:#fff;border-radius:20px;padding:36px 48px;display:flex;flex-direction:column;align-items:center;gap:18px;box-shadow:0 0 0 1px rgba(17,59,38,.12),0 24px 60px rgba(0,0,0,.20);animation:loaderSlideUp .25s cubic-bezier(.34,1.56,.64,1);min-width:220px;}' +
            '.loader-spinner svg{width:48px;height:48px;animation:loaderRotate .9s linear infinite;}' +
            '.loader-spinner circle{stroke:#113B26;stroke-linecap:round;stroke-dasharray:80;stroke-dashoffset:60;animation:loaderDash 1.4s ease-in-out infinite;}' +
            '.loader-msg{margin:0;font-size:.85rem;font-weight:600;color:#113B26;letter-spacing:.02em;text-align:center;opacity:.85;}' +
            '@keyframes loaderFadeIn{from{opacity:0;}to{opacity:1;}}' +
            '@keyframes loaderSlideUp{from{opacity:0;transform:translateY(16px) scale(.97);}to{opacity:1;transform:translateY(0) scale(1);}}' +
            '@keyframes loaderRotate{to{transform:rotate(360deg);}}' +
            '@keyframes loaderDash{0%{stroke-dashoffset:80;}50%{stroke-dashoffset:20;}100%{stroke-dashoffset:80;}}' +
            '</style>'
        );
    }
}

function hideLoader() {
    $('#global-loader').remove();
}

// ==================================================
// LOADER SYNC
// ==================================================
var _loaderSync = {
    dtReady:    false,
    statsReady: false
};

function _checkCloseLoader() {
    if (_loaderSync.dtReady && _loaderSync.statsReady) {
        hideLoader();
        _loaderSync.dtReady    = false;
        _loaderSync.statsReady = false;
    }
}

function _resetLoaderSync() {
    _loaderSync.dtReady    = false;
    _loaderSync.statsReady = false;
}

// ==================================================
// UTILS
// ==================================================
function testJSON(text) {
    if (typeof text !== "string") return false;
    try {
        var json = JSON.parse(text);
        return (typeof json === 'object');
    } catch (e) {
        return false;
    }
}

function showSwal(icon, title, html, onClose) {
    var isError  = (icon === 'error' || icon === 'warning');
    var btnColor = icon === 'success' ? '#113B26'
        : icon === 'warning' ? '#d97706'
            : '#d33';

    return Swal.fire({
        icon:               icon,
        title:              title,
        html:               html,
        confirmButtonText:  'OK',
        confirmButtonColor: btnColor,
        background:         '#fff',
        color:              '#333',
        allowOutsideClick:  false,
        allowEscapeKey:     false,
        timer:              3500,
        timerProgressBar:   true,
        showClass: { popup: isError ? 'animate__animated animate__shakeX' : 'animate__animated animate__fadeInDown' },
        hideClass: { popup: 'animate__animated animate__fadeOut' }
    }).then(function () {
        if (typeof onClose === 'function') onClose();
    });
}

function reloadAll() {
    _resetLoaderSync();
    showLoader('Mise à jour en cours…');

    setTimeout(function () {
        if ($.fn.DataTable.isDataTable('#kt_table_taches')) {
            $('#kt_table_taches').DataTable().destroy();
        }

        // statsReady = true car loadStats est indépendant du loader
        _loaderSync.statsReady = true;

        // Rafraîchir les stats sans bloquer le loader
        loadStats();

        // Relancer le DataTable (dtReady sera mis à true dans dt.one('draw'))
        KTDatatablesServerSide.init();
    }, 100);
}

// ==================================================
// HELPERS VALIDATION
// ==================================================
function showInputError(inputId, message) {
    var input = document.getElementById(inputId);
    if (!input) return;
    input.classList.add('is-invalid');

    var existing = input.parentNode.querySelector('.invalid-feedback');
    if (existing) existing.remove();

    var feedback = document.createElement('div');
    feedback.classList.add('invalid-feedback');
    feedback.textContent = message;
    input.parentNode.appendChild(feedback);
}

function clearInputError(inputId) {
    var input = document.getElementById(inputId);
    if (!input) return;
    input.classList.remove('is-invalid');
    var existing = input.parentNode.querySelector('.invalid-feedback');
    if (existing) existing.remove();
}

// ==================================================
// DATATABLE
// ==================================================
var KTDatatablesServerSide = function () {

    var dt;

    function initDatatable() {
        dt = $("#kt_table_taches").DataTable({
            responsive:  true,
            processing:  true,
            select: {
                style:     'os',
                selector:  'td:first-child',
                className: 'row-selected'
            },
            ajax: {
                url:     "/personnel/admin-controller",
                method:  "POST",
                data:    { option: 13 },
                dataSrc: "",
                error: function () {
                    _loaderSync.dtReady = true;
                    _checkCloseLoader();
                    Swal.fire('Erreur', "Impossible de charger la liste des tâches.", 'error');
                }
            },
            columns: [
                { data: "nom" },
                { data: "type" },
                {
                    data:      null,
                    orderable: false,
                    render: function (data, type, row) {
                        if (row.type === "Par défaut") {
                            return "Tout le monde";
                        } else if (row.type === "Incarnée") {
                            return "Agents du post";
                        } else if (row.type === "Structure") {
                            return '<span>' + (row.nombre_utilisateurs || 0) + '</span>';
                        }
                        return '';
                    }
                },
                { data: "code" },
                { data: "commentaire" },
                {
                    data:      null,
                    orderable: false,
                    render: function (data, type, row) {
                        var urlencoded      = btoa(row.id_struture);
                        var activateBtnText  = row.active == 1 ? 'Déactiver' : 'Activer';
                        var activateBtnColor = row.active == 1 ? 'svg-icon-danger' : 'svg-icon-warning';
                        var nomEscaped       = row.nom.replace(/'/g, "\\'");

                        return '<div class="d-flex flex-wrap gap-2 justify-content-center align-items-center">' +

                            '<span class="svg-icon svg-icon-primary svg-icon-2x"' +
                            ' data-bs-toggle="modal" data-bs-target="#voirUtilisateur"' +
                            ' onclick="voirUtilisateur(' + row.id + ', \'' + nomEscaped + '\', \'' + row.type + '\')"' +
                            ' style="cursor:pointer;" title="Voir les utilisateurs de la tâche.">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">' +
                            '<g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">' +
                            '<polygon points="0 0 24 0 24 24 0 24"/>' +
                            '<path d="M18,14 C16.3431458,14 15,12.6568542 15,11 C15,9.34314575 16.3431458,8 18,8 C19.6568542,8 21,9.34314575 21,11 C21,12.6568542 19.6568542,14 18,14 Z M9,11 C6.790861,11 5,9.209139 5,7 C5,4.790861 6.790861,3 9,3 C11.209139,3 13,4.790861 13,7 C13,9.209139 11.209139,11 9,11 Z" fill="#000000" fill-rule="nonzero" opacity="0.3"/>' +
                            '<path d="M17.6011961,15.0006174 C21.0077043,15.0378534 23.7891749,16.7601418 23.9984937,20.4 C24.0069246,20.5466056 23.9984937,21 23.4559499,21 L19.6,21 C19.6,18.7490654 18.8562935,16.6718327 17.6011961,15.0006174 Z M0.00065168429,20.1992055 C0.388258525,15.4265159 4.26191235,13 8.98334134,13 C13.7712164,13 17.7048837,15.2931929 17.9979143,20.2 C18.0095879,20.3954741 17.9979143,21 17.2466999,21 C13.541124,21 8.03472472,21 0.727502227,21 C0.476712155,21 -0.0204617505,20.45918 0.00065168429,20.1992055 Z" fill="#000000" fill-rule="nonzero"/>' +
                            '</g></svg></span>' +

                            '<span class="svg-icon svg-icon-warning svg-icon-2x"' +
                            ' style="cursor:pointer;" onclick="get_detail_tache(' + row.id + ')"' +
                            ' title="Modifier la tâche.">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">' +
                            '<g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">' +
                            '<rect x="0" y="0" width="24" height="24"/>' +
                            '<path d="M8,17.9148182 L8,5.96685884 C8,5.56391781 8.16211443,5.17792052 8.44982609,4.89581508 L10.965708,2.42895648 C11.5426798,1.86322723 12.4640974,1.85620921 13.0496196,2.41308426 L15.5337377,4.77566479 C15.8314604,5.0588212 16,5.45170806 16,5.86258077 L16,17.9148182 C16,18.7432453 15.3284271,19.4148182 14.5,19.4148182 L9.5,19.4148182 C8.67157288,19.4148182 8,18.7432453 8,17.9148182 Z" fill="#000000" fill-rule="nonzero" transform="translate(12.000000, 10.707409) rotate(-135.000000) translate(-12.000000, -10.707409)"/>' +
                            '<rect fill="#000000" opacity="0.3" x="5" y="20" width="15" height="2" rx="1"/>' +
                            '</g></svg></span>' +

                            '<span class="svg-icon ' + activateBtnColor + ' svg-icon-2x"' +
                            ' style="cursor:pointer;"' +
                            ' onclick="changeEtatTache(' + row.id + ', ' + (row.nombre_utilisateurs || 0) + ')"' +
                            ' title="' + activateBtnText + ' la tâche.">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">' +
                            '<g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">' +
                            '<rect x="0" y="0" width="24" height="24"/>' +
                            '<circle fill="#000000" opacity="0.3" cx="12" cy="12" r="10"/>' +
                            '<path d="M12.0355339,10.6213203 L14.863961,7.79289322 C15.2544853,7.40236893 15.8876503,7.40236893 16.2781746,7.79289322 C16.6686989,8.18341751 16.6686989,8.81658249 16.2781746,9.20710678 L13.4497475,12.0355339 L16.2781746,14.863961 C16.6686989,15.2544853 16.6686989,15.8876503 16.2781746,16.2781746 C15.8876503,16.6686989 15.2544853,16.6686989 14.863961,16.2781746 L12.0355339,13.4497475 L9.20710678,16.2781746 C8.81658249,16.6686989 8.18341751,16.6686989 7.79289322,16.2781746 C7.40236893,15.8876503 7.40236893,15.2544853 7.79289322,14.863961 L10.6213203,12.0355339 L7.79289322,9.20710678 C7.40236893,8.81658249 7.40236893,8.18341751 7.79289322,7.79289322 C8.18341751,7.40236893 8.81658249,7.40236893 9.20710678,7.79289322 L12.0355339,10.6213203 Z" fill="#000000"/>' +
                            '</g></svg></span>' +

                            '<a href="' + row.url + '?id=' + urlencoded + '" target="_blank" title="Aller à la tâche.">' +
                            '<span class="svg-icon svg-icon-info svg-icon-2x" style="cursor:pointer;">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">' +
                            '<g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">' +
                            '<rect x="0" y="0" width="24" height="24"/>' +
                            '<path d="M14.0069431,7.00607258 C13.4546584,7.00607258 13.0069431,6.55855153 13.0069431,6.00650634 C13.0069431,5.45446114 13.4546584,5.00694009 14.0069431,5.00694009 L15.0069431,5.00694009 C17.2160821,5.00694009 19.0069431,6.7970243 19.0069431,9.00520507 L19.0069431,15.001735 C19.0069431,17.2099158 17.2160821,19 15.0069431,19 L3.00694311,19 C0.797804106,19 -0.993056895,17.2099158 -0.993056895,15.001735 L-0.993056895,8.99826498 C-0.993056895,6.7900842 0.797804106,5 3.00694311,5 L4.00694793,5 C4.55923268,5 5.00694793,5.44752105 5.00694793,5.99956624 C5.00694793,6.55161144 4.55923268,6.99913249 4.00694793,6.99913249 L3.00694311,6.99913249 C1.90237361,6.99913249 1.00694311,7.89417459 1.00694311,8.99826498 L1.00694311,15.001735 C1.00694311,16.1058254 1.90237361,17.0008675 3.00694311,17.0008675 L15.0069431,17.0008675 C16.1115126,17.0008675 17.0069431,16.1058254 17.0069431,15.001735 L17.0069431,9.00520507 C17.0069431,7.90111468 16.1115126,7.00607258 15.0069431,7.00607258 L14.0069431,7.00607258 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" transform="translate(9.006943, 12.000000) scale(-1, 1) rotate(-90.000000) translate(-9.006943, -12.000000)"/>' +
                            '<rect fill="#000000" opacity="0.3" transform="translate(14.000000, 12.000000) rotate(-270.000000) translate(-14.000000, -12.000000)" x="13" y="6" width="2" height="12" rx="1"/>' +
                            '<path d="M21.7928932,9.79289322 C22.1834175,9.40236893 22.8165825,9.40236893 23.2071068,9.79289322 C23.5976311,10.1834175 23.5976311,10.8165825 23.2071068,11.2071068 L20.2071068,14.2071068 C19.8165825,14.5976311 19.1834175,14.5976311 18.7928932,14.2071068 L15.7928932,11.2071068 C15.4023689,10.8165825 15.4023689,10.1834175 15.7928932,9.79289322 C16.1834175,9.40236893 16.8165825,9.40236893 17.2071068,9.79289322 L19.5,12.0857864 L21.7928932,9.79289322 Z" fill="#000000" fill-rule="nonzero" transform="translate(19.500000, 12.000000) rotate(-90.000000) translate(-19.500000, -12.000000)"/>' +
                            '</g></svg></span></a>' +

                            '</div>';
                    }
                }
            ],
            ordering: false,
            createdRow: function (row, data) {
                $(row).find('td:eq(2)').attr('data-filter', data.id);
            }
        });

        dt.one('draw', function () {
            _loaderSync.dtReady = true;
            _checkCloseLoader();
        });

        dt.on('draw', function () {
            KTMenu.createInstances();
        });
    }

    function handleSearchDatatable() {
        var filterSearch = document.querySelector('[data-kt-docs-table-filter="search"]');
        if (!filterSearch) return;
        filterSearch.addEventListener('keyup', function (e) {
            dt.search(e.target.value).draw();
        });
    }

    return {
        init: function () {
            initDatatable();
            handleSearchDatatable();
        }
    };

}();

KTUtil.onDOMContentLoaded(function () {
    KTDatatablesServerSide.init();
});

// ==================================================
// DOCUMENT READY
// ==================================================
$(document).ready(function () {
    _resetLoaderSync();
    showLoader("Chargement des tâches...");

    // Stats indépendantes du loader principal
    loadStats();

    var ajaxTotal = 6;
    var ajaxDone  = 0;

    function ajaxLoaderDone() {
        ajaxDone++;
        if (ajaxDone >= ajaxTotal) {
            _loaderSync.statsReady = true;
            _checkCloseLoader();
        }
    }

    // Option 14 — Types de tâche
    $.ajax({
        type: 'POST', url: '/personnel/admin-controller', data: { option: 14 },
        success: function (data) {
            var html = (typeof data === 'string' && data.substr(0, 7) === '<option')
                ? data : '<option value="">Sélectionner un type</option>';
            $('#idTypeTache').html(html);
            ajaxLoaderDone();
        },
        error: function () {
            ajaxLoaderDone();
            Swal.fire('Erreur', "Impossible de charger les types de tâche.", 'error');
        }
    });

    // Option 15 — Fonctions
    $.ajax({
        type: 'POST', url: '/personnel/admin-controller', data: { option: 15 },
        success: function (data) {
            var html = (typeof data === 'string' && data.substr(0, 7) === '<option')
                ? data : '<option value="">Sélectionner une fonction</option>';
            $('#id_fonction').html(html);
            ajaxLoaderDone();
        },
        error: function () {
            ajaxLoaderDone();
            Swal.fire('Erreur', "Impossible de charger les fonctions.", 'error');
        }
    });

    // Option 16 — Sous menus
    $.ajax({
        type: 'POST', url: '/personnel/admin-controller', data: { option: 16 },
        success: function (data) {
            var html = (typeof data === 'string' && data.substr(0, 7) === '<option')
                ? data : '<option value="">Sélectionner un sous menu</option>';
            $('#idSousMenu').html(html);
            $('#sous_menu_tache').html(html);
            ajaxLoaderDone();
        },
        error: function () {
            ajaxLoaderDone();
            Swal.fire('Erreur', "Impossible de charger les sous menus.", 'error');
        }
    });

    // Option 17 — Icônes
    $.ajax({
        type: 'POST', url: '/personnel/admin-controller', data: { option: 17 },
        success: function (data) {
            var html = (typeof data === 'string' && data.trim() !== '')
                ? data : '<option value="">Aucune icône disponible</option>';
            $('#id_icon_tache').html(html);

            $('#id_icon_tache').select2({
                escapeMarkup: function (m) { return m; },
                templateResult: function (option) {
                    if (!option.id) return option.text;
                    var icon = $(option.element).data('icon');
                    if (!icon) return option.text;
                    return $('<span style="display:flex;align-items:center;gap:8px;">' + icon + '</span>');
                },
                templateSelection: function (option) {
                    if (!option.id) return option.text;
                    var icon = $(option.element).data('icon');
                    if (!icon) return option.text;
                    return $('<span style="display:flex;align-items:center;gap:8px;">' + icon + '</span>');
                }
            });

            ajaxLoaderDone();
        },
        error: function () {
            ajaxLoaderDone();
            Swal.fire('Erreur', "Impossible de charger les icônes.", 'error');
        }
    });

    // Option 18 — Bases de données
    $.ajax({
        type: 'POST', url: '/personnel/admin-controller', data: { option: 18 },
        success: function (data) {
            var html = (typeof data === 'string' && data.substr(0, 7) === '<option')
                ? data : '<option value="">Sélectionner une base de données</option>';
            $('#idBD').html(html);
            ajaxLoaderDone();
        },
        error: function () {
            ajaxLoaderDone();
            Swal.fire('Erreur', "Impossible de charger les bases de données.", 'error');
        }
    });

    // Option 23 — Applications
    $.ajax({
        type: 'POST', url: '/personnel/admin-controller', data: { option: 23 },
        success: function (data) {
            var html = (typeof data === 'string' && data.substr(0, 7) === '<option')
                ? data : '<option value="">Sélectionner l\'application</option>';
            $('#idAppli').html(html);
            ajaxLoaderDone();
        },
        error: function () {
            ajaxLoaderDone();
            Swal.fire('Erreur', "Impossible de charger les applications.", 'error');
        }
    });
});

// ==================================================
// TYPE TACHE ADD
// ==================================================
function type_tache(value) {
    var box_niv      = document.getElementById('box_niv');
    var box_ua       = document.getElementById('box_ua');
    var box_fonction = document.getElementById('box_fonction');

    box_niv.classList.add('d-none');
    box_ua.classList.add('d-none');
    box_fonction.classList.add('d-none');

    $('#nivUA').val('').trigger('change');
    $('#id_fonction').val('').trigger('change');
    $('#idUA').html('<option value="">Sélectionner une unité administrative</option>');

    if (value == 1) {

        box_niv.classList.remove('d-none');
        box_ua.classList.remove('d-none');

    } else if (value == 2) {

        box_fonction.classList.remove('d-none');

    }

    if (typeof validator1 !== 'undefined') {
        validator1.revalidateField('id_fonction');
        validator1.revalidateField('nivUA');
        validator1.revalidateField('idUA');
    }
}

// ==================================================
// UNITE ADMINISTRATIVE ADD
// ==================================================
function actionUniteAdministrative(nivUA) {
    $.ajax({
        type: 'POST',
        url:  '/personnel/admin-controller',
        data: { option: 20, nivUA: nivUA },
        success: function (data) {
            var html = (typeof data === 'string' && data.substr(0, 7) === '<option')
                ? data : '<option value="">Sélectionner une unité administrative</option>';
            $('#idUA').html(html);
            if (typeof validator1 !== 'undefined') validator1.revalidateField('idUA');
        },
        error: function () {
            Swal.fire('Erreur', "Impossible de charger les unités administratives.", 'error');
        }
    });
}

// ==================================================
// APERCU ICONE ADD
// ==================================================
$('#id_icon_tache').on('select2:select select2:unselect', function () {
    var selectedOption = this.options[this.selectedIndex];
    var iconPreview    = document.getElementById("iconPreview");

    if (selectedOption && selectedOption.value) {
        var icon = $(selectedOption).data('icon');
        iconPreview.innerHTML = icon ? '<span class="icon-preview">' + icon + '</span>' : '';
    } else {
        iconPreview.innerHTML = '';
    }

    if (typeof validator1 !== 'undefined') validator1.revalidateField('idIcon');
});

// ==================================================
// SOUS MENU ↔ ICONE ADD
// ==================================================
$('#idSousMenu').on('select2:select select2:unselect', function () {
    var sousMenu    = $(this).val();
    var iconWrapper = document.getElementById('iconSelectWrapper');
    var iconPreview = document.getElementById('iconPreview');

    if (sousMenu && sousMenu !== '') {
        $('#id_icon_tache').val('').trigger('change');
        iconWrapper.style.opacity       = '0.5';
        iconWrapper.style.pointerEvents = 'none';
        iconPreview.innerHTML = '';
    } else {
        iconWrapper.style.opacity       = '1';
        iconWrapper.style.pointerEvents = 'auto';
    }

    if (typeof validator1 !== 'undefined') validator1.revalidateField('idIcon');
});



function actionOrder(e)
{
alert(e);


    $.ajax({
        type: 'POST',
        url:  '/personnel/admin-controller',
        data: { option: 20, idAppli: e },
        success: function (data) {
            var html = (typeof data === 'string' && data.substr(0, 7) === '<option')
                ? data : '<option value="">Sélectionner l\'ordre d\'affichage </option>';
            $('#order').html(html);
            if (typeof validator1 !== 'undefined') validator1.revalidateField('order');
        },
        error: function () {
            Swal.fire('Erreur', "Impossible de charger les unités administratives.", 'error');
        }
    });
}

// ==================================================
// VALIDATION FORMULAIRE ADD
// ==================================================
var form1      = document.getElementById('formAddTache');
var validator1 = FormValidation.formValidation(form1, {
    fields: {
        nom: {
            validators: {
                notEmpty: { message: 'Le nom est obligatoire. Veuillez le renseigner.' }
            }
        },
        idTypeTache: {
            validators: {
                notEmpty: { message: 'Le type de tâche est obligatoire.' }
            }
        },
        idIcon: {
            validators: {
                callback: {
                    message: "L'icône est obligatoire si aucun sous-menu n'est sélectionné.",
                    callback: function () {
                        var sousMenu = document.getElementById('idSousMenu').value;
                        var icon     = document.getElementById('id_icon_tache').value;
                        if (sousMenu && sousMenu !== '') return true;
                        return icon && icon !== '';
                    }
                }
            }
        },
        url: {
            validators: {
                notEmpty: { message: "L'URL est obligatoire." }
            }
        },
        id_fonction: {
            validators: {
                callback: {
                    message: 'La fonction est obligatoire.',
                    callback: function () {
                        var type     = document.getElementById('idTypeTache').value;
                        var fonction = document.getElementById('id_fonction').value;
                        if (type == 2) return fonction && fonction !== '';
                        return true;
                    }
                }
            }
        },
        nivUA: {
            validators: {
                callback: {
                    message: 'Le niveau est obligatoire.',
                    callback: function () {
                        var type   = document.getElementById('idTypeTache').value;
                        var niveau = document.getElementById('nivUA').value;
                        if (type == 1) return niveau && niveau !== '';
                        return true;
                    }
                }
            }
        },
        idUA: {
            validators: {
                callback: {
                    message: "L'unité administrative est obligatoire.",
                    callback: function () {
                        var type = document.getElementById('idTypeTache').value;
                        var ua   = document.getElementById('idUA').value;
                        if (type == 1) return ua && ua !== '';
                        return true;
                    }
                }
            }
        },
        idBD: {
            validators: {
                notEmpty: { message: 'La base de données est obligatoire.' }
            }
        },
        idAppli: {
            validators: {
                notEmpty: { message: "L'application est obligatoire." }
            }
        }
    },
    plugins: {
        trigger:   new FormValidation.plugins.Trigger(),
        bootstrap: new FormValidation.plugins.Bootstrap5({
            rowSelector:     '.mb-3',
            eleInvalidClass: '',
            eleValidClass:   ''
        })
    }
});

// ==================================================
// SOUMISSION ADD
// ==================================================
var submitButton1 = document.getElementById('formAddTache_submit');

submitButton1.addEventListener('click', function (e) {

    console.log("1");
    e.preventDefault();
    if (!validator1) return;
    console.log(2);

    validator1.validate().then(function (status) {


        console.log(e);


        if (status !== 'Valid') return;

        submitButton1.disabled  = true;
        var originalText        = submitButton1.innerHTML;
        submitButton1.innerHTML = '<span class="indicator-progress d-inline">Veuillez patienter... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>';
        console.log(2);

        $.ajax({
            type: 'POST',
            url:  '/personnel/admin-controller',
            data: $('#formAddTache').serialize(),
            success: function (resp) {
                submitButton1.disabled  = false;
                submitButton1.innerHTML = originalText;

                var trimmed = (resp || '').trim();

                if (trimmed === 'sessionExpired') {
                    window.location.href = '/personnel/signin';

                } else if (trimmed === 'succès' || trimmed === 'succes') {
                    $('#kt_modal_add_tache').modal('hide');
                    Swal.fire({
                        icon:               'success',
                        title:              'Succès !',
                        text:               'La tâche a été enregistrée avec succès.',
                        confirmButtonColor: '#113B26',
                        timer:              2000,
                        timerProgressBar:   true,
                    }).then(function () {
                        videAddTache();
                        reloadAll();
                    });

                } else if (trimmed === 'existeTache') {
                    showInputError('urlInput', 'Une tâche avec cette URL existe déjà.');
                    document.getElementById('urlInput').addEventListener('input', function () {
                        clearInputError('urlInput');
                    }, { once: true });

                } else if (trimmed === 'tacheExisteUnite') {
                    showSwal('warning', 'Doublon', 'Une tâche avec ce nom, ce type et cette unité administrative existe déjà.');

                } else if (trimmed === 'erreur') {
                    $('#kt_modal_add_tache').modal('hide');
                    showSwal('error', 'Erreur', "Une erreur est survenue lors de l'enregistrement.");

                } else {
                    console.warn('Réponse inattendue :', trimmed);
                    $('#kt_modal_add_tache').modal('hide');
                    showSwal('error', 'Erreur inattendue', 'Réponse serveur non reconnue : ' + trimmed);
                }
            },
            error: function () {
                submitButton1.disabled  = false;
                submitButton1.innerHTML = originalText;
                $('#kt_modal_add_tache').modal('hide');
                showSwal('error', 'Erreur', "Une erreur est survenue lors de l'enregistrement.");
            }
        });
    });
});

// ==================================================
// VIDER / FERMER FORMULAIRE ADD
// ==================================================
function videAddTache() {
    type_tache('');

    $('#idTypeTache, #nivUA, #id_fonction, #idSousMenu, #id_icon_tache, #idBD, #idAppli')
        .val('').trigger('change');

    document.getElementById('iconPreview').innerHTML = '';

    var iconWrapper = document.getElementById('iconSelectWrapper');
    if (iconWrapper) {
        iconWrapper.style.opacity       = '1';
        iconWrapper.style.pointerEvents = 'auto';
    }

    form1.reset();
}

function closeAddTache() {
    videAddTache();
    $('#kt_modal_add_tache').modal('hide');
}

function ajouterTaches() {
    videAddTache();
    $('#kt_modal_add_tache').modal('show');
}

// ==================================================
// COPIER OPTIONS VERS FORMULAIRE EDIT
// ==================================================
function copyOptionsToEdit() {
    var selects = [
        { from: '#idTypeTache',   to: '#edit_idTypeTache'   },
        { from: '#id_fonction',   to: '#edit_id_fonction'   },
        { from: '#idSousMenu',    to: '#edit_idSousMenu'    },
        { from: '#id_icon_tache', to: '#edit_id_icon_tache' },
        { from: '#idBD',          to: '#edit_idBD'          },
        { from: '#idAppli',       to: '#edit_idAppli'       }
    ];

    selects.forEach(function (s) {
        $(s.to).html($(s.from).html());
    });

    $('#edit_id_icon_tache').select2({
        escapeMarkup: function (m) { return m; },
        templateResult: function (option) {
            if (!option.id) return option.text;
            var icon = $(option.element).data('icon');
            if (!icon) return option.text;
            return $('<span style="display:flex;align-items:center;gap:8px;">' + icon + '</span>');
        },
        templateSelection: function (option) {
            if (!option.id) return option.text;
            var icon = $(option.element).data('icon');
            if (!icon) return option.text;
            return $('<span style="display:flex;align-items:center;gap:8px;">' + icon + '</span>');
        }
    });
}

// ==================================================
// TYPE TACHE EDIT
// ==================================================
function edit_type_tache(value) {
    var box_niv      = document.getElementById('edit_box_niv');
    var box_ua       = document.getElementById('edit_box_ua');
    var box_fonction = document.getElementById('edit_box_fonction');

    box_niv.classList.add('d-none');
    box_ua.classList.add('d-none');
    box_fonction.classList.add('d-none');

    $('#edit_nivUA').val('').trigger('change');
    $('#edit_id_fonction').val('').trigger('change');
    $('#edit_idUA').html('<option value="">Sélectionner une unité administrative</option>');

    if (value == 1) {
        box_niv.classList.remove('d-none');
        box_ua.classList.remove('d-none');
    } else if (value == 2) {


        box_fonction.classList.remove('d-none');

    }

    if (typeof validator2 !== 'undefined') {
        validator2.revalidateField('id_fonction');
        validator2.revalidateField('nivUA');
        validator2.revalidateField('idUA');
    }
}

// ==================================================
// UNITE ADMINISTRATIVE EDIT
// ==================================================
function editActionUniteAdministrative(nivUA) {
    $.ajax({
        type: 'POST',
        url:  '/personnel/admin-controller',
        data: { option: 20, nivUA: nivUA },
        success: function (data) {
            var html = (typeof data === 'string' && data.substr(0, 7) === '<option')
                ? data : '<option value="">Sélectionner une unité administrative</option>';
            $('#edit_idUA').html(html);
            if (typeof validator2 !== 'undefined') validator2.revalidateField('idUA');
        },
        error: function () {
            Swal.fire('Erreur', "Impossible de charger les unités administratives.", 'error');
        }
    });
}

// ==================================================
// APERCU ICONE EDIT
// ==================================================
$('#edit_id_icon_tache').on('select2:select select2:unselect', function () {
    var selectedOption = this.options[this.selectedIndex];
    var iconPreview    = document.getElementById("edit_iconPreview");

    if (selectedOption && selectedOption.value) {
        var icon = $(selectedOption).data('icon');
        iconPreview.innerHTML = icon ? '<span class="icon-preview">' + icon + '</span>' : '';
    } else {
        iconPreview.innerHTML = '';
    }

    if (typeof validator2 !== 'undefined') validator2.revalidateField('idIcon');
});

// ==================================================
// SOUS MENU ↔ ICONE EDIT
// ==================================================
$('#edit_idSousMenu').on('select2:select select2:unselect', function () {
    var sousMenu    = $(this).val();
    var iconWrapper = document.getElementById('edit_iconSelectWrapper');
    var iconPreview = document.getElementById('edit_iconPreview');

    if (sousMenu && sousMenu !== '') {
        $('#edit_id_icon_tache').val('').trigger('change');
        iconWrapper.style.opacity       = '0.5';
        iconWrapper.style.pointerEvents = 'none';
        iconPreview.innerHTML = '';
    } else {
        iconWrapper.style.opacity       = '1';
        iconWrapper.style.pointerEvents = 'auto';
    }

    if (typeof validator2 !== 'undefined') validator2.revalidateField('idIcon');
});

// ==================================================
// GET DETAIL TACHE → OUVRIR MODAL EDIT
// ==================================================
function get_detail_tache(id) {

    copyOptionsToEdit();

    fetch('/personnel/admin-controller', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    new URLSearchParams({ option: 21, id: id })
    })
        .then(function (response) {
            if (!response.ok) throw new Error('Erreur réseau');
            return response.json();
        })
        .then(function (data) {
            if (!data || !data[0]) {
                Swal.fire('Erreur', 'Impossible de récupérer les détails de la tâche.', 'error');
                return;
            }

            var tache = data[0];

            // ── Champs simples ───────────────────────────────────
            document.getElementById('edit_id_tache').value        = id;
            document.getElementById('edit_nom').value             = tache.nom             || '';
            document.getElementById('edit_urlInput').value        = tache.url             || '';
            document.getElementById('edit_autre_ressource').value = tache.autre_ressource || '';
            document.getElementById('edit_commentaire').value     = tache.commentaire     || '';

            // ── Blocs visibilité AVANT remplissage selects ───────
            var box_niv      = document.getElementById('edit_box_niv');
            var box_ua       = document.getElementById('edit_box_ua');
            var box_fonction = document.getElementById('edit_box_fonction');

            box_niv.classList.add('d-none');
            box_ua.classList.add('d-none');
            box_fonction.classList.add('d-none');

            if (tache.idTypeTache == 1) {
                box_fonction.classList.remove('d-none');
            } else if (tache.idTypeTache == 2) {
                box_niv.classList.remove('d-none');
                box_ua.classList.remove('d-none');
            }

            // ── Select2 ──────────────────────────────────────────
            $('#edit_idTypeTache').val(tache.idTypeTache || '').trigger('change');
            $('#edit_idSousMenu').val(tache.idSousMenu   || '').trigger('change');
            $('#edit_id_fonction').val(tache.idFonction  || '').trigger('change');
            $('#edit_idBD').val(tache.idDB               || '').trigger('change');
            $('#edit_idAppli').val(tache.idAppli         || '').trigger('change');

            // ── Icône + aperçu ───────────────────────────────────
            $('#edit_id_icon_tache').val(tache.idIcon || '').trigger('change');
            var iconPreview = document.getElementById('edit_iconPreview');
            if (tache.idIcon) {
                var selOpt = document.getElementById('edit_id_icon_tache');
                var opt    = selOpt.options[selOpt.selectedIndex];
                var icon   = opt ? $(opt).data('icon') : null;
                iconPreview.innerHTML = icon
                    ? '<span class="icon-preview">' + icon + '</span>'
                    : (tache.icon || '');
            } else {
                iconPreview.innerHTML = tache.icon || '';
            }

            // ── Bloquer/débloquer icône selon sous menu ───────────
            var iconWrapper = document.getElementById('edit_iconSelectWrapper');
            if (tache.idSousMenu) {
                iconWrapper.style.opacity       = '0.5';
                iconWrapper.style.pointerEvents = 'none';
            } else {
                iconWrapper.style.opacity       = '1';
                iconWrapper.style.pointerEvents = 'auto';
            }

            // ── Niveau UA ────────────────────────────────────────
            if (tache.idTypeTache == 2) {
                var niveau = null;
                if (tache.idUniteAdministrativeNiv1)      niveau = 1;
                else if (tache.idUniteAdministrativeNiv2) niveau = 2;
                else if (tache.idUniteAdministrativeNiv3) niveau = 3;

                if (niveau) {
                    $('#edit_nivUA').val(niveau).trigger('change');

                    var idToSelect = tache.idUniteAdministrativeNiv1
                        || tache.idUniteAdministrativeNiv2
                        || tache.idUniteAdministrativeNiv3;

                    $.ajax({
                        type: 'POST',
                        url:  '/personnel/admin-controller',
                        data: { option: 20, nivUA: niveau },
                        success: function (resp) {
                            var html = (typeof resp === 'string' && resp.substr(0, 7) === '<option')
                                ? resp : '<option value="">Sélectionner une unité administrative</option>';
                            $('#edit_idUA').html(html);
                            setTimeout(function () {
                                $('#edit_idUA').val(idToSelect).trigger('change');
                            }, 100);
                        },
                        error: function () {
                            Swal.fire('Erreur', "Impossible de charger les unités administratives.", 'error');
                        }
                    });
                }
            }

            // ── Ouvrir le modal ──────────────────────────────────
            $('#kt_modal_edit_tache').modal('show');
        })
        .catch(function () {
            Swal.fire('Erreur', 'Une erreur est survenue lors de la récupération des détails.', 'error');
        });
}

// ==================================================
// VIDER FORMULAIRE EDIT
// ==================================================
function videEditTache() {
    var box_niv      = document.getElementById('edit_box_niv');
    var box_ua       = document.getElementById('edit_box_ua');
    var box_fonction = document.getElementById('edit_box_fonction');

    if (box_niv)      box_niv.classList.add('d-none');
    if (box_ua)       box_ua.classList.add('d-none');
    if (box_fonction) box_fonction.classList.add('d-none');

    $('#edit_idTypeTache, #edit_nivUA, #edit_id_fonction, #edit_idSousMenu, #edit_id_icon_tache, #edit_idBD, #edit_idAppli')
        .val('').trigger('change');

    $('#edit_idUA').html('<option value="">Sélectionner une unité administrative</option>');

    document.getElementById('edit_iconPreview').innerHTML  = '';
    document.getElementById('edit_id_tache').value         = '';
    document.getElementById('edit_nom').value              = '';
    document.getElementById('edit_urlInput').value         = '';
    document.getElementById('edit_autre_ressource').value  = '';
    document.getElementById('edit_commentaire').value      = '';

    var iconWrapper = document.getElementById('edit_iconSelectWrapper');
    if (iconWrapper) {
        iconWrapper.style.opacity       = '1';
        iconWrapper.style.pointerEvents = 'auto';
    }
}

function closeEditTache() {
    videEditTache();
    $('#kt_modal_edit_tache').modal('hide');
}

// ==================================================
// VALIDATION FORMULAIRE EDIT
// ==================================================
var form2      = document.getElementById('formEditTache');
var validator2 = FormValidation.formValidation(form2, {
    fields: {
        nom: {
            validators: {
                notEmpty: { message: 'Le nom est obligatoire.' }
            }
        },
        idTypeTache: {
            validators: {
                notEmpty: { message: 'Le type de tâche est obligatoire.' }
            }
        },
        idIcon: {
            validators: {
                callback: {
                    message: "L'icône est obligatoire si aucun sous-menu n'est sélectionné.",
                    callback: function () {
                        var sousMenu = document.getElementById('edit_idSousMenu').value;
                        var icon     = document.getElementById('edit_id_icon_tache').value;
                        if (sousMenu && sousMenu !== '') return true;
                        return icon && icon !== '';
                    }
                }
            }
        },
        url: {
            validators: {
                notEmpty: { message: "L'URL est obligatoire." }
            }
        },
        id_fonction: {
            validators: {
                callback: {
                    message: 'La fonction est obligatoire.',
                    callback: function () {
                        var type     = document.getElementById('edit_idTypeTache').value;
                        var fonction = document.getElementById('edit_id_fonction').value;
                        if (type == 2) return fonction && fonction !== '';
                        return true;
                    }
                }
            }
        },
        nivUA: {
            validators: {
                callback: {
                    message: 'Le niveau est obligatoire.',
                    callback: function () {
                        var type   = document.getElementById('edit_idTypeTache').value;
                        var niveau = document.getElementById('edit_nivUA').value;
                        if (type == 1) return niveau && niveau !== '';
                        return true;
                    }
                }
            }
        },
        idUA: {
            validators: {
                callback: {
                    message: "L'unité administrative est obligatoire.",
                    callback: function () {
                        var type = document.getElementById('edit_idTypeTache').value;
                        var ua   = document.getElementById('edit_idUA').value;
                        if (type == 1) return ua && ua !== '';
                        return true;
                    }
                }
            }
        },
        idBD: {
            validators: {
                notEmpty: { message: 'La base de données est obligatoire.' }
            }
        },
        idAppli: {
            validators: {
                notEmpty: { message: "L'application est obligatoire." }
            }
        }
    },
    plugins: {
        trigger:   new FormValidation.plugins.Trigger(),
        bootstrap: new FormValidation.plugins.Bootstrap5({
            rowSelector:     '.mb-3',
            eleInvalidClass: '',
            eleValidClass:   ''
        })
    }
});

// ==================================================
// SOUMISSION UPDATE
// ==================================================
var submitButton2 = document.getElementById('formEditTache_submit');

submitButton2.addEventListener('click', function (e) {
    e.preventDefault();
    if (!validator2) return;

    validator2.validate().then(function (status) {
        if (status !== 'Valid') return;

        submitButton2.disabled  = true;
        var originalText        = submitButton2.innerHTML;
        submitButton2.innerHTML = '<span class="indicator-progress d-inline">Veuillez patienter... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>';

        $.ajax({
            type: 'POST',
            url:  '/personnel/admin-controller',
            data: $('#formEditTache').serialize(),
            success: function (resp) {
                submitButton2.disabled  = false;
                submitButton2.innerHTML = originalText;

                var trimmed = (resp || '').trim();

                if (trimmed === 'sessionExpired') {
                    window.location.href = '/personnel/signin';

                } else if (trimmed === 'succes') {
                    $('#kt_modal_edit_tache').modal('hide');
                    Swal.fire({
                        icon:               'success',
                        title:              'Succès !',
                        text:               'La tâche a été modifiée avec succès.',
                        confirmButtonColor: '#113B26',
                        timer:              2000,
                        timerProgressBar:   true,
                    }).then(function () {
                        videEditTache();
                        reloadAll();
                    });

                } else if (trimmed === 'existeTache') {
                    showInputError('edit_urlInput', 'Une tâche avec cette URL existe déjà.');
                    document.getElementById('edit_urlInput').addEventListener('input', function () {
                        clearInputError('edit_urlInput');
                    }, { once: true });

                } else if (trimmed === 'tacheExisteUnite') {
                    showSwal('warning', 'Doublon', 'Une tâche avec ce nom, ce type et cette unité administrative existe déjà.');

                } else if (trimmed === 'erreur') {
                    $('#kt_modal_edit_tache').modal('hide');
                    showSwal('error', 'Erreur', "Une erreur est survenue lors de la modification.");

                } else {
                    console.warn('Réponse inattendue :', trimmed);
                    $('#kt_modal_edit_tache').modal('hide');
                    showSwal('error', 'Erreur inattendue', 'Réponse serveur non reconnue : ' + trimmed);
                }
            },
            error: function () {
                submitButton2.disabled  = false;
                submitButton2.innerHTML = originalText;
                $('#kt_modal_edit_tache').modal('hide');
                showSwal('error', 'Erreur', "Une erreur est survenue lors de la modification.");
            }
        });
    });
});

// ==================================================
// CHANGER ETAT TACHE
// ==================================================
async function changeEtatTache(id, nombreUtilisateurs) {

    if (nombreUtilisateurs > 0) {
        showSwal('warning', 'Impossible de désactiver',
            "La tâche ne doit être affectée à aucun agent avant d'être désactivée.");
        return;
    }

    var confirmation = await Swal.fire({
        title:              'Confirmation',
        text:               "Voulez-vous vraiment changer l'état de cette tâche ?",
        icon:               'question',
        showCancelButton:   true,
        confirmButtonColor: '#113B26',
        cancelButtonColor:  '#b81c2c',
        confirmButtonText:  'Oui',
        cancelButtonText:   'Annuler',
        reverseButtons:     true
    });

    if (!confirmation.isConfirmed) return;

    showLoader("Mise à jour de l'état en cours…");

    try {
        var response = await fetch('/personnel/admin-controller', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    new URLSearchParams({ option: 24, id: id }).toString()
        });

        var text    = await response.text();
        var trimmed = text.trim();

        hideLoader();

        if (trimmed === 'sessionExpired') {
            window.location.href = '/personnel/signin';

        } else if (trimmed === 'succes') {
            showSwal('success', 'Succès !', "L'état de la tâche a été modifié avec succès.", function () {
                reloadAll();
            });

        } else if (trimmed === 'erreur') {
            showSwal('error', 'Erreur', "Impossible de changer l'état de la tâche.");

        } else {
            console.warn('Réponse inattendue :', trimmed);
            showSwal('error', 'Erreur inattendue', 'Réponse serveur non reconnue : ' + trimmed);
        }

    } catch (error) {
        hideLoader();
        console.error('Erreur :', error);
        showSwal('error', 'Erreur', "Une erreur est survenue lors du changement d'état.");
    }
}

// ==================================================
// STATS
// ==================================================
function setStatEl(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value;
}

function setStatBar(pct) {
    var bar = document.getElementById('ps1-bar-fill');
    if (!bar) return;
    bar.style.transition = 'none';
    bar.style.transform  = 'scaleY(0)';
    bar.getBoundingClientRect();
    bar.style.transition = 'transform 0.6s ease';
    bar.style.transform  = 'scaleY(' + (pct / 100) + ')';
}

var STAT_IDS = ['ps1-total', 'ps1-actifs', 'ps1-inactifs', 'ps1-pct-actifs', 'ps1-pct-inactifs'];

function setStatsLoading() {
    STAT_IDS.forEach(function (id) { setStatEl(id, '…'); });
    setStatBar(0);
}

function setStatsFallback(symbol) {
    STAT_IDS.forEach(function (id) { setStatEl(id, symbol || '—'); });
    setStatBar(0);
}

function loadStats() {
    setStatsLoading();
    $.ajax({
        type: 'POST',
        url:  '/personnel/admin-controller',
        data: { option: 25 },
        success: function (data) {
            if (!data || !testJSON(data)) {
                setStatsFallback('—');
                return;
            }

            var json     = JSON.parse(data);
            var total    = json.total    || 0;
            var actifs   = json.actifs   || 0;
            var inactifs = json.inactifs || 0;

            var pctActifs   = total > 0 ? Math.round((actifs   / total) * 100) : 0;
            var pctInactifs = total > 0 ? Math.round((inactifs / total) * 100) : 0;

            setStatEl('ps1-total',        total);
            setStatEl('ps1-actifs',       actifs);
            setStatEl('ps1-inactifs',     inactifs);
            setStatEl('ps1-pct-actifs',   pctActifs   + '%');
            setStatEl('ps1-pct-inactifs', pctInactifs + '%');
            setStatBar(pctActifs);
        },
        error: function () {
            setStatsFallback('—');
        }
    });
}