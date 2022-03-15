<? class UpdateCenter_CoreUpdate{

	protected $core_um_location;
	protected $core_ziphelper_location;
	protected $pclzip_lib_location;

	protected $update_files_location;
	protected $required_files = array(
		'core_um_location',
		'core_ziphelper_location',
		'pclzip_lib_location'
	);
	protected $checked_required_files = false;

	protected $file_content = array();

	protected $required_core_um_code = array(
		'function get_blocked_update_modules(',
		'core:onAfterGetModuleVersions',
		'core:onGetBlockedUpdateModules',
		'core:onAfterRequestUpdateList',
		'function request_lemonstand_update_list(',
		'core:onFetchSoftwareUpdateFiles',
		'function is_server_responsive('
	);

	protected $compatible_checks = array();

	public function __construct(){

		$this->core_um_location = PATH_APP.'/modules/core/classes/core_updatemanager.php';
		$this->core_eula_location = PATH_APP.'/modules/core/classes/core_eulamanager.php';
		$this->pclzip_lib_location =  PATH_APP.'/modules/core/thirdpart/pclzip.lib.php';
		$this->core_ziphelper_location = PATH_APP.'/modules/core/helpers/core_ziphelper.php';
		$this->update_files_location = PATH_APP.'/modules/updatecenter/updates/core/';
	}

	public function get_file_contents($location){
		if(isset($this->file_content[$location])){
			return $this->file_content[$location];
		}
		return $this->file_content[$location] = file_get_contents($location);
	}

	public function check_required_files(){
		if(!$this->checked_required_files) {
			foreach ( $this->required_files as $file_id ) {
				if ( !is_readable( $this->$file_id ) ) {
					throw new Phpr_Application_Exception( 'Could not read contents of required file in the core module. Please check file exists and is available to file_get_contents(): ' . $this->$file_id );
				}
			}
		}
		return $this->checked_required_files = true;
	}

	public function is_compatible(){
		if(!$this->check_compatible_core_um()) {
			return false;
		}

		if(!$this->check_compatible_pclzip_lib()) {
			return false;
		}

		if(!$this->check_compatible_ziphelper()) {
			return false;
		}

		return true;
	}

	public function check_compatible_core_um(){
		if(isset($this->compatible_checks['check_compatible_core_um']))
			return $this->compatible_checks['check_compatible_core_um'];

		foreach($this->required_core_um_code as $string){
			if(strpos($this->get_file_contents($this->core_um_location), $string) === false){
				return $this->compatible_checks['check_compatible_core_um'] = false;
			}
		}

		return $this->compatible_checks['check_compatible_core_um'] = true;
	}

	public function check_compatible_pclzip_lib(){

		if(strpos($this->get_file_contents($this->pclzip_lib_location) , 'Zip Module 2.8.4') === false){
			return $this->compatible_checks['check_compatible_pclzip_lib'] = false;
		}

		return $this->compatible_checks['check_compatible_pclzip_lib'] = true;
	}

	public function check_compatible_ziphelper(){

		if(strpos($this->get_file_contents($this->core_ziphelper_location) , 'function findFile(') === false){
			return $this->compatible_checks['check_compatible_ziphelper'] = false;
		}

		return $this->compatible_checks['check_compatible_ziphelper'] = true;
	}

	public function make_compatible(){
		if(!$this->check_compatible_pclzip_lib()){
			$this->update_pclzip();
		}

		if(!$this->check_compatible_ziphelper()){
			$this->update_ziphelper();
		}

		if(!$this->check_compatible_core_um()){
			$this->update_core_um();
		}

	}

	public function update_pclzip(){
		$replacement_file = $this->update_files_location.'pclzip.lib.php';
		if(!copy($replacement_file, $this->pclzip_lib_location)){
			throw new Phpr_ApplicationException('Could not copy pclzip.lib.php to '.$this->pclzip_lib_location.' check write permissions for PHP');
		}
		return true;
	}

	public function update_ziphelper(){
		$replacement_file = $this->update_files_location.'core_ziphelper.php';
		if(!copy($replacement_file, $this->core_ziphelper_location)){
			throw new Phpr_ApplicationException('Could not copy core_ziphelper.php to '.$this->core_ziphelper_location.' check write permissions for PHP');
		}
		return true;
	}

	public function update_core_um(){
		$replacement_file = $this->update_files_location.'core_updatemanager.php';
		if(!copy($replacement_file, $this->core_um_location)){
			throw new Phpr_ApplicationException('Could not copy core_updatemanager.php to '.$this->core_um_location.' check write permissions for PHP');
		}
		$this->update_core_eula();
		return true;
	}

	public function update_core_eula(){
		$replacement_file = $this->update_files_location.'core_eulamanager.php';
		if(!copy($replacement_file, $this->core_eula_location)){
			throw new Phpr_ApplicationException('Could not copy core_eulamanager.php to '.$this->core_eula_location.' check write permissions for PHP');
		}
		return true;
	}
}