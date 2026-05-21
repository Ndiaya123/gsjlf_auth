<?php
try {
    include_once('../bd.php');

    $bd = new BD();
    $bd = $bd->connect();

    date_default_timezone_set('Africa/Dakar');


    $bd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $bd->beginTransaction();

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

    function valid_donnees($donnees)
    {
        $donnees = trim($donnees);
        $donnees = stripslashes($donnees);
        $donnees = htmlspecialchars($donnees);
        return $donnees;
    }

    function dateFranc($date)
    {
        try {
            $datetime = new DateTime($date);

            $formatter = new IntlDateFormatter(
                    'fr_FR',
                    IntlDateFormatter::FULL,
                    IntlDateFormatter::NONE
            );

            return $formatter->format($datetime);

        } catch (Exception $e) {
            return null;
        }
    }


    function comparerDate($date)
    {
        try {
            $dateParam = new DateTime($date);
            $dateParam->modify('+24 hours');

            $nowPlus24h = new DateTime();

            if ($dateParam > $nowPlus24h) {
                return true;
            }

            return false;

        } catch (Exception $e) {
            return false; // ou false selon ton choix de sécurité
        }
    }

    function comparerDateContrat($date)
    {
        try {
            $dateParam = new DateTime($date);
            $nowPlus24h = new DateTime();

            if ($dateParam > $nowPlus24h) {
                return true;
            }

            return false;

        } catch (Exception $e) {
            return false; // ou false selon ton choix de sécurité
        }
    }


    if (!empty($_GET['mat']) && !empty($_GET['code'])) {


        $now = new DateTime();
        $date_jour = $now->format('Y-m-d H:i:s');

        $matricule = tokendecrypt($_GET['mat']);
        $codeActivation_encrypt = $_GET['code'];

        $data = [
                'matricule' => $matricule
        ];

        $sql = "SELECT * FROM utilisateurs WHERE matricule=:matricule";
        $stmt = $bd->prepare($sql);
        $stmt->execute($data);
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        if ($result) {

            if ($result->codeActivation == $codeActivation_encrypt) {

                $email = $result->email;

                if ($result->statutActivation == 1) {

                    $statut = 1;

                } else {

                    if (comparerDate($result->dateEnvoiCodeValidation)) {

                        date_default_timezone_set('Africa/Dakar');


                        $date_jour = date('d/m/Y H:i:s');
                        $data = [
                                'matricule' => $matricule
                        ];

                        $data_perso = [
                                'matricule' => $matricule
                        ];
                        $sql_perso = "SELECT 
    p.identifiant,
    p.idEtatCivil,
    ec.prenom,
    ec.nom,
    cg.email
FROM personnels p
INNER JOIN etatCivil ec 
    ON p.idEtatCivil = ec.id
LEFT JOIN compteGmail cg 
    ON p.idCompteGmail = cg.id
WHERE p.matricule = :matricule;";
                        $stmt_perso = $bd->prepare($sql_perso);
                        $stmt_perso->execute($data_perso);
                        $result_perso = $stmt_perso->fetch(PDO::FETCH_OBJ);

                        if ($result_perso) {


                            $identifiant = $result_perso->identifiant;
                            $idEtatCivil = $result_perso->idEtatCivil;


                            $data_perso_contrat = [
                                    'matricule' => $matricule,
                                    'idTypeStatutContrat' => 1,

                            ];
                            $sql_perso_contrat = "SELECT * FROM contrat WHERE matricule=:matricule AND idTypeStatutContrat=:idTypeStatutContrat";
                            $stmt_perso_contrat = $bd->prepare($sql_perso_contrat);
                            $stmt_perso_contrat->execute($data_perso_contrat);
                            $result_perso_contrat = $stmt_perso_contrat->fetch(PDO::FETCH_OBJ);

                            if ($result_perso_contrat) {

                                $debutContrat = $result_perso_contrat->dateDebutContrat;
                                $finContrat = $result_perso_contrat->dateFinContrat;


                                if (comparerDateContrat($finContrat)) {



                                        $dateCreation = new DateTime();
                                        $dateCreation = $dateCreation->format('Y-m-d H:i:s');


                                        $dataCandidat = [
                                                'matricule' => $matricule,
                                                'statutActivation' => 1,
                                                'dateActivation' => $dateCreation,
                                        ];

                                        $sql = "UPDATE utilisateurs 
                                                    SET
                                                        statutActivation = :statutActivation,
                                                        dateActivation = :dateActivation
                                                    WHERE matricule = :matricule";

                                        $stmt = $bd->prepare($sql);
                                        $tmpStmt = $stmt->execute($dataCandidat);

                                        if ($tmpStmt == 1) {


                                            $table = "utilisateurs";
                                            $motif = "Activation du compte";
                                            $dateEnregistrement = new DateTime();
                                            $dateEnregistrement = $dateEnregistrement->format('Y-m-d H:i:s');
                                            $dataHistorique = [
                                                    'identifiant' => $identifiant,
                                                    'matricule' => $matricule,
                                                    'tableHistorique' => $table,
                                                    'motif' => $motif,
                                                    'idEtatCivil' => $idEtatCivil,
                                                    'dateEnregistremenent' => $dateEnregistrement,
                                            ];
                                            $sqlHistorique = "INSERT INTO auth_personnel_historiques(identifiant,matricule,tableHistorique,motif,idEtatCivil,dateEnregistremenent) VALUES (:identifiant,:matricule,:tableHistorique,:motif,:idEtatCivil,:dateEnregistremenent)";
                                            $stmtHistorique = $bd->prepare($sqlHistorique);
                                            $tmpStmtHistorique = $stmtHistorique->execute($dataHistorique);

                                            if ($tmpStmtHistorique == 1) {

$bd->commit();
                                                $statut = 3;

                                            } else {
                                                if ($bd->inTransaction()) {
                                                    $bd->rollBack();
                                                }
                                                $matricule = null;
                                                $statut = 0;
                                            }


                                        } else {
                                            if ($bd->inTransaction()) {
                                                $bd->rollBack();
                                            }
                                            $matricule = null;
                                            $statut = 0;
                                        }


                                    } else {
                                        if ($bd->inTransaction()) {
                                            $bd->rollBack();
                                        }
                                        $matricule = null;
                                        $statut = 0;
                                    }


                            } else {
                                if ($bd->inTransaction()) {
                                    $bd->rollBack();
                                }
                                $matricule = null;
                                $statut = 0;
                            }


                        } else {
                            if ($bd->inTransaction()) {
                                $bd->rollBack();
                            }
                            $matricule = null;
                            $statut = 0;

                        }

                    } else {
                        $statut = 2;
                    }

                }
            } else {
                $statut = 2;

            }


        } else {
            $matricule = null;
            $statut = 0;
        }

    } else {

        $matricule = null;
        $statut = 0;

    }

} catch (Exception $e) {
    header("Location: /personnel/erreur");
    exit;
}


?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>ENT — GSJLF</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
          rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
          rel="stylesheet"/>

    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>

        <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css" />

    <link rel="stylesheet" type="text/css" href="/personnel/ressources/dist_assets/css/activate.css">

</head>
<body>
<div class="page-shell">
    <input type="hidden" name="matricule" id="matricule" value="<?= $matricule ?>"/>
    <aside class="sidebar">
        <div class="slide active"
             style="background-image:url('/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg')"></div>
        <div class="slide"
             style="background-image:url('/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg')"></div>
        <div class="slide"
             style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>
        <div class="sidebar-overlay"></div>
        <div class="sidebar-orb"></div>
        <div class="sb-inner">
            <div><a href="/personnel/accueil" class="sb-logo">
                    <img src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" alt="GSJLF"
                         class="sb-logo-img">
                    <div>
                        <div class="sb-name">Groupe Scolaire Jean de la Fontaine</div>
                        <div class="sb-sub">Environnement Numérique de Travail</div>
                    </div>
                </a></div>
            <div class="sb-mid">
                <div class="sb-kicker"><span class="material-symbols-outlined" style="font-size:13px">verified</span>Activation
                    de compte
                </div>
                <h2 class="sb-headline">Activez votre<br><span>accès ENT.</span></h2>
                <p class="sb-desc">Confirmez votre identité pour activer définitivement votre espace numérique.</p>
                <div class="sb-stats">
                    <div class="sb-stat">
                        <div class="sb-stat-num">6</div>
                        <div class="sb-stat-lbl">Chiffres</div>
                    </div>
                    <div class="sb-stat">
                        <div class="sb-stat-num">24 h</div>
                        <div class="sb-stat-lbl">Validité</div>
                    </div>
                    <div class="sb-stat">
                        <div class="sb-stat-num">100%</div>
                        <div class="sb-stat-lbl">Sécurisé</div>
                    </div>
                </div>
                <div class="slide-ent">
                    <img id="ent-logo" src="logo_uahb.png" alt="">
                    <div class="slide-ent-info"><span id="ent-name">UAHB</span><small id="ent-desc">Université Amadou
                            Hampâté Bâ</small></div>
                </div>
                <div class="slide-dots" id="slide-dots"></div>
            </div>
            <div class="sb-bottom">
                <div class="sb-secure"><span class="material-symbols-outlined"
                                             style="font-size:13px">verified_user</span>Vérification sécurisée
                </div>
                <span>© <span id="annee_en_cours1"></span> GSJLF</span>
            </div>
        </div>
    </aside>

    <main class="main-wrap">
        <div class="auth-card">
            <a href="/personnel/signup" class="back-link"><span class="material-symbols-outlined"
                                                                style="font-size:15px">arrow_back</span>Retour à
                l'inscription</a>

            <?php

            if ($statut == 0) {
                ?>

                <div id="success-state" class="success-banner">
                    <div class="error-icon">
    <span class="material-symbols-outlined" style="font-size:34px">
        error
    </span>
                    </div>
                    <h3 style="color: red;">Erreur !</h3>
                    <p> Une erreur s’est produite. Veuillez réessayer ou contacter le service informatique à l’adresse
                        <strong><em style="color: red;">criat@uahb.sn</em></strong>.</p>
                    <!--                    <a href="/personnel/signin" class="submit-btn" style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px">Se connecter maintenant</a>-->
                </div>


            <?php } else if ($statut == 1) {
                ?>

                <div id="success-state" class="success-banner">
                    <div class="success-icon"><span class="material-symbols-outlined"
                                                    style="font-size:34px">verified</span></div>
                    <h3>Compte déjà activé !</h3>
                    <p>Votre compte est déjà actif. Veuillez vous connecter pour accéder à votre espace personnel.</p>
                    <a href="/personnel/signin" class="submit-btn"
                       style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px">Se connecter
                        maintenant</a>
                </div>


            <?php } else if ($statut == 2) {
                ?>


                <div id="success-state" class="success-banner">
                    <div class="warning-icon">
    <span class="material-symbols-outlined" style="font-size:34px">
        warning
    </span>
                    </div>
                    <h3 style="color: #e0a800;">Échec de l'activation !</h3>
                    <p> Le lien a expiré. Demandez un nouveau lien et réessayez.
                    </p>
                    <button class="warning-btn" style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px"
                            id="btnResetActivation" onclick="resetActivation()"><span class="material-symbols-outlined"
                                                                                      style="font-size:13px;vertical-align:middle">refresh</span>
                        Renvoyer le lien
                    </button>
                </div>

                <div id="success-state-mail" class="success-banner" style="display:none">
                    <div class="success-icon">
                        <span class="material-symbols-outlined" style="font-size:34px">mark_email_read</span>
                    </div>
                    <h3>Compte créé !</h3>
                    <p>Un e-mail d’activation vous a été renvoyé sur votre compte mail institutionnel. Si vous ne le trouvez pas dans votre boîte de réception, veuillez vérifier votre dossier des courriers indésirables (spams), puis cliquer sur le lien pour activer votre compte.</p>
                </div>

            <?php } else if ($statut == 3) {
                ?>

                <div id="success-state" class="success-banner">
                    <div class="success-icon"><span class="material-symbols-outlined"
                                                    style="font-size:34px">verified</span></div>
                    <h3>Compte activé !</h3>
                    <p>Votre compte est actif. Veuillez vous connecter pour accéder à votre espace personnel.</p>
                    <a href="/personnel/signin" class="submit-btn"
                       style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px">Se connecter
                        maintenant</a>
                </div>


            <?php } else {
                ?>

                <div id="success-state" class="success-banner">
                    <div class="error-icon">
    <span class="material-symbols-outlined" style="font-size:34px">
        error
    </span>
                    </div>
                    <h3 style="color: red;">Erreur !</h3>
                    <p> Une erreur s’est produite. Veuillez réessayer ou contacter le service informatique à l’adresse
                        <strong><em style="color: red;">criat@uahb.sn</em></strong>.</p>
                </div>


            <?php }
            ?>


        </div>
    </main>
</div>

<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/personnel/scripts.bundle.3.js"></script>

</body>
</html>
