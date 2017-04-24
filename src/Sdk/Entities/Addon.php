<?php
namespace Tygh\Sdk\Entities;

class Addon
{
    protected $id;

    protected $root_directory_path;

    protected $theme_name;

    public function __construct($id, $root_directory_path, $theme_name = '')
    {
        $this->id = $id;
        $this->root_directory_path = $root_directory_path;
        $this->theme_name = $theme_name;
    }

    /**
     * @return mixed
     */
    public function getXmlSchemePath()
    {
        return "{$this->getRootDirectoryPath()}/app/addons/{$this->id}/addon.xml";
    }

    /**
     * @return mixed
     */
    public function getThemeName()
    {
        return !empty($this->theme_name) ? $this->theme_name : 'responsive';
    }

    /**
     * @return mixed
     */
    public function getRootDirectoryPath()
    {
        return $this->root_directory_path;
    }

    /**
     * @return mixed
     */
    public function getAppPath()
    {
        return "{$this->getRootDirectoryPath()}/app/addons/{$this->id}/";
    }

    /**
     * @param string $view_subpath
     * @param string $area
     * @return mixed
     */
    public function getViewPath($view_subpath, $area = 'A')
    {
        $view_subpath = !empty($view_subpath) ? $view_subpath : '';
        $area = !empty($area) && $area == 'C' ? "/themes/{$this->getThemeName()}/templates/" : '/backend/templates/';

        return "{$this->getRootDirectoryPath()}/design/{$area}/addons/{$this->id}/{$view_subpath}";
    }

    /**
     * @param $model_name
     * @return mixed
     */
    public function getModelPath($model_name = '')
    {
        $model_name = !empty($model_name) ? $model_name . '.php' : '';

        return "{$this->getAppPath()}/Models/Tygh/{$model_name}";
    }

    public function getLangVarsPath($lang)
    {
        $path = "{$this->getRootDirectoryPath()}/var/langs/{$lang}/addons/{$this->id}.po";

        return $path;
    }

    /**
     * @param string $controller_name
     * @param string $area
     * @return mixed
     */
    public function getControllerPath($controller_name = '', $area = 'A')
    {
        $controller_name = !empty($controller_name) ? $controller_name . '.php' : '';
        $area = !empty($area) && $area == 'C' ? 'frontend' : 'backend';

        return "{$this->getAppPath()}/controllers/{$area}/{$controller_name}";
    }

    public function getFilesGlobMasks()
    {
        $addon_files_glob_masks = [
            // General files
            "app/addons/{$this->id}",
            "var/langs/**/addons/{$this->id}.po",
            "js/addons/{$this->id}",

            // Backend templates and assets
            "design/backend/css/addons/{$this->id}",
            "design/backend/mail/templates/addons/{$this->id}",
            "design/backend/media/images/addons/{$this->id}",
            "design/backend/media/fonts/addons/{$this->id}",
            "design/backend/templates/addons/{$this->id}",

            // Frontend templates and assets
            "design/themes/**/css/addons/{$this->id}",
            "design/themes/**/templates/addons/{$this->id}",
            "design/themes/**/layouts/addons/{$this->id}",
            "design/themes/**/mail/templates/addons/{$this->id}",
            "design/themes/**/media/images/addons/{$this->id}",
            "design/themes/**/media/images/logos/addons/{$this->id}", 
            
            "var/themes_repository/**/css/addons/{$this->id}",
            "var/themes_repository/**/templates/addons/{$this->id}",
            "var/themes_repository/**/layouts/addons/{$this->id}",
            "var/themes_repository/**/mail/templates/addons/{$this->id}",
            "var/themes_repository/**/media/images/addons/{$this->id}",
            "var/themes_repository/**/media/images/logos/addons/{$this->id}",
        ];

        if (file_exists($this->getXmlSchemePath())) {
            $addon_xml_manifest = simplexml_load_file($this->getXmlSchemePath());

            if (!empty($addon_xml_manifest->files->file)) {
                foreach ($addon_xml_manifest->files->file as $additional_file) {
                    $addon_files_glob_masks[] = $additional_file;
                }
            }
        }

        return $addon_files_glob_masks;
    }

    public function matchFilesAgainstGlobMasks($files_glob_masks, $at_directory)
    {
        $glob_matches = [];
        foreach ($files_glob_masks as $glob_mask) {
            $glob_mask = $at_directory . $glob_mask;

            foreach (glob($glob_mask) as $glob_mask_match) {
                $glob_matches[] = substr_replace($glob_mask_match, '', 0, mb_strlen($at_directory));
            }
        }

        return $glob_matches;
    }
}
