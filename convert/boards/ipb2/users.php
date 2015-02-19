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

class IPB2_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => "użytkowników",
		'progress_column' => "id",
		'encode_table' => "members",
		'postnum_column' => "posts",
		'username_column' => 'name',
		'email_column' => 'email',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		// Get members
		$query = $this->old_db->query("
			SELECT *
			FROM ".OLD_TABLE_PREFIX."members m
			LEFT JOIN ".OLD_TABLE_PREFIX."member_extra me ON (m.id=me.id)
			LEFT JOIN ".OLD_TABLE_PREFIX."members_converge mc ON (m.id=mc.converge_id)
			LIMIT ".$this->trackers['start_users'].", ".$import_session['users_per_screen']
		);
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Invision Power Board 2 values
		$insert_data['usergroup'] = $this->board->get_group_id($data['mgroup'], array("not_multiple" => true));
		$insert_data['additionalgroups'] = str_replace($insert_data['mgroup'], '', $this->board->get_group_id($data['mgroup']));
		$insert_data['displaygroup'] = $this->board->get_group_id($data['mgroup'], array("not_multiple" => true));
		$insert_data['import_usergroup'] = $this->board->get_group_id($data['mgroup'], array("not_multiple" => true, "original" => true));
		$insert_data['import_additionalgroups'] = $this->board->get_group_id($data['mgroup'], array("original" => true));
		$insert_data['import_displaygroup'] = $data['mgroup'];
		$insert_data['import_uid'] = $data['id'];
		$insert_data['username'] = encode_to_utf8($data['name'], "members", "users");
		$insert_data['email'] = $data['email'];
		$insert_data['regdate'] = $data['joined'];
		$insert_data['lastactive'] = $data['last_activity'];
		$insert_data['lastvisit'] = $data['last_visit'];
		$insert_data['website'] = $data['website'];
		$insert_data['avatardimensions'] = str_replace('x', '|', $data['avatar_size']);
		$insert_data['avatar'] = $data['avatar_location'];
		$insert_data['lastpost'] = $data['last_post'];
		$data['bday_day'] = trim($data['bday_day']);
		$data['bday_month'] = trim($data['bday_month']);
		$data['bday_year'] = trim($data['bday_year']);
		if(!empty($data['bday_day']) && !empty($data['bday_month']) && !empty($data['bday_year']))
		{
			$insert_data['birthday'] = $data['bday_day'].'-'.$data['bday_month'].'-'.$data['bday_year'];
		}
		$insert_data['icq'] = $data['icq_number'];
		$insert_data['aim'] = $data['aim_name'];
		$insert_data['yahoo'] = $data['yahoo'];
		$insert_data['msn'] = $data['msnname'];
		$insert_data['timezone'] = str_replace(array('.0', '.00'), array('', ''), $data['time_offset']);
		$insert_data['timezone'] = ((!strstr($insert_data['timezone'], '+') && !strstr($insert_data['timezone'], '-')) ? '+'.$insert_data['timezone'] : $insert_data['timezone']);
		$insert_data['style'] = 0;
		$insert_data['regip'] = $data['ip_address'];
		$insert_data['totalpms'] = $data['msg_total'];
		$insert_data['unreadpms'] = $data['new_msg'];
		$insert_data['dst'] = $data['dst_in_use'];
		$insert_data['signature'] =  encode_to_utf8($this->bbcode_parser->convert($data['signature']), "member_extra", "users");
		$insert_data['salt'] = $data['converge_pass_salt'];
		$insert_data['passwordconvert'] = $data['converge_pass_hash'];
		$insert_data['passwordconverttype'] = 'ipb2';

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of members
		if(!isset($import_session['total_users']))
		{
			$query = $this->old_db->simple_select("members", "COUNT(*) as count");
			$import_session['total_users'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_users'];
	}
}

?>