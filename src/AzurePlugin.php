<?php

namespace MarvinCaspar\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

class AzurePlugin implements PluginInterface, EventSubscriberInterface, Capable
{
    protected Composer $composer;
    protected IOInterface $io;
    protected string $cacheDir;
    public bool $hasAzureRepositories = true;

    protected FileHelper $fileHelper;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->cacheDir = str_replace(DIRECTORY_SEPARATOR, '/', $this->composer->getConfig()->get('cache-dir')) . '/azure';


        $extra = $composer->getPackage()->getExtra();
        if (!isset($extra['azure-repositories']) || !is_array($extra['azure-repositories'])) {
            $this->hasAzureRepositories = false;
        }
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
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
            ScriptEvents::PRE_INSTALL_CMD => [['execute', 50000]],
            ScriptEvents::PRE_UPDATE_CMD => [['execute', 50000]],

            ScriptEvents::POST_INSTALL_CMD => [['modifyComposerLock', 50000]],
            ScriptEvents::POST_UPDATE_CMD => [['modifyComposerLock', 50000]]
        ];
    }

    public function execute(): void
    {
        $this->fileHelper = $this->getFileHelper();

        if (!$this->hasAzureRepositories) {
            return;
        }

        $azureRepositories = $this->parseRequiredPackages($this->composer);
        $azureRepositoriesWithDependencies = $this->fetchAzurePackages($azureRepositories);
        $this->addAzureRepositories($azureRepositoriesWithDependencies);
    }

    protected function getFileHelper(): FileHelper
    {
        return new FileHelper();
    }

    public function modifyComposerLock(): void
    {
        if (!$this->hasAzureRepositories) {
            return;
        }

        $sedCommand = 'sed -i -e "s|${COMPOSER_HOME_PATH}|~/.composer|g" composer.lock';
        // on macos sed needs an empty string for the i parameter
        if (strtolower(PHP_OS) === 'darwin') {
            $sedCommand = 'sed -i "" -e "s|${COMPOSER_HOME_PATH}|~/.composer|g" composer.lock';
        }

        $command = 'COMPOSER_HOME_PATH=$(composer config --list --global | grep "\[home\]" | awk \'{print $2}\' | xargs) && ' . $sedCommand;

        $this->executeShellCmd($command);

        $this->io->write('<info>Modified composer.lock path</info>');
    }

    protected function parseRequiredPackages(Composer $composer): array
    {
        $azureRepositories = [];
        $extra = $composer->getPackage()->getExtra();
        $requires = $composer->getPackage()->getRequires();

        if (!isset($extra['azure-repositories'])) {
            return [];
        }

        foreach ($extra['azure-repositories'] as ['organization' => $organization, 'project' => $project, 'feed' => $feed, 'symlink' => $symlink, 'packages' => $packages]) {
            $azureRepository = new AzureRepository($organization, $project, $feed, $symlink);

            foreach ($packages as $packageName) {
                if (array_key_exists($packageName, $requires)) {
                    $azureRepository->addArtifact($packageName, $requires[$packageName]->getPrettyConstraint());
                }
            }

            $azureRepositories[] = $azureRepository;
        }

        return $azureRepositories;
    }

    protected function fetchAzurePackages(array $azureRepositories): array
    {
        $package_count = 0;

        foreach ($azureRepositories as $azureRepository) {
            $package_count += $azureRepository->countArtifacts();
        }

        if ($package_count == 0) {
            return [];
        }

        $this->io->write('');
        $this->io->write('<info>Fetching packages from Azure</info>');
        return $this->downloadAzureArtifacts($azureRepositories);
    }

    protected function addAzureRepositories(array $azureRepositories)
    {
        foreach ($azureRepositories as $azureRepository) {
            $organization = $azureRepository->getOrganization();
            $feed = $azureRepository->getFeed();
            $symlink = $azureRepository->getSymlink();

            foreach ($azureRepository->getArtifacts() as $artifact) {
                $repo = $this->composer->getRepositoryManager()->createRepository(
                    'path',
                    array(
                        'url' => implode('/', [$this->cacheDir, $organization, $feed, $artifact['name'], $artifact['version']]),
                        'options' => ['symlink' => $symlink]
                    )
                );
                $this->composer->getRepositoryManager()->addRepository($repo);
            }
        }
    }

    protected function downloadAzureArtifacts(array $azureRepositories): array
    {
        $azureRepositoriesWithDependencies = $azureRepositories;
        foreach ($azureRepositories as $azureRepository) {
            $organization = $azureRepository->getOrganization();
            $project = $azureRepository->getProject();
            $scope = $azureRepository->getScope();
            $feed = $azureRepository->getFeed();
            $artifacts = $azureRepository->getArtifacts();

            foreach ($artifacts as $artifact) {
                $path = implode(DIRECTORY_SEPARATOR, [$this->cacheDir, $organization, $feed, $artifact['name'], 'tmp']);

                // continue if dir already exists and it is not empty
                if (is_dir($path) && count(scandir($path)) > 2) {
                    $this->io->write('<info>Package ' . $artifact['name'] . ' already downloaded</info>');
                } else {

                    $command = 'az artifacts universal download';
                    $command .= ' --organization ' . 'https://' . $organization;
                    $command .= ' --project "' . $project . '"';
                    $command .= ' --scope ' . $scope;
                    $command .= ' --feed ' . $feed;
                    $command .= ' --name ' . str_replace('/', '.', $artifact['name']);
                    $command .= ' --version \'' . $artifact['version'] . '\'';
                    $command .= ' --path ' . $path;

                    $this->executeShellCmd($command);

                    $composer = $this->getComposer($path);
                    $version = $composer->getPackage()->getPrettyVersion();

                    $this->fileHelper->copyDirectory($path, str_replace('/tmp', $version, $path));
                    $this->fileHelper->removeDirectory($path);

                    $this->io->write('<info>Package ' . $artifact['name'] . ' downloaded</info>');
                }

                $deps = $this->solveDependencies($path);
                $azureRepositoriesWithDependencies = array_merge($azureRepositoriesWithDependencies, $deps);
            }
        }
        return $azureRepositoriesWithDependencies;
    }

    protected function executeShellCmd(string $cmd)
    {
        $output = array();
        $return_var = -1;
        exec($cmd, $output, $return_var);

        if ($return_var !== 0) {
            throw new \Exception(implode("\n", $output));
        }
    }

    protected function solveDependencies(string $packagePath): array
    {
        $composer = $this->getComposer($packagePath);
        $azureRepositories = $this->parseRequiredPackages($composer);
        $this->fetchAzurePackages($azureRepositories);

        return $azureRepositories;
    }

    protected function getComposer(string $path): Composer
    {
        $factory = new Factory();
        return $factory->createComposer($this->io, implode(DIRECTORY_SEPARATOR, [$path, Factory::getComposerFile()]));
    }
}
