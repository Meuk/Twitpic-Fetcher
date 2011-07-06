<?php
/**
 * Twitpic Fetch Class
 *
 * Patrick van Oostrom
 * http://www.meukinc.nl
 * http://www.twitter.com/Meuker
 *
 **/
class Twitpic {
	
	/**
	 * Images Stack
	 **/
	protected $_images = array();
	
	/**
	 * Twitpic user
	 **/
	protected $_user = 'Meuker';
	
	/**
	 * Set the twitpic base URL
	 **/
	protected $_twitpicUrl = "http://api.twitpic.com/2/users/show.json?";
	
	/**
	 * The page count
	 **/
	protected $_pages;
	
	/**
	 * The default number of workers
	 **/
	
	protected $_workers = 10;
	/**
	 * Start the Gearman Client, assign callbacks
	 * 
	 * @return Twitpic
	 **/
	public function startServer()
	{
		$this->_gmc= new GearmanClient();
		
		$this->_gmc->addServer();
		
		$this->_gmc->setCreatedCallback(array($this, "reverse_created"));
		$this->_gmc->setDataCallback(array($this, "reverse_data"));
		$this->_gmc->setStatusCallback(array($this, "reverse_status"));
		$this->_gmc->setCompleteCallback(array($this, "reverse_complete"));
		$this->_gmc->setFailCallback(array($this, "reverse_fail"));
		return $this;
	}
	
	/**
	 * A function to start workers on the fly (lazymode)
	 *
	 * @param integer $ammount
	 * @return Twitpic
	 **/
	public function addWorkers($ammount = null)
	{
		// If no amount is given, use configured ammount
		if(null === $ammount){
			$ammount = $this->_workers;
		}
		
		// Start x workers
		for($i = 0; $i <= $ammount; $i++)
		{
			passthru('php '.dirname(__FILE__).'/twitpic_worker.php >> '.dirname(__FILE__).'/log_file.log 2>&1 &');
		}
		
		return $this;
	}
	
	/**
	 * Get the number of pages
	 *
	 * @return integer
	 * @todo seperate getter from logic
	 **/
	public function getPages()
	{
		// Open cURL session
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, $this->_buildUrl(array('username' => $this->_user))); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		$output = curl_exec($ch); 
		curl_close($ch);
		$output = json_decode($output);
		
		// Get the photo count
		$photoCount = $output->photo_count;
		
		// Maximum ammount set by TwitPic
		$perPage = 20;
		
		// Calculate the ammount of pages
		$this->_pages = ceil($photoCount / $perPage);
		
		return $this;
	}
	
	/**
	 * Set the ammount of pages manually
	 * 
	 * @param integer $pages
	 * @return Twitpic
	 **/
	public function setPages($pages)
	{
		$this->_pages = $pages;
		return $this;
	}
	
	/**
	 * Add gearman tasks, fetch the photoIds
	 * 
	 * @todo rename function
	 **/
	public function addTasks()
	{
		for ($i = 0; $i <= $this->_pages; $i++) {
			$data = array('username' => $this->_user, 'page' => $i);
			$this->_gmc->addTask("fetchPhotoIds", serialize($data), $data);
		}
	}
	
	/**
	 * Run the jobqueue
	 * 
	 * @todo handle the error
	 **/
	public function run()
	{
		if (! $this->_gmc->runTasks())
		{
		    echo "ERROR " . $this->_gmc->error() . "\n";
		    exit;
		}
		
	}
	
	/**
	 * Callback for when a job is queued
	 * 
	 * @param GearmanTask $task
	 * @todo create a logging event for this callback
	 **/
	public function reverse_created($task) { }
	
	/**
	 * Callback for getting updated status infromation from a worker
	 *
	 * @param GearmanTask $task
	 **/
	public function reverse_status($task)
	{
	    echo "STATUS: " . $task->jobHandle() . " - " . $task->taskNumerator() . 
	         "/" . $task->taskDenominator() . "\n";
	}
	
	/**
	 * Complete callback
	 * 
	 * @param GearmanTask
	 **/
	public function reverse_complete($task)
	{
		// Merge the images stack with the task data
		$this->_images = array_merge($this->_images, unserialize($task->data()));
	}
	
	/**
	 * Callback for task failures
	 * 
	 * @param GearmanTask $task
	 * @todo create a logging event for this callback
	 **/
	public function reverse_fail($task) { }
	
	/**
	 * Callback for accepting data packets for a task
	 *
	 * @param GearmanTask $task
	 * @todo create a logging event for this callback
	 **/
	public function reverse_data($task) { }
	
	/**
	 * Build a call URL for twitpic
	 *
	 * @param array $options
	 * @return string
	 * @todo clean this up
	 **/
	private function _buildUrl(array $options)
	{
		return $this->_twitpicUrl . http_build_query($options);
	}
	
	/**
	 * Download the images to disk, return array with photo id's
	 *
	 * @return array
	 * @todo Should make the tasks created run on the background.
	 **/
	public function getImages()
	{
		// Loop trough all the photo id's
		foreach($this->_images as $photoId => $image)
		{
			if(!empty($photoId)){
				$data = array('username' => $this->_user, 'photoId' => $photoId, 'image' => $image);
				
				// Add fetchPhoto task
				$this->_gmc->addTask("fetchPhoto", serialize($data), $data);
			}
		}
		
		// Return the array with photo id's
		return array_keys($this->_images);
	}
}
