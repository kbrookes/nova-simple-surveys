<?php
/**
 * Main plugin class
 *
 * @package Nova_Simple_Surveys
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Nova_Surveys_Plugin {
    
    /**
     * Plugin instance
     *
     * @var Nova_Surveys_Plugin
     */
    private static $instance = null;
    
    /**
     * Survey Manager instance
     *
     * @var Nova_Surveys_Survey_Manager
     */
    public $survey_manager;
    
    /**
     * Submission Manager instance
     *
     * @var Nova_Surveys_Submission_Manager
     */
    public $submission_manager;
    
    /**
     * Email Handler instance
     *
     * @var Nova_Surveys_Email_Handler
     */
    public $email_handler;
    
    /**
     * Frontend Display instance
     *
     * @var Nova_Surveys_Frontend_Display
     */
    public $frontend_display;
    
    /**
     * Admin Interface instance
     *
     * @var Nova_Surveys_Admin_Interface
     */
    public $admin_interface;
    
    /**
     * Get plugin instance
     *
     * @return Nova_Surveys_Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
        $this->init_components();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once NOVA_SURVEYS_PLUGIN_DIR . 'includes/class-survey-manager.php';
        require_once NOVA_SURVEYS_PLUGIN_DIR . 'includes/class-submission-manager.php';
        require_once NOVA_SURVEYS_PLUGIN_DIR . 'includes/class-email-handler.php';
        require_once NOVA_SURVEYS_PLUGIN_DIR . 'includes/class-frontend-display.php';
        require_once NOVA_SURVEYS_PLUGIN_DIR . 'includes/class-admin-interface.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->survey_manager = new Nova_Surveys_Survey_Manager();
        $this->submission_manager = new Nova_Surveys_Submission_Manager();
        $this->email_handler = new Nova_Surveys_Email_Handler();
        $this->frontend_display = new Nova_Surveys_Frontend_Display();
        $this->admin_interface = new Nova_Surveys_Admin_Interface();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'nova-simple-surveys',
            false,
            dirname(NOVA_SURVEYS_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_style(
            'nova-surveys-frontend',
            NOVA_SURVEYS_PLUGIN_URL . 'public/css/survey-styles.css',
            array(),
            NOVA_SURVEYS_VERSION
        );
        
        wp_enqueue_script(
            'nova-surveys-frontend',
            NOVA_SURVEYS_PLUGIN_URL . 'public/js/survey-scripts.js',
            array('jquery'),
            NOVA_SURVEYS_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('nova-surveys-frontend', 'nova_surveys_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nova_surveys_ajax_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'nova-simple-surveys'),
                'error' => __('An error occurred. Please try again.', 'nova-simple-surveys'),
                'required_field' => __('This field is required.', 'nova-simple-surveys'),
                'invalid_email' => __('Please enter a valid email address.', 'nova-simple-surveys'),
            )
        ));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'nova-surveys') === false) {
            return;
        }
        
        wp_enqueue_style(
            'nova-surveys-admin',
            NOVA_SURVEYS_PLUGIN_URL . 'admin/css/admin-styles.css',
            array(),
            NOVA_SURVEYS_VERSION
        );
        
        wp_enqueue_script(
            'nova-surveys-admin',
            NOVA_SURVEYS_PLUGIN_URL . 'admin/js/admin-scripts.js',
            array('jquery', 'wp-color-picker'),
            NOVA_SURVEYS_VERSION,
            true
        );
        
        // Add color picker dependency
        wp_enqueue_style('wp-color-picker');
        
        // Localize admin script
        wp_localize_script('nova-surveys-admin', 'nova_surveys_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nova_surveys_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'nova-simple-surveys'),
                'save_success' => __('Changes saved successfully.', 'nova-simple-surveys'),
                'save_error' => __('Error saving changes.', 'nova-simple-surveys'),
            )
        ));
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        self::create_database_tables();
        self::create_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Remove database tables
        self::drop_database_tables();
        
        // Remove plugin options
        self::remove_plugin_options();
    }
    
    /**
     * Create database tables
     */
    private static function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Surveys table
        $sql_surveys = "CREATE TABLE {$wpdb->prefix}nova_surveys (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            intro_enabled tinyint(1) DEFAULT 0,
            intro_content text,
            scoring_method varchar(50) DEFAULT 'sum',
            status varchar(20) DEFAULT 'draft',
            colors_config text,
            button_config text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Questions table
        $sql_questions = "CREATE TABLE {$wpdb->prefix}nova_survey_questions (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            survey_id mediumint(9) NOT NULL,
            question_text text NOT NULL,
            question_type varchar(50) DEFAULT 'rating',
            sort_order int(11) DEFAULT 0,
            min_score int(11) DEFAULT 0,
            max_score int(11) DEFAULT 10,
            required tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY survey_id (survey_id),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        
        // Submissions table
        $sql_submissions = "CREATE TABLE {$wpdb->prefix}nova_survey_submissions (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            survey_id mediumint(9) NOT NULL,
            user_name varchar(255) NOT NULL,
            user_email varchar(255) NOT NULL,
            total_score decimal(10,2) DEFAULT 0,
            submission_data longtext,
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY survey_id (survey_id),
            KEY submitted_at (submitted_at),
            KEY user_email (user_email)
        ) $charset_collate;";
        
        // Question Responses table
        $sql_responses = "CREATE TABLE {$wpdb->prefix}nova_survey_responses (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            submission_id mediumint(9) NOT NULL,
            question_id mediumint(9) NOT NULL,
            response_value text,
            score_value decimal(10,2) DEFAULT 0,
            PRIMARY KEY (id),
            KEY submission_id (submission_id),
            KEY question_id (question_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_surveys);
        dbDelta($sql_questions);
        dbDelta($sql_submissions);
        dbDelta($sql_responses);
        
        // Update database version
        update_option('nova_surveys_db_version', NOVA_SURVEYS_VERSION);
    }
    
    /**
     * Create default plugin options
     */
    private static function create_default_options() {
        $default_options = array(
            'email_from_name' => get_bloginfo('name'),
            'email_from_email' => get_option('admin_email'),
            'admin_email_subject' => __('New Survey Submission', 'nova-simple-surveys'),
            'user_email_subject' => __('Thank you for your survey response', 'nova-simple-surveys'),
            'default_colors' => array(
                'primary' => '#0073aa',
                'secondary' => '#ffffff',
                'text' => '#333333',
                'background' => '#f9f9f9'
            )
        );
        
        add_option('nova_surveys_options', $default_options);
    }
    
    /**
     * Drop database tables
     */
    private static function drop_database_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'nova_survey_responses',
            $wpdb->prefix . 'nova_survey_submissions',
            $wpdb->prefix . 'nova_survey_questions',
            $wpdb->prefix . 'nova_surveys'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Remove plugin options
     */
    private static function remove_plugin_options() {
        delete_option('nova_surveys_options');
        delete_option('nova_surveys_db_version');
    }
} 