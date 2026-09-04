<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
require_once __DIR__.'/../modules_lmdbvehiclemanagement.php';
require_once __DIR__.'/../../../../class/lmdbvehicledossier.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

/** Native model: summary PDF plus original documents in a verified ZIP. */
class pdf_lmdb_vehicle_dossier extends ModelePDFLmdbvehiclemanagement
{
	/** @var DoliDB */ public $db;
	/** @var string */ public $name = 'lmdb_vehicle_dossier';
	/** @var string */ public $type = 'pdf';
	/** @var string */ public $description;
	/** @var string */ public $version = 'dolibarr';
	/** @var string */ public $error = '';
	/** @var array<string,string> */ public $result = array();
	/** @var int */ public $option_logo = 0;
	/** @var float */ public $marge_gauche = 10;
	/** @var float */ public $marge_droite = 10;
	/** @var float */ public $marge_haute = 10;
	/** @var float */ public $marge_basse = 10;

	/** @param DoliDB $db Database */
	public function __construct($db)
	{
		global $langs;
		$this->db = $db;
		$langs->load('lmdbvehiclemanagement@lmdbvehiclemanagement');
		$this->description = $langs->trans('LmdbVehicleDossierDescription');
	}

	/**
	 * @param LmdbVehicle $object
	 * @param Translate $outputlangs
	 * @param string $srctemplatepath
	 * @param int $hidedetails
	 * @param int $hidedesc
	 * @param int $hideref
	 * @return int
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		try {
			$builder = new LmdbVehicleDossier($this->db);
			$this->result['fullpath'] = $builder->build($object, $outputlangs, array($this, 'writeSummary'));
			return 1;
		} catch (Throwable $e) {
			$this->error = $outputlangs->trans($e->getMessage());
			dol_syslog(__METHOD__.' failed for vehicle '.((int) $object->id), LOG_ERR);
			return -1;
		}
	}

	/**
	 * Explicit page lifecycle: reserve the measured native footer, finish each page once.
	 * @param LmdbVehicle $object
	 * @param array{sections:list<array{title:string,rows:list<string>}>,files:array,warnings:list<string>} $data
	 * @param Translate $outputlangs
	 * @param string $path
	 * @return void
	 */
	public function writeSummary($object, $data, $outputlangs, $path)
	{
		$outputlangs->loadLangs(array('main', 'companies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
		$format = pdf_getFormat($outputlangs);
		$pdf = pdf_getInstance(array($format['width'], $format['height']));
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', pdf_getPDFFontSize($outputlangs));
		$pdf->SetTitle($outputlangs->transnoentities('LmdbVehicleDossier').' — '.$object->ref);
		$pdf->AddPage();
		// HTML footer helpers use TCPDF transactions themselves. Measure on a
		// separate instance so their rollback cannot destroy the document state.
		$measurement = pdf_getInstance(array($format['width'], $format['height']));
		$measurement->setPrintHeader(false);
		$measurement->setPrintFooter(false);
		$measurement->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$measurement->SetFont(pdf_getPDFFont($outputlangs), '', pdf_getPDFFontSize($outputlangs));
		$measurement->AddPage();
		$measurement->SetAutoPageBreak(false, 0);
		$heightforfooter = $this->_pagefoot($measurement, $object, $outputlangs, 0) + 5;
		unset($measurement);
		if ($heightforfooter > $format['height'] - $this->marge_haute - 40) throw new RuntimeException('LmdbDossierFooterTooLarge');
		$pdf->setPageOrientation('', true, $heightforfooter);
		$width = $format['width'] - $this->marge_gauche - $this->marge_droite;
		$fontSize = pdf_getPDFFontSize($outputlangs);
		$blocks = array(array('text' => $outputlangs->transnoentities('LmdbVehicleDossier').' — '.$object->ref, 'size' => $fontSize + 4, 'bold' => true),
			array('text' => $outputlangs->transnoentities('Date').': '.dol_print_date(dol_now(), 'dayhour', 'tzuser', $outputlangs), 'size' => $fontSize, 'bold' => false));
		foreach ($data['sections'] as $section) {
			$blocks[] = array('text' => $section['title'], 'size' => $fontSize + 1, 'bold' => true);
			foreach ($section['rows'] ?: array($outputlangs->transnoentities('NoRecordFound')) as $row) $blocks[] = array('text' => $row, 'size' => $fontSize - 1, 'bold' => false);
		}
		foreach ($blocks as $block) {
			$pdf->SetFont('', $block['bold'] ? 'B' : '', $block['size']);
			$text = $outputlangs->convToOutputCharset(html_entity_decode(strip_tags($block['text']), ENT_QUOTES, 'UTF-8'));
			foreach (preg_split('/\R/u', $text) ?: array() as $paragraph) {
				// Prefer word boundaries; split an overlong filename at Unicode characters.
				$lines = array(); $line = '';
				foreach (preg_split('/(\s+)/u', $paragraph, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: array() as $word) {
					if ($line !== '' && $pdf->GetStringWidth($line.$word) > $width - 2) { $lines[] = rtrim($line); $line = ''; }
					if ($line === '' && trim($word) === '') continue;
					foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: array() as $char) {
						if ($line !== '' && $pdf->GetStringWidth($line.$char) > $width - 2) { $lines[] = $line; $line = ''; }
						$line .= $char;
					}
				}
				$lines[] = $line;
				foreach ($lines as $line) {
					$lineHeight = $pdf->getStringHeight($width, $line ?: ' ') + 1;
					if ($pdf->GetY() + $lineHeight > $format['height'] - $heightforfooter) {
						$pdf->SetAutoPageBreak(false, 0);
						$this->_pagefoot($pdf, $object, $outputlangs, 1);
						$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
						$pdf->AddPage();
						$pdf->SetXY($this->marge_gauche, $this->marge_haute);
						$pdf->setPageOrientation('', true, $heightforfooter);
						$pdf->SetFont('', $block['bold'] ? 'B' : '', $block['size']);
					}
					$pdf->MultiCell($width, $lineHeight, $line, 0, 'L');
				}
			}
			$pdf->Ln(3);
		}
		$pdf->SetAutoPageBreak(false, 0);
		$this->_pagefoot($pdf, $object, $outputlangs, 0);
		$pdf->Output($path, 'F');
	}

	/**
	 * @param TCPDF $pdf
	 * @param LmdbVehicle $object
	 * @param Translate $outputlangs
	 * @param int $hidefreetext
	 * @return int
	 */
	protected function _pagefoot($pdf, $object, $outputlangs, $hidefreetext)
	{
		global $mysoc;
		return pdf_pagefoot($pdf, $outputlangs, 'LMDBVEHICLEMANAGEMENT_DOSSIER_FREE_TEXT', $mysoc, $this->marge_basse, $this->marge_gauche, $pdf->getPageHeight(), $object, getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS'), $hidefreetext, $pdf->getPageWidth());
	}
}
