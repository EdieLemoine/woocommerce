<?php

/**
 * @var int      $order_id
 * @var WC_Order $order
 */

use WPO\WC\MyParcelBE\Compatibility\Order as WCX_Order;
use WPO\WC\MyParcelBE\Entity\SettingsFieldArguments;

if (! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

try {
    $deliveryOptions = WCMP_Admin::getDeliveryOptionsFromOrder($order);
} catch (Exception $e) {
    exit();
}

// todo fix shipment extra options?
$extraOptions = WCX_Order::get_meta($order, WCMP_Admin::META_SHIPMENT_OPTIONS_EXTRA);

echo '<div class="wcmp wcmp__change-order">';

if ($deliveryOptions->isPickup()) {
    $pickup = $deliveryOptions->getPickupLocation();

    printf(
        "<div class=\"pickup-location\"><strong>%s:</strong><br /> %s<br />%s %s<br />%s %s</div>",
        _wcmp("Pickup location"),
        $pickup->getLocationName(),
        $pickup->getStreet(),
        $pickup->getNumber(),
        $pickup->getPostalCode(),
        $pickup->getCity()
    );

    echo "<hr>";
}

$isPackageTypeDisabled = count(WCMP_Data::getPackageTypes()) === 1 || $deliveryOptions->isPickup();

$option_rows = [
    [
        "name"              => "[carrier]",
        "label"             => _wcmp("Carrier"),
        "type"              => "select",
        "options"           => WCMP_Data::getCarriers(),
        "custom_attributes" => [
            "disabled" => "disabled",
        ],
        "value"             => $deliveryOptions->getCarrier(),
    ],
    [
        "name"              => "[$order_id][package_type]",
        "label"             => _wcmp("Shipment type"),
        "description"       => sprintf(
            _wcmp("Calculated weight: %s"),
            wc_format_weight($order->get_meta(WCMP_Admin::META_ORDER_WEIGHT))
        ),
        "type"              => "select",
        "options"           => WCMP_Data::getPackageTypes(),
        "value"             => $deliveryOptions->getDeliveryType(),
        "custom_attributes" => [
            "disabled" => $isPackageTypeDisabled ? "disabled" : null,
        ],
    ],
    [
        "name"              => "[$order_id][extra_options][colli_amount]",
        "label"             => _wcmp("Number of labels"),
        "type"              => "number",
        "value"             => isset($extraOptions['colli_amount']) ? $extraOptions['colli_amount'] : 1,
        "custom_attributes" => [
            "step" => "1",
            "min"  => "1",
            "max"  => "10",
        ],
    ],
    [
        "name"              => "[signature]",
        "type"              => "toggle",
        "label"             => _wcmp("Signature on delivery"),
        "value"             => isset($shipment_options["signature"]) ? $shipment_options["signature"] : 0,
        "custom_attributes" => [
            "disabled" => isset($option_row['disabled']) ? "disabled" : null,
        ],
    ],
    [
        "name"              => "[insured]",
        "type"              => "toggle",
        "label"             => _wcmp("Insured to &euro; 500"),
        "value"             => WCMP()->setting_collection->getByName("insured") ? 1 : 0,
        "custom_attributes" => [
            "disabled" => isset($option_row['disabled']) ? "disabled" : null,
        ],
    ],
];

if (isset($recipient) && isset($recipient['cc']) && $recipient['cc'] !== 'BE') {
    unset($option_rows['[signature]']);
}

foreach ($option_rows as $option_row) {
    $class = new SettingsFieldArguments($option_row);

    woocommerce_form_field(
        "myparcelbe_options" . $class->name,
        $class->getArguments(false),
        $option_row["value"] ?? null
    );
}

echo '<div class="wcmp_save_shipment_settings">';

submit_button(
    null,
    null,
    null,
    null,
    [
        "class"      => "button save",
        "data-order" => $order_id,
    ]
);

$this->renderSpinner();

echo '</div></div>';
