<?php
/**
 * Email Handler Class
 *
 * Handles all email functionality
 *
 * @package Nova_Simple_Surveys
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Nova_Surveys_Email_Handler {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
    }
    
    /**
     * Set email content type to HTML
     *
     * @return string
     */
    public function set_html_content_type() {
        return 'text/html';
    }
    
    /**
     * Send admin notification email
     *
     * @param int $submission_id Submission ID
     * @return bool True on success, false on failure
     */
    public function send_admin_notification($submission_id) {
        $submission_manager = new Nova_Surveys_Submission_Manager();
        $survey_manager = new Nova_Surveys_Survey_Manager();
        
        $submission = $submission_manager->get_submission($submission_id);
        if (!$submission) {
            return false;
        }
        
        $survey = $survey_manager->get_survey($submission->survey_id);
        if (!$survey) {
            return false;
        }
        
        $responses = $submission_manager->get_submission_responses($submission_id);
        
        // Get plugin options
        $options = get_option('nova_surveys_options', array());
        
        $to = $options['email_from_email'] ?? get_option('admin_email');
        $subject = $options['admin_email_subject'] ?? __('New Survey Submission', 'nova-simple-surveys');
        $subject .= ': ' . $survey->title;
        
        $message = $this->get_admin_email_template($submission, $survey, $responses);
        
        $headers = array(
            'From: ' . ($options['email_from_name'] ?? get_bloginfo('name')) . ' <' . ($options['email_from_email'] ?? get_option('admin_email')) . '>',
        );
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Send user confirmation email
     *
     * @param int $submission_id Submission ID
     * @return bool True on success, false on failure
     */
    public function send_user_confirmation($submission_id) {
        $submission_manager = new Nova_Surveys_Submission_Manager();
        $survey_manager = new Nova_Surveys_Survey_Manager();
        
        $submission = $submission_manager->get_submission($submission_id);
        if (!$submission) {
            return false;
        }
        
        $survey = $survey_manager->get_survey($submission->survey_id);
        if (!$survey) {
            return false;
        }
        
        // Get plugin options
        $options = get_option('nova_surveys_options', array());
        
        $to = $submission->user_email;
        $subject = $options['user_email_subject'] ?? __('Thank you for your survey response', 'nova-simple-surveys');
        
        $message = $this->get_user_email_template($submission, $survey);
        
        $headers = array(
            'From: ' . ($options['email_from_name'] ?? get_bloginfo('name')) . ' <' . ($options['email_from_email'] ?? get_option('admin_email')) . '>',
        );
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Get admin email template
     *
     * @param object $submission Submission object
     * @param object $survey Survey object
     * @param array $responses Response objects
     * @return string HTML email content
     */
    private function get_admin_email_template($submission, $survey, $responses) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($survey->title); ?> - New Submission</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
                .header { background: #0073aa; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; margin: -20px -20px 20px -20px; }
                .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px; }
                .section h3 { margin-top: 0; color: #0073aa; }
                .score-display { font-size: 24px; font-weight: bold; color: #0073aa; text-align: center; margin: 10px 0; }
                .response-item { margin: 10px 0; padding: 10px; background: white; border-left: 4px solid #0073aa; }
                .response-question { font-weight: bold; margin-bottom: 5px; }
                .response-answer { color: #666; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; text-align: center; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('New Survey Submission', 'nova-simple-surveys'); ?></h1>
                    <p><?php echo esc_html($survey->title); ?></p>
                </div>
                
                <div class="section">
                    <h3><?php _e('Contact Information', 'nova-simple-surveys'); ?></h3>
                    <p><strong><?php _e('Name:', 'nova-simple-surveys'); ?></strong> <?php echo esc_html($submission->user_name); ?></p>
                    <p><strong><?php _e('Email:', 'nova-simple-surveys'); ?></strong> <?php echo esc_html($submission->user_email); ?></p>
                    <p><strong><?php _e('Submitted:', 'nova-simple-surveys'); ?></strong> <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->submitted_at)); ?></p>
                </div>
                
                <div class="section">
                    <h3><?php _e('Score Summary', 'nova-simple-surveys'); ?></h3>
                    <div class="score-display"><?php echo esc_html($submission->total_score); ?></div>
                    <p><?php echo $this->get_score_interpretation($submission->total_score, $survey); ?></p>
                </div>
                
                <div class="section">
                    <h3><?php _e('Individual Responses', 'nova-simple-surveys'); ?></h3>
                    <?php foreach ($responses as $response): ?>
                        <div class="response-item">
                            <div class="response-question"><?php echo esc_html($response->question_text); ?></div>
                            <div class="response-answer">
                                <?php _e('Answer:', 'nova-simple-surveys'); ?> <strong><?php echo esc_html($response->response_value); ?></strong>
                                (<?php _e('Score:', 'nova-simple-surveys'); ?> <?php echo esc_html($response->score_value); ?>)
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="footer">
                    <p><?php printf(__('This notification was sent from %s', 'nova-simple-surveys'), '<a href="' . esc_url($site_url) . '">' . esc_html($site_name) . '</a>'); ?></p>
                    <p><?php _e('You are receiving this because you are the site administrator.', 'nova-simple-surveys'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user email template
     *
     * @param object $submission Submission object
     * @param object $survey Survey object
     * @return string HTML email content
     */
    private function get_user_email_template($submission, $survey) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php _e('Thank you for your survey response', 'nova-simple-surveys'); ?></title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
                .header { background: #0073aa; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; margin: -20px -20px 20px -20px; }
                .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px; }
                .section h3 { margin-top: 0; color: #0073aa; }
                .score-display { font-size: 36px; font-weight: bold; color: #0073aa; text-align: center; margin: 20px 0; }
                .score-meaning { font-size: 18px; text-align: center; margin: 20px 0; line-height: 1.4; }
                .cta-section { text-align: center; margin: 30px 0; }
                .cta-button { display: inline-block; padding: 15px 30px; background: #0073aa; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; }
                .cta-button:hover { background: #005a87; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; text-align: center; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('Thank you for completing our survey!', 'nova-simple-surveys'); ?></h1>
                    <p><?php echo esc_html($survey->title); ?></p>
                </div>
                
                <div class="section">
                    <h3><?php _e('Your Results', 'nova-simple-surveys'); ?></h3>
                    <div class="score-display"><?php echo esc_html($submission->total_score); ?></div>
                    <div class="score-meaning"><?php echo $this->get_score_interpretation($submission->total_score, $survey); ?></div>
                </div>
                
                <?php
                $button_config = $survey->button_config;
                if (!empty($button_config['enabled']) && !empty($button_config['text']) && !empty($button_config['url'])):
                ?>
                <div class="cta-section">
                    <p><?php echo wp_kses_post($button_config['description'] ?? ''); ?></p>
                    <a href="<?php echo esc_url($button_config['url']); ?>" class="cta-button">
                        <?php echo esc_html($button_config['text']); ?>
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="section">
                    <h3><?php _e('What\'s Next?', 'nova-simple-surveys'); ?></h3>
                    <p><?php _e('Thank you for taking the time to complete our survey. Your feedback is valuable to us and helps us improve our services.', 'nova-simple-surveys'); ?></p>
                    <p><?php _e('If you have any questions or would like to discuss your results, please don\'t hesitate to contact us.', 'nova-simple-surveys'); ?></p>
                </div>
                
                <div class="footer">
                    <p><?php printf(__('This email was sent from %s', 'nova-simple-surveys'), '<a href="' . esc_url($site_url) . '">' . esc_html($site_name) . '</a>'); ?></p>
                    <p><?php printf(__('Hello %s, this is your personal survey result.', 'nova-simple-surveys'), esc_html($submission->user_name)); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get score interpretation
     *
     * @param float $score Score value
     * @param object $survey Survey object
     * @return string Score interpretation
     */
    private function get_score_interpretation($score, $survey) {
        // This is a basic interpretation - you could make this configurable per survey
        $max_possible = 100; // You might want to calculate this based on questions
        $percentage = ($score / $max_possible) * 100;
        
        if ($percentage >= 80) {
            return __('Excellent! You scored in the top range.', 'nova-simple-surveys');
        } elseif ($percentage >= 60) {
            return __('Good score! You\'re above average.', 'nova-simple-surveys');
        } elseif ($percentage >= 40) {
            return __('Average score. There\'s room for improvement.', 'nova-simple-surveys');
        } else {
            return __('Below average. Consider areas for development.', 'nova-simple-surveys');
        }
    }
    
    /**
     * Send test email
     *
     * @param string $email Email address
     * @return bool True on success, false on failure
     */
    public function send_test_email($email) {
        $options = get_option('nova_surveys_options', array());
        
        $subject = __('Nova Surveys - Test Email', 'nova-simple-surveys');
        $message = $this->get_test_email_template();
        
        $headers = array(
            'From: ' . ($options['email_from_name'] ?? get_bloginfo('name')) . ' <' . ($options['email_from_email'] ?? get_option('admin_email')) . '>',
        );
        
        return wp_mail($email, $subject, $message, $headers);
    }
    
    /**
     * Get test email template
     *
     * @return string HTML email content
     */
    private function get_test_email_template() {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php _e('Test Email', 'nova-simple-surveys'); ?></title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
                .header { background: #0073aa; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; margin: -20px -20px 20px -20px; }
                .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; text-align: center; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('Test Email', 'nova-simple-surveys'); ?></h1>
                    <p><?php _e('Nova Simple Surveys Plugin', 'nova-simple-surveys'); ?></p>
                </div>
                
                <div class="section">
                    <h3><?php _e('Email Configuration Test', 'nova-simple-surveys'); ?></h3>
                    <p><?php _e('If you\'re seeing this email, your email configuration is working correctly!', 'nova-simple-surveys'); ?></p>
                    <p><?php _e('This test email was sent to verify that the Nova Simple Surveys plugin can send emails successfully.', 'nova-simple-surveys'); ?></p>
                </div>
                
                <div class="footer">
                    <p><?php printf(__('This test email was sent from %s', 'nova-simple-surveys'), '<a href="' . esc_url($site_url) . '">' . esc_html($site_name) . '</a>'); ?></p>
                    <p><?php printf(__('Sent at %s', 'nova-simple-surveys'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'))); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
} 