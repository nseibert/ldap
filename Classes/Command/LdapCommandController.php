<?php
namespace NormanSeibert\Ldap\Command;
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
 * Controller for scheduled execution
 */
class LdapCommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController {

	/**
	 * @var \NormanSeibert\Ldap\Domain\Repository\FrontendUserRepository
	 * @inject
	 */
	protected $feUserRepository;

	/**
	 * @var \NormanSeibert\Ldap\Domain\Repository\BackendUserRepository
	 * @inject
	 */
	protected $beUserRepository;
	
	/**
	 * @var \NormanSeibert\Ldap\Domain\Model\Configuration\Configuration
	 * @inject
	 */
	protected $ldapConfig;
	
	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface
	 * @inject
	 */
	protected $persistenceManager;
	
	/**
	 * import users from LDAP directory
	 * @param string $servers Comma sparated server identifiers from configuration file
	 * @param boolean $processFe import frontend users
	 * @param boolean $processBe import backend users
	 */
	public function importUsersCommand($servers, $processFe = FALSE, $processBe = FALSE) {
		$ldapServers = $this->ldapConfig->getLdapServers();
		$serverUids = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $servers, TRUE);
		foreach ($ldapServers as $server) {
			if (\TYPO3\CMS\Core\Utility\GeneralUtility::inArray($serverUids, $server->getConfiguration()->getUid())) {
				$this->outputLine('Importing from server: ' . $server->getConfiguration()->getUid());
				$importer = $this->objectManager->get('NormanSeibert\\Ldap\\Service\\LdapImporter');
				$runs = array();
				if ($processFe) {
					$importer->init($server, 'fe');
					$runs[] = $importer->doImport();
					$this->persistenceManager->persistAll();
					$feUsers = $this->feUserRepository->countByLastRun($runs);
					$this->outputLine('Frontend users: ' . $feUsers);
				}
				if ($processBe) {
					$importer->init($server, 'be');
					$runs[] = $importer->doImport();
					$this->persistenceManager->persistAll();
					$beUsers = $this->beUserRepository->countByLastRun($runs);
					$this->outputLine('Backend users: ' . $beUsers);
				}
			}
		}
	}
	
	/**
	 * update users from LDAP directory
	 * @param string $servers Comma sparated server identifiers from configuration file
	 * @param boolean $processFe update frontend users
	 * @param boolean $processBe update backend users
	 */
	public function updateUsersCommand($servers, $processFe = FALSE, $processBe = FALSE) {
		$ldapServers = $this->ldapConfig->getLdapServers();
		$serverUids = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $servers, TRUE);
		foreach ($ldapServers as $server) {
			if (\TYPO3\CMS\Core\Utility\GeneralUtility::inArray($serverUids, $server->getConfiguration()->getUid())) {
				$this->outputLine('Updating from server: ' . $server->getConfiguration()->getUid());
				$importer = $this->objectManager->get('NormanSeibert\\Ldap\\Service\\LdapImporter');
				$runs = array();
				if ($processFe) {
					$importer->init($server, 'fe');
					$runs[] = $importer->doUpdate();
					$this->persistenceManager->persistAll();
					$feUsers = $this->feUserRepository->countByLastRun($runs);
					$this->outputLine('Frontend users: ' . $feUsers);
				}
				if ($processBe) {
					$importer->init($server, 'be');
					$runs[] = $importer->doUpdate();
					$this->persistenceManager->persistAll();
					$beUsers = $this->beUserRepository->countByLastRun($runs);
					$this->outputLine('Backend users: ' . $beUsers);
				}
			}
		}
	}
	
	/**
	 * import or update users from LDAP directory
	 * @param string $servers Comma sparated server identifiers from configuration file
	 * @param boolean $processFe import/update frontend users
	 * @param boolean $processBe import/update backend users
	 */
	public function importAndUpdateUsersCommand($servers, $processFe = FALSE, $processBe = FALSE) {
		$ldapServers = $this->ldapConfig->getLdapServers();
		$serverUids = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $servers, TRUE);
		foreach ($ldapServers as $server) {
			if (\TYPO3\CMS\Core\Utility\GeneralUtility::inArray($serverUids, $server->getConfiguration()->getUid())) {
				$this->outputLine('Importing/updating from server: ' . $server->getConfiguration()->getUid());
				$importer = $this->objectManager->get('NormanSeibert\\Ldap\\Service\\LdapImporter');
				$runs = array();
				if ($processFe) {
					$importer->init($server, 'fe');
					$runs[] = $importer->doImportOrUpdate();
					$this->persistenceManager->persistAll();
					$feUsers = $this->feUserRepository->countByLastRun($runs);
					$this->outputLine('Frontend users: ' . $feUsers);
				}
				if ($processBe) {
					$importer->init($server, 'be');
					$runs[] = $importer->doImportOrUpdate();
					$this->persistenceManager->persistAll();
					$beUsers = $this->beUserRepository->countByLastRun($runs);
					$this->outputLine('Backend users: ' . $beUsers);
				}
			}
		}
	}
	
	/**
	 * delete/disable users not in LDAP directory
	 * @param boolean $processFe delete frontend users
	 * @param boolean $processBe delete backend users
	 * @param boolean $hideNotDelete disable users instead of deleting them
	 * @param boolean $deleteNonLdapUsers delete non-LDAP users
	 */
	public function deleteUsersCommand($processFe = FALSE, $processBe = FALSE, $hideNotDelete = FALSE, $deleteNonLdapUsers = FALSE) {
		$importer = $this->objectManager->get('NormanSeibert\\Ldap\\Service\\LdapImporter');
		$runs = array();
		if ($processFe) {
			$importer->init(NULL, 'fe');
			$runs[] = $importer->doDelete($hideNotDelete, $deleteNonLdapUsers);
			$this->persistenceManager->persistAll();
			$feUsers = $this->feUserRepository->countByLastRun($runs);
			$this->outputLine('Frontend users: ' . $feUsers);
		}
		if ($processBe) {
			$importer->init(NULL, 'be');
			$runs[] = $importer->doDelete($hideNotDelete, $deleteNonLdapUsers);
			$this->persistenceManager->persistAll();
			$beUsers = $this->beUserRepository->countByLastRun($runs);
			$this->outputLine('Backend users: ' . $beUsers);
		}
	}
}
?>