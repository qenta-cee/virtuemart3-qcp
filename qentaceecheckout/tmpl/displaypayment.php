<?php
/**
 * Shop System Plugins
 * - Terms of use can be found under
 * https://guides.qenta.com/shop_plugins:info
 * - License can be found under:
 * https://github.com/qenta-cee/virtuemart3-qcp/blob/master/LICENSE
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
        <input class="qenta_paymenttype" id="qenta_<?php echo strtolower( $pt['value'] ) ?>" type="radio"
               name="qenta_paymenttype"
               value="<?php echo strtolower( $pt['value'] ) ?>" <?php if ( $viewData['paymenttype_selected'] == strtolower( $pt['value'] ) )
            echo ' checked="checked"' ?> onclick="selectQentaPayment(this)" />

        <label for="qenta_<?php echo strtolower( $pt['value'] ) ?>">
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
    <?php if ( isset( $pt['birthday_header'] ) ) { ?>
        <div style="margin-left:23px; display: <?= ($viewData['paymenttype_selected'] !== strtolower( $pt['value'] )) ? 'none' : 'block';?>;" class="additional-information">
            <b><?php echo $pt['birthday_header']; ?></b><br/>
            <?php
            $birthday = '<select name="qcp_day" id="qcp_day_'.strtolower($pt['value']).'" style="width:auto;">';
            for ( $day = 31; $day > 0; $day -- ) {
                $selected = '';
                if ($viewData['birth_day'] == $day){
                    $selected = 'selected';
                }
                $birthday .= '<option value="'.$day.'" '.$selected.'> '.$day.' </option>';
            }

            $birthday .= '</select>';

            $birthday .= '<select name="qcp_month" id="qcp_month_'.strtolower($pt['value']).'" style="width:auto;">';
            for ( $month = 12; $month > 0; $month -- ) {
                $selected = '';
                if ($viewData['birth_month'] == $month){
                    $selected = 'selected';
                }
                $birthday .= '<option value="'.$month.'" '.$selected.'> '.$month.' </option>';
            }
            $birthday .= '</select>';

            $birthday .= '<select name="qcp_year" id="qcp_year_'.strtolower($pt['value']).'" style="width:auto;">';
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

    <?php if ( isset( $pt['additional_header'] ) ) { ?>
        <div style="margin-left:23px; display: <?= ($viewData['paymenttype_selected'] !== strtolower( $pt['value'] )) ? 'none' : 'block';?>;" class="additional-information">
            <b><?php echo $pt['additional_header']; ?></b><br/>
            <label>
                <input type="checkbox" id="consent_<?php echo strtolower( $pt['value'] ); ?>" class="required"
                       name="consent_<?php echo strtolower( $pt['value'] ); ?>"<?php echo $pt['consent_checked']; ?>>
                <?php echo $pt['consent_text']; ?>
            </label>
        </div>
    <?php } ?>
    <?php if ( isset( $pt['financial_inst'] ) ) { ?>
        <div style="margin-left:23px; display: <?= ($viewData['paymenttype_selected'] !== strtolower( $pt['value'] )) ? 'none' : 'block';?>;" class="additional-information">
            <b><?php echo JText::_('VMPAYMENT_QENTACEECHECKOUT_FINANCIAL_INST_HEADER'); ?></b><br/>
            <select name="financialInstitution_<?= strtolower( $pt['value'] ); ?>" id="financialInstitutions_<?= strtolower( $pt['value'] ); ?>">
                <?php foreach($pt['financial_inst'] as $key => $value) { ?>
                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                <?php } ?>
            </select>
        </div>
    <?php } ?>
<?php } ?>
<div>
    <script type="text/javascript">
        function selectQentaPayment(el){
            var data = getData(jQuery('.additional-information:visible'));
            jQuery('.additional-information').hide();
            jQuery(el).closest(".vm-payment-plugin-single").next(".additional-information").show();
            jQuery.ajax({
                type: "POST",
                async: false,
                dataType: "json",
                data: {"qcp_additional" : data, "qenta_paymenttype": jQuery(el).val(), "pid": <?php echo $viewData['paymentmethod_id'] ?>},
                url: "<?php echo JURI::root() ?>index.php?option=com_virtuemart&view=plugin&type=vmpayment&nosef=1&name=qentaceecheckout&loadJS=1&action=changePaymentTypeAjax"
            });

        }

        function getData(selector) {
            var data = {};
            jQuery('input, select', selector).each(function(){
                var input = jQuery(this);
                data[input.attr('name')] = input.val()
            });
            return data;
        }
        function checkBirthday(selector, event) {
            if (jQuery('#qcp_day_' + selector).val()) {
                var day = jQuery('#qcp_day_' + selector).val();
                var month = jQuery('#qcp_month_' + selector).val();
                var year = jQuery('#qcp_year_' + selector).val();
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
                    alert("<?php echo JText::_( 'VMPAYMENT_QENTACEECHECKOUT_BIRTHDAY_ERROR' ); ?>");
                    return false;
                }
            }
            return true;
        }
        function checkPayolutionConsent(selector, event) {
            var checkbox = null;
            if (jQuery('#consent_' + selector).length) {
                checkbox = jQuery('#consent_' + selector);
            }

            if (checkbox != null) {
                if (!checkbox.prop('checked') ) {
                    jQuery('.vmLoadingDiv').remove();
                    jQuery('#checkoutFormSubmit').prop("disabled", false);
                    jQuery('#checkoutFormSubmit').addClass("vm-button-correct");
                    event.preventDefault();
                    alert("<?php echo JText::_( 'VMPAYMENT_QENTACEECHECKOUT_PAYOLUTION_CONSENT_ACCEPT' ); ?>");
                    return false;
                }
            }
            return true;
        }

        jQuery("#checkoutForm").submit(function (event) {
            jQuery('.qenta_paymenttype').each(function () {
                if (jQuery(this).prop('checked')) {
                    if (!checkBirthday(this.value, event) || !checkPayolutionConsent(this.value, event)) {
                        event.preventDefault();
                    }
                }
            });
        });
    </script>
</div>

