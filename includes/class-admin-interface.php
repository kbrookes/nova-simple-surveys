<?php
/**
 * Admin Interface Class
 *
 * Handles all admin interface functionality
 *
 * @package Nova_Simple_Surveys
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Nova_Surveys_Admin_Interface {
    
    /**
     * Survey Manager instance
     *
     * @var Nova_Surveys_Survey_Manager
     */
    private $survey_manager;
    
    /**
     * Submission Manager instance
     *
     * @var Nova_Surveys_Submission_Manager
     */
    private $submission_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->survey_manager = new Nova_Surveys_Survey_Manager();
        $this->submission_manager = new Nova_Surveys_Submission_Manager();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('wp_ajax_nova_surveys_save_survey', array($this, 'ajax_save_survey'));
        add_action('wp_ajax_nova_surveys_delete_survey', array($this, 'ajax_delete_survey'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        $capability = 'manage_options';
        
        // Main menu page
        add_menu_page(
            __('Nova Surveys', 'nova-simple-surveys'),
            __('Nova Surveys', 'nova-simple-surveys'),
            $capability,
            'nova-surveys',
            array($this, 'surveys_list_page'),
            'dashicons-feedback',
            30
        );
        
        // Surveys submenu (same as main page)
        add_submenu_page(
            'nova-surveys',
            __('All Surveys', 'nova-simple-surveys'),
            __('All Surveys', 'nova-simple-surveys'),
            $capability,
            'nova-surveys',
            array($this, 'surveys_list_page')
        );
        
        // Add new survey
        add_submenu_page(
            'nova-surveys',
            __('Add New Survey', 'nova-simple-surveys'),
            __('Add New Survey', 'nova-simple-surveys'),
            $capability,
            'nova-surveys-new',
            array($this, 'survey_edit_page')
        );
        
        // Submissions
        add_submenu_page(
            'nova-surveys',
            __('Submissions', 'nova-simple-surveys'),
            __('Submissions', 'nova-simple-surveys'),
            $capability,
            'nova-surveys-submissions',
            array($this, 'submissions_page')
        );
        
        // Settings
        add_submenu_page(
            'nova-surveys',
            __('Settings', 'nova-simple-surveys'),
            __('Settings', 'nova-simple-surveys'),
            $capability,
            'nova-surveys-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle survey actions
        if (isset($_GET['action']) && isset($_GET['page']) && strpos($_GET['page'], 'nova-surveys') === 0) {
            $action = sanitize_text_field($_GET['action']);
            
            switch ($action) {
                case 'delete':
                    $this->handle_delete_survey();
                    break;
                case 'duplicate':
                    $this->handle_duplicate_survey();
                    break;
                case 'toggle_status':
                    $this->handle_toggle_status();
                    break;
            }
        }
    }
    
    /**
     * Surveys list page
     */
    public function surveys_list_page() {
        $surveys = $this->survey_manager->get_surveys();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Surveys', 'nova-simple-surveys'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=nova-surveys-new'); ?>" class="page-title-action">
                <?php _e('Add New', 'nova-simple-surveys'); ?>
            </a>
            
            <?php $this->show_admin_notices(); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text">
                        <?php _e('Select bulk action', 'nova-simple-surveys'); ?>
                    </label>
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Bulk Actions', 'nova-simple-surveys'); ?></option>
                        <option value="delete"><?php _e('Delete', 'nova-simple-surveys'); ?></option>
                    </select>
                    <input type="submit" id="doaction" class="button action" value="<?php _e('Apply', 'nova-simple-surveys'); ?>">
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped surveys">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column">
                            <input id="cb-select-all-1" type="checkbox">
                        </td>
                        <th scope="col" class="manage-column column-title column-primary">
                            <?php _e('Title', 'nova-simple-surveys'); ?>
                        </th>
                        <th scope="col" class="manage-column column-status">
                            <?php _e('Status', 'nova-simple-surveys'); ?>
                        </th>
                        <th scope="col" class="manage-column column-submissions">
                            <?php _e('Submissions', 'nova-simple-surveys'); ?>
                        </th>
                        <th scope="col" class="manage-column column-date">
                            <?php _e('Date', 'nova-simple-surveys'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($surveys)): ?>
                        <tr class="no-items">
                            <td class="colspanchange" colspan="5">
                                <?php _e('No surveys found.', 'nova-simple-surveys'); ?>
                                <a href="<?php echo admin_url('admin.php?page=nova-surveys-new'); ?>">
                                    <?php _e('Create your first survey!', 'nova-simple-surveys'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($surveys as $survey): ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="survey[]" value="<?php echo $survey->id; ?>">
                                </th>
                                <td class="title column-title column-primary">
                                    <strong>
                                        <a href="<?php echo admin_url('admin.php?page=nova-surveys-new&survey_id=' . $survey->id); ?>">
                                            <?php echo esc_html($survey->title); ?>
                                        </a>
                                    </strong>
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="<?php echo admin_url('admin.php?page=nova-surveys-new&survey_id=' . $survey->id); ?>">
                                                <?php _e('Edit', 'nova-simple-surveys'); ?>
                                            </a> |
                                        </span>
                                        <span class="duplicate">
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=nova-surveys&action=duplicate&survey_id=' . $survey->id), 'duplicate_survey_' . $survey->id); ?>">
                                                <?php _e('Duplicate', 'nova-simple-surveys'); ?>
                                            </a> |
                                        </span>
                                        <span class="trash">
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=nova-surveys&action=delete&survey_id=' . $survey->id), 'delete_survey_' . $survey->id); ?>" 
                                               onclick="return confirm('<?php _e('Are you sure you want to delete this survey?', 'nova-simple-surveys'); ?>')">
                                                <?php _e('Delete', 'nova-simple-surveys'); ?>
                                            </a>
                                        </span>
                                    </div>
                                </td>
                                <td class="status column-status">
                                    <span class="status-<?php echo $survey->status; ?>">
                                        <?php echo ucfirst($survey->status); ?>
                                    </span>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=nova-surveys&action=toggle_status&survey_id=' . $survey->id), 'toggle_status_' . $survey->id); ?>" 
                                       class="button button-small">
                                        <?php echo ($survey->status === 'published') ? __('Unpublish', 'nova-simple-surveys') : __('Publish', 'nova-simple-surveys'); ?>
                                    </a>
                                </td>
                                <td class="submissions column-submissions">
                                    <?php
                                    $submission_count = $this->submission_manager->get_submissions(array('survey_id' => $survey->id, 'limit' => 1));
                                    echo count($submission_count);
                                    ?>
                                    <a href="<?php echo admin_url('admin.php?page=nova-surveys-submissions&survey_id=' . $survey->id); ?>" class="button button-small">
                                        <?php _e('View', 'nova-simple-surveys'); ?>
                                    </a>
                                </td>
                                <td class="date column-date">
                                    <?php echo date_i18n(get_option('date_format'), strtotime($survey->updated_at)); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Survey edit page
     */
    public function survey_edit_page() {
        $survey_id = isset($_GET['survey_id']) ? intval($_GET['survey_id']) : 0;
        $survey = null;
        $questions = array();
        
        if ($survey_id > 0) {
            $survey = $this->survey_manager->get_survey($survey_id);
            $questions = $this->survey_manager->get_survey_questions($survey_id);
        }
        
        $is_edit = !empty($survey);
        $page_title = $is_edit ? __('Edit Survey', 'nova-simple-surveys') : __('Add New Survey', 'nova-simple-surveys');
        
        ?>
        <div class="wrap">
            <h1><?php echo $page_title; ?></h1>
            
            <?php $this->show_admin_notices(); ?>
            
            <form id="nova-survey-form" method="post" action="">
                <?php wp_nonce_field('nova_surveys_save_survey', 'nova_surveys_nonce'); ?>
                <input type="hidden" name="survey_id" value="<?php echo $survey_id; ?>">
                
                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <div id="post-body-content">
                            <!-- Survey Title -->
                            <div id="titlediv">
                                <input type="text" name="survey_title" id="survey-title" 
                                       value="<?php echo $survey ? esc_attr($survey->title) : ''; ?>"
                                       placeholder="<?php _e('Enter survey title', 'nova-simple-surveys'); ?>"
                                       autocomplete="off" required>
                            </div>
                            
                            <!-- Survey Description -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php _e('Survey Description', 'nova-simple-surveys'); ?></h2>
                                </div>
                                <div class="inside">
                                    <textarea name="survey_description" rows="4" style="width: 100%;"
                                              placeholder="<?php _e('Optional description for your survey...', 'nova-simple-surveys'); ?>"><?php echo $survey ? esc_textarea($survey->description) : ''; ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Questions -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php _e('Questions', 'nova-simple-surveys'); ?></h2>
                                    <div class="handle-actions">
                                        <button type="button" class="button button-primary" id="add-question">
                                            <?php _e('Add Question', 'nova-simple-surveys'); ?>
                                        </button>
                                    </div>
                                </div>
                                <div class="inside">
                                    <div id="questions-container">
                                        <?php if (!empty($questions)): ?>
                                            <?php foreach ($questions as $index => $question): ?>
                                                <?php $this->render_question_row($question, $index); ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Question Template -->
                                    <script type="text/template" id="question-template">
                                        <?php $this->render_question_row(null, '{{INDEX}}'); ?>
                                    </script>
                                </div>
                            </div>
                        </div>
                        
                        <div id="postbox-container-1" class="postbox-container">
                            <!-- Publish Box -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php _e('Publish', 'nova-simple-surveys'); ?></h2>
                                </div>
                                <div class="inside">
                                    <div class="submitbox">
                                        <div id="major-publishing-actions">
                                            <div id="publishing-action">
                                                <input type="submit" name="save_survey" id="save-survey" 
                                                       class="button button-primary button-large" 
                                                       value="<?php echo $is_edit ? __('Update Survey', 'nova-simple-surveys') : __('Create Survey', 'nova-simple-surveys'); ?>">
                                            </div>
                                            <div class="clear"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="misc-pub-section">
                                        <label for="survey_status"><?php _e('Status:', 'nova-simple-surveys'); ?></label>
                                        <select name="survey_status" id="survey_status">
                                            <option value="draft" <?php selected($survey ? $survey->status : 'draft', 'draft'); ?>>
                                                <?php _e('Draft', 'nova-simple-surveys'); ?>
                                            </option>
                                            <option value="published" <?php selected($survey ? $survey->status : '', 'published'); ?>>
                                                <?php _e('Published', 'nova-simple-surveys'); ?>
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Survey Settings -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php _e('Survey Settings', 'nova-simple-surveys'); ?></h2>
                                </div>
                                <div class="inside">
                                    <!-- Intro Page -->
                                    <p>
                                        <label>
                                            <input type="checkbox" name="intro_enabled" value="1" 
                                                   <?php checked($survey ? $survey->intro_enabled : 0, 1); ?>>
                                            <?php _e('Enable intro page', 'nova-simple-surveys'); ?>
                                        </label>
                                    </p>
                                    
                                    <div id="intro-content" style="display: <?php echo ($survey && $survey->intro_enabled) ? 'block' : 'none'; ?>;">
                                        <label for="intro_content"><?php _e('Intro Content:', 'nova-simple-surveys'); ?></label>
                                        <textarea name="intro_content" id="intro_content" rows="4" style="width: 100%;"
                                                  placeholder="<?php _e('Welcome message for your survey...', 'nova-simple-surveys'); ?>"><?php echo $survey ? esc_textarea($survey->intro_content) : ''; ?></textarea>
                                    </div>
                                    
                                    <!-- Scoring Method -->
                                    <p>
                                        <label for="scoring_method"><?php _e('Scoring Method:', 'nova-simple-surveys'); ?></label>
                                        <select name="scoring_method" id="scoring_method">
                                            <option value="sum" <?php selected($survey ? $survey->scoring_method : 'sum', 'sum'); ?>>
                                                <?php _e('Sum of all scores', 'nova-simple-surveys'); ?>
                                            </option>
                                            <option value="average" <?php selected($survey ? $survey->scoring_method : '', 'average'); ?>>
                                                <?php _e('Average score', 'nova-simple-surveys'); ?>
                                            </option>
                                        </select>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Shortcode -->
                            <?php if ($is_edit): ?>
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php _e('Shortcode', 'nova-simple-surveys'); ?></h2>
                                </div>
                                <div class="inside">
                                    <p><?php _e('Use this shortcode to display the survey:', 'nova-simple-surveys'); ?></p>
                                    <input type="text" readonly value="[survey_display id=&quot;<?php echo $survey_id; ?>&quot;]" 
                                           onclick="this.select();" style="width: 100%;">
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <style>
        .question-row {
            border: 1px solid #ddd;
            margin: 10px 0;
            padding: 15px;
            background: #f9f9f9;
        }
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .question-title {
            font-weight: bold;
        }
        .question-actions button {
            margin-left: 5px;
        }
        .question-fields {
            display: grid;
            grid-template-columns: 1fr 200px 100px 100px;
            gap: 10px;
            align-items: end;
        }
        .question-text {
            width: 100%;
        }
        #titlediv input {
            width: 100%;
            height: 50px;
            font-size: 1.7em;
            line-height: 1.1;
            outline: 0;
            margin: 0 0 3px;
            padding: 3px 8px;
            border: 1px solid #ddd;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var questionIndex = <?php echo !empty($questions) ? count($questions) : 0; ?>;
            
            // Add question
            $('#add-question').on('click', function() {
                var template = $('#question-template').html();
                template = template.replace(/\{\{INDEX\}\}/g, questionIndex);
                $('#questions-container').append(template);
                questionIndex++;
            });
            
            // Remove question
            $(document).on('click', '.remove-question', function() {
                if (confirm('<?php _e('Are you sure you want to remove this question?', 'nova-simple-surveys'); ?>')) {
                    $(this).closest('.question-row').remove();
                }
            });
            
            // Toggle intro content
            $('input[name="intro_enabled"]').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#intro-content').show();
                } else {
                    $('#intro-content').hide();
                }
            });
            
            // Make questions sortable
            $('#questions-container').sortable({
                handle: '.question-title',
                placeholder: 'question-placeholder',
                update: function() {
                    // Update sort order values
                    $('#questions-container .question-row').each(function(index) {
                        $(this).find('.sort-order').val(index);
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render question row
     */
    private function render_question_row($question = null, $index = 0) {
        $question_id = $question ? $question->id : '';
        $question_text = $question ? $question->question_text : '';
        $question_type = $question ? $question->question_type : 'rating';
        $min_score = $question ? $question->min_score : 0;
        $max_score = $question ? $question->max_score : 10;
        $required = $question ? $question->required : 1;
        $sort_order = $question ? $question->sort_order : $index;
        
        ?>
        <div class="question-row">
            <input type="hidden" name="questions[<?php echo $index; ?>][id]" value="<?php echo $question_id; ?>">
            <input type="hidden" name="questions[<?php echo $index; ?>][sort_order]" class="sort-order" value="<?php echo $sort_order; ?>">
            
            <div class="question-header">
                <span class="question-title"><?php printf(__('Question %d', 'nova-simple-surveys'), $index + 1); ?></span>
                <div class="question-actions">
                    <button type="button" class="button button-small remove-question">
                        <?php _e('Remove', 'nova-simple-surveys'); ?>
                    </button>
                </div>
            </div>
            
            <div class="question-fields">
                <div>
                    <label><?php _e('Question Text:', 'nova-simple-surveys'); ?></label>
                    <textarea name="questions[<?php echo $index; ?>][question_text]" 
                              class="question-text" rows="2" required
                              placeholder="<?php _e('Enter your question...', 'nova-simple-surveys'); ?>"><?php echo esc_textarea($question_text); ?></textarea>
                </div>
                
                <div>
                    <label><?php _e('Type:', 'nova-simple-surveys'); ?></label>
                    <select name="questions[<?php echo $index; ?>][question_type]">
                        <option value="rating" <?php selected($question_type, 'rating'); ?>>
                            <?php _e('Rating Scale', 'nova-simple-surveys'); ?>
                        </option>
                        <option value="yes_no" <?php selected($question_type, 'yes_no'); ?>>
                            <?php _e('Yes/No', 'nova-simple-surveys'); ?>
                        </option>
                        <option value="multiple_choice" <?php selected($question_type, 'multiple_choice'); ?>>
                            <?php _e('Multiple Choice', 'nova-simple-surveys'); ?>
                        </option>
                    </select>
                </div>
                
                <div>
                    <label><?php _e('Min Score:', 'nova-simple-surveys'); ?></label>
                    <input type="number" name="questions[<?php echo $index; ?>][min_score]" 
                           value="<?php echo $min_score; ?>" min="0" step="0.1">
                </div>
                
                <div>
                    <label><?php _e('Max Score:', 'nova-simple-surveys'); ?></label>
                    <input type="number" name="questions[<?php echo $index; ?>][max_score]" 
                           value="<?php echo $max_score; ?>" min="0" step="0.1">
                </div>
            </div>
            
            <p>
                <label>
                    <input type="checkbox" name="questions[<?php echo $index; ?>][required]" 
                           value="1" <?php checked($required, 1); ?>>
                    <?php _e('Required question', 'nova-simple-surveys'); ?>
                </label>
            </p>
        </div>
        <?php
    }
    
    /**
     * Submissions page
     */
    public function submissions_page() {
        // Implementation for submissions page
        echo '<div class="wrap"><h1>' . __('Submissions', 'nova-simple-surveys') . '</h1>';
        echo '<p>' . __('Submissions page will be implemented next.', 'nova-simple-surveys') . '</p></div>';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        // Implementation for settings page
        echo '<div class="wrap"><h1>' . __('Settings', 'nova-simple-surveys') . '</h1>';
        echo '<p>' . __('Settings page will be implemented next.', 'nova-simple-surveys') . '</p></div>';
    }
    
    /**
     * Show admin notices
     */
    private function show_admin_notices() {
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'success';
            
            echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
    
    /**
     * Handle delete survey
     */
    private function handle_delete_survey() {
        if (!isset($_GET['survey_id']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_survey_' . $_GET['survey_id'])) {
            wp_die(__('Security check failed', 'nova-simple-surveys'));
        }
        
        $survey_id = intval($_GET['survey_id']);
        
        if ($this->survey_manager->delete_survey($survey_id)) {
            $redirect_url = add_query_arg(array(
                'message' => __('Survey deleted successfully.', 'nova-simple-surveys'),
                'type' => 'success'
            ), admin_url('admin.php?page=nova-surveys'));
        } else {
            $redirect_url = add_query_arg(array(
                'message' => __('Error deleting survey.', 'nova-simple-surveys'),
                'type' => 'error'
            ), admin_url('admin.php?page=nova-surveys'));
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle duplicate survey
     */
    private function handle_duplicate_survey() {
        if (!isset($_GET['survey_id']) || !wp_verify_nonce($_GET['_wpnonce'], 'duplicate_survey_' . $_GET['survey_id'])) {
            wp_die(__('Security check failed', 'nova-simple-surveys'));
        }
        
        $survey_id = intval($_GET['survey_id']);
        $new_survey_id = $this->survey_manager->duplicate_survey($survey_id);
        
        if ($new_survey_id) {
            $redirect_url = add_query_arg(array(
                'message' => __('Survey duplicated successfully.', 'nova-simple-surveys'),
                'type' => 'success'
            ), admin_url('admin.php?page=nova-surveys-new&survey_id=' . $new_survey_id));
        } else {
            $redirect_url = add_query_arg(array(
                'message' => __('Error duplicating survey.', 'nova-simple-surveys'),
                'type' => 'error'
            ), admin_url('admin.php?page=nova-surveys'));
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle toggle status
     */
    private function handle_toggle_status() {
        if (!isset($_GET['survey_id']) || !wp_verify_nonce($_GET['_wpnonce'], 'toggle_status_' . $_GET['survey_id'])) {
            wp_die(__('Security check failed', 'nova-simple-surveys'));
        }
        
        $survey_id = intval($_GET['survey_id']);
        $survey = $this->survey_manager->get_survey($survey_id);
        
        if ($survey) {
            $new_status = ($survey->status === 'published') ? 'draft' : 'published';
            
            if ($this->survey_manager->update_survey($survey_id, array('status' => $new_status))) {
                $message = ($new_status === 'published') ? 
                    __('Survey published successfully.', 'nova-simple-surveys') : 
                    __('Survey unpublished successfully.', 'nova-simple-surveys');
                
                $redirect_url = add_query_arg(array(
                    'message' => $message,
                    'type' => 'success'
                ), admin_url('admin.php?page=nova-surveys'));
            } else {
                $redirect_url = add_query_arg(array(
                    'message' => __('Error updating survey status.', 'nova-simple-surveys'),
                    'type' => 'error'
                ), admin_url('admin.php?page=nova-surveys'));
            }
        } else {
            $redirect_url = add_query_arg(array(
                'message' => __('Survey not found.', 'nova-simple-surveys'),
                'type' => 'error'
            ), admin_url('admin.php?page=nova-surveys'));
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * AJAX save survey
     */
    public function ajax_save_survey() {
        if (!wp_verify_nonce($_POST['nova_surveys_nonce'], 'nova_surveys_save_survey')) {
            wp_die(__('Security check failed', 'nova-simple-surveys'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'nova-simple-surveys'));
        }
        
        // Handle survey saving logic here
        // This will be implemented when we handle form submissions
        
        wp_send_json_success(array(
            'message' => __('Survey saved successfully.', 'nova-simple-surveys')
        ));
    }
    
    /**
     * AJAX delete survey
     */
    public function ajax_delete_survey() {
        if (!wp_verify_nonce($_POST['nonce'], 'nova_surveys_admin_nonce')) {
            wp_die(__('Security check failed', 'nova-simple-surveys'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'nova-simple-surveys'));
        }
        
        $survey_id = intval($_POST['survey_id']);
        
        if ($this->survey_manager->delete_survey($survey_id)) {
            wp_send_json_success(array(
                'message' => __('Survey deleted successfully.', 'nova-simple-surveys')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Error deleting survey.', 'nova-simple-surveys')
            ));
        }
    }
} 