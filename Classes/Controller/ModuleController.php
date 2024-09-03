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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use NormanSeibert\Ldap\Domain\Model\Configuration\LdapConfiguration;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserRepository;
use NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserRepository;
use NormanSeibert\Ldap\Service\LdapImporter;
use NormanSeibert\Ldap\Service\BackendModule\ModuleDataStorageService;
use NormanSeibert\Ldap\Domain\Model\BackendModule\ModuleData;
use NormanSeibert\Ldap\Domain\Model\BackendModule\FormSettings;
use TYPO3\CMS\Backend\Template\Components\Menu\Menu;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use NormanSeibert\Ldap\Domain\Repository\LdapServer\LdapServerRepository;
use NormanSeibert\Ldap\Service\LdapHandler;

/**
 * Controller for backend module.
 */
class ModuleController extends ActionController
{

    const ERROR = 2;
    const WARNING = 1;
    const OK = 0;
    const INFO = -1;
    const NOTICE = -2;

    /**
     * @var FrontendUserRepository
     */
    protected $feUserRepository;

    /**
     * @var BackendUserRepository
     */
    protected $beUserRepository;

    /**
     * @var LdapServerRepository
     */
    protected $serverRepository;

    /**
     * @var LdapConfiguration
     */
    protected $configuration;

    protected Ldaphandler $ldapHandler;

    /**
     * @var ModuleData
     *  */
    protected $moduleData;

    /**
     * @var ModuleDataStorageService
     */
    protected $moduleDataStorageService;

    /**
     * @var BackendTemplateView
     */
    protected $view;
    
    protected ModuleTemplateFactory $moduleTemplateFactory;

    public function __construct(
        FrontendUserRepository $feUserRepository,
        BackendUserRepository $beUserRepository,
        LdapConfiguration $configuration,
        LdapServerRepository $serverRepository,
        ModuleData $moduleData,
        ModuleDataStorageService $moduleDataStorageService,
        ModuleTemplateFactory $moduleTemplateFactory,
        Ldaphandler $ldapHandler)
    {
        $this->feUserRepository = $feUserRepository;
        $this->beUserRepository = $beUserRepository;
        $this->configuration = $configuration;
        $this->serverRepository = $serverRepository;
        $this->moduleData = $moduleData;
        $this->moduleDataStorageService = $moduleDataStorageService;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->ldapHandler = $ldapHandler;
    }

    protected function createMenu($moduleTemplate)
    {
        // Add action menu
        /** @var Menu $menu */
        $menu = GeneralUtility::makeInstance(Menu::class);
        $menu->setIdentifier('_ldapMenu');

        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $uriBuilder->setRequest($this->request);

        // Add menu items
        $menu = $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('ldap');

        $items = ['check', 'summary', 'importUsers', 'updateUsers', 'importAndUpdateUsers', 'deleteUsers', 'checkLogin'];
        
        foreach ($items as $item) {
            $isActive = $this->actionMethodName === $item . 'Action';
            $uri = $uriBuilder->reset()->uriFor(
                $item,
                [],
                'Module'
            );
            $title = LocalizationUtility::translate('action.' . $item, 'ldap');
            $item = $menu->makeMenuItem()
                ->setTitle($title)
                ->setActive($isActive)
                ->setHref($uri);
            $menu->addMenuItem($item);
        }

        return $menu;
    }

    /**
     * Load and persist module data.
     *
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     */
    public function processRequest(\TYPO3\CMS\Extbase\Mvc\RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $this->moduleData = $this->moduleDataStorageService->loadModuleData();
        // We "finally" persist the module data.
        try {
            return parent::processRequest($request);
            $this->moduleDataStorageService->persistModuleData($this->moduleData);
        } catch (\TYPO3\CMS\Extbase\Mvc\Exception\StopActionException $e) {
            $this->moduleDataStorageService->persistModuleData($this->moduleData);

            throw $e;
        }
    }

    /**
     * Checks LDAP configuration.
     */
    public function checkAction(): \Psr\Http\Message\ResponseInterface
    {
        $ok = $this->configuration->checkConfiguration();
        $this->view->assign('ok', $ok);

        // return $this->htmlResponse();
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $menu = $this->createMenu($moduleTemplate);
        if ($menu instanceof Menu) {
            // $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
            $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
        }
        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * Queries LDAP and compiles a list of users and attributes.
     */
    public function summaryAction(): \Psr\Http\Message\ResponseInterface
    {
        $ldapServers = $this->serverRepository->findAll();
        $servers = [];

        foreach ($ldapServers as $uid => $ldapServer) {
            $status = $this->ldapHandler->checkBind($ldapServer);
            $ldapServer->setLimitLdapResults(3);
            $ldapServer->setUserType('fe');
            $feUsers = $this->ldapHandler->getUsers($ldapServer, '*');
            $ldapServer->setUserType('be');
            $beUsers = $this->ldapHandler->getUsers($ldapServer, '*');
            $servers[] = [
                'server' => $ldapServer->getConfiguration(),
                'status' => $status,
                'feUsers' => $feUsers,
                'beUsers' => $beUsers,
            ];
        }
        $this->view->assign('ldapServers', $servers);

        // return $this->htmlResponse();
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $menu = $this->createMenu($moduleTemplate);
        if ($menu instanceof Menu) {
            // $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
            $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
        }
        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * configures the import and display the result list.
     */
    public function importUsersAction(): \Psr\Http\Message\ResponseInterface
    {
        $beUsers = [];
        $feUsers = [];
        $settings = $this->initializeFormSettings();

        $ldapServers = $this->serverRepository->findAll();
        $serverConfigurations = [];
        foreach ($ldapServers as $ldapServer) {
            $serverConfigurations[] = $ldapServer->getConfiguration();
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

        // return $this->htmlResponse();
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $menu = $this->createMenu($moduleTemplate);
        if ($menu instanceof Menu) {
            // $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
            $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
        }
        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * imports users.
     */
    public function doImportUsersAction(FormSettings $formSettings = null)
    {
        $settings = $this->initializeFormSettings($formSettings);
        $this->view->assign('formSettings', $settings);

        $ldapServers = $this->serverRepository->findAll();
        $runs = [];

        if ($ldapServers->count()) {
            $importer = GeneralUtility::makeInstance(LdapImporter::class);
        }

        foreach ($ldapServers as $ldapServer) {
            $uid = $ldapServer->getUid();
            if (in_array($uid, $settings->getUseServers())) {
                if ($settings->getAuthenticateFe()) {
                    $ldapServer->setUserType('fe');
                    $runs[] = $importer::doImport($ldapServer);
                }
                if ($settings->getAuthenticateBe()) {
                    $ldapServer->setUserType('be');
                    $runs[] = $importer::doImport($ldapServer);
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
    public function updateUsersAction(): \Psr\Http\Message\ResponseInterface
    {
        $beUsers = [];
        $feUsers = [];
        $settings = $this->initializeFormSettings();

        $ldapServers = $this->serverRepository->findAll();
        $serverConfigurations = [];
        foreach ($ldapServers as $ldapServer) {
            $serverConfigurations[] = $ldapServer->getConfiguration();
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

        // return $this->htmlResponse();
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $menu = $this->createMenu($moduleTemplate);
        if ($menu instanceof Menu) {
            // $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
            $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
        }
        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * updates users.
     */
    public function doUpdateUsersAction(FormSettings $formSettings = null)
    {
        $settings = $this->initializeFormSettings($formSettings);
        $this->view->assign('formSettings', $settings);

        $ldapServers = $this->serverRepository->findAll();
        $runs = [];

        if ($ldapServers->count()) {
            $importer = GeneralUtility::makeInstance(LdapImporter::class);
        }

        foreach ($ldapServers as $ldapServer) {
            $uid = $ldapServer->getUid();
            if (in_array($uid, $settings->getUseServers())) {
                if ($settings->getAuthenticateFe()) {
                    $ldapServer->setUserType('fe');
                    $runs[] = $importer::doUpdate($ldapServer);
                }
                if ($settings->getAuthenticateBe()) {
                    $ldapServer->setUserType('be');
                    $runs[] = $importer::doUpdate($ldapSerer);
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
    public function importAndUpdateUsersAction(): \Psr\Http\Message\ResponseInterface
    {
        $beUsers = [];
        $feUsers = [];
        $settings = $this->initializeFormSettings();

        $ldapServers = $this->serverRepository->findAll();
        $serverConfigurations = [];
        foreach ($ldapServers as $ldapServer) {
            $serverConfigurations[] = $ldapServer->getConfiguration();
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

        // return $this->htmlResponse();
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $menu = $this->createMenu($moduleTemplate);
        if ($menu instanceof Menu) {
            // $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
            $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
        }
        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * imports or updates users.
     */
    public function doImportAndUpdateUsersAction(FormSettings $formSettings = null)
    {
        $settings = $this->initializeFormSettings($formSettings);
        $this->view->assign('formSettings', $settings);

        $ldapServers = $this->serverRepository->findAll();
        $runs = [];

        if ($ldapServers->count()) {
            $importer = GeneralUtility::makeInstance(LdapImporter::class);
        }

        foreach ($ldapServers as $uid => $ldapServer) {
            $uid = $ldapServer->getUid();
            if (in_array($uid, $settings->getUseServers())) {
                if ($settings->getAuthenticateFe()) {
                    $ldapServer->setUserType('fe');
                    $runs[] = $importer::doImportOrUpdate($ldapServer);
                }
                if ($settings->getAuthenticateBe()) {
                    $ldapServer->setUserType('be');
                    $runs[] = $importer::doImportOrUpdate($ldapServer);
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
    public function deleteUsersAction(): \Psr\Http\Message\ResponseInterface
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

        // return $this->htmlResponse();
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $menu = $this->createMenu($moduleTemplate);
        if ($menu instanceof Menu) {
            // $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
            $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
        }
        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * deletes/deactivates users.
     */
    public function doDeleteUsersAction(FormSettings $formSettings = null)
    {
        $settings = $this->initializeFormSettings($formSettings);
        $this->view->assign('formSettings', $settings);

        $importer = GeneralUtility::makeInstance(LdapImporter::class);

        $runs = [];
        if ($settings->getAuthenticateFe()) {
            $runs[] = $importer::doDelete('fe', $settings->getHideNotDelete(), $settings->getDeleteNonLdapUsers());
        }
        if ($settings->getAuthenticateBe()) {
            $runs[] = $importer::doDelete('be', $settings->getHideNotDelete(), $settings->getDeleteNonLdapUsers());
        }

        $arguments = [
            'runs' => $runs,
        ];

        $this->redirect('deleteUsers', null, null, $arguments);
    }

    /**
     * configures the login mask.
     */
    public function checkLoginAction(): \Psr\Http\Message\ResponseInterface
    {
        $user = null;
        $settings = $this->initializeFormSettings();

        $ldapServers = $this->serverRepository->findAll();
        $runs = [];

        foreach ($ldapServers as $ldapServer) {
            $uid = $ldapServer->getUid();
            $serverConfigurations[] = $ldapServer->getConfiguration();
        }

        if ($this->request->hasArgument('user')) {
            $user = $this->request->getArgument('user');
        }

        $this->view->assign('formSettings', $settings);
        $this->view->assign('ldapServers', $serverConfigurations);
        $this->view->assign('returnUrl', 'mod.php?M=tools_LdapM1');
        $this->view->assign('user', $user);

        // return $this->htmlResponse();
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $menu = $this->createMenu($moduleTemplate);
        if ($menu instanceof Menu) {
            // $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
            $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
        }
        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * checks whether a user can be authenticated successfully.
     */
    public function doCheckLoginAction(FormSettings $formSettings = null)
    {
        $settings = $this->initializeFormSettings($formSettings);
        $this->view->assign('formSettings', $settings);

        $user = [];
        $user['submitted'] = true;
        $user['found'] = false;
        $user['authenticated'] = false;
        
        $ldapServers = $this->serverRepository->findAll();

        foreach ($ldapServers as $ldapServer) {
            $uid = $ldapServer->getUid();
            if (!$user['found']) {
                if (in_array($uid, $settings->getUseServers())) {
                    $ldapServer->setUserType($settings->getLoginType());
                    $loginname = \NormanSeibert\Ldap\Utility\Helpers::sanitizeCredentials($settings->getLoginname());
                    $password = \NormanSeibert\Ldap\Utility\Helpers::sanitizeCredentials($settings->getPassword());
                    
                    $ldapHandler = new LdapHandler();
                    $ldapUsers = $ldapHandler->getUsers($ldapServer, $loginname);
                    if (isset($ldapUsers) && (1 == count($ldapUsers))) {
                        $ldapUsers->rewind();
                        $ldapUser = $ldapUsers->current();
                        $user['found'] = true;
                        $user['serverUid'] = $ldapServer->getConfiguration()->getUid();
                        $user['dn'] = $ldapUser->getDN();
                        $ldapUser = $ldapHandler->authenticateUser($ldapServer, $loginname, $password);
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
     * initializes/stores the form's content.
     *
     * @param FormSettings $settings
     *
     * @return FormSettings
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
            $formSettings = GeneralUtility::makeInstance(FormSettings::class);
        }

        if (isset($settings)) {
            $formSettings = $settings;
        }
        if (!is_object($formSettings)) {
            $formSettings = GeneralUtility::makeInstance(FormSettings::class);
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
