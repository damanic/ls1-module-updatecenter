
jQuery(document).ready(function() {

	// Code that uses jQuery's $ can follow here.
	jQuery('#UpdateCenter_Config_repository_config').live('change', function () {
		$(this).getForm().sendPhpr(
			'core:on_null',
			{
				update       : 'updatecenter_available_updates',
				loadIndicator: {show: false},
				extraFields  : {
					'config_switch': jQuery(this).val()
				},
				onSuccess    : function () {
					//$('Shop_Customer_shipping_state_id').selectedIndex = $('Shop_Customer_billing_state_id').selectedIndex;
				}
			}
		)
	})

});

