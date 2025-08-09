# WordPress Survey Plugin - Build Instructions & Progress Tracker

## 🎯 Project Overview
Build a WordPress plugin that enables site admins to create scored surveys (NPS-style) with lead capture, customizable results display, and comprehensive email notifications.

## 📋 Core Requirements Checklist

### Plugin Foundation
- [ ] Plugin header and basic structure
- [ ] Database schema setup (surveys, submissions, questions)
- [ ] Activation/deactivation hooks
- [ ] GitUpdater compatibility setup
- [ ] Security and sanitization framework

### Admin Interface
- [ ] Main admin menu page
- [ ] Survey creation/editing interface
- [ ] Question management (add/remove/reorder)
- [ ] Survey settings (colors, intro page, button config)
- [ ] Survey status toggle (publish/unpublish)
- [ ] Submissions dashboard with filtering
- [ ] Individual submission detail view

### Frontend Survey Display
- [ ] Shortcode registration `[survey_display id="x"]`
- [ ] Optional intro page rendering
- [ ] Multi-step survey form (questions → lead capture → results)
- [ ] Progressive form validation
- [ ] AJAX form submission
- [ ] Customizable color scheme application
- [ ] Mobile-responsive design

### Scoring & Results System
- [ ] Configurable scoring logic per survey
- [ ] Score calculation engine
- [ ] Results interpretation system (score ranges → meanings)
- [ ] Attractive results page with score display
- [ ] Configurable CTA button on results

### Email System
- [ ] Admin notification email (lead + answers + score)
- [ ] User confirmation email (score + meaning + CTA)
- [ ] HTML email templates
- [ ] Email customization options in admin
- [ ] Email sending with error handling

### Data Management
- [ ] Survey CRUD operations
- [ ] Submission storage and retrieval
- [ ] Data export functionality
- [ ] Database cleanup on uninstall

## 🏗️ Technical Architecture

### File Structure
```
wp-survey-plugin/
├── wp-survey-plugin.php (main plugin file)
├── includes/
│   ├── class-survey-plugin.php (main plugin class)
│   ├── class-survey-manager.php (survey CRUD)
│   ├── class-submission-manager.php (submission handling)
│   ├── class-email-handler.php (email functionality)
│   ├── class-frontend-display.php (shortcode & display)
│   └── class-admin-interface.php (admin pages)
├── admin/
│   ├── css/admin-styles.css
│   ├── js/admin-scripts.js
│   └── partials/ (admin page templates)
├── public/
│   ├── css/survey-styles.css
│   ├── js/survey-scripts.js
│   └── partials/ (frontend templates)
├── templates/
│   ├── emails/ (HTML email templates)
│   └── survey-parts/ (survey components)
└── assets/
    └── images/
```

### Database Schema
```sql
-- Surveys table
wp_survey_surveys:
- id, title, description, intro_enabled, intro_content
- scoring_method, status, colors_config, button_config
- created_at, updated_at

-- Questions table  
wp_survey_questions:
- id, survey_id, question_text, question_type, sort_order
- min_score, max_score, required

-- Submissions table
wp_survey_submissions:
- id, survey_id, user_name, user_email, total_score
- submission_data (JSON), submitted_at, ip_address

-- Question Responses table
wp_survey_responses:
- id, submission_id, question_id, response_value, score_value
```

## 🎨 Frontend Implementation Strategy

### Survey Flow
1. **Intro Page** (if enabled) → Start Survey button
2. **Questions Page** → Progressive form with validation
3. **Lead Capture** → Name/Email required before results
4. **Results Page** → Score + interpretation + CTA button

### Styling Approach
- Use CSS custom properties for dynamic theming
- Provide default attractive styles with WordPress admin color picker integration
- Support for theme class override option
- Mobile-first responsive design

### JavaScript Functionality
- AJAX form submission with loading states
- Form validation with user-friendly error messages
- Smooth transitions between survey steps
- Progress indicator for multi-question surveys

## 📧 Email Templates Strategy

### Admin Email Template
```html
<h2>New Survey Submission: {survey_title}</h2>
<div class="lead-info">
  <h3>Contact Information</h3>
  <p><strong>Name:</strong> {user_name}</p>
  <p><strong>Email:</strong> {user_email}</p>
</div>
<div class="score-summary">
  <h3>Final Score: {total_score}</h3>
  <p>{score_interpretation}</p>
</div>
<div class="responses">
  <h3>Individual Responses</h3>
  {questions_and_answers}
</div>
```

### User Email Template
```html
<h2>Thank you for completing our survey!</h2>
<div class="score-result">
  <h3>Your Score: {total_score}</h3>
  <p>{score_meaning}</p>
</div>
<div class="cta-section">
  {configurable_content}
  <a href="{button_link}" class="cta-button">{button_text}</a>
</div>
```

## 🔧 Development Phases

### Phase 1: Foundation (Priority 1)
- [ ] Plugin structure and activation
- [ ] Database schema creation
- [ ] Basic admin menu setup
- [ ] Security framework implementation

### Phase 2: Core Admin Interface (Priority 1)
- [ ] Survey creation form
- [ ] Question management interface
- [ ] Basic settings (colors, button config)
- [ ] Survey status management

### Phase 3: Frontend Display (Priority 2)
- [ ] Shortcode implementation
- [ ] Survey rendering with custom styles
- [ ] Form validation and AJAX submission
- [ ] Results page display

### Phase 4: Email System (Priority 2)
- [ ] Email template engine
- [ ] Admin and user notification setup
- [ ] HTML email formatting
- [ ] Email customization options

### Phase 5: Data Management (Priority 3)
- [ ] Submissions dashboard
- [ ] Data export functionality
- [ ] Reporting and analytics
- [ ] Performance optimization

### Phase 6: Polish & GitUpdater (Priority 3)
- [ ] GitUpdater integration
- [ ] Code cleanup and documentation
- [ ] Testing and bug fixes
- [ ] User documentation

## 🎯 Key Implementation Notes for LLM

### WordPress Best Practices
- Use WordPress coding standards and naming conventions
- Implement proper nonce verification for all forms
- Sanitize all input data and escape all output
- Use WordPress database API ($wpdb) for all database operations
- Follow plugin development security guidelines

### User Experience Focus
- Make the admin interface intuitive with clear labels
- Provide helpful tooltips and descriptions
- Implement proper error handling with user-friendly messages
- Ensure all interactions provide clear feedback
- Design for accessibility (ARIA labels, keyboard navigation)

### Performance Considerations
- Use WordPress transients for caching where appropriate
- Minimize database queries with proper indexing
- Load scripts/styles only when needed
- Optimize for mobile devices

### Extensibility
- Use WordPress hooks and filters throughout
- Create well-documented action/filter hooks for other developers
- Design database schema to accommodate future features
- Keep functions modular and reusable

## 🚀 Testing Strategy
- Test with various WordPress themes
- Test with different PHP versions (7.4+)
- Test email functionality with different mail configurations
- Test responsive design on various devices
- Test with high volume of submissions

## 📝 Progress Tracking
Use this section to track completed features and note any deviations from the original plan:

### Completed:
- [ ] Initial plugin structure
- [ ] Database setup
- [ ] Admin interface foundation
- [ ] Survey CRUD operations
- [ ] Frontend display system
- [ ] Email system
- [ ] GitUpdater integration

### Current Focus:
*Update this as you progress through development*

### Issues/Deviations:
*Note any challenges or changes to the original specification*

### Next Steps:
*Always keep 2-3 concrete next steps listed here*

---

## 🎨 Visual Design Inspiration
Think modern, clean survey tools like Typeform or Google Forms, but with WordPress admin aesthetic integration. Focus on:
- Clean typography with good hierarchy
- Subtle animations for engagement
- Clear progress indicators
- Attractive score visualization
- Professional email templates

## 🔐 Security Checklist
- [ ] Nonce verification on all forms
- [ ] Input sanitization using WordPress functions
- [ ] Output escaping for all dynamic content
- [ ] Capability checks for admin functions
- [ ] SQL injection prevention
- [ ] CSRF protection
- [ ] File upload validation (if applicable)

This document should be your north star throughout development. Update the progress tracking section as you complete features, and refer back to the requirements checklist to ensure nothing is missed.
