

// ouvreir l'application
function actionOuvrirApplication(e)
{
    alert(e);

if(e === "general")
{
    window.location.replace("/personnel/admin-dashboard");

}else
{

    $.ajax({
        type: 'POST',
        url: '/personnel/admin-controller',
        data: {
            option: 31,
            tmp : e},
        success: function (resp) {

            console.log("Réponse brute =>", resp);

            if (resp === "sesionExpired") {
                window.location.href = "http://localhost/personnel/signin";
                return;
            }

            if (resp === "erreur") {
                Swal.fire('Erreur', "Impossible de te d'ouvrir application. veuillez ressayer ou te rapprocher  a la CRIAT", 'error');

            }

            if (resp.startsWith("ac")) {
                window.location.href = resp.substr(2);
            }
        },
        error: function () {
            Swal.fire('Erreur', "Impossible de te d'ouvrir application. veuillez ressayer ou te rapprocher  a la CRIAT", 'error');
        }
    });

}
}