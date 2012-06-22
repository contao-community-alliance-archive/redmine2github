<?php

/**
 * redmine2github
 * Copyright (C) 2012 MEN AT WORK
 *
 * PHP version 5
 * @copyright MEN AT WORK 2012
 * @author Andreas Isaak <cms@men-at-work.de>
 * @author Stefan Heimes <cms@men-at-work.de>
 * @author Yanick Witschi <yanick.witschi@certo-net.ch>
 * @package redmine2github
 * @license LGPL
 * @filesource
 */

 
$arrRedmine2GithubConfig = array
(
	/**
	 * CSV path (relative to the directory where you execute Redmine2Github.php)
	 * @var string
	 */
	'csvPath' => 'export.csv',

	/**
	 * CSV delimiter
	 * @var string 
	 */
	'csvDelimiter' => ';',

	/**
	 * CSV keys
	 * Usually you don't need to change anything here if you did export the csv file correctly from Redmine
	 * The keys have to match the columns in your csv file
	 * @var array
	 */
	'csvKeys' => array
	(
		'id',
		'Project',
		'Tracker',
		'Parent task',
		'Status',
		'Priority',
		'Topic',
		'Author',
		'Assigned',
		'Updated',
		'Category',
		'Target Version',
		'Beginning',
		'Due Date',
		'Estimated time',
		'Percentage done',
		'Created',
		'Installed extensions',
		'Contao Version',
		'Description'
	),

	/**
	 * Repository data
	 * Enter the user data of a user that has the right to add labels and issues for this repository
	 */
	'repoURL'		=> 'https://api.github.com/repos/:user/:repo',
	'repoUser'		=> ':user',
	'repoPassword'	=> ':pass',

	/**
	 * Messages
	 */
	'originalAuthor'	=> '*--- Originally created by %s on %s. Ticket-Number was: %s*',
	'milestoneVersion'	=> 'Version %s',

	/**
	 * User information. You need the credentials of every user that wants to have his/her issues
	 * assigned to his/her user. You need to enter at least one user here as he/she is the fallback
	 * for all the tickets that have no user specified here.
	 * @var array
	 */
	'users'	=> array
	(
		// user name on Redmine
		'Octo Cat' => array
		(
			// user name and password on Github
			'login'		=> ':user',
			'password'	=> ':pass'
		)
	),

	/**
	 * Label information
	 * Here you can map existing labels from Redmine to new ones on Github
	 * @var array
	 */
	'labels' => array
	(
		// label on Redmine
		'Accepted' => array
		(
			// label and color on Github
			'name'		=> 'Accepted',
			'color'		=> 'dddddd'
		),
		'Completed' => array
		(
			'name'		=> 'Completed',
			'color'		=> 'dddddd'
		)
	),


	/**
	 * Status that should be closed automatically on GitHub
	 * @var array
	 */
	'closedStatus' => array
	(
		'Closed',
		'Completed'
	)
);
