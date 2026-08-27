<?php
// Heading
$_['heading_title'] = 'AG media Review Reminder';

// Text
$_['text_extension'] = 'Extensions';
$_['text_success'] = 'Success: Review Reminder settings have been saved.';
$_['text_token_success'] = 'Success: A new cron token was generated. Update the URL in EasyCron before the next run.';
$_['text_settings'] = 'Delivery settings';
$_['text_email'] = 'Email template';
$_['text_cron'] = 'Protected cron URL';
$_['text_independent_notice'] = 'This is an independent AG media extension. It does not use OrderReviews licensing, vendor services, coupons, or external tracking.';
$_['text_core_review_notice'] = 'Each product button opens OpenCart’s standard product review form. Version 1 does not create or send coupons.';
$_['text_placeholders'] = 'Available placeholders:';
$_['text_token_warning'] = 'Treat this URL like a password. Anyone who has it can start the worker. Do not publish it or include it in screenshots.';
$_['text_confirm_regenerate'] = 'The existing EasyCron URL will stop working immediately. Generate a new token?';
$_['text_recent_deliveries'] = 'Recent deliveries';
$_['text_no_deliveries'] = 'No reminders have been queued yet.';

// Entry
$_['entry_status'] = 'Status';
$_['entry_min_order_date'] = 'Earliest order date';
$_['entry_order_status'] = 'Eligible order statuses';
$_['entry_delay_days'] = 'Delay after order (days)';
$_['entry_batch_size'] = 'Messages per cron run';
$_['entry_hourly_limit'] = 'Maximum attempts per hour';
$_['entry_max_age_days'] = 'Oldest order (days)';
$_['entry_max_attempts'] = 'Maximum attempts per message';
$_['entry_sender_name'] = 'Sender name';
$_['entry_sender_email'] = 'Sender email';
$_['entry_smtp'] = 'Mail transport';
$_['entry_subject'] = 'Subject';
$_['entry_body'] = 'HTML message';
$_['entry_cron_url'] = 'EasyCron URL';

// Help
$_['help_order_status'] = 'Only orders currently in one of these statuses are eligible. A retry is cancelled if the order is no longer eligible.';
$_['help_min_order_date'] = 'Permanent safety boundary. Orders before 27 August 2026 are never queued or sent, even if the maximum age is increased later.';
$_['help_delay_days'] = 'Wait this many full days after the order date before queueing a reminder. Allowed: 0–90.';
$_['help_batch_size'] = 'Keep this small to protect the mail server. Start with 1. Allowed: 1–10.';
$_['help_hourly_limit'] = 'A hard domain-safety limit. Every SMTP attempt counts, including failed attempts. Start with 1. Allowed: 1–2, leaving reserve below the hosting threshold of 5.';
$_['help_max_age_days'] = 'Orders older than this are not added to the queue. It must be equal to or greater than the delay. Allowed: 1–365.';
$_['help_max_attempts'] = 'Temporary failures use increasing retry delays. Confirmed invalid recipients are suppressed immediately. Allowed: 1–5.';
$_['help_sender'] = 'Read-only values from System → Settings → Mail. SMTP host, port, username, and password are also taken from the OpenCart mail configuration.';
$_['help_cron'] = 'Paste this complete HTTPS URL into EasyCron. A 15-minute schedule is safe; the batch and hourly limits still apply.';

// Buttons
$_['button_copy'] = 'Copy';
$_['button_regenerate'] = 'Generate new token';

// Columns
$_['column_order'] = 'Order';
$_['column_email'] = 'Recipient';
$_['column_status'] = 'Status';
$_['column_attempts'] = 'Attempts';
$_['column_updated'] = 'Updated / sent';
$_['column_error'] = 'Last error';

// Delivery statuses
$_['status_pending'] = 'Pending';
$_['status_processing'] = 'Processing';
$_['status_sending'] = 'Sending';
$_['status_retry'] = 'Waiting to retry';
$_['status_sent'] = 'Sent';
$_['status_failed'] = 'Failed';
$_['status_cancelled'] = 'Cancelled';
$_['status_suppressed'] = 'Suppressed';
$_['status_unknown'] = 'Unknown / manual check';

// Errors
$_['error_permission'] = 'Warning: You do not have permission to modify Review Reminder.';
$_['error_smtp_not_ready'] = 'Authenticated SMTP is not fully configured. The cron worker will not send until System → Settings → Mail contains the SMTP engine, host, username, and password.';
$_['error_reviews_disabled'] = 'OpenCart product reviews are disabled. Enable them in the store settings before activating this module.';
$_['warning_guest_reviews'] = 'Guest reviews are disabled. Customers who ordered without an account will need to sign in and may be unable to leave a review.';
$_['error_order_status'] = 'Select at least one eligible order status.';
$_['error_min_order_date'] = 'Enter a valid date that is not earlier than %s.';
$_['error_number_range'] = 'Enter a whole number from %d to %d.';
$_['error_max_age_delay'] = 'The oldest order limit cannot be shorter than the delivery delay.';
$_['error_subject'] = 'The subject must contain between 3 and 180 characters.';
$_['error_body'] = 'The message must contain at least 10 visible characters and no more than 65,535 characters.';
$_['error_body_placeholders'] = 'The message must contain both {products} and {unsubscribe_url}.';
