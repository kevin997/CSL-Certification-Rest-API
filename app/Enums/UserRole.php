<?php

namespace App\Enums;

enum UserRole: string
{
    case LEARNER = 'learner';
    case INDIVIDUAL_TEACHER = 'individual_teacher';
    case COMPANY_TEACHER = 'company_teacher';
    case COMPANY_TEAM_MEMBER = 'company_team_member';
    case ADMIN = 'admin';
    case SUPER_ADMIN = 'super_admin';
}
