<?php
/**
 * Local-only SmartOps configuration.
 *
 * Copy this file to config/local.php and update the values for your machine.
 * config/local.php is intentionally excluded from Git.
 */
return [
    'db_host' => 'localhost',
    'db_name' => 'smartops',
    'db_user' => 'root',
    'db_password' => '',

    // Used by the local admin sign-up / password-reset screens.
    // Replace this before using those screens.
    'admin_access_code' => 'CHANGE_ME_FOR_LOCAL_DEMO',

    // Used by the technician self-service password-reset screen.
    // Replace this before using that screen.
    'technician_reset_code' => 'CHANGE_ME_FOR_LOCAL_DEMO',
];
