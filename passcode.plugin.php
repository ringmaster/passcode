<?php

namespace Habari;

class PasscodePlugin extends Plugin
{
	/**
	 * Executes when the plugin is loaded, each request
	 */
	public function action_init()
	{
		DB::register_table('passcodes');
	}

	/**
	 * Executes when this plugin is activated
	 */
	public function action_plugin_activation( )
	{
		// Let's make a passcodes table

		switch(DB::get_driver_name()) {

			case 'sqlite':

				$sql = <<< ADD_PERMISSIONS_TABLE_SQLITE
CREATE TABLE {\$prefix}passcodes (
  post_id INT UNSIGNED NOT NULL,
  passcode VARCHAR(255) NOT NULL,
  PRIMARY KEY (post_id, passcode)
);

ADD_PERMISSIONS_TABLE_SQLITE;

				break;

			default:

		$sql = <<< ADD_PERMISSIONS_TABLE_MYSQL
CREATE TABLE {\$prefix}passcodes (
  post_id INT UNSIGNED NOT NULL,
  passcode VARCHAR(255) NOT NULL,
  PRIMARY KEY (post_id, passcode)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;

ADD_PERMISSIONS_TABLE_MYSQL;

			break;
		}

		DB::dbdelta($sql);
	}

	/**
	 * Alter the query used to obtain posts so that the passcodes table is joined with the master permissions
	 * @param Query $query The query object used to fetch posts
	 * @param array $paramarray The array of parameters used to fetch these posts
	 */
	public function action_posts_get_query(Query $query, $paramarray)
	{
		// Create a new QueryWhere to override the core one, and allow it to see anything
		$old_master_perm_where = $query->where()->get_named('master_perm_where');

		$user = User::identify();

		// Don't use the core permissions if permissions are ignored
		// And only join permissions if the user is not a superuser, who can see everything
		if(isset($paramarray['passcode']) && (!isset($paramarray['ignore_permissions']) || $paramarray['ignore_permissions'] != true) && !$user->can('super_user')) {
			// Alter the master_perm_where to also allow access via passcode

			$passcode = $paramarray['passcode'];

			// Put the original master_perm_where into a new QueryWhere with an OR operator for the new criteria
			$master_perm_where = new QueryWhere('OR');
			$master_perm_where->add($old_master_perm_where);

			// This is the new criteria sub-where
			$by_passcode_where = new QueryWhere();
			$master_perm_where->add($by_passcode_where, array(), 'by_passcode_where');

			// Join the posts table to the passcodes
			$query->join('LEFT JOIN {passcodes} ON {posts}.id={passcodes}.post_id', array(), 'passcodes__allowed');
			$by_passcode_where->add('{passcodes}.passcode = :passcode', array('passcode' => $passcode));

			$query->where()->add($master_perm_where, array(), 'master_perm_where');
		}
	}

	public function filter_template_where_filters($paramarray)
	{
		if(isset($_GET['passcode'])) {
			$paramarray['passcode'] = $_GET['passcode'];
		}
		return $paramarray;
	}

}