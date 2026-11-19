<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Learner device slots
    |--------------------------------------------------------------------------
    |
    | Each learner may use a bounded number of devices per academy. A device is
    | a credential the browser presents (an opaque uuid), never a hardware
    | identifier: browsers cannot read a MAC address and modern phones
    | randomise theirs. Instructors can revoke a slot when a learner changes
    | device.
    |
    */

    'enforced' => env('DEVICE_LIMIT_ENFORCED', true),

    'max_active' => (int) env('DEVICE_LIMIT_MAX', 2),

    // A slot unused for this long is treated as free, so a lost phone cannot
    // lock a learner out forever. The row is kept for the instructor history.
    'idle_expiry_days' => (int) env('DEVICE_IDLE_EXPIRY_DAYS', 180),

    // Client header carrying the device uuid.
    'header' => 'X-Device-Id',

    // Roles that consume slots. Staff and instructors are never limited.
    'learner_roles' => [
        'learner',
        'company_learner',
        'company_team_member',
        'student',
    ],

    // Roles allowed to list and revoke a learner's devices.
    'manager_roles' => [
        'individual_teacher',
        'company_teacher',
        'admin',
        'super_admin',
    ],

];
