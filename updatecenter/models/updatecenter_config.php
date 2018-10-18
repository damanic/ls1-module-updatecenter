<?php

class UpdateCenter_Config extends Db_ActiveRecord
{
	protected static $ls_core_modules = array(
		'shop' => '1.25.138',
		'cms' =>  '1.10.37',
		'backend' => '1.9.29',
		'core' => '1.11.97',
		'system' => '1.3.13',
		'users' => '1.0.25',
		'blog' => '1.0.47',
	);


	protected $api_added_columns = array();
	public $table_name = 'updatecenter_config';
	public static $loadedInstance = null;
	public $repo_info = array();


	public static function create($values = null)
	{
		return new self($values);
	}


	public static function get()
	{
		if (self::$loadedInstance)
			return self::$loadedInstance;

		$config = self::$loadedInstance = self::create()->order('id desc')->find();
		return $config ? $config : self::create();
	}

	public function define_columns($context = null)
	{
		$this->define_column('blocked_modules', 'Blocked Module Updates');
		$this->define_column('repository_config', 'Repository Config');
		$this->define_column('repo_allowed_updates', 'Allowed Updates');
		$this->define_column('github_auth_key', 'GitHub personal access token');
		$this->define_column('enable_auto_updates', 'Enable Auto Updates');
		$this->define_column('auto_update_interval', 'Check For Updates Interval');
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_field('repository_config')->renderAs(frm_dropdown)->tab('Update Source')->comment('Select the repository config file you would like to use for updates. Config files can be added/edited in the folder: /modules/updatecenter/repositories', 'above');
		$this->add_form_partial(PATH_APP.'/modules/updatecenter/controllers/updatecenter_setup/_allow_repo_module_checkboxes.htm')->tab('Update Source');
		$this->add_form_partial(PATH_APP.'/modules/updatecenter/controllers/updatecenter_setup/_disable_module_checkboxes.htm')->tab('Block Updates');
		$this->add_form_partial(PATH_APP.'/modules/updatecenter/controllers/updatecenter_setup/_status.htm')->tab('Status');
		$this->add_form_field('github_auth_key')->tab('GitHub')->comment('The access token will be used by default for all github API communications. You do not need one to access public repositories but providing one will increase you API request limit. Setting a token for a specific repo is possible via the repo config file. Generate authentication keys from your github profile -> settings -> personal access tokens', 'above');

		$this->add_form_partial(PATH_APP.'/modules/updatecenter/controllers/updatecenter_setup/_cron.htm')->tab('Cron');
		$this->add_form_field('enable_auto_updates')->renderAs(frm_onoffswitcher)->tab('Cron');
		$this->add_form_field('auto_update_interval')->tab('Cron')->comment('Enter interval in minutes. If no value is entered lemonstand will check for updates every 24 hours', 'above');
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

	public function get_repository_info($config_id=null)
	{

		$repo_config_id = empty($config_id) ? $this->repository_config : $config_id;

		if(isset($this->repo_info[$repo_config_id])){
			return $this->repo_info[$repo_config_id];
		}

		$repos = $this->list_repository_options();

		if (!array_key_exists($repo_config_id, $repos))
			throw new Phpr_ApplicationException('Repository config '.$repo_config_id.' not found. Please select existing config option on the System/Settings/Custom Updates page.');

		return $this->repo_info[$repo_config_id] = $repos[$repo_config_id];
	}

	public function get_blocked_modules(){
		return explode(',',$this->blocked_modules);
	}

	public function get_allowed_updates(){
		return unserialize($this->repo_allowed_updates);
	}

	public function get_available_updates($config_id=null){
		$updates = array();

		$repo_info = $this->get_repository_info($config_id);

		if(isset($repo_info['repositories'])) {
			foreach ( $repo_info['repositories'] as $repository_data ) {
				$source = isset($repository_data['source']) ? $repository_data['source'] : null;
				if(!isset($repository_data['modules'])){
					continue;
				}
				foreach ( $repository_data['modules'] as $module_id => $update_info ) {
					$updates[$source][$module_id]                   = $update_info;
					$updates[$source][$module_id]['allowed_update'] = $this->is_allowed_update( $source, $module_id, $update_info );
				}
			}
		}
		return $updates;
	}

	public function is_allowed_update($source, $module_id, $update_info = null){
		$allowed = $this->get_allowed_updates();
		if(is_array($allowed)) {
			if ( isset( $allowed[$source][$module_id] ) ) {
				return $allowed[$source][$module_id] ? true : false;
			}
		}

		if ( is_array( $update_info ) ) {
			return isset( $update_info['default_allow_update'] ) ? $update_info['default_allow_update'] : false;
		}

		return false;
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

	public function get_module_auth($module_name, $source=null){
		$repo_info = $this->get_repository_info();
		if(isset($repo_info['repositories'])) {
			foreach ( $repo_info['repositories'] as $repo_data ) {
				if ( !isset($repo_data['source']) || !empty( $source ) && ( $repo_data['source'] !== $source)) {
					continue;
				}

				if ( isset( $repo_data['modules'][$module_name]['auth'] ) ) {
					return $repo_data['modules'][$module_name]['auth'];
				}
			}
		}
		return false;
	}


	public function is_module_declared_public($module_name, $source=null){
		$repo_info = $this->get_repository_info();
		if(isset($repo_info['repositories'])) {
			foreach ( $repo_info['repositories'] as $repo_data ) {
				if ( !isset($repo_data['source']) || !empty( $source ) && ( $repo_data['source'] !== $source)) {
					continue;
				}

				if ( isset( $repo_data['modules'][$module_name]['public'] ) ) {
					return $repo_data['modules'][$module_name]['public'];
				}
			}
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
		$active_modules = Core_ModuleManager::listModules(false);
		$disabled_modules = Core_ModuleManager::listModules(false, true);
		$modules = array_merge($active_modules,$disabled_modules);
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

	public function get_auto_updates_interval(){
		return ($this->auto_update_interval && is_numeric($this->auto_update_interval)) ? $this->auto_update_interval : 1440;
	}



//	public function after_modify($operation, $deferred_session_key) {
//	}


}

?>