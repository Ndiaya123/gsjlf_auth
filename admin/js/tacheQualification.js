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
        if ($.fn.DataTable.isDataTable('#kt_table_tache_qualification')) {
            $('#kt_table_tache_qualification').DataTable().destroy();
        }
        _loaderSync.statsReady = true;
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
        dt = $("#kt_table_tache_qualification").DataTable({
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
                data:    { option: 26 },
                dataSrc: "",
                error: function () {
                    _loaderSync.dtReady = true;
                    _checkCloseLoader();
                    Swal.fire('Erreur', "Impossible de charger la liste.", 'error');
                }
            },
            columns: [
                { data: "tache",         title: "Tâche"                },
                { data: "codeUA",        title: "Unité Administrative" },
                { data: "qualification", title: "Qualification"        },
                {
                    data:      null,
                    orderable: false,
                    render: function (data) {
                        return '<button type="button" class="btn btn-sm btn-danger"' +
                            ' onclick="changeValiditeTacheQualification(' + data.id + ')">' +
                            'Supprimer</button>';
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
    showLoader("Chargement...");

    // Option 27 — Qualifications
    $.ajax({
        type: 'POST', url: '/personnel/admin-controller', data: { option: 27 },
        success: function (data) {
            var html = (typeof data === 'string' && data.substr(0, 7) === '<option')
                ? data : '<option value="">Sélectionner une qualification</option>';
            $('#idQualification').html(html);
            _loaderSync.statsReady = true;
            _checkCloseLoader();
        },
        error: function () {
            _loaderSync.statsReady = true;
            _checkCloseLoader();
            Swal.fire('Erreur', "Impossible de charger les qualifications.", 'error');
        }
    });
});

// ==================================================
// NIVEAU → UNITÉ ADMINISTRATIVE
// ==================================================
function actionNiveau(idNiv) {
    $('#idUniteAd').html(null);

    if (!idNiv || idNiv === '') {
        $('#idUniteAd').html('<option value="">Sélectionner une unité administrative</option>');
        $('#idTache').html('<option value="">Sélectionner une tâche</option>');
        return;
    }

    $.ajax({
        type: 'POST',
        url:  '/personnel/admin-controller',
        data: { option: 20, nivUA: idNiv },
        success: function (data) {
            var html = (typeof data === 'string' && data.substr(0, 7) === '<option')
                ? data : '<option value="">Sélectionner une unité administrative</option>';
            $('#idUniteAd').html(html);
            // Vider les tâches quand on change de niveau
            $('#idTache').html('<option value="">Sélectionner une tâche</option>');
            if (typeof validator1 !== 'undefined') {
                validator1.revalidateField('idUniteAd');
                validator1.revalidateField('idTache');
            }
        },
        error: function () {
            Swal.fire('Erreur', "Impossible de charger les unités administratives.", 'error');
        }
    });
}

// ==================================================
// UNITÉ ADMINISTRATIVE → TÂCHES
// ==================================================
function actionTache(idUniteAd) {
    $('#idTache').html(null);

    if (!idUniteAd || idUniteAd === '') {
        $('#idTache').html('<option value="">Sélectionner une tâche</option>');
        return;
    }

    var idNiv = document.getElementById('idNiv').value;

    $.ajax({
        type: 'POST',
        url:  '/personnel/admin-controller',
        data: { option: 28, idUniteAd: idUniteAd, idNiv: idNiv },
        success: function (data) {

            alert("ndiaya");
            alert(data);
            var html = (typeof data === 'string' && data.substr(0, 7) === '<option')
                ? data : '<option value="">Sélectionner une tâche</option>';
            $('#idTache').html(html);
            if (typeof validator1 !== 'undefined') validator1.revalidateField('idTache');
        },
        error: function () {
            Swal.fire('Erreur', "Impossible de charger les tâches.", 'error');
        }
    });
}

// ==================================================
// OUVRIR MODAL
// ==================================================
function ajouterTacheQualification() {
    videAddTacheQualification();
    $('#kt_modal_add_tache_qualification').modal('show');
}

// ==================================================
// VALIDATION FORMULAIRE
// ==================================================
var form1      = document.getElementById('formAddTacheQualification');
var validator1 = FormValidation.formValidation(form1, {
    fields: {
        idNiv: {
            validators: {
                notEmpty: { message: 'Le niveau est obligatoire.' }
            }
        },
        idUniteAd: {
            validators: {
                notEmpty: { message: "L'unité administrative est obligatoire." }
            }
        },
        idTache: {
            validators: {
                notEmpty: { message: 'La tâche est obligatoire.' }
            }
        },
        idQualification: {
            validators: {
                notEmpty: { message: 'La qualification est obligatoire.' }
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
// SOUMISSION
// ==================================================
var submitButton1 = document.getElementById('formAddTacheQualification_submit');

submitButton1.addEventListener('click', function (e) {
    e.preventDefault();
    if (!validator1) return;

    validator1.validate().then(function (status) {
        if (status !== 'Valid') return;

        submitButton1.disabled  = true;
        var originalText        = submitButton1.innerHTML;
        submitButton1.innerHTML = '<span class="indicator-progress d-inline">Veuillez patienter... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>';

        $.ajax({
            type: 'POST',
            url:  '/personnel/admin-controller',
            data: $('#formAddTacheQualification').serialize(),
            success: function (resp) {
                submitButton1.disabled  = false;
                submitButton1.innerHTML = originalText;

                var trimmed = (resp || '').trim();

                if (trimmed === 'sessionExpired') {
                    window.location.href = '/personnel/signin';

                } else if (trimmed === 'succes') {
                    $('#kt_modal_add_tache_qualification').modal('hide');
                    Swal.fire({
                        icon:               'success',
                        title:              'Succès !',
                        text:               'La qualification a été associée avec succès.',
                        confirmButtonColor: '#113B26',
                        timer:              2000,
                        timerProgressBar:   true,
                    }).then(function () {
                        videAddTacheQualification();
                        reloadAll();
                    });

                } else if (trimmed === 'existe') {
                    showSwal('warning', 'Doublon', 'Cette qualification est déjà associée à cette tâche.');

                } else if (trimmed === 'erreur') {
                    $('#kt_modal_add_tache_qualification').modal('hide');
                    showSwal('error', 'Erreur', "Une erreur est survenue lors de l'enregistrement.");

                } else {
                    console.warn('Réponse inattendue :', trimmed);
                    $('#kt_modal_add_tache_qualification').modal('hide');
                    showSwal('error', 'Erreur inattendue', 'Réponse serveur non reconnue : ' + trimmed);
                }
            },
            error: function () {
                submitButton1.disabled  = false;
                submitButton1.innerHTML = originalText;
                $('#kt_modal_add_tache_qualification').modal('hide');
                showSwal('error', 'Erreur', "Une erreur est survenue lors de l'enregistrement.");
            }
        });
    });
});

// ==================================================
// VIDER FORMULAIRE
// ==================================================
function videAddTacheQualification() {
    $('#idNiv, #idUniteAd, #idTache, #idQualification')
        .val('').trigger('change');

    $('#idUniteAd').html('<option value="">Sélectionner une unité administrative</option>');
    $('#idTache').html('<option value="">Sélectionner une tâche</option>');

    form1.reset();
}

function closeAddTacheQualification() {
    videAddTacheQualification();
    $('#kt_modal_add_tache_qualification').modal('hide');
}

// ==================================================
// SUPPRIMER ASSOCIATION
// ==================================================
function changeValiditeTacheQualification(id) {
    Swal.fire({
        title:              'Confirmation',
        text:               'Voulez-vous vraiment supprimer cette association ?',
        icon:               'question',
        showCancelButton:   true,
        confirmButtonColor: '#113B26',
        cancelButtonColor:  '#b81c2c',
        confirmButtonText:  'Oui, supprimer',
        cancelButtonText:   'Annuler'
    }).then(function (result) {
        if (!result.isConfirmed) return;

        showLoader('Suppression en cours…');

        $.ajax({
            type: 'POST',
            url:  '/personnel/admin-controller',
            data: { option: 30, id: id },
            success: function (resp) {
                hideLoader();
                var trimmed = (resp || '').trim();

                if (trimmed === 'sessionExpired') {
                    window.location.href = '/personnel/signin';
                } else if (trimmed === 'succes') {
                    showSwal('success', 'Supprimé !', "L'association a été supprimée avec succès.", function () {
                        reloadAll();
                    });
                } else {
                    showSwal('error', 'Erreur', "Une erreur est survenue lors de la suppression.");
                }
            },
            error: function () {
                hideLoader();
                showSwal('error', 'Erreur réseau', 'Impossible de contacter le serveur.');
            }
        });
    });
}