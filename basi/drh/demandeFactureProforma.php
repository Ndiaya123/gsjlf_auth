<?php
/**
 * dfc-demande-facture-proforma.php
 * Route : /personnel/dfc_demande_facture_proforma/
 *
 * Script autonome (session → BDD → FPDF → génération → sortie, tout en un
 * seul fichier, sans passer par drh_controller.php).
 *
 * Au chargement :
 *   1. Récupère les demandes de facture pro forma actives (statut = 1),
 *      groupées par fournisseur.
 *   2. Génère un PDF par fournisseur sur le gabarit institutionnel GSJLF
 *      (Désignation / Prix vide / Délai de validité vide), avec une mise en
 *      page soignée (bandeau titre, encart fournisseur, tableau stylé).
 *   3. Regroupe les PDF (normalement 3) dans une archive ZIP.
 *   4. Propose le ZIP en téléchargement automatique.
 *
 * ⚠️ À vérifier :
 *   - PROFORMA_TEMPLATE_PDF : placez le PDF d'en-tête GSJLF fourni à ce
 *     chemin exact (ou ajustez la constante).
 *   - Chemins de bdBASI.php / fpdf.php / PDF_MC_Table.php : repris à
 *     l'identique de votre exemple fonctionnel (2 niveaux).
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
include_once('../../bdBASI.php'); // ← même profondeur que votre exemple fonctionnel

$BDBASI = new BDBASI();
$bdBASI = $BDBASI->connect();
if (!$bdBASI) {
    http_response_code(500);
    die('Connexion base de données impossible.');
}

// ─── FPDF (+ FPDI via PDF_MC_Table, comme dans votre exemple) ──────────────
require('../../includes/fpdf/fpdf.php');
require('../../includes/fpdf/PDF_MC_Table.php');

// Gabarit institutionnel (fond de page) — placez le PDF fourni à ce chemin.
define('PROFORMA_TEMPLATE_PDF', __DIR__ . '/../../includes/fpdf/template/entete_gsjlf.pdf');

// Palette de la marque (cohérente avec le reste de l'application)
define('COULEUR_VERT_FONCE',  [6, 78, 59]);     // #064e3b
define('COULEUR_VERT',        [26, 122, 94]);   // #1a7a5e
define('COULEUR_VERT_CLAIR',  [240, 253, 244]); // #f0fdf4
define('COULEUR_TEXTE',       [55, 65, 81]);    // #374151
define('COULEUR_TEXTE_DOUX',  [107, 114, 128]); // #6b7280

/**
 * Convertit une chaîne UTF-8 en ISO-8859-1 pour FPDF, qui ne supporte pas
 * nativement l'UTF-8 avec les polices standard (Helvetica, Times, Courier).
 */
function decodeFpdf($s) {
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding((string)$s, 'ISO-8859-1', 'UTF-8');
    }
    if (function_exists('iconv')) {
        return iconv('UTF-8', 'ISO-8859-1//IGNORE', (string)$s);
    }
    return (string)$s;
}

/**
 * Nettoie une chaîne pour l'utiliser comme nom de fichier (translittère les
 * accents, remplace tout caractère non alphanumérique par "_").
 */
function nomFichierSain(string $s): string {
    $translitteré = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = $translitteré !== false ? $translitteré : $s;
    $s = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $s);
    $s = trim($s, '_');
    return $s !== '' ? $s : 'fournisseur';
}

/**
 * Récupère les demandes de facture pro forma actives (statut = 1), groupées
 * par fournisseur. La référence (dp.reference) est déjà générée et stockée
 * par demanderFactureProforma() côté contrôleur — elle est lue ici telle
 * quelle, pas régénérée, pour rester cohérente entre l'enregistrement et
 * le PDF (une référence stable même si on régénère le PDF plusieurs fois).
 */
function recupererGroupesProformaActifs(PDO $bdBASI): array {
    $stmt = $bdBASI->query("
        SELECT
            dp.idFournisseur,
            dp.reference,
            f.nomF, f.prenomF, f.entreprise,
            dal.idDL,
            lb.designation
        FROM demande_proforma dp
        JOIN fournisseur     f   ON dp.idFournisseur = f.idF
        JOIN demandes_ligne  dal ON dp.idDL          = dal.idDL
        JOIN ligneBudget     lb  ON dal.idLB          = lb.id
        WHERE dp.statut = 1
        ORDER BY dp.idFournisseur, dal.idDL
    ");

    $groupes = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idF = (int)$row['idFournisseur'];
        if (!isset($groupes[$idF])) {
            $groupes[$idF] = [
                    'idFournisseur' => $idF,
                    'reference'     => $row['reference'],
                    'nom'           => trim(($row['prenomF'] ?? '') . ' ' . ($row['nomF'] ?? ''))
                            . ($row['entreprise'] ? ' — ' . $row['entreprise'] : ''),
                    'lignes'        => [],
            ];
        }
        $groupes[$idF]['lignes'][] = [
                'idDL'        => (int)$row['idDL'],
                'designation' => $row['designation'],
        ];
    }
    return array_values($groupes);
}

/* ═══════════════════════════════════════════════════════════════════════════
   CLASSE PDF — mise en page sur gabarit institutionnel
═══════════════════════════════════════════════════════════════════════════ */

class PdfProforma extends PDF_MC_Table
{
    public string $nomFournisseur = '';
    public string $reference      = '';
    public string $dateDoc        = '';

    function Header()
    {
        // Fond de page : gabarit institutionnel GSJLF (bandeau, sceau, pied de page)
        $this->setSourceFile(PROFORMA_TEMPLATE_PDF);
        $tplIdx = $this->importPage(1);
        $this->useTemplate($tplIdx, 0, 0, 210, 297);

        if ($this->PageNo() === 1) {
            // ── Bandeau de titre, coins arrondis (descendu de 4mm) ─────────
            [$r, $g, $b] = COULEUR_VERT_FONCE;
            $this->SetFillColor($r, $g, $b);
            $this->RoundedRect(10, 48, 190, 13, 2.5, 'F');
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Helvetica', 'B', 13);
            $this->SetXY(10, 48);
            $this->Cell(190, 13, decodeFpdf('DEMANDE DE FACTURE PRO FORMA'), 0, 0, 'C');

            // ── Encart fournisseur / référence / date, coins arrondis ─────
            [$r, $g, $b] = COULEUR_VERT_CLAIR;
            $this->SetFillColor($r, $g, $b);
            [$r, $g, $b] = COULEUR_VERT;
            $this->SetDrawColor($r, $g, $b);
            $this->SetLineWidth(0.3);
            $this->RoundedRect(10, 65, 190, 16, 2, 'FD');

            [$r, $g, $b] = COULEUR_TEXTE;
            $this->SetTextColor($r, $g, $b);

            $this->SetFont('Helvetica', 'B', 8);
            $this->SetXY(14, 68);
            $this->Cell(60, 4, decodeFpdf('FOURNISSEUR'), 0, 2);
            $this->SetFont('Helvetica', '', 10);
            $this->SetX(14);
            $this->Cell(90, 5, decodeFpdf($this->nomFournisseur), 0, 0);

            $this->SetFont('Helvetica', 'B', 8);
            $this->SetXY(110, 68);
            $this->Cell(40, 4, decodeFpdf('REFERENCE'), 0, 2);
            $this->SetFont('Helvetica', '', 10);
            $this->SetX(110);
            $this->Cell(40, 5, decodeFpdf($this->reference), 0, 0);

            $this->SetFont('Helvetica', 'B', 8);
            $this->SetXY(160, 68);
            $this->Cell(35, 4, decodeFpdf('DATE'), 0, 2);
            $this->SetFont('Helvetica', '', 10);
            $this->SetX(160);
            $this->Cell(35, 5, decodeFpdf($this->dateDoc), 0, 0);

            $this->SetY(88);
        } else {
            // ── Pages suivantes (plusieurs produits → plusieurs pages) ────
            // Bandeau compact : juste un rappel de la référence, pas de
            // répétition du gros titre/encart, pour laisser plus de place
            // utile au tableau.
            [$r, $g, $b] = COULEUR_TEXTE_DOUX;
            $this->SetTextColor($r, $g, $b);
            $this->SetFont('Helvetica', 'I', 9);
            $this->SetXY(10, 44);
            $this->Cell(190, 5, decodeFpdf('Référence : ' . $this->reference . ' (suite)'), 0, 1, 'R');

            $this->SetY(50);
        }

        // ── En-tête du tableau (répété sur toutes les pages) ────────────────
        [$r, $g, $b] = COULEUR_VERT_FONCE;
        $this->SetFillColor($r, $g, $b);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetX(10);
        $this->Cell(100, 9, decodeFpdf('Désignation'), 0, 0, 'L', true);
        $this->Cell(45, 9, decodeFpdf('Prix'), 0, 0, 'C', true);
        $this->Cell(45, 9, decodeFpdf('Délai de validité'), 0, 1, 'C', true);
    }

    function Footer()
    {
        // Le gabarit contient déjà le pied de page institutionnel (adresse,
        // téléphone, site web) — rien à ajouter ici.
    }

    /**
     * Rectangle à coins arrondis (recette FPDF classique — domaine public,
     * script officiel fpdf.org). $style : 'F' (rempli), 'D' (contour),
     * 'FD'/'DF' (rempli + contour).
     */
    function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $k  = $this->k;
        $hp = $this->h;
        if ($style === 'F')      $op = 'f';
        elseif ($style === 'FD' || $style === 'DF') $op = 'B';
        else                     $op = 'S';

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

/**
 * Génère le PDF de demande de facture pro forma pour un fournisseur et
 * l'enregistre sur disque (mode 'F' de FPDF::Output()).
 */
function genererPdfFournisseur(array $groupe, string $reference, string $cheminFichier): void {
    date_default_timezone_set('Africa/Dakar');
    $dateDoc = (new DateTime())->format('d/m/Y');

    $pdf = new PdfProforma();
    $pdf->nomFournisseur = $groupe['nom'];
    $pdf->reference      = $reference;
    $pdf->dateDoc        = $dateDoc;
    $pdf->SetAutoPageBreak(true, 22); // réserve l'espace du pied de page institutionnel
    $pdf->AddPage();

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetDrawColor(230, 230, 230);
    $pdf->SetLineWidth(0.2);

    $clair = false;
    foreach ($groupe['lignes'] as $l) {
        if ($clair) {
            [$r, $g, $b] = COULEUR_VERT_CLAIR;
            $pdf->SetFillColor($r, $g, $b);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        [$r, $g, $b] = COULEUR_TEXTE;
        $pdf->SetTextColor($r, $g, $b);

        $pdf->SetX(10);
        $pdf->Cell(100, 9, decodeFpdf($l['designation'] ?? ''), 0, 0, 'L', true);
        $pdf->Cell(45, 9, '', 0, 0, 'C', true);  // Prix — à renseigner par le fournisseur
        $pdf->Cell(45, 9, '', 0, 1, 'C', true);  // Délai de validité — à renseigner par le fournisseur
        $clair = !$clair;
    }

    // Ligne de clôture du tableau
    [$r, $g, $b] = COULEUR_VERT_FONCE;
    $pdf->SetDrawColor($r, $g, $b);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());

    // Note explicative
    $pdf->Ln(6);
    [$r, $g, $b] = COULEUR_TEXTE_DOUX;
    $pdf->SetTextColor($r, $g, $b);
    $pdf->SetFont('Helvetica', 'I', 9);
    $pdf->SetX(10);
    $pdf->MultiCell(190, 5, decodeFpdf(
            "Merci de bien vouloir compléter les colonnes « Prix » et « Délai de validité » ci-dessus, " .
            "puis de nous retourner ce document signé et cacheté."
    ), 0, 'L');

    $pdf->Output($cheminFichier, 'F');
}

/* ═══════════════════════════════════════════════════════════════════════════
   ZIP — création avec ZipArchive, ou repli PHP pur si l'extension est absente
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Écrit une archive ZIP valide en PHP pur (sans ext-zip), en ne s'appuyant
 * que sur zlib (gzdeflate).
 */
function creerZipPurPhp(array $fichiers, string $cheminZipDestination): bool {
    $localHeaders     = '';
    $centralDirectory = '';
    $offset           = 0;
    $nbFichiers       = 0;

    foreach ($fichiers as $f) {
        $donnees = @file_get_contents($f['chemin']);
        if ($donnees === false) continue;

        $nom                = $f['nomZip'];
        $crc                = crc32($donnees);
        $tailleOriginale    = strlen($donnees);
        $donneesCompressees = gzdeflate($donnees, 9);
        $tailleCompressee   = strlen($donneesCompressees);

        $enteteLocal = pack(
                'VvvvvvVVVvv',
                0x04034b50, 20, 0, 8, 0, 0,
                $crc, $tailleCompressee, $tailleOriginale, strlen($nom), 0
        );
        $localHeaders .= $enteteLocal . $nom . $donneesCompressees;

        $entreeCentrale = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50, 20, 20, 0, 8, 0, 0,
                $crc, $tailleCompressee, $tailleOriginale,
                strlen($nom), 0, 0, 0, 0, 0, $offset
        );
        $centralDirectory .= $entreeCentrale . $nom;

        $offset += strlen($enteteLocal) + strlen($nom) + $tailleCompressee;
        $nbFichiers++;
    }

    if ($nbFichiers === 0) return false;

    $finRepertoireCentral = pack(
            'VvvvvVVv',
            0x06054b50, 0, 0, $nbFichiers, $nbFichiers,
            strlen($centralDirectory), strlen($localHeaders), 0
    );

    return file_put_contents($cheminZipDestination, $localHeaders . $centralDirectory . $finRepertoireCentral) !== false;
}

function creerZip(array $fichiers, string $cheminZipDestination): bool {
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($cheminZipDestination, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($fichiers as $f) {
                $zip->addFile($f['chemin'], $f['nomZip']);
            }
            $zip->close();
            return true;
        }
    }
    return creerZipPurPhp($fichiers, $cheminZipDestination);
}

/* ═══════════════════════════════════════════════════════════════════════════
   TRAITEMENT PRINCIPAL
═══════════════════════════════════════════════════════════════════════════ */

$groupes = recupererGroupesProformaActifs($bdBASI);

if (empty($groupes)) {
    http_response_code(404);
    die('Aucune demande de facture pro forma active pour le moment.');
}

if (!file_exists(PROFORMA_TEMPLATE_PDF)) {
    http_response_code(500);
    die('Gabarit PDF introuvable (PROFORMA_TEMPLATE_PDF à ajuster).');
}

$dossierTmp  = sys_get_temp_dir();
$horodatage  = date('Ymd_His');
$fichiersPdf = []; // [['chemin' => ..., 'nomZip' => ...], ...]

try {
    // ── Génération d'un PDF par fournisseur ────────────────────────────────
    foreach ($groupes as $g) {
        $reference = $g['reference'];
        $nomBase   = 'proforma_' . nomFichierSain($g['nom']) . '_' . $g['idFournisseur'];
        $chemin    = $dossierTmp . '/' . $nomBase . '_' . uniqid() . '.pdf';
        genererPdfFournisseur($g, $reference, $chemin);
        $fichiersPdf[] = ['chemin' => $chemin, 'nomZip' => $nomBase . '.pdf'];
    }

    // ── Archivage en ZIP ────────────────────────────────────────────────────
    $nomZipTelecharge = 'demande_facture_proforma_' . $horodatage . '.zip';
    $cheminZip        = $dossierTmp . '/' . $nomZipTelecharge . '_' . uniqid();

    if (!creerZip($fichiersPdf, $cheminZip)) {
        throw new \RuntimeException("Impossible de créer l'archive ZIP.");
    }

    // ── Téléchargement automatique du ZIP ───────────────────────────────────
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $nomZipTelecharge . '"');
    header('Content-Length: ' . filesize($cheminZip));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($cheminZip);

    // ── Nettoyage des fichiers temporaires ──────────────────────────────────
    foreach ($fichiersPdf as $f) {
        @unlink($f['chemin']);
    }
    @unlink($cheminZip);

    ob_end_flush();
    exit;

} catch (\Throwable $e) {
    foreach ($fichiersPdf as $f) {
        @unlink($f['chemin']);
    }
    error_log('[ProForma][dfc-demande-facture-proforma] ' . $e->getMessage());
    http_response_code(500);
    die('Impossible de générer les factures pro forma.');
}