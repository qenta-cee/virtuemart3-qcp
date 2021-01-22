<?php
/**
 * Shop System Plugins
 * - Terms of use can be found under
 * https://guides.qenta.com/shop_plugins:info
 * - License can be found under:
 * https://github.com/qenta-cee/virtuemart3-qcp/blob/master/LICENSE
*/

defined('_JEXEC') or die();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
    <style type="text/css">
        body {
            font-family: arial, helvetica, sans-serif;
        }

        h3 {
            color: #555555;
            font-size: 1.1em;
            font-weight: bold;
            margin: 20px 0 10px;
        }
    </style>
</head>
<body>
<h3><?php JText::printf('VMPAYMENT_QENTACEECHECKOUT_IFRAMEBREAKOUT_REDIRECT') ?></h3>

<p><?php JText::printf('VMPAYMENT_QENTACEECHECKOUT_IFRAMEBREAKOUT_CLICK') ?></p>

<form method="POST" name="redirectForm" action="<?php echo $viewData['returnUrl']; ?>" target="_parent">
    <?php
    foreach ($_POST as $k => $v) {
        printf('<input type="hidden" name="%s" value="%s" />', htmlspecialchars($k), htmlspecialchars($v));
    }
    ?>
</form>
<script type="text/javascript">
    // <![CDATA[
    function iframeBreakout() {
        document.redirectForm.submit();
    }

    iframeBreakout();
    //]]>
</script>
</body>
</html>
