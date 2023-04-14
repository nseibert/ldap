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
 use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
 use TYPO3\CMS\Extbase\Persistence\QueryInterface;
 use NormanSeibert\Ldap\Utility\Helpers;
 use NormanSeibert\Ldap\Domain\Model\Typo3User\FrontendUser;

/**
 * Repository for TYPO3 frontend users.
 */
class FrontendUserRepository extends Repository
{
    public function findAll(): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);

        return $query->execute();
    }

    public function findByUsername(string $username, int $pid = null): FrontendUser | QueryResultInterface
    {
        $user = false;
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        Helpers::setRespectEnableFieldsToFalse($query);
        if ($pid) {
            $query->matching(
                $query->logicalAnd(
                    $query->equals('pid', $pid),
                    $query->equals('username', $username)
                )
            );
        } else {
            $query->matching(
                $query->equals('username', $username)
            );
        }
        $users = $query->execute();
        $userCount = $users->count();
        if (1 == $userCount) {
            $user = $users->getFirst();
        }

        return $user;
    }

    public function findByDn(string $dn, int $pid = null): FrontendUser | QueryResultInterface
    {
        $user = false;
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        \NormanSeibert\Ldap\Utility\Helpers::setRespectEnableFieldsToFalse($query);
        if ($pid) {
            $query->matching(
                $query->logicalAnd(
                    $query->equals('pid', $pid),
                    $query->equals('dn', $dn)
                )
            );
        } else {
            $query->matching(
                $query->equals('dn', $dn)
            );
        }
        $users = $query->execute();
        $userCount = $users->count();
        if (1 == $userCount) {
            $user = $users->getFirst();
        }

        return $user;
    }

    public function findByLastRun(array | string $lastRun): FrontendUser | QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->getQuerySettings()->setIncludeDeleted(true);
        Helpers::setRespectEnableFieldsToFalse($query);
        if (is_array($lastRun)) {
            if (1 == count($lastRun)) {
                $query->matching(
                    $query->equals('lastRun', $lastRun[0])
                );
            } else {
                $query->matching(
                    $query->in('lastRun', $lastRun)
                );
            }
        } else {
            $query->matching(
                $query->equals('lastRun', $lastRun)
            );
        }

        return $query->execute();
    }

    public function countByLastRun(array | string $lastRun): int
    {
        return $this->findByLastRun($lastRun)->count();
    }

    public function findLdapImported(): FrontendUser | QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching(
            $query->logicalNot(
                $query->equals('dn', '')
            )
        );
        $query->setOrderings(
            [
                'serverUid' => QueryInterface::ORDER_ASCENDING,
            ]
        );

        return $query->execute();
    }
}
