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
	 * @param DossierData $data
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
		$width = $format['width'] - $this->marge_gauche - $this->marge_droite;
		$fontSize = pdf_getPDFFontSize($outputlangs);
		$pdf->SetFont('', 'B', $fontSize + 4);
		$pdf->MultiCell($width, 0, $outputlangs->convToOutputCharset($outputlangs->transnoentities('LmdbVehicleDossier').' - '.$object->ref), 0, 'L');
		$pdf->Ln(2);
		$pdf->SetFont('', '', $fontSize);
		$pdf->MultiCell($width, 0, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Date').': '.dol_print_date(dol_now(), 'dayhour', 'tzuser', $outputlangs)), 0, 'L');
		$pdf->Ln(6);
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
			$pdf->MultiCell($width - 4, $height - 2, implode("\n", $cells[$column]), 0, 'L', false, 0, $x + 2, $y + 1, true, 0, false, false);
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
		$bodyHeight = $bottom - $this->marge_haute - $titleHeight - $headerHeight;
		if ($bodyHeight < $lineHeight + 2) throw new RuntimeException('LmdbDossierFooterTooLarge');
		$newPage = function () use ($pdf, $object, $outputlangs, $heightforfooter) {
			$pdf->SetAutoPageBreak(false, 0);
			$this->_pagefoot($pdf, $object, $outputlangs, 1);
			$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
			$pdf->AddPage();
			$pdf->SetXY($this->marge_gauche, $this->marge_haute);
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
		$needed = $totalHeight <= $bottom - $this->marge_haute ? $totalHeight : $titleHeight + $headerHeight + min($firstHeight, $bodyHeight);
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
		global $mysoc;
		return pdf_pagefoot($pdf, $outputlangs, 'LMDBVEHICLEMANAGEMENT_DOSSIER_FREE_TEXT', $mysoc, $this->marge_basse, $this->marge_gauche, $pdf->getPageHeight(), $object, getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS'), $hidefreetext, $pdf->getPageWidth());
	}
}
