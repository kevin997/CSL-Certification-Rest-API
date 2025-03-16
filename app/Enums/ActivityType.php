<?php

namespace App\Enums;

enum ActivityType: string
{
    case TEXT = 'text';
    case VIDEO = 'video';
    case QUIZ = 'quiz';
    case ASSESSMENT = 'assessment';
    case LESSON = 'lesson';
    case DOCUMENT = 'document';
    case ASSIGNMENT = 'assignment';
}
