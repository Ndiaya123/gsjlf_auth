<?php
session_start();

include_once("../db.php");
$DB = new DB();
$db = $DB->connect();





function tokendecrypt($data)
{
    $secretKey = 'U@hbENTDRI@TCRI@T2022';
    $secretIv = 'www.ent.uahb.sn';
    $encryptMethod = "AES-256-CBC";
    $key = hash('sha256', $secretKey);
    $iv = substr(hash('sha256', $secretIv), 0, 16);
    $result = openssl_decrypt(base64_decode($data), $encryptMethod, $key, 0, $iv);
    return $result;
}


function verifierCompte($db, $id)
{
    $data = [
        'id_utilisateur' => $id
    ];

    $sql = "SELECT * FROM  utilisateurs  WHERE  id_utilisateur=:id_utilisateur";
    $stmt = $db->prepare($sql);
    $stmt->execute($data);
    $data = $stmt->fetchAll(PDO::FETCH_OBJ);
    return $data;
}




$resultat = null;
$numPreinscription = null;
if (!empty($_GET['dateTmp']) && !empty($_GET['id'])) {


    $tmpEncryptDateCreation = $_GET['dateTmp'];
    $tmpEncryptId = $_GET['id'];
    $tmpDencryptDateCreation = tokendecrypt($tmpEncryptDateCreation);
    $tmpDencryptId = tokendecrypt($tmpEncryptId);



    if (count(verifierCompte($db, $tmpDencryptId)) == 1) {
        $infoUtilisateur = verifierCompte($db, $tmpDencryptId);
        $token = $tmpEncryptId . "/" . $tmpEncryptDateCreation;


        if ($infoUtilisateur['0']->token_activation == $token) {


            if ($infoUtilisateur['0']->etat == 1) {

                $resultat = 2;

            } else {
                date_default_timezone_set('Africa/Dakar');
                $dateNow = new DateTime("");
                $dateNow = $dateNow->format('Y-m-d H:i:s');
                $tmpDateNow = date('Y-m-d H:i:s', strtotime('+24 hour', strtotime($tmpDencryptDateCreation)));


                if ($dateNow < $tmpDateNow) {


                    $data_up_user = [
                        'id' => $tmpDencryptId,
                        'etat' => 1,
                        'token_activation' => NULL
                    ];


                    $sql_up_user = "UPDATE utilisateurs SET token_activation=:token_activation,etat=:etat WHERE id_utilisateur=:id";
                    $stmt_up_user = $db->prepare($sql_up_user);
                    $tmp_stmt_up_user = $stmt_up_user->execute($data_up_user);


                    if ($tmp_stmt_up_user) {
                        $resultat = 3;

                    } else {
                        $resultat = 4;

                    }


                } else {
                    $resultat = 1;

                }
            }

        } else {
            $resultat = 0;

        }



    } else {
        echo "papa";
        die;
        // header('Location:/erreur');
        // die;
    }

} else {


    header('Location:/404');
    die;
}



?>

<!DOCTYPE html>

<html lang="fr">

<head>
    <title>UAHB | Sama hampaté</title>
    <meta name="description" content="UAHB | Sama hampaté" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta charset="utf-8" />
    <meta property="og:locale" content="fr_FR" />
    <meta property="og:title" content="UAHB | Sama hampaté" />
    <meta property="og:site_name" content="UAHB | Sama hampaté" />
    <link rel="shortcut icon" href="/ressources/assets_auth/media/logos/1.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />

    <link href="/ressources/assets_auth/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="/ressources/assets_auth/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <link href="/ressources/assets_auth/css/style.css" rel="stylesheet" type="text/css" />
</head>

<body id="kt_body" class="bg-body bg" style="background-image: url(/ressources/assets_auth/media/bg/3.png)">
    <div class="d-flex flex-column flex-root">
        <div class="d-flex flex-column flex-lg-row flex-column-fluid">

            <div class="d-flex flex-column flex-column-fluid bgi-position-y-bottom position-x-center bgi-no-repeat bgi-size-contain bgi-attachment-fixed"
                style="background-image: url(assets/media/illustrations/dozzy-1/14.png)">
                <div class="d-flex flex-center flex-column flex-column-fluid p-10 pb-lg-20">
                    <a href="../../demo3/dist/index.html" class="mb-12">
                        <img alt="Logo" src="/ressources/assets_auth/media/logos/1.png" class="h-100px" />
                    </a>
                    <div class="w-lg-500px bg-body rounded shadow-sm p-10 p-lg-15 mx-auto">
                        <?php if ($resultat == 0) { ?>
                            <div class="alert alert-danger d-flex align-items-center p-5">
                                <span class="svg-icon svg-icon-2hx svg-icon-danger me-3"><svg
                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none">
                                        <path opacity="0.3"
                                            d="M12 22C13.6569 22 15 20.6569 15 19C15 17.3431 13.6569 16 12 16C10.3431 16 9 17.3431 9 19C9 20.6569 10.3431 22 12 22Z"
                                            fill="black" />
                                        <path
                                            d="M19 15V18C19 18.6 18.6 19 18 19H6C5.4 19 5 18.6 5 18V15C6.1 15 7 14.1 7 13V10C7 7.6 8.7 5.6 11 5.1V3C11 2.4 11.4 2 12 2C12.6 2 13 2.4 13 3V5.1C15.3 5.6 17 7.6 17 10V13C17 14.1 17.9 15 19 15ZM11 10C11 9.4 11.4 9 12 9C12.6 9 13 8.6 13 8C13 7.4 12.6 7 12 7C10.3 7 9 8.3 9 10C9 10.6 9.4 11 10 11C10.6 11 11 10.6 11 10Z"
                                            fill="black" />
                                    </svg> </span>
                                <div class="d-flex flex-column">
                                    <h4 class="mb-1 text-dark">Activation de votre compte</h4>
                                    <span class="text-danger">Le lien n'est pas valide.</span>
                                </div>
                            </div>
                            <div class="text-center">
                                <a type="submit" href="/sama-admission" class="btn btn-lg btn-primary w-100 mb-5">
                                    <span class="indicator-label">Retour à l'accueil</span>
                                </a>
                            </div> <?php } else if ($resultat == 1) { ?>
                                <div class="d-flex flex-column">
                                    <h4 class="mb-1 text-dark">Activation de votre compte</h4>
                                    <!-- <span class="text-danger">Le lien a expiré.</span> -->
                                </div>
                                <form class="form w-100" novalidate="novalidate" id="resend_form" autocomplete="off">
                                    <input type="hidden" name="numPreinscription" id="numPreinscription"
                                        value="<?php echo $numPreinscription; ?>">
                                    <input type="hidden" name="tmp" id="tmp" value="<?php echo $tmpDencryptId; ?>">

                                    <div class="text-center mb-10">
                                        <input type="hidden" name="option" value="8">
                                        <h1 class="text-danger mb-3">Le lien a expiré.</h1>
                                    </div>
                                    <div class="mb-10 fv-row" data-kt-password-meter="true">
                                        <div class="mb-1">
                                            <label class="form-label fw-bolder text-dark fs-6">Email</label>
                                            <div class="position-relative mb-3">
                                                <input class="form-control form-control-lg form-control-solid" type="email"
                                                    placeholder="" name="email" autocomplete="off" />

                                            </div>
                                        </div>

                                    </div>

                                    <div class="text-center">
                                        <button type="button" id="btnResendActivation" class="btn btn-lg btn-primary fw-bolder">
                                            <span class="indicator-label">Renvoyer l'e-mail</span>
                                            <span class="indicator-progress">Veuillez patienter...
                                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                                        </button>
                                    </div>
                                </form>
                        <?php } else if ($resultat == 2) { ?>
                                    <div class="alert alert-danger d-flex align-items-center p-5">
                                        <span class="svg-icon svg-icon-2hx svg-icon-danger me-3"><svg
                                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                fill="none">
                                                <path opacity="0.3"
                                                    d="M12 22C13.6569 22 15 20.6569 15 19C15 17.3431 13.6569 16 12 16C10.3431 16 9 17.3431 9 19C9 20.6569 10.3431 22 12 22Z"
                                                    fill="black" />
                                                <path
                                                    d="M19 15V18C19 18.6 18.6 19 18 19H6C5.4 19 5 18.6 5 18V15C6.1 15 7 14.1 7 13V10C7 7.6 8.7 5.6 11 5.1V3C11 2.4 11.4 2 12 2C12.6 2 13 2.4 13 3V5.1C15.3 5.6 17 7.6 17 10V13C17 14.1 17.9 15 19 15ZM11 10C11 9.4 11.4 9 12 9C12.6 9 13 8.6 13 8C13 7.4 12.6 7 12 7C10.3 7 9 8.3 9 10C9 10.6 9.4 11 10 11C10.6 11 11 10.6 11 10Z"
                                                    fill="black" />
                                            </svg> </span>
                                        <div class="d-flex flex-column">
                                            <h4 class="mb-1 text-dark">Activation de votre compte</h4>
                                            <span>Votre compte est déjà activé. Veuillez vous connecter directement.</span>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <a type="submit" href="/mim-page-de-connexion"
                                            class="btn btn-lg btn-primary w-100 mb-5">
                                            <span class="indicator-label">se connecter</span>
                                        </a>

                                    </div>
                        <?php } else if ($resultat == 3) { ?>
                                        <div class="alert alert-success d-flex align-items-center p-5">
                                            <span class="svg-icon svg-icon-2hx svg-icon-success me-3"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                    fill="none">
                                                    <path opacity="0.3"
                                                        d="M12 22C13.6569 22 15 20.6569 15 19C15 17.3431 13.6569 16 12 16C10.3431 16 9 17.3431 9 19C9 20.6569 10.3431 22 12 22Z"
                                                        fill="black" />
                                                    <path
                                                        d="M19 15V18C19 18.6 18.6 19 18 19H6C5.4 19 5 18.6 5 18V15C6.1 15 7 14.1 7 13V10C7 7.6 8.7 5.6 11 5.1V3C11 2.4 11.4 2 12 2C12.6 2 13 2.4 13 3V5.1C15.3 5.6 17 7.6 17 10V13C17 14.1 17.9 15 19 15ZM11 10C11 9.4 11.4 9 12 9C12.6 9 13 8.6 13 8C13 7.4 12.6 7 12 7C10.3 7 9 8.3 9 10C9 10.6 9.4 11 10 11C10.6 11 11 10.6 11 10Z"
                                                        fill="black" />
                                                </svg> </span>
                                            <div class="d-flex flex-column">
                                                <h4 class="mb-1 text-dark">Activation de votre compte</h4>
                                                <span>Félicitations, votre compte a été activé avec succès. Vous pouvez maintenant vous
                                                    connecter.</span>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <a type="submit" href="/mim-page-de-connexion"
                                                class="btn btn-lg btn-primary w-100 mb-5">
                                                <span class="indicator-label">se connecter</span>
                                            </a>

                                        </div>
                        <?php } else { ?>
                                        <div class="alert alert-danger d-flex align-items-center p-5">
                                            <span class="svg-icon svg-icon-2hx svg-icon-danger me-3"><svg
                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                    fill="none">
                                                    <path opacity="0.3"
                                                        d="M12 22C13.6569 22 15 20.6569 15 19C15 17.3431 13.6569 16 12 16C10.3431 16 9 17.3431 9 19C9 20.6569 10.3431 22 12 22Z"
                                                        fill="black" />
                                                    <path
                                                        d="M19 15V18C19 18.6 18.6 19 18 19H6C5.4 19 5 18.6 5 18V15C6.1 15 7 14.1 7 13V10C7 7.6 8.7 5.6 11 5.1V3C11 2.4 11.4 2 12 2C12.6 2 13 2.4 13 3V5.1C15.3 5.6 17 7.6 17 10V13C17 14.1 17.9 15 19 15ZM11 10C11 9.4 11.4 9 12 9C12.6 9 13 8.6 13 8C13 7.4 12.6 7 12 7C10.3 7 9 8.3 9 10C9 10.6 9.4 11 10 11C10.6 11 11 10.6 11 10Z"
                                                        fill="black" />
                                                </svg> </span>
                                            <div class="d-flex flex-column">
                                                <h4 class="mb-1 text-dark">Activation de votre compte</h4>
                                                <span class="text-danger">Une erreur est survenue. Veuillez réessayer
                                                    ultérieurement.</span>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <a type="submit" href="/sama-admission" class="btn btn-lg btn-primary w-100 mb-5">
                                                <span class="indicator-label">Retour à l'accueil</span>
                                            </a>
                                        </div>

                        <?php } ?>

                    </div>
                </div>
                <div class="d-flex flex-center flex-column-auto p-10">
                    <div class="d-flex align-items-center fw-bold fs-6">
                        <a href="/sama-admission" class="text-mued text-hover-primary px-2">Acceuil</a>
                        <a href="mailto:contact@uahb.sn" class="text-mued text-hover-primary px-2">Contact</a>
                        <a href="https://www.uahb.sn/" target="_blank" class="text-mued text-hover-primary px-2">Site
                            web uahb</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/ressources/assets_auth/plugins/global/plugins.bundle.js"></script>
    <script src="/ressources/assets_auth/js/scripts.bundle.js"></script>
    <script src="/scripts.bundle.14.js"></script>
</body>

</html>