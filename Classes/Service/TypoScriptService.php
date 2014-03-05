<?php
namespace NormanSeibert\Ldap\Service;

class TypoScriptService implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var array
	 */
	protected $typoScriptBackup;

	/**
	 * @param string $filePath
	 * @return void
	 */
	public static function loadTypoScriptFromFile($filePath) {
		static $typoScriptParser;
		$filePath = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($filePath);
		if ($filePath) {
			if ($typoScriptParser == NULL) {
				$typoScriptParser = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('t3lib_TSparser');
			}
			/* @var t3lib_TSparser $typoScriptParser */
			$typoScript = file_get_contents($filePath);
			if ($typoScript) {
				$typoScriptParser->parse($typoScript);
				$typoScriptArray = $typoScriptParser->setup;
				if (is_array($typoScriptArray) && !empty($typoScriptArray)) {
					$GLOBALS['TSFE']->tmpl->setup = \TYPO3\CMS\Core\Utility\GeneralUtility::array_merge_recursive_overrule($typoScriptArray, $GLOBALS['TSFE']->tmpl->setup);
				}
			}
		}
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