<?php

namespace NormanSeibert\Ldap\Service\Mapping;

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

// Various mapper functions

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GenericMapper
{
    private LoggerInterface $logger;

    protected int $logLevel;

    public function __construct()
    {
        $conf = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Configuration\\ExtensionConfiguration')->get('ldap');
        $this->logLevel = $conf['logLevel'];

        $this->logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
    }

    /** Retrieves a single attribute from LDAP record.
     *
     * @param array  $mapping
     * @param string $key
     * @param array  $data
     *
     * @return array
     */
    public function getAttributeMapping($mapping, $key, $data)
    {
        $ldapData = array();
        
        // stdWrap does no longer handle arrays, therefore we have to check and map manually
        // values derived from LDAP attribues
        $tmp = explode(':', $mapping[$key.'.']['data']);
        if (is_array($tmp)) {
            $attrName = $tmp[1];
            if (isset($data[$attrName])) {
                $ldapData = $data[$attrName];
            }

            $msg = 'Mapping attributes';
            $logArray = [
                'Key' => $key,
                'Rules' => $mapping,
                'Data' => $data,
            ];
            if (3 == $this->logLevel) {
                $this->logger->debug($msg, $logArray);
            }
        }

        return $ldapData;
    }

    /** Maps a single attribute from LDAP record to TYPO3 DB fields.
     *
     * @param array  $mapping
     * @param string $key
     * @param array  $data
     *
     * @return string
     */
    public function mapAttribute($mapping, $key, $data)
    {
        $ldapData = $this->getAttributeMapping($mapping, $key, $data);

        if (isset($ldapData) && is_array($ldapData)) {
            unset($ldapData['count']);
            $ldapDataList = implode(',', $ldapData);
            $result = $ldapDataList;
        } else {
            $result = $ldapData;
        }

        $msg = 'Mapping for attribute "'.$key.'"';
        $logArray = [
            'LDAP attribute value' => $ldapData,
            'Mapping result' => $result,
        ];
        if (3 == $this->logLevel) {
            $this->logger->debug($msg, $logArray);
        }
        // static values, overwrite those from LDAP if set
        if (isset($mapping[$key.'.']['value'])) {
            $tmp = $mapping[$key.'.']['value'];
            if ($tmp) {
                $result = $tmp;
                $msg = 'Setting attribute "'.$key.'" to: '.$result;
                if (3 == $this->logLevel) {
                    $this->logger->debug($msg);
                }
            }
        }

        return $result;
    }
    /** Maps attributes from LDAP record to TYPO3 DB fields.
     */
    public function mapAttributes(array $mapping, array $attributes, array $useAttributes = []): array
    {
        $insertArray = [];
        if (is_array($mapping)) {
            $msg = 'Mapping attributes';
            $logArray = [
                'Rules' => $mapping,
                'Data' => $attributes,
            ];
            if (3 == $this->logLevel) {
                $this->logger->debug($msg, $logArray);
            }

            foreach ($mapping as $key => $value) {
                if ('username.' != $key) {
                    if ('.' == substr($key, strlen($key) - 1, 1)) {
                        $key = substr($key, 0, strlen($key) - 1);
                    }
                    $result = self::mapAttribute($mapping, $key, $attributes);
                    $insertArray[$key] = $result;
                }
            }
        } else {
            $msg = 'No mapping rules found.';
            if ($this->logLevel >= 2) {
                $this->logger->notice($msg);
            }
        }

        $msg = 'Mapped values to insert into or update to DB';
        if (3 == $this->logLevel) {
            $this->logger->debug($msg, $insertArray);
        }

        return $insertArray;
    }
}