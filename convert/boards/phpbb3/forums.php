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

class PHPBB3_Converter_Module_Forums extends Converter_Module_Forums {

	var $settings = array(
		'friendly_name' => 'działów',
		'progress_column' => 'forum_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->simple_select("forums", "*", "", array('limit_start' => $this->trackers['start_forums'], 'limit' => $import_session['forums_per_screen']));
		while($forum = $this->old_db->fetch_array($query))
		{
			$fid = $this->insert($forum);

			// Update parent list.
			if($forum['forum_type'] == '0')
			{
				$db->update_query("forums", array('parentlist' => $fid), "fid = '{$fid}'");
			}
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 3 Values
		$insert_data['import_fid'] = intval($data['forum_id']);
		$insert_data['name'] = encode_to_utf8($this->fix_ampersand($data['forum_name']), "forums", "forums");
		$insert_data['description'] = encode_to_utf8($this->bbcode_parser->convert($data['forum_desc']), "forums", "forums");
		$insert_data['disporder'] = $data['left_id'];
		$insert_data['open'] = int_to_01($data['forum_status']);

		// Are there rules for this forum?
		if($data['forum_rules_link'])
		{
			$insert_data['rules'] = $data['forum_rules_link'];
		}
		else
		{
			$insert_data['rules'] = $data['forum_rules'];
		}
		$insert_data['rulestype'] = 1;

		// We have a category
		if($data['forum_type'] == '0')
		{
			$insert_data['linkto'] = '';
			$insert_data['type'] = 'c';
			$insert_data['import_pid'] = $data['parent_id'];
		}
		// We have a forum
		else
		{
			// Is this a redirect forum?
			$insert_data['linkto'] = '';
			if($data['forum_type'] == '2')
			{
				$insert_data['linkto'] = $data['forum_link'];
			}
			$insert_data['type'] = 'f';
			$insert_data['import_pid'] = $data['parent_id'];
		}

		// TODO: last post data?

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of forums
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("forums", "COUNT(*) as count");
			$import_session['total_forums'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_forums'];
	}

	function test()
	{
		$this->get_import->cache_fids_f = array(
			5 => 11,
		);

		$data = array(
			'forum_id' => 2,
			'forum_name' => 'estéfdf fdsfds &amp; s˙˙',
			'forum_desc' => 'Test, test, fdsfdsf ds dsf  estéfdf fdsfds s˙˙',
			'left_id' => 12,
			'forum_status' => 0,
			'forum_rules_link' => '',
			'forum_rules' => 'RULES TEST',
			'forum_type' => 1,
			'parent_id' => 5,
		);

		$match_data = array(
			'import_fid' => 2,
			'name' => utf8_encode('estéfdf fdsfds & s˙˙'),
			'description' => utf8_encode('Test, test, fdsfdsf ds dsf  estéfdf fdsfds s˙˙'),
			'disporder' => 12,
			'open' => 1,
			'rules' => 'RULES TEST',
			'rulestype' => 1,
			'linkto' => '',
			'type' => 'f',
			'import_pid' => 5,
		);

		$this->assert($data, $match_data);
	}

	/**
	 * Correctly associate any forums with their correct parent ids. This is automagically run after importing
	 * forums.
	 */
	function cleanup()
	{
		global $db;

		$query = $db->query("
			SELECT f.fid, f2.fid as updatefid, f.import_fid
			FROM ".TABLE_PREFIX."forums f
			LEFT JOIN ".TABLE_PREFIX."forums f2 ON (f2.import_fid=f.import_pid)
			WHERE f.import_pid != 0 AND f.pid = 0
		");
		while($forum = $db->fetch_array($query))
		{
			$db->update_query("forums", array('pid' => $forum['updatefid'], 'parentlist' => make_parent_list($forum['import_fid'])), "fid='{$forum['fid']}'", 1);
		}
	}
}

?>