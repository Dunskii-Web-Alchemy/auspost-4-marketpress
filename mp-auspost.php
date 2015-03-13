<?php
/*
Plugin Name: MarketPress AUSPOST Calculated Shipping Plugin
Plugin URL :  http://www.dunskii.com/project/auspost-marketpress/
Author: Dunskii Web Services 
Author URL: http://dunskii.com 

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA	 02111-1307	 USA


---------------------------------------------------------------

INSTALLATION and USAGE

Upload this file to /wp-content/plugins/marketpress/marketpress-includes/plugins-shipping.

MARKETPRESS STORE SETTINGS

Products ->  Store Settings -> General Tab -> Location Settings

Set Australia as country (if you don't do this you can see the plugin as a Calcualted Option in the Shipping Settings), the select your the state and post code when you ship from.

AUSPOST SHIPPING SETTINGS

Products ->  Store Settings -> Shipping Tab
Shipping method: Calculated Options
Shipping Option: AUSPOST
Measurement System: Metric
Enter your AUSPOST Developer Kit Access Key
Set the AusPost services you provide
Rename Service Label if you want
Add shipping pricing rules if you want


N.B. When you update your MarketPress plugin, you will need to reinstall this plugin. All settings should be saved.

---------------------------------------------------------------

*/

   // Set Time Limit //
	set_time_limit(0);
	
	//Errror reporting on....
	error_reporting(0);

	//Errors off...
	 ini_set('display_errors',0);

	// Request action to check api using curl //
	$action = $_REQUEST['action'];

	// check condition api //
	if($action!='my_action'):
	
	class MP_Shipping_AUS extends MP_Shipping_API {

	//private shipping method name. Lowercase alpha (a-z) and dashes (-) only please!
	public $plugin_name = 'ausship';

	//public name of your method, for lists and such.
	public $public_name = '';

	//set to true if you need to use the shipping_metabox() method to add per-product shipping options
	public $use_metabox = true;

	//set to true if you want to add per-product extra shipping cost field
	public $use_extra = true;

	//set to true if you want to add per-product weight shipping field
	public $use_weight = true;

	//Test sandboxed URI for AUSPOST Rates API
	public $sandbox_uri = 'https://auspost.com.au/api/';

	//Production Live URI for AUSPOST Rates API0
	public $production_uri = 'https://auspost.com.au/api/';

	// Defines the available shipping Services and their display names
	public $services = array();

	// default setting should be blank //
	private $settings = '';
	
	// all data are stored in this $auspost_settings //
	private $auspost_settings;

	//Set to display any errors in the Rate calculations.
	private $rate_error = '';

	/*
	 Runs when your class is instantiated. Use to setup your plugin instead of __construct()
	*/
	
	function on_creation() {
		global $mp;

		//set name here to be able to translate
		$this->public_name = __('AUSPOST', 'mp');
		
		global $wpdb;
		$select_data_domestic = $wpdb->get_results("SELECT * FROM mp_domestic_label");
		
		//AUSPOST Domestic services
		
		$this->services=array();
		
		$fields=array('domestic_1'=>array('service'=>'AUS_PARCEL_REGULAR','default'=>'2-4 Business days'),'domestic_2'=>array('service'=>'AUS_PARCEL_EXPRESS','default'=>'Next business day'),'domestic_3'=>array('service'=>'AUS_PARCEL_REGULAR_SATCHEL_5KG','default'=>'Parcel Post Large'),'domestic_4'=>array('service'=>'AUS_PARCEL_EXPRESS_SATCHEL_5KG','default'=>'Express Post Large (5Kg) Satchel'));
		
		if(!empty($select_data_domestic))
		{
			foreach($select_data_domestic as $result)
			{
				foreach($fields as $field_name=>$express_service)
				{
					if(!empty($result->$field_name))
					{
						$this->services[$result->$field_name]= new AUSPOST_Service($express_service['service'], __($result->$field_name, 'mp'),__('', 'mp'));
					}
					else
					{
						$this->services[$express_service['default']]=new AUSPOST_Service($express_service['service'], __($express_service['default'], 'mp'),__('', 'mp'));
					}
					
				}
			}
		}
		//AUSPOST International services
		$select_data_int = $wpdb->get_results("SELECT * FROM mp_int_label");
		
		$this->intl_services =array();
		$fields_inter=array('int_1'=>array('service'=>'INTL_SERVICE_AIR_MAIL','default'=>'Air Mail'),'int_2'=>array('service'=>'INTL_SERVICE_SEA_MAIL','default'=>'Sea Mail'));
		
		if(!empty($select_data_int))
		{
			foreach($select_data_int as $result)
			{
				foreach($fields_inter as $field_name_int=>$express_service_int)
				{
					if(!empty($result->$field_name_int))
					{			
						$this->intl_services[$result->$field_name_int]= new AUSPOST_Service($express_service_int['service'], __($result->$field_name_int, 'mp'),__($result->$field_name_int, 'mp'),'mp');
					}
					else
					{
						$this->intl_services[$express_service_int['default']]=new AUSPOST_Service($express_service_int['service'], __($express_service_int['default'], 'mp'),__($express_service_int['default'], 'mp'),'mp');
					}
				}
			}
		}
		
		// Get settings for convenience sake
		$this->settings = get_option('mp_settings');
		$this->auspost_settings = $this->settings['shipping']['ausship'];

	}

	function default_boxes(){
		// Initialize the default boxes if nothing there
		if(count($this->auspost_settings['boxes']['name']) < 2)
		{
		  $this->auspost_settings['boxes'] = array (
			'name' =>
				array (
				0 => 'Small Express',
				 1 => 'Medium Express',
				  2 => 'Large Express',
				   3 => 'AUSPOST 10KG',
				    4 => 'AUSPOST 25KG',
					),
			'size' =>
				array (
				0 => '13x11x2',
				 1 => '15x11x3',
				  2 => '18x13x3',
				   3 => '17x13x11',
				    4 => '19x17x14',
				   ),
			'weight' =>
				array (
				0 => '10',
				 1 => '20',
				  2 => '30',
				   3 => '22',
				    4 => '55',
				),
			);
		}
	}
	
	function default_country(){
		// Initialize the default boxes if nothing there
		if(count($this->auspost_settings['country']['name_country']) < 2)
		{
			$this->auspost_settings['country'] = array (
			'name_country'=>
			array(
				0 => 'AU'
			  )
			);
		}
	}
	
	/**
	* Echo anything you want to add to the top of the shipping screen
	*/
	function before_shipping_form($content) {
		return $content;
	}

	/**
	* Echo anything you want to add to the bottom of the shipping screen
	*/
	function after_shipping_form($content) {
		return $content;
	}

	/**
	* Echo a table row with any extra shipping fields you need to add to the shipping checkout form
	*/
	function extra_shipping_field($content) {
		?>
		<script type="text/javascript">
		/**** MarketPress Checkout JS *********/
		jQuery(document).ready(function($) {
		
			$("#mp-shipping-select-holder select").hide();	
			
			$('#mp_country').change(function() {
				if($('#mp_country').val() == 'AU' ){
					$('#extracover').css('display','block');
					$('#signature').css('display','block');
					$('#deliveryConfirmation').css('display','none');
					$('#deliveryConfirmation').find('input:checkbox:first').prop('checked', false);
					}
					else{
					$('#extracover').css('display','none');
					$('#signature').css('display','none');
					$('#extracover').find('input:checkbox:first').prop('checked', false);
					$('#signature').find('input:checkbox:first').prop('checked', false);
					$('#deliveryConfirmation').css('display','block');
					}
				});
	
			$('#deliveryConfirmationchk').change(function() {
				mp_refresh_shipping();
			});
			$('#signaturechk').change(function() {
				mp_refresh_shipping();
			});
			$('#extracoverchk').change(function() {
				mp_refresh_shipping();
			});
	
			function mp_refresh_shipping() {
		    $("#mp_shipping_submit").attr('disabled', 'disabled');
		    $("#mp-shipping-select-holder").html('<img src="'+MP_Ajax.imgUrl+'" alt="Loading..." />');
		    var serializedForm = $('form#mp_shipping_form').serialize();
		    $.post(MP_Ajax.ajaxUrl, serializedForm, function(data) {
		      $("#mp-shipping-select-holder").html(data);
		    });
		    $("#mp_shipping_submit").removeAttr('disabled');
		  	}

		});
		
		</script>
		<?php
		global $wpdb;
		$get_domestic_data = $wpdb->get_results("SELECT * FROM mp_domestic_label");
		$content .= '<tr id="extracover" style="display:none;"><td><input type="checkbox" id="extracoverchk" name="extracover" value="AUS_SERVICE_OPTION_EXTRA_COVER">Extra Cover for loss or damage</tr></td>';		
		$content .= '<tr id="deliveryConfirmation" style="display:none;"><td><input id="deliveryConfirmationchk" type="checkbox" name="deliveryConfirmation" value="INTL_SERVICE_OPTION_CONFIRM_DELIVERY">Delivery Confirmation</tr></td>';
		if($get_domestic_data[0]->active==0) {
		$content .= '<tr id="signature" style="display:none;"><td><input id="signaturechk" type="checkbox" name="signature" value="AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY">Signature on Delivery</td></tr>';
		} else {
		$content .= '<tr><td style="display:none;"><input id="signaturechk" type="checkbox" checked="checked" name="signature" value="AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY">Signature on Delivery</td><td id="signature" style="display:none;"><b>Note:&nbsp;</b>Signature Charges Included</td></tr>';
		}
		return $content;
	} // function close extra_shipping_field

		/**
		* Use this to process any additional field you may add. Use the $_POST global,
		*  and be sure to save it to both the cookie and usermeta if logged in.
		*/
		function process_shipping_form() {
		}
		/*
		* Echos one row for boxes data. If $key is non-numeric then emit a blank row for new entry
		*
		* @ $key
		*
		* @ returns HTML for one row
		*/
	private function box_row_html($key=''){
	$name = '';
	$size = '';
	$weight = '';

	if ( is_numeric($key) ){
		$name = $this->auspost_settings['boxes']['name'][$key];
		$size = $this->auspost_settings['boxes']['size'][$key];
		$weight = $this->auspost_settings['boxes']['weight'][$key];
		if (empty($name) && empty($size) &empty($weight)) return''; //rows blank, don't need it
	}
	?>
	  <tr class="variation">
		  <td class="mp_box_name">
			  <input type="text" name="mp[shipping][ausship][boxes][name][]" value="<?php echo esc_attr($name); ?>" size="18" maxlength="20" />
			  </td>
		
 	  <td class="mp_box_dimensions">
	  	<label>
	  		<input type="text" name="mp[shipping][ausship][boxes][size][]" value="<?php echo esc_attr($size); ?>" size="10" maxlength="20" />
	  		<?php echo $this->get_units_length(); ?>
		  </label>
	  </td>
		
	  <td class="mp_box_weight" colspan="2">
		  <label>
	  		<input type="text" name="mp[shipping][ausship][boxes][weight][]" value="<?php echo esc_attr($weight); ?>" size="6" maxlength="10" />
			  <?php echo $this->get_units_weight(); ?>
	  	</label>
	  </td>
	</tr>
		<?php
	}//private function close box_row_html
	 private function country_row_html($country_key=''){
	  $name_country = '';
		$rate_country = '';
		  $quantity_country = '';
		  $shipp_method = '';
		if ( is_numeric($country_key) ){
			$name_country = $this->auspost_settings['country']['name_country'][$country_key];
			$shipp_method = $this->auspost_settings['country']['shipp_method'][$country_key];
			$quantity_country = $this->auspost_settings['country']['quantity'][$country_key];
			$rate_country = $this->auspost_settings['country']['rate'][$country_key];
			$percentage_price = $this->auspost_settings['country']['percentage_price'][$country_key];
		
			if (empty($name_country) && empty($shipp_method) && empty($rate_country) && empty($quantity_country)) return''; //rows blank, don't need it
		
		}
		global $mp;

		?>
		<tr class="variation ship_info">
			<td class="mp_auspost_name">
		     <select name="mp[shipping][ausship][country][name_country][]">
				 <option value="">--Select Country--</option>
				 <?php 
				 
				 foreach($mp->countries as $code=>$country)
				 {
				 ?>
				  <option <?php if($name_country == $code) { echo "selected='selected'"; } ?> value="<?php echo $code; ?>"><?php echo $country; ?></option>    
				 <?php
				 }
				 ?>
				 </select>
			</td>
				<td class="mp_auspost_name">
			      <select name="mp[shipping][ausship][country][shipp_method][]" id="target_shipp_<?php echo $country_key; ?>" class="ship_method_dd">
				  <option value="">---Select Method---</option>

				  <option id="1" <?php if($shipp_method == "free") { echo "selected='selected'"; } ?> value="free">Free Shipping</option>
				  <option id="2" <?php if($shipp_method == "free_total") { echo "selected='selected'"; } ?> value="free_total">Free Shipping with minimum order total</option>
				  
				  <option id="3" <?php if($shipp_method == "free_quantity") { echo "selected='selected'"; } ?> value="free_quantity">Free Shipping with minimum quantity</option>

  				  <option id="4" <?php if($shipp_method == "+") { echo "selected='selected'"; } ?> value="+">Increase Price By %</option>   
  				  <option id="5" <?php if($shipp_method == "(+)") { echo "selected='selected'"; } ?> value="(+)">Increase Price By $</option>    
  				   
				  <option id="6" <?php if($shipp_method == "-") { echo "selected='selected'"; } ?> value="-">Decrease Price By %</option> 
				  <option id="7" <?php if($shipp_method == "(-)") { echo "selected='selected'"; } ?> value="(-)">Decrease Price By $</option>   
				 </select>
			</td>
			<td class="mp_rate" >
				<label>
					<input type="text" name="mp[shipping][ausship][country][quantity][]" id="target_quantity_<?php echo $country_key; ?>" class="target" value="<?php echo esc_attr($quantity_country); ?>" size="10" maxlength="20" <?php echo (($rate_country != '') || ($shipp_method == "+") || ($shipp_method == "(+)") || ($shipp_method == "(-)") || ($shipp_method == "-") || ($shipp_method =="free") || ($shipp_method=="free_total"))?'readonly':''; ?> />
				</label>
			</td>

			<td class="mp_rate" >
				<label>
					<input type="text" name="mp[shipping][ausship][country][rate][]" id="target_rate_<?php echo $country_key; ?>" class="target_rate" value="<?php echo esc_attr($rate_country); ?>" size="10" maxlength="20" <?php echo (($quantity_country != '') || ($shipp_method == "+") || ($shipp_method == "(+)") || ($shipp_method == "(-)") || ($shipp_method == "-") || ($shipp_method =="free") || ($shipp_method=="free_quantity") )?'readonly':''; ?> />
				</label>
			</td>
			<td class="mp_rate" style="text-align: center;" >
				<label>
					<input type="text" name="mp[shipping][ausship][country][percentage_price][]" id="target_percentage_<?php echo $country_key; ?>" class="percentage" value="<?php echo esc_attr($percentage_price); ?>" size="10" maxlength="20" <?php echo (($shipp_method == "free") || ($shipp_method=="free_total") || ($shipp_method=="free_quantity"))?'readonly':''; ?> />
				</label>
			</td>
				<?php if ( is_numeric($country_key) ): ?>
			<td class="mp_box_remove">
				<a onclick="auspostDeleteBox(this);" href="#mp_auspost_country_table" title="<?php _e('Remove Box', 'mp'); ?>" ></a>
			</td>
		<?php else: ?>
			<td class="mp_box_add">
				<a onclick="auspostAddBox(this);" href="#mp_auspost_country_table" title="<?php _e('Add Box', 'mp'); ?>" ></a>
			</td>
		<?php endif; ?>
		</tr>
		<?php
	}

	/**
	* Echo a settings meta box with whatever settings you need for you shipping module.
	*  Form field names should be prefixed with mp[shipping][plugin_name], like "mp[shipping][plugin_name][mysetting]".
	*  You can access saved settings via $settings array.
	*/
	function shipping_settings_box($settings)
	{
		global $mp;
		$this->settings = $settings;
		$this->auspost_settings = $this->settings['shipping']['ausship'];
		$system = $this->settings['shipping']['system']; //Current Unit settings english | metric
		?>
		<?php if($this->auspost_settings['api_key']=="") { ?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var elements = document.getElementsByClassName("diplay_check");
			for(var i = 0, length = elements.length; i < length; i++) {
		    elements[i].style.display = 'none';
			}
		});
		</script>
		<?php } ?>
		<script type="text/javascript">
		
		jQuery(document).ready(function($) {
		
		//add Id on submit button

		jQuery(".ship_method_dd").change(function(){
			var id_val = $(this).find('option:selected').attr('id');
			
			if(jQuery.isNumeric( id_val ))
			{
				if(id_val == 1)
				{
					jQuery(this).parent('td').next('td').find('input').attr("readonly", true);
					jQuery(this).parent('td').next('td').next('td').find('input').attr("readonly", true);
					jQuery(this).parent('td').next('td').next('td').next('td').find('input').attr("readonly", true);
					
					jQuery(this).parent('td').next('td').find('input').val("");
					jQuery(this).parent('td').next('td').next('td').find('input').val("");
					jQuery(this).parent('td').next('td').next('td').next('td').find('input').val("");
				}

				else if(id_val == 2)
				{
					
					jQuery(this).parent('td').next('td').find('input').attr("readonly", true);
					jQuery(this).parent('td').next('td').next('td').find('input').attr("readonly", false);
					jQuery(this).parent('td').next('td').next('td').next('td').find('input').attr("readonly",true);
					
					jQuery(this).parent('td').next('td').find('input').val("");
					jQuery(this).parent('td').next('td').next('td').find('input').val("");
					jQuery(this).parent('td').next('td').next('td').next('td').find('input').val("");
					
				} else if(id_val == 3)
				{
					
					jQuery(this).parent('td').next('td').find('input').attr("readonly", false);
					jQuery(this).parent('td').next('td').next('td').find('input').attr("readonly", true);
					jQuery(this).parent('td').next('td').next('td').next('td').find('input').attr("readonly", true);
					
					jQuery(this).parent('td').next('td').find('input').val("");
					jQuery(this).parent('td').next('td').next('td').find('input').val("");
					jQuery(this).parent('td').next('td').next('td').next('td').find('input').val("");
				}
				
				 else if(id_val == 4 || id_val == 5 || id_val == 6 || id_val == 7 )
				{
					
					jQuery(this).parent('td').next('td').find('input').attr("readonly", true);
					jQuery(this).parent('td').next('td').next('td').find('input').attr("readonly", true);
					jQuery(this).parent('td').next('td').next('td').next('td').find('input').attr("readonly", false);
					
					jQuery(this).parent('td').next('td').find('input').val("");
					jQuery(this).parent('td').next('td').next('td').find('input').val("");
					jQuery(this).parent('td').next('td').next('td').next('td').find('input').val("");
				}
			}
		});
		
		jQuery(".submit input").attr('id', 'newID');
		
			jQuery(".diplay_check .domestic").css('width','700px');

			jQuery("#url_2").css('float','right');
		
			jQuery("#img_auspost").css('width','160px');
		
			jQuery("#img_auspost").css('border','1px solid black');

			jQuery("#api_width").css('width','26%');
		
			jQuery("#small_auspost_img").css('width','20px');
		
			jQuery("#small_auspost_img").css('padding-right','8px');
		
			jQuery("#small_auspost_img").css('padding-top','10px');
		
		jQuery("input.target").each(function()
		{

			jQuery(this).change(function(){				
			if(jQuery(this).val() != '')
			{
				if(jQuery.isNumeric(jQuery(this).val()))
				{
				
					jQuery(this).parent('label').parent('td').next('td').find('input').attr("readonly", true);
					jQuery(this).parent('label').parent('td').next('td').find('input').val("");
					
				}
				else
				{
					alert('Input numeric value');
					jQuery(this).focus();
				}
			}
			else
			{
				jQuery(this).parent('label').parent('td').next('td').find('input').attr("readonly", false);
			}
		});
		});
		
		jQuery("input.target_rate").each(function()
		{
			jQuery(this).change(function(){				
			if(jQuery(this).val() != '')
			{
				if(jQuery.isNumeric(jQuery(this).val()))
				{
					jQuery(this).parent('label').parent('td').prev('td').find('input').attr("readonly", true);
					jQuery(this).parent('label').parent('td').prev('td').find('input').val("");
				}
				else
				{
					alert('Input numeric value');
					jQuery(this).focus();
				}
			}
			else
			{
				jQuery(this).parent('label').parent('td').prev('td').find('input').attr("readonly", false);
			}
			
		});
		
		
		
	});	
		
	
	}); //ready function close
		
		//api checking button
		function api_checking(){
			var check_api1 = document.getElementById("check_api").value;
			document.getElementById("img_loading").style.display = "inline-block";
			document.getElementById("loading").src="https://upload.wikimedia.org/wikipedia/commons/d/de/Ajax-loader.gif";					
			jQuery.ajax({
				type: "POST",
				url:"<?php echo $mp->plugin_url; ?>/plugins-shipping/mp-auspost.php" ,
				data:{action:'my_action',key:check_api1},
				success: function(data)
				 {
					if(data=="Api")
					{
						var elements = document.getElementsByClassName("diplay_check");
						for(var i = 0, length = elements.length; i < length; i++) {
					    elements[i].style.display = '';
						}
						 jQuery(".form-table tbody tr td label input:radio").addClass("intro");
						 jQuery('.intro').attr('checked', true);
						 document.getElementById("img_loading").style.display = "none";
						 document.getElementById("loading").src="";
						 document.getElementById("img_correct").style.display = "inline-block";
						 document.getElementById("correct").src="http://wikieducator.org/images/5/54/Correct.png";
					 }
					 else
					 {	
						document.getElementById("img_correct").style.display = "inline-block";
						document.getElementById("correct").src="<?php echo $mp->plugin_url; ?>/images/remove.png";
						document.getElementById("check_api").value="";
						var elements = document.getElementsByClassName("diplay_check");
						for(var i = 0, length = elements.length; i < length; i++) {
					    elements[i].style.display = 'none';
						}
						jQuery(".form-table tbody tr td label input:radio").addClass("intro");
						jQuery('.intro').attr('checked', false);
						document.getElementById("img_loading").style.display = "none";
						document.getElementById("text_correct").style.display = "inline-block";
						document.getElementById("loading").src="";
						}
					 }
				});
			 } 
		// close api_checking function	
			
		//Remove a row in the shipping auspost phase 2 table
			function auspostAddBox(row)
			{
				var clone = row.parentNode.parentNode.cloneNode(true);
				document.getElementById('mp_auspost_country_table').appendChild(clone);
				var fields = clone.getElementsByTagName('input');
				for(i = 0; i < fields.length; i++)
				{
					fields[i].value = '';
				}
				
			}
			function auspostDeleteBox(row)
			{
				var i = row.parentNode.parentNode.rowIndex;
				document.getElementById('mp_auspost_country_table').deleteRow(i);
			}
			</script>
			<div id="mp_ausship_rate" class="postbox">
			<h3 class='hndle'>
			<img id="small_auspost_img" src="http://static.auspost.com.au/ap/css/images/auspost.png" />
			<span><?php _e('AUSPOST SHIPPING METHOD SETTINGS', 'mp'); ?></span>
			</h3>
			<div class="inside">
			<input type="hidden" name="MP_Shipping_AUS_meta" value="1" />
		<table class="form-table">
		 <tbody>
		   <tr>
		   	<th colspan="2">
			<?php _e('Australia Post is the trading name of the Australian Government-owned Australian Postal Corporation. ', 'mp') ?>
			<?php _e('AusPost Provide and the user ID and password associated with the access key. Set these up for free <a href="https://auspost.com.au/forms/dce-registration.html">here</a>.  ', 'mp') ?><?php _e('If this information is missing or incorrect, an error will appear during the checkout process and the buyer will not be able to complete the transaction.', 'mp') ?>
			</th>
		  </tr>
		<tr class="diplay_check">
		  <th scope="row"><?php _e('AUSPOST Sandbox Mode', 'mp') ?></th>
			 <td><label><input type="radio" name="mp[shipping][ausship][sandbox]" value="1"<?php checked(! empty($this->auspost_settings['sandbox'])); ?>" /> Sandbox</label>&nbsp;&nbsp;
			<label><input type="radio" name="mp[shipping][ausship][sandbox]" value="0" <?php checked(empty($this->auspost_settings['sandbox'])); ?>" />&nbsp;Production</label></td>
			</tr>
				
	<tr class="">
	  <td colspan="2">
	   <table class="widefat" id="mp_ausship_boxes_table">
		 <thead>
			<tr>
				<th scope="col" class="mp_box_name"><?php _e('Please Enter Correct API', 'mp'); ?></th>
				<th scope="col" class="mp_box_remove"></th>
			</tr>
		    
		</thead>
		    <tbody>
			<tr>
				<th scope="row" id="api_width">
				<?php _e('AUSPOST Developer Kit Access Key', 'mp') ?>
				</th>
			<td>
				<input type="text" name="mp[shipping][ausship][api_key]" id="check_api" value="<?php echo esc_attr($this->auspost_settings['api_key']); ?>" size="40" maxlength="200" />	
		     <span id="img_correct" style="display:none"><img src="" id="correct" width="14" height="14"></span>
			 <button type="button" name="button_api" class="button-secondary" id="checking" onclick="return api_checking();" >Check </button>
			 <span id="img_loading" style="display:none"><img src="" id="loading" width="14" height="14"></span>
			 <span class="description" id="text_correct" style="display:none"><b>Note:</b>Please Enter Correct API</span>
		  </td>
		</tr>
	 </tbody>
 	 </table>
	</td>
   </tr>
	<?php
		global $wpdb;
		$get_domestic_data = $wpdb->get_results("SELECT * FROM mp_domestic_label");
			?>		
			<tr class="diplay_check	">
				<th scope="row"><?php _e('Signature On Delivery Mandatory', 'mp') ?></th>
				<td>
					<label>
						<?php //echo $get_domestic_data[0]->active; ?><input type="radio" name="mandatory_signature" value="1"<?php if($get_domestic_data[0]->active == 1 ) { echo "checked=\"checked\""; } ?> /> yes
					</label>&nbsp;&nbsp;
					<label>
						<input type="radio" name="mandatory_signature" value="0" <?php if($get_domestic_data[0]->active == 0) { echo "checked=\"checked\"";   } ?> /> No
					</label>
				</td>
			</tr>
			<tr class="diplay_check" >
				<th scope="row"><?php _e('Extra cover charge for loss or damage', 'mp') ?></th>
					<td><input type="text" id="extra_cover" name="mp[shipping][ausship][extra_cover]" value="<?php echo esc_attr($this->auspost_settings['extra_cover']); ?>" size="20" maxlength="20" /></td>
			</tr>

			<tr class="diplay_check">
				<th scope="row"><?php _e('AUSPOST Offered Domestic Services', 'mp') ?></th>

				<td>
				<?php
				$i=1;
				foreach($this->services as $name => $service) : ?>
				<label>
					<input type="checkbox" id="services" name="mp[shipping][ausship][services][<?php echo $name; ?>]" value="1" <?php checked($this->auspost_settings['services'][$name]); ?> />&nbsp;<?php echo $service->name; ?>
				</label><br />
				<?php endforeach; ?>
				</td>
			</tr>
				<?php 
				$get_int_data = $wpdb->get_results("SELECT * FROM mp_int_label");
				?>
			<tr class="diplay_check">
				<th scope="row"><?php _e('AUSPOST Offered International Services', 'mp') ?></th>
					<td>
					<?php
						$i=1; 
						foreach($this->intl_services as $name => $service) : ?>
						<label>
						<input type="checkbox" id="intl_services" name="mp[shipping][ausship][intl_services][<?php echo $name; ?>]" value="1" <?php checked($this->auspost_settings['intl_services'][$name]); ?> />
						&nbsp;<?php echo $service->name; ?>
						</label><br />
						<?php  endforeach; ?>
					</td>
			</tr>
			
			<tr class="diplay_check">
				<td colspan="2">
					<table class="widefat" id="mp_ausship_boxes_table">
				<thead>
				<tr>
					<th scope="col" class="mp_box_name"><?php _e('Domestic Services Labels', 'mp'); ?></th>
					<th scope="col" class="mp_box_remove"></th>
				</tr>
				</thead>

			<tbody>
	
			<tr class="diplay_check" >

				<th scope="row" >
					<?php _e('2-4 Business days', 'mp') ?>
				</th>
				
				<td class="domestic" >
				<input type="text" id="domestic_label1" name="domestic_label1" value="<?php echo $get_domestic_data[0]->domestic_1 ?>" size="50" maxlength="120" />
				</td>

			</tr>
			
			<tr class="diplay_check" >
			
				<th scope="row" >
					<?php _e('Next business day', 'mp') ?>
				</th>
			
				<td class="domestic">
					<input type="text" id="domestic_label2" name="domestic_label2" value="<?php echo $get_domestic_data[0]->domestic_2 ?>" size="50" maxlength="120" />
				</td>
			
			</tr>

			<tr class="diplay_check" >
				<th scope="row" >
					<?php _e('Parcel Post Large 5Kg Satchel', 'mp') ?>
				</th>

				<td class="domestic">
					<input type="text" id="domestic_label3" name="domestic_label3" value="<?php echo $get_domestic_data[0]->domestic_3 ?>" size="50" maxlength="120" />
				</td>
			
			</tr>
			
			<tr class="diplay_check" >
				<th scope="row" >
					<?php _e('Express Post Large 5Kg Satchel', 'mp') ?>
			  	</th>
			
			  	<td class="domestic">
					<input type="text" id="domestic_label4" name="domestic_label4" value="<?php echo $get_domestic_data[0]->domestic_4 ?>" size="50" maxlength="120" />
				</td>

				</tr>
			</tbody>
		</table>
	</td>
</tr>

	<?php $get_int_data = $wpdb->get_results("SELECT * FROM mp_int_label"); ?>	

	<tr class="diplay_check">
		<td colspan="2">

			<table class="widefat" id="mp_ausship_boxes_table">

			<thead>
			
				<tr>
					<th scope="col" class="mp_box_name"><?php _e('International Services Labels', 'mp'); ?></th>
					<th scope="col" class="mp_box_remove"></th>
				</tr>

			</thead>

			<tbody>
			
			<tr class="diplay_check" >
			
				<th scope="row">
				<?php _e('Air Mail', 'mp') ?>
				</th>
			
				<td class="domestic">
				<input type="text" id="intlabel_1" name="int_1" value="<?php echo $get_int_data[0]->int_1 ?>"  size="50" maxlength="120" />
				</td>

			</tr>

			<tr class="diplay_check" >
			
				<th scope="row">
				<?php _e('Sea Mail', 'mp') ?>
				</th>
			
			<td class="domestic">
				<input type="text" id="intlabel_2" name="int_2" value="<?php echo $get_int_data[0]->int_2 ?>" size="50" maxlength="120" />
			</td>
			
			   </tr>
			</tbody>
  		</table>
	</td>
</tr>

			<tr class="diplay_check">
				<td colspan="2">
					
				<p>
					<span class="description">
						<?php _e('Enter your standard box sizes as LengthxWidthxHeight', 'mp') ?> (<b>e.g. 12x8x6</b>)
						<?php _e('For each box defined enter the maximum weight it can contain.', 'mp') ?>
					</span>
				</p>
			
				<table class="widefat" id="mp_ausship_boxes_table">
			
				<thead>
					
					<tr>
						<th scope="col" class="mp_box_name"><?php _e('Box Name', 'mp'); ?></th>

						<th scope="col" class="mp_box_dimensions"><?php _e('Box Dimensions', 'mp'); ?></th>
						
						<th scope="col" class="mp_box_weight"><?php _e('Max Weight per Box', 'mp'); ?></th>
						
						<th scope="col" class="mp_box_remove" ></th>

					</tr>

				</thead>
				
			   <tbody>
			<?php
			$this->default_boxes();

			if($this->auspost_settings['boxes'])
			{
				foreach($this->auspost_settings['boxes']['name'] as $key => $value)
				{
				$this->box_row_html($key);
				}
			}
			?>
			</tbody>
		 </tbody>
		</table>
	   </td>
	 </tr>
   </table>
  </td>
</tr>
	  <tr>

		  <td colspan="2">

		  	<p>

		  	<span class="description">

		  		<?php _e('<b>Shipping Price Override</b> -Note:the shipping prices this plugin calculates are estimates...." back under the box modification section. (E.g. If country England AND total_price > $100 then shipping = free else default '); ?>

		  		</span>

		  	</p>

		  	<table class="widefat" id="mp_auspost_country_table">

		  		<thead>
				
			  		<tr>
						<th scope="col" class="mp_auspost_name"><?php _e('COUNTRY NAME', 'mp'); ?></th>
						<th scope="col" class="mp_rate"><?php _e('PRICING RULES', 'mp'); ?></th>
						<th scope="col" class="mp_rate"><?php _e('QUANTITY', 'mp'); ?></th>
						<th scope="col" class="mp_rate"><?php _e('PRICE', 'mp'); ?></th>
						<th scope="col" class="mp_rate"><?php _e('Percentage or what ever is suitable', 'mp'); ?></th>
						<th scope="col" class="mp_box_remove"></th>
					</tr>

				</thead>

			<tbody>

			<?php
			
			$this->default_country();
            
			if($this->auspost_settings['country']!="")
			{
			   foreach($this->auspost_settings['country']['name_country'] as $country_key => $value)
			   {
				$this->country_row_html($country_key); 
			   }
			   $_SESSION['count_country'] = $country_key;
			   $this->country_row_html('');
			}
			?>
			</tbody>
		</table>
	   </td>
	</tr>
			
		<tr>

			<th scope="row" colspan="2">
			<p>

				<span class="description" style="float:left;">
					<?php _e('Note: the shipping prices this plugin calculates are estimates. If they are consistently too low or too high, please check that the list of boxes above and the product weights are accurate and complete." to below the Box Name/Box dimension area.', 'mp') ?>
				</span>

				<span id="url_2" ><a href="http://auspost.com.au/index.html" target="_blank"><img src="http://gadgetbox.com.au/product_images/uploaded_images/auspost-2.png" id="img_auspost"></a></span>
			
			</p>
			
		  </th>
		  
		</tr>
		
		</tbody>
	  </table>
	</div>
  </div>
 <?php
	}
	/**
	* Filters posted data from your form. Do anything you need to the $settings['shipping']['plugin_name']
	*  array. Don't forget to return!
	*/
	function process_shipping_settings($settings) {
		return $settings;
	}

	/**
	* Echo any per-product shipping fields you need to add to the product edit screen shipping metabox
	*
	* @param array $shipping_meta, the contents of the post meta. Use to retrieve any previously saved product meta
	* @param array $settings, access saved settings via $settings array.
	*/
	function shipping_metabox($shipping_meta, $settings) {
	}

	/**
	* Save any per-product shipping fields from the shipping metabox using update_post_meta
	*
	* @param array $shipping_meta, save anything from the $_POST global
	* return array $shipping_meta
	*/
	function save_shipping_metabox($shipping_meta) {
		return $shipping_meta;
	}

	/**
	* Use this function to return your calculated price as an integer or float
	*
	* @param int $price, always 0. Modify this and return
	* @param float $total, cart total after any coupons and before tax
	* @param array $cart, the contents of the shopping cart for advanced calculations
	* @param string $address1
	* @param string $address2
	* @param string $city
	* @param string $state, state/province/region
	* @param string $zip, postal code
	* @param string $country, ISO 3166-1 alpha-2 country code
	* @param string $selected_option, if a calculated shipping module, passes the currently selected sub shipping option if set
	*
	* return float $price
	*/
	function calculate_shipping($price, $total, $cart, $address1, $address2, $city, $state, $zip, $country, $selected_option) {
		global $mp;


		if(! $this->crc_ok())
		{
			//Price added to this object
			$this->shipping_options($cart, $address1, $address2, $city, $state, $zip, $country);
		}

		$price = floatval($_SESSION['mp_shipping_info']['shipping_cost']);
		return $price;
	}

	/**
	* For calculated shipping modules, use this method to return an associative array of the sub-options. The key will be what's saved as selected
	*  in the session. Note the shipping parameters won't always be set. If they are, add the prices to the labels for each option.
	*
	* @param array $cart, the contents of the shopping cart for advanced calculations
	* @param string $address1
	* @param string $address2
	* @param string $city
	* @param string $state, state/province/region
	* @param string $zip, postal code
	* @param string $country, ISO 3166-1 alpha-2 country code
	*
	* return array $shipping_options
	*/
	function shipping_options($cart, $address1, $address2, $city, $state, $zip, $country) {

		$shipping_options = array();
		$this->address1 = $address1;
		$this->address2 = $address2;
		$this->city = $city;
		$this->state = $state;
		$this->destination_zip = $zip;
		$this->country = $country;

		if( is_array($cart) ) {
			$shipping_meta['weight'] = (is_numeric($shipping_meta['weight']) ) ? $shipping_meta['weight'] : 0;
			foreach ($cart as $product_id => $variations) {
				$shipping_meta = get_post_meta($product_id, 'mp_shipping', true);
				foreach($variations as $variation => $product) {
					$price = $product['price'];
					$qty = $product['quantity'];
					$_SESSION['qty'] = $qty;
					$_SESSION['product_price'] = $price*$qty;
					$weight = (empty($shipping_meta['weight']) ) ? $this->auspost_settings['default_weight'] : $shipping_meta['weight'];
					$this->weight += floatval($weight) * $qty;
				}
			}
		}
		// Got our totals  make sure we're in decimal pounds.
		$this->weight = $this->as_pounds($this->weight);

		//AUSPOST won't accept a zero weight Package
		$this->weight = ($this->weight == 0) ? 0.1 : $this->weight;

		$max_weight = floatval($this->AUSPOST[max_weight]);
		$max_weight = ($max_weight > 0) ? $max_weight : 75;

		//Properties should already be converted to weight in decimal pounds and Pounds and Ounces
		//Figure out how many boxes
		$this->pkg_count = ceil($this->weight / $max_weight); // Avoid zero
		// Equal size packages.
		$this->pkg_weight = $this->weight / $this->pkg_count;

		// Fixup pounds by converting multiples of 16 ounces to pounds
		$this->pounds = intval($this->pkg_weight);
		$this->ounces = round(($this->pkg_weight - $this->pounds) * 16);

		if($this->settings['base_country'] == 'AU') {
			// Can't use zip+4
			$this->settings['base_zip'] = substr($this->settings['base_zip'], 0, 5);
		}

		if($this->country == 'AU') {
			// Can't use zip+4
			$this->destination_zip = substr($this->destination_zip, 0, 5);
		}

		$shipping_options = $this->rate_request();

		return $shipping_options;

	}

	/**For uasort below
	*/
	function compare_rates($a, $b){
		if($a['rate'] == $b['rate']) return 0;
		return ($a['rate'] < $b['rate']) ? -1 : 1;
	}

	/**
	* rate_request - Makes the actual call to AUSPOST
	*/
	function rate_request() {
		global $mp;


		if($this->country == 'AU')
		{
	    	$shipping_options = $this->auspost_settings['services'];
		}
		else
		{
				$shipping_options = $this->auspost_settings['intl_services'];
		}

		//Assume equal size packages. Find the best matching box size
		$this->auspost_settings['max_weight'] = ( empty($this->auspost_settings['max_weight'])) ? 50 : $this->auspost_settings['max_weight'];
		$diff = floatval($this->auspost_settings['max_weight']);
		$found = -1;
		foreach($this->auspost_settings['boxes']['weight'] as $key => $weight) {
			if ($this->pkg_weight < $weight) {
				if(($weight - $this->pkg_weight) < $diff){
					$diff = $weight - $this->pkg_weight;
					$found = $key;
				}
			}
		}

		//found our box
		$dims = explode('x', strtolower($this->auspost_settings['boxes']['size'][$found]));
		//print_r($dims);
		
		foreach($dims as &$dim) $dim = $this->as_inches($dim);

		sort($dims); //Sort so two lowest values are used for Girth

		//Build Authorization XML
		$auth_dom = new DOMDocument('1.0', 'utf-8');
		$auth_dom->formatOutout = true;
		$root = $auth_dom->appendChild($auth_dom->createElement('AccessRequest'));
		$root->setAttribute('xml:lang', 'en-US');
		$root->appendChild($auth_dom->createElement('AccessLicenseNumber',$this->auspost_settings['api_key']));
		//Rate request XML
		$dom = new DOMDocument('1.0', 'utf-8');
		$dom->formatOutput = true;
		$root = $dom->appendChild($dom->createElement('RatingServiceSelectionRequest'));
		$root->setAttribute('xml:lang', 'en-US');

		$request = $root->appendChild($dom->createElement('Request'));

		$transaction = $request->appendChild($dom->createElement('TransactionReference'));
		$transaction->appendChild($dom->createElement('CustomerContext','MarketPress Rate Request'));
		$transaction->appendChild($dom->createElement('XpciVersion','1.0001'));

		$request->appendChild($dom->createElement('RequestAction', 'Rate'));
		$request->appendChild($dom->createElement('RequestOption', 'Shop'));

		$pickup = $root->appendChild($dom->createElement('PickupType'));
		$pickup->appendChild($dom->createElement('Code', '01'));

		//Shipper
		$shipment = $root->appendChild($dom->createElement('Shipment'));
		$shipper = $shipment->appendChild($dom->createElement('Shipper'));
		$shipper->appendChild($dom->createElement('ShipperNumber',$this->auspost_settings['shipper_number']));
		$address = $shipper->appendChild($dom->createElement('Address'));
		$address->appendChild($dom->createElement('StateProvinceCode', $this->settings['base_province']));
		$address->appendChild($dom->createElement('PostalCode', $this->settings['base_zip']));
		$address->appendChild($dom->createElement('CountryCode', $this->settings['base_country']));
		//Ship to
		$shipto = $shipment->appendChild($dom->createElement('ShipTo'));
		$address = $shipto->appendChild($dom->createElement('Address'));
		$address->appendChild($dom->createElement('AddressLine1', $this->address1));
		$address->appendChild($dom->createElement('AddressLine2', $this->address2));
		$address->appendChild($dom->createElement('City', $this->city));
		$address->appendChild($dom->createElement('StateProvinceCode', $this->state));
		$address->appendChild($dom->createElement('PostalCode', $this->destination_zip));
		$address->appendChild($dom->createElement('CountryCode', $this->country));
		//Ship from
		$shipfrom = $shipment->appendChild($dom->createElement('ShipFrom'));
		$address = $shipfrom->appendChild($dom->createElement('Address'));
		$address->appendChild($dom->createElement('StateProvinceCode', $this->settings['base_province']));
		$address->appendChild($dom->createElement('PostalCode', $this->settings['base_zip']));
		$address->appendChild($dom->createElement('CountryCode', $this->settings['base_country']));
		//Package
		$package = $shipment->appendChild($dom->createElement('Package'));
		$packaging_type = $package->appendChild($dom->createElement('PackagingType') );
		$packaging_type->appendChild($dom->createElement('Code', '02'));
		//Dimensions
		$dimensions = $package->appendChild($dom->createElement('Dimensions') );
		$uom = $dimensions->appendChild($dom->createElement('UnitOfMeasurement') );
		$uom->appendChild($dom->createElement('Code', 'IN'));
		$dimensions->appendChild($dom->createElement('Length', $dims[1]) );
		$dimensions->appendChild($dom->createElement('Width', $dims[2]) );
		$dimensions->appendChild($dom->createElement('Height', $dims[0]) );
		//Weight
		$package_weight = $package->appendChild($dom->createElement('PackageWeight') );
		$uom = $package_weight->appendChild($dom->createElement('UnitOfMeasurement') );
		$uom->appendChild($dom->createElement('Code', 'LBS'));
		$package_weight->appendChild($dom->createElement('Weight', $this->pkg_weight) );
		//We have the XML make the call
		$urlf = ($this->auspost_settings['sandbox']) ? $this->sandbox_uri : $this->production_uri;
		$response = wp_remote_request($url, array(
		'headers' => array('Auth-Key' => $this->auspost_settings['api_key'],'Content-Type: text/xml'),
		'method' => 'POST',
		'body' => $auth_dom->saveXML() . $dom->saveXML(),
		'sslverify' => false,
		)
		);
		
		if ($loaded){

			libxml_use_internal_errors(true);
			$dom = new DOMDocument();
			$dom->encoding = 'utf-8';
			$dom->formatOutput = true;
			$dom->loadHTML($body);
			libxml_clear_errors();
		}

		//Process the return XML
		//Clear any old price
		unset($_SESSION['mp_shipping_info']['shipping_cost']);

		$xpath = new DOMXPath($dom);
		
		//Check for errors
		$nodes = $xpath->query('//responsestatuscode');
		$nodes->item(0)->textContent;
		if($nodes->item(0)->textContent == '0'){
			$nodes = $xpath->query('//errordescription');
			$this->rate_error = $nodes->item(0)->textContent;
			return array('error' => '<div class="mp_checkout_error">' . $this->rate_error . '</div>');
		}

		//Good to go
		//Make SESSION copy with just prices and delivery

		if(! is_array($shipping_options)) $shipping_options = array();
		$mp_shipping_options = $shipping_options;
		
		
		$setvices_both = array();
		
		if($this->country == 'AU')
		{
		 $shipping_type =  'domestic';
		 $setvices_both = $this->services;
		}
		else 
		{
		  $shipping_type =  'international';
		  $setvices_both = $this->intl_services;
		}
		
		//extra fields for shipping
		$extracover ='';
		$suboption_code = '';
		$option_code = '';
		
		
		
		if(isset($_POST['extracover'])){		
		 $extracover = $this->auspost_settings['extra_cover'];
			if($this->country == 'AU'){
			$suboption_code = $_POST['extracover'];
			$option_code = 'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY'; //Signature on Delivery required
			}else{
			$suboption_code = 'INTL_SERVICE_OPTION_EXTRA_COVER';	
			}
		}
		
		if(isset($_POST['signature']))		
		$option_code = $_POST['signature']; //signature on delivery for domestic
		
		if(isset($_POST['deliveryConfirmation']))		
		$option_code = $_POST['deliveryConfirmation']; //Delivery confirmation for international
	
		if(! is_array($shipping_options)) $shipping_options = array();
		$mp_shipping_options = $shipping_options;
		$handling = floatval($this->auspost_settings['domestic_handling']) * $this->pkg_count; // Add handling times number of packages.
		
		
		$count_main = count($this->auspost_settings['country']['name_country']);
		unset($this->auspost_settings['country']['name_country'][$count_main-1]);
		$count = count($this->auspost_settings['country']['name_country']);

		foreach($shipping_options as $service => $option){
		$url = $urlf."postage/parcel/$shipping_type/calculate.json";	
		$data = array(
		'from_postcode' => $this->settings['base_zip'],
		'to_postcode' => $this->destination_zip,
		'country_code' => $this->country,
		'weight' => $this->pkg_weight,
		'height' => $dims[0],
		'width' => $dims[2],
		'length' => $dims[1],	
		'extra_cover' => $extracover,			
		'service_code' => $setvices_both[$service]->code,
		'option_code' => $option_code,
		'suboption_code' => $suboption_code
	);
		$first = true;
		foreach ($data as $key => $value)
		{
			$url .= $first ? '?' : '&';
			$url .= "{$key}={$value}";			
			$first = false; 	
		}	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		  'Auth-Key: ' . $this->auspost_settings['api_key']
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$contents = curl_exec ($ch);
		
		curl_close ($ch);
		$ret = json_decode($contents,true);
		$errorMessage = $ret['error']['errorMessage'];
		for( $i=0; $i<=$count; $i++)
		{
		if ($errorMessage!=''){
				return array('error' => '<div class="mp_checkout_error">AUSPOST: ' . $errorMessage . '</div>');
				die;
			}
		
		$delivery = $setvices_both[$service]->delivery;

			//COUNTRY WISE RATE MANAGES (FREE SHIPPING AND DEFAULT SHIPPING)//
			if($this->country == $this->auspost_settings['country']['name_country'][$i] && $this->auspost_settings['country']['shipp_method'][$i] == "free" && $this->auspost_settings['country']['rate'][$i]=="" && $this->auspost_settings['country']['quantity'][$i]=="" ){
				
				//echo "condition 1";
				
				$mp_shipping_options[$service] = array('rate' => 0, 'delivery' =>'Free Shipping Service - Auspost' , 'handling' => '');
				$_SESSION['mp_shipping_info']['shipping_cost'] = 0;
				break;
			} 
			elseif($this->country == $this->auspost_settings['country']['name_country'][$i] && $_SESSION['product_price'] >= $this->auspost_settings['country']['rate'][$i] && $this->auspost_settings['country']['quantity'][$i]=="" && ($this->auspost_settings['country']['shipp_method'][$i] == "free" || $this->auspost_settings['country']['shipp_method'][$i] == "free_total" || $this->auspost_settings['country']['shipp_method'][$i] == "free_quantity" ) ){
							//	echo "condition 2";

				$mp_shipping_options[$service] = array('rate' => 0, 'delivery' =>'Free Shipping Service - Auspost' , 'handling' => '');
				$_SESSION['mp_shipping_info']['shipping_cost'] = 0;
				break;
		}
		elseif($this->country == $this->auspost_settings['country']['name_country'][$i] && $_SESSION['qty']>=$this->auspost_settings['country']['quantity'][$i] && $this->auspost_settings['country']['rate'][$i]=="" && ($this->auspost_settings['country']['shipp_method'][$i] == "free" || $this->auspost_settings['country']['shipp_method'][$i] == "free_total" || $this->auspost_settings['country']['shipp_method'][$i] == "free_quantity" ))
			{
								//echo "condition 3";
				$mp_shipping_options[$service] = array('rate' => 0, 'delivery' =>'Free Shipping Service - Auspost' , 'handling' => '');
				$_SESSION['mp_shipping_info']['shipping_cost'] = 0;
				break;
			}
			else
			{
				if($this->auspost_settings['country']['name_country'][$i]==$this->country && ($this->auspost_settings['country']['shipp_method'][$i]=="+" || $this->auspost_settings['country']['shipp_method'][$i]=="-"))
					{
					$current_rate = $ret['postage_result']['total_cost'];
					$percentage_rate = $current_rate*($this->auspost_settings['country']['percentage_price'][$i]/100);
					$newrate = $current_rate+($this->auspost_settings['country']['shipp_method'][$i].$percentage_rate);
						//match it up if there is already a selection
					$mp_shipping_options[$service] = array('rate' => $newrate, 'delivery' => $delivery, 'handling' => $handling);
							if ($_SESSION['mp_shipping_info']['shipping_sub_option'] == $service){
								$_SESSION['mp_shipping_info']['shipping_cost'] =  $newrate + $handling;
							}
							break;
					}
				elseif($this->auspost_settings['country']['name_country'][$i]==$this->country && $this->auspost_settings['country']['shipp_method'][$i]=="(+)")
					{
						$current_rate = $ret['postage_result']['total_cost'];

						$newrate_add = $current_rate+$this->auspost_settings['country']['percentage_price'][$i];
						$mp_shipping_options[$service] = array('rate' => $newrate_add, 'delivery' => $delivery, 'handling' => $handling);
							if ($_SESSION['mp_shipping_info']['shipping_sub_option'] == $service){
								$_SESSION['mp_shipping_info']['shipping_cost'] =  $newrate_add + $handling;
							}
							
						break;	
							
					}
				elseif($this->auspost_settings['country']['name_country'][$i]==$this->country && $this->auspost_settings['country']['shipp_method'][$i]=="(-)")
				{

					$current_rate = $ret['postage_result']['total_cost'];

					$newrate_sub = $current_rate-$this->auspost_settings['country']['percentage_price'][$i];
					$mp_shipping_options[$service] = array('rate' => $newrate_sub, 'delivery' => $delivery, 'handling' => $handling);
						if ($_SESSION['mp_shipping_info']['shipping_sub_option'] == $service){
							$_SESSION['mp_shipping_info']['shipping_cost'] =  $newrate_sub + $handling;
						}
						
					break;	
				
				 }	
				 else 
				   {
				 
 			    		$newrate = $ret['postage_result']['total_cost'];
						$mp_shipping_options[$service] = array('rate' => $newrate, 'delivery' => $delivery, 'handling' => $handling);
						if ($_SESSION['mp_shipping_info']['shipping_sub_option'] == $service){
						$_SESSION['mp_shipping_info']['shipping_cost'] =  $newrate + $handling;
									}
				    }
			 	}
			}
		}
 
			uasort($mp_shipping_options, array($this,'compare_rates') );

			$shipping_options = array();
				foreach($mp_shipping_options as $service => $options){
					if($options['rate']	==0){
					$shipping_options[$service] = $this->format_shipping_option("Free Shipping - Auspost ", $options['rate'], $options['delivery'], $options['handling']);
					break;
					}
					else {
					$shipping_options[$service] = $this->format_shipping_option($service, $options['rate'], $options['delivery'], $options['handling']);
					}
				}
			//Update the session. Save the currently calculated CRCs
			$_SESSION['mp_shipping_options'] = $mp_shipping_options;

				$_SESSION['mp_cart_crc'] = $this->crc($mp->get_cart_cookie());

			$_SESSION['mp_shipping_crc'] = $this->crc($_SESSION['mp_shipping_info']);
			
			unset($xpath);
			unset($dom);
		return $shipping_options;
	}

	/**Used to detect changes in shopping cart between calculations
	* @param (mixed) $item to calculate CRC of
	*
	* @return CRC32 of the serialized item
	*/
	public function crc($item = ''){
		return crc32(serialize($item));
	}

	/**
	* Tests the $_SESSION cart cookie and mp_shipping_info to see if the data changed since last calculated
	* Returns true if the either the crc for cart or shipping info has changed
	*
	* @return boolean true | false
	*/
	private function crc_ok(){
		global $mp;

		//Assume it changed
		$result = false;

		//Check the shipping options to see if we already have a valid shipping price
		if(isset($_SESSION['mp_shipping_options'])){
			//We have a set of prices. Are they still valid?
			//Did the cart change since last calculation
			if ( is_numeric($_SESSION['mp_shipping_info']['shipping_cost'])){

				if($_SESSION['mp_cart_crc'] == $this->crc($mp->get_cart_cookie())){
					//Did the shipping info change
					if($_SESSION['mp_shipping_crc'] == $this->crc($_SESSION['mp_shipping_info'])){
						$result = true;
					}
				}
			}
		}
		return $result;
	}

	// Conversion Helpers

	/**
	* Formats a choice for the Shipping options dropdown
	* @param array $shipping_option, a $this->services key
	* @param float $price, the price to display
	*
	* @return string, Formatted string with shipping method name delivery time and price
	*
	*/
	private function format_shipping_option($shipping_option = '', $price = '', $delivery = '', $handling=''){
		global $mp;
		if ( isset($this->services[$shipping_option])){
			$option = $this->services[$shipping_option]->name;
		}

		$price = is_numeric($price) ? $price : 0;
		$handling = is_numeric($handling) ? $handling : 0;

		$option .=  sprintf(__(' %1$s - %2$s', 'mp'), $delivery, $mp->format_currency('', $price + $handling) );
		return $option;
	}

	/**
	* Returns an inch measurement depending on the current setting of [shipping] [system]
	* @param float $units
	*
	* @return float, Converted to the current units_used
	*/
	private function as_inches($units){
		$units = ($this->settings['shipping']['system'] == 'metric') ? floatval($units) / 2.54 : floatval($units);
		return round($units,2);
	}

	/**
	* Returns a pounds measurement depending on the current setting of [shipping] [system]
	* @param float $units
	*
	* @return float, Converted to pounds
	*/
	private function as_pounds($units){
		$units = ($this->settings['shipping']['system'] == 'metric') ? floatval($units) * 2.2 : floatval($units);
		return round($units, 2);
	}

	/**
	* Returns a the string describing the units of weight for the [mp_shipping][system] in effect
	*
	* @return string
	*/
	private function get_units_weight(){
		return ($this->settings['shipping']['system'] == 'english') ? __('Pounds','mp') : __('Kilograms', 'mp');
	}

	/**
	* Returns a the string describing the units of length for the [mp_shipping][system] in effect
	*
	* @return string
	*/
	private function get_units_length(){
		return ($this->settings['shipping']['system'] == 'english') ? __('Inches','mp') : __('Centimeters', 'mp');
	}

} //End MP_Shipping_AUS
 
else:
// checking auspost api``
  class Shipping
	{		
		private $api = 'https://auspost.com.au/api/';
	    const MAX_HEIGHT = 35; //only applies if same as width
		const MAX_WIDTH = 35; //only applies if same as height
		const MAX_WEIGHT = 20; //kgs
		const MAX_LENGTH = 105; //cms
		const MAX_GIRTH = 140; //cms
		const MIN_GIRTH = 16; //cms
 
    public function getRemoteData($url,$key)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		  'Auth-Key: ' . $key
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$contents = curl_exec ($ch);
		curl_close ($ch);
		return json_decode($contents,true);
	}
 
    public function getShippingCost($data,$key)
	{
		$edeliver_url = "{$this->api}postage/parcel/domestic/calculate.json";
		$edeliver_url = $this->arrayToUrl($edeliver_url,$data);		
		$results = $this->getRemoteData($edeliver_url,$key);
 
		if (isset($results['error']))
			throw new Exception($results['error']['errorMessage']);
 
		return $results['postage_result']['total_cost'];
	}
 
        public function arrayToUrl($url,$array)
		{
		$first = true;
			foreach ($array as $key => $value)
			{
				$url .= $first ? '?' : '&';
				$url .= "{$key}={$value}";
				$first = false; 	
			}	
			return $url;
		}
 
	     public function getGirth($height,$width)
		{
			return ($width+$height)*2;
		}
	}
		$shipping = new Shipping();
        $data = array(
		'from_postcode' => 4511,
		'to_postcode' => 4030,
		'weight' => 10,
		'height' => 105,
		'width' => 10,
		'length' => 10,
		'service_code' => 'AUS_PARCEL_REGULAR'
	);
        try{
			$shipping->getShippingCost($data,$_REQUEST['key']);
		    echo "Api";
		}
        catch (Exception $e)
        {
                 echo "oops: ".$e->getMessage();
        }

die;
endif;

 


if(! class_exists('AUSPOST_Service') ):
class AUSPOST_Service
{
	public $code;
	public $name;
	public $delivery;
	public $rate;

	function __construct($code, $name, $delivery, $rate = null)
	{
		$this->code = $code;
		$this->name = $name;
		$this->delivery = $delivery;
		$this->rate = $rate;
	}
}
endif;

if(! class_exists('Box_Size') ):
class Box_Size
{
	public $length;
	public $width;
	public $height;

	function __construct($length, $width, $height)
	{
		$this->length = $length;
		$this->width = $width;
		$this->height = $height;
	}
}
endif;


//register plugin only in US and US Possesions

$settings = get_option('mp_settings');

//if(in_array($settings['base_country'], array('US','UM','AS','FM','GU','MH','MP','PW','PR','PI')))
if(in_array($settings['base_country'], array('AU')))
{
    mp_register_shipping_plugin('MP_Shipping_AUS', 'ausship', __('AUSPOST', 'mp'), true);
	
	$table = "mp_domestic_label"; 
		$query_create = "CREATE TABLE IF NOT EXISTS " .$table. " (
			  dom_id int(9) NOT NULL AUTO_INCREMENT,
			  domestic_1 VARCHAR(255) NOT NULL,
			  domestic_2 VARCHAR(255) NOT NULL,
			  domestic_3 VARCHAR(255) NOT NULL,
			  domestic_4 VARCHAR(255) NOT NULL,
			  active tinyint(1) NOT NULL DEFAULT  '0',
			  PRIMARY KEY (`dom_id`),
	          UNIQUE (`dom_id`)
			)";
		
		$table2 = "mp_int_label"; 
			 $query_create2 = "CREATE TABLE IF NOT EXISTS " .$table2. " (
			  int_id int(9) NOT NULL AUTO_INCREMENT,
			  int_1 VARCHAR(255) NOT NULL,
			  int_2 VARCHAR(255) NOT NULL,
			  PRIMARY KEY (`int_id`),
	          UNIQUE (`int_id`)
			)";
		
		require_once(ABSPATH.'wp-admin/includes/upgrade.php');
		
		dbDelta($query_create);
		
		dbDelta($query_create2);

}
			if(isset($_REQUEST['submit_settings']))
			{
				global $wpdb;
				
				$domestic_1 = $_REQUEST['domestic_label1'];
					$domestic_2 = $_REQUEST['domestic_label2'];
						$domestic_3 = $_REQUEST['domestic_label3'];
							$domestic_4 = $_REQUEST['domestic_label4'];
								$mandatory_signature = $_REQUEST['mandatory_signature'];
				
				$select_data_domestic = $wpdb->get_results("SELECT * FROM mp_domestic_label");

					if($select_data_domestic[0]->dom_id!="")
					{
						$wpdb->query("UPDATE mp_domestic_label SET domestic_1 = '".$domestic_1."',domestic_2='".$domestic_2."',domestic_3 ='".$domestic_3."',domestic_4 = '".$domestic_4."', active = '".$mandatory_signature."'");
					}
					else
					{	
						$wpdb->query("INSERT INTO mp_domestic_label (domestic_1,domestic_2,domestic_3,domestic_4,active) VALUES ('".$domestic_1."','".$domestic_2."','".$domestic_3."','".$domestic_4."','".$mandatory_signature."')");
					}
				
				
				$int_1 = $_REQUEST['int_1'];
				$int_2 = $_REQUEST['int_2'];
				
				$select_data_domestic = $wpdb->get_results("SELECT * FROM mp_int_label");

					if($select_data_domestic[0]->int_id!="")
					{
						$wpdb->query("UPDATE mp_int_label SET int_1 = '".$int_1."', int_2='".$int_2."'");
					}
					else
					{	
						$wpdb->query("INSERT INTO mp_int_label (int_1,int_2) VALUES ('".$int_1."','".$int_2."')");
					}
				
			}
?>
