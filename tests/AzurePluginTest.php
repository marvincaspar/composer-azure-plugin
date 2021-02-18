<?php declare(strict_types=1);

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Script\ScriptEvents;
use MarvinCaspar\Composer\AzurePlugin;
use MarvinCaspar\Composer\AzureRepository;
use MarvinCaspar\Composer\FileHelper;
use PHPUnit\Framework\TestCase;

final class AzurePluginTest extends TestCase
{
    protected AzurePlugin $azurePlugin;
    protected IOInterface $ioMock;
    protected Composer $composerWithAzureRepos;
    protected Composer $composerWithoutAzureRepos;
    protected string $cacheDir;

    public function setUp(): void
    {
        $this->azurePlugin = new AzurePlugin();

        $this->ioMock = $this->getMockBuilder(IOInterface::class)->getMock();
        $factory = new Factory();
        $this->composerWithAzureRepos = $factory->createComposer($this->ioMock, implode(DIRECTORY_SEPARATOR, ['./tests', 'composer-with-azure-repo.json']));
        $this->composerWithoutAzureRepos = $factory->createComposer($this->ioMock, implode(DIRECTORY_SEPARATOR, ['./tests', 'composer-without-azure-repo.json']));

        $this->cacheDir = str_replace(DIRECTORY_SEPARATOR, '/', $this->composerWithAzureRepos->getConfig()->get('cache-dir')) . '/azure';
    }

    public function testGetCapabilities(): void
    {
        $this->assertEquals(
            ['Composer\Plugin\Capability\CommandProvider' => 'MarvinCaspar\Composer\CommandProvider'],
            $this->azurePlugin->getCapabilities()
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $this->assertEquals(
            [
                ScriptEvents::PRE_INSTALL_CMD => [['execute', 50000]],
                ScriptEvents::PRE_UPDATE_CMD => [['execute', 50000]],

                ScriptEvents::POST_INSTALL_CMD => [['modifyComposerLock', 50000]],
                ScriptEvents::POST_UPDATE_CMD => [['modifyComposerLock', 50000]]
            ],
            AzurePlugin::getSubscribedEvents()
        );
    }

    public function testExecuteWithoutAzureRepos()
    {
        $azurePlugin = $this->getMockBuilder(AzurePlugin::class)
            ->onlyMethods(['parseRequiredPackages', 'fetchAzurePackages', 'addAzureRepositories'])
            ->getMock();
        $azurePlugin->activate($this->composerWithoutAzureRepos, $this->ioMock);
        $azurePlugin->execute();
        $azurePlugin->expects($this->never())
            ->method('parseRequiredPackages');
        $azurePlugin->expects($this->never())
            ->method('fetchAzurePackages');
        $azurePlugin->expects($this->never())
            ->method('addAzureRepositories');
    }

    public function testExecuteWithAzureRepos()
    {
        $azurePlugin = $this->getMockBuilder(AzurePlugin::class)
            ->onlyMethods(['parseRequiredPackages', 'fetchAzurePackages', 'addAzureRepositories'])
            ->getMock();
        $azurePlugin->activate($this->composerWithAzureRepos, $this->ioMock);
        $this->assertTrue($azurePlugin->hasAzureRepositories);
        $azurePlugin->expects($this->once())
            ->method('parseRequiredPackages')
            ->with($this->composerWithAzureRepos);
        $azurePlugin->expects($this->once())
            ->method('fetchAzurePackages')
            ->with([]);
        $azurePlugin->expects($this->once())
            ->method('addAzureRepositories')
            ->with([]);

        $azurePlugin->execute();
    }

    public function testExecuteWithoutInternalMocks()
    {
        $azureRepo = new AzureRepository('dev.azure.com/vendor', 'project', 'feed', false);
        $azureRepo->addArtifact('vendor/azure-package', '1.0.0');

        $azurePlugin = $this->getMockBuilder(AzurePlugin::class)
            ->onlyMethods(['executeShellCmd', 'solveDependencies', 'getComposer', 'getFileHelper'])
            ->getMock();
        $azurePlugin->activate($this->composerWithAzureRepos, $this->ioMock);

        $pathTmp = implode(DIRECTORY_SEPARATOR, [$this->cacheDir, 'dev.azure.com/vendor', 'feed', 'vendor/azure-package', 'tmp']);
        $pathVersion = implode(DIRECTORY_SEPARATOR, [$this->cacheDir, 'dev.azure.com/vendor', 'feed', 'vendor/azure-package', '1.0.0']);
        $azurePlugin->expects($this->once())
            ->method('executeShellCmd')
            ->with('az artifacts universal download --organization https://dev.azure.com/vendor --project "project" --scope project --feed feed --name vendor.azure-package --version \'1.0.0\' --path ' . $pathTmp);

        $azurePlugin->expects($this->once())
            ->method('getComposer')
            ->willReturn($this->composerWithAzureRepos);

        $azurePlugin->expects($this->once())
            ->method('solveDependencies')
            ->with($pathVersion)
            ->willReturn([]);

        $helperMock = $this->getMockBuilder(FileHelper::class)
            ->getMock();
        $azurePlugin->expects($this->once())
            ->method('getFileHelper')
            ->willReturn($helperMock);

        $azurePlugin->execute();
    }

    public function testExecuteWithoutAzureReposAndInternalMocks()
    {
        $azureRepo = new AzureRepository('dev.azure.com/vendor', 'project', 'feed', false);
        $azureRepo->addArtifact('vendor/azure-package', '1.0.0');

        $azurePlugin = $this->getMockBuilder(AzurePlugin::class)
            ->onlyMethods(['executeShellCmd', 'solveDependencies'])
            ->getMock();
        $azurePlugin->activate($this->composerWithoutAzureRepos, $this->ioMock);

        $azurePlugin->expects($this->never())
            ->method('executeShellCmd');

        $azurePlugin->expects($this->never())
            ->method('solveDependencies');

        $azurePlugin->execute();
    }

    public function testModifyComposerLockWithAzureRepos()
    {
        $sedCommand = 'sed -i -e "s|${COMPOSER_HOME_PATH}|~/.composer|g" composer.lock';
        // on macos sed needs an empty string for the i parameter
        if (strtolower(PHP_OS) === 'darwin') {
            $sedCommand = 'sed -i "" -e "s|${COMPOSER_HOME_PATH}|~/.composer|g" composer.lock';
        }

        $azurePlugin = $this->getMockBuilder(AzurePlugin::class)
            ->onlyMethods(['executeShellCmd'])
            ->getMock();
        $azurePlugin->activate($this->composerWithAzureRepos, $this->ioMock);
        $azurePlugin->expects($this->once())
            ->method('executeShellCmd')
            ->with('COMPOSER_HOME_PATH=$(composer config --list --global | grep "\[home\]" | awk \'{print $2}\' | xargs) && ' . $sedCommand);

        $azurePlugin->modifyComposerLock();
    }

    public function testModifyComposerLockWithoutAzureRepos()
    {
        $azurePlugin = $this->getMockBuilder(AzurePlugin::class)
            ->onlyMethods(['executeShellCmd'])
            ->getMock();
        $azurePlugin->activate($this->composerWithoutAzureRepos, $this->ioMock);
        $azurePlugin->expects($this->never())
            ->method('executeShellCmd');

        $azurePlugin->modifyComposerLock();
    }
}