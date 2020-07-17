<?php
/* Copyright (C) 2004-2014  Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin		<regis.houssin@inodbox.com>
 * Copyright (C) 2008       Raphael Bertrand	<raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2013	Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2012      	Christophe Battarel <christophe.battarel@altairis.fr>
 * Copyright (C) 2012       Cedric Salvador     <csalvador@gpcsolutions.fr>
 * Copyright (C) 2015       Marcos García       <marcosgdf@gmail.com>
 * Copyright (C) 2017-2018  Ferran Marcet       <fmarcet@2byte.es>
 * Copyright (C) 2018       Frédéric France     <frederic.france@netlogic.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/operationorder/doc/pdf_olaf.modules.php
 *	\ingroup    operationorder
 *	\brief      File of Class to generate PDF orders with template olaf
 */

require_once dol_buildpath('/operationorder/core/modules/operationorder/modules_operationorder.php');
require_once dol_buildpath('/dolifleet/class/vehicule.class.php');
require_once dol_buildpath('/operationorder/class/operationorder.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';


/**
 *	Class to generate PDF orders with template Eratosthene
 */
class pdf_thor extends ModelePDFOperationOrder
{
	/**
	/**
	 * @var DoliDb Database handler
	 */
	public $db;

	/**
	 * @var string model name
	 */
	public $name;

	/**
	 * @var string model description (short text)
	 */
	public $description;

	/**
	 * @var int 	Save the name of generated file as the main doc when generating a doc with this template
	 */
	public $update_main_doc_field;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * @var array Minimum version of PHP required by module.
	 * e.g.: PHP ≥ 5.5 = array(5, 5)
	 */
	public $phpmin = array(5, 5);

	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * @var int page_largeur
	 */
	public $page_largeur;

	/**
	 * @var int page_hauteur
	 */
	public $page_hauteur;

	/**
	 * @var array format
	 */
	public $format;

	/**
	 * @var int marge_gauche
	 */
	public $marge_gauche;

	/**
	 * @var int marge_droite
	 */
	public $marge_droite;

	/**
	 * @var int marge_haute
	 */
	public $marge_haute;

	/**
	 * @var int marge_basse
	 */
	public $marge_basse;

	/**
	 * Issuer
	 * @var Societe
	 */
	public $emetteur;


	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs, $mysoc;

		// Translations
		$langs->loadLangs(array("main", "bills", "products", "clitheobald@clitheobald"));

		$this->db = $db;
		$this->name = "Thor";
		$this->description = $langs->trans('PDFThorDescription');
		$this->update_main_doc_field = 1;		// Save the name of generated file as the main doc when generating a doc with this template

		// Dimension page
		$this->type = 'pdf';
		$formatarray=pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur,$this->page_hauteur);
		$this->marge_gauche=isset($conf->global->MAIN_PDF_MARGIN_LEFT)?$conf->global->MAIN_PDF_MARGIN_LEFT:10;
		$this->marge_droite=isset($conf->global->MAIN_PDF_MARGIN_RIGHT)?$conf->global->MAIN_PDF_MARGIN_RIGHT:10;
		$this->marge_haute =isset($conf->global->MAIN_PDF_MARGIN_TOP)?$conf->global->MAIN_PDF_MARGIN_TOP:10;
		$this->marge_basse =isset($conf->global->MAIN_PDF_MARGIN_BOTTOM)?$conf->global->MAIN_PDF_MARGIN_BOTTOM:10;

		$this->option_logo = 1;                    // Display logo
		$this->option_tva = 1;                     // Manage the vat option FACTURE_TVAOPTION
		$this->option_modereg = 1;                 // Display payment mode
		$this->option_condreg = 1;                 // Display payment terms
		$this->option_codeproduitservice = 1;      // Display product-service code
		$this->option_multilang = 1;               // Available in several languages
		$this->option_escompte = 0;                // Displays if there has been a discount
		$this->option_credit_note = 0;             // Support credit notes
		$this->option_freetext = 1;				   // Support add of a personalised text
		$this->option_draft_watermark = 1;		   // Support add of a watermark on drafts

		$this->franchise=!$mysoc->tva_assuj;

		// Get source company
		$this->emetteur=$mysoc;
		if (empty($this->emetteur->country_code)) $this->emetteur->country_code=substr($langs->defaultlang, -2);    // By default, if was not defined

		// Define position of columns
		$this->posxdesc=$this->marge_gauche+1;


		$this->tabTitleHeight = 5; // default height

		$this->tva=array();
		$this->localtax1=array();
		$this->localtax2=array();
		$this->atleastoneratenotnull=0;
		$this->atleastonediscount=0;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to build pdf onto disk
	 *
	 *  @param		Object		$object				Object to generate
	 *  @param		Translate	$outputlangs		Lang output object
	 *  @param		string		$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int			$hidedetails		Do not show line details
	 *  @param		int			$hidedesc			Do not show desc
	 *  @param		int			$hideref			Do not show ref
	 *  @return     int             			    1=OK, 0=KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $user, $langs, $conf, $mysoc, $db, $hookmanager, $nblines;

		/**
		 * @var  $object OperationOrder
		 */

		if (! is_object($outputlangs)) $outputlangs=$langs;
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (! empty($conf->global->MAIN_USE_FPDF)) $outputlangs->charset_output='ISO-8859-1';

		// Translations
		$outputlangs->loadLangs(array("main", "dict", "companies", "bills", "products", "orders", "deliveries", "stocks"));

		// Dans le cas de ce PDF  Si la ligne de niveau 0 est composé uniquement de produit ou service qui ont des composants, ne pas l'afficher dans le PDF.
		// Si l'un des composants est un élément non composé on l'affiche bien
		$this->fetchLines($object);

		$nblines = count($object->lines);

		$hideref = 1; // force hidden ref

		$hidetop=0;
		if(!empty($conf->global->MAIN_PDF_DISABLE_COL_HEAD_TITLE)){
			$hidetop=$conf->global->MAIN_PDF_DISABLE_COL_HEAD_TITLE;
		}

		$hidetopNewPage = $hidetop;

		// Loop on each lines to detect if there is at least one image to show
		$realpatharray=array();
		$this->atleastonephoto = false;
		if (! empty($conf->global->MAIN_GENERATE_ORDERS_WITH_PICTURE))
		{
			$objphoto = new Product($this->db);

			for ($i = 0 ; $i < $nblines ; $i++)
			{
				if (empty($object->lines[$i]->fk_product)) continue;

				$objphoto->fetch($object->lines[$i]->fk_product);
				//var_dump($objphoto->ref);exit;
				if (! empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO))
				{
					$pdir[0] = get_exdir($objphoto->id, 2, 0, 0, $objphoto, 'product') . $objphoto->id ."/photos/";
					$pdir[1] = get_exdir(0, 0, 0, 0, $objphoto, 'product') . dol_sanitizeFileName($objphoto->ref).'/';
				}
				else
				{
					$pdir[0] = get_exdir(0, 0, 0, 0, $objphoto, 'product') . dol_sanitizeFileName($objphoto->ref).'/';				// default
					$pdir[1] = get_exdir($objphoto->id, 2, 0, 0, $objphoto, 'product') . $objphoto->id ."/photos/";	// alternative
				}

				$arephoto = false;
				foreach ($pdir as $midir)
				{
					if (! $arephoto)
					{
						$dir = $conf->product->dir_output.'/'.$midir;

						foreach ($objphoto->liste_photos($dir, 1) as $key => $obj)
						{
							if (empty($conf->global->CAT_HIGH_QUALITY_IMAGES))		// If CAT_HIGH_QUALITY_IMAGES not defined, we use thumb if defined and then original photo
							{
								if ($obj['photo_vignette'])
								{
									$filename = $obj['photo_vignette'];
								}
								else
								{
									$filename = $obj['photo'];
								}
							}
							else
							{
								$filename = $obj['photo'];
							}

							$realpath = $dir.$filename;
							$arephoto = true;
							$this->atleastonephoto = true;
						}
					}
				}

				if ($realpath && $arephoto) $realpatharray[$i] = $realpath;
			}
		}



		if ($conf->operationorder->dir_output)
		{
			$object->fetch_thirdparty();

			$deja_regle = 0;

			// Definition of $dir and $file
			if ($object->specimen)
			{
				$dir = $conf->operationorder->multidir_output[$conf->entity];
				$file = $dir . "/SPECIMEN.pdf";
			}
			else
			{
				$objectref = dol_sanitizeFileName($object->ref);
				$dir = $conf->operationorder->multidir_output[$object->entity] . "/" . $objectref;
				$file = $dir . "/" . $objectref . ".pdf";
			}

			if (! file_exists($dir))
			{
				if (dol_mkdir($dir) < 0)
				{
					$this->error=$langs->transnoentities("ErrorCanNotCreateDir", $dir);
					return 0;
				}
			}

			if (file_exists($dir))
			{
				// Add pdfgeneration hook
				if (!is_object($hookmanager))
				{
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager = new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

				// Create pdf instance
				$pdf = pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs); // Must be after pdf_getInstance
				$pdf->SetAutoPageBreak(1, 0);

				$heightforinfotot = 0; // Height reserved to output the info and total part
				$heightforfreetext = (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT) ? $conf->global->MAIN_PDF_FREETEXT_HEIGHT : 5); // Height reserved to output the free text on last page
				$heightforfooter = $this->marge_basse + (empty($conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS) ? 5 : 10); // Height reserved to output the footer (value include bottom margin)

				if (class_exists('TCPDF'))
				{
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));
				// Set path to the background PDF File
				if (! empty($conf->global->MAIN_ADD_PDF_BACKGROUND))
				{
					$pagecount = $pdf->setSourceFile($conf->mycompany->multidir_output[$object->entity].'/'.$conf->global->MAIN_ADD_PDF_BACKGROUND);
					$tplidx = $pdf->importPage(1);
				}

				$pdf->Open();
				$pagenb=0;
				$pdf->SetDrawColor(128, 128, 128);

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("PdfOrderTitle"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("PdfOrderTitle")." ".$outputlangs->convToOutputCharset($object->thirdparty->name));
				if (!empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) $pdf->SetCompression(false);

				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right

				/// Does we have at least one line with discount $this->atleastonediscount
				foreach ($object->lines as $line) {
					if ($line->remise_percent) {
						$this->atleastonediscount = true;
						break;
					}
				}


				// New page
				$pdf->AddPage();
				if (! empty($tplidx)) $pdf->useTemplate($tplidx);
				$pagenb++;
				$top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs);
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->MultiCell(0, 3, '');		// Set interline to 3
				$pdf->SetTextColor(0, 0, 0);


				$tab_top = 28+$top_shift;
				$tab_top_newpage = 28+$top_shift;

				// Affiche notes
				$notetoshow=empty($object->note_public)?'':$object->note_public;
				if (! empty($conf->global->MAIN_ADD_SALE_REP_SIGNATURE_IN_NOTE))
				{
					// Get first sale rep
					if (is_object($object->thirdparty))
					{
						$salereparray=$object->thirdparty->getSalesRepresentatives($user);
						$salerepobj=new User($this->db);
						$salerepobj->fetch($salereparray[0]['id']);
						if (! empty($salerepobj->signature)) $notetoshow=dol_concatdesc($notetoshow, $salerepobj->signature);
					}
				}

				$pagenb = $pdf->getPage();

				if ($notetoshow)
				{
					$backupCellPaddings = $pdf->getCellPaddings();
					$pdf->setCellPaddings(2, 1, 2, 1);

					$tab_width = $this->page_largeur-$this->marge_gauche-$this->marge_droite;
					$pageposbeforenote = $pagenb;

					$substitutionarray=pdf_getSubstitutionArray($outputlangs, null, $object);
					complete_substitutions_array($substitutionarray, $outputlangs, $object);
					$notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);
					$notetoshow = convertBackOfficeMediasLinksToPublicLinks($notetoshow);

					$tab_top -= 2;

					$pdf->startTransaction();

					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
					// Description
					$pageposafternote = $pdf->getPage();
					$posyafter = $pdf->GetY();

					if ($pageposafternote > $pageposbeforenote)
					{
						$pdf->rollbackTransaction(true);

						// prepare pages to receive notes
						while ($pagenb < $pageposafternote) {
							$pdf->AddPage();
							$pagenb++;
							if (!empty($tplidx)) $pdf->useTemplate($tplidx);
							if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 1, $outputlangs);
							// $this->_pagefoot($pdf,$object,$outputlangs,1);

							if(empty($hidetopNewPage)){
								$pdf->setTopMargin($tab_top_newpage + $this->tabTitleHeight);
							}
							else{
								$pdf->setTopMargin($tab_top_newpage);
							}
							// The only function to edit the bottom margin of current page to set it.
							$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
						}

						// back to start
						$pdf->setPage($pageposbeforenote);
						$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
						$pdf->SetFont('', '', $default_font_size - 1);
						$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
						$pageposafternote = $pdf->getPage();

						$posyafter = $pdf->GetY();

						if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + 20)))	// There is no space left for total+free text
						{
							$pdf->AddPage('', '', true);
							$pagenb++;
							$pageposafternote++;
							$pdf->setPage($pageposafternote);
							if(empty($hidetopNewPage)){
								$pdf->setTopMargin($tab_top_newpage + $this->tabTitleHeight);
							}
							else{
								$pdf->setTopMargin($tab_top_newpage);
							}
							// The only function to edit the bottom margin of current page to set it.
							$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
							//$posyafter = $tab_top_newpage;
						}


						// apply note frame to previous pages
						$i = $pageposbeforenote;
						while ($i < $pageposafternote) {
							$pdf->setPage($i);


							$pdf->SetDrawColor(128, 128, 128);
							// Draw note frame
							if ($i > $pageposbeforenote) {
								$height_note = $this->page_hauteur - ($tab_top_newpage + $heightforfooter);
								$pdf->Rect($this->marge_gauche, $tab_top_newpage, $tab_width, $height_note);
							}
							else {
								$height_note = $this->page_hauteur - ($tab_top + $heightforfooter);
								$pdf->Rect($this->marge_gauche, $tab_top, $tab_width, $height_note);
							}

							// Add footer
							$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
							$this->_pagefoot($pdf, $object, $outputlangs, 1);

							$i++;
						}

						// apply note frame to last page
						$pdf->setPage($pageposafternote);
						if (!empty($tplidx)) $pdf->useTemplate($tplidx);
						if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 1, $outputlangs);
						$height_note = $posyafter - $tab_top_newpage;
						$pdf->Rect($this->marge_gauche, $tab_top_newpage, $tab_width, $height_note);
					}
					else // No pagebreak
					{
						$pdf->commitTransaction();
						$posyafter = $pdf->GetY();
						$height_note = $posyafter - $tab_top;
						$pdf->Rect($this->marge_gauche, $tab_top, $tab_width, $height_note);


						if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + 20)))
						{
							// not enough space, need to add page
							$pdf->AddPage('', '', true);
							$pagenb++;
							$pageposafternote++;
							$pdf->setPage($pageposafternote);
							if (!empty($tplidx)) $pdf->useTemplate($tplidx);
							if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 1, $outputlangs);

							$posyafter = $tab_top_newpage;
						}
					}

					$tab_height = $tab_height - $height_note;
					$tab_top = $posyafter + 6;

					$pdf->setCellPaddings($backupCellPaddings['L'], $backupCellPaddings['T'], $backupCellPaddings['R'], $backupCellPaddings['B']);
				}
				else
				{
					$height_note = 0;
				}


				// Use new auto column system
				$this->prepareArrayColumnField($object, $outputlangs, $hidedetails, $hidedesc, $hideref);

				// tab simulation to know line height
				$pdf->startTransaction();
				$this->pdfTabTitles($pdf, $tab_top, $tab_height, $outputlangs, $hidetop);
				$pdf->rollbackTransaction(true);

				$iniY = $tab_top + $this->tabTitleHeight + 2;
				$curY = $tab_top + $this->tabTitleHeight + 2;
				$nexY = $tab_top + $this->tabTitleHeight + 2;

				// Loop on each lines
				$pageposbeforeprintlines=$pdf->getPage();
				$pagenb = $pageposbeforeprintlines;

				for ($i = 0 ; $i < $nblines ; $i++)
				{
					$curY = $nexY;
					$pdf->SetFont('', '', $default_font_size - 1);   // Into loop to work with multipage
					$pdf->SetTextColor(0, 0, 0);

					// Define size of image if we need it
					$imglinesize=array();
					if (! empty($realpatharray[$i])) $imglinesize=pdf_getSizeForImage($realpatharray[$i]);

					if(empty($hidetopNewPage)){
						$pdf->setTopMargin($tab_top_newpage + $this->tabTitleHeight);
					}
					else{
						$pdf->setTopMargin($tab_top_newpage);
					}
					$pdf->setPageOrientation('', 1, $heightforfooter+$heightforfreetext+$heightforinfotot);	// The only function to edit the bottom margin of current page to set it.
					$pageposbefore=$pdf->getPage();

					// Description of product line
					$curX = $this->posxdesc-1;

					$showpricebeforepagebreak=1;
					$posYAfterImage=0;
					$posYAfterDescription=0;

					if($this->getColumnStatus('photo'))
					{
						// We start with Photo of product line
						if (isset($imglinesize['width']) && isset($imglinesize['height']) && ($curY + $imglinesize['height']) > ($this->page_hauteur-($heightforfooter+$heightforfreetext+$heightforinfotot)))	// If photo too high, we moved completely on new page
						{
							$pdf->AddPage('', '', true);
							if (! empty($tplidx)) $pdf->useTemplate($tplidx);
							$pdf->setPage($pageposbefore+1);

							$curY = $tab_top_newpage;

							// Allows data in the first page if description is long enough to break in multiples pages
							if(!empty($conf->global->MAIN_PDF_DATA_ON_FIRST_PAGE))
								$showpricebeforepagebreak = 1;
							else
								$showpricebeforepagebreak = 0;
						}

						if (!empty($this->cols['photo']) && isset($imglinesize['width']) && isset($imglinesize['height']))
						{
							$pdf->Image($realpatharray[$i], $this->getColumnContentXStart('photo'), $curY, $imglinesize['width'], $imglinesize['height'], '', '', '', 2, 300);	// Use 300 dpi
							// $pdf->Image does not increase value return by getY, so we save it manually
							$posYAfterImage=$curY+$imglinesize['height'];
						}
					}

					if($this->getColumnStatus('desc'))
					{
						$pdf->startTransaction();
						self::pdf_writelinedesc($pdf, $object, $i, $outputlangs, $this->getColumnContentWidth('desc'), 3, $this->getColumnContentXStart('desc'), $curY, $hideref, $hidedesc);
						$pageposafter=$pdf->getPage();
						if ($pageposafter > $pageposbefore)	// There is a pagebreak
						{
							$pdf->rollbackTransaction(true);
							$pageposafter=$pageposbefore;
							//print $pageposafter.'-'.$pageposbefore;exit;
							$pdf->setPageOrientation('', 1, $heightforfooter);	// The only function to edit the bottom margin of current page to set it.
							self::pdf_writelinedesc($pdf, $object, $i, $outputlangs, $this->getColumnContentWidth('desc'), 3, $this->getColumnContentXStart('desc'), $curY, $hideref, $hidedesc);
							$pageposafter=$pdf->getPage();
							$posyafter=$pdf->GetY();
							if ($posyafter > ($this->page_hauteur - ($heightforfooter+$heightforfreetext+$heightforinfotot)))	// There is no space left for total+free text
							{
								if ($i == ($nblines-1))	// No more lines, and no space left to show total, so we create a new page
								{
									$pdf->AddPage('', '', true);
									if (! empty($tplidx)) $pdf->useTemplate($tplidx);
									if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 1, $outputlangs);
									$pdf->setPage($pageposafter+1);
								}
							}
							else
							{
								// We found a page break

								// Allows data in the first page if description is long enough to break in multiples pages
								if(!empty($conf->global->MAIN_PDF_DATA_ON_FIRST_PAGE))
									$showpricebeforepagebreak = 1;
								else
									$showpricebeforepagebreak = 0;
							}
						}
						else	// No pagebreak
						{
							$pdf->commitTransaction();
						}
						$posYAfterDescription=$pdf->GetY();
					}



					$nexY = max($pdf->GetY(), $posYAfterImage);


					$pageposafter=$pdf->getPage();

					$pdf->setPage($pageposbefore);
					$pdf->setTopMargin($this->marge_haute);

					$pdf->setPageOrientation('', 1, 0);	// The only function to edit the bottom margin of current page to set it.

					// We suppose that a too long description is moved completely on next page
					if ($pageposafter > $pageposbefore && empty($showpricebeforepagebreak)) {
						$pdf->setPage($pageposafter);
						$curY = $tab_top_newpage;
						if(empty($hidetopNewPage)){
							$curY+= $this->tabTitleHeight;
						}
					}

					$pdf->SetFont('', '', $default_font_size - 1);   // We reposition the default font

					// EAN
					if ($this->getColumnStatus('ean')) {

						if(!empty($object->lines[$i]->fk_product)){
							if(!isset($this->cacheOrScan[$object->lines[$i]->fk_product]))
							{
								$product = new Product($db);
								$product->fetch($object->lines[$i]->fk_product);
								$this->cacheOrScan[$object->lines[$i]->fk_product] = !empty($product->array_options['options_or_scan']);
							}
						}


						if(empty($object->lines[$i]->fk_product) || empty($this->cacheOrScan[$object->lines[$i]->fk_product]))
						{
							if(empty($object->lines[$i]->product_type)) {
								$this->printStdColumnContent($pdf, $curY, 'ean', $object->lines[$i]->ref);
								$nexY = max($pdf->GetY(), $nexY);
							}
						}
						else
						{
							// type : $type (string) type of barcode:
							// C39 : CODE 39 - ANSI MH10.8M-1983 - USD-3 - 3 of 9.
							//C39+ : CODE 39 with checksum
							//C39E : CODE 39 EXTENDED
							//C39E+ : CODE 39 EXTENDED + CHECKSUM
							//C93 : CODE 93 - USS-93
							//S25 : Standard 2 of 5
							//S25+ : Standard 2 of 5 + CHECKSUM
							//I25 : Interleaved 2 of 5
							//I25+ : Interleaved 2 of 5 + CHECKSUM
							//C128 : CODE 128
							//C128A : CODE 128 A
							//C128B : CODE 128 B
							//C128C : CODE 128 C
							//EAN2 : 2-Digits UPC-Based Extension
							//EAN5 : 5-Digits UPC-Based Extension
							//EAN8 : EAN 8
							//EAN13 : EAN 13
							//UPCA : UPC-A
							//UPCE : UPC-E
							//MSI : MSI (Variation of Plessey code)
							//MSI+ : MSI + CHECKSUM (modulo 11)
							//POSTNET : POSTNET
							//PLANET : PLANET
							//RMS4CC : RMS4CC (Royal Mail 4-state Customer Code) - CBC (Customer Bar Code)
							//KIX : KIX (Klant index - Customer index)
							//IMB: Intelligent Mail Barcode - Onecode - USPS-B-3200
							//CODABAR : CODABAR
							//CODE11 : CODE 11
							//PHARMA : PHARMACODE
							//PHARMA2T : PHARMACODE TWO-TRACKS

							//var_dump($object->lines[$i]->id);exit;

							$colDef = $this->cols['ean'];
							$barCodeHeight = $colDef['height'];

							$style= array(
								'text'	   => true,
								'fontsize' => 4,
								'hpadding' => 5,
	 							'vpadding' => 0.1,
							);

							$code = $object->lines[$i]->id;
							$barcodetype=empty($conf->global->OPERATION_ORDER_BARCODE_TYPE)?'C128':$conf->global->OPERATION_ORDER_BARCODE_TYPE;
							$pdf->write1DBarcode('LIG'.$code, $barcodetype, $this->getColumnContentXStart('ean'), $curY, $this->getColumnContentWidth('ean'), $barCodeHeight, '', $style);

							$nexY+=$barCodeHeight;
						}
					}

					// VAT Rate
					if ($this->getColumnStatus('vat'))
					{
						$vat_rate = pdf_getlinevatrate($object, $i, $outputlangs, $hidedetails);
						$this->printStdColumnContent($pdf, $curY, 'vat', $vat_rate);
						$nexY = max($pdf->GetY(), $nexY);
					}

					// Unit price before discount
					if ($this->getColumnStatus('subprice'))
					{
						$up_excl_tax = pdf_getlineupexcltax($object, $i, $outputlangs, $hidedetails);
						$this->printStdColumnContent($pdf, $curY, 'subprice', $up_excl_tax);
						$nexY = max($pdf->GetY(), $nexY);
					}

					// Warehouse
					if ($this->getColumnStatus('warehouse'))
					{
						$warehouse = $object->lines[$i]->showOutputFieldQuick('fk_warehouse');
						$warehouse = dol_string_nohtmltag($warehouse);
						$this->printStdColumnContent($pdf, $curY, 'warehouse', $warehouse);
						$nexY = max($pdf->GetY(), $nexY);
					}

					// Quantity
					// Enough for 6 chars
					if ($this->getColumnStatus('qty'))
					{
						$qty = pdf_getlineqty($object, $i, $outputlangs, $hidedetails);
						$this->printStdColumnContent($pdf, $curY, 'qty', $qty);
						$nexY = max($pdf->GetY(), $nexY);
					}


					// Unit
					if ($this->getColumnStatus('unit'))
					{
						$unit = pdf_getlineunit($object, $i, $outputlangs, $hidedetails, $hookmanager);
						$this->printStdColumnContent($pdf, $curY, 'unit', $unit);
						$nexY = max($pdf->GetY(), $nexY);
					}

					// Discount on line
					if ($this->getColumnStatus('discount') && $object->lines[$i]->remise_percent)
					{
						$remise_percent = pdf_getlineremisepercent($object, $i, $outputlangs, $hidedetails);
						$this->printStdColumnContent($pdf, $curY, 'discount', $remise_percent);
						$nexY = max($pdf->GetY(), $nexY);
					}

					// Total HT line
					if ($this->getColumnStatus('totalexcltax'))
					{
						$total_excl_tax = pdf_getlinetotalexcltax($object, $i, $outputlangs, $hidedetails);
						$this->printStdColumnContent($pdf, $curY, 'totalexcltax', $total_excl_tax);
						$nexY = max($pdf->GetY(), $nexY);
					}

					// time
					if ($this->getColumnStatus('timeplanned'))
					{
						$timeplanned = $object->lines[$i]->showOutputFieldQuick('time_planned');
						$this->printStdColumnContent($pdf, $curY, 'timeplanned', $timeplanned);
						$nexY = max($pdf->GetY(), $nexY);
					}

					// time et qty
					if ($this->getColumnStatus('timeplannedqty'))
					{
						if($object->lines[$i]->time_planned > 0){
							$colcontent = $object->lines[$i]->showOutputFieldQuick('time_planned');
						}
						else{
							$colcontent = pdf_getlineqty($object, $i, $outputlangs, $hidedetails);
						}

						$this->printStdColumnContent($pdf, $curY, 'timeplannedqty', $colcontent);
						$nexY = max($pdf->GetY(), $nexY);
					}

					$parameters = array(
						'object' => $object,
						'i' => $i,
						'pdf' =>& $pdf,
						'curY' =>& $curY,
						'nexY' =>& $nexY,
						'outputlangs' => $outputlangs,
						'hidedetails' => $hidedetails
					);
					$reshook=$hookmanager->executeHooks('printPDFline', $parameters, $this);    // Note that $object may have been modified by hook


					// Collection of totals by value of vat in $this->tva["rate"] = total_tva
					if ($conf->multicurrency->enabled && $object->multicurrency_tx != 1) $tvaligne=$object->lines[$i]->multicurrency_total_tva;
					else $tvaligne=$object->lines[$i]->total_tva;

					$localtax1ligne=$object->lines[$i]->total_localtax1;
					$localtax2ligne=$object->lines[$i]->total_localtax2;
					$localtax1_rate=$object->lines[$i]->localtax1_tx;
					$localtax2_rate=$object->lines[$i]->localtax2_tx;
					$localtax1_type=$object->lines[$i]->localtax1_type;
					$localtax2_type=$object->lines[$i]->localtax2_type;

					if ($object->remise_percent) $tvaligne-=($tvaligne*$object->remise_percent)/100;
					if ($object->remise_percent) $localtax1ligne-=($localtax1ligne*$object->remise_percent)/100;
					if ($object->remise_percent) $localtax2ligne-=($localtax2ligne*$object->remise_percent)/100;

					$vatrate=(string) $object->lines[$i]->tva_tx;

					// Retrieve type from database for backward compatibility with old records
					if ((! isset($localtax1_type) || $localtax1_type=='' || ! isset($localtax2_type) || $localtax2_type=='') // if tax type not defined
						&& (! empty($localtax1_rate) || ! empty($localtax2_rate))) // and there is local tax
					{
						$localtaxtmp_array=getLocalTaxesFromRate($vatrate, 0, $object->thirdparty, $mysoc);
						$localtax1_type = $localtaxtmp_array[0];
						$localtax2_type = $localtaxtmp_array[2];
					}

					// retrieve global local tax
					if ($localtax1_type && $localtax1ligne != 0)
						$this->localtax1[$localtax1_type][$localtax1_rate]+=$localtax1ligne;
					if ($localtax2_type && $localtax2ligne != 0)
						$this->localtax2[$localtax2_type][$localtax2_rate]+=$localtax2ligne;

					if (($object->lines[$i]->info_bits & 0x01) == 0x01) $vatrate.='*';
					if (! isset($this->tva[$vatrate])) 				$this->tva[$vatrate]=0;
					$this->tva[$vatrate] += $tvaligne;

					// Add line
					if (! empty($conf->global->MAIN_PDF_DASH_BETWEEN_LINES) && $i < ($nblines - 1))
					{
						$pdf->setPage($pageposafter);

						$defaultColor = array(0,0,0);

						$pdf->SetLineStyle(array('dash'=>'1,1','color'=>$defaultColor));
						if($object->lines[$i+1]->level > $object->lines[$i]->level)
						{
							$levelColor = min($object->lines[$i]->level * 80, 255);

							if(!empty($object->lines[$i]->level)){
								$dash = max(5- $object->lines[$i]->level*2 , 0);
							}else{
								$dash = '0';
								$levelColor = 80;
							}

							$pdf->SetLineStyle(array('dash'=>$dash,'color'=>array($levelColor,$levelColor,$levelColor)));
						}
						elseif(empty($object->lines[$i+1]->level))
						{
							$pdf->SetLineStyle(array('dash'=>'0','width'=> 0.4,'color'=>array(0,0,0)));
						}
						elseif($object->lines[$i+1]->level < $object->lines[$i]->level)
						{
							$levelColor = min($object->lines[$i+1]->level * 80, 255);

							if(!empty($object->lines[$i+1]->level)){
								$dash = max(5-$object->lines[$i+1]->level*2, 0);
							}else{
								$dash = '0';
							}

							$pdf->SetLineStyle(array('dash'=>$dash,'color'=>array($levelColor,$levelColor,$levelColor)));
						}
						elseif(empty($object->lines[$i]->level))
						{
							$pdf->SetLineStyle(array('dash'=>'0','width'=> 0.4,'color'=>array(0,0,0)));
						}
						elseif($object->lines[$i+1]->level == $object->lines[$i]->level)
						{
							$dash = max(5-$object->lines[$i+1]->level , 1);
							$levelColor = min($object->lines[$i]->level * 80, 255);
							if(!empty($dash)){
								$dash = $dash.',1';
							}

							$pdf->SetLineStyle(array('dash'=>$dash,'color'=>array($levelColor,$levelColor,$levelColor)));
						}

						//$pdf->SetDrawColor(190,190,200);
						$pdf->line($this->marge_gauche, $nexY+1, $this->page_largeur - $this->marge_droite, $nexY+1);
						//$pdf->SetLineStyle(array('dash'=>0));

						// go back to normal
						$pdf->SetLineStyle(array('dash'=>'1,1','width'=> 0.2 , 'color'=>$defaultColor));
					}

					$nexY+=2;    // Add space between lines

					// Detect if some page were added automatically and output _tableau for past pages
					while ($pagenb < $pageposafter)
					{
						$pdf->setPage($pagenb);
						if ($pagenb == $pageposbeforeprintlines)
						{
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, $hidetop, 1, $object->multicurrency_code);
						}
						else
						{
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, $hidetopNewPage, 1, $object->multicurrency_code);
						}
						$this->_pagefoot($pdf, $object, $outputlangs, 1);
						$pagenb++;
						$pdf->setPage($pagenb);
						$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
						if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 1, $outputlangs);
					}
					if (isset($object->lines[$i + 1]->pagebreak) && $object->lines[$i + 1]->pagebreak)
					{
						if ($pagenb == $pageposafter)
						{
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, $hidetop, 1, $object->multicurrency_code);
						}
						else
						{
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, $hidetopNewPage, 1, $object->multicurrency_code);
						}
						$this->_pagefoot($pdf, $object, $outputlangs, 1);
						// New page
						$pdf->AddPage();
						if (!empty($tplidx)) $pdf->useTemplate($tplidx);
						$pagenb++;
						if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 1, $outputlangs);
					}
				}

				// Show square
				if ($pagenb == $pageposbeforeprintlines)
					$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, $hidetop, 0, $object->multicurrency_code);
				else
					$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, $hidetopNewPage, 0, $object->multicurrency_code);
				$bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;

				// Affiche zone infos
				$posy = $this->drawInfoTable($pdf, $object, $bottomlasttab, $outputlangs);

				// Affiche zone totaux
				$posy = $this->drawTotalTable($pdf, $object, $deja_regle, $bottomlasttab, $outputlangs);

				// Affiche zone versements
				/*
				if ($deja_regle)
				{
					$posy=$this->drawPaymentsTable($pdf, $object, $posy, $outputlangs);
				}
				*/

				// Pied de page
				$this->_pagefoot($pdf, $object, $outputlangs);
				if (method_exists($pdf, 'AliasNbPages')) $pdf->AliasNbPages();

				$pdf->Close();

				$pdf->Output($file, 'F');

				// Add pdfgeneration hook
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters=array('file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs);
				global $action;
				$reshook=$hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);    // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0)
				{
					$this->error = $hookmanager->error;
					$this->errors = $hookmanager->errors;
				}

				if (! empty($conf->global->MAIN_UMASK))
					@chmod($file, octdec($conf->global->MAIN_UMASK));

				$this->result = array('fullpath'=>$file);

				return 1;   // No error
			}
			else
			{
				$this->error=$langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		}
		else
		{
			$this->error=$langs->transnoentities("ErrorConstantNotDefined", "COMMANDE_OUTPUTDIR");
			return 0;
		}
	}

	/**
	 *  Show payments table
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Object		$object			Object order
	 *	@param	int			$posy			Position y in PDF
	 *	@param	Translate	$outputlangs	Object langs for output
	 *	@return int							<0 if KO, >0 if OK
	 */
	protected function drawPaymentsTable(&$pdf, $object, $posy, $outputlangs)
	{
	}

	/**
	 *   Show miscellaneous information (payment mode, payment term, ...)
	 *
	 *   @param		TCPDF		$pdf     		Object PDF
	 *   @param		Object		$object			Object to show
	 *   @param		int			$posy			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @return	void
	 */
	protected function drawInfoTable(&$pdf, $object, $posy, $outputlangs)
	{
		global $conf;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetFont('', '', $default_font_size - 1);



		return $posy;
	}


	/**
	 *	Show total to pay
	 *
	 *	@param	TCPDF		$pdf           Object PDF
	 *	@param  Facture		$object         Object invoice
	 *	@param  int			$deja_regle     Montant deja regle
	 *	@param	int			$posy			Position depart
	 *	@param	Translate	$outputlangs	Objet langs
	 *	@return int							Position pour suite
	 */
	protected function drawTotalTable(&$pdf, $object, $deja_regle, $posy, $outputlangs)
	{
		global $conf, $mysoc;

		$default_font_size = pdf_getPDFFontSize($outputlangs);


		return $posy;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   Show table for lines
	 *
	 *   @param		TCPDF		$pdf     		Object PDF
	 *   @param		string		$tab_top		Top position of table
	 *   @param		string		$tab_height		Height of table (rectangle)
	 *   @param		int			$nexY			Y (not used)
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		int			$hidetop		1=Hide top bar of array and title, 0=Hide nothing, -1=Hide only title
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @param		string		$currency		Currency code
	 *   @return	void
	 */
	protected function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0, $currency = '')
	{
		global $conf;

		// Force to disable hidetop and hidebottom
		$hidebottom = 0;
		if ($hidetop) $hidetop = -1;
		$pdf->SetLineStyle(array('dash'=>'0','width'=> 0.2));
		$currency = !empty($currency) ? $currency : $conf->currency;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Amount in (at tab_top - 1)
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $default_font_size - 2);

		if (empty($hidetop))
		{
			//$conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR='230,230,230';
			if (! empty($conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR)) $pdf->Rect($this->marge_gauche, $tab_top, $this->page_largeur-$this->marge_droite-$this->marge_gauche, 5, 'F', null, explode(',', $conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR));
		}

		$pdf->SetDrawColor(0, 0, 0);
		$pdf->SetFont('', '', $default_font_size - 1);

		// Output Rect
		$this->printRect($pdf, $this->marge_gauche, $tab_top, $this->page_largeur-$this->marge_gauche-$this->marge_droite, $tab_height, $hidetop, $hidebottom);	// Rect takes a length in 3rd parameter and 4th parameter


		$this->pdfTabTitles($pdf, $tab_top, $tab_height, $outputlangs, $hidetop);

		if (empty($hidetop)){
			$pdf->line($this->marge_gauche, $tab_top+$this->tabTitleHeight, $this->page_largeur-$this->marge_droite, $tab_top+$this->tabTitleHeight);	// line takes a position y in 2nd parameter and 4th parameter
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show top header of page.
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Object		$object     	Object to show
	 *  @param  int	    	$showHeadDetails    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @param	string		$titlekey		Translation key to show as title of document
	 *  @return	void
	 */
	protected function _pagehead(&$pdf, $object, $showHeadDetails, $outputlangs, $titlekey = "PdfCustomOrderTitle")
	{
		// phpcs:enable
		global $conf, $langs, $hookmanager, $db;

		/**
		 * @var $object OperationOrder
		 */

		// Translations
		$outputlangs->loadLangs(array("main", "bills", "propal", "orders", "companies"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		// Show Draft Watermark
		if ($object->statut == 0 && (!empty($conf->global->COMMANDE_DRAFT_WATERMARK)))
		{
			pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', $conf->global->COMMANDE_DRAFT_WATERMARK);
		}

		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$posy=$this->marge_haute;
		$posx=$this->page_largeur-$this->marge_droite-100;

		$pdf->SetXY($this->marge_gauche, $posy);


		$barCodeHeight = 10;
		$style= array(
			'text'=> true,
			'fontsize'=>4
		);
		$barcodetype=empty($conf->global->OPERATION_ORDER_BARCODE_TYPE)?'C128':$conf->global->OPERATION_ORDER_BARCODE_TYPE;
		$pdf->write1DBarcode('OR'.$object->id, $barcodetype, $this->marge_gauche, $posy, 50, $barCodeHeight, '', $style);

		// Multicompagny
		if (!empty($conf->multicompany->enabled) && $object->entity > 0) {

			$daoMulticompany = new DaoMulticompany($this->db);
			$daoMulticompany->fetch($object->entity);

			$pdf->SetXY($this->page_largeur / 2 - 50, $posy);
			$pdf->SetFont('', '', $default_font_size);
			$entite = $outputlangs->transnoentities('PlaceOfEntitie', $daoMulticompany->name);
			$pdf->MultiCell(100, 3, $entite, '', 'C');
		}

		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 0);
		$title = $outputlangs->transnoentities($titlekey);
		$pdf->MultiCell(100, 3, $title, '', 'R');

		$pdf->SetFont('', 'B', $default_font_size);

		$posy += 5;
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->MultiCell(100, 4, $outputlangs->transnoentities("Ref")." : ".$outputlangs->convToOutputCharset($object->ref), '', 'R');

		$posy += 1;
		$pdf->SetFont('', '', $default_font_size - 1);

		if ($object->ref_client)
		{
			$posy += 5;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("RefCustomer")." : ".$outputlangs->convToOutputCharset($object->ref_client), '', 'R');
		}

		$posy += 4;
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->MultiCell(100, 3, $outputlangs->transnoentities("OperationOrderDate")." : ".dol_print_date($object->date_operation_order, "%d %b %Y", false, $outputlangs, true), '', 'R');

		// Get contact
		if (!empty($conf->global->DOC_SHOW_FIRST_SALES_REP))
		{
			$arrayidcontact = $object->getIdContact('internal', 'SALESREPFOLL');
			if (count($arrayidcontact) > 0)
			{
				$usertmp = new User($this->db);
				$usertmp->fetch($arrayidcontact[0]);
				$posy += 4;
				$pdf->SetXY($posx, $posy);
				$pdf->SetTextColor(0, 0, 0);
				$pdf->MultiCell(100, 3, $langs->trans("SalesRepresentative")." : ".$usertmp->getFullName($langs), '', 'R');
			}
		}

		$posyBefore = $pdf->getY();
		$pdf->SetTextColor(0, 0, 0);

		if ($showHeadDetails)
		{
			// If CUSTOMER contact defined on order, we use it
			$usecontact = false;
			$arrayidcontact = $object->getIdContact('external', 'CUSTOMER');
			if (count($arrayidcontact) > 0)
			{
				$usecontact = true;
				$result = $object->fetch_contact($arrayidcontact[0]);
			}

			//Recipient name
			// On peut utiliser le nom de la societe du contact
			if ($usecontact && !empty($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT)) {
				$thirdparty = $object->contact;
			} else {
				$thirdparty = $object->thirdparty;
			}

			$carac_client_name = pdfBuildThirdpartyName($thirdparty, $outputlangs);
			$vehicule = new doliFleetVehicule($db);
			if($object->array_options['options_fk_dolifleet_vehicule']){
				if($vehicule->fetch($object->array_options['options_fk_dolifleet_vehicule'])<=0){
					$vehicule->ref = $langs->trans('VehiculeNotFound');
				}
			}

			$posy = 28;

			$langs->load('dolifleet@dolifleet');
			dol_include_once('dolifleet/class/vehicule.class.php');
			$vehicule = new doliFleetVehicule($db); // use to get table of element
			$res = $vehicule->fetch($object->array_options['options_fk_dolifleet_vehicule']);
			if($res<=0) {
				$vehicule->ref = $langs->trans('VehiculeNotFound');
			}
			else{
				$vehicule->getActivities($object->date_operation_order, $object->date_operation_order);
			}

			$extraFields = new ExtraFields($this->db);
			$extraFields->fetch_name_optionals_label($object->table_element);

			$outInfos = '<table>';

			$outInfos.= '<tr>';
			$outInfos.= '<td colspan="2" class="titlefield"  ><strong>'.$carac_client_name.'</strong></td>';

			$outInfos.= '<td >&nbsp;</td>';

			$outInfos.= '<td class="titlefield"  >'.$langs->trans('Type').'</td>';
			$outInfos.= '<td ><strong>'.$object->showOutputFieldQuick('fk_c_operationorder_type').'</strong></td>';

			$outInfos.= '</tr>';

			$outInfos.= '<tr>';

			$outInfos.= '<td class="titlefield"  >'.$langs->trans('Phone').'</td>';
			$outInfos.= '<td ><strong>'.$thirdparty->phone.'</strong></td>';

			$outInfos.= '<td >&nbsp;</td>';

			$outInfos.= '<td class="titlefield"  >'.$langs->trans('Receptionnaire').'</td>';
			$outInfos.= '<td ><strong>'.dol_string_nohtmltag($object->showOutputFieldQuick('fk_user_creat')).'</strong></td>';

			$outInfos.= '</tr>';


			$outInfos.= '<tr>';

			$outInfos.= '<td class="titlefield">'.$langs->trans('ActivityType').'</td>';
			$outInfos.= '<td >';

			if(!empty($vehicule->activities)){
				$activities = array();
				foreach ($vehicule->activities as $activity){
					$activities[] = dol_string_nohtmltag($activity->getType());
				}
				$outInfos.= '<strong>'.implode(', ', $activities).'</strong>';
			}

			$outInfos.= '</td>';

			$outInfos.= '<td >&nbsp;</td>';

			$outInfos.= '<td class="titlefield"  >'.$langs->trans($object->fields['ref_client']['label']).'</td>';
			$outInfos.= '<td ><strong>'.dol_string_nohtmltag($object->showOutputFieldQuick('ref_client')).'</strong></td>';

			$outInfos.= '</tr>';


			$outInfos.= '<tr>';

			$outInfos.= '<td class="titlefield"  ></td>';
			$outInfos.= '<td ></td>';

			$outInfos.= '<td >&nbsp;</td>';

			$outInfos.= '<td class="titlefield"  >'.$outputlangs->transnoentities('PlannedDate').'</td>';
			$outInfos.= '<td ><strong>'.$extraFields->showOutputField('planned_date',$object->planned_date, '', $object->table_element).'</strong></td>';
			$outInfos.= '</tr>';


			$outInfos.= '</table>';


			$backupCellPaddings = $pdf->getCellPaddings();
			//$pdf->SetLineStyle(array('color' => array(80,80,80)));
			$pdf->SetDrawColor(0, 0, 0);
			$pdf->setCellPaddings(2, 2, 2, 2);
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->writeHTMLCell($this->page_largeur - $this->marge_droite - $this->marge_gauche, 3, $this->marge_gauche, $posy, dol_htmlentitiesbr($outInfos), 1, 1);
			$pdf->setCellPaddings($backupCellPaddings['L'], $backupCellPaddings['T'], $backupCellPaddings['R'], $backupCellPaddings['B']);
			$posy = $pdf->GetY() + 2;


			$outVehicule = '<table>';

			$outVehicule.= '<tr>';

			$outVehicule.= '<td colspan="2" class="titlefield"  ><strong>'.mb_strtoupper($langs->transnoentities('VehiculeInfos'), 'UTF-8').'</strong></td>';

			$outVehicule.= '<td >&nbsp;</td>';

			$input = 'fk_vehicule_type';
			$outVehicule.= '<td class="titlefield"  >'.$langs->trans($vehicule->fields[$input]['label']).'</td>';
			$outVehicule.= '<td ><strong>'.$vehicule->showOutputFieldQuick($input).'</strong></td>';

			$outVehicule.= '</tr>';
			$outVehicule.= '<tr>';

			$input = 'vin';
			$outVehicule.= '<td class="titlefield"  >'.$langs->trans($vehicule->fields[$input]['label']).'</td>';
			$outVehicule.= '<td ><strong>'.$vehicule->showOutputFieldQuick($input).'</strong></td>';

			$outVehicule.= '<td >&nbsp;</td>';

			$input = 'fk_vehicule_mark';
			$outVehicule.= '<td class="titlefield"  >'.$langs->trans($vehicule->fields[$input]['label']).'</td>';
			$outVehicule.= '<td ><strong>'.$vehicule->showOutputFieldQuick($input).'</strong></td>';

			$outVehicule.= '</tr>';
			$outVehicule.= '<tr>';

			$input = 'immatriculation';
			$outVehicule.= '<td class="titlefield"  >'.$langs->trans($vehicule->fields[$input]['label']).'</td>';
			$outVehicule.= '<td ><strong>'.$vehicule->showOutputFieldQuick($input).'</strong></td>';

			$outVehicule.= '<td >&nbsp;</td>';

			$input = 'fk_contract_type';
			$outVehicule.= '<td class="titlefield"  >'.$langs->trans($vehicule->fields[$input]['label']).'</td>';
			$outVehicule.= '<td ><strong>'.$vehicule->showOutputFieldQuick($input).'</strong></td>';

			$outVehicule.= '</tr>';
			$outVehicule.= '<tr>';

			$input = 'date_immat';
			$outVehicule.= '<td class="titlefield"  >'.$langs->trans('immatriculation_date_short').'</td>';
			$outVehicule.= '<td ><strong>'.$vehicule->showOutputFieldQuick($input).'</strong></td>';

			$outVehicule.= '<td >&nbsp;</td>';

			$input = 'date_end_contract';
			$outVehicule.= '<td class="titlefield"  >'.$langs->trans($vehicule->fields[$input]['label']).'</td>';
			$outVehicule.= '<td ><strong>'.$vehicule->showOutputFieldQuick($input).'</strong></td>';

			$outVehicule.= '</tr>';

			$outVehicule.= '<tr>';
			$outVehicule.= '<td class="titlefield"  ></td>';
			$outVehicule.= '<td ></td>';
			$outVehicule.= '<td >&nbsp;</td>';
			$outVehicule.= '<td class="titlefield"  >'.$outputlangs->transnoentities($extraFields->attributes[$object->table_element]['label']['km_on_creation']).'</td>';
			$outVehicule.= '<td ><strong>'.$extraFields->showOutputField('km_on_creation',$object->array_options['options_km_on_creation'], '', $object->table_element).'</strong></td>';
			$outVehicule.= '</tr>';


			$outVehicule.= '</table>';


			$pdf->SetFont('', '', $default_font_size - 1);
			$backupCellPaddings = $pdf->getCellPaddings();
			$pdf->setCellPaddings(2, 2, 2, 2);
			$pdf->writeHTMLCell($this->page_largeur - $this->marge_droite - $this->marge_gauche, 3, $this->marge_gauche, $posy, dol_htmlentitiesbr($outVehicule), 1, 1);
			$pdf->setCellPaddings($backupCellPaddings['L'], $backupCellPaddings['T'], $backupCellPaddings['R'], $backupCellPaddings['B']);

			$posy = $pdf->GetY() + 1;
			$pdf->setY($posy);
		}


		$posyAfter = $pdf->getY();

		return $posyAfter - $posyBefore;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   	Show footer of page. Need this->emetteur object
	 *
	 *   	@param	TCPDF		$pdf     			PDF
	 * 		@param	Object		$object				Object to show
	 *      @param	Translate	$outputlangs		Object lang for output
	 *      @param	int			$hidefreetext		1=Hide free text
	 *      @return	int								Return height of bottom margin including footer text
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		// phpcs:enable
		global $conf;
		$showdetails = $conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS;
		return pdf_pagefoot($pdf, $outputlangs, 'ORDER_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);
	}



	/**
	 *   	Define Array Column Field
	 *
	 *   	@param	object			$object    		common object
	 *   	@param	Translate		$outputlangs    langs
	 *      @param	int				$hidedetails	Do not show line details
	 *      @param	int				$hidedesc		Do not show desc
	 *      @param	int				$hideref		Do not show ref
	 *      @return	null
	 */
	public function defineColumnField($object, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $hookmanager;

		// Default field style for content
		$this->defaultContentsFieldsStyle = array(
			'align' => 'R', // R,C,L
			'padding' => array(0.5, 0.5, 0.5, 0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		);

		// Default field style for content
		$this->defaultTitlesFieldsStyle = array(
			'align' => 'C', // R,C,L
			'padding' => array(0.5, 0, 0.5, 0), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		);

		/*
		 * For exemple
		 $this->cols['theColKey'] = array(
		 'rank' => $rank, // int : use for ordering columns
		 'width' => 20, // the column width in mm
		 'title' => array(
		 'textkey' => 'yourLangKey', // if there is no label, yourLangKey will be translated to replace label
		 'label' => ' ', // the final label : used fore final generated text
		 'align' => 'L', // text alignement :  R,C,L
		 'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		 ),
		 'content' => array(
		 'align' => 'L', // text alignement :  R,C,L
		 'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		 ),
		 );
		 */

		$rank = 0; // do not use negative rank
		$this->cols['ean'] = array(
			'rank' => $rank,
			'width' => 40, // in mm
			'height' => 5, // in mm
			'status' => true,
			'title' => array(
				'textkey' => 'BarCode',
			),
			'content' => array(
				'align' => 'C', // R,C,L
			),
		);


		$rank = $rank + 10;
		$this->cols['desc'] = array(
			'rank' => $rank,
			'width' => false, // only for desc
			'status' => true,
			'title' => array(
				'textkey' => 'Designation', // use lang key is usefull in somme case with module
				'align' => 'L',
				// 'textkey' => 'yourLangKey', // if there is no label, yourLangKey will be translated to replace label
				// 'label' => ' ', // the final label
				'padding' => array(0.5, 0.5, 0.5, 0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			),
			'content' => array(
				'align' => 'L',
			),
			'border-left' => true, // add left line separator
		);

		$rank = $rank + 10;
		$this->cols['warehouse'] = array(
			'rank' => $rank,
			'width' => 25, // in mm
			'status' => true,
			'title' => array(
				'textkey' => 'ThorWarehouse'
			),
			'content' => array(
				'align' => 'C',
			),
			'border-left' => true, // add left line separator
		);

		$rank = $rank + 10;
		$this->cols['timeplannedqty'] = array(
			'rank' => $rank,
			'width' => 15, // in mm
			'status' => true,
			'title' => array(
				'textkey' => 'TimePlannedQty'
			),
			'border-left' => true, // add left line separator
		);


		$parameters = array(
			'object' => $object,
			'outputlangs' => $outputlangs,
			'hidedetails' => $hidedetails,
			'hidedesc' => $hidedesc,
			'hideref' => $hideref
		);

		$reshook = $hookmanager->executeHooks('defineColumnField', $parameters, $this); // Note that $object may have been modified by hook
		if ($reshook < 0)
		{
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}
		elseif (empty($reshook))
		{
			$this->cols = array_replace($this->cols, $hookmanager->resArray); // array_replace is used to preserve keys
		}
		else
		{
			$this->cols = $hookmanager->resArray;
		}
	}



	/**
	 *	Output line description into PDF
	 *
	 *  @param  TCPDF				$pdf               PDF object
	 *	@param	Object			$object				Object
	 *	@param	int				$i					Current line number
	 *  @param  Translate		$outputlangs		Object lang for output
	 *  @param  int				$w					Width
	 *  @param  int				$h					Height
	 *  @param  int				$posx				Pos x
	 *  @param  int				$posy				Pos y
	 *  @param  int				$hideref       		Hide reference
	 *  @param  int				$hidedesc           Hide description
	 * 	@param	int				$issupplierline		Is it a line for a supplier object ?
	 * 	@return	string
	 */
	public static function pdf_writelinedesc(&$pdf, $object, $i, $outputlangs, $w, $h, $posx, $posy, $hideref = 0, $hidedesc = 0, $issupplierline = 0)
	{
		global $db, $conf, $langs, $hookmanager;

		$reshook = 0;
		$result = '';
		//if (is_object($hookmanager) && ( (isset($object->lines[$i]->product_type) && $object->lines[$i]->product_type == 9 && ! empty($object->lines[$i]->special_code)) || ! empty($object->lines[$i]->fk_parent_line) ) )
		if (is_object($hookmanager))   // Old code is commented on preceding line. Reproduct this test in the pdf_xxx function if you don't want your hook to run
		{
			$special_code = $object->lines[$i]->special_code;
			if (!empty($object->lines[$i]->fk_parent_line)) $special_code = $object->getSpecialCode($object->lines[$i]->fk_parent_line);
			$parameters = array('pdf'=>$pdf, 'i'=>$i, 'outputlangs'=>$outputlangs, 'w'=>$w, 'h'=>$h, 'posx'=>$posx, 'posy'=>$posy, 'hideref'=>$hideref, 'hidedesc'=>$hidedesc, 'issupplierline'=>$issupplierline, 'special_code'=>$special_code);
			$action = '';
			$reshook = $hookmanager->executeHooks('pdf_writelinedesc', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

			if (!empty($hookmanager->resPrint)) $result .= $hookmanager->resPrint;
		}
		if (empty($reshook))
		{
			$labelproductservice = pdf_getlinedesc($object, $i, $outputlangs, $hideref, $hidedesc, $issupplierline);

			//var_dump($labelproductservice);exit;

			// Fix bug of some HTML editors that replace links <img src="http://localhostgit/viewimage.php?modulepart=medias&file=image/efd.png" into <img src="http://localhostgit/viewimage.php?modulepart=medias&amp;file=image/efd.png"
			// We make the reverse, so PDF generation has the real URL.
			$labelproductservice = preg_replace('/(<img[^>]*src=")([^"]*)(&amp;)([^"]*")/', '\1\2&\4', $labelproductservice, -1, $nbrep);

			//var_dump($labelproductservice);exit;
			$offsetStep = 3;
			$offsetW = $object->lines[$i]->level * $offsetStep;

			$pdf->SetLineStyle(array('dash'=>'0','width'=> 0.2, 'color' => array(0,0,0)));

			if($object->lines[$i]->level>0){
				$arrowXSatart = $posx + $offsetW - $offsetStep;
				$pdf->Line($arrowXSatart, $posy-1, $arrowXSatart, $posy+2);
				$pdf->Arrow($arrowXSatart, $posy+2, $arrowXSatart + $offsetStep, $posy+2, 2, 1.5 );
			}

			// Description
			$pdf->writeHTMLCell($w - $offsetW, $h, $posx + $offsetW, $posy, $outputlangs->convToOutputCharset($labelproductservice), 0, 1, false, true, 'J', true);
			$result .= $labelproductservice;
		}
		return $result;
	}


	/**
	 * Dans le cas de ce PDF  Si la ligne de niveau 0 est composé uniquement de produit ou service qui ont des composants,
	 * ne pas l'afficher dans le PDF. Si l'un des composants est un élément non composé on l'affiche bien
	 * @var $object OperationOrder
	 */
	public function fetchLines(&$object){
		$TNested = $object->fetch_all_children_nested();
		$TNewNested =array();
		if(!empty($TNested) && is_array($TNested)) {

			// Vérification du niveau 1;
			foreach ($TNested as $k => $v) {

				// vérification si chaque enfant est un parent
				$ChildrenAreAllParents = false;
				if(!empty($v['children'])){
					$ChildrenAreAllParents = true;
					foreach ($v['children']  as $k1 => $v1){
						if(empty($v1['children'])){
							$ChildrenAreAllParents = false;
							break;
						}
					}
				}

				if($ChildrenAreAllParents){
					// composé uniquement de produit ou service qui ont des composants
					foreach ($v['children'] as $k1 => $v1){
						$TNewNested[] = $v1; // Décommenter pour afficher les enfants avec un niveau plus élévé
					}
				}
				else{
					// on garde
					$TNewNested[] = $v;
				}
			}
		}

		$object->lines = array();
		$object->fetchNestedLines($TNewNested);
	}

	/**
	 * 	Surcharge du commondocgenerator pour passer le paramètre ln du writeHTMLCell à 1
	 *  print standard column content
	 *
	 *  @param	TCPDF		    $pdf    	pdf object
	 *  @param	float		$curY    	curent Y position
	 *  @param	string		$colKey    	the column key
	 *  @param	string		$columnText   column text
	 *  @return	int         new rank on success and -1 on error
	 */
	public function printStdColumnContent($pdf, &$curY, $colKey, $columnText = '')
	{
		global $hookmanager;

		$parameters = array(
			'curY' => &$curY,
			'columnText' => $columnText,
			'colKey' => $colKey
		);
		$reshook = $hookmanager->executeHooks('printStdColumnContent', $parameters, $this); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		if (!$reshook)
		{
			if (empty($columnText)) return;
			$pdf->SetXY($this->getColumnContentXStart($colKey), $curY); // Set curent position
			$colDef = $this->cols[$colKey];
			$pdf->writeHTMLCell($this->getColumnContentWidth($colKey), 2, $this->getColumnContentXStart($colKey), $curY, $columnText, 0, 1, 0, true, $colDef['content']['align']);
		}
	}
}
