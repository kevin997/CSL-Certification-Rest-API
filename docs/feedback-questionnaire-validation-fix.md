# Feedback Questionnaire Validation Fix

## Issue
When saving a questionnaire-type feedback question, the backend validation was rejecting the request with errors like:
```
"questions.0.options.0.text": ["The questions.0.options.0.text field is required when questions.0.options is present."]
```

## Root Cause
The validation rules in `FeedbackContentController.php` were expecting legacy field names (`text`) for all question types, but questionnaire questions use different field names:
- `option_text` instead of `text`
- Additional fields: `subquestion_text`, `answer_option_id`, `points`
- Separate `answer_options` array for the column headers

## Solution
Updated validation rules in both `store()` and `update()` methods to accept questionnaire-specific fields:

### Before (Lines 172-173):
```php
'questions.*.options' => 'required_if:questions.*.question_type,multiple_choice,checkbox,dropdown|array',
'questions.*.options.*.text' => 'required_with:questions.*.options|string',
```

### After (Lines 172-180 in store, 478-486 in update):
```php
'questions.*.options' => 'required_if:questions.*.question_type,multiple_choice,checkbox,dropdown,questionnaire|array',
'questions.*.options.*.text' => 'sometimes|string',
'questions.*.options.*.option_text' => 'sometimes|string',
'questions.*.options.*.subquestion_text' => 'sometimes|nullable|string',
'questions.*.options.*.answer_option_id' => 'sometimes|nullable|integer',
'questions.*.options.*.points' => 'sometimes|nullable|integer',
'questions.*.answer_options' => 'sometimes|array',
'questions.*.answer_options.*.id' => 'sometimes|integer',
'questions.*.answer_options.*.text' => 'sometimes|string',
```

## Changes Made

### File: `/app/Http/Controllers/Api/FeedbackContentController.php`

1. **Line 172**: Added `questionnaire` to `required_if` conditions for options field
2. **Lines 173-180**: Changed validation rules to support both legacy and questionnaire field formats
   - Changed `text` from `required_with` to `sometimes` (optional)
   - Added `option_text` as `sometimes` (used by questionnaire)
   - Added `subquestion_text` as `sometimes|nullable`
   - Added `answer_option_id` as `sometimes|nullable|integer`
   - Added `points` as `sometimes|nullable|integer`
   - Added `answer_options` array validation
   - Added `answer_options.*.id` and `answer_options.*.text` validation

3. **Lines 478-486**: Same changes applied to `update()` method

## Field Usage by Question Type

### Legacy Question Types (multiple_choice, checkbox, dropdown)
```php
'options' => [
    ['text' => 'Option 1'],
    ['text' => 'Option 2']
]
```

### Questionnaire Type
```php
'answer_options' => [
    ['id' => 1, 'text' => 'Strongly Agree'],
    ['id' => 2, 'text' => 'Agree'],
    ['id' => 3, 'text' => 'Neutral']
],
'options' => [
    [
        'option_text' => 'Strongly Agree',
        'subquestion_text' => 'Course quality',
        'answer_option_id' => 1,
        'points' => 5,
        'order' => 0
    ],
    [
        'option_text' => 'Agree',
        'subquestion_text' => 'Course quality',
        'answer_option_id' => 2,
        'points' => 4,
        'order' => 1
    ],
    // ... more combinations of subquestions × answer options
]
```

## Testing
After this fix, questionnaire questions can be saved successfully with the matrix structure:
- Answer options (columns) stored in `answer_options` JSON field
- Subquestion assignments (rows) stored in `feedback_question_options` table

## Additional Fix: Environment ID Issue

### Issue
After fixing validation, another error occurred:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'environment_id' in 'field list'
```

### Root Cause
The `FeedbackQuestionOption` model was using the `BelongsToEnvironment` trait, which automatically tries to set `environment_id` on create. However, the `feedback_question_options` table doesn't have this column (and doesn't need it - environment scoping is inherited through the feedback_question relationship).

### Solution
Removed the `BelongsToEnvironment` trait and `environment_id` from the model:

**File: `/app/Models/FeedbackQuestionOption.php`**

**Before:**
```php
use App\Traits\BelongsToEnvironment;
// ...
use HasFactory, SoftDeletes, HasCreatedBy, BelongsToEnvironment;
// ...
protected $fillable = [
    // ...
    'environment_id',
];
```

**After:**
```php
// Removed BelongsToEnvironment trait import
use HasFactory, SoftDeletes, HasCreatedBy;
// ...
protected $fillable = [
    // ... (removed 'environment_id')
];
```

## Date Fixed
October 29, 2025

## Related Files
- `/app/Http/Controllers/Api/FeedbackContentController.php` - Validation rules updated
- `/app/Models/FeedbackQuestionOption.php` - Removed environment scoping
- `/docs/feedback-questionnaire-implementation-complete.md` - Original implementation doc
- `/docs/feedback-questionnaire-submissions-dashboard.md` - Submissions dashboard doc
