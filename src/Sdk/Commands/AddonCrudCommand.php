<?php

namespace Tygh\Sdk\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Tygh\Sdk\Commands\Traits\NotationTrait;
use Tygh\Sdk\Commands\Traits\ValidateCartPathTrait;
use Tygh\Sdk\Entities\Addon;

class AddonCrudCommand extends Command
{
    use ValidateCartPathTrait;
    use NotationTrait;

    protected $input;
    protected $output;

    protected $args = array();
    protected $options = array();

    public function __set($name, $value)
    {
        $this->config[ $name ] = $value;
    }

    public function __get($name)
    {
        if (isset($this->config[ $name ])) {
            return $this->config[ $name ];
        }

        return null;
    }

    public function getInput()
    {
        $input = null;

        if (is_a($this->input, 'Symfony\Component\Console\Input\InputInterface')) {
            $input = & $this->input;
        }

        return $input;
    }

    public function getOutput()
    {
        $output = null;

        if (is_a($this->output, 'Symfony\Component\Console\Output\OutputInterface')) {
            $output = & $this->output;
        }

        return $output;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('addon:crud')
            ->setDescription(
                'Creates a complete working crud for a specified addon entity.'
            )
            ->addArgument('name',
                InputArgument::REQUIRED,
                'Add-on ID (name)'
            )
            ->addArgument('cart-directory',
                InputArgument::REQUIRED,
                'Path to CS-Cart installation directory'
            )
            ->addArgument('entity-name',
                InputArgument::REQUIRED,
                'CRUD entity name'
            )->addOption('db-entity',
                'db_entity',
                InputOption::VALUE_NONE,
                'Create entity database table'
            )->addOption('db-descr',
                'db_descr',
                InputOption::VALUE_NONE,
                'Create description database table'
            );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = & $input;
        $this->output = & $output;

        $saved = $this->saveConfig($input);
        if ($saved) {
            $addon = new Addon($this->args['addon_name'], $this->args['cart_directory']);

            $this->createPart($addon, 'model');
            $this->createPart($addon, 'controller');
            $this->createPart($addon, 'view');
        }
    }

    protected function saveConfig(InputInterface $input)
    {
        $this->args['cart_directory'] = $this->getCartPath();
        $this->args['addon_name'] = $input->getArgument('name');
        $this->args['entity_name'] = $input->getArgument('entity-name');

        $this->options['db_entity'] = $input->getOption('db-entity');
        $this->options['db_descr'] = $input->getOption('db-descr');

        $result = true;
        foreach ($this->args as $arg) {
            if (empty($arg)) {
                $result = false;
                break;
            }
        }

        return $result;
    }

    protected function getCartPath()
    {
        $input = $this->getInput();
        $output = $this->getOutput();

        $dir = '';

        if ($input && $output) {
            $dir = rtrim(realpath($input->getArgument('cart-directory')), '\\/') . '/';
            $this->validateCartPath($dir, $input, $output);
        }

        return $dir;
    }

    protected function createPart(Addon $addon, $type)
    {
        if (empty($addon)) {
            return $addon;
        }

        $paths = '';

        if ($type == 'model') {
            $paths = array(
                'ModelClass.php' => $addon->getModelPath($this->args['entity_name'])
            );

        } else if ($type == 'controller') {
            $paths = array(
                'Controller.php' => $addon->getControllerPath($this->args['addon_name'])
            );

        } else if ($type == 'view') {
            $paths = array(
                'EntityList.tpl' => $addon->getViewPath("/components/list.tpl"),
                'SearchForm.tpl' => $addon->getViewPath("/components/search_form.tpl"),
                'UpdateView.tpl' => $addon->getViewPath("/views/{$this->args['addon_name']}/update.tpl"),
                'ManageView.tpl' => $addon->getViewPath("/views/{$this->args['addon_name']}/manage.tpl")
            );
        }

        if (!empty($paths)) {
            foreach ($paths as $sample => $path) {
                $fp = static::smartFileOpen($path);

                if (!empty($fp)) {
                    $sample = $this->getSample($sample);
                    fwrite($fp, $sample);
                    fclose($fp);
                }
            }
        }

        return true;
    }

    protected function getSample($name)
    {
        $code = stream_get_contents($this->getCrudComponent($name));

        if (!empty($code)) {
            $vars = array(
                '%entity_name_cml%' => $this->args['entity_name'],
                '%entity_name_und%' => $this->convertNotation($this->args['entity_name'], 'camel', 'underscore'),
                '%addon_name%' => $this->args['addon_name']
            );

            foreach ($vars as $var => $value) {
                $code = str_replace($var, $value, $code);
            }
        }

        return !empty($code) ? $code : '';
    }

    protected function getCrudComponent($name)
    {
        $fs = new Filesystem();

        $hypo_filenames = array(
            $name,
            $name . '.txt',
            $name . '.php.txt',
            $name . '.tpl.txt'
        );

        $component = '';

        foreach ($hypo_filenames as $name) {
            $path = dirname(__FILE__) . '/CrudComponents/' . $name;

            if ($fs->exists($path)) {
                $component = fopen($path, 'r');
                break;
            }
        }

        return $component;
    }

    public static function smartFileOpen($path)
    {
        if(!file_exists(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        return fopen($path, 'w');
    }
}