<?php
if (!defined('ABSPATH')) exit;

class WCSOM_GitHub_Updater {
    private $file;
    private $username;
    private $repository;
    private $basename;
    private $github_response;

    public function __construct($file, $username, $repository) {
        $this->file = $file;
        $this->username = $username;
        $this->repository = $repository;
        $this->basename = plugin_basename($this->file);
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
    }

    private function get_repository_info() {
        if (is_null($this->github_response)) {
            $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->username, $this->repository);
            $response = wp_remote_get($request_uri);
            if (!is_wp_error($response)) {
                $this->github_response = json_decode(wp_remote_retrieve_body($response));
            }
        }
    }

    public function modify_transient($transient) {
        if (empty($transient->checked)) return $transient;
        
        $this->get_repository_info();
        if (!$this->github_response || !isset($this->github_response->tag_name)) return $transient;

        $doUpdate = version_compare(ltrim($this->github_response->tag_name, 'v'), WCSOM_VERSION, '>');
        
        if ($doUpdate) {
            $plugin = array(
                'url'         => $this->basename,
                'slug'        => current(explode('/', $this->basename)),
                'package'     => $this->github_response->zipball_url,
                'new_version' => $this->github_response->tag_name
            );
            $transient->response[$this->basename] = (object) $plugin;
        }
        return $transient;
    }

    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') return $result;
        if (empty($args->slug) || $args->slug != current(explode('/', $this->basename))) return $result;
        
        $this->get_repository_info();
        if (!$this->github_response) return $result;

        $plugin = array(
            'name'          => 'WooCommerce Supplier Orders Manager',
            'slug'          => $this->basename,
            'version'       => $this->github_response->tag_name,
            'author'        => 'Bind AI',
            'homepage'      => 'https://github.com/' . $this->username . '/' . $this->repository,
            'requires'      => '5.0',
            'tested'        => '6.4',
            'sections'      => array('description' => 'Latest updates pulled from GitHub releases.'),
            'download_link' => $this->github_response->zipball_url
        );
        return (object) $plugin;
    }

    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;
        
        $install_directory = plugin_dir_path($this->file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;
        
        activate_plugin($this->basename);
        return $result;
    }
}