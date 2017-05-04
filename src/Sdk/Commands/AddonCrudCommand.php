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
use Tygh\Sdk\Commands\Traits\CodeGenerator;
use Tygh\Sdk\Commands\Traits\NotationTrait;
use Tygh\Sdk\Commands\Traits\ValidateCartPathTrait;
use Tygh\Sdk\Entities\Addon;

class AddonCrudCommand extends Command
{
    use ValidateCartPathTrait;
    use NotationTrait;
    use CodeGenerator;

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
            )->addOption('db-table-main',
                'dbt-main',
                InputOption::VALUE_REQUIRED,
                'Create entity database table'
            )->addOption('db-table-description',
                'dbt-descr',
                InputOption::VALUE_REQUIRED,
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
            $this->createPart($addon, 'manifest');
            $this->createPart($addon, 'lang_vars');
            $this->createPart($addon, 'menu_schema');
        }
    }

    protected function saveConfig(InputInterface $input)
    {
        $this->args['cart_directory'] = $this->getCartPath();
        $this->args['addon_name'] = $input->getArgument('name');
        $this->args['entity_name'] = $input->getArgument('entity-name');

        $this->options['db_tables'] = $this->getDatabaseTables($input, $this->args['entity_name']);

        $result = true;
        foreach ($this->args as $arg) {
            if (empty($arg)) {
                $result = false;
                break;
            }
        }

        return $result;
    }

    protected function getDatabaseTables(InputInterface $input, $entity_name)
    {
        $tables = array(
            $entity_name . 's' => $input->getOption('db-table-main'),
            $entity_name . '_descriptions' => $input->getOption('db-table-description')
        );

        foreach ($tables as $table_name => &$table) {
            $table = explode(',', $table);

            if (!in_array($entity_name . '_id', $table)) {
                $table = array_merge(array(
                    $entity_name . '_id'
                ), $table);
            }

            if ($table_name == $entity_name . '_descriptions' && !in_array('lang_code', $table)) {
                $table = array_merge($table, array(
                    'lang_code'
                ));
            }

            $table = array_filter($table);
        } unset($table);

        return $tables;
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
                'Model.php' => $addon->getModelPath($this->convertNotation($this->args['entity_name'], 'underscore', 'camel'))
            );
        } else if ($type == 'lang_vars') {
            $paths = array(
                'LangVars.po' => $addon->getLangVarsPath('en')
            );

        } else if ($type == 'manifest') {
            $paths = array(
                'Manifest.xml' => $addon->getAppPath() . '/addon.xml'
            );

        } else if ($type == 'controller') {
            $paths = array(
                'Controller.php' => $addon->getControllerPath($this->args['entity_name'] . 's')
            );

        } else if ($type == 'view') {
            $paths = array(
                'EntityList.tpl' => $addon->getViewPath("/components/list.tpl"),
                'SearchForm.tpl' => $addon->getViewPath("/components/search_form.tpl"),
                'UpdateView.tpl' => $addon->getViewPath("/views/{$this->args['entity_name']}s/update.tpl"),
                'ManageView.tpl' => $addon->getViewPath("/views/{$this->args['entity_name']}s/manage.tpl")
            );

        } else if ($type == "menu_schema") {
            $paths = array(
                'MenuSchema.php' => $addon->getAppPath() . '/schemas/menu/menu.post.php'
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
        // TODO: Direct smarty execution.
        // It would make var replacements unnecessary by passing variables directly.
        // CodeGenerator trait would also be redundant.
        $code = stream_get_contents($this->getCrudComponent($name));

        if (!empty($code)) {
            static $vars = array();
            if (empty($vars)) {
                $vars = array(
                    // NOTE: CodeGenerator vars should always be first in this array.
                    '%generator_xml_queries%' => $this->generateCode('xml_queries', array($this->options['db_tables'])),
                    '%generator_tpl_field_list_head%' => $this->generateCode('tpl_field_list', array($this->options['db_tables'], 'head', $this->args['entity_name'])),
                    '%generator_tpl_field_list_body%' => $this->generateCode('tpl_field_list', array($this->options['db_tables'], 'body', $this->args['entity_name'])),
                    '%generator_tpl_field_updates%' => $this->generateCode('tpl_field_updates', array($this->options['db_tables'], $this->args['entity_name'])),

                    '%EntityName%' => $this->convertNotation($this->args['entity_name'], 'underscore', 'camel'),
                    '%entity_name%' => $this->args['entity_name'],
                    '%AddonName%' => $this->convertNotation($this->args['addon_name'], 'underscore', 'camel'),
                    '%addon_name%' => $this->args['addon_name'],
                );
            }

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