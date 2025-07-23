<?php

if (!defined('ABSPATH')) {
    exit;
}

class Plugin_Autopsy_Asset_Tracker {
    
    private $enqueued_assets = [];
    private $plugin_assets = [];
    
    public function __construct() {
        $this->init();
    }
    
    private function init() {
        add_action('wp_enqueue_scripts', [$this, 'track_frontend_assets'], 9999);
        add_action('admin_enqueue_scripts', [$this, 'track_admin_assets'], 9999);
        add_action('wp_head', [$this, 'capture_head_assets'], 9999);
        add_action('wp_footer', [$this, 'analyze_assets'], 9999);
        add_action('admin_footer', [$this, 'analyze_assets'], 9999);
    }
    
    public function track_frontend_assets() {
        $this->capture_enqueued_assets('frontend');
    }
    
    public function track_admin_assets() {
        $this->capture_enqueued_assets('admin');
    }
    
    public function capture_head_assets() {
        ob_start([$this, 'analyze_head_content']);
    }
    
    public function analyze_head_content($content) {
        preg_match_all('/<link[^>]*href=["\']([^"\']*)["\'][^>]*>/i', $content, $css_matches);
        preg_match_all('/<script[^>]*src=["\']([^"\']*)["\'][^>]*>/i', $content, $js_matches);
        
        foreach ($css_matches[1] as $css_url) {
            $this->categorize_asset_by_url($css_url, 'css');
        }
        
        foreach ($js_matches[1] as $js_url) {
            $this->categorize_asset_by_url($js_url, 'js');
        }
        
        return $content;
    }
    
    private function capture_enqueued_assets($context) {
        global $wp_scripts, $wp_styles;
        
        $scripts = $wp_scripts->done ?? [];
        $styles = $wp_styles->done ?? [];
        
        foreach ($scripts as $handle) {
            if (isset($wp_scripts->registered[$handle])) {
                $script = $wp_scripts->registered[$handle];
                $plugin_info = $this->identify_plugin_from_handle($handle, $script->src);
                
                if ($plugin_info) {
                    $this->add_asset_data($plugin_info['slug'], 'js', [
                        'handle' => $handle,
                        'src' => $script->src,
                        'deps' => $script->deps,
                        'ver' => $script->ver,
                        'context' => $context,
                        'size' => $this->get_asset_size($script->src)
                    ]);
                }
            }
        }
        
        foreach ($styles as $handle) {
            if (isset($wp_styles->registered[$handle])) {
                $style = $wp_styles->registered[$handle];
                $plugin_info = $this->identify_plugin_from_handle($handle, $style->src);
                
                if ($plugin_info) {
                    $this->add_asset_data($plugin_info['slug'], 'css', [
                        'handle' => $handle,
                        'src' => $style->src,
                        'deps' => $style->deps,
                        'ver' => $style->ver,
                        'context' => $context,
                        'media' => $style->args,
                        'size' => $this->get_asset_size($style->src)
                    ]);
                }
            }
        }
    }
    
    private function categorize_asset_by_url($url, $type) {
        $plugin_info = $this->identify_plugin_from_url($url);
        
        if ($plugin_info) {
            $this->add_asset_data($plugin_info['slug'], $type, [
                'src' => $url,
                'type' => 'inline_or_direct',
                'size' => $this->get_asset_size($url)
            ]);
        }
    }
    
    private function identify_plugin_from_handle($handle, $src) {
        if (empty($src)) {
            return false;
        }
        
        return $this->identify_plugin_from_url($src);
    }
    
    private function identify_plugin_from_url($url) {
        if (empty($url)) {
            return false;
        }
        
        $plugins_url = wp_normalize_path(WP_PLUGIN_URL);
        $content_url = wp_normalize_path(WP_CONTENT_URL);
        
        $url = wp_normalize_path($url);
        
        if (strpos($url, $plugins_url) !== false) {
            $relative_path = str_replace($plugins_url . '/', '', $url);
            $plugin_parts = explode('/', $relative_path);
            
            if (!empty($plugin_parts[0])) {
                return [
                    'slug' => $plugin_parts[0],
                    'type' => 'plugin'
                ];
            }
        }
        
        if (strpos($url, $content_url . '/themes/') !== false) {
            return [
                'slug' => 'active-theme',
                'type' => 'theme'
            ];
        }
        
        if (strpos($url, '/wp-includes/') !== false || strpos($url, '/wp-admin/') !== false) {
            return [
                'slug' => 'wordpress-core',
                'type' => 'core'
            ];
        }
        
        return false;
    }
    
    private function get_asset_size($url) {
        if (empty($url)) {
            return 0;
        }
        
        if (strpos($url, '://') === false) {
            $url = home_url($url);
        }
        
        $file_path = $this->url_to_path($url);
        
        if ($file_path && file_exists($file_path)) {
            return filesize($file_path);
        }
        
        $response = wp_remote_head($url, ['timeout' => 5]);
        if (!is_wp_error($response)) {
            $headers = wp_remote_retrieve_headers($response);
            return isset($headers['content-length']) ? intval($headers['content-length']) : 0;
        }
        
        return 0;
    }
    
    private function url_to_path($url) {
        $upload_dir = wp_upload_dir();
        $content_url = wp_normalize_path(WP_CONTENT_URL);
        $content_dir = wp_normalize_path(WP_CONTENT_DIR);
        
        $url = wp_normalize_path($url);
        
        if (strpos($url, $content_url) === 0) {
            return str_replace($content_url, $content_dir, $url);
        }
        
        $home_url = wp_normalize_path(home_url());
        $abspath = wp_normalize_path(ABSPATH);
        
        if (strpos($url, $home_url) === 0) {
            return str_replace($home_url, $abspath, $url);
        }
        
        return false;
    }
    
    private function add_asset_data($plugin_slug, $type, $data) {
        if (!isset($this->plugin_assets[$plugin_slug])) {
            $this->plugin_assets[$plugin_slug] = [
                'js' => [],
                'css' => [],
                'total_js_size' => 0,
                'total_css_size' => 0,
                'js_count' => 0,
                'css_count' => 0
            ];
        }
        
        $this->plugin_assets[$plugin_slug][$type][] = $data;
        $this->plugin_assets[$plugin_slug][$type . '_count']++;
        $this->plugin_assets[$plugin_slug]['total_' . $type . '_size'] += $data['size'] ?? 0;
    }
    
    public function analyze_assets() {
        $this->store_asset_data();
    }
    
    private function store_asset_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'plugin_autopsy_data';
        $current_url = $this->get_current_url();
        
        foreach ($this->plugin_assets as $plugin_slug => $data) {
            $metric_value = json_encode([
                'js_files' => count($data['js']),
                'css_files' => count($data['css']),
                'total_js_size' => $data['total_js_size'],
                'total_css_size' => $data['total_css_size'],
                'total_size' => $data['total_js_size'] + $data['total_css_size'],
                'js_details' => $data['js'],
                'css_details' => $data['css']
            ]);
            
            $wpdb->insert(
                $table_name,
                [
                    'plugin_name' => $plugin_slug,
                    'metric_type' => 'asset_loading',
                    'metric_value' => $metric_value,
                    'page_url' => $current_url,
                    'timestamp' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );
        }
    }
    
    private function get_current_url() {
        if (is_admin()) {
            global $pagenow;
            return admin_url($pagenow . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
        }
        
        return home_url(add_query_arg(null, null));
    }
    
    public function get_plugin_assets() {
        return $this->plugin_assets;
    }
    
    public function get_summary() {
        $summary = [];
        
        foreach ($this->plugin_assets as $plugin_slug => $data) {
            $total_size = $data['total_js_size'] + $data['total_css_size'];
            
            $summary[$plugin_slug] = [
                'js_files' => $data['js_count'],
                'css_files' => $data['css_count'],
                'total_files' => $data['js_count'] + $data['css_count'],
                'total_size' => $total_size,
                'formatted_size' => $this->format_bytes($total_size)
            ];
        }
        
        uasort($summary, function($a, $b) {
            return $b['total_size'] <=> $a['total_size'];
        });
        
        return $summary;
    }
    
    private function format_bytes($size) {
        if ($size === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $base = log($size, 1024);
        
        return round(pow(1024, $base - floor($base)), 2) . ' ' . $units[floor($base)];
    }
}