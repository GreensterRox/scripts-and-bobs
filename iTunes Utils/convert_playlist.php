<?php
/*
>> mv /media/sf_Ubuntu_local/iTunes\ Music\ Library.xml /home/adrian/projects/create_playlist/
>> cp /media/sf_Ubuntu_local/convert_playlist.php . ; php -f convert_playlist.php "165 (ex)"

** This now build a big assoc array of tracks and locations $TRACKS
e.g.:
	[28897]=>
  string(115) "/media/sf_Ubuntu_local/Copy_Of_Itunes_Data/Compilations/Hip Young Guitar Slinger [Disc 2]/2-12 Step Out Of Line.mp3"
  [28899]=>
  string(106) "/media/sf_Ubuntu_local/Copy_Of_Itunes_Data/Compilations/Hip Young Guitar Slinger [Disc 2]/2-13 Moanin'.mp3"
  
NEXT Job 
1.) find playlist id that we are interested in (via command line args) and copy all thos songs into a directory
2.) Then mount this temp directory onto docker ffmpeg container and run the encoding process to turn them all into MP3
3.) Then re-order them numerically based on playlist order
4.) Test with USB stick
*/
echo "Starting......"."\n";
if(empty($argv[1])){
	die('Usage: '."\n".'php -f convert_playlist.php "$playlist_name"'."\n".'e.g.'."\n".'php -f convert_playlist.php "Christmas"'."\n\n");
} else {
	$PLAYLIST = $argv[1];
	echo "Building a playlist for [".$PLAYLIST."]......"."\n";
}

// constants
define ('DICT','<dict>');
define('DICT_END','</dict>');
define('PLAYLIST_SECTION','<key>Playlists</key>');
define('PLAYLIST_XREF_START','<array>');
define('PLAYLIST_XREF_END','</array>');
define('PLAYLIST_START','<key>Playlist ID</key>');	// this assumes Playlist ID is always the first field ?? This caught me about before ! They must have changed the order in a newer version of iTunes

$fp = fopen('iTunes Music Library.xml',"r");
if(!$fp){
	die('Where\'s the file dude ?');
}

$allFields = $sql = $TRACKS = $PLAYLIST_DATA = array();
$bad_stuff = array('"','\\');

$counter = $dict_counter = 0;
//$safety = 200;
$update_count = $insert_count = $playcount_history_insert_count = 0;

$in_song = false;
$in_playlist = false;

$song_count = 0;

while ($line = utf8_encode(fgets($fp))){
	$counter++;
	
	if(isset($safety) && ($counter > $safety)){
		break;
	}
	
	$line = trim($line);
	if(empty($line)){
		continue;
	}
	
	if(strpos($line,PLAYLIST_SECTION) !== false){
		//echo 'Playlist section found - don\'t know how to process this';
		break;
	}
	
	// once we get to 2nd 'dict' we are into the songs
	if(strpos($line,DICT) !== false){
		$dict_counter++;
	}
	
	if($dict_counter == 3 && !$in_song){
		$song_count++;
		$in_song = true;
		//echo 'Found a song!<br/>';
		$sql = array();
		$where_sql = 'do not run without a WHERE clause!!!';
		$thisSong = array();
		continue;
	}
	
	if($in_song && strpos($line,DICT_END) !== false){
		$in_song = false;
		$dict_counter--;
		
		if(count($sql) > 0){
			store_track($sql);
			// see if record exists - if not run an insert
			$select_sql = 'SELECT COUNT(*) as cnt FROM itunes.itunes_library '.$where_sql;
			//$result = mysql_query($select_sql);
			//if(!$result){
			//	die (mysql_error());
			//}
			//$rs = mysql_fetch_array($result);
			//if(empty($rs['cnt'])){
			//	$write_sql = 'INSERT INTO itunes.itunes_library SET '.implode(' , ',$sql);
			//	$insert_count++;
			//} else {
			//	$write_sql = 'UPDATE itunes.itunes_library SET '.implode(' , ',$sql).' '.$where_sql;
			//	//print 'Doing UPDATE ['.$write_sql.']<br/>';
			//	$update_count++;
			//}
			//$update_result = mysql_query($write_sql);
			//if(!$update_result){
			//	print $write_sql.'<hr>';
			//	die (mysql_error());
			//}
			
			// Capture playlist count history
			//if(!empty($thisSong['persistent_id']) && isset($thisSong['play_count'])){
			if(!empty($thisSong['persistent_id'])){
				if(!isset($thisSong['play_count'])){
					$thisSong['play_count'] = 0;
				}
			//	$playcount_insert = 'INSERT INTO itunes.playcount_history (persistent_id,playcount,capture_date) VALUES ("'.$thisSong['persistent_id'].'",'.$thisSong['play_count'].',NOW() )';
				//print $playcount_insert.'<br/>';
			//	$count_result = mysql_query($playcount_insert);
				// Ignore dupe key errors (note: will only be duplicate if date is the same)
			//	if(!$count_result){
			//		$playcount_error = trim(mysql_error());
			//		if(substr($playcount_error,0,15) == 'Duplicate entry'){
						// ignore
			//		} else {
			//			die(mysql_error());
			//		}
			//	} else {
			//		$playcount_history_insert_count++;
			//	}
			}
		}
	}

	
	if($in_song){
		list($db_field,$db_value) = process_field($line);
		if(!$db_field){
			die(var_dump('aborted on line '.$counter.' could not process song field'));
		}
		//$sql[] = $db_field.' = "'.str_replace($bad_stuff,'',$db_value.'"').'"';
		$sql[] = $db_field.' = "'.str_replace($bad_stuff,'',$db_value.'"').'"';
		if($db_field == 'persistent_id'){
			$where_sql = 'WHERE persistent_id = "'.$db_value.'" LIMIT 1';
			$thisSong['persistent_id'] = $db_value;
		}
		if($db_field == 'play_count'){
			$thisSong['play_count'] = $db_value;
		}
	}
}

echo 'Found '.$song_count.' songs'."\n";
//echo $update_count.' songs were updated<br/>';
//echo $insert_count.' songs were inserted<br/>';
//echo $playcount_history_insert_count.' playcount history records updated';
//print_r(array_keys($allFields));

echo 'Finished track import'."\n";

fclose($fp);

function process_field($line){
	global $allFields;
	
	//print htmlentities($line).'<br/>';
	
	// get field
	$pattern = '~<key>.*</key>~';
	preg_match($pattern, $line, $matches);
	if(!empty($matches[0])){
		$field = trim(strip_tags($matches[0]));
		$allFields[$field] = $field;
		$line = str_replace($field,'',$line);
		$value = html_entity_decode(trim(strip_tags($line)));
		$status = true;
		
		// We could dynamcially add the field to the database??
		$db_field = strtolower(str_replace(' ','_',$field));
		//echo 'Field: ['.$db_field.'] >>> ['.$value.']<hr>';
		return array($db_field,$value);
		
	} else {
		//echo 'Couldn\'t process fields for this line: ['.$line.']';
		return false;
	}
}

/*****************************************************************
** Now do the playlists !
*****************************************************************/

$fp = fopen('iTunes Music Library.xml',"r");
if(!$fp){
	die('Where\'s the file dude ?');
}

$allFields = $meta_sql = $playlist_xref = array();
$bad_stuff = array('"','\\');

$counter = $dict_counter = $playlist_counter = $array_counter = 0;
//$safety = 2000000;
$update_playlist_count = $insert_playlist_count = 0;

$in_song = false;
$in_playlist = false;
$in_playlist_section = false;
$in_playlist_xref = false;
$written_playlist = false;

$song_count = 0;



while ($line = fgets($fp)){
	$counter++;
	
	if(isset($safety) && ($counter > $safety)){
		break;
	}
	
	$line = trim($line);
	if(empty($line)){
		continue;
	}
	
	if(strpos($line,PLAYLIST_SECTION) !== false){
		$in_playlist_section = true;
	}
	
	// once we get to 2nd 'dict' we are into the playlists
	if(strpos($line,DICT) !== false){
		$dict_counter++;
		//echo 'Dict got bigger ('.$dict_counter.') ['.$counter.']<br/>';
	}
	
	if(strpos($line,DICT_END) !== false){
		$dict_counter--;
		//echo 'Dict got smaller ('.$dict_counter.') ['.$counter.']<br/>';
	}
	
	if(!$in_playlist_section){
		continue;	// keep going till we hit this	
	}
	
	if(strpos($line,PLAYLIST_XREF_START) !== false){
		$array_counter++;
		//echo 'Array got bigger ('.$array_counter.') ['.$counter.']<br/>';
	}
	
	if(strpos($line,PLAYLIST_XREF_END) !== false){
		$array_counter--;
		//echo 'Array got smaller ('.$array_counter.') ['.$counter.']<br/>';
	}
	
	if(($dict_counter == 2 && !$in_playlist && $array_counter < 2) || ((strpos($line,PLAYLIST_START) !== false && count($meta_sql) > 0))){
		$in_playlist = true;
		$playlist_counter++;
		//echo 'Found a playlist on line ['.$counter.']!<br/>';
		$this_playlist_persistent_id = 0;
		$meta_sql = array();
	}
	
	if((strpos($line,PLAYLIST_START) !== false && count($meta_sql) > 0) || ($in_playlist && strpos($line,PLAYLIST_XREF_START) !== false && $array_counter == 2)){
		
		//echo 'Writing out playlist id ['.$this_playlist_persistent_id.'] to DB ['.$counter.']<br/>';
		
		if(count($meta_sql) > 0){
			// see if record exists - if not run an insert
			process_playlist($meta_sql);
			/*$select_sql = 'SELECT COUNT(*) as cnt FROM itunes.playlists '.$meta_where_sql;
			$result = mysql_query($select_sql);
			if(!$result){
				die (mysql_error());
			}
			$rs = mysql_fetch_array($result);
			if(empty($rs['cnt'])){
				$write_sql = 'INSERT INTO itunes.playlists SET '.implode(' , ',$meta_sql);
				$insert_playlist_count++;
			} else {
				$write_sql = 'UPDATE itunes.playlists SET '.implode(' , ',$meta_sql).' '.$meta_where_sql;
				$update_playlist_count++;
			}
			$update_result = mysql_query($write_sql);
			if(!$update_result){
				print $write_sql.'<hr>';
				die (mysql_error());
			}*/
		}
		
		$in_playlist=false;

	}
	
	if($in_playlist && strpos($line,DICT) === false && !$in_playlist_xref && $array_counter < 2 && strpos($line,DICT_END) === false){
		list($db_field,$db_value) = process_field2($line);
		if(!$db_field){
			//echo 'skipping on line '.$counter.' could not process field<br/>';
		} else {
			$meta_sql[] = $db_field.' = "'.str_replace($bad_stuff,'',$db_value.'"').'"';
			if($db_field == 'playlist_persistent_id'){
				$meta_where_sql = 'WHERE playlist_persistent_id = "'.$db_value.'" LIMIT 1';
				$this_playlist_persistent_id = $db_value;
				//echo 'Setting playlist persistent id ['.$this_playlist_persistent_id.']';
			}
		}
	}
	
	if(strpos($line,PLAYLIST_XREF_END) !== false){
		$in_playlist_xref = false;
	}
	
	if($dict_counter == 3 && strpos($line,DICT) === false){
		//echo 'processing xref ['.$counter.']<br/>';
		$in_playlist_xref = true;

		if(empty($this_playlist_persistent_id)){
			die ('No playlist id ??');
		}
		// playlist xref
		list($db_field,$db_value) = process_field2($line);
		$playlist_xref[$this_playlist_persistent_id][] = $db_value;
	}
	
}


echo 'Found '.$playlist_counter.' playlists'."\n";
//echo $update_playlist_count.' playlists were updated<br/>';
//echo $insert_playlist_count.' playlists were inserted<br/>';

// now process the playlist files
$xref_count = 0;
foreach ($playlist_xref as $playlist_persistent_id => $track_id_array){
	if($playlist_persistent_id == $PLAYLIST_DATA['playlist_persistent_id']){
		$PLAYLIST = preg_replace('/\s+|[()]/','_',$PLAYLIST);
		$playlist_directory = '/home/adrian/projects/create_playlist/playlists/'.$PLAYLIST;
		if(file_exists($playlist_directory) || mkdir($playlist_directory)){
			$order=1;
			foreach($track_id_array as $track_id){
				if($order < 10){
					$display_order = '0'.$order;
				} else {
					$display_order = $order;
				}
				$file_part = explode('/',$TRACKS[$track_id]);
				
				//$destination preg_replace('/\s+/','_',$file_part[4])= $playlist_directory.'/'.$display_order.'_'.preg_replace('/\s+|^\d+/','_',basename($TRACKS[$track_id]));
				$artist = preg_replace('/\s+/','_',$file_part[4]);
				if(strtolower($artist) == 'compilations'){
					$artist = preg_replace('/\s+/','_',$file_part[5]);
				}
				$artist = preg_replace('/\'/','',$artist);
				$trackName = preg_replace('/^\d?.?\d+|\'/','',basename($TRACKS[$track_id]));
				$trackName = preg_replace('/\s+|\(|\)/','_',$trackName);
				$destination = $playlist_directory.'/'.$display_order.'_'.$artist.'_'.$trackName;
				
				//var_dump($artist);
				//var_dump($trackName);
				//var_dump($destination);
				//continue;
				echo 'Copying Track ['.$TRACKS[$track_id].'] to ['.$destination.']'."\n";
				if(!copy($TRACKS[$track_id],$destination)){
					die('Could not copy  to ['.$destination.']');
				}
				
				// check destination file type - if not mp3 convert it !
				if(strtolower(substr($destination,-3)) !== 'mp3'){
					$docker_cmd = 'docker run -it -v '.str_replace(basename($destination),'',$destination).':/files sjourdan/ffmpeg -stats -i /files/'.basename($destination).' /files/'.substr(basename($destination),0,-3).'mp3';
					echo 'Not an MP3 - CONVERTING ! ['.$destination.']'."\n".$docker_cmd."\n";
					passthru($docker_cmd);
					unlink($destination);
				}
				
				$order++;
			}
		} else {
			die('Could not create playlist directory ['.$playlist_directory.']');
		}
	}
}

//echo 'Stored ['.$xref_count.'] playlist_xref data';
echo 'Finished'."\n\n";

function process_field2($line){
	global $allFields;
	
	//print htmlentities($line).'<br/>';
	
	// get field
	$pattern = '~<key>.*</key>~';
	preg_match($pattern, $line, $matches);
	if(!empty($matches[0])){
		$field = trim(strip_tags($matches[0]));
		$allFields[$field] = $field;
		$line = str_replace($field,'',$line);
		$value = trim(strip_tags($line));
		$status = true;
		
		// We could dynamcially add the field to the database??
		$db_field = strtolower(str_replace(' ','_',$field));
		//echo 'Field: ['.$db_field.'] >>> ['.$value.']<hr>';
		if(!empty($db_field)){
			return array($db_field,$value);
		}
		
	} else {
		//echo 'Couldn\'t process fields for this line: ['.$line.']';
		return false;
	}
	
}

function lookup_persistent_id($track_id){
	$select_sql = 'SELECT persistent_id FROM itunes.itunes_library WHERE track_id = '.$track_id;
	$result = mysql_query($select_sql);
	if(!$result){
		die (mysql_error());
	}
	$rs = mysql_fetch_array($result);
	return $rs['persistent_id'];
}

function process_playlist($meta_sql){
	global $PLAYLIST;
	global $PLAYLIST_DATA;
	if(!empty($PLAYLIST_DATA)){
		return;
	}
	
	foreach($meta_sql as $value){
		if(strpos($value,'playlist_id') !== false){
			list($f,$v) = explode("=",$value);
			$playlist_id = trim(trim($v),'"');
		}
		if(strpos($value,'playlist_persistent_id') !== false){
			list($f,$v) = explode("=",$value);
			$playlist_persistent_id = trim(trim($v),'"');
		}
		if(strpos($value,'name') !== false){
			list($f,$v) = explode("=",$value);
			$name = trim(trim($v),'"');
		}
	}
	//echo 'FOUND PLAYLIST ['.$name.']'."\n";
	if($name == $PLAYLIST){
		$PLAYLIST_DATA = array(	'playlist_id'=>$playlist_id,
						'playlist_persistent_id'=>$playlist_persistent_id,
						'name'=>$name
						);
	}
}

function store_track($sql_arr){
	global $TRACKS;
	foreach($sql_arr as $value){
		if(strpos($value,'track_id') !== false){
			list($f,$v) = explode("=",$value);
			$track_id = trim(trim($v),'"');
		}
		if(strpos($value,'location') !== false){
			list($f,$v) = explode("=",$value);
			$file_path = trim(trim($v),'"');
		}
	}
	
	if($file_path && $track_id){
		
		//*real* special cases here!
		switch($file_path){
			case 'file://localhost/C:/Users/Adrian/Music/iTunes/iTunes%20Media/Music/Slipknot/Iowa/02%20People%20';
				$file_path = '/media/sf_Ubuntu_local/Copy_Of_Itunes_Data/Slipknot/Iowa/02 People = Shit.mp3';
				break;		
		}
		
		$TRACKS[$track_id] = str_replace('file://localhost/C:/Users/Adrian/Music/iTunes/iTunes%20Media/Music/','/media/sf_Ubuntu_local/Copy_Of_Itunes_Data/',$file_path);
		$file_parts = explode('/',$TRACKS[$track_id]);
		$new_parts=array();
		$SKIP = false;
		foreach($file_parts as $part){
			$done_part = urldecode($part);
			foreach(array(
						'Pregnancy & Birth',
						'Unknown Artist'
						) as $skippers){
				if($done_part == $skippers){
					//echo 'Skipping ['.implode('/',$file_parts).']';
					return;
				}
			}
			
			foreach(array(
						'Mike   The Mechanics'=>'Mike + The Mechanics',
						'U   Ur Hand'=>'U + Ur Hand',
						'  (Deluxe Edition)'=>'+ (Deluxe Edition)'
					)
				 as $replaceFrom => $replaceTo){
				 $done_part = str_replace($replaceFrom,$replaceTo,$done_part);	
				}
			
			$new_parts[] = $done_part;
		}
		$TRACKS[$track_id] = implode('/',$new_parts);
		/*
		foreach(array(
						'%5B'=>'[',
						'%5D'=>']',
						'%23'=>'#',
						'%C3%B3'=>'ó',
						'%C3%A9'=>'é',
						'An D%C3%ADolaim'=>'An Diolaim',
						'%C3%BA'=>'u',
						'%C3%A1'=>'a',
						'Ã©'=>'e'
					)
				 as $replaceFrom => $replaceTo){
			$TRACKS[$track_id]	 	 = str_replace($replaceFrom,$replaceTo,$TRACKS[$track_id]);
		}*/
		if(!stat($TRACKS[$track_id])){
			echo 'Could not stat file - invalid path';
			var_dump(count($TRACKS));
			var_dump($file_path);
			var_dump($file_parts);
			die(var_dump($TRACKS[$track_id]));
		}
	} else {
		echo 'Could not extract track/location info';
		die(var_dump($sql_arr));
	}
	
}
?>