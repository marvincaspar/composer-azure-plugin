<?php

namespace MarvinCaspar\Composer\Command;

use Composer\Command\BaseCommand;
use Composer\Factory;
use Composer\Package\RootPackage;
use Composer\Package\PackageInterface;
use Composer\Downloader\DownloadManager;
use Composer\Package\Archiver\ArchiveManager;
use Composer\Util\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use MarvinCaspar\Composer\Helpers;

class PublishCommand extends BaseCommand
{
    protected $tempDir = '../.temp';

    protected function configure()
    {
        $this->setName('azure:publish');
        $this->setDescription('Publish this composer package to Azure DevOps.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $extra = $this->getComposer()->getPackage()->getExtra();
        
        if(!isset($extra['azure-publish-registry']) || !is_array($extra['azure-publish-registry']))
        {
            return;
        }

        $this->copyPackage();
        $this->cleanIgnoredFiles();
        // $this->removeGitFolder();
        $this->sendPackage();
        $this->removeTempFiles();

        $output->writeln('Done.');
    }

    protected function copyPackage()
    {
        Helpers::copyDirectory('.', $this->tempDir);
    }

    protected function cleanIgnoredFiles()
    {
        if(!file_exists($this->tempDir . '/.gitignore'))
        {
            return;
        }

        $ignoredFiles = file($this->tempDir . '/.gitignore');

        if($ignoredFiles === false)
        {
            return;
        }

        foreach($ignoredFiles as $ignoredFile)
        {
            if (empty(trim($ignoredFile)))
            {
                continue;
            }

            $ignoredDir = $this->tempDir . trim($ignoredFile);
            if(is_dir($ignoredDir))
            {
                Helpers::removeDirectory($ignoredDir);
            }
        }
    }

    protected function removeGitFolder()
    {
        $gitFolder = $this->tempDir . '/.git';
        if(is_dir($gitFolder))
        {
            Helpers::removeDirectory($gitFolder);
        }
    }

    protected function sendPackage()
    {
        $extra = $this->getComposer()->getPackage()->getExtra();

        $command = 'az artifacts universal publish';
        $command.= ' --organization ' . 'https://' . $extra['azure-publish-registry']['organization'];
        $command.= ' --project "' . $extra['azure-publish-registry']['project'] .'"';
        $command.= ' --scope project';
        $command.= ' --feed ' . $extra['azure-publish-registry']['feed'];
        $command.= ' --name ' . str_replace('/', '.', $this->getComposer()->getPackage()->getName());
        $command.= ' --version ' . $this->getComposer()->getPackage()->getPrettyVersion();
        $command.= ' --description "' . $this->getComposer()->getPackage()->getDescription() . '"';
        $command.= ' --path ' . $this->tempDir;

        $output = array();
        $return_var = -1;
        exec($command, $output, $return_var);
    
        if ($return_var !== 0) {
            throw new \Exception(implode("\n", $output));
        }
    }

    protected function removeTempFiles()
    {
        Helpers::removeDirectory($this->tempDir);
    }
}