<?php
/**
 * Processing an advanced search over an E-Mail Account
 *
 * @version 1.2.0
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
     * @return null
     */
    function init()
    {
        $this->rc = rcmail::get_instance();
        $this->load_config();
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

        $this->add_hook('startup', array($this, 'startup'));
    }

    function startup($input)
    {
        $search = get_input_value('_search', RCUBE_INPUT_GET);
        if ($search == 'advanced_search_active') {
            $action = get_input_value('_action', RCUBE_INPUT_GET);
            $id = get_input_value('_uid', RCUBE_INPUT_GET);
            if (($action == 'show' || $action == 'preview') && $id) {
                $uid = $_SESSION['advanced_search']['uid_list'][$id]['uid'];
                $mbox = $_SESSION['advanced_search']['uid_list'][$id]['mbox'];
                $this->rc->output->redirect(array('_task' => 'mail', '_action' => $action, '_mbox' => $mbox, '_uid' => $uid));
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
     * @return null
     */
    private function populate_i18n()
    {
        $core = array('advsearch', 'search', 'resetsearch', 'addfield', 'delete');

        foreach($core as $label) {
            $this->i18n_strings[$label] = $this->rc->gettext($label);
        }

        $local = array('in', 'and', 'or', 'not', 'where', 'exclude', 'andsubfolders', 'allfolders');

        foreach($local as $label) {
            $this->i18n_strings[$label] = $this->gettext($label);
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
        $prev_method = null;
        $next_method = null;
        $cnt = count($command_array);

        foreach($command_array as $k => $v) {
            $part = '';
            $next_method = 'unknown';

            // Lookup next method
            if ($k < $cnt-1) {
                $next_method = $command_array[$k+1]['method'];
            }

            // If previous option was OR, close any open brakets
            if ($paranthesis > 0 && $prev_method == 'or' && $v['method'] != 'or') {
                for( ; $paranthesis > 0; $paranthesis--) {
                    $part .= ')';
                }
            }

            // If there are two consecutive ORs, add brakets
            // If the next option is a new OR, add the prefix here
            // If the next option is _not_ a OR, and the current option is AND, prefix ALL
            if ($next_method == 'or') {
                if ($v['method'] == 'or') {
                    $part .= ' (';
                    $paranthesis++;
                }
                
                $part .= 'OR ';
            } else if($v['method'] == 'and') {
                $part .= 'ALL ';
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

            if (in_array($v['filter'], $this->rc->config->get('date_criteria'))) {
                $date_format = $this->rc->config->get('date_format');
                
                try {
                    $date = DateTime::createFromFormat($date_format, $v['filter-val']);
                    $command_str .= ' ' . $this->quote(date_format($date, "d-M-Y"));
                } catch (Exception $e) {
                    $date_format = preg_replace('/(\w)/','%$1', $date_format);
                    $date_array = strptime($v['filter-val'], $date_format);
                    $unix_ts = mktime($date_array['tm_hour'], $date_array['tm_min'], $date_array['tm_sec'], $date_array['tm_mon']+1, $date_array['tm_mday'], $date_array['tm_year']+1900);
                    $command_str .= ' ' . $this->quote(date("d-M-Y", $unix_ts));
                }
            } else if (in_array($v['filter'], $this->rc->config->get('email_criteria'))) {
                // Tidy autocomplete which adds ', ' to email
                $command_str .= ' ' . $this->quote(trim($v['filter-val'], " \t,"));
            } else if (!in_array($v['filter'], $this->rc->config->get('flag_criteria'))) {
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
     * @return null
     */
    function post_query()
    {
        $search = get_input_value('search', RCUBE_INPUT_GET);

        if (!empty($search)) {
            $mbox = get_input_value('folder', RCUBE_INPUT_GET) == 'all' ? 'all' : get_input_value('folder', RCUBE_INPUT_GET);
            $imap_charset = RCMAIL_CHARSET;
            $sort_column = rcmail_sort_column();
            $search_str = $this->get_search_query($search);
            $sub_folders = get_input_value('sub_folders', RCUBE_INPUT_GET) == 'true';
            $folders = array();
            $result_h = array();
            $count = 0;
            $new_id = 1;
            $current_mbox = $this->rc->storage->get_folder();
            $uid_list = array();

            $folders = $this->rc->get_storage()->list_folders_subscribed('', '*', null, null, true);
            if (empty($folders) || ($sub_folders === false && $mbox !== 'all')) {
                $folders = array($mbox);
            } else if ($mbox !== 'all') {
                foreach($folders as $k => $v) {
                    if (!preg_match('/^' . $mbox . '/', $v)) {
                        unset($folders[$k]);
                    }
                }
            }

            if ($search_str) {
                foreach($folders as $mbox) {
                    $this->rc->storage->search($mbox, $search_str, $imap_charset, $sort_column);
                    $result_set = $this->rc->storage->list_messages($mbox, 1, $sort_column, rcmail_sort_order());

                    if (!empty($result_set)) {
                        foreach($result_set as $result) {
                            $uid_list[$new_id] = array('uid' => $result->uid, 'mbox' => $mbox);
                            $result->flags["skip_mbox_check"] = TRUE;
                            $result->uid = $new_id;
                            $result_h[] = $result;
                            $new_id += 1;
                        }
                    }
                }
            }

            $count = count($result_h);
            if ($count > 0) {
                $_SESSION['advanced_search']['uid_list'] = $uid_list;
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

            $this->rc->output->set_env('search_request', 'advanced_search_active');
            $this->rc->output->set_env('messagecount', $count);
            $this->rc->output->set_env('pagecount', ceil($count / $this->rc->storage->get_pagesize()));
            $this->rc->output->set_env('exists', $this->rc->storage->count($current_folder, 'EXISTS'));
            $this->rc->output->command('set_rowcount', rcmail_get_messagecount_text($count, 1), $current_folder);
            $this->rc->output->command('plugin.search_complete');
            $this->rc->output->send();
        }
    }
    // }}}
    // {{{ mail_search_handler()

    /**
     * This adds a button into the message menu to use the advanced search
     *
     * @access public
     * @return null
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
        ), $this->rc->config->get('target_menu'));
    }
    // }}}
    // {{{ prepare_filter()

    /**
     * This functions sends the initial data to the client side where a form (in dialog) is built for the advanced search
     *
     * @access public
     * @return null
     */
    function prepare_filter()
    {
        $folders = $this->rc->get_storage()->list_folders_subscribed('', '*', null, null, true);

        if (!empty($folders)) {
            foreach($folders as $key => $folder) {
                $folders[$key] = $this->get_folder($folder);
            }
        }

        $ret = array('html' => $this->render_html($folders, true),
                     'row' => $this->render_html($folders, false),
                     'title' => $this->i18n_strings['advsearch'],
                     'date_criteria' => $this->rc->config->get('date_criteria'),
                     'flag_criteria' => $this->rc->config->get('flag_criteria'),
                     'email_criteria' => $this->rc->config->get('email_criteria'));

        $this->rc->output->command('plugin.show', $ret);
    }
    // }}}
    // {{{ render_html()

    /**
     * This function is used to render the html of the advanced search form and also
     * the later following rows are created by this function
     *
     * @param array $folders Array of folders
     * @param boolean $first True if form gets created, False to create a new row
     * @access public
     * @return string The final html
     */
    function render_html($folders, $first)
    {
        $html = '';

        if ($first) {
            $options = '';
            $attrs = array(
                'type' => 'submit',
                'name' => 'search',
                'class' => 'button mainaction',
                'value' => $this->i18n_strings['search'],
            );

            $input = html::tag('input', $attrs, null);
            $td = html::tag('td', null, $input);
            $options .= html::tag('option', array('value' => 'all'), $this->i18n_strings['allfolders']);

            foreach($folders as $folder) {
                $options .= html::tag('option', null, $folder);
            }

            $input = html::tag('input', array('type' => 'checkbox', 'name' => 'subfolder'), null);
            $select = html::tag('select', array('name' => 'folder'), $options);
            $span = html::tag('span', array('class' => 'sub-folders'), $this->i18n_strings['andsubfolders'] . ': ' . $input);
            $td .= html::tag('td', null, $this->i18n_strings['in'] . ': ' . $select . $span . $this->i18n_strings['where'] . ': ');
            $tr = html::tag('tr', null, $td);
            $html .= html::tag('thead', null, $tr);
        }

        $optgroups = '';
        $criteria = $this->rc->config->get('criteria');
        $all_criteria = array(
            'Common' => $this->rc->config->get('prefered_criteria'),
            'Addresses' => $this->rc->config->get('email_criteria'),
            'Dates' => $this->rc->config->get('date_criteria'),
            'Flags' => $this->rc->config->get('flag_criteria'),
            'Other' => $this->rc->config->get('other_criteria'),
        );

        foreach($all_criteria as $label => $specific_criteria) {
            $options = '';

            foreach($specific_criteria as $value) {
                $options .= html::tag('option', array('value' => $value), $criteria[$value]);
            }

            $optgroups .= html::tag('optgroup', array('label' => $label), $options);
        }

        $select = html::tag('select', array('name' => 'filter'), $optgroups);
        $checkbox1 = html::tag('input', array('type' => 'checkbox', 'name' => 'not'), null);
        $text = html::tag('input', array('type' => 'text', 'name' => 'filter-val'), null);
        $checkbox2 = html::tag('input', array('type' => 'checkbox', 'name' => 'filter-exclude'), null);
        $buttons = html::tag('button', array('name' => 'add', 'class' => 'add'), $this->i18n_strings['addfield']);;

        if (!$first) {
            $buttons .= html::tag('button', array('name' => 'delete', 'class' => 'delete'), $this->i18n_strings['delete']);
        }

        $content = $select . ' ' . $this->i18n_strings['not'] . $checkbox1 . $text;
        $content .= ' ' . $this->i18n_strings['exclude'] . ':' . $checkbox2 . $buttons;
        $td_content = html::tag('td', null, $content);
        $tr = '';

        if ($first) {
            $td = html::tag('td', array('class' => 'adv-search-and-or'), null);
            $tr = html::tag('tr', null, $td . $td_content);
            $html .= html::tag('tbody', null, $tr);

            $attrs = array(
                'type' => 'submit',
                'name' => 'search',
                'class' => 'button mainaction',
                'value' => $this->i18n_strings['search']
            );

            $input = html::tag('input', $attrs, null);
            $td = html::tag('td', null, $input);

            $attrs = array(
                'type' => 'reset',
                'name' => 'reset',
                'class' => 'button reset',
                'value' => $this->i18n_strings['resetsearch']
            );

            $input = html::tag('input', $attrs, null);
            $td .= html::tag('td', null, $input);
            $tr = html::tag('tr', null, $td);
            $html .= html::tag('tfoot', null, $tr);

            $html = html::tag('table', array('id' => 'adv-search'), $html);
            $html = html::tag('form', array('method' => 'post', 'action' => '#'), $html);
            $html = html::tag('div', array('id' => 'adsearch-popup'), $html);
        } else {
            $options = html::tag('option', array('value' => 'and'), $this->i18n_strings['and']);
            $options .= html::tag('option', array('value' => 'or'), $this->i18n_strings['or']);
            $select = html::tag('select', array('name' => 'method'), $options);
            $td = html::tag('td', array('class' => 'adv-search-and-or'), $select);
            $html = html::tag('tr', null, $td . $td_content);
        }

        return $html;
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
