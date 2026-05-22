
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

            '#global-loader{' +
            'position:fixed;' +
            'inset:0;' +
            'z-index:9999;' +
            'display:flex;' +
            'align-items:center;' +
            'justify-content:center' +
            '}' +

            '.loader-backdrop{' +
            'position:absolute;' +
            'inset:0;' +
            'background:rgba(4,20,11,.55);' +
            'backdrop-filter:blur(4px);' +
            'animation:loaderFadeIn .2s ease' +
            '}' +

            '.loader-box{' +
            'position:relative;' +
            'z-index:1;' +
            'background:#fff;' +
            'border-radius:20px;' +
            'padding:36px 48px;' +
            'display:flex;' +
            'flex-direction:column;' +
            'align-items:center;' +
            'gap:18px;' +
            'box-shadow:0 0 0 1px rgba(17,59,38,.12),0 24px 60px rgba(0,0,0,.20);' +
            'animation:loaderSlideUp .25s cubic-bezier(.34,1.56,.64,1);' +
            'min-width:220px' +
            '}' +

            '.loader-spinner svg{' +
            'width:48px;' +
            'height:48px;' +
            'animation:loaderRotate .9s linear infinite' +
            '}' +

            '.loader-spinner circle{' +
            'stroke:#113B26;' +
            'stroke-linecap:round;' +
            'stroke-dasharray:80;' +
            'stroke-dashoffset:60;' +
            'animation:loaderDash 1.4s ease-in-out infinite' +
            '}' +

            '.loader-msg{' +
            'margin:0;' +
            'font-size:.85rem;' +
            'font-weight:600;' +
            'color:#113B26;' +
            'letter-spacing:.02em;' +
            'text-align:center;' +
            'opacity:.85' +
            '}' +

            '@keyframes loaderFadeIn{' +
            'from{opacity:0}' +
            'to{opacity:1}' +
            '}' +

            '@keyframes loaderSlideUp{' +
            'from{' +
            'opacity:0;' +
            'transform:translateY(16px) scale(.97)' +
            '}' +
            'to{' +
            'opacity:1;' +
            'transform:translateY(0) scale(1)' +
            '}' +
            '}' +

            '@keyframes loaderRotate{' +
            'to{transform:rotate(360deg)}' +
            '}' +

            '@keyframes loaderDash{' +
            '0%{stroke-dashoffset:80}' +
            '50%{stroke-dashoffset:20}' +
            '100%{stroke-dashoffset:80}' +
            '}' +

            '</style>'
        );
    }
}

function hideLoader() {
    $('#global-loader').remove();
}


var _loaderSync = {
    dtReady: false,
    statsReady: false
};

function _checkCloseLoader() {

    if (_loaderSync.dtReady && _loaderSync.statsReady) {

        hideLoader();

        _loaderSync.dtReady = false;
        _loaderSync.statsReady = false;
    }
}

function _resetLoaderSync() {

    _loaderSync.dtReady = false;
    _loaderSync.statsReady = false;
}



function testJSON(text) {

    if (typeof text !== "string") {
        return false;
    }

    try {

        const json = JSON.parse(text);

        return (typeof json === 'object');

    } catch (e) {

        return false;
    }
}

function getYearInDakar() {

    const options = {
        timeZone: 'Africa/Dakar',
        year: 'numeric'
    };

    return new Intl.DateTimeFormat('fr-FR', options).format(new Date());
}


// ==================================================
// DATATABLE
// ==================================================
var KTDatatablesServerSide = function () {

    var dt;

    function initDatatable() {

        dt = $("#kt_datatable_utilisateurs").DataTable({

            responsive: true,

            select: {
                style: 'os',
                selector: 'td:first-child',
                className: 'row-selected'
            },

            ajax: {

                url: "/personnel/admin-controller",

                method: "POST",

                data: {
                    option: 1
                },

                dataSrc: "",

                error: function () {

                    _loaderSync.dtReady = true;

                    _checkCloseLoader();

                    Swal.fire(
                        'Erreur',
                        "Impossible de charger la liste des utilisateurs.",
                        'error'
                    );
                }
            },

            columns: [
                { data: "photo" },
                { data: "matricule" },
                { data: "prenom" },
                { data: "nom" },
                { data: "qualification" },
                { data: "affectation" },
                { data: "poste" },
                { data: "email" },
                { data: "dateCreation" },
                { data: "etat" },
                { data: "tmp" }
            ],

            columnDefs: [

                {
                    targets: 0,
                    orderable: false,
                    className: 'text-end',

                    render: function (data) {

                        return '' +
                            '<div class="d-flex align-items-center">' +
                            '<div class="symbol symbol-45px me-5">' +
                            '<img src="' + data + '" alt="Photo utilisateur" onerror="this.src=\'/assets/media/default.png\'">' +
                            '</div>' +
                            '</div>';
                    }
                },

                {
                    targets: -1,
                    data: "tmp",
                    orderable: false,
                    className: 'text-center',

                    render: function (data, type, row) {

                        const etat1 = Number(row.etat1);
                        const etat2 = Number(row.etat2);

                        if (etat2 !== 1) {
                            return '';
                        }

                        if (etat1 === 0) {
                            return '';
                        }

                        if (etat1 === 1) {

                            return '' +
                                '<a href="javascript:void(0)" ' +
                                'class="btn btn-danger btn-sm"' +
                                'onclick="actionBloquerCompte(\'' + row.tmp + '\')">'+
                                '<i class="fa fa-lock"></i> Bloquer le compte' +
                                '</a>';
                        }

                        if (etat1 === 2) {

                            return '' +
                                '<a href="javascript:void(0)" ' +
                                'class="btn btn-success btn-sm"' +
                                'onclick="actionDeBloquerCompte(\'' + row.tmp + '\')">'+
                                '<i class="fa fa-unlock"></i> Débloquer le compte' +
                                '</a>';
                        }

                        return '';
                    }
                }
            ],

            ordering: false,

            createdRow: function (row, data) {

                $(row)
                    .find('td:eq(4)')
                    .attr('data-filter', data.CreditCardType);
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

        if (!filterSearch) {
            return;
        }

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
// READY
// ==================================================
$(document).ready(function () {

    _resetLoaderSync();

    showLoader("Chargement des utilisateurs...");

    loadStats();
});


// ==================================================
// STATS
// ==================================================
function setStatEl(id, value) {

    const el = document.getElementById(id);

    if (el) {
        el.textContent = value;
    }
}

function setStatBar(pct) {

    const bar = document.getElementById("ps1-bar-fill");

    if (!bar) {
        return;
    }

    bar.style.transition = "none";
    bar.style.transform = "scaleY(0)";

    bar.getBoundingClientRect();

    bar.style.transition = "transform 0.6s ease";
    bar.style.transform = "scaleY(" + (pct / 100) + ")";
}

function setStatsLoading() {

    [
        "ps1-total",
        "ps1-actifs",
        "ps1-inactifs",
        "ps1-bloquer",
        "ps1-pct-actifs",
        "ps1-pct-inactifs",
        "ps1-pct-bloquer"
    ].forEach(function (id) {

        setStatEl(id, "…");
    });

    setStatBar(0);
}

function loadStats() {

    setStatsLoading();

    $.ajax({

        type: 'post',

        url: '/personnel/admin-controller',

        data: {
            option: 2
        },

        success: function (data) {

            const fallback = function () {

                [
                    "ps1-total",
                    "ps1-actifs",
                    "ps1-inactifs",
                    "ps1-bloquer",
                    "ps1-pct-actifs",
                    "ps1-pct-inactifs",
                    "ps1-pct-bloquer"
                ].forEach(function (id) {

                    setStatEl(id, "—");
                });

                setStatBar(0);

                _loaderSync.statsReady = true;

                _checkCloseLoader();
            };

            if (
                typeof data !== "string" ||
                data.trim() === "" ||
                !testJSON(data)
            ) {
                fallback();
                return;
            }

            const json = JSON.parse(data);

            const total = json.total ?? 0;
            const actifs = json.actifs ?? 0;
            const inactifs = json.inactifs ?? 0;
            const bloques = json.bloques ?? 0;

            const pctActifs = total > 0
                ? Math.round((actifs / total) * 100)
                : 0;

            const pctInactifs = total > 0
                ? Math.round((inactifs / total) * 100)
                : 0;

            const pctBloques = total > 0
                ? Math.round((bloques / total) * 100)
                : 0;

            setStatEl("ps1-total", total);
            setStatEl("ps1-actifs", actifs);
            setStatEl("ps1-inactifs", inactifs);
            setStatEl("ps1-bloquer", bloques);

            setStatEl("ps1-pct-actifs", pctActifs + "%");
            setStatEl("ps1-pct-inactifs", pctInactifs + "%");
            setStatEl("ps1-pct-bloquer", pctBloques + "%");

            setStatBar(pctActifs);

            _loaderSync.statsReady = true;

            _checkCloseLoader();
        },

        error: function () {

            [
                "ps1-total",
                "ps1-actifs",
                "ps1-inactifs",
                "ps1-bloquer",
                "ps1-pct-actifs",
                "ps1-pct-inactifs",
                "ps1-pct-bloquer"
            ].forEach(function (id) {

                setStatEl(id, "—");
            });

            setStatBar(0);

            _loaderSync.statsReady = true;

            _checkCloseLoader();
        }
    });
}


function actionBloquerCompte(tmp)
{

    Swal.fire({
        title: 'Confirmation',
        text: 'Êtes-vous sûr de vouloir bloquer cet utilisateur ?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, bloquer',
        cancelButtonText: 'Annuler'
    }).then((result) => {

        if (result.isConfirmed) {


            showLoader("Blocage de l'utilisateur en cours...");


            $.ajax({

                type: 'post',

                url: '/personnel/admin-controller',

                data: {
                    option: 3,
                    tmp: tmp
                },

                success: function (data) {

                    hideLoader();

                    if (data === "sessionExpired")
                    {
                        window.location.href = "/personnel/signin";

                    }
                    else if (data === "succès")
                    {

                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            html: `
                                Le compte de l'utilisateur a été bloqué avec succès.
                            `,
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#28a745',
                            timer: 3000,
                            timerProgressBar: true,
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOutUp'
                            }
                        });

                        $('#kt_datatable_utilisateurs').DataTable().destroy();
                        KTDatatablesServerSide.init();
                        loadStats();



                    }
                    else if (data === "erreur")
                    {

                        Swal.fire({
                            icon: 'error',
                            title: 'Oups...',
                            html: `
                                Une erreur inattendue est survenue.<br>
                                Veuillez réessayer ultérieurement.
                            `,
                            confirmButtonText: 'Ok',
                            confirmButtonColor: '#d33',
                            background: '#fff',
                            color: '#333',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showClass: {
                                popup: 'animate__animated animate__shakeX'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOut'
                            }
                        });

                    }else
                    {

                        Swal.fire({
                            icon: 'error',
                            title: 'Oups...',
                            html: `
                                Une erreur inattendue est survenue.<br>
                                Veuillez réessayer ultérieurement.
                            `,
                            confirmButtonText: 'Ok',
                            confirmButtonColor: '#d33',
                            background: '#fff',
                            color: '#333',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showClass: {
                                popup: 'animate__animated animate__shakeX'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOut'
                            }
                        });


                    }

                },

                error: function () {
                    hideLoader();

                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur réseau',
                        text: 'Impossible de contacter le serveur.'
                    });


                }
            });

        }
        else
        {
            Swal.fire({
                icon: 'info',
                title: 'Annulée',
                text: 'Action annulée'
            });


        }
    });
}



function actionDeBloquerCompte(tmp)
{

    Swal.fire({
        title: 'Confirmation',
        text: 'Êtes-vous sûr de vouloir débloquer cet utilisateur ?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, débloquer',
        cancelButtonText: 'Annuler'
    }).then((result) => {

        if (result.isConfirmed) {


            showLoader("Deblocage de l'utilisateur en cours...");

            $.ajax({

                type: 'post',

                url: '/personnel/admin-controller',

                data: {
                    option: 4,
                    tmp: tmp
                },

                success: function (data) {
                    hideLoader();

                    if (data === "sessionExpired")
                    {
                        window.location.href = "/personnel/signin";

                    }
                    else if (data === "succès")
                    {

                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            html: `
                                Le compte de l'utilisateur a été débloqué avec succès.
                            `,
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#28a745',
                            timer: 3000,
                            timerProgressBar: true,
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOutUp'
                            }
                        });

                        $('#kt_datatable_utilisateurs').DataTable().destroy();
                        KTDatatablesServerSide.init();
                        loadStats();


                    }
                    else if (data === "erreur")
                    {

                        Swal.fire({
                            icon: 'error',
                            title: 'Oups...',
                            html: `
                                Une erreur inattendue est survenue.<br>
                                Veuillez réessayer ultérieurement.
                            `,
                            confirmButtonText: 'Ok',
                            confirmButtonColor: '#d33',
                            background: '#fff',
                            color: '#333',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showClass: {
                                popup: 'animate__animated animate__shakeX'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOut'
                            }
                        });




                    }else
                    {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oups...',
                            html: `
                                Une erreur inattendue est survenue.<br>
                                Veuillez réessayer ultérieurement.
                            `,
                            confirmButtonText: 'Ok',
                            confirmButtonColor: '#d33',
                            background: '#fff',
                            color: '#333',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showClass: {
                                popup: 'animate__animated animate__shakeX'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOut'
                            }
                        });


                    }

                },

                error: function () {

                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur réseau',
                        text: 'Impossible de contacter le serveur.'
                    });
                    hideLoader();


                }
            });

        }
        else
        {
            Swal.fire({
                icon: 'info',
                title: 'Annulée',
                text: 'Action annulée'
            });
        }
    });
}


function ajouterUtilisateur()
{
    $("#kt_modal_add_user").modal('show');

}