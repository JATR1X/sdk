<?php

namespace Tygh\Sdk\Commands\Traits;

trait NotationTrait
{
    protected function convertNotation($string, $from = 'underscore', $to = 'camel')
    {
        $function_name = $from . 'To' . ucfirst($to);
        if (is_callable(array($this, $function_name))) {
            $string = $this->$function_name($string);
        }

        return $string;
    }

    private function underscoreToCamel($string, $capitalize_first_char = true)
    {
        $str = str_replace('_', '', ucwords($string, '_'));

        if (!$capitalize_first_char) {
            $str = lcfirst($str);
        }

        return $str;
    }

    private function camelToUnderscore($string)
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $string, $matches);

        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }

        return implode('_', $ret);
    }
}