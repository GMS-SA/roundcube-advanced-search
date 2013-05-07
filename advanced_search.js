(function($) {
    /**
     * The fontend scripts for an advanced search.
     *
     * @version 1.1.1
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

        var $html = $(r.html);

        $('select[name=folder]', $html).val(rcmail.env.mailbox);

        $html.dialog({
            width: 600,
            height: 300,
            resizable: true,
            draggable: true,
            title: r.title,
            close: function() {
                $(this).remove();
            }
        });
    });

    /**
     * The callback function of an executed advanced search query. It cleanes up the old rows of the mail interface
     * before they get replaced by the search results.
     */
    rcmail.addEventListener('plugin.set_rowcount', function() {
        if ($.stack.messages !== null && $.stack.messages.length) {
            $.stack.messages.each(function() {
                $(this).remove();
            });
        }
    });

    /**
     * The onclick event handler for the search button. This generates the search query and sends them
     * to the server. It also stores the wrapped set of the old rows into an object for later cleanup.
     *
     * @param {object} e The event element
     */
    $('input[name=search]').live('click', function(e) {
        e.preventDefault();

        var $form = $(this).closest('form'),
            $tr = $('tr', $('tbody', $form)),
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

        rcmail.http_request('plugin.post_query',
                            {search: data,
                             current_folder: rcmail.env.mailbox,
                             folder: $('select[name=folder]', $form).val(),
                             sub_folders: $('input[name=subfolder]', $form).attr('checked') == 'checked'});
    });

    /**
     * The onclick event handler of the "reset search" button, which resets the advanced search
     * back to its initial state.
     *
     * @param {object} e The event element
     */
    $('input[name=reset]').live('click', function(e) {
        e.preventDefault();

        var $form = $(this).closest('form');

        $('tr:not(:first)', $('tbody', $form)).remove();
        $('option:first', $('select', $form)).attr('selected', 'selected');
        $('input[type=checkbox]:checked', $form).attr('checked', false);
        $('input[type=text]', $form).val('');
        $('select[name=folder]', $form).val(rcmail.env.mailbox);
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
        var row_input = $(this).nextUntil('tr','input[name=filter-val]');
        var old_avs_type = row_input.data("avs_type");
        
        if( $.inArray($(this).val(), $.stack.date_criteria) >= 0 ) {
            if(old_avs_type !== "date") {
                row_input.val('');
                row_input.datepicker({dateFormat: rcmail.env.date_format});
            }
            row_input.data("avs_type","date");
        }
        else if( $.inArray($(this).val(), $.stack.email_criteria) >= 0 ) {
            if(old_avs_type !== "email") {
                rcmail.init_address_input_events(row_input, "");
                rcmail.addEventListener('autocomplete_insert', function(e){ 
                    e.field.value = e.insert.replace(/.*<(\S*?@\S*?)>.*/, "$1");
                });
            }
            row_input.data("avs_type","email");
        }
        else if( $.inArray($(this).val(), $.stack.flag_criteria) >= 0 ) {
            if(old_avs_type !== "flag_criteria") {
                row_input.val('');
                row_input.hide();
            }
            row_input.data("avs_type","flag_criteria");
        }
        else {
            row_input.data("avs_type","none");
        }

        switch (old_avs_type) {
            case "date":
                if( (row_input.data("avs_type") !== "date") && row_input.hasClass("hasDatepicker") )
                    row_input.datepicker("destroy");
                break;
            case "email":
                if( (row_input.data("avs_type") !== "email") ) {
                    row_input.removeAttr("autocomplete");
                    row_input.unbind('keydown');
                    row_input.unbind('keypress');
                }
                break;
            case "flag_criteria":
                if( (row_input.data("avs_type") !== "flag_criteria") && !row_input.is(":visible") )
                    row_input.show();
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
    $('a.icon.advanced-search').live('click', function(e) {
        e.preventDefault();

        if (!$('#adsearch-popup').length) {
            rcmail.http_request('plugin.prepare_filter');
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

    /**
     * The roundcube init funtion, which registers and enables the advanced search command. 
     */
    rcmail.addEventListener('init', function() {
        rcmail.register_command('plugin.advanced_search', true, true);
        rcmail.enable_command('plugin.advanced_search', true);
    });
})(jQuery);
