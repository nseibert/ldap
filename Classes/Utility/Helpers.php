<?php

namespace NormanSeibert\Ldap\Utility;

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

// Various helper functions

class Helpers
{
    /**
     * Adds an error to TYPO3's backend flashmessage queue.
     *
     * @param int    $severity
     * @param string $message
     * @param int    $server
     * @param array  $data
     */
    public static function addError($severity = \TYPO3\CMS\Core\Messaging\FlashMessage::INFO, $message = '', $server = '', $data = null)
    {
        $msg = $message;
        if ($data) {
            $msg .= '<br/>'.\TYPO3\CMS\Core\Utility\ArrayUtility::flatten($data);
        }
        $flashMessage = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Messaging\FlashMessage::class,
            $msg,
            'LDAP server '.$server,
            $severity,
            false
        );
        $flashMessageService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessageService::class);
        // $messageQueue = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessageQueue', 'ldap');
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier('ldap');
        // @extensionScannerIgnoreLine
        $messageQueue->addMessage($flashMessage);
    }

    /**
     * Checks a value (typically from LDAP configuration) against evaluation rules.
     *
     * @param string $value
     * @param string $eval
     * @param string $list
     *
     * @return array
     */
    public static function checkValue($value, $eval, $list = '')
    {
        $res = [];
        $set = true;
        $arrEval = explode(',', $eval);

        foreach ($arrEval as $func) {
            if ($set) {
                switch ($func) {
                    case 'int':
                        if ($value) {
                            $newValue = intval($value);
                            if (''.$newValue != ''.$value) {
                                $res['error'] = '"'.$value.'" is not an integer.';
                                $set = false;
                            }
                        }

                        break;

                    case 'int+':
                        if ($value) {
                            $newValue = abs(intval($value));
                            if ((''.$newValue != ''.$value) || (0 == $newValue)) {
                                $res['error'] = '"'.$value.'" is not a positive integer';
                                $set = false;
                            }
                        }

                        break;

                    case 'int0+':
                        if ($value) {
                            $newValue = abs(intval($value));
                            if (''.$newValue != ''.$value) {
                                $res['error'] = '"'.$value.'" is not a positive integer or zero';
                                $set = false;
                            }
                        }

                        break;

                    case 'required':
                        if (!isset($value) || '' === $value) {
                            $res['error'] = 'This value is required';
                            $set = false;
                        }

                    break;

                    case 'list':
                        if (!\TYPO3\CMS\Core\Utility\GeneralUtility::inList($list, $value)) {
                            $res['error'] = '"'.$value.'" is not in the list of allowed values ('.$list.')';
                            $set = false;
                        }

                        break;

                    default:
                        $set = false;
                }
            }
        }
        if ($set) {
            $res['value'] = $value;
        }

        return $res;
    }

    /**
     * Sanitize query string to prevent LDAP injection.
     *
     * @param string $string
     *
     * @return string
     */
    public static function sanitizeQuery($string)
    {
        $sanitized = [
            '\\' => '\5c',
            '*' => '\2a',
            '(' => '\28',
            ')' => '\29',
            "\x00" => '\00',
        ];

        return str_replace(array_keys($sanitized), array_values($sanitized), $string);
    }

    /**
     * Sanitize username/password to prevent LDAP injection.
     *
     * @param string $string
     *
     * @return string
     */
    public static function sanitizeCredentials($string)
    {
        $sanitized = [
            "\x00" => '\00',
        ];

        return str_replace(array_keys($sanitized), array_values($sanitized), $string);
    }

    /**
     * Returns a given string with underscores as lowerCamelCase.
     * Example: Converts minimal_value to minimalValue.
     *
     * @param string $string String to be converted to camel case
     *
     * @return string
     */
    public static function underscoredToLowerCamelCase($string)
    {
        $upperCamelCase = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));

        return lcfirst($upperCamelCase);
    }

    /**
     * sets the enable fields of the query correctly for TYPO3 6.x.
     *
     * @param \TYPO3\CMS\Extbase\Persistence\QueryInterface $query
     */
    public static function setRespectEnableFieldsToFalse($query)
    {
        if (function_exists($query->getQuerySettings()->setRespectEnableFields)) {
            $query->getQuerySettings()->setRespectEnableFields(false);
        } else {
            $query->getQuerySettings()->setIgnoreEnableFields(true);
        }
    }

    /**
     * Get the list of individual characters used by the search splitting algorithm.
     *
     * @return array characters to use in split searches
     */
    public static function getSearchCharacterRange()
    {
        $numerals = range(0, 9);
        $alphabet = range('a', 'z');

        return array_merge($alphabet, $numerals);
    }
}
