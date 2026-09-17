<?php
/**
 * OpenAssurance survey configuration.
 *
 * Copy this file to the server as:
 *
 *     <your home directory>/openassurance-private/survey-config.php
 *
 * That folder sits beside public_html, not inside it, so the web server can
 * never serve this file. Fill in the values on the server. Never commit the
 * real file, and never put these values in the repository: it is public.
 */

return [
    // The database you created in cPanel. cPanel prefixes both names with your account name.
    'db_host' => 'localhost',
    'db_name' => 'REPLACE_with_database_name',
    'db_user' => 'REPLACE_with_database_user',
    'db_pass' => 'REPLACE_with_database_password',

    // Whether to offer respondents an optional contact field.
    //
    // Contact details are personal information. The Privacy Act 2020 expects
    // anyone collecting it to say who is collecting and holding it, so the
    // field only appears once holder_notice below has been filled in.
    // Leave collect_contact as false to run a fully anonymous survey.
    'collect_contact' => false,
    'holder_notice' => 'REPLACE with a sentence saying who holds these contact details, that they are used only to follow up this survey, how long they are kept, and that the removal code deletes them.',

    // A ceiling on responses per hour, in place of storing visitors' addresses.
    'max_per_hour' => 60,

    // Optional. If set, only these Directory Privacy user names may open the admin area.
    // 'admin_users' => ['yourname'],
];
