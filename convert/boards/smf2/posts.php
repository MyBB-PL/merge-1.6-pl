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

class SMF2_Converter_Module_Posts extends Converter_Module_Posts {

	var $settings = array(
		'friendly_name' => 'postów',
		'progress_column' => 'id_msg',
		'default_per_screen' => 1000,
		'check_table_type' => 'messages',
	);

	var $cache_first_posts = array();

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->simple_select("messages", "*", "", array('limit_start' => $this->trackers['start_posts'], 'limit' => $import_session['posts_per_screen']));
		while($post = $this->old_db->fetch_array($query))
		{
			$pid = $this->insert($post);

			$db->update_query("threads", array("firstpost" => $pid), "import_firstpost = '".$post['id_msg']."'");
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		// SMF values
		$insert_data['import_pid'] = $data['id_msg'];
		$insert_data['tid'] = $this->get_import->tid($data['id_topic']);

		// Find if this is the first post in thread
		$first_post = $this->cache_first_post($data['id_topic']);

		// Make the replyto the first post of thread unless it is the first post
		if($first_post == $data['id_msg'])
		{
			$insert_data['replyto'] = 0;
		}
		else
		{
			$insert_data['replyto'] = $this->get_import->pid($first_post);
		}

		$insert_data['fid'] = $this->get_import->fid($data['id_board']);
		$insert_data['subject'] = encode_to_utf8(utf8_unhtmlentities($data['subject']), "messages", "posts");
		$insert_data['uid'] = $this->get_import->uid($data['id_member']);
		$insert_data['import_uid'] = $data['id_member'];
		$insert_data['username'] = $data['poster_name'];
		$insert_data['dateline'] = $data['poster_time'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert(utf8_unhtmlentities($data['body'])), "messages", "posts");
		$insert_data['ipaddress'] = $data['poster_ip'];

		if($data['smileys_enabled'] == '1')
		{
			$insert_data['smilieoff'] = 0;
		}
		else
		{
			$insert_data['smilieoff'] = 1;
		}

		// Get edit name
		if(!empty($data['modified_name']))
		{
			$query = $db->simple_select("users", "uid", "username='".$db->escape_string($data['modified_name'])."'", array('limit' => 1));
			$insert_data['edituid'] = $db->fetch_field($query, "uid");
			$db->free_result($query);
		}
		else
		{
			$insert_data['edituid'] = 0;
		}

		$insert_data['edittime'] = $data['modified_time'];

		return $insert_data;
	}

	function test()
	{
		// import_tid => tid
		$this->get_import->cache_tids = array(
			5 => 10,
		);

		// import_fid => fid
		$this->get_import->cache_fids = array(
			6 => 11,
		);

		// import_uid => uiid
		$this->get_import->cache_uids = array(
			7 => 12,
		);

		// tid => first_pid
		$this->cache_first_posts = array(
			10 => 15,
		);

		// import_pid -> pid
		$this->get_import->cache_posts = array(
			15 => 16,
		);

		$data = array(
			'id_msg' => 1,
			'ID_TOPIC' => 5,
			'ID_BOARD' => 6,
			'subject' => 'Test�fdfs��',
			'id_member' => 7,
			'posterName' => '#M�gaDeth(b)',
			'posterTime' => 12345678,
			'body' => 'Test, test, fdsfdsf ds dsf  est�fdf fdsfds s��',
			'posterIP' => '127.0.0.1',
			'smileysEnabled' => '1',
			'modifiedName' => '',
			'modifiedTime' => 0,
		);

		$match_data = array(
			'import_pid' => 1,
			'tid' => 10,
			'replyto' => 16,
			'fid' => 11,
			'uid' => 12,
			'subject' => utf8_encode('Test�fdfs��'), // The Merge System should convert the mixed ASCII/Unicode string to proper UTF8
			'username' => '#M�gaDeth(b)',
			'dateline' => 12345678,
			'message' => utf8_encode('Test, test, fdsfdsf ds dsf  est�fdf fdsfds s��'),
			'ipaddress' => '127.0.0.1',
			'smilieoff' => 0,
			'edituid' => 0,
			'edittime' => 0,
		);

		$this->assert($data, $match_data);
	}

	function after_insert($data, $insert_data, $pid)
	{
		global $db;

		// Is this the first post in a thread? Update the reply to value in the thread
		if($insert_data['replyto'] != 0)
		{
			$db->update_query("threads", array('replyto' => $this->get_import->pid($data['id_msg'])), "tid='{$insert_data['tid']}'");
		}

		$update_post['message'] = $db->escape_string(str_replace(array("[bgcolor=", "[/bgcolor]"), array("[color=", "[/color]"), preg_replace('#\[quote author\=(.*?) link\=topic\=([0-9]*).msg([0-9]*)\#msg([0-9]*) date\=(.*?)\]#i', "[quote='$1' pid='{$pid}' dateline='$5']", $insert_post['message'])));
		$db->update_query("posts", $update_post, "pid='{$pid}'");
	}

	function cache_first_post($tid)
	{
		global $db;

		if(array_key_exists($tid, $this->cache_first_posts))
		{
			return $this->cache_first_posts[$tid];
		}

		$query = $this->old_db->simple_select("topics", "id_first_msg", "id_topic = '{$tid}'", array('limit' => 1));
		$first_post = $this->old_db->fetch_field($query, "id_first_msg");
		$this->old_db->free_result($query);

		$this->cache_first_posts[$tid] = $first_post;

		return $first_post;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of posts
		if(!isset($import_session['total_posts']))
		{
			$query = $this->old_db->simple_select("messages", "COUNT(*) as count");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_posts'];
	}
}

?>