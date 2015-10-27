<?php
class UpdateCenter_Repository_github extends UpdateCenter_Repository_Driver implements UpdateCenter_Repository_Interface {

	protected $releases_uri = 'https://api.github.com/repos/{owner}/{repo}/releases/latest';
	protected $markdown_uri  = 'https://api.github.com/markdown';
	protected $cache;

	public function get_latest_version($module_name){
		if(isset($this->cache[$module_name]['latest_version'] )){
		return $this->cache[$module_name]['latest_version'];
		}

		$repo_details = $this->module_info[$module_name];
		if(!$repo_details){
			throw new Phpr_ApplicationException('No github repository information found for '.$module_name);
		}
		$repo_data = $this->request_server_data($this->get_latest_release_uri($repo_details['repo'],$repo_details['owner']));

		if(isset($repo_data['message'])){
			throw new Phpr_ApplicationException('Message from GitHub: '.$repo_data['message']);
		}

		if(!isset($repo_data['tag_name'])){
			throw new Phpr_ApplicationException('There are no releases for module `'.$module_name.'` on GitHub repo '.$repo_details['owner'].':'.$repo_details['repo'].'. To resolve this issue remove this repo from your config file or add a release version to the repository');
		}

		$this->cache[$module_name]['latest_version'] = $repo_data;
		return $repo_data;
	}

	public function get_latest_version_number($module_name){
		$repo_data = $this->get_latest_version($module_name);
		return $repo_data['tag_name'];
	}

	public function get_latest_version_description($module_name){
		$repo_data = $this->get_latest_version($module_name);
		return $repo_data['name'].' | '. strtok($repo_data['body'], "\n");
	}

	public function get_latest_version_zip_url($module_name){
		$asset = $this->get_latest_version_zip_info($module_name);
		return $asset['browser_download_url'];
	}

	public function get_latest_version_zip_size($module_name){
		$asset = $this->get_latest_version_zip_info($module_name);
		return $asset['size'];
	}


	public function get_latest_version_zip_info($module_name){
		$repo_data = $this->get_latest_version($module_name);
		if(isset($repo_data['assets'])) {
			foreach ( $repo_data['assets'] as $key => $asset ) {
				if ( $asset['content_type'] == 'application/zip' ) {
					return $asset;
				}
			}
		}

		//asset zip not found try the zip_ball
		$asset = array();
		$asset['content_type'] = 'application/zip';
		$asset['browser_download_url']  = $repo_data['zipball_url'];
		$asset['size']  = 0; //unreported by api
		return $asset;

	}

	protected function get_latest_release_uri($repo,$owner){
		$uri = str_replace('{owner}',$owner, $this->releases_uri);
		$uri = str_replace('{repo}',$repo, $uri);
		return $uri;
	}
	protected function request_server_data($url, $fields = array())
	{
		$result = null;
		try
		{
			$poststring = array();

			foreach($fields as $key=>$val) {
				$poststring[] = urlencode( $key ) . "=" . urlencode( $val );
			}

			$poststring = implode('&', $poststring);


			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_USERAGENT, 'PHP/Lemonstand');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			if(count($fields)){
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
			}


			$result = curl_exec($ch);


			if (curl_errno($ch))
				throw new Phpr_ApplicationException( "Error connecting the update server." );
			else
				curl_close($ch);

		} catch (Exception $ex) {}

		if (!$result || !strlen($result))
			throw new Exception("Error connecting to the GitHub API.");

		$result_data = json_decode($result,true);



		return $result_data ? $result_data : $result;
	}
}