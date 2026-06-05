<?php

session_start();


$tmpIdP = null;
$tmpPrenom = null;
$tmpNom = null;
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

if (
    !isset($_SESSION['tmpIdP']) ||
    !isset($_SESSION['tmpPrenom']) ||
    !isset($_SESSION['tmpNom']) ||
    !isset($_SESSION['tmpInitiales']) ||
    !isset($_SESSION['tmpNbrAppli']) ||
    !isset($_SESSION['tmpNbrAppliEnAttente']) ||
    !isset($_SESSION['tmpNbrAppliAutorisees']) ||
    !isset($_SESSION['tmpNbrAppliRefusees']) ||
    !isset($_SESSION['connectUser']) ||
    !isset($_SESSION['tmpListeApplication']) ||
    !isset($_SESSION['tmpEntite']) ||
    !isset($_SESSION['listeTachesStructures']) ||
    !isset($_SESSION['listeTachesIncarnes']) ||
    !isset($_SESSION['listeTachesParDefaut'])
) {

    session_unset();
    session_destroy();

    header("Location: /personnel/signin");
    exit();
}else
{


    $connectUser = $_SESSION['connectUser'] ?? null;

    if($connectUser != 2)
    {
        session_unset();
        session_destroy();

        header("Location: /personnel/signin");
        exit();

    }
    $tmpIdP = $_SESSION['tmpIdP'] ?? null;
    $tmpPrenom = $_SESSION['tmpPrenom'] ?? null;
    $tmpNom = $_SESSION['tmpNom'] ?? null;
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




}





