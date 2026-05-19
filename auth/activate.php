<?php
try {
    include_once('../bd.php');

    $bd  = new BD();
    $bd  = $bd ->connect();

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
    if(!empty($_GET['mat']) && !empty($_GET['code']))
    {


        $now = new DateTime();
        $date_jour = $now->format('Y-m-d H:i:s');

        $matricule = tokendecrypt($_GET['mat']);
        $codeActivation_encrypt = $_GET['code'];

        $data = [
                'matricule' => $matricule
        ];

        $sql = "SELECT * FROM utilisateurs WHERE matricule=:matricule";
        $stmt = $bd ->prepare($sql);
        $stmt->execute($data);
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        if($result)
        {

            if($result->codeActivation == $codeActivation_encrypt)
            {

                $email = $result->email;
                $jourCreation = dateFranc($result->dateCreation);
                $tempsExpire = "24 h";

                if($result->statutActivation == 1)
                {

                    $statut = 1;

                }else
                {

                    if(comparerDate($result->dateEnvoiCodeValidation))
                    {







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


                                    if (comparerDate($finContrat)) {


                                        if ($result->codeActivation == $codeActivation_encrypt) {

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
                                                    $bd->rollBack();
                                                    $matricule = null;
                                                    $statut = 12340;
                                                }


                                            } else {
                                                $bd->rollBack();
                                                $matricule = null;
                                                $statut = 1230;
                                            }


                                        } else {
                                            $bd->rollBack();
                                            $matricule = null;
                                            $statut = 1111110;
                                        }


                                    } else {

                                        $bd->rollBack();
                                        $matricule = null;
                                        $statut = 111110;

                                    }


                                } else {
                                    $bd->rollBack();
                                    $matricule = null;
                                    $statut = 111110;;
                                }


                            } else {
                                $bd->rollBack();
                                $matricule = null;
                                $statut = 11110;

                            }




//$statut = 3;
                    }else
                    {
                        $statut = 2;

                    }

                }
            }else
            {

                $matricule = null;
                $statut = 1110;

            }


        }else
        {
            $matricule = null;
            $statut = 110;
        }

    }else
    {

        $matricule = null;
        $statut = 10;


    }



}catch (Exception $e) {


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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>

    <link href="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />

    <!--    <link href="/personnel/ressources/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css" />-->

    <link rel="stylesheet" type="text/css" href="/personnel/ressources/dist_assets/css/activate.css">

</head>
<body>
<div class="page-shell">
    <input type="hidden" name="matricule" id="matricule" value="<?=  $matricule ?>"/>
    <aside class="sidebar">
        <div class="slide active" style="background-image:url('/personnel/ressources/dist_assets/media/misc/uahb-mobile.jpg')"></div>
        <div class="slide"        style="background-image:url('/personnel/ressources/dist_assets/media/misc/cmjlf-mobile.jpg')"></div>
        <div class="slide"        style="background-image:url('/personnel/ressources/dist_assets/media/misc/ctd-mobile.jpg')"></div>
        <div class="sidebar-overlay"></div>
        <div class="sidebar-orb"></div>
        <div class="sb-inner">
            <div><a href="/personnel/accueil" class="sb-logo">
                    <img src="/personnel/ressources/dist_assets/media/logos/logo_gsjlf.png" alt="GSJLF" class="sb-logo-img">
                    <div><div class="sb-name">Groupe Scolaire Jean de la Fontaine</div><div class="sb-sub">Environnement Numérique de Travail</div></div>
                </a></div>
            <div class="sb-mid">
                <div class="sb-kicker"><span class="material-symbols-outlined" style="font-size:13px">verified</span>Activation de compte</div>
                <h2 class="sb-headline">Activez votre<br><span>accès ENT.</span></h2>
                <p class="sb-desc">Confirmez votre identité avec le code reçu par email pour activer définitivement votre espace numérique.</p>
                <div class="sb-stats">
                    <div class="sb-stat"><div class="sb-stat-num">6</div><div class="sb-stat-lbl">Chiffres</div></div>
                    <div class="sb-stat"><div class="sb-stat-num">15min</div><div class="sb-stat-lbl">Validité</div></div>
                    <div class="sb-stat"><div class="sb-stat-num">1x</div><div class="sb-stat-lbl">Usage</div></div>
                </div>
                <div class="slide-ent">
                    <img id="ent-logo" src="logo_uahb.png" alt="">
                    <div class="slide-ent-info"><span id="ent-name">UAHB</span><small id="ent-desc">Université Amadou Hampâté Bâ</small></div>
                </div>
                <div class="slide-dots" id="slide-dots"></div>
            </div>
            <div class="sb-bottom">
                <div class="sb-secure"><span class="material-symbols-outlined" style="font-size:13px">verified_user</span>Vérification sécurisée</div>
                <span>© 2026 GSJLF</span>
            </div>
        </div>
    </aside>

    <main class="main-wrap">
        <div class="auth-card">
            <a href="/personnel/signup" class="back-link"><span class="material-symbols-outlined" style="font-size:15px">arrow_back</span>Retour à l'inscription</a>

            <?php

            if($statut == 0)
            {
                ?>

                <div id="success-state" class="success-banner">
                    <div class="error-icon">
    <span class="material-symbols-outlined" style="font-size:34px">
        error
    </span>
                    </div>                    <h3 style="color: red;">Erreur !</h3>
                    <p> Une erreur s’est produite. Veuillez réessayer ou contacter le service informatique à l’adresse <strong><em style="color: red;">criat@uahb.sn</em></strong>.</p>
                    <!--                    <a href="/personnel/signin" class="submit-btn" style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px">Se connecter maintenant</a>-->
                </div>


            <?php }else if($statut == 1)
            {?>

                <div id="success-state" class="success-banner">
                    <div class="success-icon"><span class="material-symbols-outlined" style="font-size:34px">verified</span></div>
                    <h3>Compte déjà activé !</h3>
                    <p>Votre compte est déjà actif. Veuillez vous connecter pour accéder à votre espace personnel.</p>
                    <a href="/personnel/signin" class="submit-btn" style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px">Se connecter maintenant</a>
                </div>


            <?php }else if($statut == 2)
            {
                ?>


                <div id="success-state" class="success-banner">
                    <div class="warning-icon">
    <span class="material-symbols-outlined" style="font-size:34px">
        warning
    </span>
                    </div>                    <h3 style="color: #e0a800;">Échec de l'activation !</h3>
                    <p>                             Le lien a expiré. Demandez un nouveau lien et réessayez.
                    </p>
                    <button class="warning-btn" style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px" id="btnResetActivation" onclick="resetActivation()"><span class="material-symbols-outlined" style="font-size:13px;vertical-align:middle">refresh</span> Renvoyer le lien</button>
                </div>

            <?php }else if($statut == 3)
            {?>

                <div id="success-state" class="success-banner">
                    <div class="success-icon"><span class="material-symbols-outlined" style="font-size:34px">verified</span></div>
                    <h3>Compte activé !</h3>
                    <p>Votre compte est actif. Veuillez vous connecter pour accéder à votre espace personnel.</p>
                    <a href="/personnel/signin" class="submit-btn" style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px">Se connecter maintenant</a>
                </div>


            <?php }else {
                ?>

                <div id="success-state" class="success-banner">
                    <div class="error-icon">
    <span class="material-symbols-outlined" style="font-size:34px">
        error
    </span>
                    </div>                    <h3 style="color: red;">Erreur !</h3>
                    <p> Une erreur s’est produite. Veuillez réessayer ou contacter le service informatique à l’adresse <strong><em style="color: red;">criat@uahb.sn</em></strong>.</p>
                    <!--                    <a href="/personnel/signin" class="submit-btn" style="margin-top:20px;text-decoration:none;width:auto;padding:0 28px">Se connecter maintenant</a>-->
                </div>


            <?php }
            ?>



        </div>
    </main>
</div>

<script src="/personnel/ressources/dist_assets/plugins/global/plugins.bundle.js"></script>
<script src="/personnel/ressources/dist_assets/js/scripts.bundle.js"></script>

<script src="/personnel/scripts.bundle.3.js"></script>
<script>
    const SLIDES=[{logo:'/personnel/ressources/dist_assets/media/logos/logo_uahb.png',name:'UAHB',desc:'Université Amadou Hampâté Bâ'},{logo:'/personnel/ressources/dist_assets/media/logos/logo_cmjlf.png',name:'CMJLF',desc:'Collège Moderne Jean de la Fontaine'},{logo:'/personnel/ressources/dist_assets/media/logos/logo_ctd.png',name:'CTD',desc:'Collège Technique de Dakar'}];
    let cur=0,timer=null;
    const slides=document.querySelectorAll('.slide');
    const dotsEl=document.getElementById('slide-dots');
    SLIDES.forEach((_,i)=>{const d=document.createElement('div');d.className='slide-dot'+(i===0?' active':'');d.style.width=i===0?'28px':'8px';d.addEventListener('click',()=>goSlide(i));dotsEl.appendChild(d);});
    function getDots(){return dotsEl.querySelectorAll('.slide-dot')}
    function goSlide(n,restart=true){slides[cur].classList.remove('active');getDots()[cur].classList.remove('active');getDots()[cur].style.width='8px';cur=(n+SLIDES.length)%SLIDES.length;slides[cur].classList.add('active');getDots()[cur].classList.add('active');getDots()[cur].style.width='28px';const el=document.getElementById('ent-logo'),en=document.getElementById('ent-name'),ed=document.getElementById('ent-desc');[el,en,ed].forEach(x=>{x.style.transition='opacity .3s';x.style.opacity='0';});setTimeout(()=>{el.src=SLIDES[cur].logo;en.textContent=SLIDES[cur].name;ed.textContent=SLIDES[cur].desc;[el,en,ed].forEach(x=>x.style.opacity='1');},300);if(restart){clearInterval(timer);timer=setInterval(()=>goSlide(cur+1,false),5000);}}
    timer=setInterval(()=>goSlide(cur+1,false),5000);

    let timerInt=null,seconds=899,codeSent=false;
    const codeInputs=document.querySelectorAll('.code-grid input');
    codeInputs.forEach((inp,i)=>{inp.addEventListener('input',()=>{inp.value=inp.value.replace(/\D/g,'').slice(0,1);if(inp.value&&codeInputs[i+1])codeInputs[i+1].focus();if([...codeInputs].every(x=>x.value))verifyCode();});inp.addEventListener('keydown',e=>{if(e.key==='Backspace'&&!inp.value&&codeInputs[i-1])codeInputs[i-1].focus();});});
    function startTimer(){timerInt=setInterval(()=>{seconds--;const m=Math.floor(seconds/60).toString().padStart(2,'0'),s=(seconds%60).toString().padStart(2,'0');document.getElementById('timer-display').textContent=m+':'+s;if(seconds<=0){clearInterval(timerInt);document.getElementById('resend-btn').style.display='inline-flex';const tp=document.getElementById('timer-pill');tp.style.background='rgba(229,57,53,.1)';tp.style.color='#e53935';}},1000);}
    function handleActivate(){const email=document.getElementById('email-input').value;if(!email){document.getElementById('email-input').focus();return;}const btn=document.getElementById('main-btn');if(!codeSent){btn.innerHTML='<span class="material-symbols-outlined" style="font-size:18px;animation:spin 1s linear infinite">progress_activity</span> Envoi…';btn.disabled=true;setTimeout(()=>{codeSent=true;document.getElementById('code-section').style.display='block';document.getElementById('resend-btn').style.display='inline-flex';btn.innerHTML='<span class="material-symbols-outlined" style="font-size:18px">verified</span> Activer le compte';btn.disabled=false;startTimer();codeInputs[0].focus();},1200);}else verifyCode(true);}
    function verifyCode(manual=false){const code=[...codeInputs].map(x=>x.value).join('');if(code.length===6||manual){const btn=document.getElementById('main-btn');btn.innerHTML='<span class="material-symbols-outlined" style="font-size:18px;animation:spin 1s linear infinite">progress_activity</span> Vérification…';btn.disabled=true;setTimeout(()=>{clearInterval(timerInt);document.getElementById('main-form').style.display='none';document.getElementById('success-state').style.display='flex';},1200);}}
    function resend(){seconds=899;clearInterval(timerInt);startTimer();codeInputs.forEach(x=>x.value='');codeInputs[0].focus();const tp=document.getElementById('timer-pill');tp.style.background='';tp.style.color='';}
</script>
</body>
</html>
