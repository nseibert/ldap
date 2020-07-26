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

use Psr\Log\LoggerAwareTrait;

/**
 * Service to import users from LDAP directory to TYPO3 database
 */
class LdapImporter implements \Psr\Log\LoggerAwareInterface {

	use LoggerAwareTrait;

    /**
     * @var string
     */
    protected $table;

	/**
	 * @var \NormanSeibert\Ldap\Domain\Model\Configuration\Configuration
	 * @TYPO3\CMS\Extbase\Annotation\Inject
	 */
	protected $ldapConfig;
	
	/**
	 * @var \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
	 */
	protected $ldapServer;
	
	/**
	 * @var \NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserRepository
	 * @TYPO3\CMS\Extbase\Annotation\Inject
	 */
	protected $feUserRepository;
	
	/**
	 * @var \NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserRepository
	 * @TYPO3\CMS\Extbase\Annotation\Inject
	 */
	protected $beUserRepository;
	
	/**
	 *
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 * @TYPO3\CMS\Extbase\Annotation\Inject
	 */
	protected $objectManager;
	
	/**
	 * initializes the importer
	 * 
	 * @param \NormanSeibert\Ldap\Domain\Model\LdapServer\Server $server
	 * @param string $scope
	 */
	public function init(\NormanSeibert\Ldap\Domain\Model\LdapServer\Server $server = NULL, $scope) {
		$this->ldapServer = $server;
		if (is_object($server)) {
			$this->ldapServer->setScope($scope);
		}
		if ($scope == 'be') {
			$this->table = 'be_users';
		} else {
			$this->table = 'fe_users';
		}
		$this->ldapConfig = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\Configuration\\Configuration');
	}

    /**
     * creates new TYPO3 users
     *
     * @param string $runIdentifier
     * @param array $ldapUsers
     */
	private function storeNewUsers($runIdentifier, $ldapUsers) {
		foreach ($ldapUsers as $user) {
            /* @var $user \NormanSeibert\Ldap\Domain\Model\LdapUser\User */
			$user->loadUser();
			$typo3User = $user->getUser();
			if (!is_object($typo3User)) {
				$user->addUser($runIdentifier);
			}
		}
	}

    /**
     * updates TYPO3 users
     *
     * @param string $runIdentifier
     * @param array $ldapUsers
     */
	private function updateUsers($runIdentifier, $ldapUsers) {
		foreach ($ldapUsers as $user) {
            /* @var $user \NormanSeibert\Ldap\Domain\Model\LdapUser\User */
			$user->loadUser();
			$typo3User = $user->getUser();
			if (is_object($typo3User)) {
				$user->updateUser($runIdentifier);
			}
		}
	}

    /**
     * imports or updates TYPO3 users
     *
     * @param string $runIdentifier
     * @param array $ldapUsers
     */
	private function storeUsers($runIdentifier, $ldapUsers) {
		foreach ($ldapUsers as $user) {
            /* @var $user \NormanSeibert\Ldap\Domain\Model\LdapUser\User */
			$user->loadUser();
			$typo3User = $user->getUser();
			if (is_object($typo3User)) {
				$user->updateUser($runIdentifier);
			} else {
				$user->addUser($runIdentifier);
			}
		}
	}

	/**
     * retrieves user records from LDAP
     *
     * @param string $runIdentifier
     * @param string $command
     * @param string $search
     */
    private function getUsers($runIdentifier, $command, $search = '*') {
        $ldapUsers = $this->ldapServer->getUsers( $search, false );
        if (is_array($ldapUsers)) {
            switch ($command) {
                case 'import':
                    $this->storeNewUsers($runIdentifier, $ldapUsers);
                    break;
                case 'update':
                    $this->updateUsers($runIdentifier, $ldapUsers);
                    break;
                case 'importOrUpdate':
                    $this->storeUsers($runIdentifier, $ldapUsers);
                    break;
            }
        } else {
            // recursive search
            if ($this->ldapConfig->logLevel >= 1) {
                $msg = 'LDAP query limit exceeded';
                $this->logger->notice($msg);
            }
            $searchCharacters = \NormanSeibert\Ldap\Utility\Helpers::getSearchCharacterRange();
            foreach ($searchCharacters as $thisCharacter) {
                $newSearch = substr_replace($search, $thisCharacter, 1, 0);
                $msg = 'Query server: ' . $this->ldapServer->getConfiguration()->getUid() . ' with getUsers("' . $newSearch . '")';
                if ($this->ldapConfig->logLevel == 3) {
                    $this->logger->debug($msg);
                }
                $this->getUsers($runIdentifier, $command, $newSearch);
            }
        }
    }
	
	/**
	 * imports users from LDAP to TYPO3 DB
	 * 
	 * @return string
	 */
	public function doImport() {
		$runIdentifier = uniqid();
		$this->ldapServer->loadAllGroups();
		$this->getUsers($runIdentifier, 'import');
		return $runIdentifier;
	}
	
	/**
	 * updates users from LDAP to TYPO3 DB
	 * 
	 * @return string
	 */
	public function doUpdate() {
		$runIdentifier = uniqid();
		$this->ldapServer->loadAllGroups();
		$this->getUsers($runIdentifier, 'update');
		return $runIdentifier;
	}
	
	/**
	 * imports resp. updates users from LDAP to TYPO3 DB
	 * 
	 * @return string
	 */
	public function doImportOrUpdate() {
		$runIdentifier = uniqid();
		$this->ldapServer->loadAllGroups();
		$this->getUsers($runIdentifier, 'importOrUpdate');	
		return $runIdentifier;
	}
	
	/**
	 * deletes/deactivates users from LDAP to TYPO3 DB
	 * 
	 * @param boolean $hide
	 * @param boolean $deleteNonLdapUsers
	 * @return string
	 */
	public function doDelete($hide = TRUE, $deleteNonLdapUsers = FALSE) {
		$runIdentifier = uniqid();
		if ($this->table == 'be_users') {
			$repository = $this->beUserRepository;
		} else {
			$repository = $this->feUserRepository;
		}
		if ($deleteNonLdapUsers) {
			$users = $repository->findAll();
		} else {
			$users = $repository->findLdapImported();
		}
		
		$tmpServer = NULL;
		$removeUsers = array();
		foreach ($users as $user) {
            /* @var $user \NormanSeibert\Ldap\Domain\Model\Typo3User\UserInterface */
			if ($user->getServerUid()) {
				$server = $this->ldapConfig->getLdapServer($user->getServerUid());
				if ($server != $tmpServer) {
					$tmpServer = $server;
				}
				$ldapUser = $tmpServer->getUser($user->getDN());
				if (!is_object($ldapUser)) {
					$user->setLastRun($runIdentifier);
					if ($hide) {
						$user->setIsDisabled(TRUE);
					} else {
						$removeUsers[] = $user;
					}
					$repository->update($user);
				}
			} else {
				$user->setLastRun($runIdentifier);
				if ($hide) {
					$user->setIsDisabled(TRUE);
				} else {
					$removeUsers[] = $user;
				}
				$repository->update($user);
			}
		}

		foreach ($removeUsers as $user) {
			$user->setLastRun($runIdentifier);
			$repository->update($user);
			$repository->remove($user);
		}
		
		return $runIdentifier;
	}
}

?>
