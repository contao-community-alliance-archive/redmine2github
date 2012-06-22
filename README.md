Redmine2Github
======================

About
-----

This is a script that allows you to easily merge your Redmine tickets to a Github project


Usage
---------------

Export all the redmine tickets as a csv file and place it into the same directory you put Redmine2Github.
Then open the config.php and enter all the data (you can also specify another config file).

Then execute the php file on your CLI. You can specify the following commands:

Basic usage
```
php Redmine2Github.php
```

Specify another config file than "config.php"
```
php Redmine2Github.php config:myconfig
```

Only import a specific ticket id (useful for testing purposes)
```
php Redmine2Github.php 133
```

Log old and new ticket number relations so you can replace them in your changelog or whatever.
The cool thing is you can specify your own template and generate any output you like.
The output will log in "ticketrelations.log"
```
php Redmine2Github.php tl:tl_template
```

Obviously you can combine all of these commands!