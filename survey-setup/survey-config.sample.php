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

    // ---- Contact form, at /contact/ ------------------------------------------
    //
    // The form takes a message from anyone. Its optional name, organisation, and
    // email fields only appear once collect_contact is true and a notice says who
    // holds them: contact_notice if you fill it in, otherwise holder_notice above.
    // 'contact_notice' => 'REPLACE with a sentence saying who holds these details, that they are used only to reply, how long they are kept, and that the removal code deletes them.',

    // Where to send a notice that a message has arrived. This address appears
    // nowhere on the site and nowhere in the repository. The notice carries no
    // part of the message, because email is not private: you read the message in
    // the admin area. Leave it out to receive no notices.
    // 'notify_email' => 'REPLACE_with_your_address',

    // Optional. The address notices are sent from. Your host may require one on
    // this domain that exists. The default is no-reply@openassurance.nz.
    // 'notify_from' => 'no-reply@openassurance.nz',

    // Messages older than this are deleted automatically. The form tells senders
    // the period in months, so keep the two in step. The default is 365.
    'message_retention_days' => 365,

    // A ceiling on messages per hour.
    'max_messages_per_hour' => 30,

    // Optional. If set, only these Directory Privacy user names may open the admin area.
    // 'admin_users' => ['yourname'],
];
