<?php
/*
 |--------------------------------------------------------------------
 | PAGE WIDGETS — admin-customizable promo banner per tourism page
 |--------------------------------------------------------------------
 | One optional widget per tourism subpage (Products, Restaurants,
 | Churches, etc. — see kt_page_widget_pages() below): a colored promo
 | banner (photo + headline + two small highlight cards) with a photo
 | strip underneath that expands on hover. Admin picks a page from a
 | dropdown in admin/adminpagewidgets.php; only one live widget per
 | page_slug (enforced by the UNIQUE key), same as site_photos' one-
 | slot-per-key pattern in config/sitecontent.php.
 |
 | Goes through the same Admin/Super Admin approval queue as Partners
 | and Event Gallery (config/admin_requests.php): a plain Admin's
 | add/edit/archive is held for review, a Super Admin's applies
 | immediately.
 |
 | Include this AFTER config/dbmain.php (needs $conn).
 */

if (!function_exists('kt_page_widgets_ensure_schema')) {
    function kt_page_widgets_ensure_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $conn->query(
            "CREATE TABLE IF NOT EXISTS page_widgets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_slug VARCHAR(50) NOT NULL UNIQUE,
                bg_color VARCHAR(20) NOT NULL DEFAULT '#8b2e1a',
                main_image VARCHAR(255) NULL,
                headline VARCHAR(150) NOT NULL,
                subtext VARCHAR(300) NULL,
                card1_title VARCHAR(100) NULL,
                card1_text VARCHAR(300) NULL,
                card2_title VARCHAR(100) NULL,
                card2_text VARCHAR(300) NULL,
                gallery_main_image VARCHAR(255) NULL,
                gallery_heading VARCHAR(150) NULL,
                gallery_text VARCHAR(300) NULL,
                gallery_image_1 VARCHAR(255) NULL,
                gallery_image_2 VARCHAR(255) NULL,
                gallery_image_3 VARCHAR(255) NULL,
                gallery_image_4 VARCHAR(255) NULL,
                status ENUM('live','archived') NOT NULL DEFAULT 'live',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

// The tourism subpages a widget can be attached to — dropdown source in
// the admin form, and the validity check when applying a request.
if (!function_exists('kt_page_widget_pages')) {
    function kt_page_widget_pages(): array
    {
        return [
            'tourism'       => 'Tourism Hub',
            'accommodation' => 'Accommodation',
            'banks'         => 'Banks',
            'churches'      => 'Churches',
            'fiestas'       => 'Fiestas',
            'industry'      => 'Industry',
            'nature'        => 'Nature',
            'people'        => 'People',
            'products'      => 'Products',
            'resort'        => 'Resort',
            'restaurants'   => 'Restaurants',
            'services'      => 'Services',
        ];
    }
}

// The banner's eyebrow/headline/description always come from here, not an
// admin-typed field — same text already shown in that page's own hero
// section (<p class="t-eyebrow">, <h1 class="t-page-title">,
// <p class="t-page-sub">), kept as a single source of truth so the widget
// can't drift out of sync with the page it's on.
if (!function_exists('kt_page_widget_page_meta')) {
    function kt_page_widget_page_meta(): array
    {
        return [
            'tourism'       => ['eyebrow' => 'MALVAR, BATANGAS', 'title' => 'Tourism', 'description' => "Everything Malvar has to offer — food, nature, culture, and people — all in one place."],
            'accommodation' => ['eyebrow' => 'STAY IN MALVAR · MALVAR, BATANGAS', 'title' => 'Accommodation', 'description' => 'Hotels, inns, and homestays for travelers looking to stay a little longer in Malvar.'],
            'banks'         => ['eyebrow' => 'MONEY & FINANCE · MALVAR, BATANGAS', 'title' => 'Banks', 'description' => 'Banks, ATMs, and remittance centers travelers rely on while in Malvar.'],
            'churches'      => ['eyebrow' => 'SACRED SITES · MALVAR, BATANGAS', 'title' => 'Churches', 'description' => "Parishes, chapels, and shrines that anchor Malvar's faith and community life."],
            'fiestas'       => ['eyebrow' => 'CULTURE · MALVAR, BATANGAS', 'title' => 'Fiestas', 'description' => "Barangay fiestas, patron saint celebrations, and the vibrant street culture of Malvar."],
            'industry'      => ['eyebrow' => 'LOCAL DESTINATIONS · MALVAR, BATANGAS', 'title' => 'Industry Zone', 'description' => 'Discover the commercial and industrial districts that drive Malvar forward.'],
            'nature'        => ['eyebrow' => 'LOCAL DESTINATIONS · MALVAR, BATANGAS', 'title' => 'Nature', 'description' => "Parks, trails, and scenic views that showcase Malvar's natural beauty."],
            'people'        => ['eyebrow' => 'CULTURE · MALVAR, BATANGAS', 'title' => 'People of Malvar', 'description' => 'Artists, leaders, farmers, and community pillars who define the Malvareño spirit.'],
            'products'      => ['eyebrow' => 'FOOD · MALVAR, BATANGAS', 'title' => 'Products', 'description' => "Locally made goods, native delicacies, and fresh produce from Malvar's markets."],
            'resort'        => ['eyebrow' => 'LOCAL DESTINATIONS · MALVAR, BATANGAS', 'title' => 'Resorts', 'description' => "Poolside relaxation and weekend getaways — Malvar's best leisure destinations."],
            'restaurants'   => ['eyebrow' => 'FOOD · MALVAR, BATANGAS', 'title' => 'Restaurants', 'description' => 'From roadside eateries to family restaurants — find your next favorite meal.'],
            'services'      => ['eyebrow' => 'ESSENTIAL SERVICES · MALVAR, BATANGAS', 'title' => 'Other Services', 'description' => 'Hospitals, terminals, and everyday essentials to help a tourist get around Malvar with ease.'],
        ];
    }
}

// Shared by every image field on the widget — same validation/storage
// pattern as config/homepage_content.php's kt_homepage_handle_image_upload().
if (!function_exists('kt_page_widget_handle_image_upload')) {
    function kt_page_widget_handle_image_upload(string $fieldName): ?string
    {
        if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime = mime_content_type($_FILES[$fieldName]['tmp_name']);
        if (!isset($allowed[$mime])) {
            return null;
        }
        $dir = __DIR__ . '/../assets/uploads/pagewidgets';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = 'widget_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $dir . '/' . $filename)) {
            return null;
        }
        // Absolute from site root (not relative) — this image gets rendered
        // by kt_page_widget_render() on tourism pages nested two directories
        // deep (pages/tourism/*.php), where a relative "assets/..." path
        // would resolve wrong. Same convention as adminsitecontent.php's
        // upload handler.
        $base = defined('BASE_URL') ? BASE_URL : '/kultoura';
        return $base . '/assets/uploads/pagewidgets/' . $filename;
    }
}

// The live widget for one page, or null if the admin hasn't set one up yet.
if (!function_exists('kt_page_widget_get')) {
    function kt_page_widget_get(mysqli $conn, string $pageSlug): ?array
    {
        $stmt = $conn->prepare("SELECT * FROM page_widgets WHERE page_slug = ? AND status = 'live' LIMIT 1");
        $stmt->bind_param('s', $pageSlug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

// Renders the banner + expanding gallery strip for one page. Safe to call
// unconditionally — a no-op when that page has no live widget.
if (!function_exists('kt_page_widget_render')) {
    function kt_page_widget_render(mysqli $conn, string $pageSlug): void
    {
        kt_page_widget_render_from(kt_page_widget_get($conn, $pageSlug), $pageSlug);
    }
}

// Same rendering, but takes an already-fetched row (or null) instead of
// querying again — lets a page call kt_page_widget_get() once up top,
// decide whether to show its own hero title (skipped when a widget will
// show the same title/description below it), and pass that same result
// in here instead of hitting the DB twice.
if (!function_exists('kt_page_widget_render_from')) {
    function kt_page_widget_render_from(?array $w, string $pageSlug): void
    {
        if (!$w) return;
        $meta = kt_page_widget_page_meta()[$pageSlug] ?? ['eyebrow' => '', 'title' => $pageSlug, 'description' => ''];
        ?>
        <section class="pw-widget" style="background:<?php echo htmlspecialchars($w['bg_color']); ?>">
            <div class="pw-banner">
                <?php if (!empty($w['main_image'])): ?>
                    <div class="pw-banner-photo">
                        <img src="<?php echo htmlspecialchars($w['main_image']); ?>" alt="" loading="lazy">
                    </div>
                <?php endif; ?>
                <div class="pw-banner-main">
                    <h2 class="pw-banner-headline"><?php echo htmlspecialchars($meta['title']); ?></h2>
                    <?php if (!empty($meta['eyebrow'])): ?>
                        <p class="pw-banner-eyebrow"><?php echo htmlspecialchars($meta['eyebrow']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($meta['description'])): ?>
                        <p class="pw-banner-subtext"><?php echo htmlspecialchars($meta['description']); ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($w['card1_title']) || !empty($w['card2_title'])): ?>
                <div class="pw-banner-cards">
                    <?php if (!empty($w['card1_title'])): ?>
                        <div class="pw-banner-card">
                            <h3><?php echo htmlspecialchars($w['card1_title']); ?></h3>
                            <?php if (!empty($w['card1_text'])): ?><p><?php echo htmlspecialchars($w['card1_text']); ?></p><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($w['card2_title'])): ?>
                        <div class="pw-banner-card">
                            <h3><?php echo htmlspecialchars($w['card2_title']); ?></h3>
                            <?php if (!empty($w['card2_text'])): ?><p><?php echo htmlspecialchars($w['card2_text']); ?></p><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php
                $galleryImages = array_filter([$w['gallery_image_1'] ?? null, $w['gallery_image_2'] ?? null, $w['gallery_image_3'] ?? null, $w['gallery_image_4'] ?? null]);
            ?>
            <?php if (!empty($w['gallery_main_image']) || !empty($galleryImages)): ?>
            <div class="pw-gallery">
                <?php if (!empty($w['gallery_main_image'])): ?>
                    <div class="pw-gallery-main">
                        <img src="<?php echo htmlspecialchars($w['gallery_main_image']); ?>" alt="" loading="lazy">
                        <?php if (!empty($w['gallery_heading']) || !empty($w['gallery_text'])): ?>
                            <div class="pw-gallery-main-text">
                                <?php if (!empty($w['gallery_heading'])): ?><h3><?php echo htmlspecialchars($w['gallery_heading']); ?></h3><?php endif; ?>
                                <?php if (!empty($w['gallery_text'])): ?><p><?php echo htmlspecialchars($w['gallery_text']); ?></p><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php foreach ($galleryImages as $gImg): ?>
                    <div class="pw-gallery-strip">
                        <img src="<?php echo htmlspecialchars($gImg); ?>" alt="" loading="lazy">
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
        <?php
    }
}
