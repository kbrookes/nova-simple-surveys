/**
 * Nova Surveys - Admin JavaScript
 *
 * @package Nova_Simple_Surveys
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initAdminFunctionality();
    });

    function initAdminFunctionality() {
        initColorPickers();
        initQuestionManagement();
        initSurveyForm();
        initBulkActions();
        initTooltips();
    }

    // Color picker initialization
    function initColorPickers() {
        if ($.fn.wpColorPicker) {
            $('.color-picker').wpColorPicker({
                change: function(event, ui) {
                    // Optional: Real-time preview updates
                    updatePreview();
                }
            });
        }
    }

    // Question management functionality
    function initQuestionManagement() {
        var questionIndex = $('.question-row').length;

        // Add new question
        $('#add-question').on('click', function(e) {
            e.preventDefault();
            addNewQuestion();
        });

        // Remove question
        $(document).on('click', '.remove-question', function(e) {
            e.preventDefault();
            
            if (confirm(nova_surveys_admin.strings.confirm_delete)) {
                $(this).closest('.question-row').fadeOut(300, function() {
                    $(this).remove();
                    updateQuestionNumbers();
                });
            }
        });

        // Question type change
        $(document).on('change', 'select[name*="question_type"]', function() {
            var $this = $(this);
            var type = $this.val();
            var $row = $this.closest('.question-row');
            
            toggleScoreFields($row, type);
        });

        // Make questions sortable
        if ($.fn.sortable) {
            $('#questions-container').sortable({
                handle: '.question-handle',
                placeholder: 'question-placeholder',
                update: function(event, ui) {
                    updateQuestionNumbers();
                    updateSortOrder();
                }
            });
        }

        function addNewQuestion() {
            var template = $('#question-template').html();
            
            if (!template) {
                console.error('Question template not found');
                return;
            }

            // Replace placeholder with actual index
            template = template.replace(/\{\{INDEX\}\}/g, questionIndex);
            
            var $newQuestion = $(template);
            $('#questions-container').append($newQuestion);
            
            // Initialize any special fields in the new question
            initQuestionRow($newQuestion);
            
            questionIndex++;
            updateQuestionNumbers();
            
            // Smooth scroll to new question
            $('html, body').animate({
                scrollTop: $newQuestion.offset().top - 100
            }, 500);
        }

        function initQuestionRow($row) {
            // Initialize color pickers if any
            $row.find('.color-picker').wpColorPicker();
            
            // Set up any other field-specific functionality
            toggleScoreFields($row, $row.find('select[name*="question_type"]').val());
        }

        function toggleScoreFields($row, type) {
            var $scoreFields = $row.find('.score-fields');
            
            if (type === 'rating') {
                $scoreFields.show();
            } else {
                $scoreFields.hide();
            }
        }

        function updateQuestionNumbers() {
            $('#questions-container .question-row').each(function(index) {
                $(this).find('.question-number').text(index + 1);
                $(this).find('.question-title').text('Question ' + (index + 1));
            });
        }

        function updateSortOrder() {
            $('#questions-container .question-row').each(function(index) {
                $(this).find('.sort-order').val(index);
            });
        }
    }

    // Survey form functionality
    function initSurveyForm() {
        // Handle intro content toggle
        $('input[name="intro_enabled"]').on('change', function() {
            var $introContent = $('#intro-content');
            
            if ($(this).is(':checked')) {
                $introContent.slideDown();
            } else {
                $introContent.slideUp();
            }
        });

        // Form validation
        $('#nova-survey-form').on('submit', function(e) {
            if (!validateSurveyForm()) {
                e.preventDefault();
                return false;
            }
        });

        // Auto-save functionality (optional)
        var autoSaveTimer;
        $('#nova-survey-form input, #nova-survey-form textarea, #nova-survey-form select').on('change', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                autoSaveSurvey();
            }, 5000); // Auto-save after 5 seconds of inactivity
        });

        function validateSurveyForm() {
            var isValid = true;
            var errors = [];

            // Validate title
            var title = $('#survey-title').val().trim();
            if (!title) {
                errors.push('Survey title is required.');
                $('#survey-title').addClass('error');
                isValid = false;
            } else {
                $('#survey-title').removeClass('error');
            }

            // Validate questions
            var questionCount = $('.question-row').length;
            if (questionCount === 0) {
                errors.push('At least one question is required.');
                isValid = false;
            }

            // Validate each question
            $('.question-row').each(function(index) {
                var $row = $(this);
                var questionText = $row.find('textarea[name*="question_text"]').val().trim();
                
                if (!questionText) {
                    errors.push('Question ' + (index + 1) + ' text is required.');
                    $row.find('textarea[name*="question_text"]').addClass('error');
                    isValid = false;
                } else {
                    $row.find('textarea[name*="question_text"]').removeClass('error');
                }
            });

            // Display errors
            if (!isValid) {
                showAdminNotice('error', errors.join('<br>'));
            }

            return isValid;
        }

        function autoSaveSurvey() {
            // Implementation for auto-save functionality
            console.log('Auto-saving survey...');
            // You could implement AJAX auto-save here
        }
    }

    // Bulk actions functionality
    function initBulkActions() {
        // Handle bulk action form submission
        $('#bulk-action-selector-top').on('change', function() {
            var action = $(this).val();
            
            if (action === 'delete') {
                // Add confirmation for delete action
                $(this).closest('form').on('submit', function(e) {
                    if (!confirm('Are you sure you want to delete the selected surveys?')) {
                        e.preventDefault();
                    }
                });
            }
        });

        // Select all checkboxes
        $('#cb-select-all-1').on('change', function() {
            var isChecked = $(this).is(':checked');
            $('input[name="survey[]"]').prop('checked', isChecked);
        });

        // Individual checkbox changes
        $(document).on('change', 'input[name="survey[]"]', function() {
            var totalCheckboxes = $('input[name="survey[]"]').length;
            var checkedCheckboxes = $('input[name="survey[]"]:checked').length;
            
            $('#cb-select-all-1').prop('checked', totalCheckboxes === checkedCheckboxes);
        });
    }

    // Tooltip functionality
    function initTooltips() {
        // Add tooltips for help text
        $('[data-tooltip]').each(function() {
            var $this = $(this);
            var tooltipText = $this.data('tooltip');
            
            $this.hover(
                function() {
                    $('<div class="admin-tooltip">' + tooltipText + '</div>')
                        .appendTo('body')
                        .fadeIn('fast');
                },
                function() {
                    $('.admin-tooltip').remove();
                }
            ).mousemove(function(e) {
                $('.admin-tooltip').css({
                    top: e.pageY + 10,
                    left: e.pageX + 10
                });
            });
        });
    }

    // Utility functions
    function showAdminNotice(type, message) {
        var noticeClass = 'notice-' + type;
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
    }

    function updatePreview() {
        // Optional: Real-time preview functionality
        console.log('Updating preview...');
    }

    // AJAX handlers
    function handleAjaxResponse(response, successCallback, errorCallback) {
        if (response.success) {
            if (typeof successCallback === 'function') {
                successCallback(response.data);
            }
            showAdminNotice('success', response.data.message || 'Operation completed successfully.');
        } else {
            if (typeof errorCallback === 'function') {
                errorCallback(response.data);
            }
            showAdminNotice('error', response.data.message || 'An error occurred.');
        }
    }

    // Survey deletion via AJAX
    $(document).on('click', '.delete-survey-ajax', function(e) {
        e.preventDefault();
        
        if (!confirm(nova_surveys_admin.strings.confirm_delete)) {
            return;
        }

        var surveyId = $(this).data('survey-id');
        var $row = $(this).closest('tr');

        $.ajax({
            url: nova_surveys_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'nova_surveys_delete_survey',
                survey_id: surveyId,
                nonce: nova_surveys_admin.nonce
            },
            success: function(response) {
                handleAjaxResponse(response, function() {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                });
            },
            error: function() {
                showAdminNotice('error', 'Failed to delete survey.');
            }
        });
    });

    // Media uploader for survey images (if needed)
    function initMediaUploader() {
        var file_frame;

        $(document).on('click', '.upload-image-button', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $input = $button.siblings('input[type="hidden"]');
            var $preview = $button.siblings('.image-preview');

            // If the media frame already exists, reopen it
            if (file_frame) {
                file_frame.open();
                return;
            }

            // Create the media frame
            file_frame = wp.media.frames.file_frame = wp.media({
                title: 'Select Image',
                button: {
                    text: 'Use Image'
                },
                multiple: false
            });

            // When an image is selected, run a callback
            file_frame.on('select', function() {
                var attachment = file_frame.state().get('selection').first().toJSON();
                
                $input.val(attachment.id);
                $preview.html('<img src="' + attachment.url + '" style="max-width: 150px; height: auto;">');
                $button.text('Change Image');
            });

            // Open the modal
            file_frame.open();
        });
    }

    // Initialize media uploader if wp.media is available
    if (typeof wp !== 'undefined' && wp.media) {
        initMediaUploader();
    }

    // Tab functionality for admin pages
    function initTabs() {
        $('.nav-tab-wrapper').on('click', '.nav-tab', function(e) {
            e.preventDefault();
            
            var $this = $(this);
            var target = $this.attr('href');
            
            // Update active tab
            $this.siblings('.nav-tab').removeClass('nav-tab-active');
            $this.addClass('nav-tab-active');
            
            // Show corresponding content
            $('.tab-content').hide();
            $(target).show();
        });

        // Show first tab by default
        $('.nav-tab-wrapper .nav-tab:first').click();
    }

    // Initialize tabs if they exist
    if ($('.nav-tab-wrapper').length) {
        initTabs();
    }

    // Form field dependencies
    function initFieldDependencies() {
        $('[data-depends-on]').each(function() {
            var $field = $(this);
            var dependsOn = $field.data('depends-on');
            var dependsValue = $field.data('depends-value');
            var $dependencyField = $('[name="' + dependsOn + '"]');
            
            function toggleField() {
                var currentValue = $dependencyField.val();
                
                if (currentValue === dependsValue) {
                    $field.show();
                } else {
                    $field.hide();
                }
            }
            
            $dependencyField.on('change', toggleField);
            toggleField(); // Initial state
        });
    }

    // Initialize field dependencies
    initFieldDependencies();

})(jQuery); 