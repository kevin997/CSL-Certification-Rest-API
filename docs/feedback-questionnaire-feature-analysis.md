# Feedback Questionnaire Feature - Feasibility Analysis

## Executive Summary

**Request:** Add questionnaire-style question functionality (matrix-style with subquestions and answer options) to the Feedback content type, similar to how it's implemented in the Assessment quiz question type.

**Verdict:** ✅ **FEASIBLE** - This feature can be implemented with database schema updates and code additions to both backend and frontend.

**Complexity:** Medium - Requires database migration, model updates, controller logic, and frontend UI changes.

**Estimated Effort:** 3-4 days

---

## Current System Analysis

### Questionnaire Question Type (Assessment)

**Location:** Assessment quiz questions (`quiz_questions` table)

**Database Structure:**
```sql
quiz_questions table:
- id
- quiz_content_id (FK)
- question
- question_text
- question_type (includes 'questionnaire')
- ... other fields

quiz_question_options table:
- id
- quiz_question_id (FK)
- option_text
- is_correct
- feedback
- order
- subquestion_text (nullable) ← KEY FOR QUESTIONNAIRE
- answer_option_id (nullable) ← KEY FOR QUESTIONNAIRE
- position (nullable) ← for hotspot
- match_text (nullable) ← for matching
```

**How Questionnaire Works:**
1. **Answer Options** - Global options that respondents can select (stored as JSON array in question.answer_options)
2. **Subquestions** - Individual sub-items to be answered (stored in quiz_question_options with subquestion_text)
3. **Assignments** - Links between subquestions and answer options (stored in quiz_question_options via answer_option_id and subquestion_text grouping)

**Example Data Structure:**
```json
{
  "question_type": "questionnaire",
  "question_text": "Rate our services",
  "answer_options": [
    {"id": 1, "text": "Excellent"},
    {"id": 2, "text": "Good"},
    {"id": 3, "text": "Fair"},
    {"id": 4, "text": "Poor"}
  ],
  "options": [
    {
      "subquestion_text": "Quality of service",
      "answer_option_id": 1,
      "points": 5
    },
    {
      "subquestion_text": "Quality of service",
      "answer_option_id": 2,
      "points": 3
    },
    // ... more assignments for this subquestion
    {
      "subquestion_text": "Staff responsiveness",
      "answer_option_id": 1,
      "points": 5
    }
    // ... etc
  ]
}
```

**Model Accessor Methods** (`QuizQuestion.php` lines 88-142):
```php
// Groups options by subquestion_text to create subquestions
public function getSubquestionsAttribute()

// Gets unique answer options from quiz_question_options
public function getAnswerOptionsAttribute()
```

### Feedback Question Type (Current)

**Location:** Feedback questions (`feedback_questions` table)

**Database Structure:**
```sql
feedback_contents table:
- id
- title
- description
- feedback_type ('360', 'questionnaire', 'form', 'survey')
- is_anonymous
- allow_multiple_submissions
- start_date, end_date
- created_by
- timestamps

feedback_questions table:
- id
- feedback_content_id (FK)
- question_text
- question_type ('text', 'rating', 'multiple_choice', 'checkbox', 'dropdown')
- options (JSON) ← Simple array of {text: string}
- is_required
- order
- created_by
- timestamps
```

**Current Question Types:**
- **text** - Open-ended text input
- **rating** - 1-5 star rating
- **multiple_choice** - Single selection from options
- **checkbox** - Multiple selections from options
- **dropdown** - Single selection via dropdown

**Current Options Structure:**
```json
{
  "options": [
    {"text": "Option 1"},
    {"text": "Option 2"},
    {"text": "Option 3"}
  ]
}
```

**No support for:**
- Subquestions
- Answer option assignments
- Matrix-style question layout
- Points/scoring per answer

---

## Gap Analysis

### What's Missing in Feedback Questions

1. **Database Fields:**
   - ❌ No `subquestion_text` field
   - ❌ No `answer_option_id` field
   - ❌ No separate table for feedback question options (options stored as JSON)
   - ❌ No `points` field for scoring

2. **Model Logic:**
   - ❌ No accessor methods for subquestions/answer options
   - ❌ No support for questionnaire-style data structure

3. **Frontend Components:**
   - ❌ No matrix-style question editor
   - ❌ No subquestion management UI
   - ❌ No answer option assignment interface

4. **Backend Validation:**
   - ❌ No validation for questionnaire data structure
   - ❌ No controller logic for questionnaire CRUD operations

---

## Proposed Solution

### Option 1: Extend Existing feedback_questions Table (RECOMMENDED)

**Approach:** Add new fields to `feedback_questions` table and create a separate `feedback_question_options` table, mirroring the `quiz_question_options` structure.

**Pros:**
- ✅ Minimal database changes
- ✅ Backward compatible with existing feedback questions
- ✅ Can reuse questionnaire component logic from assessments
- ✅ Keeps all feedback questions in one table

**Cons:**
- ⚠️ JSON `options` field becomes redundant for questionnaire types
- ⚠️ Need to handle both JSON options (legacy) and relational options (new)

**Database Changes Required:**

#### New Table: `feedback_question_options`
```php
Schema::create('feedback_question_options', function (Blueprint $table) {
    $table->id();
    $table->foreignId('feedback_question_id')
        ->constrained('feedback_questions')
        ->onDelete('cascade');
    $table->text('option_text');
    $table->text('subquestion_text')->nullable();
    $table->integer('answer_option_id')->nullable();
    $table->integer('points')->nullable()->default(0);
    $table->integer('order')->default(0);
    $table->timestamps();
    $table->softDeletes();

    // Indexes
    $table->index(['feedback_question_id', 'order']);
    $table->index('subquestion_text');
    $table->index('answer_option_id');
});
```

#### Update `feedback_questions` Table
```php
Schema::table('feedback_questions', function (Blueprint $table) {
    // Add new question_type option: 'questionnaire'
    // No schema change needed, just add to validation rules

    // Optionally add answer_options JSON field for storing global answer options
    $table->json('answer_options')->nullable()->after('options');
});
```

### Option 2: Create Separate feedback_questionnaire_questions Table

**Approach:** Create a dedicated table for questionnaire-style feedback questions.

**Pros:**
- ✅ Clean separation of concerns
- ✅ No mixing of different data structures

**Cons:**
- ❌ More complex queries (need to union from two tables)
- ❌ More complex frontend logic (two different question types)
- ❌ Harder to manage question ordering across types
- ❌ More code duplication

**Verdict:** Not recommended due to added complexity.

---

## Implementation Plan - Option 1 (RECOMMENDED)

### Phase 1: Database Layer

#### Step 1.1: Create Migration for feedback_question_options Table
**File:** `database/migrations/YYYY_MM_DD_HHMMSS_create_feedback_question_options_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_question_id')
                ->constrained('feedback_questions')
                ->onDelete('cascade')
                ->comment('The feedback question this option belongs to');

            $table->text('option_text')->comment('The option text displayed to respondents');

            // Questionnaire-specific fields
            $table->longText('subquestion_text')->nullable()
                ->comment('For questionnaire type: the subquestion text');
            $table->integer('answer_option_id')->nullable()
                ->comment('For questionnaire type: links to answer option');
            $table->integer('points')->nullable()->default(0)
                ->comment('Points assigned to this option (for scoring)');

            $table->integer('order')->default(0)->comment('Display order');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['feedback_question_id', 'order'], 'feedback_question_order_index');
            $table->index('subquestion_text', 'subquestion_text_index');
            $table->index('answer_option_id', 'answer_option_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_question_options');
    }
};
```

#### Step 1.2: Add answer_options Field to feedback_questions
**File:** `database/migrations/YYYY_MM_DD_HHMMSS_add_answer_options_to_feedback_questions_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_questions', function (Blueprint $table) {
            $table->json('answer_options')->nullable()->after('options')
                ->comment('For questionnaire type: array of global answer options');
        });
    }

    public function down(): void
    {
        Schema::table('feedback_questions', function (Blueprint $table) {
            $table->dropColumn('answer_options');
        });
    }
};
```

### Phase 2: Model Layer

#### Step 2.1: Create FeedbackQuestionOption Model
**File:** `app/Models/FeedbackQuestionOption.php`

```php
<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToEnvironment;

class FeedbackQuestionOption extends Model
{
    use HasFactory, SoftDeletes, HasCreatedBy, BelongsToEnvironment;

    protected $fillable = [
        'feedback_question_id',
        'option_text',
        'subquestion_text',
        'answer_option_id',
        'points',
        'order',
        'created_by',
        'environment_id',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'order' => 'integer',
            'answer_option_id' => 'integer',
        ];
    }

    public function feedbackQuestion(): BelongsTo
    {
        return $this->belongsTo(FeedbackQuestion::class);
    }
}
```

#### Step 2.2: Update FeedbackQuestion Model
**File:** `app/Models/FeedbackQuestion.php`

Add these methods and relationships:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

// Add to fillable array
protected $fillable = [
    'feedback_content_id',
    'question_text',
    'question_type', // text, rating, multiple_choice, checkbox, dropdown, questionnaire
    'options', // JSON array of options for legacy types
    'answer_options', // JSON array for questionnaire type
    'required',
    'order',
    'created_by',
    'environment_id',
];

// Update casts
protected function casts(): array
{
    return [
        'options' => 'json',
        'answer_options' => 'json', // NEW
        'required' => 'boolean',
        'order' => 'integer',
    ];
}

// NEW: Relationship to options table
public function questionOptions(): HasMany
{
    return $this->hasMany(FeedbackQuestionOption::class, 'feedback_question_id');
}

// NEW: Accessor for subquestions (questionnaire type)
public function getSubquestionsAttribute()
{
    if ($this->question_type !== 'questionnaire') {
        return collect();
    }

    // Get all options for this question that have subquestion data
    $options = $this->questionOptions()
        ->whereNotNull('subquestion_text')
        ->get();

    // Group by subquestion_text to create subquestions
    $subquestions = $options->groupBy('subquestion_text')
        ->map(function ($subquestionOptions, $subquestionText) {
            // Each group represents one subquestion with its assignments
            $assignments = $subquestionOptions->map(function ($option) {
                return (object) [
                    'answer_option_id' => $option->answer_option_id,
                    'points' => $option->points ?? 0,
                ];
            });

            return (object) [
                'text' => $subquestionText,
                'assignments' => $assignments,
            ];
        })->values();

    return $subquestions;
}

// NEW: Accessor for answer options (questionnaire type)
public function getAnswerOptionsListAttribute()
{
    if ($this->question_type !== 'questionnaire') {
        return collect();
    }

    // Get unique answer options from the options table
    $answerOptionIds = $this->questionOptions()
        ->whereNotNull('answer_option_id')
        ->distinct('answer_option_id')
        ->pluck('answer_option_id')
        ->unique();

    // Match with answer_options JSON field
    if ($this->answer_options && is_array($this->answer_options)) {
        return collect($this->answer_options)->whereIn('id', $answerOptionIds);
    }

    return collect();
}
```

### Phase 3: Controller Layer

#### Step 3.1: Update FeedbackContentController
**File:** `app/Http/Controllers/Api/FeedbackContentController.php`

Add validation and handling for questionnaire type:

```php
// In createFeedbackContent() and updateFeedbackContent()
protected function validateQuestionData(array $question)
{
    $rules = [
        'question_text' => 'required|string',
        'question_type' => 'required|in:text,rating,multiple_choice,checkbox,dropdown,questionnaire',
        'required' => 'boolean',
        'order' => 'integer',
    ];

    // For questionnaire type, validate answer_options and options structure
    if (isset($question['question_type']) && $question['question_type'] === 'questionnaire') {
        $rules['answer_options'] = 'required|array|min:2';
        $rules['answer_options.*.id'] = 'required|integer';
        $rules['answer_options.*.text'] = 'required|string';

        $rules['options'] = 'required|array|min:1';
        $rules['options.*.subquestion_text'] = 'required|string';
        $rules['options.*.answer_option_id'] = 'required|integer';
        $rules['options.*.points'] = 'nullable|integer';
    }
    // For legacy types, validate simple options array
    elseif (in_array($question['question_type'] ?? '', ['multiple_choice', 'checkbox', 'dropdown'])) {
        $rules['options'] = 'required|array|min:2';
        $rules['options.*.text'] = 'required|string';
    }

    return $rules;
}

// In saveQuestion() method
protected function saveQuestion(FeedbackContent $feedbackContent, array $questionData)
{
    $question = FeedbackQuestion::updateOrCreate(
        ['id' => $questionData['id'] ?? null],
        [
            'feedback_content_id' => $feedbackContent->id,
            'question_text' => $questionData['question_text'],
            'question_type' => $questionData['question_type'],
            'required' => $questionData['required'] ?? true,
            'order' => $questionData['order'] ?? 0,
            'created_by' => auth()->id(),
        ]
    );

    // Handle questionnaire type
    if ($question->question_type === 'questionnaire') {
        // Save answer_options to JSON field
        $question->answer_options = $questionData['answer_options'] ?? [];
        $question->options = null; // Clear legacy options field
        $question->save();

        // Delete existing options for this question
        $question->questionOptions()->delete();

        // Create new options from the options array
        if (isset($questionData['options']) && is_array($questionData['options'])) {
            foreach ($questionData['options'] as $index => $optionData) {
                FeedbackQuestionOption::create([
                    'feedback_question_id' => $question->id,
                    'option_text' => $optionData['option_text'] ?? '',
                    'subquestion_text' => $optionData['subquestion_text'] ?? null,
                    'answer_option_id' => $optionData['answer_option_id'] ?? null,
                    'points' => $optionData['points'] ?? 0,
                    'order' => $optionData['order'] ?? $index,
                    'created_by' => auth()->id(),
                ]);
            }
        }
    }
    // Handle legacy question types
    else {
        // Save options to JSON field
        $question->options = $questionData['options'] ?? [];
        $question->answer_options = null; // Clear questionnaire field
        $question->save();

        // No need for feedback_question_options records for legacy types
    }

    return $question;
}
```

### Phase 4: Frontend Layer

#### Step 4.1: Create QuestionnaireQuestionEditor Component
**File:** `components/activities/feedback-questionnaire-editor.tsx`

This will be similar to the assessment questionnaire editor but adapted for feedback:

```typescript
"use client"

import React, { useState, useEffect } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Plus, Trash2, GripVertical } from "lucide-react";
import { FeedbackQuestion, FeedbackQuestionOption } from "@/lib/services/feedback-content-service";

interface AnswerOption {
  id: number;
  text: string;
}

interface Subquestion {
  text: string;
  assignments: {
    answer_option_id: number;
    points: number;
  }[];
}

interface QuestionnaireQuestionEditorProps {
  question: FeedbackQuestion;
  onChange: (question: FeedbackQuestion) => void;
}

export function QuestionnaireQuestionEditor({
  question,
  onChange
}: QuestionnaireQuestionEditorProps) {
  const [answerOptions, setAnswerOptions] = useState<AnswerOption[]>([
    { id: 1, text: "Strongly Agree" },
    { id: 2, text: "Agree" },
    { id: 3, text: "Neutral" },
    { id: 4, text: "Disagree" },
    { id: 5, text: "Strongly Disagree" }
  ]);

  const [subquestions, setSubquestions] = useState<Subquestion[]>([
    {
      text: "",
      assignments: answerOptions.map(opt => ({
        answer_option_id: opt.id,
        points: 0
      }))
    }
  ]);

  // Convert subquestions to options array for API
  const convertToOptionsArray = (): FeedbackQuestionOption[] => {
    const options: FeedbackQuestionOption[] = [];

    subquestions.forEach((subq, subqIndex) => {
      subq.assignments.forEach((assignment, assignIndex) => {
        options.push({
          option_text: answerOptions.find(opt => opt.id === assignment.answer_option_id)?.text || "",
          subquestion_text: subq.text,
          answer_option_id: assignment.answer_option_id,
          points: assignment.points,
          order: subqIndex * answerOptions.length + assignIndex
        });
      });
    });

    return options;
  };

  // Update parent when data changes
  useEffect(() => {
    const updatedQuestion: FeedbackQuestion = {
      ...question,
      question_type: 'questionnaire',
      answer_options: answerOptions,
      options: convertToOptionsArray()
    };

    onChange(updatedQuestion);
  }, [answerOptions, subquestions]);

  const handleAddAnswerOption = () => {
    const newId = Math.max(...answerOptions.map(opt => opt.id)) + 1;
    const newOption = { id: newId, text: "" };
    setAnswerOptions([...answerOptions, newOption]);

    // Add this option to all subquestions
    setSubquestions(subquestions.map(subq => ({
      ...subq,
      assignments: [...subq.assignments, { answer_option_id: newId, points: 0 }]
    })));
  };

  const handleRemoveAnswerOption = (id: number) => {
    setAnswerOptions(answerOptions.filter(opt => opt.id !== id));

    // Remove this option from all subquestions
    setSubquestions(subquestions.map(subq => ({
      ...subq,
      assignments: subq.assignments.filter(a => a.answer_option_id !== id)
    })));
  };

  const handleAddSubquestion = () => {
    setSubquestions([
      ...subquestions,
      {
        text: "",
        assignments: answerOptions.map(opt => ({
          answer_option_id: opt.id,
          points: 0
        }))
      }
    ]);
  };

  const handleRemoveSubquestion = (index: number) => {
    setSubquestions(subquestions.filter((_, i) => i !== index));
  };

  return (
    <div className="space-y-6">
      {/* Answer Options Section */}
      <Card>
        <CardHeader>
          <CardTitle>Answer Options (Columns)</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          {answerOptions.map((option, index) => (
            <div key={option.id} className="flex gap-2 items-center">
              <GripVertical className="w-4 h-4 text-muted-foreground" />
              <Input
                value={option.text}
                onChange={(e) => {
                  const updated = [...answerOptions];
                  updated[index].text = e.target.value;
                  setAnswerOptions(updated);
                }}
                placeholder={`Answer option ${index + 1}`}
              />
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => handleRemoveAnswerOption(option.id)}
                disabled={answerOptions.length <= 2}
              >
                <Trash2 className="w-4 h-4" />
              </Button>
            </div>
          ))}
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={handleAddAnswerOption}
          >
            <Plus className="w-4 h-4 mr-2" />
            Add Answer Option
          </Button>
        </CardContent>
      </Card>

      {/* Subquestions Section */}
      <Card>
        <CardHeader>
          <CardTitle>Subquestions (Rows)</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {subquestions.map((subq, index) => (
            <div key={index} className="border rounded-lg p-4 space-y-3">
              <div className="flex gap-2 items-start">
                <div className="flex-1">
                  <Label>Subquestion {index + 1}</Label>
                  <Textarea
                    value={subq.text}
                    onChange={(e) => {
                      const updated = [...subquestions];
                      updated[index].text = e.target.value;
                      setSubquestions(updated);
                    }}
                    placeholder="Enter subquestion text"
                    rows={2}
                  />
                </div>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => handleRemoveSubquestion(index)}
                  disabled={subquestions.length <= 1}
                >
                  <Trash2 className="w-4 h-4" />
                </Button>
              </div>

              {/* Points assignment for each answer option */}
              <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
                {subq.assignments.map((assignment, assignIndex) => {
                  const answerOption = answerOptions.find(
                    opt => opt.id === assignment.answer_option_id
                  );
                  return (
                    <div key={assignment.answer_option_id} className="space-y-1">
                      <Label className="text-xs">{answerOption?.text || `Option ${assignIndex + 1}`}</Label>
                      <Input
                        type="number"
                        value={assignment.points}
                        onChange={(e) => {
                          const updated = [...subquestions];
                          updated[index].assignments[assignIndex].points = parseInt(e.target.value) || 0;
                          setSubquestions(updated);
                        }}
                        placeholder="Points"
                        className="h-8"
                      />
                    </div>
                  );
                })}
              </div>
            </div>
          ))}
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={handleAddSubquestion}
          >
            <Plus className="w-4 h-4 mr-2" />
            Add Subquestion
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
```

#### Step 4.2: Update FeedbackContentEditor
**File:** `components/activities/feedback-content-editor.tsx`

Add questionnaire option to question type selector (around line 396):

```typescript
<option value="text">Text Response</option>
<option value="rating">Rating (1-5 stars)</option>
<option value="multiple_choice">Multiple Choice</option>
<option value="checkbox">Checkbox (Multiple Select)</option>
<option value="dropdown">Dropdown</option>
<option value="questionnaire">Questionnaire (Matrix)</option>
```

Add conditional rendering for questionnaire editor (around line 420):

```typescript
{/* Show questionnaire editor for questionnaire type */}
{currentQuestion.question_type === "questionnaire" && (
  <QuestionnaireQuestionEditor
    question={currentQuestion}
    onChange={(updatedQuestion) => setCurrentQuestion(updatedQuestion)}
  />
)}

{/* Show simple options editor for other types */}
{["multiple_choice", "checkbox", "dropdown"].includes(currentQuestion.question_type) && (
  // ... existing options editor
)}
```

#### Step 4.3: Update FeedbackContentService
**File:** `lib/services/feedback-content-service.ts`

Add questionnaire types to TypeScript definitions:

```typescript
export interface FeedbackQuestionOption {
  id?: number;
  option_text: string;
  subquestion_text?: string;
  answer_option_id?: number;
  points?: number;
  order?: number;
}

export interface AnswerOption {
  id: number;
  text: string;
}

export interface FeedbackQuestion {
  id?: number;
  feedback_content_id?: number;
  title?: string;
  question_text: string;
  question_type:
    | "text"
    | "rating"
    | "multiple_choice"
    | "checkbox"
    | "dropdown"
    | "questionnaire"; // NEW
  options?: FeedbackQuestionOption[] | {text: string}[]; // Support both formats
  answer_options?: AnswerOption[]; // NEW
  required: boolean;
  order: number;
}
```

---

## Data Migration Strategy

### Handling Existing Feedback Questions

**Current data structure:**
```json
{
  "question_type": "multiple_choice",
  "options": [
    {"text": "Option 1"},
    {"text": "Option 2"}
  ]
}
```

**No migration needed** - Existing questions will continue to work:
1. Legacy question types (text, rating, multiple_choice, checkbox, dropdown) will continue to use the `options` JSON field
2. New questionnaire type will use `answer_options` JSON field + `feedback_question_options` table
3. Backend will check `question_type` and handle accordingly

---

## Testing Checklist

### Database Layer
- [ ] Run pivot table migrations successfully
- [ ] Verify foreign key constraints work
- [ ] Test soft delete cascading

### Model Layer
- [ ] Test FeedbackQuestionOption CRUD operations
- [ ] Test `getSubquestionsAttribute()` accessor
- [ ] Test `getAnswerOptionsListAttribute()` accessor
- [ ] Verify relationships work correctly

### Controller Layer
- [ ] Test creating questionnaire-type feedback question
- [ ] Test updating questionnaire-type feedback question
- [ ] Test deleting questionnaire-type feedback question
- [ ] Test validation rules for questionnaire data
- [ ] Verify legacy question types still work

### Frontend Layer
- [ ] Test questionnaire editor UI
- [ ] Test adding/removing answer options
- [ ] Test adding/removing subquestions
- [ ] Test points assignment
- [ ] Test saving questionnaire question
- [ ] Test editing existing questionnaire question
- [ ] Verify question type selector shows "Questionnaire"

### Integration Testing
- [ ] Create feedback form with mixed question types
- [ ] Create feedback form with only questionnaire questions
- [ ] Submit feedback responses with questionnaire answers
- [ ] View feedback results/analytics for questionnaire responses

---

## Benefits of This Implementation

### For Instructors
✅ Rich matrix-style questions for more detailed feedback
✅ Scoring capability for feedback responses
✅ Familiar interface (same as assessment questionnaires)
✅ Flexible subquestion/answer option combinations

### For Learners/Respondents
✅ Easy-to-understand matrix layout
✅ Faster response time (grid format)
✅ Clear visual organization

### For Developers
✅ Reuses proven questionnaire logic from assessments
✅ Backward compatible with existing feedback questions
✅ Clean data model with proper relationships
✅ Extensible for future question types

---

## Potential Challenges

### 1. Data Structure Complexity
**Challenge:** Questionnaire data structure is more complex than simple options.

**Mitigation:**
- Reuse validation logic from quiz questions
- Provide clear UI feedback for invalid data
- Add comprehensive error messages

### 2. Frontend State Management
**Challenge:** Managing nested state for subquestions and assignments.

**Mitigation:**
- Use React component state carefully
- Consider using reducer pattern if complexity grows
- Add thorough validation before save

### 3. Answer Submission Handling
**Challenge:** Need to update feedback submission logic to handle questionnaire responses.

**Mitigation:**
- Create separate submission handler for questionnaire type
- Store responses in `feedback_answers` table with proper structure
- Add validation for questionnaire responses

---

## Alternative Approaches Considered

### Approach A: Use Existing options JSON Field
**Idea:** Store questionnaire data entirely in JSON without new table.

**Rejected because:**
- ❌ No relational integrity
- ❌ Difficult to query subquestions
- ❌ Hard to maintain data consistency
- ❌ Limited by JSON field size

### Approach B: Share quiz_question_options Table
**Idea:** Use the same table for both quiz and feedback questions.

**Rejected because:**
- ❌ Breaks separation of concerns
- ❌ Mixes assessment and feedback data
- ❌ Complex foreign key handling
- ❌ Harder to manage permissions

---

## Recommendation

✅ **Proceed with Option 1** - Create `feedback_question_options` table and extend `FeedbackQuestion` model.

**Reasoning:**
1. Clean database design with proper relationships
2. Backward compatible with existing feedback questions
3. Reuses proven questionnaire logic
4. Extensible for future question types
5. Medium complexity with manageable implementation effort

**Estimated Timeline:**
- Phase 1 (Database): 0.5 day
- Phase 2 (Models): 0.5 day
- Phase 3 (Controllers): 1 day
- Phase 4 (Frontend): 1.5 days
- Testing & Refinement: 0.5 day
- **Total: 3-4 days**

---

## Next Steps

1. **Get approval** for this approach from stakeholders
2. **Create database migrations** for `feedback_question_options` table
3. **Implement model layer** with relationships and accessors
4. **Update controllers** with questionnaire handling logic
5. **Build frontend component** for questionnaire editor
6. **Test thoroughly** with real data
7. **Update documentation** for instructors

---

## Files to Be Created/Modified

### New Files
1. `database/migrations/YYYY_MM_DD_HHMMSS_create_feedback_question_options_table.php`
2. `database/migrations/YYYY_MM_DD_HHMMSS_add_answer_options_to_feedback_questions_table.php`
3. `app/Models/FeedbackQuestionOption.php`
4. `components/activities/feedback-questionnaire-editor.tsx`

### Files to Modify
1. `app/Models/FeedbackQuestion.php` (add relationships and accessors)
2. `app/Http/Controllers/Api/FeedbackContentController.php` (add questionnaire handling)
3. `components/activities/feedback-content-editor.tsx` (integrate questionnaire editor)
4. `lib/services/feedback-content-service.ts` (update TypeScript types)

---

## Conclusion

Adding questionnaire-style questions to the Feedback content type is **feasible and recommended**. The implementation follows Laravel best practices, maintains backward compatibility, and provides a solid foundation for rich feedback collection. The estimated 3-4 day development effort is justified by the significant value added to the platform's feedback capabilities.
