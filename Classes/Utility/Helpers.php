<?php
namespace NormanSeibert\Ldap\Utility;
/**
 * Short description for file
 * 
 * Long description (if any) ...
 * 
 * PHP version 5
 * 
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
 * @category  CategoryName
 * @package   ldap
 * @author	Norman Seibert <seibert@entios.de>
 * @copyright 2013 Norman Seibert
 * @license   http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @version   CVS: $Id:$
 * @link	  http://pear.php.net/package/ldap
 * @see	   References to other sections (if any)...
 */

/**
 * Short description for class
 * 
 * Long description (if any) ...
 * 
 * @category  CategoryName
 * @package   ldap
 * @author	Norman Seibert <seibert@entios.de>
 * @copyright 2013 Norman Seibert
 * @license   http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @version   Release: @package_version@
 * @link	  http://pear.php.net/package/ldap
 * @see	   References to other sections (if any)...
 */
class Helpers {

	protected $error = -1;
	
	function addError($severity = \TYPO3\CMS\Core\Messaging\FlashMessage::INFO, $message = '', $server = '', $data = null) {
		$msg = $message;
		if ($data) {
			$msg .= '<br/>'.\TYPO3\CMS\Core\Utility\GeneralUtility::view_array($data);
		}
		$message = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
			'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
			$msg,
			$server,
			$severity
		);
		\TYPO3\CMS\Core\Messaging\FlashMessageQueue::addMessage($message);
		if ($severity > $this->error) {
			$this->error = $severity;
		}
		$this->arrErrors[] = array(
			'message' => $msg,
			'server' => $server,
			'severity' => $severity
		);
	}
	
	function checkValue($value, $eval, $list = '') {
		$res = array();
		$newValue = $value;
		$set = true;
		$arrEval = explode(',', $eval);

		//while ((list($key, $func) = each($arrEval)) && $set) {
		foreach ($arrEval as $key => $func) {
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
							if ((''.$newValue != ''.$value) || ($newValue == 0)) {
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
						if (!isset($value) || $value === '') {
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
	
	function sanitizeQuery($string) {
		$sanitized = array(
			'\\' => '\5c',
			'*' => '\2a',
			'(' => '\28',
			')' => '\29',
			"\x00" => '\00'
		);
		$res = str_replace(array_keys($sanitized), array_values($sanitized), $string);
		
		return $res;
	}
	
	function sanitizeCredentials($string) {
		$sanitized = array(
			"\x00" => '\00'
		);
		$res = str_replace(array_keys($sanitized), array_values($sanitized), $string);
		
		return $res;
	}
	
	function generatePassword($l = 8, $c = 0, $n = 0, $s = 0) {
		// get count of all required minimum special chars
		$count = $c + $n + $s;
		$ok = true;
		$out = false;
		
		// sanitize inputs; should be self-explanatory
		if (!is_int($l) || !is_int($c) || !is_int($n) || !is_int($s)) {
			trigger_error('Argument(s) not an integer', E_USER_WARNING);
			$ok = false;
		} elseif ($l < 0 || $l > 20 || $c < 0 || $n < 0 || $s < 0) {
			trigger_error('Argument(s) out of range', E_USER_WARNING);
			$ok = false;
		} elseif ($c > $l) {
			trigger_error('Number of password capitals required exceeds password length', E_USER_WARNING);
			$ok = false;
		} elseif ($n > $l) {
			trigger_error('Number of password numerals exceeds password length', E_USER_WARNING);
			$ok = false;
		} elseif ($s > $l) {
			trigger_error('Number of password capitals exceeds password length', E_USER_WARNING);
			$ok = false;
		} elseif ($count > $l) {
			trigger_error('Number of password special characters exceeds specified password length', E_USER_WARNING);
			$ok = false;
		}
		
		// all inputs clean, proceed to build password
		if ($ok) {
			// change these strings if you want to include or exclude possible password characters
			$chars = "abcdefghijklmnopqrstuvwxyz";
			$caps = strtoupper($chars);
			$nums = "0123456789";
			$syms = "!@#$%^&*()-+?";
			
			// build the base password of all lower-case letters
			for ($i = 0; $i < $l; $i++) {
				$out .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
			}
			
			// create arrays if special character(s) required
			if ($count) {
				// split base password to array; create special chars array
				$tmp1 = str_split($out);
				$tmp2 = array();
			
				// add required special character(s) to second array
				for ($i = 0; $i < $c; $i++) {
					array_push($tmp2, substr($caps, mt_rand(0, strlen($caps) - 1), 1));
				}
				for ($i = 0; $i < $n; $i++) {
					array_push($tmp2, substr($nums, mt_rand(0, strlen($nums) - 1), 1));
				}
				for ($i = 0; $i < $s; $i++) {
					array_push($tmp2, substr($syms, mt_rand(0, strlen($syms) - 1), 1));
				}
			
				// hack off a chunk of the base password array that's as big as the special chars array
				$tmp1 = array_slice($tmp1, 0, $l - $count);
				// merge special character(s) array with base password array
				$tmp1 = array_merge($tmp1, $tmp2);
				// mix the characters up
				shuffle($tmp1);
				// convert to string for output
				$out = implode('', $tmp1);
			}
		}

		return $out;
	}
	
	/**
	 * Returns a given string with underscores as lowerCamelCase.
	 * Example: Converts minimal_value to minimalValue
	 *
	 * @param string $string String to be converted to camel case
	 * @return string lowerCamelCasedWord
	 */
	function underscoredToLowerCamelCase($string) {
		$upperCamelCase = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
		$lowerCamelCase = lcfirst($upperCamelCase);
		return $lowerCamelCase;
	}
	
	function setRespectEnableFieldsToFalse($query) {
		if (function_exists($query->getQuerySettings()->setRespectEnableFields)) {
			$query->getQuerySettings()->setRespectEnableFields(FALSE);
		} else {
			$query->getQuerySettings()->setIgnoreEnableFields(TRUE);
		}
	}

    /**
     * Get the list of individual characters used by the search splitting algorithm.
     * @return array characters to use in split searches
     */
    public function getSearchCharacterRange() {
        $numerals = range(0, 9);
        $alphabet = range('a', 'z');

        return array_merge($alphabet, $numerals);
    }
}
?>