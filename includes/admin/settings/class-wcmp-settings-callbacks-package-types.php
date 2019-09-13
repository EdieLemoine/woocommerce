<?php

use WPO\WC\MyParcelBE\Entity\SettingsFieldArguments;

if (! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (class_exists('WCMP_Settings_Callbacks_Package_Types')) {
    return;
}

class WCMP_Settings_Callbacks_Package_Types
{
    public function __construct($args)
    {
        $this->createPackageTypeSearchBox($args);
    }

    /**
     * @param $args
     */
    public function createPackageTypesSelect($args)
    {
        foreach ($args["package_types"] as $package_type => $package_type_title) {
            printf('<div class="package_type_title">%s:<div>', $package_type_title);
            $args["package_type"] = $package_type;
            unset($args["description"]);
            $this->createPackageTypeSearchBox($args);
        }
    }

    // Shipping method search callback.
    public function createPackageTypeSearchBox($args)
    {
        $option = get_option($args["name"]);

        if (isset($option[$args['id']])) {
            $current = $option[$args['id']];
        }

        if (isset($args["package_type"])) {
            $setting_name = "{$args["name"]}[{$args["package_type"]}]";
            $current      = $current[$args["package_type"]] ?? '';
        }

        // get shipping methods
        $available_shipping_methods = [];
        $shipping_methods           = WC()->shipping->load_shipping_methods();

        if ($shipping_methods) {
            foreach ($shipping_methods as $key => $shipping_method) {
                // Automattic / WooCommerce Table Rate Shipping
                if ($key == 'table_rate' && class_exists('WC_Table_Rate_Shipping')
                    && class_exists(
                        'WC_Shipping_Zones'
                    )) {
                    $zones = WC_Shipping_Zones::get_zones();
                    foreach ($zones as $zone_data) {
                        if (isset($zone_data['id'])) {
                            $zone_id = $zone_data['id'];
                        } else if (isset($zone_data['zone_id'])) {
                            $zone_id = $zone_data['zone_id'];
                        } else {
                            continue;
                        }
                        $zone         = WC_Shipping_Zones::get_zone($zone_id);
                        $zone_methods = $zone->get_shipping_methods(false);
                        foreach ($zone_methods as $key => $shipping_method) {
                            if ($shipping_method->id == 'table_rate'
                                && method_exists(
                                    $shipping_method,
                                    'get_shipping_rates'
                                )) {
                                $zone_table_rates = $shipping_method->get_shipping_rates();
                                foreach ($zone_table_rates as $zone_table_rate) {
                                    $rate_label                                                                                           =
                                        ! empty($zone_table_rate->rate_label) ? $zone_table_rate->rate_label
                                            : "{$shipping_method->title} ({$zone_table_rate->rate_id})";
                                    $available_shipping_methods["table_rate:{$shipping_method->instance_id}:{$zone_table_rate->rate_id}"] =
                                        "{$zone->get_zone_name()} - {$rate_label}";
                                }
                            }
                        }
                    }
                    continue;
                }

                // Bolder Elements Table Rate Shipping
                if ($key == 'betrs_shipping' && is_a($shipping_method, 'BE_Table_Rate_Method')
                    && class_exists(
                        'WC_Shipping_Zones'
                    )) {
                    $zones = WC_Shipping_Zones::get_zones();
                    foreach ($zones as $zone_data) {
                        if (isset($zone_data['id'])) {
                            $zone_id = $zone_data['id'];
                        } else if (isset($zone_data['zone_id'])) {
                            $zone_id = $zone_data['zone_id'];
                        } else {
                            continue;
                        }
                        $zone         = WC_Shipping_Zones::get_zone($zone_id);
                        $zone_methods = $zone->get_shipping_methods(false);
                        foreach ($zone_methods as $key => $shipping_method) {
                            if ($shipping_method->id == 'betrs_shipping') {
                                $shipping_method_options = get_option(
                                    $shipping_method->id . '_options-' . $shipping_method->instance_id
                                );
                                if (isset($shipping_method_options['settings'])) {
                                    foreach ($shipping_method_options['settings'] as $zone_table_rate) {
                                        $rate_label                                                                                                   =
                                            ! empty($zone_table_rate['title']) ? $zone_table_rate['title']
                                                : "{$shipping_method->title} ({$zone_table_rate['option_id']})";
                                        $available_shipping_methods["betrs_shipping_{$shipping_method->instance_id}-{$zone_table_rate['option_id']}"] =
                                            "{$zone->get_zone_name()} - {$rate_label}";
                                    }
                                }
                            }
                        }
                    }
                    continue;
                }
                $method_title                     =
                    ! empty($shipping_methods[$key]->method_title) ? $shipping_methods[$key]->method_title
                        : $shipping_methods[$key]->title;
                $available_shipping_methods[$key] = $method_title;

                // split flat rate by shipping class
                if (($key == 'flat_rate' || $key == 'legacy_flat_rate')
                    && version_compare(WOOCOMMERCE_VERSION, '2.4', '>=')) {
                    $shipping_classes = WC()->shipping->get_shipping_classes();
                    foreach ($shipping_classes as $shipping_class) {
                        if (! isset($shipping_class->term_id)) {
                            continue;
                        }
                        $id                                        = $shipping_class->term_id;
                        $name                                      =
                            esc_html("{$method_title} - {$shipping_class->name}");
                        $method_class                              = esc_attr($key) . ":" . $id;
                        $available_shipping_methods[$method_class] = $name;
                    }
                }
            }
        }

        ?>
        <select id="<?php echo $args["id"]; ?>"
                name="<?php echo $args["name"]; ?>[]"
                style="width: 50%;"
                class="wc-enhanced-select"
                multiple="multiple"
                data-placeholder="<?php echo $args["placeholder"] ?? ""; ?>">
            <?php
            $shipping_methods_selected = (array) $current;

            $shipping_methods = WC()->shipping->load_shipping_methods();
            if ($available_shipping_methods) {
                foreach ($available_shipping_methods as $key => $label) {
                    echo '<option value="' . esc_attr($key) . '"' . selected(
                            in_array($key, $shipping_methods_selected),
                            true,
                            false
                        ) . '>' . esc_html($label) . '</option>';
                }
            }
            ?>
        </select>
        <?php
    }
}
