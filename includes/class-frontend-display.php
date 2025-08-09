<?php
/**
 * Frontend Display Class
 *
 * Handles frontend survey display and shortcode functionality
 *
 * @package Nova_Simple_Surveys
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Nova_Surveys_Frontend_Display {
    
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
        
        add_shortcode('survey_display', array($this, 'render_survey_shortcode'));
        add_action('wp', array($this, 'handle_survey_results_page'));
        add_action('wp_footer', array($this, 'add_survey_styles'));
    }
    
    /**
     * Render survey shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function render_survey_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'survey_display');
        
        $survey_id = intval($atts['id']);
        
        if ($survey_id <= 0) {
            return '<div class="nova-survey-error">' . __('Invalid survey ID.', 'nova-simple-surveys') . '</div>';
        }
        
        $survey = $this->survey_manager->get_survey($survey_id);
        
        if (!$survey) {
            return '<div class="nova-survey-error">' . __('Survey not found.', 'nova-simple-surveys') . '</div>';
        }
        
        if ($survey->status !== 'published') {
            return '<div class="nova-survey-error">' . __('This survey is not currently available.', 'nova-simple-surveys') . '</div>';
        }
        
        $questions = $this->survey_manager->get_survey_questions($survey_id);
        
        if (empty($questions)) {
            return '<div class="nova-survey-error">' . __('This survey has no questions.', 'nova-simple-surveys') . '</div>';
        }
        
        ob_start();
        
        if ($survey->intro_enabled && !empty($survey->intro_content)) {
            echo $this->render_intro_page($survey);
        } else {
            echo $this->render_survey_form($survey, $questions);
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render intro page
     *
     * @param object $survey Survey object
     * @return string HTML output
     */
    private function render_intro_page($survey) {
        ob_start();
        ?>
        <div class="nova-survey-container" data-survey-id="<?php echo esc_attr($survey->id); ?>">
            <div class="nova-survey-intro">
                <div class="nova-survey-header">
                    <h2 class="nova-survey-title"><?php echo esc_html($survey->title); ?></h2>
                    <?php if (!empty($survey->description)): ?>
                        <p class="nova-survey-description"><?php echo esc_html($survey->description); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="nova-survey-intro-content">
                    <?php echo wp_kses_post($survey->intro_content); ?>
                </div>
                
                <div class="nova-survey-actions">
                    <button type="button" class="nova-survey-btn nova-survey-btn-primary" id="start-survey">
                        <?php _e('Start Survey', 'nova-simple-surveys'); ?>
                    </button>
                </div>
            </div>
            
            <div class="nova-survey-form-container" style="display: none;">
                <?php echo $this->render_survey_form($survey, $this->survey_manager->get_survey_questions($survey->id), false); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render survey form
     *
     * @param object $survey Survey object
     * @param array $questions Questions array
     * @param bool $standalone Whether this is a standalone form
     * @return string HTML output
     */
    private function render_survey_form($survey, $questions, $standalone = true) {
        ob_start();
        
        $container_class = $standalone ? 'nova-survey-container' : '';
        ?>
        <div class="<?php echo $container_class; ?>" data-survey-id="<?php echo esc_attr($survey->id); ?>">
            <?php if ($standalone): ?>
                <div class="nova-survey-header">
                    <h2 class="nova-survey-title"><?php echo esc_html($survey->title); ?></h2>
                    <?php if (!empty($survey->description)): ?>
                        <p class="nova-survey-description"><?php echo esc_html($survey->description); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form class="nova-survey-form" id="nova-survey-form-<?php echo esc_attr($survey->id); ?>" method="post">
                <?php wp_nonce_field('nova_surveys_submit', 'nova_surveys_nonce'); ?>
                <input type="hidden" name="survey_id" value="<?php echo esc_attr($survey->id); ?>">
                
                <div class="nova-survey-progress">
                    <div class="nova-survey-progress-bar">
                        <div class="nova-survey-progress-fill" style="width: 0%;"></div>
                    </div>
                    <span class="nova-survey-progress-text">
                        <span class="current-step">1</span> / <span class="total-steps"><?php echo count($questions) + 1; ?></span>
                    </span>
                </div>
                
                <div class="nova-survey-steps">
                    <!-- Questions Steps -->
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="nova-survey-step" data-step="<?php echo $index + 1; ?>" <?php echo $index === 0 ? '' : 'style="display: none;"'; ?>>
                            <div class="nova-survey-question">
                                <h3 class="question-title">
                                    <?php echo esc_html($question->question_text); ?>
                                    <?php if ($question->required): ?>
                                        <span class="required">*</span>
                                    <?php endif; ?>
                                </h3>
                                
                                <div class="question-input">
                                    <?php echo $this->render_question_input($question); ?>
                                </div>
                                
                                <div class="nova-survey-step-actions">
                                    <?php if ($index > 0): ?>
                                        <button type="button" class="nova-survey-btn nova-survey-btn-secondary prev-step">
                                            <?php _e('Previous', 'nova-simple-surveys'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="nova-survey-btn nova-survey-btn-primary next-step">
                                        <?php echo ($index === count($questions) - 1) ? __('Continue to Contact Info', 'nova-simple-surveys') : __('Next', 'nova-simple-surveys'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Lead Capture Step -->
                    <div class="nova-survey-step nova-survey-lead-capture" data-step="<?php echo count($questions) + 1; ?>" style="display: none;">
                        <div class="nova-survey-question">
                            <h3 class="question-title"><?php _e('Almost Done! Please provide your contact information to see your results.', 'nova-simple-surveys'); ?></h3>
                            
                            <div class="nova-survey-form-fields">
                                <div class="form-field">
                                    <label for="user_name"><?php _e('Name', 'nova-simple-surveys'); ?> <span class="required">*</span></label>
                                    <input type="text" name="user_name" id="user_name" required>
                                </div>
                                
                                <div class="form-field">
                                    <label for="user_email"><?php _e('Email Address', 'nova-simple-surveys'); ?> <span class="required">*</span></label>
                                    <input type="email" name="user_email" id="user_email" required>
                                </div>
                            </div>
                            
                            <div class="nova-survey-step-actions">
                                <button type="button" class="nova-survey-btn nova-survey-btn-secondary prev-step">
                                    <?php _e('Previous', 'nova-simple-surveys'); ?>
                                </button>
                                
                                <button type="submit" class="nova-survey-btn nova-survey-btn-primary submit-survey">
                                    <?php _e('Submit & See Results', 'nova-simple-surveys'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="nova-survey-loading" style="display: none;">
                    <div class="loading-spinner"></div>
                    <p><?php _e('Processing your responses...', 'nova-simple-surveys'); ?></p>
                </div>
                
                <div class="nova-survey-messages"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render question input based on type
     *
     * @param object $question Question object
     * @return string HTML input
     */
    private function render_question_input($question) {
        $name = 'responses[' . $question->id . ']';
        $required = $question->required ? 'required' : '';
        
        switch ($question->question_type) {
            case 'rating':
                return $this->render_rating_input($question, $name, $required);
                
            case 'yes_no':
                return $this->render_yes_no_input($question, $name, $required);
                
            case 'multiple_choice':
                return $this->render_multiple_choice_input($question, $name, $required);
                
            default:
                return '<input type="text" name="' . esc_attr($name) . '" ' . $required . '>';
        }
    }
    
    /**
     * Render rating input
     *
     * @param object $question Question object
     * @param string $name Input name
     * @param string $required Required attribute
     * @return string HTML input
     */
    private function render_rating_input($question, $name, $required) {
        $min = $question->min_score;
        $max = $question->max_score;
        
        ob_start();
        ?>
        <div class="rating-scale">
            <div class="rating-labels">
                <span class="rating-label-min"><?php echo esc_html($min); ?></span>
                <span class="rating-label-max"><?php echo esc_html($max); ?></span>
            </div>
            <div class="rating-buttons">
                <?php for ($i = $min; $i <= $max; $i++): ?>
                    <label class="rating-option">
                        <input type="radio" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($i); ?>" <?php echo $required; ?>>
                        <span class="rating-button"><?php echo esc_html($i); ?></span>
                    </label>
                <?php endfor; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render yes/no input
     *
     * @param object $question Question object
     * @param string $name Input name
     * @param string $required Required attribute
     * @return string HTML input
     */
    private function render_yes_no_input($question, $name, $required) {
        ob_start();
        ?>
        <div class="yes-no-options">
            <label class="yes-no-option">
                <input type="radio" name="<?php echo esc_attr($name); ?>" value="yes" <?php echo $required; ?>>
                <span class="option-button yes-button"><?php _e('Yes', 'nova-simple-surveys'); ?></span>
            </label>
            <label class="yes-no-option">
                <input type="radio" name="<?php echo esc_attr($name); ?>" value="no" <?php echo $required; ?>>
                <span class="option-button no-button"><?php _e('No', 'nova-simple-surveys'); ?></span>
            </label>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render multiple choice input
     *
     * @param object $question Question object
     * @param string $name Input name
     * @param string $required Required attribute
     * @return string HTML input
     */
    private function render_multiple_choice_input($question, $name, $required) {
        // For now, this is a placeholder. You could store options in question meta or separate table
        $options = array(
            array('value' => '1', 'label' => __('Option 1', 'nova-simple-surveys')),
            array('value' => '2', 'label' => __('Option 2', 'nova-simple-surveys')),
            array('value' => '3', 'label' => __('Option 3', 'nova-simple-surveys')),
        );
        
        ob_start();
        ?>
        <div class="multiple-choice-options">
            <?php foreach ($options as $option): ?>
                <label class="multiple-choice-option">
                    <input type="radio" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($option['value']); ?>" <?php echo $required; ?>>
                    <span class="option-text"><?php echo esc_html($option['label']); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle survey results page
     */
    public function handle_survey_results_page() {
        if (!isset($_GET['nova_survey_results']) || !isset($_GET['submission_id'])) {
            return;
        }
        
        $submission_id = intval($_GET['submission_id']);
        $submission = $this->submission_manager->get_submission($submission_id);
        
        if (!$submission) {
            wp_die(__('Submission not found.', 'nova-simple-surveys'));
        }
        
        $survey = $this->survey_manager->get_survey($submission->survey_id);
        
        if (!$survey) {
            wp_die(__('Survey not found.', 'nova-simple-surveys'));
        }
        
        // Override the main content
        add_filter('the_content', array($this, 'display_survey_results'));
        add_filter('the_title', function($title) use ($survey) {
            if (in_the_loop() && is_main_query()) {
                return $survey->title . ' - ' . __('Results', 'nova-simple-surveys');
            }
            return $title;
        });
    }
    
    /**
     * Display survey results
     *
     * @param string $content Original content
     * @return string Results content
     */
    public function display_survey_results($content) {
        if (!isset($_GET['nova_survey_results']) || !isset($_GET['submission_id'])) {
            return $content;
        }
        
        $submission_id = intval($_GET['submission_id']);
        $submission = $this->submission_manager->get_submission($submission_id);
        $survey = $this->survey_manager->get_survey($submission->survey_id);
        
        ob_start();
        ?>
        <div class="nova-survey-results">
            <div class="results-header">
                <h1><?php _e('Thank You!', 'nova-simple-surveys'); ?></h1>
                <p><?php _e('Your survey has been submitted successfully.', 'nova-simple-surveys'); ?></p>
            </div>
            
            <div class="results-score">
                <h2><?php _e('Your Score', 'nova-simple-surveys'); ?></h2>
                <div class="score-display">
                    <span class="score-number"><?php echo esc_html($submission->total_score); ?></span>
                </div>
                <div class="score-interpretation">
                    <?php echo $this->get_score_interpretation($submission->total_score); ?>
                </div>
            </div>
            
            <?php
            $button_config = $survey->button_config;
            if (!empty($button_config['enabled']) && !empty($button_config['text']) && !empty($button_config['url'])):
            ?>
            <div class="results-cta">
                <?php if (!empty($button_config['description'])): ?>
                    <p><?php echo wp_kses_post($button_config['description']); ?></p>
                <?php endif; ?>
                <a href="<?php echo esc_url($button_config['url']); ?>" class="nova-survey-btn nova-survey-btn-primary nova-survey-btn-large">
                    <?php echo esc_html($button_config['text']); ?>
                </a>
            </div>
            <?php endif; ?>
            
            <div class="results-footer">
                <p><?php _e('You should receive an email confirmation shortly with your complete results.', 'nova-simple-surveys'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get score interpretation
     *
     * @param float $score Score value
     * @return string Score interpretation
     */
    private function get_score_interpretation($score) {
        // Basic interpretation - could be made configurable
        if ($score >= 80) {
            return __('Excellent! You scored in the top range.', 'nova-simple-surveys');
        } elseif ($score >= 60) {
            return __('Good score! You\'re above average.', 'nova-simple-surveys');
        } elseif ($score >= 40) {
            return __('Average score. There\'s room for improvement.', 'nova-simple-surveys');
        } else {
            return __('Below average. Consider areas for development.', 'nova-simple-surveys');
        }
    }
    
    /**
     * Add survey styles to footer
     */
    public function add_survey_styles() {
        if (!$this->page_has_survey()) {
            return;
        }
        
        ?>
        <style>
        /* Nova Survey Styles */
        .nova-survey-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .nova-survey-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .nova-survey-title {
            color: #333;
            margin-bottom: 10px;
        }
        
        .nova-survey-description {
            color: #666;
            font-size: 16px;
        }
        
        .nova-survey-progress {
            margin-bottom: 30px;
        }
        
        .nova-survey-progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .nova-survey-progress-fill {
            height: 100%;
            background: #0073aa;
            transition: width 0.3s ease;
        }
        
        .nova-survey-progress-text {
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        
        .nova-survey-question {
            margin-bottom: 30px;
        }
        
        .question-title {
            margin-bottom: 20px;
            color: #333;
        }
        
        .required {
            color: #dc3232;
        }
        
        .rating-scale {
            text-align: center;
        }
        
        .rating-labels {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #666;
        }
        
        .rating-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        
        .rating-option input {
            display: none;
        }
        
        .rating-button {
            display: block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            border: 2px solid #ddd;
            border-radius: 50%;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .rating-option input:checked + .rating-button {
            background: #0073aa;
            color: white;
            border-color: #0073aa;
        }
        
        .yes-no-options, .multiple-choice-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .yes-no-option, .multiple-choice-option {
            cursor: pointer;
        }
        
        .yes-no-option input, .multiple-choice-option input {
            display: none;
        }
        
        .option-button, .option-text {
            display: block;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            background: #fff;
            transition: all 0.2s ease;
        }
        
        .yes-no-option input:checked + .option-button,
        .multiple-choice-option input:checked + .option-text {
            background: #0073aa;
            color: white;
            border-color: #0073aa;
        }
        
        .nova-survey-form-fields {
            margin-bottom: 20px;
        }
        
        .form-field {
            margin-bottom: 15px;
        }
        
        .form-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-field input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .nova-survey-step-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .nova-survey-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.2s ease;
        }
        
        .nova-survey-btn-primary {
            background: #0073aa;
            color: white;
        }
        
        .nova-survey-btn-primary:hover {
            background: #005a87;
        }
        
        .nova-survey-btn-secondary {
            background: #f1f1f1;
            color: #333;
        }
        
        .nova-survey-btn-secondary:hover {
            background: #e1e1e1;
        }
        
        .nova-survey-btn-large {
            padding: 15px 30px;
            font-size: 18px;
        }
        
        .nova-survey-loading {
            text-align: center;
            padding: 40px 20px;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0073aa;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .nova-survey-results {
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .score-display {
            font-size: 4em;
            font-weight: bold;
            color: #0073aa;
            margin: 20px 0;
        }
        
        .score-interpretation {
            font-size: 1.2em;
            margin: 20px 0;
            color: #666;
        }
        
        .results-cta {
            margin: 40px 0;
        }
        
        .nova-survey-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        @media (max-width: 768px) {
            .nova-survey-container {
                margin: 10px;
                padding: 15px;
            }
            
            .rating-buttons {
                gap: 5px;
            }
            
            .rating-button {
                width: 35px;
                height: 35px;
                line-height: 35px;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Check if current page has survey shortcode
     *
     * @return bool
     */
    private function page_has_survey() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        return has_shortcode($post->post_content, 'survey_display') || isset($_GET['nova_survey_results']);
    }
} 