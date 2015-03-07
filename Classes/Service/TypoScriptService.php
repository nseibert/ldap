<?php
namespace NormanSeibert\Ldap\Service;
/**
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the textfile GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 *
 * @package   ldap
 * @author	  Norman Seibert <seibert@entios.de>
 * @copyright 2013 Norman Seibert
 */

class TypoScriptService {

	/**
	 * @var array
	 */
	protected $typoScriptBackup;

	/**
	 * @param string $filePath
	 * @return void
	 */
	static function loadTypoScriptFromFile($filePath) {
		$typoScriptArray = Array();
        $filePath = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($filePath);
		if ($filePath) {
            /* @var $objectManager \TYPO3\CMS\Extbase\Object\ObjectManager */
			$objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
            /* @var $typoScriptParser \TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser */
            $typoScriptParser = $objectManager->get('TYPO3\\CMS\\Core\\TypoScript\\Parser\\TypoScriptParser');
			$typoScript = file_get_contents($filePath);
			if ($typoScript) {
				$typoScriptParser->parse($typoScript);
				$typoScriptArray = $typoScriptParser->setup;
			}
		}
		return $typoScriptArray;
	}

	/**
	 * @return void
	 */
	public function makeTypoScriptBackup() {
		$this->typoScriptBackup = array();
		foreach ($GLOBALS['TSFE']->tmpl->setup as $key => $value) {
			$this->typoScriptBackup[$key] = $value;
		}
	}

	/**
	 * @return void
	 */
	public function restoreTypoScriptBackup() {
		if ($this->hasTypoScriptBackup()) {
			$GLOBALS['TSFE']->tmpl->setup = $this->typoScriptBackup;
		}
	}

	/**
	 * @return boolean
	 */
	public function hasTypoScriptBackup() {
		return is_array($this->typoScriptBackup) && !empty($this->typoScriptBackup);
	}

}
