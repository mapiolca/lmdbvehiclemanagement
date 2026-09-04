<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
require_once __DIR__.'/../modules_lmdbvehiclemanagement.php';
require_once __DIR__.'/../../../../class/lmdbvehicledossier.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

/**
 * Native model: summary PDF plus original documents in a verified ZIP.
 * @phpstan-import-type DossierData from LmdbVehicleDossier
 * @phpstan-import-type DossierTable from LmdbVehicleDossier
 */
class pdf_lmdb_vehicle_dossier extends ModelePDFLmdbvehiclemanagement
{
	/** @var DoliDB */ public $db;
	/** @var string */ public $name = 'lmdb_vehicle_dossier';
	/** @var string */ public $type = 'pdf';
	/** @var string */ public $description;
	/** @var string */ public $version = 'dolibarr';
	/** @var string */ public $error = '';
	/** @var array<string,string> */ public $result = array();
	/** @var int */ public $option_logo = 1;
	/** @var Societe|null */ public $emetteur;
	/** @var int */ private $generationTimestamp = 0;
	/** @var float */ private $headerBottom = 0;
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
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
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
	 * @param DossierData $data
	 * @param Translate $outputlangs
	 * @param string $path
	 * @return void
	 */
	public function writeSummary($object, $data, $outputlangs, $path)
	{
		global $mysoc;
		$this->emetteur = $mysoc instanceof Societe ? $mysoc : null;
		$this->generationTimestamp = dol_now();
		$outputlangs->loadLangs(array('main', 'companies', 'lmdbvehiclemanagement@lmdbvehiclemanagement'));
		$format = pdf_getFormat($outputlangs);
		$pdf = pdf_getInstance(array($format['width'], $format['height']));
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->setCellPaddings(0, 0, 0, 0);
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
		$this->headerBottom = $this->_pagehead($pdf, $object, $outputlangs);
		if ($this->headerBottom + 40 > $format['height'] - $heightforfooter) throw new RuntimeException('LmdbDossierWriteFailed');
		$pdf->setPageOrientation('', true, $heightforfooter);
		foreach ($data['sections'] as $section) {
			$tables = $section['tables'] ?: array(array('title' => '', 'columns' => array($outputlangs->transnoentities('Description')), 'rows' => array()));
			foreach ($tables as $table) {
				$this->writeTable($pdf, $object, $table, $section['title'], $outputlangs, $heightforfooter);
			}
		}
		$pdf->SetAutoPageBreak(false, 0);
		$this->_pagefoot($pdf, $object, $outputlangs, 0);
		$pdf->Output($path, 'F');
	}

	/**
	 * Same header layout as native Espadon: company logo left, document identity right.
	 * Return the actual content start, including wrapped references and tall logos.
	 * @param TCPDF $pdf
	 * @param LmdbVehicle $object
	 * @param Translate $outputlangs
	 * @return float
	 */
	protected function _pagehead($pdf, $object, $outputlangs)
	{
		global $conf;
		pdf_pagehead($pdf, $outputlangs, $pdf->getPageHeight());
		$pdf->SetAutoPageBreak(false, 0);
		$pdf->setCellPaddings(0, 0, 0, 0);
		$pdf->setCellMargins(0, 0, 0, 0);
		$fontSize = pdf_getPDFFontSize($outputlangs);
		$width = $pdf->getPageWidth() - $this->marge_gauche - $this->marge_droite;
		$rightWidth = min(110, $width * 0.58);
		$leftWidth = $width - $rightWidth - 10;
		$rightX = $pdf->getPageWidth() - $this->marge_droite - $rightWidth;
		$leftBottom = $this->marge_haute;
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $fontSize + 3);
		$pdf->SetXY($this->marge_gauche, $this->marge_haute);
		$logo = '';
		if ($this->emetteur && $this->emetteur->logo) {
			$entity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
			$logodir = $conf->mycompany->multidir_output[$entity] ?? '';
			if (!$logodir && $entity === (int) $conf->entity) $logodir = $conf->mycompany->dir_output ?? '';
			if ($logodir) {
				if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') && $this->emetteur->logo_small) {
					$logo = $logodir.'/logos/thumbs/'.$this->emetteur->logo_small;
				}
				if (!$logo || !is_readable($logo)) $logo = $logodir.'/logos/'.$this->emetteur->logo;
			}
		}
		if ($logo && is_readable($logo)) {
			$height = pdf_getHeightForLogo($logo);
			$size = dol_getImageSize($logo);
			if (!empty($size['width']) && !empty($size['height'])) {
				// Preserve the native height/aspect ratio without entering the reference block.
				$height = min($height, $leftWidth * $size['height'] / $size['width']);
				$pdf->Image($logo, $this->marge_gauche, $this->marge_haute, 0, $height);
				$leftBottom += $height;
			} else {
				$logo = '';
			}
		} else {
			$logo = '';
		}
		if (!$logo && $this->emetteur) {
			$pdf->MultiCell($leftWidth, 0, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
			$leftBottom = $pdf->GetY();
		}
		$pdf->SetFont('', 'B', $fontSize + 2);
		$pdf->SetXY($rightX, $this->marge_haute);
		$pdf->MultiCell($rightWidth, 0, $outputlangs->convToOutputCharset($outputlangs->transnoentities('LmdbVehicleDossier')), 0, 'R');
		$pdf->SetFont('', '', $fontSize + 1);
		$pdf->SetX($rightX);
		$pdf->MultiCell($rightWidth, 0, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Ref').' : '.$object->ref), 0, 'R');
		$pdf->SetFont('', '', $fontSize - 1);
		$pdf->SetX($rightX);
		$pdf->MultiCell($rightWidth, 0, $outputlangs->convToOutputCharset($outputlangs->transnoentities('DateBuild').' : '.dol_print_date($this->generationTimestamp, 'dayhour', 'tzuser', $outputlangs)), 0, 'R');
		$bottom = max($leftBottom, $pdf->GetY()) + 8;
		$pdf->SetXY($this->marge_gauche, $bottom);
		return $bottom;
	}

	/**
	 * Wrap plain text before drawing the grid, including long unbroken filenames.
	 * @param TCPDF $pdf
	 * @param string $text Already converted to the PDF output charset
	 * @param float $width Available text width, excluding padding
	 * @return list<string>
	 */
	private function wrapCell($pdf, $text, $width)
	{
		$lines = array();
		foreach (preg_split('/\R/u', $text) ?: array('') as $paragraph) {
			$line = '';
			foreach (preg_split('/(\s+)/u', $paragraph, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: array() as $word) {
				if ($line !== '' && $pdf->GetStringWidth($line.$word) > $width) { $lines[] = rtrim($line); $line = ''; }
				if ($line === '' && trim($word) === '') continue;
				foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: array() as $char) {
					if ($line !== '' && $pdf->GetStringWidth($line.$char) > $width) { $lines[] = $line; $line = ''; }
					$line .= $char;
				}
			}
			$lines[] = rtrim($line);
		}
		return $lines;
	}

	/**
	 * @param TCPDF $pdf
	 * @param list<list<string>> $cells Wrapped cell contents
	 * @param list<float> $widths Column widths
	 * @param float $height Row height, including 1 mm vertical padding on each side
	 * @param int $shade Background grey level
	 * @return void
	 */
	private function drawRow($pdf, $cells, $widths, $height, $shade)
	{
		$x = $this->marge_gauche;
		$y = $pdf->GetY();
		$pdf->SetDrawColor(210, 210, 210);
		$pdf->SetFillColor($shade, $shade, $shade);
		$pdf->SetTextColor(30, 30, 30);
		$pdf->SetLineWidth(0.15);
		foreach ($widths as $column => $width) {
			$pdf->Rect($x, $y, $width, $height, 'DF');
			// Lines are already wrapped and measured. Cell avoids a second, different
			// TCPDF MultiCell wrap spilling into the following painted row.
			$lineHeight = $pdf->getStringHeight($width, 'Ag');
			foreach ($cells[$column] as $line => $text) {
				$pdf->SetXY($x + 2, $y + 1 + $line * $lineHeight);
				$pdf->Cell($width - 4, $lineHeight, $text, 0, 0, 'L');
			}
			$x += $width;
		}
		$pdf->SetXY($this->marge_gauche, $y + $height);
	}

	/**
	 * Keep ordinary rows together; split only rows taller than a usable page.
	 * Every continuation repeats the section, record reference and column headers.
	 * @param TCPDF $pdf
	 * @param LmdbVehicle $object
	 * @param DossierTable $table
	 * @param string $sectionTitle
	 * @param Translate $outputlangs
	 * @param float $heightforfooter
	 * @return void
	 */
	private function writeTable($pdf, $object, $table, $sectionTitle, $outputlangs, $heightforfooter)
	{
		$width = $pdf->getPageWidth() - $this->marge_gauche - $this->marge_droite;
		$bottom = $pdf->getPageHeight() - $heightforfooter;
		$fontSize = pdf_getPDFFontSize($outputlangs) - 1;
		$count = count($table['columns']);
		$ratios = $count === 2 ? array(0.34, 0.66) : ($count === 6 ? array(0.15, 0.14, 0.14, 0.30, 0.13, 0.14) : array_fill(0, $count, 1 / $count));
		if ($count === 7) $ratios = array(0.14, 0.15, 0.14, 0.15, 0.14, 0.07, 0.21);
		$widths = array_map(static function ($ratio) use ($width) { return $width * $ratio; }, $ratios);
		$title = $sectionTitle.($table['title'] !== '' ? ' - '.$table['title'] : '');
		$pdf->SetFont('', 'B', $fontSize + 2);
		$title = implode("\n", $this->wrapCell($pdf, $outputlangs->convToOutputCharset($title), $width));
		$titleHeight = $pdf->getStringHeight($width, $title) + 2;
		$pdf->SetFont('', 'B', $fontSize);
		$headers = array();
		foreach ($table['columns'] as $column => $label) $headers[] = $this->wrapCell($pdf, $outputlangs->convToOutputCharset($label), $widths[$column] - 4);
		$lineHeight = $pdf->getStringHeight($width, 'Ag');
		$headerHeight = max(array_map('count', $headers)) * $lineHeight + 2;
		$pdf->SetFont('', '', $fontSize);
		$rows = array();
		$totalHeight = $titleHeight + $headerHeight;
		foreach ($table['rows'] as $row) {
			$cells = array();
			foreach ($widths as $column => $columnWidth) $cells[] = $this->wrapCell($pdf, $outputlangs->convToOutputCharset($row[$column]), $columnWidth - 4);
			$rows[] = $cells;
			$totalHeight += max(array_map('count', $cells)) * $lineHeight + 2;
		}
		if (!$rows) {
			$rows[] = array($this->wrapCell($pdf, $outputlangs->convToOutputCharset($outputlangs->transnoentities('NoRecordFound')), $width - 4));
			$totalHeight += count($rows[0][0]) * $lineHeight + 2;
		}
		$bodyHeight = $bottom - $this->headerBottom - $titleHeight - $headerHeight;
		if ($bodyHeight < $lineHeight + 2) throw new RuntimeException('LmdbDossierFooterTooLarge');
		$newPage = function () use ($pdf, $object, $outputlangs, $heightforfooter) {
			$pdf->SetAutoPageBreak(false, 0);
			$this->_pagefoot($pdf, $object, $outputlangs, 1);
			$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
			$pdf->AddPage();
			$this->_pagehead($pdf, $object, $outputlangs);
			$pdf->setPageOrientation('', true, $heightforfooter);
			$pdf->setCellPaddings(0, 0, 0, 0);
		};
		$heading = function () use ($pdf, $title, $width, $titleHeight, $headers, $widths, $headerHeight, $fontSize) {
			$pdf->SetFont('', 'B', $fontSize + 2);
			$pdf->SetTextColor(30, 30, 30);
			$y = $pdf->GetY();
			$pdf->MultiCell($width, $titleHeight - 2, $title, 0, 'L');
			$pdf->SetXY($this->marge_gauche, $y + $titleHeight);
			$pdf->SetFont('', 'B', $fontSize);
			$this->drawRow($pdf, $headers, $widths, $headerHeight, 232);
			$pdf->SetFont('', '', $fontSize);
		};
		// A short record fits on one page: move the whole table if necessary.
		$firstHeight = max(array_map('count', $rows[0])) * $lineHeight + 2;
		$needed = $totalHeight <= $bottom - $this->headerBottom ? $totalHeight : $titleHeight + $headerHeight + min($firstHeight, $bodyHeight);
		if ($pdf->GetY() + $needed > $bottom) $newPage();
		$heading();
		foreach ($rows as $index => $cells) {
			$remaining = max(array_map('count', $cells));
			$rowHeight = $remaining * $lineHeight + 2;
			if ($pdf->GetY() + $rowHeight > $bottom && $rowHeight <= $bodyHeight) { $newPage(); $heading(); }
			$offset = 0;
			while ($remaining > 0) {
				$capacity = (int) floor(($bottom - $pdf->GetY() - 2) / $lineHeight + 0.00001);
				if ($capacity < 1) { $newPage(); $heading(); continue; }
				$take = min($remaining, $capacity);
				$fragment = array_map(static function ($lines) use ($offset, $take) { return array_slice($lines, $offset, $take); }, $cells);
				$this->drawRow($pdf, $fragment, $table['rows'] ? $widths : array($width), $take * $lineHeight + 2, $index % 2 ? 248 : 255);
				$remaining -= $take;
				$offset += $take;
				if ($remaining > 0) { $newPage(); $heading(); }
			}
		}
		$pdf->Ln(6);
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
		return pdf_pagefoot($pdf, $outputlangs, 'LMDBVEHICLEMANAGEMENT_DOSSIER_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $pdf->getPageHeight(), $object, getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS'), $hidefreetext, $pdf->getPageWidth());
	}
}
