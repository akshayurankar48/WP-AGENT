<?php

namespace WPB {
    /**
     * Plugin_Loader
     *
     * @since X.X.X
     */
    class Plugin_Loader
    {
        /**
         * Initiator
         *
         * @since X.X.X
         * @return object initialized object of class.
         */
        public static function get_instance()
        {
        }
        /**
         * Autoload classes.
         *
         * @param string $class class name.
         */
        public function autoload($class)
        {
        }
        /**
         * Constructor
         *
         * @since X.X.X
         */
        public function __construct()
        {
        }
        /**
         * Load Plugin Text Domain.
         * This will load the translation textdomain depending on the file priorities.
         *      1. Global Languages /wp-content/languages/wp-plugin-base/ folder
         *      2. Local dorectory /wp-content/plugins/wp-plugin-base/languages/ folder
         *
         * @since X.X.X
         * @return void
         */
        public function load_textdomain()
        {
        }
    }
}
namespace {
    /**
     * Plugin Name: Plugin starter code
     * Description: It is a started and simple which helps you to speedup the process.
     * Author: Sandesh
     * Version: 0.0.1
     * License: GPL v2
     * Text Domain: wp-plugin-base
     *
     * @package {{package}}
     */
    /**
     * Set constants
     */
    \define('WPB_FILE', __FILE__);
    \define('WPB_BASE', \plugin_basename(\WPB_FILE));
    \define('WPB_DIR', \plugin_dir_path(\WPB_FILE));
    \define('WPB_URL', \plugins_url('/', \WPB_FILE));
    \define('WPB_VER', '0.0.1');
}