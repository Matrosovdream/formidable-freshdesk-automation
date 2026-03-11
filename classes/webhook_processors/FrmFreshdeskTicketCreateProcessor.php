<?php

if ( ! defined('ABSPATH') ) { exit; }

class FrmFreshdeskTicketCreateProcessor {

    /**
     * Field IDs in wp_frm_item_metas
     */
    private const EMAIL_FIELD_ID  = 4;
    private const STATUS_FIELD_ID = 7;

    /**
     * Process entries whose current status is ANY of these
     */
    private const STATUS_MATCH = [
        'Processing-X',
        'Processing',
        'Verified'
    ];

    /**
     * Status value to set
     */
    private const STATUS_SET = 'Processing-E';

    // Logger
    private $logger;

    // Entry helper
    private $entryHelper;

    public function __construct( 
        $logger = new FrmFreshdeskLogger(),
        $entryHelper=null
        ) {
        $this->logger = $logger;

        if ( $entryHelper === null ) {
            $this->entryHelper = new FrmFreshdeskEntryHelper();
        }

    }

    /**
     * Expected payload path:
     * $payload['freshdesk_webhook']['ticket_contact_email']
     */
    public function process(array $payload): array {
        global $wpdb;

        $email = $this->extract_email($payload);
        if ($email === '') {
            return [
                'ok'     => false,
                'action' => 'none',
                'reason' => 'missing_ticket_contact_email',
            ];
        }

        $table = $wpdb->prefix . 'frm_item_metas';

        $match_statuses = array_values(array_filter(array_map('strval', self::STATUS_MATCH), static function($v){
            return trim($v) !== '';
        }));

        if (empty($match_statuses)) {
            return [
                'ok'     => false,
                'action' => 'none',
                'email'  => $email,
                'reason' => 'status_match_empty',
            ];
        }

        // WP doesn't allow passing array to prepare() placeholders directly,
        // so we build %s,%s,... safely and pass params as a flat array.
        $placeholders = implode(',', array_fill(0, count($match_statuses), '%s'));

        // Find item_id(s) that have:
        // - field_id=EMAIL_FIELD_ID meta_value=email
        // - AND field_id=STATUS_FIELD_ID meta_value IN STATUS_MATCH array
        $sql = "
            SELECT DISTINCT e.item_id
            FROM {$table} e
            INNER JOIN {$table} s
                ON s.item_id = e.item_id
               AND s.field_id = %d
               AND s.meta_value IN ($placeholders)
            WHERE e.field_id = %d
              AND e.meta_value = %s
        ";

        $params = array_merge(
            [ self::STATUS_FIELD_ID ],
            $match_statuses,
            [ self::EMAIL_FIELD_ID, $email ]
        );

        $sqlStr  = $wpdb->prepare($sql, $params);
        $item_ids = $wpdb->get_col($sqlStr);

        if (empty($item_ids)) {
            return [
                'ok'           => true,
                'action'       => 'no_match',
                'email'        => $email,
                'updated'      => 0,
                'item_ids'     => [],
                'status_was'   => $match_statuses,
                'status_to'    => self::STATUS_SET,
            ];
        }

        // Update status for each matching item_id
        $updated = 0;

        foreach ($item_ids as $item_id) {
            $item_id = (int) $item_id;

            // Update existing status meta row (field 7)
            /*$res = $wpdb->update(
                $table,
                ['meta_value' => self::STATUS_SET],
                ['item_id' => $item_id, 'field_id' => self::STATUS_FIELD_ID],
                ['%s'],
                ['%d', '%d']
            );*/

            $res = $this->entryHelper->updateMetaField( $item_id, self::STATUS_FIELD_ID, self::STATUS_SET );

            // Log each update result (for debugging; can be removed later)
            $this->logger->log('ticket_status_entry_update', [
                'item_id' => $item_id,
                'field_id' => self::STATUS_FIELD_ID,
                'status_to' => self::STATUS_SET,
                'update_result' => $res,
            ]);

            if ($res !== false) {
                // $res is number of rows updated; can be 0 if already same value
                $updated += (int) $res;
            } 
        }

        $processRes = [
            'ok'         => true,
            'action'     => 'updated',
            'email'      => $email,
            'updated'    => $updated,
            'item_ids'   => array_map('intval', $item_ids),
            'status_was' => $match_statuses,
            'status_to'  => self::STATUS_SET,
        ];

        // Log the update action
        $this->logger->log('ticket_create_processed', $processRes);

        return $processRes;

        
    }

    private function extract_email(array $payload): string {
        $email = '';

        if (isset($payload['freshdesk_webhook']) && is_array($payload['freshdesk_webhook'])) {
            $email = (string) ($payload['freshdesk_webhook']['ticket_contact_email'] ?? '');
        }

        return trim($email);

    }

}