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

			$repo_info = $this->config->get_repository_info();

			if(!isset($repo_info['repositories'])){
				return $latest_versions;
			}

			foreach ( $repo_info['repositories'] as $id => $info ) {

				if(!isset($info['source'])){
					continue;
				}

				$source       = $info['source'];
				$driver_class = 'UpdateCenter_Repository_' . $source;
				if ( !class_exists( $driver_class ) ) {
					throw new Phpr_ApplicationException( "Repository driver ($driver_class) does not exist for repo/source type: " . $source );
				}

				if(!isset($info['modules'])){
					continue;
				}

				$driver = new $driver_class( $info['modules'] );

				foreach ( $info['modules'] as $module_name => $module_info ) {
					if(!isset( $module_info['owner']) || !isset($module_info['repo'])){
						continue;
					}
					if($this->config->is_allowed_update($source,$module_name,$module_info) && !$this->config->is_blocked_module($module_name)) {
						$latest_versions[$module_name]['version']     = $driver->get_latest_version_number( $module_name, $source );
						$latest_versions[$module_name]['description'] = $source . '/' . $module_info['owner'] . '/' . $module_info['repo'] . ': ' . $driver->get_latest_version_description( $module_name );
						$latest_versions[$module_name]['source']      = $source;
					}
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
			if(!isset($new_version_info['version']) || !isset($installed_versions[$module_name]['version'])){
				continue;
			}
			if($force || UpdateCenter_Helper::is_version_newer($new_version_info['version'],$installed_versions[$module_name]['version'])){
				$obj = new stdClass();
				$obj->name = $installed_versions[$module_name]['info']->name;
				$obj->updates = array($new_version_info['version'] => $new_version_info['description']);
				$obj->source = $new_version_info['source'];
				$updates[$module_name] = $obj;
			}
		}

		return $updates;
	}



	public function get_latest_version_number($module_name, $source=null){
			$repo = $this->load_driver_for( $module_name, $source );
			return $repo->get_latest_version_number( $module_name );
	}

	public function get_latest_version_zip_url($module_name, $source=null){
		$repo = $this->load_driver_for($module_name,$source);
		return $repo->get_latest_version_zip_url($module_name);
	}

	public function get_latest_version_zip_size($module_name,$source=null){
		$repo = $this->load_driver_for($module_name,$source);
		return $repo->get_latest_version_zip_size($module_name);
	}


	public function download_update_to_temp($module_name, $source){
		$repo = $this->load_driver_for($module_name, $source);
		$remote_location = $repo->get_latest_version_zip_url($module_name);
		$size = $repo->get_latest_version_zip_size($module_name);

		$headers = array();
		$auth = $this->config->get_module_auth($module_name, $source);
		$headers = array_merge($headers,$repo->get_auth_headers($auth));

		if (!filter_var($remote_location, FILTER_VALIDATE_URL))
			throw new Phpr_ApplicationException('Could not locate download zip file for module update ('.$module_name.') '.$remote_location);


		set_time_limit(0); // unlimited max execution time
		$tmp_location = PATH_APP.'/temp/'.$module_name.'.zip';

		$fp = fopen($tmp_location, 'w+');

		if($fp === false)
			throw new Phpr_ApplicationException("PHP cannot write to $tmp_location, please update the folders permissions");


		$ch = curl_init($remote_location);

		if(count($headers)){
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

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



	public function load_driver_for($module_name, $source=null){

		$repo_info = $this->config->get_repository_info();

		if(!isset($repo_info['repositories'])){
			throw new Phpr_ApplicationException('Could not load driver for '.$module_name.' the updatecenter config file is not correct');
		}

		foreach($repo_info['repositories'] as $id => $info){
			if(!isset($info['source']) || (!empty($source) && $source !== $info['source'])){
				continue;
			}
			$source = $info['source'];

			if(!isset($info['modules'])){
				continue;
			}

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
	public function get_auth_headers($auth);
}