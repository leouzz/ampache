<?php
/*

 Copyright (c) 2001 - 2006 Ampache.org
 All rights reserved.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License v2
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

*/
?>
<h3><?php echo _('Admin Controls'); ?></h3>
<?php if (!$tmp_playlist->vote_active()) { ?>
<form id="form_playlist" method="post" action="<?php echo conf('web_path'); ?>/tv.php" enctype="multipart/form-data" >
<?php echo _('Base Playlist'); ?>:
<?php show_playlist_dropdown(); ?>
<input type="hidden" name="action" value="create_playlist" />
<input type="submit" value="<?php echo _('Activate'); ?>" />
</form>
<?php 
} 

else { 
?>
<?php echo _('Democratic Play Active'); ?>&nbsp;
<form method="post" style="Display:inline;" action="<?php echo conf('web_path'); ?>/tv.php?action=send_playlist&amp;tmp_playlist_id=<?php echo scrub_out($tmp_playlist->id); ?>" enctype="multipart/form-data">
<select name="play_type">
	<?php 
		$controllers = get_localplay_controllers(); 
		foreach ($controllers as $controller) { 
	?>
	<option value="__<?php echo $controller; ?>"><?php echo ucfirst($controller); ?></option>
	<?php } // end foreach ?>
	<option value="stream"><?php echo _('Stream'); ?></option>
	<option value="downsample"><?php echo _('Downsample'); ?></option>
</select>
<input type="submit" value="<?php echo _('Play'); ?>" />
</form>
<a href="<?php echo $tmp_playlist->get_vote_url(); ?>"><?php echo _('Play'); ?></a><br />
<?php echo _('Base Playlist'); ?>: 
<form method="post" style="Display:inline;" action="<?php echo conf('web_path'); ?>/tv.php?action=update_playlist&amp;playlist_id=<?php echo $tmp_playlist->base_playlist; ?>" enctype="multipart/form-data">
	<?php show_playlist_dropdown($tmp_playlist->base_playlist); ?>		
	<input type="hidden" name="tmp_playlist_id" value="<?php echo $tmp_playlist->id; ?>" />
	<input type="submit" value="<?php echo _('Update'); ?>" />
</form>
<?php } ?>