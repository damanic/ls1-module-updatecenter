<?
	$repository_info = array(
		'name'=>'Core Module Updates | github:damanic',
		'description'=>'Bugfixes and new event additions to core modules no longer updated by lemonstand (cms,core,etc). Plus updates to the updatecenter module',

		'repositories' => array(
			array(
				'source' =>	'github',
				'modules' => array(
					'core' => array(
						'owner' => 'damanic',
						'repo' => 'ls1-module-core'
					),
					'cms' => array(
						'owner' => 'damanic',
						'repo' => 'ls1-module-cms'
					),
					'updatecenter' => array(
						'owner' => 'damanic',
						'repo' => 'ls1-module-updatecenter'
					)
				)
			)
		),

	);

?>