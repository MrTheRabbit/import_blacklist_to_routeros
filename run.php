<?
require('routeros_api.class.php');

define('NAME_LIST', 'rublacklist');

// Получим список запрещенных IP
$arrListBlockIPFromSite = $arrImportListBlockIP = $arrDeleteListBlockIP = $arrListBlockIPFromRouter = array();

// Список IP которые нужно исключить из списка
$arrNotAddToRouter = ['74.125.196.132'];

$strList = file_get_contents('http://reestr.rublacklist.net/api/ips');
$strList = str_replace('"', '', $strList);
$arrListBlockIPFromSite = explode(';', $strList);
unset($strList);

// Почистим массив
$arrListBlockIPFromSite = array_unique($arrListBlockIPFromSite);
$arrListBlockIPFromSite = array_filter(
	$arrListBlockIPFromSite,
	function($el){
		global $arrNotAddToRouter;
		return !empty($el) && !in_array($el, $arrNotAddToRouter);
	}
);
print_r('Выкачано с сайта '.count($arrListBlockIPFromSite).' IP'."\n");

// Получим список IP из роутера

$API = new RouterosAPI();
$API->debug = false;
$API->port = 8472;
if ($API->connect('192.168.1.1', 'admin', '1234567890')) {
	$arrAddressList = $API->comm('/ip/firewall/address-list/print');
	if (count($arrAddressList)) {
		foreach ($arrAddressList as $arrT) {
			if ($arrT['list'] == NAME_LIST) {
				$arrListBlockIPFromRouter[$arrT['.id']] = $arrT['address'];
			}//\\ if
		}//\\ foreach
	}//\\ if
	print_r('В роутере '.count($arrListBlockIPFromRouter).' IP'."\n");
	
	// Получим массив, который нужно будет добавить в роутер
	$arrImportListBlockIP = array_diff($arrListBlockIPFromSite, $arrListBlockIPFromRouter);
	print_r('Будет добавлено '.count($arrImportListBlockIP).' IP'."\n");
	
	// Получим массив, который нужно будет удалить из роутера
	$arrDeleteListBlockIP = array_diff($arrListBlockIPFromRouter, $arrListBlockIPFromSite);
	$arrDeleteListBlockIP2 = array_filter(
	$arrListBlockIPFromRouter,
		function($el){
			global $arrNotAddToRouter;
			return in_array($el, $arrNotAddToRouter);
		}
	);
	if (count($arrDeleteListBlockIP2)) $arrDeleteListBlockIP = array_merge($arrDeleteListBlockIP, $arrDeleteListBlockIP2);
	unset($arrListBlockIPFromSite);
	unset($arrListBlockIPFromRouter);
	unset($arrDeleteListBlockIP2);
	print_r('Будет удалено из роутера '.count($arrDeleteListBlockIP).' IP'."\n");
	
	// Добавляем список IP в роутер
	if (count($arrImportListBlockIP)) {
		foreach ($arrImportListBlockIP as $strIP) {
			// ip firewall address-list add address=92.48.118.2 list=rublacklist
			$API->comm('/ip/firewall/address-list/add', array(
				'address' => $strIP,
				'list' => NAME_LIST,
			));
		}//\\ foreach
	}//\\ if

	// Удаляем устаревшие IP из списка роутера
	if (count($arrDeleteListBlockIP)) {
		foreach ($arrDeleteListBlockIP as $strID => $strIP) {
			$API->comm('/ip/firewall/address-list/remove', array(
				'.id' => $strID
			));
		}//\\ foreach
	}//\\ if

}//\\ if