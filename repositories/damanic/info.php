<?
	$repository_info = array(
		'name'=>'Core Module Updates (daManic)',
		'description'=>'Updates to core modules no longer updated by lemonstand (cms,shop,core,etc). Plus updates to the updatecenter module.',

		'repositories' => array(
			array(
				'source' =>	'github',
				'modules' => array(
					'shop' => array(
						'owner' => 'damanic',
						'repo' => 'ls1-module-shop',
					),
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