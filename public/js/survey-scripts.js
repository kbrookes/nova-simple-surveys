/**
 * Nova Surveys - Frontend JavaScript
 *
 * @package Nova_Simple_Surveys
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initSurveyFunctionality();
    });

    function initSurveyFunctionality() {
        // Initialize all surveys on the page
        $('.nova-survey-container').each(function() {
            var $container = $(this);
            var surveyId = $container.data('survey-id');
            
            if (surveyId) {
                initSingleSurvey($container);
            }
        });
    }

    function initSingleSurvey($container) {
        var $form = $container.find('.nova-survey-form');
        var $steps = $container.find('.nova-survey-step');
        var currentStep = 1;
        var totalSteps = $steps.length;

        // Handle intro page start button
        $container.find('#start-survey').on('click', function() {
            $container.find('.nova-survey-intro').hide();
            $container.find('.nova-survey-form-container').show();
            updateProgress(1, totalSteps);
        });

        // Handle next step
        $container.on('click', '.next-step', function(e) {
            e.preventDefault();
            
            if (validateCurrentStep(currentStep, $container)) {
                if (currentStep < totalSteps) {
                    showStep(currentStep + 1, $container);
                    currentStep++;
                    updateProgress(currentStep, totalSteps);
                }
            }
        });

        // Handle previous step
        $container.on('click', '.prev-step', function(e) {
            e.preventDefault();
            
            if (currentStep > 1) {
                showStep(currentStep - 1, $container);
                currentStep--;
                updateProgress(currentStep, totalSteps);
            }
        });

        // Handle rating button clicks
        $container.on('click', '.rating-option', function() {
            $(this).closest('.rating-buttons').find('.rating-option').removeClass('selected');
            $(this).addClass('selected');
        });

        // Handle yes/no and multiple choice clicks
        $container.on('click', '.yes-no-option, .multiple-choice-option', function() {
            $(this).closest('.yes-no-options, .multiple-choice-options').find('.yes-no-option, .multiple-choice-option').removeClass('selected');
            $(this).addClass('selected');
        });

        // Handle form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            
            if (validateCurrentStep(currentStep, $container)) {
                submitSurvey($container);
            }
        });

        // Initialize first step
        showStep(1, $container);
        updateProgress(1, totalSteps);
    }

    function showStep(stepNumber, $container) {
        $container.find('.nova-survey-step').hide();
        $container.find('.nova-survey-step[data-step="' + stepNumber + '"]').show();
    }

    function updateProgress(current, total) {
        var percentage = (current / total) * 100;
        $('.nova-survey-progress-fill').css('width', percentage + '%');
        $('.nova-survey-progress-text .current-step').text(current);
        $('.nova-survey-progress-text .total-steps').text(total);
    }

    function validateCurrentStep(stepNumber, $container) {
        var $currentStep = $container.find('.nova-survey-step[data-step="' + stepNumber + '"]');
        var isValid = true;
        var errorMessage = '';

        // Clear previous errors
        $currentStep.find('.error-message').remove();

        // Check required fields based on step type
        if ($currentStep.hasClass('nova-survey-lead-capture')) {
            // Validate lead capture fields
            var name = $currentStep.find('input[name="user_name"]').val().trim();
            var email = $currentStep.find('input[name="user_email"]').val().trim();

            if (!name) {
                isValid = false;
                errorMessage = nova_surveys_ajax.strings.required_field;
                showFieldError($currentStep.find('input[name="user_name"]'), errorMessage);
            }

            if (!email) {
                isValid = false;
                errorMessage = nova_surveys_ajax.strings.required_field;
                showFieldError($currentStep.find('input[name="user_email"]'), errorMessage);
            } else if (!isValidEmail(email)) {
                isValid = false;
                errorMessage = nova_surveys_ajax.strings.invalid_email;
                showFieldError($currentStep.find('input[name="user_email"]'), errorMessage);
            }
        } else {
            // Validate question step
            var $requiredInputs = $currentStep.find('input[required], select[required], textarea[required]');
            
            $requiredInputs.each(function() {
                var $input = $(this);
                var inputType = $input.attr('type');
                
                if (inputType === 'radio') {
                    var name = $input.attr('name');
                    if (!$currentStep.find('input[name="' + name + '"]:checked').length) {
                        isValid = false;
                        errorMessage = nova_surveys_ajax.strings.required_field;
                        showFieldError($input.closest('.question-input'), errorMessage);
                    }
                } else {
                    if (!$input.val().trim()) {
                        isValid = false;
                        errorMessage = nova_surveys_ajax.strings.required_field;
                        showFieldError($input, errorMessage);
                    }
                }
            });
        }

        return isValid;
    }

    function showFieldError($field, message) {
        $field.addClass('error');
        
        if (!$field.siblings('.error-message').length) {
            $field.after('<div class="error-message">' + message + '</div>');
        }
    }

    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function submitSurvey($container) {
        var $form = $container.find('.nova-survey-form');
        var $loading = $container.find('.nova-survey-loading');
        var $messages = $container.find('.nova-survey-messages');

        // Show loading state
        $form.hide();
        $loading.show();
        $messages.empty();

        // Collect form data
        var formData = {
            action: 'nova_surveys_submit',
            nonce: nova_surveys_ajax.nonce,
            survey_id: $container.data('survey-id'),
            user_name: $form.find('input[name="user_name"]').val(),
            user_email: $form.find('input[name="user_email"]').val(),
            responses: {}
        };

        // Collect question responses
        $form.find('input[name^="responses"]:checked, select[name^="responses"], textarea[name^="responses"]').each(function() {
            var $input = $(this);
            var name = $input.attr('name');
            var matches = name.match(/responses\[(\d+)\]/);
            
            if (matches) {
                var questionId = matches[1];
                formData.responses[questionId] = $input.val();
            }
        });

        // Submit via AJAX
        $.ajax({
            url: nova_surveys_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Redirect to results page
                    if (response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        showSuccessMessage($messages, response.data.message);
                        $loading.hide();
                    }
                } else {
                    showErrorMessage($messages, response.data.message);
                    $loading.hide();
                    $form.show();
                }
            },
            error: function() {
                showErrorMessage($messages, nova_surveys_ajax.strings.error);
                $loading.hide();
                $form.show();
            }
        });
    }

    function showSuccessMessage($container, message) {
        $container.html('<div class="nova-survey-message nova-survey-success">' + message + '</div>');
    }

    function showErrorMessage($container, message) {
        $container.html('<div class="nova-survey-message nova-survey-error">' + message + '</div>');
    }

    // Additional utility functions
    function animateProgress(percentage) {
        $('.nova-survey-progress-fill').animate({
            width: percentage + '%'
        }, 300);
    }

    // Keyboard navigation support
    $(document).on('keydown', function(e) {
        if ($('.nova-survey-container').length) {
            if (e.key === 'Enter' && !$(e.target).is('textarea')) {
                e.preventDefault();
                $('.nova-survey-step:visible .next-step, .nova-survey-step:visible .submit-survey').first().click();
            }
        }
    });

    // Auto-advance for single-choice questions (optional feature)
    function enableAutoAdvance($container) {
        $container.on('change', 'input[type="radio"]', function() {
            var $step = $(this).closest('.nova-survey-step');
            
            // Auto-advance after a short delay for better UX
            setTimeout(function() {
                if ($step.find('.next-step').length) {
                    $step.find('.next-step').click();
                }
            }, 500);
        });
    }

    // Accessibility improvements
    function enhanceAccessibility() {
        // Add ARIA labels and roles
        $('.nova-survey-container').attr('role', 'form');
        $('.nova-survey-step').attr('role', 'group');
        $('.nova-survey-progress').attr('role', 'progressbar');
        
        // Update progress bar accessibility
        $('.nova-survey-progress').attr('aria-valuenow', function() {
            var current = parseInt($('.current-step').text());
            var total = parseInt($('.total-steps').text());
            return Math.round((current / total) * 100);
        });
    }

    // Initialize accessibility enhancements
    enhanceAccessibility();

})(jQuery); 