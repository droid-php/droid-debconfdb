<?php

namespace Droid\Plugin\Debconfdb\Command;

use RuntimeException;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

use Droid\Lib\Plugin\Command\CheckableTrait;

class DebconfdbSetCommand extends Command
{
    use CheckableTrait;

    private $processBuilder;

    public function __construct(
        ProcessBuilder $processBuilder,
        $name = null
    ) {
        $this->processBuilder = $processBuilder;
        return parent::__construct($name);
    }

    public function configure()
    {
        $this
            ->setName('debconfdb:set')
            ->setDescription('Set debconf database entries from the supplied file.')
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'File containing database entries as described in manpage debconf-set-selections(1).'
            )
            ->addOption(
                'no-cleanup',
                null,
                InputOption::VALUE_NONE,
                'Do not delete <file>.'
            )
        ;
        $this->configureCheckMode();
        $help = <<<HELP
The format of the file is first checked to ensure it is correct and is deleted
after it is successfully processed (unless the --no-cleanup option is given).
HELP;
        $this->setHelp($help);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->activateCheckMode($input);

        $filepath = $input->getArgument('file');

        if (!file_exists($filepath)) {
            throw new RuntimeException(
                sprintf('The file does not exist: "%s".', $filepath)
            );
        }

        $p = $this->getProcess(
            array('debconf-set-selections', '--checkonly', $filepath)
        );
        if ($p->run()) {
            throw new RuntimeException(
                sprintf(
                    'The format of file "%s" is incorrect.',
                    $filepath,
                    $p->getErrorOutput()
                )
            );
        }

        $this->markChange();

        if ($this->checkMode()) {
            $output->writeLn(
                sprintf(
                    'I would set debconf database entries from the file "%s".',
                    $filepath
                )
            );
            $this->reportChange($output);
            return 0;
        }

        $p = $this->getProcess(array('debconf-set-selections', $filepath));
        if ($p->run()) {
            throw new RuntimeException(
                sprintf(
                    'I cannot set database entries from the file "%s": %s.',
                    $filepath,
                    $p->getErrorOutput()
                )
            );
        }
        if (! $input->getOption('no-cleanup')) {
            $p = $this->getProcess(array('unlink', $filepath));
            if ($p->run()) {
                throw new RuntimeException(
                    sprintf(
                        'I cannot cleanup: failed to delete file "%s": %s.',
                        $filepath,
                        $p->getErrorOutput()
                    )
                );
            }
        }

        $output->writeLn(
            sprintf(
                'I have set debconf database entries from the file "%s".',
                $filepath
            )
        );

        $this->reportChange($output);
        return 0;
    }

    private function getProcess($arguments)
    {
        return $this
            ->processBuilder
            ->setArguments($arguments)
            ->getProcess()
        ;
    }
}
