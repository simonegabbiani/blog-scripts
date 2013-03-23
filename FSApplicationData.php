<?php

//FSApplicationData.php
//Application object for PHP based on Filesystem
//simonegabbiani.blogspot.it/2013/03/application-object-su-filesystem.html

//(c) Simone Gabbiani - Released under GNU General Public License

//namespace \Web

//require_once "IApplicationData.php";

class FSApplicationData //implements IApplicationData
{
  private $path;
	private $id;
	public function __construct($path) {
        $cwd = getcwd();
		if (!is_dir($path))
			die("FileApplicationData: the path '$path' does not exists or it is not a directory");
        chdir($path);
        $this->path = getcwd();
		touch($this->path.'/~lockfile');
        chdir($cwd);
	}
	public function Exists( $name ) {
		return file_exists($this->path."/".urlencode($name));
	}
	public function Set( $name, $value ) {
		file_put_contents($this->path."/".urlencode($name), serialize($value));
	}
    //returns FALSE when $name does not exist
	public function Get( $name ) {
		return @unserialize(@file_get_contents($this->path."/".urlencode($name)));
	}
	public function Lock() {
		$this->locked = fopen($this->path.'/.lockfile', "w");
		return (flock($this->locked, LOCK_EX));
	}
	public function Unlock() {
		flock($this->locked, LOCK_UN);
		$this->locked = null;
	}
	public function Destroy() {
		if ($this->locked)
			flock($this->locked, LOCK_UN);
	}
	public function getOpenedContexts() {
		//unsupported
	}
}

?>
