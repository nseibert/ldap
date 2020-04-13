<?php

namespace NormanSeibert\Ldap\Controller;

/*
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
 * @copyright 2020 Norman Seibert
 */

use NormanSeibert\Ldap\Domain\Model\BackendModule\ModuleData;
use NormanSeibert\Ldap\Domain\Model\Configuration\Configuration;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserRepository;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserRepository;
use NormanSeibert\Ldap\Service\LdapImporter;
use NormanSeibert\Ldap\Service\ModuleDataStorageService;

/**
 * Controller for backend module.
 */
class ModuleController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * @var string Key of the extension this controller belongs to
     */
    protected $extensionName = 'Ldap';

    /**
     * @var \TYPO3\CMS\Core\Page\PageRenderer
     */
    protected $pageRenderer;

    /**
     * @var FrontendUserRepository
     */
    protected $feUserRepository;

    /**
     * @var BackendUserRepository
     */
    protected $beUserRepository;

    /**
     * @var Configuration
     */
    protected $ldapConfig;

    /**
     * @var ModuleData
     *  */
    protected $moduleData;

    /**
     * @var ModuleDataStorageService
     */
    protected $moduleDataStorageService;

    /**
     * @var LdapImporter
     */
    protected $importer;

    /**
     * @param
     */
    public function __construct(FrontendUserRepository $feUserRepository, BackendUserRepository $beUserRepository, Configuration $ldapConfig, ModuleData $moduleData, ModuleDataStorageService $moduleDataStorageService, LdapImporter $importer)
    {
        $this->feUserRepository = $feUserRepository;
        $this->beUserRepository = $beUserRepository;
        $this->ldapConfig = $ldapConfig;
        $this->moduleData = $moduleData;
        $this->moduleDataStorageService = $moduleDataStorageService;
        $this->importer = $importer;
    }

    /**
     * Load and persist module data.
     *
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     */
    public function processRequest(\TYPO3\CMS\Extbase\Mvc\RequestInterface $request, \TYPO3\CMS\Extbase\Mvc\ResponseInterface $response)
    {
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
     * Checks LDAP configuration.
     */
    public function checkAction()
    {
        $this->ldapConfig->getLdapServers();
        $ok = $this->ldapConfig->isConfigOK();
        $this->view->assign('ok', $ok);
    }

    /**
     * Queries LDAP and compiles a list of users and attributes.
     */
    public function summaryAction()
    {
        $ldapServers = $this->ldapConfig->getLdapServers();
        $servers = [];
        foreach ($ldapServers as $server) {
            // @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
            $status = $server->checkBind();
            $server->setLimitLdapResults(3);
            $server->setScope('fe');
            $feUsers = $server->getUsers('*');
            $server->setScope('be');
            $beUsers = $server->getUsers('*');
            $servers[] = [
                'server' => $server,
                'status' => $status,
                'feUsers' => $feUsers,
                'beUsers' => $beUsers,
            ];
        }
        $this->view->assign('ldapServers', $servers);
    }

    /**
     * configures the import and display the result list.
     */
    public function importUsersAction()
    {
        $beUsers = [];
        $feUsers = [];
        $settings = $this->initializeFormSettings();

        $ldapServers = $this->ldapConfig->getLdapServers();
        $serverConfigurations = [];
        foreach ($ldapServers as $server) {
            // @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
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
    }

    /**
     * imports users.
     */
    public function doImportUsersAction(\NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings = null)
    {
        $settings = $this->initializeFormSettings($formSettings);
        $this->view->assign('formSettings', $settings);

        $ldapServers = $this->ldapConfig->getLdapServers();
        $runs = [];
        foreach ($ldapServers as $server) {
            // @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
            if (in_array($server->getConfiguration()->getUid(), $settings->getUseServers())) {
                if ($settings->getAuthenticateFe()) {
                    $this->importer->init($server, 'fe');
                    $runs[] = $this->importer->doImport();
                }
                if ($settings->getAuthenticateBe()) {
                    $this->importer->init($server, 'be');
                    $runs[] = $this->importer->doImport();
                }
            }
        }

        $arguments = [
            'runs' => $runs,
        ];

        $this->redirect('importUsers', null, null, $arguments);
    }

    /**
     * configures the update and display the result list.
     */
    public function updateUsersAction()
    {
        $beUsers = [];
        $feUsers = [];
        $settings = $this->initializeFormSettings();

        $ldapServers = $this->ldapConfig->getLdapServers();
        $serverConfigurations = [];
        foreach ($ldapServers as $server) {
            // @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
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
    }

    /**
     * updates users.
     */
    public function doUpdateUsersAction(\NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings = null)
    {
        $settings = $this->initializeFormSettings($formSettings);
        $this->view->assign('formSettings', $settings);

        $ldapServers = $this->ldapConfig->getLdapServers();
        $runs = [];
        foreach ($ldapServers as $server) {
            // @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
            if (in_array($server->getConfiguration()->getUid(), $settings->getUseServers())) {
                if ($settings->getAuthenticateFe()) {
                    $this->importer->init($server, 'fe');
                    $runs[] = $this->importer->doUpdate();
                }
                if ($settings->getAuthenticateBe()) {
                    $this->importer->init($server, 'be');
                    $runs[] = $this->importer->doUpdate();
                }
            }
        }

        $arguments = [
            'runs' => $runs,
        ];

        $this->redirect('updateUsers', null, null, $arguments);
    }

    /**
     * configures the import/update and display the result list.
     */
    public function importAndUpdateUsersAction()
    {
        $beUsers = [];
        $feUsers = [];
        $settings = $this->initializeFormSettings();

        $ldapServers = $this->ldapConfig->getLdapServers();
        $serverConfigurations = [];
        foreach ($ldapServers as $server) {
            // @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
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
    }

    /**
     * imports or updates users.
     */
    public function doImportAndUpdateUsersAction(\NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings = null)
    {
        $settings = $this->initializeFormSettings($formSettings);
        $this->view->assign('formSettings', $settings);

        $ldapServers = $this->ldapConfig->getLdapServers();
        $runs = [];
        foreach ($ldapServers as $server) {
            if (in_array($server->getConfiguration()->getUid(), $settings->getUseServers())) {
                if ($settings->getAuthenticateFe()) {
                    $this->importer->init($server, 'fe');
                    $runs[] = $this->importer->doImportOrUpdate();
                }
                if ($settings->getAuthenticateBe()) {
                    $this->importer->init($server, 'be');
                    $runs[] = $this->importer->doImportOrUpdate();
                }
            }
        }

        $arguments = [
            'runs' => $runs,
        ];

        $this->redirect('importAndUpdateUsers', null, null, $arguments);
    }

    /**
     * configures the deletion/deactivation and display the result list.
     */
    public function deleteUsersAction()
    {
        $beUsers = [];
        $feUsers = [];
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
    }

    /**
     * deletes/deactivates users.
     */
    public function doDeleteUsersAction(\NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings = null)
    {
        $settings = $this->initializeFormSettings($formSettings);
        $this->view->assign('formSettings', $settings);

        $runs = [];
        if ($settings->getAuthenticateFe()) {
            $this->importer->init(null, 'fe');
            $runs[] = $this->importer->doDelete($settings->getHideNotDelete(), $settings->getDeleteNonLdapUsers());
        }
        if ($settings->getAuthenticateBe()) {
            $this->importer->init(null, 'be');
            $runs[] = $this->importer->doDelete($settings->getHideNotDelete(), $settings->getDeleteNonLdapUsers());
        }

        $arguments = [
            'runs' => $runs,
        ];

        $this->redirect('deleteUsers', null, null, $arguments);
    }

    /**
     * configures the login mask.
     */
    public function checkLoginAction()
    {
        $user = null;
        $settings = $this->initializeFormSettings();

        $ldapServers = $this->ldapConfig->getLdapServers();
        $serverConfigurations = [];
        foreach ($ldapServers as $server) {
            // @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
            $serverConfigurations[] = $server->getConfiguration();
        }

        if ($this->request->hasArgument('user')) {
            $user = $this->request->getArgument('user');
        }

        $this->view->assign('formSettings', $settings);
        $this->view->assign('ldapServers', $serverConfigurations);
        $this->view->assign('returnUrl', 'mod.php?M=tools_LdapM1');
        $this->view->assign('user', $user);
    }

    /**
     * checks whether a user can be authenticated successfully.
     */
    public function doCheckLoginAction(\NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $formSettings = null)
    {
        $settings = $this->initializeFormSettings($formSettings);
        $this->view->assign('formSettings', $settings);

        $user = [];
        $user['submitted'] = true;
        $user['found'] = false;
        $user['authenticated'] = false;
        $ldapServers = $this->ldapConfig->getLdapServers();
        foreach ($ldapServers as $server) {
            // @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
            if (!$user['found']) {
                if (in_array($server->getConfiguration()->getUid(), $settings->getUseServers())) {
                    $server->setScope($settings->getLoginType());
                    $loginname = \NormanSeibert\Ldap\Utility\Helpers::sanitizeCredentials($settings->getLoginname());
                    $password = \NormanSeibert\Ldap\Utility\Helpers::sanitizeCredentials($settings->getPassword());
                    $ldapUsers = $server->getUsers($loginname);
                    if (1 == count($ldapUsers)) {
                        // @var $ldapUser \NormanSeibert\Ldap\Domain\Model\LdapUser\User
                        $ldapUser = $ldapUsers[0];
                        $user['found'] = true;
                        $user['serverUid'] = $server->getConfiguration()->getUid();
                        $user['dn'] = $ldapUser->getDN();
                        $ldapUser = $server->authenticateUser($loginname, $password);
                        if (is_object($ldapUser)) {
                            $user['authenticated'] = true;
                        }
                    }
                }
            }
        }

        $arguments = [
            'user' => $user,
        ];

        $this->redirect('checkLogin', null, null, $arguments);
    }

    /**
     * Assign default variables to view.
     *
     * @param ViewInterface $view
     */
    protected function initializeView(\TYPO3\CMS\Extbase\Mvc\View\ViewInterface $view)
    {
        $view->assignMultiple([
            'shortcutLabel' => 'ldap',
            'dateFormat' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'],
            'timeFormat' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'],
        ]);

        // Workaround to make FlashMessages appear in the module
        // Don't know why this works
        $flashMessage = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
            'dummy',
            '',
            \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR,
            false
        );
        $messageQueue = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessageQueue', 'ldap');
        $messageQueue->enqueue($flashMessage);
    }

    /**
     * initializes/stores the form's content.
     *
     * @param \NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings $settings
     *
     * @return \NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings
     */
    private function initializeFormSettings($settings = null)
    {
        if (null === $settings) {
            $formSettings = $this->moduleData->getFormSettings();
        } else {
            $this->moduleData->setFormSettings($settings);
            $formSettings = $settings;
        }
        if (!is_object($formSettings)) {
            $formSettings = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\BackendModule\\FormSettings');
        }

        if ('' != $formSettings->getAuthenticateBe()) {
            $formSettings->setAuthenticateBe(true);
        } else {
            $formSettings->setAuthenticateBe(false);
        }

        if ('' != $formSettings->getAuthenticateFe()) {
            $formSettings->setAuthenticateFe(true);
        } else {
            $formSettings->setAuthenticateFe(false);
        }

        if ('' != $formSettings->getHideNotDelete()) {
            $formSettings->setHideNotDelete(true);
        } else {
            $formSettings->setHideNotDelete(false);
        }

        if ('' != $formSettings->getDeleteNonLdapUsers()) {
            $formSettings->setDeleteNonLdapUsers(true);
        } else {
            $formSettings->setDeleteNonLdapUsers(false);
        }

        return $formSettings;
    }
}
