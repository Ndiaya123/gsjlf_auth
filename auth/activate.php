<?php

include_once('../sessions/commun.php');


?>

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
        $secretKey     = 'U@hbENTDRI@TCRI@T2022';
        $secretIv      = 'www.ent.uahb.sn';
        $encryptMethod = "AES-256-CBC";
        $key           = hash('sha256', $secretKey);
        $iv            = substr(hash('sha256', $secretIv), 0, 16);
        return openssl_decrypt(base64_decode($data), $encryptMethod, $key, 0, $iv);
    }

    function valid_donnees($donnees)
    {
        return htmlspecialchars(stripslashes(trim($donnees)));
    }

    function dateFranc($date)
    {
        try {
            $datetime  = new DateTime($date);
            $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
            return $formatter->format($datetime);
        } catch (Exception $e) {
            return null;
        }
    }

    /* Lien d'activation valable 24 h */
    function comparerDate($date)
    {
        try {
            $dateParam = new DateTime($date);
            $dateParam->modify('+24 hours');
            return $dateParam > new DateTime();
        } catch (Exception $e) {
            return false;
        }
    }

    /* Fin de contrat dans le futur */
    function comparerDateContrat($date)
    {
        try {
            return (new DateTime($date)) > new DateTime();
        } catch (Exception $e) {
            return false;
        }
    }

    if (!empty($_GET['mat']) && !empty($_GET['code'])) {

        $matricule              = tokendecrypt($_GET['mat']);
        $codeActivation_encrypt = $_GET['code'];

        $stmt = $bd->prepare("SELECT * FROM utilisateurs WHERE matricule=:matricule");
        $stmt->execute(['matricule' => $matricule]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        if ($result) {

            if ($result->codeActivation == $codeActivation_encrypt) {

                if ($result->statutActivation == 1) {

                    $statut = 1; // déjà activé

                } else {

                    if (comparerDate($result->dateEnvoiCodeValidation)) {

                        /* Personnel */
                        $stmt_perso = $bd->prepare("SELECT p.identifiant, p.idEtatCivil, ec.prenom, ec.nom, cg.email
                            FROM personnels p
                            INNER JOIN etatCivil ec ON p.idEtatCivil = ec.id
                            LEFT JOIN compteGmail cg ON p.idCompteGmail = cg.id
                            WHERE p.matricule = :matricule");
                        $stmt_perso->execute(['matricule' => $matricule]);
                        $result_perso = $stmt_perso->fetch(PDO::FETCH_OBJ);

                        if ($result_perso) {

                            $identifiant = $result_perso->identifiant;
                            $idEtatCivil = $result_perso->idEtatCivil;

                            /* Contrat actif */
                            $stmt_contrat = $bd->prepare("SELECT * FROM contrat WHERE matricule=:matricule AND idTypeStatutContrat=:idTypeStatutContrat");
                            $stmt_contrat->execute(['matricule' => $matricule, 'idTypeStatutContrat' => 1]);
                            $result_contrat = $stmt_contrat->fetch(PDO::FETCH_OBJ);

                            if ($result_contrat && comparerDateContrat($result_contrat->dateFinContrat)) {

                                $dateActivation = (new DateTime())->format('Y-m-d H:i:s');

                                $stmt_upd = $bd->prepare("UPDATE utilisateurs SET statutActivation=:statutActivation, dateActivation=:dateActivation WHERE matricule=:matricule");
                                $ok = $stmt_upd->execute(['matricule' => $matricule, 'statutActivation' => 1, 'dateActivation' => $dateActivation]);

                                if ($ok) {

                                    $dateEnreg = (new DateTime())->format('Y-m-d H:i:s');
                                    $stmt_histo = $bd->prepare("INSERT INTO auth_personnel_historiques(identifiant,matricule,tableHistorique,motif,idEtatCivil,dateEnregistremenent) VALUES (:identifiant,:matricule,:tableHistorique,:motif,:idEtatCivil,:dateEnregistremenent)");
                                    $ok_histo = $stmt_histo->execute([
                                            'identifiant'          => $identifiant,
                                            'matricule'            => $matricule,
                                            'tableHistorique'      => 'utilisateurs',
                                            'motif'                => 'Activation du compte',
                                            'idEtatCivil'          => $idEtatCivil,
                                            'dateEnregistremenent' => $dateEnreg,
                                    ]);

                                    if ($ok_histo) {
                                        $bd->commit();
                                        $statut = 3; // succès
                                    } else {
                                        $bd->rollBack(); $matricule = null; $statut = 0;
                                    }

                                } else {
                                    $bd->rollBack(); $matricule = null; $statut = 0;
                                }

                            } else {
                                $bd->rollBack(); $matricule = null; $statut = 0; // contrat invalide
                            }

                        } else {
                            $bd->rollBack(); $matricule = null; $statut = 0; // personnel introuvable
                        }

                    } else {
                        $statut = 2; // lien expiré
                    }
                }

            } else {
                $statut = 2; // mauvais code → lien invalide
            }

        } else {
            $matricule = null; $statut = 0; // utilisateur introuvable
        }

    } else {
        $matricule = null; $statut = 0; // paramètres manquants
    }

} catch (Exception $e) {
    header("Location: /personnel/erreur");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>GSJLF — Activation de compte</title>
    <link rel="shortcut icon" href="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css"/>
    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" type="text/css" href="/personnel/ressources/dist_assets/css/activate.css">
</head>
<body>
<div class="ps_page-shell">
    <input type="hidden" name="matricule" id="matricule" value="<?= $matricule ?>"/>

    <aside class="ps_sidebar">
        <div class="ps_slide ps_active" style="background-image:url('/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg')"></div>
        <div class="ps_slide"           style="background-image:url('/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg')"></div>
        <div class="ps_slide"           style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>
        <div class="ps_sidebar-overlay"></div>
        <div class="ps_sidebar-orb"></div>
        <div class="ps_sb-inner">
            <div>
                <a href="/personnel/accueil" class="ps_sb-logo">
                    <img src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" alt="GSJLF" class="ps_sb-logo-img">
                    <div>
                        <div class="ps_sb-name">Groupe Scolaire Jean de la Fontaine</div>
                        <div class="ps_sb-sub">Environnement Numérique de Travail</div>
                    </div>
                </a>
            </div>
            <div class="ps_sb-mid">
                <div class="ps_sb-kicker"><span class="material-symbols-outlined" style="font-size:13px">verified</span>Activation de compte</div>
                <h2 class="ps_sb-headline">Activez votre<br><span>accès ENT.</span></h2>
                <p class="ps_sb-desc">Confirmez votre identité pour activer définitivement votre espace numérique.</p>
                <div class="ps_sb-stats">
                    <div class="ps_sb-stat"><div class="ps_sb-stat-num">6</div><div class="ps_sb-stat-lbl">Chiffres</div></div>
                    <div class="ps_sb-stat"><div class="ps_sb-stat-num">24 h</div><div class="ps_sb-stat-lbl">Validité</div></div>
                    <div class="ps_sb-stat"><div class="ps_sb-stat-num">100%</div><div class="ps_sb-stat-lbl">Sécurisé</div></div>
                </div>
                <div class="ps_slide-ent">
                    <img id="ent-logo" src="/personnel/ressources/dist_assets/media/logos/logo_uahb.png" alt="">
                    <div class="ps_slide-ent-info">
                        <span id="ent-name">UAHB</span>
                        <small id="ent-desc">Université Amadou Hampâté Bâ</small>
                    </div>
                </div>
                <div class="ps_slide-dots" id="slide-dots"></div>
            </div>
            <div class="ps_sb-bottom">
                <div class="ps_sb-secure"><span class="material-symbols-outlined" style="font-size:13px">verified_user</span>Vérification sécurisée</div>
                <span>© <span id="annee_en_cours1"></span> GSJLF</span>
            </div>
        </div>
    </aside>

    <main class="ps_main-wrap">
        <div class="ps_auth-card">
            <a href="/personnel/signup" class="ps_back-link">
                <span class="material-symbols-outlined" style="font-size:15px">arrow_back</span>Retour à l'inscription
            </a>

            <?php if ($statut == 1) { ?>

                <!-- Compte déjà activé -->
                <div class="ps_success-banner">
                    <div class="ps_success-icon"><span class="material-symbols-outlined" style="font-size:34px">verified</span></div>
                    <h3>Compte déjà activé !</h3>
                    <p>Votre compte est déjà actif. Connectez-vous pour accéder à votre espace personnel.</p>
                    <a href="/personnel/signin" class="ps_submit-btn" style="margin-top:20px;width:auto;padding:0 28px">Se connecter maintenant</a>
                </div>

            <?php } else if ($statut == 2) { ?>

                <!-- Lien expiré -->
                <div id="success-state" class="ps_success-banner">
                    <div class="ps_warning-icon"><span class="material-symbols-outlined" style="font-size:34px">warning</span></div>
                    <h3 style="color:#e0a800">Lien expiré !</h3>
                    <p>Ce lien d'activation a expiré (24 heures). Demandez un nouveau lien et réessayez.</p>
                    <button class="ps_warning-btn" id="btnResetActivation" onclick="resetActivation()">
                        <span class="material-symbols-outlined" style="font-size:18px">refresh</span>Renvoyer le lien
                    </button>
                </div>

                <!-- Email renvoyé avec succès -->
                <div id="success-state-mail" class="ps_success-banner" style="display:none">
                    <div class="ps_success-icon"><span class="material-symbols-outlined" style="font-size:34px">mark_email_read</span></div>
                    <h3>Email renvoyé !</h3>
                    <p>Un e-mail d'activation vous a été renvoyé sur votre adresse institutionnelle. Vérifiez également vos spams, puis cliquez sur le lien pour activer votre compte.</p>
                </div>

            <?php } else if ($statut == 3) { ?>

                <!-- Activation réussie -->
                <div class="ps_success-banner">
                    <div class="ps_success-icon"><span class="material-symbols-outlined" style="font-size:34px">verified</span></div>
                    <h3>Compte activé !</h3>
                    <p>Votre compte est maintenant actif. Connectez-vous pour accéder à votre espace personnel.</p>
                    <a href="/personnel/signin" class="ps_submit-btn" style="margin-top:20px;width:auto;padding:0 28px">Se connecter maintenant</a>
                </div>

            <?php } else { ?>

                <!-- Erreur générique -->
                <div class="ps_error-banner">
                    <div class="ps_error-icon"><span class="material-symbols-outlined" style="font-size:34px">error</span></div>
                    <h3 style="color:#dc3545">Erreur !</h3>
                    <p>Une erreur s'est produite. Veuillez réessayer ou contacter le service informatique à <strong><em style="color:#dc3545">criat@uahb.sn</em></strong>.</p>
                </div>

            <?php } ?>

        </div>
    </main>
</div>

<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>
<script src="/personnel/scripts.bundle.3.js"></script>
</body>
</html>