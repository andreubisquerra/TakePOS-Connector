<?php
/* Copyright (C) 2001-2004	Andreu Bisquerra	<jove@bisquerra.com>
 * Copyright (C) 2020		Thibault FOUCART	<support@ptibogxiv.net>
 * Copyright (C) 2020 Catriel Rios <catriel_r@hotmail.com> 
 *
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
 */

/**
 *	\file       htdocs/directprintwhb/ajax/ajax.php
 *	\brief      Ajax search component for TakePos. It search products of a category.
 */

if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/../main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/../main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

$category = GETPOST('category', 'alphanohtml');	// Can be id of category or 'supplements'
$action = GETPOST('action', 'aZ09');
$term = GETPOST('term', 'alpha');
$id = GETPOST('id', 'int');

if (empty($user->rights->takepos->run)) {
	accessforbidden();
}


/*
 * View
 */

if ($action == "printinvoiceticket" && $term != '' && $id > 0 && !empty($user->rights->facture->lire)) {
	require_once '../dolreceiptprinter.class.php';
	require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
	$printer = new dolReceiptPrinter($db);
	$object = new Facture($db);
	$object->fetch($id);
	$conf->global->TAKEPOS_PRINT_METHOD = "takeposconnector"; //CATRIEL para obtener la salida de $printer
	$ret = $printer->sendToPrinter($object, $conf->global->{'TAKEPOS_TEMPLATE_TO_USE_FOR_INVOICES'.$term}, $conf->global->{'TAKEPOS_PRINTER_TO_USE'.$term});

}

$invoiceid = GETPOST('invoiceid', 'int');
$place = (GETPOST('place', 'aZ09') ? GETPOST('place', 'aZ09') : 0); // $place is id of table for Bar or Restaurant
$placeid = 0; // $placeid is ID of invoice


//CATRIEL
if ($action == "order") {

	require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
	require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';



	$invoice = new Facture($db);
	if ($invoiceid > 0) {
		$ret = $invoice->fetch($invoiceid);
	} else {
		$ret = $invoice->fetch('', '(PROV-POS'.$_SESSION["takeposterminal"].'-'.$place.')');
	}
	if ($ret > 0) {
		$placeid = $invoice->id;
	}

	$constforcompanyid = 'CASHDESK_ID_THIRDPARTY'.$_SESSION["takeposterminal"];

	$soc = new Societe($db);
	if ($invoice->socid > 0) {
		$soc->fetch($invoice->socid);
	} else {
		$soc->fetch($conf->global->$constforcompanyid);
	}


	$conf->global->TAKEPOS_PRINT_METHOD = "takeposconnector"; //CATRIEL para obtener la salida de $printer
	if ($conf->global->TAKEPOS_PRINT_METHOD == "receiptprinter" || $conf->global->TAKEPOS_PRINT_METHOD == "takeposconnector") {
		require_once DOL_DOCUMENT_ROOT.'/core/class/dolreceiptprinter.class.php';
		$printer = new dolReceiptPrinter($db);
	}

	$sql = "SELECT label FROM ".MAIN_DB_PREFIX."takepos_floor_tables where rowid=".((int) $place);
	$resql = $db->query($sql);
	$row = $db->fetch_object($resql);

	$catsprinter1 = explode(';', $conf->global->TAKEPOS_PRINTED_CATEGORIES_1);
	$catsprinter2 = explode(';', $conf->global->TAKEPOS_PRINTED_CATEGORIES_2);
	$catsprinter3 = explode(';', $conf->global->TAKEPOS_PRINTED_CATEGORIES_3);
	$linestoprint = 0;

	echo "["; //Start JSON
	foreach ($invoice->lines as $line) {
		if ($line->special_code == "4") {
			continue;
		}
		$c = new Categorie($db);
		$existing = $c->containing($line->fk_product, Categorie::TYPE_PRODUCT, 'id');
		$result = array_intersect($catsprinter1, $existing);
		$count = count($result);
		if (!$line->fk_product) {
			$count++; // Print Free-text item (Unassigned printer) to Printer 1
		}
		if ($count > 0) {
			$linestoprint++;
			$sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='1' where rowid=".$line->id; //Set to print on printer 1
			$db->query($sql);

		}
	}
	if (($conf->global->TAKEPOS_PRINT_METHOD == "receiptprinter" || $conf->global->TAKEPOS_PRINT_METHOD == "takeposconnector") && $linestoprint > 0) {
		$invoice->fetch($placeid); //Reload object before send to printer
		$printer->orderprinter = 1;
//		echo "<script>";
//		echo "var orderprinter1esc='";
		echo "{\"id\":\"1\",\"data\":\"";
		$ret = $printer->sendToPrinter($invoice, $conf->global->{'TAKEPOS_TEMPLATE_TO_USE_FOR_ORDERS'.$_SESSION["takeposterminal"]}, $conf->global->{'TAKEPOS_ORDER_PRINTER1_TO_USE'.$_SESSION["takeposterminal"]}); // PRINT TO PRINTER 1
		echo "\"}";
//		echo "';</script>";
	}
	$sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='4' where special_code='1' and fk_facture=".$invoice->id; // Set as printed
	$db->query($sql);
	$invoice->fetch($placeid); //Reload object after set lines as printed
	$linestoprint = 0;

	foreach ($invoice->lines as $line) {
		if ($line->special_code == "4") {
			continue;
		}
		$c = new Categorie($db);
		$existing = $c->containing($line->fk_product, Categorie::TYPE_PRODUCT, 'id');
		$result = array_intersect($catsprinter2, $existing);
		$count = count($result);
		if ($count > 0) {
			$linestoprint++;
			$sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='2' where rowid=".$line->id; //Set to print on printer 2
			$db->query($sql);
		}
	}
	if (($conf->global->TAKEPOS_PRINT_METHOD == "receiptprinter" || $conf->global->TAKEPOS_PRINT_METHOD == "takeposconnector") && $linestoprint > 0) {
		$invoice->fetch($placeid); //Reload object before send to printer
		$printer->orderprinter = 2;
//		echo "<script>";
//		echo "var orderprinter2esc='";
		echo "{\"id\":\"2\",\"data\":\"";
		$ret = $printer->sendToPrinter($invoice, $conf->global->{'TAKEPOS_TEMPLATE_TO_USE_FOR_ORDERS'.$_SESSION["takeposterminal"]}, $conf->global->{'TAKEPOS_ORDER_PRINTER2_TO_USE'.$_SESSION["takeposterminal"]}); // PRINT TO PRINTER 2
		echo "\"}";
//		echo "';</script>";
	}
	$sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='4' where special_code='2' and fk_facture=".$invoice->id; // Set as printed
	$db->query($sql);
	$invoice->fetch($placeid); //Reload object after set lines as printed
	$linestoprint = 0;

	foreach ($invoice->lines as $line) {
		if ($line->special_code == "4") {
			continue;
		}
		$c = new Categorie($db);
		$existing = $c->containing($line->fk_product, Categorie::TYPE_PRODUCT, 'id');
		$result = array_intersect($catsprinter3, $existing);
		$count = count($result);
		if ($count > 0) {
			$linestoprint++;
			$sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='3' where rowid=".$line->id; //Set to print on printer 3
			$db->query($sql);
		}
	}
	if (($conf->global->TAKEPOS_PRINT_METHOD == "receiptprinter" || $conf->global->TAKEPOS_PRINT_METHOD == "takeposconnector") && $linestoprint > 0) {
		$invoice->fetch($placeid); //Reload object before send to printer
		$printer->orderprinter = 3;
//		echo "<script>";
//		echo "var orderprinter3esc='";
		echo "{\"id\":\"3\",\"data\":\"";
		$ret = $printer->sendToPrinter($invoice, $conf->global->{'TAKEPOS_TEMPLATE_TO_USE_FOR_ORDERS'.$_SESSION["takeposterminal"]}, $conf->global->{'TAKEPOS_ORDER_PRINTER3_TO_USE'.$_SESSION["takeposterminal"]}); // PRINT TO PRINTER 3

		echo "\"}";
//		echo "';</script>";
	}

	echo "]";  //end JSON
	$sql = "UPDATE ".MAIN_DB_PREFIX."facturedet set special_code='4' where special_code='3' and fk_facture=".$invoice->id; // Set as printed
	$db->query($sql);

}



