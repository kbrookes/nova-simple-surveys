<?php
/**
 * Submission Manager Class
 *
 * Handles all submission-related database operations
 *
 * @package Nova_Simple_Surveys
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Nova_Surveys_Submission_Manager {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_nova_surveys_submit', array($this, 'handle_ajax_submission'));
        add_action('wp_ajax_nopriv_nova_surveys_submit', array($this, 'handle_ajax_submission'));
    }
    
    /**
     * Handle AJAX survey submission
     */
    public function handle_ajax_submission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nova_surveys_ajax_nonce')) {
            wp_die(__('Security check failed', 'nova-simple-surveys'));
        }
        
        $survey_id = intval($_POST['survey_id']);
        $user_name = sanitize_text_field($_POST['user_name']);
        $user_email = sanitize_email($_POST['user_email']);
        $responses = $_POST['responses'];
        
        // Validate required fields
        if (empty($user_name) || empty($user_email) || !is_email($user_email)) {
            wp_send_json_error(array(
                'message' => __('Please provide valid name and email address.', 'nova-simple-surveys')
            ));
        }
        
        // Process submission
        $submission_id = $this->create_submission($survey_id, $user_name, $user_email, $responses);
        
        if ($submission_id) {
            // Send emails
            $email_handler = new Nova_Surveys_Email_Handler();
            $email_handler->send_admin_notification($submission_id);
            $email_handler->send_user_confirmation($submission_id);
            
            // Get final score and result data
            $submission = $this->get_submission($submission_id);
            
            wp_send_json_success(array(
                'message' => __('Survey submitted successfully!', 'nova-simple-surveys'),
                'submission_id' => $submission_id,
                'total_score' => $submission->total_score,
                'redirect_url' => $this->get_results_url($submission_id)
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to submit survey. Please try again.', 'nova-simple-surveys')
            ));
        }
    }
    
    /**
     * Create a new submission
     *
     * @param int $survey_id Survey ID
     * @param string $user_name User name
     * @param string $user_email User email
     * @param array $responses Question responses
     * @return int|false Submission ID on success, false on failure
     */
    public function create_submission($survey_id, $user_name, $user_email, $responses) {
        global $wpdb;
        
        // Get survey questions for validation and scoring
        $survey_manager = new Nova_Surveys_Survey_Manager();
        $questions = $survey_manager->get_survey_questions($survey_id);
        
        if (empty($questions)) {
            return false;
        }
        
        // Calculate total score
        $total_score = $this->calculate_score($questions, $responses);
        
        // Prepare submission data
        $submission_data = array(
            'survey_id' => intval($survey_id),
            'user_name' => sanitize_text_field($user_name),
            'user_email' => sanitize_email($user_email),
            'total_score' => $total_score,
            'submission_data' => json_encode($responses),
            'ip_address' => $this->get_user_ip()
        );
        
        // Insert submission
        $result = $wpdb->insert(
            $wpdb->prefix . 'nova_survey_submissions',
            $submission_data,
            array('%d', '%s', '%s', '%f', '%s', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        $submission_id = $wpdb->insert_id;
        
        // Insert individual responses
        foreach ($responses as $question_id => $response_value) {
            $question = $this->get_question_by_id($questions, $question_id);
            if ($question) {
                $score_value = $this->calculate_question_score($question, $response_value);
                
                $wpdb->insert(
                    $wpdb->prefix . 'nova_survey_responses',
                    array(
                        'submission_id' => $submission_id,
                        'question_id' => intval($question_id),
                        'response_value' => sanitize_text_field($response_value),
                        'score_value' => $score_value
                    ),
                    array('%d', '%d', '%s', '%f')
                );
            }
        }
        
        return $submission_id;
    }
    
    /**
     * Get submission by ID
     *
     * @param int $submission_id Submission ID
     * @return object|null Submission object or null if not found
     */
    public function get_submission($submission_id) {
        global $wpdb;
        
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nova_survey_submissions WHERE id = %d",
            intval($submission_id)
        ));
        
        if ($submission) {
            $submission->submission_data = json_decode($submission->submission_data, true);
        }
        
        return $submission;
    }
    
    /**
     * Get submissions with optional filtering
     *
     * @param array $args Query arguments
     * @return array Array of submission objects
     */
    public function get_submissions($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'survey_id' => 0,
            'orderby' => 'submitted_at',
            'order' => 'DESC',
            'limit' => -1,
            'offset' => 0,
            'date_from' => '',
            'date_to' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array();
        $where_values = array();
        
        if ($args['survey_id'] > 0) {
            $where[] = 'survey_id = %d';
            $where_values[] = $args['survey_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'submitted_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'submitted_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
        }
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'submitted_at DESC';
        }
        
        $limit = '';
        if ($args['limit'] > 0) {
            $limit = $wpdb->prepare('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        }
        
        $query = "SELECT * FROM {$wpdb->prefix}nova_survey_submissions $where_clause ORDER BY $orderby $limit";
        
        if (!empty($where_values)) {
            $submissions = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $submissions = $wpdb->get_results($query);
        }
        
        // Decode JSON fields
        foreach ($submissions as $submission) {
            $submission->submission_data = json_decode($submission->submission_data, true);
        }
        
        return $submissions;
    }
    
    /**
     * Get submission responses
     *
     * @param int $submission_id Submission ID
     * @return array Array of response objects
     */
    public function get_submission_responses($submission_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, q.question_text, q.question_type 
             FROM {$wpdb->prefix}nova_survey_responses r
             JOIN {$wpdb->prefix}nova_survey_questions q ON r.question_id = q.id
             WHERE r.submission_id = %d
             ORDER BY q.sort_order ASC",
            intval($submission_id)
        ));
    }
    
    /**
     * Delete a submission
     *
     * @param int $submission_id Submission ID
     * @return bool True on success, false on failure
     */
    public function delete_submission($submission_id) {
        global $wpdb;
        
        // Delete responses first
        $wpdb->delete(
            $wpdb->prefix . 'nova_survey_responses',
            array('submission_id' => intval($submission_id)),
            array('%d')
        );
        
        // Delete submission
        $result = $wpdb->delete(
            $wpdb->prefix . 'nova_survey_submissions',
            array('id' => intval($submission_id)),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Calculate total score for submission
     *
     * @param array $questions Survey questions
     * @param array $responses User responses
     * @return float Total score
     */
    private function calculate_score($questions, $responses) {
        $total_score = 0;
        
        foreach ($questions as $question) {
            if (isset($responses[$question->id])) {
                $score = $this->calculate_question_score($question, $responses[$question->id]);
                $total_score += $score;
            }
        }
        
        return $total_score;
    }
    
    /**
     * Calculate score for individual question
     *
     * @param object $question Question object
     * @param mixed $response_value User response
     * @return float Question score
     */
    private function calculate_question_score($question, $response_value) {
        switch ($question->question_type) {
            case 'rating':
                $value = floatval($response_value);
                return max($question->min_score, min($question->max_score, $value));
                
            case 'yes_no':
                return ($response_value === 'yes') ? $question->max_score : $question->min_score;
                
            case 'multiple_choice':
                // For multiple choice, you might have predefined score mappings
                // This is a simplified version
                return floatval($response_value);
                
            default:
                return 0;
        }
    }
    
    /**
     * Get question by ID from questions array
     *
     * @param array $questions Questions array
     * @param int $question_id Question ID
     * @return object|null Question object or null
     */
    private function get_question_by_id($questions, $question_id) {
        foreach ($questions as $question) {
            if ($question->id == $question_id) {
                return $question;
            }
        }
        return null;
    }
    
    /**
     * Get user IP address
     *
     * @return string User IP address
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get results URL for submission
     *
     * @param int $submission_id Submission ID
     * @return string Results URL
     */
    private function get_results_url($submission_id) {
        return add_query_arg(array(
            'nova_survey_results' => 1,
            'submission_id' => $submission_id
        ), home_url());
    }
    
    /**
     * Get submission statistics for a survey
     *
     * @param int $survey_id Survey ID
     * @return array Statistics array
     */
    public function get_survey_statistics($survey_id) {
        global $wpdb;
        
        $stats = array();
        
        // Total submissions
        $stats['total_submissions'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}nova_survey_submissions WHERE survey_id = %d",
            $survey_id
        ));
        
        // Average score
        $stats['average_score'] = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(total_score) FROM {$wpdb->prefix}nova_survey_submissions WHERE survey_id = %d",
            $survey_id
        ));
        
        // Score distribution
        $stats['score_distribution'] = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                FLOOR(total_score/10)*10 as score_range,
                COUNT(*) as count
             FROM {$wpdb->prefix}nova_survey_submissions 
             WHERE survey_id = %d 
             GROUP BY FLOOR(total_score/10)
             ORDER BY score_range",
            $survey_id
        ));
        
        // Recent submissions (last 30 days)
        $stats['recent_submissions'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}nova_survey_submissions 
             WHERE survey_id = %d AND submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $survey_id
        ));
        
        return $stats;
    }
} 