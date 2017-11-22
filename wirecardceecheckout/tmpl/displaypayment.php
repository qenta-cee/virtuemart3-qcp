<?php
/**
 * Shop System Plugins - Terms of Use
 *
 * The plugins offered are provided free of charge by Wirecard Central Eastern Europe GmbH
 * (abbreviated to Wirecard CEE) and are explicitly not part of the Wirecard CEE range of
 * products and services.
 *
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License Version 2 (GPLv2) and can be used, developed and passed on to third parties under
 * the same terms.
 *
 * However, Wirecard CEE does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 *
 * Customers use the plugins at their own risk. Wirecard CEE does not guarantee their full
 * functionality neither does Wirecard CEE assume liability for any disadvantages related to
 * the use of the plugins. Additionally, Wirecard CEE does not guarantee the full functionality
 * for customized shop systems or installed plugins of other vendors of plugins within the same
 * shop system.
 *
 * Customers are responsible for testing the plugin's functionality before starting productive
 * operation.
 *
 * By installing the plugin into the shop system the customer agrees to these terms of use.
 * Please do not use the plugin if you do not agree to these terms of use!
 */

defined( '_JEXEC' ) or die();
?>
<input type="radio" name="virtuemart_paymentmethod_id" id="payment_id_<?php echo $viewData['paymentmethod_id'] ?>"
       value="<?php echo $viewData['paymentmethod_id'] ?>" style="display:none;">
<?php echo $viewData['ratepay_script'] ?>
</div>
<?php
$url = JURI::root() . 'images/stories/virtuemart/' . $this->_psType . '/';

foreach ( $viewData['paymenttypes'] as $pt ) {
	?>
    <div class="vm-payment-plugin-single">
        <input class="wirecard_paymenttype" id="wirecard_<?php echo strtolower( $pt['value'] ) ?>" type="radio"
               name="wirecard_paymenttype"
               value="<?php echo strtolower( $pt['value'] ) ?>" <?php if ( $viewData['paymenttype_selected'] == strtolower( $pt['value'] ) )
			echo ' checked="checked"' ?> />

        <label for="wirecard_<?php echo strtolower( $pt['value'] ) ?>">
                <span class="vmpayment">
                    <?php if ( isset( $pt['image'] ) ) { ?>
                        <span class="vmCartPaymentLogo">
                            <img align="middle" src="<?php echo $url . $pt['image'] ?>.png"
                                 alt="<?php echo $pt['title'] ?>">
                        </span>
                    <?php } ?>
                    <span class="vmpayment_name"><?php echo $pt['title'] ?></span>

                </span>
        </label>
    </div>
	<?php if ( isset( $pt['birthday_header'] ) && $viewData['paymenttype_selected'] == strtolower( $pt['value'] ) ) { ?>
        <div style="margin-left:23px;" class="additional-information">
            <b><?php echo $pt['birthday_header']; ?></b><br/>
			<?php
			$birthday = '<select name="wcp_day" id="wcp_day_'.strtolower($pt['value']).'" style="width:auto;">';
			for ( $day = 31; $day > 0; $day -- ) {
				$selected = '';
				if ($viewData['birth_day'] == $day){
					$selected = 'selected';
				}
				$birthday .= '<option value="'.$day.'" '.$selected.'> '.$day.' </option>';
			}

			$birthday .= '</select>';

			$birthday .= '<select name="wcp_month" id="wcp_month_'.strtolower($pt['value']).'" style="width:auto;">';
			for ( $month = 12; $month > 0; $month -- ) {
				$selected = '';
				if ($viewData['birth_month'] == $month){
					$selected = 'selected';
				}
				$birthday .= '<option value="'.$month.'" '.$selected.'> '.$month.' </option>';
			}
			$birthday .= '</select>';

			$birthday .= '<select name="wcp_year" id="wcp_year_'.strtolower($pt['value']).'" style="width:auto;">';
			for ( $year = date( "Y" ); $year > 1900; $year -- ) {
				$selected = '';
				if ($viewData['birth_year'] == $year){
					$selected = 'selected';
				}
				$birthday .= '<option value="'.$year.'" '.$selected.'> '.$year.' </option>';
			}
			$birthday .= '</select>';
			echo $birthday;
			?>
        </div>
	<?php } ?>

	<?php if ( isset( $pt['additional_header'] ) && $viewData['paymenttype_selected'] == strtolower( $pt['value'] ) ) { ?>
        <div style="margin-left:23px;" class="additional-information">
            <b><?php echo $pt['additional_header']; ?></b><br/>
            <label>
                <input type="checkbox" id="consent_<?php echo strtolower( $pt['value'] ); ?>" class="required"
                       name="consent_<?php echo strtolower( $pt['value'] ); ?>"<?php echo $pt['consent_checked']; ?>>
				<?php echo $pt['consent_text']; ?>
            </label>
        </div>
	<?php } ?>
	<?php if ( isset( $pt['financial_inst'] ) && $viewData['paymenttype_selected'] == strtolower( $pt['value'] ) ) { ?>
        <div style="margin-left:23px;" class="additional-information">
            <b><?php echo JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_FINANCIAL_INST_HEADER'); ?></b><br/>
            <select name="financialInstitution" id="financialInstitutions">
				<?php foreach($pt['financial_inst'] as $key => $value) { ?>
                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
				<?php } ?>
            </select>
        </div>
	<?php } ?>
<?php } ?>
<div>
    <script type="text/javascript">
        jQuery('#checkoutForm').submit(function (event) {
            jQuery('.wirecard_paymenttype').each(function () {
                if (jQuery(this).prop('checked')) {
                    if (jQuery('#wcp_day_' + this.value).val()) {
                        var day = jQuery('#wcp_day_' + this.value).val();
                        var month = jQuery('#wcp_month_' + this.value).val();
                        var year = jQuery('#wcp_year_' + this.value).val();
                        var dateStr = year + '-' + month + '-' + day;
                        var minAge = 18;

                        var birthdate = new Date(dateStr);
                        var year = birthdate.getFullYear();
                        var today = new Date();
                        var limit = new Date((today.getFullYear() - minAge), today.getMonth(), today.getDate());

                        if (birthdate > limit) {
                            jQuery('.vmLoadingDiv').remove();
                            jQuery('#checkoutFormSubmit').prop("disabled", false);
                            jQuery('#checkoutFormSubmit').addClass("vm-button-correct");
                            event.preventDefault();
                            alert("<?php echo JText::_( 'VMPAYMENT_WIRECARDCEECHECKOUT_BIRTHDAY_ERROR' ); ?>");
                            return;
                        }
                    }
                    var checkbox = null;
                    if (jQuery('#consent_' + this.value).length) {
                        checkbox = jQuery('#consent_' + this.value);
                    }

                    if (checkbox != null) {
                        if (!checkbox.prop('checked') ) {
                            jQuery('.vmLoadingDiv').remove();
                            jQuery('#checkoutFormSubmit').prop("disabled", false);
                            jQuery('#checkoutFormSubmit').addClass("vm-button-correct");
                            event.preventDefault();
                            alert("<?php echo JText::_( 'VMPAYMENT_WIRECARDCEECHECKOUT_PAYOLUTION_CONSENT_ACCEPT' ); ?>");
                            return;
                        }
                    }
                }
            });
        });

        jQuery('.wirecard_paymenttype').each(function () {
            jQuery(this).change(function (evt) {
                jQuery('#payment_id_<?php echo $viewData['paymentmethod_id'] ?>').prop('checked', true);
                jQuery.ajax({
                    type: "POST",
                    dataType: "json",
                    url: "<?php echo JURI::root() ?>index.php?option=com_virtuemart&view=plugin&type=vmpayment&nosef=1&name=wirecardceecheckout&loadJS=1&action=changePaymentTypeAjax&paymenttype=" + (this).value
                });
            });
        });
        jQuery('input[name=virtuemart_paymentmethod_id]').change(function (evt) {
            jQuery('.additional-information').remove();
        });
    </script>
</div>

