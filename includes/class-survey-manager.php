<?php
/**
 * Survey Manager Class
 *
 * Handles all survey-related database operations
 *
 * @package Nova_Simple_Surveys
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Nova_Surveys_Survey_Manager {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add any initialization hooks here
    }
    
    /**
     * Create a new survey
     *
     * @param array $data Survey data
     * @return int|false Survey ID on success, false on failure
     */
    public function create_survey($data) {
        global $wpdb;
        
        $defaults = array(
            'title' => '',
            'description' => '',
            'intro_enabled' => 0,
            'intro_content' => '',
            'scoring_method' => 'sum',
            'status' => 'draft',
            'colors_config' => json_encode(array()),
            'button_config' => json_encode(array())
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitize data
        $data['title'] = sanitize_text_field($data['title']);
        $data['description'] = sanitize_textarea_field($data['description']);
        $data['intro_content'] = wp_kses_post($data['intro_content']);
        $data['scoring_method'] = sanitize_text_field($data['scoring_method']);
        $data['status'] = sanitize_text_field($data['status']);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'nova_surveys',
            $data,
            array('%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update an existing survey
     *
     * @param int $survey_id Survey ID
     * @param array $data Survey data
     * @return bool True on success, false on failure
     */
    public function update_survey($survey_id, $data) {
        global $wpdb;
        
        // Sanitize data
        if (isset($data['title'])) {
            $data['title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['description'])) {
            $data['description'] = sanitize_textarea_field($data['description']);
        }
        if (isset($data['intro_content'])) {
            $data['intro_content'] = wp_kses_post($data['intro_content']);
        }
        if (isset($data['scoring_method'])) {
            $data['scoring_method'] = sanitize_text_field($data['scoring_method']);
        }
        if (isset($data['status'])) {
            $data['status'] = sanitize_text_field($data['status']);
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'nova_surveys',
            $data,
            array('id' => intval($survey_id)),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a survey and all related data
     *
     * @param int $survey_id Survey ID
     * @return bool True on success, false on failure
     */
    public function delete_survey($survey_id) {
        global $wpdb;
        
        $survey_id = intval($survey_id);
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Delete responses first (foreign key constraint)
            $submission_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}nova_survey_submissions WHERE survey_id = %d",
                $survey_id
            ));
            
            if (!empty($submission_ids)) {
                $placeholders = implode(',', array_fill(0, count($submission_ids), '%d'));
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}nova_survey_responses WHERE submission_id IN ($placeholders)",
                    $submission_ids
                ));
            }
            
            // Delete submissions
            $wpdb->delete(
                $wpdb->prefix . 'nova_survey_submissions',
                array('survey_id' => $survey_id),
                array('%d')
            );
            
            // Delete questions
            $wpdb->delete(
                $wpdb->prefix . 'nova_survey_questions',
                array('survey_id' => $survey_id),
                array('%d')
            );
            
            // Delete survey
            $result = $wpdb->delete(
                $wpdb->prefix . 'nova_surveys',
                array('id' => $survey_id),
                array('%d')
            );
            
            if ($result === false) {
                throw new Exception('Failed to delete survey');
            }
            
            $wpdb->query('COMMIT');
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }
    
    /**
     * Get a survey by ID
     *
     * @param int $survey_id Survey ID
     * @return object|null Survey object or null if not found
     */
    public function get_survey($survey_id) {
        global $wpdb;
        
        $survey = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nova_surveys WHERE id = %d",
            intval($survey_id)
        ));
        
        if ($survey) {
            // Decode JSON fields
            $survey->colors_config = json_decode($survey->colors_config, true);
            $survey->button_config = json_decode($survey->button_config, true);
        }
        
        return $survey;
    }
    
    /**
     * Get all surveys with optional filtering
     *
     * @param array $args Query arguments
     * @return array Array of survey objects
     */
    public function get_surveys($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'all',
            'orderby' => 'updated_at',
            'order' => 'DESC',
            'limit' => -1,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = '';
        $where_values = array();
        
        if ($args['status'] !== 'all') {
            $where = 'WHERE status = %s';
            $where_values[] = $args['status'];
        }
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'updated_at DESC';
        }
        
        $limit = '';
        if ($args['limit'] > 0) {
            $limit = $wpdb->prepare('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        }
        
        $query = "SELECT * FROM {$wpdb->prefix}nova_surveys $where ORDER BY $orderby $limit";
        
        if (!empty($where_values)) {
            $surveys = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $surveys = $wpdb->get_results($query);
        }
        
        // Decode JSON fields for each survey
        foreach ($surveys as $survey) {
            $survey->colors_config = json_decode($survey->colors_config, true);
            $survey->button_config = json_decode($survey->button_config, true);
        }
        
        return $surveys;
    }
    
    /**
     * Get survey questions
     *
     * @param int $survey_id Survey ID
     * @return array Array of question objects
     */
    public function get_survey_questions($survey_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nova_survey_questions 
             WHERE survey_id = %d 
             ORDER BY sort_order ASC",
            intval($survey_id)
        ));
    }
    
    /**
     * Add a question to a survey
     *
     * @param int $survey_id Survey ID
     * @param array $question_data Question data
     * @return int|false Question ID on success, false on failure
     */
    public function add_question($survey_id, $question_data) {
        global $wpdb;
        
        $defaults = array(
            'survey_id' => $survey_id,
            'question_text' => '',
            'question_type' => 'rating',
            'sort_order' => 0,
            'min_score' => 0,
            'max_score' => 10,
            'required' => 1
        );
        
        $question_data = wp_parse_args($question_data, $defaults);
        
        // Sanitize data
        $question_data['question_text'] = sanitize_textarea_field($question_data['question_text']);
        $question_data['question_type'] = sanitize_text_field($question_data['question_type']);
        $question_data['sort_order'] = intval($question_data['sort_order']);
        $question_data['min_score'] = intval($question_data['min_score']);
        $question_data['max_score'] = intval($question_data['max_score']);
        $question_data['required'] = intval($question_data['required']);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'nova_survey_questions',
            $question_data,
            array('%d', '%s', '%s', '%d', '%d', '%d', '%d')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update a question
     *
     * @param int $question_id Question ID
     * @param array $question_data Question data
     * @return bool True on success, false on failure
     */
    public function update_question($question_id, $question_data) {
        global $wpdb;
        
        // Sanitize data
        if (isset($question_data['question_text'])) {
            $question_data['question_text'] = sanitize_textarea_field($question_data['question_text']);
        }
        if (isset($question_data['question_type'])) {
            $question_data['question_type'] = sanitize_text_field($question_data['question_type']);
        }
        if (isset($question_data['sort_order'])) {
            $question_data['sort_order'] = intval($question_data['sort_order']);
        }
        if (isset($question_data['min_score'])) {
            $question_data['min_score'] = intval($question_data['min_score']);
        }
        if (isset($question_data['max_score'])) {
            $question_data['max_score'] = intval($question_data['max_score']);
        }
        if (isset($question_data['required'])) {
            $question_data['required'] = intval($question_data['required']);
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'nova_survey_questions',
            $question_data,
            array('id' => intval($question_id)),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a question
     *
     * @param int $question_id Question ID
     * @return bool True on success, false on failure
     */
    public function delete_question($question_id) {
        global $wpdb;
        
        // Delete related responses first
        $wpdb->delete(
            $wpdb->prefix . 'nova_survey_responses',
            array('question_id' => intval($question_id)),
            array('%d')
        );
        
        // Delete the question
        $result = $wpdb->delete(
            $wpdb->prefix . 'nova_survey_questions',
            array('id' => intval($question_id)),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Duplicate a survey
     *
     * @param int $survey_id Survey ID to duplicate
     * @return int|false New survey ID on success, false on failure
     */
    public function duplicate_survey($survey_id) {
        $original_survey = $this->get_survey($survey_id);
        
        if (!$original_survey) {
            return false;
        }
        
        // Prepare data for new survey
        $new_survey_data = array(
            'title' => $original_survey->title . ' (Copy)',
            'description' => $original_survey->description,
            'intro_enabled' => $original_survey->intro_enabled,
            'intro_content' => $original_survey->intro_content,
            'scoring_method' => $original_survey->scoring_method,
            'status' => 'draft',
            'colors_config' => json_encode($original_survey->colors_config),
            'button_config' => json_encode($original_survey->button_config)
        );
        
        // Create new survey
        $new_survey_id = $this->create_survey($new_survey_data);
        
        if (!$new_survey_id) {
            return false;
        }
        
        // Duplicate questions
        $questions = $this->get_survey_questions($survey_id);
        foreach ($questions as $question) {
            $question_data = array(
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'sort_order' => $question->sort_order,
                'min_score' => $question->min_score,
                'max_score' => $question->max_score,
                'required' => $question->required
            );
            
            $this->add_question($new_survey_id, $question_data);
        }
        
        return $new_survey_id;
    }
} 