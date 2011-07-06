<?php 

// Instantiate a new Gearman Worker
$worker= new GearmanWorker();

// Non-blocking
$worker->addOptions(GEARMAN_WORKER_NON_BLOCKING); 

// Add the localhost:4730 as server (default)
$worker->addServer(); 

// Add the functions to the worker
// Fetch all photo Ids of this twitpic user
$worker->addFunction("fetchPhotoIds", "fetchPhotoIds");

// Fetch a photo from twitpic
$worker->addFunction("fetchPhoto", "fetchPhoto");

// This worker will destroy itself in 5 seconds.
$worker->setTimeout(5000);

// Keep worker alive as long as there are jobs
while (@$worker->work() || $worker->returnCode() == GEARMAN_IO_WAIT || 
		$worker->returnCode() == GEARMAN_NO_JOBS){
			
	if ($worker->returnCode() == GEARMAN_SUCCESS){
		echo date('d-m-Y H:i:s') . "| Job Successful \n";
		continue;
	}
	
	if (!@$worker->wait()) { 
		if ($worker->returnCode() == GEARMAN_NO_ACTIVE_FDS) { 
			// We are not connected to the Gearman Server
			// We shall not hammer the Gearman Server
			// We have patience, not much, but some.
			sleep(5); 

			continue; 
		} 
		break; 
	} 
}

/**
 * Fetch a page with photo id's. Since there is paging on twitpic, we have to fetch every page
 *
 * @param GearmanJob $job
 * @return string
 *
 **/

function fetchPhotoIds($job)
{
	// Set the twitpic base URL
	$twitpic = 'http://api.twitpic.com/2/users/show.json?';
	
	// Empty image array to return later on
	$images = array();
	
	// Get the gearman job workload
	$payload = $job->workload();
	
	// Unserialize it
	$options = unserialize($payload);
	
	// Create a twitpic url
	$url = $twitpic . http_build_query($options);
	
	// Logging
	print "Processing ".$options['username'].", page ".$options['page'] . "\n";
	print $url . "\n";
	
	// Open a cURL sesion
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL, $url); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	$output = curl_exec($ch); 
	curl_close($ch);
	
	// Decode the json response
	$output = json_decode($output);
	
	// Loop through the images, add them to the stack.
	foreach($output->images as $image){
		$images[$image->short_id] = $image;
	}
	
	// Return the images stack, serialized
	return serialize($images);
}

/**
 * Fetch a photo and save it to disk.
 *
 * @param GearmanJob $job
 * @return string
 *
 **/

function fetchPhoto($job)
{
	// Set the twitpic base URL
	$twitpic = 'http://twitpic.com/show/full/';
	
	// Get the gearman job workload
	$payload = $job->workload();
	
	// Unserialize it
	$options = unserialize($payload);
	
	// Create a twitpic url
	$url = $twitpic .($options['photoId']);
	
	// Logging
	print "Processing ".$options['username'].", photo ".$options['photoId'] . "\n";
	print $url . "\n";
	
	// Open a cURL sesion
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL, $url); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
	
	// Since this URL redirects us to an Amazon S3 location
	// We should follow the location	
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			
	$output = curl_exec($ch); 
	
	// Log the Amazon S3 source URL
	print "Effective URL: ".curl_getinfo($ch, CURLINFO_EFFECTIVE_URL)."\n";

	// Closing the gate
	curl_close($ch);
	
	// Write the image to filestorage
	$fp = fopen('pics/'.$options['photoId'].'.jpg', 'w');
	fwrite($fp, $output);
	fclose($fp);
	
	// Serialize the image array and return it
	return serialize(array($image));
}