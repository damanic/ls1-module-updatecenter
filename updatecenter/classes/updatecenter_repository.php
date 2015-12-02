<?php
class UpdateCenter_Repository {

	protected $config;
	protected $repo_type;
	protected $cache;

	public function __construct(){
		$this->load_config();
	}

	public function load_config(){
		$this->config = UpdateCenter_Config::get();
	}


	public function get_latest_versions(){
		$latest_versions = array();

			$this->config = UpdateCenter_Config::get()->get_repository_info();
			foreach ( $this->config['repositories'] as $id => $info ) {
				$source       = $info['source'];
				$driver_class = 'UpdateCenter_Repository_' . $source;
				if ( !class_exists( $driver_class ) ) {
					throw new Phpr_ApplicationException( "Repository driver ($driver_class) does not exist for repo type: " . $source );
				}
				$driver = new $driver_class( $info['modules'] );

				foreach ( $info['modules'] as $module_name => $module_info ) {
					$latest_versions[$module_name]['version'] = $driver->get_latest_version_number( $module_name );
					$latest_versions[$module_name]['description'] = $source.'/'.$module_info['owner'].'/'.$module_info['repo'].': '.$driver->get_latest_version_description( $module_name );
					$latest_versions[$module_name]['source'] = $source;
				}
			}
		return $latest_versions;
	}

	public function get_repository_updates($force=false){
		$updates = array();

		$repo = new UpdateCenter_Repository();
		$installed_versions = UpdateCenter_Config::get_installed_module_versions();
		$latest_versions = $repo->get_latest_versions();

		foreach($latest_versions as  $module_name => $new_version_info){
			if($force || UpdateCenter_Helper::is_version_newer($new_version_info['version'],$installed_versions[$module_name]['version'])){
				$obj = new stdClass();
				$obj->name = $installed_versions[$module_name]['info']->name;
				$obj->updates = array($new_version_info['version'] => $new_version_info['description']);
				$updates[$module_name] = $obj;
			}
		}

		return $updates;
	}



	public function get_latest_version_number($module_name){
			$repo = $this->load_driver_for( $module_name );
			return $repo->get_latest_version_number( $module_name );
	}

	public function get_latest_version_zip_url($module_name){
		$repo = $this->load_driver_for($module_name);
		return $repo->get_latest_version_zip_url($module_name);
	}

	public function get_latest_version_zip_size($module_name){
		$repo = $this->load_driver_for($module_name);
		return $repo->get_latest_version_zip_size($module_name);
	}


	public function download_update_to_temp($module_name){
		$repo = $this->load_driver_for($module_name);
		$remote_location = $repo->get_latest_version_zip_url($module_name);
		$size = $repo->get_latest_version_zip_size($module_name);

		if (!filter_var($remote_location, FILTER_VALIDATE_URL))
			throw new Phpr_ApplicationException('Could not locate download zip file for module update ('.$module_name.') '.$remote_location);


		set_time_limit(0); // unlimited max execution time
		$tmp_location = PATH_APP.'/temp/'.$module_name.'.zip';

		$fp = fopen($tmp_location, 'w+');

		if($fp === false)
			throw new Phpr_ApplicationException("PHP cannot write to $tmp_location, please update the folders permissions");


		$ch = curl_init($remote_location);
		curl_setopt($ch, CURLOPT_USERAGENT, "PHP/Lemonstand");
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_TIMEOUT, 28800);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_exec($ch);

		if(curl_errno($ch))
			throw new Phpr_ApplicationException("Failed to download update from $remote_location: " . curl_error($ch));

		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if(!$statusCode == 200)
			throw new Phpr_ApplicationException("Failed to download update from $remote_location, status code: " . $statusCode);


		if(!file_exists($tmp_location) || ($size && $size !== filesize($tmp_location))){
			throw new Phpr_ApplicationException('Could not complete download for: '.$module_name.'. Try again.');
		}
		return $tmp_location;
	}



	public function load_driver_for($module_name){

		foreach($this->config['repositories'] as $id => $info){
			$source = $info['source'];
			foreach($info['modules'] as $module_id => $module_details){
				if($module_name == $module_id){
					$driver_class = 'UpdateCenter_Repository_'.$source;

					if(!class_exists($driver_class)){
						throw new Phpr_ApplicationException("Repository driver ($driver_class) does not exist for repo type: ".$source);
					}

					return new $driver_class($info['modules']);
				}
			}
		}
		throw new Phpr_ApplicationException('Could not load driver for '.$module_name);
	}
}

abstract class UpdateCenter_Repository_Driver{

	protected $module_info;

	public function __construct($module_info){
		$this->module_info = $module_info;
	}

}

interface UpdateCenter_Repository_Interface {
	public function get_latest_version_number($module_name);
	public function get_latest_version_description($module_name);
	public function get_latest_version_zip_url($module_name);
	public function get_latest_version_zip_size($module_name);
}