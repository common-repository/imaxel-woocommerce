<?php

namespace Printspot\ICP;

class View
{
    public static function addScript($path, $data = [])
    {
        list($plugin, $path) = explode(':', $path);

        $handle = sanitize_title($path);

        wp_register_script($handle, plugins_url('/' . $plugin . '/' . $path),[],\WC_Imaxel::plugin_version());

        if (!empty($data)) {
            foreach ($data as $key => $value) {
                wp_localize_script($handle, $key, $value);
            }
        }

        wp_enqueue_script($handle, '', [], \WC_Imaxel::plugin_version(), TRUE);

    }


    public static function addStyle($path)
    {
        list($plugin, $path) = explode(':', $path);

        $handle = sanitize_title($path);

        wp_enqueue_style($handle, plugins_url('/' . $plugin . '/' . $path));
    }

    public static function renderLoad($view, $data = [])
    {
        ob_start();
        self::load($view, $data);
        return ob_get_clean();
    }

    public static function load($view, $data = [])
    {
        list($plugin, $view) = explode(':', $view);
        extract($data);

        include WP_PLUGIN_DIR . '/' . $plugin . '/templates/' . $view;
    }
}
