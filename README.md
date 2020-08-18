# Advanced Search Plugin for Roundcube

## Installation Instructions

### Composer
In the root of your Roundcube root directory using a terminal, type `composer require texxasrulez/advanced_searh` and it will automatically install.

### FTP
Download latest release from Github and upload via FTP to /roundcube_root/plugins/advanced_search.  
Add to Roundcube main config.inc.php in plugin array  

### GIT
* Clone the GitHub repository to 'advanced_search':

 >     git clone git://github.com/texxasrulez/roundcube-advanced-search.git advanced_search

* Change to the 'stable' branch:

 >     cd advanced_search
 >     git checkout -b stable origin/stable

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
