<?php
/**
 * Processing an advanced search over an E-Mail Account
 *
 * @version 1.3.0
 * @licence GNU GPLv3+
 * @author  Wilwert Claude
 * @author  Ludovicy Steve
 * @author  Chris Moules
 * @website http://www.gms.lu
 */

class advanced_search extends rcube_plugin
{
    /**
     * Instance of rcmail
     *
     * @var object
     * @access private
     */
    private $rc;

    /**
     * Plugin config
     *
     * @var array
     * @access private
     */
    private $config;

    /**
     * Localization strings
     *
     * @var array
     * @access private
     */
    private $i18n_strings = array();

    /**
     * Initialisation of the plugin
     *
     * @access public
     * @return null
     */
    function init()
    {
        $this->rc = rcmail::get_instance();
        $this->load_config("config-default.inc.php");
        $this->load_config();
        $this->config = $this->rc->config->get('advanced_search_plugin');
        $this->register_action('plugin.display_advanced_search', array($this, 'display_advanced_search'));
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

            if (empty($this->rc->action)) {
                $this->add_menu_entry();
            }
        }

        $this->add_hook('startup', array($this, 'startup'));
    }

    function message_set_mbox($p) {
        if (!isset($p['messages']) or !is_array($p['messages']))
            return $p;

        foreach ($p['messages'] as $message) {
            $message->list_flags['extra_flags']['plugin_advanced_search'] = array("wtf" => 123);
        }

        return $p;
    }

    function startup($input)
    {
        $search = get_input_value('_search', RCUBE_INPUT_GPC);
        if ($search == 'advanced_search_active') {
            $action = get_input_value('_action', RCUBE_INPUT_GPC);
            $page = get_input_value('_page', RCUBE_INPUT_GPC);
            $id = get_input_value('_uid', RCUBE_INPUT_GPC);
            switch($action) {
                case 'show':
                case 'preview':
                    if($id) {
                        $uid = $_SESSION['advanced_search']['uid_list'][$id]['uid'];
                        $mbox = $_SESSION['advanced_search']['uid_list'][$id]['mbox'];
                        $this->rc->output->redirect(array('_task' => 'mail', '_action' => $action, '_mbox' => $mbox, '_uid' => $uid));
                    }
                    break;
                case 'list':
                    $this->rc->output->command('advanced_search_active', '_page=' . $page);
                    $this->rc->output->send();
            }
        }
    }

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

    /**
     * This adds a button into the configured menu to use the advanced search
     *
     * @access public
     * @return null
     */
    function add_menu_entry()
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
                    ), $this->config['target_menu']);
    }

    /**
     * This function quotes some specific values based on their data type
     *
     * @param mixed $input The value to get quoted
     * @access public
     * @return The quoted value
     */
    function quote($value)
    {
        if (getType($value) == 'string') {
            if (!preg_match('/"/', $value)) {
                $value = preg_replace('/^"/', '', $value);
                $value = preg_replace('/"$/', '', $value);
                $value = preg_replace('/"/', '\\"', $value);
            }

            $value = '"' . $value . '"';
        }

       return $value;
    }

    /**
     * This function generates the IMAP compatible search query based on the request data (by javascript)
     *
     * @param array $input The raw criteria data sent by javascript
     * @access private
     * @return string or int
     */
    private function process_search_part($search_part)
    {
        $command_str = '';
        $flag = false;

        // Check for valid input
        if(!array_key_exists($search_part['filter'], $this->config['criteria'])) {
            $this->rc->output->show_message($this->gettext('internalerror'), 'error');
            return 0;
        }
        if (in_array($search_part['filter'], $this->config['flag_criteria'])) {
            $flag = true;
        }
        if(!$flag && !(isset($search_part['filter-val']) && $search_part['filter-val'] != '')) {
            return 1;
        }

        // Negate part
        if ($search_part['not'] == 'true') {
            $command_str .= 'NOT ';
        }

        $command_str .= $search_part['filter'];

        if(!$flag) {
            if (in_array($search_part['filter'], $this->config['date_criteria'])) {
                // Take date format from user environment
                $date_format = $this->config['date_format'];

                // Try to use PHP5.2+ DateTime but fallback to ugly old method
                if(class_exists('DateTime')) {
                    $date = DateTime::createFromFormat($date_format, $search_part['filter-val']);
                    $command_str .= ' ' . $this->quote(DateTime::format($date, "d-M-Y"));
                } else {
                    $date_format = preg_replace('/(\w)/','%$1', $date_format);
                    $date_array = strptime($search_part['filter-val'], $date_format);
                    $unix_ts = mktime($date_array['tm_hour'], $date_array['tm_min'], $date_array['tm_sec'], $date_array['tm_mon']+1, $date_array['tm_mday'], $date_array['tm_year']+1900);
                    $command_str .= ' ' . $this->quote(date("d-M-Y", $unix_ts));
                }
            }

            // Strip possible ',' added by autocomplete
            else if (in_array($search_part['filter'], $this->config['email_criteria'])) {
                $command_str .= ' ' . $this->quote(trim($search_part['filter-val'], " \t,"));
            }

            // Don't try to use a value for a binary flag object
            else {
                $command_str .= ' ' . $this->quote($search_part['filter-val']);
            }
        }

        return $command_str;
    }

    /**
     * This function generates the IMAP compatible search query based on the request data (by javascript)
     *
     * @param array $input The raw criteria data sent by javascript
     * @access public
     * @return The final search command
     */
    function get_search_query($input)
    {
        $command = array();

        foreach($input as $search_part) {
            if(! $part_command = $this->process_search_part($search_part)) {
                return 0;
            }
            // Skip invalid parts
            if($part_command === 1) {
                continue;
            }

            $command[] = array('method' => isset($search_part['method']) ? $search_part['method'] : 'and',
                               'command' => $part_command);
        }

        $command_string = $this->build_search_string($command);

        return $command_string;
    }

    /**
     * This function converts the preconfigured query parts (as array) into an IMAP compatible string
     *
     * @param array $command_array An array containing the advanced search criteria
     * @access public
     * @return The command string
     */
    private function build_search_string($command_array)
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
            // If the next option is _not_ an OR, and the current option is AND, prefix ALL
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

    /**
     * This functions sends the initial data to the client side where a form (in dialog) is built for the advanced search
     *
     * @access public
     * @return null
     */
    function display_advanced_search()
    {
        $ret = array('html' => $this->generate_searchbox(),
                     'row' => $this->add_row(),
                     'title' => $this->i18n_strings['advsearch'],
                     'date_criteria' => $this->config['date_criteria'],
                     'flag_criteria' => $this->config['flag_criteria'],
                     'email_criteria' => $this->config['email_criteria']);

        $this->rc->output->command('plugin.show', $ret);
    }

    function generate_searchbox()
    {
        $search_button = new html_inputfield(array('type' => 'submit', 'name' => 'search', 'class' => 'button mainaction', 'value' => $this->i18n_strings['search']));
        $reset_button = new html_inputfield(array('type' => 'reset', 'name' => 'reset', 'class' => 'button reset', 'value' => $this->i18n_strings['resetsearch']));

        $layout_table = new html_table();
        $layout_table->add(null,$search_button->show());
        $folderConfig = array('name' => 'folder');
        $layout_table->add(null, $this->i18n_strings['in'] . ': ' .
            $this->folder_selector($folderConfig)->show($this->rc->storage->get_folder()) .
            html::span(array('class' => 'sub-folders'), $this->i18n_strings['andsubfolders'] . ': ' . html::tag('input', array('type' => 'checkbox', 'name' => 'subfolder'), null)) .
            $this->i19n_strings['where']);
        $first_row = $this->add_row(true);
        $layout_table->add_row();
        $layout_table->add(array('class' => 'adv-search-and-or'), null);
        $layout_table->add(null, $first_row);
        $layout_table->add_row();
        $layout_table->add(null,$search_button->show());
        $layout_table->add(null,$reset_button->show());

        return html::tag('div', array('id' => 'adsearch-popup'),
                html::tag('form', array('method' => 'post', 'action' => '#'), $layout_table->show()) );
    }

    /**
     * This function is used to render the html of the advanced search form and also
     * the later following rows are created by this function
     *
     * @param array $folders Array of folders
     * @param boolean $first True if form gets created, False to create a new row
     * @access public
     * @return string The final html
     */
    function add_row($first = false)
    {
        $row_html = '';
        $optgroups = '';

        $criteria = $this->config['criteria'];
        $all_criteria = array(
            'Common' => $this->config['prefered_criteria'],
            'Addresses' => $this->config['email_criteria'],
            'Dates' => $this->config['date_criteria'],
            'Flags' => $this->config['flag_criteria'],
            'Other' => $this->config['other_criteria'],
        );

        foreach($all_criteria as $label => $specific_criteria) {
            $options = '';

            foreach($specific_criteria as $value) {
                $options .= html::tag('option', array('value' => $value), $criteria[$value]);
            }

            $optgroups .= html::tag('optgroup', array('label' => $label), $options);
        }

        $tmp = html::tag('select', array('name' => 'filter'), $optgroups);
        $tmp .= $this->i18n_strings['not'] . ':' . html::tag('input', array('type' => 'checkbox', 'name' => 'not'), null);
        $tmp .= html::tag('input', array('type' => 'text', 'name' => 'filter-val'));
        $tmp .= $this->i18n_strings['exclude'] . ':' . html::tag('input', array('type' => 'checkbox', 'name' => 'filter-exclude'), null);
        $tmp .= html::tag('button', array('name' => 'add', 'class' => 'add'), $this->i18n_strings['addfield']);;

        if ($first) {
            $row_html = $tmp;
        } else {
            $and_or_select = new html_select();
            $and_or_select->add($this->i18n_strings['and'], 'and');
            $and_or_select->add($this->i18n_strings['or'], 'or');
            $tmp .= html::tag('button', array('name' => 'delete', 'class' => 'delete'), $this->i18n_strings['delete']);
            $row_html = html::tag('tr', null,
                html::tag('td', array('class' => 'adv-search-and-or'), $and_or_select->show()) .
                html::tag('td', null, $tmp)
            );
        }

        return $row_html;
    }

    /**
     * Return folders list as html_select object
     *
     * This is a copy of the core function and adapted to fit
     * the needs of the advanced_search function
     *
     * @param array $p  Named parameters
     *
     * @return html_select HTML drop-down object
     */
    public function folder_selector($p = array())
    {
        $p += array('maxlength' => 100, 'realnames' => false, 'is_escaped' => true);
        $a_mailboxes = array();
        $storage = $this->rc->get_storage();

        $list = $storage->list_folders_subscribed();
        $delimiter = $storage->get_hierarchy_delimiter();

        foreach ($list as $folder) {
            $this->rc->build_folder_tree($a_mailboxes, $folder, $delimiter);
        }

        $select = new html_select($p);
        $select->add($this->i18n_strings['allfolders'], "all");

        $this->rc->render_folder_tree_select($a_mailboxes, $mbox, $p['maxlength'], $select, $p['realnames'], 0, $p);

        return $select;
    }

    /**
     * Here is where the actual query is fired to the imap server and the result is evaluated and sent back to the client side
     *
     * @access public
     * @return null
     */
    function post_query()
    {

        //$avcols = array('threads', 'subject', 'status', 'fromto', 'date', 'size', 'flag', 'attachment', 'priority', 'avmbox');
        $search = get_input_value('search', RCUBE_INPUT_GPC);
        // reset list_page and old search results
        $this->rc->storage->set_page(1);
        $this->rc->storage->set_search_set(NULL);
        $_SESSION['page'] = 1;
        $page = get_input_value('_page', RCUBE_INPUT_GPC);
        $page = $page ? $page : 1;
        $pagesize = $this->rc->storage->get_pagesize();

        if (!empty($search)) {
            $mbox = get_input_value('folder', RCUBE_INPUT_GPC) == 'all' ? 'all' : get_input_value('folder', RCUBE_INPUT_GPC);
            $imap_charset = RCMAIL_CHARSET;
            $sort_column = rcmail_sort_column();
            $search_str = $this->get_search_query($search);
            $sub_folders = get_input_value('sub_folders', RCUBE_INPUT_GPC) == 'true';
            $folders = array();
            $result_h = array();
            $count = 0;
            $new_id = 1;
            $current_mbox = $this->rc->storage->get_folder();
            $uid_list = array();

            $nosub = $sub_folders;
            $folders = $this->rc->get_storage()->list_folders_subscribed();
            if (empty($folders) || ($sub_folders === false && $mbox !== 'all')) {
                $folders = array($mbox);
            } else if ($mbox !== 'all') {
              if($sub_folders === false) {
                $folders = array($mbox);
              } else {
                $folders = $this->rc->get_storage()->list_folders_subscribed_direct($mbox);
              }
            }
            if ($search_str) {
                foreach($folders as $mbox) {
                    if ($mbox == $this->rc->config->get('trash_mbox'))
                        continue;
                    $this->rc->storage->set_folder($mbox);
                    $this->rc->storage->search($mbox, $search_str, $imap_charset, $sort_column);
                    $result_set = $this->rc->storage->list_messages($mbox, 1, $sort_column, rcmail_sort_order());
                    $counter = $this->rc->storage->count($mbox, 'ALL', !empty($_REQUEST['_refresh']));
                    $count = $count + $counter;
                    if (!empty($result_set)) {
                        foreach($result_set as $result) {
                            $uid_list[$new_id] = array('uid' => $result->uid, 'mbox' => $mbox);
                            $result->flags["skip_mbox_check"] = TRUE;
                            $result->uid = $new_id;
                            $result->avmbox = $mbox;
                            $result_h[] = $result;
                            $new_id += 1;
                        }
                    }
                }
            }

            // Apply page size rules
            if ($count > $pagesize)
                $result_h = array_slice($result_h, ($page - 1) * $pagesize, $pagesize);
            if ($count > 0) {
                $_SESSION['advanced_search']['uid_list'] = $uid_list;
                $this->rcmail_js_message_list($result_h, false, null, $mbox, array('avmbox')); //$avcols);
                if ($search_str) {
                    $this->rc->output->show_message('searchsuccessful', 'confirmation', array('nr' => $count));
                }
            } else if ($err_code = $this->rc->storage->get_error_code()) {
                rcmail_display_server_error();
            } else {
                $this->rc->output->show_message('searchnomatch', 'notice');
            }

            $current_folder = get_input_value('current_folder', RCUBE_INPUT_GPC);

            $this->rc->output->set_env('search_request', 'advanced_search_active');
            $this->rc->output->set_env('messagecount', $count);
            $this->rc->output->set_env('pagecount', ceil($count / $pagesize));
            $this->rc->output->set_env('exists', $this->rc->storage->count($current_folder, 'EXISTS'));
            $this->rc->output->command('set_rowcount', rcmail_get_messagecount_text($count));
            $this->rc->output->command('plugin.search_complete');
            $this->rc->output->send();
        }
    }

    /**
     * return javascript commands to add rows to the message list
     */
    function rcmail_js_message_list($a_headers, $insert_top=FALSE, $a_show_cols=null, $avmbox=false, $avcols=array())
    {
      global $CONFIG, $RCMAIL, $OUTPUT;

      if (empty($a_show_cols)) {
        if (!empty($_SESSION['list_attrib']['columns']))
          $a_show_cols = $_SESSION['list_attrib']['columns'];
        else
          $a_show_cols = is_array($CONFIG['list_cols']) ? $CONFIG['list_cols'] : array('subject');
      }
      else {
        if (!is_array($a_show_cols))
          $a_show_cols = preg_split('/[\s,;]+/', strip_quotes($a_show_cols));
        $head_replace = true;
      }

      $mbox = $RCMAIL->storage->get_folder();

      // make sure 'threads' and 'subject' columns are present
      if (!in_array('subject', $a_show_cols))
        array_unshift($a_show_cols, 'subject');
      if (!in_array('threads', $a_show_cols))
        array_unshift($a_show_cols, 'threads');

      $_SESSION['list_attrib']['columns'] = $a_show_cols;

      // Make sure there are no duplicated columns (#1486999)
      $a_show_cols = array_merge($a_show_cols, $avcols);
      $a_show_cols = array_unique($a_show_cols);

      // Plugins may set header's list_cols/list_flags and other rcube_message_header variables
      // and list columns
      $plugin = $RCMAIL->plugins->exec_hook('messages_list',
        array('messages' => $a_headers, 'cols' => $a_show_cols));

      $a_show_cols = $plugin['cols'];
      $a_headers   = $plugin['messages'];


      $thead = $head_replace ? rcmail_message_list_head($_SESSION['list_attrib'], $a_show_cols) : NULL;

      // get name of smart From/To column in folder context
      if (($f = array_search('fromto', $a_show_cols)) !== false) {
        $smart_col = rcmail_message_list_smart_column_name();
      }

      $OUTPUT->command('set_message_coltypes', $a_show_cols, $thead, $smart_col);
      $OUTPUT->command('plugin.avaddheader', array());

      if (empty($a_headers))
        return;

      // remove 'threads', 'attachment', 'flag', 'status' columns, we don't need them here
      foreach (array('threads', 'attachment', 'flag', 'status', 'priority') as $col) {
        if (($key = array_search($col, $a_show_cols)) !== FALSE)
          unset($a_show_cols[$key]);
      }

      // loop through message headers
      foreach ($a_headers as $n => $header) {
        if (empty($header))
          continue;

        $a_msg_cols = array();
        $a_msg_flags = array();

        // format each col; similar as in rcmail_message_list()
        foreach ($a_show_cols as $col) {
          $col_name = $col == 'fromto' ? $smart_col : $col;

          if (in_array($col_name, array('from', 'to', 'cc', 'replyto')))
            $cont = rcmail_address_string($header->$col_name, 3, false, null, $header->charset);
          else if ($col == 'subject') {
            $cont = trim(rcube_mime::decode_header($header->$col, $header->charset));
            if (!$cont) $cont = rcube_label('nosubject');
            $cont = Q($cont);
          }
          else if ($col == 'size')
            $cont = show_bytes($header->$col);
          else if ($col == 'date')
            $cont = format_date($header->date);
          else
            $cont = Q($header->$col);

          $a_msg_cols[$col] = $cont;
        }

        $a_msg_flags = array_change_key_case(array_map('intval', (array) $header->flags));
        if ($header->depth)
          $a_msg_flags['depth'] = $header->depth;
        else if ($header->has_children)
          $roots[] = $header->uid;
        if ($header->parent_uid)
          $a_msg_flags['parent_uid'] = $header->parent_uid;
        if ($header->has_children)
          $a_msg_flags['has_children'] = $header->has_children;
        if ($header->unread_children)
          $a_msg_flags['unread_children'] = $header->unread_children;
        if ($header->others['list-post'])
          $a_msg_flags['ml'] = 1;
        if ($header->priority)
          $a_msg_flags['prio'] = (int) $header->priority;

        $a_msg_flags['ctype'] = Q($header->ctype);
        $a_msg_flags['mbox'] = $mbox;

        // merge with plugin result (Deprecated, use $header->flags)
        if (!empty($header->list_flags) && is_array($header->list_flags))
          $a_msg_flags = array_merge($a_msg_flags, $header->list_flags);
        if (!empty($header->list_cols) && is_array($header->list_cols))
          $a_msg_cols = array_merge($a_msg_cols, $header->list_cols);

        $a_msg_flags['avmbox'] = $avmbox;

        $OUTPUT->command('add_message_row',
          $header->uid,
          $a_msg_cols,
          $a_msg_flags,
          $insert_top);
      }

      if ($RCMAIL->storage->get_threading()) {
        $OUTPUT->command('init_threads', (array) $roots, $mbox);
      }
    }

}
?>
