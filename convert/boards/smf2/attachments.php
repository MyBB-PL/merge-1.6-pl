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

class SMF2_Converter_Module_Attachments extends Converter_Module_Attachments {

	var $settings = array(
		'friendly_name' => 'załączników',
		'progress_column' => 'id_attach',
		'default_per_screen' => 20,
	);

	var $thumbs = array();

	var $cache_attach_filenames = array();

	function pre_setup()
	{
		global $import_session, $output;

		// Set uploads path
		if(!isset($import_session['uploadspath']))
		{
			$query = $this->old_db->simple_select("settings", "value", "variable = 'attachmentUploadDir'", array('limit' => 1));
			$import_session['uploadspath'] = $this->old_db->fetch_field($query, 'value');
			$this->old_db->free_result($query);

			if(empty($import_session['uploadspath']))
			{
				$query = $this->old_db->simple_select("settings", "value", "variable = 'avatar_url'", array('limit' => 1));
				$import_session['uploadspath'] = str_replace('avatars', 'attachments', $this->old_db->fetch_field($query, 'value'));
				$this->old_db->free_result($query);
			}
		}

		$this->check_attachments_dir_perms();

		if($mybb->input['uploadspath'])
		{
			// Test our ability to read attachment files from the forum software
			$this->test_readability("attachments", "id_attach,file_hash");
		}

		$output->print_inline_errors();
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("attachments", "*", "attachment_type != '3'", array('limit_start' => $this->trackers['start_attachments'], 'limit' => $import_session['attachments_per_screen']));
		while($attachment = $this->old_db->fetch_array($query))
		{
			if(in_array($attachment['id_attach'], $this->thumbs))
			{
				continue;
			}

			$this->insert($attachment);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// SMF values
		$insert_data['import_aid'] = $data['id_attach'];

		$insert_data['uid'] = $this->get_import->uid($data['id_member']);
		$insert_data['filename'] = $data['filename'];
		$insert_data['attachname'] = "post_".$insert_data['uid']."_".TIME_NOW.".attach";
		$insert_data['filetype'] = $data['mime_type'];

		// Check if this is an image
		switch(strtolower($insert_data['filetype']))
		{
			case "image/gif":
			case "image/jpeg":
			case "image/x-jpg":
			case "image/x-jpeg":
			case "image/pjpeg":
			case "image/jpg":
			case "image/png":
			case "image/x-png":
				$is_image = 1;
				break;
			default:
				$is_image = 0;
				break;
		}

		// Check if this is an image
		if($is_image == 1)
		{
			$insert_data['thumbnail'] = 'SMALL';
		}
		else
		{
			$insert_data['thumbnail'] = '';
		}

		$insert_data['filesize'] = $data['size'];
		$insert_data['downloads'] = $data['downloads'];

		$posthash = $this->get_import->post_attachment_details($data['id_msg']);
		$insert_data['pid'] = $posthash['pid'];
		if($posthash['posthash'])
		{
			$insert_data['posthash'] = $posthash['posthash'];
		}
		else
		{
			$insert_data['posthash'] = md5($posthash['tid'].$posthash['uid'].random_str());
		}

		if($data['id_thumb'] != 0)
		{
			$this->thumbs[] = $data['id_thumb'];

			$query = $this->old_db->simple_select("attachments", "*", "id_attach = '{$data['id_thumb']}'");
			$fdk = $this->old_db->fetch_array($query);
			$this->old_db->free_result($query);

			switch(strtolower($fdk['mime_type']))
			{
				case "image/gif":
					$ext = "gif";
					break;
				case "image/jpeg":
				case "image/x-jpg":
				case "image/x-jpeg":
				case "image/pjpeg":
				case "image/jpg":
					$ext = "jpg";
					break;
				case "image/png":
				case "image/x-png":
					$ext = "png";
					break;
			}

			$insert_data['thumbnail'] = str_replace(".attach", "_thumb.$ext", $insert_data['attachname']);
		}

		return $insert_data;
	}

	function after_insert($data, $insert_data, $aid)
	{
		global $import_session, $mybb, $db;

		// Transfer attachment thumbnail
		$thumb_not_exists = "";
		if($data['id_thumb'] != 0)
		{
			// Transfer attachment thumbnail
			$query = $this->old_db->simple_select("attachments", "*", "id_attach = '{$data['id_thumb']}'");
			$data['thumb_file_name'] = $data['id_thumb']."_".$this->old_db->fetch_field($query, "file_hash");

			$this->old_db->free_result($query);

			$attachment_thumbnail_file = merge_fetch_remote_file($import_session['uploadspath'].'/'.$data['thumb_file_name']);

			if(!empty($attachment_thumbnail_file))
			{
				$attachrs = @fopen($mybb->settings['uploadspath'].'/'.$insert_data['thumbnail'], 'w');
				if($attachrs)
				{
					@fwrite($attachrs, $attachment_thumbnail_file);
				}
				else
				{
					$this->board->set_error_notice_in_progress("Nie można przenieść załącznika (ID: {$aid})");
				}
				@fclose($attachrs);
				@my_chmod($mybb->settings['uploadspath'].'/'.$insert_data['thumbnail'], '0777');
			}
			else
			{
				$this->board->set_error_notice_in_progress("Nie można odnaleźć załącznika (ID: {$aid})");
			}
		}

		// Transfer attachment
		$attachment_file = merge_fetch_remote_file($import_session['uploadspath'].'/'.$data['id_attach']."_".$data['file_hash']);
		if(!empty($attachment_file))
		{
			$attachrs = @fopen($mybb->settings['uploadspath'].'/'.$insert_data['attachname'], 'w');
			if($attachrs)
			{
				@fwrite($attachrs, $attachment_file);
			}
			else
			{
				$this->board->set_error_notice_in_progress("Error transfering the attachment (ID: {$aid})");
			}
			@fclose($attachrs);
			@my_chmod($mybb->settings['uploadspath'].'/'.$insert_data['attachname'], '0777');
		}
		else
		{
			$this->board->set_error_notice_in_progress("Error could not find the attachment (ID: {$aid})");
		}

		if(!$posthash)
		{
			// Restore connection
			$db->update_query("posts", array('posthash' => $insert_data['posthash']), "pid = '{$insert_data['pid']}'");
		}

		$posthash = $this->get_import->post_attachment_details($data['id_msg']);
		$db->write_query("UPDATE ".TABLE_PREFIX."threads SET attachmentcount = attachmentcount + 1 WHERE tid = '".$posthash['tid']."'");
	}

	function test_attachment()
	{
		global $mybb, $import_session, $output;

		if($import_session['total_attachments'] <= 0)
		{
			return;
		}

		if($mybb->input['uploadspath'])
		{
			$import_session['uploadspath'] = $mybb->input['uploadspath'];
		}

		if(strpos($mybb->input['uploadspath'], "localhost") !== false)
		{
			$this->errors[] = "<p>You may not use \"localhost\" in the URL. Please use your Internet IP Address (Please make sure Port 80 is open on your firewall and router).</p>";
			$import_session['uploads_test'] = 0;
		}

		$query = $this->old_db->simple_select("attachments", "*", "", array('limit' => 1));
		$attachment = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		if(!is_readable($import_session['uploadspath'].'/'.$attachment['id_attach'].'_'.$attachment['file_hash']))
		{
			$this->errors[] = 'The attachments could not be read. Please adjust the <a href="http://wiki.mybb.com/index.php/CHMOD%20Files" target="_blank">chmod</a> permissions to allow it to be read from and ensure the URL is correct. If you are still experiencing issues, please try the full system path instead of a URL (ex: /var/www/htdocs/path/to/your/old/forum/uploads/).';
			$import_session['uploads_test'] = 0;
		}
	}

	function print_attachments_per_screen_page()
	{
		global $import_session;

		echo '<tr>
<th colspan="2" class="first last">Podaj adres do folderu z załącznikami silnika '.$this->plain_bbname.':</th>
</tr>
<tr>
<td><label for="uploadspath"> Adres URL folderu z załącznikami:
</label></td>
<td width="50%"><input type="text" name="uploadspath" id="uploadspath" value="'.$import_session['uploadspath'].'" style="width: 95%;" /></td>
</tr>';
	}

	function test()
	{
		$this->get_import->cache_uids = array(
			3 => 10
		);

		$this->get_import->cache_post_attachment_details = array(
			4 => array(
				'posthash' => 'ds12312dsffdsfs132123f5t54teuhybum',
				'pid' => 11,
				'tid' => 12,
				'uid' => 10,
			),
		);

		$data = array(
			'id_attach' => 2,
			'id_member' => 3,
			'filename' => 'testblarg.png',
			'size' => 1024,
			'downloads' => 50,
			'ID_MSG' => 4,
			'id_thumb' => 0,
		);

		$match_data = array(
			'import_aid' => 2,
			'thumbnail' => '',
			'uid' => 10,
			'attachname' => 'post_10_'.TIME_NOW.'.attach',
			'filesize' => 1024,
			'downloads' => 50,
			'pid' => 11,
			'posthash' => 'ds12312dsffdsfs132123f5t54teuhybum',
		);

		$this->assert($data, $match_data);
	}

	function get_import_attach_filename($aid)
	{
		if(array_key_exists($aid, $this->cache_attach_filenames))
		{
			return $this->cache_attach_filenames[$aid];
		}

		$query = $this->old_db->simple_select("attachments", "filename", "id_attach = '{$aid}'");
		$thumbnail = $this->old_db->fetch_array($query, "filename");
		$this->old_db->free_result($query);

		$this->cache_attach_filenames[$aid] = $thumbnail;

		return $thumbnail;
	}

	function generate_raw_filename($attachment)
	{
		return $attachment['id_attach']."_".$attachment['file_hash'];
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of attachments
		if(!isset($import_session['total_attachments']))
		{
			$query = $this->old_db->simple_select("attachments", "COUNT(*) as count", "attachment_type != '3'");
			$import_session['total_attachments'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_attachments'];
	}
}

?>