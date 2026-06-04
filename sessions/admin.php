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
    !isset($_SESSION['tmpId']) ||
    !isset($_SESSION['tmpIdBASI']) ||
    !isset($_SESSION['tmpListeApplication']) ||
    !isset($_SESSION['tmpEntite'])
) {
    session_unset();
    session_destroy();

    header("Location: /personnel/signin");
    exit();
}else
{

    $tmpIdP = $_SESSION['tmpIdP'] ?? null;
    $tmpPrenom = $_SESSION['tmpPrenom'] ?? null;
    $tmpNom = $_SESSION['tmpNom'] ?? null;
    $tmpInitiales = $_SESSION['tmpInitiales'] ?? null;
    $tmpNbrAppli = $_SESSION['tmpNbrAppli'] ?? null;
    $tmpNbrAppliEnAttente = $_SESSION['tmpNbrAppliEnAttente'] ?? null;
    $tmpNbrAppliAutorisees = $_SESSION['tmpNbrAppliAutorisees'] ?? null;
    $tmpNbrAppliRefusees = $_SESSION['tmpNbrAppliRefusees'] ?? null;
    $connectUser = $_SESSION['connectUser'] ?? null;
    $tmpId = $_SESSION['tmpId'] ?? null;
    $tmpIdBASI = $_SESSION['tmpIdBASI'] ?? null;
    $tmpListeApplication = $_SESSION['tmpListeApplication'] ?? null;
    $tmpEntite = $_SESSION['tmpEntite'];


}



