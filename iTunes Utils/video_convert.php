<?php

echo 'Starting'."\n\n";

$folder_to_convert = 'videos';

$path = '/home/adrian/projects/create_playlist/'.$folder_to_convert;
$dir = opendir($path);
if(!$dir){
	die('Couldn\'t find ['.$path.']');
}

while (false !== ($file = readdir($dir))) {
    if ('.' === $file) continue;
    if ('..' === $file) continue;
    if ($file === 'default_image.jpg') continue;

	//die('did it work?');
    // do something with the file
    echo 'processing ['.$file.']'."\n";
    $new_file = '_'.preg_replace('/\s+|\(|\)|\!|&|\'|"/','_',$file);
    if(copy ($path.'/'.$file,$path.'/'.$new_file)){
    	unlink($path.'/'.$file);
    	convert_with_volume($path.'/'.$new_file);
    	//convert_with_volume($path.'/'.$new_file,$image_path,false,'1.2');
    	//convert_with_volume($path.'/'.$new_file,$image_path,false,'1.5');
    	//convert_with_volume($path.'/'.$new_file,$image_path,false,'1.8');
    	//convert_with_volume($path.'/'.$new_file,$image_path,false,'2');
    	//convert_with_volume($path.'/'.$new_file,$image_path,false,'2.5');
    	//convert_with_volume($path.'/'.$new_file,$image_path,false,'3');
    	//convert_with_volume($path.'/'.$new_file,$image_path,false,'3.5');
    	//convert_with_volume($path.'/'.$new_file,$image_path,false,'4');
    	//convert_with_volume($path.'/'.$new_file,$image_path,false,'4.5');*/
    
    } else {
    	die('Couldn\'t copy the file bud ['.$file.'] to ['.$new_file.']');
    }
}
closedir($dir);

//convert('/home/adrian/projects/create_playlist/straight_between_the_eyes/death.wav','default_image.jpg',false,'2');
echo 'Finished'."\n\n";

function convert_with_volume($destination){
	
	//$type = '.wav';
	$type = '.avi';
	
	$output_file_name = substr(basename($destination),0,-4).$type;
	
	$docker_cmd = 'docker run -it -v '.str_replace(basename($destination),'',$destination).':/files sjourdan/ffmpeg -stats -i /files/'.basename($destination).' -qscale 0 /files/'.$output_file_name;
	
	echo 'Converting: ['.$destination.']'."\n".$docker_cmd."\n";

	passthru($docker_cmd);
	
	echo 'Finished Conversion'."\n";
	
	//unlink($destination);
		
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
	if(!$google){
		die(var_dump(curl_error($ch)));
	}
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

?>
