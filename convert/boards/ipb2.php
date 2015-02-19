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

class IPB2_Converter extends Converter {

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "Invision Power Board 2.1, 2.2 lub 2.3";

	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "Invision Power Board 2";

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
						 "import_forums" => array("name" => "Działy", "dependencies" => "db_configuration,import_users"),
						 "import_forumperms" => array("name" => "Uprawnienia do działów", "dependencies" => "db_configuration,import_forums"),
						 "import_threads" => array("name" => "Wątki", "dependencies" => "db_configuration,import_forums"),
						 "import_polls" => array("name" => "Ankiety", "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Głosy w ankietach", "dependencies" => "db_configuration,import_polls"),
						 "import_posts" => array("name" => "Posty", "dependencies" => "db_configuration,import_threads"),
						 "import_moderators" => array("name" => "Moderatorzy", "dependencies" => "db_configuration,import_forums,import_users"),
						 "import_privatemessages" => array("name" => "Prywatne wiadomości", "dependencies" => "db_configuration,import_users"),
						 "import_settings" => array("name" => "Ustawienia", "dependencies" => "db_configuration"),
						 "import_events" => array("name" => "Wydarzenia w kalendarzu", "dependencies" => "db_configuration,import_users"),
						 "import_attachments" => array("name" => "Załączniki", "dependencies" => "db_configuration,import_posts"),
						);

	var $supported_versions = array(
			'name' => 'IPB seria 2.x',
			'2.1' => 'IPB 2.1',
			'2.2' => 'IPB 2.2',
			'2.3' => 'IPB 2.3',
		);

	/**
	 * Convert a IPB group ID into a MyBB group ID
	 *
	 * @param int Group ID
	 * @param array Options for retreiving the group ids
	 * @return mixed group id(s)
	 */
	function get_group_id($gid, $options=array())
	{
		if($options['not_multiple'] != true)
		{
			$query = $this->old_db->simple_select("groups", "COUNT(*) as rows", "g_id='{$gid}'");
			$query = $this->old_db->simple_select("groups", "*", "g_id='{$gid}'", array('limit_start' => '1', 'limit' => $this->old_db->fetch_field($query, 'rows')));
		}
		else
		{
			$query = $this->old_db->simple_select("groups", "*", "g_id='{$gid}'");
		}

		$comma = $group = '';
		while($ipbgroup = $this->old_db->fetch_array($query))
		{
			if($options['original'] == true)
			{
				$group .= $ipbgroup['g_id'].$comma;
			}
			else
			{
				$group .= $comma;
				switch($ipbgroup['g_id'])
				{
					case 1: // Awaiting activation
						$group .= 5;
						break;
					case 2: // Guests
						$group .= 1;
						break;
					case 3: // Registered
						$group .= 2;
						break;
					case 5: // Banned
						$group .= 7;
						break;
					case 4: // Root Admin
					case 6: // Administrator
						$group .= 4;
						break;
					default:
						$gid = $this->get_import->gid($ipbgroup['g_id']);
						if($gid > 0)
						{
							// If there is an associated custom group...
							$group .= $gid;
						}
						else
						{
							// The lot
							$group .= 2;
						}
				}
			}
			$comma = ',';
		}

		if(!$query)
		{
			return 2; // Return regular registered user.
		}

		$this->old_db->free_result($query);
		return $group;
	}
}

?>