<?php

session_start();


$tmpIdP = null;
$tmpMatricule = null;
$tmpPrenom = null;
$tmpNom = null;
$tmpPhoto = null;
$tmpEmail = null;
$tmpInitiales = null;
$tmpNbrAppli = null;
$tmpNbrAppliEnAttente = null;
$tmpNbrAppliAutorisees = null;
$tmpNbrAppliRefusees = null;
$connectUser = null;
$tmpId = null;
$tmpIdBASI = null;
$tmpListeApplication = null;
$tmpEntite = null;

$listeTachesStructures = null;
$listeTachesIncarnes = null;
$listeTachesParDefaut = null;
$statutPoste = null;




if (
    !isset($_SESSION['tmpIdP']) ||
!isset($_SESSION['tmpMatricule']) ||
    !isset($_SESSION['tmpPrenom']) ||
    !isset($_SESSION['tmpNom']) ||
    !isset($_SESSION['tmpEmail']) ||
    !isset($_SESSION['tmpPhoto']) ||
    !isset($_SESSION['tmpInitiales']) ||
    !isset($_SESSION['tmpNbrAppli']) ||
    !isset($_SESSION['tmpNbrAppliEnAttente']) ||
    !isset($_SESSION['tmpNbrAppliAutorisees']) ||
    !isset($_SESSION['tmpNbrAppliRefusees']) ||
    !isset($_SESSION['connectUserGSJLF_ENT']) ||
    !isset($_SESSION['tmpListeApplication']) ||
    !isset($_SESSION['tmpEntite']) ||
    !isset($_SESSION['listeTachesStructures']) ||
    !isset($_SESSION['listeTachesIncarnes']) ||
    !isset($_SESSION['listeTachesParDefaut']) ) {

    session_unset();
    session_destroy();

    header("Location: /personnel/signin");
    exit();
}else
{



    $connectUser = $_SESSION['connectUserGSJLF_ENT'] ?? null;

    if($connectUser != 2)
    {
        session_unset();
        session_destroy();

        header("Location: /personnel/signin");
        exit();

    }
    $tmpIdP = $_SESSION['tmpIdP'] ?? null;
    $tmpMatricule = $_SESSION['tmpMatricule'] ?? null;
    $tmpPrenom = !empty($_SESSION['tmpPrenom'])
        ? ucwords(strtolower($_SESSION['tmpPrenom']))
        : null;

    $tmpNom = !empty($_SESSION['tmpNom'])
        ? strtoupper($_SESSION['tmpNom'])
        : null;
    $tmpEmail = $_SESSION['tmpEmail'] ?? null;
    $tmpPhoto = $_SESSION['tmpPhoto'] ?? null;
    $tmpInitiales = $_SESSION['tmpInitiales'] ?? null;
    $tmpNbrAppli = $_SESSION['tmpNbrAppli'] ?? null;
    $tmpNbrAppliEnAttente = $_SESSION['tmpNbrAppliEnAttente'] ?? null;
    $tmpNbrAppliAutorisees = $_SESSION['tmpNbrAppliAutorisees'] ?? null;
    $tmpNbrAppliRefusees = $_SESSION['tmpNbrAppliRefusees'] ?? null;
    $tmpId = $_SESSION['tmpId'] ?? null;
    $tmpIdBASI = $_SESSION['tmpIdBASI'] ?? null;
    $tmpListeApplication = $_SESSION['tmpListeApplication'] ?? null;
    $tmpEntite = $_SESSION['tmpEntite'];

    $listeTachesStructures = $_SESSION['listeTachesStructures'];
    $listeTachesIncarnes = $_SESSION['listeTachesIncarnes'];
    $listeTachesParDefaut = $_SESSION['listeTachesParDefaut'];
    $statutPoste = $_SESSION['statutPoste'] ?? null;








}





