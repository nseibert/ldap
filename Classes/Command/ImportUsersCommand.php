<?php
namespace NormanSeibert\Ldap\Command;
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
 * @copyright 2020 Norman Seibert
 */

use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Input\InputArgument;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Style\SymfonyStyle;
use \TYPO3\CMS\Core\Core\Bootstrap;
use \NormanSeibert\Ldap\Domain\Repository\Typo3User\FrontendUserRepository;
use \NormanSeibert\Ldap\Domain\Repository\Typo3User\BackendUserRepository;
use \NormanSeibert\Ldap\Domain\Model\Configuration\Configuration;
use \NormanSeibert\Ldap\Service\LdapImporter;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Extbase\Object\ObjectManager;
use \TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Controller for scheduled execution
 */
class ImportUsersCommand extends Command {

	/**
	 * @var FrontendUserRepository
	 */
	protected $feUserRepository;

	/**
	 * @var BackendUserRepository
	 */
	protected $beUserRepository;
	
	/**
	 * @var Configuration
	 */
	protected $ldapConfig;
	
	/**
	 * @var LdapImporter
	 */
	protected $importer;

    /**
     *
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

	/**
	 * @param FrontendUserRepository $feUserRepository
	 * @param BackendUserRepository $beUserRepository
	 * @param Configuration $ldapConfig
	 * @param LdapImporter $importer
	 * @param 
	 */
	public function __construct(FrontendUserRepository $feUserRepository, BackendUserRepository $beUserRepository, Configuration $ldapConfig, LdapImporter $importer) {
		parent::__construct();
	    $this->feUserRepository = $feUserRepository;
	    $this->beUserRepository = $beUserRepository;
	    $this->ldapConfig = $ldapConfig;
	    $this->importer = $importer;
	    $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
	    $this->persistenceManager = $objectManager->get(PersistenceManagerInterface::class);
	}

    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure() {
        $this
        	->setDescription(
        		'Import new users'
        	)
        	->setHelp(
        			'New users will be imported from LDAP servers.'
        	)
        	->addArgument(
        		'servers',
        		InputArgument::REQUIRED,
        		'[int] Comma sparated server identifiers from configuration file'
        	)
        	->addArgument(
        		'processFe',
        		InputArgument::REQUIRED,
        		'[Boolean] Import frontend users'
        	)
        	->addArgument(
        		'processBe',
        		InputArgument::REQUIRED,
        		'[Boolean] Import backend users'
        	);
    }

    /**
     * Executes the command to import LDAP users
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function execute(InputInterface $input, OutputInterface $output) {
    	// Ensure the _cli_ user is authenticated
        Bootstrap::initializeBackendAuthentication();

        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

    	$servers = $input->getArgument('servers');
    	$processFe = $input->getArgument('processFe');
    	$processBe = $input->getArgument('processBe');

    	$ldapServers = $this->ldapConfig->getLdapServers();
		$serverUids = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $servers, TRUE);
		foreach ($ldapServers as $server) {
            /* @var $server \NormanSeibert\Ldap\Domain\Model\LdapServer\Server */
			if (in_array($server->getConfiguration()->getUid(), $serverUids)) {
				$io->writeln('Importing from server: ' . $server->getConfiguration()->getUid());
				$runs = array();
				if ($processFe) {
					$this->importer->init($server, 'fe');
					$runs[] = $this->importer->doImport();
					$this->persistenceManager->persistAll();
					$feUsers = $this->feUserRepository->countByLastRun($runs);
					$io->writeln('Frontend users: ' . $feUsers);
				}
				if ($processBe) {
					$this->importer->init($server, 'be');
					$runs[] = $this->importer->doImport();
					$this->persistenceManager->persistAll();
					$beUsers = $this->beUserRepository->countByLastRun($runs);
					$io->writeln('Backend users: ' . $beUsers);
				}
			}
		}

		$this->persistenceManager->persistAll();

        return 0; // everything fine
    }

}