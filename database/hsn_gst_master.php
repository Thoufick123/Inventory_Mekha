<?php

use App\Core\DB;

function ensureHsnGstMaster(): void
{
    DB::query("CREATE TABLE IF NOT EXISTS `hsn_masters` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `concern_id` INT NULL,
        `code_type` ENUM('HSN', 'SAC') NOT NULL DEFAULT 'HSN',
        `hsn_code` VARCHAR(50) NOT NULL,
        `description` VARCHAR(255) NULL,
        `category` VARCHAR(100) NULL,
        `gst_rate` DECIMAL(5,2) NULL DEFAULT NULL,
        `rate_note` VARCHAR(500) NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_hsn_concern` (`concern_id`),
        INDEX `idx_hsn_code` (`hsn_code`),
        INDEX `idx_hsn_active_type` (`is_active`, `code_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = array_column(DB::fetchAll('DESCRIBE `hsn_masters`'), 'Field');
    if (!in_array('code_type', $columns, true)) {
        DB::query("ALTER TABLE `hsn_masters` ADD COLUMN `code_type` ENUM('HSN', 'SAC') NOT NULL DEFAULT 'HSN' AFTER `concern_id`");
    }
    if (!in_array('category', $columns, true)) {
        DB::query('ALTER TABLE `hsn_masters` ADD COLUMN `category` VARCHAR(100) NULL AFTER `description`');
    }
    if (!in_array('rate_note', $columns, true)) {
        DB::query('ALTER TABLE `hsn_masters` ADD COLUMN `rate_note` VARCHAR(500) NULL AFTER `gst_rate`');
    }
    if (!in_array('is_active', $columns, true)) {
        DB::query('ALTER TABLE `hsn_masters` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `rate_note`');
    }
    DB::query('ALTER TABLE `hsn_masters` MODIFY COLUMN `gst_rate` DECIMAL(5,2) NULL DEFAULT NULL');

    DB::query(
        "UPDATE `hsn_masters`
         SET `is_active` = 0, `gst_rate` = NULL,
             `rate_note` = 'Legacy placeholder, not a valid textile HSN. Select the applicable HSN instead.'
         WHERE `concern_id` IS NULL AND `hsn_code` = '107'
           AND `description` = 'Kids & Baby Garments and Dresses'"
    );

    foreach (hsnGstMasterRows() as $row) {
        $existing = DB::fetch(
            "SELECT `id` FROM `hsn_masters`
             WHERE `concern_id` IS NULL AND `code_type` = ? AND `hsn_code` = ?
             ORDER BY `id` ASC LIMIT 1",
            [$row['code_type'], $row['hsn_code']]
        );
        if ($existing) {
            DB::query(
                "UPDATE `hsn_masters`
                 SET `description` = ?, `category` = ?, `gst_rate` = ?, `rate_note` = ?, `is_active` = 1
                 WHERE `id` = ?",
                [$row['description'], $row['category'], $row['gst_rate'], $row['rate_note'], $existing['id']]
            );
            continue;
        }

        DB::query(
            "INSERT INTO `hsn_masters`
                (`concern_id`, `code_type`, `hsn_code`, `description`, `category`, `gst_rate`, `rate_note`)
             VALUES (NULL, ?, ?, ?, ?, ?, ?)",
            [$row['code_type'], $row['hsn_code'], $row['description'], $row['category'], $row['gst_rate'], $row['rate_note']]
        );
    }
}

function hsnGstMasterRows(): array
{
    $rows = [];
    $add = static function (
        string $type,
        string $code,
        string $category,
        string $description,
        ?float $rate,
        ?string $note = null
    ) use (&$rows): void {
        $rows[] = [
            'code_type' => $type,
            'hsn_code' => $code,
            'category' => $category,
            'description' => $description,
            'gst_rate' => $rate,
            'rate_note' => $note,
        ];
    };

    $textileRateNote = 'Standard rate shown; confirm the exact fiber composition and current GST notification before billing.';
    foreach ([
        ['5201', 'Raw cotton, not carded or combed'],
        ['5202', 'Cotton waste, including yarn waste and garnetted stock'],
        ['5203', 'Cotton, carded or combed'],
        ['5204', 'Cotton sewing thread'],
        ['5205', 'Cotton yarn, containing 85% or more cotton, not put up for retail sale'],
        ['5206', 'Cotton yarn, containing less than 85% cotton, not put up for retail sale'],
        ['5207', 'Cotton yarn put up for retail sale'],
        ['5208', 'Woven cotton fabrics, containing 85% or more cotton, weighing not more than 200 g/m2'],
        ['5209', 'Woven cotton fabrics, containing 85% or more cotton, weighing more than 200 g/m2'],
        ['5210', 'Woven cotton fabrics, containing less than 85% cotton, mixed mainly or solely with man-made fibres'],
        ['5211', 'Woven cotton fabrics, containing less than 85% cotton, weighing more than 200 g/m2'],
        ['5212', 'Other woven cotton fabrics'],
        ['5401', 'Sewing thread of man-made filaments'],
        ['5402', 'Synthetic filament yarn, including high-tenacity yarn'],
        ['5404', 'Synthetic monofilament and strip, of an apparent width not exceeding 5 mm'],
        ['5407', 'Woven fabrics of synthetic filament yarn'],
        ['5408', 'Woven fabrics of artificial filament yarn'],
        ['5501', 'Synthetic filament tow'],
        ['5502', 'Artificial filament tow'],
        ['5503', 'Synthetic staple fibres, not carded, combed or otherwise processed for spinning'],
        ['5504', 'Artificial staple fibres, not carded, combed or otherwise processed for spinning'],
        ['5508', 'Sewing thread of man-made staple fibres'],
        ['5509', 'Yarn of synthetic staple fibres, not put up for retail sale'],
        ['5510', 'Yarn of artificial staple fibres, not put up for retail sale'],
        ['5511', 'Yarn of man-made staple fibres put up for retail sale'],
        ['5512', 'Woven fabrics containing 85% or more synthetic staple fibres'],
        ['5513', 'Woven fabrics containing less than 85% synthetic staple fibres, mixed mainly with cotton'],
        ['5514', 'Woven fabrics containing less than 85% synthetic staple fibres, mixed mainly with man-made fibres'],
        ['5515', 'Other woven fabrics of synthetic staple fibres'],
        ['5516', 'Woven fabrics of artificial staple fibres'],
        ['5101', 'Wool, not carded or combed'],
        ['5106', 'Yarn of carded wool, not put up for retail sale'],
        ['5111', 'Woven fabrics of carded wool or fine animal hair'],
        ['5112', 'Woven fabrics of combed wool or fine animal hair'],
        ['5007', 'Woven fabrics of silk or silk waste'],
    ] as [$code, $description]) {
        $add('HSN', $code, 'Raw materials and fabrics', $description, 5.00, $textileRateNote);
    }

    foreach ([
        ['6101', "Men's or boys' overcoats and similar garments, knitted or crocheted"],
        ['6102', "Women's or girls' overcoats and similar garments, knitted or crocheted"],
        ['6103', "Men's or boys' suits, jackets, trousers and shorts, knitted or crocheted"],
        ['6104', "Women's or girls' suits, dresses, skirts, trousers and shorts, knitted or crocheted"],
        ['6105', "Men's or boys' shirts, knitted or crocheted"],
        ['6106', "Women's or girls' blouses and shirts, knitted or crocheted"],
        ['6109', 'T-shirts, singlets and other vests, knitted or crocheted'],
        ['6110', 'Jerseys, pullovers, cardigans and similar articles, knitted or crocheted'],
        ['6111', "Babies' garments and clothing accessories, knitted or crocheted"],
        ['6112', 'Track suits, ski suits and swimwear, knitted or crocheted'],
        ['6113', 'Garments made from knitted or crocheted coated fabrics'],
        ['6114', 'Other garments, knitted or crocheted'],
        ['6115', 'Hosiery, including socks and tights'],
        ['6116', 'Gloves, mittens and mitts, knitted or crocheted'],
        ['6117', 'Other made-up clothing accessories, knitted or crocheted'],
        ['6201', "Men's or boys' overcoats and similar garments, not knitted or crocheted"],
        ['6202', "Women's or girls' overcoats and similar garments, not knitted or crocheted"],
        ['6203', "Men's or boys' suits, jackets, trousers and shorts, not knitted or crocheted"],
        ['6204', "Women's or girls' suits, dresses, skirts, trousers and shorts, not knitted or crocheted"],
        ['6205', "Men's or boys' shirts, not knitted or crocheted"],
        ['6206', "Women's or girls' blouses and shirts, not knitted or crocheted"],
        ['6207', "Men's or boys' undergarments and nightwear"],
        ['6208', "Women's or girls' undergarments and nightwear"],
        ['6209', "Babies' garments and clothing accessories, not knitted or crocheted"],
        ['6210', 'Garments made from fabrics of headings 5602, 5603, 5903, 5906 or 5907'],
        ['6211', 'Track suits, ski suits and swimwear, not knitted or crocheted'],
        ['6212', 'Brassieres, girdles, corsets, braces, suspenders and similar articles'],
        ['6214', 'Shawls, scarves, mufflers, mantillas, veils and similar articles'],
        ['6217', 'Other made-up clothing accessories'],
    ] as [$code, $description]) {
        $add(
            'HSN',
            $code,
            'Garments',
            $description,
            5.00,
            'Standard garment rate 5% (12% if piece value > ₹1000).'
        );
    }

    foreach ([
        ['6301', 'Blankets and travelling rugs'],
        ['6302', 'Bed linen, table linen, toilet linen and kitchen linen, including towels and bedsheets'],
        ['6303', 'Curtains, drapes, interior blinds and bed valances'],
        ['6304', 'Other furnishing articles, including furnishing textiles'],
        ['6305', 'Sacks and bags used for packing goods, of textile materials'],
        ['6307', 'Other made-up textile articles, including certain labels and textile accessories'],
    ] as [$code, $description]) {
        $add('HSN', $code, 'Home and made-up textiles', $description, 5.00, 'Standard rate 5% for made-up textile articles.');
    }

    foreach ([
        ['5607', 'Twine, cordage, ropes and cables, whether or not plaited'],
        ['5804', 'Tulles, lace and embroidery lace in the piece, in strips or motifs'],
        ['5806', 'Narrow woven fabrics, including certain woven labels'],
        ['5807', 'Woven labels, badges and similar textile articles'],
        ['5808', 'Braids in the piece; ornamental trimmings in the piece'],
        ['5810', 'Embroidery in the piece, in strips or in motifs'],
    ] as [$code, $description]) {
        $add('HSN', $code, 'Garment accessories and manufacturing materials', $description, 5.00, 'Standard textile accessories rate 5%.');
    }

    foreach ([
        ['9606', 'Buttons, press-fasteners, snap-fasteners and parts thereof'],
        ['9607', 'Slide fasteners (zippers) and parts thereof'],
    ] as [$code, $description]) {
        $add('HSN', $code, 'Garment accessories and manufacturing materials', $description, 12.00, 'Standard rate 12% for buttons and slide fasteners.');
    }

    foreach ([
        ['3923', 'Articles for the conveyance or packing of goods, of plastics'],
        ['3919', 'Self-adhesive plates, sheets, film, foil, tape and other flat shapes, of plastics'],
    ] as [$code, $description]) {
        $add('HSN', $code, 'Packaging materials', $description, 18.00, 'Standard rate 18% for plastic packaging materials.');
    }

    foreach ([
        ['4819', 'Cartons, boxes, cases, bags and other packing containers of paper or paperboard'],
        ['4820', 'Registers, notebooks, folders, labels and similar articles of paper or paperboard'],
        ['4821', 'Paper or paperboard labels of all kinds, printed or not'],
    ] as [$code, $description]) {
        $add('HSN', $code, 'Packaging materials', $description, 12.00, 'Standard rate 12% for paper boxes and labels.');
    }

    foreach ([
        ['8444', 'Machines for extruding, drawing, texturing or cutting man-made textile materials'],
        ['8445', 'Machines for preparing textile fibres and spinning, doubling or twisting textile yarns'],
        ['8446', 'Weaving machines (looms)'],
        ['8447', 'Knitting machines, stitch-bonding machines and machines for making net or lace'],
        ['8448', 'Auxiliary machinery and parts for machines of headings 8444 to 8447'],
        ['8451', 'Machinery for washing, bleaching, dyeing, dressing, finishing, coating or impregnating textile yarns or fabrics'],
        ['8452', 'Sewing machines and furniture, bases and covers specially designed for sewing machines'],
    ] as [$code, $description]) {
        $add('HSN', $code, 'Machinery and parts', $description, 18.00, 'Standard rate shown; confirm the machine or part classification and any applicable exemption before billing.');
    }

    $add('SAC', '9987', 'Maintenance and repair services', 'Maintenance, repair and installation services (except construction)', 18.00, 'Standard rate shown; verify the precise service, recipient and any applicable exemption.');
    $add('SAC', '998713', 'Maintenance and repair services', 'Maintenance and repair services of commercial and industrial machinery', 18.00, 'Standard rate shown; verify the precise service, recipient and any applicable exemption.');
    $add('SAC', '998719', 'Maintenance and repair services', 'Maintenance and repair services of other machinery and equipment', 18.00, 'Standard rate shown; verify the precise service, recipient and any applicable exemption.');
    $add('SAC', '9988', 'Job work and manufacturing services', 'Manufacturing services on physical inputs (goods) owned by others', null, 'Job-work rate depends on the goods and applicable service-rate conditions; verify before billing.');
    $add('SAC', '998821', 'Job work and manufacturing services', 'Textile and apparel job work, including cutting, stitching, embroidery and finishing', null, 'Rate depends on the goods and applicable job-work conditions; verify before billing.');

    return $rows;
}
