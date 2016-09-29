<?php

namespace Droid\Test\Plugin\Debconfdb\Command;

use RuntimeException;

use org\bovigo\vfs\vfsStream;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

use Droid\Plugin\Debconfdb\Command\DebconfdbSetCommand;

class DebconfdbSetCommandTest extends \PHPUnit_Framework_TestCase
{
    protected $app;
    protected $vfs;
    protected $tester;
    protected $process;
    protected $processBuilder;

    protected function setUp()
    {
        $this->process = $this
            ->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->setMethods(array('run', 'getErrorOutput', 'getExitCode'))
            ->getMock()
        ;
        $this->processBuilder = $this
            ->getMockBuilder(ProcessBuilder::class)
            ->setMethods(array('setArguments', 'setTimeout', 'getProcess'))
            ->getMock()
        ;
        $this
            ->processBuilder
            ->method('setArguments')
            ->willReturnSelf()
        ;
        $this
            ->processBuilder
            ->method('setTimeout')
            ->willReturnSelf()
        ;
        $this
            ->processBuilder
            ->method('getProcess')
            ->willReturn($this->process)
        ;

        $command = new DebconfdbSetCommand($this->processBuilder);

        $this->app = new Application;
        $this->app->add($command);

        $this->tester = new CommandTester($command);

        $this->vfs = vfsStream::setup();
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The file does not exist: "vfs://root/not-a-file"
     */
    public function testCommandFailsWhenFileNotFound()
    {
        $this->tester->execute(array(
            'command' => $this->app->find('debconfdb:set')->getName(),
            'file' => vfsStream::url('root/not-a-file'),
        ));
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The format of file "vfs://root/some-file" is incorrect
     */
    public function testCommandFailsWhenFileFormatIsIncorrect()
    {
        vfsStream::newFile('some-file')->at($this->vfs);

        $this
            ->processBuilder
            ->expects($this->once())
            ->method('setArguments')
            ->with(
                array(
                    'debconf-set-selections',
                    '--checkonly',
                    'vfs://root/some-file',
                )
            )
        ;
        $this
            ->processBuilder
            ->expects($this->once())
            ->method('setTimeout')
            ->with($this->equalTo(0.0))
        ;
        $this
            ->processBuilder
            ->expects($this->once())
            ->method('getProcess')
        ;
        $this
            ->process
            ->expects($this->once())
            ->method('run')
            ->willReturn(1)
        ;

        $this->tester->execute(array(
            'command' => $this->app->find('debconfdb:set')->getName(),
            'file' => vfsStream::url('root/some-file'),
        ));
    }

    public function testCommandReportsOnlyInCheckMode()
    {
        vfsStream::newFile('some-file')->at($this->vfs);

        $this
            ->processBuilder
            ->expects($this->once())
            ->method('setArguments')
            ->with(
                array(
                    'debconf-set-selections',
                    '--checkonly',
                    'vfs://root/some-file',
                )
            )
        ;
        $this
            ->processBuilder
            ->expects($this->once())
            ->method('setTimeout')
            ->with($this->equalTo(0.0))
        ;
        $this
            ->processBuilder
            ->expects($this->once())
            ->method('getProcess')
        ;
        $this
            ->process
            ->expects($this->once())
            ->method('run')
            ->willReturn(0)
        ;

        $this->tester->execute(array(
            'command' => $this->app->find('debconfdb:set')->getName(),
            'file' => vfsStream::url('root/some-file'),
            '--check' => true,
        ));

        $this->assertRegExp(
            '/^I would set debconf database entries from the file "[^"]*"/',
            $this->tester->getDisplay()
        );
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage I cannot set database entries from the file "vfs://root/some-file"
     */
    public function testCommandFailsWhenItFailsToSetSelections()
    {
        vfsStream::newFile('some-file')->at($this->vfs);

        $this
            ->processBuilder
            ->expects($this->exactly(2))
            ->method('setArguments')
            ->withConsecutive(
                array(array(
                    'debconf-set-selections',
                    '--checkonly',
                    'vfs://root/some-file',
                )),
                array(array(
                    'debconf-set-selections',
                    'vfs://root/some-file',
                ))
            )
        ;
        $this
            ->processBuilder
            ->expects($this->exactly(2))
            ->method('setTimeout')
            ->with($this->equalTo(0.0))
        ;
        $this
            ->processBuilder
            ->expects($this->exactly(2))
            ->method('getProcess')
        ;
        $this
            ->process
            ->expects($this->exactly(2))
            ->method('run')
            ->willReturnOnConsecutiveCalls(0, 1)
        ;

        $this->tester->execute(array(
            'command' => $this->app->find('debconfdb:set')->getName(),
            'file' => vfsStream::url('root/some-file'),
        ));
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage I cannot cleanup: failed to delete file "vfs://root/some-file"
     */
    public function testCommandFailsWhenItFailsToCleanup()
    {
        vfsStream::newFile('some-file')->at($this->vfs);

        $this
            ->processBuilder
            ->expects($this->exactly(3))
            ->method('setArguments')
            ->withConsecutive(
                array(array(
                    'debconf-set-selections',
                    '--checkonly',
                    'vfs://root/some-file',
                )),
                array(array(
                    'debconf-set-selections',
                    'vfs://root/some-file',
                )),
                array(array(
                    'unlink',
                    'vfs://root/some-file',
                ))
            )
        ;
        $this
            ->processBuilder
            ->expects($this->exactly(3))
            ->method('setTimeout')
            ->with($this->equalTo(0.0))
        ;
        $this
            ->processBuilder
            ->expects($this->exactly(3))
            ->method('getProcess')
        ;
        $this
            ->process
            ->expects($this->exactly(3))
            ->method('run')
            ->willReturnOnConsecutiveCalls(0, 0, 1)
        ;

        $this->tester->execute(array(
            'command' => $this->app->find('debconfdb:set')->getName(),
            'file' => vfsStream::url('root/some-file'),
        ));
    }

    public function testCommandWithOptionNoCleanupDoesNotDeleteFile()
    {
        vfsStream::newFile('some-file')->at($this->vfs);

        $this
            ->processBuilder
            ->expects($this->exactly(2))
            ->method('setArguments')
            ->withConsecutive(
                array(array(
                    'debconf-set-selections',
                    '--checkonly',
                    'vfs://root/some-file',
                )),
                array(array(
                    'debconf-set-selections',
                    'vfs://root/some-file',
                ))
            )
        ;
        $this
            ->processBuilder
            ->expects($this->exactly(2))
            ->method('setTimeout')
            ->with($this->equalTo(0.0))
        ;
        $this
            ->processBuilder
            ->expects($this->exactly(2))
            ->method('getProcess')
        ;
        $this
            ->process
            ->expects($this->exactly(2))
            ->method('run')
            ->willReturnOnConsecutiveCalls(0, 0)
        ;

        $this->tester->execute(array(
            'command' => $this->app->find('debconfdb:set')->getName(),
            'file' => vfsStream::url('root/some-file'),
            '--no-cleanup' => true,
        ));

        $this->assertRegExp(
            '/^I have set debconf database entries from the file "[^"]*"/',
            $this->tester->getDisplay()
        );
    }

    public function testCommandSucceedsAndCleansUp()
    {
        vfsStream::newFile('some-file')->at($this->vfs);

        $this
            ->processBuilder
            ->expects($this->exactly(3))
            ->method('setArguments')
            ->withConsecutive(
                array(array(
                    'debconf-set-selections',
                    '--checkonly',
                    'vfs://root/some-file',
                )),
                array(array(
                    'debconf-set-selections',
                    'vfs://root/some-file',
                )),
                array(array(
                    'unlink',
                    'vfs://root/some-file',
                ))
            )
        ;
        $this
            ->processBuilder
            ->expects($this->exactly(3))
            ->method('setTimeout')
            ->with($this->equalTo(0.0))
        ;
        $this
            ->processBuilder
            ->expects($this->exactly(3))
            ->method('getProcess')
        ;
        $this
            ->process
            ->expects($this->exactly(3))
            ->method('run')
            ->willReturnOnConsecutiveCalls(0, 0, 0)
        ;

        $this->tester->execute(array(
            'command' => $this->app->find('debconfdb:set')->getName(),
            'file' => vfsStream::url('root/some-file'),
        ));

        $this->assertRegExp(
            '/^I have set debconf database entries from the file "[^"]*"/',
            $this->tester->getDisplay()
        );
    }
}
