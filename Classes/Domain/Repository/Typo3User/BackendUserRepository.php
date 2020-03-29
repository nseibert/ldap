<?php
namespace NormanSeibert\Ldap\Domain\Repository\Typo3User;
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
 * @copyright 2020 Norman Seibert
 */

/**
 * Repository for TYPO3 backend users
 */
class BackendUserRepository extends \TYPO3\CMS\Extbase\Domain\Repository\BackendUserRepository implements \NormanSeibert\Ldap\Domain\Repository\Typo3User\UserRepositoryInterface {
	
	/**
	 * 
	 * @param string $username
     * @param int $pid
	 * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface
	 */
	public function findByUsername($username, $pid = NULL) {
		$user = false;
		$query = $this->createQuery();
		$query->getQuerySettings()->setRespectStoragePage(FALSE);
		\NormanSeibert\Ldap\Utility\Helpers::setRespectEnableFieldsToFalse($query);
		$query->matching(	
			$query->equals("username", $username)
		);
		$users = $query->execute();
		$userCount = $users->count();
		if ($userCount == 1) {
			$user = $users->getFirst();
		}
		
		return $user;
	}
	
	/**
	 * 
	 * @param string $dn
     * @param int $pid
	 * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface
	 */
	public function findByDn($dn, $pid = NULL) {
		$user = false;
		$query = $this->createQuery();
		$query->getQuerySettings()->setRespectStoragePage(FALSE);
		\NormanSeibert\Ldap\Utility\Helpers::setRespectEnableFieldsToFalse($query);
		$query->matching(	
			$query->equals("dn", $dn)
		);
		$users = $query->execute();
		$userCount = $users->count();
		if ($userCount == 1) {
			$user = $users->getFirst();
		}
		return $user;
	}
	
	/**
	 * 
	 * @param mixed $lastRun
	 * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface
	 */
	public function findByLastRun($lastRun) {
		$query = $this->createQuery();
		$query->getQuerySettings()->setRespectStoragePage(FALSE);
		$query->getQuerySettings()->setIncludeDeleted(TRUE);
		\NormanSeibert\Ldap\Utility\Helpers::setRespectEnableFieldsToFalse($query);
		if (is_array($lastRun)) {
			if (count($lastRun) == 1) {
				$query->matching(
					$query->equals("lastRun", $lastRun[0])
				);
			} else {
				$query->matching(
					$query->in("lastRun", $lastRun)
				);
			}
		} else {
			$query->matching(
				$query->equals("lastRun", $lastRun)
			);
		}
		$users = $query->execute();
		return $users;
	}
	
	/**
	 * 
	 * @param mixed $lastRun
	 * @return integer
	 */
	public function countByLastRun($lastRun) {
		return $this->findByLastRun($lastRun)->count();
	}
	
	/**
	 * 
	 * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface
	 */
	public function findLdapImported() {
		$query = $this->createQuery();
		// \NormanSeibert\Ldap\Utility\Helpers::setRespectEnableFieldsToFalse($query);
		$query->matching(
			$query->logicalNot(
				$query->equals("dn", "")
			)
		);
		$query->setOrderings(
			array(
				'serverUid' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING
			)
		);
		$users = $query->execute();
		return $users;
	}
}
?>