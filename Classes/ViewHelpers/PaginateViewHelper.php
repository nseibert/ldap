<?php

namespace NormanSeibert\Ldap\ViewHelpers;

/*
 *  Copyright notice
 *
 *  (c) 2023 Norman Seibert <seibert@entios.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */
/**
 * Paginate viewhelper to replace the Fluid pagination widget which has been removed in TYPO3 11.
 *
 * @author Norman Seibert <seibert@entios.de>
 */
use Closure;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\PaginationInterface;
use TYPO3\CMS\Core\Pagination\PaginatorInterface;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Service\ExtensionService;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * PaginateViewHelper
 */
class PaginateViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * @return void
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('objects', 'mixed', 'array or queryresult', true);
        $this->registerArgument('as', 'string', 'new variable name', true);
        $this->registerArgument('itemsPerPage', 'int', 'items per page', false, 10);
        $this->registerArgument('name', 'string', 'unique identification - will take "as" as fallback', false, '');
    }

    /**
     * @param array $arguments
     * @param Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        if ($arguments['objects'] === null) {
            return $renderChildrenClosure();
        }
        $templateVariableContainer = $renderingContext->getVariableProvider();
        $templateVariableContainer->add($arguments['as'], [
            'pagination' => self::getPagination($arguments, $renderingContext),
            'paginator' => self::getPaginator($arguments, $renderingContext),
            'name' => self::getName($arguments)
        ]);
        $output = $renderChildrenClosure();
        $templateVariableContainer->remove($arguments['as']);
        return $output;
    }

    /**
     * @param array $arguments
     * @param RenderingContextInterface $renderingContext
     * @return PaginationInterface
     */
    protected static function getPagination(
        array $arguments,
        RenderingContextInterface $renderingContext
    ): PaginationInterface {
        $paginator = self::getPaginator($arguments, $renderingContext);
        return GeneralUtility::makeInstance(SimplePagination::class, $paginator);
    }

    /**
     * @param array $arguments
     * @param RenderingContextInterface $renderingContext
     * @return PaginatorInterface
     */
    protected static function getPaginator(
        array $arguments,
        RenderingContextInterface $renderingContext
    ): PaginatorInterface {
        if (is_array($arguments['objects'])) {
            $paginatorClass = ArrayPaginator::class;
        } elseif (is_a($arguments['objects'], QueryResultInterface::class)) {
            $paginatorClass = QueryResultPaginator::class;
        } else {
            throw new Exception('Given object is not supported for pagination', 1634132847);
        }
        return GeneralUtility::makeInstance(
            $paginatorClass,
            $arguments['objects'],
            self::getPageNumber($arguments, $renderingContext),
            $arguments['itemsPerPage']
        );
    }

    /**
     * @param array $arguments
     * @param RenderingContextInterface $renderingContext
     * @return int
     */
    protected static function getPageNumber(array $arguments, RenderingContextInterface $renderingContext): int
    {
        $extensionName = $renderingContext->getControllerContext()->getRequest()->getControllerExtensionName();
        $pluginName = $renderingContext->getControllerContext()->getRequest()->getPluginName();
        $extensionService = GeneralUtility::makeInstance(ExtensionService::class);
        $pluginNamespace = $extensionService->getPluginNamespace($extensionName, $pluginName);
        $variables = GeneralUtility::_GP($pluginNamespace);
        // $variables = $this->request->getParsedBody()[$pluginNamespace] ?? $this->request->getQueryParams()[pluginNamespace] ?? null;
        if ($variables !== null) {
            if (!empty($variables[self::getName($arguments)]['currentPage'])) {
                return (int)$variables[self::getName($arguments)]['currentPage'];
            }
        }
        return 1;
    }

    /**
     * @param array $arguments
     * @return string
     */
    protected static function getName(array $arguments): string
    {
        return $arguments['name'] ?: $arguments['as'];
    }
}