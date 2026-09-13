<?php
/*
 |--------------------------------------------------------------------
 | SITE CONTENT — admin-editable photos & About sections
 |--------------------------------------------------------------------
 | Backs the "Site Content" admin page (admin/adminsitecontent.php)
 | and is read by index.php (home hero photos) and pages/about.php
 | (about hero photo + the History/Geography/etc. section rows).
 |
 | Include this AFTER config/dbmain.php (needs $conn). Safe to include
 | on every page load — table creation/seeding is idempotent.
 |
 |   site_photos     — named singleton image slots (home hero x2, about hero)
 |   about_sections  — admin-managed, reorderable list of About page rows
 */

$conn->query(
    "CREATE TABLE IF NOT EXISTS site_photos (
        photo_key  VARCHAR(40) NOT NULL PRIMARY KEY,
        image      VARCHAR(255) NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$conn->query(
    "CREATE TABLE IF NOT EXISTS about_sections (
        section_id INT AUTO_INCREMENT PRIMARY KEY,
        title      VARCHAR(100) NOT NULL,
        icon_key   VARCHAR(20) NOT NULL DEFAULT 'history',
        image      VARCHAR(255) NULL,
        body       TEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// First-ever run: seed the 5 sections with the site's real, already-written
// copy so turning this feature on doesn't change what visitors see until an
// admin actually edits something.
$seedCheck = $conn->query("SELECT COUNT(*) AS c FROM about_sections");
if ($seedCheck && (int) $seedCheck->fetch_assoc()['c'] === 0) {
    $seedSections = [
        [
            'title' => 'History',
            'icon_key' => 'history',
            'sort_order' => 1,
            'body' => "Long before it carried its present name, the land now known as Malvar was called Luta — a name tied to local legend and the oral histories passed down through Batangas. According to accounts recorded by the historian Ferdinand Blumentritt, the area's roots trace back to around 1300 AD, when Datu Puti and his followers, having fled the rule of Sultan Makatunao of Borneo, settled across what is now Batangas province. Their descendants went on to populate parts of Laguna, Cavite, the Bicol region, and this stretch of land then known as Luta — originally a barrio under the municipality of Lipa.\n\nAs Luta grew, its boundaries expanded to include the barrios of Payapa, Kalikangan, San Gallo, and Bilukaw — communities that would later split further into San Fernando, Santiago, Bagong Pook, San Andres, and San Juan as the population grew and organized.\n\nLocal leaders and residents, organized under the group Samahang Mag-aararo, petitioned for Luta's independence from Lipa, with the support of then-Secretary of the Interior Teodoro M. Kalaw. Their efforts succeeded on December 16, 1918, when Acting Governor-General Charles B. Yeater proclaimed Luta an independent municipality. The town was formally inaugurated on January 10, 1919 — and renamed Malvar, in honor of General Miguel Malvar, the Batangueño revolutionary leader remembered as the last Filipino general to surrender to American forces during the Philippine-American War.",
        ],
        [
            'title' => 'Geography',
            'icon_key' => 'geography',
            'sort_order' => 2,
            'body' => "Malvar sits in the CALABARZON region, nestled between the City of Tanauan to the north and the City of Lipa to the south, within Batangas' 3rd congressional district. The municipality spans roughly 33 square kilometers and is organized into 15 barangays, with elevations ranging from about 24 to 354 meters above sea level.\n\nIts location along the President J.P. Laurel Highway and its proximity to the Southern Tagalog Arterial Road (STAR Tollway) make Malvar an easy stop for travelers coming from Metro Manila — typically reachable within one and a half to two hours by road.",
        ],
        [
            'title' => 'Economy & Industry',
            'icon_key' => 'economy',
            'sort_order' => 3,
            'body' => "Malvar has grown into one of Batangas' more dynamic economic centers, anchored by the Light Industry & Science Park IV (LISP IV) — a roughly 170-hectare PEZA-registered industrial zone developed by the Science Park of the Philippines. The town is also part of the larger LIMA Estate, a mixed-use economic zone shared with Lipa that spans over 1,000 hectares and hosts hundreds of manufacturing, logistics, and technology locators.\n\nAlongside its industrial growth, agriculture remains part of Malvar's identity. Coconut and sugarcane are among the province's most widely cultivated crops, and Malvar has been part of local initiatives promoting organic farming — efforts that have earned the area recognition as an emerging \"Organic Capital\" within Batangas, supporting farmers' associations and small agri-tourism ventures alongside its industrial parks.",
        ],
        [
            'title' => 'Culture & Festivals',
            'icon_key' => 'culture',
            'sort_order' => 4,
            'body' => "At the heart of Malvar's community life is the San Juan Bautista Parish Church, a longstanding landmark that continues to anchor much of the town's religious and civic gatherings. Each year, the Malvar Town Fiesta brings the community together with parades, cultural performances, and local cuisine — a celebration that reflects the same spirit of cooperation that helped the town win its independence back in 1918.\n\nThe Malvar Municipal Hall itself stands as a marker of that history, reflecting decades of local governance rooted in the town's founding generation.",
        ],
        [
            'title' => 'Nature & Attractions',
            'icon_key' => 'nature',
            'sort_order' => 5,
            'body' => "Despite its industrial growth, Malvar keeps close ties to nature. Calejon Falls in Barangay San Gregorio is among the town's best-known natural spots — a set of four major waterfalls (plus two smaller ones) reached by a descent of roughly 300 steps, popular with visitors looking to cool off in its clear waters.\n\nThe town also lies close to Mount Malarayat, offering opportunities for hiking and scenic views over the surrounding Batangas countryside — a reminder that Malvar's character is shaped as much by its landscapes as by its factories and fiestas.",
        ],
    ];
    $seedStmt = $conn->prepare("INSERT INTO about_sections (title, icon_key, body, sort_order) VALUES (?, ?, ?, ?)");
    foreach ($seedSections as $s) {
        $seedStmt->bind_param('sssi', $s['title'], $s['icon_key'], $s['body'], $s['sort_order']);
        $seedStmt->execute();
    }
    $seedStmt->close();
}

/*
 |--------------------------------------------------------------------
 | ICON PRESETS
 |--------------------------------------------------------------------
 | Admin picks one of these by key (a dropdown, not raw markup) so a
 | new About section always gets a safe, on-brand icon.
 */
function sitecontent_icons(): array
{
    return [
        'history'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 5.5v15"/><path d="M20 18H6.5A2.5 2.5 0 0 0 4 20.5"/></svg>',
        'geography' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-7.5 7-12a7 7 0 0 0-14 0c0 4.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>',
        'economy'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>',
        'culture'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M18.4 5.6l-2.8 2.8M8.4 15.6l-2.8 2.8"/></svg>',
        'nature'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 7 9h3l-4 6h4v5h4v-5h4l-4-6h3z"/></svg>',
        'star'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l2.6 5.6 6.1.6-4.6 4.1 1.3 6-5.4-3.1-5.4 3.1 1.3-6-4.6-4.1 6.1-.6z"/></svg>',
        'heart'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20s-7-4.5-9.5-9C.5 7 2 3.5 5.5 3.5c2 0 3.5 1 4.5 2.5 1-1.5 2.5-2.5 4.5-2.5C18 3.5 19.5 7 19.5 11 17 15.5 12 20 12 20z"/></svg>',
        'flag'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V4"/><path d="M5 4h13l-3 4 3 4H5"/></svg>',
        'building'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 8h1M14 8h1M9 12h1M14 12h1M9 16h1M14 16h1"/></svg>',
        'camera'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h3l2-2h6l2 2h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="14" r="3.5"/></svg>',
        'compass'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-2 6-6 2 2-6z"/></svg>',
    ];
}

function sitecontent_icon_labels(): array
{
    return [
        'history'   => 'Book (History)',
        'geography' => 'Pin (Geography)',
        'economy'   => 'Bar Chart (Economy)',
        'culture'   => 'Sparkle (Culture)',
        'nature'    => 'Tree (Nature)',
        'star'      => 'Star',
        'heart'     => 'Heart',
        'flag'      => 'Flag',
        'building'  => 'Building',
        'camera'    => 'Camera',
        'compass'   => 'Compass',
    ];
}

/*
 |--------------------------------------------------------------------
 | READ HELPERS
 |--------------------------------------------------------------------
 */

// Returns the raw stored path (e.g. "assets/uploads/sitecontent/xyz.jpg")
// for a photo slot, or null if it hasn't been set by an admin yet.
function sitecontent_get_photo(mysqli $conn, string $key): ?string
{
    $stmt = $conn->prepare("SELECT image FROM site_photos WHERE photo_key = ? LIMIT 1");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (!empty($row['image'])) ? $row['image'] : null;
}

// All About sections, admin-ordered, each with its 'paragraphs' array
// pre-split so the template can just foreach() them into <p> tags.
function sitecontent_get_about_sections(mysqli $conn): array
{
    $sections = [];
    $result = $conn->query("SELECT * FROM about_sections ORDER BY sort_order ASC, section_id ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['paragraphs'] = array_values(array_filter(array_map('trim', preg_split('/\r?\n\r?\n/', (string) $row['body']))));
            $sections[] = $row;
        }
    }
    return $sections;
}
