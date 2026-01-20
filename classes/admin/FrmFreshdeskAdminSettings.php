<?php
/**
 * Plugin Name: Frm Freshdesk Admin Settings
 * Description: Adds Freshdesk admin menu with settings tabs and stores options in wp_options.
 * Version: 1.2.0
 */

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Options helper: all get_option / update_option logic lives here.
 */
final class FrmFreshdeskOptionsHelper {

    /** Option key in wp_options */
    private const OPTION_KEY = 'frm_freshdesk';

    /** Default options shape */
    public static function defaults(): array {
        return [
            'api_key'            => '',
            'entry_status_rules' => [],
        ];
    }

    /** Get the full options array (merged with defaults) */
    public static function get_options(): array {
        $stored = get_option(self::OPTION_KEY, []);
        if ( ! is_array($stored) ) {
            $stored = [];
        }
        return array_replace_recursive(self::defaults(), $stored);
    }

    /** Update the full options array */
    public static function set_options(array $options): bool {
        $merged = array_replace_recursive(self::get_options(), $options);
        return (bool) update_option(self::OPTION_KEY, $merged, false);
    }

    /** Get a single option by key with default fallback */
    public static function get(string $key, $default = null) {
        $opts = self::get_options();
        return array_key_exists($key, $opts) ? $opts[$key] : $default;
    }

    /** Set a single option by key */
    public static function set(string $key, $value): bool {
        $opts = self::get_options();
        $opts[$key] = $value;
        return (bool) update_option(self::OPTION_KEY, $opts, false);
    }

    /** API key convenience methods */
    public static function get_api_key(): string {
        $val = self::get('api_key', '');
        return is_string($val) ? $val : '';
    }

    public static function set_api_key(string $api_key): bool {
        $api_key = trim($api_key);
        return self::set('api_key', $api_key);
    }

    /** Entry status rules */
    public static function get_entry_status_rules(): array {
        $val = self::get('entry_status_rules', []);
        return is_array($val) ? $val : [];
    }

    public static function set_entry_status_rules(array $rules): bool {
        return self::set('entry_status_rules', $rules);
    }

    /** One empty rule template for UI */
    public static function empty_rule(): array {
        return [
            'action'           => 'ticket.created',
            'form_id'          => '',
            'email_field_id'   => '',
            'status_field_id'  => '',
            'initial_statuses' => '',
            'set_status_to'    => '',
            'active'           => 1,
        ];
    }
}

/**
 * Admin settings UI + routing.
 */
final class FrmFreshdeskAdminSettings {

    private const PAGE_SLUG   = 'frm-freshdesk-settings';
    private const CAPABILITY  = 'manage_options';

    // Tabs
    private const TAB_API      = 'api';
    private const TAB_STATUSES = 'statuses';

    // Nonce/action
    private const NONCE_ACTION = 'frm_freshdesk_save_settings';
    private const NONCE_NAME   = 'frm_freshdesk_nonce';

    public function init(): void {
        add_action('admin_menu', [ $this, 'register_menus' ]);
        add_action('admin_post_frm_freshdesk_save_settings', [ $this, 'handle_save' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_assets' ]);
    }

    public function register_menus(): void {

        // Parent (top-level)
        add_menu_page(
            'Freshdesk',
            'Freshdesk',
            self::CAPABILITY,
            self::PAGE_SLUG,
            [ $this, 'render_settings_page' ],
            'dashicons-admin-generic',
            56
        );

        // Child: Settings
        add_submenu_page(
            self::PAGE_SLUG,
            'Settings',
            'Settings',
            self::CAPABILITY,
            self::PAGE_SLUG,
            [ $this, 'render_settings_page' ]
        );
    }

    public function enqueue_assets(string $hook): void {
        // Only load on our settings page
        if ($hook !== 'toplevel_page_' . self::PAGE_SLUG) {
            return;
        }
        wp_enqueue_script('jquery');
    }

    public function render_settings_page(): void {
        if ( ! current_user_can(self::CAPABILITY) ) {
            wp_die('You do not have permission to access this page.');
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : self::TAB_API;
        if ( ! in_array($tab, [ self::TAB_API, self::TAB_STATUSES ], true) ) {
            $tab = self::TAB_API;
        }

        // flash messages
        $updated = isset($_GET['updated']) && $_GET['updated'] === '1';
        $error   = isset($_GET['error']) ? sanitize_text_field((string) $_GET['error']) : '';

        $tabs = [
            self::TAB_API      => 'API settings',
            self::TAB_STATUSES => 'Entry Statuses',
        ];

        echo '<div class="wrap">';
        echo '<h1>Freshdesk Settings</h1>';

        if ($updated) {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        } elseif ($error !== '') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
        }

        // Tabs
        echo '<h2 class="nav-tab-wrapper" style="margin-top:12px;">';
        foreach ($tabs as $key => $label) {
            $url = $this->tab_url($key);
            $cls = ($tab === $key) ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a class="' . esc_attr($cls) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';

        // Content
        echo '<div style="margin-top:16px;">';
        if ($tab === self::TAB_API) {
            $this->render_tab_api();
        } else {
            $this->render_tab_statuses();
        }
        echo '</div>';

        echo '</div>';
    }

    private function render_tab_api(): void {
        $api_key = FrmFreshdeskOptionsHelper::get_api_key();

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="frm_freshdesk_save_settings" />';
        echo '<input type="hidden" name="tab" value="' . esc_attr(self::TAB_API) . '" />';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="frm_freshdesk_api_key">API key</label></th>';
        echo '<td>';
        echo '<input type="text" class="regular-text" id="frm_freshdesk_api_key" name="api_key" value="' . esc_attr($api_key) . '" autocomplete="off" />';
        echo '<p class="description">Your Freshdesk API key.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        submit_button('Save API settings');
        echo '</form>';
    }

    private function render_tab_statuses(): void {
        $rules = FrmFreshdeskOptionsHelper::get_entry_status_rules();

        if (empty($rules)) {
            $rules = [ FrmFreshdeskOptionsHelper::empty_rule() ];
        }

        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:14px;border-radius:6px;max-width:1300px;">';
        echo '<h2 style="margin-top:0;">Updated entry status on</h2>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="frm_freshdesk_save_settings" />';
        echo '<input type="hidden" name="tab" value="' . esc_attr(self::TAB_STATUSES) . '" />';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        echo '<table class="widefat striped" id="frm-fd-status-table" style="margin-top:10px;">';
        echo '<thead><tr>';
        echo '<th style="width:150px;">Action</th>';
        echo '<th style="width:90px;">Form Id</th>';
        echo '<th style="width:120px;">Email Field Id</th>';
        echo '<th style="width:120px;">Status Field Id</th>';
        echo '<th>Initial entry statuses (comma-separated)</th>';
        echo '<th style="width:200px;">Set status to</th>';
        echo '<th style="width:90px;text-align:center;">Active</th>';
        echo '<th style="width:90px;text-align:center;">Remove</th>';
        echo '</tr></thead>';

        echo '<tbody id="frm-fd-status-tbody">';
        foreach ($rules as $i => $row) {
            echo $this->get_rule_row_html($i, is_array($row) ? $row : []);
        }
        echo '</tbody>';
        echo '</table>';

        echo '<p style="margin-top:12px;">';
        echo '<button type="button" class="button" id="frm-fd-add-row">+ Add row</button>';
        echo '</p>';

        echo '<p class="description" style="margin-top:10px;">';
        echo 'When the ticket is created and email is found in Entry fields by Email, and status is in this list, then change Status to.';
        echo '</p>';

        submit_button('Save Entry Statuses');

        echo '</form>';
        echo '</div>';

        // Hidden template row used by JS
        $tmpl = FrmFreshdeskOptionsHelper::empty_rule();
        echo '<script type="text/template" id="frm-fd-row-template">';
        echo $this->get_rule_row_html('__INDEX__', $tmpl);
        echo '</script>';

        // JS for add/remove rows
        ?>
        <script>
        (function($){

            function renumberRows(){
                $('#frm-fd-status-tbody tr').each(function(idx){
                    $(this).attr('data-index', idx);

                    $(this).find('[name]').each(function(){
                        var name = $(this).attr('name');
                        if(!name) return;
                        name = name.replace(/^rules\[\d+\]/, 'rules['+idx+']');
                        $(this).attr('name', name);
                    });
                });
            }

            function addRow(){
                var tmpl = $('#frm-fd-row-template').html();
                var idx = $('#frm-fd-status-tbody tr').length;
                tmpl = tmpl.replaceAll('__INDEX__', idx);
                $('#frm-fd-status-tbody').append(tmpl);
            }

            $(function(){
                $('#frm-fd-add-row').on('click', function(){
                    addRow();
                });

                $(document).on('click', '.frm-fd-remove-row', function(){
                    $(this).closest('tr').remove();
                    renumberRows();
                });
            });

        })(jQuery);
        </script>
        <?php
    }

    private function get_rule_row_html($i, array $row): string {
        $action           = isset($row['action']) ? (string) $row['action'] : '';
        $form_id          = isset($row['form_id']) ? (string) $row['form_id'] : '';
        $email_field_id   = isset($row['email_field_id']) ? (string) $row['email_field_id'] : '';
        $status_field_id  = isset($row['status_field_id']) ? (string) $row['status_field_id'] : '';
        $initial_statuses = isset($row['initial_statuses']) ? (string) $row['initial_statuses'] : '';
        $set_status_to    = isset($row['set_status_to']) ? (string) $row['set_status_to'] : '';
        $active           = ! empty($row['active']) ? 1 : 0;

        ob_start();
        ?>
        <tr data-index="<?php echo esc_attr($i); ?>">
            <td>
                <input
                    type="text"
                    class="regular-text"
                    style="width:100%;"
                    placeholder="ticket.created"
                    name="rules[<?php echo esc_attr($i); ?>][action]"
                    value="<?php echo esc_attr($action); ?>"
                />
            </td>

            <td>
                <input
                    type="number"
                    min="0"
                    step="1"
                    style="width:100%;"
                    name="rules[<?php echo esc_attr($i); ?>][form_id]"
                    value="<?php echo esc_attr($form_id); ?>"
                />
            </td>

            <td>
                <input
                    type="number"
                    min="0"
                    step="1"
                    style="width:100%;"
                    name="rules[<?php echo esc_attr($i); ?>][email_field_id]"
                    value="<?php echo esc_attr($email_field_id); ?>"
                />
            </td>

            <td>
                <input
                    type="number"
                    min="0"
                    step="1"
                    style="width:100%;"
                    name="rules[<?php echo esc_attr($i); ?>][status_field_id]"
                    value="<?php echo esc_attr($status_field_id); ?>"
                />
            </td>

            <td>
                <textarea
                    style="width:100%;height:100px;"
                    placeholder="Pending, New, Processing"
                    name="rules[<?php echo esc_attr($i); ?>][initial_statuses]"
                ><?php echo esc_textarea($initial_statuses); ?></textarea>
            </td>

            <td>
                <input
                    type="text"
                    style="width:100%;"
                    placeholder="Verified"
                    name="rules[<?php echo esc_attr($i); ?>][set_status_to]"
                    value="<?php echo esc_attr($set_status_to); ?>"
                />
            </td>

            <td style="text-align:center;">
                <input
                    type="checkbox"
                    name="rules[<?php echo esc_attr($i); ?>][active]"
                    value="1"
                    <?php checked($active, 1); ?>
                />
            </td>

            <td style="text-align:center;">
                <button type="button" class="button frm-fd-remove-row">Remove</button>
            </td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    public function handle_save(): void {
        if ( ! current_user_can(self::CAPABILITY) ) {
            wp_die('You do not have permission to perform this action.');
        }

        $tab = isset($_POST['tab']) ? sanitize_key((string) $_POST['tab']) : self::TAB_API;

        // Nonce
        if ( ! isset($_POST[self::NONCE_NAME]) || ! wp_verify_nonce((string) $_POST[self::NONCE_NAME], self::NONCE_ACTION) ) {
            $this->redirect($tab, false, 'Security check failed.');
        }

        if ($tab === self::TAB_API) {
            $api_key = isset($_POST['api_key']) ? sanitize_text_field((string) $_POST['api_key']) : '';
            FrmFreshdeskOptionsHelper::set_api_key($api_key);
            $this->redirect($tab, true);
        }

        if ($tab === self::TAB_STATUSES) {

            $posted = $_POST['rules'] ?? [];
            if ( ! is_array($posted) ) {
                $posted = [];
            }

            $clean = [];
            foreach ($posted as $row) {
                if ( ! is_array($row) ) { continue; }

                $action = isset($row['action']) ? sanitize_text_field((string) $row['action']) : '';
                $form_id = isset($row['form_id']) ? (int) $row['form_id'] : 0;
                $email_field_id = isset($row['email_field_id']) ? (int) $row['email_field_id'] : 0;
                $status_field_id = isset($row['status_field_id']) ? (int) $row['status_field_id'] : 0;
                $initial_statuses = isset($row['initial_statuses']) ? sanitize_text_field((string) $row['initial_statuses']) : '';
                $set_status_to = isset($row['set_status_to']) ? sanitize_text_field((string) $row['set_status_to']) : '';
                $active = ! empty($row['active']) ? 1 : 0;

                // Skip completely empty rows
                $is_empty = (
                    $action === '' &&
                    $form_id === 0 &&
                    $email_field_id === 0 &&
                    $status_field_id === 0 &&
                    $initial_statuses === '' &&
                    $set_status_to === ''
                );
                if ($is_empty) {
                    continue;
                }

                if ($action === '') {
                    $action = 'ticket.created';
                }

                $clean[] = [
                    'action'           => $action,
                    'form_id'          => $form_id,
                    'email_field_id'   => $email_field_id,
                    'status_field_id'  => $status_field_id,
                    'initial_statuses' => $initial_statuses,
                    'set_status_to'    => $set_status_to,
                    'active'           => $active,
                ];
            }

            FrmFreshdeskOptionsHelper::set_entry_status_rules($clean);
            $this->redirect($tab, true);
        }

        $this->redirect(self::TAB_API, false, 'Unknown tab.');
    }

    private function redirect(string $tab, bool $updated, string $error = ''): void {
        $url = $this->tab_url($tab, $updated, $error);
        wp_safe_redirect($url);
        exit;
    }

    private function tab_url(string $tab, bool $updated = false, string $error = ''): string {
        $args = [
            'page' => self::PAGE_SLUG,
            'tab'  => $tab,
        ];
        if ($updated) {
            $args['updated'] = '1';
        }
        if ($error !== '') {
            $args['error'] = $error;
        }
        return add_query_arg($args, admin_url('admin.php'));
    }
}

// Boot
add_action('plugins_loaded', static function () {
    (new FrmFreshdeskAdminSettings())->init();
});
