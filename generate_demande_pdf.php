<?php
require_once 'config.php';
require_once 'check_auth.php';
require_once('tcpdf/tcpdf.php');

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de demande invalide");
}

$id = (int)$_GET['id'];

// Récupérer les informations de la demande
$query = $db->prepare("
    SELECT 
        dv.*,
        e.nom as employe_nom,
        e.prenoms as employe_prenoms,
        e.service as employe_service,
        d.nom as destination_nom,
        d.ville as destination_ville,
        d.adresse as destination_adresse,
        d.contact_nom as destination_contact_nom,
        d.contact_telephone as destination_contact_tel,
        v.marque as vehicule_marque,
        v.modele as vehicule_modele,
        v.immatriculation as vehicule_immatriculation,
        v.type as vehicule_type,
        c.nom as chauffeur_nom,
        c.prenoms as chauffeur_prenoms,
        c.telephone as chauffeur_telephone
    FROM demandes_vehicules dv
    LEFT JOIN employees e ON dv.employe_id = e.id
    LEFT JOIN destinations d ON dv.destination_id = d.id
    LEFT JOIN vehicules v ON dv.vehicule_id = v.id
    LEFT JOIN chauffeurs c ON dv.chauffeur_id = c.id
    WHERE dv.id = :id
");

$query->execute([':id' => $id]);
$demande = $query->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    die("Demande non trouvée");
}

// Créer une nouvelle instance de TCPDF
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 15);
        $this->Cell(0, 15, SITE_NAME, 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(20);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Créer le document PDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Définir les informations du document
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor(SITE_NAME);
$pdf->SetTitle('Demande de véhicule #' . $id);

// Définir les marges
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Définir l'auto-page-break
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Ajouter une page
$pdf->AddPage();

// Définir la police
$pdf->SetFont('helvetica', 'B', 16);

// Titre
$pdf->Cell(0, 10, 'Demande de véhicule #' . $id, 0, 1, 'C');
$pdf->Ln(10);

// Informations de la demande
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Informations de la demande', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 11);

$pdf->Cell(60, 7, 'Date de création:', 0, 0);
$pdf->Cell(0, 7, date('d/m/Y H:i', strtotime($demande['created_at'])), 0, 1);

$pdf->Cell(60, 7, 'Statut:', 0, 0);
$pdf->Cell(0, 7, ucfirst(str_replace('_', ' ', $demande['statut'])), 0, 1);

$pdf->Cell(60, 7, 'Niveau d\'urgence:', 0, 0);
$pdf->Cell(0, 7, ucfirst($demande['urgence']), 0, 1);

$pdf->Ln(5);

// Informations de l'employé
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Demandeur', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 11);

$pdf->Cell(60, 7, 'Nom et prénoms:', 0, 0);
$pdf->Cell(0, 7, $demande['employe_nom'] . ' ' . $demande['employe_prenoms'], 0, 1);

$pdf->Cell(60, 7, 'Service:', 0, 0);
$pdf->Cell(0, 7, $demande['employe_service'], 0, 1);

$pdf->Ln(5);

// Informations du déplacement
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Informations du déplacement', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 11);

$pdf->Cell(60, 7, 'Destination:', 0, 0);
$pdf->Cell(0, 7, $demande['destination_nom'] . ' (' . $demande['destination_ville'] . ')', 0, 1);

$pdf->Cell(60, 7, 'Adresse:', 0, 0);
$pdf->Cell(0, 7, $demande['destination_adresse'], 0, 1);

$pdf->Cell(60, 7, 'Contact sur place:', 0, 0);
$pdf->Cell(0, 7, $demande['destination_contact_nom'] . ' - ' . $demande['destination_contact_tel'], 0, 1);

$pdf->Cell(60, 7, 'Date de départ:', 0, 0);
$pdf->Cell(0, 7, date('d/m/Y H:i', strtotime($demande['date_depart'])), 0, 1);

$pdf->Cell(60, 7, 'Date de retour:', 0, 0);
$pdf->Cell(0, 7, date('d/m/Y H:i', strtotime($demande['date_retour'])), 0, 1);

$pdf->Cell(60, 7, 'Nombre de passagers:', 0, 0);
$pdf->Cell(0, 7, $demande['nb_passagers'], 0, 1);

$pdf->Cell(60, 7, 'Motif:', 0, 0);
$pdf->MultiCell(0, 7, $demande['motif'], 0, 'L');

$pdf->Ln(5);

// Informations du véhicule et du chauffeur
if ($demande['vehicule_id']) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Véhicule assigné', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);

    $pdf->Cell(60, 7, 'Véhicule:', 0, 0);
    $pdf->Cell(0, 7, $demande['vehicule_marque'] . ' ' . $demande['vehicule_modele'], 0, 1);

    $pdf->Cell(60, 7, 'Immatriculation:', 0, 0);
    $pdf->Cell(0, 7, $demande['vehicule_immatriculation'], 0, 1);

    $pdf->Cell(60, 7, 'Type:', 0, 0);
    $pdf->Cell(0, 7, $demande['vehicule_type'], 0, 1);
}

if ($demande['chauffeur_id']) {
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Chauffeur assigné', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);

    $pdf->Cell(60, 7, 'Nom et prénoms:', 0, 0);
    $pdf->Cell(0, 7, $demande['chauffeur_nom'] . ' ' . $demande['chauffeur_prenoms'], 0, 1);

    $pdf->Cell(60, 7, 'Téléphone:', 0, 0);
    $pdf->Cell(0, 7, $demande['chauffeur_telephone'], 0, 1);
}

if ($demande['commentaire']) {
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Commentaires', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);
    $pdf->MultiCell(0, 7, $demande['commentaire'], 0, 'L');
}

// Signatures
$pdf->Ln(20);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(90, 7, 'Signature du demandeur', 0, 0, 'C');
$pdf->Cell(90, 7, 'Signature du responsable', 0, 1, 'C');
$pdf->Ln(20);
$pdf->Cell(90, 7, '............................', 0, 0, 'C');
$pdf->Cell(90, 7, '............................', 0, 1, 'C');

// Générer le PDF
$pdf->Output('demande_vehicule_' . $id . '.pdf', 'I'); 