<?php

use MyParcelNL\Sdk\src\Model\Consignment\BpostConsignment;
use MyParcelNL\Sdk\src\Model\Consignment\DPDConsignment;

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('wcmp_settings_data')) :

    /**
     * This class contains all data for the admin settings screens created by the plugin.
     */
    class wcmp_settings_data
    {
        const CARRIERS = [
            BpostConsignment::CARRIER_NAME,
            DPDConsignment::CARRIER_NAME,
        ];

        const SETTINGS_GENERAL         = "general";
        const SETTINGS_EXPORT_DEFAULTS = "export_defaults";

        /**
         * @var wcmp_settings_callbacks
         */
        private $callbacks;

        public function __construct()
        {
            $this->callbacks = require 'class-wcmp-settings-callbacks.php';

            // Create the MyParcel settings with the admin_init hook.
            add_action("admin_init", [$this, "create_all_settings"]);
        }

        public function log_screen()
        {
            echo "<pre>";
            var_dump(get_current_screen());
            echo "</pre>";
        }

        /**
         * Create all settings sections.
         */
        public function create_all_settings(): void
        {
            $this->generate_settings($this->get_general_sections(), self::SETTINGS_GENERAL);
            $this->generate_settings($this->get_export_defaults_sections(), self::SETTINGS_EXPORT_DEFAULTS);

            $this->generate_settings($this->get_carrier_bpost_sections(), 'bpost', true);
            $this->generate_settings($this->get_carrier_dpd_sections(), 'dpd', true);
        }

        /**
         * Generate settings sections and fields by the given $settingsArray.
         *
         * @param array  $settingsArray - Array of settings to loop through.
         * @param string $optionName    - Name to use in the identifier.
         * @param bool   $prefix        - Add the key of the top level settings as prefix before every setting or not.
         */
        private function generate_settings(array $settingsArray, string $optionName, bool $prefix = false): void
        {
            $optionIdentifier = "woocommerce_myparcelbe_{$optionName}_settings";

            // Register settings.
            register_setting($optionIdentifier, $optionIdentifier, [$this->callbacks, 'validate']);

            // Create option in wp_options with default settings if the option doesn't exist yet.
            if (false === get_option($optionIdentifier)) {
                $this->set_default_settings($optionName, $optionIdentifier);
            }

            foreach ($settingsArray as $name => $array) {
                foreach ($array as $section) {
                    $sectionName = "{$name}_{$section["name"]}";

                    add_settings_section(
                        $sectionName,
                        $section["label"],
                        [$this->callbacks, 'section'],
                        $optionIdentifier
                    );

                    foreach ($section["settings"] as $setting) {
                        $settingName = $prefix ? "{$name}_{$setting["name"]}" : $setting["name"];

                        add_settings_field(
                            $settingName,
                            $setting["label"],
                            $setting["type"],
                            $optionIdentifier,
                            $sectionName,
                            array_merge(
                                [
                                    'option_name' => $optionIdentifier,
                                    'id'          => $settingName,
                                ],
                                $setting["args"] ?? []
                            )
                        );
                    }
                }
            }
        }

        /**
         * Get a default string from the translations.
         *
         * @param $key
         *
         * @return string
         */
        public static function get_default_string($key): string
        {
            return __(self::get_default_strings()[$key], 'woocommerce-myparcelbe');
        }

        /**
         * Set default settings.
         *
         * @param string $option
         * @param string $optionIdentifier
         *
         * @return void.
         */
        public static function set_default_settings(string $option, string $optionIdentifier): void
        {
            switch ($option) {
                case self::SETTINGS_GENERAL:
                    $default = self::get_default_general_settings();
                    break;
                case self::SETTINGS_EXPORT_DEFAULTS:
                    $default = self::get_default_export_defaults_settings();
                    break;
                case BpostConsignment::CARRIER_NAME:
                case DPDConsignment::CARRIER_NAME:
                    $default = self::get_default_carrier_settings($option);
                    break;
                default:
                    $default = [];
                    break;
            }

            // Add the option if it doesn't exist yet, otherwise update it.
            if (false === get_option($optionIdentifier)) {
                add_option($optionIdentifier, $default);
            } else {
                update_option($optionIdentifier, $default);
            }
        }

        /**
         * These are the unprefixed settings for bpost.
         * After the settings are generated every name will be prefixed with "bpost_"
         * Example: delivery_enabled => bpost_delivery_enabled
         *
         * @return array
         */
        private function get_bpost_section_delivery_options(): array
        {
            return [
                [
                    "name"  => "delivery_enabled",
                    "label" => __("Enable bpost delivery", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                ],
                [
                    "name"  => "drop_off_days",
                    "label" => __("Drop-off days", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "enhanced_select"],
                    "args"  => [
                        "options"     => self::get_weekdays(),
                        "description" => __(
                            "Days of the week on which you hand over parcels to bpost",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "cutoff_time",
                    "label" => __("Cut-off time", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "text_input"],
                    "args"  => [
                        "type"        => "text",
                        "size"        => "5",
                        "description" => __(
                            "Time at which you stop processing orders for the day (format: hh:mm)",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "drop_off_delay",
                    "label" => __("Drop-off delay", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "text_input"],
                    "args"  => [
                        "type"        => "text",
                        "size"        => "5",
                        "description" => __(
                            "Number of days you need to process an order.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "delivery_days_window",
                    "label" => __("Delivery days window", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                    "args"  => [
                        "description" => __(
                            "Show the delivery date inside the checkout.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "signature",
                    "label" => __("Signature on delivery", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "delivery_option_enable"],
                    "args"  => [
                        "has_title"   => false,
                        "has_price"   => true,
                        "size"        => 3,
                        "description" => sprintf(
                            __(
                                "Enter an amount that is either positive or negative. For example, do you want to give a discount for using this function or do you want to charge extra for this delivery option.",
                                "woocommerce-myparcelbe"
                            )
                        ),
                    ],
                ],
            ];
        }

        /**
         * Create an array of translated weekdays.
         *
         * @return array
         */
        public static function get_weekdays()
        {
            return [
                '0' => __('Sunday', 'woocommerce-myparcelbe'),
                '1' => __('Monday', 'woocommerce-myparcelbe'),
                '2' => __('Tuesday', 'woocommerce-myparcelbe'),
                '3' => __('Wednesday', 'woocommerce-myparcelbe'),
                '4' => __('Thursday', 'woocommerce-myparcelbe'),
                '5' => __('Friday', 'woocommerce-myparcelbe'),
                '6' => __('Saturday', 'woocommerce-myparcelbe'),
            ];
        }

        /**
         * @return array
         */
        private function get_bpost_section_pickup_options(): array
        {
            return [
                [
                    "name"  => "pickup",
                    "label" => __("bpost pickup", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "delivery_option_enable"],
                    "args"  => [
                        "has_title"   => false,
                        "has_price"   => true,
                        "size"        => 3,
                        "description" => sprintf(
                            __(
                                "Enter an amount that is either positive or negative. For example, do you want to give a discount for using this function or do you want to charge extra for this delivery option.",
                                "woocommerce-myparcelbe"
                            )
                        ),
                    ],
                ],
            ];
        }

        /**
         * @return array
         */
        private function get_general_sections()
        {
            return [
                self:: SETTINGS_GENERAL => [
                    [
                        "name"     => "api",
                        "label"    => __("API settings", "woocommerce-myparcelbe"),
                        "settings" => $this->get_general_section_api(),
                    ],
                    [
                        "name"     => "general",
                        "label"    => __("General settings", "woocommerce-myparcelbe"),
                        "settings" => $this->get_general_section_general(),
                    ],
                    [
                        "name"     => "checkout_options",
                        "label"    => __("Checkout options", "woocommerce-myparcelbe"),
                        "settings" => $this->get_general_section_checkout_options(),
                    ],
                    [
                        "name"     => "customizations",
                        "label"    => __("Customizations", "woocommerce-myparcelbe"),
                        "settings" => $this->get_general_section_customizations(),
                    ],
                    [
                        "name"     => "diagnostics",
                        "label"    => __("Diagnostic tools", "woocommerce-myparcelbe"),
                        "settings" => $this->get_general_section_diagnostics(),
                    ],
                ],
            ];
        }

        private function get_export_defaults_sections()
        {
            return [
                self::SETTINGS_EXPORT_DEFAULTS => [
                    [
                        "name"     => "defaults",
                        "label"    => __('Default export settings', 'woocommerce-myparcelbe'),
                        "settings" => $this->get_export_defaults_section_defaults(),
                    ],
                ],
            ];
        }

        /**
         * Get the array of bpost sections and their settings to be added to WordPress.
         *
         * @return array
         */
        private function get_carrier_bpost_sections()
        {
            return [
                BpostConsignment::CARRIER_NAME => [
                    [
                        "name"     => "delivery_options",
                        "label"    => __("bpost delivery settings", "woocommerce-myparcelbe"),
                        "settings" => $this->get_bpost_section_delivery_options(),
                    ],
                    [
                        "name"     => "pickup_options",
                        "label"    => __("bpost pickup options", "woocommerce-myparcelbe"),
                        "settings" => $this->get_bpost_section_pickup_options(),
                    ],
                ],
            ];
        }

        /**
         * Get the array of dpd sections and their settings to be added to WordPress.
         *
         * @return array
         */
        private function get_carrier_dpd_sections()
        {
            return [
                DPDConsignment::CARRIER_NAME => [
                    [
                        "name"     => "delivery_options",
                        "label"    => __("dpd settings", "woocommerce-myparcelbe"),
                        "settings" => $this->get_dpd_section_delivery_options(),
                    ],
                    [
                        "name"     => "pickup_options",
                        "label"    => __("dpd delivery options", "woocommerce-myparcelbe"),
                        "settings" => $this->get_dpd_section_pickup_options(),
                    ],
                ],
            ];
        }

        private function carrier_defaults()
        {
            return [
                BpostConsignment::CARRIER_NAME => [
                    // settings
                    "delivery_enabled"     => "",
                    "drop_off_days"        => "",
                    "cutoff_time"          => "",
                    "drop_off_delay"       => "",
                    "delivery_days_window" => "",

                    // delivery_options
                    "signature"            => "",
                    "pickup"               => "",
                ],
                DPDConsignment::CARRIER_NAME   => [
                    // settings
                    "delivery_enabled"     => "",
                    "drop_off_days"        => "",
                    "cutoff_time"          => "",
                    "drop_off_delay"       => "",
                    "delivery_days_window" => "",

                    // delivery_options
                    "pickup"               => "",
                ],
            ];
        }

        /**
         * These are the unprefixed settings for dpd.
         * After the settings are generated every name will be prefixed with "dpd_"
         * Example: delivery_enabled => dpd_delivery_enabled
         *
         * @return array
         */
        private function get_dpd_section_delivery_options(): array
        {
            return [
                [
                    "name"  => "delivery_enabled",
                    "label" => __("Enable DPD delivery", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                ],
                [
                    "name"  => "drop_off_days",
                    "label" => __("Drop-off days", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "enhanced_select"],
                    "args"  => [
                        "options"     => self::get_weekdays(),
                        "description" => __(
                            "Days of the week on which you hand over parcels to dpd",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "cutoff_time",
                    "label" => __("Cut-off time", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "text_input"],
                    "args"  => [
                        "type"        => "text",
                        "size"        => "5",
                        "description" => __(
                            "Time at which you stop processing orders for the day (format: hh:mm)",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "drop_off_delay",
                    "label" => __("Drop-off delay", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "text_input"],
                    "args"  => [
                        "type"        => "text",
                        "size"        => "5",
                        "description" => __(
                            "Number of days you need to process an order.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "delivery_days_window",
                    "label" => __("Delivery days window", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                    "args"  => [
                        "description" => __(
                            "Show the delivery date inside the checkout.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
            ];
        }

        /**
         * @return array
         */
        private function get_dpd_section_pickup_options(): array
        {
            return [
                [
                    "name"  => "pickup",
                    "label" => __("dpd pickup", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "delivery_option_enable"],
                    "args"  => [
                        "has_title"   => false,
                        "has_price"   => true,
                        "size"        => 3,
                        "description" => sprintf(
                            __(
                                "Enter an amount that is either positive or negative. For example, do you want to give a discount for using this function or do you want to charge extra for this delivery option.",
                                "woocommerce-myparcelbe"
                            )
                        ),
                    ],
                ],
            ];
        }

        /**
         * @return array
         */
        private function get_general_section_api(): array
        {
            return [
                [
                    "name"  => "api_key",
                    "label" => __("Key", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "text_input"],
                    "args"  => [
                        "size" => 50,
                    ],
                ],
            ];
        }

        /**
         * @return array
         */
        private function get_general_section_general()
        {
            return [
                [
                    "name"  => "download_display",
                    "label" => __("Label display", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "radio_button"],
                    "args"  => [
                        "options" => [
                            "download" => __("Download PDF", "woocommerce-myparcelbe"),
                            "display"  => __("Open the PDF in a new tab", "woocommerce-myparcelbe"),
                        ],
                    ],
                ],
                [
                    "name"  => "label_format",
                    "label" => __("Label format", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "radio_button"],
                    "args"  => [
                        "options" => [
                            "A4" => __("Standard printer (A4)", "woocommerce-myparcelbe"),
                            "A6" => __("Label Printer (A6)", "woocommerce-myparcelbe"),
                        ],
                    ],
                ],
                [
                    "name"  => "print_position_offset",
                    "label" => __("Ask for print start position", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                    "args"  => [
                        "description" => __(
                            "This option enables you to continue printing where you left off last time",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "email_tracktrace",
                    "label" => __("Track & Trace in email", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                    "args"  => [
                        "description" => __(
                            "Add the Track & Trace code to emails to the customer.<br/><strong>Note!</strong> When you select this option, make sure you have not enabled the Track & Trace email in your MyParcel BE backend.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "myaccount_tracktrace",
                    "label" => __("Track & Trace in My Account", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                    "args"  => [
                        "description" => __(
                            "Show Track & Trace trace code and link in My Account.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "process_directly",
                    "label" => __("Process shipments directly", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                    "args"  => [
                        "description" => __(
                            "When you enable this option, shipments will be directly processed when sent to MyParcel BE.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "order_status_automation",
                    "label" => __("Order status automation", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                    "args"  => [
                        "description" => __(
                            "Automatically set order status to a predefined status after successful MyParcel BE export.<br/>Make sure <strong>Process shipments directly</strong> is enabled when you use this option together with the <strong>Track & Trace in email</strong> option, otherwise the Track & Trace code will not be included in the customer email.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "automatic_order_status",
                    "label" => __("Automatic order status", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "order_status_select"],
                    "args"  => [
                        "class" => "automatic_order_status",
                    ],
                ],
                [
                    "name"  => "keep_shipments",
                    "label" => __("Keep old shipments", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                    "args"  => [
                        "default"     => 0,
                        "description" => __(
                            "With this option enabled, data from previous shipments (Track & Trace links) will be kept in the order when you export more than once.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "barcode_in_note",
                    "label" => __("Place barcode inside note", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                    "args"  => [
                        "class"       => "barcode_in_note",
                        "description" => __("Place the barcode inside a note of the order", "woocommerce-myparcelbe"),
                    ],
                ],
                [
                    "name"  => "barcode_in_note_title",
                    "label" => __("Title before the barcode", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "text_input"],
                    "args"  => [
                        "class"       => "barcode_in_note_title",
                        "default"     => "Tracking code:",
                        "size"        => 25,
                        "description" => __(
                            "You can change the text before the barcode inside an note",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
            ];
        }

        /**
         * @return array
         */
        private function get_general_section_checkout_options()
        {
            return [
                [
                    "name"  => "use_split_address_fields",
                    "label" => __("MyParcel BE address fields", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                    "args"  => [
                        "class"       => "use_split_address_fields",
                        "description" => __(
                            "When enabled the checkout will use the MyParcel BE address fields. This means there will be three separate fields for street name, number and suffix. Want to use the WooCommerce default fields? Leave this option unchecked.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "delivery_options_enabled",
                    "label" => __("Enable MyParcel BE delivery options", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                ],
                [
                    "name"  => "header_delivery_options_title",
                    "label" => __("Delivery options title", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "text_input"],
                    "args"  => [
                        "size"        => "53",
                        "title"       => "Delivery options title",
                        "description" => __(
                            "You can place a delivery title above the MyParcel BE options. When there is no title, it will not be visible.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "at_home_delivery",
                    "label" => __("Home delivery title", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "text_input"],
                    "args"  => [
                        "size"    => "53",
                        "title"   => "Delivered at home or at work",
                        "current" => self::get_default_string("at_home_delivery_title"),
                    ],
                ],
                [
                    "name"  => "standard_title",
                    "label" => __("Standard delivery title", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "text_input"],
                    "args"  => [
                        "size"        => "53",
                        "title"       => "Standard delivery",
                        "current"     => self::get_default_string("standard_title"),
                        "description" => __(
                            "When there is no title, the delivery time will automatically be visible.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "signature_title",
                    "label" => __("Signature on delivery", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "text_input"],
                    "args"  => [
                        "has_title" => true,
                        "title"     => "Signature on delivery",
                        "current"   => self::get_default_string("signature_title"),
                        "size"      => "30",
                    ],
                ],
                [
                    "name"  => "pickup_title",
                    "label" => __("Pickup", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "text_input"],
                    "args"  => [
                        "has_title" => true,
                        "title"     => "Pickup",
                        "current"   => self::get_default_string("pickup_title"),
                        "size"      => "30",
                    ],
                ],
                [
                    "name"  => "checkout_display",
                    "label" => __("Display for", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "select"],
                    "args"  => [
                        "options"     => [
                            "selected_methods" => __(
                                "Shipping methods associated with Parcels",
                                "woocommerce-myparcelbe"
                            ),
                            "all_methods"      => __("All shipping methods", "woocommerce-myparcelbe"),
                        ],
                        "description" => __(
                            "You can link the delivery options to specific shipping methods by adding them to the package types under \"Standard export settings\". The delivery options are not visible at foreign addresses.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "checkout_position",
                    "label" => __("Checkout position", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "select"],
                    "args"  => [
                        "options"     => [
                            "woocommerce_after_checkout_billing_form"  => __(
                                "Show checkout options after billing details",
                                "woocommerce-myparcelbe"
                            ),
                            "woocommerce_after_checkout_shipping_form" => __(
                                "Show checkout options after shipping details",
                                "woocommerce-myparcelbe"
                            ),
                            "woocommerce_after_order_notes"            => __(
                                "Show checkout options after notes",
                                "woocommerce-myparcelbe"
                            ),
                        ],
                        "description" => __(
                            "You can change the place of the checkout options on the checkout page. By default it will be placed after shipping details.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
            ];
        }

        /**
         * @return array
         */
        private function get_general_section_customizations()
        {
            return [
                [
                    "name"  => "custom_css",
                    "label" => __("Custom styles", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "textarea"],
                    "args"  => [
                        "width"  => "80",
                        "height" => "8",

                    ],
                ],
            ];
        }

        /**
         * @return array
         */
        private function get_general_section_diagnostics()
        {
            return [
                [
                    "name"  => "error_logging",
                    "label" => __("Log API communication", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                    "args"  => [
                        "description" => '<a href="' . esc_url_raw(
                                admin_url("admin.php?page=wc-status&tab=logs")
                            ) . '" target="_blank">' . __(
                                "View logs",
                                "woocommerce-myparcelbe"
                            ) . "</a> (wc-myparcelbe)",
                    ],
                ],
            ];
        }

        private function get_export_defaults_section_defaults()
        {
            return [
                [
                    "name"  => "shipping_methods_package_types",
                    "label" => __("Package types", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "shipping_methods_package_types"],
                    "args"  => [
                        "package_types" => WooCommerce_MyParcelBE()->export->get_package_types(),
                        "description"   => __(
                            "Select one or more shipping methods for each MyParcel BE package type",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "connect_email",
                    "label" => __("Connect customer email", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                    "args"  => [
                        "description" => sprintf(
                            __(
                                "When you connect the customer email, MyParcel BE can send a Track & Trace email to this address. In your %sMyParcel BE backend%s you can enable or disable this email and format it in your own style.",
                                "woocommerce-myparcelbe"
                            ),
                            '<a href="https://backoffice.sendmyparcel.be/settings/account" target="_blank">',
                            '</a>'
                        ),
                    ],
                ],
                [
                    "name"  => "connect_phone",
                    "label" => __("Connect customer phone", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                    "args"  => [
                        "description" => __(
                            "When you connect the customer's phone number, the courier can use this for the delivery of the parcel. This greatly increases the delivery success rate for foreign shipments.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "label_description",
                    "label" => __("Label description", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "text_input"],
                    "args"  => [
                        "size"        => "25",
                        "description" => __(
                            "When you connect the customer's phone number, the courier can use this for the delivery of the parcel. This greatly increases the delivery success rate for foreign shipments.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
                [
                    "name"  => "connect_phone",
                    "label" => __("Connect customer phone", "woocommerce-myparcelbe"),
                    "type"  => [$this->callbacks, "checkbox"],
                    "args"  => [
                        "description" => __(
                            "When you connect the customer's phone number, the courier can use this for the delivery of the parcel. This greatly increases the delivery success rate for foreign shipments.",
                            "woocommerce-myparcelbe"
                        ),
                    ],
                ],
            ];
        }

        /**
         * Get the defaults for each carrier.
         *
         * @param string $carrier
         *
         * @return array
         */
        public static function get_default_carrier_settings(string $carrier): array
        {
            return [
                "{$carrier}_delivery_enabled"     => 1,
                "{$carrier}_pickup_enabled"       => 0,
                "{$carrier}_drop_off_days"        => [1, 2, 3, 4, 5],
                "{$carrier}_drop_off_delay"       => 0,
                "{$carrier}_delivery_days_window" => 1,
            ];
        }

        /**
         * @return array
         */
        private static function get_default_general_settings(): array
        {
            return [
                "download_display" => "download",
                "label_format"     => "A4",
            ];
        }

        /**
         * @return array
         */
        private static function get_default_export_defaults_settings(): array
        {
            return [];
        }

        private static function get_default_strings()
        {
            return [
                "at_home_delivery_title" => "Delivered at home or at work",
                "standard_title"         => "Standard delivery",
                "signature_title"        => "Signature on delivery",
                "pickup_title"           => "Pickup",
            ];
        }
    }

endif;

new wcmp_settings_data();