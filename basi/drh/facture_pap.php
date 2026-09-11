<?php
/**
 * bon_pap_pdf.php
 * Route : /personnel/bon_pap_pdf
 *
 * Script autonome (session → BDD → FPDF → sortie), générant le PDF du bon
 * de commande (achat) ou de la facture de paiement (paiement) pour une
 * opération donnée, disponible pour idStatut = 4 (Acceptée), 6 (En
 * paiement / Livrée) ou 7 (Terminée).
 *
 * Paramètre GET : token (chiffré de passer_achat_et_paiement.id)
 *
 * ⚠️ À vérifier :
 *   - PROFORMA_TEMPLATE_PDF : même gabarit institutionnel que les autres
 *     scripts PDF de l'application.
 *   - Chemins de bdBASI.php / fpdf.php / PDF_MC_Table.php : 2 niveaux,
 *     comme dfc-demande-facture-proforma.php.
 */

// ─── Session ────────────────────────────────────────────────────────────────
ob_start();
session_start();

$sessionOk = !empty($_SESSION['tmpIdBASI']) && !empty($_SESSION['tmpIdDirection']) && !empty($_SESSION['tmpMatricule']);
if (!$sessionOk) {
    header('Location: /personnel/signin'); // ← ajuster selon la vraie route de connexion
    die;
}

// ─── Connexion BDD ──────────────────────────────────────────────────────────
include_once('../../bdBASI.php'); // ← même profondeur que dfc-demande-facture-proforma.php

$BDBASI = new BDBASI();
$bdBASI = $BDBASI->connect();
if (!$bdBASI) {
    http_response_code(500);
    die('Connexion base de données impossible.');
}

// ─── Déchiffrement du token (même clé que drhController::tokendecrypt) ─────
function tokendecryptPdf($data) {
    $key = hash('sha256', 'U@hbENTDRI@TCRI@T2022');
    $iv  = substr(hash('sha256', 'www.ent.uahb.sn'), 0, 16);
    return openssl_decrypt(base64_decode(strtr($data, '-_.', '+/=')), "AES-256-CBC", $key, 0, $iv);
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if ($token === '') {
    http_response_code(400);
    die('Token manquant.');
}
$idPAP = (int) tokendecryptPdf($token);
if ($idPAP <= 0) {
    http_response_code(400);
    die('Token invalide.');
}

// ─── FPDF (+ FPDI via PDF_MC_Table) ─────────────────────────────────────────
require('../../includes/fpdf/fpdf.php');
require('../../includes/fpdf/PDF_MC_Table.php');

define('PROFORMA_TEMPLATE_PDF', __DIR__ . '/../../includes/fpdf/template/entete_gsjlf.pdf');

define('COULEUR_VERT_FONCE',  [6, 78, 59]);
define('COULEUR_VERT',        [26, 122, 94]);
define('COULEUR_VERT_CLAIR',  [240, 253, 244]);
define('COULEUR_TEXTE',       [55, 65, 81]);
define('COULEUR_TEXTE_DOUX',  [107, 114, 128]);

//function decodeFpdf($s) {
//    if (function_exists('mb_convert_encoding')) {
//        return mb_convert_encoding((string)$s, 'ISO-8859-1', 'UTF-8');
//    }
//    if (function_exists('iconv')) {
//        return iconv('UTF-8', 'ISO-8859-1//IGNORE', (string)$s);
//    }
//    return (string)$s;
//}

function decodeFpdf($value): string
{
    $value = (string) $value;

    // Déjà vide
    if ($value === '') {
        return '';
    }

    // FPDF classique utilise généralement ISO-8859-1
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
    }

    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);

        if ($converted !== false) {
            return $converted;
        }
    }

    // Dernier recours : retourner le texte original
    return $value;
}


function formatMontantPdf($n): string {
    if ($n === null || $n === '') return '—';
    return number_format((float)$n, 0, ',', ' ') . ' FCFA';
}

/**
 * Récupère toutes les données nécessaires au document : en-tête de
 * l'opération, acheteur, fournisseur retenu (achat), lignes (prix réel pour
 * l'achat, montant réel pour le paiement), et conditions de règlement /
 * modalités de paiement (+ tranches si modalité = 3).
 */
function recupererDonneesBon(PDO $bdBASI, int $idPAP): ?array {
    $stmt = $bdBASI->prepare("
        SELECT
            p.id AS idPAP, p.nom_commande, p.montant_total, p.idTypePAP, p.idStatut,
            p.nb_tranche,
            mr.mode_reglement AS mode_reglement_nom,
            mp.mode_paiement  AS mode_paiement_nom,
            CONCAT(u.prenom, ' ', u.nom) AS acheteur
        FROM passer_achat_et_paiement p
        LEFT JOIN mode_reglement mr ON p.id_mode_reglement = mr.id
        LEFT JOIN mode_paiement  mp ON p.id_mode_paiement  = mp.id
        LEFT JOIN utilisateurs   u  ON p.idUtilisateur     = u.id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$idPAP]);
    $entete = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entete) return null;

    $estAchat = ((int)$entete['idTypePAP'] === 1);

    // Fournisseur retenu (achat uniquement — le paiement n'a pas de notion
    // de fournisseur dans ce module).
    $nomFournisseur = '—';
    if ($estAchat) {
        $stmtF = $bdBASI->prepare("
            SELECT f.nomF, f.prenomF, f.entreprise
            FROM documents_pap dp
            JOIN fournisseur f ON dp.id_fournisseur = f.idF
            WHERE dp.idPAP = ? AND dp.choix = 1
            LIMIT 1
        ");
        $stmtF->execute([$idPAP]);
        $f = $stmtF->fetch(PDO::FETCH_ASSOC);
        if ($f) {
            $nomFournisseur = trim(($f['prenomF'] ?? '') . ' ' . ($f['nomF'] ?? ''))
                . ($f['entreprise'] ? ' — ' . $f['entreprise'] : '');
        }
    }

    // Lignes : prix réel (achat) ou montant réel (paiement)
    $stmtL = $bdBASI->prepare("
        SELECT lb.designation, papl.quantite_reelle, papl.prix_reel, papl.montant_total_ligne
        FROM passer_achat_et_paiement_ligne papl
        JOIN demandes_ligne dal ON papl.idDL = dal.idDL
        JOIN ligneBudget    lb  ON dal.idLB  = lb.id
        WHERE papl.idPAP = ? AND papl.id_statut_PAPL = 1
        ORDER BY papl.id ASC
    ");
    $stmtL->execute([$idPAP]);
    $lignes = $stmtL->fetchAll(PDO::FETCH_ASSOC);

    // Tranches (si modalité = 3)
    $tranches = [];
    if ((int)$entete['nb_tranche'] > 0) {
        $stmtT = $bdBASI->prepare("SELECT ordre, pourcentage FROM tranches WHERE idPAP = ? AND statut = 1 ORDER BY ordre ASC");
        $stmtT->execute([$idPAP]);
        $tranches = $stmtT->fetchAll(PDO::FETCH_ASSOC);
    }

    return [
        'entete'          => $entete,
        'estAchat'        => $estAchat,
        'nomFournisseur'  => $nomFournisseur,
        'lignes'          => $lignes,
        'tranches'        => $tranches,
    ];
}

/* ═══════════════════════════════════════════════════════════════════════════
   CLASSE PDF — mise en page sur gabarit institutionnel
═══════════════════════════════════════════════════════════════════════════ */

class PdfBonPap extends PDF_MC_Table
{
    public string $titre         = '';
    public string $numero        = '';
    public string $nomCommande   = '';
    public string $acheteur      = '';
    public string $fournisseur   = '';
    public string $dateDoc       = '';
    public bool   $estAchat      = true;

    function Header()
    {
        $this->setSourceFile(PROFORMA_TEMPLATE_PDF);
        $tplIdx = $this->importPage(1);
        $this->useTemplate($tplIdx, 0, 0, 210, 297);

        if ($this->PageNo() === 1) {
            [$r, $g, $b] = COULEUR_VERT_FONCE;
            $this->SetFillColor($r, $g, $b);
            $this->RoundedRect(10, 48, 190, 13, 2.5, 'F');
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Helvetica', 'B', 13);
            $this->SetXY(10, 48);
            $this->Cell(190, 13, decodeFpdf($this->titre), 0, 0, 'C');

            [$r, $g, $b] = COULEUR_VERT_CLAIR;
            $this->SetFillColor($r, $g, $b);
            [$r, $g, $b] = COULEUR_VERT;
            $this->SetDrawColor($r, $g, $b);
            $this->SetLineWidth(0.3);
            $this->RoundedRect(10, 65, 190, 24, 2, 'FD');

            [$r, $g, $b] = COULEUR_TEXTE;
            $this->SetTextColor($r, $g, $b);

            $this->SetFont('Helvetica', 'B', 8);
            $this->SetXY(14, 68);
            $this->Cell(85, 4, decodeFpdf('N° / NOM'), 0, 2);
            $this->SetFont('Helvetica', '', 9);
            $this->SetX(14);
            $this->Cell(85, 5, decodeFpdf('#' . $this->numero . ' — ' . $this->nomCommande), 0, 0);

            $this->SetFont('Helvetica', 'B', 8);
            $this->SetXY(160, 68);
            $this->Cell(35, 4, decodeFpdf('DATE'), 0, 2);
            $this->SetFont('Helvetica', '', 9);
            $this->SetX(160);
            $this->Cell(35, 5, decodeFpdf($this->dateDoc), 0, 0);

            $this->SetFont('Helvetica', 'B', 8);
            $this->SetXY(14, 76);
            $this->Cell(85, 4, decodeFpdf('ACHETEUR'), 0, 2);
            $this->SetFont('Helvetica', '', 9);
            $this->SetX(14);
            $this->Cell(85, 5, decodeFpdf($this->acheteur), 0, 0);

            $this->SetFont('Helvetica', 'B', 8);
            $this->SetXY(110, 76);
            $this->Cell(85, 4, decodeFpdf('FOURNISSEUR'), 0, 2);
            $this->SetFont('Helvetica', '', 9);
            $this->SetX(110);
            $this->Cell(85, 5, decodeFpdf($this->fournisseur), 0, 0);

            $this->SetY(96);
        } else {
            [$r, $g, $b] = COULEUR_TEXTE_DOUX;
            $this->SetTextColor($r, $g, $b);
            $this->SetFont('Helvetica', 'I', 9);
            $this->SetXY(10, 44);
            $this->Cell(190, 5, decodeFpdf('Bon #' . $this->numero . ' (suite)'), 0, 1, 'R');
            $this->SetY(50);
        }

        [$r, $g, $b] = COULEUR_VERT_FONCE;
        $this->SetFillColor($r, $g, $b);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetX(10);
        $libelleMontant = $this->estAchat ? 'Prix reel' : 'Montant';
        $this->Cell(120, 9, decodeFpdf('Designation'), 0, 0, 'L', true);
        if ($this->estAchat) {
            $this->Cell(30, 9, decodeFpdf('Qte'), 0, 0, 'C', true);
            $this->Cell(40, 9, decodeFpdf($libelleMontant), 0, 1, 'C', true);
        } else {
            $this->Cell(70, 9, decodeFpdf($libelleMontant), 0, 1, 'C', true);
        }
    }

    // Rectangle aux coins arrondis (recette FPDF classique, domaine public)
    function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $k = $this->k; $hp = $this->h;
        $op = ($style === 'F') ? 'f' : (($style === 'FD' || $style === 'DF') ? 'B' : 'S');
        $arc = 4 / 3 * (sqrt(2) - 1);

        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_Arc($xc + $r * $arc, $yc - $r, $xc + $r, $yc - $r * $arc, $xc + $r, $yc);
        $xc = $x + $w - $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc + $r, $yc + $r * $arc, $xc + $r * $arc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_Arc($xc - $r * $arc, $yc + $r, $xc - $r, $yc + $r * $arc, $xc - $r, $yc);
        $xc = $x + $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_Arc($xc - $r, $yc - $r * $arc, $xc - $r * $arc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c ',
            $x1 * $this->k, ($h - $y1) * $this->k,
            $x2 * $this->k, ($h - $y2) * $this->k,
            $x3 * $this->k, ($h - $y3) * $this->k
        ));
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   GÉNÉRATION
═══════════════════════════════════════════════════════════════════════════ */

$donnees = recupererDonneesBon($bdBASI, $idPAP);
if (!$donnees) {
    http_response_code(404);
    die('Opération introuvable.');
}
if (!in_array((int)$donnees['entete']['idStatut'], [4, 6, 7], true)) {
    http_response_code(403);
    die("Le document n'est disponible que pour une opération Acceptée, En paiement/Livrée ou Terminée.");
}
if (!file_exists(PROFORMA_TEMPLATE_PDF)) {
    http_response_code(500);
    die('Gabarit PDF introuvable (PROFORMA_TEMPLATE_PDF à ajuster).');
}

date_default_timezone_set('Africa/Dakar');

$entete   = $donnees['entete'];
$estAchat = $donnees['estAchat'];

$pdf = new PdfBonPap();
$pdf->titre        = $estAchat ? 'BON DE COMMANDE' : 'FACTURE DE PAIEMENT';
$pdf->numero       = (string)$entete['idPAP'];
$pdf->nomCommande  = $entete['nom_commande'] ?? '';
$pdf->acheteur     = $entete['acheteur'] ?? '—';
$pdf->fournisseur  = $donnees['nomFournisseur'];
$pdf->dateDoc      = (new DateTime())->format('d/m/Y');
$pdf->estAchat     = $estAchat;
$pdf->SetAutoPageBreak(true, 30);
$pdf->AddPage();

$pdf->SetFont('Helvetica', '', 10);
$pdf->SetDrawColor(230, 230, 230);
$pdf->SetLineWidth(0.2);

$clair = false;
foreach ($donnees['lignes'] as $l) {
    [$r, $g, $b] = $clair ? COULEUR_VERT_CLAIR : [255, 255, 255];
    $pdf->SetFillColor($r, $g, $b);
    [$r, $g, $b] = COULEUR_TEXTE;
    $pdf->SetTextColor($r, $g, $b);

    $pdf->SetX(10);
    $pdf->Cell(120, 9, decodeFpdf($l['designation'] ?? ''), 0, 0, 'L', true);
    if ($estAchat) {
        $pdf->Cell(30, 9, decodeFpdf((string)($l['quantite_reelle'] ?? '—')), 0, 0, 'C', true);
        $pdf->Cell(40, 9, decodeFpdf(formatMontantPdf($l['prix_reel'] ?? null)), 0, 1, 'C', true);
    } else {
        $pdf->Cell(70, 9, decodeFpdf(formatMontantPdf($l['montant_total_ligne'] ?? null)), 0, 1, 'C', true);
    }
    $clair = !$clair;
}

// ── Montant total ───────────────────────────────────────────────────────────
[$r, $g, $b] = COULEUR_VERT_FONCE;
$pdf->SetDrawColor($r, $g, $b);
$pdf->SetLineWidth(0.5);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(3);

$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetTextColor($r, $g, $b);
$pdf->SetX(10);
$pdf->Cell(150, 8, decodeFpdf('MONTANT TOTAL'), 0, 0, 'R');
$pdf->Cell(40, 8, decodeFpdf(formatMontantPdf($entete['montant_total'])), 0, 1, 'R');

// ── Conditions de règlement + modalités de paiement (bas de page) ──────────
$pdf->Ln(10);
[$r, $g, $b] = COULEUR_VERT_CLAIR;
$pdf->SetFillColor($r, $g, $b);
[$r, $g, $b] = COULEUR_VERT;
$pdf->SetDrawColor($r, $g, $b);
$pdf->SetLineWidth(0.3);
$hauteurBloc = 20 + (count($donnees['tranches']) > 0 ? 5 * count($donnees['tranches']) : 0);
$pdf->RoundedRect(10, $pdf->GetY(), 190, $hauteurBloc, 2, 'FD');

[$r, $g, $b] = COULEUR_TEXTE;
$pdf->SetTextColor($r, $g, $b);
$yBloc = $pdf->GetY() + 3;

$pdf->SetFont('Helvetica', 'B', 8);
$pdf->SetXY(14, $yBloc);
$pdf->Cell(85, 4, decodeFpdf('CONDITIONS DE REGLEMENT'), 0, 2);
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetX(14);
$pdf->Cell(85, 5, decodeFpdf($entete['mode_reglement_nom'] ?? '—'), 0, 0);

$pdf->SetFont('Helvetica', 'B', 8);
$pdf->SetXY(110, $yBloc);
$pdf->Cell(85, 4, decodeFpdf('MODALITE DE PAIEMENT'), 0, 2);
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetX(110);
$pdf->Cell(85, 5, decodeFpdf($entete['mode_paiement_nom'] ?? '—'), 0, 0);

if (!empty($donnees['tranches'])) {
    $pdf->SetXY(14, $yBloc + 8);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->Cell(180, 4, decodeFpdf('REPARTITION DES TRANCHES'), 0, 2);
    $pdf->SetFont('Helvetica', '', 9);
    foreach ($donnees['tranches'] as $t) {
        $pdf->SetX(14);
        $pdf->Cell(180, 5, decodeFpdf('Tranche ' . $t['ordre'] . ' : ' . number_format((float)$t['pourcentage'], 2, ',', ' ') . ' %'), 0, 2);
    }
}

// ── Sortie inline dans le navigateur ────────────────────────────────────────
ob_end_clean();
$pdf->Output('I', 'bon_' . ($estAchat ? 'commande' : 'paiement') . '_' . $entete['idPAP'] . '.pdf');
exit;