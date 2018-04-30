<?php

echo 'Starting'."\n\n";

$folder_to_convert = 'straight_between_the_eyes';
$path = '~/projects/create_playlist/'.$folder_to_convert;
$dir = opendir($path);
if(!$dir){
	die('Couldn\'t find ['.$path.']');
}
copy('./default_image.jpg',$path.'/default_image.jpg');

while (false !== ($file = readdir($dir))) {
    if ('.' === $file) continue;
    if ('..' === $file) continue;
    if ($file === 'default_image.jpg') continue;

    // do something with the file
    echo 'processing ['.$file.']'."\n";
    $new_file = preg_replace('/\s+|\(|\)|\!|&|\'|"/','_',$file);
    if(copy ($path.'/'.$file,$path.'/'.$new_file)){
    	unlink($path.'/'.$file);
    	convert_with_volume($path.'/'.$new_file,'default_image.jpg',false,'2');
    	convert_with_volume($path.'/'.$new_file,'default_image.jpg',false,'4');
    	
    } else {
    	die('Couldn\'t copy the file bud ['.$file.'] to ['.$new_file.']');
    }
}
closedir($dir);

//convert('~/projects/create_playlist/straight_between_the_eyes/death.wav','default_image.jpg',false,'2');
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


?>
