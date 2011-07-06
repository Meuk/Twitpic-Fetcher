<?php
error_Reporting(E_ALL);
ini_set('display_errors', 1);

include 'twitpic.php';
// Print the start time
print(date('H:i:s') . ' Fetching Started. <br />');

// new Twitpic object
$tp = new Twitpic;

// Start the server, get the pages, add the workers and tasks
$tp->startServer()
	->getPages()
	->addWorkers(10)
	->addTasks();
	
// Run the tasks
$tp->run();

// Download the images to disk.
$images = $tp->getImages();
$tp->run();

// Print the end time and total ammount of images
print(date('H:i:s') . ' Fetching Finished. <br />');
print(count($images). ' images downloaded.<br />');

?>