(function($) {
    /**
     * The fontend scripts for an advanced search.
     *
     * @version 2.0.0
     * @licence GNU GPLv3+
     * @author  Wilwert Claude
     * @author  Ludovicy Steve
     * @author  Chris Moules
     * @website http://www.gms.lu
     */

    $.stack = {
        /**
         * This object is used to buffer all the server side information which doesn't change. So the script
         * doesn't have to send an ajax-request for every new added row.
         *
         * @name stack
         * @namespace
         */
        date_criteria: {},
        flag_criteria: {},
        email_criteria: {},
        row: null,
        messages: null
    };

    $("#button_display_option").live('change', function(e) {
        var img = $('img', $(this).closest('p'));
        var src = img.attr('src');
        if(this.value == 'messagemenu') {
            src = src.replace('menu_location_b.jpg', 'menu_location_a.jpg');
        } else {
            src = src.replace('menu_location_a.jpg', 'menu_location_b.jpg');
        }
        img.attr('src', src);
    });

    $("#_show_message_mbox_info, #_show_message_label_header").live('change', function(e) {
        var img = $('img', $(this).closest('p'));
        if($(this).is(':checked')) {
            img.removeClass('disabled');
        } else {
            img.addClass('disabled');
        }
    });

    /**
     * The callback function of the initial dialog call. It creates the dialog and buffers the serverside
     * informations into an object.
     *
     * @param {object} r The serverside informations
     */
    rcmail.addEventListener('plugin.show', function(r) {
        $.stack.date_criteria = r.date_criteria;
        $.stack.flag_criteria = r.flag_criteria;
        $.stack.email_criteria = r.email_criteria;
        $.stack.row = r.row;
        $.stack.html = r.html;

        var $html = $(r.html);

        $html.dialog({
            width: 640,
            height: 300,
            resizable: true,
            draggable: true,
            title: r.title,
            dialogClass: "advanced_search_dialog",
            close: function() {
                $(this).remove();
                $('body').css('overflow', 'auto');
            },
            create: function() {
                $('body').css('overflow', 'hidden');
            }
        });
    });

    rcmail.addEventListener('plugin.advanced_search_add_header', function(evt) {
        if($("#messagelist #rcavbox").length == 0) {
            $("#messagelist tr:first").append('<td class="mbox" id="rcavbox"><span class="mbox">Mbox</span></td>');
        }
    });

    rcube_webmail.prototype.advanced_search_add_mbox = function (mbox, count, showMbox) {
        if (!this.gui_objects.messagelist || !this.message_list) {
            return false;
        }

        var colspan = showMbox == true ? 9 : 8;
        $(rcmail.message_list.list).append('<tr class="aslabel_mbox"><td><span class="aslabel_found">' + count + '</span></td><td colspan="' + colspan + '">' + mbox + '</td></tr>');
    }

    rcube_webmail.prototype.advanced_search_active = function(param) {
        var page = param.replace('_page=', '');
        rcmail.http_request('plugin.trigger_search_pagination', { _page : page });
    }

    /**
     * The onclick event handler for the search button. This generates the search query and sends them
     * to the server. It also stores the wrapped set of the old rows into an object for later cleanup.
     *
     * @param {object} e The event element
     */
    $('input[name=search]').live('click', function(e) {
        e.preventDefault();

        rcmail.clear_message_list();

        var $form = $(this).closest('form'),
            $tr = $('tr', $('tbody', $form)).not(':first').not(':last'),
            data = [];

        if ($tr.length) {
            $tr.each(function() {
                if ($('input[name=filter-exclude]', $(this)).attr('checked') != 'checked') {
                    var item = {not: $('input[name=not]', $(this)).attr('checked') == 'checked',
                                filter: $('option:selected', $('select[name=filter]', $(this))).val(),
                                'filter-val': $('input[name=filter-val]', $(this)).val()};

                    if ($('select[name=method]', $(this)).length) {
                        item.method = $('option:selected', $('select[name=method]', $(this))).val();
                    }

                    data.push(item);
                }
            });
        }

        $.stack.messages = $('tr', $('tbody', '#messagelist'));

        rcmail.http_request('plugin.trigger_search',
                            {search: data,
                             current_folder: rcmail.env.mailbox,
                             folder: $('select[name=folder]', $form).val(),
                             sub_folders: $('input[name=subfolder]', $form).attr('checked') == 'checked'});

        $(".ui-dialog-titlebar-close", ".advanced_search_dialog").trigger("click");
    });

    /**
     * The onclick event handler of the "reset search" button, which resets the advanced search
     * back to its initial state.
     *
     * @param {object} e The event element
     */
    $('input[name=reset]').live('click', function(e) {
        e.preventDefault();
        $('#adsearch-popup').html($.stack.html);
    });

    /**
     * The onclick event handler for the "add" button. This adds one new row to the query dialog
     *
     * @param {object} e The event element
     */
    $('button[name=add]').live('click', function(e) {
        e.preventDefault();

        $(this).closest('tr').after($.stack.row);
    });

    /**
     * The onclick event handler for the "delete" button. This removes the containing row from
     * the query dialog
     *
     * @param {object} e The event element
     */
    $('button[name=delete]').live('click', function(e) {
        e.preventDefault();

        $(this).closest('tr').remove();
    });

    /**
     * The change event handler for the filter selector.
     * Make the input field context relevent.
     *
     * @param {object} e The event element
     */
    $('select[name=filter]').live('change', function(e) {
        var $row_input = $(this).nextUntil('tr', 'input[name=filter-val]'),
            old_avs_type = $row_input.data("avs_type");

        if ($.inArray($(this).val(), $.stack.date_criteria) >= 0) {
            if(old_avs_type !== "date") {
                $row_input.val('');
                $row_input.datepicker({dateFormat: rcmail.env.date_format});
            }

            $row_input.data("avs_type", "date");
        } else if ($.inArray($(this).val(), $.stack.email_criteria) >= 0) {
            if(old_avs_type !== "email") {
                rcmail.init_address_input_events($row_input, "");
                rcmail.addEventListener('autocomplete_insert', function(e){
                    e.field.value = e.insert.replace(/.*<(\S*?@\S*?)>.*/, "$1");
                });
            }

            $row_input.data("avs_type", "email");
        } else if ($.inArray($(this).val(), $.stack.flag_criteria) >= 0) {
            if (old_avs_type !== "flag_criteria") {
                $row_input.val('');
                $row_input.hide();
            }

            $row_input.data("avs_type", "flag_criteria");
        } else {
            $row_input.data("avs_type", "none");
        }

        switch (old_avs_type) {
            case "date":
                if (($row_input.data("avs_type") !== "date") && $row_input.hasClass("hasDatepicker")) {
                    $row_input.datepicker("destroy");
                }
            break;
            case "email":
                if (($row_input.data("avs_type") !== "email")) {
                    $row_input.removeAttr("autocomplete");
                    $row_input.unbind('keydown');
                    $row_input.unbind('keypress');
                }
            break;
            case "flag_criteria":
                if (($row_input.data("avs_type") !== "flag_criteria") && !$row_input.is(":visible")) {
                    $row_input.show();
                }
            break;
        }
    });

    /**
     * The change event handler for the folder select box. It makes the subfolder checkbox invisible
     * when selecting the "all folders" option
     *
     * @param {object} e The event element
     */
    $('select[name=folder]').live('click', function(e) {
        $('span.sub-folders', $(this).closest('form')).css('display', $(this).val() == 'all' ? 'none' : 'inline');
    });

    /**
     * The onclick event handler for the menu entry of the advanced search. This makes the initial call
     * of the advanced search and fires a query to the server to get every important information.
     *
     * @param {object} e The event element
     */
    $('a.icon.advanced-search, a.button.advanced-search').live('click', function(e) {
        e.preventDefault();

        if (!$('#adsearch-popup').length) {
            rcmail.http_request('plugin.display_advanced_search');
        }
    });

    /**
     * Stop propagation of keydown and keypress events.
     * This should stop these events being processed by other listeners in the mailbox.
     *
     * @param {object} e The event element
     */
    $("#adsearch-popup").live('keydown keypress', function(e) {
        e.stopPropagation();
    });

    var advanced_search_redirect_draft_messages = function(check) {
        if (rcmail.env.search_request == "advanced_search_active") {
            if (rcmail.task == 'mail') {
                uid = rcmail.get_single_uid();
                if (uid && (!rcmail.env.uid || uid != rcmail.env.uid || check)) {
                    if ((rcmail.env.mailbox == rcmail.env.drafts_mailbox) || check) {
                        url = {
                            _mbox: this.env.mailbox,
                            _search: 'advanced_search_active'
                        };
                        url[this.env.mailbox == this.env.drafts_mailbox ? '_draft_uid' : '_uid'] = uid;
                        this.goto_url('compose', url, true);
                    }
                }
            }
            return true;
        }
        return false;
    }

    var advanced_search_perform_action = function(props, action) {

        var raw_selection = rcmail.message_list.get_selection();
        var md5_folders = rcmail.env.as_md5_folders;
        var i;
        var selections = {};
        for (i in raw_selection) {
            raw_selection[i];
            var parts = raw_selection[i].split('__MB__');
            var mid = parts[0];
            var md5Mbox = parts[1];
            var mbox = md5_folders[md5Mbox];
            if (!selections[mbox]) {
                selections[mbox] = [];
            }
            selections[mbox].push(mid);
        }

        if (i != undefined) {
            // show wait message
            if (rcmail.env.action == 'show') {
                lock = rcmail.set_busy(true, 'movingmessage');
            } else {
                rcmail.show_contentframe(false);
            }
            // Hide message command buttons until a message is selected
            rcmail.enable_command(rcmail.env.message_commands, false);
            var j;
            for (j in selections) {
                rcmail.select_folder(j, '', true);
                rcmail.env.mailbox = j;
                var uids = selections[j].join(',');
                var lock = false,
                    post_data = rcmail.selection_post_data({
                        _target_mbox: props.id,
                        _uid: uids
                    });
                rcmail._with_selected_messages(action, post_data, lock);
            }
            // Make sure we have no selection
            rcmail.env.uid = undefined;
            rcmail.message_list.selection = [];
        }

    }

    var advanced_search_check_multi_mbox = function () {
        var raw_selection = rcmail.message_list.get_selection();
        var md5_folders = rcmail.env.as_md5_folders;
        var i;
        var mcount = 0;
        var selections = {};
        for (i in raw_selection) {
            raw_selection[i];
            var parts = raw_selection[i].split('__MB__');
            var mid = parts[0];
            var md5Mbox = parts[1];
            var mbox = md5_folders[md5Mbox];
            if (!selections[mbox]) {
                selections[mbox] = [];
                mcount++;
            }
            selections[mbox].push(mid);
        }

        return {
            isMulti: mcount > 1,
            selections: selections
        };
    }

    /**
     * The roundcube init funtion, which registers and enables the advanced search command.
     */
    rcmail.addEventListener('init', function() {
        rcmail.register_command('plugin.advanced_search', true, true);
        rcmail.enable_command('plugin.advanced_search', true);

        rcmail.addEventListener('plugin.search_complete', function(r) {
            /* Start registering event listeners for handling drag/drop, marking, flagging etc... */
            rcmail.addEventListener('beforeedit', function (command) {
                rcmail.env.framed = true;
                if (advanced_search_redirect_draft_messages(true)) {
                    return false;
                }
            });

            rcmail.message_list.addEventListener('dblclick', function (o) {
                advanced_search_redirect_draft_messages();
            });

            rcmail.message_list.addEventListener('select', function (list) {
                if (rcmail.env.search_request == "advanced_search_active") {
                    if (list.selection.length == 1) {
                        var parts = list.selection[0].split('__MB__');
                        var mid = parts[0];
                        var md5Mbox = parts[1];
                        var mbox = rcmail.env.as_md5_folders[md5Mbox];
                        rcmail.env.uid = mid;
                        if (rcmail.env.mailbox != mbox) {
                            var ex = [];
                            li = rcmail.get_folder_li(mbox, '', true);
                            parent = $(li).parents(".mailbox");
                            parent.each(function () {
                                div = $(this.getElementsByTagName('div')[0]);
                                a = $(this.getElementsByTagName('a')[0]);
                                if (div.hasClass('collapsed')) {
                                    ex.push($(a).attr("rel"));
                                }
                            });
                            for (var i = ex.length - 1; i >= 0; i--) {
                                rcmail.command('collapse-folder', ex[i]);
                            }
                            rcmail.select_folder(mbox, '', true);
                            rcmail.env.mailbox = mbox;
                        }
                        return false;
                    }
                }
            });

            rcmail.addEventListener('beforemoveto', function (props) {
                if (rcmail.env.search_request == 'advanced_search_active') {
                    advanced_search_perform_action(props, 'moveto');

                    return false;
                }
            });

            rcmail.addEventListener('beforedelete', function (props) {
                if (rcmail.env.search_request == 'advanced_search_active') {
                    advanced_search_perform_action(props, 'delete');

                    return false;
                }
            });

            rcmail.addEventListener('beforemark', function (flag) {
                if (rcmail.env.search_request == 'advanced_search_active') {
                    var res = advanced_search_check_multi_mbox();
                    //Update on server
                    var i;
                    var sel = res.selections;
                    for (i in sel) {
                        var uids = sel[i].join(',');
                        var lock = false;
                        var post_data = {
                            _uid: uids,
                            _flag: flag,
                            _mbox: i,
                            _remote: 1
                        };
                        rcmail.http_post('mark', post_data, lock);
                    }
                    var raw_selection = rcmail.message_list.get_selection();
                    for(i in raw_selection) {
                        var key = raw_selection[i];
                        switch (flag) {
                            case 'read':
                                rcmail.message_list.rows[key].unread = 0;
                                break;
                            case 'unread':
                                rcmail.message_list.rows[key].unread = 1;
                                break;
                            case 'flagged':
                                rcmail.message_list.rows[key].flagged = 1;
                                break;
                            case 'unflagged':
                                rcmail.message_list.rows[key].flagged = 0;
                                break;
                        }
                    }
                    //Refresh ui
                    var messages = [];
                    var selections = rcmail.message_list.get_selection();
                    var j;
                    for (j in selections) {
                        messages.push('#rcmrow' + selections[j]);
                    }
                    var selector = messages.join(', ');
                    var selection = $(selector);
                    switch (flag) {
                    case 'read':
                        selection.removeClass('unread');
                        break;
                    case 'unread':
                        selection.addClass('unread');
                        break;
                    case 'flagged':
                        selection.addClass('flagged');
                        $("td.flag span", selection).removeClass('unflagged').addClass('flagged');
                        break;
                    case 'unflagged':
                        selection.removeClass('flagged');
                        $("td.flag span", selection).removeClass('flagged').addClass('unflagged');
                        break;
                    default:
                        break;
                    }
                    return false;
                }
            });

            rcmail.addEventListener('beforeforward', function (props) {
                if (rcmail.env.search_request == 'advanced_search_active' && rcmail.message_list.selection.length > 1) {
                    var res = advanced_search_check_multi_mbox();
                    if (res.isMulti == true) {
                        //Selections from more then one folder
                        return false;
                    } else {
                        //Only one folder, redirecting
                        var i, url, sel = res.selections;
                        for (i in sel) {
                            url = '&_forward_uid=' + sel[i].join(',') + '&_mbox=' + i;
                        }
                        url += '&_attachment=1&_action=compose';
                        window.location = location.pathname + rcmail.env.comm_path + url;

                        return false;
                    }
                }
            });

            rcmail.addEventListener('beforeforward-attachment', function (props) {
                if (rcmail.env.search_request == 'advanced_search_active' && rcmail.message_list.selection.length > 1) {
                    var res = advanced_search_check_multi_mbox();
                    if (res.isMulti == true) {
                        //Selections from more then one folder
                        return false;
                    } else {
                        //Only one folder, redirecting
                        var i, url, sel = res.selections;
                        for (i in sel) {
                            url = '&_forward_uid=' + sel[i].join(',') + '&_mbox=' + i;
                        }
                        url += '&_attachment=1&_action=compose';
                        window.location = location.pathname + rcmail.env.comm_path + url;

                        return false;
                    }
                }
            });

            rcmail.addEventListener('beforetoggle_flag', function (props) {
                if (rcmail.env.search_request == 'advanced_search_active') {
                    var flag = $(props).hasClass('unflagged') ? 'flagged' : 'unflagged';
                    var tr = $(props).closest('tr');
                    var id = tr.attr('id').replace('rcmrow', '');
                    var parts = id.split('__MB__');
                    var lock = false;
                    var mbox = rcmail.env.as_md5_folders[parts[1]];
                    var post_data = {
                        _uid: parts[0],
                        _flag: flag,
                        _mbox: mbox,
                        _remote: 1
                    };
                    rcmail.http_post('mark', post_data, lock);
                    if (flag == 'flagged') {
                        tr.addClass('flagged');
                        $("td.flag span", tr).removeClass('unflagged').addClass('flagged');
                        rcmail.message_list.rows[id].flagged = 1;
                    } else {
                        tr.removeClass('flagged');
                        $("td.flag span", tr).removeClass('flagged').addClass('unflagged');
                        rcmail.message_list.rows[id].flagged = 0;
                    }
                    return false;
                }
            });
            /* End registering event listeners */
        });

    });
})(jQuery);
