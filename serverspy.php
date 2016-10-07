<?php

/**
 * ServerSpy PHP Backup Agent
 * Description: This is a stateless server backup agent controlled by https://serverspy.io. This file is open
 *   source and requires no configuration settings to be set unless directed by technical support to do so. If
 *   you have any questions, comments, bug reports, or security concerns; please reach out to us. 
 * 
 * hello@serverspy.io
 * https://serverspy.io
 * https://github.com/serverspy-io
 **/

// API Information
define("API", "api.serverspy.io/dev/");
define("CLIENT_VERSION", "0.1"); 

// If someone calls the serverspy.php file directly, we will show a splash page with information on our product.
// If you would like to instead return a 404, change this setting to false.
define("SPLASH", true); 

class serverspy {

    public static $curlDebug;

    private $debug;

    private $task;
    private $taskData;
    private $taskId;
    private $taskResponse;
    private $taskRetEndpoint;

    /**
     * Constructor, validate basic input.
     * Description: This will validate the basic input which was received from the requestor. At this
     *   point we will only attempt to continue should we detect that the UUID is valid. If it is not,
     *   we will present an error page.
     **/
    public function __construct($taskId) {
 
      // Valid both the and the task are valid UUIDs
      if(!$this->isUUID($taskId))
        throw new Exception("TaskId is not in valid format.");

      $this->taskId = $taskId;

      return $this;

    }

    /**
     * Static Constructor
     * Description: Only here to make the initialisation code look cooler. 
     **/
    public function staticInit($taskId) {

      $serverspy = new serverspy($taskId);
      return $serverspy;

    }

    /**
     * Retrieve a task.
     * Description: This will make a callback to the ServerSpy API over an SSL connection to gather
     *   the task to perform. This 
     **/
    public function retrieveTask() {

      $callObject = $this->callObject("tasks/{$this->taskId}");

      $res = $this->call($callObject);

      if(!isset($res->task) && !isset($res->data)) {
        self::$curlDebug["body"] = $res;
        throw new Exception("Invalid data returned.");
      }

      // Localize task data.
      $this->task     = $res->task;
      $this->taskData = $res->data;

      return $this;

    }

    /**
     * Perform a set task.
     * Description: We have a very limited set of code that can be executed in relation to a task. There
     *   is no ability to eval, change endpoints, or in general break out of our sandbox without prior
     *   modification of this file. (In other words, you were already hacked.) This will attempt to verify
     *   that the requested task can be performed by the agent, and if so, perform it. 
     **/
    public function performTask() {

      // Make sure the action attempting to be performed matches
      // available actions.
      $taskHandler = "task_{$this->task}";
      if(!method_exists($this, $taskHandler))
        throw new Exception("Task handler '{$taskHandler}' does not exist.");

      // execute task
      $this->$taskHandler();

      // return self
      return $this;

    }

    /**
     * Present the tasks response to the caller.
     * Description: Some tasks require a callback UUID so the task initiator knows that the task was fully
     *   performed. This will present the callback, and if there is any error data, the error as well. This
     *   will helps us improve application performance over time.
     **/
    public function present() {

      print json_encode($this->taskResponse);
      exit();

    }

    /**
     * Verify the current server.
     * Description: A new server needs to provide us with basic server information so we can do our job of
     *   backing up the server correctly. This will provide us with all the necessary information to keep
     *   tabs on your data.
     **/
    private function task_verify() {

      $callObject = $this->callObject("targets/tasks/{$this->taskId}", "POST");

      // Setup request body
      $body = new stdClass;
      $body->taskId = $this->taskId;
      $body->data->pwd        = __DIR__;
      $body->data->uname      = php_uname("a");
      $body->data->php        = PHP_VERSION;
      $body->data->os         = PHP_OS;
      $body->data->sapi       = PHP_SAPI;
      $body->data->extensions = $this->getExtensions();
      $body->data->ini        = $this->getIni();
      $body->data->server     = $_SERVER;
      $body->data->disk = new stdClass;
      $body->data->disk->free = disk_free_space(__DIR__);
      $body->data->disk->capacity = disk_total_space(__DIR__);
      //$body->data->map        = $this->task_map(__DIR__);

      $callObject->body =& $body;

      $this->debug = $callObject;

      $this->taskResponse = $this->call($callObject);

      return $this;

    }

    /**
     * Send performance metrics.
     * Description: If your account has this option set (and they all do by default), we will check on the server
     *   once every 60-300 seconds to gather basic server information. We will then present this information to you
     *   in your control panel area, and alert you to potential issues such as high load, low disk space, and more.
     **/
    private function task_metrics() {

    }

    /**
     * Map a directory.
     * Description: This will map a specific directory and provide us with all the relevant information about the
     *   data within the directory. We will perform a full map of your specified backup directory once about every
     *   six hours.
     **/
    private function task_map($dir = false) {

      // $dir would be set to __DIR__ only on the verify request
      // where we lack filesystem insight. 
      $dir = !$dir?$this->taskData:$dir;

      // Create the map object
      $map = new stdClass;

      // Attempt to open the directory, map data within
      try {

        $handle = opendir($dir);
        while(false !== ($object = readdir($handle))) {

          // Shortcut to file
          $filepath = "{$dir}/{$object}";

          // Skip parent directories (..) and define the type of data.
          if($object === "." || $object === "..")
            continue;
          
          // Create reference for brevity
          $m =& $map->$object;

          // Gleen what type of entry this is
          if(is_link("{$dir}/{$object}"))   
            $m->type = "symlink";
          elseif(is_file("{$dir}/{$object}")) 
            $m->type = "file";
          elseif(is_dir("{$dir}/{$object}")) 
            $m->type = "dir";
          else 
            $m->type = "unknown";

          // Stat the entry
          $stat = stat("{$dir}/{$object}");

          // Add the stat to the map input
          switch($m->type){
          case "file":
            $m->bytes    = $stat[7];
            $m->mtime    = $stat[9];
            $m->md5      = md5_file("{$dir}/{$object}");
          case "symlink":
            $m->linkinfo = linkinfo("{$dir}/{$object}");
          default:
            $m->mode     = $stat[2];
            $m->uid      = $stat[4];
            $m->gid      = $stat[5];
          }

        }

      } catch(Exception $e) {

        $map->error = $e->getMessage();
  
      }

      $callObject = $this->callObject("/maps", "POST");
      $callObject->headers[] = "Token: " . $this->taskId;
      $callObject->body = $map;
      $this->taskResponse = $this->call($callObject);
      return $this;

    }

    /**
     * Backup a file.
     * Description: This will open an SSL socket to our server and send the file to it in small chunks. The only 
     *   accompanying information along with the file is the taskId which will be included in the first 32 bits.
     *   Once we have received the file, we will run an AV check, check to see if there are any newer versions of
     *   the file available, and finally store it for long term recovery.
     **/
    private function task_push() {

      // SSL connection to the upload server
      $upload = fsockopen("ssl://upload.serverspy.io", 443, $errno, $errstr, 5);

      // Send taskId to server first
      $write = fwrite($upload, $this->taskId);

      // Break down the file into chunks for sending
      $local = fopen($this->taskData,"r");
      while(!feof($local))
        fwrite($upload,fread($local,64000));

      // Close the file and socket
      fclose($local);
      fclose($upload);

      // In theory we wont get here unless the above finishes succesfully. If it does not respond with a true
      // to the caller, then we will re-initiate at the last chunk.
      $this->taskResponse = new stdClass;
      $this->taskResponse->status = true;
     
      return $this;

    }

    /**
     * Return an object with loaded PHP modules and extensions.
     **/
    private function getExtensions() {

      $extension = new stdClass;
      foreach(get_loaded_extensions() as $val)
        $extension->$val = phpversion($val);

      return $extension;

    }

    /**
     * Return an object with the systems php.ini settings.
     **/
    private function getIni() {

      $ini = new stdClass;
      foreach(ini_get_all() AS $key => $val) {
        $ini->$key = new stdClass();
        foreach($val as $k => $v)
          $ini->$key->$k = $v;
      }

      return $ini;

    }

    /**
     * Callback
     * Description: This is the HTTP callback function which will make the necessary REST
     *   calls to our server either asking for task information, or responding with the
     *   data of a completed task. 
     **/
    private function call($callObject) {

      $req = curl_init($callObject->url);

      // POSTing JSON body to endpoint
      if(isset($callObject->body) && 
         is_object($callObject->body)) {
  
        $data = json_encode($callObject->body);
        curl_setopt($req, CURLOPT_POSTFIELDS, $data);
        $callObject->headers[] = 'Content-Length: ' . strlen($data);

      }

      curl_setopt($req, CURLOPT_HTTPHEADER, $callObject->headers);
      curl_setopt($req, CURLOPT_CUSTOMREQUEST, $callObject->method);
      curl_setopt($req, CURLOPT_RETURNTRANSFER, true);

      $res = curl_exec($req);

      // Debug
      self::$curlDebug = curl_getinfo($req);

      return json_decode($res);

    }

    /**
     * Create a new call object with default settings.
     * Description: This will create a new call object with the default settings.
     **/
    private function callObject($endpoint, $method = "GET") {

      $call = new stdClass;
      $call->url    = "https://" . API . $endpoint;
      $call->method = $method;

      $call->headers = array();
      $call->headers[] = "Content-Type: application/json";
      $call->headers[] = "ServerSpy-Agent: " . CLIENT_VERSION;

      return $call;

    }

    private function isUUID($uuid) {

      if(preg_match('/^\{?[A-Z0-9]{8}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{12}\}?$/i', $uuid,$matches))
        return true;
      else
        return false;

    }

    /**
     * Display a static webpage to the caller. 
     */
    public static function splashPage() {

      echo("<html><head><title>ServerSpy Backup</title></head><body>This website utilizes backup software from <a href='//serverspy.io'>www.serverspy.io</a>!</body></html>");
      exit();

    }

}

/**
 * Attempt to run main application
 **/
try {

  // Check for Token and TaskId headers
  // If it does not exist, assume this is a request from an unknown source
  // and show them a generic splash page.
  if(!isset($_SERVER["HTTP_TASKID"]))
    return serverspy::splashPage();

  // Going ahead, response type will always be JSON.
  header('Content-Type: application/json');
  serverspy::staticInit($_SERVER["HTTP_TASKID"])
    ->retrieveTask()
    ->performTask()
    ->present();

} catch(Exception $e) {

  $ret = new stdClass;
  $ret->status = false;
  $ret->error  = $e->getMessage();
  $ret->debug  = serverspy::$curlDebug;
  print json_encode($ret);

  exit();

}
