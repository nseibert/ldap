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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ExtensionService;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception as FluidViewHelperException;

/**
 * UriViewHelper
 */
class UriViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * Initialize arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('name', 'string', 'identifier important if more widgets on same page', false, 'widget');
        $this->registerArgument('arguments', 'array', 'Arguments', false, []);
    }

    /**
     * Build an uri to current action with &tx_ext_plugin[currentPage]=2
     *
     * @return string The rendered uri
     * @throws FluidViewHelperException
     */
    public function render(): string
    {
        if (! $this->renderingContext instanceof RenderingContext) {
            throw new FluidViewHelperException(
                'Something went wrong; RenderingContext should be available in ViewHelper',
                1638341671
            );
        }
        $uriBuilder = $this->renderingContext->getUriBuilder();
        $extensionName = $this->renderingContext->getRequest()->getControllerExtensionName();
        $pluginName = $this->renderingContext->getRequest()->getPluginName();
        $extensionService = GeneralUtility::makeInstance(ExtensionService::class);
        $pluginNamespace = $extensionService->getPluginNamespace($extensionName, $pluginName);
        $argumentPrefix = $pluginNamespace . '[' . $this->arguments['name'] . ']';
        $arguments = $this->hasArgument('arguments') ? $this->arguments['arguments'] : [];
        if ($this->hasArgument('action')) {
            $arguments['action'] = $this->arguments['action'];
        }
        if ($this->hasArgument('format') && $this->arguments['format'] !== '') {
            $arguments['format'] = $this->arguments['format'];
        }
        $uriBuilder->reset()
            ->setArguments([$argumentPrefix => $arguments])
            ->setAddQueryString(true)
            ->setArgumentsToBeExcludedFromQueryString([$argumentPrefix, 'cHash']);
        return $uriBuilder->build();
    }
}