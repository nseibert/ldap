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
 * @author	  Norman Seibert <seibert@entios.de>
 * @copyright 2020 Norman Seibert
 */

use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Repository for TYPO3 frontend usergroups.
 */
class FrontendUserGroupRepository extends Repository
{
    /**
     * @return array
     */
    public function findAll()
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        \NormanSeibert\Ldap\Utility\Helpers::setRespectEnableFieldsToFalse($query);
        $groups = $query->execute();
    }

    /**
     * @param string $grouptitle
     * @param int    $pid
     *
     * @return \TYPO3\CMS\Extbase\Domain\Repository\FrontendUserGroupRepository | \TYPO3\CMS\Extbase\Persistence\QueryResultInterface
     */
    public function findByGroupTitle($grouptitle, $pid = null)
    {
        $group = false;
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        \NormanSeibert\Ldap\Utility\Helpers::setRespectEnableFieldsToFalse($query);
        if ($pid) {
            $query->matching(
                $query->logicalAnd(
                    $query->equals('pid', $pid),
                    $query->equals('title', $grouptitle)
                )
            );
        } else {
            $query->matching(
                $query->equals('title', $grouptitle)
            );
        }
        $groups = $query->execute();
        $groupCount = $groups->count();
        if (1 == $groupCount) {
            $group = $groups->getFirst();
        }

        return $group;
    }


    public function findByUids($uidList)
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        \NormanSeibert\Ldap\Utility\Helpers::setRespectEnableFieldsToFalse($query);
        $query->matching(
            $query->in('uid', $uidList)
        );
        $groups = $query->execute();
    }

    public function findByPid($pid)
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        \NormanSeibert\Ldap\Utility\Helpers::setRespectEnableFieldsToFalse($query);
        $query->matching(
            $query->equals('pid', $pid)
        );
        $groups = $query->execute();
    }

    public function findByDn($dn, $pid = null)
    {
        $user = false;
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        \NormanSeibert\Ldap\Utility\Helpers::setRespectEnableFieldsToFalse($query);
        $query->matching(
            $query->logicalAnd(
                $query->equals('pid', $pid),
                $query->equals('tx_ldap_dn', $dn)
            )
        );
        $users = $query->execute();
        $userCount = $users->count();
        if (1 == $userCount) {
            $user = $users->getFirst();
        }

        return $user;
    }

    public function findByLastRun($lastRun)
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        \NormanSeibert\Ldap\Utility\Helpers::setRespectEnableFieldsToFalse($query);
        $query->matching(
            $query->in('tx_ldap_lastrun', $lastRun)
        );

        return $query->execute();
    }

    public function findLdapImported()
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        // \NormanSeibert\Ldap\Utility\Helpers::setRespectEnableFieldsToFalse($query);
        $query->matching(
            $query->logicalNot(
                $query->equals('dn', '')
            )
        );
        $query->setOrderings(
            [
                'serverUid' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING,
            ]
        );

        return $query->execute();
    }
}
