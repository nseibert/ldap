<?php
namespace NormanSeibert\Ldap\Service;

class TypoScriptService {

	/**
	 * @var array
	 */
	protected $typoScriptBackup;
	
	/**
	 *
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 * @inject
	 */
	protected $objectManager;
	
	/**
	 * 
	 */
	public function __construct() {
		$this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
	}

	/**
	 * @param string $filePath
	 * @return void
	 */
	public function loadTypoScriptFromFile($filePath) {
		$filePath = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($filePath);
		if ($filePath) {
			$typoScriptParser = $this->objectManager->get('TYPO3\\CMS\\Core\\TypoScript\\Parser\\TypoScriptParser');
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