<?php

namespace Tygh\Sdk\Commands\Traits;

use Tygh\Sdk\Entities\Addon;

trait CodeGenerator
{
    protected function generateCode($function_name, $params)
    {
        $string = '';

        if (is_callable(array($this, 'generator_' . $function_name))) {
            $string = call_user_func_array(array($this, 'generator_' . $function_name), $params);
        }

        return $string;
    }

    private function append(&$doc, $string, $tab_level = 1)
    {
        $tabs = "";
        for ($i = 0; $i < $tab_level; $i++) {
            $tabs .= "\t";
        }

        $doc .= $tabs . $string . "\r\n";
    }

    private function generator_xml_queries($tables)
    {
        $result = '';
        if (empty($tables)) {
            return $result;
        }

        $this->append($result, "<queries>", 1);
        foreach ($tables as $table_name => $columns) {
            $this->append($result, "<item for=\"install\">", 2);
            $this->append($result, "CREATE TABLE `?:{$table_name}` (", 3);

            foreach ($columns as $column) {
                $this->append($result, "`{$column}` <type_placeholder>,", 4);
            }

            $this->append($result, "PRIMARY KEY (`{$this->args['entity_name']}_id`)", 4);
            $this->append($result, ") ENGINE=MyISAM DEFAULT CHARSET=utf8;", 3);
            $this->append($result, "</item>", 2);
        }

        $this->append($result, "</queries>", 1);

        return $result;
    }

    private function generator_tpl_field_list($tables, $table_section = 'head')
    {
        $result = '';

        $fields = array();
        foreach ($tables as $table) {
            foreach ($table as $field) {
                $fields[ $field ] = true;
            }
        }

        $fields = array_keys($fields);

        if ($table_section == 'head') {
            foreach ($fields as $field) {
                $this->append($result, '<th width="10%" class="left">', 1);
                $this->append($result, '<a class="cm-ajax" href="{"`$c_url`&sort_by=' . $field . '&sort_order=`$search.sort_order_rev`"|fn_url}" data-ca-target-id="pagination_contents">', 2);
                $this->append($result, '{__("' . $field .'")}{if $search.sort_by == "' . $field . '"}{$c_icon nofilter}{else}{$c_dummy nofilter}{/if}', 3);
                $this->append($result, '</a>', 2);
                $this->append($result, '</th>', 1);
            }

        } else if ($table_section == 'body') {
            foreach ($fields as $field) {
                $this->append($result, '<td width="10%">', 1);
                $this->append($result, '<a href="{$_update_href}" id="$%entity_name%_' . $field . '_{$%entity_name%->%entity_name%_id}">', 2);
                $this->append($result, '{$%entity_name%->' . $field . '}', 3);
                $this->append($result, '</a>', 2);
                $this->append($result, '</td>', 1);
            }
        }

        return $result;
    }
}