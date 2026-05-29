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
        const json = JSON.parse(text);
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
        if ($.fn.DataTable.isDataTable('#kt_table_sous_menu')) {
            $('#kt_table_sous_menu').DataTable().destroy();
        }

        // statsReady = true car on ne recharge pas les icônes
        _loaderSync.statsReady = true;

        KTDatatablesServerSide.init();
    }, 100);
}

// ==================================================
// HELPERS VALIDATION
// ==================================================
function showInputError(inputId, message) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.add('is-invalid');

    const existing = input.parentNode.querySelector('.invalid-feedback');
    if (existing) existing.remove();

    const feedback = document.createElement('div');
    feedback.classList.add('invalid-feedback');
    feedback.textContent = message;
    input.parentNode.appendChild(feedback);
}

function clearInputError(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.remove('is-invalid');
    const existing = input.parentNode.querySelector('.invalid-feedback');
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
                    Swal.fire('Erreur', "Impossible de charger la liste des sous menus.", 'error');
                }
            },
            columns: [
                { data: "nom" },
                { data: "type" },
                {
                    data: null,
                    orderable: false,
                    render: function (data, type, row, meta) {
                        if (row.type === "Par défaut") {
                            return "Tout le monde";
                        } else if (row.type === "Incarnée") {
                            return "Agents du post";
                        } else if (row.type === "Structure") {
                            return `<span>
                            ${row.nombre_utilisateurs || 0}
                            </span>`;
                        }
                    }
                },
                { data: "code" },
                { data: "commentaire" },
                // { data: "url" },
                {
                    data: null,
                    orderable: false,
                    render: function (data, type, row, meta) {
                        let urlencoded = btoa(`${row.id_struture}`)
                        let activateBtnText = row.active == 1 ? 'Déactiver' : 'Activer'
                        let activateBtnColor = row.active == 1 ? 'svg-icon-danger' : 'svg-icon-warning'
                        return `
                            <div class="d-flex flex-wrap gap-2 justify-content-center align-items-center ">
							   <span class="svg-icon svg-icon-primary svg-icon-2x" data-bs-toggle="modal" data-bs-target="#voirUtilisateur" onclick="voirUtilisateur(${row.id}, '${row.nom.replace(/'/g, "\\'")}','${row.type}')" style="cursor: pointer;"><!--begin::Svg Icon | path:C:\wamp64HG\themes\metronic\theme\html\demo2\dist/../src/media/svg/icons\Communication\Group.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                    <title>Voir les utilisateurs de la tache.</title>
                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <polygon points="0 0 24 0 24 24 0 24"/>
                                        <path d="M18,14 C16.3431458,14 15,12.6568542 15,11 C15,9.34314575 16.3431458,8 18,8 C19.6568542,8 21,9.34314575 21,11 C21,12.6568542 19.6568542,14 18,14 Z M9,11 C6.790861,11 5,9.209139 5,7 C5,4.790861 6.790861,3 9,3 C11.209139,3 13,4.790861 13,7 C13,9.209139 11.209139,11 9,11 Z" fill="#000000" fill-rule="nonzero" opacity="0.3"/>
                                        <path d="M17.6011961,15.0006174 C21.0077043,15.0378534 23.7891749,16.7601418 23.9984937,20.4 C24.0069246,20.5466056 23.9984937,21 23.4559499,21 L19.6,21 C19.6,18.7490654 18.8562935,16.6718327 17.6011961,15.0006174 Z M0.00065168429,20.1992055 C0.388258525,15.4265159 4.26191235,13 8.98334134,13 C13.7712164,13 17.7048837,15.2931929 17.9979143,20.2 C18.0095879,20.3954741 17.9979143,21 17.2466999,21 C13.541124,21 8.03472472,21 0.727502227,21 C0.476712155,21 -0.0204617505,20.45918 0.00065168429,20.1992055 Z" fill="#000000" fill-rule="nonzero"/>
                                    </g>
                                </svg><!--end::Svg Icon--></span>
                                    <span class="svg-icon svg-icon-warning svg-icon-2x" style="cursor: pointer;" onclick="get_detail_tache(${row.id})"><!--begin::Svg Icon | path:/var/www/preview.keenthemes.com/metronic/releases/2021-05-14-112058/theme/html/demo1/dist/../src/media/svg/icons/Design/Edit.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                      <title>Modifier la tache. </title>
                                      <desc>Created with Sketch.</desc>
                                      <defs/>
                                      <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                          <rect x="0" y="0" width="24" height="24"/>
                                          <path d="M8,17.9148182 L8,5.96685884 C8,5.56391781 8.16211443,5.17792052 8.44982609,4.89581508 L10.965708,2.42895648 C11.5426798,1.86322723 12.4640974,1.85620921 13.0496196,2.41308426 L15.5337377,4.77566479 C15.8314604,5.0588212 16,5.45170806 16,5.86258077 L16,17.9148182 C16,18.7432453 15.3284271,19.4148182 14.5,19.4148182 L9.5,19.4148182 C8.67157288,19.4148182 8,18.7432453 8,17.9148182 Z" fill="#000000" fill-rule="nonzero" transform="translate(12.000000, 10.707409) rotate(-135.000000) translate(-12.000000, -10.707409) "/>
                                          <rect fill="#000000" opacity="0.3" x="5" y="20" width="15" height="2" rx="1"/>
                                      </g>
                                  </svg><!--end::Svg Icon--></span>
                                 
                                  <span class="svg-icon ${activateBtnColor} svg-icon-2x" style="cursor: pointer;" onclick="changeEtatTache(${row.id}, ${row.nombre_utilisateurs || 0})"><!--begin::Svg Icon | path:C:\wamp64\www\keenthemes\themes\metronic\theme\html\demo2\dist/../src/media/svg/icons\Code\Error-circle.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                  <title>${activateBtnText} la tache.</title>
                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <rect x="0" y="0" width="24" height="24"/>
                                        <circle fill="#000000" opacity="0.3" cx="12" cy="12" r="10"/>
                                        <path d="M12.0355339,10.6213203 L14.863961,7.79289322 C15.2544853,7.40236893 15.8876503,7.40236893 16.2781746,7.79289322 C16.6686989,8.18341751 16.6686989,8.81658249 16.2781746,9.20710678 L13.4497475,12.0355339 L16.2781746,14.863961 C16.6686989,15.2544853 16.6686989,15.8876503 16.2781746,16.2781746 C15.8876503,16.6686989 15.2544853,16.6686989 14.863961,16.2781746 L12.0355339,13.4497475 L9.20710678,16.2781746 C8.81658249,16.6686989 8.18341751,16.6686989 7.79289322,16.2781746 C7.40236893,15.8876503 7.40236893,15.2544853 7.79289322,14.863961 L10.6213203,12.0355339 L7.79289322,9.20710678 C7.40236893,8.81658249 7.40236893,8.18341751 7.79289322,7.79289322 C8.18341751,7.40236893 8.81658249,7.40236893 9.20710678,7.79289322 L12.0355339,10.6213203 Z" fill="#000000"/>
                                    </g>
                                </svg><!--end::Svg Icon--></span>
                                
                                <a class="" href='${row.url+ "?id=" + urlencoded}' target="_blank">
                                    <span class="svg-icon svg-icon-info svg-icon-2x" style="cursor: pointer;"><!--begin::Svg Icon | path:C:\wamp64\www\keenthemes\themes\metronic\theme\html\demo2\dist/../src/media/svg/icons\Navigation\Sign-out.svg--><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                      <title>Aller à la tache.</title>
                                      <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                          <rect x="0" y="0" width="24" height="24"/>
                                          <path d="M14.0069431,7.00607258 C13.4546584,7.00607258 13.0069431,6.55855153 13.0069431,6.00650634 C13.0069431,5.45446114 13.4546584,5.00694009 14.0069431,5.00694009 L15.0069431,5.00694009 C17.2160821,5.00694009 19.0069431,6.7970243 19.0069431,9.00520507 L19.0069431,15.001735 C19.0069431,17.2099158 17.2160821,19 15.0069431,19 L3.00694311,19 C0.797804106,19 -0.993056895,17.2099158 -0.993056895,15.001735 L-0.993056895,8.99826498 C-0.993056895,6.7900842 0.797804106,5 3.00694311,5 L4.00694793,5 C4.55923268,5 5.00694793,5.44752105 5.00694793,5.99956624 C5.00694793,6.55161144 4.55923268,6.99913249 4.00694793,6.99913249 L3.00694311,6.99913249 C1.90237361,6.99913249 1.00694311,7.89417459 1.00694311,8.99826498 L1.00694311,15.001735 C1.00694311,16.1058254 1.90237361,17.0008675 3.00694311,17.0008675 L15.0069431,17.0008675 C16.1115126,17.0008675 17.0069431,16.1058254 17.0069431,15.001735 L17.0069431,9.00520507 C17.0069431,7.90111468 16.1115126,7.00607258 15.0069431,7.00607258 L14.0069431,7.00607258 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" transform="translate(9.006943, 12.000000) scale(-1, 1) rotate(-90.000000) translate(-9.006943, -12.000000) "/>
                                          <rect fill="#000000" opacity="0.3" transform="translate(14.000000, 12.000000) rotate(-270.000000) translate(-14.000000, -12.000000) " x="13" y="6" width="2" height="12" rx="1"/>
                                          <path d="M21.7928932,9.79289322 C22.1834175,9.40236893 22.8165825,9.40236893 23.2071068,9.79289322 C23.5976311,10.1834175 23.5976311,10.8165825 23.2071068,11.2071068 L20.2071068,14.2071068 C19.8165825,14.5976311 19.1834175,14.5976311 18.7928932,14.2071068 L15.7928932,11.2071068 C15.4023689,10.8165825 15.4023689,10.1834175 15.7928932,9.79289322 C16.1834175,9.40236893 16.8165825,9.40236893 17.2071068,9.79289322 L19.5,12.0857864 L21.7928932,9.79289322 Z" fill="#000000" fill-rule="nonzero" transform="translate(19.500000, 12.000000) rotate(-90.000000) translate(-19.500000, -12.000000) "/>
                                      </g>
                                  </svg><!--end::Svg Icon--></span>
                                </a>

                                
                            </div>
                        `;
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
        const filterSearch = document.querySelector('[data-kt-docs-table-filter="search"]');
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
    showLoader("Chargement des sous menus...");

    $.ajax({
        type: 'POST',
        url:  '/personnel/admin-controller',
        data: { option: 14 },
        success: function (data) {
            if (typeof data === 'string' && data.substr(0, 7) === "<option") {
                $('#idTypeTache').html(data.substr(4));
                $('#type_tache').html(data.substr(4));

            } else {
                $('#idTypeTache').html('');
                $('#type_tache').html('');
            }
            _loaderSync.statsReady = true;
            _checkCloseLoader();
        },
        error: function () {
            _loaderSync.statsReady = true;
            _checkCloseLoader();
            Swal.fire('Erreur', "Impossible de charger les icônes.", 'error');
        }
    });


    $.ajax({
        type: 'POST',
        url:  '/personnel/admin-controller',
        data: { option: 15 },
        success: function (data) {
            alert(data);
            if (typeof data === 'string' && data.substr(0, 7) === "<option") {
                $('#id_fonction').html(data.substr(4));
            } else {
                $('#id_fonction').html('');
            }
            _loaderSync.statsReady = true;
            _checkCloseLoader();
        },
        error: function () {
            _loaderSync.statsReady = true;
            _checkCloseLoader();
            Swal.fire('Erreur', "Impossible de charger les icônes.", 'error');
        }
    });


    $.ajax({
        type: 'POST',
        url:  '/personnel/admin-controller',
        data: { option: 16 },
        success: function (data) {

            alert(data);
            if (typeof data === 'string' && data.substr(0, 7) === "<option") {
                $('#idSousMenu').html(data.substr(4));
                $('#sous_menu_tache').html(data.substr(4));
            } else {
                $('#idSousMenu').html('');
                $('#sous_menu_tache').html('');

            }
            _loaderSync.statsReady = true;
            _checkCloseLoader();
        },
        error: function () {
            _loaderSync.statsReady = true;
            _checkCloseLoader();
            Swal.fire('Erreur', "Impossible de charger les icônes.", 'error');
        }
    });

    $.ajax({
        type: 'POST',
        url:  '/personnel/admin-controller',
        data: { option: 17 },
        success: function (data) {
            if (typeof data === 'string' && data.trim() !== '') {

                // Injecter les options
                $('#id_icon_tache').html(data);

                // Initialiser Select2 avec rendu icône
                $('#id_icon_tache').select2({
                    escapeMarkup: function(m) { return m; },
                    templateResult: function(option) {
                        if (!option.id) return option.text;

                        var icon = $(option.element).data('icon');
                        if (!icon) return option.text;

                        return $(
                            '<span style="display:flex;align-items:center;gap:8px;">' +
                            icon +
                            '</span>'
                        );
                    },
                    templateSelection: function(option) {
                        if (!option.id) return option.text;

                        var icon = $(option.element).data('icon');
                        if (!icon) return option.text;

                        return $(
                            '<span style="display:flex;align-items:center;gap:8px;">' +
                            icon +
                            '</span>'
                        );
                    }
                });

            } else {
                $('#id_icon_tache').html('<option value="">Aucune icône disponible</option>');
            }

            _loaderSync.statsReady = true;
            _checkCloseLoader();
        },
        error: function () {
            _loaderSync.statsReady = true;
            _checkCloseLoader();
            Swal.fire('Erreur', "Impossible de charger les icônes.", 'error');
        }
    });

    $.ajax({
        type: 'POST',
        url:  '/personnel/admin-controller',
        data: { option: 18 },
        success: function (data) {

            alert(data);
            if (typeof data === 'string' && data.substr(0, 7) === "<option") {
                $('#idDB').html(data.substr(4));
            } else {
                $('#idDB').html('');
            }
            _loaderSync.statsReady = true;
            _checkCloseLoader();
        },
        error: function () {
            _loaderSync.statsReady = true;
            _checkCloseLoader();
            Swal.fire('Erreur', "Impossible de charger les icônes.", 'error');
        }
    });


    $('.select2-icon').select2({
        templateResult: formatIcon,
        templateSelection: formatIcon
    });

    // Validation HTML5 + custom feedback
    $('#tacheForm').on('submit', function(e) {
        let valid = true;

        if (!$('#id_icon_tache').val()) {
            $('#id_icon_tache').addClass('is-invalid');
            $('#validationid_icon_tacheFeedback').show();
            valid = false;
        } else {
            $('#id_icon_tache').removeClass('is-invalid');
            $('#validationid_icon_tacheFeedback').hide();
        }
        if (!valid) e.preventDefault();
    });

    $('#idSousMenu').on('change', function() {
        if ($(this).val()) {
            $(this).removeClass('is-invalid');
            $('#validationidSousMenuFeedback').hide();
        }
    });
    $('#id_icon_tache').on('change', function() {
        if ($(this).val()) {
            $(this).removeClass('is-invalid');
            $('#validationid_icon_tacheFeedback').hide();
        }
    });

    // Réinitialise Select2 lors du reset du formulaire
    $('#tacheForm').on('reset', function() {
        $('#id_icon_tache').val('').trigger('change');
    });
});



function formatIcon(option) {
    if (!option.id) return option.text;
    var svgIcon = $(option.element).data('icon');
    var $wrapper = $('<span></span>');
    $wrapper.append(option.text);
    return $wrapper;
}

function updateIconPreview(select) {
    const selectedOption = select.options[select.selectedIndex];
    const preview = document.getElementById("iconPreview");
    if (selectedOption.value) {
        preview.innerHTML = `
                                            <span class="menu-icon">
                                                <span class="svg-icon svg-icon-2">
                                                    ${selectedOption.getAttribute("data-svg")}
                                                </span>
                                                ${selectedOption.text}
                                            </span>
                                        `;
    } else {
        preview.innerHTML = "";
    }
}



// ✅ Correct avec Select2
$('#id_icon_tache').on('select2:select select2:unselect', function(e) {
    const selectedOption = this.options[this.selectedIndex];
    const iconPreview    = document.getElementById("iconPreview");

    if (selectedOption && selectedOption.value) {
        const icon = $(selectedOption).data('icon');

        if (icon) {
            iconPreview.innerHTML = '<span class="icon-preview">' + icon + '</span>';
        } else {
            iconPreview.innerHTML = '';
        }
    } else {
        iconPreview.innerHTML = '';
    }
});

function ajouterTaches() {
    $('#add_tache').modal('show');
}




function submitForm() {
    // Sélectionner tous les boutons de soumission des formulaires

    // Récupérer le formulaire
    const form = document.getElementById('tacheForm');
    // vérifier si les champs obligatoires contenant l'attribut required sont remplis
    const requiredFields = form.querySelectorAll('[required]');
    let allFieldsFilled = true;
    requiredFields.forEach(field => {
        if (!field.value) {
            allFieldsFilled = false;
            field.classList.add('is-invalid'); // Ajoute une classe d'erreur si le champ est vide
        } else {
            field.classList.remove('is-invalid'); // Supprime la classe d'erreur si le champ est rempli
        }
    });
    const typeTache = document.getElementById('type_tache').value;
    if (typeTache == 1) {
        const niveauUA = document.getElementById('nivUA').value;
        const id_UA = document.getElementById('id_UA').value;
        if (!niveauUA) {
            allFieldsFilled = false;
            document.getElementById('nivUA').classList.add('is-invalid');
        }
        if (!id_UA) {
            allFieldsFilled = false;
            document.getElementById('id_UA').classList.add('is-invalid');
        }
    }
    if (typeTache == 2) {
        const fonction = document.getElementById('id_fonction');
        if (fonction) {
            if (fonction.value.trim() === "") {
                allFieldsFilled = false;
                fonction.classList.add('is-invalid');
                return;
            } else {
                allFieldsFilled = true
                fonction.classList.remove('is-invalid');
            }
        }
    }
    const id_icon_tache = document.getElementById('id_icon_tache');
    const idSousMenu = document.getElementById('idSousMenu');
    // Si le sous-menu est vide, rendre id_icon_tache requis
    if (id_icon_tache) {
        if (!idSousMenu.value) {
            id_icon_tache.setAttribute('required', 'required');
        } else {
            id_icon_tache.removeAttribute('required');
        }
    }
    // Les deux champs doivent être remplis (non vides)
    if (!id_icon_tache.value && !idSousMenu.value) {
        allFieldsFilled = false;

        if (!id_icon_tache.value) {
            id_icon_tache.classList.add('is-invalid');
        } else {
            id_icon_tache.classList.remove('is-invalid');
        }
        return;
    } else {
        id_icon_tache.classList.remove('is-invalid');
    }

    if (!form) {
        console.error("Formulaire introuvable !");
        return;
    }

    // Créer un objet FormData pour récupérer les champs du formulaire
    const formData = new FormData(form);

    // Convertir FormData en objet simple
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });

    if (data.idTypeTache == 3) {
        data.niveau_UA = null; // Réinitialiser le niveau UA

        const niveauUA = document.getElementById('nivUA');
        const id_UA = document.getElementById('id_UA');
        if (!niveauUA) {
            allFieldsFilled = true;
        }
        if (!id_UA) {
            allFieldsFilled = true;
        }
    }
    // Validation de l'URL
    const urlInput = document.getElementById("urlInput");
    const urlRegex = /^(https?:\/\/)?([\p{L}\p{N}_-]+\.)+[\p{L}\p{N}_-]{2,}(\/[\p{L}\p{N}\-._~:/?#[\]@!$&'()*+,;=%]*)?$/u;

    if (urlInput) {
        const urlValue = urlInput.value.trim();
        if (!urlRegex.test(urlValue)) {
            allFieldsFilled = false;
            urlInput.classList.add('is-invalid');
            const feedback = document.getElementById('validationUrlFeedback');
            if (feedback) feedback.textContent = "Veuillez entrer une URL valide.";
        } else {
            urlInput.classList.remove('is-invalid');
            urlInput.classList.add('is-valid');
        }
    }
    console.log("Données envoyées :", data);
    console.log("Type de tâche :", data.idTypeTache);
    console.log("Tous les champs remplis :", allFieldsFilled);


    if (allFieldsFilled) {

        const submitButton = document.querySelector('.submitButton');
        submitButton.disabled = true;

        // Optionnel : Ajouter un indicateur de chargement
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> En cours...';

        const queryString = new URLSearchParams(data).toString();

        // Envoyer les données via Fetch API vers le backend (controller.php)
        // Envoyer les données via Fetch API avec la méthode POST vers le backend (controller.php)
        fetch('/personnel/admin-controller', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data).toString()
        }).then(response => {
            if (!response.ok) {
                throw new Error(`Erreur HTTP : ${response.status}`);
            }
            return response.json(); // Traiter la réponse comme JSON
        })
            .then(result => {
                console.log("Réponse du serveur :", result);
                submitButton.disabled = false;
                submitButton.innerHTML = originalText; // Réinitialiser le texte du bouton
                if (result.success) {
                    Swal.fire({
                        position: "center",
                        icon: "success",
                        title: result.message || "Tâche ajoutée avec succès !",
                        showConfirmButton: false,
                        timer: 1500
                    });
                    window.location.reload()

                    // Réinitialiser le formulaire
                    form.reset();

                    // Fermer le modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById("add_tache"));
                    if (modal) {
                        modal.hide();
                    }

                    // Rafraîchir la liste des tâches
                    KTDatatablesTache.init();
                    KTDatatablesTacheQualification.init();
                    getTacheQualification()
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: result.message || "Une erreur est survenue lors de l'ajout de la tâche.",
                    });
                }
            })
            .catch(error => {
                console.error("Erreur lors de la requête :", error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: "Une erreur est survenue lors de la soumission du formulaire.",
                });
            });
    }
}


//Choix type tache
async function type_tache(choix) {
    const niveauUAElement = document.getElementById('niveau_UA');

    const selected_UA = document.getElementById('selected_UA');
    selected_UA.innerHTML = ''; // Réinitialise le contenu de l'élément
    niveauUAElement.style.display = 'none'; // Affiche l'élément
    if ( choix == 1) {
        niveauUAElement.style.display = 'block'; // Masque l'élément
        niveauUAElement.innerHTML = `
                                <label class="form-label">Unite Administrative</label>

    <select name="niveau_UA" id="nivUA" class="form-select" required aria-label="select example"  onchange="get_UA(this.value)" >
                                    <option value="">Selectionner un niveau</option>
                                    <option value="1">Niveau 1</option>
                                    <option value="2">Niveau 2</option>
                                    <option value="3">Niveau 3</option>

                                </select>
<div class="invalid-feedback">Veuillez choisir le niveau de l'unité administrative.</div>
    `
    }
    if (choix == 2){
        document.getElementById('id_fonction').setAttribute('required', 'required');
        document.getElementById('selected_fonction').classList.remove('d-none')
    }else{
        document.getElementById('id_fonction').removeAttribute('required');
        document.getElementById('selected_fonction').classList.add('d-none');
    }

    console.log(choix);
}

//Affichage des elements de l'unité administrative correspondant
function get_UA(UA_value) {
    const selected_UA = document.getElementById('selected_UA');
    selected_UA.innerHTML = ''; // Réinitialise le contenu de l'élément

    const select = document.createElement('select');
    select.classList.add('form-control'); // Ajoute la classe au select
    // Ajouter l'attribut id à l'élément select
    select.setAttribute('id', 'id_UA'); // Ajoute l'ID à l'élément select

    // Rendre le champ obligatoire

    select.name = 'id_UA'
    select.setAttribute('required', 'required'); // Rendre le champ obligatoire
    select.innerHTML = `
  <option value="">Selectionner une unité administrative</option>`; // Ajoute une option par défaut
    let data = {
        option: 20,
        niveau: UA_value, // Peut-être 1, 2, ou 3
    };

    fetch('/personnel/admin-controller', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(data).toString()
    }).then((response) => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
        .then((data) => {
            console.log(data);

            // Traiter les données en fonction du niveau reçu
            data.forEach((element) => {
                if (element.sans == 0 ) {

                    const option = document.createElement('option');
                    option.classList.add('form-control'); // Ajoute la classe au select
                    option.setAttribute('required', ''); // Rendre le champ obligatoire



                    // Affiche des informations distinctes en fonction du niveau
                    if (UA_value == 1) {
                        option.value = element.id;
                        option.innerHTML = element.niveau1;
                    } else if (UA_value == 2) {
                        option.value = element.id;
                        option.innerHTML = element.niveau2;
                    } else if (UA_value == 3) {
                        option.value = element.id;
                        option.innerHTML = element.niveau3;
                    }
                    console.log(option.value)
                    select.appendChild(option);
                }
            });

            selected_UA.appendChild(select); // Ajoute le select au DOM une fois qu'il est complet
        })
        .catch((error) => {
            console.error('Error fetching data:', error);
        });
}


// function get_detail_tache(id) {
//     // Préparer les données pour la requête
//     const data = {
//         action: "get_one_tache",
//         id: id,
//     };

//     const queryString = new URLSearchParams(data).toString();

//     // Récupérer les éléments du formulaire et du modal
//     const form = document.getElementById("tacheForm");
//     if (!form) {
//         console.error("Formulaire introuvable !");
//         return;
//     }

//     const id_tache = form.querySelector('input[name="id"]');
//     const nom = form.querySelector('input[name="nom"]');
//     const typeTache = form.querySelector('select[name="idTypeTache"]');
//     const sousMenu = form.querySelector('select[name="idSousMenu"]');
//     const url = form.querySelector('input[name="url"]');
//     const autreRessource = form.querySelector('textarea[name="autre_ressource"]');
//     const commentaire = form.querySelector('textarea[name="commentaire"]');
//     const idIcon = form.querySelector('select[name="idIcon"]');
//     const actionInput = form.querySelector('input[name="option"]');
//     const niveau_UA = form.querySelector('select[name="nivUA"]');
//     const id_UA = form.querySelector('select[name="id_UA"]');
//     const idFonction = form.querySelector('select[name="id_fonction"]');
//     const idDB = form.querySelector('select[name="idDB"]');


//     !id_tache && alert("Veuillez renseigner l'identifiant de la tâche");
//     !nom && alert("Veuillez renseigner le nom");
//     !typeTache && alert("Veuillez renseigner le type de tâche");
//     !sousMenu && alert("Veuillez renseigner le sous-menu");
//     !url && alert("Veuillez renseigner l'URL");
//     !autreRessource && alert("Veuillez renseigner l'autre ressource");
//     !commentaire && alert("Veuillez renseigner le commentaire");
//     !idIcon && alert("Veuillez renseigner l'icône");
//     !actionInput && alert("Veuillez renseigner l'action");

//     // Vérifier si tous les éléments nécessaires sont présents
//     if (!id_tache || !nom || !typeTache || !sousMenu || !url || !autreRessource || !commentaire || !idIcon || !actionInput) {
//         console.error("Certains champs du formulaire sont introuvables !");
//         return;
//     }

//     // Envoyer la requête pour récupérer les détails de la tâche
//    fetch('/personnel/admin-controller', {
//         method:  "POST",
//         headers: { "Content-Type": "application/x-www-form-urlencoded" },
//         body:    new URLSearchParams({ option: 21, id: id })
//     })
//         .then((response) => {
//             if (!response.ok) {
//                 throw new Error("Erreur lors de la récupération des détails de la tâche.");
//             }
//             return response.json();
//         })
//         .then((data) => {
//             if (data && data && data[0]) {
//                 const tache = data[0];
//                 console.log("Détails de la tâche :", tache);

//                 // Remplir les champs du formulaire avec les données récupérées
//                 id_tache.value = id;
//                 nom.value = tache.nom;
//                 typeTache.value = tache.idTypeTache;
//                 sousMenu.value = tache.idSousMenu;
//                 url.value = tache.url || "";
//                 autreRessource.value = tache.autre_ressource || "";
//                 commentaire.value = tache.commentaire || "";
//                 idIcon.value = tache.idIcon;
//                 idFonction.value = tache.idFonction
//                 idDB.value = tache.idDB || "";

//                 // Appeler la fonction type_tache
//                 type_tache(tache.idTypeTache);
//                 // id_qualification.textContent = tache.qualification;

//                 // 4. Modifier get_UA pour accepter une valeur présélectionnée
//                 if (tache.idTypeTache == 1) {
//                     // Gérer le niveau UA après un court délai
//                     const niveau_UA = document.getElementById('nivUA');
//                     if (tache.idUniteAdministrativeNiv1 !== null) {
//                         niveau_UA.value = 1;
//                     } else if (tache.idUniteAdministrativeNiv2 !== null) {
//                         niveau_UA.value = 2;
//                     } else if (tache.idUniteAdministrativeNiv3 !== null) {
//                         niveau_UA.value = 3;
//                     }
//                     niveau_UA.dispatchEvent(new Event('change')); // Déclencher l'événement de changement

//                     setTimeout(() => {

//                         let idUASelect = document.getElementById('id_UA');
//                         if (idUASelect) {
//                             // Déterminer l'ID à présélectionner
//                             let idToSelect;
//                             if (tache.idUniteAdministrativeNiv1 !== null) {
//                                 idToSelect = tache.idUniteAdministrativeNiv1;
//                             } else if (tache.idUniteAdministrativeNiv2 !== null) {
//                                 idToSelect = tache.idUniteAdministrativeNiv2;
//                             } else if (tache.idUniteAdministrativeNiv3 !== null) {
//                                 idToSelect = tache.idUniteAdministrativeNiv3;
//                             }

//                             if (idToSelect) {
//                                 // Modifier get_UA pour accepter un paramètre optionnel
//                                 idUASelect.value = idToSelect; // Mettre à jour la valeur du select
//                             }
//                         }
//                     }, 1000);
//                 }
//                 // Mettre à jour l'aperçu de l'icône
//                 document.getElementById('iconPreview').innerHTML = tache.icon || "";

//                 // Modifier l'attribut "action" du formulaire
//                 actionInput.value = "edit_tache";

//                 // Ouvrir le modal avec l'ID "add_tache"
//                 const modal = new bootstrap.Modal(document.getElementById("add_tache"));
//                 modal.show();
//             } else {
//                 console.error("Aucune tâche trouvée avec cet ID.");
//                 Swal.fire({
//                     icon: "error",
//                     title: "Erreur",
//                     text: "Impossible de récupérer les détails de la tâche.",
//                 });
//             }
//         })
//         .catch((error) => {
//             console.error("Erreur :", error);
//             Swal.fire({
//                 icon: "error",
//                 title: "Erreur",
//                 text: "Une erreur est survenue lors de la récupération des détails de la tâche.",
//             });
//         });
// }

function get_detail_tache(id) {

    const form = document.getElementById("tacheForm");
    if (!form) {
        console.error("Formulaire introuvable !");
        return;
    }

    const id_tache       = form.querySelector('input[name="id"]');
    const nom            = form.querySelector('input[name="nom"]');
    const typeTache      = form.querySelector('select[name="idTypeTache"]');
    const sousMenu       = form.querySelector('select[name="idSousMenu"]');
    const url            = form.querySelector('input[name="url"]');
    const autreRessource = form.querySelector('textarea[name="autre_ressource"]');
    const commentaire    = form.querySelector('textarea[name="commentaire"]');
    const idIcon         = form.querySelector('select[name="idIcon"]');
    const actionInput    = form.querySelector('input[name="option"]');
    const idFonction     = form.querySelector('select[name="id_fonction"]');
    const idDB           = form.querySelector('select[name="idDB"]');

    if (!id_tache || !nom || !typeTache || !sousMenu || !url || !autreRessource || !commentaire || !idIcon || !actionInput) {
        console.error("Certains champs du formulaire sont introuvables !");
        return;
    }

    fetch('/personnel/admin-controller', {
        method:  "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body:    new URLSearchParams({ option: 21, id: id })
    })
        .then((response) => {
            if (!response.ok) throw new Error("Erreur lors de la récupération des détails de la tâche.");
            return response.json();
        })
        .then((data) => {
            if (!data || !data[0]) {
                console.error("Aucune tâche trouvée avec cet ID.");
                Swal.fire({
                    icon:  "error",
                    title: "Erreur",
                    text:  "Impossible de récupérer les détails de la tâche.",
                });
                return;
            }

            const tache = data[0];
            console.log("Détails de la tâche :", tache);

            // ── Champs simples ──────────────────────────────────────
            id_tache.value       = id;
            nom.value            = tache.nom            || "";
            url.value            = tache.url            || "";
            autreRessource.value = tache.autre_ressource || "";
            commentaire.value    = tache.commentaire    || "";
            actionInput.value    = "edit_tache";

            // ── Champs Select2 ──────────────────────────────────────
            $(typeTache).val(tache.idTypeTache).trigger('change');
            $(sousMenu).val(tache.idSousMenu).trigger('change');
            $(idFonction).val(tache.idFonction || "").trigger('change');
            $(idDB).val(tache.idDB || "").trigger('change');

            // ── Icône Select2 + aperçu ──────────────────────────────
            $(idIcon).val(tache.idIcon).trigger('change');

            const selectedOption = idIcon.options[idIcon.selectedIndex];
            const iconPreview    = document.getElementById("iconPreview");

            if (selectedOption && selectedOption.value) {
                const icon = $(selectedOption).data('icon');
                iconPreview.innerHTML = icon
                    ? '<span class="icon-preview">' + icon + '</span>'
                    : (tache.icon || "");
            } else {
                iconPreview.innerHTML = tache.icon || "";
            }

            // ── Type tâche ──────────────────────────────────────────
            type_tache(tache.idTypeTache);

            // ── Niveau UA (si type Structure) ───────────────────────
            if (tache.idTypeTache == 1) {
                const niveau_UA = document.getElementById('nivUA');

                if (tache.idUniteAdministrativeNiv1 !== null) {
                    $(niveau_UA).val(1).trigger('change');
                } else if (tache.idUniteAdministrativeNiv2 !== null) {
                    $(niveau_UA).val(2).trigger('change');
                } else if (tache.idUniteAdministrativeNiv3 !== null) {
                    $(niveau_UA).val(3).trigger('change');
                }

                setTimeout(() => {
                    const idUASelect = document.getElementById('id_UA');
                    if (!idUASelect) return;

                    let idToSelect = null;

                    if (tache.idUniteAdministrativeNiv1 !== null) {
                        idToSelect = tache.idUniteAdministrativeNiv1;
                    } else if (tache.idUniteAdministrativeNiv2 !== null) {
                        idToSelect = tache.idUniteAdministrativeNiv2;
                    } else if (tache.idUniteAdministrativeNiv3 !== null) {
                        idToSelect = tache.idUniteAdministrativeNiv3;
                    }

                    if (idToSelect) {
                        $(idUASelect).val(idToSelect).trigger('change');
                    }
                }, 1000);
            }

            // ── Ouvrir le modal ─────────────────────────────────────
            const modal = new bootstrap.Modal(document.getElementById("add_tache"));
            modal.show();
        })
        .catch((error) => {
            console.error("Erreur :", error);
            Swal.fire({
                icon:  "error",
                title: "Erreur",
                text:  "Une erreur est survenue lors de la récupération des détails de la tâche.",
            });
        });
}

