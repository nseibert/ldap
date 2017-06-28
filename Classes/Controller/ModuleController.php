<?php
namespace NormanSeibert\Ldap\Controller;
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

/**
 * Controller for backend module
 */
class ModuleController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

	/**
	 * @var string Key of the extension this controller belongs to
	 */
	protected $extensionName = 'Ldap';

	/**
	 * @var \TYPO3\CMS\Core\Page\PageRenderer
	 */
	protected $pageRenderer;

	/**
	 * @var \NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserRepository
	 * @inject
	 */
	protected $feUserRepository;

	/**
	 * @var \NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserRepository
	 * @inject
	 */
	protected $beUserRepository;

	/**
	 * @var \NormanSeibert\Ldap\Domain\Model\Configuration\Configuration
	 * @inject
	 */
	protected $ldapConfig;

	/**
	 * @var \NormanSeibert\Ldap\Domain\Model\BackendModule\ModuleData
	 *  */
	protected $moduleData;

	/**
	 * @var \NormanSeibert\Ldap\Service\ModuleDataStorageService
	 * @inject
	 */
	protected $moduleDataStorageService;

	/**
	 * Load and persist module data
	 *
	 * @param \TYPO3\CMS\Extbase\Mvc\RequestInterface $request
	 * @param \TYPO3\CMS\Extbase\Mvc\ResponseInterface $response
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
	 * @return void
	 */
	public function processRequest(\TYPO3\CMS\Extbase\Mvc\RequestInterface $request, \TYPO3\CMS\Extbase\Mvc\ResponseInterface $response) {
		$this->moduleData = $this->moduleDataStorageService->loadModuleData();
		// We "finally" persist the module data.
		try {
			parent::processRequest($request, $response);
			$this->moduleDataStorageService->persistModuleData($this->moduleData);
		} catch (\TYPO3\CMS\Extbase\Mvc\Exception\StopActionException $e) {
			$this->moduleDataStorageService->persistModuleData($this->moduleData);
			throw $e;
		}
	}

	/**
	 * Initialize actions
	 *
	 * @throws \RuntimeException
	 * @return void
	 */
	public function initializeAction() {
		// Extbase backend modules relies on frontend TypoScript for view, persistence
		// and settings. Thus, we need a TypoScript root template, that then loads the
		// ext_typoscript_setup.txt file of this module. This is nasty, but can not be
		// circumvented until there is a better solution in extbase.
		// For now we throw an exception if no settings are detected.
		if (empty($this->settings)) {
			throw new \RuntimeException(
				'No settings detected. This module can not work then. ' .
				'This usually happens if there is no frontend TypoScript template with root flag set.',
				1344375003
			);
		}
	}

	private function flushMessages() {
		$messageQueue = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessageQueue', 'ldap');
		$messageQueue->getAllMessagesAndFlush();
	}

	/**
	 * Checks LDAP configuration.
	 *
	 * @return void
	 */
	public function checkAction() {
		$this->ldapConfig->getLdapServers();
		$messageQueue = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessageQueue', 'ldap');
		$flashMessages = $messageQueue->getAllMessages();
		$this->view->assign('errorCount', count($flashMessages));
		$this->flushMessages();
	}

	/**
	 * Queries LDAP and compiles a list of users and attributes.
	 *
	 * @return void
	 */
	public function summaryAction() {
		$ldapServers = $this->ldapConfig->getLdapServers();
		$servers = array();
		foreach ($ldapServers as $server) {
            /* @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server */
			$server->setLimitLdapResults(3);
			$server->setScope('fe');
			$feUsers = $server->getUsers('*');
			$server->setScope('be');
			$beUsers = $server->getUsers('*');
			$servers[] = array(
				'server' => $server,
				'feUsers' => $feUsers,
				'beUsers' => $beUsers
			);
		}
		$this->view->assign('ldapServers', $servers);
		$this->flushMessages();
	}

	/**
	 * initializes/stores the form's content
	 *
	 * @param \NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $settings
	 * @return \NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings
	 */
	private function initializeFormSettings($settings = NULL) {
		if ($settings === NULL) {
			$formSettings = $this->moduleData->getFormSettings();
		} else {
			$this->moduleData->setFormSettings($settings);
			$formSettings = $settings;
		}
		if (!is_object($formSettings)) {
			$formSettings = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\BackendModule\\FormSettings');
		}

		if ($formSettings->getAuthenticateBe() != "") {
			$formSettings->setAuthenticateBe(TRUE);
		} else {
			$formSettings->setAuthenticateBe(FALSE);
		}

		if ($formSettings->getAuthenticateFe() != "") {
			$formSettings->setAuthenticateFe(TRUE);
		} else {
			$formSettings->setAuthenticateFe(FALSE);
		}

		if ($formSettings->getHideNotDelete() != "") {
			$formSettings->setHideNotDelete(TRUE);
		} else {
			$formSettings->setHideNotDelete(FALSE);
		}

		if ($formSettings->getDeleteNonLdapUsers() != "") {
			$formSettings->setDeleteNonLdapUsers(TRUE);
		} else {
			$formSettings->setDeleteNonLdapUsers(FALSE);
		}

		return $formSettings;
	}

	/**
	 * configures the import and display the result list
	 */
	public function importUsersAction() {
        $beUsers = array();
        $feUsers = array();
		$settings = $this->initializeFormSettings();

		$ldapServers = $this->ldapConfig->getLdapServers();
		$serverConfigurations = array();
		foreach ($ldapServers as $server) {
            /* @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server */
			$serverConfigurations[] = $server->getConfiguration();
		}

		if ($this->request->hasArgument('runs')) {
			$runs = $this->request->getArgument('runs');
			$feUsers = $this->feUserRepository->findByLastRun($runs);
			$beUsers = $this->beUserRepository->findByLastRun($runs);
		}

		$this->view->assign('formSettings', $settings);
		$this->view->assign('ldapServers', $serverConfigurations);
		$this->view->assign('returnUrl', 'mod.php?M=tools_LdapM1');

		$this->view->assign('be_users', $beUsers);
		$this->view->assign('fe_users', $feUsers);
		$this->flushMessages();
	}

    /**
     * imports users
     *
     * @param \NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings
     */
	public function doImportUsersAction(\NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings = NULL) {
		$settings = $this->initializeFormSettings($formSettings);
		$this->view->assign('formSettings', $settings);

		$ldapServers = $this->ldapConfig->getLdapServers();
		$importer = $this->objectManager->get('NormanSeibert\\Ldap\\Service\\LdapImporter');
		$runs = array();
		foreach ($ldapServers as $server) {
            /* @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server */
			if (\TYPO3\CMS\Core\Utility\ArrayUtility::inArray($settings->getUseServers(), $server->getConfiguration()->getUid())) {
				if ($settings->getAuthenticateFe()) {
					$importer->init($server, 'fe');
					$runs[] = $importer->doImport();
				}
				if ($settings->getAuthenticateBe()) {
					$importer->init($server, 'be');
					$runs[] = $importer->doImport();
				}
			}
		}

		$arguments = array(
			'runs' => $runs
		);

		$this->redirect('importUsers', NULL, NULL, $arguments);
	}

	/**
	 * configures the update and display the result list
	 */
	public function updateUsersAction() {
        $beUsers = array();
        $feUsers = array();
		$settings = $this->initializeFormSettings();

		$ldapServers = $this->ldapConfig->getLdapServers();
		$serverConfigurations = array();
		foreach ($ldapServers as $server) {
            /* @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server */
			$serverConfigurations[] = $server->getConfiguration();
		}

		if ($this->request->hasArgument('runs')) {
			$runs = $this->request->getArgument('runs');
			$feUsers = $this->feUserRepository->findByLastRun($runs);
			$beUsers = $this->beUserRepository->findByLastRun($runs);
		}

		$this->view->assign('formSettings', $settings);
		$this->view->assign('ldapServers', $serverConfigurations);
		$this->view->assign('returnUrl', 'mod.php?M=tools_LdapM1');

		$this->view->assign('be_users', $beUsers);
		$this->view->assign('fe_users', $feUsers);
		$this->flushMessages();
	}

    /**
     * updates users
     *
     * @param \NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings
     */
	public function doUpdateUsersAction(\NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings = NULL) {
		$settings = $this->initializeFormSettings($formSettings);
		$this->view->assign('formSettings', $settings);

		$ldapServers = $this->ldapConfig->getLdapServers();
		$importer = $this->objectManager->get('NormanSeibert\\Ldap\\Service\\LdapImporter');
		$runs = array();
		foreach ($ldapServers as $server) {
            /* @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server */
			if (\TYPO3\CMS\Core\Utility\ArrayUtility::inArray($settings->getUseServers(), $server->getConfiguration()->getUid())) {
				if ($settings->getAuthenticateFe()) {
					$importer->init($server, 'fe');
					$runs[] = $importer->doUpdate();
				}
				if ($settings->getAuthenticateBe()) {
					$importer->init($server, 'be');
					$runs[] = $importer->doUpdate();
				}
			}
		}

		$arguments = array(
			'runs' => $runs
		);

		$this->redirect('updateUsers', NULL, NULL, $arguments);
	}

	/**
	 * configures the import/update and display the result list
	 */
	public function importAndUpdateUsersAction() {
        $beUsers = array();
        $feUsers = array();
		$settings = $this->initializeFormSettings();

		$ldapServers = $this->ldapConfig->getLdapServers();
		$serverConfigurations = array();
		foreach ($ldapServers as $server) {
            /* @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server */
			$serverConfigurations[] = $server->getConfiguration();
		}

		if ($this->request->hasArgument('runs')) {
			$runs = $this->request->getArgument('runs');
			$feUsers = $this->feUserRepository->findByLastRun($runs);
			$beUsers = $this->beUserRepository->findByLastRun($runs);
		}

		$this->view->assign('formSettings', $settings);
		$this->view->assign('ldapServers', $serverConfigurations);
		$this->view->assign('returnUrl', 'mod.php?M=tools_LdapM1');

		$this->view->assign('be_users', $beUsers);
		$this->view->assign('fe_users', $feUsers);
		$this->flushMessages();
	}

    /**
     * imports or updates users
     *
     * @param \NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings
     */
	public function doImportAndUpdateUsersAction(\NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings = NULL) {
		$settings = $this->initializeFormSettings($formSettings);
		$this->view->assign('formSettings', $settings);

		$ldapServers = $this->ldapConfig->getLdapServers();
		$importer = $this->objectManager->get('NormanSeibert\\Ldap\\Service\\LdapImporter');
		$runs = array();
		foreach ($ldapServers as $server) {
			if (\TYPO3\CMS\Core\Utility\ArrayUtility::inArray($settings->getUseServers(), $server->getConfiguration()->getUid())) {
				if ($settings->getAuthenticateFe()) {
					$importer->init($server, 'fe');
					$runs[] = $importer->doImportOrUpdate();
				}
				if ($settings->getAuthenticateBe()) {
					$importer->init($server, 'be');
					$runs[] = $importer->doImportOrUpdate();
				}
			}
		}

		$arguments = array(
			'runs' => $runs
		);

		$this->redirect('importAndUpdateUsers', NULL, NULL, $arguments);
	}

	/**
	 * configures the deletion/deactivation and display the result list
	 */
	public function deleteUsersAction() {
        $beUsers = NULL;
        $feUsers = NULL;
		$settings = $this->initializeFormSettings();

		if ($this->request->hasArgument('runs')) {
			$runs = $this->request->getArgument('runs');
			$feUsers = $this->feUserRepository->findByLastRun($runs);
			$beUsers = $this->beUserRepository->findByLastRun($runs);
		}

		$this->view->assign('formSettings', $settings);
		$this->view->assign('returnUrl', 'mod.php?M=tools_LdapM1');

		$this->view->assign('be_users', $beUsers);
		$this->view->assign('fe_users', $feUsers);
		$this->flushMessages();
	}

    /**
     * deletes/deactivates users
     *
     * @param \NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings
     */
	public function doDeleteUsersAction(\NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings = NULL) {
		$settings = $this->initializeFormSettings($formSettings);
		$this->view->assign('formSettings', $settings);

		$importer = $this->objectManager->get('NormanSeibert\\Ldap\\Service\\LdapImporter');
		$runs = array();
		if ($settings->getAuthenticateFe()) {
			$importer->init(NULL, 'fe');
			$runs[] = $importer->doDelete($settings->getHideNotDelete(), $settings->getDeleteNonLdapUsers());
		}
		if ($settings->getAuthenticateBe()) {
			$importer->init(NULL, 'be');
			$runs[] = $importer->doDelete($settings->getHideNotDelete(), $settings->getDeleteNonLdapUsers());
		}

		$arguments = array(
			'runs' => $runs
		);

		$this->redirect('deleteUsers', NULL, NULL, $arguments);
	}

	/**
	 * configures the login mask
	 */
	public function checkLoginAction() {
        $user = NULL;
		$settings = $this->initializeFormSettings();

		$ldapServers = $this->ldapConfig->getLdapServers();
		$serverConfigurations = array();
		foreach ($ldapServers as $server) {
            /* @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server */
			$serverConfigurations[] = $server->getConfiguration();
		}
		
		if ($this->request->hasArgument('user')) {
			$user = $this->request->getArgument('user');
		}

		$this->view->assign('formSettings', $settings);
		$this->view->assign('ldapServers', $serverConfigurations);
		$this->view->assign('returnUrl', 'mod.php?M=tools_LdapM1');
		$this->view->assign('user', $user);
		$this->flushMessages();
	}

    /**
     * checks whether a user can be authenticated successfully
     *
     * @param \NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings
     */
	public function doCheckLoginAction(\NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings = NULL) {
		$settings = $this->initializeFormSettings($formSettings);
		$this->view->assign('formSettings', $settings);
		
		$user = array();
		$user['submitted'] = TRUE;
		$user['found'] = FALSE;
		$user['authenticated'] = FALSE;
		$ldapServers = $this->ldapConfig->getLdapServers();
		foreach ($ldapServers as $server) {
            /* @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server */
			if (!$user['found']) {
				if (\TYPO3\CMS\Core\Utility\GeneralUtility::inArray($settings->getUseServers(), $server->getConfiguration()->getUid())) {
					$server->setScope($settings->getLoginType());
					$loginname = \NormanSeibert\Ldap\Utility\Helpers::sanitizeCredentials($settings->getLoginname());
					$password = \NormanSeibert\Ldap\Utility\Helpers::sanitizeCredentials($settings->getPassword());
					$ldapUsers = $server->getUsers($loginname);
					if (count($ldapUsers) == 1) {
                        /* @var $ldapUser \NormanSeibert\Ldap\Domain\Model\LdapUser\User */
                        $ldapUser = $ldapUsers[0];
						$user['found'] = TRUE;
						$user['serverUid'] = $server->getConfiguration()->getUid();
						$user['dn'] = $ldapUser->getDN();
						$ldapUser = $server->authenticateUser($loginname, $password);
						if (is_object($ldapUser)) {
							$user['authenticated'] = TRUE;
						}
					}
				}
			}
		}

		$arguments = array(
			'user' => $user
		);

		$this->redirect('checkLogin', NULL, NULL, $arguments);
	}
}
?>
