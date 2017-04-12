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

    protected $config = array();

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

        $this->saveConfig($input);

        $addon_app_path = $this->getAddonPath($this->cart_directory, $this->addon_name, '/app/');
        $addon_bck_path = $this->getAddonPath($this->cart_directory, $this->addon_name, '/backend/templates/');

        $this->createModel($addon_app_path);
    }

    protected function saveConfig(InputInterface $input)
    {
        $this->cart_directory = $this->getCartPath();
        $this->addon_name = $input->getArgument('name');
        $this->entity_name = $input->getArgument('entity-name');
        $this->db_entity = $input->getOption('db-entity');
        $this->db_descr = $input->getOption('db-descr');
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

    protected function getAddonPath($cart_path, $addon_name, $inter = '', $create = true)
    {
        $path = '';

        if (!empty($cart_path) && !empty($addon_name)) {
            $fs = new Filesystem();

            $hypo_dir = $cart_path . $inter . '/addons/' . $addon_name;

            if (!$fs->exists($hypo_dir)) {
                if ($create === true) {
                    $fs->mkdir($hypo_dir);
                    $path = $hypo_dir;
                }

            } else {
                $path = $hypo_dir;
            }
        }

        return $path;
    }

    protected function createModel($path, $namespace_dirs = true)
    {
        $fs = new Filesystem();
        $models_dir = $path;

        if (!empty($models_dir) && $namespace_dirs === true) {
            $models_dir = $path . '/Tygh/Models/';
            if (!$fs->exists($models_dir)) {
                $fs->mkdir($models_dir);
            }
        }

        if (!empty($models_dir)) {
            $fp = fopen($models_dir . $this->entity_name . '.php', 'w');
            $get_model_code = $this->getModelClass();
            fwrite($fp, $get_model_code);
            fclose($fp);
        }
    }

    protected function getModelClass()
    {
        $input = $this->getInput();
        $entity_name = $this->entity_name;

        $code = '';

        if ($input && $entity_name) {
            $code = stream_get_contents($this->getCodeComponent('ModelClass.php'));

            if ($code) {
                $vars = array(
                    '%entity_name_cml%' => $entity_name,
                    '%entity_name_und%' => $this->convertNotation($entity_name, 'camel', 'underscore')
                );

                foreach ($vars as $var => $value) {
                    $code = str_replace($var, $value, $code);
                }
            }
        }

        return $code;
    }

    protected function getCodeComponent($name)
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
}