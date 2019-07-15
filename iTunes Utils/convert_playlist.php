<?php
/*
>> I keep a copy of entire music collection (mp3s) on the ubuntu shared drive (safer to have it work on a copy).
>> This will need refreshing every now and then otherwise a playlist might refer to a song (details) that has been altered.
>> sudo su -
>> cd /home/adrian/projects/create_playlist/
>> mv /media/sf_Ubuntu_local/iTunes\ Music\ Library.xml /home/adrian/projects/create_playlist/
>> cp /media/sf_Ubuntu_local/convert_playlist.php . ; php -f convert_playlist.php "100 (ex)"
>> mv playlists/*ex* /media/sf_Ubuntu_local/created_playlists/

** This now build a big assoc array of tracks and locations $TRACKS
e.g.:
	[28897]=>
  string(115) "/media/sf_Ubuntu_local/Copy_Of_Itunes_Data/Compilations/Hip Young Guitar Slinger [Disc 2]/2-12 Step Out Of Line.mp3"
  [28899]=>
  string(106) "/media/sf_Ubuntu_local/Copy_Of_Itunes_Data/Compilations/Hip Young Guitar Slinger [Disc 2]/2-13 Moanin'.mp3"
  
Also does:
1.) dynamically pull down images and bake them into mp3 when genertaing playlist
2.) dynamically honour itunes 'stop at' point when creating playlist
3.) covert volume (ramp up amplitude) - NEEDS TESTING !
*/
echo "Starting......"."\n";

//getImage(array(4=>'alanis morrisette',5=>'So Called Chaos'),'./');		// test getImage function
//getBingImage('Pink singer');
//getGoogleImage('Pink singer');
//getWikiImage('Pink singer');
//die('ded');

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

$allFields = $sql = $TRACKS = $PLAYLIST_DATA = $TRUNCATE_TRACKS = $VOLUME_TRACKS = array();
$bad_stuff = array('"','\\');

$counter = $dict_counter = 0;
//$safety = 200;
$update_count = $insert_count = $playcount_history_insert_count = 0;

$in_song = false;
$in_playlist = false;

$song_count = 0;

// Blow away
//$blow_away_sql = 'TRUNCATE itunes.itunes_library';
//$result = mysql_query($blow_away_sql);
//if(!$result){
//	die (mysql_error());
//}


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
		if($db_field == 'stop_time'){
			$thisSong['stop_time'] = $db_value;
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
				$artist = preg_replace('/\s+|&|\!/','_',$file_part[4]);
				if(strtolower($artist) == 'compilations'){
					$artist = preg_replace('/\s+|&|\!/','_',$file_part[5]);
				}
				$artist = preg_replace('/\'|\(|\)/','',$artist);
				$trackName = preg_replace('/^\d?.?\d+|\'/','',basename($TRACKS[$track_id]));
				$trackName = preg_replace('/\s+|\(|\)|\!|&/','_',$trackName);
				$destination = $playlist_directory.'/'.$display_order.'_'.$artist.'_'.$trackName;
				//$TRACKS[$track_id] = preg_replace('/!/','\\!',$TRACKS[$track_id]);
				
				//var_dump($artist);
				//var_dump($trackName);
				//var_dump($destination);
				//continue;
				echo 'Copying Track ['.$TRACKS[$track_id].'] to ['.$destination.']'."\n";
				if(!copy($TRACKS[$track_id],$destination)){
					die('Could not copy  to ['.$destination.']');
				}
				
				$truncate_time=false;
				if(isset($TRUNCATE_TRACKS[$track_id])){
					$truncate_time = ($TRUNCATE_TRACKS[$track_id] / 1000);
				}
				
				$volume_adjust=false;
				if(isset($VOLUME_TRACKS[$track_id])){
					switch($VOLUME_TRACKS[$track_id]){
						case '255':
							$volume_adjust	= '3';
							break;
						default:
							if($VOLUME_TRACKS[$track_id] > 200) {
								$volume_adjust	= '2.5';
							} else if($VOLUME_TRACKS[$track_id] > 150) {
								$volume_adjust	= '2';
							} else {
								$volume_adjust	= '1.5';
							}
							
					}
				}
				var_dump($volume_adjust);
				
				$image_path = getImage($file_part,$playlist_directory);
				
				convert($destination,$image_path,$truncate_time,$volume_adjust);
				
				$created_track = substr($destination,0,-4).'_.mp3';
				if(!filesize($created_track)){
					die('ERROR: Created EMPTY track ['.$created_track.']');
				}
				
				unlink($image_path);
				
				$order++;
			}
		} else {
			die('Could not create playlist directory ['.$playlist_directory.']');
		}
	}
}

//echo 'Stored ['.$xref_count.'] playlist_xref data';
echo 'Finished'."\n\n";

function convert_with_volume($destination,$image_path,$truncate_time,$volume_adjust){
	
	$output_file_name = substr(basename($destination),0,-4).'_converted.wav';
	
	$truncate_section = $volume_option = '';
	if($truncate_time){
		$truncate_section = '-to '.$truncate_time.' ';
	}
	
	if($volume_adjust){
		$output_file_name = substr(basename($destination),0,-4).'_converted_vol_'.$volume_adjust.'.wav';
		$volume_option = '-filter:a "volume='.$volume_adjust.'"';
	}
	
	$docker_cmd = 'docker run -it -v '.str_replace(basename($destination),'',$destination).':/files sjourdan/ffmpeg -stats -i /files/'.basename($destination).' '.($image_path ? '-i /files/'.basename($image_path) : '').' -map 0:0 -id3v2_version 3 -metadata:s:v title="Album cover" -metadata:s:v comment="Cover (Front)" '.$volume_option.' '.$truncate_section.'/files/'.$output_file_name;
	
	echo 'Converting: ['.$destination.']'."\n".$docker_cmd."\n";

	passthru($docker_cmd);
	
	echo 'Finished Conversion'."\n";
	
	//unlink($destination);
		
}

function convert($destination,$image_path,$truncate_time,$volume_adjust){
	
	$truncate_section = '';
	if($truncate_time){
		$truncate_section = '-to '.$truncate_time.' ';
	}
	
	$volume_option = '';
	if($volume_adjust){
		$volume_option = '-filter:a "volume='.$volume_adjust.'"';
	}
	
	$docker_cmd = 'docker run -it -v '.str_replace(basename($destination),'',$destination).':/files sjourdan/ffmpeg -stats -i /files/'.basename(escapeshellcmd($destination)).' -i /files/'.basename($image_path).' -map 0:0 -map 1:0 -id3v2_version 3 -metadata:s:v title="Album cover" -metadata:s:v comment="Cover (Front)" '.$volume_option.' '.$truncate_section.'/files/'.substr(escapeshellcmd(basename($destination)),0,-4).'_.mp3';
	
	echo 'Converting: ['.$destination.']'."\n".$docker_cmd."\n";

	passthru($docker_cmd);
	
	echo 'Finished Conversion';
	
	unlink($destination);
		
}

function really_old_convert($destination,$image_path){
	// IDEA: Just convert everything to mp3 ??
	// check destination file type - if not mp3 convert it !
	if(strtolower(substr($destination,-3)) !== 'mp3'){
		$docker_cmd = 'docker run -it -v '.str_replace(basename($destination),'',$destination).':/files sjourdan/ffmpeg -stats -i /files/'.basename($destination).' /files/'.substr(basename($destination),0,-3).'mp3';
		echo 'Not an MP3 - CONVERTING ! ['.$destination.']'."\n".$docker_cmd."\n";
		die('ded');
		//passthru($docker_cmd);
		unlink($destination);
	}/* else {
		// add the album art anyway - where to source images from ?? Maybe pull them down from wikipedia automagically ?? :-)
		// TO DO !
		
		$docker_cmd = 'docker run -it -v '.str_replace(basename($destination),'',$destination).':/files sjourdan/ffmpeg -stats -i /files/'.basename($destination).' -i /files/'.basename($image_path).' -map 0:0 -map 1:0 -c copy -id3v2_version 3 -metadata:s:v title="Album cover" -metadata:s:v comment="Cover (Front)" /files/'.substr(basename($destination),0,-3).'mp3';
		echo 'No conversion needed, just ADDING album art ['.$destination.']'."\n".$docker_cmd."\n";
		passthru($docker_cmd);
		die('ded');
		unlink($destination);
	}*/
}

function getWikiImage($search_criteria){
	// Kinda works if article exists , really needs to do two step search on wiki *then* go into article to retrieve image
	$url='https://en.wikipedia.org/wiki/'.urlencode(str_replace(' ','_',$search_criteria));
	
	echo 'Searching WIKIPEDIA'."\n";
	echo 'GET: '.$url."\n";
	$options  = array('http' => array('user_agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.1'));
	$context  = stream_context_create($options);
	$data=file_get_contents($url,false,$context);
	
	if($data){
		
		$image_data=explode('<a href="/wiki/File:',$data);
		
		if(!empty($image_data[1])){
			$top_result = $image_data[1];
			$url_data = explode('</a>',$top_result);
			$image_tag = $url_data[0];
			$tag_parts = explode('"',$image_tag);
			$image_url = 'https:'.$tag_parts[6];
			
			$new_image = $playlist_directory.'/'.preg_replace($stuff,'_',$album).'.jpg';
			file_put_contents($new_image, file_get_contents($image_url,false,$context));
			
			return $new_image;
		}
	}
	
}

function getGoogleImage($search_criteria){
	$search_query = $search_criteria; //change this
	$search_query = urlencode( $search_query );
	$googleRealURL = "https://www.google.com/search?hl=en&biw=1360&bih=652&tbs=isz%3Alt%2Cislt%3Asvga%2Citp%3Aphoto&tbm=isch&sa=1&q=".$search_query."&oq=".$search_query."&gs_l=psy-ab.12...0.0.0.10572.0.0.0.0.0.0.0.0..0.0....0...1..64.psy-ab..0.0.0.wFdNGGlUIRk";
	echo 'Searching GOOGLE'."\n";
	echo 'GET: '.$googleRealURL."\n";
	// Call Google with CURL + User-Agent
	$ch = curl_init($googleRealURL);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux i686; rv:20.0) Gecko/20121230 Firefox/20.0');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	$google = curl_exec($ch);   
	$array_imghtml = explode("\"ou\":\"", $google); //the big url is inside JSON snippet "ou":"big url"
	foreach($array_imghtml as $key => $value){
	  if ($key > 0) {
	    $array_imghtml_2 = explode("\",\"", $value);
	    $array_imgurl[] = $array_imghtml_2[0];
	  }
	}
	//var_dump($array_imgurl); //array contains the urls for the big images
	return $array_imgurl[0];
}

function getBingImage($search_criteria){
	// DOES NOT WORK :-(
	$url='https://www.bing.com/images/search?q='.urlencode($search_criteria).'&qs=n&form=QBILPG&sp=-1&ghc=1&pq='.urlencode($search_criteria).'&sc=8-11&sk=&cvid=F58ED3940AD44DBC8C8E321DF71996EA';
	
	echo 'Searching BING'."\n";
	echo 'GET: '.$url."\n";
	$options  = array('http' => array('user_agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.1'));
	$context  = stream_context_create($options);
	$data=file_get_contents($url,false,$context);
	
	if($data){
		
		$image_data=explode('img height',$data);
		
		if(!empty($image_data[1])){
			$top_result = $image_data[1];
			$url_data = explode('"',$top_result);
			
			$image_url = $url_data[29];
			
			return $image_url;
		}
	}
	
	return false;
}

function getImage($file_part,$playlist_directory){

	$stuff = array('/&/','/"/','/ /',"/'/",'/\+/','/\[/','/\(/','/\]/','/\)/','/\*/','/\./');
	$options  = array('http' => array('user_agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.1'));
	$context  = stream_context_create($options);
	
	$album = $file_part[5];
	if($file_part[4] == 'Compilations'){
		$artist = $file_part[6];
	} else {
		$artist = $file_part[4];
	}
	
	$search = $artist.' '.$album;
	echo 'Image search criteria: '.$search."\n";
	
	foreach(array('getGoogleImage','getWikiImage') as $func){
		$image_url = $func($search);
		if($image_url){
			$new_image = $playlist_directory.'/'.preg_replace($stuff,'_',$album).'.jpg';
			echo 'New image: '.$new_image;
			$status = file_put_contents($new_image, file_get_contents($image_url,false,$context));
			if($status && file_exists($new_image) && mime_content_type($new_image) == 'image/jpeg' && filesize($new_image) > 0){
				return $new_image;
			}
		}
	}

	
	echo 'Image not found, using placeholder'."\n";
	copy('default_image.jpg',$playlist_directory.'/default_image.jpg');
	return($playlist_directory.'/default_image.jpg');
}

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
	global $TRACKS, $TRUNCATE_TRACKS, $VOLUME_TRACKS;

	foreach($sql_arr as $value){
		if(strpos($value,'track_id') !== false){
			list($f,$v) = explode("=",$value);
			$track_id = trim(trim($v),'"');
		}
		if(strpos($value,'location') !== false){
			list($f,$v) = explode("=",$value);
			$file_path = trim(trim($v),'"');
		}
		if(strpos($value,'stop_time') !== false){
			list($f,$v) = explode("=",$value);
			$stop_time = trim(trim($v),'"');
		}
		if(strpos($value,'volume_adjustment') !== false){
			list($f,$v) = explode("=",$value);
			$volume_adjustment = trim(trim($v),'"');
		}
	}
	
	if(isset($file_path) && isset($track_id)){
		
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
		
		// does song need truncating?
		if(isset($stop_time)){
			$TRUNCATE_TRACKS[$track_id] = $stop_time;
		}
		
		if(isset($volume_adjustment)){
			$VOLUME_TRACKS[$track_id] = $volume_adjustment;
		}
		
	} else {
		echo 'Could not extract track/location info';
		die(var_dump($sql_arr));
	}
	
}
?>
