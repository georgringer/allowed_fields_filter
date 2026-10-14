<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Allowed Fields Filter',
    'description' => 'List only fields of permitted tables in the "Allowed fields" of backend user groups',
    'category' => 'be',
    'author' => 'Georg Ringer',
    'author_email' => 'mail@ringer.it',
    'state' => 'beta',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
        ],
    ],
];
