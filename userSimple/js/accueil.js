

function actionOuvrirApplication(e,e1)
{

    if(e1 === 1)
    {

        if(e === "gest-tache")
        {
            window.location.href = "user-gestion-taches";

        }else
        {

            $.ajax({
                type: 'POST',
                url: '/personnel/user-controller',   // ✅ pointer vers userController
                data: {
                    option: 1,
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
    }