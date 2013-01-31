(function($) {
    /**
     * The fontend scripts for an advanced search.
     *
     * @version 1.0
     * @licence GNU GPLv3+
     * @author  Wilwert Claude
     * @author  Ludovicy Steve
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
        folders: {},
        criterias: {},
        prefered_criterias: {},
        messages: null
    };

    /**
     * This function generates a table dialog or a row for the table dialog, which is used to define the exact
     * parameters for the advanced search query.
     *
     * @param {bool} first True -> Create entire dialog; False -> Create single row
     */
    function createCriteria(first) {
        var html = [];

        if (first) {
            html.push('<div id="adsearch-popup">');
            html.push('<form method="post" action="#">');
            html.push('<table id="adv-search">');
            html.push('<thead><tr><td colspan="2">');

            html.push('Search in: <select name="folder">');
            html.push('<option value="all">All folders</option>');

            for(var i in $.stack.folders) {
                html.push('<option value="'+i+'">'+$.stack.folders[i]+'</option>');
            }

            html.push('</select>');
            html.push('<span class="sub-folders"> and subfolders <input type="checkbox" name="subfolder"> </span> where : ');
            html.push('</td></tr></thead>');
            html.push('<tbody><tr><td> </td><td>');
        } else {
            html.push('<tr><td> ');
            html.push('<select name="method"><option value="and">And</option><option value="or">Or</option></select>');
            html.push('</td><td>');
        }

        html.push('<select name="filter">');

        for(var i in $.stack.prefered_criterias) {
            html.push('<option value="'+$.stack.prefered_criterias[i]+'">'+$.stack.criterias[$.stack.prefered_criterias[i]]+'</option>');
        }

        html.push('<optgroup label="All criterias">'); 

        for(var i in $.stack.criterias) {
            html.push('<option value="'+i+'">'+$.stack.criterias[i]+'</option>');
        }

        html.push('</optgroup></select>');
        html.push(' Not<input type="checkbox" name="not" /><input type="text" name="filter-val" />');
        html.push(' Exclude:<input type="checkbox" name="filter-exclude" />');
        html.push('<button name="add" class="add">Add row</button>');

        if (!first) {
            html.push('<button name="delete" class="delete">Delete</button>');
        }

        html.push('</td></tr>');

        if (first) {
            html.push('</tbody><tfoot><tr><td colspan="2">');
            html.push('<input type="button" name="search" class="button mainaction" value="Search" />');
            html.push('<input type="button" name="reset" class="button reset" value="Reset" />');
            html.push('</tr></tfoot></table></form></div>');
        }

        html = html.join('');

        return html;
    }

    /**
     * The callback function of the initial dialog call. It creates the dialog and buffers the serverside
     * informations into an object.
     *
     * @param {object} r The serverside informations
     */
    rcmail.addEventListener('plugin.show', function(r) {
        $.stack.folders = r.folders;
        $.stack.criterias = r.criterias;
        $.stack.prefered_criterias = r.prefered_criterias;

        var $html = $(createCriteria(true));

        $('select[name=folder]', $html).val(rcmail.env.mailbox);

        $html.dialog({
            width: 550,
            height: 300,
            resizable: true,
            draggable: true,
            title: 'Advanced search',
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

        $(this).closest('tr').after(createCriteria(false));
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
     * The roundcube init funtion, which registers and enables the advanced search command. 
     */
    rcmail.addEventListener('init', function() {
        rcmail.register_command('plugin.advanced_search', true, true);
        rcmail.enable_command('plugin.advanced_search', true);
    });
})(jQuery);
