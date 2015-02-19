<?php
/** 
* Polish Language File developed by mybboard.pl for MyBB Merge System
* Tłumaczenie: szulcu, Gigi
* Poprawki: Ekipa Polskiego Supportu MyBB (mybboard.pl)
* Wersja 1.0
**/
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class PUNBB_Converter extends Converter
{

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "punBB 1.2";

	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "punBB 1";

	/**
	 * Whether or not this module requires the loginconvert.php plugin
	 *
	 * @var boolean
	 */
	var $requires_loginconvert = true;

	/**
	 * Array of all of the modules
	 *
	 * @var array
	 */
	var $modules = array("db_configuration" => array("name" => "Konfiguracja bazy danych", "dependencies" => ""),
						 "import_usergroups" => array("name" => "Grupy użytkowników", "dependencies" => "db_configuration"),
						 "import_users" => array("name" => "Użytkownicy", "dependencies" => "db_configuration,import_usergroups"),
						 "import_categories" => array("name" => "Kategorie", "dependencies" => "db_configuration,import_users"),
						 "import_forums" => array("name" => "Działy", "dependencies" => "db_configuration,import_categories"),
						 "import_forumperms" => array("name" => "Uprawnienia do działów", "dependencies" => "db_configuration,import_forums"),
						 "import_threads" => array("name" => "Wątki", "dependencies" => "db_configuration,import_forums"),
						 "import_posts" => array("name" => "Posty", "dependencies" => "db_configuration,import_threads"),
						 "import_settings" => array("name" => "Ustawienia", "dependencies" => "db_configuration"),
						);

	/**
	 * Get a user from the punBB database
	 *
	 * @param string Username
	 * @return array If the uid is 0, returns an array of username as Guest.  Otherwise returns the user
	 */
	function get_user($username)
	{
		if(empty($username))
		{
			return array(
				'username' => 'Guest',
				'id' => 0,
			);
		}

		$query = $this->old_db->simple_select("users", "id, username", "username = '".$this->old_db->escape_string($username)."'", array('limit' => 1));

		$results = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		return $results;
	}

	/**
	 * Convert a punBB group ID into a MyBB group ID
	 *
	 * @param int Group ID
	 * @param array Options for retreiving the group ids
	 * @return mixed group id(s)
	 */
	function get_group_id($gid, $options=array())
	{
		static $groupcache;
		if(!isset($groupcache))
		{
			$groupcache = array();
			$query = $this->old_db->simple_select("groups", "g_id");
			while($punbbgroup = $this->old_db->fetch_array($query))
			{
				switch($punbbgroup['g_id'])
				{
					case 1: // Administrator
						$group = 4;
						break;
					case 2: // Moderator
						$group = 6;
						break;
					case 3: // Guest
						$group = 1;
						break;
					case 4: // Member
						$group = 2;
						break;
					default:
						$group = $this->get_import->gid($punbbgroup['g_id']);
						if($group <= 0)
						{
							// The lot
							$group = 2;
						}
				}
				$groupcache[$punbbgroup['g_id']] = $group;
			}
		}

		if(isset($groupcache[$gid]))
		{
			if($options['original'] == true)
			{
				return $gid;
			}
			else
			{
				return $groupcache[$gid];
			}
		}
		else
		{
			return 2; // Return regular registered user.
		}
	}
}

?>