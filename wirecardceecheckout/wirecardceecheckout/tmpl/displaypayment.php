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

defined('_JEXEC') or die();
?>

<div style="margin-left: 20px; width: 100%;">

    <?php
    $url = JURI::root() . 'images/stories/virtuemart/' . $this->_psType . '/';

    foreach ($viewData['paymenttypes'] as $pt) {
        ?>
        <div style="margin: 6px; white-space: nowrap;">
            <input class="wirecard_paymenttype" id="wirecard_<?php echo strtolower($pt['value']) ?>" type="radio"
                   name="wirecard_paymenttype"
                   value="<?php echo strtolower($pt['value']) ?>" <?php if ($viewData['paymenttype_selected'] == strtolower($pt['value'])) echo ' checked="checked"' ?> />

            <label for="wirecard_<?php echo strtolower($pt['value'])?>">
                <span class="vmpayment">
                    <?php if (isset($pt['image'])) { ?>
                        <span class="vmCartPaymentLogo">
                            <img align="middle" src="<?php echo $url . $pt['image'] ?>.png"
                                 alt="<?php echo $pt['title'] ?>">
                        </span>
                    <?php } ?>
                    <span class="vmpayment_name"><?php echo $pt['title'] ?></span>

                </span>
            </label>
        </div>
        <?php if (isset($pt['consent_text'])) { ?>
            <div style="margin: 0 6px 6px 6px;">
                <label>
                    <input type="checkbox" id="consent_<?php echo strtolower($pt['value']); ?>"
                           name="consent_<?php echo strtolower($pt['value']); ?>"<?php echo $pt['consent_checked']; ?>>
                    <?php echo $pt['consent_text']; ?>
                </label>
            </div>
        <?php } ?>
    <?php } ?>

</div>

<script type="text/javascript">
    jQuery('#checkoutForm').submit(function (event) {
        jQuery('.wirecard_paymenttype').each(function () {
            if (this.checked) {
                var checkbox = null;
                if (jQuery('#consent_' + this.value).length) {
                    checkbox = jQuery('#consent_' + this.value);
                }

                if (checkbox != null) {
                    if (!checkbox.prop('checked')) {
                        jQuery('.vmLoadingDiv').remove();
                        jQuery('#checkoutFormSubmit').prop("disabled", false);
                        jQuery('#checkoutFormSubmit').addClass("vm-button-correct");
                        event.preventDefault();
                        alert("<?php echo JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYOLUTION_CONSENT_ACCEPT'); ?>");
                    }
                }
            }
        });
    });

    jQuery('.wirecard_paymenttype').each(function () {
        jQuery(this).change(function (evt) {
            jQuery('#payment_id_<?php echo $viewData['paymentmethod_id'] ?>').prop('checked', true);
        });
    });
</script>
