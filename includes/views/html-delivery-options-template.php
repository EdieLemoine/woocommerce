<div class="woocommerce-myparcel__delivery-options">
    <?php
    // Add custom css to the delivery options, if any
    if (!empty(WCMP()->setting_collection->getByName(WCMP_Settings::SETTING_DELIVERY_OPTIONS_CUSTOM_CSS))) {
        echo "<style>";
        echo WCMP()->setting_collection->getByName(WCMP_Settings::SETTING_DELIVERY_OPTIONS_CUSTOM_CSS);
        echo "</style>";
    }
    ?>
  <div id="myparcel-delivery-options"></div>
</div>
