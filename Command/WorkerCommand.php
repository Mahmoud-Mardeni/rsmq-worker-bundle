<?php

namespace Redis\RSMQWorkerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class WorkerCommand extends ContainerAwareCommand
{

    private $params;

    protected function configure()
    {
        $this
            ->setName('rsmq:worker')
            ->addArgument('arguments', InputArgument::OPTIONAL)
            ->addArgument('recompile', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->params = $this->getContainer()->getParameter("rsmq_worker_config");

        $name = $input->getArgument('arguments');

        if ($name)
        {

            switch ($name)
            {
                case 'start':
                    $this->actionStart();
                    break;

                case 'stop':
                    $this->actionStop();
                    break;

                case 'restart':
                    $recompile = $input->getArgument('recompile');
                    $this->actionRestart($recompile);
                    break;

                default:
                    $response = $this->render_usage();
                    $output->writeln($response);
            }

        } else {

            $response = $this->render_usage();

            $output->writeln($response);

        }
    }

    protected function render_usage()
    {

        $text = <<<EOD
                
    USAGE
      bin/console {$this->getName()} [action] [parameter]

    DESCRIPTION
      This command provides support for managing RSMQ worker node app.

    EXAMPLES
     * bin/console {$this->getName()} start
       Start RSMQ worker server, check is started

     * bin/console {$this->getName()} stop
       Stop RSMQ worker server

     * bin/console {$this->getName()} restart
       Restart RSMQ worker server
                        
EOD;

        return $text;


    }

    protected function actionStart()
    {
        if ($this->isInProgress())
        {
            printf("Server " . $this->params['process_name'] . " already started\n");
            return true;
        }

        $this->compileServer();
        $this->compileClient();
        printf("Starting server\n");

        $serverPath = implode(DIRECTORY_SEPARATOR, array(
            __DIR__,
            '..',
            'Library',
            'js',
            'server.js'
        ));

        $runtime_command = implode(' ', array(
            'pm2',
            'start',
            $serverPath,
            '--name',
            '"' . $this->params['process_name'] . '"',
            '--log-date-format "YYYY-MM-DD HH:mm"',
        ));


        printf("Starting command:\n$runtime_command\n");

        $process = new Process($runtime_command);
        $process->start();
        while ($process->isRunning()) ;
        if ($this->isInProgress()) {
            printf("RSMQ worker client successfully started on:\n");
            printf("\t\thost: " . $this->params['host'] . "\n");
            printf("\t\tport: " . $this->params['port'] . "\n");
        } else {
            printf("Error: RSMQ worker client can not start. Please check app logs.\n");
        }
    }

    protected function compileClient()
    {

        printf("Compile client\n");

        $client_js_config = implode(DIRECTORY_SEPARATOR, array(
            __DIR__,
            '..',
            'Library',
            'js',
            'client.template.js.php'
        ));

        if (file_exists($client_js_config))
        {
            echo ('Configuration file found "client.template.js"') . PHP_EOL;
        }

        ob_start();

        $configs = $this->params;

        include($client_js_config);

        $js = ob_get_clean();

        $result = file_put_contents(__DIR__ . '/../Resources/public/js/client.js', $js);

        $command = $this->getApplication()->find('asset:install');

        $arguments = array(
            'command' => 'asset:install',
            '--symlink' => true,
        );

        $input = new ArrayInput($arguments);
        $command->run($input, new NullOutput());

        return $result;
    }

    protected function actionStop()
    {
        $processName = $this->params['process_name'];

        if ($this->isInProgress()) {
            printf("Stopping " . $processName . " server...\n");
            $runtime_command = implode(' ', array(
                'pm2',
                'stop',
                $processName,
            ));
            $process = new Process($runtime_command);
            $process->start();

            while ($process->isRunning()) ;

            if (!$this->isInProgress())
            {
                printf("Server " . $processName . " successfully stopped on port: %s \n", $this->params['port']);
                return true;
            }

            printf("Stopping " . $processName . " server error\n");
            return false;
        }

        printf("Server " . $processName . " is not running\n");
        return true;
    }

    protected function actionRestart($recompile)
    {
        $processName = $this->params['process_name'];
        printf("Restarting " . $processName . " server...\n");

        if ($recompile) {
            $this->compileServer();
            $this->compileClient();
        }

        $runtime_command = implode(' ', array(
            'pm2',
            'restart',
            $processName,
        ));

        $process = new Process($runtime_command);
        $process->start();

        while ($process->isRunning()) ;

        if ($this->isInProgress())
        {
            printf('Server ' . $processName . ' restarted successfully.');
        } else
        {
            printf('Can not restart server ' . $processName . '.');
        }
    }

    protected function compileServer()
    {

        printf("Compile server\n");

        $server_js_config = implode(DIRECTORY_SEPARATOR, array(
            __DIR__,
            '..',
            'Library',
            'js',
            'server.config.js.php'
        ));

        if (file_exists($server_js_config))
        {
            echo ('Configuration file found "server.config.js.php"') . PHP_EOL;
        }

        ob_start();

        $configs = $this->params;

        include($server_js_config);

        $js = ob_get_clean();

        return file_put_contents(__DIR__ . '/../Library/js/server.config.js', $js);
    }

    /**
     * @return bool
     */
    protected function isInProgress()
    {
        $runtime_command = implode(' ', array(
            'pm2',
            'jlist',
        ));
        $process = new Process($runtime_command);
        $process->start();

        while ($process->isRunning()) ;

        $nodeApps = json_decode($process->getOutput());
        if($nodeApps) {
            foreach ($nodeApps as $app) {
                if ($app->name == $this->params['process_name'] && $app->pm2_env->status == 'online') {
                    return true;
                }
            }
        }
        return false;
    }


}
