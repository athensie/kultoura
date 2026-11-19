-- Removes only rows created by seed_proposal_analytics.php.
START TRANSACTION;

DELETE FROM user_activity_log
WHERE activity_id IN (
    SELECT row_id FROM analytics_proposal_seed_rows
    WHERE seed_key='proposal_analytics_2024_2026_v1' AND table_name='user_activity_log'
);

DELETE FROM item_views
WHERE view_id IN (
    SELECT row_id FROM analytics_proposal_seed_rows
    WHERE seed_key='proposal_analytics_2024_2026_v1' AND table_name='item_views'
);

DELETE FROM page_views
WHERE referrer_host='proposal-seed.kultoura.local';

DELETE FROM analytics_proposal_seed_rows WHERE seed_key='proposal_analytics_2024_2026_v1';
DELETE FROM analytics_proposal_seed_batches WHERE seed_key='proposal_analytics_2024_2026_v1';

COMMIT;
