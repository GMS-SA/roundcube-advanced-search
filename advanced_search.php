<?php
    /**
     * Processing an advanced search over an E-Mail Account
     *
     * @version 1.0
     * @licence GNU GPLv3+
     * @author  Wilwert Claude
     * @author  Ludovicy Steve
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
            'NEW' => 'New',
            'OLD' => 'Old',
            'ON' => 'On',
            'RECENT' => 'Recent',
            'SEEN' => 'Seen',
            'SENTBEFORE' => 'Sent Before',
            'HEADER FROM' => 'Sent By',
            'SENTON' => 'Sent On',
            'SENTSINCE' => 'Sent Since',
            'HEADER TO' => 'Sent To',
            'SINCE' => 'Since',
            'SMALLER' => 'Smaller Than',
            'SUBJECT' => 'Subject Contains'
        );
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
            $this->add_texts('localization/', false);
            $this->skin = $this->rc->config->get('skin');
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

            foreach($command_array as $k => $v) {
                $part = '';

                if ($v['method'] == 'or' && $k != count($command_array)-1) {
                    $part .= '(' . strtoupper($v['method']) . ' ';
                    $paranthesis++;
                }

                $command[] = $part . $v['command'];
            }

            $command = implode(' ', $command);

            if ($paranthesis > 0) {
                for($i=0; $i<$paranthesis; $i++) {
                    $command .= ')';
                }
            }

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
                    $command_str .= ' ' . $this->quote(date("d-M-Y", strtotime($v['filter-val'])));
                } else {
                    if (!in_array($v['filter'], $this->flag_criteria)) {
                        $command_str .= ' ' . $this->quote($v['filter-val']);
                    }
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
                    'label'      => 'advanced_search.label',
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
                         'criteria' => $this->criteria,
                         'prefered_criteria' => $this->prefered_criteria);

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
