DELETE FROM page_views
WHERE referrer_host = 'demo-seed.kultoura.local';

INSERT INTO page_views (page, referrer_host, viewed_at)
SELECT
    pages.page,
    'demo-seed.kultoura.local',
    DATE_ADD(
        DATE_SUB(CURDATE(), INTERVAL dates.day_offset DAY),
        INTERVAL MOD(DAYOFYEAR(DATE_SUB(CURDATE(), INTERVAL dates.day_offset DAY)) * 997 + sequences.visit_number * 3109 + pages.page_order * 721, 86400) SECOND
    )
FROM (
    SELECT ones.n + tens.n * 10 AS day_offset
    FROM (
        SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
    ) AS ones
    CROSS JOIN (
        SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
    ) AS tens
    WHERE ones.n + tens.n * 10 < 90
) AS dates
CROSS JOIN (
    SELECT 1 AS visit_number UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
    UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
    UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
) AS sequences
CROSS JOIN (
    SELECT 'home' AS page, 1 AS page_order
    UNION ALL SELECT 'tourism', 2
    UNION ALL SELECT 'foryou', 3
    UNION ALL SELECT 'traveldiary', 4
    UNION ALL SELECT 'restaurants', 5
) AS pages
WHERE sequences.visit_number <= 4 + MOD(
    DAYOFYEAR(DATE_SUB(CURDATE(), INTERVAL dates.day_offset DAY)) * 13 + pages.page_order * 7,
    9
);
