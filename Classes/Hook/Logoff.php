<?php
class Logoff {
	function main($params, $pObj) {
		$bt = debug_backtrace();
		$recursive = 0;
		foreach ($bt as $k => $v) {
			if ($recursive < 2) {
				if ($v['args']['0'] == 'EXT:ldap/Classes/Controller/Hook/Logoff.php:&Logoff->main') {
					$recursive++;
				}
			}
		}
		if ($recursive < 2) {
			$user = $pObj->fetchUserSession(true);
			if ($user) {
				$updateArray = array(
					'tx_ldap_nosso' => '1'
				);
				$GLOBALS['TYPO3_DB']->exec_UPDATEquery($pObj->user_table, 'uid="'.$user['uid'].'"', $updateArray);
			}
		}
	}	
}
?>