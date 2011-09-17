<?php
/**
 * Print the program list data only
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  TV
 *
/**/

// UTF-8 content
    @header("Content-Type: text/html; charset=utf-8");
?>

<div id="list_head" class="clearfix">
    <form class="form" id="program_listing" action="<?php echo root_url ?>tv/list" method="get">
    <div id="x_current_time"><?php
        echo t('Currently Browsing:  $1', strftime($_SESSION['date_statusbar'], $list_starttime))
    ?></div>
    <table id="x-jumpto" class="commandbox commands" border="0" cellspacing="0" cellpadding="0">
    <tr>
        <td class="x-jumpto"><?php echo t('Jump To') ?>:</td>
        <td class="x-hour"><?php hour_select('id="hour_select" onchange="list_update($(\'hour_select\')[$(\'hour_select\').selectedIndex].value);"') ?></td>
        <td class="x-day">
            <a class="link" onclick="list_update(<?php echo $list_starttime - (24 * 60 * 60); ?>);">
                <img src="<?php echo skin_url ?>img/left.gif" alt="<?php echo t('left'); ?>">
            </a>
            <?php date_select('id="date_select" onchange="list_update($(\'date_select\')[$(\'date_select\').selectedIndex].value);"') ?>
            <a class="link" onclick="list_update(<?php echo $list_starttime + (24 * 60 * 60); ?>);">
                <img src="<?php echo skin_url ?>img/right.gif" alt="<?php echo t('right'); ?>">
            </a>
        </td>
    </tr>
    </table>
    </form>
</div>

<table width="100%" border="0" cellpadding="4" cellspacing="2" class="list small">
<?php
        $timeslot_anchor    = 0;
        $channel_count      = 0;
        $displayed_channels = array();
        $channel			= 0;
        $timeslots_left		= 0;
        $timeslots_used		= 0;

		$last_chan_id		= -1;
		// this loads a program list, sorted by channel, then start time
        $program_list = load_program_list($list_starttime, $list_endtime);

        foreach ($program_list as $program) {
        // Get a modified start/end time for this program (in case it starts/ends outside of the aloted time
            $program_starts = $program->starttime;
            $program_ends   = $program->endtime;
            if ($program->starttime < $list_starttime)
                $program_starts = $list_starttime;
            if ($program->endtime > $list_endtime)
                $program_ends = $list_endtime;

        // check to see if we've moved onto a new channel row
        	if ($last_chan_id != $program->chanid){
	            $channel =& Channel::find($program->chanid);
		        // Ignore channels with no number
        	    if (strlen($channel->channum) < 1)
            	    continue;
		        // Skip already-displayed channels
        	    if ($displayed_channels[$channel->channum][$channel->callsign])
            	    continue;
            	$displayed_channels[$channel->channum][$channel->callsign] = 1;

        		if ($last_chan_id != -1){
        		// close the row, first rows have no rows to close
				// Uh oh, there are leftover timeslots - display a no data message
					if ($timeslots_left > 0) {
						$timeslots_left = $timeslots_used;
						require tmpl_dir.'list_cell_nodata.php';
					}
        			echo "<td>&nbsp;</td></tr>";
				}
        		$last_chan_id = $program->chanid;
				if (defined('theme_num_time_slots')) {
					$timeslots_left = theme_num_time_slots;
					$timeslot_size = theme_timeslot_size;
				} else {
					$timeslots_left = num_time_slots;
					$timeslot_size = timeslot_size;
				}
		        // Display the timeslot bar?
	            if ($channel_count % timeslotbar_skip == 0) {
		            // Update the timeslot anchor
        	        $timeslot_anchor++;
?><tr>
    <td class="menu" align="right"><a class="link" onclick="list_update(<?php echo $list_starttime - (timeslot_size * num_time_slots); ?>);" name="anchor<?php echo $timeslot_anchor ?>"><img src="<?php echo skin_url ?>img/left.gif" alt="left"></a></td>
<?php
	                $block_count = 0;
    	            foreach ($Timeslots as $time) {
        	            if ($block_count++ % timeslot_blocks)
            	            continue;
?>
    <td class="menu nowrap" colspan="<?php echo timeslot_blocks ?>" style="width: <?php echo intVal(timeslot_blocks * 94 / num_time_slots) ?>%" align="center"><a class="link" onclick="list_update(<?php echo $time; ?>);"><?php echo strftime($_SESSION['time_format'], $time) ?></a></td>
<?php
	                }
?>
    <td class="menu nowrap"><a class="link" onclick="list_update(<?php echo $list_starttime + (timeslot_size * num_time_slots); ?>);"><img src="<?php echo skin_url ?>img/right.gif" alt="right"></a></td>
</tr><?php
	            }
		        // Count this channel
	            $channel_count++;
			// Print the data
	?><tr>
		<td class="x-channel">
			<a href="<?php echo root_url ?>tv/channel/<?php echo $channel->chanid, '/', $list_starttime ?>"
					title="<?php
						echo t('Details for: $1',
							   html_entities($channel->name).'; '.$channel->channum)
					?>">
	<?php       if ($_SESSION["show_channel_icons"] == true && !empty($channel->icon)) { ?>
			<img src="<?php echo $channel->icon ?>" style="padding:5px;"><br>
	<?php       } ?>
			<?php echo ($_SESSION["prefer_channum"] ? $channel->channum : $channel->callsign), "\n" ?>
	<?php       if ($_SESSION["show_channel_icons"] == false || empty($channel->icon)) {
					echo '<br>('.($_SESSION["prefer_channum"] ? $channel->callsign : $channel->channum), ")\n";
	} ?>
			</a>
			</td>
	<?php
				if ($program_starts > $list_starttime) {
					$length = (($program_starts - $list_starttime) / $timeslot_size);
					if ($length >= 0.5) {
						$timeslots_used = ceil($length);
						require tmpl_dir.'list_cell_nodata.php';
						$list_starttime += $timeslots_used * timeslot_size;
						if ($timeslots_left < $timeslots_used)
							$timeslots_used = $timeslots_left;
						$timeslots_left -= $timeslots_used;
					}
				}
	        } // end new channel
	        if ($timeslots_left < 1)
                continue;

        // Calculate the number of time slots this program gets
            $length = (($program_ends - $program_starts) / $timeslot_size);
            if ($length < .5) continue; // ignore shows that don't take up at least half a timeslot
            $timeslots_used = ceil($length);
        // Increment $start_time so we avoid putting tiny shows (ones smaller than a timeslot) into their own timeslot
            //$list_starttime += $timeslots_used * $timeslot_size;
        // Make sure this doesn't put us over
            if ($timeslots_left < $timeslots_used)
                $timeslots_used = $timeslots_left;
            $timeslots_left -= $timeslots_used;
            #if ($timeslots_left > 0)
            require tmpl_dir.'list_cell_program.php';
        // Cleanup is good
            unset($program);

// Let the channel object figure out how to display its programs
//    $channel->display_programs($list_starttime, $list_endtime);
//            flush();
        }
?>
</table>
