<?php

declare(strict_types=1);

namespace Ueberbit\DrushGdprDump\Drush\Commands;

use DrupalFinder\DrupalFinderComposerRuntime;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\SiteAlias\ProcessManager;
use Drush\Sql\SqlBase;
use Drush\Sql\SqlMysql;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
  name: self::NAME,
  description: 'Exports the Drupal DB as SQL using gdpr-dump.'
)]
#[CLI\Bootstrap(DrupalBootLevels::CONFIGURATION)]
#[CLI\OptionsetSql]
final class GdprDumpDrushCommand extends Command {

  use AutowireTrait;

  public const string NAME = 'gdpr:dump';

  private DrupalFinderComposerRuntime $drupalFinder;

  public function __construct(
    protected readonly ProcessManager $processManager,
  ) {
    parent::__construct();
    $this->drupalFinder = new DrupalFinderComposerRuntime();
  }

  protected function configure() {
    $this
      ->addOption('config', NULL, InputOption::VALUE_REQUIRED, 'Path to the gdpr-dump YAML configuration file. Default: <composer-root>/gdpr-config.yaml or <composer-root>/drush/gdpr-config.yaml')
      ->addOption(name: 'result-file', mode: InputOption::VALUE_OPTIONAL, description: 'Save to a file. The file should be relative to Drupal root. If --result-file is provided with the value \'auto\', a date-based filename will be created under ~/drush-backups directory.')
      ->addOption(name: 'gzip', description: 'Compress the dump using the gzip program which must be in your <info>$PATH</info>.');
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $options = $input->getOptions();
    $composerRoot = $this->drupalFinder->getComposerRoot();

    if (($options['config'] ?? NULL) === NULL) {
      if (file_exists($composerRoot . '/gdpr-config.yaml')) {
        $options['config'] = $composerRoot . '/gdpr-config.yaml';
      }
      elseif (file_exists($composerRoot . '/gdpr-config.yaml')) {
        $options['config'] = $composerRoot . '/drush/gdpr-config.yaml';
      }
    }

    $binary = $composerRoot . '/vendor/bin/gdpr-dump';
    if (!is_file($binary)) {
      throw new \RuntimeException('gdpr-dump binary not found at: ' . $binary);
    }
    if (!is_file($options['config'])) {
      throw new \RuntimeException('Config file not found in ' . $composerRoot . ' or ' . $composerRoot . '/drush');
    }
    $sql = SqlBase::create($input->getOptions());
    if (!($sql instanceof SqlMysql)) {
      throw new \RuntimeException('Database driver not supported.');
    }

    $foo = $sql->getDbSpec();
    $parameters = $this->convertCommandLineParameters(explode(' ', $sql->creds(FALSE)));

    $cmd = [$binary];
    $cmd = array_merge($cmd, $parameters);

    if (!empty($options['dry-run'])) {
      $cmd[] = '--dry-run';
    }

    $cmd[] = (string) $options['config'];

    $process = $this->processManager->process($cmd);

    $resultFile = $sql->dumpFile($options['result-file']);
    if ($resultFile) {
      $handle = fopen($resultFile, 'w');
      $process->mustRun(function (string $type, string $buffer) use ($handle, $output): void {
        if ($type === Process::OUT) {
          fwrite($handle, $buffer);
        }
        else {
          $output->write($buffer);
        }
      });
      fclose($handle);

      if ($input->getOption('gzip')) {
        $process = $this->processManager->process(['gzip', $resultFile]);
        $process->mustRun($process->showRealtime());
        $resultFile = $resultFile . '.gz';
      }

      $output->writeln(sprintf('Database dump saved to %s', $resultFile));
    }
    else {
      $process->mustRun($process->showRealtime());
    }

    return 0;
  }

  protected function convertCommandLineParameters(array $parameters): array {
    $replace = [
      '--ssl-ca=' => '--ca=',
      '--ssl-capath=' => '--capath=',
      '--ssl-cert=' => '--cert=',
      '--ssl-cipher=' => '--cipher=',
      '--ssl-key=' => '--key=',
    ];
    $sslEnabled = FALSE;
    $parameters = array_map(function ($argument) use ($replace, &$sslEnabled) {
      $result = str_replace(array_keys($replace), array_values($replace), $argument, $count);
      if ($count > 0) {
        $sslEnabled = TRUE;
      }
      return $result;
    }, $parameters);

    if ($sslEnabled) {
      $parameters[] = '--ssl';
    }

    return $parameters;
  }

}
