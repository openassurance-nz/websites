<?php
/**
 * The question set. This one file drives the form, the validation, and the
 * labels in the admin area, so a change is made here and nowhere else.
 *
 * Rules for editing:
 *   - never name a product, a company, a scheme, or a person;
 *   - keep codes short and stable, because stored answers refer to them;
 *   - bump SURVEY_VERSION when a question's meaning changes, so old and new
 *     answers are never added together.
 */

const SURVEY_VERSION = '2026-09';

function survey_sections(): array
{
    return [
        [
            'id' => 'about',
            'title' => 'About your organisation',
            'intro' => 'Three questions, so that answers from different kinds of organisation can be told apart.',
            'questions' => [
                'role' => [
                    'label' => 'Which best describes your organisation\'s main role?',
                    'type' => 'single',
                    'required' => true,
                    'options' => [
                        'supplier' => 'We supply services or labour to other organisations',
                        'buyer' => 'We engage contractors or suppliers',
                        'both' => 'Both of the above',
                        'assessor' => 'We assess or prequalify other organisations',
                        'software' => 'We provide assurance, competency, or contractor-management software',
                        'training' => 'We provide training, assessment of people, or qualifications',
                        'body' => 'Industry body, regulator, or government agency',
                        'other' => 'Something else',
                    ],
                ],
                'sector' => [
                    'label' => 'Which sector do you mainly work in?',
                    'type' => 'single',
                    'options' => [
                        'construction' => 'Construction',
                        'infrastructure' => 'Infrastructure and utilities',
                        'energy' => 'Energy',
                        'transport' => 'Transport and logistics',
                        'manufacturing' => 'Manufacturing',
                        'primary' => 'Agriculture, forestry, or fishing',
                        'health' => 'Health and social services',
                        'services' => 'Facilities, property, or other services',
                        'public' => 'Local or central government',
                        'other' => 'Another sector',
                    ],
                ],
                'size' => [
                    'label' => 'How many people work in your organisation?',
                    'type' => 'single',
                    'options' => [
                        '1' => 'Just me',
                        '2-19' => '2 to 19',
                        '20-99' => '20 to 99',
                        '100-499' => '100 to 499',
                        '500+' => '500 or more',
                    ],
                ],
            ],
        ],
        [
            'id' => 'supplier',
            'title' => 'If you supply services or labour',
            'intro' => 'Skip this section if it does not apply to you. Estimates are fine.',
            'questions' => [
                'prequal_count' => [
                    'label' => 'In the last 12 months, how many separate prequalifications or supplier assessments did your organisation complete?',
                    'type' => 'single',
                    'options' => ['0' => 'None', '1-2' => '1 or 2', '3-5' => '3 to 5', '6-10' => '6 to 10', '11-25' => '11 to 25', '26+' => 'More than 25'],
                ],
                'prequal_overlap' => [
                    'label' => 'How many of them asked for substantially the same information?',
                    'type' => 'single',
                    'options' => ['none' => 'None of them', 'some' => 'Some', 'most' => 'Most', 'all' => 'Nearly all', 'na' => 'Not applicable'],
                ],
                'prequal_hours' => [
                    'label' => 'Roughly how many hours did that take in total?',
                    'type' => 'single',
                    'options' => ['<10' => 'Under 10', '10-40' => '10 to 40', '41-100' => '41 to 100', '101-250' => '101 to 250', '250+' => 'More than 250', 'dk' => 'Don\'t know'],
                ],
                'prequal_fees' => [
                    'label' => 'Roughly what did you pay in fees over those 12 months?',
                    'type' => 'single',
                    'options' => ['0' => 'Nothing', '<1k' => 'Under $1,000', '1-5k' => '$1,000 to $5,000', '5-15k' => '$5,000 to $15,000', '15k+' => 'More than $15,000', 'dk' => 'Don\'t know'],
                ],
                'worker_systems' => [
                    'label' => 'In how many systems other than your own do you keep records about your workers\' qualifications or competency?',
                    'type' => 'single',
                    'options' => ['0' => 'None', '1' => '1', '2-3' => '2 or 3', '4-6' => '4 to 6', '7+' => '7 or more'],
                ],
                'declined_work' => [
                    'label' => 'Have you turned down or walked away from work because of the cost or effort of getting prequalified?',
                    'type' => 'single',
                    'options' => ['yes' => 'Yes', 'considered' => 'We have considered it', 'no' => 'No'],
                ],
            ],
        ],
        [
            'id' => 'buyer',
            'title' => 'If you engage contractors or suppliers',
            'intro' => 'Skip this section if it does not apply to you.',
            'questions' => [
                'buyer_method' => [
                    'label' => 'How do you check a contractor\'s health and safety capability before engaging them? Choose all that apply.',
                    'type' => 'multi',
                    'options' => [
                        'own' => 'Our own questionnaire or process',
                        'one_scheme' => 'One prequalification scheme we nominate',
                        'many_schemes' => 'Any of several schemes',
                        'certification' => 'A management-system certification',
                        'conversation' => 'Conversation, references, or past experience',
                        'none' => 'We do not check formally',
                    ],
                ],
                'buyer_accept' => [
                    'label' => 'If a supplier already held a verified assessment or record, and you could confirm it was authentic and current without joining another platform, would you accept it?',
                    'type' => 'single',
                    'options' => ['yes' => 'Yes', 'lower_risk' => 'Yes, for lower-risk work', 'maybe' => 'Possibly', 'no' => 'No', 'dk' => 'Don\'t know'],
                ],
                'buyer_needs' => [
                    'label' => 'What would you need to see in order to rely on it? Choose all that apply.',
                    'type' => 'multi',
                    'options' => [
                        'issuer' => 'Who issued it',
                        'current' => 'That it is still current',
                        'criteria' => 'The criteria it was assessed against',
                        'evidence' => 'What evidence was reviewed',
                        'site_visit' => 'Whether there was a site visit',
                        'score' => 'A score or grade',
                        'confirm' => 'A way to confirm it with the issuer',
                    ],
                ],
                'buyer_workers' => [
                    'label' => 'Do you ask contractors to enter their workers\' qualifications into a system you nominate?',
                    'type' => 'single',
                    'options' => ['yes' => 'Yes', 'sometimes' => 'For some work', 'no' => 'No'],
                ],
            ],
        ],
        [
            'id' => 'provider',
            'title' => 'If you assess organisations, train people, or provide software',
            'intro' => 'Skip this section if it does not apply to you.',
            'questions' => [
                'provider_export' => [
                    'label' => 'Could you give your customers a record that someone else could verify without becoming your customer?',
                    'type' => 'single',
                    'options' => ['already' => 'We already can', 'moderate' => 'With moderate work', 'hard' => 'It would be difficult', 'wont' => 'It is not something we would do', 'dk' => 'Don\'t know'],
                ],
                'provider_motive' => [
                    'label' => 'What would make an open exchange format worth supporting? Choose all that apply.',
                    'type' => 'multi',
                    'options' => [
                        'customers' => 'Our customers asking for it',
                        'buyers' => 'Buyers or government requiring it',
                        'cost' => 'A low cost to implement',
                        'clear' => 'A clear, stable specification',
                        'governance' => 'Governance that no single provider controls',
                        'nothing' => 'Nothing would',
                    ],
                ],
            ],
        ],
        [
            'id' => 'everyone',
            'title' => 'For everyone',
            'intro' => '',
            'questions' => [
                'priority' => [
                    'label' => 'Which of these would you most like fixed first?',
                    'type' => 'single',
                    'options' => [
                        'prequal' => 'Repeating substantially the same prequalification',
                        'worker_records' => 'Re-entering worker records in other organisations\' systems',
                        'genuine' => 'Checking that a qualification or licence is genuine',
                        'current' => 'Keeping records current across several systems',
                        'other' => 'Something else',
                        'none' => 'None of these is a problem for us',
                    ],
                ],
                'comment' => [
                    'label' => 'Is there anything else we should know?',
                    'help' => 'Please do not name products, companies, or people. Nothing written here is ever published word for word.',
                    'type' => 'text',
                    'max' => 1500,
                ],
            ],
        ],
    ];
}

/** Flat map of question id => definition, for validation and for the admin area. */
function survey_questions(): array
{
    $flat = [];
    foreach (survey_sections() as $section) {
        foreach ($section['questions'] as $id => $q) {
            $flat[$id] = $q;
        }
    }
    return $flat;
}
