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
        dt = $("#kt_table_sous_menu").DataTable({
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
                data:    { option: 7 },
                dataSrc: "",
                error: function () {
                    _loaderSync.dtReady = true;
                    _checkCloseLoader();
                    Swal.fire('Erreur', "Impossible de charger la liste des sous menus.", 'error');
                }
            },
            columns: [
                {
                    data:      "icon",
                    className: 'text-center',
                    render:    function (data) { return data; }
                },
                {
                    data:      "nom_s",
                    className: 'text-center',
                    render:    function (data) { return data; }
                },
                {
                    data:      "id",
                    className: 'text-center max-w-100px',
                    render:    function (data, type, row) {
                        if (row.tmp === 0) {
                            return `
                                <a href="javascript:void(0)" class="btn btn-warning btn-sm" onclick="get_detail_sous_menu(${data})">
                                    <i class="fa fa-edit"></i> Modifier
                                </a>
                                <a href="javascript:void(0)" class="btn btn-danger btn-sm" onclick="sup_sous_menu(${data})">
                                    <i class="fa fa-trash"></i> Supprimer
                                </a>`;
                        } else {
                            return `<span class="badge badge-success">Verrouillé</span>`;
                        }
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
        data: { option: 8 },
        success: function (data) {
            if (typeof data === 'string' && data.substr(0, 4) === "icon") {
                $('#boxIcon1').html(data.substr(4));
                $('#boxIcon2').html(data.substr(4));
            } else {
                $('#boxIcon1').html('');
                $('#boxIcon2').html('');
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
});

// ==================================================
// SELECTION ICONE
// ==================================================
function selectIcon(iconId, element) {
    document.querySelectorAll('.icon-item, .dropdown-item')
        .forEach(icon => icon.classList.remove('active'));

    element.classList.add('active');

    document.getElementById("selectedIconId").value       = iconId;
    document.getElementById("selectedIconIdToEdit").value = iconId;
}

// ==================================================
// AJOUT SOUS MENU
// ==================================================
function ajouterSousMenu() {
    $('#add_sous_menu').modal('show');
}

function addSousMenu(event) {
    event.preventDefault();

    clearInputError('nom');
    clearInputError('selectedIconId');

    const nom    = document.getElementById('nom').value.trim();
    const idIcon = document.getElementById('selectedIconId').value;
    const form   = event.target;

    if (!idIcon) {
        showInputError('selectedIconId', 'Veuillez sélectionner une icône.');
        return;
    }
    if (!nom) {
        showInputError('nom', 'Veuillez saisir un nom.');
        return;
    }

    const submitButton     = document.querySelector('.submitAddSousMenuButton');
    submitButton.disabled  = true;
    const originalText     = submitButton.innerHTML;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> En cours...';

    fetch("/personnel/admin-controller", {
        method:  "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body:    new URLSearchParams({ option: 10, nom: nom, idIcon: idIcon }).toString()
    })
        .then((response) => {
            if (!response.ok) throw new Error("Network response was not ok");
            return response.text();
        })
        .then((result) => {
            submitButton.disabled  = false;
            submitButton.innerHTML = originalText;

            const trimmed = result.trim();

            if (trimmed === 'sessionExpired') {
                window.location.href = '/personnel/signin';

            } else if (trimmed === 'succès') {
                $('#add_sous_menu').modal('hide');

                Swal.fire({
                    icon:               'success',
                    title:              'Succès !',
                    text:               'Le sous-menu a été ajouté avec succès.',
                    confirmButtonText:  'OK',
                    confirmButtonColor: '#113B26',
                    timer:              2000,
                    timerProgressBar:   true,
                    showClass: { popup: 'animate__animated animate__fadeInDown' },
                    hideClass: { popup: 'animate__animated animate__fadeOut' }
                }).then(() => {
                    form.reset();
                    document.getElementById('selectedIconId').value = '';
                    document.querySelectorAll('.icon-item.active, .dropdown-item.active')
                        .forEach(el => el.classList.remove('active'));
                    reloadAll();
                });

            } else if (trimmed === 'nomExiste') {
                showInputError('nom', 'Ce nom existe déjà, veuillez en choisir un autre.');
                document.getElementById('nom').addEventListener('input', () => {
                    clearInputError('nom');
                }, { once: true });

            } else if (trimmed === 'erreur') {
                $('#add_sous_menu').modal('hide');
                showSwal('error', 'Erreur', "Une erreur est survenue lors de l'ajout du sous-menu.");

            } else {
                console.warn('Réponse inattendue:', trimmed);
                $('#add_sous_menu').modal('hide');
                showSwal('error', 'Erreur inattendue', 'Réponse serveur non reconnue : ' + trimmed);
            }
        })
        .catch((error) => {
            console.error('Erreur:', error);
            submitButton.disabled  = false;
            submitButton.innerHTML = originalText;
            $('#add_sous_menu').modal('hide');
            showSwal('error', 'Erreur', "Une erreur est survenue lors de l'ajout du sous-menu.");
        });
}

// ==================================================
// SUPPRESSION SOUS MENU
// ==================================================
function sup_sous_menu(id) {
    Swal.fire({
        title:              'Confirmation',
        text:               'Êtes-vous sûr de vouloir supprimer ce sous-menu ?',
        icon:               'question',
        showCancelButton:   true,
        confirmButtonColor: '#113B26',
        cancelButtonColor:  '#b81c2c',
        confirmButtonText:  'Oui, supprimer',
        cancelButtonText:   'Annuler'
    }).then(function (result) {
        if (!result.isConfirmed) return;

        showLoader("Suppression du sous-menu en cours…");

        $.ajax({
            type: 'POST',
            url:  '/personnel/admin-controller',
            data: { option: 11, id: id },
            success: function (data) {
                hideLoader();
                const trimmed = (data || '').trim();

                if (trimmed === 'sessionExpired') {
                    window.location.href = '/personnel/signin';

                } else if (trimmed === 'succès') {
                    showSwal('success', 'Supprimé !', 'Le sous-menu a été supprimé avec succès.', function () {
                        reloadAll();
                    });

                } else {
                    showSwal('error', 'Erreur', "Une erreur inattendue est survenue. Veuillez réessayer.");
                }
            },
            error: function () {
                hideLoader();
                showSwal('error', 'Erreur réseau', 'Impossible de contacter le serveur.');
            }
        });
    });
}

// ==================================================
// DETAIL / EDITION SOUS MENU
// ==================================================
function get_detail_sous_menu(id) {
    document.querySelectorAll('.icon-item, .dropdown-item')
        .forEach(icon => icon.classList.remove('active'));

    const id_sous_menu = document.getElementById("id_sous_menu");
    const nom          = document.getElementById("nomTacheToEdit");
    const id_icon      = document.getElementById("selectedIconIdToEdit");
    const ancienIcon   = document.getElementById("ancienIcon");

    id_sous_menu.value = id;

    fetch('/personnel/admin-controller', {
        method:  "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body:    new URLSearchParams({ option: 9, id: id })
    })
        .then((response) => response.text())
        .then((responseText) => {
            if (!testJSON(responseText)) {
                Swal.fire('Erreur', 'Le serveur a renvoyé une réponse invalide.', 'error');
                return;
            }

            const data = JSON.parse(responseText);

            if (data === null || typeof data !== 'object') {
                Swal.fire('Erreur', 'Aucune donnée trouvée.', 'error');
                return;
            }

            nom.value            = data.nom    || '';
            id_icon.value        = data.idIcon || '';
            ancienIcon.innerHTML = data.icon   || '';

            const selectedIcon = document.querySelector(`.icon-item[data-id="${data.idIcon}"]`);
            if (selectedIcon) selectedIcon.classList.add('active');

            clearInputError('nomTacheToEdit');
            clearInputError('selectedIconIdToEdit');

            $('#edit_sous_menu').modal('show');
        })
        .catch(() => {
            Swal.fire('Erreur', 'Impossible de récupérer les informations.', 'error');
        });
}

// ==================================================
// MODIFICATION SOUS MENU
// ==================================================
function updateSousMenuOne(event) {
    event.preventDefault();

    clearInputError('nomTacheToEdit');
    clearInputError('selectedIconIdToEdit');

    const nom        = document.getElementById('nomTacheToEdit').value.trim();
    const idIcon     = document.getElementById('selectedIconIdToEdit').value;
    const idSousMenu = document.getElementById('id_sous_menu').value;
    const form       = document.getElementById('editSousMenuFrom');

    if (!form) {
        console.error("Formulaire introuvable !");
        return;
    }

    if (!idIcon) {
        showInputError('selectedIconIdToEdit', 'Veuillez sélectionner une icône.');
        return;
    }
    if (!nom) {
        showInputError('nomTacheToEdit', 'Veuillez saisir un nom.');
        return;
    }

    const submitButton     = document.querySelector('.submitEditSousMenuButton');
    submitButton.disabled  = true;
    const originalText     = submitButton.innerHTML;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> En cours...';

    fetch("/personnel/admin-controller", {
        method:  "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body:    new URLSearchParams({
            option:  12,
            id:      idSousMenu,
            nom:     nom,
            idIcon:  idIcon
        }).toString()
    })
        .then((response) => {
            if (!response.ok) throw new Error("Network response was not ok");
            return response.text();
        })
        .then((result) => {
            submitButton.disabled  = false;
            submitButton.innerHTML = originalText;

            const trimmed = result.trim();

            if (trimmed === 'sessionExpired') {
                window.location.href = '/personnel/signin';

            } else if (trimmed === 'succès') {
                $('#edit_sous_menu').modal('hide');

                Swal.fire({
                    icon:               'success',
                    title:              'Succès !',
                    text:               'Le sous-menu a été modifié avec succès.',
                    confirmButtonText:  'OK',
                    confirmButtonColor: '#113B26',
                    timer:              2000,
                    timerProgressBar:   true,
                    showClass: { popup: 'animate__animated animate__fadeInDown' },
                    hideClass: { popup: 'animate__animated animate__fadeOut' }
                }).then(() => {
                    form.reset();
                    document.getElementById('selectedIconIdToEdit').value = '';
                    document.querySelectorAll('.icon-item.active, .dropdown-item.active')
                        .forEach(el => el.classList.remove('active'));
                    reloadAll();
                });

            } else if (trimmed === 'nomExiste') {
                showInputError('nomTacheToEdit', 'Ce nom existe déjà, veuillez en choisir un autre.');
                document.getElementById('nomTacheToEdit').addEventListener('input', () => {
                    clearInputError('nomTacheToEdit');
                }, { once: true });

            } else if (trimmed === 'erreur') {
                $('#edit_sous_menu').modal('hide');
                showSwal('error', 'Erreur', "Une erreur est survenue lors de la modification du sous-menu.");

            } else {
                console.warn('Réponse inattendue:', trimmed);
                $('#edit_sous_menu').modal('hide');
                showSwal('error', 'Erreur inattendue', 'Réponse serveur non reconnue : ' + trimmed);
            }
        })
        .catch((error) => {
            console.error('Erreur:', error);
            submitButton.disabled  = false;
            submitButton.innerHTML = originalText;
            $('#edit_sous_menu').modal('hide');
            showSwal('error', 'Erreur', "Une erreur est survenue lors de la modification du sous-menu.");
        });
}