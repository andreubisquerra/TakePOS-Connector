<?php
/* Copyright (C) 2022 Catriel Rios <catriel_r@hotmail.com>
 * Copyright (C) 2022 Andreu Bisquerra <jove@bisquerra.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * Library javascript to enable Browser notifications
 */

if (!defined('NOREQUIREUSER')) {
	define('NOREQUIREUSER', '1');
}
//if (!defined('NOREQUIREDB')) {
//	define('NOREQUIREDB', '1');
//}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
//if (!defined('NOREQUIRETRAN')) {
//	define('NOREQUIRETRAN', '1');
//}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
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

// Define js type
header('Content-Type: application/javascript');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
if (empty($dolibarr_nocache)) {
	header('Cache-Control: max-age=3600, public, must-revalidate');
} else {
	header('Cache-Control: no-cache');
}

$refer = '';
if (isset($_SERVER['HTTP_REFERER'])) $refer = $_SERVER['HTTP_REFERER'];
if(empty($refer) || preg_match('/takepos\/index.php/', $refer)){
	$terminaltouse = 0;
	if ($_SESSION["takeposterminal"]) {
		$terminaltouse = $_SESSION["takeposterminal"];
	}
}
if(empty($refer) || preg_match('/compta\/facture\/card.php/', $refer) ){
	$terminaltouse = 0;
}

global $conf, $langs;



$ws = 'ws://';
if ($conf->global->{'DIRECTPRINTWHB_SECURE'.$terminaltouse}) $ws = 'wss://';


?>

/* Javascript library of module TakePOS connector */




function WebSocketPrinter(options) {
	var defaults = {
		onConnect: function () {
		},
		onDisconnect: function () {
		},
		onUpdate: function () {
		},
	};

	var settings = Object.assign({}, defaults, options);
	var websocket;
	var connected = false;

	var onMessage = function (evt) {
		settings.onUpdate(evt.data);
	};

	var onConnect = function () {
		connected = true;
		settings.onConnect();
	};

	var onDisconnect = function () {
		connected = false;
		settings.onDisconnect();
		reconnect();
	};

	var connect = function () {
		websocket = new WebSocket(settings.url);
		websocket.onopen = onConnect;
		websocket.onclose = onDisconnect;
		websocket.onmessage = onMessage;
	};

	var reconnect = function () {
		connect();
	};

	this.submit = function (data) {
		if (Array.isArray(data)) {
			data.forEach(function (element) {
				websocket.send(JSON.stringify(element));
			});
		} else {
			websocket.send(JSON.stringify(data));
		}
	};

	this.isConnected = function () {
		return connected;
	};

	connect();
}

var url = window.location.pathname;
if (url.includes('/takepos/index.php') ||
	url.includes('/compta/facture/card.php')) {

	var printService = new WebSocketPrinter({
		url: "<?php echo $ws;
		if ($conf->global->{'DIRECTPRINTWHB_IPADDRESS' . $terminaltouse}) echo $conf->global->{'DIRECTPRINTWHB_IPADDRESS' . $terminaltouse};
		else echo "127.0.0.1";
		echo ":";
		if ($conf->global->{'DIRECTPRINTWHB_PORT' . $terminaltouse}) echo $conf->global->{'DIRECTPRINTWHB_PORT' . $terminaltouse};
		else echo "12212";?>/printer",

		onConnect: function () {
			$.jnotify("<?php echo $langs->trans('Connected');?>",
				"info",
				{timeout: 5},
				{
					remove: function () {
					}
				});

			console.log('Connected');
		},
		onDisconnect: function () {
			$.jnotify("<?php echo $langs->trans('Disconnected');?>",
				"error",
				{timeout: 5},
				{
					remove: function () {
					}
				});
			console.log('Disconnected');
		},
		onUpdate: function (message) {
			$.jnotify(message,
				"info",
				{timeout: 5},
				{
					remove: function () {
					}
				});

			//parent.jQuery.colorbox.close();
			console.log(message);
		},
	});



	//TAKEPOS
	//Action button
		if (url.includes('/takepos/index.php')) {

			$(document).on('DOMNodeInserted', function (e) {
				if (e.target.id == "poslines") {
					//$("#buttonprint").prop("onclick", null).off("click");
					//$("#buttonprint").unbind();

					$('#buttonprint').attr("onclick", "DirectPrintWHBDolibarrTakeposPrinting(placeid);");

					//botones de acciones
					var buttons = document.querySelectorAll(".actionbutton");
					for (var button of buttons) {
						if (button["attributes"]["onclick"].value.includes("DolibarrTakeposPrinting")) {
							button["attributes"]["onclick"].value = "DirectPrintWHBDolibarrTakeposPrinting(placeid);";
						}

						if (button["attributes"]["onclick"].value.includes("DolibarrOpenDrawer")) {
							button["attributes"]["onclick"].value = "DirectPrintWHBDolibarrOpenDrawer();";
						}

					}
				}
			});

		}
			var orderprinter = new Array;
			orderprinter[1] = "<?php echo $conf->global->{'DIRECTPRINTWHB_ORDER_TPPRINTERID' . $terminaltouse . '_1'};?>";
			orderprinter[2] = "<?php echo $conf->global->{'DIRECTPRINTWHB_ORDER_TPPRINTERID' . $terminaltouse . '_2'};?>";
			orderprinter[3] = "<?php echo $conf->global->{'DIRECTPRINTWHB_ORDER_TPPRINTERID' . $terminaltouse . '_3'};?>";

			function DirectPrintWHBDolibarrTakeposPrinting(id) {
				console.log("DolibarrTakeposPrinting Printing invoice ticket " + id)
				$.ajax({
					type: "GET",
					data: {token: '<?php echo currentToken(); ?>'},
					url: "<?php print dol_buildpath('/takeposconnector', 2) . '/ajax/ajax.php?action=printinvoiceticket&term=' . urlencode($_SESSION["takeposterminal"]) . '&id='; ?>" + id,
					success: function (getdata) {

						printService.submit({
							"type": "<?php echo $conf->global->{'DIRECTPRINTWHB_TPPRINTERID' . $terminaltouse};?>",
							"raw_content": "\"" + getdata + "\""
						});

					}
				});
			}

			function DirectPrintWHBDolibarrOpenDrawer() {
				console.log("DolibarrOpenDrawer call ajax url /ajax/ajax.php?action=opendrawer&term=<?php print urlencode($_SESSION["takeposterminal"]); ?>");
				$.ajax({
					type: "GET",
					data: {token: '<?php echo currentToken(); ?>'},
					url: "<?php print dol_buildpath('/directprintwhb', 2) . '/ajax/ajax.php?action=opendrawer&term=' . urlencode($_SESSION["takeposterminal"]); ?>",
					success: function (getdata) {

						printService.submit({
							"type": "<?php echo $conf->global->{'DIRECTPRINTWHB_TPPRINTERID' . $terminaltouse};?>",
							"raw_content": "\"" + getdata + "\""
						});

					}

				});
			}

}
