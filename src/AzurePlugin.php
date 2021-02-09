<?php

namespace MarvinCaspar\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\InstallerEvents;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

use MarvinCaspar\Composer\AzureRepository;

/**
 * @todo load packages on install/update only
 * @todo handle version modifiers
 * @todo avoid redownloading cached packages
 */
class AzurePlugin implements PluginInterface, EventSubscriberInterface, Capable
{
    protected Composer $composer;
    protected IOInterface $io;
    protected string $cacheDir;
    protected Array $repositories = [];

    public function activate(Composer $composer, IOInterface $io)
    {
        $extra = $composer->getPackage()->getExtra();
        
        if(!isset($extra['azure-repositories']) || !is_array($extra['azure-repositories']))
        {
            return;
        }
        
        $this->composer = $composer;
        $this->io = $io;
        $this->cacheDir = str_replace(DIRECTORY_SEPARATOR, '/', $this->composer->getConfig()->get('cache-dir')) . '/azure';
    }

    public function getCapabilities()
    {
        return array(
            'Composer\Plugin\Capability\CommandProvider' => 'MarvinCaspar\Composer\CommandProvider',
        );
    }

    public static function getSubscribedEvents()
    {
        return [
            // InstallerEvents::PRE_DEPENDENCIES_SOLVING   => [ [ 'fetchAzurePackages', 0 ] ],
            
            ScriptEvents::PRE_INSTALL_CMD   => [ [ 'execute', 50000 ] ],
            ScriptEvents::PRE_UPDATE_CMD    => [ [ 'execute', 50000 ] ],

            ScriptEvents::POST_INSTALL_CMD   => [ [ 'modifyComposerLock', 50000 ] ],
            ScriptEvents::POST_UPDATE_CMD    => [ [ 'modifyComposerLock', 50000 ] ]
        ];
    }

    public function execute()
    {
        $this->parseRequiredPackages();
        $this->fetchAzurePackages();
        $this->addAzureRepositories();
    }

    public function modifyComposerLock()
    {
        $sedCommand = 'sed -i -e "s|${COMPOSER_HOME_PATH}|~/.composer|g" composer.lock';
        // on macos sed needs an empty string for the i parameter
        if(strtolower(PHP_OS) === 'darwin') {
            $sedCommand = 'sed -i "" -e "s|${COMPOSER_HOME_PATH}|~/.composer|g" composer.lock';
        }

        $command = 'COMPOSER_HOME_PATH=$(composer config --list --global | grep "\[home\]" | awk \'{print $2}\' | xargs) && ' . $sedCommand;

        $output = array();
        $return_var = -1;
        exec($command, $output, $return_var);
    
        if ($return_var !== 0) {
            throw new \Exception(implode("\n", $output));
        }
        $this->io->write('<info>Modified composer.lock path</info>');
    }

    protected function fetchAzurePackages()
    {
        $package_count = 0;

        foreach($this->repositories as $azureRepository)
        {
            $package_count+= $azureRepository->countArtifacts();
        }

        if($package_count == 0)
        {
            return;
        }

        $this->io->write('');
        $this->io->write('<info>Fetching packages from Azure</info>');
        $this->downloadAzureArtifacts();
    }

    protected function parseRequiredPackages()
    {
        $extra = $this->composer->getPackage()->getExtra();
        $requires = $this->composer->getPackage()->getRequires();

        foreach($extra['azure-repositories'] as [ 'organization' => $organization, 'project' => $project, 'feed' => $feed, 'symlink' => $symlink, 'packages' => $packages ])
        {
            $azureRepository = new AzureRepository($organization, $project, $feed, $symlink);

            foreach($packages as $packageName)
            {
                if(array_key_exists($packageName, $requires))
                {
                    $azureRepository->addArtifact($packageName, $requires[$packageName]->getPrettyConstraint());
                }
            }

            $this->repositories[] = $azureRepository;
        }
    }

    protected function addAzureRepositories()
    {
        $repositories = [];
        
        foreach($this->repositories as $azureRepository)
        {
            $organization = $azureRepository->getOrganization();
            $feed = $azureRepository->getFeed();
            $symlink = $azureRepository->getSymlink();
            
            foreach($azureRepository->getArtifacts() as $artifact)
            {
                // array_unshift($repositories, [
                //     'type'      => 'path',
                //     'url'       => implode('/', [ $this->cacheDir, $organization, $feed, $artifact['name'], $artifact['version'] ]),
                //     'options'   => [ 'symlink' =>  false ]
                // ]);

                $repo = $this->composer->getRepositoryManager()->createRepository(
                    'path', 
                    array(
                        'url' => implode('/', [ $this->cacheDir, $organization, $feed, $artifact['name'], $artifact['version']]),
                        'options'   => [ 'symlink' =>  $symlink ]
                    )
                );
                $this->composer->getRepositoryManager()->addRepository($repo);
            }
        }

        // $this->composer->getConfig()->merge(['repositories' => $repositories]);
    }

    protected function downloadAzureArtifacts()
    {
        foreach($this->repositories as $azureRepository)
        {
            $organization = $azureRepository->getOrganization();
            $project = $azureRepository->getProject();
            $scope = $azureRepository->getScope();
            $feed = $azureRepository->getFeed();
            $artifacts = $azureRepository->getArtifacts();

            foreach($artifacts as $artifact)
            {
                $path = implode(DIRECTORY_SEPARATOR, [ $this->cacheDir, $organization, $feed, $artifact['name'], $artifact['version'] ]);

                // continue if dir already exists and it is not empty
                if(is_dir($path) && count(scandir($path)) > 2) {
                    $this->io->write('<info>Package ' . $artifact['name'] . ' already downloaded</info>');
                    continue;
                }

                $command = 'az artifacts universal download';
                $command.= ' --organization ' . 'https://' . $organization;
                $command.= ' --project "' . $project .'"';
                $command.= ' --scope ' . $scope;
                $command.= ' --feed ' . $feed;
                $command.= ' --name ' . str_replace('/', '.', $artifact['name']);
                $command.= ' --version ' . $artifact['version'];
                $command.= ' --path ' . $path;

                $output = array();
                $return_var = -1;
                exec($command, $output, $return_var);
            
                if ($return_var !== 0) {
                    throw new \Exception(implode("\n", $output));
                }
                $this->io->write('<info>Package ' . $artifact['name'] . ' downloaded</info>');
            }
        }
    }
}
