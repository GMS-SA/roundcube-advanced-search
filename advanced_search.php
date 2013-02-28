<?php
    /**
     * Processing an advanced search over an E-Mail Account
     *
     * @version 1.1
     * @licence GNU GPLv3+
     * @author  Wilwert Claude
     * @author  Ludovicy Steve
     * @author  Chris Moules
     * @website http://www.gms.lu
     */

    class advanced_search extends rcube_plugin
    {
        // {{{ class vars

        /**
         * Instance of rcmail
         *
         * @var object
         * @access private
         */
        private $rc;
        /**
         * The menu where to place the advanced search button
         *
         * @var string
         * @access private
         */
        private $target_menu = 'messagemenu';
        /**
         * Every criteria which takes a email as argument
         *
         * @var array
         * @access private
         */
        private $email_criteria = array('HEADER FROM', 'HEADER TO', 'CC', 'BCC');
        /**
         * Every criteria which takes a date as argument
         *
         * @var array
         * @access private
         */
        private $date_criteria = array('BEFORE', 'ON', 'SINCE', 'SENTBEFORE', 'SENTON', 'SENTSINCE');
        /**
         * Every criteria which doesn't take an argument
         *
         * @var array
         * @access private
         */
        private $flag_criteria = array('ANSWERED', 'DELETED', 'DRAFT', 'FLAGGED', 'SEEN');
        /**
         * Prefered criteria to show on the top of lists
         *
         * @var array
         * @access private
         */
        private $prefered_criteria = array('SUBJECT', 'BODY', 'HEADER FROM', 'HEADER TO', 'SENTSINCE', 'LARGER');
        /**
         * Other criteria, anything not in the above lists, except 'prefered_criteria'
         *
         * @var array
         * @access private
         */
        private $other_criteria = array('SUBJECT', 'BODY', 'KEYWORD', 'LARGER', 'SMALLER');
        /**
         * All filter criteria
         *
         * @var array
         * @access private
         */
        private $criteria = array(
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
        );
        /**
         * Localization strings
         *
         * @var array
         * @access private
         */
        private $i18n_strings = array();
        // }}}
        // {{{ init()

        /**
         * Initialisation of the plugin
         *
         * @access public
         */
        function init()
        {
            $this->rc = rcmail::get_instance();
            $this->register_action('plugin.prepare_filter', array($this, 'prepare_filter'));
            $this->register_action('plugin.post_query', array($this, 'post_query'));
            $this->skin = $this->rc->config->get('skin');
            $this->add_texts('localization', true);
            $this->populate_i18n();
            $this->include_script('advanced_search.js');

            if ($this->rc->task == 'mail') {
                $file = 'skins/' . $this->skin . '/advanced_search.css';

                if (file_exists($this->home . '/' . $file)) {
                    $this->include_stylesheet($file);
                } else {
                    $this->include_stylesheet('skins/default/advanced_search.css');
                }

                if (empty($this->rc->action) || $this->rc->action == 'show') {
                    $this->mail_search_handler();
                }
            }
        }
        // }}}
        // {{{ populate_i18n()

        /**
         * This function populates an array with localization texts.
         * This is needed as ew are using a lot of localizations from core.
         * The core localizations are not avalable directly in JS
         *
         * @access private
         */
        private function populate_i18n()
        {
            // From Roundcube core localization
            $this->i18n_strings['advsearch'] = $this->rc->gettext('advsearch');
            $this->i18n_strings['search'] = $this->rc->gettext('search');
            $this->i18n_strings['resetsearch'] = $this->rc->gettext('resetsearch');
            $this->i18n_strings['addfield'] = $this->rc->gettext('addfield');
            $this->i18n_strings['delete'] = $this->rc->gettext('delete');
            // From plugin localization
            $this->i18n_strings['in'] = $this->gettext('in');
            $this->i18n_strings['and'] = $this->gettext('and');
            $this->i18n_strings['or'] = $this->gettext('or');
            $this->i18n_strings['not'] = $this->gettext('not');
            $this->i18n_strings['where'] = $this->gettext('where');
            $this->i18n_strings['exclude'] = $this->gettext('exclude');
            $this->i18n_strings['andsubfolders'] = $this->gettext('andsubfolders');
            $this->i18n_strings['allfolders'] = $this->gettext('allfolders');
        }
        // }}}
        // {{{ format_input()

        /**
         * This function formats some incoming criteria (by javascript) into IMAP compatible criteria
         *
         * @param array $input The requested search parameters
         * @access public
         * @return IMAP compatible requested data
         */
        function format_input($input)
        {
            $return_data = array();

            foreach($input as $item) {
                switch($item['filter']) {
                    case 'HEADERBODY':
                        $item['filter'] = 'HEADER FROM';
                        $return_data[] = $item;
                        $item['filter'] = 'BODY';
                        $return_data[] = $item;
                    break;
                    default:
                        $return_data[] = $item;
                    break;
                }
            }

            return $return_data;
        }
        // }}}
        // {{{ finalise_command()

        /**
         * This function converts the preconfigured query parts (as array) into an IMAP compatible string
         *
         * @param array $command_array An array containing the advanced search criteria
         * @access public
         * @return The command string
         */
        function finalise_command($command_array)
        {
            $command = array();
            $paranthesis = 0;
            $prev_method = null;
            $next_method = null;
            $cnt = count($command_array);

            foreach($command_array as $k => $v) {
                $part = '';
                $next_method = 'unknown';

                // Lookup next method
                if($k < $cnt-1) {
                    $next_method = $command_array[$k+1]['method'];
                }

                // If previous option was OR, close any open brakets
                if($paranthesis > 0 && $prev_method == 'or' && $v['method'] != 'or') {
                    for( ; $paranthesis > 0; $paranthesis--) {
                        $part .= ')';
                    }
                }

                // If there are two consecutive ORs, add brakets
                // If the next option is a new OR, add the prefix here
                // If the next option is _not_ a OR, and the current option is AND, prefix ALL
                if($next_method == 'or') {
                    if($v['method'] == 'or') {
                        $part .= ' (';
                        $paranthesis++;
                    }
                    $part .= ' OR ';
                } else if($v['method'] == 'and') {
                    $part .= ' ALL ';
                }

                $part .= $v['command'];

                // If this is the end of the query, and we have open brakets, close them
                if($k == $cnt-1 && $paranthesis > 0) {
                    for( ; $paranthesis > 0; $paranthesis--) {
                        $part .= ')';
                    }
                }

                $prev_method = $v['method'];
                $command[] = $part;
            }

            $command = implode(' ', $command);

            return $command;
        }
        // }}}
        // {{{ get_search_query()

        /**
         * This function generates the IMAP compatible search query based on the request data (by javascript)
         *
         * @param array $input The raw criteria data sent by javascript
         * @access public
         * @return The final search command
         */
        function get_search_query($input)
        {
            $input = $this->format_input($input);
            $command = array();

            foreach($input as $v) {
                $command_str = '';

                if ($v['not'] == 'true') {
                    $command_str .= 'NOT ';
                }

                $command_str .= $v['filter'];

                if (in_array($v['filter'], $this->date_criteria)) {
                    $date_format = $this->rc->config->get('date_format');
                    try {
                        $date = DateTime::createFromFormat($date_format, $v['filter-val']);
                        $command_str .= ' ' . $this->quote(date_format($date, "d-M-Y"));
                    }
                    catch (Exception $e) {
                        $date_format = preg_replace('/(\w)/','%$1', $date_format);
                        $date_array = strptime($v['filter-val'], $date_format);
                        $unix_ts = mktime($date_array['tm_hour'], $date_array['tm_min'], $date_array['tm_sec'], $date_array['tm_mon']+1, $date_array['tm_mday'], $date_array['tm_year']+1900);
                        $command_str .= ' ' . $this->quote(date("d-M-Y", $unix_ts));
                    }

                } else if (!in_array($v['filter'], $this->flag_criteria)) {
                        $command_str .= ' ' . $this->quote($v['filter-val']);
                }

                $command[] = array('method' => isset($v['method']) ? $v['method'] : 'and',
                                   'command' => $command_str);
            }

            $command = $this->finalise_command($command);

            return $command;
        }
        // }}}
        // {{{ quote()

        /**
         * This function quotes some specific values based on their data type
         *
         * @param mixed $input The value to get quoted
         * @access public
         * @return The quoted value
         */
        function quote($value)
        {
            if (is_numeric($value)) {
                $value = (int)$value;
            } else if (getType($value) == 'string') {
                if (!preg_match('/"/', $value)) {
                    $value = preg_replace('/^"/', '', $value);
                    $value = preg_replace('/"$/', '', $value);
                    $value = preg_replace('/"/', '\\"', $value);
                }

                $value = '"' . $value . '"';
            }

            return $value;
        }
        // }}}
        // {{{ post_query()

        /**
         * Here is where the actual query is fired to the imap server and the result is evaluated and sent back to the client side
         *
         * @access public
         */
        function post_query()
        {
            $search = get_input_value('search', RCUBE_INPUT_GET);

            if (!empty($search)) {
                $mbox = get_input_value('folder', RCUBE_INPUT_GET) != 'all' ? get_input_value('folder', RCUBE_INPUT_GET) : null;
                $imap_charset = RCMAIL_CHARSET;
                $sort_column = rcmail_sort_column();
                $search_str = $this->get_search_query($search);
                $sub_folders = get_input_value('sub_folders', RCUBE_INPUT_GET) == 'true';
                $folders = array();
                $result_h = array();

                if ($sub_folders === false) {
                    $folders[] = $mbox;
                } else {
                    $folders = $this->rc->get_storage()->list_folders_subscribed('', '*', null, null, true);

                    if (!empty($folders)) {
                        foreach($folders as $k => $v) {
                            if (!preg_match('/^' . $mbox . '/', $v)) {
                                unset($folders[$k]);
                            }
                        }
                    } else {
                        $folders[] = $mbox;
                    }
                }

                if ($search_str) {
                    foreach($folders as $mbox) {
                        $this->rc->storage->search($mbox, $search_str, $imap_charset, $sort_column);
                        $result_set = $this->rc->storage->list_messages($mbox, 1, $sort_column, rcmail_sort_order());

                        if (!empty($result_set)) {
                            $result_h = array_merge($result_h, $result_set);
                        }
                    }
                }

                $count = count($result_h);

                if (!empty($result_h)) {
                    rcmail_js_message_list($result_h);

                    if ($search_str) {
                        $this->rc->output->show_message('searchsuccessful', 'confirmation', array('nr' => $count));
                    }
                } else if ($err_code = $this->rc->storage->get_error_code()) {
                    rcmail_display_server_error();
                } else {
                    $this->rc->output->show_message('searchnomatch', 'notice');
                }

                $current_folder = get_input_value('current_folder', RCUBE_INPUT_GET);

                $this->rc->output->set_env('search_request', $search_str ? $search_request : '');
                $this->rc->output->set_env('messagecount', $count);
                $this->rc->output->set_env('pagecount', ceil($count / $this->rc->storage->get_pagesize()));
                $this->rc->output->set_env('exists', $this->rc->storage->count($current_folder, 'EXISTS'));
                $this->rc->output->command('plugin.set_rowcount', rcmail_get_messagecount_text($count, 1), $current_folder);
                $this->rc->output->command('set_rowcount', rcmail_get_messagecount_text($count, 1), $current_folder);
                $this->rc->output->send();
            }
        }
        // }}}
        // {{{ mail_search_handler()

        /**
         * This adds a button into the message menu to use the advanced search
         *
         * @access public
         */
        function mail_search_handler()
        {
            $this->api->add_content(html::tag('li', null,
                $this->api->output->button(array(
                    'command'    => 'plugin.advanced_search',
                    'label'      => 'advsearch',
                    'type'       => 'link',
                    'classact'   => 'icon advanced-search active',
                    'class'      => 'icon advanced-search',
                    'innerclass' => 'icon advanced-search',
                    )
                )
            ), $this->target_menu);
        }
        // }}}
        // {{{ prepare_filter()

        /**
         * This functions sends the initial data to the client side where a form (in dialog) is built for the advanced search
         *
         * @access public
         */
        function prepare_filter()
        {
            $folders = $this->rc->get_storage()->list_folders_subscribed('', '*', null, null, true);

            if (!empty($folders)) {
                $folders = $this->convert_folders($folders);
            }

            $ret = array('folders' => $folders,
                         'i18n_strings' => $this->i18n_strings,
                         'criteria' => $this->criteria,
                         'date_criteria' => $this->date_criteria,
                         'flag_criteria' => $this->flag_criteria,
                         'email_criteria' => $this->email_criteria,
                         'prefered_criteria' => $this->prefered_criteria,
                         'other_criteria' => $this->other_criteria);

            $this->rc->output->command('plugin.show', $ret);
        }
        // }}}
        // {{{ convert_folder()

        /**
         * This function loops all the folders and fires them throw a conversion function
         *
         * @param array $folders The array of folders to search in
         * @access public
         * @return An array ready to use for a <select></select> in javascript
         */
        function convert_folders($folders)
        {
            $return_value = array();

            foreach($folders as $folder) {
                $return_value[$folder] = $this->get_folder($folder);
            }

            return $return_value;
        }
        // }}}
        // {{{ get_folder()

        /**
         * This function creates an array with the IMAP foldername as key and only the real (sub)folder name as value 
         * with some breaks for a better visualisation on the client side.
         *
         * @param array $folder The (sub)folder name
         * @access public
         * @return Only the real folder name with breaker to better visualise subfolders in a select box
         */
        function get_folder($folder)
        {
            $dots = substr_count($folder, '.');
            $breakers = '';

            if ($dots > 0) {
                for($i=0; $i<$dots; $i++) {
                    $breakers .= '&nbsp;&nbsp;';
                }

                $folder = explode('.', $folder);
            } else {
                $folder = array($folder);
            }

            return $breakers . $folder[count($folder)-1];
        }
        // }}}
    }
?>
