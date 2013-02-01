
Advanced Search Plugin for Roundcube
====================================

## Usage

Please use the _'stable'_ brach for deployment.

Advantages:

* This version should be tested and bug-free
* It uses minified versions of the JavaScript

## License

This plugin is released under the GNU General Public License Version 3
or later (http://www.gnu.org/licenses/gpl.html).

Even if skins might contain some programming work, they are not considered
as a linked part of the plugin and therefore skins DO NOT fall under the
provisions of the GPL license. See the README file located in the core skins
folder for details on the skin license.

## Download

### GIT :
* Clone the GitHub repository to 'advanced _ search':

 >     git clone git://github.com/GMS-SA/roundcube-advanced-search.git advanced_search

* Change to the 'stable' branch:

 >     cd advanced_search
 >     git checkout -b stable

### ZIP :
* Swap branches to 'stable'
* Click on the 'ZIP' download icon
* Rename the unziped directory 'advanced _ search'

## Install

* Place the 'advanced _ search' plugin folder into the plugins directory of Roundcube.
* If using git and not wanting all the '.git' repository data in your live webmail:

 >     cd advanced_search
 >     git archive --format=tar --prefix=advanced_search/ stable | tar -x -C /path/to/roundcube/plugins/

  This will give you a git-free copy of the stable branch.
* Add advanced _ search to $rcmail _ config['plugins'] in your Roundcube config

## Credits

* Wilwert Claude
* Ludovicy Steve
* [Global Media Systems](http://www.gms.lu)
