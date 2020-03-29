<?php
namespace NormanSeibert\Ldap\Utility;
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
use TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 *  Functions copied from TYPO3 CMS ContentRendererObject
 */
class ContentRendererLight implements \Psr\Log\LoggerAwareInterface {
    use LoggerAwareTrait;

    /**
     * stdWrap functions in their correct order
     *
     * @see stdWrap()
     */
    public $stdWrapOrder = [
        // this is a placeholder for checking if the content is available in cache
        'setContentToCurrent' => 'boolean',
        'setContentToCurrent.' => 'array',
        'setCurrent' => 'string',
        'setCurrent.' => 'array',
        'data' => 'getText',
        'data.' => 'array',
        'field' => 'fieldName',
        'field.' => 'array',
        'current' => 'boolean',
        'current.' => 'array',
        'numRows.' => 'array',
        'preIfEmptyListNum' => 'listNum',
        'preIfEmptyListNum.' => 'array',
        'ifNull' => 'string',
        'ifNull.' => 'array',
        'ifEmpty' => 'string',
        'ifEmpty.' => 'array',
        'ifBlank' => 'string',
        'ifBlank.' => 'array',
        'listNum' => 'listNum',
        'listNum.' => 'array',
        'trim' => 'boolean',
        'trim.' => 'array',
        'strPad.' => 'array',
        'stdWrap' => 'stdWrap',
        'stdWrap.' => 'array',
        'stdWrapProcess' => 'hook',
        // this is a placeholder for the third Hook
        'required' => 'boolean',
        'required.' => 'array',
        'if.' => 'array',
        'fieldRequired' => 'fieldName',
        'fieldRequired.' => 'array',
        'csConv' => 'string',
        'csConv.' => 'array',
        'split.' => 'array',
        'replacement.' => 'array',
        'char' => 'integer',
        'char.' => 'array',
        'intval' => 'boolean',
        'intval.' => 'array',
        'hash' => 'string',
        'hash.' => 'array',
        'numberFormat.' => 'array',
        'expandList' => 'boolean',
        'expandList.' => 'array',
        'date' => 'dateconf',
        'date.' => 'array',
        'strtotime' => 'strtotimeconf',
        'strtotime.' => 'array',
        'strftime' => 'strftimeconf',
        'strftime.' => 'array',
        'case' => 'case',
        'case.' => 'array',
        'substring' => 'parameters',
        'substring.' => 'array',
        'crop' => 'crop',
        'crop.' => 'array',
        'innerWrap' => 'wrap',
        'innerWrap.' => 'array',
        'innerWrap2' => 'wrap',
        'innerWrap2.' => 'array',
        'wrap' => 'wrap',
        'wrap.' => 'array',
        'noTrimWrap' => 'wrap',
        'noTrimWrap.' => 'array',
        'wrap2' => 'wrap',
        'wrap2.' => 'array',
        'dataWrap' => 'dataWrap',
        'dataWrap.' => 'array',
        'wrap3' => 'wrap',
        'wrap3.' => 'array',
        'orderedStdWrap' => 'stdWrap',
        'orderedStdWrap.' => 'array',
        'outerWrap' => 'wrap',
        'outerWrap.' => 'array',
        'insertData' => 'boolean',
        'insertData.' => 'array',
    ];

    /**
     * Returns number of rows selected by the query made by the properties set.
     * Implements the stdWrap "numRows" property
     *
     * @param array $conf TypoScript properties for the property (see link to "numRows")
     * @return int The number of rows found by the select
     * @internal
     * @see stdWrap()
     */
    public function numRows($conf)
    {
        $conf['select.']['selectFields'] = 'count(*)';
        $statement = $this->exec_getQuery($conf['table'], $conf['select.']);

        return (int)$statement->fetchColumn(0);
    }

    /**
     * Exploding a string by the $char value (if integer its an ASCII value) and returning index $listNum
     *
     * @param string $content String to explode
     * @param string $listNum Index-number. You can place the word "last" in it and it will be substituted with the pointer to the last value. You can use math operators like "+-/*" (passed to calc())
     * @param string $char Either a string used to explode the content string or an integer value which will then be changed into a character, eg. "10" for a linebreak char.
     * @return string
     */
    public function listNum($content, $listNum, $char)
    {
        $char = $char ?: ',';
        if (MathUtility::canBeInterpretedAsInteger($char)) {
            $char = chr($char);
        }
        $temp = explode($char, $content);
        $last = '' . (count($temp) - 1);
        // Take a random item if requested
        if ($listNum === 'rand') {
            $listNum = random_int(0, count($temp) - 1);
        }
        $index = $this->calc(str_ireplace('last', $last, $listNum));
        return $temp[$index];
    }

    /**
     * Compares values together based on the settings in the input TypoScript array and returns the comparison result.
     * Implements the "if" function in TYPO3 TypoScript
     *
     * @param array $conf TypoScript properties defining what to compare
     * @return bool
     * @see stdWrap()
     * @see _parseFunc()
     */
    public function checkIf($conf)
    {
        if (!is_array($conf)) {
            return true;
        }
        if (isset($conf['directReturn'])) {
            return (bool)$conf['directReturn'];
        }
        $flag = true;
        if (isset($conf['isNull.'])) {
            $isNull = $this->stdWrap('', $conf['isNull.']);
            if ($isNull !== null) {
                $flag = false;
            }
        }
        if (isset($conf['isTrue']) || isset($conf['isTrue.'])) {
            $isTrue = isset($conf['isTrue.']) ? trim($this->stdWrap($conf['isTrue'], $conf['isTrue.'])) : trim($conf['isTrue']);
            if (!$isTrue) {
                $flag = false;
            }
        }
        if (isset($conf['isFalse']) || isset($conf['isFalse.'])) {
            $isFalse = isset($conf['isFalse.']) ? trim($this->stdWrap($conf['isFalse'], $conf['isFalse.'])) : trim($conf['isFalse']);
            if ($isFalse) {
                $flag = false;
            }
        }
        if (isset($conf['isPositive']) || isset($conf['isPositive.'])) {
            $number = isset($conf['isPositive.']) ? $this->calc($this->stdWrap($conf['isPositive'], $conf['isPositive.'])) : $this->calc($conf['isPositive']);
            if ($number < 1) {
                $flag = false;
            }
        }
        if ($flag) {
            $value = isset($conf['value.'])
                ? trim($this->stdWrap($conf['value'] ?? '', $conf['value.']))
                : trim($conf['value'] ?? '');
            if (isset($conf['isGreaterThan']) || isset($conf['isGreaterThan.'])) {
                $number = isset($conf['isGreaterThan.']) ? trim($this->stdWrap($conf['isGreaterThan'], $conf['isGreaterThan.'])) : trim($conf['isGreaterThan']);
                if ($number <= $value) {
                    $flag = false;
                }
            }
            if (isset($conf['isLessThan']) || isset($conf['isLessThan.'])) {
                $number = isset($conf['isLessThan.']) ? trim($this->stdWrap($conf['isLessThan'], $conf['isLessThan.'])) : trim($conf['isLessThan']);
                if ($number >= $value) {
                    $flag = false;
                }
            }
            if (isset($conf['equals']) || isset($conf['equals.'])) {
                $number = isset($conf['equals.']) ? trim($this->stdWrap($conf['equals'], $conf['equals.'])) : trim($conf['equals']);
                if ($number != $value) {
                    $flag = false;
                }
            }
            if (isset($conf['isInList']) || isset($conf['isInList.'])) {
                $number = isset($conf['isInList.']) ? trim($this->stdWrap($conf['isInList'], $conf['isInList.'])) : trim($conf['isInList']);
                if (!GeneralUtility::inList($value, $number)) {
                    $flag = false;
                }
            }
            if (isset($conf['bitAnd']) || isset($conf['bitAnd.'])) {
                $number = isset($conf['bitAnd.']) ? trim($this->stdWrap($conf['bitAnd'], $conf['bitAnd.'])) : trim($conf['bitAnd']);
                if ((new BitSet($number))->get($value) === false) {
                    $flag = false;
                }
            }
        }
        if ($conf['negate'] ?? false) {
            $flag = !$flag;
        }
        return $flag;
    }



     /**
     * Wrapping input value in a regular "wrap" but parses the wrapping value first for "insertData" codes.
     *
     * @param string $content Input string being wrapped
     * @param string $wrap The wrap string, eg. "<strong></strong>" or more likely here '<a href="index.php?id={TSFE:id}"> | </a>' which will wrap the input string in a <a> tag linking to the current page.
     * @return string Output string wrapped in the wrapping value.
     * @see insertData()
     * @see stdWrap()
     */
    public function dataWrap($content, $wrap)
    {
        return $this->wrap($content, $this->insertData($wrap));
    }

    /**
     * Implements the "insertData" property of stdWrap meaning that if strings matching {...} is found in the input string they
     * will be substituted with the return value from getData (datatype) which is passed the content of the curly braces.
     * If the content inside the curly braces starts with a hash sign {#...} it is a field name that must be quoted by Doctrine
     * DBAL and is skipped here for later processing.
     *
     * Example: If input string is "This is the page title: {page:title}" then the part, '{page:title}', will be substituted with
     * the current pages title field value.
     *
     * @param string $str Input value
     * @return string Processed input value
     * @see getData()
     * @see stdWrap()
     * @see dataWrap()
     */
    public function insertData($str)
    {
        $inside = 0;
        $newVal = '';
        $pointer = 0;
        $totalLen = strlen($str);
        do {
            if (!$inside) {
                $len = strcspn(substr($str, $pointer), '{');
                $newVal .= substr($str, $pointer, $len);
                $inside = true;
                if (substr($str, $pointer + $len + 1, 1) === '#') {
                    $len2 = strcspn(substr($str, $pointer + $len), '}');
                    $newVal .= substr($str, $pointer + $len, $len2);
                    $len += $len2;
                    $inside = false;
                }
            } else {
                $len = strcspn(substr($str, $pointer), '}') + 1;
                $newVal .= $this->getData(substr($str, $pointer + 1, $len - 2), $this->data);
                $inside = false;
            }
            $pointer += $len;
        } while ($pointer < $totalLen);
        return $newVal;
    }

    /**
     * Implements the stdWrap property "substring" which is basically a TypoScript implementation of the PHP function, substr()
     *
     * @param string $content The string to perform the operation on
     * @param string $options The parameters to substring, given as a comma list of integers where the first and second number is passed as arg 1 and 2 to substr().
     * @return string The processed input value.
     * @internal
     * @see stdWrap()
     */
    public function substring($content, $options)
    {
        $options = GeneralUtility::intExplode(',', $options . ',');
        if ($options[1]) {
            return mb_substr($content, $options[0], $options[1], 'utf-8');
        }
        return mb_substr($content, $options[0], null, 'utf-8');
    }

    /**
     * Performs basic mathematical evaluation of the input string. Does NOT take parenthesis and operator precedence into account! (for that, see \TYPO3\CMS\Core\Utility\MathUtility::calculateWithPriorityToAdditionAndSubtraction())
     *
     * @param string $val The string to evaluate. Example: "3+4*10/5" will generate "35". Only integer numbers can be used.
     * @return int The result (might be a float if you did a division of the numbers).
     * @see \TYPO3\CMS\Core\Utility\MathUtility::calculateWithPriorityToAdditionAndSubtraction()
     */
    public function calc($val)
    {
        $parts = GeneralUtility::splitCalc($val, '+-*/');
        $value = 0;
        foreach ($parts as $part) {
            $theVal = $part[1];
            $sign = $part[0];
            if ((string)(int)$theVal === (string)$theVal) {
                $theVal = (int)$theVal;
            } else {
                $theVal = 0;
            }
            if ($sign === '-') {
                $value -= $theVal;
            }
            if ($sign === '+') {
                $value += $theVal;
            }
            if ($sign === '/') {
                if ((int)$theVal) {
                    $value /= (int)$theVal;
                }
            }
            if ($sign === '*') {
                $value *= $theVal;
            }
        }
        return $value;
    }

    /**
     * Implements the "split" property of stdWrap; Splits a string based on a token (given in TypoScript properties), sets the "current" value to each part and then renders a content object pointer to by a number.
     * In classic TypoScript (like 'content (default)'/'styles.content (default)') this is used to render tables, splitting rows and cells by tokens and putting them together again wrapped in <td> tags etc.
     * Implements the "optionSplit" processing of the TypoScript options for each splitted value to parse.
     *
     * @param string $value The string value to explode by $conf[token] and process each part
     * @param array $conf TypoScript properties for "split
     * @return string Compiled result
     * @internal
     * @see stdWrap()
     * @see \TYPO3\CMS\Frontend\ContentObject\Menu\AbstractMenuContentObject::processItemStates()
     */
    public function splitObj($value, $conf)
    {
        $conf['token'] = isset($conf['token.']) ? $this->stdWrap($conf['token'], $conf['token.']) : $conf['token'];
        if ($conf['token'] === '') {
            return $value;
        }
        $valArr = explode($conf['token'], $value);

        // return value directly by returnKey. No further processing
        if (!empty($valArr) && (MathUtility::canBeInterpretedAsInteger($conf['returnKey'] ?? null) || ($conf['returnKey.'] ?? false))
        ) {
            $key = isset($conf['returnKey.']) ? (int)$this->stdWrap($conf['returnKey'], $conf['returnKey.']) : (int)$conf['returnKey'];
            return $valArr[$key] ?? '';
        }

        // return the amount of elements. No further processing
        if (!empty($valArr) && ($conf['returnCount'] || $conf['returnCount.'])) {
            $returnCount = isset($conf['returnCount.']) ? (bool)$this->stdWrap($conf['returnCount'], $conf['returnCount.']) : (bool)$conf['returnCount'];
            return $returnCount ? count($valArr) : 0;
        }

        // calculate splitCount
        $splitCount = count($valArr);
        $max = isset($conf['max.']) ? (int)$this->stdWrap($conf['max'], $conf['max.']) : (int)$conf['max'];
        if ($max && $splitCount > $max) {
            $splitCount = $max;
        }
        $min = isset($conf['min.']) ? (int)$this->stdWrap($conf['min'], $conf['min.']) : (int)$conf['min'];
        if ($min && $splitCount < $min) {
            $splitCount = $min;
        }
        $wrap = isset($conf['wrap.']) ? (string)$this->stdWrap($conf['wrap'], $conf['wrap.']) : (string)$conf['wrap'];
        $cObjNumSplitConf = isset($conf['cObjNum.']) ? (string)$this->stdWrap($conf['cObjNum'], $conf['cObjNum.']) : (string)$conf['cObjNum'];
        $splitArr = [];
        if ($wrap !== '' || $cObjNumSplitConf !== '') {
            $splitArr['wrap'] = $wrap;
            $splitArr['cObjNum'] = $cObjNumSplitConf;
            $splitArr = GeneralUtility::makeInstance(TypoScriptService::class)
                ->explodeConfigurationForOptionSplit($splitArr, $splitCount);
        }
        $content = '';
        for ($a = 0; $a < $splitCount; $a++) {
            $this->getTypoScriptFrontendController()->register['SPLIT_COUNT'] = $a;
            $value = '' . $valArr[$a];
            $this->data[$this->currentValKey] = $value;
            if ($splitArr[$a]['cObjNum']) {
                $objName = (int)$splitArr[$a]['cObjNum'];
                $value = isset($conf[$objName . '.'])
                    ? $this->stdWrap($this->cObjGet($conf[$objName . '.'], $objName . '.'), $conf[$objName . '.'])
                    : $this->cObjGet($conf[$objName . '.'], $objName . '.');
            }
            $wrap = isset($splitArr[$a]['wrap.']) ? $this->stdWrap($splitArr[$a]['wrap'], $splitArr[$a]['wrap.']) : $splitArr[$a]['wrap'];
            if ($wrap) {
                $value = $this->wrap($value, $wrap);
            }
            $content .= $value;
        }
        return $content;
    }

    /**
     * Processes ordered replacements on content data.
     *
     * @param string $content The content to be processed
     * @param array $configuration The TypoScript configuration for stdWrap.replacement
     * @return string The processed content data
     */
    protected function replacement($content, array $configuration)
    {
        // Sorts actions in configuration by numeric index
        ksort($configuration, SORT_NUMERIC);
        foreach ($configuration as $index => $action) {
            // Checks whether we have a valid action and a numeric key ending with a dot ("10.")
            if (is_array($action) && substr($index, -1) === '.' && MathUtility::canBeInterpretedAsInteger(substr($index, 0, -1))) {
                $content = $this->replacementSingle($content, $action);
            }
        }
        return $content;
    }

    /**
     * Processes a single search/replace on content data.
     *
     * @param string $content The content to be processed
     * @param array $configuration The TypoScript of the search/replace action to be processed
     * @return string The processed content data
     */
    protected function replacementSingle($content, array $configuration)
    {
        if ((isset($configuration['search']) || isset($configuration['search.'])) && (isset($configuration['replace']) || isset($configuration['replace.']))) {
            // Gets the strings
            $search = isset($configuration['search.']) ? $this->stdWrap($configuration['search'], $configuration['search.']) : $configuration['search'];
            $replace = isset($configuration['replace.'])
                ? $this->stdWrap($configuration['replace'] ?? null, $configuration['replace.'])
                : $configuration['replace'] ?? null;
            $useRegularExpression = false;
            // Determines whether regular expression shall be used
            if (isset($configuration['useRegExp'])
                || (isset($configuration['useRegExp.']) && $configuration['useRegExp.'])
            ) {
                $useRegularExpression = isset($configuration['useRegExp.']) ? (bool)$this->stdWrap($configuration['useRegExp'], $configuration['useRegExp.']) : (bool)$configuration['useRegExp'];
            }
            $useOptionSplitReplace = false;
            // Determines whether replace-pattern uses option-split
            if (isset($configuration['useOptionSplitReplace']) || isset($configuration['useOptionSplitReplace.'])) {
                $useOptionSplitReplace = isset($configuration['useOptionSplitReplace.']) ? (bool)$this->stdWrap($configuration['useOptionSplitReplace'], $configuration['useOptionSplitReplace.']) : (bool)$configuration['useOptionSplitReplace'];
            }

            // Performs a replacement by preg_replace()
            if ($useRegularExpression) {
                // Get separator-character which precedes the string and separates search-string from the modifiers
                $separator = $search[0];
                $startModifiers = strrpos($search, $separator);
                if ($separator !== false && $startModifiers > 0) {
                    $modifiers = substr($search, $startModifiers + 1);
                    // remove "e" (eval-modifier), which would otherwise allow to run arbitrary PHP-code
                    $modifiers = str_replace('e', '', $modifiers);
                    $search = substr($search, 0, $startModifiers + 1) . $modifiers;
                }
                if ($useOptionSplitReplace) {
                    // init for replacement
                    $splitCount = preg_match_all($search, $content, $matches);
                    $typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);
                    $replaceArray = $typoScriptService->explodeConfigurationForOptionSplit([$replace], $splitCount);
                    $replaceCount = 0;

                    $replaceCallback = function ($match) use ($replaceArray, $search, &$replaceCount) {
                        $replaceCount++;
                        return preg_replace($search, $replaceArray[$replaceCount - 1][0], $match[0]);
                    };
                    $content = preg_replace_callback($search, $replaceCallback, $content);
                } else {
                    $content = preg_replace($search, $replace, $content);
                }
            } elseif ($useOptionSplitReplace) {
                // turn search-string into a preg-pattern
                $searchPreg = '#' . preg_quote($search, '#') . '#';

                // init for replacement
                $splitCount = preg_match_all($searchPreg, $content, $matches);
                $typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);
                $replaceArray = $typoScriptService->explodeConfigurationForOptionSplit([$replace], $splitCount);
                $replaceCount = 0;

                $replaceCallback = function () use ($replaceArray, $search, &$replaceCount) {
                    $replaceCount++;
                    return $replaceArray[$replaceCount - 1][0];
                };
                $content = preg_replace_callback($searchPreg, $replaceCallback, $content);
            } else {
                $content = str_replace($search, $replace, $content);
            }
        }
        return $content;
    }

    /**
     * Implements the stdWrap property "numberFormat"
     * This is a Wrapper function for php's number_format()
     *
     * @param float $content Value to process
     * @param array $conf TypoScript Configuration for numberFormat
     * @return string The formatted number
     */
    public function numberFormat($content, $conf)
    {
        $decimals = isset($conf['decimals.'])
            ? (int)$this->stdWrap($conf['decimals'] ?? '', $conf['decimals.'])
            : (int)($conf['decimals'] ?? 0);
        $dec_point = isset($conf['dec_point.'])
            ? $this->stdWrap($conf['dec_point'] ?? '', $conf['dec_point.'])
            : ($conf['dec_point'] ?? null);
        $thousands_sep = isset($conf['thousands_sep.'])
            ? $this->stdWrap($conf['thousands_sep'] ?? '', $conf['thousands_sep.'])
            : ($conf['thousands_sep'] ?? null);
        return number_format((float)$content, $decimals, $dec_point, $thousands_sep);
    }

    /***********************************************
     *
     * Data retrieval etc.
     *
     ***********************************************/
    /**
     * Returns the value for the field from $this->data. If "//" is found in the $field value that token will split the field values apart and the first field having a non-blank value will be returned.
     *
     * @param string $field The fieldname, eg. "title" or "navtitle // title" (in the latter case the value of $this->data[navtitle] is returned if not blank, otherwise $this->data[title] will be)
     * @return string|null
     */
    public function getFieldVal($field)
    {
        if (strpos($field, '//') === false) {
            return $this->data[trim($field)] ?? null;
        }
        $sections = GeneralUtility::trimExplode('//', $field, true);
        foreach ($sections as $k) {
            if ((string)$this->data[$k] !== '') {
                return $this->data[$k];
            }
        }

        return '';
    }

    /**
     * Implements the TypoScript data type "getText". This takes a string with parameters and based on those a value from somewhere in the system is returned.
     *
     * @param string $string The parameter string, eg. "field : title" or "field : navtitle // field : title" (in the latter case and example of how the value is FIRST splitted by "//" is shown)
     * @param array|null $fieldArray Alternative field array; If you set this to an array this variable will be used to look up values for the "field" key. Otherwise the current page record in $GLOBALS['TSFE']->page is used.
     * @return string The value fetched
     * @see getFieldVal()
     */
    public function getData($string, $fieldArray = null)
    {
        $tsfe = $this->getTypoScriptFrontendController();
        if (!is_array($fieldArray)) {
            $fieldArray = $tsfe->page;
        }
        $retVal = '';
        $sections = explode('//', $string);
        foreach ($sections as $secKey => $secVal) {
            if ($retVal) {
                break;
            }
            $parts = explode(':', $secVal, 2);
            $type = strtolower(trim($parts[0]));
            $typesWithOutParameters = ['level', 'date', 'current', 'pagelayout'];
            $key = trim($parts[1] ?? '');
            if (($key != '') || in_array($type, $typesWithOutParameters)) {
                switch ($type) {
                    case 'gp':
                        // Merge GET and POST and get $key out of the merged array
                        $getPostArray = GeneralUtility::_GET();
                        ArrayUtility::mergeRecursiveWithOverrule($getPostArray, GeneralUtility::_POST());
                        $retVal = $this->getGlobal($key, $getPostArray);
                        break;
                    case 'tsfe':
                        $retVal = $this->getGlobal('TSFE|' . $key);
                        break;
                    case 'getenv':
                        $retVal = getenv($key);
                        break;
                    case 'getindpenv':
                        $retVal = $this->getEnvironmentVariable($key);
                        break;
                    case 'field':
                        $retVal = $this->getGlobal($key, $fieldArray);
                        break;
                    case 'file':
                        $retVal = $this->getFileDataKey($key);
                        break;
                    case 'parameters':
                        $retVal = $this->parameters[$key];
                        break;
                    case 'register':
                        $retVal = $tsfe->register[$key] ?? null;
                        break;
                    case 'global':
                        $retVal = $this->getGlobal($key);
                        break;
                    case 'level':
                        $retVal = count($tsfe->tmpl->rootLine) - 1;
                        break;
                    case 'leveltitle':
                        $keyParts = GeneralUtility::trimExplode(',', $key);
                        $numericKey = $this->getKey($keyParts[0], $tsfe->tmpl->rootLine);
                        $retVal = $this->rootLineValue($numericKey, 'title', strtolower($keyParts[1] ?? '') === 'slide');
                        break;
                    case 'levelmedia':
                        $keyParts = GeneralUtility::trimExplode(',', $key);
                        $numericKey = $this->getKey($keyParts[0], $tsfe->tmpl->rootLine);
                        $retVal = $this->rootLineValue($numericKey, 'media', strtolower($keyParts[1] ?? '') === 'slide');
                        break;
                    case 'leveluid':
                        $numericKey = $this->getKey($key, $tsfe->tmpl->rootLine);
                        $retVal = $this->rootLineValue($numericKey, 'uid');
                        break;
                    case 'levelfield':
                        $keyParts = GeneralUtility::trimExplode(',', $key);
                        $numericKey = $this->getKey($keyParts[0], $tsfe->tmpl->rootLine);
                        $retVal = $this->rootLineValue($numericKey, $keyParts[1], strtolower($keyParts[2] ?? '') === 'slide');
                        break;
                    case 'fullrootline':
                        $keyParts = GeneralUtility::trimExplode(',', $key);
                        $fullKey = (int)$keyParts[0] - count($tsfe->tmpl->rootLine) + count($tsfe->rootLine);
                        if ($fullKey >= 0) {
                            $retVal = $this->rootLineValue($fullKey, $keyParts[1], stristr($keyParts[2] ?? '', 'slide'), $tsfe->rootLine);
                        }
                        break;
                    case 'date':
                        if (!$key) {
                            $key = 'd/m Y';
                        }
                        $retVal = date($key, $GLOBALS['EXEC_TIME']);
                        break;
                    case 'page':
                        $retVal = $tsfe->page[$key];
                        break;
                    case 'pagelayout':
                        $retVal = GeneralUtility::makeInstance(PageLayoutResolver::class)
                            ->getLayoutForPage($tsfe->page, $tsfe->rootLine);
                        break;
                    case 'current':
                        $retVal = $this->data[$this->currentValKey] ?? null;
                        break;
                    case 'db':
                        $selectParts = GeneralUtility::trimExplode(':', $key);
                        $db_rec = $tsfe->sys_page->getRawRecord($selectParts[0], $selectParts[1]);
                        if (is_array($db_rec) && $selectParts[2]) {
                            $retVal = $db_rec[$selectParts[2]];
                        }
                        break;
                    case 'lll':
                        $retVal = $tsfe->sL('LLL:' . $key);
                        break;
                    case 'path':
                        try {
                            $retVal = GeneralUtility::makeInstance(FilePathSanitizer::class)->sanitize($key);
                        } catch (\TYPO3\CMS\Core\Resource\Exception $e) {
                            // do nothing in case the file path is invalid
                            $retVal = null;
                        }
                        break;
                    case 'cobj':
                        switch ($key) {
                            case 'parentRecordNumber':
                                $retVal = $this->parentRecordNumber;
                                break;
                        }
                        break;
                    case 'debug':
                        switch ($key) {
                            case 'rootLine':
                                $retVal = DebugUtility::viewArray($tsfe->tmpl->rootLine);
                                break;
                            case 'fullRootLine':
                                $retVal = DebugUtility::viewArray($tsfe->rootLine);
                                break;
                            case 'data':
                                $retVal = DebugUtility::viewArray($this->data);
                                break;
                            case 'register':
                                $retVal = DebugUtility::viewArray($tsfe->register);
                                break;
                            case 'page':
                                $retVal = DebugUtility::viewArray($tsfe->page);
                                break;
                        }
                        break;
                    case 'flexform':
                        $keyParts = GeneralUtility::trimExplode(':', $key, true);
                        if (count($keyParts) === 2 && isset($this->data[$keyParts[0]])) {
                            $flexFormContent = $this->data[$keyParts[0]];
                            if (!empty($flexFormContent)) {
                                $flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
                                $flexFormKey = str_replace('.', '|', $keyParts[1]);
                                $settings = $flexFormService->convertFlexFormContentToArray($flexFormContent);
                                $retVal = $this->getGlobal($flexFormKey, $settings);
                            }
                        }
                        break;
                    case 'session':
                        $keyParts = GeneralUtility::trimExplode('|', $key, true);
                        $sessionKey = array_shift($keyParts);
                        $retVal = $this->getTypoScriptFrontendController()->fe_user->getSessionData($sessionKey);
                        foreach ($keyParts as $keyPart) {
                            if (is_object($retVal)) {
                                $retVal = $retVal->{$keyPart};
                            } elseif (is_array($retVal)) {
                                $retVal = $retVal[$keyPart];
                            } else {
                                $retVal = '';
                                break;
                            }
                        }
                        if (!is_scalar($retVal)) {
                            $retVal = '';
                        }
                        break;
                    case 'context':
                        $context = GeneralUtility::makeInstance(Context::class);
                        [$aspectName, $propertyName] = GeneralUtility::trimExplode(':', $key, true, 2);
                        $retVal = $context->getPropertyFromAspect($aspectName, $propertyName, '');
                        if (is_array($retVal)) {
                            $retVal = implode(',', $retVal);
                        }
                        if (!is_scalar($retVal)) {
                            $retVal = '';
                        }
                        break;
                    case 'site':
                        $site = $this->getTypoScriptFrontendController()->getSite();
                        if ($key === 'identifier') {
                            $retVal = $site->getIdentifier();
                        } elseif ($key === 'base') {
                            $retVal = $site->getBase();
                        } else {
                            try {
                                $retVal = ArrayUtility::getValueByPath($site->getConfiguration(), $key, '.');
                            } catch (MissingArrayPathException $exception) {
                                $this->logger->warning(sprintf('getData() with "%s" failed', $key), ['exception' => $exception]);
                            }
                        }
                        break;
                    case 'sitelanguage':
                        $siteLanguage = $this->getTypoScriptFrontendController()->getLanguage();
                        $config = $siteLanguage->toArray();
                        if (isset($config[$key])) {
                            $retVal = $config[$key];
                        }
                        break;
                }
            }

            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['getData'] ?? [] as $className) {
                $hookObject = GeneralUtility::makeInstance($className);
                if (!$hookObject instanceof ContentObjectGetDataHookInterface) {
                    throw new \UnexpectedValueException('$hookObject must implement interface ' . ContentObjectGetDataHookInterface::class, 1195044480);
                }
                $ref = $this; // introduced for phpstan to not lose type information when passing $this into callUserFunction
                $retVal = $hookObject->getDataExtension($string, $fieldArray, $secVal, $retVal, $ref);
            }
        }
        return $retVal;
    }

    /**
     * Processing of key values pointing to entries in $arr; Here negative values are converted to positive keys pointer to an entry in the array but from behind (based on the negative value).
     * Example: entrylevel = -1 means that entryLevel ends up pointing at the outermost-level, -2 means the level before the outermost...
     *
     * @param int $key The integer to transform
     * @param array $arr array in which the key should be found.
     * @return int The processed integer key value.
     * @internal
     * @see getData()
     */
    public function getKey($key, $arr)
    {
        $key = (int)$key;
        if (is_array($arr)) {
            if ($key < 0) {
                $key = count($arr) + $key;
            }
            if ($key < 0) {
                $key = 0;
            }
        }
        return $key;
    }

    /***********************************************
     *
     * Miscellaneous functions, stand alone
     *
     ***********************************************/
    /**
     * Wrapping a string.
     * Implements the TypoScript "wrap" property.
     * Example: $content = "HELLO WORLD" and $wrap = "<strong> | </strong>", result: "<strong>HELLO WORLD</strong>"
     *
     * @param string $content The content to wrap
     * @param string $wrap The wrap value, eg. "<strong> | </strong>
     * @param string $char The char used to split the wrapping value, default is "|
     * @return string Wrapped input string
     * @see noTrimWrap()
     */
    public function wrap($content, $wrap, $char = '|')
    {
        if ($wrap) {
            $wrapArr = explode($char, $wrap);
            $content = trim($wrapArr[0] ?? '') . $content . trim($wrapArr[1] ?? '');
        }
        return $content;
    }

    /**
     * Wrapping a string, preserving whitespace in wrap value.
     * Notice that the wrap value uses part 1/2 to wrap (and not 0/1 which wrap() does)
     *
     * @param string $content The content to wrap, eg. "HELLO WORLD
     * @param string $wrap The wrap value, eg. " | <strong> | </strong>
     * @param string $char The char used to split the wrapping value, default is "|"
     * @return string Wrapped input string, eg. " <strong> HELLO WORD </strong>
     * @see wrap()
     */
    public function noTrimWrap($content, $wrap, $char = '|')
    {
        if ($wrap) {
            // expects to be wrapped with (at least) 3 characters (before, middle, after)
            // anything else is not taken into account
            $wrapArr = explode($char, $wrap, 4);
            $content = $wrapArr[1] . $content . $wrapArr[2];
        }
        return $content;
    }

    /**
     * Changing character case of a string, converting typically used western charset characters as well.
     *
     * @param string $theValue The string to change case for.
     * @param string $case The direction; either "upper" or "lower
     * @return string
     * @see HTMLcaseshift()
     */
    public function caseshift($theValue, $case)
    {
        switch (strtolower($case)) {
            case 'upper':
                $theValue = mb_strtoupper($theValue, 'utf-8');
                break;
            case 'lower':
                $theValue = mb_strtolower($theValue, 'utf-8');
                break;
            case 'capitalize':
                $theValue = mb_convert_case($theValue, MB_CASE_TITLE, 'utf-8');
                break;
            case 'ucfirst':
                $firstChar = mb_substr($theValue, 0, 1, 'utf-8');
                $firstChar = mb_strtoupper($firstChar, 'utf-8');
                $remainder = mb_substr($theValue, 1, null, 'utf-8');
                $theValue = $firstChar . $remainder;
                break;
            case 'lcfirst':
                $firstChar = mb_substr($theValue, 0, 1, 'utf-8');
                $firstChar = mb_strtolower($firstChar, 'utf-8');
                $remainder = mb_substr($theValue, 1, null, 'utf-8');
                $theValue = $firstChar . $remainder;
                break;
            case 'uppercamelcase':
                $theValue = GeneralUtility::underscoredToUpperCamelCase($theValue);
                break;
            case 'lowercamelcase':
                $theValue = GeneralUtility::underscoredToLowerCamelCase($theValue);
                break;
        }
        return $theValue;
    }

    /**
     * Shifts the case of characters outside of HTML tags in the input string
     *
     * @param string $theValue The string to change case for.
     * @param string $case The direction; either "upper" or "lower
     * @return string
     * @see caseshift()
     */
    public function HTMLcaseshift($theValue, $case)
    {
        $inside = 0;
        $newVal = '';
        $pointer = 0;
        $totalLen = strlen($theValue);
        do {
            if (!$inside) {
                $len = strcspn(substr($theValue, $pointer), '<');
                $newVal .= $this->caseshift(substr($theValue, $pointer, $len), $case);
                $inside = 1;
            } else {
                $len = strcspn(substr($theValue, $pointer), '>') + 1;
                $newVal .= substr($theValue, $pointer, $len);
                $inside = 0;
            }
            $pointer += $len;
        } while ($pointer < $totalLen);
        return $newVal;
    }



    /**
     * Implements the stdWrap property "crop" which is a modified "substr" function allowing to limit a string length to a certain number of chars (from either start or end of string) and having a pre/postfix applied if the string really was cropped.
     *
     * @param string $content The string to perform the operation on
     * @param string $options The parameters splitted by "|": First parameter is the max number of chars of the string. Negative value means cropping from end of string. Second parameter is the pre/postfix string to apply if cropping occurs. Third parameter is a boolean value. If set then crop will be applied at nearest space.
     * @return string The processed input value.
     * @internal
     * @see stdWrap()
     */
    public function crop($content, $options)
    {
        $options = explode('|', $options);
        $chars = (int)$options[0];
        $afterstring = trim($options[1] ?? '');
        $crop2space = trim($options[2] ?? '');
        if ($chars) {
            if (mb_strlen($content, 'utf-8') > abs($chars)) {
                $truncatePosition = false;
                if ($chars < 0) {
                    $content = mb_substr($content, $chars, null, 'utf-8');
                    if ($crop2space) {
                        $truncatePosition = strpos($content, ' ');
                    }
                    $content = $truncatePosition ? $afterstring . substr($content, $truncatePosition) : $afterstring . $content;
                } else {
                    $content = mb_substr($content, 0, $chars, 'utf-8');
                    if ($crop2space) {
                        $truncatePosition = strrpos($content, ' ');
                    }
                    $content = $truncatePosition ? substr($content, 0, $truncatePosition) . $afterstring : $content . $afterstring;
                }
            }
        }
        return $content;
    }

    /**
     * Loaded with the current data-record.
     *
     * If the instance of this class is used to render records from the database those records are found in this array.
     * The function stdWrap has TypoScript properties that fetch field-data from this array.
     *
     * @var array
     * @see start()
     */
    public $data = [];

    /**
     * @var string
     */
    protected $table = '';

    /**
     * Used for backup
     *
     * @var array
     */
    public $oldData = [];

    /**
     * If this is set with an array before stdWrap, it's used instead of $this->data in the data-property in stdWrap
     *
     * @var string
     */
    public $alternativeData = '';

    /**
     * Used by the parseFunc function and is loaded with tag-parameters when parsing tags.
     *
     * @var array
     */
    public $parameters = [];

    /**
     * @var string
     */
    public $currentValKey = 'currentValue_kidjls9dksoje';

    /**
     * This is set to the [table]:[uid] of the record delivered in the $data-array, if the cObjects CONTENT or RECORD is in operation.
     * Note that $GLOBALS['TSFE']->currentRecord is set to an equal value but always indicating the latest record rendered.
     *
     * @var string
     */
    public $currentRecord = '';

    /**
     * Set in RecordsContentObject and ContentContentObject to the current number of records selected in a query.
     *
     * @var int
     */
    public $currentRecordTotal = 0;

    /**
     * Incremented in RecordsContentObject and ContentContentObject before each record rendering.
     *
     * @var int
     */
    public $currentRecordNumber = 0;

    /**
     * Incremented in RecordsContentObject and ContentContentObject before each record rendering.
     *
     * @var int
     */
    public $parentRecordNumber = 0;

    /**
     * If the ContentObjectRender was started from ContentContentObject, RecordsContentObject or SearchResultContentObject this array has two keys, 'data' and 'currentRecord' which indicates the record and data for the parent cObj.
     *
     * @var array
     */
    public $parentRecord = [];

    /**
     * array that registers rendered content elements (or any table) to make sure they are not rendered recursively!
     *
     * @var array
     */
    public $recordRegister = [];

    /**
     * Set to TRUE by doConvertToUserIntObject() if USER object wants to become USER_INT
     */
    public $doConvertToUserIntObject = false;

    /**
     * Indicates current object type. Can hold one of OBJECTTYPE_ constants or FALSE.
     * The value is set and reset inside USER() function. Any time outside of
     * USER() it is FALSE.
     */
    protected $userObjectType = false;

    /**
     * @var array
     */
    protected $stopRendering = [];

    /**
     * @var int
     */
    protected $stdWrapRecursionLevel = 0;

    /**
     * Indicates that object type is USER.
     *
     * @see ContentObjectRender::$userObjectType
     */
    const OBJECTTYPE_USER_INT = 1;
    /**
     * Indicates that object type is USER.
     *
     * @see ContentObjectRender::$userObjectType
     */
    const OBJECTTYPE_USER = 2;

    /***********************************************
     *
     * "stdWrap" + sub functions
     *
     ***********************************************/
    /**
     * The "stdWrap" function. This is the implementation of what is known as "stdWrap properties" in TypoScript.
     * Basically "stdWrap" performs some processing of a value based on properties in the input $conf array(holding the TypoScript "stdWrap properties")
     * See the link below for a complete list of properties and what they do. The order of the table with properties found in TSref (the link) follows the actual order of implementation in this function.
     *
     * If $this->alternativeData is an array it's used instead of the $this->data array in ->getData
     *
     * @param string $content Input value undergoing processing in this function. Possibly substituted by other values fetched from another source.
     * @param array $conf TypoScript "stdWrap properties".
     * @return string The processed input value
     */
    public function stdWrap($content = '', $conf = [])
    {
        $content = (string)$content;
        // If there is any hook object, activate all of the process and override functions.
        // The hook interface ContentObjectStdWrapHookInterface takes care that all 4 methods exist.
        /*
        if ($this->stdWrapHookObjects) {
            $conf['stdWrapPreProcess'] = 1;
            $conf['stdWrapOverride'] = 1;
            $conf['stdWrapProcess'] = 1;
            $conf['stdWrapPostProcess'] = 1;
        }
        */

        if (!is_array($conf) || !$conf) {
            return $content;
        }

        // Cache handling
        /*
        if (isset($conf['cache.']) && is_array($conf['cache.'])) {
            $conf['cache.']['key'] = $this->stdWrap($conf['cache.']['key'], $conf['cache.']['key.']);
            $conf['cache.']['tags'] = $this->stdWrap($conf['cache.']['tags'], $conf['cache.']['tags.']);
            $conf['cache.']['lifetime'] = $this->stdWrap($conf['cache.']['lifetime'], $conf['cache.']['lifetime.']);
            $conf['cacheRead'] = 1;
            $conf['cacheStore'] = 1;
        }
        */

        // The configuration is sorted and filtered by intersection with the defined stdWrapOrder.
        $sortedConf = array_keys(array_intersect_key($this->stdWrapOrder, $conf));
        // Functions types that should not make use of nested stdWrap function calls to avoid conflicts with internal TypoScript used by these functions
        $stdWrapDisabledFunctionTypes = 'cObject,functionName,stdWrap';
        // Additional Array to check whether a function has already been executed
        $isExecuted = [];
        // Additional switch to make sure 'required', 'if' and 'fieldRequired'
        // will still stop rendering immediately in case they return FALSE
        $this->stdWrapRecursionLevel++;
        $this->stopRendering[$this->stdWrapRecursionLevel] = false;
        // execute each function in the predefined order
        foreach ($sortedConf as $stdWrapName) {
            // eliminate the second key of a pair 'key'|'key.' to make sure functions get called only once and check if rendering has been stopped
            if ((!isset($isExecuted[$stdWrapName]) || !$isExecuted[$stdWrapName]) && !$this->stopRendering[$this->stdWrapRecursionLevel]) {
                $functionName = rtrim($stdWrapName, '.');
                $functionProperties = $functionName . '.';
                $functionType = $this->stdWrapOrder[$functionName] ?? null;
                // If there is any code on the next level, check if it contains "official" stdWrap functions
                // if yes, execute them first - will make each function stdWrap aware
                // so additional stdWrap calls within the functions can be removed, since the result will be the same
                if (!empty($conf[$functionProperties]) && !GeneralUtility::inList($stdWrapDisabledFunctionTypes, $functionType)) {
                    if (array_intersect_key($this->stdWrapOrder, $conf[$functionProperties])) {
                        // Check if there's already content available before processing
                        // any ifEmpty or ifBlank stdWrap properties
                        if (($functionName === 'ifEmpty' && !empty($content)) ||
                            ($functionName === 'ifBlank' && $content !== '')) {
                            continue;
                        }

                        $conf[$functionName] = $this->stdWrap($conf[$functionName] ?? '', $conf[$functionProperties] ?? []);
                    }
                }
                // Check if key is still containing something, since it might have been changed by next level stdWrap before
                if ((isset($conf[$functionName]) || $conf[$functionProperties])
                    && ($functionType !== 'boolean' || $conf[$functionName])
                ) {
                    // Get just that part of $conf that is needed for the particular function
                    $singleConf = [
                        $functionName => $conf[$functionName] ?? null,
                        $functionProperties => $conf[$functionProperties] ?? null
                    ];
                    // Hand over the whole $conf array to the stdWrapHookObjects
                    if ($functionType === 'hook') {
                        $singleConf = $conf;
                    }
                    // Add both keys - with and without the dot - to the set of executed functions
                    $isExecuted[$functionName] = true;
                    $isExecuted[$functionProperties] = true;
                    // Call the function with the prefix stdWrap_ to make sure nobody can execute functions just by adding their name to the TS Array
                    $functionName = 'stdWrap_' . $functionName;
                    $content = $this->{$functionName}($content, $singleConf);
                } elseif ($functionType === 'boolean' && !$conf[$functionName]) {
                    $isExecuted[$functionName] = true;
                    $isExecuted[$functionProperties] = true;
                }
            }
        }
        unset($this->stopRendering[$this->stdWrapRecursionLevel]);
        $this->stdWrapRecursionLevel--;

        return $content;
    }

    /**
     * Gets a configuration value by passing them through stdWrap first and taking a default value if stdWrap doesn't yield a result.
     *
     * @param string $key The config variable key (from TS array).
     * @param array $config The TypoScript array.
     * @param string $defaultValue Optional default value.
     * @return string Value of the config variable
     */
    public function stdWrapValue($key, array $config, $defaultValue = '')
    {
        if (isset($config[$key])) {
            if (!isset($config[$key . '.'])) {
                return $config[$key];
            }
        } elseif (isset($config[$key . '.'])) {
            $config[$key] = '';
        } else {
            return $defaultValue;
        }
        $stdWrapped = $this->stdWrap($config[$key], $config[$key . '.']);
        return $stdWrapped ?: $defaultValue;
    }

    /**
     * setContentToCurrent
     * actually it just does the contrary: Sets the value of 'current' based on current content
     *
     * @param string $content Input value undergoing processing in this function.
     * @return string The processed input value
     */
    public function stdWrap_setContentToCurrent($content = '')
    {
        $this->data[$this->currentValKey] = $content;
        return $content;
    }

    /**
     * setCurrent
     * Sets the value of 'current' based on the outcome of stdWrap operations
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for setCurrent.
     * @return string The processed input value
     */
    public function stdWrap_setCurrent($content = '', $conf = [])
    {
        $this->data[$this->currentValKey] = $conf['setCurrent'] ?? null;
        return $content;
    }

    /**
     * data
     * Gets content from different sources based on getText functions, makes use of alternativeData, when set
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for data.
     * @return string The processed input value
     */
    public function stdWrap_data($content = '', $conf = [])
    {
        $content = $this->getData($conf['data'], is_array($this->alternativeData) ? $this->alternativeData : $this->data);
        // This must be unset directly after
        $this->alternativeData = '';
        return $content;
    }

    /**
     * field
     * Gets content from a DB field
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for field.
     * @return string The processed input value
     */
    public function stdWrap_field($content = '', $conf = [])
    {
        return $this->getFieldVal($conf['field']);
    }

    /**
     * current
     * Gets content that has been previously set as 'current'
     * Can be set via setContentToCurrent or setCurrent or will be set automatically i.e. inside the split function
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for current.
     * @return string The processed input value
     */
    public function stdWrap_current($content = '', $conf = [])
    {
        return $this->data[$this->currentValKey];
    }

    /**
     * numRows
     * Counts the number of returned records of a DB operation
     * makes use of select internally
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for numRows.
     * @return string The processed input value
     */
    public function stdWrap_numRows($content = '', $conf = [])
    {
        return $this->numRows($conf['numRows.']);
    }

    /**
     * override
     * Will override the current value of content with its own value'
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for override.
     * @return string The processed input value
     */
    public function stdWrap_override($content = '', $conf = [])
    {
        if (trim($conf['override'] ?? false)) {
            $content = $conf['override'];
        }
        return $content;
    }

    /**
     * preIfEmptyListNum
     * Gets a value off a CSV list before the following ifEmpty check
     * Makes sure that the result of ifEmpty will be TRUE in case the CSV does not contain a value at the position given by preIfEmptyListNum
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for preIfEmptyListNum.
     * @return string The processed input value
     */
    public function stdWrap_preIfEmptyListNum($content = '', $conf = [])
    {
        return $this->listNum($content, $conf['preIfEmptyListNum'] ?? null, $conf['preIfEmptyListNum.']['splitChar'] ?? null);
    }

    /**
     * ifNull
     * Will set content to a replacement value in case the value of content is NULL
     *
     * @param string|null $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for ifNull.
     * @return string The processed input value
     */
    public function stdWrap_ifNull($content = '', $conf = [])
    {
        return $content ?? $conf['ifNull'];
    }

    /**
     * ifEmpty
     * Will set content to a replacement value in case the trimmed value of content returns FALSE
     * 0 (zero) will be replaced as well
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for ifEmpty.
     * @return string The processed input value
     */
    public function stdWrap_ifEmpty($content = '', $conf = [])
    {
        if (!trim($content)) {
            $content = $conf['ifEmpty'];
        }
        return $content;
    }

    /**
     * ifBlank
     * Will set content to a replacement value in case the trimmed value of content has no length
     * 0 (zero) will not be replaced
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for ifBlank.
     * @return string The processed input value
     */
    public function stdWrap_ifBlank($content = '', $conf = [])
    {
        if (trim($content) === '') {
            $content = $conf['ifBlank'];
        }
        return $content;
    }

    /**
     * listNum
     * Gets a value off a CSV list after ifEmpty check
     * Might return an empty value in case the CSV does not contain a value at the position given by listNum
     * Use preIfEmptyListNum to avoid that behaviour
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for listNum.
     * @return string The processed input value
     */
    public function stdWrap_listNum($content = '', $conf = [])
    {
        return $this->listNum($content, $conf['listNum'] ?? null, $conf['listNum.']['splitChar'] ?? null);
    }

    /**
     * trim
     * Cuts off any whitespace at the beginning and the end of the content
     *
     * @param string $content Input value undergoing processing in this function.
     * @return string The processed input value
     */
    public function stdWrap_trim($content = '')
    {
        return trim($content);
    }

    /**
     * strPad
     * Will return a string padded left/right/on both sides, based on configuration given as stdWrap properties
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for strPad.
     * @return string The processed input value
     */
    public function stdWrap_strPad($content = '', $conf = [])
    {
        // Must specify a length in conf for this to make sense
        $length = 0;
        // Padding with space is PHP-default
        $padWith = ' ';
        // Padding on the right side is PHP-default
        $padType = STR_PAD_RIGHT;
        if (!empty($conf['strPad.']['length'])) {
            $length = isset($conf['strPad.']['length.']) ? $this->stdWrap($conf['strPad.']['length'], $conf['strPad.']['length.']) : $conf['strPad.']['length'];
            $length = (int)$length;
        }
        if (isset($conf['strPad.']['padWith']) && (string)$conf['strPad.']['padWith'] !== '') {
            $padWith = isset($conf['strPad.']['padWith.']) ? $this->stdWrap($conf['strPad.']['padWith'], $conf['strPad.']['padWith.']) : $conf['strPad.']['padWith'];
        }
        if (!empty($conf['strPad.']['type'])) {
            $type = isset($conf['strPad.']['type.']) ? $this->stdWrap($conf['strPad.']['type'], $conf['strPad.']['type.']) : $conf['strPad.']['type'];
            if (strtolower($type) === 'left') {
                $padType = STR_PAD_LEFT;
            } elseif (strtolower($type) === 'both') {
                $padType = STR_PAD_BOTH;
            }
        }
        return str_pad($content, $length, $padWith, $padType);
    }

    /**
     * stdWrap
     * A recursive call of the stdWrap function set
     * This enables the user to execute stdWrap functions in another than the predefined order
     * It modifies the content, not the property
     * while the new feature of chained stdWrap functions modifies the property and not the content
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for stdWrap.
     * @return string The processed input value
     */
    public function stdWrap_stdWrap($content = '', $conf = [])
    {
        return $this->stdWrap($content, $conf['stdWrap.']);
    }

    /**
     * if
     * Will immediately stop rendering and return an empty value
     * when the result of the checks returns FALSE
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for if.
     * @return string The processed input value
     */
    public function stdWrap_if($content = '', $conf = [])
    {
        if (empty($conf['if.']) || $this->checkIf($conf['if.'])) {
            return $content;
        }
        $this->stopRendering[$this->stdWrapRecursionLevel] = true;
        return '';
    }

    /**
     * stdWrap csConv: Converts the input to UTF-8
     *
     * The character set of the input must be specified. Returns the input if
     * matters go wrong, for example if an invalid character set is given.
     *
     * @param string $content The string to convert.
     * @param array $conf stdWrap properties for csConv.
     * @return string The processed input.
     */
    public function stdWrap_csConv($content = '', $conf = [])
    {
        if (!empty($conf['csConv'])) {
            $output = mb_convert_encoding($content, 'utf-8', trim(strtolower($conf['csConv'])));
            return $output !== false && $output !== '' ? $output : $content;
        }
        return $content;
    }

    /**
     * split
     * Will split the content by a given token and treat the results separately
     * Automatically fills 'current' with a single result
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for split.
     * @return string The processed input value
     */
    public function stdWrap_split($content = '', $conf = [])
    {
        return $this->splitObj($content, $conf['split.']);
    }

    /**
     * replacement
     * Will execute replacements on the content (optionally with preg-regex)
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for replacement.
     * @return string The processed input value
     */
    public function stdWrap_replacement($content = '', $conf = [])
    {
        return $this->replacement($content, $conf['replacement.']);
    }

    /**
     * char
     * Returns a one-character string containing the character specified by ascii code.
     *
     * Reliable results only for character codes in the integer range 0 - 127.
     *
     * @see http://php.net/manual/en/function.chr.php
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for char.
     * @return string The processed input value
     */
    public function stdWrap_char($content = '', $conf = [])
    {
        return chr((int)$conf['char']);
    }

    /**
     * intval
     * Will return an integer value of the current content
     *
     * @param string $content Input value undergoing processing in this function.
     * @return string The processed input value
     */
    public function stdWrap_intval($content = '')
    {
        return (int)$content;
    }

    /**
     * Will return a hashed value of the current content
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for hash.
     * @return string The processed input value
     * @link http://php.net/manual/de/function.hash-algos.php for a list of supported hash algorithms
     */
    public function stdWrap_hash($content = '', array $conf = [])
    {
        $algorithm = isset($conf['hash.']) ? $this->stdWrap($conf['hash'], $conf['hash.']) : $conf['hash'];
        if (function_exists('hash') && in_array($algorithm, hash_algos())) {
            return hash($algorithm, $content);
        }
        // Non-existing hashing algorithm
        return '';
    }

    /**
     * numberFormat
     * Will return a formatted number based on configuration given as stdWrap properties
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for numberFormat.
     * @return string The processed input value
     */
    public function stdWrap_numberFormat($content = '', $conf = [])
    {
        return $this->numberFormat($content, $conf['numberFormat.'] ?? []);
    }

    /**
     * date
     * Will return a formatted date based on configuration given according to PHP date/gmdate properties
     * Will return gmdate when the property GMT returns TRUE
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for date.
     * @return string The processed input value
     */
    public function stdWrap_date($content = '', $conf = [])
    {
        // Check for zero length string to mimic default case of date/gmdate.
        $content = (string)$content === '' ? $GLOBALS['EXEC_TIME'] : (int)$content;
        $content = !empty($conf['date.']['GMT']) ? gmdate($conf['date'] ?? null, $content) : date($conf['date'] ?? null, $content);
        return $content;
    }

    /**
     * strftime
     * Will return a formatted date based on configuration given according to PHP strftime/gmstrftime properties
     * Will return gmstrftime when the property GMT returns TRUE
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for strftime.
     * @return string The processed input value
     */
    public function stdWrap_strftime($content = '', $conf = [])
    {
        // Check for zero length string to mimic default case of strtime/gmstrftime
        $content = (string)$content === '' ? $GLOBALS['EXEC_TIME'] : (int)$content;
        $content = (isset($conf['strftime.']['GMT']) && $conf['strftime.']['GMT'])
            ? gmstrftime($conf['strftime'] ?? null, $content)
            : strftime($conf['strftime'] ?? null, $content);
        if (!empty($conf['strftime.']['charset'])) {
            $output = mb_convert_encoding($content, 'utf-8', trim(strtolower($conf['strftime.']['charset'])));
            return $output ?: $content;
        }
        return $content;
    }

    /**
     * strtotime
     * Will return a timestamp based on configuration given according to PHP strtotime
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for strtotime.
     * @return string The processed input value
     */
    public function stdWrap_strtotime($content = '', $conf = [])
    {
        if ($conf['strtotime'] !== '1') {
            $content .= ' ' . $conf['strtotime'];
        }
        return strtotime($content, $GLOBALS['EXEC_TIME']);
    }

    /**
     * case
     * Will transform the content to be upper or lower case only
     * Leaves HTML tags untouched
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for case.
     * @return string The processed input value
     */
    public function stdWrap_case($content = '', $conf = [])
    {
        return $this->HTMLcaseshift($content, $conf['case']);
    }

    /**
     * substring
     * Will return a substring based on position information given by stdWrap properties
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for substring.
     * @return string The processed input value
     */
    public function stdWrap_substring($content = '', $conf = [])
    {
        return $this->substring($content, $conf['substring']);
    }

    /**
     * crop
     * Crops content to a given size without caring about HTML tags
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for crop.
     * @return string The processed input value
     */
    public function stdWrap_crop($content = '', $conf = [])
    {
        return $this->crop($content, $conf['crop']);
    }

    /**
     * innerWrap
     * First of a set of different wraps which will be applied in a certain order before or after other functions that modify the content
     * See wrap
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for innerWrap.
     * @return string The processed input value
     */
    public function stdWrap_innerWrap($content = '', $conf = [])
    {
        return $this->wrap($content, $conf['innerWrap'] ?? null);
    }

    /**
     * innerWrap2
     * Second of a set of different wraps which will be applied in a certain order before or after other functions that modify the content
     * See wrap
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for innerWrap2.
     * @return string The processed input value
     */
    public function stdWrap_innerWrap2($content = '', $conf = [])
    {
        return $this->wrap($content, $conf['innerWrap2'] ?? null);
    }

    /**
     * wrap
     * This is the "mother" of all wraps
     * Third of a set of different wraps which will be applied in a certain order before or after other functions that modify the content
     * Basically it will put additional content before and after the current content using a split character as a placeholder for the current content
     * The default split character is | but it can be replaced with other characters by the property splitChar
     * Any other wrap that does not have own splitChar settings will be using the default split char though
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for wrap.
     * @return string The processed input value
     */
    public function stdWrap_wrap($content = '', $conf = [])
    {
        return $this->wrap(
            $content,
            $conf['wrap'] ?? null,
            $conf['wrap.']['splitChar'] ?? '|'
        );
    }

    /**
     * noTrimWrap
     * Fourth of a set of different wraps which will be applied in a certain order before or after other functions that modify the content
     * The major difference to any other wrap is, that this one can make use of whitespace without trimming	 *
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for noTrimWrap.
     * @return string The processed input value
     */
    public function stdWrap_noTrimWrap($content = '', $conf = [])
    {
        $splitChar = isset($conf['noTrimWrap.']['splitChar.'])
            ? $this->stdWrap($conf['noTrimWrap.']['splitChar'] ?? '', $conf['noTrimWrap.']['splitChar.'])
            : $conf['noTrimWrap.']['splitChar'] ?? '';
        if ($splitChar === null || $splitChar === '') {
            $splitChar = '|';
        }
        $content = $this->noTrimWrap(
            $content,
            $conf['noTrimWrap'],
            $splitChar
        );
        return $content;
    }

    /**
     * wrap2
     * Fifth of a set of different wraps which will be applied in a certain order before or after other functions that modify the content
     * The default split character is | but it can be replaced with other characters by the property splitChar
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for wrap2.
     * @return string The processed input value
     */
    public function stdWrap_wrap2($content = '', $conf = [])
    {
        return $this->wrap(
            $content,
            $conf['wrap2'] ?? null,
            $conf['wrap2.']['splitChar'] ?? '|'
        );
    }

    /**
     * dataWrap
     * Sixth of a set of different wraps which will be applied in a certain order before or after other functions that modify the content
     * Can fetch additional content the same way data does (i.e. {field:whatever}) and apply it to the wrap before that is applied to the content
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for dataWrap.
     * @return string The processed input value
     */
    public function stdWrap_dataWrap($content = '', $conf = [])
    {
        return $this->dataWrap($content, $conf['dataWrap']);
    }

    /**
     * wrap3
     * Seventh of a set of different wraps which will be applied in a certain order before or after other functions that modify the content
     * The default split character is | but it can be replaced with other characters by the property splitChar
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for wrap3.
     * @return string The processed input value
     */
    public function stdWrap_wrap3($content = '', $conf = [])
    {
        return $this->wrap(
            $content,
            $conf['wrap3'] ?? null,
            $conf['wrap3.']['splitChar'] ?? '|'
        );
    }

    /**
     * orderedStdWrap
     * Calls stdWrap for each entry in the provided array
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for orderedStdWrap.
     * @return string The processed input value
     */
    public function stdWrap_orderedStdWrap($content = '', $conf = [])
    {
        $sortedKeysArray = ArrayUtility::filterAndSortByNumericKeys($conf['orderedStdWrap.'], true);
        foreach ($sortedKeysArray as $key) {
            $content = $this->stdWrap($content, $conf['orderedStdWrap.'][$key . '.'] ?? null);
        }
        return $content;
    }

    /**
     * outerWrap
     * Eighth of a set of different wraps which will be applied in a certain order before or after other functions that modify the content
     *
     * @param string $content Input value undergoing processing in this function.
     * @param array $conf stdWrap properties for outerWrap.
     * @return string The processed input value
     */
    public function stdWrap_outerWrap($content = '', $conf = [])
    {
        return $this->wrap($content, $conf['outerWrap'] ?? null);
    }

    /**
     * insertData
     * Can fetch additional content the same way data does and replaces any occurrence of {field:whatever} with this content
     *
     * @param string $content Input value undergoing processing in this function.
     * @return string The processed input value
     */
    public function stdWrap_insertData($content = '')
    {
        return $this->insertData($content);
    }
}
?>
