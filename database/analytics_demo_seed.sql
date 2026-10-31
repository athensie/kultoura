DELETE FROM page_views
WHERE referrer_host = 'demo-seed.kultoura.local';

INSERT INTO page_views (page, referrer_host, viewed_at)
WITH RECURSIVE dates AS (
    SELECT CURDATE() - INTERVAL 89 DAY AS visit_date
    UNION ALL
    SELECT visit_date + INTERVAL 1 DAY FROM dates WHERE visit_date < CURDATE()
),
sequences AS (
    SELECT 1 AS visit_number
    UNION ALL
    SELECT visit_number + 1 FROM sequences WHERE visit_number < 12
),
pages AS (
    SELECT 'home' AS page, 1 AS page_order
    UNION ALL SELECT 'tourism', 2
    UNION ALL SELECT 'foryou', 3
    UNION ALL SELECT 'traveldiary', 4
    UNION ALL SELECT 'restaurants', 5
)
SELECT
    pages.page,
    'demo-seed.kultoura.local',
    TIMESTAMP(dates.visit_date) + INTERVAL MOD(DAYOFYEAR(dates.visit_date) * 997 + sequences.visit_number * 3109 + pages.page_order * 721, 86400) SECOND
FROM dates
CROSS JOIN sequences
CROSS JOIN pages
WHERE sequences.visit_number <= 4 + MOD(DAYOFYEAR(dates.visit_date) * 13 + pages.page_order * 7, 9);
