# Advanced Search Plugin for Roundcube

Advanced Search Plugin for Roundcube
====================================

## Getting It

You can download direct from GitHub or consider using
the [plugin repository for Roundcube](http://plugins.roundcube.net/)

## Usage

After install, 'Advanced search' will show up under the 'more' menu.

Please use the _'stable'_ brach for deployment.

Advantages:

* This version should be tested and bug-free
* It uses minified versions of the JavaScript

## Requirements
Version 2.0.0 requires Roundcube 0.9.4 or later

## License

This plugin is released under the GNU General Public License Version 3
or later (http://www.gnu.org/licenses/gpl.html).

Even if skins might contain some programming work, they are not considered
as a linked part of the plugin and therefore skins DO NOT fall under the
provisions of the GPL license. See the README file located in the core skins
folder for details on the skin license.

## Download

### GIT :
* Clone the GitHub repository to 'advanced_search':

 >     git clone git://github.com/texxasrulez/roundcube-advanced-search.git advanced_search

* Change to the 'stable' branch:

 >     cd advanced_search
 >     git checkout -b stable origin/stable

### ZIP :
* Swap branches to 'stable'
* Click on the 'ZIP' download icon
* Rename the unziped directory 'advanced_search'

## Install

* Place the 'advanced_search' plugin folder into the plugins directory of Roundcube.
* If using git and not wanting all the '.git' repository data in your live webmail:

 >     cd advanced_search
 >     git archive --format=tar --prefix=advanced_search/ stable | tar -x -C /path/to/roundcube/plugins/

  This will give you a git-free copy of the stable branch.
* Add advanced_search to $rcmail_config['plugins'] in your Roundcube config

* To override defaults, copy the config-default.inc.php file to config.inc.php and modify

## Configuration

* Available search criterias 
* Targeted roundcube menu for the advanced search

:moneybag: **Donations** :moneybag:

If you use this plugin and would like to show your appreciation by buying me a cup of coffee, I surely would appreciate it. A regular cup of Joe is sufficient, but a Starbucks Coffee would be better ... \
Zelle (Zelle is integrated within many major banks Mobile Apps by default) - Just send to texxasrulez at yahoo dot com \
No Zelle in your banks mobile app, no problem, just click [Paypal](https://paypal.me/texxasrulez?locale.x=en_US) and I can make a Starbucks run ...

I forked this long since updated plugin that I personally love and will try to keep it alive.

Thanks and enjoy!

## Credits

* Wilwert Claude
* Ludovicy Steve
* Moules Chris
* [Global Media Systems](http://www.gms.lu)
* Gene Hawkins
