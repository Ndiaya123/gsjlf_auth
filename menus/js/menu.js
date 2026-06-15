function changeFavicon(url) {
    let link = document.querySelector("link[rel*='icon']");
    if (!link) {
        link = document.createElement('link');
        link.rel = 'shortcut icon';
        document.head.appendChild(link);
    }
    link.href = url;
}

function changeLogo(id, url) {
    const img = document.getElementById(id);
    if (img) {
        img.src = url;
    } else {
        console.error(`Élément avec l'id "${id}" introuvable.`);
    }
}

const logo_gsjlf = "http://localhost/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png";

function testJSON(text) {
    if (typeof text !== "string") return false;
    try {
        var json = JSON.parse(text);
        return (typeof json === 'object');
    } catch (e) {
        return false;
    }
}

$(document).ready(function () {

    // ⚠️ pathname uniquement, pas href complet
    const url_page = window.location.pathname;
    console.log("url_page =>", url_page);

    changeFavicon(logo_gsjlf);
    changeLogo("logo1", logo_gsjlf);
    changeLogo("logo2", logo_gsjlf);

    $.ajax({
        type: 'POST',
        url: '/personnel/menu-controller',
        data: {
            option: 1,
            url_page: url_page
        },
        success: function (data) {

            console.log("Réponse brute =>", data);

            if (data === "sesionExpired") {
                window.location.href = "http://localhost/personnel/signin";
                return;
            }

            if (data === "erreur") {
                document.getElementById("lien_logo1").removeAttribute("href");
                document.getElementById("lien_logo2").removeAttribute("href");
                document.getElementById("user_photo1").removeAttribute("src");
                document.getElementById("user_photo2").removeAttribute("src");
                document.getElementById("user_pn").innerHTML     = '';
                document.getElementById("user_email").innerHTML  = '';
                document.getElementById("infoAppli").innerHTML   = '';
                // ✅ id sans #
                document.getElementById("kt_aside_menu").innerHTML = '';
                return;
            }

            if (testJSON(data)) {
                var json = JSON.parse(data);
                var d = json[0];

                // Liens logo
                document.getElementById("lien_logo1").href = d.lien_logo1 ?? '#';
                document.getElementById("lien_logo2").href = d.lien_logo2 ?? '#';
                document.getElementById("lien_ent").href = d.lien_logo1 ?? '#'

                // Infos utilisateur
                document.getElementById("user_photo1").src      = d.user_photo ?? '';
                document.getElementById("user_photo2").src      = d.user_photo ?? '';
                document.getElementById("user_pn").innerHTML    = d.user_pn    ?? '';
                document.getElementById("user_email").innerHTML = d.user_email ?? '';
                document.getElementById("infoAppli").innerHTML  = d.infoAppli  ?? '';

                // ✅ id sans # — correction critique
                document.getElementById("kt_aside_menu").innerHTML = d.menus ?? '';

                // ✅ createInstances() au lieu de init()
                // init() réinitialise TOUT et détruit les listeners existants
                // createInstances() ne traite que les nouveaux éléments injectés
                if (typeof KTMenu !== 'undefined') {
                    KTMenu.createInstances();
                }
            }
        },
        error: function () {
            Swal.fire('Erreur', "Impossible de charger le menu.", 'error');
        }
    });

});

function actionMonProfile() {
    // à implémenter
}

function actionQuitter() {
    window.location.href = "http://localhost/personnel/signin";
}

document.getElementById('kt_user_menu_dark_mode_toggle')
    .addEventListener('click', function () {
        const url = "http://localhost/personnel/signout";
        if (url) {
            window.location.href = url;
        }
    });