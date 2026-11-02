<?php

namespace Shopmonkeynl\ShopmonkeyCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Shopmonkeynl\ShopmonkeyCli\Services\InputOutput;
use Shopmonkeynl\ShopmonkeyCli\Services\TemplateCheckService;

class Check extends Command
{
    /**
     * The name of the command (the part after "bin/shopmonkey").
     *
     * @var string
     */
    protected static $defaultName = 'check';

    /**
     * The command description shown when running "bin/shopmonkey list".
     *
     * @var string
     */
    protected static $defaultDescription = 'Check templates for syntax errors, missing includes and size problems.';

    protected function configure(): void
    {
        $this->addArgument(
            'files',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'Template files to check (default: every template in ./theme).'
        );
        $this->addOption('short', null, InputOption::VALUE_NONE, 'Only print the problems, no summary (used by the watcher).');
    }

    /**
     * Execute the command
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int 0 if everything went fine, or an exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new InputOutput($input, $output);
        $checker  = new TemplateCheckService();
        $themeDir = getcwd() . '/theme';

        $files = $this->resolveFiles($input->getArgument('files'), $themeDir);
        if (!$files) {
            $io->warning('No templates found to check. Run "shopmonkey pull" first, or pass file paths.');
            return Command::SUCCESS;
        }

        $errors   = 0;
        $warnings = 0;

        foreach ($files as $key => $path) {
            $issues = $checker->checkFile($path, $key);

            foreach ($issues as $issue) {
                $isError = $issue['level'] === TemplateCheckService::ERROR;
                $isError ? $errors++ : $warnings++;

                $io->writeln(sprintf(
                    '  %s <comment>%s%s</comment> %s',
                    $isError ? '<error>✖</error>' : '<comment>!</comment>',
                    $key,
                    $issue['line'] ? ':' . $issue['line'] : '',
                    $issue['message']
                ));
            }
        }

        if ($input->getOption('short')) {
            return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        $io->newLine();
        $summary = sprintf('%d template(s) checked: %d error(s), %d warning(s).', count($files), $errors, $warnings);

        if ($errors > 0) {
            $io->wrong($summary);
            return Command::FAILURE;
        }

        $io->right($summary);

        return Command::SUCCESS;
    }

    /**
     * Turn the given paths (or the whole theme) into key => absolute path.
     */
    private function resolveFiles(array $arguments, $themeDir)
    {
        $files = [];

        if ($arguments) {
            foreach ($arguments as $argument) {
                $path = is_file($argument) ? realpath($argument) : realpath($themeDir . '/' . ltrim($argument, '/'));
                if ($path === false || !is_file($path)) {
                    continue;
                }
                $files[basename(dirname($path)) . '/' . basename($path)] = $path;
            }

            return $files;
        }

        foreach (['layouts', 'pages', 'snippets'] as $directory) {
            if (!is_dir($themeDir . '/' . $directory)) {
                continue;
            }

            foreach (scandir($themeDir . '/' . $directory) as $file) {
                if (substr($file, -5) === '.rain') {
                    $files[$directory . '/' . $file] = $themeDir . '/' . $directory . '/' . $file;
                }
            }
        }

        return $files;
    }
}
