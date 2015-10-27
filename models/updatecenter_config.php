<?php

class UpdateCenter_Config extends Db_ActiveRecord
{
	protected static $ls_core_modules = array(
		'shop' => '1.25.138',
		'cms' =>  '1.10.37',
		'backend' => '1.9.29',
		'core' => '1.11.97',
		'system' => '1.3.13',
	);


	protected $api_added_columns = array();
	public $table_name = 'updatecenter_config';
	public static $loadedInstance = null;


	public static function create($values = null)
	{
		return new self($values);
	}


	public static function get()
	{
		if (self::$loadedInstance)
			return self::$loadedInstance;

		return self::$loadedInstance = self::create()->order('id desc')->find();
	}

	public function define_columns($context = null)
	{
		$this->define_column('blocked_modules', 'Blocked Module Updates');
		$this->define_column('repository_config', 'Repository Config');
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_field('repository_config')->renderAs(frm_dropdown)->tab('Update Source')->comment('Select the repository config file you would like to use for updates', 'above');
		$this->add_form_partial(PATH_APP.'/modules/updatecenter/controllers/updatecenter_setup/_disable_module_checkboxes.htm')->tab('Block Updates');
	}

	public function has_active_repository(){
		if(empty($this->repository_config)){
			return false;
		}
		return true;
	}

	public function get_repository_config_options($key_value = -1) //deprecated @todo remove
	{
		$repos = $this->list_repository_options();
		$result = array();
		$result[''] = 'no repo updates';
		foreach ($repos as $repo_id=>$repo)
			$result[$repo_id] = isset($repo['name']) ? $repo['name'] : 'Unknown repository';

		if ($key_value != -1)
		{
			if (!strlen($key_value))
				return null;

			return $result[$key_value] ? $key_value : null;
		}

		return $result;

	}

	public function list_repository_options()
	{
		$result = array();

		$config_path = PATH_APP."/modules/updatecenter/repositories";
		$iterator = new DirectoryIterator( $config_path );
		foreach ( $iterator as $dir )
		{
			if ( $dir->isDir() && !$dir->isDot() )
			{
				$dirPath = $config_path."/".$dir->getFilename();
				$repo_id = $dir->getFilename();

				$infoPath = $dirPath."/"."info.php";

				if (!file_exists($infoPath))
					continue;

				include($infoPath);
				$repository_info['repo_id'] = $repo_id;
				$result[$repo_id] = $repository_info;
			}
		}

		return $result;
	}

	public function get_repository_info()
	{
		$repo_config_id = $this->repository_config;
		$repos = $this->list_repository_options();

		if (!array_key_exists($repo_config_id, $repos))
			throw new Phpr_ApplicationException('Repository config '.$repo_config_id.' not found. Please select existing config option on the System/Settings/Custom Updates page.');

		return $repos[$repo_config_id];
	}

	public function get_blocked_modules(){
		return explode(',',$this->blocked_modules);
	}

	public function is_blocked_module($module_name){

		if(in_array($module_name, $this->get_blocked_modules())){
			return true;
		}
		return false;
	}

	public static function is_core_module($module_name){
		if(in_array(strtolower($module_name),self::get_core_modules())){
			return true;
		}
		return false;
	}

	public static function get_core_modules(){
		$modules = array();
		foreach (self::$ls_core_modules as $module => $version){
			$modules[] = $module;
		}
		return $modules;
	}

	public static function get_last_ls_version($module_name){
		return self::$ls_core_modules[$module_name];
	}

	public static function get_installed_module_versions(){
		$modules = Core_ModuleManager::listModules();
		$result = array();
		foreach ($modules as $module)
		{
			$module_info = $module->getModuleInfo();
			$module_id = mb_strtolower($module->getId());
			$build = $module_info->getVersion();

			$result[$module_id]['version'] = $build;
			$result[$module_id]['info'] = $module_info;
		}
		return $result;
	}



//	public function after_modify($operation, $deferred_session_key) {
//	}


}

?>