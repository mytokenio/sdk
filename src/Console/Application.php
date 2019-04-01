<?php

namespace Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends SymfonyApplication
{
    protected $command;

    /**
     * The output from the previous command.
     *
     * @var \Symfony\Component\Console\Output\BufferedOutput
     */
    protected $lastOutput;

    /**
     * Create a new Artisan console application.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('MyToken Console', '1.0.0');
        $this->setAutoExit(false);
        $this->setCatchExceptions(false);
    }

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output()
    {
        return $this->lastOutput ? $this->lastOutput->fetch() : '';
    }

    /**
     * @throws \ReflectionException
     */
    public function bootstrap()
    {
        //$this->add(new HelloCommand());
        $this->registerCommands();
    }

    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        try {
            $this->bootstrap();

            return parent::run($input, $output);
        } catch (\Exception $e) {
            $this->renderException($e, $output);

            \Log::getLogger('command')->error($e->getMessage(), ['e' => $e]);

            return 1;
        } catch (\Throwable $e) {
            $e = new \ErrorException(
                $e->getMessage(),
                $e->getCode(),
                E_ERROR,
                $e->getFile(),
                $e->getLine()
            );

            \Log::getLogger('command')->error($e->getMessage(), ['e' => $e]);
            $this->renderException($e, $output);

            return 1;
        }
    }

    /**
     * Add a command to the console.
     *
     * @param  \Symfony\Component\Console\Command\Command $command
     * @return \Symfony\Component\Console\Command\Command
     */
    public function add(SymfonyCommand $command)
    {
        return parent::add($command);
    }

    /**
     * Get the default input definitions for the applications.
     *
     * This is used to add the --env option to every available command.
     *
     * @return \Symfony\Component\Console\Input\InputDefinition
     */
    protected function getDefaultInputDefinition()
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption($this->getEnvironmentOption());

        return $definition;
    }

    /**
     * Get the global environment option for the definition.
     *
     * @return \Symfony\Component\Console\Input\InputOption
     */
    protected function getEnvironmentOption()
    {
        $message = 'The environment the command should run under.';

        return new InputOption('--env', null, InputOption::VALUE_OPTIONAL, $message);
    }


    /**
     * Finds and registers Commands.
     *
     * @param null $dir
     * @throws \ReflectionException
     */
    public function registerCommands($dir = null)
    {
        if (!defined('APPLICATION_PATH')) {
            return;
        }

        if (empty($dir)) {
            $dir = APPLICATION_PATH . '/application/commands';
        }
        if (!is_dir($dir)) {
            return;
        }


        $subDirs = glob($dir . '/*', GLOB_ONLYDIR);
        foreach ($subDirs as $subDir) {
            $this->registerCommands($subDir);
        }

        $files = glob($dir . '/*Command.php');
        foreach ($files as $file) {
            $this->registerCommand($file);
        }
    }

    /**
     * @param $file
     * @throws \ReflectionException
     */
    private function registerCommand($file)
    {
        $class = '\\Commands\\';

        $dir = dirname($file);
        $prefixPath = APPLICATION_PATH . '/application/commands';

        $dir = str_replace([$prefixPath . '/', $prefixPath], '', $dir);

        if (!empty($dir)) {
            $array = explode('/', $dir);
            foreach ($array as $item) {
                $class .= $item . '\\';
            }
        }
        $class .= basename($file, '.php');
        $r = new \ReflectionClass($class);
        if ($r->isSubclassOf('Symfony\\Component\\Console\\Command\\Command') && !$r->isAbstract() && !$r->getConstructor()->getNumberOfRequiredParameters()) {
            $this->add($r->newInstance());
        }
    }
}
