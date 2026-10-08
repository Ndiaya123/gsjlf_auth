<?php
/**
 * inventaire-pdf-vierge.php
 * Script autonome (comme bon_pap_pdf.php) : aucune requête AJAX. Génère un
 * PDF vierge (Catégorie / Sous-catégorie / Produit / Quantité à renseigner)
 * pour la saisie terrain — accessible au Comptable (idStatut 1 ou 2) et à
 * l'Opérateur (même document).
 *
 * Design : sobre et élégant, quasi monochrome, sans aplats colorés.
 *
 * Gabarit institutionnel (FPDI, comme bon_pap_pdf.php) :
 *   - Page 1 : TEMPLATE_PREMIERE_PAGE (gsjlf_finance.pdf)
 *   - Pages suivantes : TEMPLATE_PAGES_SUIVANTES (gsjlf_template_finance.pdf)
 *
 * Paramètre GET : token (chiffré de inventaire.id)
 */


ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);

ob_start();
session_start();
$sessionOk = !empty($_SESSION['tmpIdBASI']) && !empty($_SESSION['tmpMatricule']);
if (!$sessionOk) {
    header('Location: /signin'); // ← ajuster selon la vraie route de connexion
    die;
}

include_once('../../bdBASI.php'); // ← même profondeur que bon_pap_pdf.php
ob_end_clean();

$BDBASI = new BDBASI();
$bdBASI = $BDBASI->connect();
if (!$bdBASI) {
    http_response_code(500);
    die('Connexion base de données impossible.');
}

function tokendecryptInvPdf($data) {
    $key = hash('sha256', 'U@hbENTDRI@TCRI@T2022');
    $iv  = substr(hash('sha256', 'www.ent.uahb.sn'), 0, 16);
    return openssl_decrypt(base64_decode(strtr($data, '-_.', '+/=')), "AES-256-CBC", $key, 0, $iv);
}

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '') { http_response_code(400); die('Token manquant.'); }
$idI = (int) tokendecryptInvPdf($token);
if ($idI <= 0) { http_response_code(400); die('Token invalide.'); }

require('../../includes/fpdf/fpdf.php');
require('../../includes/fpdf/PDF_MC_Table.php');

// define('TEMPLATE_PREMIERE_PAGE','../../includes/fpdf/template/gsjlf_finance.pdf');
// define('TEMPLATE_PAGES_SUIVANTES','../../includes/fpdf/template/gsjlf_template_finance.pdf');

define('TEMPLATE_PREMIERE_PAGE','../../includes/fpdf/template/gsjlf_template_2026_1.pdf');
define('TEMPLATE_PAGES_SUIVANTES','../../includes/fpdf/template/gsjlf_template_2026_1.pdf');



define('ENCRE',       [31, 41, 55]);
define('ENCRE_DOUCE',  [107, 114, 128]);
define('ACCENT',      [15, 76, 58]);
define('FILET',       [223, 226, 230]);
define('FILET_FONCE', [180, 186, 192]);
define('ARGENT',      [214, 219, 224]);  // fond argenté discret de l'en-tête tableau

function decodeFpdfInv($s) {
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding((string) $s, 'ISO-8859-1', 'UTF-8');
    }
    if (function_exists('iconv')) {
        return iconv('UTF-8', 'ISO-8859-1//IGNORE', (string) $s);
    }
    return (string) $s;
}

date_default_timezone_set('Africa/Dakar');

$stmtI = $bdBASI->prepare("SELECT id, reference, idStatut, dateDebut FROM inventaire WHERE id = ? AND idStatut IN (1, 2) LIMIT 1");
$stmtI->execute([$idI]);
$inventaire = $stmtI->fetch(PDO::FETCH_ASSOC);
if (!$inventaire) { http_response_code(404); die('Inventaire introuvable ou non éligible.'); }

$stmtLignes = $bdBASI->prepare("
    SELECT p.nomproduit, sc.nom_sous_categorie, c.nom_categorie
    FROM inventaire_produit ip
    JOIN product p ON ip.idP = p.idP
    JOIN souscategorie sc ON p.id_Sous_categorie = sc.id
    JOIN categorie c ON sc.categorie_id = c.id
    WHERE ip.idI = ?
    ORDER BY c.nom_categorie ASC, sc.nom_sous_categorie ASC, p.nomproduit ASC
");
$stmtLignes->execute([$idI]);
$lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

/* ═══════════════════════════════════════════════════════════════════════════
   CLASSE PDF — sobre, sur gabarit institutionnel (page 1 vs suivantes)
═══════════════════════════════════════════════════════════════════════════ */
class PdfInventaireVierge extends PDF_MC_Table
{
    public string $reference = '';
    public string $dateDebut = '';

    function Header()
    {
        $template = ($this->PageNo() === 1) ? TEMPLATE_PREMIERE_PAGE : TEMPLATE_PAGES_SUIVANTES;
        $this->setSourceFile($template);
        $tplIdx = $this->importPage(1);
        $this->useTemplate($tplIdx, 0, 0, 210, 297);

        if ($this->PageNo() === 1) {
            [$r, $g, $b] = ACCENT;
            $this->SetTextColor($r, $g, $b);
            $this->SetFont('Helvetica', 'B', 17);
            $this->SetXY(10, 50);
            $this->Cell(190, 9, decodeFpdfInv("Fiche d'inventaire"), 0, 1, 'L');
            [$r, $g, $b] = ENCRE_DOUCE;
            $this->SetTextColor($r, $g, $b);
            $this->SetFont('Helvetica', '', 9.5);
            $this->SetX(10);
            $this->Cell(190, 5, decodeFpdfInv('Feuille vierge - saisie terrain'), 0, 1, 'L');

            [$r, $g, $b] = FILET_FONCE;
            $this->SetDrawColor($r, $g, $b);
            $this->SetLineWidth(0.5);
            $this->Line(10, 65, 200, 65);

            $y = 71;
            [$r, $g, $b] = ENCRE_DOUCE;
            $this->SetTextColor($r, $g, $b);
            $this->SetFont('Helvetica', 'B', 6.8);
            $this->SetXY(10, $y);
            $this->Cell(90, 3.5, decodeFpdfInv('RÉFÉRENCE'), 0, 1);
            $this->SetXY(105, $y);
            $this->Cell(90, 3.5, decodeFpdfInv('DATE DE DÉBUT'), 0, 1);
            [$r, $g, $b] = ENCRE;
            $this->SetTextColor($r, $g, $b);
            $this->SetFont('Helvetica', '', 9.5);
            $this->SetXY(10, $y + 3.8);
            $this->Cell(90, 5, decodeFpdfInv($this->reference), 0, 0);
            $this->SetXY(105, $y + 3.8);
            $this->Cell(90, 5, decodeFpdfInv($this->dateDebut), 0, 0);

            $this->SetY(90);
        } else {
            [$r, $g, $b] = ENCRE_DOUCE;
            $this->SetTextColor($r, $g, $b);
            $this->SetFont('Helvetica', 'I', 8.5);
            $this->SetXY(10, 44);
            $this->Cell(190, 5, decodeFpdfInv($this->reference . ' — suite'), 0, 1, 'R');
            $this->SetY(50);
        }

        [$r, $g, $b] = ARGENT;
        $this->SetFillColor($r, $g, $b);
        $this->Rect(10, $this->GetY(), 190, 8, 'F');

        [$r, $g, $b] = ENCRE;
        $this->SetTextColor($r, $g, $b);
        $this->SetFont('Helvetica', 'B', 7.3);
        $this->SetX(10);
        $this->Cell(50, 8, decodeFpdfInv('CATÉGORIE'), 0, 0, 'L');
        $this->Cell(50, 8, decodeFpdfInv('SOUS-CATÉGORIE'), 0, 0, 'L');
        $this->Cell(60, 8, decodeFpdfInv('PRODUIT'), 0, 0, 'L');
        $this->Cell(30, 8, decodeFpdfInv('QUANTITÉ'), 0, 1, 'C');

        [$r, $g, $b] = FILET_FONCE;
        $this->SetDrawColor($r, $g, $b);
        $this->SetLineWidth(0.4);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(1);
    }

    function Footer()
    {
        $this->SetY(-15);
        [$r, $g, $b] = FILET;
        $this->SetDrawColor($r, $g, $b);
        $this->SetLineWidth(0.2);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        [$r, $g, $b] = ENCRE_DOUCE;
        $this->SetTextColor($r, $g, $b);
        $this->SetFont('Helvetica', 'I', 7.5);
        $this->SetXY(10, -16);
        $this->Cell(0, 8, decodeFpdfInv($this->reference), 0, 0, 'L');
        $this->SetX(170);
        $this->Cell(30, 8, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
        $this->AliasNbPages();
    }
}

$pdf = new PdfInventaireVierge();
$pdf->reference = $inventaire['reference'];
$pdf->dateDebut = date('d/m/Y H:i', strtotime($inventaire['dateDebut']));
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->SetFont('Helvetica', '', 8.5);
foreach ($lignes as $l) {
    if ($pdf->GetY() > 265) { $pdf->AddPage(); }

    [$r, $g, $b] = ENCRE;
    $pdf->SetTextColor($r, $g, $b);
    $pdf->SetX(10);
    $pdf->Cell(50, 8, decodeFpdfInv(substr($l['nom_categorie'], 0, 32)), 0, 0, 'L');
    $pdf->Cell(50, 8, decodeFpdfInv(substr($l['nom_sous_categorie'], 0, 32)), 0, 0, 'L');
    $pdf->Cell(60, 8, decodeFpdfInv(substr($l['nomproduit'], 0, 38)), 0, 0, 'L');
    $pdf->Cell(30, 8, '', 0, 1, 'C');

    [$r, $g, $b] = FILET;
    $pdf->SetDrawColor($r, $g, $b);
    $pdf->SetLineWidth(0.15);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
}

if (empty($lignes)) {
    [$r, $g, $b] = ENCRE_DOUCE;
    $pdf->SetTextColor($r, $g, $b);
    $pdf->SetFont('Helvetica', 'I', 9.5);
    $pdf->SetX(10);
    $pdf->Cell(190, 12, decodeFpdfInv('Aucun produit dans cet inventaire.'), 0, 1, 'C');
}

$pdf->Output('I', 'inventaire_vierge_' . $inventaire['reference'] . '.pdf');