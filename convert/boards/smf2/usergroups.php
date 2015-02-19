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

class SMF2_Converter_Module_Usergroups extends Converter_Module_Usergroups {

	var $settings = array(
		'friendly_name' => 'grup użytkowników',
		'progress_column' => 'id_group',
		'default_per_screen' => 1000,
	);

	var $permissions = array();

	function import()
	{
		global $import_session, $db;

		// Get only non-staff groups.
		$query = $this->old_db->simple_select("membergroups", "*", "id_group > 3 AND min_posts = -1", array('limit_start' => $this->trackers['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
		while($group = $this->old_db->fetch_array($query))
		{
			$gid = $this->insert($group);

			// Update our internal cache array
			$this->get_import->cache_gids[$group['id_group']] = $gid;

			// Restore connections
			$db->update_query("users", array('usergroup' => $gid), "import_usergroup = '{$group['id_group']}' OR import_displaygroup = '{$group['id_group']}'");

			$query2 = $db->simple_select("users", "uid, import_additionalgroups AS additional_groups", "CONCAT(',', import_additionalgroups, ',') LIKE '%,{$group['id_group']},%'");
			while($user = $db->fetch_array($query2))
			{
				$db->update_array("users", array('additionalgroups' => $this->board->get_group_id($user['additional_groups'])), "uid = '{$user['uid']}'");
			}
			$db->free_result($query2);
		}
	}

	function convert_data($data)
	{
		if(!$this->permissions)
		{
			// Cache permissions
			$this->permissions = $this->get_group_permissions();
		}

		$insert_data = array();

		// SMF values
		$insert_data['import_gid'] = $data['id_group'];
		$insert_data['type'] = 2;
		$insert_data['title'] = $data['group_name'];
		$insert_data['description'] = 'SMF imported group';
		if(!empty($data['online_color']))
		{
			$insert_data['namestyle'] = "<span style=\"color: {$data['online_color']}\">{username}</span>";
		}
		else
		{
			$insert_data['namestyle'] = '{username}';
		}

		$star_info = explode('#', $data['stars']);
		$insert_data['stars'] = $star_info[0];
		$insert_data['starimage'] = 'images/'.$star_info[1];
		$insert_data['canviewprofiles'] = $this->permissions[$data['id_group']]['profile_view_any'];
		$insert_data['candlattachments'] = $this->permissions[$data['id_group']]['view_attachments'];
		$insert_data['canpostthreads'] = $this->permissions[$data['id_group']]['post_new'];
		$insert_data['canpostreplys'] = $this->permissions[$data['id_group']]['post_reply_any'];
		$insert_data['canpostattachments'] = $this->permissions[$data['id_group']]['post_attachment'];
		$insert_data['caneditposts'] = $this->permissions[$data['id_group']]['modify_own'];
		$insert_data['candeleteposts'] = $this->permissions[$data['id_group']]['remove_own'];
		$insert_data['candeletethreads'] = $this->permissions[$data['id_group']]['delete_own'];
		$insert_data['caneditattachments'] = $this->permissions[$data['id_group']]['post_attachment'];
		$insert_data['canpostpolls'] = $this->permissions[$data['id_group']]['poll_post'];
		$insert_data['canvotepolls'] = $this->permissions[$data['id_group']]['poll_vote'];
		$insert_data['canusepms'] = $this->permissions[$data['id_group']]['pm_read'];
		$insert_data['cansendpms'] = $this->permissions[$data['id_group']]['pm_send'];
		$insert_data['canviewmemberlist'] = $this->permissions[$data['id_group']]['view_mlist'];
		$insert_data['canviewcalendar'] = $this->permissions[$data['id_group']]['calendar_view'];
		$insert_data['canaddevents'] = $this->permissions[$data['id_group']]['calendar_post'];
		$insert_data['canviewonline'] = $this->permissions[$data['id_group']]['who_view'];
		$insert_data['cancp'] = $this->permissions[$data['id_group']]['admin_forum'];
		$insert_data['issupermod'] = $this->permissions[$data['id_group']]['moderate_board'];
		$insert_data['cansearch'] = $this->permissions[$data['id_group']]['search_posts'];
		$insert_data['canusercp'] = $this->permissions[$data['id_group']]['profile_identity_own'];
		$insert_data['usereputationsystem'] = $this->permissions[$data['id_group']]['karma_edit'];
		$insert_data['cangivereputations'] = $this->permissions[$data['id_group']]['karma_edit'];
		$insert_data['cancustomtitle'] = $this->permissions[$data['id_group']]['profile_title_own'];

		return $insert_data;
	}

	function test()
	{
		$this->permissions = array(
			4 => array(
				'profile_view_any' => 1,
				'view_attachments' => 1,
				'post_new' => 1,
				'post_reply_any' => 1,
				'post_attachment' => 1,
				'modify_own' => 1,
				'remove_own' => 1,
				'delete_own' => 1,
				'poll_post' => 1,
				'poll_vote' => 1,
				'pm_read' => 1,
				'pm_send' => 1,
				'view_mlist' => 1,
				'calendar_view' => 1,
				'calendar_post' => 1,
				'who_view' => 1,
				'admin_forum' => 1,
				'moderate_board' => 1,
				'search_posts' => 1,
				'profile_identity_own' => 1,
				'karma_edit' => 1,
				'profile_title_own' => 1,
			),
		);

		$data = array(
			'minPosts' => -1,
			'id_group' => 4,
			'groupName' => 'Test abcdef',
			'onlineColor' => 'red',
			'stars' => '5#test_star.png',
		);

		$match_data = array(
			'import_gid' => 4,
			'type' => 2,
			'title' => 'Test abcdef',
			'description' => 'SMF imported group',
			'namestyle' => "<span style=\"color: red\">{username}</span>",
			'stars' => 5,
			'starimage' => 'images/test_star.png',
			'canviewprofiles' => 1,
			'candlattachments' => 1,
			'canpostthreads' => 1,
			'canpostreplys' => 1,
			'canpostattachments' => 1,
			'caneditposts' => 1,
			'candeleteposts' => 1,
			'candeletethreads' => 1,
			'caneditattachments' => 1,
			'canpostpolls' => 1,
			'canvotepolls' => 1,
			'canusepms' => 1,
			'cansendpms' => 1,
			'canviewmemberlist' => 1,
			'canviewcalendar' => 1,
			'canaddevents' => 1,
			'canviewonline' => 1,
			'cancp' => 1,
			'issupermod' => 1,
			'cansearch' => 1,
			'canusercp' => 1,
			'usereputationsystem' => 1,
			'cangivereputations' => 1,
			'cancustomtitle' => 1,
		);

		$this->assert($data, $match_data);
	}

	/**
	 * Get the usergroup permissions from SMF
	 *
	 * @return array group permissions
	 */
	function get_group_permissions()
	{
		$query = $this->old_db->simple_select("permissions", "*", "add_deny = 1");
		$permissions = array();
		while($permission = $this->old_db->fetch_array($query))
		{
			$permissions[$permission['id_group']][$permission['permission']] = 1;
		}
		$this->old_db->free_result($query);

		$query = $this->old_db->simple_select("board_permissions", "id_group, permission", "add_deny = 1");
		while($permission = $this->old_db->fetch_array($query))
		{
			$permissions[$permission['id_group']][$permission['permission']] = 1;
		}
		$this->old_db->free_result($query);

		return $permissions;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of usergroups
		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("membergroups", "COUNT(*) as count", "id_group > 3 AND min_posts = -1");
			$import_session['total_usergroups'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_usergroups'];
	}
}

?>