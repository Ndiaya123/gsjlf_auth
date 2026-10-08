<?php
/**
 * inventaire-rapport-pdf.php
 * Script autonome (comme bon_pap_pdf.php) : génère le rapport PDF final
 * d'un inventaire Terminé (idStatut = 4) — quantités système / opérateur /
 * validée + constat par ligne, et les dates clés de l'inventaire.
 *
 * Design : sobre et élégant, quasi monochrome (encre + un seul accent
 * discret pour le titre et les filets), sans aplats colorés.
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

// ─── Session ────────────────────────────────────────────────────────────────
ob_start();
session_start();
$sessionOk = !empty($_SESSION['tmpIdBASI']) && !empty($_SESSION['tmpMatricule']);
if (!$sessionOk) {
    header('Location: /personnel/signin'); // ← ajuster selon la vraie route de connexion
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

function tokendecryptInvRapport($data) {
    $key = hash('sha256', 'U@hbENTDRI@TCRI@T2022');
    $iv  = substr(hash('sha256', 'www.ent.uahb.sn'), 0, 16);
    return openssl_decrypt(base64_decode(strtr($data, '-_.', '+/=')), "AES-256-CBC", $key, 0, $iv);
}

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '') { http_response_code(400); die('Token manquant.'); }
$idI = (int) tokendecryptInvRapport($token);
if ($idI <= 0) { http_response_code(400); die('Token invalide.'); }

require('../../includes/fpdf/fpdf.php');
require('../../includes/fpdf/PDF_MC_Table.php');

// define('TEMPLATE_PREMIERE_PAGE',   __DIR__ . '/../../includes/fpdf/template/gsjlf_template.pdf');
// define('TEMPLATE_PAGES_SUIVANTES', __DIR__ . '/../../includes/fpdf/template/gsjlf_template.pdf');

define('TEMPLATE_PREMIERE_PAGE',   __DIR__ . '/../../includes/fpdf/template/gsjlf_template_2026_1.pdf');
define('TEMPLATE_PAGES_SUIVANTES', __DIR__ . '/../../includes/fpdf/template/gsjlf_template_2026_1.pdf');



// ── Palette sobre : encre + un seul accent, pas d'aplats de couleur ────────
define('ENCRE',      [31, 41, 55]);     // texte principal, quasi noir
define('ENCRE_DOUCE', [107, 114, 128]); // texte secondaire / labels
define('ACCENT',     [15, 76, 58]);     // vert institutionnel, usage minimal
define('FILET',      [223, 226, 230]);  // lignes fines
define('FILET_FONCE',[180, 186, 192]);  // filet double sous l'en-tête tableau
define('ARGENT',     [214, 219, 224]);  // fond argenté discret de l'en-tête tableau
define('CONFORME_C', [55, 118, 90]);    // vert éteint
define('EXCEDENT_C', [163, 118, 45]);   // ocre éteint
define('DEFICIT_C',  [163, 60, 51]);    // brique éteint

function decodeFpdfInvR($s) {
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding((string) $s, 'ISO-8859-1', 'UTF-8');
    }
    if (function_exists('iconv')) {
        return iconv('UTF-8', 'ISO-8859-1//IGNORE', (string) $s);
    }
    return (string) $s;
}

function constatLigneR(float $systeme, ?float $valide): array {
    if ($valide === null) return ['—', ENCRE_DOUCE];
    if (abs($systeme - $valide) < 0.001) return ['Conforme', CONFORME_C];
    if ($valide > $systeme) return ['Excédent', EXCEDENT_C];
    return ['Déficitaire', DEFICIT_C];
}

date_default_timezone_set('Africa/Dakar');

$stmtI = $bdBASI->prepare("
    SELECT i.id, i.reference, i.idStatut, i.dateDebut, i.dateSoumission, i.dateFin, i.dateEnregistrement,
           i.observation_operateur, i.observation_comptable,
           CONCAT(u.prenom, ' ', u.nom) AS createur
    FROM inventaire i
    JOIN utilisateurs u ON i.idUtilisateur = u.id
    WHERE i.id = ? AND i.idStatut = 4
    LIMIT 1
");
$stmtI->execute([$idI]);
$inventaire = $stmtI->fetch(PDO::FETCH_ASSOC);
if (!$inventaire) { http_response_code(404); die('Inventaire introuvable ou non terminé.'); }

$stmtLignes = $bdBASI->prepare("
    SELECT p.nomproduit, sc.nom_sous_categorie, c.nom_categorie,
           ip.quantite_systeme, ip.quantite_operateur, ip.quantite_valide
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
class PdfInventaireRapport extends PDF_MC_Table
{
    public string $reference = '';
    public string $createur  = '';
    public string $dateCreation   = '';
    public string $dateDebut      = '';
    public string $dateSoumission = '';
    public string $dateFin        = '';
    public string $observationOperateur = '';
    public string $observationComptable = '';

    function Header()
    {
        $template = ($this->PageNo() === 1) ? TEMPLATE_PREMIERE_PAGE : TEMPLATE_PAGES_SUIVANTES;
        $this->setSourceFile($template);
        $tplIdx = $this->importPage(1);
        $this->useTemplate($tplIdx, 0, 0, 210, 297);

        if ($this->PageNo() === 1) {
            // Titre — texte seul, pas d'aplat, filet fin en dessous.
            [$r, $g, $b] = ACCENT;
            $this->SetTextColor($r, $g, $b);
            $this->SetFont('Helvetica', 'B', 17);
            $this->SetXY(10, 50);
            $this->Cell(190, 9, decodeFpdfInvR("Rapport d'inventaire"), 0, 1, 'L');

            [$r, $g, $b] = FILET_FONCE;
            $this->SetDrawColor($r, $g, $b);
            $this->SetLineWidth(0.5);
            $this->Line(10, 61, 200, 61);

            // Bloc d'identification — grille sobre, labels petites capitales.
            $y = 68;
            $this->blocChampR('RÉFÉRENCE', $this->reference, 10, $y);
            $this->blocChampR('CRÉÉ PAR', $this->createur, 105, $y);
            $y += 11;
            $this->blocChampR('DATE DE CRÉATION', $this->dateCreation, 10, $y);
            $this->blocChampR('DATE DE DÉBUT', $this->dateDebut, 105, $y);
            $y += 11;
            $this->blocChampR('SOUMISSION OPÉRATEUR', $this->dateSoumission, 10, $y);
            $this->blocChampR('VALIDATION COMPTABLE', $this->dateFin, 105, $y);
            $y += 12;

            if ($this->observationOperateur !== '' || $this->observationComptable !== '') {
                [$r, $g, $b] = FILET;
                $this->SetDrawColor($r, $g, $b);
                $this->SetLineWidth(0.2);
                $this->Line(10, $y, 200, $y);
                $y += 4;

                if ($this->observationOperateur !== '') {
                    [$r, $g, $b] = ENCRE_DOUCE;
                    $this->SetTextColor($r, $g, $b);
                    $this->SetFont('Helvetica', 'B', 7);
                    $this->SetXY(10, $y);
                    $this->Cell(190, 3.5, decodeFpdfInvR('OBSERVATION OPÉRATEUR'), 0, 1);
                    [$r, $g, $b] = ENCRE;
                    $this->SetTextColor($r, $g, $b);
                    $this->SetFont('Helvetica', '', 8.5);
                    $this->SetX(10);
                    $this->MultiCell(190, 4.2, decodeFpdfInvR($this->observationOperateur));
                    $y = $this->GetY() + 2;
                }
                if ($this->observationComptable !== '') {
                    [$r, $g, $b] = ENCRE_DOUCE;
                    $this->SetTextColor($r, $g, $b);
                    $this->SetFont('Helvetica', 'B', 7);
                    $this->SetXY(10, $y);
                    $this->Cell(190, 3.5, decodeFpdfInvR('OBSERVATION COMPTABLE'), 0, 1);
                    [$r, $g, $b] = ENCRE;
                    $this->SetTextColor($r, $g, $b);
                    $this->SetFont('Helvetica', '', 8.5);
                    $this->SetX(10);
                    $this->MultiCell(190, 4.2, decodeFpdfInvR($this->observationComptable));
                    $y = $this->GetY() + 2;
                }
            }

            $this->SetY(max($y + 3, 100));
        } else {
            [$r, $g, $b] = ENCRE_DOUCE;
            $this->SetTextColor($r, $g, $b);
            $this->SetFont('Helvetica', 'I', 8.5);
            $this->SetXY(10, 44);
            $this->Cell(190, 5, decodeFpdfInvR($this->reference . ' — suite'), 0, 1, 'R');
            $this->SetY(50);
        }

        $this->enteteTableau();
    }

    /** Petit bloc "label / valeur" sobre, sans fond. */
    function blocChampR(string $label, string $valeur, float $x, float $y): void
    {
        [$r, $g, $b] = ENCRE_DOUCE;
        $this->SetTextColor($r, $g, $b);
        $this->SetFont('Helvetica', 'B', 6.8);
        $this->SetXY($x, $y);
        $this->Cell(90, 3.5, decodeFpdfInvR($label), 0, 1);
        [$r, $g, $b] = ENCRE;
        $this->SetTextColor($r, $g, $b);
        $this->SetFont('Helvetica', '', 9.5);
        $this->SetXY($x, $y + 3.8);
        $this->Cell(90, 5, decodeFpdfInvR($valeur), 0, 0);
    }

    /** En-tête de tableau sobre : texte + double filet, aucun aplat. */
    function enteteTableau(): void
    {
        [$r, $g, $b] = ARGENT;
        $this->SetFillColor($r, $g, $b);
        $this->Rect(10, $this->GetY(), 190, 8, 'F');

        [$r, $g, $b] = ENCRE;
        $this->SetTextColor($r, $g, $b);
        $this->SetFont('Helvetica', 'B', 7.3);
        $this->SetX(10);
        $this->Cell(35, 8, decodeFpdfInvR('CATÉGORIE'), 0, 0, 'L');
        $this->Cell(35, 8, decodeFpdfInvR('SOUS-CATÉGORIE'), 0, 0, 'L');
        $this->Cell(45, 8, decodeFpdfInvR('PRODUIT'), 0, 0, 'L');
        $this->Cell(20, 8, decodeFpdfInvR('SYSTÈME'), 0, 0, 'R');
        $this->Cell(20, 8, decodeFpdfInvR('OPÉRATEUR'), 0, 0, 'R');
        $this->Cell(15, 8, decodeFpdfInvR('VALIDÉE'), 0, 0, 'R');
        $this->Cell(20, 8, decodeFpdfInvR('CONSTAT'), 0, 1, 'R');

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
        $this->SetXY(10, -15);
        $this->Cell(0, 8, decodeFpdfInvR($this->reference), 0, 0, 'L');
        $this->SetX(170);
        $this->Cell(30, 8, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
        $this->AliasNbPages();
    }
}

$fmtDate = function ($d) { return $d ? date('d/m/Y H:i', strtotime($d)) : '—'; };

$pdf = new PdfInventaireRapport();
$pdf->reference             = $inventaire['reference'];
$pdf->createur              = $inventaire['createur'];
$pdf->dateCreation          = $fmtDate($inventaire['dateEnregistrement']);
$pdf->dateDebut             = $fmtDate($inventaire['dateDebut']);
$pdf->dateSoumission        = $fmtDate($inventaire['dateSoumission']);
$pdf->dateFin               = $fmtDate($inventaire['dateFin']);
$pdf->observationOperateur  = (string) ($inventaire['observation_operateur'] ?? '');
$pdf->observationComptable  = (string) ($inventaire['observation_comptable'] ?? '');
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->SetFont('Helvetica', '', 8);
$i = 0;
foreach ($lignes as $l) {
    if ($pdf->GetY() > 262) { $pdf->AddPage(); }

    [$r, $g, $b] = ENCRE;
    $pdf->SetTextColor($r, $g, $b);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetX(10);

    $systeme = (float) $l['quantite_systeme'];
    $valide  = $l['quantite_valide'] !== null ? (float) $l['quantite_valide'] : null;
    [$libelleConstat, $couleurConstat] = constatLigneR($systeme, $valide);

    $pdf->Cell(35, 7.5, decodeFpdfInvR(substr($l['nom_categorie'], 0, 24)), 0, 0, 'L');
    $pdf->Cell(35, 7.5, decodeFpdfInvR(substr($l['nom_sous_categorie'], 0, 24)), 0, 0, 'L');
    $pdf->Cell(45, 7.5, decodeFpdfInvR(substr($l['nomproduit'], 0, 30)), 0, 0, 'L');
    $pdf->Cell(20, 7.5, (string) $systeme, 0, 0, 'R');
    $pdf->Cell(20, 7.5, $l['quantite_operateur'] !== null ? (string) $l['quantite_operateur'] : '—', 0, 0, 'R');
    $pdf->Cell(15, 7.5, $valide !== null ? (string) $valide : '—', 0, 0, 'R');

    [$r, $g, $b] = $couleurConstat;
    $pdf->SetTextColor($r, $g, $b);
    $pdf->SetFont('Helvetica', 'B', 7.6);
    $pdf->Cell(20, 7.5, decodeFpdfInvR($libelleConstat), 0, 1, 'R');

    [$r, $g, $b] = FILET;
    $pdf->SetDrawColor($r, $g, $b);
    $pdf->SetLineWidth(0.15);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());

    $i++;
}

if (empty($lignes)) {
    [$r, $g, $b] = ENCRE_DOUCE;
    $pdf->SetTextColor($r, $g, $b);
    $pdf->SetFont('Helvetica', 'I', 9.5);
    $pdf->SetX(10);
    $pdf->Cell(190, 12, decodeFpdfInvR('Aucun produit dans cet inventaire.'), 0, 1, 'C');
}

$pdf->Output('I', 'inventaire_rapport_' . $inventaire['reference'] . '.pdf');