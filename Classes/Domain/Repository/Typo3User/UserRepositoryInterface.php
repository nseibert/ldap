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
 * @copyright 2013 Norman Seibert
 */

interface UserRepositoryInterface {

    /**
     *
     * @param string $dn
     * @param int $pid
     * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface
     */
    function findByDn($dn, $pid = NULL);

    /**
     *
     * @param string $dn
     * @param int $pid
     * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface
     */
    function findByUsername($dn, $pid = NULL);

    /**
     *
     * @param mixed $lastRun
     * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface
     */
    function findByLastRun($lastRun);
} 