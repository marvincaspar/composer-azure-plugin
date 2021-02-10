<?php declare(strict_types=1);

namespace Command;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use MarvinCaspar\Composer\Command\PublishCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PublishCommandTest extends TestCase
{
    private InputInterface $inputMock;
    private OutputInterface $outputMock;
    private Composer $composerWithAzureRepos;
    private Composer $composerWithoutAzureRepos;

    public function setUp(): void
    {
        $ioMock = $this->getMockBuilder(IOInterface::class)->getMock();
        $factory = new Factory();
        $this->composerWithAzureRepos = $factory->createComposer($ioMock, implode(DIRECTORY_SEPARATOR, ['./tests', 'composer-with-azure-repo.json']));
        $this->composerWithoutAzureRepos = $factory->createComposer($ioMock, implode(DIRECTORY_SEPARATOR, ['./tests', 'composer-without-azure-repo.json']));

        $this->inputMock = $this->getMockBuilder(InputInterface::class)
            ->getMockForAbstractClass();
        $this->outputMock = $this->getMockBuilder(OutputInterface::class)
            ->getMockForAbstractClass();
    }

    public function testGetName(): void
    {
        $publishCommand = new PublishCommand();
        $this->assertEquals(
            'azure:publish',
            $publishCommand->getName()
        );
    }

    public function testExecutionWithoutAzureRepos()
    {
        $publishCommand = $this->getMockBuilder(PublishCommand::class)
            ->onlyMethods(['copyPackage', 'cleanIgnoredFiles', 'sendPackage', 'removeTempFiles'])
            ->getMock();
        $publishCommand->setComposer($this->composerWithoutAzureRepos);

        $publishCommand->expects($this->never())
            ->method('copyPackage');
        $publishCommand->expects($this->never())
            ->method('cleanIgnoredFiles');
        $publishCommand->expects($this->never())
            ->method('sendPackage');
        $publishCommand->expects($this->never())
            ->method('removeTempFiles');

        $status = $publishCommand->run($this->inputMock, $this->outputMock);

        $this->assertSame(0, $status);
    }

    public function testExecutionWithAzureRepos()
    {
        $publishCommand = $this->getMockBuilder(PublishCommand::class)
            ->onlyMethods(['executeShellCmd'])
            ->getMock();
        $publishCommand->setComposer($this->composerWithAzureRepos);

        $publishCommand->expects($this->once())
            ->method('executeShellCmd')
            ->with('az artifacts universal publish --organization https://dev.azure.com/vendor --project "project" --scope project --feed feed --name vendor.package --version 1.0.0 --description "" --path ../.temp');

        $status = $publishCommand->run($this->inputMock, $this->outputMock);

        $this->assertSame(0, $status);
    }
}
