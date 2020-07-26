<?php

namespace NormanSeibert\Ldap\Domain\Model\LdapUser;

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

use NormanSeibert\Ldap\Domain\Model\Configuration\Configuration;
use NormanSeibert\Ldap\Utility\ContentRendererLight;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Model for objects read from LDAP server.
 */
class LdapEntity extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity implements \Psr\Log\LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var string
     */
    protected $dn;

    /**
     * @var array
     */
    protected $attributes;

    /**
     * @var \NormanSeibert\Ldap\Domain\Model\LdapServer\Server
     */
    protected $ldapServer;

    /**
     * @var Configuration
     */
    protected $ldapConfig;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var ContentRendererLight
     */
    protected $cObj;

    public function __construct()
    {
        $this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
        $this->ldapConfig = $this->objectManager->get('NormanSeibert\\Ldap\\Domain\\Model\\Configuration\\Configuration');
        $this->cObj = $this->objectManager->get('NormanSeibert\\Ldap\\Utility\\ContentRendererLight');
    }

    /**
     * @param string $dn
     *
     * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\User
     */
    public function setDN($dn)
    {
        $this->dn = $dn;

        return $this;
    }

    /**
     * @return string
     */
    public function getDN()
    {
        return $this->dn;
    }

    /**
     * @param array $attrs
     *
     * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\User
     */
    public function setAttributes($attrs)
    {
        $this->attributes = $attrs;

        return $this;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param string $attr
     * @param string $value
     *
     * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\User
     */
    public function setAttribute($attr, $value)
    {
        $this->attributes[$attr] = $value;

        return $this;
    }

    /**
     * @param string $attr
     *
     * @return array
     */
    public function getAttribute($attr)
    {
        return $this->attributes[$attr];
    }

    /** Retrieves a single attribute from LDAP record.
     *
     * @param array  $mapping
     * @param string $key
     * @param array  $data
     *
     * @return array
     */
    protected function getAttributeMapping($mapping, $key, $data)
    {
        // stdWrap does no longer handle arrays, therefore we have to check and map manually
        // values derived from LDAP attribues
        $tmp = explode(':', $mapping[$key.'.']['data']);
        if (is_array($tmp)) {
            $attrName = $tmp[1];
            $ldapData = $data[$attrName];

            $msg = 'Mapping attributes';
            $logArray = [
                'Key' => $key,
                'Rules' => $mapping,
                'Data' => $data,
            ];
            if (3 == $this->ldapConfig->logLevel) {
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
    protected function mapAttribute($mapping, $key, $data)
    {
        $ldapData = $this->getAttributeMapping($mapping, $key, $data);

        $stdWrap = $mapping[$key.'.']['stdWrap.'];
        if (is_array($mapping[$key.'.']['stdWrap.'])) {
            unset($mapping[$key.'.']['stdWrap.']);
        }

        if (is_array($ldapData)) {
            unset($ldapData['count']);
            $ldapDataList = implode(',', $ldapData);
            $result = $this->cObj->stdWrap($ldapDataList, $stdWrap);
        } else {
            $result = $this->cObj->stdWrap($ldapData, $stdWrap);
        }

        $msg = 'Mapping for attribute "'.$key.'"';
        $logArray = [
            'LDAP attribute value' => $ldapData,
            'Mapping result' => $result,
        ];
        if (3 == $this->ldapConfig->logLevel) {
            $this->logger->debug($msg, $logArray);
        }
        // static values, overwrite those from LDAP if set
        $tmp = $mapping[$key.'.']['value'];
        if ($tmp) {
            $result = $tmp;
            $msg = 'Setting attribute "'.$key.'" to: '.$result;
            if (3 == $this->ldapConfig->logLevel) {
                $this->logger->debug($msg);
            }
        }

        return $result;
    }

    /** Maps attributes from LDAP record to TYPO3 DB fields.
     *
     * @param string $mappingType
     * @param array  $useAttributes
     *
     * @return array
     */
    protected function mapAttributes($mappingType = 'user', $useAttributes = [])
    {
        $insertArray = [];

        if ('group' == $mappingType) {
            $mapping = $this->userRules->getGroupRules()->getMapping();
            $attributes = $useAttributes;
        } else {
            $mapping = $this->userRules->getMapping();
            $attributes = $this->attributes;
        }
        if (is_array($mapping)) {
            $msg = 'Mapping attributes';
            $logArray = [
                'Type' => $mappingType,
                'Rules' => $mapping,
                'Data' => $attributes,
            ];
            if (3 == $this->ldapConfig->logLevel) {
                $this->logger->debug($msg, $logArray);
            }

            foreach ($mapping as $key => $value) {
                if ('username.' != $key) {
                    if ('.' == substr($key, strlen($key) - 1, 1)) {
                        $key = substr($key, 0, strlen($key) - 1);
                    }
                    $result = $this->mapAttribute($mapping, $key, $attributes);
                    $insertArray[$key] = $result;
                }
            }
        } else {
            $msg = 'No mapping rules found for type "'.$mappingType.'"';
            if ($this->ldapConfig->logLevel >= 2) {
                $this->logger->notice($msg);
            }
        }

        $msg = 'Mapped values to insert into or update to DB';
        if (3 == $this->ldapConfig->logLevel) {
            $this->logger->debug($msg, $insertArray);
        }

        return $insertArray;
    }
}
