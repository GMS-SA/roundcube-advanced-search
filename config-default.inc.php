<?php

$rcmail_config['advanced_search_plugin'] = array(
    // The menu where to place the advanced search button by default
    'target_menu' => 'messagemenu',

    // Every criteria which takes a email as argument
    'email_criteria' => array('HEADER FROM', 'HEADER TO', 'CC', 'BCC'),

    // Every criteria which takes a date as argument
    'date_criteria' => array('BEFORE', 'ON', 'SINCE', 'SENTBEFORE', 'SENTON', 'SENTSINCE'),

    // Every criteria which doesn't take an argument
    'flag_criteria' => array('ANSWERED', 'DELETED', 'DRAFT', 'FLAGGED', 'SEEN'),

    // Prefered criteria to show on the top of lists
    'prefered_criteria' => array('SUBJECT', 'BODY', 'HEADER FROM', 'HEADER TO', 'SENTSINCE', 'LARGER'),

    // Other criteria, anything not in the above lists, except 'prefered_criteria'
    'other_criteria' => array('SUBJECT', 'BODY', 'KEYWORD', 'LARGER', 'SMALLER'),

    // All filter criteria
    'criteria' => array(
        'ANSWERED' => 'Answered',
        'BCC' => 'Bcc',
        'BEFORE' => 'Before',
        'CC' => 'Cc',
        'DELETED' => 'Deleted',
        'DRAFT' => 'Draft',
        'FLAGGED' => 'Flagged',
        'KEYWORD' => 'Keyword',
        'LARGER' => 'Larger Than',
        'BODY' => 'Message Body',
        'ON' => 'On',
        'SEEN' => 'Read',
        'SENTBEFORE' => 'Sent Before',
        'HEADER FROM' => 'From',
        'SENTON' => 'Sent On',
        'SENTSINCE' => 'Sent Since',
        'HEADER TO' => 'To',
        'SINCE' => 'Since',
        'SMALLER' => 'Smaller Than',
        'SUBJECT' => 'Subject Contains'
    )
);
