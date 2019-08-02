<?

	/**
	 * @has_documentable_methods
	 */
	class Core_UpdateManager
	{
		const allowed_license_change_num = 3;

		protected static $instance = null;
		protected $server_responsive = null;
		
		protected function __construct()
		{
		}
		
		public static function create()
		{
			if (!self::$instance)
				self::$instance = new Core_UpdateManager();

			return self::$instance;
		}

		protected function is_server_responsive($wait=5){
			if(!empty($this->server_responsive)){
				return $this->server_responsive;
			}

			$uc_url = Phpr::$config->get('UPDATE_CENTER');
			if (!strlen($uc_url))
				throw new Exception('LemonStand eCommerce Inc. server URL is not specified in the configuration file.');

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'http://'.$uc_url.'/');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, $wait);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $wait);

			$result = curl_exec($ch);
			curl_close($ch);

			$this->server_responsive =  $result ? true : false;
			return $this->server_responsive;
		}

		protected function request_server_data($url, $fields = array(), $force = false, $time_limit=3600)
		{
			if (!$force && Phpr::$config->get('FREEZE_UPDATES'))
				throw new Exception("We are sorry, all updates have been blocked by the system administrator.");

			if(Phpr::$config->get('FREEZE_LS_UPDATES')){
				throw new Exception("Updates from Lemonstands update servers have been blocked by the system administrator.");
			}

			if(!$this->is_server_responsive()) {
				throw new Exception("Lemonstands update servers are not responding.");
			}

			$uc_url = Phpr::$config->get( 'UPDATE_CENTER' );
			if ( !strlen( $uc_url ) ) {
				throw new Exception( 'LemonStand eCommerce Inc. server URL is not specified in the configuration file.' );
			}

			Backend::$events->fireEvent( 'core:onBeforeSoftwareUpdateRequest' );

			$result = null;
			try {
				$poststring = array();

				foreach ( $fields as $key => $val ) {
					$poststring[] = urlencode( $key ) . "=" . urlencode( $val );
				}

				$poststring = implode( '&', $poststring );


				$ch = curl_init();
				curl_setopt( $ch, CURLOPT_URL, 'http://' . $uc_url . '/' . $url );
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
				curl_setopt( $ch, CURLOPT_TIMEOUT, $time_limit );
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $poststring );
				curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );


				$result = curl_exec( $ch );

				if ( curl_errno( $ch ) ) {
					throw new Phpr_ApplicationException( "Error connecting the update server." );
				} else {
					curl_close( $ch );
				}

			} catch ( Exception $ex ) {
			}

			if ( !$result || !strlen( $result ) ) {
				throw new Exception( "Error connecting to the LemonStand eCommerce Inc. server." );
			}

			$result_data = false;
			try {
				$result_data = @unserialize( $result );

			} catch ( Exception $ex ) {
				throw new Exception( "Invalid response from the LemonStand eCommerce Inc. server." );
			}

			if ( $result_data === false ) {
				throw new Exception( "Invalid response from the LemonStand eCommerce Inc. server." );
			}

			if ( $result_data['error'] ) {
				throw new Exception( $result_data['error'] );
			}

			Backend::$events->fireEvent( 'core:onAfterSoftwareUpdateRequest', $result_data );


			return $result_data;


		}
		
		protected function get_module_versions()
		{
			$result = array();
			
			$modules = Core_ModuleManager::listModules();
			foreach ($modules as $module)
			{
				$module_info = $module->getModuleInfo();
				$module_id = mb_strtolower($module->getId());
				$build = $module_info->getVersion();
				
				$result[$module_id] = $build;
			}

			$result = Backend::$events->fire_event(array('name' => 'core:onAfterGetModuleVersions', 'type' => 'filter'), array(
				'modules' => $result,
			));

			return $result['modules'];
		}

		protected function get_blocked_update_modules(){
			$ignored = Phpr::$config->get('DISABLE_MODULES', array());
			$ignored = Backend::$events->fire_event(array('name' => 'core:onGetBlockedUpdateModules', 'type' => 'filter'), array(
				'modules' => $ignored,
			));
			return $ignored['modules'];
		}

		protected function get_hash()
		{
			$hash = Db_ModuleParameters::get('core', 'hash');
			if (!$hash)
				throw new Phpr_ApplicationException('License information not found');
			
			$framework = Phpr_SecurityFramework::create();
			return $framework->decrypt(base64_decode($hash));
		}

		public function request_lemonstand_update_list($hash = null, $force = false, $time_limit=15){

			if (!$force && (Phpr::$config->get('FREEZE_UPDATES') || Phpr::$config->get('FREEZE_LS_UPDATES'))){
				throw new Exception( "We are sorry, updates were blocked by the system administrator." );
			}
			$hash = $hash ? $hash : $this->get_hash();
			$url = base64_encode(root_url('/', true, 'http'));

			$fields = array(
				'versions'=>serialize($this->get_module_versions()),
				'url'=>$url,
				'disabled'=>serialize($this->get_blocked_update_modules())
			);

			$response = $this->request_server_data('get_update_list/'.$hash, $fields, $force, $time_limit);

			if (!isset($response['data']))
				throw new Phpr_ApplicationException('Invalid server response.');

			if (!count($response['data']))
				Db_ModuleParameters::set('backend', 'ls_updates_available', 0);

			return $response;
		}

		public function request_update_list($hash = null, $force = false)
		{
			if (Phpr::$config->get('FREEZE_UPDATES'))
				throw new Exception("We are sorry, updates were blocked by the system administrator.");

			try {
				$response = $this->request_lemonstand_update_list( $hash, $force, $time_limit = 10 );
			} catch(Exception $e){
				$response = array();
			}

			$response = Backend::$events->fire_event(array('name' => 'core:onAfterRequestUpdateList', 'type' => 'filter'), array(
				'update_list' => $response,
				'force' => $force,
			));

			return $response['update_list'];
		}
		
		public function update_application($cli_mode = false, $force = false)
		{
			@set_time_limit(3600);
			
			if (Phpr::$config->get('FREEZE_UPDATES'))
				throw new Exception("We are sorry, updates were blocked by the system administrator.");

			if (!is_writable(PATH_APP.'/temp') || !is_writable(PATH_APP.'/modules') || !is_writable(PATH_APP.'/phproad'))
				throw new Exception('An install directory in '.PATH_APP.' (/temp , /modules, /phproad) is not writable for PHP.');

			try {
				if ( !$force ) {
					$update_list = $this->request_lemonstand_update_list();
					$versions    = $update_list['data'];
				} else {
					$versions = $this->get_module_versions();
				}
			} catch (Exception $e){
				//LS server down
			}
			$ls_files = array();
			try {

				if ( array( $versions ) && !Phpr::$config->get('FREEZE_LS_UPDATES',false)) {
					//do lemonstand update downloads
					$fields = array(
						'modules'  => serialize( array_keys( $versions ) ),
						'disabled' => serialize( $this->get_blocked_update_modules() )
					);

					$hash        = $this->get_hash();
					$result      = $this->request_server_data( 'get_update_hashes/' . $hash, $fields );
					$file_hashes = $result['data'];

					if ( !is_array( $file_hashes ) ) {
						throw new Exception( "Invalid server response" );
					}

					$tmp_path = PATH_APP . '/temp';
					if ( !is_writable( $tmp_path ) ) {
						throw new Exception( "Cannot create temporary file. Path is not writable: $tmp_path" );
					}


					try {
						foreach ( $file_hashes as $code => $file_hash ) {
							$tmp_file = $tmp_path . '/' . $code . '.arc';
							$result   = $this->request_server_data( 'get_update_file/' . $hash . '/' . $code );

							$tmp_save_result = false;
							try {
								$tmp_save_result = @file_put_contents( $tmp_file, $result['data'] );
							} catch ( Exception $ex ) {
								throw new Exception( "Error creating temporary file in " . $tmp_path );
							}

							$ls_files[] = $tmp_file;

							if ( !$tmp_save_result ) {
								throw new Exception( "Error creating temporary file in " . $tmp_path );
							}

							$downloaded_hash = md5_file( $tmp_file );
							if ( $downloaded_hash != $file_hash ) {
								throw new Exception( "Downloaded archive is corrupted. Please try again." );
							}
						}
					} catch ( Exception $ex ) {
						$this->update_cleanup( $ls_files );
						throw $ex;
					}
				}
			} catch (Exception $e){
				//ls server likely unreachable/blocked
			}

			//allow for other modules to provide updates
			$files = Backend::$events->fire_event(array('name' => 'core:onFetchSoftwareUpdateFiles', 'type' => 'filter'), array(
				'files' => $ls_files,
				'force' => $force,
			));
			$files = $files['files'];

			try{
				Backend::$events->fireEvent('core:onBeforeSoftwareUpdate');

				foreach ($files as $file) {
					Core_ZipHelper::unzip(PATH_APP, $file, $cli_mode);
				}

				$this->update_cleanup($files);

				Db_UpdateManager::update();

				Db_ModuleParameters::set('backend', 'ls_updates_available', 0);

				Backend::$events->fireEvent('core:onAfterSoftwareUpdate');
			} catch (Exception $ex) {
				$this->update_cleanup($files);
				throw $ex;
			}


		}
		
		protected function update_cleanup($files)
		{
			foreach ($files as $file)
			{
				if (file_exists($file))
					@unlink($file);
			}
		}
		
		public function get_updates_flag()
		{
			if (!Phpr::$config->get('AUTO_CHECK_UPDATES', true))
				return false;

			if (Phpr::$config->get('FREEZE_UPDATES'))
				return false;
			
			if (Db_ModuleParameters::get('backend', 'ls_updates_available', false))
				return true;

			try
			{
				$last_check = Db_ModuleParameters::get('backend', 'ls_last_update_check', null);
				if (strlen($last_check))
				{
					try
					{
						$last_check_time = new Phpr_DateTime($last_check);

						$check_interval = Phpr::$config->get('UPDATE_CHECK_INTERVAL', 24);
						if (Phpr_DateTime::now()->substractDateTime($last_check_time)->getHoursTotal() > $check_interval)
							$last_check = false;
					} catch (Exception $ex) {}
				}

				if (!$last_check)
				{
					try
					{
						$update_data = Core_UpdateManager::create()->request_lemonstand_update_list(null,false,5);
						$updates = $update_data['data'];
						
						Db_ModuleParameters::set('backend', 'ls_updates_available', count($updates));
					} catch (Exception $ex) {}

					$last_check = Db_ModuleParameters::set('backend', 'ls_last_update_check', 
						Phpr_DateTime::now()->format(Phpr_DateTime::universalDateTimeFormat)
					);
				}
			} catch (Exception $ex) {}
		}
		
		public function cli_update($force = false)
		{
			Core_Cli::print_line();
			Core_Cli::print_line('LEMONSTAND UPDATE TOOL');
			Core_Cli::print_line();
			
			if (Phpr::$config->get('FREEZE_UPDATES'))
			{
				Core_Cli::print_error('We are sorry, updates were blocked by the system administrator.');
				exit(1);
			}
			
			/*
			 * Check the writing permissions
			 */
			
			if (!is_writable(PATH_APP) || !is_writable(PATH_APP.'/modules') || !is_writable(PATH_APP.'/phproad'))
			{
				Core_Cli::print_error('The LemonStand directory ('.PATH_APP.') is not writable for PHP.');
				exit(1);
			}
			
			Core_Cli::print_line('Requesting updates...');
			
			try
			{
				$update_data = $this->request_lemonstand_update_list();
				$update_list = $update_data['data'];
				
				if (!count($update_list))
				{
					Core_Cli::print_line('No updates found.');
					
					if (!$force)
						exit(0);
				}
				
				if (count($update_list))
				{
					Core_Cli::print_line('The following updates were found:');
					Core_Cli::print_line();

					foreach ($update_list as $module_code=>$update_data)
					{
						Core_Cli::print_line('MODULE: '.$update_data->name);
						foreach ($update_data->updates as $version=>$description)
						{
							Core_Cli::print_line('Version: '.$version);
							Core_Cli::print_line('Description: '.$description);
						}
						Core_Cli::print_line();
					}

					if (!Core_Cli::read_bool_option('Do you want to install the updates? (Y/N): '))
						exit(1);  
				} else {
					if (!Core_Cli::read_bool_option('Do you want force update LemonStand? (Y/N): '))
						exit(1);  
				}
					
				if (Core_EulaManager::pull())
				{
					Core_Cli::print_line("The LemonStand End User License Agreement has changed. Please carefully read it. You must accept the new EULA before updating the Software.");
					Core_Cli::print_line();
					$eula_data = Core_EulaManager::get_saved_eula_data();
					
					$lines = explode("\n", $eula_data['c']);
					foreach ($lines as $line)
						Core_Cli::print_line(wordwrap($line, 80, "\n"));

					Core_Cli::print_line();
					Core_Cli::print_line();

					$agree = Core_Cli::read_bool_option('I AGREE WITH ALL THE TERMS OF THE LICENSE AGREEMENT [Y/N]: ');
					if (!$agree)
						throw new Phpr_ApplicationException('You must agree to the License Agreement to continue.');
						
					Core_EulaManager::commit();
				}
					
				Core_Cli::print_line('Updating LemonStand...');
				$this->update_application(true, $force);
			} catch (Exception $ex)
			{
				Core_Cli::print_error($ex->getMessage());
				exit(1);
			}
			
			Core_Cli::print_line('LemonStand has been successfully updated.');
			exit(0);
		}
		
		public function get_license_change_num()
		{
			return Db_ModuleParameters::get('core', 'license_change_num', 0);
		}
		
		public function set_license_info($data)
		{
			$change_num = $this->get_license_change_num();
			$max_change_num = Core_UpdateManager::allowed_license_change_num;
			
			if ($change_num >= self::allowed_license_change_num)
				throw new Phpr_ApplicationException('We are sorry, you cannot change the license details of this LemonStand installation more than '.$max_change_num.' times');
			
			$validation = new Phpr_Validation();
			$validation->add('serial_number', 'Serial number')->fn('trim')->required('Please enter the serial number');
			$validation->add('holder_name', 'Holder name')->fn('trim')->required('Please enter the holder name');
			$validation->add('license_key', 'License key')->fn('trim')->required('Please enter the license key');
			
			if (!$validation->validate($data))
				$validation->throwException();
				
			$serial_number = $validation->fieldValues['serial_number'];
			$holder_name = $validation->fieldValues['holder_name'];
			$new_license_key = $validation->fieldValues['license_key'];

			$framework = Phpr_SecurityFramework::create();
			$new_hash = base64_encode($framework->encrypt(md5($serial_number.$holder_name)));

			$this->request_lemonstand_update_list(md5($serial_number.$holder_name), true);

			Db_ModuleParameters::set('core', 'hash', $new_hash);
			Db_ModuleParameters::set('core', 'license_key', $new_license_key);
			Db_ModuleParameters::set('core', 'license_change_num', $change_num+1);
		}
		
		/*
		 * Event descriptions
		 */
		
		/**
		 * Triggered before LemonStand update request is issued.
		 * In the event handler you can throw an exception in order to cancel the request. Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('core:onBeforeSoftwareUpdateRequest', $this, 'before_software_update_request');
		 * }
		 * 
		 * public function before_software_update_request()
		 * {
		 *   throw new Phpr_ApplicationException("We're sorry, you cannot update this store.");
		 * }
		 * </pre>
		 * 
		 * @event core:onBeforeSoftwareUpdateRequest
		 * @see core:onAfterSoftwareUpdateRequest
		 * @see core:onBeforeSoftwareUpdate
		 * @see core:onAfterSoftwareUpdate
		 * @package core.events
		 * @author LemonStand eCommerce Inc.
		 */
		private function event_onBeforeSoftwareUpdateRequest() {}
		
		/**
		 * Triggered after LemonStand update request is issued.
		 * The event handler accepts the server response array. In the event handler you can throw an exception in order to cancel the request.
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('core:onAfterSoftwareUpdateRequest', $this, 'after_software_update_request');
		 * }
		 * 
		 * public function after_software_update_request($response)
		 * {
		 *   throw new Phpr_ApplicationException("We're sorry, you cannot update this store.");
		 * }
		 * </pre>
		 * 
		 * @event core:onAfterSoftwareUpdateRequest
		 * @see core:onBeforeSoftwareUpdateRequest
		 * @see core:onBeforeSoftwareUpdate
		 * @see core:onAfterSoftwareUpdate
		 * @package core.events
		 * @author LemonStand eCommerce Inc.
		 * @param array $response Specifies the server response array.
		 */
		private function event_onAfterSoftwareUpdateRequest($response) {}
			
		/**
		 * Triggered just before LemonStand is updated.
		 * This event is triggered after {@link core:onBeforeSoftwareUpdateRequest} and {@link core:onAfterSoftwareUpdateRequest} events, 
		 * when the update manager already received all required data from LemonStand update gateway.
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('core:onBeforeSoftwareUpdate', $this, 'before_software_update');
		 * }
		 * 
		 * public function before_software_update()
		 * {
		 *   // Do something
		 * }
		 * </pre>
		 * 
		 * @event core:onBeforeSoftwareUpdate
		 * @see core:onBeforeSoftwareUpdateRequest
		 * @see core:onAfterSoftwareUpdateRequest
		 * @see core:onAfterSoftwareUpdate
		 * @package core.events
		 * @author LemonStand eCommerce Inc.
		 */
		private function event_onBeforeSoftwareUpdate() {}
			
		/**
		 * Triggered after LemonStand is updated.
		 * This event is triggered after {@link core:onBeforeSoftwareUpdateRequest}, {@link core:onAfterSoftwareUpdateRequest} 
		 * and {@link core:onBeforeSoftwareUpdate} events.
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('core:onAfterSoftwareUpdate', $this, 'after_software_update');
		 * }
		 * 
		 * public function after_software_update()
		 * {
		 *   // Do something
		 * }
		 * </pre>
		 * 
		 * @event core:onAfterSoftwareUpdate
		 * @see core:onBeforeSoftwareUpdateRequest
		 * @see core:onAfterSoftwareUpdateRequest
		 * @see core:onBeforeSoftwareUpdate
		 * @package core.events
		 * @author LemonStand eCommerce Inc.
		 */
		private function event_onAfterSoftwareUpdate() {}
	}

?>