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
    case DOCUMENTATION = 'documentation';
    case EVENT = 'event';
    case CERTIFICATE = 'certificate';
    case FEEDBACK = 'feedback';
    case WEBINAR = 'webinar';
    case QUESTIONNAIRE = 'questionnaire';
}
